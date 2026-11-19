<?php

namespace Tests\Feature;

use App\Services\EmployeeDeletionService;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\View;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use Tests\TestCase;

/**
 * SUT: komponen formulir bersama x-admin.form.section, x-admin.form.footer, x-admin.form.error-summary,
 * dan x-admin.form.textarea (DESIGN.md §7.1).
 */
class AdminFormComponentsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Di luar permintaan HTTP tidak ada middleware yang membagikan $errors ke tampilan.
        View::share('errors', new ViewErrorBag);
    }

    public function test_section_renders_number_title_description_and_labelled_region(): void
    {
        $html = Blade::render('<x-admin.form.section number="2" title="Jadwal" description="Periode pelaksanaan">ISI</x-admin.form.section>');

        $this->assertStringContainsString('aria-labelledby="form-section-2-title"', $html);
        $this->assertStringContainsString('id="form-section-2-title"', $html);
        $this->assertStringContainsString('Jadwal', $html);
        $this->assertStringContainsString('Periode pelaksanaan', $html);
        $this->assertStringContainsString('ISI', $html);
        $this->assertStringNotContainsString('wajib diisi', $html);
    }

    public function test_section_shows_the_required_legend_only_when_asked(): void
    {
        $html = Blade::render('<x-admin.form.section number="1" title="A" legend>x</x-admin.form.section>');

        $this->assertStringContainsString('Kolom bertanda', $html);
        $this->assertStringContainsString('wajib diisi', $html);
    }

    public function test_footer_places_cancel_and_a_single_submit_with_double_click_protection(): void
    {
        $html = Blade::render('<x-admin.form.footer cancel-url="/batal" submit-label="Simpan Data" note="Tercatat pada riwayat." />');

        $this->assertStringContainsString('sticky bottom-4', $html);
        $this->assertStringContainsString('href="/batal"', $html);
        $this->assertStringContainsString('Batal', $html);
        $this->assertSame(1, substr_count($html, 'type="submit"'));
        $this->assertStringContainsString('Simpan Data', $html);
        $this->assertStringContainsString('form?.addEventListener(\'submit\'', $html);
        $this->assertStringContainsString('Tercatat pada riwayat.', $html);
    }

    public function test_footer_omits_the_note_when_none_is_given(): void
    {
        $html = Blade::render('<x-admin.form.footer cancel-url="/batal" />');

        $this->assertStringNotContainsString('fa-shield-halved', $html);
    }

    public function test_error_summary_is_hidden_without_errors(): void
    {
        $this->assertStringNotContainsString('errorSummary', Blade::render('<x-admin.form.error-summary />'));
    }

    public function test_error_summary_lists_each_error_with_a_link_to_its_field(): void
    {
        View::share('errors', (new ViewErrorBag)->put('default', new MessageBag([
            'judul' => ['Judul Pelatihan wajib diisi.'],
            'tanggal_akhir' => ['Tanggal Akhir tidak boleh lebih awal dari Tanggal Mulai.'],
        ])));

        $html = Blade::render('<x-admin.form.error-summary />');

        $this->assertStringContainsString('role="alert"', $html);
        $this->assertStringContainsString('2 isian perlu diperbaiki', $html);
        $this->assertStringContainsString('href="#judul"', $html);
        $this->assertStringContainsString('href="#tanggal_akhir"', $html);
    }

    public function test_textarea_links_label_applies_limit_and_escapes_the_value(): void
    {
        $html = Blade::render('<x-admin.form.textarea name="keterangan" label="Keterangan" maxlength="50" value="<b>x</b>" />');

        $this->assertStringContainsString('<label for="keterangan"', $html);
        $this->assertStringContainsString('id="keterangan"', $html);
        $this->assertStringContainsString('maxlength="50"', $html);
        $this->assertStringContainsString('&lt;b&gt;x&lt;/b&gt;', $html);
        $this->assertStringNotContainsString('<b>x</b>', $html);
    }

    public function test_reason_field_enforces_the_shared_length_limits_in_the_browser(): void
    {
        $html = Blade::render('<x-admin.form.reason-field id="alasan-uji" name="alasan" />');

        $this->assertStringContainsString('<label for="alasan-uji"', $html);
        $this->assertStringContainsString('name="alasan"', $html);
        $this->assertStringContainsString('minlength="'.EmployeeDeletionService::REASON_MIN_LENGTH.'"', $html);
        $this->assertStringContainsString('maxlength="'.EmployeeDeletionService::REASON_MAX_LENGTH.'"', $html);
        $this->assertStringContainsString('aria-required="true"', $html);
    }

    public function test_reason_field_for_livewire_forwards_attributes_and_skips_native_validation(): void
    {
        $html = Blade::render('<x-admin.form.reason-field id="bulk-reason" wire:model="bulkReason" :native-validation="false" />');

        $this->assertStringContainsString('wire:model="bulkReason"', $html);
        $this->assertStringNotContainsString('required', $html);
        $this->assertStringNotContainsString('minlength', $html);
        $this->assertStringNotContainsString(' name=', $html);
    }

    public function test_textarea_marks_itself_invalid_and_shows_the_message(): void
    {
        View::share('errors', (new ViewErrorBag)->put('default', new MessageBag(['keterangan' => ['Terlalu panjang.']])));

        $html = Blade::render('<x-admin.form.textarea name="keterangan" label="Keterangan" />');

        $this->assertStringContainsString('aria-invalid="true"', $html);
        $this->assertStringContainsString('id="keterangan-error"', $html);
        $this->assertStringContainsString('text-danger-700', $html);
        $this->assertStringContainsString('Terlalu panjang.', $html);
    }
}
