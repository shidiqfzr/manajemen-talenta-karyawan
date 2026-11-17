<?php

namespace App\Services\Structure;

use App\Exceptions\StructureDecreeException;
use App\Models\Employee;
use App\Models\JobHistory;
use App\Models\MasterAuditLog;
use App\Models\StructureDecree;
use App\Models\Unit;
use App\Models\User;
use App\Services\Formasi\FormasiExcelParser;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Throwable;

/**
 * Siklus SK struktur organisasi (DOMAIN.md §3.7): draf, tinjau, ajukan, setujui, terapkan.
 */
class StructureDecreeService
{
    private const DISK = 'local';

    public function __construct(
        private readonly StructureReader $reader,
        private readonly StructureDiffCalculator $calculator,
        private readonly FormasiExcelParser $parser,
    ) {}

    /**
     * @param  array{sk_number?: string, title?: string, sk_date?: string, effective_date?: ?string, revokes?: ?string, note?: ?string}  $data
     *
     * @throws StructureDecreeException
     */
    public function createDraft(array $data, UploadedFile $workbook, ?UploadedFile $pdf, User $user): StructureDecree
    {
        $this->authorize($user, 'struktur.prepare');

        $validator = Validator::make(
            $data + ['workbook' => $workbook, 'pdf' => $pdf],
            [
                'sk_number' => 'required|string|max:120',
                'title' => 'required|string|max:500',
                'sk_date' => 'required|date',
                'effective_date' => 'nullable|date',
                'revokes' => 'nullable|string|max:2000',
                'note' => 'nullable|string|max:2000',
                'workbook' => 'required|file|mimes:xlsx|max:10240',
                'pdf' => 'nullable|file|mimes:pdf|max:10240',
            ],
            [
                'sk_number.required' => 'Nomor SK wajib diisi.',
                'title.required' => 'Baris "Tentang" SK wajib diisi.',
                'sk_date.required' => 'Tanggal SK wajib diisi.',
                'sk_date.date' => 'Tanggal SK tidak valid.',
                'effective_date.date' => 'Tanggal berlaku tidak valid.',
                'workbook.required' => 'Lampiran SK (.xlsx) wajib diunggah.',
                'workbook.mimes' => 'Lampiran SK harus berkas Excel (.xlsx).',
                'workbook.max' => 'Lampiran SK maksimal 10 MB.',
                'pdf.mimes' => 'Salinan SK harus berkas PDF.',
                'pdf.max' => 'Salinan SK maksimal 10 MB.',
            ]
        );
        if ($validator->fails()) {
            throw new StructureDecreeException((string) $validator->errors()->first());
        }

        $number = StructureDecree::normalizeNumber($data['sk_number']);
        $effective = $data['effective_date'] ?? null ?: $data['sk_date'];

        if (StructureDecree::where('sk_number', $number)->exists()) {
            throw new StructureDecreeException("Nomor SK {$number} sudah terdaftar.");
        }
        if (StructureDecree::whereIn('status', StructureDecree::OPEN_STATUSES)->exists()) {
            throw new StructureDecreeException('Masih terbuka satu SK lain yang belum selesai. Selesaikan atau batalkan SK tersebut terlebih dahulu.');
        }
        $latest = StructureDecree::where('status', StructureDecree::STATUS_APPLIED)->max('effective_date');
        if ($latest !== null && $effective < substr((string) $latest, 0, 10)) {
            throw new StructureDecreeException('Tanggal berlaku tidak boleh lebih awal dari tanggal berlaku SK terakhir yang diterapkan ('.substr((string) $latest, 0, 10).').');
        }

        $parsed = $this->parse($workbook);
        $target = $this->reader->fromParsedSeats($parsed);
        $diff = $this->calculator->calculate($target, $this->reader->current());

        try {
            return DB::transaction(function () use ($data, $number, $effective, $workbook, $pdf, $target, $diff): StructureDecree {
                $decree = StructureDecree::create([
                    'sk_number' => $number,
                    'title' => trim($data['title']),
                    'revokes' => $data['revokes'] ?? null,
                    'note' => $data['note'] ?? null,
                    'sk_date' => $data['sk_date'],
                    'effective_date' => $effective,
                    'status' => StructureDecree::STATUS_DRAFT,
                    'source_path' => '',
                    'source_sha256' => (string) hash_file('sha256', $workbook->getRealPath()),
                    'target' => $target,
                    'diff' => $diff->toArray(),
                    'unit_mapping' => ['mapping' => [], 'closed' => [], 'new_units' => [], 'positions' => []],
                ]);

                $directory = "structure-decrees/{$decree->id}";
                $decree->source_path = (string) $workbook->storeAs($directory, 'source.xlsx', self::DISK);
                if ($pdf !== null) {
                    $decree->pdf_path = (string) $pdf->storeAs($directory, 'sk.pdf', self::DISK);
                }
                $decree->save();

                return $decree;
            });
        } catch (Throwable $e) {
            // Transaksi dibatalkan; berkas yang sempat tersimpan tidak boleh tertinggal tanpa pemilik.
            foreach (Storage::disk(self::DISK)->directories('structure-decrees') as $directory) {
                if (Storage::disk(self::DISK)->allFiles($directory) !== [] && ! StructureDecree::where('source_path', 'like', $directory.'/%')->exists()) {
                    Storage::disk(self::DISK)->deleteDirectory($directory);
                }
            }

            throw $e;
        }
    }

