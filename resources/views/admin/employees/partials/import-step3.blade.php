<!-- ============================================================ -->
<!-- STEP 3: WORKBENCH RINGKASAN EKSEKUTIF & AKSI OPERASIONAL      -->
<!-- (RASIO ENTERPRISE 8:4 / 67% VS 33% — LEBAR LEGA & KONSISTEN) -->
<!-- ============================================================ -->
<div x-show="currentStep === 3" x-transition.opacity.duration.300ms class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-stretch">
    
    <!-- KOLOM KIRI (lg:col-span-8): RINGKASAN EKSEKUTIF, DETAIL BERKAS, 4 KPI CARD & PANEL KARANTINA -->
    <div class="lg:col-span-8 bg-white rounded-2xl border border-slate-200/80 p-6 shadow-xs flex flex-col justify-between space-y-5 h-full">
        <div class="space-y-4">
            <!-- Header -->
            <div class="border-b border-slate-100 pb-4">
                <div class="flex items-center gap-3.5">
                    <div class="w-12 h-12 rounded-xl bg-emerald-600 text-white flex items-center justify-center text-xl shrink-0 shadow-sm">
                        <i class="fas fa-clipboard-check"></i>
                    </div>
                    <div>
                        <h3 class="text-base sm:text-lg font-black text-slate-900">Ringkasan Eksekutif Impor Pegawai</h3>
                        <p class="text-xs text-slate-500 mt-1 leading-relaxed">
                            Berita acara pemutakhiran master data kepegawaian dan pencatatan riwayat penugasan resmi.
                        </p>
                    </div>
                </div>
            </div>

            <!-- File & Session Details (Full Width Grid tanpa Truncate Agresif) -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5 text-xs bg-slate-50/70 p-3.5 rounded-xl border border-slate-200/60">
                <div class="flex items-center gap-2.5 min-w-0">
                    <i class="fas fa-file-excel text-emerald-600 text-base shrink-0"></i>
                    <div class="min-w-0">
                        <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 block">Berkas Spreadsheet</span>
                        <span class="font-bold text-slate-800 text-xs block truncate" :title="importFileName" x-text="importFileName || '-'"></span>
                    </div>
                </div>

                <div class="flex items-center gap-2.5 min-w-0">
                    <i class="fas fa-clock text-slate-400 text-base shrink-0"></i>
                    <div class="min-w-0">
                        <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 block">Waktu Selesai Eksekusi</span>
                        <span class="font-bold text-slate-800 text-xs block" x-text="commitTimestamp || 'Selesai'"></span>
                    </div>
                </div>

                <div class="flex items-center gap-2.5 min-w-0">
                    <i class="fas fa-hard-drive text-slate-400 text-base shrink-0"></i>
                    <div class="min-w-0">
                        <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 block">Ukuran Berkas</span>
                        <span class="font-bold text-slate-800 text-xs block" x-text="importFileSize || '-'"></span>
                    </div>
                </div>

                <div class="flex items-center gap-2.5 min-w-0">
                    <i class="fas fa-user-shield text-emerald-600 text-base shrink-0"></i>
                    <div class="min-w-0">
                        <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 block">Otorisator Sistem</span>
                        <span class="font-bold text-slate-800 text-xs block">{{ auth()->user()->name ?? 'Administrator SDM' }}</span>
                    </div>
                </div>
            </div>

            <!-- 4-KPI Metric Cards (Inside Left Column with Generous col-span-8 Width) -->
            <div>
                <h4 class="text-xs font-bold uppercase tracking-wider text-slate-400 mb-2.5 flex items-center justify-between">
                    <span>Hasil Pemrosesan Batch Data</span>
                    <span class="text-[11px] font-normal text-slate-500 lowercase">otomasi terverifikasi</span>
                </h4>
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                    <x-admin.kpi-card
                        title="Perubahan"
                        x-text="commitSummary?.updated ?? 0"
                        subtitle="Atribut disesuaikan"
                        icon="fas fa-pen-to-square"
                        theme="amber" />

                    <x-admin.kpi-card
                        title="Pegawai Baru"
                        x-text="commitSummary?.created ?? 0"
                        subtitle="Data ditambahkan"
                        icon="fas fa-user-plus"
                        theme="emerald" />

                    <x-admin.kpi-card
                        title="Data Tetap"
                        x-text="commitSummary?.unchanged ?? 0"
                        subtitle="Sudah mutakhir"
                        icon="fas fa-shield-halved"
                        theme="blue" />

                    <x-admin.kpi-card
                        title="Terkendala"
                        x-text="commitSummary?.skipped ?? 0"
                        subtitle="Perlu koreksi"
                        icon="fas fa-triangle-exclamation"
                        theme="red" />
                </div>
            </div>
        </div>

        <!-- Panel Koreksi Kontekstual di Bawah -->
        <div class="pt-2">
            <!-- Kasus A: Jika skipped > 0 (Muncul Alert Kendala & Tombol Unduh Berkas Koreksi) -->
            <template x-if="commitSummary?.skipped > 0">
                <div class="p-4 rounded-xl bg-rose-50/80 border border-rose-200 flex flex-col sm:flex-row sm:items-center justify-between gap-3 text-xs">
                    <div class="flex items-start gap-3 text-rose-950 min-w-0">
                        <div class="w-8 h-8 rounded-lg bg-rose-100 text-rose-600 flex items-center justify-center shrink-0 mt-0.5 shadow-2xs">
                            <i class="fas fa-triangle-exclamation text-sm"></i>
                        </div>
                        <div class="min-w-0">
                            <p class="font-bold">
                                Terdapat <span x-text="commitSummary?.skipped"></span> baris data yang belum memenuhi syarat validasi.
                            </p>
                            <p class="text-[11px] text-rose-800/90 mt-0.5 leading-relaxed">
                                Anda dapat langsung mengunduh berkas berisi baris terkendala untuk diperbaiki di Excel dan diunggah ulang.
                            </p>
                        </div>
                    </div>
                    <form action="{{ route('admin.employees.import.downloadFailed') }}" method="POST" target="_blank" class="shrink-0 w-full sm:w-auto">
                        @csrf
                        <input type="hidden" name="token" :value="importToken">
                        <button type="submit"
                            style="background-color: #B91C1C !important; color: #FFFFFF !important;"
                            class="w-full sm:w-auto inline-flex items-center justify-center gap-1.5 px-3.5 py-1.5 rounded-lg bg-red-700 hover:bg-red-800 active:scale-[0.99] text-white text-xs font-bold transition shadow-2xs cursor-pointer border border-red-800 hover:shadow-xs">
                            <i class="fas fa-file-arrow-down text-xs"></i>
                            <span>Unduh Berkas Koreksi</span>
                        </button>
                    </form>
                </div>
            </template>

            <!-- Kasus B: Jika skipped === 0 (100% Sempurna) -->
            <template x-if="!commitSummary?.skipped || commitSummary?.skipped === 0">
                <div class="p-3.5 rounded-xl bg-emerald-50/80 border border-emerald-200 flex items-center gap-3 text-xs text-emerald-950">
                    <div class="w-8 h-8 rounded-lg bg-emerald-100 text-emerald-700 flex items-center justify-center shrink-0">
                        <i class="fas fa-circle-check text-sm"></i>
                    </div>
                    <div>
                        <p class="font-bold">100% Data Spreadsheet Berhasil Disimpan Sempurna</p>
                        <p class="text-[11px] text-emerald-800 mt-0.5">
                            Seluruh baris spreadsheet memenuhi spesifikasi kamus data dan aturan tata kelola kepegawaian tanpa ada baris yang terkendala.
                        </p>
                    </div>
                </div>
            </template>
        </div>
    </div>

    {{-- KOLOM KANAN: JAMINAN TATA KELOLA SDM & ACTION DOCK (Tahap 3) --}}
    @include('admin.employees.partials.import-step3-governance')

</div>
