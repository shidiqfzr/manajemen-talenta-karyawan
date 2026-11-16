<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Evaluation;
use App\Models\MasterAuditLog;
use App\Models\User;
use App\Services\Mutasi\MutasiPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * SUT: App\Http\Controllers\Admin\EvaluationController (simpan, ubah, hapus Penilaian Karyawan),
 * termasuk EvaluationRequest dan EvaluationService yang dipanggilnya.
 */
class EvaluationControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Employee $employee;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->employee = Employee::factory()->create();
    }

    /** @param array<string, mixed> $overrides */
    private function payload(array $overrides = []): array
    {
        return $overrides + [
            'employee_nik' => $this->employee->nik,
            'nilai_kepemimpinan' => 8,
            'nilai_perilaku_budaya' => 7,
            'nilai_pengalaman_teknis' => 6,
            'nilai_kematangan_pribadi' => 5,
            'skor_smkbk_9box' => 80,
            'skor_cli_9box' => 70,
            'kategori_9box' => 'Box 9: Star',
        ];
    }

    private function makeEvaluation(): Evaluation
    {
        return Evaluation::create($this->payload());
    }

    public function test_non_admin_and_guest_cannot_reach_evaluation_routes(): void
    {
        $this->post(route('admin.evaluations.store'), $this->payload())->assertRedirect();
        $this->actingAs(User::factory()->create(['role' => 'user']))
            ->post(route('admin.evaluations.store'), $this->payload())
            ->assertForbidden();

        $this->assertDatabaseCount('evaluations', 0);
    }

    public function test_store_saves_the_evaluation_with_weighted_score_and_audit_entry(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.evaluations.store'), $this->payload())
            ->assertRedirect(route('admin.evaluations.index'));

        $evaluation = Evaluation::firstOrFail();
        // 8*0.4 + 7*0.3 + 6*0.2 + 5*0.1
        $this->assertEquals(7.0, (float) $evaluation->nilai_tertimbang);

        $log = MasterAuditLog::where('action', 'evaluation.created')->firstOrFail();
        $this->assertSame($this->admin->id, $log->user_id);
        $this->assertSame('evaluation', $log->entity_type);
        $this->assertSame($evaluation->id, $log->entity_id);
    }

    public function test_audit_entry_never_contains_the_confidential_scores(): void
    {
        $this->actingAs($this->admin)->post(route('admin.evaluations.store'), $this->payload());

        $stored = json_encode(MasterAuditLog::all()->toArray());

        $this->assertStringNotContainsString('Box 9', $stored);
        $this->assertStringNotContainsString('"80', $stored);
        $this->assertStringContainsString('Skor SMK-BK', $stored);
    }

    /**
     * @dataProvider invalidInputs
     *
     * @param  array<string, mixed>  $override
     */
    public function test_store_rejects_invalid_input(array $override, string $field): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.evaluations.store'), $this->payload($override))
            ->assertSessionHasErrors($field);

        $this->assertDatabaseCount('evaluations', 0);
    }

    /** @return array<string, array{0: array<string, mixed>, 1: string}> */
    public static function invalidInputs(): array
    {
        return [
            'nilai above range' => [['nilai_kepemimpinan' => 11], 'nilai_kepemimpinan'],
            'nilai negative' => [['nilai_perilaku_budaya' => -1], 'nilai_perilaku_budaya'],
            'skor above range' => [['skor_smkbk_9box' => 9999], 'skor_smkbk_9box'],
            'skor not numeric' => [['skor_cli_9box' => 'tinggi'], 'skor_cli_9box'],
            'nik not 8 digits' => [['employee_nik' => '123'], 'employee_nik'],
            'nik unknown' => [['employee_nik' => '99999999'], 'employee_nik'],
            'expired before assessment' => [['tanggal_pelaksanaan_asesmen' => '2026-05-01', 'expired_asesmen' => '2026-04-01'], 'expired_asesmen'],
            'kategori free text' => [['kategori_9box' => 'Solid Performer'], 'kategori_9box'],
            'kategori unknown box' => [['kategori_9box' => 'Box 10: Super'], 'kategori_9box'],
        ];
    }

    public function test_validation_messages_use_indonesian_business_terms(): void
    {
        $response = $this->actingAs($this->admin)
            ->from(route('admin.evaluations.create'))
            ->post(route('admin.evaluations.store'), $this->payload(['nilai_kepemimpinan' => 11]));

        $this->assertSame(
            'Nilai Kepemimpinan harus berada pada rentang 0 sampai 10.',
            session('errors')->first('nilai_kepemimpinan'),
        );
        $response->assertRedirect(route('admin.evaluations.create'));
    }

    public function test_update_records_only_the_changed_column_names(): void
    {
        $evaluation = $this->makeEvaluation();

        $this->actingAs($this->admin)
            ->put(route('admin.evaluations.update', $evaluation), $this->payload(['skor_cli_9box' => 90]))
            ->assertRedirect(route('admin.evaluations.index'));

        $this->assertEquals(90, (float) $evaluation->fresh()->skor_cli_9box);

        $log = MasterAuditLog::where('action', 'evaluation.updated')->firstOrFail();
        $this->assertSame(['Skor CLI'], $log->new_values['kolom_diubah']);
    }

    public function test_update_without_changes_writes_no_audit_entry(): void
    {
        $evaluation = $this->makeEvaluation();

        $this->actingAs($this->admin)->put(route('admin.evaluations.update', $evaluation), $this->payload());

        $this->assertDatabaseMissing('master_audit_logs', ['action' => 'evaluation.updated']);
    }

    public function test_update_cannot_reassign_the_evaluation_to_another_employee(): void
    {
        $evaluation = $this->makeEvaluation();
        $other = Employee::factory()->create();

        $this->actingAs($this->admin)
            ->put(route('admin.evaluations.update', $evaluation), $this->payload(['employee_nik' => $other->nik]));

        $this->assertSame($this->employee->nik, $evaluation->fresh()->employee_nik);
    }

    public function test_destroy_deletes_the_evaluation_and_logs_it(): void
    {
        $evaluation = $this->makeEvaluation();

        $this->actingAs($this->admin)
            ->delete(route('admin.evaluations.destroy', $evaluation), ['alasan' => 'Data ganda hasil impor'])
            ->assertRedirect(route('admin.evaluations.index'));

        $this->assertModelMissing($evaluation);
        $this->assertDatabaseHas('master_audit_logs', [
            'action' => 'evaluation.deleted',
            'entity_type' => 'evaluation',
            'entity_id' => $evaluation->id,
            'reason' => 'Data ganda hasil impor',
        ]);
    }

    /** @dataProvider invalidReasons */
    public function test_destroy_requires_a_valid_reason_and_changes_nothing_without_it(?string $reason, string $message): void
    {
        $evaluation = $this->makeEvaluation();

        $this->actingAs($this->admin)
            ->from(route('admin.evaluations.index'))
            ->delete(route('admin.evaluations.destroy', $evaluation), $reason === null ? [] : ['alasan' => $reason])
            ->assertRedirect(route('admin.evaluations.index'))
            ->assertSessionHas('error', $message);

        $this->assertModelExists($evaluation);
        $this->assertDatabaseMissing('master_audit_logs', ['action' => 'evaluation.deleted']);
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

    public function test_the_deletion_reason_never_leaks_the_scores_into_the_audit_entry(): void
    {
        $evaluation = $this->makeEvaluation();

        $this->actingAs($this->admin)->delete(route('admin.evaluations.destroy', $evaluation), ['alasan' => 'Data ganda hasil impor']);

        $log = MasterAuditLog::where('action', 'evaluation.deleted')->firstOrFail();
        $this->assertNull($log->old_values);
        $this->assertNull($log->new_values);
    }

    public function test_store_accepts_each_of_the_nine_standard_boxes(): void
    {
        $this->actingAs($this->admin);

        foreach (range(1, 9) as $number) {
            $this->post(route('admin.evaluations.store'), $this->payload(['kategori_9box' => Evaluation::boxValue($number)]))
                ->assertSessionHasNoErrors();
        }

        $this->assertDatabaseCount('evaluations', 9);
        $this->assertDatabaseHas('evaluations', ['kategori_9box' => 'Box 9: Star']);
    }

    public function test_store_accepts_an_empty_category(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.evaluations.store'), $this->payload(['kategori_9box' => null]))
            ->assertSessionHasNoErrors();

        $this->assertNull(Evaluation::firstOrFail()->kategori_9box);
    }

    public function test_update_keeps_a_legacy_category_but_new_evaluations_cannot_use_it(): void
    {
        $evaluation = $this->makeEvaluation();
        $evaluation->forceFill(['kategori_9box' => 'Solid Performer'])->save();

        $this->actingAs($this->admin)
            ->put(route('admin.evaluations.update', $evaluation), $this->payload(['kategori_9box' => 'Solid Performer', 'skor_cli_9box' => 75]))
            ->assertSessionHasNoErrors();

        $this->assertSame('Solid Performer', $evaluation->fresh()->kategori_9box);

        $this->post(route('admin.evaluations.store'), $this->payload(['kategori_9box' => 'Solid Performer']))
            ->assertSessionHasErrors('kategori_9box');
    }

    public function test_category_dropdown_lists_the_boxes_with_their_meaning_and_keeps_a_legacy_option(): void
    {
        $evaluation = $this->makeEvaluation();
        $evaluation->forceFill(['kategori_9box' => 'Solid Performer'])->save();
        $this->actingAs($this->admin);

        $this->get(route('admin.evaluations.create'))
            ->assertOk()
            ->assertSee('<option value="Box 9: Star"', false)
            ->assertSee('Box 9: Star (Potensi Tinggi, Kinerja Tinggi)', false)
            ->assertSee('Box 1: Risk (Potensi Rendah, Kinerja Rendah)', false)
            ->assertSee('Box 5: Core Talent (Potensi Sedang, Kinerja Sedang)', false)
            ->assertDontSee('<option value="Solid Performer"', false);

        $this->get(route('admin.evaluations.edit', $evaluation))
            ->assertSee('<option value="Solid Performer" selected', false);
    }

    public function test_box_numbering_follows_the_matrix_in_the_domain_document(): void
    {
        // Baris naik menurut potensi, kolom naik menurut kinerja (DOMAIN.md §6).
        $this->assertSame('Potensi Tinggi, Kinerja Rendah', Evaluation::boxMeaning(7));
        $this->assertSame('Potensi Tinggi, Kinerja Sedang', Evaluation::boxMeaning(8));
        $this->assertSame('Potensi Sedang, Kinerja Rendah', Evaluation::boxMeaning(4));
        $this->assertSame('Potensi Rendah, Kinerja Tinggi', Evaluation::boxMeaning(3));
        $this->assertSame('Box 8: High Potential', Evaluation::boxValue(8));
    }

    public function test_stored_box_values_satisfy_the_mutation_rule_for_good_evaluations(): void
    {
        $good = collect(range(1, 9))
            ->filter(fn (int $n) => Str::contains(Evaluation::boxValue($n), MutasiPolicy::GOOD_EVALUATION_BOXES))
            ->values()->all();

        $this->assertSame([5, 6, 8, 9], $good);
    }

    public function test_failed_audit_write_rolls_back_the_evaluation(): void
    {
        MasterAuditLog::creating(fn () => throw new \RuntimeException('audit down'));

        try {
            $this->actingAs($this->admin)->withoutExceptionHandling()
                ->post(route('admin.evaluations.store'), $this->payload());
            $this->fail('Expected the audit failure to surface.');
        } catch (\RuntimeException) {
            $this->assertDatabaseCount('evaluations', 0);
        }
    }

    public function test_create_form_uses_a_searchable_picker_of_active_employees_and_business_labels(): void
    {
        $this->actingAs($this->admin);

        $this->get(route('admin.evaluations.create'))
            ->assertOk()
            ->assertSee('name="employee_nik"', false)
            ->assertSee($this->employee->nik.' - '.$this->employee->nama, false)
            ->assertSee('Masa Berlaku Asesmen')
            ->assertSee('Skor Matriks Talenta (9-Box)')
            ->assertDontSee('Expired Asesmen')
            ->assertDontSee('(Auto)')
            ->assertSee('max-w-5xl', false);
    }

    public function test_create_form_follows_the_shared_sectioned_layout(): void
    {
        $this->actingAs($this->admin);

        $this->get(route('admin.evaluations.create'))
            ->assertOk()
            ->assertSee('Karyawan yang Dinilai')
            ->assertSee('Nilai Per Kriteria')
            ->assertSee('Asesmen &amp; Catatan', false)
            ->assertSee('form-section-4-title', false)
            ->assertSee('Kolom bertanda')
            ->assertSee('sticky bottom-4', false)
            ->assertSee('Simpan Penilaian')
            ->assertSee('Kembali ke Daftar Penilaian')
            ->assertSee('-- Belum Dikategorikan --')
            ->assertSee('name="keterangan"', false);
    }

    public function test_criteria_hints_show_the_weights_from_the_single_source(): void
    {
        $this->actingAs($this->admin);

        $this->get(route('admin.evaluations.create'))
            ->assertSee('Skala 0 sampai 10, bobot 40%')
            ->assertSee('Skala 0 sampai 10, bobot 30%')
            ->assertSee('Skala 0 sampai 10, bobot 20%')
            ->assertSee('Skala 0 sampai 10, bobot 10%');
    }

    public function test_edit_form_does_not_prefill_missing_dates_with_today(): void
    {
        $evaluation = $this->makeEvaluation();
        $this->actingAs($this->admin);

        $this->get(route('admin.evaluations.edit', $evaluation))
            ->assertOk()
            ->assertDontSee('value="'.now()->format('Y-m-d').'"', false)
            ->assertSee('Simpan Perubahan');
    }

    public function test_edit_form_prefills_stored_dates_and_fixes_the_employee(): void
    {
        $evaluation = Evaluation::create($this->payload([
            'tanggal_pelaksanaan_asesmen' => '2026-01-15',
            'expired_asesmen' => '2028-01-15',
        ]));
        $this->actingAs($this->admin);

        $this->get(route('admin.evaluations.edit', $evaluation))
            ->assertOk()
            ->assertSee('value="2026-01-15"', false)
            ->assertSee('value="2028-01-15"', false)
            ->assertSee($this->employee->nik.' - '.$this->employee->nama, false)
            ->assertDontSee('name="employee_nik"', false);
    }

    public function test_pages_render_without_error(): void
    {
        $evaluation = $this->makeEvaluation();
        $this->actingAs($this->admin);

        $this->get(route('admin.evaluations.create'))->assertOk()->assertSee('Tambah Penilaian Karyawan');
        $this->get(route('admin.evaluations.edit', $evaluation))->assertOk()->assertSee('Edit Penilaian Karyawan');
    }
}
