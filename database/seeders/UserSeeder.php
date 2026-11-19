<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Validator;
use RuntimeException;

class UserSeeder extends Seeder
{
    /**
     * Passwords that ship with the repository or are trivially guessable. Never accepted for the production admin.
     *
     * @var list<string>
     */
    private const FORBIDDEN_PASSWORDS = ['ptpn4reg5', 'password', 'admin', 'admin123', 'ptpn4reg5!'];

    /**
     * Local, testing and E2E environments get fixed demo accounts. Production gets exactly one administrator whose
     * credentials come from the environment (config('hris.bootstrap_admin')), never from source code (SECURITY.md §3).
     */
    public function run(): void
    {
        if (app()->environment('production')) {
            $this->seedProductionAdmin();

            return;
        }

        $this->seedDemoAccounts();
    }

    /**
     * Idempotent: does nothing once any administrator exists, and never overwrites an existing password.
     */
    private function seedProductionAdmin(): void
    {
        if (User::where('role', 'admin')->exists()) {
            $this->command?->info('An administrator already exists; nothing to seed.');

            return;
        }

        $config = (array) config('hris.bootstrap_admin');

        $validator = Validator::make($config, [
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:255', 'unique:users,username'],
            'email' => ['required', 'email:rfc', 'max:255', 'unique:users,email', 'not_regex:/@example\.(com|org|net)$/i'],
            'password' => [
                'required', 'string', 'min:'.(int) config('hris.bootstrap_admin.min_password_length', 12),
                'regex:/[A-Za-z]/', 'regex:/\d/',
                fn (string $attribute, mixed $value, \Closure $fail) => in_array(strtolower((string) $value), self::FORBIDDEN_PASSWORDS, true)
                    ? $fail('The bootstrap admin password is on the list of forbidden passwords.')
                    : null,
            ],
        ]);

        if ($validator->fails()) {
            throw new RuntimeException(
                'Cannot create the production administrator. Set HRIS_ADMIN_NAME, HRIS_ADMIN_USERNAME, HRIS_ADMIN_EMAIL '
                .'and HRIS_ADMIN_PASSWORD in .env (password: at least '.(int) config('hris.bootstrap_admin.min_password_length', 12)
                .' characters with letters and digits). Problems: '.implode(' ', $validator->errors()->all())
            );
        }

        $this->createUser($config['name'], $config['username'], $config['email'], $config['password'], 'admin');

        $this->command?->info('Production administrator created. Remove HRIS_ADMIN_PASSWORD from .env now.');
    }

    /**
     * Fixed demo accounts for development, automated tests and E2E. Not credentials of any real environment.
     */
    private function seedDemoAccounts(): void
    {
        $this->createUser('Admin User', 'admin', 'admin@example.com', 'ptpn4reg5', 'admin');
        $this->createUser('Regular User', 'user', 'user@example.com', 'password', 'user');
    }

    private function createUser(string $name, string $username, string $email, string $password, string $role): void
    {
        if (User::where('username', $username)->orWhere('email', $email)->exists()) {
            return;
        }

        // `role` is deliberately not mass assignable (SECURITY.md §4.5), so it is set explicitly here.
        $user = new User(['name' => $name, 'username' => $username, 'email' => $email, 'password' => $password]);
        $user->role = $role;
        $user->save();
    }
}
