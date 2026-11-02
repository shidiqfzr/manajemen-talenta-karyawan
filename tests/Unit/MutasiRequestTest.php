<?php

namespace Tests\Unit;

use App\Services\Data\MutasiRequest;
use Carbon\Carbon;
use Tests\TestCase;

class MutasiRequestTest extends TestCase
{
    private function make(array $overrides = []): MutasiRequest
    {
        return new MutasiRequest(...array_merge([
            'employeeNik' => '12345678',
            'targetJabatan' => 'Asisten Tanaman',
            'targetUnitKerja' => 'Kebun A',
            'tmtAwal' => now()->toDateString(),
        ], $overrides));
    }

    public function test_blank_optional_strings_are_normalised_to_null(): void
    {
        $request = $this->make([
            'nomorSk' => '',
            'tanggalSk' => '  ',
            'catatan' => '',
            'tanggalBerakhirSanksi' => '',
            'tmtAkhirEstimasi' => '',
            'fileSk' => '',
            'alasanDispensasi' => ' ',
            'newGolongan' => '',
            'newJobGrade' => '',
        ]);

        $this->assertNull($request->nomorSk);
        $this->assertNull($request->tanggalSk);
        $this->assertNull($request->catatan);
        $this->assertNull($request->tanggalBerakhirSanksi);
        $this->assertNull($request->tmtAkhirEstimasi);
        $this->assertNull($request->fileSk);
        $this->assertNull($request->alasanDispensasi);
        $this->assertNull($request->newGolongan);
        $this->assertNull($request->newJobGrade);
    }

    public function test_defaults_match_a_definitive_unit_transfer(): void
    {
        $request = $this->make();

        $this->assertSame('MUTASI_UNIT', $request->jenisMutasi);
        $this->assertSame('Definitif', $request->statusJabatan);
        $this->assertTrue($request->isDefinitif());
        $this->assertFalse($request->isDispensasi);
        $this->assertSame('Mutasi ke Asisten Tanaman di Kebun A', $request->defaultCatatan());
    }

    public function test_future_tmt_is_staged_while_today_and_past_are_applied(): void
    {
        $this->assertFalse($this->make(['tmtAwal' => now()->toDateString()])->isFuture());
        $this->assertFalse($this->make(['tmtAwal' => now()->subDay()->toDateString()])->isFuture());
        $this->assertTrue($this->make(['tmtAwal' => now()->addDay()->toDateString()])->isFuture());
    }

    public function test_previous_job_ends_the_day_before_the_tmt(): void
    {
        $request = $this->make(['tmtAwal' => '2026-11-01']);

        $this->assertSame('2026-10-31', $request->tmtAkhir());
    }

    public function test_dispensation_only_counts_with_a_written_reason(): void
    {
        $this->assertFalse($this->make(['isDispensasi' => true])->hasDispensasi());
        $this->assertFalse($this->make(['isDispensasi' => true, 'alasanDispensasi' => ' '])->hasDispensasi());
        $this->assertFalse($this->make(['isDispensasi' => false, 'alasanDispensasi' => 'Nota Dinas'])->hasDispensasi());
        $this->assertTrue($this->make(['isDispensasi' => true, 'alasanDispensasi' => 'Nota Dinas'])->hasDispensasi());
    }

    public function test_chain_node_combines_collective_sk_header_with_per_employee_choices(): void
    {
        $header = [
            'nomor_sk' => 'SK/KOLEKTIF/01',
            'tanggal_sk' => '2026-10-20',
            'tmt_awal' => '2026-11-01',
            'file_sk' => 'dokumen_sk/kolektif.pdf',
            'created_by' => 7,
            'catatan' => 'Penyegaran organisasi',
        ];
        $node = [
            'employee_nik' => '12345678',
            'target_jabatan' => 'Askep Tanaman',
            'target_unit_kerja' => 'Kebun B',
            'jenis_mutasi' => 'PROMOSI',
            'status_jabatan' => 'Definitif',
            'catatan' => '',
            'tmt_akhir_estimasi' => '',
            'is_dispensasi' => true,
            'alasan_dispensasi' => '[Dispensasi Promosi]: Rekomendasi BOD',
            'new_golongan' => 'IIIC/00',
            'new_job_grade' => 13,
        ];

        $request = MutasiRequest::fromChainNode($header, $node);

        $this->assertSame('12345678', $request->employeeNik);
        $this->assertSame('Askep Tanaman', $request->targetJabatan);
        $this->assertSame('Kebun B', $request->targetUnitKerja);
        $this->assertSame('PROMOSI', $request->jenisMutasi);
        $this->assertSame('SK/KOLEKTIF/01', $request->nomorSk);
        $this->assertSame('2026-11-01', $request->tmtAwal);
        $this->assertSame('dokumen_sk/kolektif.pdf', $request->fileSk);
        $this->assertSame(7, $request->createdBy);
        $this->assertSame('IIIC/00', $request->newGolongan);
        $this->assertSame('13', $request->newJobGrade);
        $this->assertTrue($request->hasDispensasi());
        // Blank node note falls back to the collective SK note
        $this->assertSame('[SK/KOLEKTIF/01] Penyegaran organisasi', $request->catatan);
        $this->assertNull($request->tmtAkhirEstimasi);
    }

    public function test_chain_node_without_any_note_uses_default_catatan(): void
    {
        $request = MutasiRequest::fromChainNode(
            ['tmt_awal' => now()->toDateString(), 'created_by' => 1],
            ['employee_nik' => '12345678', 'target_jabatan' => 'Asisten Tanaman', 'target_unit_kerja' => 'Kebun A']
        );

        $this->assertNull($request->catatan);
        $this->assertSame('Mutasi ke Asisten Tanaman di Kebun A', $request->defaultCatatan());
        $this->assertSame('MUTASI_UNIT', $request->jenisMutasi);
        $this->assertTrue($request->isDefinitif());
    }

    public function test_is_future_follows_the_system_clock(): void
    {
        Carbon::setTestNow('2026-10-26 15:30:00');

        try {
            $this->assertFalse($this->make(['tmtAwal' => '2026-10-26'])->isFuture());
            $this->assertTrue($this->make(['tmtAwal' => '2026-10-27'])->isFuture());
        } finally {
            Carbon::setTestNow();
        }
    }
}
