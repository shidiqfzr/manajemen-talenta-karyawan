<?php

namespace App\Services\Import;

use App\Models\Employee;
use App\Models\JobHistory;
use App\Models\Position;
use App\Models\Unit;
use Carbon\Carbon;

class ConcurrentJobTextParser
{
    private const INDO_MONTHS = [
        'januari' => 1, 'februari' => 2, 'maret' => 3, 'april' => 4,
        'mei' => 5, 'juni' => 6, 'juli' => 7, 'agustus' => 8,
        'september' => 9, 'oktober' => 10, 'november' => 11, 'desember' => 12,
        'jan' => 1, 'feb' => 2, 'mar' => 3, 'apr' => 4,
        'jun' => 6, 'jul' => 7, 'agu' => 8, 'agt' => 8,
        'sep' => 9, 'okt' => 10, 'nov' => 11, 'des' => 12,
    ];

    /** Banyak tugas rangkap maksimal dalam satu sel (satu tugas per baris atau dipisah titik koma). */
    public const MAX_SEGMENTS = 10;

    /** Status pada tanda kurung siku (format ekspor) dan padanannya pada job_histories. */
    private const STATUS_ALIASES = [
        'plt' => Employee::STATUS_PLT,
        'pj' => Employee::STATUS_PJ,
        'pjs' => Employee::STATUS_PJS,
        'pejabat sementara' => Employee::STATUS_PJS,
        'pejabat sementara (pjs)' => Employee::STATUS_PJS,
        'diperbantukan' => Employee::STATUS_DIPERBANTUKAN,
        'tugas belajar' => Employee::STATUS_TUGAS_BELAJAR,
        'advisor' => Employee::STATUS_ADVISOR,
    ];

    /** Format ekspor: "[Plt] Jabatan — Unit (SK: ..., TMT: ...)"; bagian dalam kurung opsional. */
    private const STRUCTURED_PATTERN = '/^\[(?<status>[^\]]+)\]\s*(?<jabatan>.+?)\s+[\x{2014}\x{2013}]\s+(?<unit>.+?)\s*(?:\((?<extra>(?:SK|TMT)[^()]*)\))?$/su';

    /** Hasil pemeriksaan per (unit karyawan, teks): berkas besar memeriksa setiap sel dua kali (galat dan peringatan). */
    private array $inspectCache = [];

    /** @var array<string, bool> */
    private array $formationCache = [];

    /** @var array<string, list<string>> */
    private array $seatUnitsCache = [];

    private ?array $allUnitNames = null;

    private ?array $allPositionNames = null;

    /** Tanggal TMT dari teks ("TMT 15 Juli 2026", "TMT: 2026-07-15"); null bila tidak ada atau tidak valid. */
    private function extractTmt(string $text): ?string
    {
        $tmt = null;
        if (preg_match('/tmt\s*:?\s*(\d{1,2})\s+([a-zA-Z]+)\s+(\d{4})/i', $text, $m)) {
            $day = (int) $m[1];
            $monthStr = strtolower($m[2]);
            $year = (int) $m[3];
            $month = self::INDO_MONTHS[$monthStr] ?? null;
            if ($month && checkdate($month, $day, $year)) {
                $tmt = sprintf('%04d-%02d-%02d', $year, $month, $day);
            }
        } elseif (preg_match('/tmt\s*:?\s*(\d{4})-(\d{2})-(\d{2})/i', $text, $m) && checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
            $tmt = "{$m[1]}-{$m[2]}-{$m[3]}";
        }

        return $tmt;
    }

