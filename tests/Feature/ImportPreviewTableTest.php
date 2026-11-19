<?php

namespace Tests\Feature;

use App\Livewire\Admin\Employees\ImportPreviewTable;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * SUT: App\Livewire\Admin\Employees\ImportPreviewTable (tabel pratinjau Impor Data Karyawan pada komponen tabel bersama).
 * Data pratinjau dibaca dari cache sesi impor milik pengunggah; di sini disiapkan langsung sebagai larik polos (cache lama),
 * yang tetap dibaca layanan, supaya jumlah dan isi baris dapat diatur tepat.
 */
class ImportPreviewTableTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($this->admin);
        $this->token = (string) Str::uuid();
    }

    /** @param  array<string, mixed>  $summary */
    private function cacheSession(array $summary, ?string $token = null, ?int $owner = null): string
    {
        $token ??= $this->token;
        Cache::put("import_owner_{$token}", $owner ?? $this->admin->id, now()->addHour());
        Cache::put("import_preview_{$token}", ['summary' => $summary], now()->addHour());

        return $token;
    }

    /** @return array<string, mixed> */
    private function valid(int $i, string $action = 'CREATE', array $extra = []): array
    {
        return $extra + [
            'action' => $action, 'status' => $action, 'nik' => sprintf('1300%04d', $i), 'nama' => "KARYAWAN {$i}",
            'jabatan' => 'Asisten Afdeling', 'unit_kerja' => 'Kebun Tajati', 'level' => 'Karpim', 'golongan' => 'IIIA/00', 'rm_level' => 'RM-3',
            'diff' => [], 'warnings' => [],
        ];
    }

    private function load(string $token): Testable
    {
        return Livewire::test(ImportPreviewTable::class)->dispatch('import-preview-loaded', token: $token, tab: 'all');
    }

    public function test_it_shows_an_empty_state_until_a_preview_is_loaded(): void
    {
        Livewire::test(ImportPreviewTable::class)
            ->assertSee('Belum ada pratinjau')
            ->assertSet('token', '');
    }

    public function test_it_lists_the_valid_rows_with_masked_nik_per_tab(): void
    {
        $token = $this->cacheSession(['failed_count' => 0, 'sample_rows' => [
            $this->valid(1, 'CREATE'), $this->valid(2, 'UPDATE', ['diff' => [['field' => 'tempat_lahir', 'label' => 'Tempat Lahir', 'old' => 'A', 'new' => 'B']]]), $this->valid(3, 'UNCHANGED'),
        ], 'errors' => []]);

        $component = $this->load($token);
        $html = $component->html();

        $component->assertSee('KARYAWAN 1')->assertSee('KARYAWAN 2')->assertSee('KARYAWAN 3')->assertSee('Semua Data Karyawan');
        $this->assertStringContainsString('*****001', $html, 'NIK disamarkan, tiga karakter terakhir terlihat.');
        $this->assertStringNotContainsString('13000001', $html, 'NIK utuh tidak boleh tampil.');
        $this->assertStringContainsString('open-import-diff', $html, 'Baris perubahan membuka modal rincian.');
        $this->assertStringNotContainsString('13000002', $html, 'Muatan modal juga memakai NIK samaran.');

        $component->dispatch('import-preview-tab', tab: 'created')->assertSee('KARYAWAN 1')->assertDontSee('KARYAWAN 2')->assertSee('Data Karyawan Baru');
        $component->dispatch('import-preview-tab', tab: 'updated')->assertSee('KARYAWAN 2')->assertDontSee('KARYAWAN 1')->assertSee('1 Perubahan');
        $component->dispatch('import-preview-tab', tab: 'unchanged')->assertSee('KARYAWAN 3')->assertDontSee('KARYAWAN 2');
        $component->dispatch('import-preview-tab', tab: 'tidak-ada')->assertSet('tab', 'all');
    }

    public function test_search_matches_the_full_nik_and_names_and_can_be_reset(): void
    {
        $token = $this->cacheSession(['sample_rows' => [$this->valid(1), $this->valid(2), $this->valid(3)], 'errors' => []]);

        $this->load($token)
            ->set('search', '13000002')->assertSee('KARYAWAN 2')->assertDontSee('KARYAWAN 1')
            ->set('search', 'karyawan 3')->assertSee('KARYAWAN 3')->assertDontSee('KARYAWAN 2')
            ->set('search', 'tidak ada yang cocok')->assertSee('Tidak ada baris yang cocok')
            ->call('resetFilters')->assertSet('search', '')->assertSee('KARYAWAN 1');

        $long = $this->load($token)->set('search', str_repeat('a', 300));
        $this->assertSame(100, mb_strlen($long->get('search')));
    }

    public function test_rows_are_paged_on_the_server_with_the_shared_options(): void
    {
        $rows = array_map(fn (int $i) => $this->valid($i), range(1, 60));
        $token = $this->cacheSession(['sample_rows' => $rows, 'errors' => []]);

        $component = $this->load($token)->assertSee('KARYAWAN 25')->assertDontSee('KARYAWAN 26');
        $component->call('gotoPage', 2)->assertSee('KARYAWAN 26')->assertDontSee('KARYAWAN 25');
        $component->set('perPage', 50)->assertSee('KARYAWAN 50')->assertDontSee('KARYAWAN 51');
        $component->set('perPage', 7)->assertSet('perPage', (int) config('hris.pagination.default'));
    }

    public function test_error_rows_show_causes_masked_nik_and_a_correction_download(): void
    {
        $token = $this->cacheSession(['failed_count' => 2, 'sample_rows' => [$this->valid(1)], 'errors' => [
            ['row' => 7, 'nik' => '13005377', 'nama' => 'Y. JAHIN', 'jabatan' => 'Asisten Humas', 'unit_kerja' => 'Bagian Sekretariat & Hukum', 'errors' => ['Tugas Rangkap: unit tidak tertulis.']],
            ['row' => 16, 'nik' => 'Unknown', 'nama' => 'SUMARDI', 'jabatan' => '-', 'unit_kerja' => 'Bagian Tanaman', 'errors' => ['Jabatan karyawan wajib diisi.']],
        ]]);

        $component = $this->load($token)->dispatch('import-preview-tab', tab: 'errors');
        $html = $component->html();

        $component->assertSee('Daftar Baris Terkendala')->assertSee('Y. JAHIN')->assertSee('Tugas Rangkap: unit tidak tertulis.')
            ->assertSee('Posisi tidak terbaca')->assertSee('2 baris terkendala validasi dan tidak diimpor')->assertSee('Unduh Berkas Koreksi');
        $this->assertStringContainsString('*****377', $html);
        $this->assertStringNotContainsString('13005377', $html);
        $this->assertStringContainsString('NIK: Unknown', $html, 'Nilai bukan NIK tidak disamarkan.');
        $this->assertStringContainsString('name="token" value="'.$token.'"', $html, 'Formulir unduhan membawa token sesi.');

        $component->set('search', 'sumardi')->assertSee('SUMARDI')->assertDontSee('Y. JAHIN');
    }

    public function test_a_session_without_errors_says_so(): void
    {
        $token = $this->cacheSession(['failed_count' => 0, 'sample_rows' => [$this->valid(1)], 'errors' => []]);

        $this->load($token)->dispatch('import-preview-tab', tab: 'errors')->assertSee('Tidak ada baris terkendala')->assertDontSee('Unduh Berkas Koreksi');
    }

    public function test_only_the_owner_can_load_a_session_and_the_token_cannot_be_tampered_with(): void
    {
        $other = User::factory()->create(['role' => 'admin']);
        $foreign = $this->cacheSession(['sample_rows' => [$this->valid(1)], 'errors' => []], (string) Str::uuid(), $other->id);

        $this->load($foreign)->assertSet('token', '')->assertDontSee('KARYAWAN 1')->assertSee('Belum ada pratinjau');
        $this->load('bukan-uuid')->assertSet('token', '');
        $this->load((string) Str::uuid())->assertSet('token', '');

        $own = $this->cacheSession(['sample_rows' => [$this->valid(1)], 'errors' => []]);
        $this->expectException(CannotUpdateLockedPropertyException::class);
        $this->load($own)->set('token', $foreign);
    }

    public function test_the_import_permission_is_required_on_every_request(): void
    {
        config(['hris.permissions' => ['mutasi.prepare' => ['admin']]]);

        Livewire::test(ImportPreviewTable::class)->assertForbidden();
    }
}
