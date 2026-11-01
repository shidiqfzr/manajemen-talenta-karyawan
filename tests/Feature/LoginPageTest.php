<?php

namespace Tests\Feature;

use Tests\TestCase;

class LoginPageTest extends TestCase
{
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
}
