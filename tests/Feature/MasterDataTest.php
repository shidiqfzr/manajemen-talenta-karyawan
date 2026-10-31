<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\MasterAuditLog;
use App\Models\Position;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MasterDataTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin']);
    }

    public function test_admin_can_access_master_units_index(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.master.units.index'));

        $response->assertOk();
        $response->assertViewHas(['units', 'totalUnits', 'activeUnits', 'inactiveUnits', 'wilayahList']);
        $response->assertSee('Master Unit Kerja');
    }

    public function test_admin_can_create_new_master_unit_and_it_appears_in_employee_form(): void
    {
        $unitName = 'Kebun Percobaan Baru Kalimantan';
        Unit::where('nama', $unitName)->delete();

        // 1. Admin creates unit via Master Data
        $postData = [
            'nama' => $unitName,
            'kode' => 'KPBK',
            'wilayah' => 'Wilayah Kalimantan Barat',
            'urutan' => 999,
            'is_active' => '1',
        ];

        $response = $this->actingAs($this->admin)->post(route('admin.master.units.store'), $postData);
        $response->assertRedirect(route('admin.master.units.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('units', [
            'nama' => $unitName,
            'wilayah' => 'Wilayah Kalimantan Barat',
            'is_active' => true,
        ]);

        // 2. Verify it dynamically appears in the add-employee form
        $createFormResponse = $this->actingAs($this->admin)->get(route('admin.employees.create'));
        $createFormResponse->assertOk();
        $createFormResponse->assertSee($unitName);

        // Clean up
        Unit::where('nama', $unitName)->delete();
    }

    public function test_admin_can_toggle_unit_active_status(): void
    {
        $unit = Unit::firstOrCreate(
            ['nama' => 'Unit Testing Toggle'],
            ['wilayah' => 'Wilayah Kalimantan Barat', 'is_active' => true]
        );

        $this->assertTrue($unit->is_active);

        // Toggle to inactive
        $response = $this->actingAs($this->admin)->post(route('admin.master.units.toggle', $unit), ['reason' => 'Unit dinonaktifkan sementara']);
        $response->assertRedirect();

        $unit->refresh();
        $this->assertFalse($unit->is_active);

        // Toggle back to active
        $response2 = $this->actingAs($this->admin)->post(route('admin.master.units.toggle', $unit));
        $response2->assertRedirect();

        $unit->refresh();
        $this->assertTrue($unit->is_active);

        $unit->delete();
    }

    public function test_admin_cannot_delete_unit_with_assigned_employees(): void
    {
        $unitName = 'Kebun Testing Proteksi';
        $nik = '88990099';

        Unit::where('nama', $unitName)->delete();
        Employee::where('nik', $nik)->delete();

        $unit = Unit::create([
            'nama' => $unitName,
            'wilayah' => 'Wilayah Kalimantan Timur',
            'is_active' => true,
        ]);

        $employee = Employee::create([
            'nik' => $nik,
            'nama' => 'PEGAWAI PROTEKSI UNIT',
            'jabatan' => 'Mandor',
            'unit_kerja' => $unitName,
            'level' => 'Karpel',
        ]);

        // Try to delete unit
        $response = $this->actingAs($this->admin)->delete(route('admin.master.units.destroy', $unit));
        $response->assertSessionHas('error');

        $this->assertDatabaseHas('units', ['nama' => $unitName]);

        // Clean up
        $employee->delete();
        $unit->delete();
    }

    public function test_admin_can_access_master_positions_index(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.master.positions.index'));

        $response->assertOk();
        $response->assertViewHas(['positions', 'totalPositions', 'karpimCount', 'karpelCount', 'activeCount']);
        $response->assertSee('Master Jabatan &amp; Posisi', false);
    }

    public function test_admin_can_create_and_toggle_position(): void
    {
        $posName = 'Specialist Carbon Offset & ESG';
        Position::where('nama', $posName)->delete();

        // 1. Create Position
        $response = $this->actingAs($this->admin)->post(route('admin.master.positions.store'), [
            'nama' => $posName,
            'bidang' => 'UMU',
            'level' => 'Karpim',
            'rm_level' => 'RM-2',
            'is_active' => '1',
        ]);

        $response->assertRedirect(route('admin.master.positions.index'));
        $response->assertSessionHas('success');

        $position = Position::where('nama', $posName)->first();
        $this->assertNotNull($position);
        $this->assertEquals('RM-2', $position->rm_level);
        $this->assertTrue($position->is_active);

        // 2. Toggle Status
        $toggleResponse = $this->actingAs($this->admin)->post(route('admin.master.positions.toggle', $position), ['reason' => 'Jabatan dinonaktifkan sementara']);
        $toggleResponse->assertRedirect();

        $position->refresh();
        $this->assertFalse($position->is_active);

        // Clean up
        $position->delete();
    }

    public function test_ajax_json_quick_store_unit(): void
    {
        $unitName = 'Unit AJAX Quick Add Test';
        Unit::where('nama', $unitName)->delete();

        $response = $this->actingAs($this->admin)->postJson(route('admin.master.units.store'), [
            'nama' => $unitName,
            'wilayah' => 'Wilayah Kalimantan Selatan/Tengah',
        ]);

        $response->assertStatus(201);
        $response->assertJson([
            'success' => true,
            'unit' => [
                'nama' => $unitName,
                'wilayah' => 'Wilayah Kalimantan Selatan/Tengah',
            ],
        ]);

        $this->assertDatabaseHas('units', ['nama' => $unitName]);
        Unit::where('nama', $unitName)->delete();
    }

    public function test_creating_position_with_unit_stores_bidang_on_the_seat(): void
    {
        $unit = Unit::create(['nama' => 'Kebun Uji Formasi', 'kode' => 'Kebun', 'wilayah' => 'Kalimantan Barat', 'is_active' => true, 'urutan' => 99]);

        $this->actingAs($this->admin)->post(route('admin.master.positions.store'), [
            'nama' => 'Asisten Uji Kursi',
            'bidang' => 'KEU',
            'level' => 'Karpim',
            'rm_level' => 'RM-3',
            'unit_nama' => $unit->nama,
        ])->assertRedirect(route('admin.master.positions.index'));

        $position = Position::where('nama', 'Asisten Uji Kursi')->firstOrFail();
        $this->assertDatabaseHas('position_unit', [
            'position_id' => $position->id,
            'unit_id' => $unit->id,
            'kuota' => 1,
            'bidang' => 'KEU',
        ]);
    }

    public function test_position_index_filters_by_seat_bidang(): void
    {
        $unit = Unit::create(['nama' => 'Kebun Uji Filter', 'kode' => 'Kebun', 'wilayah' => 'Kalimantan Barat', 'is_active' => true, 'urutan' => 98]);
        $tan = Position::create(['nama' => 'Asisten Filter Tanaman', 'level' => 'Karpim', 'rm_level' => 'RM-3', 'is_active' => true]);
        $keu = Position::create(['nama' => 'Asisten Filter Keuangan', 'level' => 'Karpim', 'rm_level' => 'RM-3', 'is_active' => true]);
        $unit->positions()->attach($tan->id, ['kuota' => 1, 'bidang' => 'TAN']);
        $unit->positions()->attach($keu->id, ['kuota' => 1, 'bidang' => 'KEU']);

        $response = $this->actingAs($this->admin)->get(route('admin.master.positions.index', ['bidang' => 'TAN']));

        $response->assertOk();
        $response->assertSee('Asisten Filter Tanaman');
        $response->assertDontSee('Asisten Filter Keuangan');
    }

    public function test_unit_crud_writes_audit_rows(): void
    {
        $this->actingAs($this->admin)->post(route('admin.master.units.store'), [
            'nama' => 'Kebun Audit Uji', 'kode' => 'Kebun', 'wilayah' => 'Wilayah Kalimantan Barat', 'urutan' => 91,
        ])->assertRedirect(route('admin.master.units.index'));

        $unit = Unit::where('nama', 'Kebun Audit Uji')->firstOrFail();

        $this->actingAs($this->admin)->put(route('admin.master.units.update', $unit), [
            'nama' => 'Kebun Audit Baru', 'kode' => 'Kebun', 'wilayah' => 'Wilayah Kalimantan Barat', 'urutan' => 91,
            'is_active' => '1', 'reason' => 'Penyesuaian nama unit resmi',
        ])->assertRedirect(route('admin.master.units.index'));

        $this->actingAs($this->admin)->post(route('admin.master.units.toggle', $unit), ['reason' => 'Unit digabung ke unit lain'])
            ->assertSessionHas('success');

        $this->actingAs($this->admin)->delete(route('admin.master.units.destroy', $unit), ['reason' => 'Unit dihapus dari master'])
            ->assertSessionHas('success');

        $this->assertSame(
            ['unit.created', 'unit.renamed', 'unit.deactivated', 'unit.deleted'],
            MasterAuditLog::orderBy('id')->pluck('action')->all()
        );
    }

    public function test_position_crud_writes_audit_rows(): void
    {
        $this->actingAs($this->admin)->post(route('admin.master.positions.store'), [
            'nama' => 'Asisten Audit Uji', 'level' => 'Karpim', 'rm_level' => 'RM-3', 'bidang' => 'TAN',
        ])->assertRedirect(route('admin.master.positions.index'));

        $position = Position::where('nama', 'Asisten Audit Uji')->firstOrFail();

        $this->actingAs($this->admin)->put(route('admin.master.positions.update', $position), [
            'nama' => 'Asisten Audit Baru', 'level' => 'Karpim', 'rm_level' => 'RM-3', 'bidang' => 'TAN',
            'is_active' => '1', 'reason' => 'Penyesuaian nomenklatur jabatan',
        ])->assertRedirect(route('admin.master.positions.index'));

        $this->actingAs($this->admin)->post(route('admin.master.positions.toggle', $position), ['reason' => 'Jabatan tidak dipakai lagi'])
            ->assertSessionHas('success');

        $this->actingAs($this->admin)->delete(route('admin.master.positions.destroy', $position), ['reason' => 'Jabatan dihapus dari master'])
            ->assertSessionHas('success');

        $this->assertSame(
            ['position.created', 'position.renamed', 'position.deactivated', 'position.deleted'],
            MasterAuditLog::orderBy('id')->pluck('action')->all()
        );
    }

    public function test_blocked_unit_deactivation_redirects_back_with_an_error_message(): void
    {
        $unit = Unit::create(['nama' => 'Kebun Berpegawai Uji', 'kode' => 'Kebun', 'wilayah' => 'Wilayah Kalimantan Barat', 'is_active' => true, 'urutan' => 92]);
        Employee::factory()->count(2)->create(['unit_kerja' => $unit->nama]);

        $response = $this->actingAs($this->admin)->post(route('admin.master.units.toggle', $unit), ['reason' => 'Unit digabung ke unit lain']);

        $response->assertSessionHas('error');
        $this->assertStringContainsString('2 pegawai', session('error'));
        $this->assertTrue($unit->fresh()->is_active);
        $this->assertSame(0, MasterAuditLog::count());
    }

    public function test_rename_without_reason_is_refused_with_a_message(): void
    {
        $unit = Unit::create(['nama' => 'Kebun Nama Lama', 'kode' => 'Kebun', 'wilayah' => 'Wilayah Kalimantan Barat', 'is_active' => true, 'urutan' => 93]);

        $response = $this->actingAs($this->admin)->put(route('admin.master.units.update', $unit), [
            'nama' => 'Kebun Nama Baru', 'kode' => 'Kebun', 'wilayah' => 'Wilayah Kalimantan Barat', 'urutan' => 93, 'is_active' => '1',
        ]);

        $response->assertSessionHas('error');
        $this->assertSame('Kebun Nama Lama', $unit->fresh()->nama);
    }

    public function test_creating_karpim_position_with_unit_and_without_bidang_is_refused(): void
    {
        $unit = Unit::create(['nama' => 'Kebun Kursi Uji', 'kode' => 'Kebun', 'wilayah' => 'Wilayah Kalimantan Barat', 'is_active' => true, 'urutan' => 94]);

        $response = $this->actingAs($this->admin)->post(route('admin.master.positions.store'), [
            'nama' => 'Asisten Tanpa Bidang', 'level' => 'Karpim', 'rm_level' => 'RM-3', 'unit_nama' => $unit->nama,
        ]);

        $response->assertSessionHas('error');
        $this->assertDatabaseMissing('positions', ['nama' => 'Asisten Tanpa Bidang']);
    }

    /**
     * Payload aksi yang dikirim tombol-tombol halaman Master Data (JSON.parse('...') pada atribut @click).
     *
     * @return list<array<string, mixed>>
     */
    private function dispatchedActions(string $html): array
    {
        preg_match_all("/JSON\\.parse\\('(.+?)'\\)/s", $html, $matches);

        return array_map(function (string $literal): array {
            $json = preg_replace_callback(
                '/\\\\u([0-9a-fA-F]{4})|\\\\(.)/s',
                fn (array $m) => ($m[1] ?? '') !== '' ? mb_chr(hexdec($m[1])) : $m[2],
                $literal
            );

            return json_decode(html_entity_decode($json), true, 512, JSON_THROW_ON_ERROR);
        }, $matches[1]);
    }

    public function test_units_index_offers_management_actions_for_each_unit(): void
    {
        $active = Unit::create(['nama' => 'Kebun Aksi Aktif', 'kode' => 'Kebun', 'wilayah' => 'Wilayah Kalimantan Barat', 'is_active' => true, 'urutan' => 1]);
        $inactive = Unit::create(['nama' => 'Kebun Aksi Nonaktif', 'kode' => 'Kebun', 'wilayah' => 'Wilayah Kalimantan Barat', 'is_active' => false, 'urutan' => 2]);

        $response = $this->actingAs($this->admin)->get(route('admin.master.units.index'));
        $response->assertOk()->assertSee('Tambah Unit Kerja')->assertSee('master-action-title', false);

        $actions = collect($this->dispatchedActions($response->getContent()))->keyBy(fn ($a) => $a['method'].' '.$a['url']);

        $this->assertTrue($actions->has('POST '.route('admin.master.units.store')));
        $update = $actions->get('PUT '.route('admin.master.units.update', $active));
        $this->assertSame('Ubah Unit Kerja', $update['title']);
        $this->assertContains('reason', array_column($update['fields'], 'name'));

        $deactivate = $actions->get('POST '.route('admin.master.units.toggle', $active));
        $this->assertSame('Nonaktifkan Unit Kerja', $deactivate['title']);
        $reason = collect($deactivate['fields'])->firstWhere('name', 'reason');
        $this->assertTrue($reason['required']);

        $activate = $actions->get('POST '.route('admin.master.units.toggle', $inactive));
        $this->assertSame('Aktifkan Unit Kerja', $activate['title']);
        $this->assertSame([], $activate['fields']);

        $delete = $actions->get('DELETE '.route('admin.master.units.destroy', $active));
        $this->assertTrue(collect($delete['fields'])->firstWhere('name', 'reason')['required']);
    }

    public function test_positions_index_offers_management_actions_for_each_position(): void
    {
        $position = Position::create(['nama' => 'Asisten Aksi Uji', 'level' => 'Karpim', 'rm_level' => 'RM-3', 'is_active' => true]);

        $response = $this->actingAs($this->admin)->get(route('admin.master.positions.index'));
        $response->assertOk()->assertSee('Tambah Jabatan');

        $actions = collect($this->dispatchedActions($response->getContent()))->keyBy(fn ($a) => $a['method'].' '.$a['url']);

        $this->assertTrue($actions->has('POST '.route('admin.master.positions.store')));
        $update = $actions->get('PUT '.route('admin.master.positions.update', $position));
        $this->assertContains('rm_level', array_column($update['fields'], 'name'));
        $this->assertTrue($actions->has('POST '.route('admin.master.positions.toggle', $position)));
        $this->assertTrue(collect($actions->get('DELETE '.route('admin.master.positions.destroy', $position))['fields'])->firstWhere('name', 'reason')['required']);
    }

    public function test_master_pages_show_flash_messages_from_blocked_actions(): void
    {
        $unit = Unit::create(['nama' => 'Kebun Pesan Uji', 'kode' => 'Kebun', 'wilayah' => 'Wilayah Kalimantan Barat', 'is_active' => true, 'urutan' => 97]);
        Employee::factory()->create(['unit_kerja' => $unit->nama]);

        $this->actingAs($this->admin)
            ->from(route('admin.master.units.index'))
            ->post(route('admin.master.units.toggle', $unit), ['reason' => 'Unit digabung ke unit lain'])
            ->assertRedirect(route('admin.master.units.index'));

        $this->actingAs($this->admin)->get(route('admin.master.units.index'))
            ->assertSee('1 pegawai', false)
            ->assertSee('Mutasi');
    }

    public function test_update_without_is_active_keeps_unit_and_position_active(): void
    {
        $unit = Unit::create(['nama' => 'Kebun Tetap Aktif', 'kode' => 'Kebun', 'wilayah' => 'Wilayah Kalimantan Barat', 'is_active' => true, 'urutan' => 98]);
        $position = Position::create(['nama' => 'Asisten Tetap Aktif', 'level' => 'Karpim', 'rm_level' => 'RM-3', 'is_active' => true]);

        $this->actingAs($this->admin)->put(route('admin.master.units.update', $unit), [
            'nama' => 'Kebun Tetap Aktif Baru', 'kode' => 'Kebun', 'wilayah' => 'Wilayah Kalimantan Barat', 'urutan' => 98,
            'reason' => 'Penyesuaian nama unit resmi',
        ])->assertRedirect(route('admin.master.units.index'));

        $this->actingAs($this->admin)->put(route('admin.master.positions.update', $position), [
            'nama' => 'Asisten Tetap Aktif Baru', 'level' => 'Karpim', 'rm_level' => 'RM-3',
            'reason' => 'Penyesuaian nomenklatur jabatan',
        ])->assertRedirect(route('admin.master.positions.index'));

        $this->assertTrue($unit->fresh()->is_active);
        $this->assertTrue($position->fresh()->is_active);
        $this->assertSame(['unit.renamed', 'position.renamed'], MasterAuditLog::orderBy('id')->pluck('action')->all());
    }

    public function test_edit_dialog_for_a_pool_position_keeps_its_current_rm_band(): void
    {
        $pool = Position::create(['nama' => 'Advisor RM-4', 'level' => 'Karpim', 'rm_level' => 'RM-4', 'is_active' => true]);

        $response = $this->actingAs($this->admin)->get(route('admin.master.positions.index'));

        $actions = collect($this->dispatchedActions($response->getContent()))->keyBy(fn ($a) => $a['method'].' '.$a['url']);
        $rm = collect($actions->get('PUT '.route('admin.master.positions.update', $pool))['fields'])->firstWhere('name', 'rm_level');

        $this->assertSame('RM-4', $rm['value']);
        $this->assertContains('RM-4', array_column($rm['options'], 'value'));
    }

    public function test_master_pages_share_the_section_navigation_with_their_own_link_marked_current(): void
    {
        $pages = [
            'admin.master.formasi.index' => 'Formasi',
            'admin.master.units.index' => 'Unit Kerja',
            'admin.master.positions.index' => 'Jabatan & Posisi',
            'admin.master.riwayat.index' => 'Riwayat Perubahan',
        ];

        foreach ($pages as $route => $currentLabel) {
            $html = $this->actingAs($this->admin)->get(route($route))->assertOk()->getContent();

            $this->assertSame(1, substr_count($html, 'aria-label="Bagian Master Data"'), "{$route}: navigasi seksi harus ada tepat satu.");
            preg_match('/<nav aria-label="Bagian Master Data".*?<\/nav>/s', $html, $nav);
            $links = [];
            preg_match_all('/<a [^>]*href="([^"]+)"[^>]*>(.*?)<\/a>/s', $nav[0], $links, PREG_SET_ORDER);

            $this->assertCount(4, $links, "{$route}: empat bagian harus tersedia.");
            $this->assertSame(
                array_map(fn ($name) => route($name), array_keys($pages)),
                array_map(fn ($link) => html_entity_decode($link[1]), $links),
                "{$route}: urutan bagian harus Formasi, Unit Kerja, Jabatan, Riwayat."
            );

            $current = array_values(array_filter($links, fn ($link) => str_contains($link[0], 'aria-current="page"')));
            $this->assertCount(1, $current, "{$route}: tepat satu tautan bertanda halaman aktif.");
            $this->assertStringContainsString(htmlspecialchars($currentLabel), strip_tags($current[0][2]), "{$route}: tautan aktif harus {$currentLabel}.");
        }
    }

    public function test_sidebar_has_a_single_master_data_entry(): void
    {
        $html = $this->actingAs($this->admin)->get(route('admin.master.units.index'))->assertOk()->getContent();

        $this->assertStringContainsString('title="Master Formasi"', $html);
        foreach (['Master Unit Kerja', 'Master Jabatan', 'Formasi Unit Kerja', 'Riwayat Perubahan Master'] as $oldTitle) {
            $this->assertStringNotContainsString('title="'.$oldTitle.'"', $html, "Sidebar tidak boleh lagi memuat entri terpisah '{$oldTitle}'.");
        }
    }

    public function test_ajax_json_quick_store_position(): void
    {
        $posName = 'Asisten AJAX Quick Add Test';
        Position::where('nama', $posName)->delete();

        $response = $this->actingAs($this->admin)->postJson(route('admin.master.positions.store'), [
            'nama' => $posName,
            'bidang' => 'TAN',
            'level' => 'Karpim',
            'rm_level' => 'RM-3',
        ]);

        $response->assertStatus(201);
        $response->assertJson([
            'success' => true,
            'position' => [
                'nama' => $posName,
                'bidang' => 'TAN',
                'level' => 'Karpim',
                'rm_level' => 'RM-3',
            ],
        ]);

        $this->assertDatabaseHas('positions', ['nama' => $posName]);
        Position::where('nama', $posName)->delete();
    }
}
