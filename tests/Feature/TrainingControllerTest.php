<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\MasterAuditLog;
use App\Models\Training;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * SUT: App\Http\Controllers\Admin\TrainingController (simpan, ubah, hapus Pelatihan),
 * termasuk TrainingRequest dan TrainingService yang dipanggilnya.
 */
class TrainingControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => 'admin']);
    }

    /** @param array<string, mixed> $overrides */
    private function payload(array $overrides = []): array
    {
        return $overrides + [
            'judul' => 'Pelatihan Kepemimpinan',
            'jenis' => 'Leadership',
            'metode' => 'Klasikal (Tatap Muka)',
            'tanggal_mulai' => '2026-03-02',
            'tanggal_akhir' => '2026-03-04',
            'biaya_tiket_peserta' => 1500000,
            'biaya_pelatihan' => 5000000,
            'penyelenggara' => 'Lembaga Uji',
            'nomor_surat' => 'SRT/001/2026',
            'tanggal_surat' => '2026-02-20',
            'keterangan' => 'Angkatan 1',
            'bidang_pelatihan' => 'UMU',
            'jam_belajar_per_hari' => 8,
        ];
    }

    private function makeTraining(): Training
    {
        return Training::create($this->payload() + ['jumlah_man_hours' => 0]);
    }

    public function test_non_admin_cannot_store_a_training(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'user']))
            ->post(route('admin.trainings.store'), $this->payload())
            ->assertForbidden();

        $this->assertDatabaseCount('trainings', 0);
    }

    public function test_store_creates_the_training_with_zero_man_hours_and_audit_entry(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.trainings.store'), $this->payload())
            ->assertRedirect(route('admin.trainings.index'));

        $training = Training::firstOrFail();
        $this->assertSame(0, (int) $training->jumlah_man_hours);

        $log = MasterAuditLog::where('action', 'training.created')->firstOrFail();
        $this->assertSame('training', $log->entity_type);
        $this->assertSame($training->id, $log->entity_id);
        $this->assertSame($this->admin->id, $log->user_id);
    }

    public function test_store_accepts_an_empty_keterangan(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.trainings.store'), $this->payload(['keterangan' => null]))
            ->assertSessionHasNoErrors();

        $this->assertSame('', Training::firstOrFail()->keterangan);
    }

    /**
     * @dataProvider invalidInputs
     *
     * @param  array<string, mixed>  $override
     */
    public function test_store_rejects_invalid_input(array $override, string $field): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.trainings.store'), $this->payload($override))
            ->assertSessionHasErrors($field);

        $this->assertDatabaseCount('trainings', 0);
    }

    /** @return array<string, array{0: array<string, mixed>, 1: string}> */
    public static function invalidInputs(): array
    {
        return [
            'end before start' => [['tanggal_mulai' => '2026-03-10', 'tanggal_akhir' => '2026-03-01'], 'tanggal_akhir'],
            'negative cost' => [['biaya_pelatihan' => -1], 'biaya_pelatihan'],
            'cost beyond int32' => [['biaya_tiket_peserta' => 3000000000], 'biaya_tiket_peserta'],
            'more than 24 hours a day' => [['jam_belajar_per_hari' => 25], 'jam_belajar_per_hari'],
            'zero hours a day' => [['jam_belajar_per_hari' => 0], 'jam_belajar_per_hari'],
            'missing title' => [['judul' => ''], 'judul'],
        ];
    }

    public function test_end_date_equal_to_start_date_is_allowed(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.trainings.store'), $this->payload(['tanggal_mulai' => '2026-03-02', 'tanggal_akhir' => '2026-03-02']))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('trainings', 1);
    }

    public function test_validation_messages_use_indonesian_business_terms(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.trainings.store'), $this->payload(['tanggal_mulai' => '2026-03-10', 'tanggal_akhir' => '2026-03-01']));

        $this->assertSame(
            'Tanggal Akhir tidak boleh lebih awal dari Tanggal Mulai.',
            session('errors')->first('tanggal_akhir'),
        );
    }

    public function test_update_recalculates_man_hours_and_logs_old_and_new_values(): void
    {
        $training = $this->makeTraining();
        $training->employees()->attach(Employee::factory()->count(2)->create()->pluck('nik'));

        $this->actingAs($this->admin)
            ->put(route('admin.trainings.update', $training), $this->payload(['jam_belajar_per_hari' => 6]))
            ->assertRedirect(route('admin.trainings.show', $training->id));

        // 3 hari x 6 jam x 2 peserta
        $this->assertSame(36, (int) $training->fresh()->jumlah_man_hours);

        $log = MasterAuditLog::where('action', 'training.updated')->firstOrFail();
        $this->assertSame(['jam_belajar_per_hari' => 8], $log->old_values);
        $this->assertSame(['jam_belajar_per_hari' => 6], $log->new_values);
    }

    public function test_update_without_changes_writes_no_audit_entry(): void
    {
        $training = $this->makeTraining();

        $this->actingAs($this->admin)->put(route('admin.trainings.update', $training), $this->payload());

        $this->assertDatabaseMissing('master_audit_logs', ['action' => 'training.updated']);
    }

    public function test_destroy_deletes_the_training_and_logs_the_participant_count(): void
    {
        $training = $this->makeTraining();
        $training->employees()->attach(Employee::factory()->create()->nik);

        $this->actingAs($this->admin)
            ->delete(route('admin.trainings.destroy', $training->id), ['alasan' => 'Data ganda hasil impor'])
            ->assertRedirect(route('admin.trainings.index'));

        $this->assertModelMissing($training);

        $log = MasterAuditLog::where('action', 'training.deleted')->firstOrFail();
        $this->assertSame(['jumlah' => 1], $log->new_values);
        $this->assertSame('Data ganda hasil impor', $log->reason);
    }

    /** @dataProvider invalidReasons */
    public function test_destroy_requires_a_valid_reason_and_changes_nothing_without_it(?string $reason, string $message): void
    {
        $training = $this->makeTraining();

        $this->actingAs($this->admin)
            ->from(route('admin.trainings.index'))
            ->delete(route('admin.trainings.destroy', $training->id), $reason === null ? [] : ['alasan' => $reason])
            ->assertRedirect(route('admin.trainings.index'))
            ->assertSessionHas('error', $message);

        $this->assertModelExists($training);
        $this->assertDatabaseMissing('master_audit_logs', ['action' => 'training.deleted']);
    }

    /** @return array<string, array{0: string|null, 1: string}> */
    public static function invalidReasons(): array
    {
        return [
            'missing' => [null, 'Alasan penghapusan wajib diisi (minimal 10 karakter).'],
            'too short' => ['singkat', 'Alasan penghapusan minimal 10 karakter.'],
            'too long' => [str_repeat('a', 501), 'Alasan penghapusan maksimal 500 karakter.'],
        ];
    }

    public function test_non_admin_cannot_delete_a_training_even_with_a_reason(): void
    {
        $training = $this->makeTraining();

        $this->actingAs(User::factory()->create(['role' => 'user']))
            ->delete(route('admin.trainings.destroy', $training->id), ['alasan' => 'Data ganda hasil impor'])
            ->assertForbidden();

        $this->assertModelExists($training);
    }

    public function test_failed_audit_write_rolls_back_the_training(): void
    {
        MasterAuditLog::creating(fn () => throw new \RuntimeException('audit down'));

        try {
            $this->actingAs($this->admin)->withoutExceptionHandling()
                ->post(route('admin.trainings.store'), $this->payload());
            $this->fail('Expected the audit failure to surface.');
        } catch (\RuntimeException) {
            $this->assertDatabaseCount('trainings', 0);
        }
    }

    public function test_jenis_metode_and_bidang_must_come_from_the_standard_lists(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.trainings.store'), $this->payload([
                'jenis' => 'Bebas Ketik',
                'metode' => 'Bebas Ketik',
                'bidang_pelatihan' => 'XYZ',
            ]))
            ->assertSessionHasErrors(['jenis', 'metode', 'bidang_pelatihan']);

        $this->assertSame(
            'Jenis Pelatihan harus dipilih dari daftar yang tersedia.',
            session('errors')->first('jenis'),
        );
        $this->assertDatabaseCount('trainings', 0);
    }

    public function test_update_keeps_a_legacy_value_stored_before_the_standard_lists(): void
    {
        $training = $this->makeTraining();
        $training->forceFill(['jenis' => 'Teknis', 'metode' => 'Tatap Muka', 'bidang_pelatihan' => 'TEK-LAMA'])->save();

        $this->actingAs($this->admin)
            ->put(route('admin.trainings.update', $training), $this->payload([
                'jenis' => 'Teknis',
                'metode' => 'Tatap Muka',
                'bidang_pelatihan' => 'TEK-LAMA',
                'judul' => 'Judul Baru',
            ]))
            ->assertSessionHasNoErrors();

        $this->assertSame('Judul Baru', $training->fresh()->judul);

        // Nilai lama hanya berlaku untuk pelatihan itu sendiri, bukan untuk pelatihan baru.
        $this->actingAs($this->admin)
            ->post(route('admin.trainings.store'), $this->payload(['jenis' => 'Teknis']))
            ->assertSessionHasErrors('jenis');
    }

    public function test_form_offers_standard_dropdowns_and_keeps_a_legacy_option_when_editing(): void
    {
        $training = $this->makeTraining();
        $training->forceFill(['jenis' => 'Teknis'])->save();
        $this->actingAs($this->admin);

        $this->get(route('admin.trainings.create'))
            ->assertOk()
            ->assertSee('<option value="Teknis Pabrik"', false)
            ->assertSee('<option value="E-Learning"', false)
            ->assertSee('<option value="TEK"', false)
            ->assertSee('TEK (Teknik &amp; Pengolahan)', false)
            ->assertSee('id="keterangan"', false)
            ->assertSee('max-w-5xl', false);

        $this->get(route('admin.trainings.edit', $training))
            ->assertOk()
            ->assertSee('<option value="Teknis" selected', false);
    }

    public function test_create_form_follows_the_shared_sectioned_layout(): void
    {
        $this->actingAs($this->admin);

        $this->get(route('admin.trainings.create'))
            ->assertOk()
            ->assertSee('Informasi Program Pelatihan')
            ->assertSee('Jadwal, Durasi &amp; Biaya', false)
            ->assertSee('Penyelenggara &amp; Surat Tugas', false)
            ->assertSee('form-section-3-title', false)
            ->assertSee('Kolom bertanda')
            ->assertSee('sticky bottom-4', false)
            ->assertSee('Simpan Pelatihan')
            ->assertSee('Kembali ke Daftar Pelatihan')
            ->assertSee('Contoh: SRT/001/DIR/2026', false)
            ->assertSee('max="24"', false);
    }

    public function test_edit_form_prefills_dates_in_the_date_input_format(): void
    {
        $training = $this->makeTraining();
        $this->actingAs($this->admin);

        $this->get(route('admin.trainings.edit', $training))
            ->assertOk()
            ->assertSee('value="2026-03-02"', false)
            ->assertSee('value="2026-03-04"', false)
            ->assertSee('value="2026-02-20"', false)
            ->assertSee('Simpan Perubahan')
            ->assertSee('Kembali ke Detail Pelatihan');
    }

    public function test_failed_validation_shows_the_error_summary_with_inline_messages(): void
    {
        $this->actingAs($this->admin);

        $this->followingRedirects()
            ->from(route('admin.trainings.create'))
            ->post(route('admin.trainings.store'), $this->payload(['tanggal_mulai' => '2026-03-10', 'tanggal_akhir' => '2026-03-01']))
            ->assertSee('isian perlu diperbaiki')
            ->assertSee('Tanggal Akhir tidak boleh lebih awal dari Tanggal Mulai.')
            ->assertSee('href="#tanggal_akhir"', false);
    }

    public function test_create_and_edit_pages_render_the_page_header(): void
    {
        $training = $this->makeTraining();
        $this->actingAs($this->admin);

        $this->get(route('admin.trainings.create'))->assertOk()->assertSee('Tambah Data Pelatihan');
        $this->get(route('admin.trainings.edit', $training))->assertOk()->assertSee('Edit Data Pelatihan');
    }
}
