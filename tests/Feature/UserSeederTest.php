<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

/**
 * SUT: UserSeeder. Production must never create demo accounts or ship a password from source code (SECURITY.md §3).
 */
class UserSeederTest extends TestCase
{
    use RefreshDatabase;

    private function asProduction(array $admin = []): void
    {
        $this->app['env'] = 'production';

        config(['hris.bootstrap_admin' => array_merge([
            'name' => 'Administrator SDM',
            'username' => 'admin.sdm',
            'email' => 'admin.sdm@ptpn4.co.id',
            'password' => 'Str0ng-Passphrase-2026',
            'min_password_length' => 12,
        ], $admin)]);
    }

    public function test_non_production_seeds_two_demo_admins_and_a_user_with_the_right_roles(): void
    {
        (new UserSeeder)->run();

        $this->assertSame('admin', User::where('username', 'admin')->value('role'));
        $this->assertSame('user', User::where('username', 'user')->value('role'));
        $this->assertTrue(Hash::check('ptpn4reg5', User::where('username', 'admin')->value('password')));

        // A second administrator lets four-eyes approvals (SK, import, mutation chain) be exercised locally.
        $second = User::where('username', 'admin2')->firstOrFail();
        $this->assertSame('admin', $second->role);
        $this->assertSame('admin2@example.com', $second->email);
        $this->assertTrue(Hash::check('ptpn4reg5', $second->password));
    }

    public function test_non_production_seeding_twice_does_not_duplicate_accounts(): void
    {
        (new UserSeeder)->run();
        (new UserSeeder)->run();

        $this->assertSame(3, User::count());
    }

    public function test_production_creates_only_the_configured_admin_and_no_demo_accounts(): void
    {
        $this->asProduction();

        (new UserSeeder)->run();

        $this->assertSame(1, User::count());
        $admin = User::firstOrFail();
        $this->assertSame('admin', $admin->role);
        $this->assertSame('admin.sdm@ptpn4.co.id', $admin->email);
        $this->assertTrue(Hash::check('Str0ng-Passphrase-2026', $admin->password));
        $this->assertNull(User::where('username', 'user')->first());
        $this->assertNull(User::where('email', 'admin@example.com')->first());
    }

    public function test_production_never_overwrites_an_existing_admin(): void
    {
        $this->asProduction();
        (new UserSeeder)->run();
        $hash = User::firstOrFail()->password;

        $this->asProduction(['username' => 'admin.lain', 'email' => 'lain@ptpn4.co.id', 'password' => 'An0ther-Passphrase-99']);
        (new UserSeeder)->run();

        $this->assertSame(1, User::count());
        $this->assertSame($hash, User::firstOrFail()->password);
    }

    public function test_production_refuses_to_run_without_credentials(): void
    {
        $this->asProduction(['name' => null, 'username' => null, 'email' => null, 'password' => null]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('HRIS_ADMIN_PASSWORD');

        try {
            (new UserSeeder)->run();
        } finally {
            $this->assertSame(0, User::count());
        }
    }

    #[DataProvider('weakPasswords')]
    public function test_production_rejects_weak_or_repository_known_passwords(string $password): void
    {
        $this->asProduction(['password' => $password]);

        $this->expectException(RuntimeException::class);

        try {
            (new UserSeeder)->run();
        } finally {
            $this->assertSame(0, User::count());
        }
    }

    public static function weakPasswords(): array
    {
        return [
            'too short' => ['Ab1'],
            'repository default' => ['ptpn4reg5'],
            'repository default padded to length' => ['PASSWORD'],
            'letters only' => ['abcdefghijklmnop'],
            'digits only' => ['123456789012345'],
        ];
    }

    public function test_production_rejects_a_placeholder_example_email(): void
    {
        $this->asProduction(['email' => 'admin@example.com']);

        $this->expectException(RuntimeException::class);

        try {
            (new UserSeeder)->run();
        } finally {
            $this->assertSame(0, User::count());
        }
    }
}
