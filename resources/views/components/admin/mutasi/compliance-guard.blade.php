{{--
    Hasil cek aturan masa dinas unit & kualifikasi promosi pada alur mutasi (DESIGN.md §7.2).
    Semua lolos -> strip ringkas primary; ada pelanggaran -> satu kartu Dispensasi Direksi.

    Satu kartu untuk semua jenis pengecualian: daftar pengecualian yang diajukan menyesuaikan aturan yang tidak terpenuhi,
    sedangkan persetujuannya cukup satu dasar persetujuan berupa dokumen (nomor nota dinas / SK + tanggal, x-admin.mutasi.dispensasi-field).
    Identik untuk Mutasi Tunggal maupun Mutasi Berantai dan untuk semua jenis mutasi.
    Persetujuan wajib (proses tertahan sampai terpenuhi) dan tercatat di riwayat jabatan sebagai bahan audit.

    Hierarki warna: amber hanya pada header kartu; badan kartu netral. Merah hanya untuk kriteria yang gagal.
    locked = true (kendala penempatan belum selesai, DESIGN.md §7.2 "Terblokir"): kartu tetap terbaca tetapi isian dikunci.

    <x-admin.mutasi.compliance-guard :tenure-analysis="$tenure" :promotion-analysis="$promo" />
--}}
@props([
    'tenureAnalysis' => null,
    'promotionAnalysis' => null,
    'currentUnit' => 'unit saat ini',
    'currentRm' => null,
    'employeeNik' => null,
    'referenceModel' => 'form.alasanDispensasiDireksi',
    'dateModel' => 'form.tanggalDispensasiDireksi',
    'isApproved' => false,
    'locked' => false,
    'errorReferenceKey' => 'alasanDispensasiDireksi',
    'errorDateKey' => 'tanggalDispensasiDireksi',
])

@php
    $isTenureNonCompliant = $tenureAnalysis && !($tenureAnalysis['is_compliant'] ?? true) && ($tenureAnalysis['is_applicable'] ?? true);
    $isPromoNonCompliant = $promotionAnalysis && !($promotionAnalysis['is_eligible'] ?? true) && ($promotionAnalysis['is_applicable'] ?? false);
    $isPromotionApplicable = $promotionAnalysis && ($promotionAnalysis['is_applicable'] ?? false);
    $isAllCompliant = !$isTenureNonCompliant && !$isPromoNonCompliant;
@endphp

@if ($isAllCompliant)
    <!-- ── 1. CLEAN COMPLIANT STRIP (Ringkas, Hemat Ruang, Enterprise Grade) ── -->
    <div class="p-3 bg-primary-50/70 border border-primary-200/90 rounded-xl flex items-center justify-between gap-3 text-xs text-primary-950 animate-fade-in shadow-2xs">
        <div class="flex items-center gap-2.5 min-w-0">
            <div class="w-6 h-6 rounded-md bg-primary-600 text-white flex items-center justify-center text-xs shrink-0 shadow-2xs">
                <i class="fas fa-clipboard-check" aria-hidden="true"></i>
            </div>
            <div class="truncate">
                <span class="font-bold text-primary-950">Kepatuhan Regulasi Terpenuhi:</span>
                <span class="text-primary-800 ml-1 text-xs">
                    @if ($tenureAnalysis && ($tenureAnalysis['is_applicable'] ?? false))
                        Masa dinas unit ({{ \App\Services\Mutasi\TalentProfileBuilder::formatMonths($tenureAnalysis['months'] ?? 0) }}) dan jenjang eselon memenuhi regulasi.
                    @else
                        Kualifikasi eselon dan rotasi internal unit kerja telah memenuhi standar regulasi kepegawaian.
                    @endif
                </span>
            </div>
        </div>
        <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-primary-100 text-primary-800 border border-primary-300 shrink-0">
            @if ($isPromotionApplicable)
                Promosi Memenuhi Syarat
            @else
                Lateral • {{ $currentRm ?? 'RM' }}
            @endif
        </span>
    </div>
