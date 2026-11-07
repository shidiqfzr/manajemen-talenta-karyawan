<?php

namespace Tests\Feature;

use App\Livewire\Admin\TrainingParticipantTable;
use App\Models\Employee;
use App\Models\Training;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * SUT: App\Livewire\Admin\TrainingParticipantTable (participants list on the admin training detail page).
 */
class TrainingParticipantTableTest extends TestCase
{
    use RefreshDatabase;

    private Training $training;

    private Employee $alice;

    private Employee $bob;

    private Employee $outsider;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create(['role' => 'admin']));

        $this->training = $this->makeTraining('Pelatihan Utama');
        $otherTraining = $this->makeTraining('Pelatihan Lain');

        $this->alice = Employee::factory()->create(['nama' => 'Alice Peserta', 'level' => 'Karpim', 'jabatan' => 'Manajer Kebun']);
        $this->bob = Employee::factory()->create(['nama' => 'Bob Peserta', 'level' => 'Karpel', 'jabatan' => 'Asisten Teknik']);
        $this->outsider = Employee::factory()->create(['nama' => 'Carol Luar', 'level' => 'Karpel']);

        $this->training->employees()->attach($this->alice->nik, ['sertifikat' => 'sertifikat/alice.pdf']);
        $this->training->employees()->attach($this->bob->nik);
        $otherTraining->employees()->attach($this->outsider->nik);
    }

    private function makeTraining(string $judul): Training
    {
        return Training::create([
            'judul' => $judul,
            'jenis' => 'Teknis',
            'metode' => 'Luring',
            'tanggal_mulai' => '2026-02-10',
            'tanggal_akhir' => '2026-02-12',
            'biaya_tiket_peserta' => 0,
            'biaya_pelatihan' => 0,
            'penyelenggara' => 'Lembaga Uji',
            'nomor_surat' => 'NS/001',
            'tanggal_surat' => '2026-01-30',
            'keterangan' => '-',
            'bidang_pelatihan' => 'TEK',
            'jam_belajar_per_hari' => 8,
        ]);
    }

    private function table()
    {
        return Livewire::test(TrainingParticipantTable::class, ['trainingId' => $this->training->id]);
    }

    public function test_lists_only_this_trainings_participants(): void
    {
        $this->table()
            ->assertSee('Alice Peserta')
            ->assertSee('Bob Peserta')
            ->assertDontSee('Carol Luar')
            ->assertSee('sertifikat/alice.pdf')
            ->assertSee('Tidak ada')
            ->assertSee(route('admin.trainings.participants.edit', ['training' => $this->training->id, 'employee' => $this->alice->nik]), false);
    }

    public function test_training_id_cannot_be_changed_from_the_client(): void
    {
        $this->expectException(CannotUpdateLockedPropertyException::class);

        $this->table()->set('trainingId', 999);
    }

    public function test_header_noun_without_title_or_export(): void
    {
        $html = $this->table()->html();
        preg_match('/<p role="status"[^>]*>(.*?)<\/p>/s', $html, $region);

        $this->assertStringStartsWith('2 Peserta', trim(preg_replace('/\s+/', ' ', strip_tags($region[1]))));
        $this->assertStringNotContainsString('Ekspor Excel', $html);
        $this->assertStringNotContainsString('text-sm font-bold text-surface-900', $html);
    }

    public function test_search_and_level_filter(): void
    {
        $this->table()
            ->set('search', 'Alice')
            ->assertSee('Alice Peserta')
            ->assertDontSee('Bob Peserta')
            ->set('search', $this->bob->nik)
            ->assertSee('Bob Peserta')
            ->assertDontSee('Alice Peserta')
            ->set('search', '')
            ->set('level', 'Karpel')
            ->assertSee('Bob Peserta')
            ->assertDontSee('Alice Peserta')
            ->assertDontSee('Carol Luar');
    }

    public function test_remove_participant_still_works(): void
    {
        $this->delete(route('admin.trainings.participants.destroy', ['training' => $this->training->id, 'employee' => $this->alice->nik]))
            ->assertRedirect();

        $this->assertFalse($this->training->employees()->where('employee_nik', $this->alice->nik)->exists());
        $this->table()->assertDontSee('Alice Peserta')->assertSee('Bob Peserta');

        $html = $this->table()->html();
        $this->assertStringContainsString('name="_method" value="DELETE"', $html);
        $this->assertStringContainsString('name="_token"', $html);
    }

    public function test_page_embeds_the_table_with_its_own_pagination_name(): void
    {
        $this->get(route('admin.trainings.show', $this->training->id))
            ->assertOk()
            ->assertSeeLivewire(TrainingParticipantTable::class)
            ->assertSee('Alice Peserta');

        $this->assertSame('peserta', (new \ReflectionMethod(TrainingParticipantTable::class, 'paginationName'))->invoke($this->table()->instance()));
    }
}
