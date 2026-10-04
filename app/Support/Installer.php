<?php

namespace App\Support;

use App\Models\Company;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Whether this copy has been set up by the web installer (Picalica edition).
 *
 * Installed = a marker file in storage, or (for copies set up before the installer existed,
 * e.g. with `php artisan wafiq:setup`) a database that already has a company.
 * The check never needs the database once the marker is there.
 */
final class Installer
{
    private static ?bool $installed = null;

    public static function markerPath(): string
    {
        return storage_path('app/installed');
    }

    public static function isInstalled(): bool
    {
        if (config('wafiq.force_installed') !== null) {
            return (bool) config('wafiq.force_installed'); // tests
        }

        if (self::$installed !== null) {
            return self::$installed;
        }

        if (file_exists(self::markerPath())) {
            return self::$installed = true;
        }

        try {
            if (Schema::hasTable('companies') && Company::query()->exists()) {
                self::markInstalled();

                return true;
            }
        } catch (Throwable) {
            // No database configured yet: not installed.
        }

        return self::$installed = false;
    }

    public static function markInstalled(): void
    {
        if (config('wafiq.force_installed') === null) { // tests that force the state never write the file
            @file_put_contents(self::markerPath(), now()->toIso8601String());
        }
        self::$installed = true;
    }

    public static function forget(): void
    {
        self::$installed = null;
    }

    /**
     * What the server must have. Each entry: [label, ok, detail].
     *
     * @return list<array{label: string, ok: bool, detail: string}>
     */
    public static function requirements(): array
    {
        $checks = [
            ['PHP 8.2+', version_compare(PHP_VERSION, '8.2.0', '>='), PHP_VERSION],
        ];

        foreach (['pdo_mysql', 'mbstring', 'gd', 'intl', 'fileinfo', 'openssl', 'tokenizer', 'xml', 'ctype', 'curl', 'zip'] as $extension) {
            $checks[] = ["PHP {$extension}", extension_loaded($extension), extension_loaded($extension) ? '' : __('ui.install.missing')];
        }

        foreach (['storage', 'storage/app', 'storage/framework', 'storage/logs', 'bootstrap/cache'] as $folder) {
            $checks[] = [$folder, is_writable(base_path($folder)), is_writable(base_path($folder)) ? '' : __('ui.install.not_writable')];
        }

        $env = EnvFile::path();
        $envWritable = file_exists($env) ? is_writable($env) : is_writable(dirname($env));
        $checks[] = ['.env', $envWritable, $envWritable ? '' : __('ui.install.not_writable')];

        return array_map(fn (array $check) => ['label' => $check[0], 'ok' => $check[1], 'detail' => $check[2]], $checks);
    }

    public static function requirementsMet(): bool
    {
        return collect(self::requirements())->every(fn (array $check) => $check['ok']);
    }

    /** The one cron line the buyer adds in cPanel (scheduler + queued emails). */
    public static function cronLine(): string
    {
        return '* * * * * cd '.base_path().' && php artisan schedule:run >> /dev/null 2>&1';
    }
}
