<?php

namespace Tests\Unit;

use Tests\TestCase;

/**
 * SUT: the setup, verify, demo and dev scripts in composer.json (CONTRIBUTING.md). They are the entry points for a new
 * machine, so their order and limits matter more than their length.
 */
class ComposerScriptsTest extends TestCase
{
    private const DISABLE_TIMEOUT = 'Composer\\Config::disableProcessTimeout';

    /**
     * @return list<string>
     */
    private function script(string $name): array
    {
        $composer = json_decode((string) file_get_contents(base_path('composer.json')), true, 512, JSON_THROW_ON_ERROR);

        return (array) $composer['scripts'][$name];
    }

    public function test_setup_runs_the_production_guard_before_it_installs_anything(): void
    {
        $steps = $this->script('setup');

        $guard = array_search('@php scripts/prepare-env.php', $steps, true);
        $install = array_search('@composer install', $steps, true);

        $this->assertNotFalse($guard, 'setup must run scripts/prepare-env.php');
        $this->assertNotFalse($install, 'setup must install the PHP dependencies');
        $this->assertLessThan($install, $guard, 'on a server, setup must refuse before it installs dev packages');
    }

    public function test_setup_migrates_only_after_the_guard(): void
    {
        $steps = $this->script('setup');

        $guard = array_search('@php scripts/prepare-env.php', $steps, true);
        $migrate = array_filter($steps, fn (string $step): bool => str_contains($step, 'artisan migrate'));

        $this->assertNotEmpty($migrate);
        $this->assertLessThan(array_key_first($migrate), $guard);
    }

    public function test_long_running_scripts_turn_off_composers_process_timeout(): void
    {
        // Composer stops a script after 300 seconds by default; the full test suite alone takes about fifteen minutes.
        foreach (['setup', 'verify', 'demo', 'dev'] as $name) {
            $this->assertContains(self::DISABLE_TIMEOUT, $this->script($name), "{$name} must disable the process timeout");
        }
    }

    public function test_verify_runs_every_check_of_the_ci_gate(): void
    {
        $steps = implode("\n", $this->script('verify'));

        foreach (['pint --test', '@composer audit', '@php artisan test', 'npm audit --audit-level=high', 'npm run test:js'] as $check) {
            $this->assertStringContainsString($check, $steps, "verify is missing: {$check}");
        }
    }

    public function test_demo_runs_the_demo_seeder_and_nothing_else(): void
    {
        $steps = array_values(array_diff($this->script('demo'), [self::DISABLE_TIMEOUT]));

        $this->assertCount(1, $steps);
        $this->assertStringContainsString('db:seed --class=DemoEmployeeSeeder', $steps[0]);
    }
}
