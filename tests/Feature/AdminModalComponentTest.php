<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ViewErrorBag;
use Tests\TestCase;

/**
 * SUT: komponen Blade x-admin.modal (cangkang modal tunggal, DESIGN.md §7.6).
 */
class AdminModalComponentTest extends TestCase
{
    private function render(string $template): string
    {
        return Blade::render($template);
    }

    public function test_confirm_delete_modal_asks_for_a_reason_only_when_requested(): void
    {
        View::share('errors', new ViewErrorBag);

        $plain = $this->render('<x-admin.confirm-delete-modal title="Hapus Data" noun="data" />');
        $withReason = $this->render('<x-admin.confirm-delete-modal title="Hapus Data" noun="data" reason />');

        $this->assertStringNotContainsString('name="alasan"', $plain);
        $this->assertStringContainsString('name="alasan"', $withReason);
        $this->assertStringContainsString('required', $withReason);
        $this->assertStringContainsString('minlength="10"', $withReason);
        $this->assertStringContainsString('maxlength="500"', $withReason);
        $this->assertStringContainsString('x-model="reason"', $withReason);
        // Isian dikosongkan setiap kali modal dibuka untuk baris lain.
        $this->assertStringContainsString("reason = ''; open = true", $withReason);
    }

    public function test_it_renders_an_accessible_dialog_with_a_focus_trap(): void
    {
        $html = $this->render('<x-admin.modal title="Judul Uji" description="Penjelasan uji">Isi</x-admin.modal>');

        $this->assertStringContainsString('role="dialog"', $html);
        $this->assertStringContainsString('aria-modal="true"', $html);
        $this->assertMatchesRegularExpression('/aria-labelledby="modal-title-[a-f0-9]{8}"/', $html);
        $this->assertStringContainsString('x-trap.noscroll="open"', $html);
        $this->assertStringContainsString('@keydown.escape.window="open = false"', $html);
        $this->assertStringContainsString('aria-label="Tutup"', $html);
        $this->assertStringContainsString('Judul Uji', $html);
        $this->assertStringContainsString('Penjelasan uji', $html);
    }

    public function test_destructive_tones_focus_cancel_first_and_neutral_ones_do_not(): void
    {
        $danger = $this->render('<x-admin.modal tone="danger" title="Hapus?">x</x-admin.modal>');
        $primary = $this->render('<x-admin.modal title="Ubah">x</x-admin.modal>');

        $this->assertStringContainsString('autofocus', $danger);
        $this->assertStringContainsString('bg-danger-100', $danger);
        $this->assertStringNotContainsString('autofocus', $primary);
        $this->assertStringContainsString('bg-primary-100', $primary);
    }

    public function test_footer_puts_cancel_before_the_primary_action(): void
    {
        $html = $this->render('<x-admin.modal title="Uji" cancel="Kembali"><x-slot:footer><button>Simpan Uji</button></x-slot:footer></x-admin.modal>');

        $this->assertLessThan(strpos($html, 'Simpan Uji'), strpos($html, 'Kembali'));
    }

    public function test_cancel_can_be_hidden_for_information_only_dialogs(): void
    {
        $html = $this->render('<x-admin.modal title="Info" :cancel="false">x</x-admin.modal>');

        $this->assertStringNotContainsString('Batal', $html);
    }

    public function test_form_mode_adds_csrf_and_method_spoofing(): void
    {
        $html = $this->render('<x-admin.modal title="Hapus" form method="DELETE" action="/hapus">x</x-admin.modal>');

        $this->assertStringContainsString('<form method="POST" action="/hapus"', $html);
        $this->assertStringContainsString('name="_token"', $html);
        $this->assertStringContainsString('name="_method" value="DELETE"', $html);
    }

    public function test_server_rendered_modals_use_their_own_open_and_close_expressions(): void
    {
        $html = $this->render('<x-admin.modal open="true" close="$wire.closeUji()" title="Uji">x</x-admin.modal>');

        $this->assertStringContainsString('x-show="true"', $html);
        $this->assertStringContainsString('@keydown.escape.window="$wire.closeUji()"', $html);
        $this->assertStringContainsString('@click="$wire.closeUji()"', $html);
    }

    public function test_size_and_scroll_options_shape_the_panel(): void
    {
        $html = $this->render('<x-admin.modal title="Besar" size="2xl" scroll>x</x-admin.modal>');

        $this->assertStringContainsString('sm:max-w-4xl', $html);
        $this->assertStringContainsString('max-h-[60vh] overflow-y-auto', $html);
    }

    public function test_the_explanation_sits_full_width_in_the_body_and_the_header_keeps_one_context_line(): void
    {
        $html = $this->render('<x-admin.modal title="Judul Uji" description="Penjelasan panjang uji"><x-slot:subtitle>NIK: 123</x-slot:subtitle>
 Isian</x-admin.modal>');

        $header = substr($html, 0, strpos($html, 'Penjelasan panjang uji'));
        $this->assertStringContainsString('NIK: 123', $header);
        $this->assertStringContainsString('truncate', $header);
        // Penjelasan berada setelah kepala (setelah judul dan tombol Tutup), sebelum isian.
        $this->assertGreaterThan(strpos($html, 'aria-label="Tutup"'), strpos($html, 'Penjelasan panjang uji'));
        $this->assertLessThan(strpos($html, 'Isian'), strpos($html, 'Penjelasan panjang uji'));
        $this->assertMatchesRegularExpression('/aria-describedby="modal-title-[a-f0-9]{8}-desc"/', $html);
    }
}
