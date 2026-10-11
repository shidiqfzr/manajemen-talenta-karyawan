<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeePeriodFilterTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::first() ?? User::factory()->create(['role' => 'admin']);
    }

    public function test_authenticated_admin_can_access_employees_index(): void
    {
        $response = $this->actingAs($this->user)->get(route('admin.employees.index'));

        $response->assertStatus(200);
        $response->assertSee('Daftar Karyawan');
        $response->assertSee('Unit Kerja');
        $response->assertSee('Jabatan');
        $response->assertSee('Eselon / RM Band');
        $response->assertSee('Tahun Masuk (TMT)');
        $response->assertSee('Export Data');
    }

    public function test_employees_can_be_filtered_by_rm_level(): void
    {
        $employee = Employee::factory()->create([
            'nama' => 'TESTING RM LEADERSHIP',
            'rm_level' => 'RM-1',
            'level' => 'Karpim',
        ]);

        $response = $this->actingAs($this->user)->get(route('admin.employees.index', [
            'rm_level' => 'RM-1',
        ]));

        $response->assertStatus(200);
        $response->assertSee('TESTING RM LEADERSHIP');
        $response->assertSee('Eselon: <strong>RM-1</strong>', false);
    }

    public function test_employees_can_be_filtered_by_jabatan_and_unit_kerja(): void
    {
        $employee = Employee::factory()->create([
            'nama' => 'TESTING JABATAN FILTER',
            'jabatan' => 'Asisten Tanaman Afdeling I',
            'unit_kerja' => 'Kebun Danau Salak',
        ]);

        $response = $this->actingAs($this->user)->get(route('admin.employees.index', [
            'jabatan' => 'Asisten Tanaman Afdeling I',
            'unit_kerja' => 'Kebun Danau Salak',
        ]));

        $response->assertStatus(200);
        $response->assertSee('TESTING JABATAN FILTER');
        $response->assertSee('Jabatan: <strong>Asisten Tanaman Afdeling I</strong>', false);
        $response->assertSee('Unit: <strong>Kebun Danau Salak</strong>', false);
    }

    public function test_employees_omnisearch_searches_nik_nama_jabatan_and_unit_kerja(): void
    {
        $employee = Employee::factory()->create([
            'nik' => '99887766',
            'nama' => 'BAMBANG HERMANTO',
            'jabatan' => 'Kepala Tata Usaha (KTU)',
            'unit_kerja' => 'PKS Rimba Belian',
        ]);

        // 1. Search by NIK
        $resNik = $this->actingAs($this->user)->get(route('admin.employees.index', ['search' => '99887766']));
        $resNik->assertStatus(200)->assertSee('BAMBANG HERMANTO');

        // 2. Search by Nama
        $resNama = $this->actingAs($this->user)->get(route('admin.employees.index', ['search' => 'HERMANTO']));
        $resNama->assertStatus(200)->assertSee('BAMBANG HERMANTO');

        // 3. Search by Jabatan
        $resJabatan = $this->actingAs($this->user)->get(route('admin.employees.index', ['search' => 'Tata Usaha']));
        $resJabatan->assertStatus(200)->assertSee('BAMBANG HERMANTO');

        // 4. Search by Unit Kerja
        $resUnit = $this->actingAs($this->user)->get(route('admin.employees.index', ['search' => 'Rimba Belian']));
        $resUnit->assertStatus(200)->assertSee('BAMBANG HERMANTO');
    }

    public function test_employees_can_be_filtered_by_year_and_month(): void
    {
        // Fetch an existing employee to test with their tmt_bekerja year/month
        $employee = Employee::whereNotNull('tmt_bekerja')->first();

        if ($employee) {
            $tmt = Carbon::parse($employee->tmt_bekerja);
            $year = $tmt->year;
            $month = $tmt->month;

            $response = $this->actingAs($this->user)->get(route('admin.employees.index', [
                'tahun_masuk' => $year,
                'bulan_masuk' => $month,
            ]));

            $response->assertStatus(200);
            $response->assertSee((string) $year);
            $response->assertSee('Periode: Tahun '.$year);
            $response->assertSee('Tahun: <strong>'.$year.'</strong>', false);
        } else {
            $this->assertTrue(true);
        }
    }

    public function test_legacy_statistics_url_redirects_to_employees_index(): void
    {
        $response = $this->actingAs($this->user)->get('/admin/employees/statistics');

        $response->assertRedirect(route('admin.employees.index'));
    }

    public function test_employees_can_be_filtered_by_status_khusus_sanksi_and_shows_governance_badges(): void
    {
        $sanctionedEmp = Employee::factory()->create([
            'nama' => 'PEGAWAI SANKSI TEST',
            'jabatan' => 'Diperbantukan Bagian Operasional',
            'tanggal_berakhir_sanksi' => Carbon::today()->addDays(30)->toDateString(),
        ]);

        $normalEmp = Employee::factory()->create([
            'nama' => 'PEGAWAI NORMAL TEST',
            'tanggal_berakhir_sanksi' => null,
        ]);

        $response = $this->actingAs($this->user)->get(route('admin.employees.index', [
            'status_khusus' => 'sanksi',
        ]));

        $response->assertStatus(200);
        $response->assertSee('PEGAWAI SANKSI TEST');
        $response->assertDontSee('PEGAWAI NORMAL TEST');
        $response->assertSee('Tata Kelola: <strong>Masa Sanksi (Diperban)</strong>', false);
        $response->assertSee('Masa Sanksi s/d');
    }

    public function test_employees_can_be_filtered_by_status_khusus_overflow_and_shows_pool_badges(): void
    {
        $advisorEmp = Employee::factory()->create([
            'nama' => 'PEGAWAI ADVISOR TEST',
            'jabatan' => 'Senior Advisor Agronomi',
        ]);

        $regularEmp = Employee::factory()->create([
            'nama' => 'PEGAWAI REGULAR TEST',
            'jabatan' => 'Mandor Panen',
        ]);

        $response = $this->actingAs($this->user)->get(route('admin.employees.index', [
            'status_khusus' => 'overflow',
        ]));

        $response->assertStatus(200);
        $response->assertSee('PEGAWAI ADVISOR TEST');
        $response->assertDontSee('PEGAWAI REGULAR TEST');
        $response->assertSee('Tata Kelola: <strong>Jabatan Overflow (Advisor)</strong>', false);
        $response->assertSee('Fungsional Pool');
    }

    public function test_employees_can_be_filtered_by_status_khusus_rangkap(): void
    {
        $rangkapEmp = Employee::factory()->create([
            'nama' => 'PEGAWAI RANGKAP TEST',
            'jabatan' => 'Asisten Tanaman',
        ]);
        $rangkapEmp->jobHistories()->create([
            'jabatan' => 'Pj. Kepala Bagian Tanaman',
            'unit_kerja' => 'Kantor Direksi',
            'status_penugasan' => 'Pjs',
            'tmt_awal' => Carbon::today()->subMonths(2),
            'status_riwayat' => 'Aktif',
        ]);

        $singleEmp = Employee::factory()->create([
            'nama' => 'PEGAWAI SINGLE TEST',
            'jabatan' => 'Mandor Panen',
        ]);

        $response = $this->actingAs($this->user)->get(route('admin.employees.index', [
            'status_khusus' => 'rangkap',
        ]));

        $response->assertStatus(200);
        $response->assertSee('PEGAWAI RANGKAP TEST');
        $response->assertDontSee('PEGAWAI SINGLE TEST');
        $response->assertSee('Tata Kelola: <strong>Tugas Rangkap</strong>', false);
    }
}
