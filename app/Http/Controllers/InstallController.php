<?php

namespace App\Http\Controllers;

use App\Actions\CreateCompany;
use App\Models\User;
use App\Support\EnvFile;
use App\Support\Installer;
use App\Support\MailSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use PDO;
use Throwable;

/**
 * The web installer (Picalica edition): requirements → database → company & owner →
 * email test → done (cron line, then straight into the app).
 * Progress lives in the session; the routes disappear once the copy is installed.
 */
class InstallController extends Controller
{
    private const STEPS = ['requirements', 'database', 'company', 'mail', 'done'];

    public function show(Request $request): Response|RedirectResponse
    {
        // A cached config (after `php artisan optimize`) ignores .env, so nothing the installer
        // saves would take effect. Clear it and reload with the real settings.
        if (app()->configurationIsCached()) {
            Artisan::call('optimize:clear');

            return redirect('/install');
        }

        $step = $this->checkedStep($request);

        return Inertia::render('Install/Index', [
            'step' => $step,
            'steps' => self::STEPS,
            'requirements' => Installer::requirements(),
            'requirementsMet' => Installer::requirementsMet(),
            'currencies' => collect(config('currencies'))->keys()->map(fn ($code) => ['code' => $code, 'name' => __("currencies.{$code}")]),
            'ownerEmail' => $request->session()->get('install.owner_email'),
            'mailSkipped' => (bool) $request->session()->get('install.mail_skipped'),
            'cron' => Installer::cronLine(),
            'rescue' => 'php artisan wafiq:login-link '.($request->session()->get('install.owner_email') ?? 'you@example.com'),
            'defaults' => ['db_host' => '127.0.0.1', 'db_port' => '3306', 'mail_port' => '587', 'app_url' => $request->root()],
        ]);
    }

    public function requirements(Request $request): RedirectResponse
    {
        if (! Installer::requirementsMet()) {
            return back()->with('error', __('ui.install.requirements_missing'));
        }

        return $this->goTo($request, 'database');
    }

    public function database(Request $request): RedirectResponse
    {
        $this->requireStep($request, 'database');

        $data = $request->validate([
            'host' => ['required', 'string', 'max:255'],
            'port' => ['required', 'integer', 'between:1,65535'],
            'database' => ['required', 'string', 'max:64'],
            'username' => ['required', 'string', 'max:64'],
            'password' => ['nullable', 'string', 'max:255'],
        ]);

        // Try the connection first, with a clear message, before touching .env.
        try {
            new PDO("mysql:host={$data['host']};port={$data['port']};dbname={$data['database']};charset=utf8mb4", $data['username'], $data['password'] ?? '', [
                PDO::ATTR_TIMEOUT => 5,
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ]);
        } catch (Throwable $e) {
            throw ValidationException::withMessages(['database' => __('ui.install.db_failed', ['error' => Str::limit($e->getMessage(), 200)])]);
        }

        config([
            'database.default' => 'mysql',
            'database.connections.mysql' => [...config('database.connections.mysql'), ...[
                'host' => $data['host'], 'port' => $data['port'], 'database' => $data['database'],
                'username' => $data['username'], 'password' => $data['password'] ?? '',
            ]],
        ]);
        DB::purge('mysql');

        // Tables that Laravel's migration log doesn't know about belong to something else (another
        // app, or a half-finished earlier attempt): stop with a clear message rather than fail midway.
        // getTables() lists every database on the server unless told which one.
        $tables = array_column(DB::connection('mysql')->getSchemaBuilder()->getTables($data['database']), 'name');
        $migrated = in_array('migrations', $tables, true) ? DB::connection('mysql')->table('migrations')->count() : 0;

        if ($tables && $migrated === 0 && array_diff($tables, ['migrations'])) {
            throw ValidationException::withMessages(['database' => __('ui.install.db_not_empty', ['count' => count($tables)])]);
        }

        // One run at a time: a double click must not start two migrations in parallel.
        $lock = Cache::lock('wafiq-install-migrate', 120);
        if (! $lock->get()) {
            throw ValidationException::withMessages(['database' => __('ui.install.migrating')]);
        }

        try {
            Artisan::call('migrate', ['--force' => true]);
        } catch (Throwable $e) {
            throw ValidationException::withMessages(['database' => __('ui.install.migrate_failed', ['error' => Str::limit($e->getMessage(), 200)])]);
        } finally {
            $lock->release();
        }

        // Saved last, once everything worked: `php artisan serve` restarts as soon as .env changes,
        // which would cut off this request if it still had work to do.
        EnvFile::set([
            'APP_URL' => $request->root(),
            'DB_CONNECTION' => 'mysql',
            'DB_HOST' => $data['host'],
            'DB_PORT' => $data['port'],
            'DB_DATABASE' => $data['database'],
            'DB_USERNAME' => $data['username'],
            'DB_PASSWORD' => $data['password'] ?? '',
        ]);

        return $this->goTo($request, 'company');
    }

