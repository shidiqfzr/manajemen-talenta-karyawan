<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Riwayat perubahan master unit, jabatan, dan formasi jabatan (SECURITY.md §5).
 * Append-only: baris tidak dapat diubah atau dihapus melalui aplikasi.
 */
class MasterAuditLog extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'master_audit_logs';

    protected $fillable = [
        'user_id',
        'user_name',
        'action',
        'entity_type',
        'entity_id',
        'entity_label',
        'old_values',
        'new_values',
        'reason',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'created_at' => 'datetime',
    ];

    public const ACTION_LABELS = [
        'seat.added' => 'Formasi jabatan ditambahkan',
        'seat.updated' => 'Formasi jabatan diubah',
        'seat.removed' => 'Formasi jabatan dihapus',
        'unit.created' => 'Unit dibuat',
        'unit.updated' => 'Unit diubah',
        'unit.renamed' => 'Nama unit diubah',
        'unit.activated' => 'Unit diaktifkan',
        'unit.deactivated' => 'Unit dinonaktifkan',
        'unit.deleted' => 'Unit dihapus',
        'position.created' => 'Jabatan dibuat',
        'position.updated' => 'Jabatan diubah',
        'position.renamed' => 'Nama jabatan diubah',
        'position.activated' => 'Jabatan diaktifkan',
        'position.deactivated' => 'Jabatan dinonaktifkan',
        'position.deleted' => 'Jabatan dihapus',
        'pool.opened' => 'Jabatan penampungan dibuka pada unit',
        'pool.closed' => 'Jabatan penampungan ditutup pada unit',
        'mpp_supply.created' => 'Rencana pasokan MPP dibuat',
        'mpp_supply.updated' => 'Rencana pasokan MPP diubah',
        'mpp_period.captured' => 'Draf periode MPP direkam',
        'mpp_period.closed' => 'Periode MPP ditutup',
        'mpp_period.reopened' => 'Periode MPP dibuka kembali',
        'evaluation.created' => 'Penilaian karyawan ditambahkan',
        'evaluation.updated' => 'Penilaian karyawan diubah',
        'evaluation.deleted' => 'Penilaian karyawan dihapus',
        'training.created' => 'Pelatihan ditambahkan',
        'training.updated' => 'Pelatihan diubah',
        'training.deleted' => 'Pelatihan dihapus',
        'employee.separated' => 'Karyawan diberhentikan dari jabatan',
        'employee.separation_sk_recorded' => 'SK pemberhentian karyawan dicatat',
        'mpp_access.view_personnel' => 'Rincian personil MPP dibuka',
        'mpp_access.export_personnel' => 'Rincian personil MPP diunduh',
        'mpp_access.export_matrix' => 'Matriks MPP diunduh',
        'seat_access.view_occupants' => 'Pemegang jabatan formasi dibuka',
        'data_table.bulk' => 'Aksi massal pada tabel data',
        'data_table.export' => 'Tabel data diunduh',
    ];

    public const ENTITY_LABELS = [
        'unit' => 'Unit Kerja',
        'position' => 'Jabatan',
        'seat' => 'Formasi Jabatan',
        'pool_seat' => 'Jabatan Penampungan',
        'employee' => 'Karyawan',
        'evaluation' => 'Penilaian Karyawan',
        'training' => 'Pelatihan',
        'mpp_supply' => 'Rencana Pasokan MPP',
        'mpp_period' => 'Periode MPP',
        'mpp_access' => 'Akses Data MPP',
        'seat_access' => 'Akses Data Formasi',
        'data_table' => 'Tabel Data',
    ];

    /** Istilah bisnis untuk kolom yang tercatat pada nilai lama/baru. */
    public const FIELD_LABELS = [
        'nama' => 'Nama',
        'kode' => 'Kode',
        'wilayah' => 'Wilayah',
        'urutan' => 'Urutan',
        'is_active' => 'Status',
        'level' => 'Strata',
        'rm_level' => 'RM Band',
        'bidang' => 'Bidang',
        'kuota' => 'Kuota',
        'filled' => 'Terisi',
        'jumlah' => 'Jumlah',
        'status' => 'Status Periode',
        'revision' => 'Revisi',
        'indikator' => 'Indikator',
        'unit' => 'Unit Kerja',
        'jabatan' => 'Jabatan',
        'tahun' => 'Tahun',
        'posisi_per' => 'Posisi per',
        'jumlah_baris' => 'Jumlah Baris',
        'aksi' => 'Aksi',
        'filter' => 'Filter',
        'pencarian' => 'Pencarian',
        'periode' => 'Periode',
        'revisi' => 'Revisi',
        'alasan' => 'Alasan',
        'tanggal_berhenti' => 'Tanggal Berhenti',
        'nomor_sk' => 'Nomor SK',
        'tanggal_sk' => 'Tanggal SK',
        'kolom_diubah' => 'Kolom Diubah',
        'judul' => 'Judul Pelatihan',
        'jenis' => 'Jenis Pelatihan',
        'metode' => 'Metode',
        'tanggal_mulai' => 'Tanggal Mulai',
        'tanggal_akhir' => 'Tanggal Akhir',
        'biaya_tiket_peserta' => 'Biaya Tiket Peserta',
        'biaya_pelatihan' => 'Biaya Pelatihan',
        'penyelenggara' => 'Penyelenggara',
        'nomor_surat' => 'Nomor Surat',
        'tanggal_surat' => 'Tanggal Surat',
        'keterangan' => 'Keterangan',
        'bidang_pelatihan' => 'Bidang Pelatihan',
        'jam_belajar_per_hari' => 'Jam Belajar per Hari',
        'nilai_kepemimpinan' => 'Nilai Kepemimpinan',
        'nilai_perilaku_budaya' => 'Nilai Perilaku Budaya',
        'nilai_pengalaman_teknis' => 'Nilai Pengalaman Teknis',
        'nilai_kematangan_pribadi' => 'Nilai Kematangan Pribadi',
        'skor_smkbk_9box' => 'Skor SMK-BK',
        'skor_cli_9box' => 'Skor CLI',
        'kategori_9box' => 'Kategori 9-Box',
        'bidang_tugas' => 'Bidang Tugas',
        'lembaga_asesmen' => 'Lembaga Asesmen',
        'tanggal_pelaksanaan_asesmen' => 'Tanggal Pelaksanaan Asesmen',
        'hasil_skor_asesmen' => 'Hasil Skor Asesmen',
        'kategori_asesmen' => 'Kategori Asesmen',
        'keterangan_asesmen' => 'Keterangan Asesmen',
        'expired_asesmen' => 'Masa Berlaku Asesmen',
    ];

    /**
     * Pembukaan layar yang hanya membaca data (mis. rincian personil MPP, pemegang jabatan formasi) tetap
     * tersimpan sebagai jejak akses (SECURITY.md, UU PDP), tetapi bukan perubahan data sehingga tidak
     * ditampilkan pada layar Riwayat Perubahan.
     */
    public function scopeExcludingViewAccess(Builder $query): Builder
    {
        return $query->whereRaw("action not like '%!_access.view!_%' escape '!'");
    }

    public static function actionLabel(string $action): string
    {
        return self::ACTION_LABELS[$action] ?? $action;
    }

    /**
     * Ringkasan perubahan dengan istilah bisnis, mis. "Kuota: 1 → 3".
     *
     * @return list<string>
     */
    public function changeSummary(): array
    {
        $old = $this->old_values ?? [];
        $new = $this->new_values ?? [];
        $keys = array_values(array_unique([...array_keys($old), ...array_keys($new)]));

        return array_map(function (string $key) use ($old, $new): string {
            $show = fn (mixed $value): string => match (true) {
                $value === null => '-',
                $key === 'is_active' => $value ? 'Aktif' : 'Nonaktif',
                default => self::displayValue($value),
            };

            return (self::FIELD_LABELS[$key] ?? $key).': '.$show($old[$key] ?? null).' → '.$show($new[$key] ?? null);
        }, $keys);
    }

    /**
     * Readable form of one stored value. Arrays (e.g. the active filters of a data table audit row)
     * become a "Label=nilai" list; an empty array shows as "-".
     */
    private static function displayValue(mixed $value): string
    {
        if (is_array($value)) {
            if ($value === []) {
                return '-';
            }

            $parts = [];

            foreach ($value as $key => $item) {
                $item = self::displayValue($item);
                $parts[] = is_string($key) ? (self::FIELD_LABELS[$key] ?? $key).'='.$item : $item;
            }

            return implode(', ', $parts);
        }

        return match (true) {
            $value === null => '-',
            is_bool($value) => $value ? 'ya' : 'tidak',
            is_scalar($value) => (string) $value,
            default => '-',
        };
    }

    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new LogicException('Riwayat perubahan master bersifat append-only.');
        });

        static::deleting(function (): never {
            throw new LogicException('Riwayat perubahan master bersifat append-only.');
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function record(
        ?User $user,
        string $action,
        string $entityType,
        ?int $entityId,
        string $entityLabel,
        ?array $old = null,
        ?array $new = null,
        ?string $reason = null,
    ): self {
        return static::create([
            'user_id' => $user?->id,
            'user_name' => $user?->name ?? 'Sistem',
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'entity_label' => $entityLabel,
            'old_values' => $old,
            'new_values' => $new,
            'reason' => $reason,
        ]);
    }
}