    /**
     * Simpan pasangan unit dan jabatan, unit yang ditutup, serta atribut unit baru, lalu hitung ulang selisihnya.
     *
     * @param  array<string, string>  $unitMapping  [namaUnitLama => namaUnitBaru]
     * @param  list<string>  $closedUnits
     * @param  array<string, array{kode?: ?string, wilayah?: ?string, urutan?: ?int}>  $newUnitAttributes
     * @param  array<string, string>  $positionMapping  [namaJabatanLama => namaJabatanBaru]
     */
    public function updateReview(StructureDecree $decree, array $unitMapping, array $closedUnits, array $newUnitAttributes, array $positionMapping, User $user): StructureDecree
    {
        $this->authorize($user, 'struktur.prepare');

        if (! in_array($decree->status, [StructureDecree::STATUS_DRAFT, StructureDecree::STATUS_STALE], true)) {
            throw new StructureDecreeException('SK yang sudah diajukan tidak dapat diubah.');
        }

        $decree->unit_mapping = [
            'mapping' => array_filter($unitMapping, fn ($new, $old) => $new !== '' && $new !== $old, ARRAY_FILTER_USE_BOTH),
            'closed' => array_values($closedUnits),
            'new_units' => $newUnitAttributes,
            'positions' => array_filter($positionMapping, fn ($new, $old) => $new !== '' && $new !== $old, ARRAY_FILTER_USE_BOTH),
        ];
        $decree->diff = $this->preview($decree)->toArray();
        $decree->status = StructureDecree::STATUS_DRAFT;
        $decree->save();

        return $decree;
    }

    /** Selisih terkini antara hasil baca lampiran (beserta pasangan yang dipilih) dan struktur saat ini. */
    public function preview(StructureDecree $decree): StructureDiff
    {
        $mapping = $decree->unit_mapping ?? [];

        return $this->calculator->calculate(
            $decree->target ?? ['units' => []],
            $this->reader->current(),
            $mapping['mapping'] ?? [],
            $mapping['closed'] ?? [],
            $mapping['positions'] ?? [],
        );
    }

