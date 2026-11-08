<?php

namespace Tests\Feature;

use App\Exceptions\ImportAbortedException;
use App\Exports\EmployeesExport;
use App\Exports\EmployeeTemplateExport;
use App\Exports\FailedEmployeesExport;
use App\Exports\FailedEmployeesWorkbook;
use App\Exports\ImportSummaryExport;
use App\Exports\Sheets\EmployeeTemplateDataSheet;
use App\Exports\Sheets\EmployeeTemplateFormasiSheet;
use App\Exports\Sheets\EmployeeTemplateReferenceSheet;
use App\Imports\EmployeesImport;
use App\Livewire\Admin\Employees\ImportBatchTable;
use App\Models\Employee;
use App\Models\EmployeeImportChange;
use App\Models\EmployeeImportLog;
use App\Models\JobHistory;
use App\Models\Position;
use App\Models\Unit;
use App\Models\UnitAlias;
use App\Models\User;
use App\Services\EmployeeImportService;
use App\Services\Import\EmployeeImportDictionary;
use Carbon\Carbon;
use Database\Seeders\PositionUnitSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Livewire\Livewire;
use Maatwebsite\Excel\Events\AfterSheet;
use Maatwebsite\Excel\Excel as ExcelWriter;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Sheet;
use PhpOffice\PhpSpreadsheet\Calculation\Calculation;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\IOFactory;
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

        $pageResponse = $this->get(route('admin.employees.import.page'));
        $pageResponse->assertRedirect(route('login'));
    }

    public function test_admin_can_access_import_wizard_page(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.employees.import.page'));
        $response->assertOk();
        $response->assertViewIs('admin.employees.import');
        $response->assertViewHas('dictionaryCategories');
        $response->assertSee('Impor Data Karyawan');
        $response->assertSee('Kamus Format Spreadsheet');
        $response->assertSeeText('Unggah & Panduan Kolom');
        $response->assertSee('Unduh Template Excel Resmi');
        $response->assertSee('searchColumn');
        $mandatory = collect(EmployeeImportDictionary::getColumns())->where('is_mandatory', true)->count();
        $response->assertSee($mandatory.' wajib');

        // Pastikan seluruh kolom dari EmployeeImportDictionary ter-render (jumlahnya mengikuti template: 24 kolom)
        $columns = EmployeeImportDictionary::getColumns();
        $this->assertCount(24, $columns);
        foreach ($columns as $col) {
            $response->assertSee($col['key']);
        }
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

        // Verifikasi kalkulasi pensiun (56 tahun) & MBT (6 bulan sebelum pensiun)
        $expectedPensiun = Carbon::parse('1990-05-15')->addYears(56)->startOfMonth()->toDateString();
        $expectedMbt = Carbon::parse($expectedPensiun)->subMonths(6)->toDateString();
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
        // Buat karyawan eksisting
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
        // Karyawan umur < 18 tahun saat mulai bekerja
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

            return count($sheets) === 3
                && $sheets[0] instanceof EmployeeTemplateDataSheet
                && $sheets[1] instanceof EmployeeTemplateFormasiSheet
                && $sheets[2] instanceof EmployeeTemplateReferenceSheet;
        });
    }

    public function test_template_export_sheets_contain_correct_metadata_and_references(): void
    {
        $export = new EmployeeTemplateExport;
        $sheets = $export->sheets();

        $this->assertCount(3, $sheets);

        $dataSheet = $sheets[0];
        $this->assertEquals('Data Karyawan', $dataSheet->title());
        $this->assertCount(24, $dataSheet->headings(), 'Template data sheet harus memiliki tepat 24 kolom (Level dan RM Level diturunkan)');
        $this->assertContains('NIK*', $dataSheet->headings());
        $this->assertContains('UNIT KERJA*', $dataSheet->headings());
        $this->assertContains('TUGAS RANGKAP / PENUGASAN SEMENTARA', $dataSheet->headings());
        $sampleData = $dataSheet->array();
        $this->assertCount(1, $sampleData);
        $this->assertCount(24, $sampleData[0], 'Baris data contoh template harus memiliki 24 kolom');
        $this->assertEquals('13009988', $sampleData[0][0]); // Sample NIK

        $this->assertSame('Formasi per Unit', $sheets[1]->title());

        // Pasangan Unit Kerja dan Jabatan hanya ada di katalog Formasi, bukan sebagai dua daftar terpisah.
        $refSheet = $sheets[2];
        $this->assertEquals('Referensi Master Data', $refSheet->title());
        $this->assertContains('UNIT KERJA RESMI', $refSheet->headings());
        $this->assertNotContains('JABATAN FORMASI AKTIF', $refSheet->headings());
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
        $this->assertSame('A1:X1', $activeSheet->getAutoFilter()->getRange());

        // 2. Verifikasi Styling Background Header: Seragam Hijau Korporat PTPN IV (#1B5330)
        $a1Fill = $activeSheet->getStyle('A1')->getFill()->getStartColor()->getRGB();
        $o1Fill = $activeSheet->getStyle('M1')->getFill()->getStartColor()->getRGB();
        $z1Fill = $activeSheet->getStyle('X1')->getFill()->getStartColor()->getRGB();

        $this->assertSame('1B5330', strtoupper($a1Fill), 'Header A1 harus berwarna Hijau Korporat PTPN IV (1B5330)');
        $this->assertSame('1B5330', strtoupper($o1Fill), 'Header M1 harus berwarna Hijau Korporat PTPN IV (1B5330)');
        $this->assertSame('1B5330', strtoupper($z1Fill), 'Header X1 harus berwarna Hijau Korporat PTPN IV (1B5330)');

        // 3. Verifikasi Tanda Bintang (*) Berwarna Merah (#EF4444) pada kolom wajib
        // Level dan RM Level diturunkan dari Formasi Jabatan, sehingga bukan kolom template.
        $this->assertNotContains('LEVEL', $dataSheet->headings());
        $this->assertNotContains('RM LEVEL', $dataSheet->headings());

        $mandatoryCells = ['A1', 'B1', 'H1', 'I1', 'J1'];
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
        $this->assertSame('Panduan Nama Karyawan (Wajib)', $valB1->getPromptTitle());

        $valM1 = $activeSheet->getCell('M1')->getDataValidation();
        $this->assertTrue($valM1->getShowInputMessage(), 'Cell M1 harus memiliki Input Message aktif');
        $this->assertSame('Panduan Tugas Rangkap', $valM1->getPromptTitle());
    }

    public function test_template_sample_row_is_fully_compatible_with_import_processor(): void
    {
        $export = new EmployeeTemplateExport;
        $dataSheet = $export->sheets()[0];

        // Buat file Excel persis seperti template data sheet
        $file = $this->createExcelUploadFile($dataSheet->array(), 'downloaded_template.xlsx', $dataSheet->headings());

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
                'HRIS Import Data Karyawan dieksekusi',
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

    public function test_import_persists_audit_log_without_personal_data(): void
    {
        $valid = $this->karpimImportRow('88005001', 'Asisten Afdeling', 'Kebun Inti Gunung Meliau');
        $invalid = $this->karpimImportRow('88005002', 'Jabatan Tidak Ada', 'Kebun Inti Gunung Meliau');

        $this->actingAs($this->admin)->post(route('admin.employees.import'), ['import_file' => $this->createExcelUploadFile([$valid, $invalid])]);

        $log = EmployeeImportLog::sole();
        $this->assertSame(1, $log->created_count);
        $this->assertSame(1, $log->skipped_count);
        $this->assertArrayNotHasKey('sample_rows', $log->metadata['summary']);
        $this->assertSame(['row', 'errors'], array_keys($log->metadata['summary']['errors'][0]));
        $this->assertStringNotContainsString('KARYAWAN UJI FORMASI', json_encode($log->metadata));
    }

    public function test_import_session_is_only_accessible_by_the_admin_who_uploaded_it(): void
    {
        $token = $this->actingAs($this->admin)
            ->post(route('admin.employees.import.preview'), ['import_file' => $this->createExcelUploadFile([$this->karpimImportRow('88006001', 'Asisten Afdeling', 'Kebun Inti Gunung Meliau')])])
            ->json('token');

        $other = User::factory()->create(['role' => 'admin']);

        $this->actingAs($other)->postJson(route('admin.employees.import.commit'), ['token' => $token])->assertForbidden();
        $this->actingAs($other)->getJson(route('admin.employees.import.status', $token))->assertForbidden();
        $this->actingAs($other)->post(route('admin.employees.import.downloadFailed'), ['token' => $token])->assertForbidden();
        $this->actingAs($this->admin)->postJson(route('admin.employees.import.commit'), ['token' => $token])->assertOk();
    }

    public function test_import_upload_endpoints_are_rate_limited(): void
    {
        $this->actingAs($this->admin);

        for ($i = 0; $i < 10; $i++) {
            $this->post(route('admin.employees.import'), []);
        }

        $this->post(route('admin.employees.import'), [])->assertStatus(429);
    }

    public function test_import_update_of_core_fields_is_attributed_to_the_acting_admin_in_job_history(): void
    {
        $first = $this->karpimImportRow('88007001', 'Asisten Afdeling', 'Kebun Inti Gunung Meliau');
        $this->actingAs($this->admin)->post(route('admin.employees.import'), ['import_file' => $this->createExcelUploadFile([$first])]);

        $second = $first;
        $second[11] = 'IVA/00';
        $this->actingAs($this->admin)->post(route('admin.employees.import'), ['import_file' => $this->createExcelUploadFile([$second])]);

        $this->assertDatabaseHas('job_histories', [
            'employee_nik' => '88007001',
            'golongan' => 'IVA/00',
            'catatan' => 'Perubahan data melalui impor Excel oleh '.$this->admin->name,
        ]);
    }

    public function test_import_keeps_row_numbers_and_duplicate_detection_across_chunks(): void
    {
        $unit = Unit::create(['nama' => 'Kebun Besar', 'kode' => 'Kebun', 'wilayah' => 'Wilayah Kalimantan Barat', 'is_active' => true, 'urutan' => 50]);
        $position = Position::firstOrCreate(['nama' => 'Manajer Besar', 'level' => 'Karpim'], ['rm_level' => 'RM-1', 'is_active' => true]);
        $unit->positions()->attach($position->id, ['kuota' => 2000, 'bidang' => 'TAN']);

        $rows = [];
        for ($i = 1; $i <= 600; $i++) {
            $rows[] = $this->karpimImportRow(sprintf('77%06d', $i), 'Manajer Besar', 'Kebun Besar');
        }
        $rows[] = $this->karpimImportRow('77000001', 'Manajer Besar', 'Kebun Besar'); // duplikat NIK di chunk berikutnya

        $this->actingAs($this->admin)->post(route('admin.employees.import'), ['import_file' => $this->createExcelUploadFile($rows)]);

        $summary = session('import_summary');
        $this->assertSame(600, $summary['created']);
        $this->assertSame(1, $summary['skipped']);
        $this->assertSame(602, $summary['errors'][0]['row']);
        $this->assertStringContainsString('duplikat dari baris 2', $summary['errors'][0]['errors'][0]);
    }

    public function test_preview_diff_does_not_reveal_family_data_values(): void
    {
        $first = $this->karpimImportRow('88008001', 'Asisten Afdeling', 'Kebun Inti Gunung Meliau');
        $this->actingAs($this->admin)->post(route('admin.employees.import'), ['import_file' => $this->createExcelUploadFile([$first])]);

        $second = $first;
        $second[6] = 'K/3';
        $response = $this->actingAs($this->admin)->post(route('admin.employees.import.preview'), ['import_file' => $this->createExcelUploadFile([$second])]);

        $diff = collect($response->json('summary.sample_rows.0.diff'))->firstWhere('field', 'susunan_keluarga');
        $this->assertNotNull($diff, 'Perubahan data keluarga tetap harus ditandai.');
        $this->assertStringNotContainsString('K/', $diff['old'].$diff['new']);
    }

    public function test_failed_rows_are_cached_encrypted_and_downloads_are_audited(): void
    {
        Log::spy();
        $invalid = $this->karpimImportRow('88009001', 'Jabatan Tidak Ada', 'Kebun Inti Gunung Meliau');

        $token = $this->actingAs($this->admin)
            ->post(route('admin.employees.import.preview'), ['import_file' => $this->createExcelUploadFile([$invalid])])
            ->json('token');

        $cached = Cache::get("import_failed_rows_{$token}");
        $this->assertIsString($cached);
        $this->assertStringNotContainsString('88009001', $cached);
        $this->assertStringNotContainsString('KARYAWAN UJI FORMASI', $cached);

        $this->actingAs($this->admin)->post(route('admin.employees.import.downloadFailed'), ['token' => $token])->assertOk();

        Log::shouldHaveReceived('info')->with('HRIS Unduh Berkas Impor Karyawan', \Mockery::on(
            fn (array $context) => $context['kind'] === 'baris_gagal'
                && $context['user_id'] === $this->admin->id
                && $context['record_count'] === 1
                && ! str_contains(json_encode($context), '88009001')
        ));
    }

    public function test_import_summary_report_neutralises_formula_injection(): void
    {
        $export = new ImportSummaryExport(['rows' => [['action' => 'CREATE', 'nik' => '88009002', 'nama' => '=HYPERLINK("http://x")', 'jabatan' => '+cmd']]]);

        $cells = collect($export->sheets())->flatMap(fn ($sheet) => $sheet->array())->flatten()->all();

        $this->assertContains("'=HYPERLINK(\"http://x\")", $cells);
        $this->assertContains("'+cmd", $cells);
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
        $this->assertEquals(0, $summary['updated']);
        $this->assertEquals(1, $summary['unchanged']);
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
        Carbon::setTestNow(now());
        try {
            Excel::fake();

            $downloadResponse = $this->actingAs($this->admin)->post(route('admin.employees.import.downloadFailed'), [
                'token' => $token,
            ]);

            Excel::assertDownloaded('baris_gagal_impor_karyawan_'.now()->format('Ymd_His').'.xlsx', function (FailedEmployeesWorkbook $workbook) {
                $export = $workbook->sheets()[0];
                $array = $export->array();
                $headings = $export->headings();

                return count($array) === 1
                    && in_array('NO. BARIS EXCEL', $headings)
                    && in_array('ALASAN PENOLAKAN / KESALAHAN SISTEM', $headings);
            });
        } finally {
            Carbon::setTestNow();
        }

        // 3. Jalankan komit (Tahap 3)
        $commitResponse = $this->actingAs($this->admin)->postJson(route('admin.employees.import.commit'), [
            'token' => $token,
        ]);
        $commitResponse->assertOk()->assertJsonPath('success', true);

        // 4. Download baris gagal pada Tahap 3 (pasca-komit) harus tetap berhasil
        Carbon::setTestNow(now());
        try {
            Excel::fake();

            $downloadPostCommit = $this->actingAs($this->admin)->post(route('admin.employees.import.downloadFailed'), [
                'token' => $token,
            ]);

            $downloadPostCommit->assertOk();
            Excel::assertDownloaded('baris_gagal_impor_karyawan_'.now()->format('Ymd_His').'.xlsx', function (FailedEmployeesWorkbook $workbook) {
                return count($workbook->sheets()[0]->array()) === 1;
            });
        } finally {
            Carbon::setTestNow();
        }
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
            ['UNIT KERJA RESMI', 'JABATAN FORMASI AKTIF', 'STRATA KARYAWAN'],
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

        // Audit trail persisten harus tercatat di employee_import_logs
        $this->assertDatabaseHas('employee_import_logs', [
            'token' => $token,
            'created_count' => 1,
            'status' => 'completed',
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

    /**
     * Baris import Karpim lengkap (25 kolom) untuk skenario validasi unit kerja.
     */
    private function karpimImportRow(string $nik, string $jabatan, string $unit): array
    {
        return [
            $nik, 'KARYAWAN UJI FORMASI', 'L', 'Pontianak', '1985-03-10',
            'Islam', 'K/1', 'Tetap (PKWTT)', $jabatan, 'Karpim', $unit,
            'IIID/05', 'RM-1', 'Definitif',
            '2008-03-01', '2010-01-01', '2022-01-01', '2022-01-01',
            'Reguler', 13, 13, 'S1', 'Universitas',
            '', '',
        ];
    }

    public function test_import_accepts_a_unit_that_is_new_in_the_excel(): void
    {
        $unit = Unit::create(['nama' => 'Kebun Kumai', 'kode' => 'Kebun', 'wilayah' => 'Kalimantan Selatan & Tengah', 'is_active' => true, 'urutan' => 30]);
        $manajer = Position::firstOrCreate(['nama' => 'Manajer', 'level' => 'Karpim'], ['rm_level' => 'RM-1', 'is_active' => true]);
        $unit->positions()->attach($manajer->id, ['kuota' => 1, 'bidang' => 'TAN']);

        $file = $this->createExcelUploadFile([$this->karpimImportRow('88001001', 'Manajer', 'KEBUN KUMAI')]);

        $this->actingAs($this->admin)->post(route('admin.employees.import'), ['import_file' => $file]);

        $this->assertSame([], session('import_summary')['errors'] ?? [], 'Baris import seharusnya valid.');
        $this->assertDatabaseHas('employees', ['nik' => '88001001', 'unit_kerja' => 'Kebun Kumai', 'jabatan' => 'Manajer']);
    }

    private function formasiUnit(string $nama, string $jabatan, string $rm, int $kuota): Unit
    {
        $unit = Unit::create(['nama' => $nama, 'kode' => 'Kebun', 'wilayah' => 'Wilayah Kalimantan Barat', 'is_active' => true, 'urutan' => 40]);
        $position = Position::firstOrCreate(['nama' => $jabatan, 'level' => 'Karpim'], ['rm_level' => $rm, 'is_active' => true]);
        $unit->positions()->attach($position->id, ['kuota' => $kuota, 'bidang' => 'TAN']);

        return $unit;
    }

    public function test_import_rejects_a_level_for_which_the_unit_has_no_formasi(): void
    {
        $this->formasiUnit('Kebun Sandai', 'Manajer Sandai', 'RM-1', 1);
        $row = $this->karpimImportRow('88002001', 'Mandor Apa Saja', 'Kebun Sandai');
        $row[9] = 'Karpel';
        $row[19] = 5;
        $row[20] = 5;

        $this->actingAs($this->admin)->post(route('admin.employees.import'), ['import_file' => $this->createExcelUploadFile([$row])]);

        $this->assertStringContainsString('belum memiliki formasi jabatan', session('import_summary')['errors'][0]['errors'][0]);
        $this->assertDatabaseMissing('employees', ['nik' => '88002001']);
    }

    public function test_import_rejects_rows_beyond_the_formasi_quota(): void
    {
        $this->formasiUnit('Kebun Kuota', 'Manajer Kuota', 'RM-1', 1);

        $file = $this->createExcelUploadFile([
            $this->karpimImportRow('88003001', 'Manajer Kuota', 'Kebun Kuota'),
            $this->karpimImportRow('88003002', 'Manajer Kuota', 'Kebun Kuota'),
        ]);

        $this->actingAs($this->admin)->post(route('admin.employees.import'), ['import_file' => $file]);

        $this->assertStringContainsString('Kuota formasi jabatan', session('import_summary')['errors'][0]['errors'][0]);
        $this->assertDatabaseHas('employees', ['nik' => '88003001']);
        $this->assertDatabaseMissing('employees', ['nik' => '88003002']);
    }

    public function test_import_rejects_rm_level_that_differs_from_the_formasi_and_derives_it_when_blank(): void
    {
        $this->formasiUnit('Kebun Band', 'Manajer Band', 'RM-2', 2);
        $mismatch = $this->karpimImportRow('88004001', 'Manajer Band', 'Kebun Band');
        $blank = $this->karpimImportRow('88004002', 'Manajer Band', 'Kebun Band');
        $blank[12] = '';

        $this->actingAs($this->admin)->post(route('admin.employees.import'), ['import_file' => $this->createExcelUploadFile([$mismatch, $blank])]);

        $this->assertStringContainsString('tidak sesuai dengan formasi jabatan', session('import_summary')['errors'][0]['errors'][0]);
        $this->assertDatabaseMissing('employees', ['nik' => '88004001']);
        $this->assertDatabaseHas('employees', ['nik' => '88004002', 'rm_level' => 'RM-2']);
    }

    public function test_import_rejects_rm_level_that_differs_from_the_formasi_for_an_existing_employee(): void
    {
        $this->formasiUnit('Kebun Band Lama', 'Manajer Band Lama', 'RM-2', 2);
        Employee::factory()->create([
            'nik' => '88005001',
            'jabatan' => 'Manajer Band Lama',
            'unit_kerja' => 'Kebun Band Lama',
            'level' => 'Karpim',
            'rm_level' => 'RM-2',
        ]);
        $mismatch = $this->karpimImportRow('88005001', 'Manajer Band Lama', 'Kebun Band Lama');
        $mismatch[12] = 'RM-1';

        $this->actingAs($this->admin)->post(route('admin.employees.import'), ['import_file' => $this->createExcelUploadFile([$mismatch])]);

        $this->assertStringContainsString('tidak sesuai dengan formasi jabatan', session('import_summary')['errors'][0]['errors'][0]);
        $this->assertDatabaseHas('employees', ['nik' => '88005001', 'rm_level' => 'RM-2']);
    }

    public function test_import_matches_unit_names_ignoring_ampersand_versus_dan(): void
    {
        $unit = Unit::create(['nama' => 'Bagian Akuntansi & Keuangan', 'kode' => 'Bagian', 'wilayah' => 'Regional Office (Pontianak)', 'is_active' => true, 'urutan' => 5]);
        $kepala = Position::firstOrCreate(['nama' => 'Kepala Bagian', 'level' => 'Karpim'], ['rm_level' => 'RM-1', 'is_active' => true]);
        $unit->positions()->attach($kepala->id, ['kuota' => 1, 'bidang' => 'KEU']);

        $file = $this->createExcelUploadFile([$this->karpimImportRow('88001005', 'Kepala Bagian', 'Bagian Akuntansi dan Keuangan')]);

        $this->actingAs($this->admin)->post(route('admin.employees.import'), ['import_file' => $file]);

        $this->assertSame([], session('import_summary')['errors'] ?? [], 'Baris import seharusnya valid.');
        $this->assertDatabaseHas('employees', ['nik' => '88001005', 'unit_kerja' => 'Bagian Akuntansi & Keuangan']);
    }

    public function test_import_rejects_a_deactivated_unit_including_via_alias(): void
    {
        $kepala = Position::firstOrCreate(['nama' => 'Kepala Bagian', 'level' => 'Karpim'], ['rm_level' => 'RM-1', 'is_active' => true]);
        foreach (['Kebun Nonaktif', 'PMO Batu Bara & Bisnis Lainnya'] as $i => $name) {
            $unit = Unit::create(['nama' => $name, 'kode' => 'Kebun', 'wilayah' => 'Wilayah Kalimantan Barat', 'is_active' => false, 'urutan' => 90 + $i]);
            $unit->positions()->attach($kepala->id, ['kuota' => 1, 'bidang' => 'UMU']);
        }

        $file = $this->createExcelUploadFile([
            $this->karpimImportRow('88001006', 'Kepala Bagian', 'Kebun Nonaktif'),
            $this->karpimImportRow('88001007', 'Kepala Bagian', 'PROJECT MANAGEMENT OFFICE (PMO) BATU BARA DAN BISNIS LAINNYA'),
        ]);

        $this->actingAs($this->admin)->post(route('admin.employees.import'), ['import_file' => $file]);

        $summary = session('import_summary');
        $this->assertEquals(2, $summary['skipped']);
        $this->assertStringContainsString('tidak terdaftar di Master Unit', $summary['errors'][0]['errors'][0]);
        $this->assertDatabaseMissing('employees', ['nik' => '88001006']);
        $this->assertDatabaseMissing('employees', ['nik' => '88001007']);
    }

    public function test_import_rejects_unit_names_that_were_retired(): void
    {
        $file = $this->createExcelUploadFile([
            $this->karpimImportRow('88001002', 'Asisten Auditor VA', 'Satuan Pengawasan Internal (SPI)'),
            $this->karpimImportRow('88001003', 'Group Manager', 'Unit Group Kalimantan Barat'),
        ]);

        $this->actingAs($this->admin)->post(route('admin.employees.import'), ['import_file' => $file]);

        $summary = session('import_summary');
        $this->assertEquals(2, $summary['skipped']);
        $this->assertStringContainsString('tidak terdaftar di Master Unit', $summary['errors'][0]['errors'][0]);
        $this->assertDatabaseMissing('employees', ['nik' => '88001002']);
        $this->assertDatabaseMissing('employees', ['nik' => '88001003']);
    }

    public function test_retired_unit_alias_does_not_resolve(): void
    {
        $file = $this->createExcelUploadFile([$this->karpimImportRow('88001004', 'Head of PMO', 'PMO')]);

        $this->actingAs($this->admin)->post(route('admin.employees.import'), ['import_file' => $file]);

        $summary = session('import_summary');
        $this->assertEquals(1, $summary['skipped']);
        $this->assertStringContainsString('tidak terdaftar di Master Unit', $summary['errors'][0]['errors'][0]);
        $this->assertDatabaseMissing('employees', ['nik' => '88001004']);
    }

    public function test_import_with_pmo_unit_alias_and_head_of_pmo_investasi_tanaman(): void
    {
        $pmoUnit = Unit::firstOrCreate(
            ['nama' => 'PMO Investasi Tanaman Wilayah Kaltim & Kalsel-Teng'],
            ['kode' => 'PMO', 'wilayah' => 'Regional Office (Pontianak)', 'is_active' => true]
        );

        $pos = Position::firstOrCreate(
            ['nama' => 'Head of PMO Investasi Tanamanan Wilayah Kaltim & Kalsel-Teng'],
            ['bidang' => 'TAN', 'level' => 'Karpim', 'rm_level' => 'RM-1', 'is_active' => true]
        );

        $pmoUnit->positions()->syncWithoutDetaching([
            $pos->id => ['kuota' => 1],
        ]);

        // Nama lama pada berkas dipetakan lewat alias yang tercatat di Master Data, bukan konstanta kode.
        UnitAlias::remember($pmoUnit, 'PROJECT MANAGEMENT OFFICE (PMO) INVESTASI TANAMAN WILAYAH KALTIM & KALSEL-TENG', 'baseline');

        $rows = [
            [
                '13001428', 'JAN PURDY RAJAGUKGUK', 'L', 'Bakara', '1971-01-11',
                'Kristen Protestan', 'K/3', 'Tetap (PKWTT)',
                'Head of PMO Investasi Tanamanan Wilayah Kaltim & Kalsel-Teng', 'Karpim',
                'PROJECT MANAGEMENT OFFICE (PMO) INVESTASI TANAMAN WILAYAH KALTIM & KALSEL-TENG',
                'IVB/05', 'RM-1', 'Definitif',
                '1997-03-21', '1999-09-01', '2026-04-01', '2026-04-01',
                'Reguler', 15, 15, 'S1', 'Universitas',
                '2026-01-01', '2027-01-01',
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

        $employee = Employee::where('nik', '13001428')->firstOrFail();
        $this->assertEquals('PMO Investasi Tanaman Wilayah Kaltim & Kalsel-Teng', $employee->unit_kerja);
        $this->assertEquals('Head of PMO Investasi Tanamanan Wilayah Kaltim & Kalsel-Teng', $employee->jabatan);
        $this->assertEquals('Karpim', $employee->level);
        $this->assertEquals('RM-1', $employee->rm_level);
    }

    public function test_reimport_identical_employee_detected_as_unchanged_and_skips_database_update(): void
    {
        // 1. Buat karyawan definitif lengkap di database
        $employee = Employee::create([
            'nik' => '13008888',
            'nama' => 'JOKO SUSILO',
            'jenis_kelamin' => 'L',
            'tempat_lahir' => 'Pontianak',
            'tanggal_lahir' => '1985-05-15',
            'agama' => 'Islam',
            'susunan_keluarga' => 'K/2',
            'status_karyawan' => 'Tetap (PKWTT)',
            'jabatan' => 'Asisten Afdeling',
            'level' => 'Karpim',
            'unit_kerja' => 'Kebun Inti Gunung Meliau',
            'golongan' => 'IIIA/00',
            'rm_level' => 'RM-3',
            'status_penugasan' => 'Definitif',
            'tmt_bekerja' => '2010-01-01',
            'tanggal_diangkat_staf' => '2012-01-01',
            'tanggal_dalam_jabatan' => '2020-01-01',
            'tmt_unit_kerja' => '2020-01-01',
            'jalur_masuk' => 'Reguler',
            'job_grade' => 11,
            'person_grade' => 11,
            'pendidikan_terakhir' => 'S1',
            'sekolah' => 'Universitas Tanjungpura',
            'tanggal_pensiun' => '2041-05-01',
            'tanggal_mbt' => '2040-05-01',
        ]);

        $initialUpdatedAt = $employee->updated_at->toIso8601String();

        // 2. Buat berkas Excel dengan data yang 100% identik persis
        $rows = [
            [
                '13008888', 'JOKO SUSILO', 'L', 'Pontianak', '1985-05-15',
                'Islam', 'K/2', 'Tetap (PKWTT)', 'Asisten Afdeling', 'Karpim',
                'Kebun Inti Gunung Meliau', 'IIIA/00', 'RM-3', 'Definitif',
                '2010-01-01', '2012-01-01', '2020-01-01', '2020-01-01',
                'Reguler', 11, 11, 'S1', 'Universitas Tanjungpura',
                '2040-05-01', '2041-05-01',
            ],
        ];

        $file = $this->createExcelUploadFile($rows);

        // 3. Dry-Run Preview: Harus terdeteksi UNCHANGED / TETAP, bukan UPDATE
        $previewResponse = $this->actingAs($this->admin)->postJson(route('admin.employees.import.preview'), [
            'import_file' => $file,
        ]);

        $previewResponse->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('summary.created', 0)
            ->assertJsonPath('summary.updated', 0)
            ->assertJsonPath('summary.unchanged', 1)
            ->assertJsonPath('summary.failed_count', 0)
            ->assertJsonPath('summary.sample_rows.0.action', 'UNCHANGED');

        $token = $previewResponse->json('token');

        // 4. Commit Import: Eksekusi lewati SQL update
        $commitResponse = $this->actingAs($this->admin)->postJson(route('admin.employees.import.commit'), [
            'token' => $token,
        ]);

        $commitResponse->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('summary.created', 0)
            ->assertJsonPath('summary.updated', 0)
            ->assertJsonPath('summary.unchanged', 1);

        // 5. Pastikan record di database tetap utuh dan timestamp updated_at tidak terganggu
        $refreshedEmployee = Employee::where('nik', '13008888')->firstOrFail();
        $this->assertEquals($initialUpdatedAt, $refreshedEmployee->updated_at->toIso8601String());
    }

    /**
     * Uji Penguatan Keamanan: Sanitasi Formula Injection (CWE-1236 / OWASP) pada Ekspor Baris Gagal.
     */
    public function test_failed_employees_export_sanitizes_formula_injection_characters(): void
    {
        $this->assertEquals("'=SUM(A1:A10)", FailedEmployeesExport::sanitizeCellValue('=SUM(A1:A10)'));
        $this->assertEquals("'+12345", FailedEmployeesExport::sanitizeCellValue('+12345'));
        $this->assertEquals("'-100", FailedEmployeesExport::sanitizeCellValue('-100'));
        $this->assertEquals("'@malicious", FailedEmployeesExport::sanitizeCellValue('@malicious'));
        $this->assertEquals("'\tTabbed", FailedEmployeesExport::sanitizeCellValue("\tTabbed"));
        $this->assertEquals('Normal Text', FailedEmployeesExport::sanitizeCellValue('Normal Text'));
        $this->assertEquals('', FailedEmployeesExport::sanitizeCellValue(null));

        $failedRows = [
            [
                'row' => 2,
                'nik' => '=13009988',
                'errors' => ['=HYPERLINK("http://evil.com")'],
                'raw_data' => [
                    'nik' => '=13009988',
                    'nama' => '+DROP TABLE',
                ],
            ],
        ];

        $export = new FailedEmployeesExport($failedRows);
        $data = $export->array();

        $this->assertCount(1, $data);
        $this->assertEquals("'=HYPERLINK(\"http://evil.com\")", $data[0][1]); // reasons
        $this->assertEquals("'=13009988", $data[0][2]); // NIK
        $this->assertEquals("'+DROP TABLE", $data[0][3]); // Nama
    }

    /**
     * Uji Integritas Komit: Kebijakan komit parsial menyimpan baris valid dan mengisolasi baris gagal.
     */
    public function test_commit_import_persists_valid_rows_and_isolates_failed_rows(): void
    {
        // 1. Buat file Excel dengan 1 baris valid dan 1 baris invalid (Job Grade 5 untuk Karpim -> Ditolak)
        $rows = [
            [
                '13007765',
                'Valid Employee Mixed',
                'L',
                'Pontianak',
                '1992-04-10',
                'Islam',
                'K/0',
                'PKWTT',
                'Asisten Afdeling',
                'Karpim',
                'Kebun Inti Gunung Meliau',
                'IIIA/00',
                'RM-3',
                'Definitif',
                '2015-01-01',
                '2018-01-01',
                '2021-01-01',
                '2021-01-01',
                'Reguler',
                12,
                12,
                'D4 / S1',
                'INSTIPER',
                '',
                '',
            ],
            [
                '13007766',
                'Invalid Grade Karpim',
                'L',
                'Pontianak',
                '1992-04-10',
                'Islam',
                'K/0',
                'PKWTT',
                'Asisten Afdeling',
                'Karpim',
                'Kebun Inti Gunung Meliau',
                'IIIA/00',
                'RM-3',
                'Definitif',
                '2015-01-01',
                '2018-01-01',
                '2021-01-01',
                '2021-01-01',
                'Reguler',
                5, // INVALID untuk Karpim (wajib 11-16)
                5,
                'D4 / S1',
                'INSTIPER',
                '',
                '',
            ],
        ];

        $file = $this->createExcelUploadFile($rows);

        // 2. Preview Dry-Run
        $previewResponse = $this->actingAs($this->admin)->postJson(route('admin.employees.import.preview'), [
            'import_file' => $file,
        ]);

        $previewResponse->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('summary.created', 1)
            ->assertJsonPath('summary.skipped', 1);

        $token = $previewResponse->json('token');

        // 3. Commit Tunggal (Simpan Parsial) -> Berhasil menyimpan 1 valid dan mengisolasi 1 gagal
        $commitResponse = $this->actingAs($this->admin)->postJson(route('admin.employees.import.commit'), [
            'token' => $token,
        ]);

        $commitResponse->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('summary.created', 1)
            ->assertJsonPath('summary.skipped', 1);

        // Pastikan data valid tersimpan dan data tidak valid diisolasi
        $this->assertDatabaseHas('employees', ['nik' => '13007765']);
        $this->assertDatabaseMissing('employees', ['nik' => '13007766']);
    }

    /**
     * Uji Integritas Komit: Berhasil menyimpan ketika seluruh baris 100% valid.
     */
    public function test_commit_import_succeeds_with_clean_dataset(): void
    {
        $rows = [
            [
                '13008899',
                'Valid Employee Strict',
                'L',
                'Pontianak',
                '1992-04-10',
                'Islam',
                'K/0',
                'PKWTT',
                'Asisten Afdeling',
                'Karpim',
                'Kebun Inti Gunung Meliau',
                'IIIA/00',
                'RM-3',
                'Definitif',
                '2015-01-01',
                '2018-01-01',
                '2021-01-01',
                '2021-01-01',
                'Reguler',
                12,
                12,
                'D4 / S1',
                'INSTIPER',
                '',
                '',
            ],
        ];

        $file = $this->createExcelUploadFile($rows);

        $previewResponse = $this->actingAs($this->admin)->postJson(route('admin.employees.import.preview'), [
            'import_file' => $file,
        ]);

        $previewResponse->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('summary.skipped', 0);

        $token = $previewResponse->json('token');

        $commitResponse = $this->actingAs($this->admin)->postJson(route('admin.employees.import.commit'), [
            'token' => $token,
        ]);

        $commitResponse->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('summary.created', 1);

        $this->assertDatabaseHas('employees', ['nik' => '13008899']);
    }

    /**
     * Uji Endpoint Polling Status Antrean Kerja Impor (Hybrid Execution).
     */
    public function test_import_status_endpoint_returns_job_progress(): void
    {
        $token = 'test-token-'.uniqid();

        // 404 jika token belum terdaftar
        $notFoundResponse = $this->actingAs($this->admin)->getJson(route('admin.employees.import.status', ['token' => $token]));
        $notFoundResponse->assertStatus(404);

        // Simulasi status job tersimpan di Cache
        Cache::put("import_job_{$token}", [
            'status' => 'processing',
            'progress' => 60,
            'message' => 'Sedang memproses baris data...',
            'token' => $token,
            'type' => 'preview',
        ], now()->addMinutes(10));

        $statusResponse = $this->actingAs($this->admin)->getJson(route('admin.employees.import.status', ['token' => $token]));
        $statusResponse->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('status', 'processing')
            ->assertJsonPath('progress', 60);
    }

    /**
     * Uji Fail-Safe Auto-Fallback ke Sinkron jika Worker Antrean tidak merespons (5-detik SLA).
     */
    public function test_import_status_endpoint_supports_fail_safe_auto_fallback_to_sync(): void
    {
        $rows = [
            [
                '13009911',
                'Fallback Employee Test',
                'L',
                'Pontianak',
                '1993-02-15',
                'Islam',
                'K/0',
                'PKWTT',
                'Asisten Afdeling',
                'Karpim',
                'Kebun Inti Gunung Meliau',
                'IIIA/00',
                'RM-3',
                'Definitif',
                '2016-01-01',
                '2019-01-01',
                '2022-01-01',
                '2022-01-01',
                'Reguler',
                11,
                11,
                'D4 / S1',
                'INSTIPER',
                '',
                '',
            ],
        ];

        $file = $this->createExcelUploadFile($rows);

        $previewResponse = $this->actingAs($this->admin)->postJson(route('admin.employees.import.preview'), [
            'import_file' => $file,
        ]);

        $token = $previewResponse->json('token');

        // Simulasikan token sedang berada di antrean commit (processing)
        Cache::put("import_commit_job_{$token}", [
            'status' => 'processing',
            'progress' => 15,
            'message' => 'Menunggu worker antrean...',
            'token' => $token,
            'type' => 'commit',
        ], now()->addMinutes(10));

        // Panggil endpoint status dengan query fallback_sync=1
        $fallbackResponse = $this->actingAs($this->admin)->getJson(route('admin.employees.import.status', [
            'token' => $token,
            'fallback_sync' => 1,
        ]));

        $fallbackResponse->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('fallback_executed', true)
            ->assertJsonPath('created', 1);

        $this->assertDatabaseHas('employees', ['nik' => '13009911']);
    }

    /**
     * Uji Artisan Console Command untuk Pembersihan Berkas Kedaluwarsa Terjadwal.
     */
    public function test_console_command_purges_expired_import_files(): void
    {
        $this->artisan('hris:purge-import-files', ['--hours' => 2])
            ->expectsOutputToContain('Berhasil membersihkan')
            ->assertExitCode(0);
    }

    /**
     * Uji deteksi inspeksi diff atribut lama vs baru untuk data status UPDATE pada sesi pratinjau.
     */
    public function test_dry_run_preview_detects_attribute_diff_for_update_rows(): void
    {
        // Siapkan karyawan eksisting
        Employee::factory()->create([
            'nik' => '13008899',
            'nama' => 'HENDRA WIJAYA',
            'jabatan' => 'Asisten Afdeling',
            'level' => 'Karpim',
            'unit_kerja' => $this->validUnit->nama,
            'golongan' => 'IIIA/00',
            'rm_level' => 'RM-3',
            'agama' => 'Islam',
            'status_karyawan' => 'Tetap (PKWTT)',
        ]);

        $rows = [
            [
                '13008899',
                'HENDRA WIJAYA',
                'L',
                'Pontianak',
                '1990-01-01',
                'Islam',
                'K/1',
                'Tetap (PKWTT)',
                'Asisten Afdeling',
                'Karpim',
                $this->validUnit->nama,
                'IIIB/00', // Berubah dari IIIA/00 ke IIIB/00
                'RM-2',    // Berubah dari RM-3 ke RM-2
                'Definitif',
                '',
                '2015-01-01',
                '2015-01-01',
                '2020-01-01',
                '2020-01-01',
                'Reguler',
                '12',
                '12',
                'D4 / S1',
                'Universitas Tanjungpura',
                '',
                '',
            ],
        ];

        $file = $this->createExcelUploadFile($rows);

        $response = $this->actingAs($this->admin)->postJson(route('admin.employees.import.preview'), [
            'import_file' => $file,
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('summary.updated', 1)
            ->assertJsonPath('summary.created', 0);

        $sampleRows = $response->json('summary.sample_rows');
        $this->assertNotEmpty($sampleRows);
        $this->assertEquals('UPDATE', $sampleRows[0]['action']);
        $this->assertNotEmpty($sampleRows[0]['diff']);

        $diffFields = collect($sampleRows[0]['diff'])->pluck('field')->toArray();
        $this->assertContains('golongan', $diffFields);
        $this->assertContains('rm_level', $diffFields);

        // Pastikan nilai old dan new akurat
        $golDiff = collect($sampleRows[0]['diff'])->firstWhere('field', 'golongan');
        $this->assertEquals('IIIA/00', $golDiff['old']);
        $this->assertEquals('IIIB/00', $golDiff['new']);
    }

    /**
     * Uji unduh rekapitulasi laporan impor pasca-komit (.xlsx).
     */
    public function test_admin_can_download_import_summary_report(): void
    {
        $rows = [
            [
                '13007788',
                'RATNA SARI',
                'P',
                'Medan',
                '1992-04-10',
                'Islam',
                'TK',
                'Tetap (PKWTT)',
                'Mandor Panen',
                'Karpel',
                $this->validUnit->nama,
                'IIC/00',
                'RM-4',
                'Definitif',
                '',
                '2018-01-01',
                '',
                '2020-01-01',
                '2020-01-01',
                'Reguler',
                '5',
                '5',
                'SMA/SMK',
                'SMK Negeri 1',
                '',
                '',
            ],
        ];

        $file = $this->createExcelUploadFile($rows);

        // 1. Jalankan preview
        $previewRes = $this->actingAs($this->admin)->postJson(route('admin.employees.import.preview'), [
            'import_file' => $file,
        ]);
        $token = $previewRes->json('token');

        // 2. Eksekusi commit
        $commitRes = $this->actingAs($this->admin)->postJson(route('admin.employees.import.commit'), [
            'token' => $token,
        ]);
        $commitRes->assertOk()->assertJsonPath('success', true);

        // 3. Unduh laporan rekapitulasi impor
        $reportRes = $this->actingAs($this->admin)->post(route('admin.employees.import.downloadReport'), [
            'token' => $token,
        ]);

        $reportRes->assertOk();
        $this->assertStringContainsString('spreadsheetml', $reportRes->headers->get('content-type'));
        $this->assertStringContainsString('rekap_laporan_impor_karyawan_', $reportRes->headers->get('content-disposition'));

        // 4. Unduh dengan token tidak valid harus ditolak
        $invalidRes = $this->actingAs($this->admin)->post(route('admin.employees.import.downloadReport'), [
            'token' => 'invalid-token-xyz',
        ]);
        $invalidRes->assertRedirect();
        $invalidRes->assertSessionHas('error');

        // 5. Validasi struktur baris dan header sheet Ringkasan & Metadata
        $export = new ImportSummaryExport([
            'summary' => [
                'total_processed' => 100,
                'created' => 20,
                'updated' => 30,
                'unchanged' => 50,
                'skipped' => 0,
            ],
        ]);
        $sheets = $export->sheets();
        $this->assertCount(2, $sheets);
        $summarySheet = $sheets[0];
        $this->assertEquals('Ringkasan & Metadata', $summarySheet->title());
        $arrayData = $summarySheet->array();

        // Baris 3 (index 2): Header Parameter Sistem
        $this->assertEquals(['PARAMETER SISTEM', 'NILAI / KETERANGAN'], $arrayData[2]);
        // Baris 11 (index 10): Baris kosong pemisah
        $this->assertEquals([''], $arrayData[10]);
        // Baris 12 (index 11): Header Ringkasan Metrik Hasil Impor
        $this->assertEquals(['RINGKASAN METRIK HASIL IMPOR', 'JUMLAH BARIS', 'PERSENTASE'], $arrayData[11]);
        // Baris 13 (index 12): Data Total Baris Diproses
        $this->assertEquals(['Total Baris Diproses', 100, '100%'], $arrayData[12]);
    }

    /**
     * Memastikan jika dalam berkas spreadsheet terdapat NIK ganda, baris duplikat berikutnya
     * otomatis dikarantina dengan pesan informatif dan tidak menimpa data baris pertama.
     */
    public function test_import_quarantines_duplicate_niks_within_same_file(): void
    {
        $rows = [
            // Baris 2: NIK 13008899 pertama (valid)
            [
                '13008899', 'BUDI SANTOSO', 'L', 'Pontianak', '1992-05-10',
                'Islam', 'K/1', 'Tetap (PKWTT)', 'Asisten Afdeling', 'Karpim',
                'Kebun Inti Gunung Meliau', 'IIIB/00', 'RM-3', 'Definitif', '',
                '2015-01-01', '2018-01-01', '2020-01-01', '2020-01-01',
                'Reguler', 12, 12, 'S1', 'Universitas Tanjungpura',
                '2047-05-01', '2048-05-01',
            ],
            // Baris 3: NIK 13008899 kedua (duplikat dalam berkas yang sama)
            [
                '13008899', 'BUDI SANTOSO DUPLIKAT', 'L', 'Pontianak', '1992-05-10',
                'Islam', 'K/1', 'Tetap (PKWTT)', 'Asisten Afdeling', 'Karpim',
                'Kebun Inti Gunung Meliau', 'IIIB/00', 'RM-3', 'Definitif', '',
                '2015-01-01', '2018-01-01', '2020-01-01', '2020-01-01',
                'Reguler', 12, 12, 'S1', 'Universitas Tanjungpura',
                '2047-05-01', '2048-05-01',
            ],
        ];

        $file = $this->createExcelUploadFile($rows, 'duplicate_niks_test.xlsx');

        $previewResponse = $this->actingAs($this->admin)->postJson(route('admin.employees.import.preview'), [
            'import_file' => $file,
        ]);

        $previewResponse->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('summary.created', 1)
            ->assertJsonPath('summary.skipped', 1);

        $token = $previewResponse->json('token');

        // Komit data
        $commitResponse = $this->actingAs($this->admin)->postJson(route('admin.employees.import.commit'), [
            'token' => $token,
        ]);

        $commitResponse->assertOk()
            ->assertJsonPath('summary.created', 1)
            ->assertJsonPath('summary.skipped', 1);

        // Hanya baris pertama yang tersimpan
        $employee = Employee::find('13008899');
        $this->assertNotNull($employee);
        $this->assertEquals('BUDI SANTOSO', $employee->nama);
    }

    private function previewToken(array $rows): string
    {
        $response = $this->actingAs($this->admin)
            ->post(route('admin.employees.import.preview'), ['import_file' => $this->createExcelUploadFile($rows)])
            ->assertOk();

        return $response->json('token');
    }

    public function test_commit_is_all_or_nothing_when_a_row_hits_a_system_error(): void
    {
        $this->formasiUnit('Kebun Atomik', 'Manajer Atomik', 'RM-1', 5);
        $token = $this->previewToken([
            $this->karpimImportRow('88007001', 'Manajer Atomik', 'Kebun Atomik'),
            $this->karpimImportRow('88007002', 'Manajer Atomik', 'Kebun Atomik'),
            $this->karpimImportRow('88007003', 'Manajer Atomik', 'Kebun Atomik'),
        ]);

        // Kendala sistem (bukan aturan data) pada baris kedua saat penulisan nyata.
        Employee::saving(function (Employee $employee) {
            if ($employee->nik === '88007002') {
                throw new \RuntimeException('Simulasi kegagalan penulisan');
            }
        });

        $this->actingAs($this->admin)
            ->postJson(route('admin.employees.import.commit'), ['token' => $token])
            ->assertStatus(422);

        $this->assertDatabaseMissing('employees', ['nik' => '88007001']);
        $this->assertDatabaseMissing('employees', ['nik' => '88007003']);
        $this->assertSame(0, EmployeeImportLog::count());
        $this->assertSame(0, JobHistory::whereIn('employee_nik', ['88007001', '88007003'])->count());
    }

    public function test_commit_rolls_back_the_data_when_the_audit_log_cannot_be_written(): void
    {
        $this->formasiUnit('Kebun Audit', 'Manajer Audit', 'RM-1', 5);
        $token = $this->previewToken([$this->karpimImportRow('88007101', 'Manajer Audit', 'Kebun Audit')]);

        EmployeeImportLog::creating(fn () => throw new \RuntimeException('Simulasi kegagalan audit'));

        $response = $this->actingAs($this->admin)->postJson(route('admin.employees.import.commit'), ['token' => $token]);

        $response->assertStatus(422);
        $this->assertStringNotContainsString('Simulasi', $response->json('message'), 'Rincian internal tidak boleh sampai ke peramban.');
        $this->assertStringContainsString('Tidak ada data yang tersimpan', $response->json('message'));
        $this->assertDatabaseMissing('employees', ['nik' => '88007101']);
    }

    public function test_commit_refuses_a_file_that_changed_after_the_preview(): void
    {
        $this->formasiUnit('Kebun Hash', 'Manajer Hash', 'RM-1', 5);
        $token = $this->previewToken([$this->karpimImportRow('88007201', 'Manajer Hash', 'Kebun Hash')]);

        $swapped = $this->createExcelUploadFile([$this->karpimImportRow('88007299', 'Manajer Hash', 'Kebun Hash')]);
        Storage::disk('local')->put(Cache::get("import_preview_{$token}")['file_path'], file_get_contents($swapped->getRealPath()));

        $response = $this->actingAs($this->admin)->postJson(route('admin.employees.import.commit'), ['token' => $token]);

        $response->assertStatus(422);
        $this->assertStringContainsString('berubah sejak pratinjau', $response->json('message'));
        $this->assertDatabaseMissing('employees', ['nik' => '88007299']);
        $this->assertDatabaseMissing('employees', ['nik' => '88007201']);
    }

    public function test_commit_audit_log_records_the_sha256_of_the_committed_file(): void
    {
        $this->formasiUnit('Kebun Jejak', 'Manajer Jejak', 'RM-1', 5);
        $file = $this->createExcelUploadFile([$this->karpimImportRow('88007301', 'Manajer Jejak', 'Kebun Jejak')]);
        $expected = hash_file('sha256', $file->getRealPath());

        $token = $this->actingAs($this->admin)
            ->post(route('admin.employees.import.preview'), ['import_file' => $file])
            ->json('token');
        $this->actingAs($this->admin)->postJson(route('admin.employees.import.commit'), ['token' => $token])->assertOk();

        $this->assertSame($expected, EmployeeImportLog::sole()->metadata['file_sha256']);
    }

    public function test_the_same_preview_token_cannot_be_committed_twice_in_parallel(): void
    {
        $this->formasiUnit('Kebun Ganda', 'Manajer Ganda', 'RM-1', 5);
        $token = $this->previewToken([$this->karpimImportRow('88007401', 'Manajer Ganda', 'Kebun Ganda')]);

        // Permintaan pertama sedang berjalan (penanda terpasang): permintaan kedua harus ditolak.
        Cache::put("import_commit_started_{$token}", true, now()->addMinutes(5));

        $response = $this->actingAs($this->admin)->postJson(route('admin.employees.import.commit'), ['token' => $token]);

        $response->assertStatus(422);
        $this->assertStringContainsString('sudah dijalankan', $response->json('message'));
        $this->assertDatabaseMissing('employees', ['nik' => '88007401']);
    }

    public function test_a_failed_commit_releases_the_token_so_the_admin_can_retry(): void
    {
        $this->formasiUnit('Kebun Ulang', 'Manajer Ulang', 'RM-1', 5);
        $token = $this->previewToken([$this->karpimImportRow('88007501', 'Manajer Ulang', 'Kebun Ulang')]);

        $path = Cache::get("import_preview_{$token}")['file_path'];
        $original = Storage::disk('local')->get($path);
        Storage::disk('local')->put($path, 'rusak');

        $this->actingAs($this->admin)->postJson(route('admin.employees.import.commit'), ['token' => $token])->assertStatus(422);

        Storage::disk('local')->put($path, $original);
        $this->actingAs($this->admin)->postJson(route('admin.employees.import.commit'), ['token' => $token])->assertOk();

        $this->assertDatabaseHas('employees', ['nik' => '88007501']);
    }

    public function test_only_a_real_commit_locks_the_unit_rows_it_counts_against_the_quota(): void
    {
        $this->assertTrue((new EmployeesImport(dryRun: false))->validator->lockSeats);
        $this->assertFalse((new EmployeesImport(dryRun: true))->validator->lockSeats);

        $this->formasiUnit('Kebun Kunci', 'Manajer Kunci', 'RM-1', 1);
        $token = $this->previewToken([$this->karpimImportRow('88007601', 'Manajer Kunci', 'Kebun Kunci')]);

        $this->actingAs($this->admin)->postJson(route('admin.employees.import.commit'), ['token' => $token])->assertOk();

        $this->assertDatabaseHas('employees', ['nik' => '88007601']);
    }

    public function test_preview_rejects_an_unreadable_file_cleanly_and_removes_it(): void
    {
        $fake = UploadedFile::fake()->createWithContent('karyawan.xlsx', 'ini bukan spreadsheet');
        $before = Storage::disk('local')->files('imports');

        $this->actingAs($this->admin)
            ->postJson(route('admin.employees.import.preview'), ['import_file' => $fake])
            ->assertStatus(422)
            ->assertJsonPath('success', false);

        $this->assertSame($before, Storage::disk('local')->files('imports'), 'Berkas yang tidak terbaca tidak boleh tertinggal di disk.');
    }

    public function test_import_resolves_a_legacy_unit_name_from_the_unit_alias_master(): void
    {
        $unit = $this->formasiUnit('Kebun Nama Baru', 'Manajer Alias', 'RM-1', 2);
        UnitAlias::remember($unit, 'Kebun Nama Lama', 'rename');

        $file = $this->createExcelUploadFile([$this->karpimImportRow('88008001', 'Manajer Alias', 'KEBUN NAMA LAMA')]);
        $this->actingAs($this->admin)->post(route('admin.employees.import'), ['import_file' => $file]);

        $this->assertDatabaseHas('employees', ['nik' => '88008001', 'unit_kerja' => 'Kebun Nama Baru']);
    }

    public function test_import_does_not_resolve_an_alias_of_an_inactive_unit(): void
    {
        $unit = $this->formasiUnit('Kebun Tutup', 'Manajer Tutup', 'RM-1', 2);
        UnitAlias::remember($unit, 'Kebun Tutup Lama', 'rename');
        $unit->update(['is_active' => false]);

        $file = $this->createExcelUploadFile([$this->karpimImportRow('88008101', 'Manajer Tutup', 'Kebun Tutup Lama')]);
        $this->actingAs($this->admin)->post(route('admin.employees.import'), ['import_file' => $file]);

        $this->assertDatabaseMissing('employees', ['nik' => '88008101']);
        $this->assertStringContainsString('tidak terdaftar', session('import_summary')['errors'][0]['errors'][0]);
    }

    public function test_import_accepts_every_rm_band_in_the_official_list_and_rejects_others(): void
    {
        $this->formasiUnit('Kebun RM Resmi', 'Manajer RM Resmi', 'RM-1', 1);
        $ok = $this->karpimImportRow('88008201', 'Manajer RM Resmi', 'Kebun RM Resmi');
        $bad = $this->karpimImportRow('88008202', 'Manajer RM Resmi', 'Kebun RM Resmi');
        $bad[12] = 'RM-9';

        $this->actingAs($this->admin)->post(route('admin.employees.import'), ['import_file' => $this->createExcelUploadFile([$ok, $bad])]);

        $this->assertDatabaseHas('employees', ['nik' => '88008201']);
        $this->assertStringContainsString('RM-1 sampai RM-7', session('import_summary')['errors'][0]['errors'][0]);
    }

    public function test_existing_employee_whose_position_left_the_formasi_gets_a_warning_but_is_still_imported(): void
    {
        $this->formasiUnit('Kebun Geser', 'Manajer Geser', 'RM-1', 2);
        Employee::factory()->create([
            'nik' => '88008301', 'nama' => 'LAMA', 'jabatan' => 'Jabatan Dihapus', 'unit_kerja' => 'Kebun Geser',
            'level' => 'Karpim', 'rm_level' => null, 'status_penugasan' => 'Definitif',
        ]);

        $row = $this->karpimImportRow('88008301', 'Jabatan Dihapus', 'Kebun Geser');
        $row[1] = 'NAMA DIPERBARUI';
        $row[12] = '';
        $response = $this->actingAs($this->admin)->post(route('admin.employees.import.preview'), ['import_file' => $this->createExcelUploadFile([$row])]);

        $response->assertOk()->assertJsonPath('summary.skipped', 0)->assertJsonPath('summary.warning_count', 1);
        $this->assertStringContainsString('tidak lagi tercantum', $response->json('summary.sample_rows.0.warnings.0'));

        $this->actingAs($this->admin)->postJson(route('admin.employees.import.commit'), ['token' => $response->json('token')])->assertOk();
        $this->assertSame('NAMA DIPERBARUI', Employee::find('88008301')->nama);
        $this->assertArrayNotHasKey('warnings', EmployeeImportLog::sole()->metadata['summary']);
        $this->assertSame(1, EmployeeImportLog::sole()->metadata['summary']['warning_count']);
    }

    public function test_existing_employee_in_an_overfilled_seat_gets_a_quota_warning(): void
    {
        $this->formasiUnit('Kebun Penuh', 'Manajer Penuh', 'RM-1', 1);
        foreach (['88008401', '88008402'] as $nik) {
            Employee::factory()->create([
                'nik' => $nik, 'jabatan' => 'Manajer Penuh', 'unit_kerja' => 'Kebun Penuh',
                'level' => 'Karpim', 'rm_level' => 'RM-1', 'status_penugasan' => 'Definitif',
            ]);
        }

        $response = $this->actingAs($this->admin)->post(route('admin.employees.import.preview'), [
            'import_file' => $this->createExcelUploadFile([$this->karpimImportRow('88008401', 'Manajer Penuh', 'Kebun Penuh')]),
        ]);

        $response->assertOk()->assertJsonPath('summary.warning_count', 1);
        $this->assertStringContainsString('melebihi kuota', $response->json('summary.sample_rows.0.warnings.0'));
    }

    public function test_existing_karpel_in_a_unit_without_karpel_formasi_is_not_warned(): void
    {
        $this->formasiUnit('Kebun Karpel', 'Manajer Karpel', 'RM-1', 1);
        Employee::factory()->create([
            'nik' => '88008501', 'jabatan' => 'Mandor Lepas', 'unit_kerja' => 'Kebun Karpel',
            'level' => 'Karpel', 'rm_level' => 'RM-4', 'status_penugasan' => 'Definitif', 'job_grade' => 5, 'person_grade' => 5,
        ]);

        $row = $this->karpimImportRow('88008501', 'Mandor Lepas', 'Kebun Karpel');
        $row[9] = 'Karpel';
        $row[12] = '';
        $row[19] = 5;
        $row[20] = 5;
        $response = $this->actingAs($this->admin)->post(route('admin.employees.import.preview'), ['import_file' => $this->createExcelUploadFile([$row])]);

        $response->assertOk()->assertJsonPath('summary.skipped', 0)->assertJsonPath('summary.warning_count', 0);
    }

    public function test_formasi_sheet_lists_every_seat_grouped_by_unit_with_vacancy(): void
    {
        $this->seed(PositionUnitSeeder::class);
        $tajati = Unit::where('nama', 'Kebun Tajati')->firstOrFail();
        $seatsOfTajati = DB::table('position_unit')->where('unit_id', $tajati->id)->count();
        Employee::factory()->create([
            'nik' => '88010001', 'unit_kerja' => 'Kebun Tajati', 'jabatan' => 'Manajer',
            'level' => 'Karpim', 'rm_level' => 'RM-1', 'status_penugasan' => 'Definitif',
        ]);

        $rows = (new EmployeeTemplateFormasiSheet)->array();
        $seatRows = array_values(array_filter($rows, fn (array $row) => $row[0] !== ''));

        $this->assertCount(DB::table('position_unit')->count(), $seatRows);

        // Setiap unit membentuk satu blok berurutan (syarat rentang bernama), tidak muncul kembali di tempat lain.
        $blocks = [];
        foreach (array_column($seatRows, 0) as $unit) {
            if (end($blocks) !== $unit) {
                $blocks[] = $unit;
            }
        }
        $this->assertSame(count($blocks), count(array_unique($blocks)));
        $this->assertCount($seatsOfTajati, array_filter($seatRows, fn (array $row) => $row[0] === 'Kebun Tajati'));

        $manajer = collect($seatRows)->first(fn (array $row) => $row[0] === 'Kebun Tajati' && $row[1] === 'Manajer');
        $this->assertSame(['Karpim', 'RM-1', 'TAN', 1, 1, 0], array_slice($manajer, 2, 6));
    }

    public function test_template_workbook_makes_jabatan_depend_on_the_selected_unit(): void
    {
        $this->seed(PositionUnitSeeder::class);

        $path = tempnam(sys_get_temp_dir(), 'tpl_').'.xlsx';
        file_put_contents($path, Excel::raw(new EmployeeTemplateExport, ExcelWriter::XLSX));
        $workbook = IOFactory::load($path);
        unlink($path);

        $data = $workbook->getSheetByName('Data Karyawan');
        $formasi = $workbook->getSheetByName('Formasi per Unit');
        $this->assertNotNull($formasi);
        $this->assertTrue($formasi->getProtection()->getSheet(), 'Katalog dikunci agar pengelompokan per unit tidak rusak.');
        $this->assertTrue($formasi->getProtection()->getSort(), 'Pengurutan dikunci karena merusak pengelompokan per unit.');
        $this->assertFalse($formasi->getProtection()->getAutoFilter(), 'Penyaringan tetap boleh.');
        $this->assertSame([], $workbook->getDefinedNames(), 'Tanpa rentang bernama atau kolom bantu: hanya katalog.');
        // Nol harus tertulis sebagai 0 di berkas (bukan sel kosong): Terisi 0 dan Sisa 0 sama-sama bermakna.
        $this->assertSame(0, $formasi->getCell('G2')->getValue(), 'Kolom Terisi tanpa pemangku harus bernilai 0.');
        $this->assertSame('', (string) $formasi->getCell('I1')->getValue());

        // Rumus validasi: Jabatan (J) bergantung pada Unit (I) di baris yang sama, dan menolak pilihan di luar daftar.
        $validation = $data->getCell('J7')->getDataValidation();
        $formula = $validation->getFormula1();
        $this->assertStringContainsString('$I7', $formula);
        $this->assertSame(DataValidation::STYLE_STOP, $validation->getErrorStyle());
        $this->assertSame(DataValidation::STYLE_STOP, $data->getCell('I7')->getDataValidation()->getErrorStyle());

        // Rumus yang persis sama dihitung oleh mesin kalkulasi untuk SETIAP unit: hasilnya harus tepat jabatan unit itu.
        $scratch = $workbook->getSheetByName('Referensi Master Data');
        $checked = 0;
        foreach (Unit::pluck('nama') as $unit) {
            // Mesin kalkulasi menyimpan hasil per sel; kosongkan agar tiap unit dihitung ulang.
            Calculation::getInstance($workbook)->clearCalculationCache();
            $scratch->setCellValue('Y1', $unit);
            $list = str_replace('$I7', '$Y$1', $formula);
            $scratch->setCellValue('Y2', "=ROWS({$list})");
            $scratch->setCellValue('Y3', "=INDEX({$list},1,1)");
            $scratch->setCellValue('Y4', '=INDEX('.$list.',ROWS('.$list.'),1)');

            $expected = DB::table('position_unit')
                ->join('units', 'units.id', '=', 'position_unit.unit_id')
                ->join('positions', 'positions.id', '=', 'position_unit.position_id')
                ->where('units.nama', $unit)
                ->orderByRaw("case positions.level when 'Karpim' then 0 else 1 end")->orderBy('positions.rm_level')->orderBy('positions.nama')
                ->pluck('positions.nama')->all();

            $this->assertSame(count($expected), (int) $scratch->getCell('Y2')->getCalculatedValue(), "Jumlah Jabatan untuk {$unit}");
            $this->assertSame($expected[0], $scratch->getCell('Y3')->getCalculatedValue(), "Jabatan pertama untuk {$unit}");
            $this->assertSame(end($expected), $scratch->getCell('Y4')->getCalculatedValue(), "Jabatan terakhir untuk {$unit}");
            $checked++;
        }
        $this->assertSame(Unit::count(), $checked);
    }

    public function test_import_derives_strata_when_the_level_column_is_blank(): void
    {
        $this->formasiUnit('Kebun Turunan', 'Manajer Turunan', 'RM-1', 2);
        $karpel = Position::create(['nama' => 'Mandor Turunan', 'level' => 'Karpel', 'rm_level' => 'RM-5', 'is_active' => true]);
        Unit::where('nama', 'Kebun Turunan')->first()->positions()->attach($karpel->id, ['kuota' => 2, 'bidang' => 'TAN']);

        $karpim = $this->karpimImportRow('88010101', 'Manajer Turunan', 'Kebun Turunan');
        $karpim[9] = '';
        $karpim[12] = '';
        $pelaksana = $this->karpimImportRow('88010102', 'Mandor Turunan', 'Kebun Turunan');
        $pelaksana[9] = '';
        $pelaksana[12] = '';
        $pelaksana[19] = 5;
        $pelaksana[20] = 5;

        $this->actingAs($this->admin)->post(route('admin.employees.import'), ['import_file' => $this->createExcelUploadFile([$karpim, $pelaksana])]);

        $this->assertSame([], session('import_summary')['errors'] ?? [], 'Kedua baris seharusnya valid tanpa kolom Level.');
        $this->assertDatabaseHas('employees', ['nik' => '88010101', 'level' => 'Karpim', 'rm_level' => 'RM-1']);
        $this->assertDatabaseHas('employees', ['nik' => '88010102', 'level' => 'Karpel', 'rm_level' => 'RM-5']);
    }

    public function test_import_works_with_the_template_format_that_has_no_level_or_rm_columns(): void
    {
        $this->formasiUnit('Kebun Tanpa Level', 'Manajer Tanpa Level', 'RM-1', 2);
        $headings = (new EmployeeTemplateDataSheet)->headings();
        $row = (new EmployeeTemplateDataSheet)->array()[0];
        $set = function (string $heading, mixed $value) use (&$row, $headings): void {
            $row[array_search($heading, $headings, true)] = $value;
        };
        $set('NIK*', '88010201');
        $set('UNIT KERJA*', 'Kebun Tanpa Level');
        $set('JABATAN*', 'Manajer Tanpa Level');
        $set('JOB GRADE', 13);
        $set('PERSON GRADE', 13);

        $this->assertNotContains('LEVEL', $headings);
        $this->actingAs($this->admin)->post(route('admin.employees.import'), ['import_file' => $this->createExcelUploadFile([$row], 'tanpa_level.xlsx', $headings)]);

        $this->assertSame([], session('import_summary')['errors'] ?? []);
        $this->assertDatabaseHas('employees', ['nik' => '88010201', 'level' => 'Karpim', 'rm_level' => 'RM-1']);
    }

    public function test_import_rejects_a_level_that_contradicts_the_formasi_strata(): void
    {
        $this->formasiUnit('Kebun Strata', 'Manajer Strata', 'RM-1', 2);
        $row = $this->karpimImportRow('88010301', 'Manajer Strata', 'Kebun Strata');
        $row[9] = 'Karpel';
        $row[19] = 5;
        $row[20] = 5;

        $this->actingAs($this->admin)->post(route('admin.employees.import'), ['import_file' => $this->createExcelUploadFile([$row])]);

        $message = session('import_summary')['errors'][0]['errors'][0];
        $this->assertStringContainsString('berstrata Karpim', $message);
        $this->assertStringContainsString('kosongkan kolom Level', $message);
        $this->assertDatabaseMissing('employees', ['nik' => '88010301']);
    }

    public function test_import_guides_the_admin_when_jabatan_and_unit_do_not_match(): void
    {
        $this->formasiUnit('Kebun Pencarian A', 'Manajer Pencarian', 'RM-1', 1);
        $this->formasiUnit('Kebun Pencarian B', 'Asisten Pencarian Khusus', 'RM-3', 1);

        $elsewhere = $this->karpimImportRow('88010401', 'Asisten Pencarian Khusus', 'Kebun Pencarian A');
        $elsewhere[9] = '';
        $typo = $this->karpimImportRow('88010402', 'Manajer Pencarin', 'Kebun Pencarian A');
        $typo[9] = '';
        $noSeats = $this->karpimImportRow('88010403', 'Manajer Pencarian', 'Kebun Tanpa Formasi');
        $noSeats[9] = '';
        Unit::create(['nama' => 'Kebun Tanpa Formasi', 'kode' => 'Kebun', 'wilayah' => 'Wilayah Kalimantan Barat', 'is_active' => true, 'urutan' => 99]);

        $this->actingAs($this->admin)->post(route('admin.employees.import'), ['import_file' => $this->createExcelUploadFile([$elsewhere, $typo, $noSeats])]);

        $errors = collect(session('import_summary')['errors'])->mapWithKeys(fn ($e) => [$e['nik'] => $e['errors'][0]]);
        $this->assertStringContainsString('terdaftar pada unit: Kebun Pencarian B', $errors['88010401']);
        $this->assertStringContainsString('Jabatan serupa di unit ini: Manajer Pencarian', $errors['88010402']);
        $this->assertStringContainsString("sheet 'Formasi per Unit'", $errors['88010402']);
        $this->assertStringContainsString('belum memiliki formasi jabatan', $errors['88010403']);
    }

    public function test_full_quota_error_lists_the_formasi_that_are_still_open(): void
    {
        $unit = $this->formasiUnit('Kebun Penuh Sebagian', 'Manajer Penuh Sebagian', 'RM-1', 1);
        $open = Position::create(['nama' => 'Asisten Masih Kosong', 'level' => 'Karpim', 'rm_level' => 'RM-3', 'is_active' => true]);
        $unit->positions()->attach($open->id, ['kuota' => 2, 'bidang' => 'TAN']);
        Employee::factory()->create([
            'nik' => '88010501', 'jabatan' => 'Manajer Penuh Sebagian', 'unit_kerja' => 'Kebun Penuh Sebagian',
            'level' => 'Karpim', 'rm_level' => 'RM-1', 'status_penugasan' => 'Definitif',
        ]);

        $this->actingAs($this->admin)->post(route('admin.employees.import'), [
            'import_file' => $this->createExcelUploadFile([$this->karpimImportRow('88010502', 'Manajer Penuh Sebagian', 'Kebun Penuh Sebagian')]),
        ]);

        $this->assertStringContainsString('Formasi masih kosong di unit ini: Asisten Masih Kosong', session('import_summary')['errors'][0]['errors'][0]);
    }

    public function test_template_columns_dropdowns_prompts_and_guide_are_aligned_by_heading(): void
    {
        $this->seed(PositionUnitSeeder::class);
        $sheet = new EmployeeTemplateDataSheet;
        $headings = $sheet->headings();

        $path = tempnam(sys_get_temp_dir(), 'tpl_').'.xlsx';
        file_put_contents($path, Excel::raw(new EmployeeTemplateExport, ExcelWriter::XLSX));
        $workbook = IOFactory::load($path);
        unlink($path);
        $data = $workbook->getSheetByName('Data Karyawan');

        $column = fn (string $heading): string => Coordinate::stringFromColumnIndex(array_search($heading, $headings, true) + 1);
        $normalize = fn (string $heading): string => strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '_', str_replace('*', '', $heading)), '_'));

        // Panduan kolom: setiap huruf dan kunci pada kamus sama dengan header template pada huruf itu.
        $this->assertCount(count($headings), EmployeeImportDictionary::getColumns());
        foreach (EmployeeImportDictionary::getColumns() as $guide) {
            $heading = $headings[Coordinate::columnIndexFromString($guide['letter']) - 1];
            $this->assertSame($guide['key'], $normalize($heading), "Kamus kolom {$guide['letter']} harus cocok dengan header template.");
        }

        // Dropdown: setiap kolom menunjuk daftar yang benar, termasuk pada rujukan ke sheet referensi.
        $listValues = function (string $formula) use ($workbook): array {
            if (str_starts_with($formula, '"')) {
                return array_map('trim', explode(',', trim($formula, '"')));
            }
            [$sheetName, $range] = explode('!', $formula);
            $source = $workbook->getSheetByName(trim($sheetName, "'"));
            $values = array_column($source->rangeToArray(str_replace('$', '', $range), null, true, false), 0);

            return array_values(array_filter($values, fn ($value) => $value !== null && $value !== ''));
        };
        $expected = [
            'JENIS KELAMIN' => ['L', 'P'],
            'AGAMA' => Employee::RELIGIONS,
            'SUSUNAN KELUARGA' => array_keys(Employee::FAMILY_STATUSES),
            'STATUS KARYAWAN*' => ['Tetap (PKWTT)', 'Kontrak (PKWT)', 'Calon Pegawai'],
            'UNIT KERJA*' => Unit::orderBy('urutan')->orderBy('nama')->pluck('nama')->all(),
            'STATUS PENUGASAN' => ['Definitif', 'Diperbantukan', 'Pejabat Sementara (Pjs)', 'Tugas Belajar', 'Advisor'],
            'JALUR MASUK' => array_keys(Employee::ENTRY_CHANNELS),
            'PENDIDIKAN TERAKHIR' => Employee::EDUCATION_LEVELS,
        ];
        foreach ($expected as $heading => $values) {
            $cell = $column($heading).'7';
            $this->assertSame($values, $listValues($data->getCell($cell)->getDataValidation()->getFormula1()), "Dropdown {$heading} ({$cell})");
        }
        $this->assertStringContainsString('OFFSET(', $data->getCell($column('JABATAN*').'7')->getDataValidation()->getFormula1());

        // Panduan input pada header: judulnya membahas kolom yang benar.
        $titles = [
            'UNIT KERJA*' => 'Unit Kerja', 'JABATAN*' => 'Jabatan', 'GOLONGAN' => 'Golongan', 'STATUS PENUGASAN' => 'Status Penugasan',
            'TUGAS RANGKAP / PENUGASAN SEMENTARA' => 'Tugas Rangkap', 'TMT BEKERJA' => 'TMT Bekerja', 'TANGGAL DIANGKAT STAF' => 'Tgl Diangkat Staf',
            'JOB GRADE' => 'Job Grade', 'PERSON GRADE' => 'Person Grade', 'TANGGAL MBT' => 'Tanggal MBT', 'TANGGAL PENSIUN' => 'Tanggal Pensiun',
        ];
        foreach ($titles as $heading => $needle) {
            $this->assertStringContainsString($needle, $data->getCell($column($heading).'1')->getDataValidation()->getPromptTitle(), "Panduan header {$heading}");
        }

        // Format tanggal terpasang pada kolom tanggal, bukan pada kolom lain yang bergeser.
        foreach (['TANGGAL LAHIR', 'TMT BEKERJA', 'TANGGAL DIANGKAT STAF', 'TANGGAL DALAM JABATAN', 'TMT UNIT KERJA', 'TANGGAL MBT', 'TANGGAL PENSIUN'] as $heading) {
            $this->assertArrayHasKey($column($heading), $sheet->columnFormats(), "Format tanggal untuk {$heading}");
        }
    }

    public function test_reimporting_an_existing_employee_from_the_template_format_keeps_strata_and_rm_band(): void
    {
        $this->formasiUnit('Kebun Reimpor', 'Manajer Reimpor', 'RM-1', 2);
        Employee::factory()->create([
            'nik' => '88010601', 'nama' => 'NAMA LAMA', 'jabatan' => 'Manajer Reimpor', 'unit_kerja' => 'Kebun Reimpor',
            'level' => 'Karpim', 'rm_level' => 'RM-1', 'status_penugasan' => 'Definitif', 'status_karyawan' => 'Tetap (PKWTT)',
        ]);

        $headings = (new EmployeeTemplateDataSheet)->headings();
        $row = (new EmployeeTemplateDataSheet)->array()[0];
        $set = function (string $heading, mixed $value) use (&$row, $headings): void {
            $row[array_search($heading, $headings, true)] = $value;
        };
        $set('NIK*', '88010601');
        $set('NAMA*', 'NAMA BARU');
        $set('UNIT KERJA*', 'Kebun Reimpor');
        $set('JABATAN*', 'Manajer Reimpor');
        $set('JOB GRADE', 13);
        $set('PERSON GRADE', 13);

        $this->actingAs($this->admin)->post(route('admin.employees.import'), ['import_file' => $this->createExcelUploadFile([$row], 'reimpor.xlsx', $headings)]);

        $this->assertSame([], session('import_summary')['errors'] ?? []);
        $this->assertSame(1, session('import_summary')['updated']);
        $this->assertDatabaseHas('employees', ['nik' => '88010601', 'nama' => 'NAMA BARU', 'level' => 'Karpim', 'rm_level' => 'RM-1']);
    }

    /* ------------------------------------------------------------------ tata kelola: maker-checker & undo */

    private function enableApproval(): void
    {
        config(['hris.import.require_approval' => true]);
    }

    private function commitRows(array $rows, ?User $as = null): TestResponse
    {
        $as ??= $this->admin;
        $token = $this->actingAs($as)
            ->post(route('admin.employees.import.preview'), ['import_file' => $this->createExcelUploadFile($rows)])
            ->assertOk()
            ->json('token');

        return $this->actingAs($as)->postJson(route('admin.employees.import.commit'), ['token' => $token]);
    }

    private function otherAdmin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    private function reviewDigest(EmployeeImportLog $log, User $checker): string
    {
        $html = $this->actingAs($checker)->get(route('admin.employees.import.batches.show', $log))->assertOk()->getContent();
        $this->assertSame(1, preg_match('/name="reviewed_digest" value="([a-f0-9]{64})"/', $html, $match), 'Formulir persetujuan harus tampil untuk penyetuju.');

        return $match[1];
    }

    private function seedGovernanceUnit(string $nama, string $jabatan): void
    {
        $this->formasiUnit($nama, $jabatan, 'RM-1', 5);
        Employee::factory()->create([
            'nik' => '88021001', 'nama' => 'NAMA ASLI', 'jabatan' => $jabatan, 'unit_kerja' => $nama,
            'level' => 'Karpim', 'rm_level' => 'RM-1', 'status_penugasan' => 'Definitif',
            'status_karyawan' => 'Tetap (PKWTT)', 'golongan' => 'IIIA/00',
        ]);
    }

    public function test_batch_detail_offers_correction_download_with_expiry_and_a_sample_per_skipped_reason(): void
    {
        $log = EmployeeImportLog::create([
            'user_id' => $this->admin->id, 'user_name' => $this->admin->name, 'file_name' => 'koreksi.xlsx', 'file_size' => 2048,
            'file_sha256' => hash('sha256', 'koreksi'), 'total_rows' => 3, 'created_count' => 1, 'skipped_count' => 2, 'has_errors' => true,
            'status' => EmployeeImportLog::STATUS_COMPLETED, 'committed_at' => now(), 'token' => 'token-koreksi',
            'metadata' => ['summary' => ['errors' => [
                ['row' => 7, 'errors' => ["Kuota formasi jabatan 'Asisten Kepala' pada Unit Kerja 'Kebun Tajati' sudah terpenuhi (3 formasi)."]],
                ['row' => 9, 'errors' => ["Kuota formasi jabatan 'Mandor' pada Unit Kerja 'Kebun Lain' sudah terpenuhi (2 formasi)."]],
            ]]],
        ]);

        $page = fn () => $this->actingAs($this->admin)->get(route('admin.employees.import.batches.show', $log))->assertOk();

        $page()->assertDontSee('Unduh Berkas Koreksi')->assertSee('Berkas koreksi sudah tidak tersedia karena memuat data pribadi', false);

        Cache::put('import_failed_rows_token-koreksi', 'sealed', now()->addHours(2));
        $page()->assertSee('Unduh Berkas Koreksi')
            ->assertSee('tersedia sampai', false)
            ->assertSee($log->committed_at->copy()->addHours(2)->format('d/m/Y H:i'))
            ->assertSee("Contoh: Kuota formasi jabatan 'Asisten Kepala'")
            ->assertSeeText('3 baris dalam berkas');
    }

    /** @return array{0: EmployeeImportLog, 1: string} impor tersimpan (kebijakan nonaktif) dan hash kondisi awal karyawan eksisting */
    private function committedImport(): array
    {
        $this->seedGovernanceUnit('Kebun Undo', 'Manajer Undo');
        $hashBefore = Employee::stateHash(Employee::findOrFail('88021001'));

        $this->commitRows([
            $this->karpimImportRow('88021001', 'Manajer Undo', 'Kebun Undo'),
            $this->karpimImportRow('88021002', 'Manajer Undo', 'Kebun Undo'),
        ])->assertOk();

        return [EmployeeImportLog::latest('id')->firstOrFail(), $hashBefore];
    }

    public function test_the_four_eyes_policy_is_off_by_default_and_the_import_page_reflects_the_setting(): void
    {
        $this->assertFalse(config('hris.import.require_approval'));

        $this->enableApproval();
        $this->actingAs($this->admin)->get(route('admin.employees.import.page'))->assertOk()->assertSee('requiresApproval: true', false);
    }

    public function test_commit_only_submits_for_approval_when_the_policy_is_active(): void
    {
        $this->enableApproval();
        $this->formasiUnit('Kebun Tata Kelola', 'Manajer Tata Kelola', 'RM-1', 5);

        $response = $this->commitRows([$this->karpimImportRow('88020001', 'Manajer Tata Kelola', 'Kebun Tata Kelola')]);

        $response->assertOk()->assertJsonPath('pending_approval', true)->assertJsonPath('summary.created', 1);
        $log = EmployeeImportLog::sole();
        $this->assertSame('pending_approval', $log->status);
        $this->assertSame($this->admin->id, $log->user_id);
        $this->assertNull($log->checker_id);
        $this->assertDatabaseMissing('employees', ['nik' => '88020001']);
        Storage::disk('local')->assertExists($log->stored_path);
        $this->assertSame(0, $log->changes()->count());
    }

    public function test_the_maker_cannot_approve_their_own_submission(): void
    {
        $this->enableApproval();
        $this->formasiUnit('Kebun Tata Kelola', 'Manajer Tata Kelola', 'RM-1', 5);
        $this->commitRows([$this->karpimImportRow('88020001', 'Manajer Tata Kelola', 'Kebun Tata Kelola')]);
        $log = EmployeeImportLog::sole();

        $this->actingAs($this->admin)->get(route('admin.employees.import.batches.show', $log))->assertOk()->assertDontSee('reviewed_digest');
        $response = $this->actingAs($this->admin)->post(route('admin.employees.import.batches.approve', $log), ['reviewed_digest' => str_repeat('a', 64)]);

        $response->assertRedirect();
        $this->assertStringContainsString('pengajunya sendiri', session('error'));
        $this->assertSame('pending_approval', $log->fresh()->status);
        $this->assertDatabaseMissing('employees', ['nik' => '88020001']);
    }

    public function test_another_admin_reviews_and_approves_and_both_names_reach_the_history(): void
    {
        $this->enableApproval();
        $this->seedGovernanceUnit('Kebun Tata Kelola', 'Manajer Tata Kelola');
        $this->commitRows([
            $this->karpimImportRow('88021001', 'Manajer Tata Kelola', 'Kebun Tata Kelola'),
            $this->karpimImportRow('88020002', 'Manajer Tata Kelola', 'Kebun Tata Kelola'),
        ])->assertOk();
        $log = EmployeeImportLog::sole();
        $checker = $this->otherAdmin();

        $page = $this->actingAs($checker)->get(route('admin.employees.import.batches.show', $log));
        $page->assertOk()->assertSee('KARYAWAN UJI FORMASI')->assertSee('Pratinjau Terkini');
        $digest = $this->reviewDigest($log, $checker);

        $this->actingAs($checker)->post(route('admin.employees.import.batches.approve', $log), ['reviewed_digest' => $digest])
            ->assertRedirect()->assertSessionHas('success');

        $log->refresh();
        $this->assertSame('completed', $log->status);
        $this->assertSame($this->admin->id, $log->user_id, 'Pengaju tetap tercatat sebagai pengaju.');
        $this->assertSame($checker->id, $log->checker_id);
        $this->assertSame($checker->name, $log->checker_name);
        $this->assertNotNull($log->committed_at);
        $this->assertNull($log->stored_path);
        $this->assertDatabaseHas('employees', ['nik' => '88020002']);
        $this->assertSame(2, $log->changes()->count());

        $note = JobHistory::where('employee_nik', '88021001')->latest('id')->value('catatan');
        $this->assertStringContainsString($this->admin->name, $note);
        $this->assertStringContainsString("disetujui {$checker->name}", $note);
    }

    public function test_approval_is_refused_when_the_preview_changed_since_the_review(): void
    {
        $this->enableApproval();
        $this->formasiUnit('Kebun Tata Kelola', 'Manajer Tata Kelola', 'RM-1', 5);
        $this->commitRows([$this->karpimImportRow('88020001', 'Manajer Tata Kelola', 'Kebun Tata Kelola')]);
        $log = EmployeeImportLog::sole();
        $checker = $this->otherAdmin();
        $digest = $this->reviewDigest($log, $checker);

        // Master berubah setelah ditinjau: unit dinonaktifkan sehingga baris tidak lagi valid.
        Unit::where('nama', 'Kebun Tata Kelola')->update(['is_active' => false]);

        $this->actingAs($checker)->post(route('admin.employees.import.batches.approve', $log), ['reviewed_digest' => $digest])->assertRedirect();

        $this->assertStringContainsString('berubah sejak Anda meninjau', session('error'));
        $this->assertSame('pending_approval', $log->fresh()->status);
        $this->assertNull($log->fresh()->checker_id, 'Persetujuan yang gagal dilepas agar dapat ditinjau ulang.');
        $this->assertDatabaseMissing('employees', ['nik' => '88020001']);
    }

    public function test_rejecting_requires_a_reason_and_removes_the_submitted_file(): void
    {
        $this->enableApproval();
        $this->formasiUnit('Kebun Tata Kelola', 'Manajer Tata Kelola', 'RM-1', 5);
        $this->commitRows([$this->karpimImportRow('88020001', 'Manajer Tata Kelola', 'Kebun Tata Kelola')]);
        $log = EmployeeImportLog::sole();
        $path = $log->stored_path;
        $checker = $this->otherAdmin();

        $this->actingAs($checker)->post(route('admin.employees.import.batches.reject', $log), ['reason' => 'singkat'])->assertSessionHasErrors('reason');
        $this->assertSame('pending_approval', $log->fresh()->status);

        $this->actingAs($checker)->post(route('admin.employees.import.batches.reject', $log), ['reason' => 'Jabatan pada berkas tidak sesuai SK'])
            ->assertRedirect(route('admin.employees.import.batches.index'));

        $log->refresh();
        $this->assertSame('rejected', $log->status);
        $this->assertSame('Jabatan pada berkas tidak sesuai SK', $log->decision_note);
        $this->assertSame($checker->id, $log->checker_id);
        Storage::disk('local')->assertMissing($path);
        $this->assertDatabaseMissing('employees', ['nik' => '88020001']);
    }

    public function test_the_maker_can_withdraw_their_own_submission(): void
    {
        $this->enableApproval();
        $this->formasiUnit('Kebun Tata Kelola', 'Manajer Tata Kelola', 'RM-1', 5);
        $this->commitRows([$this->karpimImportRow('88020001', 'Manajer Tata Kelola', 'Kebun Tata Kelola')]);
        $log = EmployeeImportLog::sole();

        $this->actingAs($this->admin)->post(route('admin.employees.import.batches.reject', $log), ['reason' => 'Salah mengunggah berkas periode lama'])->assertRedirect();

        $this->assertSame('rejected', $log->fresh()->status);
    }

    public function test_real_saving_paths_are_blocked_when_approval_is_required(): void
    {
        $this->enableApproval();
        $this->formasiUnit('Kebun Tata Kelola', 'Manajer Tata Kelola', 'RM-1', 5);

        // Rute impor langsung ditolak.
        $this->actingAs($this->admin)->post(route('admin.employees.import'), [
            'import_file' => $this->createExcelUploadFile([$this->karpimImportRow('88020001', 'Manajer Tata Kelola', 'Kebun Tata Kelola')]),
        ])->assertSessionHasErrors('import_file');

        // Jalur dalam (fallback sinkron, antrean) pun tidak dapat menyimpan tanpa persetujuan.
        $token = $this->actingAs($this->admin)
            ->post(route('admin.employees.import.preview'), ['import_file' => $this->createExcelUploadFile([$this->karpimImportRow('88020001', 'Manajer Tata Kelola', 'Kebun Tata Kelola')])])
            ->json('token');
        try {
            app(EmployeeImportService::class)->executeCommit(Cache::get("import_preview_{$token}"), $token, $this->admin);
            $this->fail('Penyimpanan tanpa persetujuan seharusnya ditolak.');
        } catch (ImportAbortedException $e) {
            $this->assertStringContainsString('persetujuan admin lain', $e->getMessage());
        }

        $this->assertDatabaseMissing('employees', ['nik' => '88020001']);
    }

    public function test_a_stale_submission_expires_and_cannot_be_approved(): void
    {
        $this->enableApproval();
        $this->formasiUnit('Kebun Tata Kelola', 'Manajer Tata Kelola', 'RM-1', 5);
        $this->commitRows([$this->karpimImportRow('88020001', 'Manajer Tata Kelola', 'Kebun Tata Kelola')]);
        $log = EmployeeImportLog::sole();
        $path = $log->stored_path;
        $checker = $this->otherAdmin();
        $digest = $this->reviewDigest($log, $checker);

        $log->update(['submitted_at' => now()->subDays(8)]);
        $this->actingAs($checker)->post(route('admin.employees.import.batches.approve', $log), ['reviewed_digest' => $digest])->assertRedirect();

        $this->assertStringContainsString('kedaluwarsa', session('error'));
        $this->assertSame('expired', $log->fresh()->status);
        Storage::disk('local')->assertMissing($path);
        $this->assertDatabaseMissing('employees', ['nik' => '88020001']);
    }

    public function test_the_housekeeping_command_expires_old_submissions_and_deletes_old_snapshots(): void
    {
        $this->enableApproval();
        $this->formasiUnit('Kebun Tata Kelola', 'Manajer Tata Kelola', 'RM-1', 5);
        $this->commitRows([$this->karpimImportRow('88020001', 'Manajer Tata Kelola', 'Kebun Tata Kelola')]);
        $pending = EmployeeImportLog::sole();
        $pending->update(['submitted_at' => now()->subDays(8)]);

        config(['hris.import.require_approval' => false]);
        [$saved] = $this->committedImport();
        EmployeeImportChange::query()->update(['created_at' => now()->subDays(8)]);

        $this->artisan('hris:purge-import-governance')->assertSuccessful();

        $this->assertSame('expired', $pending->fresh()->status);
        $this->assertSame(0, EmployeeImportChange::count());
        $this->assertSame('completed', $saved->fresh()->status);
    }

    public function test_a_submitted_file_that_was_tampered_with_cannot_be_reviewed_or_approved(): void
    {
        $this->enableApproval();
        $this->formasiUnit('Kebun Tata Kelola', 'Manajer Tata Kelola', 'RM-1', 5);
        $this->commitRows([$this->karpimImportRow('88020001', 'Manajer Tata Kelola', 'Kebun Tata Kelola')]);
        $log = EmployeeImportLog::sole();
        Storage::disk('local')->put($log->stored_path, 'berkas diganti');

        $this->actingAs($this->otherAdmin())->get(route('admin.employees.import.batches.show', $log))
            ->assertOk()->assertSee('berubah sejak diajukan')->assertDontSee('reviewed_digest');
    }

    public function test_governance_pages_are_admin_only_and_list_the_history(): void
    {
        $this->seedGovernanceUnit('Kebun Riwayat', 'Manajer Riwayat');
        $this->commitRows([$this->karpimImportRow('88021002', 'Manajer Riwayat', 'Kebun Riwayat')])->assertOk();

        $this->actingAs(User::factory()->create(['role' => 'user']))->get(route('admin.employees.import.batches.index'))->assertForbidden();
        $this->actingAs($this->admin)->get(route('admin.employees.import.batches.index'))
            ->assertOk()->assertSee('Riwayat & Persetujuan Impor')->assertSee('Tersimpan')->assertSee($this->admin->name);
    }

    public function test_commit_records_encrypted_change_snapshots_for_every_created_and_updated_employee(): void
    {
        [$log] = $this->committedImport();

        $this->assertSame(2, $log->changes()->count());
        $actions = $log->changes()->pluck('action')->sort()->values()->all();
        $this->assertSame(['CREATE', 'UPDATE'], $actions);

        // Data pribadi tidak tersimpan polos pada basis data.
        $rawNik = implode('', DB::table('employee_import_changes')->pluck('nik')->all());
        $rawBefore = implode('', DB::table('employee_import_changes')->pluck('before')->filter()->all());
        $this->assertStringNotContainsString('88021001', $rawNik);
        $this->assertStringNotContainsString('NAMA ASLI', $rawBefore);
        $this->assertSame('NAMA ASLI', $log->changes()->where('action', 'UPDATE')->first()->before['nama']);
    }

    public function test_rollback_restores_updated_employees_and_removes_created_ones(): void
    {
        [$log, $hashBefore] = $this->committedImport();
        $historyBefore = JobHistory::where('employee_nik', '88021001')->count();
        $this->assertSame('KARYAWAN UJI FORMASI', Employee::find('88021001')->nama);

        $this->actingAs($this->admin)->post(route('admin.employees.import.batches.rollback', $log), ['reason' => 'Berkas periode salah diunggah'])
            ->assertRedirect()->assertSessionHas('success');

        $this->assertDatabaseMissing('employees', ['nik' => '88021002']);
        $this->assertSame($hashBefore, Employee::stateHash(Employee::find('88021001')), 'Karyawan eksisting kembali persis ke kondisi sebelum impor.');
        $this->assertSame('NAMA ASLI', Employee::find('88021001')->nama);
        $this->assertSame('IIIA/00', Employee::find('88021001')->golongan);

        $log->refresh();
        $this->assertSame('rolled_back', $log->status);
        $this->assertSame('Berkas periode salah diunggah', $log->rollback_reason);
        $this->assertSame($this->admin->id, $log->rolled_back_by);
        $this->assertSame(0, $log->changes()->count(), 'Rekaman data pribadi dihapus setelah pembatalan.');

        // Riwayat tidak dihapus: pembatalan menambah catatan baru.
        $this->assertGreaterThan($historyBefore, JobHistory::where('employee_nik', '88021001')->count());
        $this->assertStringContainsString('Pembatalan impor Excel', JobHistory::where('employee_nik', '88021001')->latest('id')->value('catatan'));
    }

    public function test_rollback_is_all_or_nothing_when_an_employee_changed_after_the_import(): void
    {
        [$log] = $this->committedImport();
        Employee::where('nik', '88021002')->update(['sekolah' => 'DIUBAH SETELAH IMPOR']);

        $response = $this->actingAs($this->admin)->post(route('admin.employees.import.batches.rollback', $log), ['reason' => 'Berkas periode salah diunggah']);

        $response->assertRedirect();
        $this->assertStringContainsString('Pembatalan dihentikan', session('error'));
        $this->assertStringContainsString('88****02', session('error'), 'NIK ditampilkan tersamar.');
        $this->assertSame('completed', $log->fresh()->status);
        $this->assertSame('KARYAWAN UJI FORMASI', Employee::find('88021001')->nama, 'Tidak ada karyawan yang dikembalikan sebagian.');
        $this->assertDatabaseHas('employees', ['nik' => '88021002']);
        $this->assertSame(2, $log->changes()->count());
    }

    public function test_rollback_is_refused_for_a_created_employee_that_gained_history_after_the_import(): void
    {
        [$log] = $this->committedImport();

        $this->travel(5)->minutes();
        JobHistory::create(['employee_nik' => '88021002', 'jabatan' => 'Manajer Undo', 'unit_kerja' => 'Kebun Undo', 'status_penugasan' => 'Plt', 'tmt_awal' => now()->toDateString()]);

        $this->actingAs($this->admin)->post(route('admin.employees.import.batches.rollback', $log), ['reason' => 'Berkas periode salah diunggah'])->assertRedirect();

        $this->assertStringContainsString('Pembatalan dihentikan', session('error'));
        $this->assertDatabaseHas('employees', ['nik' => '88021002']);
        $this->assertSame('completed', $log->fresh()->status);
    }

    public function test_rollback_needs_a_reason_works_once_and_expires_with_the_undo_window(): void
    {
        [$log] = $this->committedImport();

        $this->actingAs($this->admin)->post(route('admin.employees.import.batches.rollback', $log), ['reason' => 'pendek'])->assertSessionHasErrors('reason');
        $this->assertSame('completed', $log->fresh()->status);

        $this->actingAs($this->admin)->post(route('admin.employees.import.batches.rollback', $log), ['reason' => 'Berkas periode salah diunggah'])->assertSessionHas('success');
        $this->actingAs($this->admin)->post(route('admin.employees.import.batches.rollback', $log), ['reason' => 'Berkas periode salah diunggah'])->assertRedirect();
        $this->assertStringContainsString('status impor saat ini', session('error'));

        $second = $this->freshImportAfterRollback();
        $second->update(['committed_at' => now()->subDays(8)]);
        $this->actingAs($this->admin)->post(route('admin.employees.import.batches.rollback', $second), ['reason' => 'Berkas periode salah diunggah'])->assertRedirect();
        $this->assertStringContainsString('Masa pembatalan', session('error'));
        $this->actingAs($this->admin)->get(route('admin.employees.import.batches.show', $second))->assertOk()->assertSee('tidak dapat lagi dibatalkan');
    }

    private function freshImportAfterRollback(): EmployeeImportLog
    {
        $this->commitRows([$this->karpimImportRow('88021003', 'Manajer Undo', 'Kebun Undo')])->assertOk();

        return EmployeeImportLog::latest('id')->firstOrFail();
    }

    public function test_rollback_cancels_the_concurrent_assignment_the_import_created_without_deleting_history(): void
    {
        $pksUnit = Unit::firstOrCreate(['nama' => 'PKS Gunung Meliau'], ['kode' => 'PKGM', 'wilayah' => 'Wilayah Kalimantan Barat', 'is_active' => true]);
        $manajerPos = Position::firstOrCreate(['nama' => 'Manajer PKS', 'level' => 'Karpim'], ['is_active' => true]);
        $pksUnit->positions()->syncWithoutDetaching([$manajerPos->id => ['kuota' => 1]]);
        Employee::factory()->create([
            'nik' => '13009901', 'nama' => 'NAMA ASLI', 'jabatan' => 'Asisten Afdeling', 'unit_kerja' => 'Kebun Inti Gunung Meliau',
            'level' => 'Karpim', 'rm_level' => 'RM-3', 'status_penugasan' => 'Definitif', 'status_karyawan' => 'Tetap (PKWTT)', 'golongan' => 'IIIA/00',
        ]);

        $row = [
            '13009901', 'BUDI SANTOSO', 'L', 'Pontianak', '1988-05-10',
            'Islam', 'K/2', 'Tetap (PKWTT)', 'Asisten Afdeling', 'Karpim',
            'Kebun Inti Gunung Meliau', 'IIIA/00', 'RM-3', 'Definitif',
            'Plt. Manajer PKS PKS Gunung Meliau TMT 15 Juli 2026',
            '2015-01-01', '2017-01-01', '2020-01-01', '2020-01-01',
            'Reguler', 11, 11, 'S1', 'Universitas Tanjungpura',
            '2043-05-01', '2044-05-01',
        ];
        $this->commitRows([$row])->assertOk();
        $employee = Employee::findOrFail('13009901');
        $this->assertSame(1, $employee->concurrentJobs()->count());
        $jobId = $employee->concurrentJobs()->value('id');
        $log = EmployeeImportLog::sole();

        $this->actingAs($this->admin)->post(route('admin.employees.import.batches.rollback', $log), ['reason' => 'Tugas rangkap pada berkas keliru'])->assertSessionHas('success');

        $this->assertSame(0, Employee::findOrFail('13009901')->concurrentJobs()->count());
        $job = JobHistory::withTrashed()->findOrFail($jobId);
        $this->assertTrue($job->trashed(), 'Dibatalkan (soft delete), bukan dihapus permanen.');
        $this->assertSame($this->admin->id, $job->cancelled_by);
        $this->assertStringContainsString('Pembatalan impor Excel', $job->alasan_pembatalan);
        $this->assertSame('NAMA ASLI', Employee::find('13009901')->nama);
    }

    /* ------------------------------------------------------------------ tata kelola: antarmuka */

    public function test_import_status_badge_follows_the_registered_labels_icons_and_partial_state(): void
    {
        $expected = [
            ['pending_approval', 0, 'Menunggu Persetujuan', 'fa-clock'],
            ['approved', 0, 'Menyimpan', 'fa-rotate'],
            ['completed', 0, 'Tersimpan', 'fa-check'],
            ['completed', 3, 'Tersimpan Sebagian', 'fa-triangle-exclamation'],
            ['rejected', 0, 'Ditolak', 'fa-ban'],
            ['rolled_back', 0, 'Dibatalkan', 'fa-rotate-left'],
            ['expired', 0, 'Kedaluwarsa', 'fa-hourglass-end'],
        ];

        foreach ($expected as [$status, $skipped, $label, $icon]) {
            $html = Blade::render('<x-admin.import-status :status="$status" :skipped="$skipped" />', compact('status', 'skipped'));

            $this->assertStringContainsString($label, $html);
            $this->assertStringContainsString($icon, $html, "Status {$label} harus memiliki ikon, bukan hanya warna.");
            $this->assertStringNotContainsString('uppercase', $html, 'Lencana status tidak memakai huruf kapital.');
        }
    }

    public function test_history_list_filters_by_tab_and_search_and_the_import_page_shows_the_pending_count(): void
    {
        $this->enableApproval();
        $this->formasiUnit('Kebun Filter', 'Manajer Filter', 'RM-1', 5);
        $this->commitRows([$this->karpimImportRow('88022001', 'Manajer Filter', 'Kebun Filter')])->assertOk();
        config(['hris.import.require_approval' => false]);
        $this->commitRows([$this->karpimImportRow('88022002', 'Manajer Filter', 'Kebun Filter')])->assertOk();

        // Tabs, search and counts are covered by ImportBatchTableTest; here: the page renders the same data end to end.
        $pending = Livewire::withQueryParams(['status' => 'pending'])->test(ImportBatchTable::class);
        $this->assertSame(['all' => 2, 'pending' => 1, 'saved' => 1, 'closed' => 0], $pending->instance()->tabCounts);
        $this->assertSame(['pending_approval'], $pending->instance()->rows->pluck('status')->all());

        $saved = Livewire::withQueryParams(['status' => 'saved'])->test(ImportBatchTable::class);
        $this->assertSame(['completed'], $saved->instance()->rows->pluck('status')->all());

        Livewire::withQueryParams(['search' => 'tidak-ada-berkas-ini'])->test(ImportBatchTable::class)
            ->assertSee('Tidak ada impor yang cocok dengan filter ini');
        $this->assertCount(2, Livewire::withQueryParams(['search' => 'test_import'])->test(ImportBatchTable::class)->instance()->rows);

        $this->actingAs($this->admin)->get(route('admin.employees.import.batches.index', ['status' => 'pending']))
            ->assertOk()->assertSeeLivewire(ImportBatchTable::class);
        $this->actingAs($this->admin)->get(route('admin.employees.import.page'))
            ->assertOk()->assertSee('1 pengajuan menunggu persetujuan')->assertSee('Riwayat &amp; Persetujuan', false);
    }

    public function test_detail_groups_skipped_rows_by_reason_and_never_stores_a_nik_in_the_messages(): void
    {
        $this->formasiUnit('Kebun Alasan', 'Manajer Alasan', 'RM-1', 5);
        $good = $this->karpimImportRow('88023001', 'Manajer Alasan', 'Kebun Alasan');
        $dupA = $this->karpimImportRow('88023002', 'Manajer Alasan', 'Kebun Alasan');
        $dupB = $this->karpimImportRow('88023002', 'Manajer Alasan', 'Kebun Alasan');
        $badA = $this->karpimImportRow('88023003', 'Jabatan Salah Satu', 'Kebun Alasan');
        $badB = $this->karpimImportRow('88023004', 'Jabatan Salah Dua', 'Kebun Alasan');

        $this->commitRows([$good, $dupA, $dupB, $badA, $badB])->assertOk();
        $log = EmployeeImportLog::sole();

        // NIK pada pesan galat disamarkan sebelum disimpan permanen.
        $this->assertStringNotContainsString('88023002', json_encode($log->metadata));
        $this->assertStringContainsString('88****02', json_encode($log->metadata));

        $page = $this->actingAs($this->admin)->get(route('admin.employees.import.batches.show', $log))->assertOk();
        $page->assertSee('Baris yang Dilewati (3)')->assertSee('Tersimpan Sebagian')->assertSee('3 baris dilewati');
        $groups = $page->viewData('skippedGroups');
        $this->assertCount(2, $groups, 'Dua pola alasan: jabatan tidak valid dan NIK ganda.');
        $this->assertSame(2, $groups[0]['count']);
        $this->assertStringContainsString("Jabatan '…' tidak valid untuk Unit Kerja '…'", $groups[0]['reason']);
        $this->assertSame(1, $groups[1]['count']);
        $page->assertSee('Lihat seluruh baris beserta pesannya');
    }

    public function test_detail_shows_whether_the_rollback_is_ready_before_the_admin_clicks(): void
    {
        [$log] = $this->committedImport();

        $ready = $this->actingAs($this->admin)->get(route('admin.employees.import.batches.show', $log))->assertOk();
        $ready->assertSee('Tindakan Berisiko')->assertSee('Siap dibatalkan')->assertSee('rollback-reason', false);
        $this->assertTrue($ready->viewData('readiness')['ready']);

        Employee::where('nik', '88021002')->update(['sekolah' => 'DIUBAH SETELAH IMPOR']);

        $blocked = $this->actingAs($this->admin)->get(route('admin.employees.import.batches.show', $log))->assertOk();
        $blocked->assertSee('Belum dapat dibatalkan')->assertSee('88****02')->assertDontSee('rollback-reason', false);
        $this->assertFalse($blocked->viewData('readiness')['ready']);
    }

    public function test_a_large_rollback_needs_the_confirmation_word_typed(): void
    {
        config(['hris.import.typed_confirmation_threshold' => 1]);
        [$log] = $this->committedImport();

        $this->actingAs($this->admin)->get(route('admin.employees.import.batches.show', $log))->assertOk()->assertSee('rollback-confirmation', false);

        $this->actingAs($this->admin)->post(route('admin.employees.import.batches.rollback', $log), ['reason' => 'Berkas periode salah diunggah'])->assertRedirect();
        $this->assertStringContainsString('Ketik kata BATALKAN', session('error'));
        $this->assertSame('completed', $log->fresh()->status);
        $this->assertDatabaseHas('employees', ['nik' => '88021002']);

        $this->actingAs($this->admin)->post(route('admin.employees.import.batches.rollback', $log), ['reason' => 'Berkas periode salah diunggah', 'confirmation' => 'batalkan'])
            ->assertSessionHas('success');
        $this->assertSame('rolled_back', $log->fresh()->status);
    }

    public function test_small_rollbacks_do_not_ask_for_the_confirmation_word(): void
    {
        [$log] = $this->committedImport();

        $this->actingAs($this->admin)->get(route('admin.employees.import.batches.show', $log))->assertOk()->assertDontSee('rollback-confirmation', false);
        $this->actingAs($this->admin)->post(route('admin.employees.import.batches.rollback', $log), ['reason' => 'Berkas periode salah diunggah'])->assertSessionHas('success');
    }

    public function test_the_timeline_lists_each_decision_step_with_actor_and_reason(): void
    {
        $this->enableApproval();
        $this->formasiUnit('Kebun Linimasa', 'Manajer Linimasa', 'RM-1', 5);
        $this->commitRows([$this->karpimImportRow('88024001', 'Manajer Linimasa', 'Kebun Linimasa')])->assertOk();
        $log = EmployeeImportLog::sole();
        $checker = $this->otherAdmin();

        $this->actingAs($checker)->get(route('admin.employees.import.batches.show', $log))
            ->assertOk()->assertSee('Diajukan')->assertSee('Menunggu persetujuan admin lain')->assertDontSee('Disetujui');

        $digest = $this->reviewDigest($log, $checker);
        $this->actingAs($checker)->post(route('admin.employees.import.batches.approve', $log), ['reviewed_digest' => $digest])->assertRedirect();
        $this->actingAs($checker)->post(route('admin.employees.import.batches.rollback', $log), ['reason' => 'Dibatalkan karena SK belum terbit'])->assertSessionHas('success');

        $page = $this->actingAs($checker)->get(route('admin.employees.import.batches.show', $log->fresh()))->assertOk();
        foreach (['Diajukan', 'Disetujui', 'Disimpan', 'Dibatalkan', $this->admin->name, $checker->name, 'Dibatalkan karena SK belum terbit'] as $text) {
            $page->assertSee($text);
        }
        $page->assertDontSee('Menunggu persetujuan admin lain')->assertDontSee('Tindakan Berisiko');
    }
}