    /**
     * Mem-parsing teks tugas rangkap di Kolom O untuk mengekstrak status penugasan (Plt/Pjs),
     * tanggal TMT, unit kerja penugasan, dan nama jabatan yang diemban.
     */
    public function parse(string $text, string $defaultUnit = ''): ?array
    {
        $text = trim($text);
        if (empty($text)) {
            return null;
        }

        $this->loadLists();

        if (preg_match(self::STRUCTURED_PATTERN, $text, $structured)) {
            return $this->parseStructured($structured);
        }

        // 1. Ekstrak Status Penugasan
        $status = Employee::STATUS_PLT;
        if (preg_match('/pjs|pejabat\s+sementara/i', $text)) {
            $status = Employee::STATUS_PJS;
        } elseif (preg_match('/diperbantukan/i', $text)) {
            $status = Employee::STATUS_DIPERBANTUKAN;
        } elseif (preg_match('/^pj\b/i', $text)) {
            $status = Employee::STATUS_PJ;
        }

        // 2. Ekstrak Tanggal TMT
        $tmt = $this->extractTmt($text);

        // 3. Ekstrak Unit Kerja
        if ($this->allUnitNames === null) {
            $this->allUnitNames = Unit::orderByRaw('LENGTH(nama) DESC')->pluck('nama')->toArray();
        }

        $matchedUnit = null;
        foreach ($this->allUnitNames as $unitName) {
            if (stripos($text, $unitName) !== false) {
                $matchedUnit = $unitName;
                break;
            }
        }
        // Unit yang tertulis pada teks (termasuk unit milik karyawan itu sendiri) bukan bagian dari nama jabatan.
        $unitInText = $matchedUnit !== null;

        if (! $matchedUnit) {
            $matchedUnit = $defaultUnit;
        }

        // 4. Ekstrak Jabatan. Nama jabatan resmi dapat memuat nama unit (mis. 'Kepala Bagian Sekretariat & Hukum'), jadi teks utuh
        // dicocokkan lebih dulu; baru bila gagal, nama unit dilepas dari teks ('Manajer PKS' + unit 'PKS Gunung Meliau').
        $extract = function (bool $stripUnit) use ($text, $matchedUnit, $unitInText): string {
            $cleaned = $text;
            if (preg_match('/\btmt\b/i', $text)) {
                $cleaned = preg_replace('/tmt\s*:?.*$/i', '', $cleaned);
            }
            if ($stripUnit && $unitInText) {
                $cleaned = str_ireplace($matchedUnit, '', $cleaned);
            }
            $cleaned = preg_replace('/^(merangkap\s+(sebagai|sbg)?\s*)/i', '', $cleaned);
            $cleaned = preg_replace('/^(plt\.?|pjs\.?|pj\.?)\s*/i', '', trim($cleaned));

            return trim(preg_replace('/\s+/', ' ', $cleaned));
        };

        if ($this->allPositionNames === null) {
            $this->allPositionNames = Position::orderByRaw('LENGTH(nama) DESC')->pluck('nama')->toArray();
        }

        $resolve = fn (string $candidate): ?string => $this->resolvePosition($candidate);

        $resolved = true;
        $matchedPos = $resolve($extract(false));
        if (! $matchedPos) {
            $cleaned = $extract(true);
            $matchedPos = $resolve($cleaned);
        }

        if (! $matchedPos) {
            $resolved = false;
            $matchedPos = filled($cleaned) ? $cleaned : $text;
        }

        return [
            'jabatan_dikenal' => $resolved,
            'teks_jabatan_utuh' => $extract(false),
            'tmt_terbaca' => $tmt !== null || ! preg_match('/\btmt\b/i', $text),
            'tmt_eksplisit' => $tmt !== null,
            'status_dikenal' => true,
            'unit_dikenal' => true,
            'unit_tertulis' => $unitInText,
            'status_penugasan' => $status,
            'jabatan' => $matchedPos,
            'unit_kerja' => $matchedUnit,
            'tmt_awal' => $tmt ?? now()->toDateString(),
        ];
    }

    /**
     * Memisahkan sel Tugas Rangkap menjadi tugas-tugas terpisah: satu tugas per baris, atau dipisah titik koma.
     *
     * @return list<string>
     */
    public function segments(string $text): array
    {
        $parts = preg_split('/\R|;/u', $text) ?: [];

        return array_values(array_filter(array_map('trim', $parts), fn (string $part): bool => $part !== ''));
    }

    private function loadLists(): void
    {
        $this->allUnitNames ??= Unit::orderByRaw('LENGTH(nama) DESC')->pluck('nama')->toArray();
        $this->allPositionNames ??= Position::orderByRaw('LENGTH(nama) DESC')->pluck('nama')->toArray();
    }

    private function resolvePosition(string $candidate): ?string
    {
        foreach ($this->allPositionNames as $posName) {
            if (strcasecmp($candidate, $posName) === 0) {
                return $posName;
            }
        }

        return $this->matchPosition($candidate, $this->allPositionNames);
    }

    private function normalizeStatus(string $token): ?string
    {
        return self::STATUS_ALIASES[mb_strtolower(trim($token, " .\t"))] ?? null;
    }

