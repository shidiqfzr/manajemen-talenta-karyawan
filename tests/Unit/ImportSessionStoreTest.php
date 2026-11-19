<?php

namespace Tests\Unit;

use App\Models\User;
use App\Services\Import\ImportSessionStore;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * SUT: App\Services\Import\ImportSessionStore, satu-satunya pemilik kunci, masa berlaku, kepemilikan, dan enkripsi sesi Impor
 * Data Karyawan di cache (UU PDP No. 27/2022). Nama kunci tidak boleh berubah agar sesi yang sedang berjalan tidak terputus.
 */
class ImportSessionStoreTest extends TestCase
{
    private ImportSessionStore $store;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        $this->store = new ImportSessionStore;
    }

    private function user(int $id): User
    {
        return (new User)->forceFill(['id' => $id]);
    }

    public function test_only_the_uploader_owns_a_session_and_an_expired_owner_is_let_through(): void
    {
        $token = 'tok-owner';
        $this->assertTrue($this->store->isOwnedBy($token, $this->user(7)), 'Token tanpa pemilik (kedaluwarsa) diteruskan agar pesan sesi berakhir tampil.');

        $this->store->claim($token, $this->user(7));

        $this->assertTrue($this->store->isOwnedBy($token, $this->user(7)));
        $this->assertFalse($this->store->isOwnedBy($token, $this->user(8)));
        $this->assertFalse($this->store->isOwnedBy($token, null));
        $this->assertSame(7, Cache::get("import_owner_{$token}"), 'Nama kunci kepemilikan tidak berubah.');
    }

    public function test_entries_with_personal_data_are_stored_encrypted_under_the_unchanged_key_names(): void
    {
        $token = 'tok-sealed';
        $payload = ['summary' => ['sample_rows' => [['nik' => '13009961', 'nama' => 'BUDI SANTOSO']]], 'file_path' => 'imports/x.xlsx'];

        $this->store->putPreview($token, $payload);
        $this->store->putJob($token, ['status' => 'processing']);
        $this->store->putCommitJob($token, ['status' => 'completed']);
        $this->store->putReport($token, ['rows' => $this->store->sealRows([['nik' => '13009961']]), 'failed_rows' => $this->store->sealRows([])]);

        foreach (['preview', 'job', 'commit_job', 'report'] as $kind) {
            $raw = Cache::get("import_{$kind}_{$token}");
            $this->assertIsString($raw, "{$kind} harus tersimpan sebagai muatan terenkripsi");
            $this->assertStringNotContainsString('13009961', $raw);
            $this->assertStringNotContainsString('BUDI SANTOSO', $raw);
        }

        $this->assertSame($payload, $this->store->preview($token));
        $this->assertSame('completed', $this->store->jobStatus($token)['status'], 'Status komit didahulukan dari status pratinjau.');
        $this->assertSame([['nik' => '13009961']], $this->store->report($token)['rows']);
    }

    public function test_unreadable_or_legacy_entries_are_handled_without_breaking_a_running_session(): void
    {
        $token = 'tok-legacy';

        Cache::put("import_preview_{$token}", ['summary' => ['created' => 1]], now()->addMinutes(5));
        $this->assertSame(['summary' => ['created' => 1]], $this->store->preview($token), 'Larik polos (cache lama) tetap terbaca.');

        Cache::put("import_preview_{$token}", 'bukan-muatan-terenkripsi', now()->addMinutes(5));
        $this->assertNull($this->store->preview($token), 'Isi rusak diperlakukan sebagai sesi berakhir.');

        $this->assertNull($this->store->preview('tidak-ada'));
        $this->assertNull($this->store->report('tidak-ada'));
        $this->assertNull($this->store->failedRows('tidak-ada'));
    }

    public function test_failed_rows_are_read_from_the_dedicated_entry_then_the_report_then_the_preview(): void
    {
        $rows = [['row' => 3, 'nik' => '13009961']];

        $this->store->putPreview('tok-a', ['failed_rows' => $this->store->sealRows($rows)]);
        $this->assertSame($rows, $this->store->failedRows('tok-a'));
        $this->assertFalse($this->store->hasFailedRows('tok-a'));

        $this->store->putFailedRows('tok-a', [['row' => 9]]);
        $this->assertTrue($this->store->hasFailedRows('tok-a'));
        $this->assertSame([['row' => 9]], $this->store->failedRows('tok-a'), 'Entri baris gagal khusus didahulukan.');

        $this->store->putReport('tok-b', ['rows' => $this->store->sealRows([]), 'failed_rows' => $this->store->sealRows($rows)]);
        $this->assertSame($rows, $this->store->failedRows('tok-b'));
    }

    public function test_a_token_can_only_begin_one_commit_until_it_is_released(): void
    {
        $token = 'tok-commit';

        $this->assertTrue($this->store->beginCommit($token), 'Komit pertama diizinkan.');
        $this->assertFalse($this->store->beginCommit($token), 'Komit kedua pada token yang sama ditolak selama yang pertama berjalan.');
        $this->assertTrue(Cache::has("import_commit_started_{$token}"), 'Nama kunci pengaman tidak berubah.');

        $this->store->releaseCommit($token);
        $this->assertTrue($this->store->beginCommit($token), 'Setelah dilepas (komit gagal), pengunggah dapat mencoba lagi.');
    }

    public function test_the_browser_summary_carries_counts_only(): void
    {
        $summary = ['created' => 2, 'updated' => 1, 'failed_count' => 1, 'sample_rows' => [['nik' => '13009961']], 'valid_rows' => [['nik' => '13009961']], 'errors' => [['row' => 4]]];

        $this->assertSame(['created' => 2, 'updated' => 1, 'failed_count' => 1], $this->store->browserSummary($summary));
    }

    public function test_forgetting_the_preview_leaves_the_rest_of_the_session_in_place(): void
    {
        $token = 'tok-forget';
        $this->store->putPreview($token, ['summary' => []]);
        $this->store->putJob($token, ['status' => 'completed']);

        $this->store->forgetPreview($token);

        $this->assertNull($this->store->preview($token));
        $this->assertTrue($this->store->hasJob($token));
        $this->assertFalse($this->store->hasCommitJob($token));
    }
}
