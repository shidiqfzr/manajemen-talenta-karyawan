<?php

/**
 * Prepares a development .env for `composer setup` (CONTRIBUTING.md). Plain PHP: it runs before anything else is
 * installed and needs no framework.
 *
 *   1. Copies .env.example to .env when .env is missing.
 *   2. Refuses (exit 1) when any uncommented APP_ENV line says production: setup is for development machines only, and
 *      it must never install, migrate or re-seed on a server (DOMAIN.md §3.7). It fails closed: it reads the same
 *      spellings the dotenv parser accepts (optional `export`, quotes, spaces, a trailing comment, a UTF-8 BOM).
 *   3. Writes an application key only when no APP_KEY line has a value. Replacing a key would invalidate encrypted
 *      sessions and data.
 *   4. Creates database/database.sqlite when the default SQLite file is the database in use.
 *
 * Safe to run again: a second run changes nothing.
 *
 * Usage: php scripts/prepare-env.php [projectRoot]
 */
$root = rtrim($argv[1] ?? dirname(__DIR__), '/\\');
$envPath = $root.DIRECTORY_SEPARATOR.'.env';
$examplePath = $root.DIRECTORY_SEPARATOR.'.env.example';
$bom = "\xEF\xBB\xBF";

/**
 * Value of one dotenv line, without quotes or a trailing comment.
 */
function cleanValue(string $raw): string
{
    $value = trim($raw);

    if ($value !== '' && ($value[0] === '"' || $value[0] === "'")) {
        $end = strpos($value, $value[0], 1);

        return $end === false ? trim(substr($value, 1)) : substr($value, 1, $end - 1);
    }

    return trim((string) preg_replace('/\s+#.*$/', '', $value));
}

/**
 * Values of every uncommented `[export ]KEY = value` line, in file order. dotenv lets the last line win.
 *
 * @return list<string>
 */
function envValues(string $env, string $key, string $flags = ''): array
{
    preg_match_all('/^[ \t]*(?:export[ \t]+)?'.preg_quote($key, '/').'[ \t]*=([^\r\n]*)/m'.$flags, $env, $matches);

    return array_map('cleanValue', $matches[1]);
}

/**
 * The value dotenv would use (the last line), or '' when the key is absent or empty.
 */
function effectiveValue(string $env, string $key): string
{
    $values = envValues($env, $key);

    return $values === [] ? '' : end($values);
}

if (! file_exists($envPath)) {
    if (! file_exists($examplePath)) {
        fwrite(STDERR, "Cannot prepare .env: .env.example was not found in {$root}.\n");
        exit(1);
    }

    copy($examplePath, $envPath);
    echo "Created .env from .env.example\n";
}

$original = (string) file_get_contents($envPath);
$hasBom = str_starts_with($original, $bom);
$env = $hasBom ? substr($original, strlen($bom)) : $original;

foreach (envValues($env, 'APP_ENV', 'i') as $value) {
    if (strtolower($value) === 'production') {
        fwrite(STDERR, "Refusing to run: .env has APP_ENV=production. composer setup is for development machines only.\n");
        exit(1);
    }
}

$hasKey = array_filter(envValues($env, 'APP_KEY'), fn (string $value): bool => $value !== '') !== [];

if (! $hasKey) {
    $key = 'base64:'.base64_encode(random_bytes(32));

    if (envValues($env, 'APP_KEY') === []) {
        $eol = str_contains($env, "\r\n") ? "\r\n" : "\n";
        $env = rtrim($env, "\r\n").$eol.'APP_KEY='.$key.$eol;
    } else {
        $env = (string) preg_replace_callback(
            '/^([ \t]*(?:export[ \t]+)?APP_KEY[ \t]*=)[^\r\n]*/m',
            fn (array $match): string => $match[1].$key,
            $env,
        );
    }

    file_put_contents($envPath, ($hasBom ? $bom : '').$env);
    echo "Generated an application key\n";
}

$connection = strtolower(effectiveValue($env, 'DB_CONNECTION') ?: 'sqlite');
$sqlitePath = $root.DIRECTORY_SEPARATOR.'database'.DIRECTORY_SEPARATOR.'database.sqlite';

if ($connection === 'sqlite' && effectiveValue($env, 'DB_DATABASE') === '' && ! file_exists($sqlitePath)) {
    if (! is_dir(dirname($sqlitePath))) {
        mkdir(dirname($sqlitePath), 0777, true);
    }

    touch($sqlitePath);
    echo "Created database/database.sqlite\n";
}

exit(0);