@else
    <!-- ── 2. KARTU DISPENSASI DIREKSI (satu persetujuan untuk seluruh pengecualian regulasi) ── -->
    @php
        $exceptionCount = (int) $isTenureNonCompliant + (int) $isPromoNonCompliant;
        $headingId = 'dispensasi-heading-'.trim(preg_replace('/[^a-z0-9]+/i', '-', $referenceModel), '-');
        // Status berwarna + ikon + teks (bukan warna saja): terkunci -> menunggu, bukti lengkap -> tercatat, selain itu perlu dispensasi
        [$pillClass, $pillIcon, $pillText] = match (true) {
            $locked => ['bg-surface-100 text-surface-700 border-surface-300', 'fa-lock', 'Belum Tersedia'],
            $isApproved => ['bg-primary-50 text-primary-800 border-primary-200', 'fa-check', 'Dispensasi Tercatat'],
            default => ['bg-warning-50 text-warning-800 border-warning-200', 'fa-triangle-exclamation', 'Perlu Dispensasi'],
        };

        // Satu pola baris untuk setiap syarat: [ikon] Syarat (batas) - status, lalu satu baris fakta.
        // Angka (batas, selisih, nilai kriteria) berasal dari service; view hanya menyusun teks.
        $rows = [];
        if ($isTenureNonCompliant) {
            $tenureFacts = ! empty($tenureAnalysis['tmt_unit_kerja'])
                ? 'Saat ini '.\App\Services\Mutasi\TalentProfileBuilder::formatMonths((int) ($tenureAnalysis['months'] ?? 0)).' di '.$currentUnit
                : 'TMT unit kerja belum tercatat';
            $rows[] = [
                'type' => 'tenure',
                'group' => null,
                'met' => false,
                'label' => 'Masa Dinas Unit Kerja',
                'limit' => 'min. '.($tenureAnalysis['required_label'] ?? '2 tahun'),
                'facts' => [$tenureFacts.' · kurang '.\App\Services\Mutasi\TalentProfileBuilder::formatMonths((int) ($tenureAnalysis['shortfall_months'] ?? 0))],
                'dataGap' => false,
            ];
        }
        if ($isPromoNonCompliant) {
            $promoGroup = 'Kualifikasi Promosi '.($promotionAnalysis['current_rm'] ?? 'RM').' → '.($promotionAnalysis['target_rm'] ?? 'RM');
            foreach (($promotionAnalysis['criteria'] ?? []) as $crit) {
                $rows[] = [
                    'type' => 'promotion',
                    'group' => $promoGroup,
                    'met' => ! empty($crit['met']),
                    'label' => $crit['label'],
                    'limit' => null,
                    // Nilai gabungan ("Gol: .. | Masa Dinas: ..") dirapikan jadi satu baris fakta
                    'facts' => [str_replace(' | ', ' · ', (string) ($crit['value'] ?? ''))],
                    'dataGap' => ! empty($crit['is_data_gap']),
                ];
            }
        }
        $lastGroup = null;
    @endphp
    <section class="rounded-xl border border-warning-200 bg-white overflow-hidden animate-fade-in" aria-labelledby="{{ $headingId }}" data-dispensasi-card @if ($locked) data-dispensasi-locked-card @endif>
        {{-- Header: satu-satunya area beraksen amber, identik pada semua keadaan; hanya pil status yang berubah --}}
        <header class="flex items-start justify-between gap-3 px-4 sm:px-5 py-3.5 border-b bg-warning-50/70 border-warning-200">
            <div class="flex items-start gap-2 min-w-0">
                {{-- Kotak ikon setinggi baris judul (h-5 = leading text-sm) agar ikon sejajar tengah dengan judul; teks judul, subjudul, dan baris syarat berawal di tepi kiri yang sama --}}
                <span class="flex h-5 w-4 shrink-0 items-center justify-center"><i class="fas fa-shield-halved text-warning-700 text-sm translate-y-px" aria-hidden="true"></i></span>
                <div class="min-w-0">
                    <h4 id="{{ $headingId }}" class="text-sm font-bold text-warning-800">Dispensasi Direksi</h4>
                    <p class="text-xs text-surface-600">{{ $exceptionCount }} pengecualian regulasi memerlukan persetujuan Direksi</p>
                </div>
            </div>
            <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-xs font-bold border shrink-0 whitespace-nowrap {{ $pillClass }}">
                <i class="fas {{ $pillIcon }}" aria-hidden="true"></i>
                {{ $pillText }}
            </span>
        </header>

        <div class="px-4 sm:px-5 py-4 space-y-4">
            <div>
                <h5 class="sr-only">Pengecualian regulasi yang diajukan</h5>
                <ul class="space-y-3">
                    @foreach ($rows as $row)
                        @if ($row['group'] && $row['group'] !== $lastGroup)
                            @php $lastGroup = $row['group']; @endphp
                            <li class="pt-1 text-xs font-bold text-surface-700 {{ $loop->first ? '' : 'border-t border-surface-200 pt-3' }}">{{ $row['group'] }}</li>
                        @endif
                        {{-- leading-5 + kotak ikon h-5: ikon sejajar tengah dengan baris syarat; baris fakta jatuh tepat di bawah teks syarat --}}
                        <li class="flex items-start gap-2 text-xs leading-5" data-dispensasi-item="{{ $row['type'] }}" @if ($row['type'] === 'promotion') data-promotion-criteria @endif>
                            <span class="flex h-5 w-4 shrink-0 items-center justify-center"><i class="fas {{ $row['met'] ? 'fa-circle-check text-primary-600' : 'fa-circle-xmark text-danger' }} text-sm" aria-hidden="true"></i></span>
                            <div class="min-w-0">
                                <p class="text-surface-900">
                                    <span class="font-semibold">{{ $row['label'] }}</span>
                                    @if ($row['limit'])
                                        <span class="text-surface-600">({{ $row['limit'] }})</span>
                                    @endif
                                    <span class="font-medium {{ $row['met'] ? 'text-primary-700' : 'text-danger-700' }}">&mdash; {{ $row['met'] ? 'terpenuhi' : 'belum terpenuhi' }}</span>
                                </p>
                                <p class="text-surface-600">
                                    {{ implode(' · ', array_filter($row['facts'])) }}
                                    {{-- Data belum ada = celah data yang dapat dilengkapi, bukan standar yang gagal --}}
                                    @if ($row['dataGap'] && $employeeNik)
                                        &middot; <a href="{{ route('admin.evaluations.create', ['employee_nik' => $employeeNik]) }}" target="_blank" rel="noopener"
                                            class="inline-flex items-center gap-1 font-semibold text-primary-700 hover:text-primary-800 underline underline-offset-2 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 rounded">
                                            Lengkapi data evaluasi
                                            <i class="fas fa-arrow-up-right-from-square text-[10px]" aria-hidden="true"></i>
                                            <span class="sr-only">(dibuka di tab baru)</span>
                                        </a>
                                    @endif
                                </p>
                            </div>
                        </li>
                    @endforeach
                </ul>
            </div>

            <x-admin.mutasi.dispensasi-field
                :locked="$locked"
                :reference-model="$referenceModel"
                :date-model="$dateModel"
                :error-reference-key="$errorReferenceKey"
                :error-date-key="$errorDateKey" />
        </div>
    </section>
@endif
