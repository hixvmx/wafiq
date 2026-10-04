<?php

namespace App\Providers;

use App\Enums\Ability;
use App\Models\User;
use App\Services\CurrentCompany;
use App\Support\Edition;
use App\Support\EnvFile;
use App\Support\Installer;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Throwable;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->ensureAppKey();
        $this->useFilesUntilInstalled();

        // One per request (and reset between queued jobs).
        $this->app->scoped(CurrentCompany::class);
        $this->app->alias(CurrentCompany::class, 'company.current'); // short name for Blade views
    }

    /**
     * A freshly uploaded copy has no APP_KEY, and without one every page fails (cookies are
     * encrypted). Generate it on the first visit so the web installer can open.
     */
    private function ensureAppKey(): void
    {
        // Web requests only: a console command (e.g. while building the release package) must
        // never write a key, or every buyer would get the same one.
        if (config('app.key') || $this->app->runningInConsole()) {
            return;
        }

        $key = 'base64:'.base64_encode(random_bytes(32));

        try {
            EnvFile::set(['APP_KEY' => $key]);
        } catch (Throwable) {
            // .env not writable: the installer's requirements screen says so.
        }

        config(['app.key' => $key]);
    }

    /**
     * Before installation the database may be empty or missing, so sessions and cache can't
     * live there, whatever .env says (e.g. SESSION_DRIVER=database): the installer must open.
     * Only the marker file is checked here, never the database.
     */
    private function useFilesUntilInstalled(): void
    {
        if ($this->app->runningUnitTests() || Edition::isSaas() || file_exists(Installer::markerPath())) {
            return;
        }

        config(['session.driver' => 'file', 'cache.default' => 'file']);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // $user->can('manage_team'), @can, $this->authorize()… based on the role in the current company.
        foreach (Ability::cases() as $ability) {
            Gate::define($ability->value, fn (User $user) => (bool) $user->currentRole()?->allows($ability));
        }
    }
}
