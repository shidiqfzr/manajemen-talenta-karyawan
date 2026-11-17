<?php

namespace Tests\Feature;

use App\Livewire\Admin\Master\StructureDecreeDetail;
use App\Livewire\Admin\Master\StructureDecreeIndex;
use App\Livewire\Admin\Master\StructureDecreeWizard;
use App\Models\Employee;
use App\Models\StructureDecree;
use App\Models\User;
use App\Services\Structure\StructureDecreeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Concerns\CreatesStructureFixtures;
use Tests\TestCase;

class StructureDecreeScreensTest extends TestCase
{
    use CreatesStructureFixtures;
    use RefreshDatabase;

    private User $admin;

    private StructureDecreeService $service;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->travelTo('2026-11-17 08:00:00');
        $this->service = app(StructureDecreeService::class);
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->baseStructure();
    }

    protected function tearDown(): void
    {
        $this->cleanUpWorkbooks();
        parent::tearDown();
    }

    /** @param list<array> $rows */
    private function draft(array $rows, ?string $effective = null, string $number = 'DPSB/SKPTS/352/IX/2026'): StructureDecree
    {
        return $this->service->createDraft(
            ['sk_number' => $number, 'title' => 'Perubahan Struktur Organisasi Region Office', 'sk_date' => '2026-09-01', 'effective_date' => $effective],
            $this->uploadWorkbook($rows),
            null,
            $this->admin,
        );
    }

    private function changedRows(): array
    {
        return [['Bagian Tanaman', 'Kepala Bagian', 'RM 1', 'TAN', 1], ['Bagian Tanaman', 'Asisten Pemetaan', 'RM 3', 'TAN', 3]];
    }

    private function submitted(array $rows, ?string $effective = null): StructureDecree
    {
        return $this->service->submit($this->draft($rows, $effective), $this->admin);
    }

    public function test_only_admins_reach_the_decree_pages(): void
    {
        $decree = $this->draft($this->changedRows());
        $user = User::factory()->create(['role' => 'user']);

        foreach (['admin.master.sk.index' => [], 'admin.master.sk.create' => [], 'admin.master.sk.show' => [$decree], 'admin.master.sk.review' => [$decree]] as $route => $params) {
            $this->actingAs($user)->get(route($route, $params))->assertForbidden();
            $this->actingAs($this->admin)->get(route($route, $params))->assertOk();
        }
    }

    public function test_index_lists_decrees_with_number_subject_dates_and_status(): void
    {
        $this->draft($this->changedRows());

        Livewire::actingAs($this->admin)->test(StructureDecreeIndex::class)
            ->assertSee('DPSB/SKPTS/352/IX/2026')
            ->assertSee('Perubahan Struktur Organisasi Region Office')
            ->assertSee('01 Sep 2026')
            ->assertSee('Draf')
            ->assertSee('Surat Keputusan Struktur');
    }

    public function test_index_page_has_the_primary_action_in_the_header_and_the_approval_policy_banner(): void
    {
        config(['hris.structure.require_approval' => true]);
        $this->actingAs($this->admin)->get(route('admin.master.sk.index'))
            ->assertOk()
            ->assertSee('Catat SK dari Holding')
            ->assertSee('Persetujuan dua pihak (empat mata) aktif');

        config(['hris.structure.require_approval' => false]);
        $this->actingAs($this->admin)->get(route('admin.master.sk.index'))
            ->assertSee('Persetujuan dua pihak (empat mata) belum diaktifkan');
    }

    public function test_index_is_forbidden_to_non_admin_users_even_through_livewire(): void
    {
        Livewire::actingAs(User::factory()->create(['role' => 'user']))->test(StructureDecreeIndex::class)->assertForbidden();
    }

    /** SK pertama dibatalkan (menjadi "Ditutup") agar SK kedua boleh dibuat: hanya satu SK terbuka pada satu waktu. */
    private function twoDecrees(): void
    {
        $first = $this->draft($this->changedRows(), null, 'AAA/1/2026');
        $this->service->cancel($first, $this->admin, 'Dibatalkan untuk uji daftar SK.');
        $this->draft($this->changedRows(), null, 'BBB/2/2026');
    }

    public function test_index_search_filters_by_number_or_subject(): void
    {
        $this->twoDecrees();

        Livewire::actingAs($this->admin)->test(StructureDecreeIndex::class)
            ->assertSee('AAA/1/2026')->assertSee('BBB/2/2026')
            ->set('search', 'AAA')
            ->assertSee('AAA/1/2026')->assertDontSee('BBB/2/2026')
            ->set('search', 'Region Office')
            ->assertSee('AAA/1/2026')->assertSee('BBB/2/2026')
            ->set('search', 'tidak-ada-yang-cocok')
            ->assertSee('Tidak ada SK yang cocok');
    }

    public function test_index_status_tabs_filter_the_list_and_show_their_counts(): void
    {
        $this->twoDecrees();

        Livewire::actingAs($this->admin)->test(StructureDecreeIndex::class)
            ->assertSeeInOrder(['Semua', '2', 'Berjalan', '1', 'Diterapkan', '0', 'Ditutup', '1'])
            ->set('status', 'closed')
            ->assertSee('AAA/1/2026')->assertDontSee('BBB/2/2026')
            ->set('status', 'open')
            ->assertSee('BBB/2/2026')->assertDontSee('AAA/1/2026')
            ->set('status', '')
            ->assertSee('AAA/1/2026')->assertSee('BBB/2/2026');
    }

    public function test_index_sorts_by_number_in_both_directions(): void
    {
        $this->twoDecrees();

        $component = Livewire::actingAs($this->admin)->test(StructureDecreeIndex::class)->call('sortBy', 'sk_number');
        $this->assertSame(['AAA/1/2026', 'BBB/2/2026'], $component->instance()->rows->pluck('sk_number')->all());

        $component->call('sortBy', 'sk_number');
        $this->assertSame(['BBB/2/2026', 'AAA/1/2026'], $component->instance()->rows->pluck('sk_number')->all());
    }

    public function test_index_shows_a_guiding_empty_state_when_there_is_no_decree(): void
    {
        Livewire::actingAs($this->admin)->test(StructureDecreeIndex::class)
            ->assertSee('Belum ada SK struktur organisasi');
    }

    public function test_the_form_explains_the_holding_sk_and_offers_the_template(): void
    {
        $this->actingAs($this->admin)->get(route('admin.master.sk.create'))
            ->assertOk()
            ->assertSee('Catat &amp; Terapkan SK Struktur', false)
            ->assertSee('diterbitkan Holding')
            ->assertSee('Unduh Template Lampiran')
            ->assertSee('Cara menyiapkan lampiran');

        Livewire::actingAs($this->admin)->test(StructureDecreeWizard::class)
            ->assertSee('Unduh Template Lampiran')
            ->assertSee('Cara menyiapkan lampiran');
    }

    public function test_step_one_is_built_from_numbered_sections_the_shared_upload_and_the_shared_footer(): void
    {
        Livewire::actingAs($this->admin)->test(StructureDecreeWizard::class)
            ->assertSee('Data SK')
            ->assertSee('Lampiran')
            ->assertSee('Kolom bertanda')
            ->assertSee('fa-file-excel', false)
            ->assertSee('Belum ada lampiran dipilih')
            ->assertSee('Lanjut ke Tinjauan')
            ->assertSee('wire:target="createDraft"', false)
            ->assertDontSee('addEventListener', false); // tombol kirim tidak boleh terkunci permanen pada form Livewire
    }

    public function test_a_server_error_is_summarised_at_the_top_of_the_form(): void
    {
        StructureDecree::create(['sk_number' => 'DPSB/SKPTS/352/IX/2026', 'title' => 'SK Lama', 'sk_date' => '2026-01-01', 'effective_date' => '2026-01-01', 'status' => StructureDecree::STATUS_APPLIED, 'source_path' => 'baseline:x', 'source_sha256' => str_repeat('a', 64)]);
        $file = $this->uploadWorkbook($this->changedRows());

        Livewire::actingAs($this->admin)->test(StructureDecreeWizard::class)
            ->set('skNumber', 'DPSB/SKPTS/352/IX/2026')->set('title', 'Perubahan Struktur')->set('skDate', '2026-09-01')
            ->set('workbook', UploadedFile::fake()->createWithContent('lampiran.xlsx', (string) file_get_contents($file->getRealPath())))
            ->call('createDraft')
            ->assertSee('isian perlu diperbaiki')
            ->assertSee('sudah terdaftar');
    }

    public function test_template_downloads_for_admins_only(): void
    {
        $user = User::factory()->create(['role' => 'user']);

        $this->actingAs($this->admin)->get(route('admin.master.sk.template'))->assertOk()->assertDownload('template-struktur-organisasi.xlsx');
        $this->actingAs($user)->get(route('admin.master.sk.template'))->assertForbidden();
    }

    public function test_wizard_creates_a_draft_and_shows_the_review_with_the_diff(): void
    {
        $file = $this->uploadWorkbook($this->changedRows());

        Livewire::actingAs($this->admin)->test(StructureDecreeWizard::class)
            ->set('skNumber', ' DPSB/SKPTS/ 352 /IX/2026 ')
            ->set('title', 'Perubahan Struktur Organisasi Region Office')
            ->set('skDate', '2026-09-01')
            ->set('workbook', UploadedFile::fake()->createWithContent('lampiran.xlsx', (string) file_get_contents($file->getRealPath())))
            ->call('createDraft')
            ->assertSet('step', 2)
            ->assertSee('Tinjau Perubahan')
            ->assertSee('Asisten Pemetaan');

        $decree = StructureDecree::sole();
        $this->assertSame('DPSB/SKPTS/352/IX/2026', $decree->sk_number);
        $this->assertSame('2026-09-01', $decree->effective_date->toDateString());
    }

    public function test_wizard_form_text_is_not_double_escaped(): void
    {
        Livewire::actingAs($this->admin)->test(StructureDecreeWizard::class)
            ->assertSee('Judul SK pada baris "Tentang".')
            ->assertDontSee('&amp;quot;', false);
    }

    public function test_wizard_shows_the_service_message_instead_of_failing(): void
    {
        StructureDecree::create(['sk_number' => 'DPSB/SKPTS/352/IX/2026', 'title' => 'SK Lama', 'sk_date' => '2026-01-01', 'effective_date' => '2026-01-01', 'status' => StructureDecree::STATUS_APPLIED, 'source_path' => 'baseline:x', 'source_sha256' => str_repeat('a', 64)]);
        $file = $this->uploadWorkbook($this->changedRows());

        Livewire::actingAs($this->admin)->test(StructureDecreeWizard::class)
            ->set('skNumber', 'DPSB/SKPTS/352/IX/2026')
            ->set('title', 'Perubahan Struktur')
            ->set('skDate', '2026-09-01')
            ->set('workbook', UploadedFile::fake()->createWithContent('lampiran.xlsx', (string) file_get_contents($file->getRealPath())))
            ->call('createDraft')
            ->assertSet('step', 1)
            ->assertSee('sudah terdaftar');
    }

    public function test_review_blocks_submission_until_new_unit_attributes_are_filled(): void
    {
        $decree = $this->draft([['Kebun Baru', 'Manajer', 'RM 1', 'TAN', 1]]);

        $component = Livewire::actingAs($this->admin)->test(StructureDecreeWizard::class, ['decree' => $decree])
            ->assertSee('belum memiliki kode')
            ->assertSee('hal perlu diselesaikan sebelum SK dapat diajukan')
            ->assertSee('Ajukan nonaktif sampai')
            ->assertSee('Jumlah Perubahan')
            ->assertSee('Total Kuota')
            ->call('submit');
        $this->assertSame(StructureDecree::STATUS_DRAFT, $decree->fresh()->status);

        $component->set('newUnits.0.kode', 'Kebun')
            ->set('newUnits.0.wilayah', self::REGIONAL_OFFICE)
            ->set('newUnits.0.urutan', 9)
            ->call('submit')
            ->assertRedirect(route('admin.master.sk.show', $decree));
        $this->assertSame(StructureDecree::STATUS_SUBMITTED, $decree->fresh()->status);
    }

    public function test_review_pairs_a_renamed_unit_and_recalculates_the_diff(): void
    {
        $decree = $this->draft([
            ['Kebun Tajati Baru', 'Kepala Bagian', 'RM 1', 'TAN', 1],
            ['Kebun Tajati Baru', 'Manajer', 'RM 1', 'TAN', 1],
        ]);

        Livewire::actingAs($this->admin)->test(StructureDecreeWizard::class, ['decree' => $decree])
            ->assertSee('Unit baru')
            ->set('unitPairs.0.old', 'Kebun Tajati')
            ->call('recalculate')
            ->assertSee('Ganti nama unit');

        $this->assertSame(['Kebun Tajati' => 'Kebun Tajati Baru'], $decree->fresh()->unit_mapping['mapping']);
    }

    public function test_review_pairs_a_renamed_jabatan_so_holders_keep_their_seat(): void
    {
        $decree = $this->draft([
            ['Bagian Tanaman', 'Kepala Bagian', 'RM 1', 'TAN', 1],
            ['Bagian Tanaman', 'Asisten Pemetaan & Survei', 'RM 3', 'TAN', 2],
        ]);

        Livewire::actingAs($this->admin)->test(StructureDecreeWizard::class, ['decree' => $decree])
            ->assertSee('Asisten Pemetaan & Survei')
            ->set('positionPairs.0.old', 'Asisten Pemetaan')
            ->call('recalculate')
            ->assertSee('Ganti nama jabatan');

        $this->assertSame(['Asisten Pemetaan' => 'Asisten Pemetaan & Survei'], $decree->fresh()->unit_mapping['positions']);
    }

    public function test_detail_hides_approval_from_the_submitter_when_four_eyes_is_on(): void
    {
        config(['hris.structure.require_approval' => true]);
        $decree = $this->submitted($this->changedRows(), '2026-12-01');
        $checker = User::factory()->create(['role' => 'admin']);

        Livewire::actingAs($this->admin)->test(StructureDecreeDetail::class, ['decree' => $decree])
            ->assertDontSee('Setujui SK')
            ->assertSee('tidak dapat menyetujui');
        Livewire::actingAs($checker)->test(StructureDecreeDetail::class, ['decree' => $decree])
            ->assertSee('Setujui SK');
    }

    public function test_reject_needs_a_reason_and_records_it(): void
    {
        $decree = $this->submitted($this->changedRows(), '2026-12-01');

        Livewire::actingAs($this->admin)->test(StructureDecreeDetail::class, ['decree' => $decree])
            ->set('reason', 'pendek')
            ->call('reject')
            ->assertHasErrors('reason')
            ->assertNotDispatched('decree-action-finished') // dialog tetap terbuka selama alasan belum valid
            ->set('reason', 'Lampiran belum sesuai SK asli.')
            ->call('reject')
            ->assertHasNoErrors()
            ->assertDispatched('decree-action-finished') // dialog ditutup setelah aksi selesai
            ->assertSee('Ditolak');

        $this->assertSame('Lampiran belum sesuai SK asli.', $decree->fresh()->decision_reason);
    }

    public function test_approving_a_due_decree_applies_it_and_shows_the_worklist(): void
    {
        $employee = Employee::factory()->create(['unit_kerja' => 'Bagian Tanaman', 'jabatan' => 'Asisten Pemetaan']);
        $decree = $this->submitted([['Bagian Tanaman', 'Kepala Bagian', 'RM 1', 'TAN', 1]], '2026-09-01');

        Livewire::actingAs($this->admin)->test(StructureDecreeDetail::class, ['decree' => $decree])
            ->call('approve')
            ->assertSee('Diterapkan')
            ->assertSee('Karyawan Perlu Penempatan Ulang')
            ->assertSee('Asisten Pemetaan')
            // NIK tidak boleh muncul di HTML daftar (UU PDP), termasuk pada atribut wire:key.
            ->assertDontSee($employee->nik, false);

        $this->assertSame(StructureDecree::STATUS_APPLIED, $decree->fresh()->status);
    }

    public function test_pages_never_show_internal_document_names(): void
    {
        $decree = $this->draft($this->changedRows());

        foreach ([route('admin.master.sk.index'), route('admin.master.sk.create'), route('admin.master.sk.show', $decree), route('admin.master.sk.review', $decree)] as $url) {
            $response = $this->actingAs($this->admin)->get($url)->assertOk();
            foreach (['AGENTS.md', 'DOMAIN.md', 'DESIGN.md', 'ENGINEERING.md', 'SECURITY.md', 'SSOT'] as $forbidden) {
                $response->assertDontSee($forbidden, false);
            }
        }
    }

    public function test_the_baseline_record_explains_that_it_is_the_starting_structure_not_a_comparison(): void
    {
        $baseline = StructureDecree::create(['sk_number' => 'DPSB/SKPTS/352/IX/2026', 'title' => 'Baseline', 'sk_date' => '2026-09-01', 'effective_date' => '2026-09-01', 'status' => StructureDecree::STATUS_APPLIED, 'source_path' => 'baseline:database/data/master_formasi.json', 'source_sha256' => str_repeat('a', 64)]);

        Livewire::actingAs($this->admin)->test(StructureDecreeDetail::class, ['decree' => $baseline])
            ->assertSee('struktur awal')
            ->assertDontSee('Tidak ada perubahan dibandingkan dengan struktur saat ini');
    }

    public function test_source_files_download_for_admins_only_and_not_for_the_baseline_record(): void
    {
        $decree = $this->draft($this->changedRows());
        $baseline = StructureDecree::create(['sk_number' => 'DPSB/SKPTS/100/I/2026', 'title' => 'Baseline', 'sk_date' => '2026-01-01', 'effective_date' => '2026-01-01', 'status' => StructureDecree::STATUS_APPLIED, 'source_path' => 'baseline:database/data/master_formasi.json', 'source_sha256' => str_repeat('a', 64)]);
        $user = User::factory()->create(['role' => 'user']);

        $this->actingAs($this->admin)->get(route('admin.master.sk.download', [$decree, 'lampiran']))->assertOk()->assertDownload('lampiran-sk.xlsx');
        $this->actingAs($this->admin)->get(route('admin.master.sk.download', [$decree, 'salinan']))->assertNotFound();
        $this->actingAs($this->admin)->get(route('admin.master.sk.download', [$baseline, 'lampiran']))->assertNotFound();
        $this->actingAs($user)->get(route('admin.master.sk.download', [$decree, 'lampiran']))->assertForbidden();
    }
}
