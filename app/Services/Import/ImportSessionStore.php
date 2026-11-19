<?php

namespace App\Services\Import;

use App\Models\User;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;

/**
 * Penyimpanan sesi Impor Data Karyawan di cache: satu-satunya tempat yang mengenal kunci, masa berlaku, kepemilikan, dan
 * enkripsi entri sesi (UU PDP No. 27/2022). Ringkasan, status, pratinjau, laporan, dan baris gagal memuat NIK dan nama, sehingga
 * disimpan terenkripsi dan hanya dibaca lewat kelas ini; entri berupa larik polos (cache lama) tetap terbaca, dan entri yang
 * tidak terbaca (kunci aplikasi berubah, isi rusak) diperlakukan sebagai sesi yang sudah berakhir.
 *
 * Nama kunci tidak berubah dari sebelumnya, sehingga sesi yang sedang berjalan tidak terputus.
 */
class ImportSessionStore
{
    /** Masa berlaku sesi pratinjau, status pekerjaan, dan pengaman komit. */
    public const TTL_MINUTES = 60;

    /** Masa berlaku kepemilikan, baris gagal, dan laporan hasil komit. */
    public const RETENTION_HOURS = 2;

    // ------------------------------------------------------------------ kepemilikan

    /** Mencatat pengguna yang mengunggah berkas; hanya dia yang boleh mengakses sesinya. */
    public function claim(string $token, ?User $user): void
    {
        Cache::put($this->key('owner', $token), $user?->id ?? 0, now()->addHours(self::RETENTION_HOURS));
    }

    /**
     * Sesi impor hanya boleh diakses admin yang mengunggah berkasnya. Token tanpa pemilik (kedaluwarsa) dianggap boleh
     * diteruskan agar pesan "sesi berakhir" tetap tampil.
     */
    public function isOwnedBy(string $token, ?User $user): bool
    {
        $owner = Cache::get($this->key('owner', $token));

        return $owner === null || $owner === ($user?->id ?? 0);
    }

    // ------------------------------------------------------------------ entri terenkripsi

    /** @param  array<string, mixed>  $payload */
    public function putJob(string $token, array $payload): void
    {
        $this->putSealed($this->key('job', $token), $payload, now()->addMinutes(self::TTL_MINUTES));
    }

    /** @param  array<string, mixed>  $payload */
    public function putCommitJob(string $token, array $payload): void
    {
        $this->putSealed($this->key('commit_job', $token), $payload, now()->addMinutes(self::TTL_MINUTES));
    }

    /** @param  array<string, mixed>  $payload */
    public function putPreview(string $token, array $payload): void
    {
        $this->putSealed($this->key('preview', $token), $payload, now()->addMinutes(self::TTL_MINUTES));
    }

    /** @param  array<string, mixed>  $payload */
    public function putReport(string $token, array $payload): void
    {
        $this->putSealed($this->key('report', $token), $payload, now()->addHours(self::RETENTION_HOURS));
    }

    /** Pratinjau sesi (ringkasan, jalur berkas, baris gagal), atau null bila sesi berakhir atau tak terbaca. @return array<string, mixed>|null */
    public function preview(string $token): ?array
    {
        return $this->opened($this->key('preview', $token));
    }

    public function forgetPreview(string $token): void
    {
        Cache::forget($this->key('preview', $token));
    }

    /** Status pekerjaan pratinjau (latar belakang) menurut token. @return array<string, mixed>|null */
    public function job(string $token): ?array
    {
        return $this->opened($this->key('job', $token));
    }

    /** Status pekerjaan komit bila ada, selain itu status pekerjaan pratinjau. @return array<string, mixed>|null */
    public function jobStatus(string $token): ?array
    {
        return $this->opened($this->key('commit_job', $token)) ?? $this->opened($this->key('job', $token));
    }

    public function hasCommitJob(string $token): bool
    {
        return Cache::has($this->key('commit_job', $token));
    }

