<?php

namespace Tests\Feature;

use Tests\TestCase;

class AdminButtonTest extends TestCase
{
    public function test_defaults_to_a_primary_button_element_that_uses_project_tokens(): void
    {
        $view = $this->blade('<x-admin.button>Simpan</x-admin.button>');

        $view->assertSee('<button', false);
        $view->assertSee('type="button"', false);
        $view->assertSee('bg-primary-600', false);
        $view->assertDontSee('emerald', false);
        $view->assertDontSee('wire:loading', false);
    }

    public function test_renders_an_anchor_when_href_is_given(): void
    {
        $view = $this->blade('<x-admin.button href="/kembali" variant="secondary">Kembali</x-admin.button>');

        $view->assertSee('<a', false);
        $view->assertSee('href="/kembali"', false);
        $view->assertDontSee('<button', false);
        $view->assertSee('border-surface-300', false);
    }

    public function test_danger_variant_uses_the_danger_token_with_a_passing_contrast_pair(): void
    {
        $view = $this->blade('<x-admin.button variant="danger">Hapus</x-admin.button>');

        $view->assertSee('bg-danger', false);
        $view->assertSee('text-white', false);
    }

    public function test_icon_is_decorative(): void
    {
        $view = $this->blade('<x-admin.button icon="fas fa-check">Simpan</x-admin.button>');

        $view->assertSee('<i class="fas fa-check', false);
        $view->assertSee('aria-hidden="true"', false);
    }

    public function test_livewire_target_disables_the_button_and_swaps_the_icon_for_a_spinner(): void
    {
        $view = $this->blade('<x-admin.button wire:click="save" icon="fas fa-check" target="save">Simpan</x-admin.button>');

        $view->assertSee('wire:click="save"', false);
        $view->assertSee('wire:loading.attr="disabled"', false);
        $view->assertSee('wire:target="save"', false);
        $view->assertSee('fa-spinner fa-spin', false);
        $view->assertSee('wire:loading.remove', false);
    }

    public function test_once_disables_the_button_after_the_form_is_actually_submitted(): void
    {
        $view = $this->blade('<form><x-admin.button type="submit" once>Simpan</x-admin.button></form>');

        $view->assertSee('type="submit"', false);
        $view->assertSee("addEventListener('submit'", false);
        $view->assertDontSee('x-on:click', false);
        $view->assertDontSee('@click', false);
    }
}
