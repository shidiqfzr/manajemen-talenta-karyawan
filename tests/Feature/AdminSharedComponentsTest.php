<?php

namespace Tests\Feature;

use Illuminate\Support\Carbon;
use Illuminate\Support\ViewErrorBag;
use Tests\TestCase;

/**
 * SUT: komponen Blade bersama modul admin yang diperluas atau ditambahkan untuk layar SK struktur
 * (x-admin.form.footer, x-admin.file-upload, x-admin.decision-timeline, x-admin.file-verification).
 */
class AdminSharedComponentsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Komponen form membaca $errors; middleware ShareErrorsFromSession tidak berjalan pada $this->blade().
        view()->share('errors', new ViewErrorBag);
    }

    public function test_form_footer_keeps_its_defaults_for_ordinary_forms(): void
    {
        $view = $this->blade('<x-admin.form.footer cancel-url="/kembali" submit-label="Simpan Pelatihan" note="Tercatat pada riwayat." />');

        $view->assertSee('Batal');
        $view->assertSee('Simpan Pelatihan');
        $view->assertSee('Tercatat pada riwayat.');
        $view->assertSee('addEventListener', false); // once: tombol terkunci setelah form terkirim
        $view->assertDontSee('wire:target', false);
    }

    public function test_form_footer_supports_livewire_forms_without_locking_the_submit_button(): void
    {
        $view = $this->blade('<x-admin.form.footer cancel-url="/kembali" cancel-label="Simpan sebagai Draf" submit-label="Ajukan" :once="false" target="submit" :disabled="true"><x-slot:actions><button type="button">Hitung Ulang</button></x-slot:actions></x-admin.form.footer>');

        $view->assertSee('Simpan sebagai Draf');
        $view->assertSee('Hitung Ulang');
        $view->assertSee('wire:target="submit"', false);
        $view->assertDontSee('addEventListener', false);
        $view->assertSee('disabled', false);
    }

    public function test_file_upload_can_describe_a_spreadsheet_instead_of_a_pdf(): void
    {
        $view = $this->blade('<x-admin.file-upload wire-model="workbook" id="workbook" label="Lampiran SK (.xlsx)" helper="(Wajib, maks. 10 MB)" accept=".xlsx" error-key="workbook" icon="fas fa-file-excel" empty-text="Belum ada lampiran dipilih" />');

        $view->assertSee('fa-file-excel', false);
        $view->assertSee('Belum ada lampiran dipilih');
        $view->assertDontSee('Belum ada dokumen PDF dipilih');
        $view->assertSee('id="workbook"', false);
        $view->assertSee('for="workbook"', false);
    }

    public function test_file_upload_still_defaults_to_the_pdf_wording(): void
    {
        $view = $this->blade('<x-admin.file-upload wire-model="fileSk" />');

        $view->assertSee('fa-file-pdf', false);
        $view->assertSee('Belum ada dokumen PDF dipilih');
    }

    public function test_decision_timeline_lists_each_step_with_actor_time_and_note(): void
    {
        $steps = [
            ['icon' => 'fa-paper-plane', 'tone' => 'done', 'title' => 'Diajukan', 'who' => 'Admin Satu', 'at' => Carbon::parse('2026-11-17 08:30'), 'note' => null],
            ['icon' => 'fa-ban', 'tone' => 'closed', 'title' => 'Ditolak', 'who' => 'Admin Dua', 'at' => null, 'note' => 'Lampiran belum sesuai.'],
            ['icon' => 'fa-clock', 'tone' => 'current', 'title' => 'Menunggu persetujuan', 'who' => null, 'at' => null, 'note' => null],
        ];

        $view = $this->blade('<x-admin.decision-timeline :steps="$steps" />', ['steps' => $steps]);

        $view->assertSeeInOrder(['Diajukan', 'Admin Satu', '17/11/2026 08:30', 'Ditolak', 'Admin Dua', 'Lampiran belum sesuai.', 'Menunggu persetujuan']);
    }

    public function test_file_verification_shows_size_and_a_copyable_hash(): void
    {
        $hash = str_repeat('ab12', 16);

        $view = $this->blade('<x-admin.file-verification :hash="$hash" :size="46205" />', ['hash' => $hash]);

        $view->assertSee('Verifikasi Berkas');
        $view->assertSee('45,1 KB');
        $view->assertSee($hash);
        $view->assertSee('Salin kode verifikasi berkas');
        // Dokumentasi komponen tidak boleh bocor sebagai teks halaman.
        $view->assertDontSee('size: byte', false);
        $view->assertDontSee('--}}', false);
    }

    public function test_stepper_marks_the_current_step_and_completed_steps(): void
    {
        $view = $this->blade('<x-admin.stepper :steps="[\'Data SK\', \'Tinjau Perubahan\', \'Ajukan\']" :current="2" label="Tahap pencatatan SK" />');

        $view->assertSeeInOrder(['Data SK', 'Tinjau Perubahan', 'Ajukan']);
        $view->assertSee('aria-label="Tahap pencatatan SK"', false);
        $view->assertSee('aria-current="step"', false);
        $this->assertSame(1, substr_count($view->__toString(), 'aria-current="step"'));
    }

    public function test_file_verification_without_a_hash_shows_a_dash_and_no_copy_button(): void
    {
        $view = $this->blade('<x-admin.file-verification :hash="null" />');

        $view->assertDontSee('Salin kode verifikasi berkas');
        $view->assertSee('-');
    }
}