    public function hasJob(string $token): bool
    {
        return Cache::has($this->key('job', $token));
    }

    /** Laporan hasil komit dengan baris valid dan baris gagal sudah terbuka, atau null. @return array<string, mixed>|null */
    public function report(string $token): ?array
    {
        $report = $this->opened($this->key('report', $token));

        if ($report === null) {
            return null;
        }

        $report['rows'] = $this->openRows($report['rows'] ?? []);
        $report['failed_rows'] = $this->openRows($report['failed_rows'] ?? []);

        return $report;
    }

    // ------------------------------------------------------------------ baris gagal

    /** @param  array<int, mixed>  $rows */
    public function putFailedRows(string $token, array $rows): void
    {
        Cache::put($this->key('failed_rows', $token), $this->sealRows($rows), now()->addHours(self::RETENTION_HOURS));
    }

    public function hasFailedRows(string $token): bool
    {
        return Cache::has($this->key('failed_rows', $token));
    }

    /** Baris gagal dari sesi pratinjau maupun pasca-komit, atau null bila tidak ada. @return array<int, mixed>|null */
    public function failedRows(string $token): ?array
    {
        $sealed = Cache::get($this->key('failed_rows', $token))
            ?? $this->opened($this->key('report', $token))['failed_rows']
            ?? $this->opened($this->key('preview', $token))['failed_rows']
            ?? null;

        return $sealed === null ? null : $this->openRows($sealed);
    }

    // ------------------------------------------------------------------ pengaman komit

    /** Satu token hanya boleh dikomit sekali: true bila giliran ini yang pertama, false bila komit sudah berjalan. */
    public function beginCommit(string $token): bool
    {
        return Cache::add($this->key('commit_started', $token), true, now()->addMinutes(self::TTL_MINUTES));
    }

    public function releaseCommit(string $token): void
    {
        Cache::forget($this->key('commit_started', $token));
    }

    // ------------------------------------------------------------------ baris terenkripsi dan ringkasan untuk peramban

    /** Baris impor memuat data pribadi: di cache disimpan terenkripsi. @param  array<int, mixed>  $rows */
    public function sealRows(array $rows): string
    {
        return Crypt::encrypt($rows);
    }

    /** Membuka baris dari cache; nilai larik polos (cache lama) diteruskan apa adanya. @return array<int, mixed> */
    public function openRows(mixed $rows): array
    {
        return is_string($rows) ? Crypt::decrypt($rows) : (array) $rows;
    }

    /**
     * Ringkasan untuk peramban hanya memuat angka (jumlah baru, perubahan, tetap, terkendala, peringatan). Baris karyawan dan
     * baris terkendala (NIK, nama) tetap di server dan dibaca halaman demi halaman oleh tabel pratinjau (ImportPreviewTable),
     * bukan dikirim utuh ke peramban (UU PDP: minimisasi data).
     *
     * @param  array<string, mixed>  $summary
     * @return array<string, mixed>
     */
    public function browserSummary(array $summary): array
    {
        unset($summary['sample_rows'], $summary['valid_rows'], $summary['errors']);

        return $summary;
    }

    // ------------------------------------------------------------------ dalam

    /** Nama kunci cache sesi (tidak berubah dari sebelumnya). */
    private function key(string $kind, string $token): string
    {
        return "import_{$kind}_{$token}";
    }

    /** @param  array<string, mixed>  $payload */
    private function putSealed(string $key, array $payload, mixed $ttl): void
    {
        Cache::put($key, Crypt::encrypt($payload), $ttl);
    }

    /** @return array<string, mixed>|null */
    private function opened(string $key): ?array
    {
        $value = Cache::get($key);
        if ($value === null) {
            return null;
        }
        if (! is_string($value)) {
            return (array) $value;
        }

        try {
            return (array) Crypt::decrypt($value);
        } catch (DecryptException) {
            // Kunci aplikasi berubah atau isi rusak: diperlakukan sebagai sesi yang sudah berakhir.
            return null;
        }
    }
}
