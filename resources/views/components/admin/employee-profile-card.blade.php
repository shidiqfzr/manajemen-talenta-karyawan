@props([
    'employee' => null,
    'nik' => null,
    'showEmptyState' => false,
    'showReadinessMetrics' => false,
    'showGradeBadge' => true,
    'unitTenure' => null,
    'emptyMessage' => 'Pilih pegawai pada pencarian di atas untuk melihat profil penempatan saat ini.',
])

@php
    $emp = $employee;
    $nama = $emp['nama'] ?? $emp['employee_nama'] ?? null;
    $nikVal = $emp['nik'] ?? $emp['employee_nik'] ?? $nik ?? null;
    $jabatan = $emp['jabatan'] ?? $emp['current_jabatan'] ?? '—';
    $unit = $emp['unit_kerja'] ?? $emp['current_unit'] ?? '—';
    $rm = $emp['rm_level'] ?? $emp['current_rm'] ?? null;
    $golongan = $emp['golongan'] ?? $emp['current_golongan'] ?? null;
    $level = $emp['level'] ?? null;
@endphp

@if (!empty($nama) || !empty($nikVal))
    <div class="space-y-2.5 animate-fade-in">
        <!-- Main Biodata Card -->
        <div class="p-4 bg-surface-50/80 rounded-2xl border border-surface-200 text-xs space-y-2.5 shadow-2xs">
            <div class="flex items-center gap-3 pb-2.5 border-b border-surface-200/80">
                <div class="w-10 h-10 rounded-xl bg-primary-600 text-white font-bold text-sm flex items-center justify-center shadow-xs shrink-0">
                    {{ strtoupper(substr($nama ?? '?', 0, 1)) }}
                </div>
                <div class="min-w-0 flex-1">
                    <div class="font-bold text-surface-900 truncate text-xs sm:text-sm">{{ $nama }}</div>
                    <div class="text-xs font-mono text-surface-700">
                        NIK: {{ $nikVal }}@if (! $showGradeBadge && ($rm || $golongan)) &bull; {{ $rm ?? '—' }} &bull; Gol: {{ $golongan ?? '—' }}@endif
                    </div>
                </div>
                @if ($showGradeBadge && ($rm || $golongan))
                    <span class="inline-flex items-center px-2 py-0.5 rounded-lg text-[10px] font-bold bg-earth-100 text-earth-800 border border-earth-200 tabular-nums shrink-0">
                        {{ $rm ?? '—' }} • {{ $golongan ?? '—' }}
                    </span>
                @endif
            </div>

            <div class="space-y-1.5 pt-0.5 text-xs">
                <div class="flex items-start justify-between gap-2">
                    <span class="text-surface-600 shrink-0">Jabatan Terkini:</span>
                    <span class="font-bold text-surface-800 text-right">{{ $jabatan }}</span>
                </div>
                <div class="flex items-start justify-between gap-2">
                    <span class="text-surface-600 shrink-0">Unit Kerja:</span>
                    <span class="font-medium text-surface-800 text-right">{{ $unit }}</span>
                </div>
                @if (!empty($unitTenure))
                    <div class="flex items-start justify-between gap-2">
                        <span class="text-surface-600 shrink-0">Masa Dinas Unit:</span>
                        <span class="font-medium text-surface-800 text-right">{{ $unitTenure }}</span>
                    </div>
                @endif
                @if (!empty($level))
                    <div class="flex items-center justify-between pt-1 border-t border-surface-200/60">
                        <span class="text-surface-600">Klasifikasi Pegawai:</span>
                        <span class="font-bold text-primary-800">{{ $level }}</span>
                    </div>
                @endif
            </div>
        </div>

        @if ($showReadinessMetrics && !empty($emp))
            <!-- Compact Talent Metrics & Readiness Strip (Enterprise Standard) -->
            <div class="p-3 bg-surface-50/80 rounded-xl border border-surface-200/90 space-y-2.5 shadow-2xs">
                <div class="flex items-center justify-between">
                    <span class="text-[10px] font-bold text-surface-500 uppercase tracking-wider flex items-center gap-1.5">
                        <i class="fas fa-chart-line text-primary-600" aria-hidden="true"></i>
                        <span>Kesiapan Talenta &amp; Masa Kerja</span>
                    </span>
                    @if (!empty($nikVal))
                        <button type="button" wire:click="openTalentDrawer('{{ $nikVal }}')"
                            class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[10px] font-bold text-primary-700 bg-white hover:bg-primary-50 border border-primary-200 transition cursor-pointer shadow-2xs"
                            title="Buka panel rekam jejak talenta 9-Box &amp; asesmen lengkap">
                            <i class="fas fa-th-large text-[10px]" aria-hidden="true"></i>
                            <span>Lihat 9-Box</span>
                        </button>
                    @endif
                </div>

                <!-- 4 Compact Key Metric Pills -->
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 text-[11px]">
                    <!-- Masa Jabatan -->
                    <div class="p-2 bg-white rounded-lg border border-surface-200/80 text-center shadow-2xs">
                        <span class="text-[10px] text-surface-500 block font-medium">Masa Jabatan</span>
                        <span class="font-bold text-surface-900 text-xs block leading-tight mt-0.5">
                            {{ $emp['tenure_jabatan_formatted'] ?? '—' }}
                        </span>
                    </div>

                    <!-- Masa Unit -->
                    <div class="p-2 bg-white rounded-lg border border-surface-200/80 text-center shadow-2xs">
                        <span class="text-[10px] text-surface-500 block font-medium">Masa Unit</span>
                        <div class="flex items-center justify-center gap-1 mt-0.5">
                            <span class="font-bold text-surface-900 text-xs leading-tight">
                                {{ $emp['tenure_unit_formatted'] ?? '—' }}
                            </span>
                            @if ($emp['is_tenure_unit_compliant'] ?? false)
                                <i class="fas fa-check-circle text-[10px] text-primary-600" title="&ge;2 Tahun" aria-hidden="true"></i>
                            @else
                                <i class="fas fa-triangle-exclamation text-[10px] text-warning-700" title="&lt;2 Tahun" aria-hidden="true"></i>
                            @endif
                        </div>
                    </div>

                    <!-- Usia & BUP -->
                    <div class="p-2 bg-white rounded-lg border border-surface-200/80 text-center shadow-2xs">
                        <span class="text-[10px] text-surface-500 block font-medium">Usia (BUP)</span>
                        <span class="font-bold text-surface-900 text-xs block leading-tight mt-0.5 truncate" title="Pensiun: {{ $emp['tanggal_pensiun_formatted'] ?? '—' }}">
                            {{ $emp['usia_formatted'] ?? '—' }}
                        </span>
                    </div>

                    <!-- Matriks 9-Box -->
                    <div class="p-2 bg-white rounded-lg border border-surface-200/80 text-center shadow-2xs">
                        <span class="text-[10px] text-surface-500 block font-medium">9-Box</span>
                        <span class="font-bold text-xs block leading-tight mt-0.5 {{ !empty($emp['kategori_9box']) ? 'text-primary-700' : 'text-surface-500 italic font-normal text-[10px]' }}">
                            {{ $emp['kategori_9box'] ?? 'Belum ada' }}
                        </span>
                    </div>
                </div>

                <!-- Micro SK Reference Line -->
                @if (!empty($emp['last_sk_nomor']))
                    <div class="flex items-center justify-between pt-1.5 border-t border-surface-200/70 text-[10px] text-surface-500">
                        <div class="truncate flex items-center gap-1.5">
                            <i class="fas fa-file-contract text-primary-600" aria-hidden="true"></i>
                            <span>SK Aktif:</span>
                            <span class="font-mono font-semibold text-surface-800 truncate" title="{{ $emp['last_sk_nomor'] }}">
                                {{ $emp['last_sk_nomor'] }}
                            </span>
                        </div>
                        @if (!empty($emp['last_job_id']) && !empty($emp['has_last_sk_file']))
                            <a href="{{ route('admin.mutasi.downloadSk', $emp['last_job_id']) }}" target="_blank"
                                class="text-primary-700 hover:text-primary-800 font-bold shrink-0 ml-2"
                                title="Unduh Berkas SK">
                                <i class="fas fa-arrow-up-right-from-square text-[10px]" aria-hidden="true"></i> SK
                            </a>
                        @endif
                    </div>
                @endif
            </div>
        @endif
    </div>
@elseif ($showEmptyState)
    <div class="p-6 rounded-2xl bg-surface-50/70 border border-dashed border-surface-200 text-center space-y-2.5 shadow-2xs animate-fade-in">
        <div class="w-11 h-11 mx-auto rounded-2xl bg-surface-100 text-surface-500 flex items-center justify-center text-base shadow-2xs">
            <i class="fas fa-id-card-clip" aria-hidden="true"></i>
        </div>
        <div class="space-y-1">
            <h5 class="text-xs font-bold text-surface-700">Belum Ada Pegawai Dipilih</h5>
            <p class="text-[11px] text-surface-500 max-w-xs mx-auto leading-relaxed">
                {{ $emptyMessage }}
            </p>
        </div>
    </div>
@endif
