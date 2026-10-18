<!-- ============================================================ -->
<!-- STEP 2: ANALISIS & PRATINJAU DIFF WORKBENCH (FULL VIEWPORT)  -->
<!-- ============================================================ -->
<div x-show="currentStep === 2" x-transition.opacity.duration.300ms class="space-y-6">
    
    <!-- Compact File Context Strip & Quick Actions -->
    <div class="bg-white rounded-xl border border-slate-200/80 px-4 py-2.5 shadow-2xs flex flex-wrap items-center justify-between gap-3 text-xs">
        <div class="flex items-center gap-2.5 min-w-0">
            <div class="w-7 h-7 rounded-lg bg-emerald-50 text-emerald-700 border border-emerald-200/80 flex items-center justify-center text-xs shrink-0">
                <i class="fas fa-file-excel"></i>
            </div>
            <div class="flex items-center gap-2 min-w-0 flex-wrap">
                <span class="text-slate-400 font-medium">Berkas Aktif:</span>
                <strong class="font-bold text-slate-800 truncate max-w-[200px] sm:max-w-xs md:max-w-md" x-text="importFileName"></strong>
                <span class="text-slate-300">•</span>
                <span class="text-slate-500 tabular-nums font-mono text-[11px]" x-text="importFileSize"></span>
                <span class="hidden md:inline-flex items-center gap-1 text-[11px] font-semibold text-emerald-700 bg-emerald-50/80 px-2 py-0.5 rounded-full border border-emerald-200/60">
                    <i class="fas fa-check-circle text-[10px]"></i> Analisis Selesai
                </span>
            </div>
        </div>

        <div class="flex items-center gap-2 shrink-0">
            <button type="button" @click="resetToStep1()"
                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-slate-200/80 bg-white hover:bg-slate-50 text-slate-700 text-xs font-semibold transition hover:border-slate-300 shadow-2xs cursor-pointer">
                <i class="fas fa-arrow-left text-[11px] text-slate-400"></i>
                <span>Ganti Berkas</span>
            </button>
        </div>
    </div>

    <!-- 5-CARD UNIFIED KPI TABS (PRIMARY FILTER WORKBENCH) -->
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3.5">
        <x-admin.kpi-card
            title="Semua Data"
            tab-key="all"
            x-text="validationSummary?.total_processed ?? 0"
            subtitle="Total spreadsheet"
            icon="fas fa-list"
            theme="slate" />

        <x-admin.kpi-card
            title="Perubahan"
            tab-key="updated"
            x-text="validationSummary?.updated ?? 0"
            subtitle="Ada penyesuaian atribut"
            icon="fas fa-pen-to-square"
            theme="amber" />

        <x-admin.kpi-card
            title="Pegawai Baru"
            tab-key="created"
            x-text="validationSummary?.created ?? 0"
            subtitle="Akan ditambahkan"
            icon="fas fa-user-plus"
            theme="emerald" />

        <x-admin.kpi-card
            title="Data Tetap"
            tab-key="unchanged"
            x-text="validationSummary?.unchanged ?? 0"
            subtitle="Data sudah mutakhir"
            icon="fas fa-shield-halved"
            theme="blue" />

        <x-admin.kpi-card
            title="Baris Terkendala"
            tab-key="errors"
            x-text="validationSummary?.skipped ?? 0"
            theme="red"
            icon="fas fa-triangle-exclamation"
            class="col-span-2 sm:col-span-1">
            <div class="flex items-center justify-between"
                :class="(validationSummary?.skipped ?? 0) > 0 ? 'text-red-700 font-semibold' : 'text-slate-400'">
                <span x-text="(validationSummary?.skipped ?? 0) > 0 ? 'Format belum sesuai' : 'Semua data valid'"></span>
                <span x-show="(validationSummary?.skipped ?? 0) > 0" class="text-[10px] font-bold text-red-600 underline hover:text-red-800">Periksa &rarr;</span>
            </div>
        </x-admin.kpi-card>
    </div>

    <!-- ============================================================ -->
    <!-- FULL-WIDTH TABBED WORKBENCH DENGAN FILTER & SEARCH CEPAT    -->
    <!-- ============================================================ -->
    <div class="bg-white rounded-2xl border border-slate-200/80 shadow-xs overflow-hidden flex flex-col">
        
        <!-- Workbench Header: Contextual Active View Title & Quick Search -->
        <div class="px-5 py-3.5 border-b border-slate-200 bg-slate-50/80 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <!-- Active View Title & Live Counter (Driven by Unified KPI Cards) -->
            <div class="flex items-center gap-2.5">
                <span class="w-2.5 h-2.5 rounded-full shrink-0"
                    :class="{
                        'bg-slate-700': activeFilterTab === 'all',
                        'bg-amber-500': activeFilterTab === 'updated',
                        'bg-emerald-500': activeFilterTab === 'created',
                        'bg-blue-500': activeFilterTab === 'unchanged',
                        'bg-danger': activeFilterTab === 'errors'
                    }"></span>
                <h4 class="text-xs font-black uppercase tracking-wider text-slate-800"
                    x-text="activeFilterTab === 'all' ? 'Semua Data Pegawai' : (activeFilterTab === 'updated' ? 'Data Perubahan Atribut' : (activeFilterTab === 'created' ? 'Data Pegawai Baru' : (activeFilterTab === 'unchanged' ? 'Data Tetap / Sudah Mutakhir' : 'Daftar Baris Terkendala')))">
                </h4>
                <span class="px-2 py-0.5 rounded-full text-[11px] font-mono font-extrabold bg-slate-200/80 text-slate-700"
                    x-text="filteredList.length + ' baris'"></span>
            </div>

            <!-- Live Search & Per Page Selector -->
            <div class="flex items-center gap-2.5 w-full sm:w-auto">
                <!-- Search Input -->
                <div class="relative flex-1 sm:w-64">
                    <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs pointer-events-none"></i>
                    <input type="text" x-model="previewSearch" @input="currentPage = 1"
                        placeholder="Cari NIK, nama, jabatan..."
                        class="w-full pl-8 pr-7 py-1.5 bg-white border border-slate-200 rounded-xl text-xs text-slate-800 placeholder-slate-400 focus:outline-hidden focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 shadow-2xs">
                    <button type="button" x-show="previewSearch" @click="previewSearch = ''; currentPage = 1"
                        class="absolute right-2.5 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 text-xs cursor-pointer">
                        <i class="fas fa-times-circle"></i>
                    </button>
                </div>

                <!-- Per Page Select -->
                <select x-model.number="perPage" @change="currentPage = 1"
                    class="py-1.5 px-2 bg-white border border-slate-200 rounded-xl text-xs text-slate-700 font-semibold focus:outline-hidden focus:border-emerald-500 shadow-2xs shrink-0 cursor-pointer">
                    <option :value="10">10 / hal</option>
                    <option :value="25">25 / hal</option>
                    <option :value="50">50 / hal</option>
                    <option :value="100">100 / hal</option>
                </select>
            </div>
        </div>

        <!-- TABEL 1: DATA PEGAWAI VALID (FOKUS 5 KOLOM, PAS 100% VIEWPORT TANPA HORIZONTAL SCROLL) -->
        <div x-show="activeFilterTab !== 'errors'" class="min-h-[380px] max-h-[540px] overflow-y-auto">
            <template x-if="paginatedRows.length === 0">
                <div class="p-16 text-center space-y-3">
                    <div class="w-14 h-14 mx-auto rounded-2xl bg-slate-50 text-slate-400 border border-slate-200 flex items-center justify-center text-2xl">
                        <i class="fas fa-inbox"></i>
                    </div>
                    <h4 class="text-sm font-bold text-slate-800">Tidak Ada Data yang Cocok</h4>
                    <p class="text-xs text-slate-500 max-w-md mx-auto">
                        Tidak ditemukan baris pegawai yang memenuhi kriteria filter atau kata kunci pencarian saat ini.
                    </p>
                </div>
            </template>

            <template x-if="paginatedRows.length > 0">
                <table class="w-full text-left border-collapse text-xs table-fixed">
                    <thead class="sticky top-0 bg-slate-100/95 backdrop-blur-xs border-b border-slate-200 text-slate-700 text-[11px] uppercase tracking-wider font-extrabold z-10 shadow-2xs">
                        <tr>
                            <th class="py-3 px-4 font-bold w-[26%]">Pegawai</th>
                            <th class="py-3 px-4 font-bold w-[30%]">Formasi &amp; Penempatan</th>
                            <th class="py-3 px-4 font-bold w-[18%]">Strata &amp; Golongan</th>
                            <th class="py-3 px-4 font-bold w-[12%] text-center">Status Aksi</th>
                            <th class="py-3 px-4 font-bold w-[14%] text-right pr-6">Rincian Perubahan</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-[11px] bg-white">
                        <template x-for="(row, idx) in paginatedRows" :key="'valid-' + (row.nik || idx)">
                            <tr class="hover:bg-slate-50/80 transition-colors">
                                <!-- Kolom 1: Pegawai (Nama + NIK) -->
                                <td class="py-3 px-4 align-top">
                                    <div class="font-extrabold text-slate-900 text-xs tracking-tight" x-text="row.nama"></div>
                                    <div class="text-[11px] text-slate-500 font-mono mt-0.5 flex items-center gap-1.5">
                                        <span>NIK:</span>
                                        <span class="font-bold text-slate-700" x-text="row.nik"></span>
                                    </div>
                                </td>
                                
                                <!-- Kolom 2: Formasi & Penempatan (Jabatan + Unit Kerja) -->
                                <td class="py-3 px-4 align-top">
                                    <div class="font-semibold text-slate-800 text-xs leading-snug" x-text="row.jabatan"></div>
                                    <div class="text-[11px] text-slate-500 mt-0.5 flex items-center gap-1">
                                        <i class="fas fa-building text-[10px] text-slate-400"></i>
                                        <span class="truncate" x-text="row.unit_kerja"></span>
                                    </div>
                                </td>
                                
                                <!-- Kolom 3: Strata & Golongan (Karpim/Karpel + Golongan/RM Band) -->
                                <td class="py-3 px-4 align-top">
                                    <div class="font-semibold text-slate-800 text-xs" x-text="row.level || '-'"></div>
                                    <div class="text-[11px] text-slate-500 font-mono mt-0.5">
                                        <span class="font-bold text-slate-700" x-text="row.golongan || '-'"></span>
                                        <span class="text-slate-400 mx-1">&bull;</span>
                                        <span class="text-slate-600 font-sans" x-text="row.rm_level || '-'"></span>
                                    </div>
                                </td>
                                
                                <!-- Kolom 4: Status Aksi (Pill Badge) -->
                                <td class="py-3 px-4 align-top text-center whitespace-nowrap">
                                    <template x-if="row.status === 'BARU'">
                                        <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full bg-emerald-100 text-emerald-800 border border-emerald-200 font-extrabold text-[10px] uppercase tracking-wider">
                                            <i class="fas fa-plus text-[8px]"></i> Baru
                                        </span>
                                    </template>
                                    <template x-if="row.status === 'UPDATE'">
                                        <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full bg-amber-100 text-amber-900 border border-amber-300 font-extrabold text-[10px] uppercase tracking-wider">
                                            <i class="fas fa-pen text-[8px] text-amber-700"></i> Update
                                        </span>
                                    </template>
                                    <template x-if="row.status === 'UNCHANGED' || row.status === 'TETAP'">
                                        <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full bg-slate-100 text-slate-700 border border-slate-200 font-bold text-[10px] uppercase tracking-wider">
                                            <i class="fas fa-check text-[8px] text-slate-500"></i> Tetap
                                        </span>
                                    </template>
                                </td>

                                <!-- Kolom 5: Rincian Perubahan (Trigger Modal Diff Inspeksi) -->
                                <td class="py-3 px-4 align-top text-right pr-6 whitespace-nowrap">
                                    <template x-if="row.diff && row.diff.length > 0">
                                        <div class="flex flex-col items-end gap-1">
                                            <button type="button" @click="openDiffModal(row)"
                                                class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-amber-50 hover:bg-amber-100 text-amber-900 border border-amber-300 text-[11px] font-bold transition shadow-2xs group cursor-pointer"
                                                :title="'Inspeksi ' + row.diff.length + ' perbedaan data'">
                                                <i class="fas fa-pen-to-square text-amber-600 text-[10px]"></i>
                                                <span x-text="row.diff.length + ' Perubahan'"></span>
                                                <span class="text-amber-700 group-hover:underline text-[9px] ml-0.5">Detail &rarr;</span>
                                            </button>
                                            <span class="text-[10px] text-slate-400 font-medium truncate max-w-[170px]"
                                                :title="row.diff.map(d => d.label || d.field).join(', ')"
                                                x-text="row.diff.map(d => d.label || d.field).join(', ')"></span>
                                        </div>
                                    </template>
                                    <template x-if="!row.diff || row.diff.length === 0">
                                        <span class="text-slate-400 font-medium text-[11px]">Data mutakhir</span>
                                    </template>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </template>
        </div>

        <!-- TABEL 2: BARIS TERKENDALA (ERRORS - SINGLE AUTHORITATIVE WORKBENCH) -->
        <div x-show="activeFilterTab === 'errors'" class="min-h-[380px] max-h-[540px] overflow-y-auto">
            <template x-if="validationErrors.length === 0">
                <div class="p-16 text-center space-y-3">
                    <div class="w-14 h-14 mx-auto rounded-2xl bg-emerald-50 text-emerald-600 border border-emerald-200 flex items-center justify-center text-2xl shadow-2xs">
                        <i class="fas fa-circle-check"></i>
                    </div>
                    <h4 class="text-sm font-bold text-slate-800">Sempurna! Tidak Ada Baris Terkendala</h4>
                    <p class="text-xs text-slate-500 max-w-sm mx-auto">
                        Seluruh baris spreadsheet berhasil melewati verifikasi format dan aturan bisnis master data organisasi.
                    </p>
                </div>
            </template>

            <template x-if="validationErrors.length > 0">
                <div>
                    <!-- Header Banner & Action Bar inside tab (Pusat Aksi Tunggal Koreksi) -->
                    <div class="py-3 px-4 sm:px-5 bg-gradient-to-r from-red-50 via-rose-50/70 to-amber-50/40 border-b border-red-200/80 flex flex-col sm:flex-row sm:items-center justify-between gap-3 text-xs">
                        <div class="flex items-center gap-3 text-slate-800">
                            <div class="w-8 h-8 rounded-lg bg-red-100 text-red-700 border border-red-200 flex items-center justify-center shrink-0 shadow-2xs">
                                <i class="fas fa-triangle-exclamation text-sm"></i>
                            </div>
                            <div class="space-y-0.5">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <h4 class="font-extrabold text-slate-900 text-xs sm:text-sm">
                                        <span x-text="validationErrors.length"></span> Baris Terkendala Validasi
                                    </h4>
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[9px] font-extrabold bg-red-100 text-red-800 border border-red-200 uppercase tracking-wider">
                                        <i class="fas fa-ban text-[7px]"></i> Tidak Diimpor
                                    </span>
                                </div>
                                <p class="text-[11px] text-slate-600 leading-snug max-w-2xl">
                                    Unduh berkas koreksi di samping untuk memeriksa dan memperbaiki data di Excel, lalu unggah kembali.
                                </p>
                            </div>
                        </div>

                        <!-- Tombol Unduh Berkas Koreksi Ber-Kontras Tinggi & Proporsional -->
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

                    <!-- TABEL ERROR BERSTANDAR ENTERPRISE SLATE DENGAN 5 KOLOM SEIMBANG -->
                    <table class="w-full text-left border-collapse text-xs table-fixed">
                        <thead class="sticky top-0 bg-slate-100/95 backdrop-blur-xs border-b border-slate-200 text-slate-700 text-[11px] uppercase tracking-wider font-extrabold z-10 shadow-2xs">
                            <tr>
                                <th class="py-2.5 px-4 font-bold w-20">Baris</th>
                                <th class="py-2.5 px-4 font-bold w-[22%]">Pegawai Terkait</th>
                                <th class="py-2.5 px-4 font-bold w-[24%]">Formasi &amp; Penempatan</th>
                                <th class="py-2.5 px-4 font-bold w-[12%] text-center">Status</th>
                                <th class="py-2.5 px-4 font-bold w-[42%] pr-6">Penyebab Kendala</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-[11px] bg-white">
                            <template x-for="(err, idx) in paginatedRows" :key="'err-' + (err.row || idx)">
                                <tr class="hover:bg-red-50/20 transition-colors">
                                    <!-- Kolom 1: Baris (Nomor Polos Monospace) -->
                                    <td class="py-2.5 px-4 align-top whitespace-nowrap">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-md bg-slate-100 text-slate-700 border border-slate-200 font-mono font-bold text-xs" x-text="err.row"></span>
                                    </td>

                                    <!-- Kolom 2: Pegawai (Nama + NIK) -->
                                    <td class="py-2.5 px-4 align-top">
                                        <div class="font-extrabold text-slate-900 text-xs tracking-tight" x-text="err.nama || '-'"></div>
                                        <div class="text-[11px] text-slate-500 font-mono mt-0.5 flex items-center gap-1.5">
                                            <span>NIK:</span>
                                            <span class="font-bold text-slate-700" x-text="err.nik || '-'"></span>
                                        </div>
                                    </td>

                                    <!-- Kolom 3: Formasi & Penempatan -->
                                    <td class="py-2.5 px-4 align-top">
                                        <div class="font-semibold text-slate-800 text-xs leading-snug truncate" :title="err.jabatan" x-text="(err.jabatan && err.jabatan !== '-') ? err.jabatan : 'Posisi tidak terbaca'"></div>
                                        <div class="text-[11px] text-slate-500 mt-0.5 flex items-center gap-1">
                                            <i class="fas fa-building text-[10px] text-slate-400"></i>
                                            <span class="truncate" :title="err.unit_kerja" x-text="(err.unit_kerja && err.unit_kerja !== '-') ? err.unit_kerja : '-'"></span>
                                        </div>
                                    </td>

                                    <!-- Kolom 4: Status (Pill Badge Perlu Koreksi) -->
                                    <td class="py-2.5 px-4 align-top text-center whitespace-nowrap">
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-red-100 text-red-800 border border-red-200 font-extrabold text-[10px] uppercase tracking-wider">
                                            <i class="fas fa-circle-exclamation text-[8px]"></i> Perlu Koreksi
                                        </span>
                                    </td>

                                    <!-- Kolom 5: Rincian Kendala (Ringkas & Informatif) -->
                                    <td class="py-2.5 px-4 align-top pr-6">
                                        <div class="space-y-1">
                                            <template x-for="(errMsg, msgIdx) in (Array.isArray(err.errors) ? err.errors : [err.errors])" :key="'msg-' + msgIdx">
                                                <div class="flex items-start gap-1.5 text-xs text-rose-800 font-medium leading-relaxed">
                                                    <i class="fas fa-circle-exclamation text-[11px] mt-0.5 shrink-0 text-red-600"></i>
                                                    <span x-text="errMsg"></span>
                                                </div>
                                            </template>
                                        </div>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </template>
        </div>

        <!-- PAGINATION BAR (PAGINATION LOKAL ENTERPRISE) -->
        <div class="px-6 py-3 bg-white border-t border-slate-200/80 flex flex-col sm:flex-row items-center justify-between gap-3 text-xs text-slate-500">
            <div>
                Menampilkan <strong class="text-slate-800 font-bold" x-text="filteredList.length === 0 ? 0 : ((currentPage - 1) * perPage + 1)"></strong> sampai <strong class="text-slate-800 font-bold" x-text="Math.min(currentPage * perPage, filteredList.length)"></strong> dari <strong class="text-slate-800 font-bold" x-text="filteredList.length"></strong> baris
                <span x-show="previewSearch" class="text-emerald-700 font-semibold">(terfilter)</span>
            </div>

            <div class="flex items-center gap-1.5" x-show="totalPages > 1">
                <button type="button" @click="prevPage()" :disabled="currentPage === 1"
                    class="px-2.5 py-1 rounded-lg border border-slate-200 bg-white hover:bg-slate-50 text-slate-700 font-bold disabled:opacity-40 disabled:cursor-not-allowed transition shadow-2xs cursor-pointer">
                    <i class="fas fa-chevron-left text-[10px]"></i>
                </button>
                
                <span class="px-2 text-slate-700 font-bold">
                    Halaman <span x-text="currentPage"></span> / <span x-text="totalPages"></span>
                </span>

                <button type="button" @click="nextPage()" :disabled="currentPage === totalPages"
                    class="px-2.5 py-1 rounded-lg border border-slate-200 bg-white hover:bg-slate-50 text-slate-700 font-bold disabled:opacity-40 disabled:cursor-not-allowed transition shadow-2xs cursor-pointer">
                    <i class="fas fa-chevron-right text-[10px]"></i>
                </button>
            </div>
        </div>

        <!-- Footer Action Bar -->
        <div class="p-4 bg-slate-50 border-t border-slate-200 flex flex-col sm:flex-row items-center justify-between gap-3">
            <div class="text-xs text-slate-500">
                <span x-show="sampleRows.length > 0">
                    Siap mengimpor <strong class="text-emerald-700 font-extrabold" x-text="sampleRows.length"></strong> data pegawai yang valid.
                </span>
                <span x-show="sampleRows.length === 0" class="text-danger font-semibold">
                    Semua data ditolak. Silakan unduh baris terkendala untuk diperbaiki.
                </span>
            </div>

            <div class="flex items-center gap-3 w-full sm:w-auto">
                <button type="button" @click="resetToStep1()"
                    class="flex-1 sm:flex-initial px-4 py-2.5 rounded-xl border border-slate-200 bg-white hover:bg-slate-50 text-slate-700 text-xs font-bold transition shadow-2xs cursor-pointer">
                    Batal
                </button>

                <!-- Tombol Konfirmasi Impor Data Valid -->
                <button type="button"
                    @click="executeCommitImport()"
                    :disabled="sampleRows.length === 0 || isCommitting"
                    class="flex-1 sm:flex-initial px-5 py-2.5 rounded-xl text-xs sm:text-sm font-extrabold text-white transition flex items-center justify-center gap-2 shadow-xs cursor-pointer"
                    :class="(sampleRows.length === 0 || isCommitting) ? 'bg-slate-300 cursor-not-allowed' : 'bg-emerald-600 hover:bg-emerald-700 active:scale-[0.99] shadow-md shadow-emerald-600/20'">
                    
                    <template x-if="!isCommitting">
                        <span class="inline-flex items-center gap-2">
                            <i class="fas fa-check-circle"></i>
                            <span x-text="'Konfirmasi & Impor ' + sampleRows.length + ' Data Valid'"></span>
                        </span>
                    </template>

                    <template x-if="isCommitting">
                        <span class="inline-flex items-center gap-2">
                            <i class="fas fa-circle-notch fa-spin"></i>
                            <span>Menyimpan Data Pegawai...</span>
                        </span>
                    </template>
                </button>
            </div>
        </div>
    </div>
</div>
