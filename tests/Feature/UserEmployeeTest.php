<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Halaman karyawan untuk peran user: avatar foto asli atau inisial (tanpa gambar bawaan).
 */
class UserEmployeeTest extends TestCase
{
    use RefreshDatabase;

    private function staff(): User
    {
        return User::factory()->create(['role' => 'user']);
    }

    public function test_guest_is_redirected_to_login_from_the_staff_pages(): void
    {
        $employee = Employee::factory()->create();

        $this->get(route('user.employees.index'))->assertRedirect(route('login'));
        $this->get(route('user.employees.show', $employee->nik))->assertRedirect(route('login'));
        $this->get(route('user.trainings.index'))->assertRedirect(route('login'));
    }

    public function test_admin_role_cannot_open_the_staff_pages(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->get(route('user.employees.index'))->assertForbidden();
    }

    public function test_profile_of_a_separated_employee_is_not_found(): void
    {
        $employee = Employee::factory()->create(['tanggal_berhenti' => '2026-01-01']);

        $this->actingAs($this->staff())
            ->get(route('user.employees.show', $employee->nik))
            ->assertNotFound();
    }

    public function test_employee_without_photo_shows_initials_instead_of_a_default_image(): void
    {
        $employee = Employee::factory()->create(['nama' => 'Budi Santoso', 'foto' => null]);

        $this->actingAs($this->staff())->get(route('user.employees.index'))
            ->assertOk()
            ->assertSee('BS')
            ->assertDontSee('images/default.png');

        $this->actingAs($this->staff())->get(route('user.employees.show', $employee->nik))
            ->assertOk()
            ->assertSee('BS')
            ->assertDontSee('images/default.png');
    }

    public function test_employee_with_existing_photo_file_shows_the_photo(): void
    {
        Storage::fake('public');
        $path = UploadedFile::fake()->image('foto.jpg')->store('foto', 'public');
        $employee = Employee::factory()->create(['nama' => 'Siti Aminah', 'foto' => $path]);

        $this->actingAs($this->staff())->get(route('user.employees.show', $employee->nik))
            ->assertOk()
            ->assertSee(Storage::disk('public')->url($path), false);
    }

    public function test_stale_photo_path_falls_back_to_initials(): void
    {
        Storage::fake('public');
        $employee = Employee::factory()->create(['nama' => 'Siti Aminah', 'foto' => 'foto/hilang.jpg']);

        $this->actingAs($this->staff())->get(route('user.employees.show', $employee->nik))
            ->assertOk()
            ->assertDontSee('foto/hilang.jpg')
            ->assertSee('SA');
    }
}
