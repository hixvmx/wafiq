<?php

/**
 * Builds the Picalica package: build/wafiq-<version>.zip
 *
 *   php scripts/build-release.php 1.0.0
 *
 * What goes in: the app with production dependencies (vendor), compiled assets (public/build),
 * the source (buyers may customise it) and a production .env.example.
 * What stays out: the SaaS edition (app/Saas, routes/saas.php, resources/js/Pages/Saas),
 * development files, tests, secrets (.env), local data (storage contents, SQLite files).
 * This script itself is not in the package.
 */
$root = dirname(__DIR__);
$version = $argv[1] ?? null;

if (! $version || ! preg_match('/^\d+\.\d+\.\d+$/', $version)) {
    fwrite(STDERR, "Usage: php scripts/build-release.php <version, e.g. 1.0.0>\n");
    exit(1);
}

$build = "{$root}/build";
$target = "{$build}/wafiq";
$zipPath = "{$build}/wafiq-{$version}.zip";

/** Paths (relative to the project) never copied. Directories end with "/". */
$exclude = [
    // The SaaS edition is ours alone (see idea-wafiq.md, section 6).
    'app/Saas/', 'routes/saas.php', 'resources/js/Pages/Saas/',
    // Development only
    '.git/', '.github/', 'node_modules/', 'vendor/', 'tests/', 'build/', 'scripts/', 'deploy/',
    '.env', '.env.example', '.env.testing', '.phpunit.result.cache', 'phpunit.xml', '.editorconfig',
    'eslint.config.js', '.prettierrc', 'jsconfig.json', 'idea-wafiq.md', 'public/hot', 'public/storage',
    '.claude/', '.vscode/', '.idea/',
    // Our sales material, not part of the product
    'marketing/', 'website/',
];

/** Folders copied empty (only their .gitignore): runtime data never ships. */
$emptied = ['storage/', 'bootstrap/cache/'];

function step(string $message): void
{
    echo "\n▸ {$message}\n";
}

function run(string $command, string $cwd): void
{
    echo "  $ {$command}\n";
    $process = proc_open($command, [STDIN, STDOUT, STDERR], $pipes, $cwd);
    if (proc_close($process) !== 0) {
        fwrite(STDERR, "Failed: {$command}\n");
        exit(1);
    }
}

function removeDirectory(string $path): void
{
    if (! is_dir($path)) {
        return;
    }
    $items = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($items as $item) {
        $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    }
    rmdir($path);
}

step('Building assets');
run('npm run build', $root);

step('Copying files to build/wafiq');
removeDirectory($target);
@unlink($zipPath);
mkdir($target, 0777, true);

$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);
$copied = 0;

foreach ($files as $file) {
    $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
    $path = $file->isDir() ? "{$relative}/" : $relative;

    foreach ($exclude as $skip) {
        if ($path === $skip || str_starts_with($path, $skip)) {
            continue 2;
        }
    }
    foreach ($emptied as $folder) {
        if (str_starts_with($path, $folder) && ! $file->isDir() && basename($relative) !== '.gitignore') {
            continue 2;
        }
    }
    if (str_ends_with($relative, '.sqlite') || str_ends_with($relative, '.log')) {
        continue;
    }

    $destination = "{$target}/{$relative}";
    if ($file->isDir()) {
        @mkdir($destination, 0777, true);
    } else {
        @mkdir(dirname($destination), 0777, true);
        copy($file->getPathname(), $destination);
        $copied++;
    }
}
echo "  {$copied} files\n";

copy("{$root}/deploy/env.example", "{$target}/.env.example");

step('Installing production dependencies (composer install --no-dev)');
// --no-scripts: nothing may boot the app here (it could write files such as .env into the
// package). Laravel builds its package list itself on the buyer's first request.
run('composer install --no-dev --optimize-autoloader --no-interaction --no-progress --no-scripts', $target);
foreach (glob("{$target}/bootstrap/cache/*.php") as $cached) {
    unlink($cached);
}

step('Checking the package');
$problems = [];
foreach (['app/Saas', 'routes/saas.php', 'resources/js/Pages/Saas', '.env', 'tests', 'node_modules', 'marketing', 'website'] as $mustNotExist) {
    if (file_exists("{$target}/{$mustNotExist}")) {
        $problems[] = "{$mustNotExist} is in the package";
    }
}
foreach (['public/build/manifest.json', 'vendor/autoload.php', '.env.example', 'resources/fonts/pdf/ibmplexsansarabic.php', 'resources/fonts/pdf/OFL.txt', 'docs/README.md', 'LICENSE.txt', 'CREDITS.md', 'CHANGELOG.md'] as $mustExist) {
    if (! file_exists("{$target}/{$mustExist}")) {
        $problems[] = "{$mustExist} is missing";
    }
}
if (! str_contains((string) file_get_contents("{$target}/.env.example"), 'APP_EDITION=self_hosted')) {
    $problems[] = '.env.example does not force APP_EDITION=self_hosted';
}
if (is_dir("{$target}/vendor/phpunit")) {
    $problems[] = 'development packages (phpunit) are in vendor';
}
if ($problems) {
    fwrite(STDERR, '  ✗ '.implode("\n  ✗ ", $problems)."\n");
    exit(1);
}
echo "  ✓ no SaaS code, no secrets, no dev packages; assets, vendor and fonts present\n";

step("Zipping to build/wafiq-{$version}.zip");
$zip = new ZipArchive;
$zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
$items = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($target, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);
foreach ($items as $item) {
    $name = 'wafiq/'.str_replace('\\', '/', substr($item->getPathname(), strlen($target) + 1));
    $item->isDir() ? $zip->addEmptyDir($name) : $zip->addFile($item->getPathname(), $name);
}
$zip->close();

printf("\n✓ %s (%.1f MB)\n", $zipPath, filesize($zipPath) / 1048576);