    /**
     * Format ekspor "[Status] Jabatan — Unit (SK: ..., TMT: ...)". Unit dan jabatan wajib sesuai master. Nomor SK pada teks
     * hanya tersimpan di catatan: kolom nomor_sk hanya untuk SK mutasi resmi (baris impor tidak membuat nomor SK).
     *
     * @param  array<string, string>  $match
     */
    private function parseStructured(array $match): array
    {
        $statusToken = trim($match['status']);
        $status = $this->normalizeStatus($statusToken);
        $unitText = trim($match['unit']);
        $unit = PositionNameMatcher::match($unitText, $this->allUnitNames);
        $jabatanText = trim($match['jabatan']);
        $jabatan = $this->resolvePosition($jabatanText);
        $extra = $match['extra'] ?? '';
        $tmt = $this->extractTmt($extra);

        return [
            'jabatan_dikenal' => $jabatan !== null,
            'status_dikenal' => $status !== null,
            'unit_dikenal' => $unit !== null,
            'unit_tertulis' => true,
            'tmt_terbaca' => $tmt !== null || ! preg_match('/\btmt\b/i', $extra),
            'tmt_eksplisit' => $tmt !== null,
            'teks_jabatan_utuh' => $jabatanText,
            'status_token' => $statusToken,
            'unit_teks' => $unitText,
            'status_penugasan' => $status ?? Employee::STATUS_PLT,
            'jabatan' => $jabatan ?? $jabatanText,
            'unit_kerja' => $unit ?? $unitText,
            'tmt_awal' => $tmt ?? now()->toDateString(),
        ];
    }

    /**
     * Memeriksa teks Tugas Rangkap (satu atau beberapa tugas) sebelum disimpan, agar riwayat jabatan tidak berisi teks bebas
     * yang tidak dapat dicocokkan dengan master jabatan, master unit, dan struktur SK. Kesalahan menolak baris (dapat diperbaiki
     * lewat berkas baris gagal); peringatan tidak memblokir. Tidak ada tebakan otomatis.
     *
     * @return array{errors: list<string>, warnings: list<string>}
     */
    public function inspect(string $text, string $defaultUnit = ''): array
    {
        return $this->inspectCache[$defaultUnit."\0".$text] ??= $this->runInspect($text, $defaultUnit);
    }

