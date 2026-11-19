<?php

namespace Tests\Feature;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
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

    public function test_stepper_renders_the_card_bar_with_descriptions_and_completion_icons(): void
    {
        $steps = "[['title' => 'Data SK', 'description' => 'Nomor dan lampiran'], ['title' => 'Tinjau Perubahan', 'description' => 'Selisih dan dampak'], 'Ajukan']";
        $view = $this->blade('<x-admin.stepper :steps="'.$steps.'" :current="2" label="Tahap pencatatan SK" />');

        $view->assertSeeInOrder(['Tahap 1', 'Data SK', 'Nomor dan lampiran', 'Tahap 2', 'Tinjau Perubahan', 'Selisih dan dampak', 'Tahap 3', 'Ajukan']);
        $view->assertSee('md:grid-cols-3', false);
        $view->assertSee('fa-check', false); // tahap 1 selesai
        $view->assertDontSee('fa-check-double', false); // tahap terakhir belum berjalan
        $view->assertSee('bg-surface-200 text-surface-700', false); // tahap 3 belum tiba: netral, tanpa opasitas
        $view->assertDontSee('opacity-', false); // opasitas menurunkan kontras di bawah 4.5:1
    }

    public function test_stepper_shows_the_final_check_when_the_last_step_is_current_and_supports_two_steps(): void
    {
        $this->blade('<x-admin.stepper :steps="[\'Satu\', \'Dua\', \'Tiga\']" :current="3" />')->assertSee('fa-check-double', false);
        $this->blade('<x-admin.stepper :steps="[\'Satu\', \'Dua\']" :current="1" />')->assertSee('md:grid-cols-2', false)->assertDontSee('md:grid-cols-3', false);
    }

    public function test_stepper_in_alpine_mode_binds_to_the_callers_expression_instead_of_fixed_classes(): void
    {
        $view = $this->blade('<x-admin.stepper :steps="[\'Satu\', \'Dua\', \'Tiga\']" current-expression="currentStep" label="Tahap impor" />');

        $view->assertSee('currentStep === 2', false);
        $view->assertSee('x-if="currentStep > 1"', false);
        $view->assertSee(':aria-current="currentStep === 3', false);
        $view->assertDontSee('aria-current="step"', false);
    }

    public function test_segmented_renders_a_keyboard_operable_radiogroup_with_the_current_option_checked(): void
    {
        $view = $this->blade('<x-admin.segmented label="Tampilan sebaran" :options="[\'jabatan\' => \'Per Jabatan\', \'unit\' => \'Per Unit\']" current="unit" />');

        $view->assertSee('role="radiogroup"', false);
        $view->assertSee('aria-label="Tampilan sebaran"', false);
        $view->assertSeeInOrder(['Per Jabatan', 'Per Unit']);
        $view->assertSee('@keydown.arrow-right.prevent="move(1)"', false);
        $view->assertSee('@keydown.arrow-left.prevent="move(-1)"', false);
        $this->assertSame(1, substr_count($view->__toString(), 'aria-checked="true"'));
        $this->assertSame(1, substr_count($view->__toString(), 'tabindex="0"'), 'tabindex berpindah (roving): hanya pilihan aktif yang dapat difokus dengan Tab');
        $this->assertSame(1, substr_count($view->__toString(), 'tabindex="-1"'));
        $view->assertSee('wire:click="setView(\'jabatan\')"', false);
    }

    public function test_segmented_action_name_is_configurable(): void
    {
        $this->blade('<x-admin.segmented label="Tampilan" :options="[\'a\' => \'A\', \'b\' => \'B\']" current="a" action="pilihTampilan" />')
            ->assertSee('wire:click="pilihTampilan(\'b\')"', false)
            ->assertDontSee('setView', false);
    }

    public function test_file_verification_without_a_hash_shows_a_dash_and_no_copy_button(): void
    {
        $view = $this->blade('<x-admin.file-verification :hash="null" />');

        $view->assertDontSee('Salin kode verifikasi berkas');
        $view->assertSee('-');
    }

    public function test_approval_policy_notice_switches_title_icon_and_tone_with_the_policy(): void
    {
        $markup = <<<'BLADE'
        <x-admin.approval-policy-notice :active="$active">
            <x-slot:enabled>Teks aktif.</x-slot:enabled>
            <x-slot:disabled>Teks nonaktif.</x-slot:disabled>
            Kalimat penutup.
        </x-admin.approval-policy-notice>
        BLADE;

        $off = $this->blade($markup, ['active' => false]);
        $off->assertSee('Persetujuan dua pihak (empat mata) belum diaktifkan.');
        $off->assertSeeInOrder(['Teks nonaktif.', 'Kalimat penutup.']);
        $off->assertDontSee('Teks aktif.');
        $off->assertSee('fa-circle-info', false);
        $off->assertSee('role="status"', false);

        $on = $this->blade($markup, ['active' => true]);
        $on->assertSee('Persetujuan dua pihak (empat mata) aktif.');
        $on->assertSeeInOrder(['Teks aktif.', 'Kalimat penutup.']);
        $on->assertDontSee('Teks nonaktif.');
        $on->assertSee('fa-user-shield', false);
    }

    public function test_the_approval_policy_title_is_written_only_in_the_shared_component(): void
    {
        $offenders = collect(File::allFiles(resource_path('views')))
            ->filter(fn ($file) => str_ends_with($file->getFilename(), '.blade.php'))
            ->filter(fn ($file) => $file->getFilename() !== 'approval-policy-notice.blade.php')
            ->filter(fn ($file) => str_contains(file_get_contents($file->getPathname()), 'Persetujuan dua pihak (empat mata)'))
            ->map(fn ($file) => $file->getRelativePathname())->values()->all();

        $this->assertSame([], $offenders, 'Pemberitahuan kebijakan empat mata wajib memakai <x-admin.approval-policy-notice>.');
    }
}
