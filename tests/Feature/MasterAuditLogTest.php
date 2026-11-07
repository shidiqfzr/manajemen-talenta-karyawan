<?php

namespace Tests\Feature;

use App\Livewire\Admin\Master\AuditHistory;
use App\Models\Employee;
use App\Models\MasterAuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use LogicException;
use Tests\TestCase;

class MasterAuditLogTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin']);
    }

    private function log(): MasterAuditLog
    {
        return MasterAuditLog::record(
            $this->admin,
            'seat.added',
            'seat',
            7,
            'Kebun Tajati — Manajer',
            null,
            ['kuota' => 1],
            null
        );
    }

    public function test_history_page_is_admin_only(): void
    {
        $user = User::factory()->create(['role' => 'user']);

        $this->actingAs($user)->get(route('admin.master.riwayat.index'))->assertForbidden();
        $this->actingAs($this->admin)->get(route('admin.master.riwayat.index'))->assertOk()->assertSee('Riwayat Perubahan Master');
    }

    public function test_history_lists_newest_first_and_paginates_by_25(): void
    {
        foreach (range(1, 30) as $i) {
            MasterAuditLog::record($this->admin, 'seat.added', 'seat', $i, "Unit Uji — Jabatan {$i}", null, ['kuota' => 1]);
        }

        $first = $this->actingAs($this->admin)->get(route('admin.master.riwayat.index'));
        $first->assertSee('Jabatan 30')->assertSee('Jabatan 6')->assertDontSee('Jabatan 5');

        $second = $this->actingAs($this->admin)->get(route('admin.master.riwayat.index', ['page' => 2]));
        $second->assertSee('Jabatan 5')->assertDontSee('Jabatan 30');
    }

    public function test_history_filters_by_entity_type_user_and_date_range(): void
    {
        $other = User::factory()->create(['role' => 'admin', 'name' => 'Admin Lain']);
        MasterAuditLog::record($this->admin, 'seat.added', 'seat', 1, 'Unit A — Seat Satu');
        MasterAuditLog::record($this->admin, 'unit.created', 'unit', 2, 'Unit Baru Dua');
        MasterAuditLog::record($other, 'position.created', 'position', 3, 'Jabatan Tiga');

        $old = MasterAuditLog::record($this->admin, 'unit.deleted', 'unit', 4, 'Unit Lama Empat');
        DB::table('master_audit_logs')->where('id', $old->id)->update(['created_at' => '2020-01-15 10:00:00']);

        $byType = $this->actingAs($this->admin)->get(route('admin.master.riwayat.index', ['entity_type' => 'seat']));
        $byType->assertSee('Seat Satu')->assertDontSee('Unit Baru Dua')->assertDontSee('Jabatan Tiga');

        $byUser = $this->actingAs($this->admin)->get(route('admin.master.riwayat.index', ['user_id' => $other->id]));
        $byUser->assertSee('Jabatan Tiga')->assertDontSee('Seat Satu');

        $byDate = $this->actingAs($this->admin)->get(route('admin.master.riwayat.index', ['from' => '2020-01-01', 'to' => '2020-01-31']));
        $byDate->assertSee('Unit Lama Empat')->assertDontSee('Seat Satu');
    }

    public function test_history_hides_view_only_access_entries_but_keeps_them_stored_and_shows_exports(): void
    {
        MasterAuditLog::record($this->admin, 'mpp_access.view_personnel', 'mpp_access', null, 'Rincian Lihat MPP 2026-11', null, ['tahun' => 2026]);
        MasterAuditLog::record($this->admin, 'seat_access.view_occupants', 'seat_access', 5, 'Pemegang Lihat Formasi');
        MasterAuditLog::record($this->admin, 'mpp_access.export_matrix', 'mpp_access', null, 'Matriks Unduh MPP 2026-11', null, ['tahun' => 2026]);
        MasterAuditLog::record($this->admin, 'seat.added', 'seat', 1, 'Unit A — Seat Satu');

        Livewire::actingAs($this->admin)->test(AuditHistory::class)
            ->assertSee('Seat Satu')
            ->assertSee('Matriks Unduh MPP')
            ->assertDontSee('Rincian Lihat MPP')
            ->assertDontSee('Pemegang Lihat Formasi');

        $this->assertSame(2, MasterAuditLog::whereIn('action', ['mpp_access.view_personnel', 'seat_access.view_occupants'])->count());
    }

    public function test_history_shows_who_what_and_reason_but_no_employee_data(): void
    {
        $employee = Employee::factory()->create(['nama' => 'Pegawai Rahasia Uji']);
        MasterAuditLog::record(
            $this->admin, 'seat.removed', 'seat', 9, 'Kebun Uji — Manajer',
            ['kuota' => 1, 'bidang' => 'TAN', 'filled' => 0], null, 'Kursi dihapus karena restrukturisasi'
        );

        $response = $this->actingAs($this->admin)->get(route('admin.master.riwayat.index'));

        $response->assertSee($this->admin->name)
            ->assertSee('Kursi dihapus')
            ->assertSee('Kebun Uji — Manajer')
            ->assertSee('Kursi dihapus karena restrukturisasi')
            ->assertDontSee($employee->nama)
            ->assertDontSee($employee->nik);
    }

    public function test_history_page_renders_old_and_new_values_in_readable_form(): void
    {
        MasterAuditLog::record($this->admin, 'seat.updated', 'seat', 5, 'Kebun Uji — Asisten', ['kuota' => 1, 'bidang' => null], ['kuota' => 3, 'bidang' => 'TAN']);
        MasterAuditLog::record($this->admin, 'unit.deactivated', 'unit', 6, 'Kebun Mati', ['is_active' => true], ['is_active' => false]);

        $response = $this->actingAs($this->admin)->get(route('admin.master.riwayat.index'));

        $response->assertSee('Kuota: 1 → 3')->assertSee('Bidang: - → TAN')->assertSee('Status: Aktif → Nonaktif');
    }

    public function test_change_summary_renders_data_table_audit_details_readably(): void
    {
        $plain = MasterAuditLog::record($this->admin, 'data_table.export', 'data_table', null, 'Riwayat Jabatan', null, [
            'jumlah_baris' => 12, 'filter' => [], 'pencarian' => true,
        ]);
        $filtered = MasterAuditLog::record($this->admin, 'data_table.bulk', 'data_table', null, 'Riwayat Jabatan', null, [
            'aksi' => 'nonaktifkan', 'jumlah_baris' => 3, 'filter' => ['bidang' => 'KEU'], 'pencarian' => false,
        ]);

        $this->assertSame(
            ['Jumlah Baris: - → 12', 'Filter: - → -', 'Pencarian: - → ya'],
            $plain->changeSummary(),
        );
        $this->assertSame(
            ['Aksi: - → nonaktifkan', 'Jumlah Baris: - → 3', 'Filter: - → Bidang=KEU', 'Pencarian: - → tidak'],
            $filtered->changeSummary(),
        );
    }

    public function test_history_page_renders_with_data_table_audit_rows(): void
    {
        MasterAuditLog::record($this->admin, 'data_table.export', 'data_table', null, 'Riwayat Jabatan', null, [
            'jumlah_baris' => 12, 'filter' => [], 'pencarian' => false,
        ]);
        MasterAuditLog::record($this->admin, 'data_table.bulk', 'data_table', null, 'Riwayat Jabatan', null, [
            'aksi' => 'nonaktifkan', 'jumlah_baris' => 3, 'filter' => ['bidang' => 'KEU'], 'pencarian' => true,
        ]);

        $this->actingAs($this->admin)->get(route('admin.master.riwayat.index'))
            ->assertOk()
            ->assertSee('Tabel data diunduh')
            ->assertSee('Aksi massal pada tabel data')
            ->assertSee('Tabel Data')
            ->assertSee('Filter: - → Bidang=KEU')
            ->assertDontSee('Array');
    }

    public function test_record_stores_the_audit_row_with_a_user_snapshot(): void
    {
        $log = $this->log()->fresh();

        $this->assertSame($this->admin->id, $log->user_id);
        $this->assertSame($this->admin->name, $log->user_name);
        $this->assertSame('seat.added', $log->action);
        $this->assertSame('seat', $log->entity_type);
        $this->assertSame(7, $log->entity_id);
        $this->assertSame('Kebun Tajati — Manajer', $log->entity_label);
        $this->assertSame(['kuota' => 1], $log->new_values);
        $this->assertNull($log->old_values);
    }

    public function test_audit_rows_cannot_be_updated(): void
    {
        $log = $this->log();

        $this->expectException(LogicException::class);
        $log->update(['reason' => 'x']);
    }

    public function test_audit_rows_cannot_be_deleted(): void
    {
        $log = $this->log();

        try {
            $log->delete();
            $this->fail('Menghapus riwayat seharusnya ditolak.');
        } catch (LogicException) {
            $this->assertDatabaseHas('master_audit_logs', ['id' => $log->id]);
        }
    }

    public function test_deleting_the_user_keeps_the_row_with_its_name_snapshot(): void
    {
        $log = $this->log();
        $name = $this->admin->name;

        $this->admin->delete();

        $log = $log->fresh();
        $this->assertNull($log->user_id);
        $this->assertSame($name, $log->user_name);
    }

    public function test_history_component_searches_resets_and_sorts(): void
    {
        MasterAuditLog::record($this->admin, 'seat.added', 'seat', 1, 'Unit A — Kursi Cari', null, ['kuota' => 1], 'Alasan khusus ABC');
        MasterAuditLog::record($this->admin, 'unit.created', 'unit', 2, 'Unit Lain Dua');

        Livewire::actingAs($this->admin)->test(AuditHistory::class)
            ->set('search', 'ABC')
            ->assertSee('Kursi Cari')->assertDontSee('Unit Lain Dua')
            ->call('resetFilters')
            ->assertSet('search', '')
            ->assertSee('Unit Lain Dua')
            ->call('sortBy', 'entity_label')
            ->assertSet('sortField', 'entity_label')->assertSet('sortDirection', 'asc')
            ->call('sortBy', 'tidak_ada')
            ->assertSet('sortField', 'entity_label');
    }

    public function test_history_component_rejects_non_admin_and_ignores_malformed_filters(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        Livewire::actingAs($user)->test(AuditHistory::class)->assertForbidden();

        $this->log();
        Livewire::actingAs($this->admin)->test(AuditHistory::class)
            ->set('from', 'bukan-tanggal')->set('userId', 'x')->set('entityType', 'sembarang')
            ->assertSee('Kebun Tajati — Manajer');
    }

    public function test_history_keeps_its_url_keys(): void
    {
        $other = User::factory()->create(['role' => 'admin', 'name' => 'Admin Lain']);

        foreach (range(1, 55) as $i) {
            MasterAuditLog::record($this->admin, 'seat.added', 'seat', $i, sprintf('Seat Nomor %02d', $i));
        }
        MasterAuditLog::record($this->admin, 'unit.created', 'unit', 100, 'Unit Bukan Seat');
        MasterAuditLog::record($other, 'seat.added', 'seat', 101, 'Seat Dari Lain');

        $response = $this->actingAs($this->admin)->get(route('admin.master.riwayat.index', [
            'entity_type' => 'seat',
            'user_id' => $this->admin->id,
            'from' => '2000-01-01',
            'to' => '2099-12-31',
            'sort' => 'entity_label',
            'direction' => 'asc',
            'per_page' => 50,
            'page' => 2,
        ]));

        $response->assertOk()
            ->assertSee('Seat Nomor 51')->assertSee('Seat Nomor 55')
            ->assertDontSee('Seat Nomor 50')
            ->assertDontSee('Unit Bukan Seat')->assertDontSee('Seat Dari Lain');
    }

    public function test_history_ignores_tampered_url_state(): void
    {
        foreach (range(1, 30) as $i) {
            MasterAuditLog::record($this->admin, 'seat.added', 'seat', $i, "Unit Uji — Jabatan {$i}", null, ['kuota' => 1]);
        }

        $response = $this->actingAs($this->admin)->get(route('admin.master.riwayat.index', [
            'sort' => 'password',
            'direction' => 'sideways',
            'per_page' => 999,
            'entity_type' => 'zzz',
            'user_id' => 'abc',
            'from' => '2026-02-30',
        ]));

        $response->assertOk()
            ->assertSee('Jabatan 30')->assertSee('Jabatan 6')->assertDontSee('Jabatan 5')
            ->assertDontSee('Filter Aktif:');
    }

    public function test_history_date_range_includes_the_whole_end_day(): void
    {
        $last = MasterAuditLog::record($this->admin, 'unit.created', 'unit', 1, 'Hari Akhir Audit');
        $next = MasterAuditLog::record($this->admin, 'unit.created', 'unit', 2, 'Hari Esok Audit');
        DB::table('master_audit_logs')->where('id', $last->id)->update(['created_at' => '2026-01-31 23:59:00']);
        DB::table('master_audit_logs')->where('id', $next->id)->update(['created_at' => '2026-02-01 00:00:00']);

        $this->actingAs($this->admin)
            ->get(route('admin.master.riwayat.index', ['from' => '2026-01-01', 'to' => '2026-01-31']))
            ->assertSee('Hari Akhir Audit')
            ->assertDontSee('Hari Esok Audit');
    }

    public function test_history_from_after_to_shows_the_filtered_empty_state(): void
    {
        $this->log();

        $this->actingAs($this->admin)
            ->get(route('admin.master.riwayat.index', ['from' => '2020-02-01', 'to' => '2020-01-01']))
            ->assertOk()
            // The empty-state button comes after its message; the toolbar's own Reset button comes before it.
            ->assertSeeInOrder(['Tidak ada data yang sesuai dengan pencarian atau filter.', 'Reset filter']);
    }

    public function test_history_search_treats_wildcards_literally(): void
    {
        MasterAuditLog::record($this->admin, 'seat.added', 'seat', 1, 'Seat_1');
        MasterAuditLog::record($this->admin, 'seat.added', 'seat', 2, 'SeatX1');

        $component = Livewire::actingAs($this->admin)->test(AuditHistory::class)->set('search', 'Seat_1');

        // Assert on the listed rows, not the page text: the search chip also prints the term.
        $this->assertSame(['Seat_1'], $component->instance()->rows->pluck('entity_label')->all());
    }

    public function test_history_shows_active_filter_chips(): void
    {
        MasterAuditLog::record($this->admin, 'seat.added', 'seat', 1, 'Unit A — Seat Satu');

        $this->actingAs($this->admin)
            ->get(route('admin.master.riwayat.index', ['entity_type' => 'seat']))
            ->assertSee('Filter Aktif:')
            ->assertSee('Jenis Data: Formasi Jabatan');
    }

    public function test_history_announces_the_result_count(): void
    {
        foreach (range(1, 3) as $i) {
            MasterAuditLog::record($this->admin, 'unit.created', 'unit', $i, "Unit Hitung {$i}");
        }

        $this->actingAs($this->admin)->get(route('admin.master.riwayat.index'))
            ->assertSee('3 catatan ditemukan')
            ->assertSeeHtml('role="status"');
    }

    public function test_pages_use_the_shared_module_header_with_the_current_page_in_the_breadcrumb(): void
    {
        $html = $this->actingAs($this->admin)->get(route('admin.master.riwayat.index'))->getContent();

        $this->assertMatchesRegularExpression('/aria-label="Breadcrumb".*Core HR.*Master Formasi.*aria-current="page">Riwayat Perubahan</s', $html);
    }
}