    /**
     * Alasan SK belum boleh diajukan.
     *
     * @return list<string>
     */
    public function blockers(StructureDecree $decree): array
    {
        $diff = $this->preview($decree);
        $mapping = $decree->unit_mapping ?? [];
        $blockers = [];

        if ($diff->isEmpty()) {
            return ['Tidak ada perubahan dibandingkan dengan struktur saat ini.'];
        }

        foreach ($diff->ofType('unit.add') as $change) {
            $attributes = ($mapping['new_units'] ?? [])[$change['unit']] ?? [];
            if (trim((string) ($attributes['kode'] ?? '')) === '') {
                $blockers[] = "Unit baru '{$change['unit']}' belum memiliki kode.";
            }
            if (! in_array($attributes['wilayah'] ?? null, Unit::WILAYAH_LIST, true)) {
                $blockers[] = "Unit baru '{$change['unit']}' belum memiliki wilayah yang valid.";
            }
        }

        $renamedAway = array_keys($mapping['mapping'] ?? []);
        foreach ($diff->ofType('unit.rename') as $change) {
            if (Unit::where('nama', $change['to'])->exists() && ! in_array($change['to'], $renamedAway, true)) {
                $blockers[] = "Nama unit '{$change['to']}' sudah dipakai unit lain.";
            }
        }

        $coveredAfter = array_keys(($decree->target ?? [])['units'] ?? []);
        $current = $this->reader->current();
        foreach ($diff->ofType('position.rename') as $change) {
            if (DB::table('positions')->where('nama', $change['to'])->exists() && ! in_array($change['to'], array_keys($mapping['positions'] ?? []), true)) {
                $blockers[] = "Nama jabatan '{$change['to']}' sudah dipakai jabatan lain.";
            }

            foreach ($current['units'] as $unitName => $unit) {
                $after = ($mapping['mapping'] ?? [])[$unitName] ?? $unitName;
                if (isset($unit['seats'][$change['from']]) && ! in_array($after, $coveredAfter, true)) {
                    $blockers[] = "Jabatan '{$change['from']}' juga dipakai unit '{$unitName}' yang tidak tercantum pada lampiran, sehingga namanya tidak dapat diganti.";
                }
            }
        }

        return array_values(array_unique($blockers));
    }

    /**
     * Dampak pada karyawan: yang terdampak, formasi yang menjadi melebihi kuota, dan mutasi terjadwal yang terkena.
     * Tanpa NIK atau nama karyawan (UU PDP).
     *
     * @return array{affected_employees: int, over_quota_seats: int, scheduled_mutations: list<string>}
     */
    public function impact(StructureDecree $decree): array
    {
        $mapping = $decree->unit_mapping ?? [];
        $oldUnit = fn (string $name): string => (string) (array_search($name, $mapping['mapping'] ?? [], true) ?: $name);
        $oldJabatan = fn (string $name): string => (string) (array_search($name, $mapping['positions'] ?? [], true) ?: $name);
        $holders = fn (string $unit, ?string $jabatan = null): int => Employee::aktif()->where('unit_kerja', $unit)
            ->when($jabatan !== null, fn ($q) => $q->where('jabatan', $jabatan))->count();
        $pending = fn (string $unit, ?string $jabatan = null) => JobHistory::where('is_applied', false)->where('unit_kerja', $unit)
            ->when($jabatan !== null, fn ($q) => $q->where('jabatan', $jabatan))->get();

        $affected = 0;
        $overQuota = 0;
        $scheduled = [];
        $label = fn (JobHistory $job): string => "{$job->jabatan} di {$job->unit_kerja} (TMT ".$job->tmt_awal?->format('d-m-Y').')';

        foreach ($this->preview($decree)->changes as $change) {
            switch ($change['type']) {
                case 'seat.remove':
                    $unit = $oldUnit($change['unit']);
                    $jabatan = $oldJabatan($change['jabatan']);
                    $filled = $holders($unit, $jabatan);
                    $affected += $filled;
                    $overQuota += $filled > 0 ? 1 : 0;
                    foreach ($pending($unit, $jabatan) as $job) {
                        $scheduled[] = $label($job);
                    }
                    break;
                case 'seat.update':
                    $filled = $holders($oldUnit($change['unit']), $oldJabatan($change['jabatan']));
                    $overQuota += isset($change['new']['kuota']) && $change['new']['kuota'] < $filled ? 1 : 0;
                    break;
                case 'unit.close':
                    $affected += $holders($change['unit']);
                    foreach ($pending($change['unit']) as $job) {
                        $scheduled[] = $label($job);
                    }
                    break;
                case 'unit.rename':
                    $affected += $holders($change['from']);
                    break;
            }
        }

        return ['affected_employees' => $affected, 'over_quota_seats' => $overQuota, 'scheduled_mutations' => $scheduled];
    }