    /** @return array{errors: list<string>, warnings: list<string>} */
    private function runInspect(string $text, string $defaultUnit): array
    {
        $segments = $this->segments($text);
        if (count($segments) > self::MAX_SEGMENTS) {
            return ['errors' => ['Tugas Rangkap: maksimal '.self::MAX_SEGMENTS.' tugas per sel. Tulis satu tugas per baris.'], 'warnings' => []];
        }

        $multiple = count($segments) > 1;
        $errors = [];
        $warnings = [];
        $seen = [];

        foreach ($segments as $index => $segment) {
            $parsed = $this->parse($segment, $defaultUnit);
            if (! $parsed) {
                continue;
            }

            $label = $multiple ? 'Tugas Rangkap ke-'.($index + 1) : 'Tugas Rangkap';
            $own = [];

            if (! $parsed['status_dikenal']) {
                $own[] = "{$label}: status '{$parsed['status_token']}' tidak dikenali. Gunakan Plt, Pj, Pjs, atau Diperbantukan.";
            }
            if (! $parsed['unit_dikenal']) {
                $own[] = "{$label}: unit kerja '{$parsed['unit_teks']}' tidak ada pada master unit. Tulis nama unit sesuai struktur SK.";
            }
            if (! $parsed['jabatan_dikenal']) {
                $own[] = "{$label}: jabatan '{$parsed['jabatan']}' tidak dikenali pada master jabatan. ".$this->unresolvedHint($parsed['teks_jabatan_utuh'])."Tulis nama jabatan lengkap sesuai SK struktur, contoh: 'Plt. Kepala Bagian Pengadaan & Teknologi Informasi TMT 15 Juli 2026'.";
            }
            if (! $parsed['tmt_terbaca']) {
                $own[] = "{$label}: tanggal TMT tidak terbaca atau tidak valid. Gunakan 'TMT 15 Juli 2026' atau 'TMT 2026-07-15'.";
            }

            if ($own === []) {
                $key = mb_strtolower($parsed['jabatan'].'|'.$parsed['unit_kerja']);
                if (isset($seen[$key])) {
                    $own[] = "{$label}: jabatan '{$parsed['jabatan']}' pada unit '{$parsed['unit_kerja']}' tertulis lebih dari sekali dalam sel yang sama.";
                }
                $seen[$key] = true;
            }

            if ($own === []) {
                $position = Position::getAllCached()->get($parsed['jabatan']);
                $isPool = $position && ($position->is_overflow || $position->is_diperban);
                $inFormation = $this->unitHasSeat($parsed['unit_kerja'], $parsed['jabatan']);

                // Unit tidak tertulis dan jabatan itu juga ada di unit lain: dianggap unit karyawan (bawaan), tetapi dapat keliru.
                if ($inFormation && ! $isPool && ! $parsed['unit_tertulis']) {
                    $others = $this->unitsWithSeat($parsed['jabatan'], $parsed['unit_kerja']);
                    if ($others !== []) {
                        $warnings[] = "{$label}: unit tidak tertulis, dianggap unit karyawan '{$parsed['unit_kerja']}'. Jabatan '{$parsed['jabatan']}' juga ada pada unit lain (".implode('; ', $others).'). Tulis nama unit bila yang dimaksud unit lain.';
                    }
                }

                if (! $isPool && ! $inFormation) {
                    // Unit yang tidak tertulis dianggap unit karyawan; bila jabatan itu ada di unit lain pada SK, hampir pasti unitnya
                    // keliru. Tidak ditebak: baris ditolak beserta petunjuk unit yang benar.
                    $elsewhere = $parsed['unit_tertulis'] ? [] : $this->unitsWithSeat($parsed['jabatan']);

                    if ($elsewhere !== []) {
                        $own[] = "{$label}: unit tidak tertulis sehingga dianggap unit karyawan '{$parsed['unit_kerja']}', padahal jabatan '{$parsed['jabatan']}' tidak ada pada formasi unit itu. Jabatan ini ada pada unit: ".implode('; ', $elsewhere).". Tulis nama unit pada teks, contoh: 'Plt. {$parsed['jabatan']} {$elsewhere[0]} TMT 15 Juli 2026'.";
                    } else {
                        $warnings[] = "{$label}: jabatan '{$parsed['jabatan']}' tidak tercantum pada formasi Unit Kerja '{$parsed['unit_kerja']}'. Periksa unit penugasan; penugasan tetap dicatat sebagai tugas rangkap.";
                    }
                }
            }

            $errors = [...$errors, ...$own];
        }

        return ['errors' => $errors, 'warnings' => $warnings];
    }

    private function unitHasSeat(string $unit, string $jabatan): bool
    {
        return $this->formationCache[$unit."\0".$jabatan] ??= Unit::where('nama', $unit)
            ->whereHas('positions', fn ($query) => $query->where('positions.nama', $jabatan))
            ->exists();
    }

    /**
     * Unit aktif (maksimal 3, tanpa $except) yang memiliki formasi untuk jabatan itu menurut master.
     *
     * @return list<string>
     */
    private function unitsWithSeat(string $jabatan, ?string $except = null): array
    {
        return $this->seatUnitsCache[$jabatan."\0".$except] ??= Unit::where('is_active', true)
            ->when($except !== null, fn ($query) => $query->where('nama', '!=', $except))
            ->whereHas('positions', fn ($query) => $query->where('positions.nama', $jabatan))
            ->orderBy('urutan')
            ->orderBy('nama')
            ->limit(3)
            ->pluck('nama')
            ->all();
    }

    /**
     * Petunjuk untuk teks yang tidak dikenali: jabatan resmi terpanjang yang menjadi awal teks, dan sisa teks yang tidak
     * cocok dengan unit mana pun (mis. nama unit lama). Hanya saran pada pesan; tidak pernah dipakai otomatis.
     */
    private function unresolvedHint(string $candidate): string
    {
        foreach ($this->allPositionNames ?? [] as $posName) {
            if (mb_stripos($candidate, $posName) === 0 && mb_strlen($candidate) > mb_strlen($posName)) {
                $rest = trim(mb_substr($candidate, mb_strlen($posName)));

                return "Jabatan resmi yang mirip: '{$posName}'; sisa teks '{$rest}' tidak cocok dengan unit kerja mana pun pada master. ";
            }
        }

        return '';
    }

