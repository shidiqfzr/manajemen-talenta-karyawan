<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class JobHistory extends Model
{
    use HasFactory;

    // Audit Trail (SECURITY.md §5): riwayat jabatan tidak pernah dihapus permanen.
    use SoftDeletes;

    public const MUTASI_TYPES = [
        'PROMOSI',
        'ROTASI',
        'DEMOSI',
        'MUTASI_UNIT',
        'PENUGASAN',
        'PENUGASAN_KHUSUS',
        'SANKSI_INDISIPLINER',
    ];

    public const STATUS_JABATAN = [
        'Definitif',
        'Pj',
        'Plt',
    ];

    /** Asal baris riwayat (DOMAIN.md §2.2): hanya `mutasi` yang berdasar SK mutasi resmi. */
    public const SUMBER_MUTASI = 'mutasi';

    public const SUMBER_DATA_AWAL = 'data_awal';

    public const SUMBER_PEMBARUAN_DATA = 'pembaruan_data';

    public const SUMBER_IMPOR = 'impor';

    public const SUMBER_LABELS = [
        self::SUMBER_MUTASI => 'Mutasi Resmi',
        self::SUMBER_DATA_AWAL => 'Data Awal',
        self::SUMBER_PEMBARUAN_DATA => 'Pembaruan Data',
        self::SUMBER_IMPOR => 'Impor Excel',
    ];

    protected $fillable = [
        'sumber',
        'batch_id',
        'chain_order',
        'is_applied',
        'employee_nik',
        'created_by',
        'jabatan',
        'unit_kerja',
        'status_penugasan',
        'level',
        'golongan',
        'job_grade',
        'tmt_awal',
        'tmt_akhir',
        'tmt_akhir_estimasi',
        'tanggal_berakhir_sanksi',
        'jenis_mutasi',
        'nomor_sk',
        'tanggal_sk',
        'file_sk',
        'catatan',
        'is_dispensasi',
        'alasan_dispensasi',
        'cancelled_by',
        'alasan_pembatalan',
    ];

    protected $casts = [
        'job_grade' => 'integer',
        'is_applied' => 'boolean',
        'tmt_awal' => 'date',
        'tmt_akhir' => 'date',
        'tmt_akhir_estimasi' => 'date',
        'tanggal_berakhir_sanksi' => 'date',
        'tanggal_sk' => 'date',
        'is_dispensasi' => 'boolean',
    ];

    /** Baris yang bukan SK mutasi resmi: data awal, pembaruan data pegawai, dan tugas rangkap hasil impor. */
    public function isRecordOnly(): bool
    {
        return $this->sumber !== self::SUMBER_MUTASI;
    }

    /** Label sumber dengan istilah bisnis. */
    public function getSumberLabelAttribute(): string
    {
        return self::SUMBER_LABELS[$this->sumber] ?? self::SUMBER_LABELS[self::SUMBER_MUTASI];
    }

    /**
     * Saring menurut sumber. `resmi` (bawaan layar riwayat) hanya memuat mutasi resmi; `semua` atau kosong
     * memuat seluruhnya; nilai lain harus salah satu kunci SUMBER_LABELS.
     */
    public function scopeBySumber($query, ?string $sumber)
    {
        return match (true) {
            $sumber === 'resmi' => $query->where('sumber', self::SUMBER_MUTASI),
            array_key_exists((string) $sumber, self::SUMBER_LABELS) => $query->where('sumber', $sumber),
            default => $query,
        };
    }

    /**
     * Scope to only applied (active/historical) records.
     */
    public function scopeApplied($query)
    {
        return $query->where('is_applied', true);
    }

    /**
     * Scope to only scheduled (future staging) records.
     */
    public function scopeScheduled($query)
    {
        return $query->where('is_applied', false);
    }

    /**
     * Scope to scheduled records that are due for activation on or before the given date.
     */
    public function scopeDueForActivation($query, $date = null)
    {
        $targetDate = $date ?? now()->toDateString();

        return $query->where('is_applied', false)
            ->whereDate('tmt_awal', '<=', $targetDate);
    }

    public function setTmtAkhirEstimasiAttribute($value): void
    {
        $this->attributes['tmt_akhir_estimasi'] = ! empty($value) ? $value : null;
    }

    public function setTanggalSkAttribute($value): void
    {
        $this->attributes['tanggal_sk'] = ! empty($value) ? $value : null;
    }

    public function setTmtAkhirAttribute($value): void
    {
        $this->attributes['tmt_akhir'] = ! empty($value) ? $value : null;
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function canceller()
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_nik', 'nik');
    }

    /**
     * Business label of a mutation type code, e.g. MUTASI_UNIT → "Mutasi Unit".
     */
    public static function labelFor(?string $type): string
    {
        if (! $type) {
            return '—';
        }

        return match ($type) {
            'PROMOSI' => 'Promosi',
            'ROTASI' => 'Rotasi',
            'DEMOSI' => 'Demosi',
            'MUTASI_UNIT' => 'Mutasi Unit',
            'PENUGASAN', 'PENUGASAN_SEMENTARA' => 'Penugasan',
            'PENUGASAN_KHUSUS' => 'Penugasan Khusus',
            'SANKSI_INDISIPLINER' => 'Sanksi Indisipliner',
            default => $type,
        };
    }

    public function getJenisMutasiLabelAttribute(): string
    {
        return self::labelFor($this->jenis_mutasi);
    }

    public function getJenisMutasiBadgeClassAttribute(): string
    {
        return match ($this->jenis_mutasi) {
            'PROMOSI' => 'bg-primary-50 text-primary-800 border-primary-200',
            'ROTASI' => 'bg-info-50 text-info-800 border-info-200',
            'MUTASI_UNIT' => 'bg-primary-50 text-primary-800 border-primary-300',
            'PENUGASAN', 'PENUGASAN_SEMENTARA' => 'bg-surface-100 text-surface-800 border-surface-300',
            'DEMOSI' => 'bg-danger-50 text-danger-800 border-danger-200',
            default => 'bg-surface-100 text-surface-700 border-surface-200',
        };
    }
}