    public function company(Request $request, CreateCompany $create): RedirectResponse
    {
        $this->requireStep($request, 'company');

        $data = $request->validate([
            'company' => ['required', 'string', 'max:255'],
            'currency' => ['required', Rule::in(array_keys(config('currencies')))],
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255'],
        ]);

        [, $owner] = $create->handle($data);

        // The company now exists, which counts as "installed": keep the installer open to finish.
        $request->session()->put([
            'install.finishing' => true,
            'install.owner_id' => $owner->id,
            'install.owner_email' => $owner->email,
        ]);

        return $this->goTo($request, 'mail');
    }

    /** Saves the mail settings and sends a test message to the owner. */
    public function mail(Request $request): RedirectResponse
    {
        $this->requireStep($request, 'mail');

        $data = $request->validate(Arr::except(MailSettings::rules($request), 'from_name'));
        $env = MailSettings::env($data);
        MailSettings::apply($env);

        $error = MailSettings::sendTest($request->session()->get('install.owner_email'), __('ui.install.test_mail_subject'), __('ui.install.test_mail_body'));
        if ($error !== null) {
            throw ValidationException::withMessages(['mailer' => __('ui.install.mail_failed', ['error' => $error])]);
        }

        $request->session()->forget('install.mail_skipped');
        EnvFile::set($env); // only settings that just sent a message are kept; last, see database()

        return $this->goTo($request, 'done');
    }

    /** No working mail yet: carry on, the owner is logged in at the end and can fix it later. */
    public function skipMail(Request $request): RedirectResponse
    {
        $this->requireStep($request, 'mail');
        EnvFile::set(['MAIL_MAILER' => 'log']);
        $request->session()->put('install.mail_skipped', true);

        return $this->goTo($request, 'done');
    }

    /** Logs the owner in and closes the installer for good. */
    public function finish(Request $request): RedirectResponse
    {
        $this->requireStep($request, 'done');

        $owner = User::findOrFail($request->session()->get('install.owner_id'));
        Installer::markInstalled();
        Artisan::call('config:clear');

        $request->session()->forget(['install.step', 'install.finishing', 'install.owner_id', 'install.owner_email', 'install.mail_skipped']);
        Auth::login($owner);
        $request->session()->regenerate();

        return redirect()->route('home')->with('success', __('ui.install.welcome'));
    }

    /** Forgets the installer's progress and starts again from the first step. */
    public function restart(Request $request): RedirectResponse
    {
        $request->session()->forget(['install.step', 'install.finishing', 'install.owner_id', 'install.owner_email', 'install.mail_skipped']);

        return redirect('/install');
    }

    /**
     * The step saved in the session, moved back if what it relies on is gone (the database was
     * wiped, the owner deleted…): the session remembers progress, the server has the last word.
     */
    private function checkedStep(Request $request): string
    {
        $step = $request->session()->get('install.step', 'requirements');
        $position = array_search($step, self::STEPS, true);

        if ($position >= array_search('company', self::STEPS, true) && ! $this->databaseReady()) {
            $step = 'database';
        } elseif ($position >= array_search('mail', self::STEPS, true) && ! User::whereKey($request->session()->get('install.owner_id'))->exists()) {
            $step = 'company';
        }

        if ($step !== $request->session()->get('install.step', 'requirements')) {
            $request->session()->put('install.step', $step);
        }

        return $step;
    }

    private function databaseReady(): bool
    {
        try {
            return Schema::hasTable('companies') && Schema::hasTable('users');
        } catch (Throwable) {
            return false;
        }
    }

    private function goTo(Request $request, string $step): RedirectResponse
    {
        $request->session()->put('install.step', $step);

        return redirect('/install');
    }

    /** Steps happen in order: posting a later step's form directly isn't allowed. */
    private function requireStep(Request $request, string $step): void
    {
        abort_unless($request->session()->get('install.step', 'requirements') === $step, 409);
    }
}
