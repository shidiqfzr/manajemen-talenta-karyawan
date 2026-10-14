<?php

namespace Tests\Feature;

use App\Exports\EmployeesExport;
use App\Models\Employee;
use App\Models\JobHistory;
use App\Models\User;
use App\Services\EmployeeExportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Events\AfterSheet;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Sheet;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use Tests\TestCase;

class EmployeeExportTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->adminUser = User::first() ?? User::factory()->create(['role' => 'admin']);
    }

    public function test_unauthenticated_user_cannot_export_employees(): void
    {
        $response = $this->get(route('admin.employees.export'));
        $response->assertRedirect(route('login'));
    }

    public function test_regular_user_without_admin_role_cannot_export_employees(): void
    {
        $regularUser = User::factory()->create(['role' => 'user']);

        $response = $this->actingAs($regularUser)->get(route('admin.employees.export'));
        $response->assertStatus(403);
    }

    public function test_admin_can_export_all_employees_with_dynamic_timestamped_filename(): void
    {
        Excel::fake();
        Excel::matchByRegex();

        Employee::factory()->count(3)->create();

        $response = $this->actingAs($this->adminUser)->get(route('admin.employees.export'));

        $response->assertSuccessful();

        Excel::assertDownloaded(
            '/^data_karyawan_ptpn4_reg5_\d{8}_\d{6}\.xlsx$/',
            function (EmployeesExport $export) {
                return $export->query()->count() >= 3;
            }
        );
    }

    public function test_admin_can_export_filtered_employees(): void
    {
        Excel::fake();
        Excel::matchByRegex();

        Employee::factory()->create([
            'nama' => 'PEGAWAI KEBUN MELIAU',
            'unit_kerja' => 'Kebun Inti Gunung Meliau',
            'jabatan' => 'Asisten Afdeling',
        ]);

        Employee::factory()->create([
            'nama' => 'PEGAWAI PKS SAMUNTAI',
            'unit_kerja' => 'PKS Samuntai',
            'jabatan' => 'Masinis Kepala',
        ]);

        $response = $this->actingAs($this->adminUser)->get(route('admin.employees.export', [
            'unit_kerja' => 'Kebun Inti Gunung Meliau',
            'jabatan' => 'Asisten Afdeling',
        ]));

        $response->assertSuccessful();

        Excel::assertDownloaded(
            '/^data_karyawan_ptpn4_reg5_\d{8}_\d{6}\.xlsx$/',
            function (EmployeesExport $export) {
                $results = $export->query()->get();

                return $results->count() === 1
                    && $results->first()->nama === 'PEGAWAI KEBUN MELIAU';
            }
        );
    }

    public function test_admin_can_export_selected_employees_by_niks(): void
    {
        Excel::fake();
        Excel::matchByRegex();

        $emp1 = Employee::factory()->create(['nik' => '13000001', 'nama' => 'PEGAWAI SATU']);
        $emp2 = Employee::factory()->create(['nik' => '13000002', 'nama' => 'PEGAWAI DUA']);
        $emp3 = Employee::factory()->create(['nik' => '13000003', 'nama' => 'PEGAWAI TIGA']);

        $response = $this->actingAs($this->adminUser)->get(route('admin.employees.export', [
            'niks' => ['13000001', '13000003'],
        ]));

        $response->assertSuccessful();

        Excel::assertDownloaded(
            '/^data_karyawan_ptpn4_reg5_\d{8}_\d{6}\.xlsx$/',
            function (EmployeesExport $export) {
                $results = $export->query()->get();

                return $results->count() === 2
                    && $results->pluck('nik')->sort()->values()->toArray() === ['13000001', '13000003'];
            }
        );
    }

    public function test_export_class_structure_headings_and_cell_formatting_meet_26_column_ssot(): void
    {
        $export = new EmployeesExport;

        $headings = $export->headings();
        $this->assertCount(26, $headings, 'Export harus memiliki tepat 26 kolom terstandarisasi (termasuk tugas rangkap)');

        $this->assertSame('NIK', $headings[0]);
        $this->assertSame('NAMA', $headings[1]);
        $this->assertSame('JENIS KELAMIN', $headings[2]);
        $this->assertSame('TEMPAT LAHIR', $headings[3]);
        $this->assertSame('TANGGAL LAHIR', $headings[4]);
        $this->assertSame('AGAMA', $headings[5]);
        $this->assertSame('SUSUNAN KELUARGA', $headings[6]);
        $this->assertSame('STATUS KARYAWAN', $headings[7]);
        $this->assertSame('JABATAN', $headings[8]);
        $this->assertSame('LEVEL', $headings[9]);
        $this->assertSame('UNIT KERJA', $headings[10]);
        $this->assertSame('GOLONGAN', $headings[11]);
        $this->assertSame('RM LEVEL', $headings[12]);
        $this->assertSame('STATUS PENUGASAN', $headings[13]);
        $this->assertSame('TUGAS RANGKAP / PENUGASAN SEMENTARA', $headings[14], 'Kolom 15 (O) harus memuat Tugas Rangkap');
        $this->assertSame('TMT BEKERJA', $headings[15]);
        $this->assertSame('TANGGAL DIANGKAT STAF', $headings[16]);
        $this->assertSame('TANGGAL DALAM JABATAN', $headings[17]);
        $this->assertSame('TMT UNIT KERJA', $headings[18]);
        $this->assertSame('JALUR MASUK', $headings[19]);
        $this->assertSame('JOB GRADE', $headings[20]);
        $this->assertSame('PERSON GRADE', $headings[21]);
        $this->assertSame('PENDIDIKAN TERAKHIR', $headings[22]);
        $this->assertSame('SEKOLAH', $headings[23]);
        $this->assertSame('TANGGAL MBT', $headings[24]);
        $this->assertSame('TANGGAL PENSIUN', $headings[25]);

        $formats = $export->columnFormats();
        $this->assertSame(NumberFormat::FORMAT_TEXT, $formats['A'], 'Kolom A (NIK) wajib bertipe TEXT agar nol di depan tidak hilang');
        $this->assertSame(NumberFormat::FORMAT_DATE_YYYYMMDD, $formats['E']);
        $this->assertSame(NumberFormat::FORMAT_DATE_YYYYMMDD, $formats['P']);
        $this->assertSame(NumberFormat::FORMAT_DATE_YYYYMMDD, $formats['Y']);
        $this->assertSame(NumberFormat::FORMAT_DATE_YYYYMMDD, $formats['Z']);

        $events = $export->registerEvents();
        $this->assertArrayHasKey(AfterSheet::class, $events, 'Event AfterSheet wajib terdaftar untuk styling korporat PTPN IV');
    }

    public function test_export_row_mapping_transforms_single_and_multi_concurrent_jobs_accurately(): void
    {
        // 1. Pegawai Definitif Murni (Tanpa Tugas Rangkap)
        $empSingle = Employee::factory()->create([
            'nik' => '13009988',
            'nama' => 'BUDI SANTOSO',
            'status_penugasan' => 'Definitif',
        ]);

        // 2. Pegawai dengan 1 Tugas Rangkap (Plt)
        $empPlt = Employee::factory()->create([
            'nik' => '13009977',
            'nama' => 'AHMAD DAHLAN',
            'jabatan' => 'Asisten Afdeling I',
            'unit_kerja' => 'Kebun Danau Salak',
        ]);
        JobHistory::create([
            'employee_nik' => '13009977',
            'jabatan' => 'Pj. Asisten Kepala Tanaman',
            'unit_kerja' => 'Kebun Danau Salak',
            'status_penugasan' => 'Plt',
            'nomor_sk' => 'SK/001/DIR/2024',
            'tmt_awal' => '2024-03-01',
            'tmt_akhir' => null,
        ]);

        // 3. Pegawai dengan Multi Tugas Rangkap (Plt & Diperbantukan)
        $empMulti = Employee::factory()->create([
            'nik' => '13009966',
            'nama' => 'SITI AMINAH',
            'jabatan' => 'Kepala Tata Usaha',
            'unit_kerja' => 'PKS Rimba Belian',
        ]);
        JobHistory::create([
            'employee_nik' => '13009966',
            'jabatan' => 'Pj. Masinis Kepala',
            'unit_kerja' => 'PKS Rimba Belian',
            'status_penugasan' => 'Pjs',
            'nomor_sk' => 'SK/010/DIR/2024',
            'tmt_awal' => '2024-01-15',
            'tmt_akhir' => null,
        ]);
        JobHistory::create([
            'employee_nik' => '13009966',
            'jabatan' => 'Tim Pengadaan Logistik',
            'unit_kerja' => 'Kantor Direksi Pontianak',
            'status_penugasan' => 'Diperbantukan',
            'nomor_sk' => 'SK/020/DIR/2024',
            'tmt_awal' => '2024-02-01',
            'tmt_akhir' => null,
        ]);

        $export = new EmployeesExport;

        // Query dengan eager-loading concurrentJobs
        $empSingleLoaded = $export->query()->where('nik', '13009988')->first();
        $empPltLoaded = $export->query()->where('nik', '13009977')->first();
        $empMultiLoaded = $export->query()->where('nik', '13009966')->first();

        $rowSingle = $export->map($empSingleLoaded);
        $rowPlt = $export->map($empPltLoaded);
        $rowMulti = $export->map($empMultiLoaded);

        // Validasi Pegawai 1: Kolom O (index 14) harus kosong
        $this->assertSame('', $rowSingle[14], 'Pegawai tanpa tugas rangkap harus memiliki sel kosong di kolom tugas rangkap');

        // Validasi Pegawai 2: 1 tugas rangkap dengan nomor SK dan TMT
        $this->assertStringContainsString('[Plt] Pj. Asisten Kepala Tanaman — Kebun Danau Salak', $rowPlt[14]);
        $this->assertStringContainsString('SK: SK/001/DIR/2024', $rowPlt[14]);
        $this->assertStringContainsString('TMT: 2024-03-01', $rowPlt[14]);

        // Validasi Pegawai 3: Multi tugas rangkap dipisahkan newline \n
        $this->assertStringContainsString("\n", $rowMulti[14], 'Multi tugas rangkap harus dipisahkan oleh baris baru');
        $this->assertStringContainsString('[Pjs] Pj. Masinis Kepala — PKS Rimba Belian', $rowMulti[14]);
        $this->assertStringContainsString('[Diperbantukan] Tim Pengadaan Logistik — Kantor Direksi Pontianak', $rowMulti[14]);
    }

    public function test_export_action_records_security_audit_trail_log(): void
    {
        Excel::fake();

        Employee::factory()->count(2)->create();

        Log::shouldReceive('info')
            ->once()
            ->with(
                'HRIS Export Data Pegawai dieksekusi',
                \Mockery::on(function ($context) {
                    return isset($context['user_id'])
                        && $context['user_id'] === $this->adminUser->id
                        && isset($context['record_count'])
                        && $context['record_count'] >= 2
                        && isset($context['timestamp']);
                })
            );

        $response = $this->actingAs($this->adminUser)->get(route('admin.employees.export'));
        $response->assertSuccessful();
    }

    public function test_export_generates_uniform_corporate_green_header_styling(): void
    {
        $export = new EmployeesExport;
        $sheet = new Spreadsheet;
        $activeSheet = $sheet->getActiveSheet();

        // Populate headings
        $activeSheet->fromArray($export->headings(), null, 'A1');

        // Execute AfterSheet event closure
        $events = $export->registerEvents();
        $afterSheetCallback = $events[AfterSheet::class];

        $eventMock = new AfterSheet(
            new Sheet($activeSheet),
            $export
        );

        $afterSheetCallback($eventMock);

        // Verify Header Styling: Uniform PTPN IV Corporate Green (#1B5330) across columns
        $a1Fill = $activeSheet->getStyle('A1')->getFill()->getStartColor()->getRGB();
        $m1Fill = $activeSheet->getStyle('M1')->getFill()->getStartColor()->getRGB();
        $o1Fill = $activeSheet->getStyle('O1')->getFill()->getStartColor()->getRGB();
        $z1Fill = $activeSheet->getStyle('Z1')->getFill()->getStartColor()->getRGB();

        $this->assertSame('1B5330', strtoupper($a1Fill), 'Header A1 harus berwarna Hijau Korporat PTPN IV (1B5330)');
        $this->assertSame('1B5330', strtoupper($m1Fill), 'Header M1 harus berwarna Hijau Korporat PTPN IV (1B5330)');
        $this->assertSame('1B5330', strtoupper($o1Fill), 'Header O1 harus berwarna Hijau Korporat PTPN IV (1B5330)');
        $this->assertSame('1B5330', strtoupper($z1Fill), 'Header Z1 harus berwarna Hijau Korporat PTPN IV (1B5330)');

        // Verify Font: Bold & White
        $this->assertTrue($activeSheet->getStyle('A1')->getFont()->getBold());
        $this->assertSame('FFFFFF', strtoupper($activeSheet->getStyle('A1')->getFont()->getColor()->getRGB()));

        // Verify AutoFilter is set on A1:Z1
        $this->assertSame('A1:Z1', $activeSheet->getAutoFilter()->getRange());
    }

    public function test_employee_export_service_handles_export_and_audit_logging(): void
    {
        Excel::fake();
        Excel::matchByRegex();

        Employee::factory()->count(2)->create(['level' => 'Karpim']);

        Log::shouldReceive('info')
            ->once()
            ->with(
                'HRIS Export Data Pegawai dieksekusi',
                \Mockery::on(function ($context) {
                    return isset($context['user_id'])
                        && $context['user_id'] === $this->adminUser->id
                        && isset($context['filters']['level'])
                        && $context['filters']['level'] === 'Karpim'
                        && isset($context['record_count']);
                })
            );

        $service = new EmployeeExportService;
        $response = $service->exportExcel(['level' => 'Karpim'], $this->adminUser);

        $this->assertNotNull($response);

        Excel::assertDownloaded(
            '/^data_karyawan_ptpn4_reg5_\d{8}_\d{6}\.xlsx$/',
            function (EmployeesExport $export) {
                return $export->query()->count() >= 2;
            }
        );
    }

    public function test_export_contains_enterprise_document_properties_and_pdp_metadata(): void
    {
        $export = new EmployeesExport([], $this->adminUser);
        $properties = $export->properties();

        $this->assertArrayHasKey('creator', $properties);
        $this->assertStringContainsString($this->adminUser->name, $properties['creator']);
        $this->assertArrayHasKey('company', $properties);
        $this->assertSame('PT Perkebunan Nusantara IV (Persero) Regional V', $properties['company']);
        $this->assertArrayHasKey('category', $properties);
        $this->assertSame('HR Confidential Document', $properties['category']);
        $this->assertArrayHasKey('description', $properties);
        $this->assertStringContainsString('UU PDP No. 27/2022', $properties['description']);
    }

    public function test_export_embeds_confidential_print_footer_in_sheet(): void
    {
        $export = new EmployeesExport([], $this->adminUser);
        $sheet = new Spreadsheet;
        $activeSheet = $sheet->getActiveSheet();

        $activeSheet->fromArray($export->headings(), null, 'A1');

        $events = $export->registerEvents();
        $afterSheetCallback = $events[AfterSheet::class];

        $eventMock = new AfterSheet(
            new Sheet($activeSheet),
            $export
        );

        $afterSheetCallback($eventMock);

        $oddFooter = $activeSheet->getHeaderFooter()->getOddFooter();
        $this->assertStringContainsString('Dokumen Rahasia PT Perkebunan Nusantara IV Regional V - UU PDP No. 27/2022', $oddFooter);
        $this->assertStringContainsString($this->adminUser->name, $oddFooter);
    }
}
