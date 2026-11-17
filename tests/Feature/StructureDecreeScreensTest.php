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
    private function draft(array $rows, ?string $effective = null): StructureDecree
    {
        return $this->service->createDraft(
            ['sk_number' => 'DPSB/SKPTS/352/IX/2026', 'title' => 'Perubahan Struktur Organisasi Region Office', 'sk_date' => '2026-09-01', 'effective_date' => $effective],
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

    public function test_index_lists_decrees_with_number_status_and_the_create_action(): void
    {
        $this->draft($this->changedRows());

        Livewire::actingAs($this->admin)->test(StructureDecreeIndex::class)
            ->assertSee('DPSB/SKPTS/352/IX/2026')
            ->assertSee('Perubahan Struktur Organisasi Region Office')
            ->assertSee('Draf')
            ->assertSee('Buat SK Baru');
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
            ->set('reason', 'Lampiran belum sesuai SK asli.')
            ->call('reject')
            ->assertHasNoErrors()
            ->assertSee('Ditolak');

        $this->assertSame('Lampiran belum sesuai SK asli.', $decree->fresh()->decision_reason);
    }

    public function test_approving_a_due_decree_applies_it_and_shows_the_worklist(): void
    {
        Employee::factory()->create(['unit_kerja' => 'Bagian Tanaman', 'jabatan' => 'Asisten Pemetaan']);
        $decree = $this->submitted([['Bagian Tanaman', 'Kepala Bagian', 'RM 1', 'TAN', 1]], '2026-09-01');

        Livewire::actingAs($this->admin)->test(StructureDecreeDetail::class, ['decree' => $decree])
            ->call('approve')
            ->assertSee('Diterapkan')
            ->assertSee('Karyawan Perlu Penempatan Ulang')
            ->assertSee('Asisten Pemetaan');

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