    /** Semua tugas pada sel sudah tercatat identik pada job_histories aktif karyawan (impor ulang tidak mengubah apa pun). */
    public function matchesExisting(string $nik, string $rawText, string $defaultUnit = ''): bool
    {
        $segments = $this->segments($rawText);
        if ($segments === []) {
            return false;
        }

        foreach ($segments as $segment) {
            $parsed = $this->parse($segment, $defaultUnit);
            if (! $parsed || ! $this->isMatchingExisting($nik, $parsed)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Memeriksa apakah penugasan sementara (parsed Kolom O) sudah tercatat identik di JobHistory aktif.
     */
    public function isMatchingExisting(string $nik, array $parsed): bool
    {
        $existingJob = JobHistory::where('employee_nik', $nik)
            ->whereNull('tmt_akhir')
            ->where('jabatan', $parsed['jabatan'])
            ->where('unit_kerja', $parsed['unit_kerja'])
            ->first();

        if (! $existingJob) {
            return false;
        }

        if (strcasecmp((string) $existingJob->status_penugasan, (string) $parsed['status_penugasan']) !== 0) {
            return false;
        }

        if (($parsed['tmt_eksplisit'] ?? true) && ! empty($parsed['tmt_awal'])) {
            $existingTmt = $existingJob->tmt_awal ? Carbon::parse($existingJob->tmt_awal)->toDateString() : null;
            if ($existingTmt !== $parsed['tmt_awal']) {
                return false;
            }
        }

        return true;
    }

    /**
     * Mendaftarkan seluruh tugas rangkap pada sel ke job_histories. Tugas yang sudah ada (jabatan dan unit sama, masih aktif)
     * diperbarui, bukan digandakan; tugas yang tidak lagi tertulis pada sel TIDAK diakhiri (pengakhiran hanya melalui Mutasi).
     * Mengembalikan rincian baris riwayat yang dibuat atau diubah (untuk pembatalan impor).
     *
     * @return list<array{id: int, created: bool, before: ?array<string, mixed>}>
     */
    public function registerAllFromText(Employee $employee, string $rawText): array
    {
        $records = [];
        foreach ($this->segments($rawText) as $segment) {
            $parsed = $this->parse($segment, (string) $employee->unit_kerja);
            if ($parsed) {
                $records[] = $this->registerParsed($employee, $parsed, $segment);
            }
        }

        return $records;
    }

    /**
     * @param  array<string, mixed>  $parsed
     * @return array{id: int, created: bool, before: ?array<string, mixed>}
     */
    private function registerParsed(Employee $employee, array $parsed, string $segment): array
    {
        $catatan = 'Tugas Rangkap via Excel Import: '.$segment;

        $existingJob = JobHistory::where('employee_nik', $employee->nik)
            ->whereNull('tmt_akhir')
            ->where('jabatan', $parsed['jabatan'])
            ->where('unit_kerja', $parsed['unit_kerja'])
            ->first();

        if ($existingJob) {
            $before = array_intersect_key($existingJob->getRawOriginal(), array_flip(['status_penugasan', 'tmt_awal', 'catatan']));
            $changes = ['status_penugasan' => $parsed['status_penugasan'], 'catatan' => $catatan];
            // TMT yang tidak tertulis tidak boleh menimpa tanggal mulai yang sudah tercatat.
            if ($parsed['tmt_eksplisit'] ?? true) {
                $changes['tmt_awal'] = $parsed['tmt_awal'];
            }
            $existingJob->update($changes);

            return ['id' => $existingJob->id, 'created' => false, 'before' => $before];
        }

        $created = JobHistory::create([
            'employee_nik' => $employee->nik,
            'jabatan' => $parsed['jabatan'],
            'unit_kerja' => $parsed['unit_kerja'],
            'level' => $employee->level,
            'golongan' => $employee->golongan,
            'status_penugasan' => $parsed['status_penugasan'],
            'tmt_awal' => $parsed['tmt_awal'],
            'tmt_akhir' => null,
            'jenis_mutasi' => 'PENUGASAN',
            'sumber' => JobHistory::SUMBER_IMPOR,
            'catatan' => $catatan,
        ]);

        return ['id' => $created->id, 'created' => true, 'before' => null];
    }

    /**
     * Pencocokan posisi toleran terhadap tanda baca dan spasi (delegasi ke PositionNameMatcher).
     */
    private function matchPosition(string $input, array $validPositions): ?string
    {
        return PositionNameMatcher::match($input, $validPositions);
    }
}
