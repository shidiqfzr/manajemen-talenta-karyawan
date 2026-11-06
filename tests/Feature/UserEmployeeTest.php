<?php

namespace Tests\Feature;

use App\Models\Employee;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Halaman pegawai untuk peran user: avatar foto asli atau inisial (tanpa gambar bawaan).
 */
class UserEmployeeTest extends TestCase
{
    use RefreshDatabase;

    public function test_employee_without_photo_shows_initials_instead_of_a_default_image(): void
    {
        $employee = Employee::factory()->create(['nama' => 'Budi Santoso', 'foto' => null]);

        $this->get(route('user.employees.index'))
            ->assertOk()
            ->assertSee('BS')
            ->assertDontSee('images/default.png');

        $this->get(route('user.employees.show', $employee->nik))
            ->assertOk()
            ->assertSee('BS')
            ->assertDontSee('images/default.png');
    }

    public function test_employee_with_existing_photo_file_shows_the_photo(): void
    {
        Storage::fake('public');
        $path = UploadedFile::fake()->image('foto.jpg')->store('foto', 'public');
        $employee = Employee::factory()->create(['nama' => 'Siti Aminah', 'foto' => $path]);

        $this->get(route('user.employees.show', $employee->nik))
            ->assertOk()
            ->assertSee(Storage::disk('public')->url($path), false);
    }

    public function test_stale_photo_path_falls_back_to_initials(): void
    {
        Storage::fake('public');
        $employee = Employee::factory()->create(['nama' => 'Siti Aminah', 'foto' => 'foto/hilang.jpg']);

        $this->get(route('user.employees.show', $employee->nik))
            ->assertOk()
            ->assertDontSee('foto/hilang.jpg')
            ->assertSee('SA');
    }
}
