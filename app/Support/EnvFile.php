<?php

namespace App\Support;

use RuntimeException;

/** Reads and updates the .env file (used by the web installer). */
final class EnvFile
{
    public static function path(): string
    {
        return app()->environmentFilePath();
    }

    /** Creates .env from .env.example when the buyer hasn't made one. */
    public static function ensureExists(): void
    {
        $path = self::path();

        if (! file_exists($path)) {
            $example = dirname($path).DIRECTORY_SEPARATOR.'.env.example';
            if (! @copy($example, $path)) {
                throw new RuntimeException("Could not create {$path}");
            }
        }
    }

    /**
     * Sets keys, replacing existing lines or appending new ones.
     *
     * @param  array<string, string|int|bool|null>  $values
     */
    public static function set(array $values): void
    {
        self::ensureExists();
        $path = self::path();
        $content = (string) file_get_contents($path);

        foreach ($values as $key => $value) {
            $line = $key.'='.self::quote($value);
            $pattern = '/^'.preg_quote($key, '/').'=.*$/m';
            $content = preg_match($pattern, $content)
                ? preg_replace($pattern, str_replace(['\\', '$'], ['\\\\', '\\$'], $line), $content)
                : rtrim($content)."\n".$line."\n";
        }

        if (@file_put_contents($path, $content) === false) {
            throw new RuntimeException("Could not write {$path}");
        }
    }

    private static function quote(string|int|bool|null $value): string
    {
        if ($value === null) {
            return 'null';
        }
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        $value = (string) $value;

        // Quote anything with spaces, #, quotes or "=", escaping inner quotes and backslashes.
        return preg_match('/[\s#"\'=\\\\$]/', $value) || $value === ''
            ? '"'.str_replace(['\\', '"', '$'], ['\\\\', '\\"', '\\$'], $value).'"'
            : $value;
    }
}
