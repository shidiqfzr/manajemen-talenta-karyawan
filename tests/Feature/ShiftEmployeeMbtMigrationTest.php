<?php

namespace Tests\Feature;

use App\Models\Employee;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * SUT: database/migrations/2026_11_11_100000_shift_employee_mbt_to_six_months_before_pension.php
 */
class ShiftEmployeeMbtMigrationTest extends TestCase
{
    use RefreshDatabase;

    private function migration(): object
    {
        return require database_path('migrations/2026_11_11_100000_shift_employee_mbt_to_six_months_before_pension.php');
    }

    private function mbt(Employee $employee): string
    {
        return (string) DB::table('employees')->where('nik', $employee->nik)->value('tanggal_mbt');
    }

    public function test_up_moves_old_rule_dates_but_keeps_hand_entered_ones_and_down_restores_them(): void
    {
        $oldRule = Employee::factory()->create(['tanggal_pensiun' => '2041-05-01']);
        $custom = Employee::factory()->create(['tanggal_pensiun' => '2041-05-01']);
        $noMbt = Employee::factory()->create(['tanggal_pensiun' => '2041-05-01']);

        DB::table('employees')->where('nik', $oldRule->nik)->update(['tanggal_mbt' => '2040-05-01']);
        DB::table('employees')->where('nik', $custom->nik)->update(['tanggal_mbt' => '2040-08-15']);
        DB::table('employees')->where('nik', $noMbt->nik)->update(['tanggal_mbt' => null]);

        $this->migration()->up();

        $this->assertSame('2040-11-01', substr($this->mbt($oldRule), 0, 10));
        $this->assertSame('2040-08-15', substr($this->mbt($custom), 0, 10));
        $this->assertSame('', $this->mbt($noMbt));

        $this->migration()->down();

        $this->assertSame('2040-05-01', substr($this->mbt($oldRule), 0, 10));
        $this->assertSame('2040-08-15', substr($this->mbt($custom), 0, 10));
    }

    public function test_up_is_safe_to_run_twice(): void
    {
        $employee = Employee::factory()->create(['tanggal_pensiun' => '2041-05-01']);
        DB::table('employees')->where('nik', $employee->nik)->update(['tanggal_mbt' => '2040-05-01']);

        $this->migration()->up();
        $this->migration()->up();

        $this->assertSame('2040-11-01', substr($this->mbt($employee), 0, 10));
    }
}