    /**
     * Ajukan SK untuk persetujuan; sidik selisih dibekukan agar penyetuju melihat persis yang diajukan.
     *
     * @throws StructureDecreeException
     */
    public function submit(StructureDecree $decree, User $user): StructureDecree
    {
        $this->authorize($user, 'struktur.prepare');
        $this->assertStatus($decree, [StructureDecree::STATUS_DRAFT], 'diajukan');

        if (($blockers = $this->blockers($decree)) !== []) {
            throw new StructureDecreeException(implode(' ', $blockers));
        }

        return DB::transaction(function () use ($decree, $user): StructureDecree {
            $decree = $this->lock($decree);
            $this->assertStatus($decree, [StructureDecree::STATUS_DRAFT], 'diajukan');

            $decree->fill([
                'status' => StructureDecree::STATUS_SUBMITTED,
                'review_digest' => $this->preview($decree)->digest(),
                'submitted_by' => $user->id,
                'submitted_at' => now(),
            ])->save();
            $this->audit($user, 'decree.submitted', $decree, StructureDecree::STATUS_DRAFT);

            return $decree;
        });
    }

    /**
     * Setujui SK. Bila kebijakan empat mata aktif, pengaju tidak dapat menyetujui SK-nya sendiri. Bila struktur
     * berubah sejak diajukan, SK menjadi "Perlu Tinjau Ulang" dan tidak ada yang disetujui.
     *
     * @throws StructureDecreeException
     */
    public function approve(StructureDecree $decree, User $checker): StructureDecree
    {
        $this->authorize($checker, 'struktur.approve');

        $stale = false;
        $result = DB::transaction(function () use ($decree, $checker, &$stale): StructureDecree {
            $decree = $this->lock($decree);
            $this->assertStatus($decree, [StructureDecree::STATUS_SUBMITTED], 'disetujui');

            if (config('hris.structure.require_approval') && $decree->submitted_by === $checker->id) {
                throw new StructureDecreeException('SK tidak dapat disetujui oleh pengajunya sendiri. Persetujuan harus dilakukan admin lain (prinsip empat mata).');
            }

            if ($this->isStale($decree)) {
                $decree->update(['status' => StructureDecree::STATUS_STALE]);
                $stale = true;

                return $decree;
            }

            $decree->fill(['status' => StructureDecree::STATUS_APPROVED, 'decided_by' => $checker->id, 'decided_at' => now()])->save();
            $this->audit($checker, 'decree.approved', $decree, StructureDecree::STATUS_SUBMITTED);

            return $decree;
        });

        if ($stale) {
            throw new StructureDecreeException('Struktur organisasi berubah sejak SK diajukan. SK perlu ditinjau ulang dan diajukan kembali.');
        }

        return $result;
    }

    /** @throws StructureDecreeException */
    public function reject(StructureDecree $decree, User $checker, string $reason): StructureDecree
    {
        $this->authorize($checker, 'struktur.approve');
        $reason = $this->requireReason($reason);

        return DB::transaction(function () use ($decree, $checker, $reason): StructureDecree {
            $decree = $this->lock($decree);
            $this->assertStatus($decree, [StructureDecree::STATUS_SUBMITTED], 'ditolak');

            $decree->fill(['status' => StructureDecree::STATUS_REJECTED, 'decided_by' => $checker->id, 'decided_at' => now(), 'decision_reason' => $reason])->save();
            $this->audit($checker, 'decree.rejected', $decree, StructureDecree::STATUS_SUBMITTED, $reason);

            return $decree;
        });
    }

