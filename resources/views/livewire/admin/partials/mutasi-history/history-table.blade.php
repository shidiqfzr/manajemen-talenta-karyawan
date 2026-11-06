    <!-- ── 4. UNIFIED ENTERPRISE TABLE & FILTER CARD ── -->
    <div class="bg-white rounded-2xl border border-surface-200 shadow-xs overflow-hidden">
        <!-- Ambient Loading Bar -->
        <div wire:loading.delay class="w-full h-1 bg-gradient-to-r from-primary-600 via-primary-400 to-primary-600 motion-safe:animate-pulse"></div>

        {{-- Wilayah live permanen: hasil filter diumumkan walau penomoran halaman tidak tampil. --}}
        <div class="sr-only" role="status" aria-live="polite" aria-atomic="true">{{ number_format($history->total()) }} riwayat ditemukan</div>

        <!-- Integrated Controls Bar (Search & Specific Category Filters) -->
        <div class="p-3 sm:p-4 bg-surface-50/70 border-b border-surface-200">
            <div class="flex flex-col lg:flex-row items-stretch lg:items-center justify-between gap-2.5">
                <!-- Search Bar (Roomy & Responsive) -->
                <div class="relative flex-1 min-w-[260px]">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-surface-500">
                        <i class="fas fa-search text-xs" aria-hidden="true"></i>
                    </div>
                    <input type="text"
                        wire:model.live.debounce.300ms="search"
                        placeholder="Cari nama karyawan, NIK, jabatan, atau unit..."
                        class="w-full pl-8.5 pr-8 py-2 bg-white border border-surface-300 rounded-xl text-xs text-surface-900 placeholder-surface-400 focus:outline-none focus:ring-2 focus:ring-primary-100 focus:border-primary-600 transition shadow-2xs">
                    @if ($search)
                        <button type="button" wire:click="$set('search', '')" class="absolute inset-y-0 right-0 pr-2.5 flex items-center text-surface-500 hover:text-surface-600 cursor-pointer" title="Bersihkan pencarian">
                            <i class="fas fa-times-circle text-xs" aria-hidden="true"></i>
                        </button>
                    @endif
                </div>

                <!-- Dropdown Filters Group (Clean & Non-Redundant) -->
                <div class="flex flex-wrap items-center gap-2">
                    <!-- Unit Filter -->
                    <div class="relative flex-1 sm:flex-initial min-w-[200px]">
                        <div class="absolute inset-y-0 left-0 pl-2.5 flex items-center pointer-events-none text-surface-500">
                            <i class="fas fa-building text-[11px]" aria-hidden="true"></i>
                        </div>
                        <select wire:model.live="unitFilter"
                            class="w-full pl-7.5 pr-7 py-2 bg-white border border-surface-300 rounded-xl text-xs text-surface-800 focus:outline-none focus:ring-2 focus:ring-primary-100 focus:border-primary-600 transition appearance-none cursor-pointer shadow-2xs">
                            <option value="">Semua Unit Kerja ({{ count($units) }})</option>
                            @foreach ($units as $unit)
                                <option value="{{ $unit }}">{{ $unit }}</option>
                            @endforeach
                        </select>
                        <div class="absolute inset-y-0 right-0 pr-2.5 flex items-center pointer-events-none text-surface-500">
                            <i class="fas fa-chevron-down text-[10px]" aria-hidden="true"></i>
                        </div>
                    </div>

                    <!-- Jenis Mutasi Filter -->
                    <div class="relative flex-1 sm:flex-initial min-w-[170px]">
                        <div class="absolute inset-y-0 left-0 pl-2.5 flex items-center pointer-events-none text-surface-500">
                            <i class="fas fa-tag text-[11px]" aria-hidden="true"></i>
                        </div>
                        <select wire:model.live="mutasiFilter"
                            class="w-full pl-7.5 pr-7 py-2 bg-white border border-surface-300 rounded-xl text-xs text-surface-800 focus:outline-none focus:ring-2 focus:ring-primary-100 focus:border-primary-600 transition appearance-none cursor-pointer shadow-2xs">
                            <option value="">Semua Jenis Mutasi</option>
                            @foreach ($mutasiTypes as $type)
                                <option value="{{ $type }}">{{ \App\Models\JobHistory::labelFor($type) }}</option>
                            @endforeach
                        </select>
                        <div class="absolute inset-y-0 right-0 pr-2.5 flex items-center pointer-events-none text-surface-500">
                            <i class="fas fa-chevron-down text-[10px]" aria-hidden="true"></i>
                        </div>
                    </div>

                    <!-- Sumber Catatan Filter: bawaan hanya mutasi resmi; data awal & pembaruan data dapat ditampilkan -->
                    <div class="relative flex-1 sm:flex-initial min-w-[170px]">
                        <div class="absolute inset-y-0 left-0 pl-2.5 flex items-center pointer-events-none text-surface-500">
                            <i class="fas fa-layer-group text-[11px]" aria-hidden="true"></i>
                        </div>
                        <select wire:model.live="sumberFilter" aria-label="Sumber catatan"
                            class="w-full pl-7.5 pr-7 py-2 bg-white border border-surface-300 rounded-xl text-xs text-surface-800 focus:outline-none focus:ring-2 focus:ring-primary-100 focus:border-primary-600 transition appearance-none cursor-pointer shadow-2xs">
                            <option value="resmi">Mutasi Resmi</option>
                            <option value="semua">Semua Catatan</option>
                            @foreach ($sumberOptions as $key => $label)
                                @continue($key === \App\Models\JobHistory::SUMBER_MUTASI)
                                <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                        </select>
                        <div class="absolute inset-y-0 right-0 pr-2.5 flex items-center pointer-events-none text-surface-500">
                            <i class="fas fa-chevron-down text-[10px]" aria-hidden="true"></i>
                        </div>
                    </div>

                    <!-- Active Filter Pill Indicator (jika statusFilter bukan all) -->
                    @if ($statusFilter !== '')
                        <div class="inline-flex items-center gap-1.5 px-2.5 py-1.5 bg-primary-50 text-primary-800 border border-primary-200 rounded-xl text-xs font-semibold shadow-2xs">
                            <span class="w-1.5 h-1.5 rounded-full bg-primary-600"></span>
                            <span>Filter: {{ $statusFilter === 'applied' ? 'Aktif Menjabat' : ($statusFilter === 'scheduled' ? 'Terjadwal' : 'Siap Diaktifkan') }}</span>
                            <button type="button" wire:click="toggleStatusFilter('')" class="hover:text-primary-950 p-0.5 cursor-pointer" title="Lepas filter status">
                                <i class="fas fa-times text-[10px]" aria-hidden="true"></i>
                            </button>
                        </div>
                    @endif

                    <!-- Reset Filter Button -->
                    @if ($search || $unitFilter || $mutasiFilter || $statusFilter !== '' || $sumberFilter !== 'resmi')
                        <button type="button" wire:click="resetFilters"
                            class="inline-flex items-center gap-1.5 px-3 py-2 bg-white hover:bg-danger-50 hover:text-danger-700 text-surface-600 border border-surface-300 hover:border-danger-200 rounded-xl text-xs font-semibold transition cursor-pointer shadow-2xs"
                            title="Reset semua filter">
                            <i class="fas fa-rotate-left text-[11px]" aria-hidden="true"></i>
                            <span class="hidden sm:inline">Reset</span>
                        </button>
                    @endif

                    <!-- Export Audit Compliance Button (Excel / Inspektorat & BPK) -->
                    <button type="button" wire:click="exportAuditLog" wire:loading.attr="disabled"
                        class="inline-flex items-center gap-1.5 px-3 py-2 bg-primary-50 hover:bg-primary-100 text-primary-800 border border-primary-300 rounded-xl text-xs font-semibold transition cursor-pointer shadow-2xs shrink-0"
                        title="Unduh Berkas Audit Kepatuhan Mutasi Sesuai Filter (Excel .xlsx)">
                        <i class="fas fa-file-excel text-primary-600 text-xs" aria-hidden="true"></i>
                        <span wire:loading.remove wire:target="exportAuditLog">Ekspor Log</span>
                        <span wire:loading wire:target="exportAuditLog">Mengunduh...</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- Integrated Table Content -->
        @if ($history->isEmpty())
            <div class="flex flex-col items-center justify-center py-16 px-4 text-center">
                <div class="w-16 h-16 bg-surface-100 rounded-2xl flex items-center justify-center text-surface-500 text-2xl mb-3 border border-surface-200 shadow-2xs">
                    <i class="fas fa-history" aria-hidden="true"></i>
                </div>
                <h4 class="text-base font-bold text-surface-900">Tidak Ada Catatan Riwayat Mutasi</h4>
                <p class="text-xs text-surface-500 max-w-sm mt-1 leading-relaxed">
                    @if ($search || $unitFilter || $mutasiFilter || $statusFilter || $sumberFilter !== 'resmi')
                        Tidak ditemukan catatan dengan kriteria filter saat ini. Silakan bersihkan kata kunci pencarian.
                    @else
                        Belum ada mutasi atau alih tugas yang tercatat dalam sistem.
                    @endif
                </p>
                @if ($search || $unitFilter || $mutasiFilter || $statusFilter || $sumberFilter !== 'resmi')
                    <button wire:click="resetFilters"
                        class="mt-3 inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-xl bg-surface-100 hover:bg-surface-200 text-surface-800 text-xs font-semibold border border-surface-300 transition cursor-pointer">
                        <i class="fas fa-undo text-[10px]" aria-hidden="true"></i>
                        Reset Filter
                    </button>
                @endif
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full table-fixed text-left border-collapse text-xs">
                    <thead>
                        <tr class="bg-surface-50/90 border-b border-surface-200 text-[11px] font-bold text-surface-700 uppercase tracking-wider">
                            <x-admin.table.th sortable field="employee_nik" :sort-field="$sortField" :sort-direction="$sortDirection" class="py-3.5 px-4 w-[18%]">
                                Karyawan
                            </x-admin.table.th>
                            <x-admin.table.th sortable field="jabatan" :sort-field="$sortField" :sort-direction="$sortDirection" class="py-3.5 px-4 w-[24%]">
                                Jabatan &amp; Unit Kerja
                            </x-admin.table.th>
                            <x-admin.table.th sortable field="jenis_mutasi" :sort-field="$sortField" :sort-direction="$sortDirection" class="py-3.5 px-2 w-[12%] text-center">
                                Jenis &amp; Regulasi
                            </x-admin.table.th>
                            <x-admin.table.th sortable field="tmt_awal" :sort-field="$sortField" :sort-direction="$sortDirection" class="py-3.5 px-3 w-[12%]">
                                TMT Efektif
                            </x-admin.table.th>
                            <x-admin.table.th class="py-3.5 px-2 w-[12%] text-center">
                                Status
                            </x-admin.table.th>
                            <x-admin.table.th sortable field="nomor_sk" :sort-field="$sortField" :sort-direction="$sortDirection" class="py-3.5 px-3 w-[14%]">
                                Dasar SK
                            </x-admin.table.th>
                            <x-admin.table.th class="py-3.5 px-2 w-[8%] text-center">
                                Aksi
                            </x-admin.table.th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-surface-200 text-xs text-surface-800">
                        @foreach ($history as $record)
                            @php
                                $isActive = $record->is_applied && $record->tmt_akhir === null;
                                $isScheduled = ! $record->is_applied;
                            @endphp
                            <tr wire:key="history-row-{{ $record->id }}"
                                class="hover:bg-surface-50/75 transition duration-150 {{ $isScheduled ? 'bg-warning-50/20' : ($isActive ? 'bg-primary-50/15' : '') }}">
                                <!-- 1. Karyawan -->
                                <td class="py-3.5 px-4 align-middle">
                                    <div class="flex items-center gap-2.5 min-w-0">
                                        <div class="w-7 h-7 rounded-lg {{ $isScheduled ? 'bg-warning-100 text-warning-800 border border-warning-200' : 'bg-primary-100 text-primary-800 border border-primary-200' }} flex items-center justify-center text-[11px] font-bold shrink-0 shadow-2xs">
                                            {{ strtoupper(substr($record->employee?->nama ?? '?', 0, 1)) }}
                                        </div>
                                        <div class="min-w-0 flex-1">
                                            @if ($record->employee)
                                                <a href="{{ route('admin.employees.show', $record->employee_nik) }}"
                                                    class="font-semibold text-xs text-surface-900 hover:text-primary-700 transition block truncate leading-snug"
                                                    title="{{ $record->employee->nama }}">
                                                    {{ $record->employee->nama }}
                                                </a>
                                            @else
                                                <span class="font-semibold text-xs text-surface-900 block truncate leading-snug">{{ $record->employee_nik }}</span>
                                            @endif
                                            <span class="text-[10px] text-surface-500 font-mono tabular-nums block truncate mt-0.5">NIK: {{ $record->employee_nik }}</span>
                                        </div>
                                    </div>
                                </td>

                                <!-- 2. Jabatan & Unit Kerja -->
                                <td class="py-3.5 px-4 align-middle min-w-0">
                                    <div class="font-medium text-surface-900 text-xs leading-relaxed line-clamp-2" title="{{ $record->jabatan }}">
                                        {{ $record->jabatan }}
                                    </div>
                                    <div class="text-[11px] text-surface-500 mt-1 flex items-center gap-1.5 truncate" title="{{ $record->unit_kerja }}">
                                        <i class="fas fa-building text-[10px] text-surface-400 shrink-0" aria-hidden="true"></i>
                                        <span class="truncate">{{ $record->unit_kerja }}</span>
                                    </div>
                                </td>

                                <!-- 3. Jenis Mutasi & Kepatuhan -->
                                <td class="py-3.5 px-2 align-middle text-center whitespace-nowrap">
                                    <div class="inline-flex flex-col items-center gap-1">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold border {{ $record->jenis_mutasi_badge_class }}">
                                            {{ $record->jenis_mutasi_label }}
                                        </span>
                                        @if ($record->isRecordOnly())
                                            <span class="inline-flex items-center gap-1 px-1.5 py-0.2 rounded-full text-[10px] font-bold bg-info-50 text-info-800 border border-info-200"
                                                title="Catatan data kepegawaian, bukan SK mutasi resmi">
                                                <i class="fas fa-database text-[10px]" aria-hidden="true"></i> {{ $record->sumber_label }}
                                            </span>
                                        @endif
                                        @if ($record->is_dispensasi)
                                            <span class="inline-flex items-center gap-1 px-1.5 py-0.2 rounded-full text-[10px] font-bold bg-warning-50 text-warning-800 border border-warning-200"
                                                title="{{ $record->alasan_dispensasi ?? 'Dispensasi Khusus Direksi' }}">
                                                <i class="fas fa-shield text-[10px] text-warning-700" aria-hidden="true"></i> Dispensasi
                                            </span>
                                        @endif
                                    </div>
                                </td>

                                <!-- 4. TMT Efektif -->
                                <td class="py-3.5 px-3 align-middle text-xs">
                                    <div class="font-medium text-surface-900 tabular-nums text-xs">
                                        {{ $record->tmt_awal ? \Carbon\Carbon::parse($record->tmt_awal)->translatedFormat('d M Y') : '—' }}
                                    </div>
                                    @if ($isScheduled)
                                        <span class="text-[10px] text-warning-700 font-medium block mt-0.5 tabular-nums truncate" title="Jatuh tempo dlm {{ \Carbon\Carbon::parse($record->tmt_awal)->diffForHumans(null, true) }}">
                                            Jatuh tempo dlm {{ \Carbon\Carbon::parse($record->tmt_awal)->diffForHumans(null, true) }}
                                        </span>
                                    @else
                                        <span class="text-[10px] text-surface-500 block mt-0.5 tabular-nums truncate">
                                            Sampai: {{ $record->tmt_akhir ? \Carbon\Carbon::parse($record->tmt_akhir)->translatedFormat('d M Y') : 'Sekarang' }}
                                        </span>
                                    @endif
                                </td>

                                <!-- 5. Status Siklus Hidup (Minimalist Indicator Dot) -->
                                <td class="py-3.5 px-2 align-middle text-center whitespace-nowrap">
                                    <div class="flex items-center justify-center">
                                        @if ($isScheduled)
                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-warning-50 text-warning-800 border border-warning-200 text-[11px] font-bold shadow-2xs">
                                                <i class="fas fa-hourglass-half text-[10px] text-warning-700" aria-hidden="true"></i>
                                                <span>Staging (Belum Aktif)</span>
                                            </span>
                                        @elseif ($isActive)
                                            <span class="inline-flex items-center gap-1.5 text-xs font-semibold text-primary-800">
                                                <span class="w-2 h-2 rounded-full bg-primary-500 motion-safe:animate-pulse"></span>
                                                <span>Aktif Menjabat</span>
                                            </span>
                                        @else
                                            <span class="inline-flex items-center gap-1 text-xs text-surface-500 font-medium">
                                                <i class="fas fa-check text-[10px] text-surface-400" aria-hidden="true"></i>
                                                <span>Selesai</span>
                                            </span>
                                        @endif
                                    </div>
                                </td>

                                <!-- 6. Dasar SK & Rantai Kolektif (Clean Inline) -->
                                <td class="py-3.5 px-3 align-middle text-xs min-w-0">
                                    <div class="flex items-center gap-1.5 flex-wrap">
                                        @if ($record->nomor_sk)
                                            <span class="font-mono text-[10px] font-semibold text-surface-800 bg-surface-100 px-1.5 py-0.5 rounded border border-surface-200 tabular-nums truncate max-w-[130px] inline-block" title="{{ $record->nomor_sk }}">
                                                {{ $record->nomor_sk }}
                                            </span>
                                        @else
                                            <span class="text-surface-500 italic text-[10px]">—</span>
                                        @endif

                                        @if ($record->batch_id)
                                            <button type="button" wire:click="viewBatch('{{ $record->batch_id }}')"
                                                class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[10px] font-bold bg-primary-100 text-primary-800 border border-primary-200/70 hover:bg-primary-200 transition cursor-pointer shrink-0"
                                                title="Lihat seluruh gerbong mutasi berantai ini">
                                                <i class="fas fa-diagram-project text-[10px]" aria-hidden="true"></i>
                                                <span>Gerbong #{{ $record->chain_order ?? 1 }}</span>
                                            </button>
                                        @endif
                                    </div>
                                </td>

                                <!-- 7. Aksi Dedikasi (Dedicated Action Column) -->
                                <td class="py-3.5 px-2 align-middle text-center whitespace-nowrap">
                                    <div class="flex items-center justify-center gap-1">
                                        <!-- Tombol Detail Riwayat & Jejak Audit (Tersedia di Setiap Baris) -->
                                        <button type="button" wire:click="showDetail({{ $record->id }})"
                                            class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-xs font-semibold bg-white hover:bg-primary-50 text-surface-700 hover:text-primary-700 border border-surface-200 hover:border-primary-300 transition shadow-2xs cursor-pointer"
                                            title="Lihat Detail Riwayat &amp; Jejak Audit">
                                            <i class="fas fa-eye text-[10px] text-primary-600" aria-hidden="true"></i>
                                            <span>Detail</span>
                                        </button>

                                        @if ($isScheduled)
                                            <!-- Tombol Aktivasi Terjadwal -->
                                            <button type="button" wire:click="applyNow({{ $record->id }})" wire:confirm="Aktifkan mutasi ini sekarang dan perbarui jabatan pegawai di master data?"
                                                class="inline-flex items-center justify-center w-6 h-6 rounded-lg text-[10px] font-bold bg-primary-600 hover:bg-primary-700 text-white transition shadow-2xs cursor-pointer"
                                                title="Aktivasi mutasi sekarang (mendahului TMT)">
                                                <i class="fas fa-bolt text-[10px]" aria-hidden="true"></i>
                                            </button>

                                            <!-- Tombol Batalkan Jadwal -->
                                            <button type="button" wire:click="cancelScheduled({{ $record->id }})" wire:confirm="Yakin ingin membatalkan jadwal mutasi ini? Formasi target akan dilepaskan kembali."
                                                class="inline-flex items-center justify-center w-6 h-6 rounded-lg text-[10px] font-semibold bg-danger-50 hover:bg-danger-100 text-danger-700 border border-danger-200 transition cursor-pointer"
                                                title="Batalkan jadwal mutasi">
                                                <i class="fas fa-times text-[10px]" aria-hidden="true"></i>
                                            </button>
                                        @endif

                                        @if ($record->file_sk)
                                            <!-- Unduh Berkas SK PDF -->
                                            <a href="{{ route('admin.mutasi.downloadSk', $record->id) }}" target="_blank"
                                                class="inline-flex items-center justify-center w-6 h-6 rounded-lg text-[10px] font-semibold bg-surface-100 hover:bg-surface-200 text-surface-700 border border-surface-200 transition"
                                                title="Lihat Dokumen SK Digital (PDF)">
                                                <i class="fas fa-file-pdf text-danger" aria-hidden="true"></i>
                                            </a>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($history->hasPages())
                <div class="px-5 py-3.5 border-t border-surface-200 bg-surface-50 flex items-center justify-between">
                    <span class="text-xs text-surface-500">
                        Menampilkan <span class="font-bold text-surface-800">{{ $history->firstItem() }}</span> - <span class="font-bold text-surface-800">{{ $history->lastItem() }}</span> dari <span class="font-bold text-surface-800">{{ $history->total() }}</span> mutasi
                    </span>
                    <div>
                        {{ $history->links() }}
                    </div>
                </div>
            @endif
        @endif
    </div>
