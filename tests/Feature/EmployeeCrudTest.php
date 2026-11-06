<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\JobHistory;
use App\Models\Position;
use App\Models\Unit;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class EmployeeCrudTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin']);
    }

    public function test_admin_can_create_employee_with_auto_retirement_and_initial_job_history(): void
    {
        $nik = '88990011';

        // Clean up if already exists from prior run
        Employee::where('nik', $nik)->delete();

        // Ensure the unit and position exist and are linked to satisfy validation
        $unit = Unit::firstOrCreate(['nama' => 'Kebun Inti Gunung Meliau'], ['kode' => 'KIGM', 'wilayah' => 'Lainnya', 'is_active' => true]);
        $pos = Position::firstOrCreate(['nama' => 'Asisten Afdeling', 'level' => 'Karpim'], ['is_active' => true]);
        $unit->positions()->syncWithoutDetaching([$pos->id => ['kuota' => 1]]);

        $postData = [
            'nik' => $nik,
            'nama' => 'Budi Prasetyo Testing',
            'jabatan' => 'Asisten Afdeling',
            'unit_kerja' => 'Kebun Inti Gunung Meliau',
            'level' => 'Karpim',
            'golongan' => 'IIIA/00',
            'tanggal_lahir' => '1988-06-15',
            'tmt_bekerja' => '2015-01-01',
            'tanggal_dalam_jabatan' => '2023-01-01',
            'job_grade' => 11,
            'person_grade' => 11,
            'status_karyawan' => 'Tetap (PKWTT)',
            // tanggal_pensiun and tanggal_mbt are intentionally left empty!
        ];

        $response = $this->actingAs($this->admin)->post(route('admin.employees.store'), $postData);

        $response->assertRedirect(route('admin.employees.index'));
        $response->assertSessionHas('success');

        // 1. Verify Employee created with auto-calculated retirement dates & uppercase name
        $employee = Employee::where('nik', $nik)->first();
        $this->assertNotNull($employee);
        $this->assertEquals('BUDI PRASETYO TESTING', $employee->nama);
        $this->assertEquals('2044-06-01', $employee->tanggal_pensiun->toDateString()); // 1988 + 56 = 2044, startOfMonth of June
        $this->assertEquals('2043-06-01', $employee->tanggal_mbt->toDateString());     // T-1 year from pensiun

        // 2. Verify initial JobHistory was atomically created
        $history = JobHistory::where('employee_nik', $nik)->first();
        $this->assertNotNull($history);
        $this->assertEquals('Asisten Afdeling', $history->jabatan);
        $this->assertEquals('Kebun Inti Gunung Meliau', $history->unit_kerja);
        $this->assertEquals('PENUGASAN', $history->jenis_mutasi);
        $this->assertNull($history->tmt_akhir);

        // Clean up
        $employee->delete();
    }

    public function test_validation_rejects_invalid_nik(): void
    {
        // Case 1: Short NIK (5 digits)
        $response = $this->actingAs($this->admin)->post(route('admin.employees.store'), [
            'nik' => '12345',
            'nama' => 'Test Invalid',
            'jabatan' => 'Mandor',
            'unit_kerja' => 'Kebun Ngabang',
            'level' => 'Karpel',
        ]);
        $response->assertSessionHasErrors('nik');

        // Case 2: Alphanumeric NIK (contains letters)
        $response2 = $this->actingAs($this->admin)->post(route('admin.employees.store'), [
            'nik' => '1234567A',
            'nama' => 'Test Invalid',
            'jabatan' => 'Mandor',
            'unit_kerja' => 'Kebun Ngabang',
            'level' => 'Karpel',
        ]);
        $response2->assertSessionHasErrors('nik');
    }

    public function test_admin_can_update_employee_data(): void
    {
        $nik = '88990022';
        Employee::where('nik', $nik)->delete();

        $employee = Employee::create([
            'nik' => $nik,
            'nama' => 'SITI AMINAH',
            'jabatan' => 'Kerani Tata Usaha',
            'unit_kerja' => 'Kebun Parindu',
            'level' => 'Karpel',
            'golongan' => 'IIB/02',
            'tanggal_lahir' => '1990-03-10',
            'tanggal_pensiun' => '2046-04-01',
            'tanggal_mbt' => '2045-04-01',
            'tanggal_dalam_jabatan' => '2020-01-01',
            'job_grade' => 4,
            'person_grade' => 4,
        ]);

        $response = $this->actingAs($this->admin)->put(route('admin.employees.update', $nik), [
            'nama' => 'Siti Aminah Updated',
            'jabatan' => 'Kerani Kepala',
            'unit_kerja' => 'Kebun Parindu',
            'level' => 'Karpel',
            'golongan' => 'IIC/00',
            'tanggal_lahir' => '1990-03-10',
            'job_grade' => 5,
            'person_grade' => 5,
            'status_karyawan' => 'Tetap (PKWTT)',
        ]);

        $response->assertRedirect(route('admin.employees.index'));

        $employee->refresh();
        $this->assertEquals('SITI AMINAH UPDATED', $employee->nama);
        // jabatan dan golongan tidak boleh berubah via form edit (SSOT rules)
        $this->assertEquals(5, $employee->job_grade);

        // Clean up
        $employee->delete();
    }

    public function test_directory_selection_uses_real_named_checkboxes_for_keyboard_users(): void
    {
        foreach (['88990041' => 'PEGAWAI SATU', '88990042' => 'PEGAWAI DUA'] as $nik => $nama) {
            Employee::create([
                'nik' => $nik,
                'nama' => $nama,
                'jabatan' => 'Operator Pabrik',
                'unit_kerja' => 'PKS Parindu',
                'level' => 'Karpel',
            ]);
        }

        $response = $this->actingAs($this->admin)->get(route('admin.employees.index'));

        $response->assertOk()
            ->assertSee('type="checkbox"', false)
            ->assertSee('aria-label="Pilih PEGAWAI SATU"', false)
            ->assertSee('aria-label="Pilih PEGAWAI DUA"', false)
            ->assertSee('aria-label="Pilih semua pegawai di halaman ini"', false)
            ->assertSee('x-effect="$el.indeterminate', false)
            ->assertSee('role="status"', false)
            ->assertDontSee('Pilih Semua Pegawai di Halaman Ini (Tips');
    }

    public function test_admin_can_delete_employee(): void
    {
        $nik = '88990033';
        Employee::where('nik', $nik)->delete();

        $employee = Employee::create([
            'nik' => $nik,
            'nama' => 'PEGAWAI DIHAPUS',
            'jabatan' => 'Operator Pabrik',
            'unit_kerja' => 'PKS Parindu',
            'level' => 'Karpel',
        ]);

        JobHistory::create([
            'employee_nik' => $nik,
            'jabatan' => 'Operator Pabrik',
            'unit_kerja' => 'PKS Parindu',
            'tmt_awal' => '2022-01-01',
        ]);

        $response = $this->actingAs($this->admin)->delete(route('admin.employees.destroy', $nik));

        $response->assertRedirect(route('admin.employees.index'));
        $this->assertNull(Employee::where('nik', $nik)->first());
        $this->assertEquals(0, JobHistory::where('employee_nik', $nik)->count());
    }

    public function test_admin_can_access_create_form_with_master_dropdowns(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.employees.create'));

        $response->assertOk();
        $response->assertViewHas(['groupedUnits', 'religions', 'genders', 'levels', 'familyStatuses', 'educationLevels']);
        $response->assertSee('name="jenis_kelamin"', false);
        $response->assertSee('name="unit_kerja"', false);
        $response->assertSee('name="agama"', false);
        $response->assertSee('name="susunan_keluarga"', false);
        $response->assertSee('name="pendidikan_terakhir"', false);
        $response->assertSee('Regional Office (Kantor Direksi Pontianak)');
    }

    public function test_admin_can_access_edit_form_with_master_dropdowns(): void
    {
        $nik = '88990044';
        Employee::where('nik', $nik)->delete();

        $employee = Employee::create([
            'nik' => $nik,
            'nama' => 'TESTING DROPDOWN EDIT',
            'jabatan' => 'Asisten Tanaman',
            'unit_kerja' => 'Kebun Ngabang',
            'level' => 'Karpim',
            'jenis_kelamin' => 'L',
            'agama' => 'Islam',
            'susunan_keluarga' => 'K/2',
            'pendidikan_terakhir' => 'D4 / S1',
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.employees.edit', $nik));

        $response->assertOk();
        $response->assertViewHas(['groupedUnits', 'religions', 'genders', 'levels', 'familyStatuses', 'educationLevels']);
        $response->assertSee('name="jenis_kelamin"', false);
        $response->assertSee('name="unit_kerja"', false);
        $response->assertSee('Kebun Ngabang');

        // Also test show view renders gender
        $showResponse = $this->actingAs($this->admin)->get(route('admin.employees.show', $nik));
        $showResponse->assertOk();
        $showResponse->assertSee('Jenis Kelamin');
        $showResponse->assertSee('Laki-laki (L)');

        $employee->delete();
    }

    public function test_employee_rm_level_follows_the_formasi_jabatan_and_cannot_be_overridden(): void
    {
        $nik = '88990055';
        Employee::where('nik', $nik)->delete();

        $unit = Unit::firstOrCreate(['nama' => 'Kebun Inti Gunung Meliau'], ['kode' => 'KIGM', 'wilayah' => 'Lainnya', 'is_active' => true]);
        $pos = Position::firstOrCreate(['nama' => 'Asisten Afdeling', 'level' => 'Karpim'], ['is_active' => true]);
        $pos->update(['rm_level' => 'RM-3']);
        $unit->positions()->syncWithoutDetaching([$pos->id => ['kuota' => 1]]);

        $postData = [
            'nik' => $nik,
            'nama' => 'TESTING RM FORMASI',
            'jabatan' => 'Asisten Afdeling',
            'unit_kerja' => 'Kebun Inti Gunung Meliau',
            'level' => 'Karpim',
            'rm_level' => 'RM-2',
            'golongan' => 'IIIA/00',
            'job_grade' => 11,
            'person_grade' => 11,
            'status_karyawan' => 'Tetap (PKWTT)',
        ];

        // 1. RM Band yang berbeda dari formasi jabatan ditolak
        $this->actingAs($this->admin)->post(route('admin.employees.store'), $postData)->assertSessionHasErrors('rm_level');
        $this->assertNull(Employee::where('nik', $nik)->first());

        // 2. RM Band kosong diturunkan dari formasi jabatan
        $this->actingAs($this->admin)->post(route('admin.employees.store'), array_merge($postData, ['rm_level' => '']))
            ->assertRedirect(route('admin.employees.index'));

        $employee = Employee::where('nik', $nik)->firstOrFail();
        $this->assertEquals('RM-3', $employee->rm_level);

        // 3. Form edit menampilkan RM Band hanya-baca
        $this->actingAs($this->admin)->get(route('admin.employees.edit', $nik))
            ->assertOk()
            ->assertViewHas('rmLevels')
            ->assertSee('name="rm_level"', false);

        // 4. Pembaruan tidak dapat mengubah RM Band
        $this->actingAs($this->admin)->put(route('admin.employees.update', $nik), array_merge($postData, ['rm_level' => 'RM-1']))
            ->assertRedirect(route('admin.employees.index'));

        $this->assertEquals('RM-3', $employee->refresh()->rm_level);

        $employee->delete();
    }

    public function test_store_rejects_employee_beyond_the_formasi_quota_and_unseated_positions(): void
    {
        $unit = Unit::firstOrCreate(['nama' => 'Kebun Kuota Form'], ['kode' => 'KKF', 'wilayah' => 'Lainnya', 'is_active' => true]);
        $pos = Position::firstOrCreate(['nama' => 'Manajer Kuota Form', 'level' => 'Karpim'], ['rm_level' => 'RM-1', 'is_active' => true]);
        $unit->positions()->syncWithoutDetaching([$pos->id => ['kuota' => 1, 'bidang' => 'TAN']]);

        $payload = fn (string $nik, string $jabatan = 'Manajer Kuota Form') => [
            'nik' => $nik,
            'nama' => 'PEGAWAI KUOTA',
            'jabatan' => $jabatan,
            'unit_kerja' => 'Kebun Kuota Form',
            'level' => 'Karpim',
            'golongan' => 'IVA/00',
            'job_grade' => 15,
            'person_grade' => 15,
            'status_karyawan' => 'Tetap (PKWTT)',
        ];

        $this->actingAs($this->admin)->post(route('admin.employees.store'), $payload('88770001'))
            ->assertRedirect(route('admin.employees.index'));

        // Kursi kedua melebihi kuota
        $this->actingAs($this->admin)->post(route('admin.employees.store'), $payload('88770002'))
            ->assertSessionHasErrors('jabatan');
        $this->assertDatabaseMissing('employees', ['nik' => '88770002']);

        // Jabatan di luar formasi unit ditolak walaupun unit sudah memiliki formasi lain
        $this->actingAs($this->admin)->post(route('admin.employees.store'), $payload('88770003', 'Jabatan Tanpa Kursi'))
            ->assertSessionHasErrors('jabatan');
        $this->assertDatabaseMissing('employees', ['nik' => '88770003']);
    }

    public function test_create_form_follows_position_control_and_form_accessibility_rules(): void
    {
        $html = $this->actingAs($this->admin)->get(route('admin.employees.create'))->assertOk()->getContent();

        // Master formasi hanya diubah melalui Master Data, tidak dari form pegawai
        $this->assertStringNotContainsString('Unit Baru', $html);
        $this->assertStringNotContainsString('Jabatan Baru', $html);
        $this->assertStringNotContainsString('Tambah Unit Baru', $html);

        // Label terhubung ke kontrol, tanda wajib, dan perlindungan klik ganda
        $this->assertStringContainsString('for="nikInput"', $html);
        $this->assertStringContainsString('id="nikInput"', $html);
        $this->assertStringContainsString('for="jabatanInput"', $html);
        $this->assertStringContainsString('role="radiogroup"', $html);
        $this->assertStringContainsString('wajib diisi', $html);
        $this->assertStringContainsString("addEventListener('submit'", $html);
        $this->assertMatchesRegularExpression('/<input[^>]*id="rmLevelInput"[^>]*readonly/', $html);

        // Jalur masuk hanya berisi jalur baku, tanpa pilihan di luar pedoman
        foreach (['value="CKP"', 'value="Talent Scouting"', 'value="RBB"', 'value="Reguler"'] as $option) {
            $this->assertStringContainsString($option, $html);
        }
        $this->assertDoesNotMatchRegularExpression('/<select[^>]*id="jalur_masuk".*?<option value="">/s', $html, 'Jalur masuk wajib diisi dan tidak boleh punya opsi kosong.');
        $this->assertStringNotContainsString('Pro Hire', $html);
        $this->assertStringNotContainsString('value="Lainnya"', $html);

        // Foto bersifat opsional tanpa label tambahan
        $this->assertStringNotContainsString('>Opsional<', $html);
    }

    public function test_check_nik_reports_availability_without_exposing_personal_data(): void
    {
        Employee::factory()->create(['nik' => '88550001', 'nama' => 'RAHASIA PEGAWAI']);

        $taken = $this->actingAs($this->admin)->getJson(route('admin.employees.checkNik', ['nik' => '88550001']));
        $taken->assertOk()->assertJson(['valid' => true, 'exists' => true]);
        $this->assertStringNotContainsString('RAHASIA', $taken->getContent());

        $this->actingAs($this->admin)->getJson(route('admin.employees.checkNik', ['nik' => '88550002']))
            ->assertOk()->assertJson(['valid' => true, 'exists' => false, 'url' => null]);

        $this->actingAs($this->admin)->getJson(route('admin.employees.checkNik', ['nik' => '123']))
            ->assertStatus(422)->assertJson(['valid' => false]);

        Auth::guard('web')->logout();
        $this->getJson(route('admin.employees.checkNik', ['nik' => '88550001']))->assertUnauthorized();
    }

    public function test_failed_submission_shows_error_summary_and_inline_error_state(): void
    {
        $html = $this->actingAs($this->admin)->from(route('admin.employees.create'))
            ->followingRedirects()
            ->post(route('admin.employees.store'), ['nik' => '12', 'nama' => ''])
            ->assertOk()->getContent();

        $this->assertStringContainsString('id="errorSummary"', $html);
        $this->assertStringContainsString('isian perlu diperbaiki', $html);
        $this->assertStringContainsString('aria-invalid="true"', $html);
        $this->assertStringContainsString('border-danger', $html);
        $this->assertStringContainsString('href="#nikInput"', $html);
    }

    public function test_store_accepts_each_standard_entry_channel_and_rejects_others(): void
    {
        $unit = Unit::firstOrCreate(['nama' => 'Kebun Jalur Form'], ['kode' => 'KJF', 'wilayah' => 'Lainnya', 'is_active' => true]);
        $pos = Position::firstOrCreate(['nama' => 'Asisten Jalur Form', 'level' => 'Karpim'], ['rm_level' => 'RM-3', 'is_active' => true]);
        $unit->positions()->syncWithoutDetaching([$pos->id => ['kuota' => 5, 'bidang' => 'TAN']]);

        $payload = fn (string $nik, string $jalur) => [
            'nik' => $nik,
            'nama' => 'PEGAWAI JALUR',
            'jabatan' => 'Asisten Jalur Form',
            'unit_kerja' => 'Kebun Jalur Form',
            'level' => 'Karpim',
            'golongan' => 'IIIA/00',
            'job_grade' => 12,
            'person_grade' => 12,
            'status_karyawan' => 'Tetap (PKWTT)',
            'jalur_masuk' => $jalur,
        ];

        foreach (['CKP' => '88660001', 'Talent Scouting' => '88660002', 'RBB' => '88660003', 'Reguler' => '88660004'] as $jalur => $nik) {
            $this->actingAs($this->admin)->post(route('admin.employees.store'), $payload($nik, $jalur))
                ->assertRedirect(route('admin.employees.index'));
            $this->assertDatabaseHas('employees', ['nik' => $nik, 'jalur_masuk' => $jalur]);
        }

        $this->actingAs($this->admin)->post(route('admin.employees.store'), $payload('88660005', 'Pro Hire'))
            ->assertSessionHasErrors('jalur_masuk');
    }

    public function test_api_unit_positions_returns_filtered_positions_by_level(): void
    {
        // 1. Setup a specific Unit and Positions
        $unitName = 'Testing Unit API';
        $unit = Unit::create([
            'nama' => $unitName,
            'kode' => 'TUA',
            'wilayah' => 'Lainnya',
            'is_active' => true,
        ]);

        $posKarpim = Position::create([
            'nama' => 'Manajer Testing',
            'level' => 'Karpim',
            'is_active' => true,
        ]);

        $posKarpel = Position::create([
            'nama' => 'Kerani Testing',
            'level' => 'Karpel',
            'is_active' => true,
        ]);

        $unit->positions()->attach([
            $posKarpim->id => ['kuota' => 1],
            $posKarpel->id => ['kuota' => 2],
        ]);

        // 2. Request without level should return all
        $responseAll = $this->actingAs($this->admin)->getJson(route('admin.api.unit-positions', [
            'unit_name' => $unitName,
        ]));
        $responseAll->assertOk();
        $responseAll->assertJsonFragment(['positions' => ['Manajer Testing', 'Kerani Testing']]);

        // 3. Request with Karpim level should return only Karpim position
        $responseKarpim = $this->actingAs($this->admin)->getJson(route('admin.api.unit-positions', [
            'unit_name' => $unitName,
            'level' => 'Karpim',
        ]));
        $responseKarpim->assertOk();
        $responseKarpim->assertJsonFragment(['positions' => ['Manajer Testing']]);
        $responseKarpim->assertJsonMissing(['Kerani Testing']);

        // 4. Request with Karpel level should return only Karpel position
        $responseKarpel = $this->actingAs($this->admin)->getJson(route('admin.api.unit-positions', [
            'unit_name' => $unitName,
            'level' => 'Karpel',
        ]));
        $responseKarpel->assertOk();
        $responseKarpel->assertJsonFragment(['positions' => ['Kerani Testing']]);
        $responseKarpel->assertJsonMissing(['Manajer Testing']);

        // Clean up
        $unit->positions()->detach();
        $unit->delete();
        $posKarpim->delete();
        $posKarpel->delete();
    }

    public function test_employee_creation_fails_if_position_invalid_for_unit(): void
    {
        // Setup a unit and a specific position
        $unitName = 'Validation Test Unit';
        $unit = Unit::create([
            'nama' => $unitName,
            'kode' => 'VTU',
            'wilayah' => 'Lainnya',
            'is_active' => true,
        ]);

        $posValid = Position::create([
            'nama' => 'Posisi Valid Karpim',
            'level' => 'Karpim',
            'is_active' => true,
        ]);

        $unit->positions()->attach($posValid->id, ['kuota' => 1]);

        $nik = '88990099';
        Employee::where('nik', $nik)->delete();

        $postData = [
            'nik' => $nik,
            'nama' => 'Testing Validation',
            'jabatan' => 'Posisi Tidak Valid', // Not attached to unit!
            'unit_kerja' => $unitName,
            'level' => 'Karpim',
            'golongan' => 'IIIA/00',
            'tanggal_lahir' => '1990-01-01',
            'tmt_bekerja' => '2015-01-01',
            'status_karyawan' => 'Tetap (PKWTT)',
        ];

        $response = $this->actingAs($this->admin)->post(route('admin.employees.store'), $postData);

        // Should be redirected back with errors for 'jabatan'
        $response->assertSessionHasErrors('jabatan');
        $this->assertNull(Employee::where('nik', $nik)->first());

        // Now test with valid position
        $postData['jabatan'] = 'Posisi Valid Karpim';
        $responseValid = $this->actingAs($this->admin)->post(route('admin.employees.store'), $postData);
        $responseValid->assertRedirect(route('admin.employees.index'));
        $this->assertNotNull(Employee::where('nik', $nik)->first());

        // Clean up
        $unit->positions()->detach();
        $unit->delete();
        $posValid->delete();
        Employee::where('nik', $nik)->first()->delete();
    }

    public function test_admin_can_bulk_delete_employees(): void
    {
        Employee::whereIn('nik', ['77112233', '77112244'])->delete();

        Employee::create([
            'nik' => '77112233',
            'nama' => 'PEGAWAI BULK SATU',
            'jabatan' => 'Asisten Afdeling',
            'unit_kerja' => 'Kebun Parindu',
            'level' => 'Karpim',
            'golongan' => 'IIIA/00',
            'tanggal_lahir' => '1990-01-01',
            'tmt_bekerja' => '2015-01-01',
        ]);

        Employee::create([
            'nik' => '77112244',
            'nama' => 'PEGAWAI BULK DUA',
            'jabatan' => 'Asisten Afdeling',
            'unit_kerja' => 'Kebun Parindu',
            'level' => 'Karpim',
            'golongan' => 'IIIA/00',
            'tanggal_lahir' => '1992-01-01',
            'tmt_bekerja' => '2016-01-01',
        ]);

        $response = $this->actingAs($this->admin)->post(route('admin.employees.bulkDestroy'), [
            'niks' => ['77112233', '77112244'],
        ]);

        $response->assertRedirect(route('admin.employees.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseMissing('employees', ['nik' => '77112233']);
        $this->assertDatabaseMissing('employees', ['nik' => '77112244']);
    }

    public function test_employee_mbt_and_pension_lifecycle_helpers(): void
    {
        // Approaching MBT (age 54.5)
        $approachingDate = Carbon::today()->subYears(54)->subMonths(6)->toDateString();
        $empApproaching = new Employee(['tanggal_lahir' => $approachingDate]);
        $this->assertTrue($empApproaching->isApproachingMbt());
        $this->assertFalse($empApproaching->isMbtActive());
        $this->assertFalse($empApproaching->isPensionReached());
        $this->assertNotNull($empApproaching->mbt_countdown);

        // MBT Active (age 55.5)
        $mbtDate = Carbon::today()->subYears(55)->subMonths(6)->toDateString();
        $empMbt = new Employee(['tanggal_lahir' => $mbtDate]);
        $this->assertFalse($empMbt->isApproachingMbt());
        $this->assertTrue($empMbt->isMbtActive());
        $this->assertFalse($empMbt->isPensionReached());

        // Pension Reached (age 56.5)
        $pensionDate = Carbon::today()->subYears(56)->subMonths(6)->toDateString();
        $empPension = new Employee(['tanggal_lahir' => $pensionDate]);
        $this->assertFalse($empPension->isMbtActive());
        $this->assertTrue($empPension->isPensionReached());
    }

    public function test_karpel_can_be_stored_with_the_rm_5_band_of_its_position(): void
    {
        $nik = '88990066';
        Employee::where('nik', $nik)->delete();

        $unit = Unit::firstOrCreate(['nama' => 'Bagian Sekretariat & Hukum'], ['kode' => 'Bagian', 'wilayah' => 'Lainnya', 'is_active' => true]);
        $pos = Position::firstOrCreate(['nama' => 'Satpam'], ['level' => 'Karpel', 'rm_level' => 'RM-5', 'is_active' => true]);
        $unit->positions()->syncWithoutDetaching([$pos->id => ['kuota' => 1]]);

        $response = $this->actingAs($this->admin)->post(route('admin.employees.store'), [
            'nik' => $nik,
            'nama' => 'Karpel Testing',
            'jabatan' => 'Satpam',
            'unit_kerja' => 'Bagian Sekretariat & Hukum',
            'level' => 'Karpel',
            'rm_level' => 'RM-5',
            'golongan' => 'IIA/00',
            'tanggal_lahir' => '1990-06-15',
            'tmt_bekerja' => '2015-01-01',
            'tanggal_dalam_jabatan' => '2023-01-01',
            'job_grade' => 5,
            'person_grade' => 5,
            'status_karyawan' => 'Tetap (PKWTT)',
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertSame('RM-5', Employee::where('nik', $nik)->value('rm_level'));

        Employee::where('nik', $nik)->delete();
    }
}
