<?php

namespace Tests\Feature;

use App\Exports\EmployeesExport;
use App\Exports\EmployeeTemplateExport;
use App\Exports\FailedEmployeesExport;
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
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Events\AfterSheet;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Sheet;
use PhpOffice\PhpSpreadsheet\RichText\RichText;
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
    private function createExcelUploadFile(array $rows, string $filename = 'test_import.xlsx', ?array $customHeaders = null): UploadedFile
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();

        if ($customHeaders !== null) {
            $headers = $customHeaders;
        } elseif (! empty($rows) && count($rows[0]) === 26) {
            $headers = [
                'NIK*', 'NAMA*', 'JENIS KELAMIN', 'TEMPAT LAHIR', 'TANGGAL LAHIR',
                'AGAMA', 'SUSUNAN KELUARGA', 'STATUS KARYAWAN*', 'JABATAN*', 'LEVEL*',
                'UNIT KERJA*', 'GOLONGAN', 'RM LEVEL', 'STATUS PENUGASAN',
                'TUGAS RANGKAP / PENUGASAN SEMENTARA',
                'TMT BEKERJA', 'TANGGAL DIANGKAT STAF', 'TANGGAL DALAM JABATAN',
                'TMT UNIT KERJA', 'JALUR MASUK', 'JOB GRADE', 'PERSON GRADE',
                'PENDIDIKAN TERAKHIR', 'SEKOLAH', 'TANGGAL MBT', 'TANGGAL PENSIUN',
            ];
        } else {
            $headers = [
                'NIK*', 'NAMA*', 'JENIS KELAMIN', 'TEMPAT LAHIR', 'TANGGAL LAHIR',
                'AGAMA', 'SUSUNAN KELUARGA', 'STATUS KARYAWAN*', 'JABATAN*', 'LEVEL*',
                'UNIT KERJA*', 'GOLONGAN', 'RM LEVEL', 'STATUS PENUGASAN',
                'TMT BEKERJA', 'TANGGAL DIANGKAT STAF', 'TANGGAL DALAM JABATAN',
                'TMT UNIT KERJA', 'JALUR MASUK', 'JOB GRADE', 'PERSON GRADE',
                'PENDIDIKAN TERAKHIR', 'SEKOLAH', 'TANGGAL MBT', 'TANGGAL PENSIUN',
            ];
        }

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
        $this->assertCount(26, $dataSheet->headings(), 'Template data sheet harus memiliki tepat 26 kolom');
        $this->assertContains('NIK*', $dataSheet->headings());
        $this->assertContains('UNIT KERJA*', $dataSheet->headings());
        $this->assertContains('TUGAS RANGKAP / PENUGASAN SEMENTARA', $dataSheet->headings());
        $sampleData = $dataSheet->array();
        $this->assertCount(1, $sampleData);
        $this->assertCount(26, $sampleData[0], 'Baris data contoh template harus memiliki 26 kolom');
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

    public function test_template_data_sheet_styling_uniform_corporate_green_and_red_asterisk(): void
    {
        $export = new EmployeeTemplateExport;
        $dataSheet = $export->sheets()[0];

        $spreadsheet = new Spreadsheet;
        $activeSheet = $spreadsheet->getActiveSheet();

        // Populate headings
        $activeSheet->fromArray($dataSheet->headings(), null, 'A1');

        // Execute AfterSheet event closure
        $events = $dataSheet->registerEvents();
        $afterSheetCallback = $events[AfterSheet::class];

        $eventMock = new AfterSheet(
            new Sheet($activeSheet),
            $dataSheet
        );

        $afterSheetCallback($eventMock);

        // 1. Verifikasi Freeze Pane A2 dan AutoFilter A1:Z1
        $this->assertSame('A2', $activeSheet->getFreezePane());
        $this->assertSame('A1:Z1', $activeSheet->getAutoFilter()->getRange());

        // 2. Verifikasi Styling Background Header: Seragam Hijau Korporat PTPN IV (#1B5330)
        $a1Fill = $activeSheet->getStyle('A1')->getFill()->getStartColor()->getRGB();
        $o1Fill = $activeSheet->getStyle('O1')->getFill()->getStartColor()->getRGB();
        $z1Fill = $activeSheet->getStyle('Z1')->getFill()->getStartColor()->getRGB();

        $this->assertSame('1B5330', strtoupper($a1Fill), 'Header A1 harus berwarna Hijau Korporat PTPN IV (1B5330)');
        $this->assertSame('1B5330', strtoupper($o1Fill), 'Header O1 harus berwarna Hijau Korporat PTPN IV (1B5330)');
        $this->assertSame('1B5330', strtoupper($z1Fill), 'Header Z1 harus berwarna Hijau Korporat PTPN IV (1B5330)');

        // 3. Verifikasi Tanda Bintang (*) Berwarna Merah (#EF4444) pada kolom wajib
        $mandatoryCells = ['A1', 'B1', 'H1', 'I1', 'J1', 'K1'];
        foreach ($mandatoryCells as $cellId) {
            $cell = $activeSheet->getCell($cellId);
            $val = $cell->getValue();

            $this->assertInstanceOf(RichText::class, $val, "Cell {$cellId} harus bertipe RichText untuk format asterisk");
            $elements = $val->getRichTextElements();
            $this->assertGreaterThanOrEqual(2, count($elements), "Cell {$cellId} harus memiliki elemen teks judul dan bintang");

            $titleRun = $elements[0];
            $starRun = end($elements);

            $this->assertSame('FFFFFF', strtoupper($titleRun->getFont()->getColor()->getRGB()), "Judul {$cellId} harus teks putih (FFFFFF)");
            $this->assertSame('*', $starRun->getText(), "Elemen terakhir {$cellId} harus tanda bintang (*)");
            $this->assertSame('EF4444', strtoupper($starRun->getFont()->getColor()->getRGB()), "Asterisk {$cellId} harus berwarna merah (EF4444)");
        }

        // 4. Verifikasi Panduan Input Message pada Header (100% Bebas dari Garis Tembus Gridline/Freeze Pane)
        $this->assertEmpty($activeSheet->getComments(), 'Komentar VML lama harus kosong agar tidak ada garis pembatas baris yang menembus');

        $valA1 = $activeSheet->getCell('A1')->getDataValidation();
        $this->assertTrue($valA1->getShowInputMessage(), 'Cell A1 harus memiliki Input Message aktif');
        $this->assertSame('Panduan NIK (Wajib)', $valA1->getPromptTitle());
        $this->assertStringContainsString('Format 8 digit angka', $valA1->getPrompt());

        $valB1 = $activeSheet->getCell('B1')->getDataValidation();
        $this->assertTrue($valB1->getShowInputMessage(), 'Cell B1 harus memiliki Input Message aktif');
        $this->assertSame('Panduan Nama Pegawai (Wajib)', $valB1->getPromptTitle());

        $valO1 = $activeSheet->getCell('O1')->getDataValidation();
        $this->assertTrue($valO1->getShowInputMessage(), 'Cell O1 harus memiliki Input Message aktif');
        $this->assertSame('Panduan Tugas Rangkap', $valO1->getPromptTitle());
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

    public function test_import_action_records_security_audit_trail_log(): void
    {
        Log::spy();

        $rows = [
            [
                '13008899', 'Siti Rahmawati', 'P', 'Pontianak', '1992-08-10',
                'Islam', 'TK', 'Tetap (PKWTT)', 'Mandor Panen', 'Karpel',
                'Kebun Inti Gunung Meliau', 'IIA/00', 'RM-4', 'Definitif',
                '', // Kolom O (Tugas Rangkap)
                '2018-01-01', '', '2020-01-01', '2020-01-01', 'Reguler',
                5, 5, 'SMA / SMK', 'SMA 1', '', '',
            ],
        ];

        $file = $this->createExcelUploadFile($rows, 'batch_employee_import.xlsx');

        $response = $this->actingAs($this->admin)->post(route('admin.employees.import'), [
            'import_file' => $file,
        ]);

        $response->assertRedirect(route('admin.employees.index'));

        Log::shouldHaveReceived('info')
            ->with(
                'HRIS Import Data Pegawai dieksekusi',
                \Mockery::on(function ($context) {
                    return isset($context['user_id'])
                        && $context['user_id'] === $this->admin->id
                        && isset($context['file_name'])
                        && $context['file_name'] === 'batch_employee_import.xlsx'
                        && isset($context['created_count'])
                        && $context['created_count'] === 1
                        && isset($context['skipped_count'])
                        && $context['skipped_count'] === 0
                        && isset($context['timestamp']);
                })
            );
    }

    public function test_export_output_file_can_be_roundtrip_imported_cleanly(): void
    {
        $employee = Employee::factory()->create([
            'nik' => '13009988',
            'nama' => 'BUDI SANTOSO',
            'unit_kerja' => 'Kebun Inti Gunung Meliau',
            'jabatan' => 'Asisten Afdeling',
            'level' => 'Karpim',
            'status_karyawan' => 'Tetap (PKWTT)',
            'status_penugasan' => 'Definitif',
            'golongan' => 'IIIA/00',
            'rm_level' => 'RM-3',
            'tmt_bekerja' => '2018-01-01',
            'tanggal_lahir' => '1990-05-15',
            'tanggal_diangkat_staf' => '2020-06-01',
            'tanggal_dalam_jabatan' => '2022-01-01',
            'tmt_unit_kerja' => '2022-01-05',
            'jalur_masuk' => 'Reguler',
            'job_grade' => 11,
            'person_grade' => 11,
        ]);

        $export = new EmployeesExport;
        $headings = $export->headings();
        $row = $export->map($employee);

        $file = $this->createExcelUploadFile([$row], 'roundtrip_export.xlsx', $headings);

        $response = $this->actingAs($this->admin)->post(route('admin.employees.import'), [
            'import_file' => $file,
        ]);

        $response->assertRedirect(route('admin.employees.index'));
        $summary = session('import_summary');

        $this->assertEquals(0, $summary['created']);
        $this->assertEquals(1, $summary['updated']);
        $this->assertEquals(0, $summary['skipped']);
        $this->assertFalse($summary['has_errors']);
    }

    public function test_import_validation_rejects_files_exceeding_10mb(): void
    {
        $largeFile = UploadedFile::fake()->create('huge_dataset.xlsx', 11000); // 11MB > 10MB (10240KB)

        $response = $this->actingAs($this->admin)->post(route('admin.employees.import'), [
            'import_file' => $largeFile,
        ]);

        $response->assertSessionHasErrors(['import_file']);
    }

    public function test_dry_run_preview_validates_without_saving_to_database(): void
    {
        $rows = [
            [
                '13009988', 'BUDI SANTOSO', 'L', 'Pontianak', '1990-05-15',
                'Islam', 'K/1', 'Tetap (PKWTT)', 'Asisten Afdeling', 'Karpim',
                'Kebun Inti Gunung Meliau', 'IIIA/00', 'RM-3', 'Definitif',
                '',
                '2018-01-01', '2020-06-01', '2022-01-01',
                '2022-01-05', 'Reguler', 11, 11,
                'D4 / S1', 'INSTIPER Yogyakarta', '', '',
            ],
            [
                '99999999', 'INVALID EMPLOYEE', 'L', 'Pontianak', '1990-05-15',
                'Islam', 'K/1', 'Tetap (PKWTT)', 'Jabatan Khayalan', 'Karpim',
                'Unit Khayalan', 'IIIA/00', 'RM-3', 'Definitif',
                '',
                '2018-01-01', '2020-06-01', '2022-01-01',
                '2022-01-05', 'Reguler', 11, 11,
                'D4 / S1', 'INSTIPER Yogyakarta', '', '',
            ],
        ];

        $file = $this->createExcelUploadFile($rows, 'preview_test.xlsx');

        $response = $this->actingAs($this->admin)->postJson(route('admin.employees.import.preview'), [
            'import_file' => $file,
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonStructure([
                'token',
                'file_name',
                'file_size',
                'summary' => [
                    'created',
                    'updated',
                    'skipped',
                    'total_processed',
                    'has_errors',
                    'sample_rows',
                    'failed_count',
                    'is_dry_run',
                ],
            ]);

        $summary = $response->json('summary');
        $this->assertEquals(1, $summary['created']);
        $this->assertEquals(1, $summary['skipped']);
        $this->assertTrue($summary['is_dry_run']);

        // Data TIDAK boleh tersimpan di database selama dry-run preview!
        $this->assertDatabaseMissing('employees', ['nik' => '13009988']);
        $this->assertDatabaseMissing('employees', ['nik' => '99999999']);
    }

    public function test_commit_import_saves_valid_records_from_preview_session(): void
    {
        $rows = [
            [
                '13009988', 'BUDI SANTOSO', 'L', 'Pontianak', '1990-05-15',
                'Islam', 'K/1', 'Tetap (PKWTT)', 'Asisten Afdeling', 'Karpim',
                'Kebun Inti Gunung Meliau', 'IIIA/00', 'RM-3', 'Definitif',
                '',
                '2018-01-01', '2020-06-01', '2022-01-01',
                '2022-01-05', 'Reguler', 11, 11,
                'D4 / S1', 'INSTIPER Yogyakarta', '', '',
            ],
        ];

        $file = $this->createExcelUploadFile($rows, 'commit_test.xlsx');

        // 1. Jalankan dry-run preview
        $previewResponse = $this->actingAs($this->admin)->postJson(route('admin.employees.import.preview'), [
            'import_file' => $file,
        ]);

        $token = $previewResponse->json('token');
        $this->assertNotEmpty($token);

        // 2. Jalankan commit
        $commitResponse = $this->actingAs($this->admin)->postJson(route('admin.employees.import.commit'), [
            'token' => $token,
        ]);

        $commitResponse->assertOk()
            ->assertJson([
                'success' => true,
                'redirect_url' => route('admin.employees.index'),
            ]);

        // Verifikasi data kini tersimpan di database
        $this->assertDatabaseHas('employees', [
            'nik' => '13009988',
            'nama' => 'BUDI SANTOSO',
            'jabatan' => 'Asisten Afdeling',
            'unit_kerja' => 'Kebun Inti Gunung Meliau',
        ]);

        // Verifikasi Job History awal terbuat otomatis via EmployeeObserver
        $this->assertDatabaseHas('job_histories', [
            'employee_nik' => '13009988',
            'jabatan' => 'Asisten Afdeling',
            'unit_kerja' => 'Kebun Inti Gunung Meliau',
            'jenis_mutasi' => 'PENUGASAN',
        ]);
    }

    public function test_commit_import_rejects_expired_or_invalid_token(): void
    {
        $response = $this->actingAs($this->admin)->postJson(route('admin.employees.import.commit'), [
            'token' => 'non-existent-token-uuid-1234',
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
            ]);
    }

    public function test_download_failed_rows_exports_rejected_rows_with_reasons(): void
    {
        $rows = [
            [
                '99999999', 'INVALID EMPLOYEE', 'L', 'Pontianak', '1990-05-15',
                'Islam', 'K/1', 'Tetap (PKWTT)', 'Jabatan Khayalan', 'Karpim',
                'Unit Khayalan', 'IIIA/00', 'RM-3', 'Definitif',
                '',
                '2018-01-01', '2020-06-01', '2022-01-01',
                '2022-01-05', 'Reguler', 11, 11,
                'D4 / S1', 'INSTIPER Yogyakarta', '', '',
            ],
        ];

        $file = $this->createExcelUploadFile($rows, 'failed_rows_test.xlsx');

        // 1. Jalankan dry-run preview
        $previewResponse = $this->actingAs($this->admin)->postJson(route('admin.employees.import.preview'), [
            'import_file' => $file,
        ]);

        $token = $previewResponse->json('token');

        // 2. Download baris gagal
        Excel::fake();

        $downloadResponse = $this->actingAs($this->admin)->post(route('admin.employees.import.downloadFailed'), [
            'token' => $token,
        ]);

        Excel::assertDownloaded('baris_gagal_impor_karyawan_'.now()->format('Ymd_His').'.xlsx', function (FailedEmployeesExport $export) {
            $array = $export->array();
            $headings = $export->headings();

            return count($array) === 1
                && in_array('NO. BARIS EXCEL', $headings)
                && in_array('ALASAN PENOLAKAN / KESALAHAN SISTEM', $headings);
        });
    }

    /**
     * Memastikan workbook multi-sheet (seperti template resmi dengan sheet 'Referensi Master Data')
     * hanya memproses sheet pertama ('Data Karyawan') dan tidak memicu error palsu pada sheet referensi.
     */
    public function test_import_only_processes_first_sheet_and_ignores_reference_sheet(): void
    {
        $spreadsheet = new Spreadsheet;

        // Sheet 1: Data Karyawan (1 baris valid)
        $sheet1 = $spreadsheet->getActiveSheet();
        $sheet1->setTitle('Data Karyawan');
        $sheet1->fromArray([
            [
                'NIK*', 'NAMA*', 'JENIS KELAMIN', 'TEMPAT LAHIR', 'TANGGAL LAHIR',
                'AGAMA', 'SUSUNAN KELUARGA', 'STATUS KARYAWAN*', 'JABATAN*', 'LEVEL*',
                'UNIT KERJA*', 'GOLONGAN', 'RM LEVEL', 'STATUS PENUGASAN', 'TUGAS RANGKAP',
                'TMT BEKERJA', 'TANGGAL DIANGKAT STAF', 'TANGGAL DALAM JABATAN', 'TMT UNIT KERJA',
                'JALUR MASUK', 'JOB GRADE', 'PERSON GRADE', 'PENDIDIKAN TERAKHIR', 'SEKOLAH',
                'TANGGAL MBT', 'TANGGAL PENSIUN',
            ],
            [
                '13008899', 'SITI RAHMAWATI', 'P', 'Pontianak', '1992-04-10',
                'Islam', 'K/1', 'Tetap (PKWTT)', 'Asisten Afdeling', 'Karpim',
                'Kebun Inti Gunung Meliau', 'IIIA/00', 'RM-3', 'Definitif', '',
                '2018-01-01', '2020-01-01', '2022-01-01', '2022-01-01',
                'Reguler', 11, 11, 'S1', 'Universitas Tanjungpura',
                '2047-04-01', '2048-04-01',
            ],
        ]);

        // Sheet 2: Referensi Master Data (data kamus, tanpa kolom NIK/Nama)
        $sheet2 = $spreadsheet->createSheet();
        $sheet2->setTitle('Referensi Master Data');
        $sheet2->fromArray([
            ['43 UNIT KERJA RESMI', 'JABATAN FORMASI AKTIF', 'STRATA PEGAWAI'],
            ['Kebun Inti Gunung Meliau', 'Asisten Afdeling', 'Karpim'],
            ['Kebun Gunung Emas', 'Mandor Panen', 'Karpel'],
            ['PKS Longpinang', 'Asisten Pengolahan', 'Karpim'],
        ]);

        $tmpFile = tempnam(sys_get_temp_dir(), 'test_multisheet_').'.xlsx';
        $writer = new Xlsx($spreadsheet);
        $writer->save($tmpFile);

        $uploadedFile = new UploadedFile($tmpFile, 'test_multisheet.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);

        $response = $this->actingAs($this->admin)->postJson(route('admin.employees.import.preview'), [
            'import_file' => $uploadedFile,
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('summary.total_processed', 1)
            ->assertJsonPath('summary.created', 1)
            ->assertJsonPath('summary.failed_count', 0);

        if (file_exists($tmpFile)) {
            @unlink($tmpFile);
        }
    }

    /**
     * Memastikan variasi tipografi pada nama jabatan (misal: 'Asisten Tata Usaha Keuangan'
     * vs 'Asisten Tata Usaha & Keuangan') dinormalkan ke nama jabatan master resmi secara otomatis.
     */
    public function test_import_resolves_position_with_punctuation_and_spacing_variations(): void
    {
        $tuPos = Position::firstOrCreate(
            ['nama' => 'Asisten Tata Usaha & Keuangan', 'level' => 'Karpim'],
            ['is_active' => true]
        );

        $this->validUnit->positions()->syncWithoutDetaching([
            $tuPos->id => ['kuota' => 2],
        ]);

        // Input dengan 'Asisten Tata Usaha Keuangan' (tanpa ampersand &)
        $rows = [
            [
                '13007788', 'ANDI WIJAYA', 'L', 'Sintang', '1990-08-15',
                'Islam', 'K/0', 'Tetap (PKWTT)', 'Asisten Tata Usaha Keuangan', 'Karpim',
                'Kebun Inti Gunung Meliau', 'IIIA/00', 'RM-3', 'Definitif', '',
                '2018-01-01', '2020-01-01', '2022-01-01', '2022-01-01',
                'Reguler', 11, 11, 'S1', 'Universitas Gadjah Mada',
                '2045-08-01', '2046-08-01',
            ],
        ];

        $file = $this->createExcelUploadFile($rows);

        $previewResponse = $this->actingAs($this->admin)->postJson(route('admin.employees.import.preview'), [
            'import_file' => $file,
        ]);

        $previewResponse->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('summary.created', 1)
            ->assertJsonPath('summary.failed_count', 0);

        $token = $previewResponse->json('token');

        $commitResponse = $this->actingAs($this->admin)->postJson(route('admin.employees.import.commit'), [
            'token' => $token,
        ]);

        $commitResponse->assertOk()->assertJsonPath('success', true);

        // Jabatan di database harus ternormalisasi ke master resmi dengan ampersand (&)
        $this->assertDatabaseHas('employees', [
            'nik' => '13007788',
            'jabatan' => 'Asisten Tata Usaha & Keuangan',
        ]);
    }

    /**
     * Memastikan data tugas rangkap / penugasan sementara pada Kolom O otomatis
     * diparsing dan didaftarkan sebagai JobHistory tugas rangkap aktif (concurrentJobs).
     */
    public function test_import_auto_registers_concurrent_job_from_column_o(): void
    {
        // Posisi tugas rangkap di unit PKS Gunung Meliau
        $pksUnit = Unit::firstOrCreate(
            ['nama' => 'PKS Gunung Meliau'],
            ['kode' => 'PKGM', 'wilayah' => 'Wilayah Kalimantan Barat', 'is_active' => true]
        );

        $manajerPos = Position::firstOrCreate(
            ['nama' => 'Manajer PKS', 'level' => 'Karpim'],
            ['is_active' => true]
        );

        $pksUnit->positions()->syncWithoutDetaching([
            $manajerPos->id => ['kuota' => 1],
        ]);

        $rows = [
            [
                '13009901', 'BUDI SANTOSO', 'L', 'Pontianak', '1988-05-10',
                'Islam', 'K/2', 'Tetap (PKWTT)', 'Asisten Afdeling', 'Karpim',
                'Kebun Inti Gunung Meliau', 'IIIA/00', 'RM-3', 'Definitif',
                'Plt. Manajer PKS PKS Gunung Meliau TMT 15 Juli 2026',
                '2015-01-01', '2017-01-01', '2020-01-01', '2020-01-01',
                'Reguler', 11, 11, 'S1', 'Universitas Tanjungpura',
                '2043-05-01', '2044-05-01',
            ],
        ];

        $file = $this->createExcelUploadFile($rows);

        $previewResponse = $this->actingAs($this->admin)->postJson(route('admin.employees.import.preview'), [
            'import_file' => $file,
        ]);

        $previewResponse->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('summary.created', 1)
            ->assertJsonPath('summary.failed_count', 0);

        $this->assertEquals(
            'Plt. Manajer PKS PKS Gunung Meliau TMT 15 Juli 2026',
            $previewResponse->json('summary.sample_rows.0.tugas_rangkap')
        );

        $token = $previewResponse->json('token');

        $commitResponse = $this->actingAs($this->admin)->postJson(route('admin.employees.import.commit'), [
            'token' => $token,
        ]);

        $commitResponse->assertOk()->assertJsonPath('success', true);

        // Pastikan data definitif tersimpan di tabel employees
        $employee = Employee::where('nik', '13009901')->firstOrFail();
        $this->assertEquals('Asisten Afdeling', $employee->jabatan);
        $this->assertEquals('Kebun Inti Gunung Meliau', $employee->unit_kerja);
        $this->assertEquals('Definitif', $employee->status_penugasan);

        // Pastikan tugas rangkap otomatis terdaftar di job_histories
        $this->assertTrue($employee->hasConcurrentJobs());
        $this->assertEquals(1, $employee->concurrentJobs()->count());

        $concurrentJob = $employee->concurrentJobs()->first();
        $this->assertEquals('Manajer PKS', $concurrentJob->jabatan);
        $this->assertEquals('PKS Gunung Meliau', $concurrentJob->unit_kerja);
        $this->assertEquals('Plt', $concurrentJob->status_penugasan);
        $this->assertEquals('2026-07-15', $concurrentJob->tmt_awal->toDateString());
        $this->assertNull($concurrentJob->tmt_akhir);
        $this->assertEquals('PENUGASAN', $concurrentJob->jenis_mutasi);
        $this->assertStringContainsString('Plt. Manajer PKS', $concurrentJob->catatan);
    }
}
