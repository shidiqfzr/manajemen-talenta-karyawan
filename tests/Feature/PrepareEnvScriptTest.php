<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Process\Process;
use Tests\TestCase;

/**
 * SUT: scripts/prepare-env.php, the step of `composer setup` that prepares a development .env.
 * It must never touch a production .env, never replace an existing key and be safe to re-run (CONTRIBUTING.md).
 */
class PrepareEnvScriptTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'prepare-env-'.bin2hex(random_bytes(6));
        mkdir($this->dir.DIRECTORY_SEPARATOR.'database', 0777, true);
        copy(base_path('.env.example'), $this->path('.env.example'));
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->dir);

        parent::tearDown();
    }

    private function path(string $relative): string
    {
        return $this->dir.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relative);
    }

    private function prepare(): Process
    {
        $process = new Process([PHP_BINARY, base_path('scripts'.DIRECTORY_SEPARATOR.'prepare-env.php'), $this->dir]);
        $process->run();

        return $process;
    }

    /**
     * @return array<string, array{string}>
     */
    public static function productionSpellings(): array
    {
        return [
            'plain' => ['APP_ENV=production'],
            'double quoted' => ['APP_ENV="production"'],
            'single quoted' => ["APP_ENV='production'"],
            'spaces around the sign' => ['APP_ENV = production'],
            'upper case' => ['APP_ENV=PRODUCTION'],
        ];
    }

    public function test_fresh_folder_gets_env_key_and_sqlite_file(): void
    {
        $process = $this->prepare();

        $this->assertSame(0, $process->getExitCode(), $process->getErrorOutput());
        $this->assertFileExists($this->path('.env'));
        $this->assertMatchesRegularExpression('/^APP_KEY=base64:(.+)$/m', file_get_contents($this->path('.env')));
        preg_match('/^APP_KEY=base64:(.+)$/m', file_get_contents($this->path('.env')), $match);
        $this->assertSame(32, strlen((string) base64_decode(trim($match[1]), true)));
        $this->assertFileExists($this->path('database/database.sqlite'));
    }

    public function test_existing_key_is_never_replaced(): void
    {
        $key = 'base64:'.base64_encode(str_repeat('k', 32));
        file_put_contents($this->path('.env'), "APP_NAME=PALM\nAPP_KEY={$key}\nAPP_ENV=local\n");

        $process = $this->prepare();

        $this->assertSame(0, $process->getExitCode(), $process->getErrorOutput());
        $this->assertStringContainsString("APP_KEY={$key}\n", file_get_contents($this->path('.env')));
    }

    #[DataProvider('productionSpellings')]
    public function test_production_is_refused_before_anything_changes(string $line): void
    {
        $before = "APP_NAME=PALM\n{$line}\nAPP_KEY=\n";
        file_put_contents($this->path('.env'), $before);

        $process = $this->prepare();

        $this->assertSame(1, $process->getExitCode());
        $this->assertStringContainsString('Refusing to run', $process->getErrorOutput());
        $this->assertSame($before, file_get_contents($this->path('.env')));
        $this->assertFileDoesNotExist($this->path('database/database.sqlite'));
    }

    public function test_a_commented_production_line_does_not_block_a_development_machine(): void
    {
        file_put_contents($this->path('.env'), "# APP_ENV=production on the server\nAPP_ENV=local\nAPP_KEY=\n");

        $process = $this->prepare();

        $this->assertSame(0, $process->getExitCode(), $process->getErrorOutput());
        $this->assertMatchesRegularExpression('/^APP_KEY=base64:/m', file_get_contents($this->path('.env')));
    }

    public function test_mysql_connection_creates_no_sqlite_file(): void
    {
        file_put_contents($this->path('.env'), "APP_ENV=local\nAPP_KEY=\nDB_CONNECTION=mysql\nDB_DATABASE=palm_hris\n");

        $process = $this->prepare();

        $this->assertSame(0, $process->getExitCode(), $process->getErrorOutput());
        $this->assertFileDoesNotExist($this->path('database/database.sqlite'));
    }

    public function test_custom_sqlite_path_creates_no_default_file(): void
    {
        file_put_contents($this->path('.env'), "APP_ENV=local\nAPP_KEY=\nDB_CONNECTION=sqlite\nDB_DATABASE=/somewhere/else.sqlite\n");

        $process = $this->prepare();

        $this->assertSame(0, $process->getExitCode(), $process->getErrorOutput());
        $this->assertFileDoesNotExist($this->path('database/database.sqlite'));
    }

    public function test_running_twice_changes_nothing(): void
    {
        $this->assertSame(0, $this->prepare()->getExitCode());
        $first = file_get_contents($this->path('.env'));

        $second = $this->prepare();

        $this->assertSame(0, $second->getExitCode(), $second->getErrorOutput());
        $this->assertSame($first, file_get_contents($this->path('.env')));
        $this->assertFileExists($this->path('database/database.sqlite'));
    }
}
