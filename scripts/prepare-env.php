<?php

/**
 * Prepares a development .env for `composer setup` (CONTRIBUTING.md). Plain PHP: it runs before anything else is
 * installed and needs no framework.
 *
 *   1. Copies .env.example to .env when .env is missing.
 *   2. Refuses (exit 1) when .env says APP_ENV=production: setup is for development machines only, and it must never
 *      migrate or re-seed a server database (DOMAIN.md §3.7).
 *   3. Writes an application key only when APP_KEY is empty. Replacing a key would invalidate encrypted sessions and data.
 *   4. Creates database/database.sqlite when the default SQLite file is the database in use.
 *
 * Safe to run again: a second run changes nothing.
 *
 * Usage: php scripts/prepare-env.php [projectRoot]
 */
$root = rtrim($argv[1] ?? dirname(__DIR__), '/\\');
$envPath = $root.DIRECTORY_SEPARATOR.'.env';
$examplePath = $root.DIRECTORY_SEPARATOR.'.env.example';

/**
 * Value of an uncommented KEY=value line, without surrounding quotes; null when the line is absent.
 */
function envValue(string $env, string $key): ?string
{
    if (preg_match('/^'.preg_quote($key, '/').'=([^\r\n]*)/m', $env, $match) !== 1) {
        return null;
    }

    return trim(trim($match[1]), "\"'");
}

if (! file_exists($envPath)) {
    if (! file_exists($examplePath)) {
        fwrite(STDERR, "Cannot prepare .env: .env.example was not found in {$root}.\n");
        exit(1);
    }

    copy($examplePath, $envPath);
    echo "Created .env from .env.example\n";
}

$env = (string) file_get_contents($envPath);

if (preg_match('/^\s*APP_ENV\s*=\s*["\']?production["\']?\s*$/mi', $env) === 1) {
    fwrite(STDERR, "Refusing to run: .env has APP_ENV=production. composer setup is for development machines only.\n");
    exit(1);
}

if (envValue($env, 'APP_KEY') === null || envValue($env, 'APP_KEY') === '') {
    $key = 'base64:'.base64_encode(random_bytes(32));

    if (envValue($env, 'APP_KEY') === null) {
        $env = rtrim($env)."\nAPP_KEY={$key}\n";
    } else {
        $env = preg_replace('/^APP_KEY=[^\r\n]*/m', 'APP_KEY='.$key, $env, 1);
    }

    file_put_contents($envPath, $env);
    echo "Generated an application key\n";
}

$connection = strtolower(envValue($env, 'DB_CONNECTION') ?? 'sqlite');
$sqlitePath = $root.DIRECTORY_SEPARATOR.'database'.DIRECTORY_SEPARATOR.'database.sqlite';

if ($connection === 'sqlite' && envValue($env, 'DB_DATABASE') === null && ! file_exists($sqlitePath)) {
    if (! is_dir(dirname($sqlitePath))) {
        mkdir(dirname($sqlitePath), 0777, true);
    }

    touch($sqlitePath);
    echo "Created database/database.sqlite\n";
}

exit(0);
