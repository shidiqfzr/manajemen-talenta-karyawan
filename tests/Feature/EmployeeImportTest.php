<?php

namespace Tests\Feature;

use App\Exports\EmployeeTemplateExport;
use App\Exports\Sheets\EmployeeTemplateDataSheet;
use App\Exports\Sheets\EmployeeTemplateReferenceSheet;
use App\Models\Employee;
use App\Models\JobHistory;
use App\Models\Position;
use App\Models\Unit;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class EmployeeImportTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected Unit $validUnit;

    protected Position $validKarpimPos;

    protected Position $validKarpelPos;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => 'admin']);

        // Persiapkan master unit & formasi resmi
        $this->validUnit = Unit::firstOrCreate(
            ['nama' => 'Kebun Inti Gunung Meliau'],
            ['kode' => 'KIGM', 'wilayah' => 'Wilayah Kalimantan Barat', 'is_active' => true]
        );

        $this->validKarpimPos = Position::firstOrCreate(
            ['nama' => 'Asisten Afdeling', 'level' => 'Karpim'],
            ['is_active' => true]
        );

        $this->validKarpelPos = Position::firstOrCreate(
            ['nama' => 'Mandor Panen', 'level' => 'Karpel'],
            ['is_active' => true]
        );

        $this->validUnit->positions()->syncWithoutDetaching([
            $this->validKarpimPos->id => ['kuota' => 5],
            $this->validKarpelPos->id => ['kuota' => 10],
        ]);
    }

    /**
     * Helper untuk membuat file Excel fisik sementara untuk pengujian HTTP upload.
     */
    private function createExcelUploadFile(array $rows, string $filename = 'test_import.xlsx'): UploadedFile
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();

        $headers = [
            'NIK*', 'NAMA*', 'JENIS KELAMIN', 'TEMPAT LAHIR', 'TANGGAL LAHIR',
            'AGAMA', 'SUSUNAN KELUARGA', 'STATUS KARYAWAN*', 'JABATAN*', 'LEVEL*',
            'UNIT KERJA*', 'GOLONGAN', 'RM LEVEL', 'STATUS PENUGASAN',
            'TMT BEKERJA', 'TANGGAL DIANGKAT STAF', 'TANGGAL DALAM JABATAN',
            'TMT UNIT KERJA', 'JALUR MASUK', 'JOB GRADE', 'PERSON GRADE',
            'PENDIDIKAN TERAKHIR', 'SEKOLAH', 'TANGGAL MBT', 'TANGGAL PENSIUN',
        ];

        $sheet->fromArray([$headers], null, 'A1');

        if (! empty($rows)) {
            $sheet->fromArray($rows, null, 'A2');
        }

        $tempPath = tempnam(sys_get_temp_dir(), 'test_excel_').'.xlsx';
        $writer = new Xlsx($spreadsheet);
        $writer->save($tempPath);

        return new UploadedFile($tempPath, $filename, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
    }

    public function test_guest_cannot_access_import_endpoint(): void
    {
        $response = $this->post(route('admin.employees.import'), []);
        $response->assertRedirect(route('login'));
    }

    public function test_import_validation_rejects_non_excel_files_and_empty_requests(): void
    {
        // 1. Kosong tanpa file
        $response = $this->actingAs($this->admin)->post(route('admin.employees.import'), []);
        $response->assertSessionHasErrors(['import_file']);

        // 2. Berkas PDF palsu
        $fakePdf = UploadedFile::fake()->create('document.pdf', 100, 'application/pdf');
        $responsePdf = $this->actingAs($this->admin)->post(route('admin.employees.import'), [
            'import_file' => $fakePdf,
        ]);
        $responsePdf->assertSessionHasErrors(['import_file']);
    }

    public function test_successful_import_with_auto_healing_nik_and_retirement_calculation(): void
    {
        // NIK 7-digit numerik untuk menguji auto-healing leading zero -> harus menjadi '09911223'
        $rows = [
            [
                '9911223', // NIK 7 digit
                'Budi Hendrawan Testing',
                'L',
                'Pontianak',
                '1990-05-15',
                'Islam',
                'K/1',
                'PKWTT',
                'Asisten Afdeling',
                'Karpim',
                'Kebun Inti Gunung Meliau',
                'IIIA/00',
                'RM-3',
                'Definitif',
                '2014-01-01',
                '2016-01-01',
                '2020-01-01',
                '2020-01-01',
                'Reguler',
                11,
                11,
                'D4 / S1',
                'INSTIPER',
                '', // Tanggal MBT dikosongkan agar auto-calculate
                '', // Tanggal Pensiun dikosongkan agar auto-calculate
            ],
        ];

        $file = $this->createExcelUploadFile($rows);

        $response = $this->actingAs($this->admin)->post(route('admin.employees.import'), [
            'import_file' => $file,
        ]);

        $response->assertRedirect(route('admin.employees.index'));
        $response->assertSessionHas('success');
        $response->assertSessionHas('import_summary');

        // Pastikan NIK tersimpan dengan padding 8 digit
        $this->assertDatabaseHas('employees', [
            'nik' => '09911223',
            'nama' => 'BUDI HENDRAWAN TESTING',
            'jabatan' => 'Asisten Afdeling',
            'unit_kerja' => 'Kebun Inti Gunung Meliau',
            'level' => 'Karpim',
            'rm_level' => 'RM-3',
            'status_karyawan' => 'Tetap (PKWTT)',
        ]);

        $emp = Employee::where('nik', '09911223')->first();
        $this->assertNotNull($emp);

        // Verifikasi kalkulasi pensiun (56 tahun) & MBT (55 tahun)
        $expectedPensiun = Carbon::parse('1990-05-15')->addYears(56)->startOfMonth()->toDateString();
        $expectedMbt = Carbon::parse($expectedPensiun)->subYear()->toDateString();
        $this->assertEquals($expectedPensiun, $emp->tanggal_pensiun->toDateString());
        $this->assertEquals($expectedMbt, $emp->tanggal_mbt->toDateString());

        // Verifikasi audit trail initial JobHistory dibuat otomatis via Observer
        $this->assertDatabaseHas('job_histories', [
            'employee_nik' => '09911223',
            'jabatan' => 'Asisten Afdeling',
            'unit_kerja' => 'Kebun Inti Gunung Meliau',
            'status_penugasan' => 'Definitif',
        ]);
    }

    public function test_import_updates_existing_employee_when_job_information_unchanged(): void
    {
        // Buat pegawai eksisting
        Employee::create([
            'nik' => '12345678',
            'nama' => 'AGUS SALIM',
            'jabatan' => 'Asisten Afdeling',
            'unit_kerja' => 'Kebun Inti Gunung Meliau',
            'level' => 'Karpim',
            'rm_level' => 'RM-3',
            'status_karyawan' => 'Tetap (PKWTT)',
            'agama' => 'Islam',
            'susunan_keluarga' => 'TK',
        ]);

        // Kirim update Excel dengan info profil berubah (misal susunan keluarga menjadi K/1)
        $rows = [
            [
                '12345678',
                'AGUS SALIM',
                'L',
                'Medan',
                '1985-02-10',
                'Islam',
                'K/1', // Update tanggungan
                'PKWTT',
                'Asisten Afdeling', // Tetap
                'Karpim', // Tetap
                'Kebun Inti Gunung Meliau', // Tetap
                'IIIB/02',
                'RM-3',
                'Definitif',
                '2010-01-01',
                '2012-01-01',
                '2020-01-01',
                '2020-01-01',
                'Reguler',
                12,
                12,
                'D4 / S1',
                'UGM',
                '',
                '',
            ],
        ];

        $file = $this->createExcelUploadFile($rows);

        $response = $this->actingAs($this->admin)->post(route('admin.employees.import'), [
            'import_file' => $file,
        ]);

        $response->assertRedirect(route('admin.employees.index'));
        $summary = session('import_summary');
        $this->assertEquals(0, $summary['created']);
        $this->assertEquals(1, $summary['updated']);
        $this->assertEquals(0, $summary['skipped']);

        $this->assertDatabaseHas('employees', [
            'nik' => '12345678',
            'susunan_keluarga' => 'K/1',
        ]);
    }

    public function test_import_rejects_mutation_bypass_on_existing_employee(): void
    {
        Employee::create([
            'nik' => '87654321',
            'nama' => 'RUDI HERMAWAN',
            'jabatan' => 'Asisten Afdeling',
            'unit_kerja' => 'Kebun Inti Gunung Meliau',
            'level' => 'Karpim',
            'rm_level' => 'RM-3',
            'status_karyawan' => 'Tetap (PKWTT)',
        ]);

        // Coba ganti jabatan via Excel
        $rows = [
            [
                '87654321',
                'RUDI HERMAWAN',
                'L',
                'Jakarta',
                '1985-01-01',
                'Islam',
                'K/0',
                'PKWTT',
                'Askep Tanaman', // Berbeda dari database!
                'Karpim',
                'Kebun Inti Gunung Meliau',
                'IIIC/00',
                'RM-2',
                'Definitif',
            ],
        ];

        $file = $this->createExcelUploadFile($rows);

        $response = $this->actingAs($this->admin)->post(route('admin.employees.import'), [
            'import_file' => $file,
        ]);

        $response->assertRedirect(route('admin.employees.index'));
        $summary = session('import_summary');

        $this->assertEquals(1, $summary['skipped']);
        $this->assertTrue($summary['has_errors']);
        $this->assertStringContainsString('Gunakan Modul Mutasi Karyawan', $summary['errors'][0]['errors'][0]);

        // Jabatan di database tetap tidak berubah
        $this->assertDatabaseHas('employees', [
            'nik' => '87654321',
            'jabatan' => 'Asisten Afdeling',
        ]);
    }

    public function test_import_rejects_unregistered_master_unit(): void
    {
        $rows = [
            [
                '99001122',
                'KARYAWAN UNIT FIKTIF',
                'L',
                'Pontianak',
                '1992-01-01',
                'Islam',
                'TK',
                'PKWTT',
                'Asisten Afdeling',
                'Karpim',
                'Unit Siluman Fiktif', // Tidak ada di master data
                'IIIA/00',
            ],
        ];

        $file = $this->createExcelUploadFile($rows);

        $response = $this->actingAs($this->admin)->post(route('admin.employees.import'), [
            'import_file' => $file,
        ]);

        $summary = session('import_summary');
        $this->assertEquals(1, $summary['skipped']);
        $this->assertStringContainsString('tidak terdaftar di Master Unit', $summary['errors'][0]['errors'][0]);

        $this->assertDatabaseMissing('employees', ['nik' => '99001122']);
    }

    public function test_import_rejects_position_invalid_for_unit_or_level(): void
    {
        $rows = [
            [
                '99003344',
                'JOKO SUSANTO',
                'L',
                'Pontianak',
                '1995-01-01',
                'Islam',
                'TK',
                'PKWTT',
                'Kepala Pabrik Nonexistent', // Posisi tidak ada di formasi unit
                'Karpim',
                'Kebun Inti Gunung Meliau',
                'IIIA/00',
            ],
        ];

        $file = $this->createExcelUploadFile($rows);

        $response = $this->actingAs($this->admin)->post(route('admin.employees.import'), [
            'import_file' => $file,
        ]);

        $summary = session('import_summary');
        $this->assertEquals(1, $summary['skipped']);
        $this->assertStringContainsString('tidak valid untuk Unit Kerja', $summary['errors'][0]['errors'][0]);

        $this->assertDatabaseMissing('employees', ['nik' => '99003344']);
    }

    public function test_import_rejects_grade_mismatched_with_strata(): void
    {
        // Karpel dengan Job Grade 14 (seharusnya 1-10)
        $rows = [
            [
                '99005566',
                'MANDOR OVERGRADE',
                'L',
                'Pontianak',
                '1995-01-01',
                'Islam',
                'TK',
                'PKWTT',
                'Mandor Panen',
                'Karpel',
                'Kebun Inti Gunung Meliau',
                'IID/00',
                'RM-4',
                'Definitif',
                '2018-01-01',
                '',
                '2018-01-01',
                '2018-01-01',
                'Reguler',
                14, // Salah! Harus 1 s.d. 10
                14,
            ],
        ];

        $file = $this->createExcelUploadFile($rows);

        $response = $this->actingAs($this->admin)->post(route('admin.employees.import'), [
            'import_file' => $file,
        ]);

        $summary = session('import_summary');
        $this->assertEquals(1, $summary['skipped']);
        $this->assertStringContainsString('Job Grade wajib berada dalam rentang 1 sampai 10', $summary['errors'][0]['errors'][0]);

        $this->assertDatabaseMissing('employees', ['nik' => '99005566']);
    }

    public function test_import_rejects_invalid_chronological_dates(): void
    {
        // Pegawai umur < 18 tahun saat mulai bekerja
        $rows = [
            [
                '99007788',
                'ANAK DI BAWAH UMUR',
                'L',
                'Pontianak',
                '2005-01-01', // Lahir 2005
                'Islam',
                'TK',
                'PKWTT',
                'Mandor Panen',
                'Karpel',
                'Kebun Inti Gunung Meliau',
                'IIA/00',
                'RM-4',
                'Definitif',
                '2015-01-01', // Kerja 2015 (hanya 10 tahun!)
            ],
        ];

        $file = $this->createExcelUploadFile($rows);

        $response = $this->actingAs($this->admin)->post(route('admin.employees.import'), [
            'import_file' => $file,
        ]);

        $summary = session('import_summary');
        $this->assertEquals(1, $summary['skipped']);
        $this->assertStringContainsString('minimal 18 tahun setelah Tanggal Lahir', $summary['errors'][0]['errors'][0]);

        $this->assertDatabaseMissing('employees', ['nik' => '99007788']);
    }

    public function test_admin_employee_index_renders_import_summary_with_errors_without_blade_exception(): void
    {
        // Verifikasi bahwa tampilan Blade `admin.employees.index` tidak mengalami error Array to string conversion saat menampilkan summary
        $mockSummary = [
            'created' => 2,
            'updated' => 1,
            'skipped' => 2,
            'total_processed' => 5,
            'has_errors' => true,
            'errors' => [
                [
                    'row' => 3,
                    'nik' => '13009988',
                    'errors' => ['Format NIK harus berupa 8 digit angka numerik.'],
                ],
                [
                    'row' => 4,
                    'nik' => '13009989',
                    'errors' => ['Unit Kerja tidak terdaftar di Master Unit Organisasi.'],
                ],
            ],
        ];

        $response = $this->actingAs($this->admin)
            ->withSession(['import_summary' => $mockSummary])
            ->get(route('admin.employees.index'));

        $response->assertOk();
        $response->assertSee('Import Selesai dengan Beberapa Catatan');
        $response->assertSee('13009988');
        $response->assertSee('Format NIK harus berupa 8 digit angka numerik.');
    }

    public function test_admin_can_download_employee_import_template(): void
    {
        Excel::fake();

        $response = $this->actingAs($this->admin)->get(route('admin.employees.downloadTemplate'));
        $response->assertOk();

        Excel::assertDownloaded('karyawan_import_template.xlsx', function (EmployeeTemplateExport $export) {
            $sheets = $export->sheets();

            return count($sheets) === 2
                && $sheets[0] instanceof EmployeeTemplateDataSheet
                && $sheets[1] instanceof EmployeeTemplateReferenceSheet;
        });
    }

    public function test_template_export_sheets_contain_correct_metadata_and_references(): void
    {
        $export = new EmployeeTemplateExport;
        $sheets = $export->sheets();

        $this->assertCount(2, $sheets);

        $dataSheet = $sheets[0];
        $this->assertEquals('Data Karyawan', $dataSheet->title());
        $this->assertContains('NIK*', $dataSheet->headings());
        $this->assertContains('UNIT KERJA*', $dataSheet->headings());
        $sampleData = $dataSheet->array();
        $this->assertCount(1, $sampleData);
        $this->assertEquals('13009988', $sampleData[0][0]); // Sample NIK

        $refSheet = $sheets[1];
        $this->assertEquals('Referensi Master Data', $refSheet->title());
        $this->assertContains('43 UNIT KERJA RESMI', $refSheet->headings());
        $this->assertContains('JABATAN FORMASI AKTIF', $refSheet->headings());
        $refData = $refSheet->array();
        $this->assertNotEmpty($refData);
        // Pastikan Kebun Inti Gunung Meliau ada di kolom A
        $unitNames = array_column($refData, 0);
        $this->assertContains('Kebun Inti Gunung Meliau', $unitNames);
    }

    public function test_template_sample_row_is_fully_compatible_with_import_processor(): void
    {
        $export = new EmployeeTemplateExport;
        $dataSheet = $export->sheets()[0];

        // Buat file Excel persis seperti template data sheet
        $file = $this->createExcelUploadFile($dataSheet->array(), 'downloaded_template.xlsx');

        $response = $this->actingAs($this->admin)->post(route('admin.employees.import'), [
            'import_file' => $file,
        ]);

        $response->assertRedirect(route('admin.employees.index'));
        $summary = session('import_summary');

        // Pastikan baris contoh di template 100% valid dan lolos impor
        $this->assertEquals(1, $summary['created']);
        $this->assertEquals(0, $summary['skipped']);
        $this->assertFalse($summary['has_errors']);

        $this->assertDatabaseHas('employees', [
            'nik' => '13009988',
            'nama' => 'BUDI SANTOSO',
            'unit_kerja' => 'Kebun Inti Gunung Meliau',
            'jabatan' => 'Asisten Afdeling',
            'level' => 'Karpim',
        ]);
    }
}
