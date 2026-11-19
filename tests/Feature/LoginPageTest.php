<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class LoginPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_uses_the_bundled_stylesheet_instead_of_the_tailwind_cdn(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertDontSee('cdn.tailwindcss.com', false)
            ->assertSee('/build/assets/app-', false);
    }

    public function test_login_page_has_no_removed_tailwind_opacity_utilities(): void
    {
        $this->get(route('login'))->assertOk()->assertDontSee('bg-opacity-', false);
    }

    public function test_toggled_icons_sit_inside_wrapper_elements_so_the_hidden_utility_wins_over_the_icon_font(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('<span id="eye-off" class="hidden">', false)
            ->assertSee('<span id="btnSpinner" class="hidden">', false);
    }

    public function test_login_page_uses_role_neutral_indonesian_copy_and_the_current_year(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('Selamat Datang')
            ->assertSee('Username atau Email')
            ->assertSee('Hak cipta dilindungi')
            ->assertSee('&copy; '.date('Y'), false)
            ->assertDontSee('Admin Login')
            ->assertDontSee('All rights reserved')
            ->assertDontSee('Secure');
    }

    public function test_login_page_does_not_expose_internal_documentation_names(): void
    {
        $response = $this->get(route('login'))->assertOk();

        foreach (['AGENTS.md', 'DOMAIN.md', 'DESIGN.md', 'ENGINEERING.md', 'SECURITY.md', 'SSOT'] as $internal) {
            $response->assertDontSee($internal, false);
        }
    }

    public function test_failed_login_keeps_the_typed_username_and_flags_the_field_as_invalid(): void
    {
        $this->from(route('login'))
            ->post(route('login.submit'), ['username' => 'tidak.ada', 'password' => 'salah'])
            ->assertRedirect(route('login'));

        $this->get(route('login'))
            ->assertOk()
            ->assertSee('value="tidak.ada"', false)
            ->assertSee('aria-invalid="true"', false)
            ->assertSee('Username/Email atau password salah.');
    }

    public function test_the_sixth_attempt_within_a_minute_is_blocked_even_with_the_correct_password(): void
    {
        $user = $this->staffUser();
        $this->failLogin(5);

        $this->from(route('login'))
            ->post(route('login.submit'), ['username' => $user->username, 'password' => 'rahasia123'])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('username');

        $this->assertGuest();
        $this->get(route('login'))
            ->assertSee('Terlalu banyak percobaan masuk.')
            ->assertSee('value="'.$user->username.'"', false);
    }

    public function test_the_fifth_attempt_still_reaches_the_credential_check(): void
    {
        $user = $this->staffUser();
        $this->failLogin(4);

        $this->post(route('login.submit'), ['username' => $user->username, 'password' => 'rahasia123']);

        $this->assertAuthenticatedAs($user);
    }

    public function test_the_block_lifts_after_a_minute(): void
    {
        $user = $this->staffUser();
        $this->failLogin(5);

        $this->travel(61)->seconds();
        $this->post(route('login.submit'), ['username' => $user->username, 'password' => 'rahasia123']);

        $this->assertAuthenticatedAs($user);
    }

    public function test_successful_logins_do_not_count_towards_the_limit(): void
    {
        $user = $this->staffUser();

        // Staff sharing one office IP must not lock each other out by signing in normally.
        foreach (range(1, 7) as $attempt) {
            $this->post(route('login.submit'), ['username' => $user->username, 'password' => 'rahasia123']);
            $this->assertAuthenticatedAs($user);
            $this->post(route('logout'));
        }
    }

    public function test_the_block_applies_per_ip_address(): void
    {
        $user = $this->staffUser();
        $this->failLogin(5);

        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.2'])
            ->post(route('login.submit'), ['username' => $user->username, 'password' => 'rahasia123']);

        $this->assertAuthenticatedAs($user);
    }

    private function staffUser(): User
    {
        return User::factory()->create(['password' => Hash::make('rahasia123')]);
    }

    private function failLogin(int $times): void
    {
        foreach (range(1, $times) as $attempt) {
            $this->from(route('login'))
                ->post(route('login.submit'), ['username' => 'tidak.ada', 'password' => 'salah']);
        }
    }
}