    /** Batalkan SK yang belum diterapkan. SK yang sudah diterapkan dikoreksi dengan SK baru. */
    public function cancel(StructureDecree $decree, User $user, string $reason): StructureDecree
    {
        $this->authorize($user, 'struktur.prepare');
        $reason = $this->requireReason($reason);

        return DB::transaction(function () use ($decree, $user, $reason): StructureDecree {
            $decree = $this->lock($decree);
            $this->assertStatus($decree, StructureDecree::OPEN_STATUSES, 'dibatalkan');
            $old = $decree->status;

            $decree->fill(['status' => StructureDecree::STATUS_CANCELLED, 'decided_by' => $user->id, 'decided_at' => now(), 'decision_reason' => $reason])->save();
            $this->audit($user, 'decree.cancelled', $decree, $old, $reason);

            return $decree;
        });
    }

    /** Struktur saat ini sudah berbeda dari yang ditinjau saat SK diajukan. */
    public function isStale(StructureDecree $decree): bool
    {
        return $decree->review_digest !== null && $this->preview($decree)->digest() !== $decree->review_digest;
    }

    private function lock(StructureDecree $decree): StructureDecree
    {
        return StructureDecree::whereKey($decree->id)->lockForUpdate()->firstOrFail();
    }

    /** @param  list<string>  $allowed */
    private function assertStatus(StructureDecree $decree, array $allowed, string $action): void
    {
        if (! in_array($decree->status, $allowed, true)) {
            $label = StructureDecree::STATUS_LABELS[$decree->status] ?? $decree->status;

            throw new StructureDecreeException("SK berstatus {$label} sehingga tidak dapat {$action}.");
        }
    }

    private function requireReason(string $reason): string
    {
        $reason = trim($reason);
        if (mb_strlen($reason) < 10) {
            throw new StructureDecreeException('Alasan wajib diisi minimal 10 karakter.');
        }

        return $reason;
    }

    private function audit(User $user, string $action, StructureDecree $decree, string $oldStatus, ?string $reason = null): void
    {
        MasterAuditLog::forDecree($decree->id, fn () => MasterAuditLog::record(
            $user, $action, 'decree', $decree->id, $decree->sk_number,
            ['status' => $oldStatus], ['status' => $decree->status], $reason
        ));
    }

    /** @return list<array{unit: string, jabatan: string, rm_level: string, bidang: string, kuota: int}> */
    private function parse(UploadedFile $workbook): array
    {
        try {
            $result = $this->parser->parse($workbook->getRealPath());
        } catch (Throwable) {
            throw new StructureDecreeException('Lampiran SK tidak dapat dibaca sebagai berkas Excel (.xlsx).');
        }

        if ($result['errors'] !== []) {
            $messages = array_map(fn (array $e): string => "Baris {$e['row']}: {$e['message']}", array_slice($result['errors'], 0, 5));
            $more = count($result['errors']) - count($messages);

            throw new StructureDecreeException('Lampiran SK memiliki kesalahan. '.implode(' ', $messages).($more > 0 ? " (dan {$more} kesalahan lain)" : ''));
        }

        if ($result['seats'] === []) {
            throw new StructureDecreeException('Lampiran SK tidak memuat satu pun formasi jabatan.');
        }

        return $result['seats'];
    }

    private function authorize(User $user, string $ability): void
    {
        if (! Gate::forUser($user)->allows($ability)) {
            throw new StructureDecreeException('Anda tidak berwenang melakukan aksi ini pada SK struktur organisasi.');
        }
    }
}
