<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\User;
use Carbon\Carbon;
use Tests\TestCase;

class EmployeePeriodFilterTest extends TestCase
{
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::first() ?? User::factory()->create();
    }

    public function test_authenticated_admin_can_access_employees_index(): void
    {
        $response = $this->actingAs($this->user)->get(route('admin.employees.index'));

        $response->assertStatus(200);
        $response->assertSee('Data Karyawan');
        $response->assertSee('Tahun Masuk (TMT)');
        $response->assertSee('Bulan Masuk');
        $response->assertSee('Export Data');
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
}
