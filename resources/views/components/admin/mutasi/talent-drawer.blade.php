@props([
    'show' => false,
    'data' => null,
])

@if ($show && $data)
    <div class="fixed inset-0 z-50 overflow-hidden" 
         role="dialog" 
         aria-modal="true" 
         aria-labelledby="talent-drawer-title"
         x-data x-trap.noscroll="true" tabindex="-1"
         @keydown.escape.window="$wire.closeTalentDrawer()">
        
        <!-- Backdrop Blur Overlay -->
        <div class="fixed inset-0 bg-surface-900/60 backdrop-blur-xs transition-opacity animate-fade-in" 
             wire:click="closeTalentDrawer"></div>

        <div class="fixed inset-y-0 right-0 max-w-full flex pl-10">
            <!-- Slide-Over Content Panel -->
            <div class="w-screen max-w-lg bg-white shadow-2xl flex flex-col border-l border-surface-200 animate-slide-in-right">
                
                <!-- Drawer Header -->
                <div class="px-6 py-5 bg-surface-50 border-b border-surface-200 flex items-center justify-between shrink-0">
                    <div class="flex items-center gap-3.5">
                        <div class="w-10 h-10 rounded-xl bg-primary-50 text-primary-700 flex items-center justify-center font-bold text-base shadow-2xs border border-primary-200/60">
                            <i class="fas fa-chart-line" aria-hidden="true"></i>
                        </div>
                        <div>
                            <h3 id="talent-drawer-title" class="text-base font-bold text-surface-900 leading-tight">
                                Profil &amp; Rekam Jejak Talenta
                            </h3>
                            <p class="text-xs text-surface-500 mt-0.5">
                                Matriks 9-Box, kepatuhan masa dinas &amp; kesiapan alih tugas
                            </p>
                        </div>
                    </div>
                    <button type="button" wire:click="closeTalentDrawer" 
                            class="text-surface-500 hover:text-surface-700 p-2 rounded-xl hover:bg-surface-200/60 transition cursor-pointer" 
                            title="Tutup panel (Esc)">
                        <i class="fas fa-times text-sm" aria-hidden="true"></i>
                    </button>
                </div>

                <!-- Drawer Body Scrollable -->
                <div class="flex-1 overflow-y-auto p-6 space-y-6">
                    
                    <!-- 1. Employee Header Identity Card -->
                    <div class="p-4 bg-surface-50/70 rounded-2xl border border-surface-200/90 shadow-2xs space-y-3">
                        <div class="flex items-center gap-3.5">
                            @if (!empty($data['foto']))
                                <img src="{{ asset('storage/' . $data['foto']) }}" 
                                     alt="{{ $data['nama'] }}" 
                                     class="w-13 h-13 rounded-2xl object-cover border border-surface-200 shadow-2xs shrink-0">
                            @else
                                <div class="w-13 h-13 rounded-2xl bg-primary-700 text-white flex items-center justify-center font-bold text-base shadow-2xs shrink-0 tracking-wider">
                                    {{ collect(explode(' ', $data['nama']))->take(2)->map(fn($p) => strtoupper(substr($p, 0, 1)))->implode('') }}
                                </div>
                            @endif

                            <div class="min-w-0 flex-1">
                                <h4 class="text-sm font-bold text-surface-900 truncate" title="{{ $data['nama'] }}">
                                    {{ $data['nama'] }}
                                </h4>
                                <div class="text-xs text-surface-500 font-mono mt-0.5">
                                    NIK: {{ $data['nik'] }}
                                </div>
                                <div class="text-xs font-semibold text-primary-800 truncate mt-1">
                                    {{ $data['jabatan'] }}
                                </div>
                                <div class="text-[11px] text-surface-500 flex items-center gap-1.5 truncate mt-0.5">
                                    <i class="fas fa-building text-[10px] text-surface-400" aria-hidden="true"></i>
                                    <span class="truncate">{{ $data['unit_kerja'] }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- Parameter Badges (RM Band, Golongan, Grade, Strata) -->
                        <div class="pt-2 border-t border-surface-200/60 flex items-center gap-2 flex-wrap text-[11px]">
                            <span class="px-2 py-0.5 rounded-md font-bold bg-primary-100 text-primary-800 border border-primary-200">
                                {{ $data['level'] }}
                            </span>
                            <span class="px-2 py-0.5 rounded-md font-bold bg-surface-100 text-surface-800 border border-surface-200">
                                {{ $data['rm_level'] }}
                            </span>
                            <span class="px-2 py-0.5 rounded-md font-bold bg-surface-100 text-surface-700 border border-surface-200">
                                Gol. {{ $data['golongan'] ?: '—' }}
                            </span>
                            @if (!empty($data['person_grade']) || !empty($data['job_grade']))
                                <span class="px-2 py-0.5 rounded-md font-mono text-[10px] font-semibold bg-surface-100 text-surface-600 border border-surface-200">
                                    PG: {{ $data['person_grade'] ?: '—' }} | JG: {{ $data['job_grade'] ?: '—' }}
                                </span>
                            @endif
                            <span class="px-2 py-0.5 rounded-md font-semibold bg-info-50 text-info-800 border border-info-200">
                                Bidang {{ $data['bidang'] }}
                            </span>
                        </div>
                    </div>

                    <!-- 2. Matriks Talenta 9-Box & Evaluasi Kinerja (Holding Standard) -->
                    <div class="p-4 bg-white rounded-2xl border border-surface-200 shadow-2xs space-y-3.5">
                        <div class="flex items-center justify-between pb-2.5 border-b border-surface-100">
                            <div class="flex items-center gap-2">
                                <span class="w-6 h-6 rounded-lg bg-primary-100 text-primary-800 flex items-center justify-center text-xs">
                                    <i class="fas fa-th-large" aria-hidden="true"></i>
                                </span>
                                <h4 class="text-xs font-bold text-surface-900 tracking-wide uppercase">
                                    Pemetaan Matriks 9-Box Talenta
                                </h4>
                            </div>
                            <span class="text-[10px] font-semibold text-primary-700 bg-primary-50 px-2 py-0.5 rounded-full border border-primary-200">
                                Standar BUMN
                            </span>
                        </div>

                        <!-- Kuadran 9-Box Banner -->
                        <div class="p-3 rounded-xl bg-gradient-to-r from-primary-50 to-teal-50/60 border border-primary-200/80 flex items-center justify-between">
                            <div>
                                <span class="text-[10px] text-primary-700 font-bold uppercase tracking-wider block">Kuadran Pemetaan:</span>
                                <div class="text-sm font-extrabold text-primary-950 mt-0.5">
                                    {{ $data['kategori_9box'] ?: 'Belum Masuk Pemetaan Asesmen' }}
                                </div>
                            </div>
                            <div class="w-9 h-9 rounded-xl bg-primary-600 text-white flex items-center justify-center font-bold text-xs shadow-2xs">
                                <i class="fas fa-star" aria-hidden="true"></i>
                            </div>
                        </div>

                        <!-- Parameter Skor Kinerja (SMKBK) & Potensi (CLI) -->
                        <div class="grid grid-cols-2 gap-3 text-xs">
                            <div class="p-3 bg-surface-50 rounded-xl border border-surface-200/80">
                                <span class="text-[10px] text-surface-500 font-semibold block">Skor Kinerja (SMKBK)</span>
                                <div class="text-base font-bold text-surface-900 mt-1">
                                    {{ $data['skor_kinerja'] ? number_format((float) $data['skor_kinerja'], 2) : '—' }}
                                </div>
                                <span class="text-[10px] text-surface-500 block mt-0.5">Capaian KPI Tahunan</span>
                            </div>

                            <div class="p-3 bg-surface-50 rounded-xl border border-surface-200/80">
                                <span class="text-[10px] text-surface-500 font-semibold block">Skor Potensi (CLI)</span>
                                <div class="text-base font-bold text-surface-900 mt-1">
                                    {{ $data['skor_potensi'] ? number_format((float) $data['skor_potensi'], 2) : '—' }}
                                </div>
                                <span class="text-[10px] text-surface-500 block mt-0.5">Capacity &amp; Leadership</span>
                            </div>
                        </div>

                        <!-- Lembaga & Validitas Asesmen -->
                        @if (!empty($data['lembaga_asesmen']) || !empty($data['tanggal_asesmen_formatted']))
                            <div class="text-[11px] bg-surface-50/60 p-2.5 rounded-xl border border-surface-200/70 space-y-1">
                                <div class="flex items-center justify-between">
                                    <span class="text-surface-500">Lembaga Asesmen:</span>
                                    <span class="font-bold text-surface-800">{{ $data['lembaga_asesmen'] ?: 'Internal Assessment Center' }}</span>
                                </div>
                                <div class="flex items-center justify-between">
                                    <span class="text-surface-500">Masa Berlaku:</span>
                                    @if ($data['is_asesmen_expired'])
                                        <span class="font-bold text-danger-700 bg-danger-50 px-1.5 py-0.2 rounded border border-danger-200">
                                            Kedaluwarsa ({{ $data['expired_asesmen_formatted'] }})
                                        </span>
                                    @elseif (!empty($data['expired_asesmen_formatted']))
                                        <span class="font-bold text-primary-700">
                                            Aktif s.d. {{ $data['expired_asesmen_formatted'] }}
                                        </span>
                                    @else
                                        <span class="text-surface-500">—</span>
                                    @endif
                                </div>
                            </div>
                        @endif
                    </div>

                    <!-- 3. Kepatuhan Masa Dinas (Tenure Governance) -->
                    <div class="p-4 bg-white rounded-2xl border border-surface-200 shadow-2xs space-y-3.5">
                        <div class="flex items-center justify-between pb-2.5 border-b border-surface-100">
                            <div class="flex items-center gap-2">
                                <span class="w-6 h-6 rounded-lg bg-info-100 text-info-800 flex items-center justify-center text-xs">
                                    <i class="fas fa-business-time" aria-hidden="true"></i>
                                </span>
                                <h4 class="text-xs font-bold text-surface-900 tracking-wide uppercase">
                                    Masa Dinas &amp; Batas Usia Kerja
                                </h4>
                            </div>
                            <span class="text-[10px] font-semibold text-info-700 bg-info-50 px-2 py-0.5 rounded-full border border-info-200">
                                Regulasi SDM
                            </span>
                        </div>

                        <div class="grid grid-cols-2 gap-3 text-xs">
                            <!-- Masa Jabatan -->
                            <div class="p-3 bg-surface-50 rounded-xl border border-surface-200/80">
                                <span class="text-[10px] text-surface-500 font-semibold block flex items-center gap-1">
                                    <i class="fas fa-clock text-primary-600 text-[10px]" aria-hidden="true"></i> Masa Jabatan
                                </span>
                                <div class="text-xs font-bold text-surface-900 mt-1">
                                    {{ $data['tenure_jabatan_formatted'] }}
                                </div>
                                <span class="text-[10px] text-surface-500 block mt-0.5 truncate">
                                    TMT: {{ !empty($data['tanggal_dalam_jabatan']) ? \Carbon\Carbon::parse($data['tanggal_dalam_jabatan'])->translatedFormat('d M Y') : '—' }}
                                </span>
                            </div>

                            <!-- Masa Unit Kerja -->
                            <div class="p-3 bg-surface-50 rounded-xl border border-surface-200/80">
                                <span class="text-[10px] text-surface-500 font-semibold block flex items-center gap-1">
                                    <i class="fas fa-map-location-dot text-info text-[10px]" aria-hidden="true"></i> Masa Unit Kerja
                                </span>
                                <div class="text-xs font-bold text-surface-900 mt-1">
                                    {{ $data['tenure_unit_formatted'] }}
                                </div>
                                <div class="mt-1">
                                    @if ($data['is_tenure_unit_compliant'])
                                        <span class="inline-flex items-center gap-1 text-[10px] font-bold text-primary-700 bg-primary-50 px-1.5 py-0.5 rounded border border-primary-200">
                                            <i class="fas fa-check-circle text-[10px]" aria-hidden="true"></i> &ge; 2 Tahun
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1 text-[10px] font-bold text-warning-700 bg-warning-50 px-1.5 py-0.5 rounded border border-warning-200" title="Aturan standar: minimal 2-3 tahun sebelum mutasi antar-unit">
                                            <i class="fas fa-triangle-exclamation text-[10px]" aria-hidden="true"></i> &lt; 2 Tahun
                                        </span>
                                    @endif
                                </div>
                            </div>
                        </div>

                        <!-- Usia & Proyeksi Pensiun (56 Th) / MBT (H-6 bulan) -->
                        <div class="p-3 rounded-xl bg-surface-50 border border-surface-200/80 space-y-1.5 text-[11px]">
                            <div class="flex items-center justify-between">
                                <span class="text-surface-500">Usia Karyawan:</span>
                                <span class="font-bold text-surface-900">{{ $data['usia_formatted'] }}</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-surface-500">Batas Usia Pensiun (56 Th):</span>
                                <span class="font-bold text-surface-800">{{ $data['tanggal_pensiun_formatted'] ?: '—' }}</span>
                            </div>
                            <div class="flex items-center justify-between pt-1 border-t border-surface-200/60">
                                <span class="text-surface-500">Sisa Masa Tugas:</span>
                                <span class="font-bold {{ $data['is_mendekati_pensiun'] ? 'text-danger-700 font-extrabold' : 'text-primary-800' }}">
                                    {{ $data['sisa_pensiun_formatted'] }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- 4. Riwayat Pelatihan & Sertifikasi L&D -->
                    <div class="p-4 bg-white rounded-2xl border border-surface-200 shadow-2xs space-y-3.5">
                        <div class="flex items-center justify-between pb-2.5 border-b border-surface-100">
                            <div class="flex items-center gap-2">
                                <span class="w-6 h-6 rounded-lg bg-earth-100 text-earth-800 flex items-center justify-center text-xs">
                                    <i class="fas fa-graduation-cap" aria-hidden="true"></i>
                                </span>
                                <h4 class="text-xs font-bold text-surface-900 tracking-wide uppercase">
                                    Pengembangan &amp; Pelatihan (L&amp;D)
                                </h4>
                            </div>
                            <span class="text-[10px] font-semibold text-earth-700 bg-earth-50 px-2 py-0.5 rounded-full border border-earth-200">
                                Kompetensi
                            </span>
                        </div>

                        @if (!empty($data['trainings']) && count($data['trainings']) > 0)
                            <div class="space-y-2.5">
                                @foreach ($data['trainings'] as $tr)
                                    <div class="p-2.5 bg-surface-50 rounded-xl border border-surface-200/80 flex items-start justify-between gap-3 text-xs">
                                        <div class="min-w-0">
                                            <div class="font-bold text-surface-900 leading-tight">
                                                {{ $tr['judul'] }}
                                            </div>
                                            <div class="text-[10px] text-surface-500 mt-1 flex items-center gap-2">
                                                <span>{{ $tr['tanggal_mulai'] }}</span>
                                                &bull;
                                                <span>{{ $tr['penyelenggara'] }}</span>
                                            </div>
                                        </div>
                                        @if ($tr['has_sertifikat'])
                                            <span class="shrink-0 inline-flex items-center gap-1 text-[10px] font-bold text-primary-700 bg-primary-50 px-1.5 py-0.5 rounded border border-primary-200">
                                                <i class="fas fa-certificate text-[10px]" aria-hidden="true"></i> Bersertifikat
                                            </span>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <div class="p-4 bg-surface-50/60 rounded-xl border border-surface-200/60 text-center">
                                <p class="text-xs text-surface-500">Belum ada riwayat pelatihan eksternal/holding yang tercatat.</p>
                            </div>
                        @endif
                    </div>

                    <!-- 5. Dasar SK Jabatan Aktif -->
                    @if (!empty($data['last_sk_nomor']))
                        <div class="p-3 bg-surface-50 rounded-2xl border border-surface-200 text-xs flex items-center justify-between gap-3">
                            <div class="min-w-0">
                                <span class="text-[10px] text-surface-500 block font-medium">Dasar SK Jabatan Saat Ini:</span>
                                <span class="font-mono font-bold text-surface-800 text-[11px] truncate block mt-0.5">
                                    {{ $data['last_sk_nomor'] }}
                                </span>
                                @if (!empty($data['last_sk_tanggal']))
                                    <span class="text-[10px] text-surface-500 block mt-0.5">
                                        Ditetapkan: {{ $data['last_sk_tanggal'] }}
                                    </span>
                                @endif
                            </div>
                            @if (!empty($data['last_job_id']) && !empty($data['has_last_sk_file']))
                                <a href="{{ route('admin.mutasi.downloadSk', $data['last_job_id']) }}" target="_blank"
                                   class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold text-primary-700 bg-white hover:bg-primary-50 border border-surface-300 transition shrink-0 shadow-2xs">
                                    <i class="fas fa-arrow-up-right-from-square text-[10px]" aria-hidden="true"></i>
                                    <span>Unduh SK</span>
                                </a>
                            @endif
                        </div>
                    @endif

                </div>

                <!-- Drawer Footer -->
                <div class="px-6 py-4 bg-surface-50 border-t border-surface-200 flex items-center justify-end shrink-0">
                    <button type="button" wire:click="closeTalentDrawer"
                            class="px-5 py-2 rounded-xl bg-surface-200 hover:bg-surface-300 text-surface-800 text-xs font-bold transition cursor-pointer">
                        Tutup Panel
                    </button>
                </div>

            </div>
        </div>
    </div>
@endif
