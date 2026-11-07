{{-- Riwayat Mutasi di atas lapisan tabel data bersama (referensi: tests/Fixtures/DataTable/FixtureTable.php, ENGINEERING.md §2.3). --}}
@php
    $rows = $this->rows;
    $columns = $this->columns();
    $selectable = $this->bulkActions() !== [];
    $pageKeys = $this->tablePageKeys();
    // Aktivasi/pembatalan menampilkan kerangka pemuatan; membuka popup detail atau gerbong tidak.
    $loadingTargets = $this->tableLoadingTargets('applyNow', 'cancelScheduled', 'applyAllDue');
    $columnCount = $this->tableColumnCount();
@endphp
<x-admin.table.card :notice="$tableNotice">
    {{-- Wilayah live permanen: hasil filter diumumkan walau penomoran halaman tidak tampil. --}}
    <div class="sr-only" role="status" aria-live="polite" aria-atomic="true">{{ number_format($rows->total()) }} riwayat ditemukan</div>

    {{-- Tanpa tombol pintas: tabel ini juga disematkan sebagai tab di halaman Mutasi & Penempatan. --}}
    <x-admin.table.toolbar
        :table-id="$this->getId()"
        :filters="$this->filters()"
        :filter-values="$this->activeFilterValues()"
        :export="$this->exportName() !== null"
        :has-active-filters="$this->hasActiveFilters()"
        :search="$search"
        :search-shortcut="null"
        search-placeholder="Cari nama karyawan, NIK, jabatan, atau unit..." />

    <div class="overflow-x-auto">
        <table class="w-full table-fixed text-left border-collapse text-xs" wire:loading.attr="aria-busy" wire:target="{{ $loadingTargets }}">
            <x-admin.table.head
                :columns="$columns"
                :sort-field="$sortField"
                :sort-direction="$sortDirection"
                :selectable="$selectable"
                :page-keys="$pageKeys"
                :selected="$selected"
                :select-all-matching="$selectAllMatching" />

            <tbody class="divide-y divide-surface-200 text-xs text-surface-800" wire:loading.delay.remove wire:target="{{ $loadingTargets }}">
                @forelse ($rows as $record)
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
                                    <button type="button" wire:click="applyNow({{ $record->id }})" wire:confirm="Aktifkan mutasi ini sekarang dan perbarui jabatan pegawai di master data?" wire:loading.attr="disabled" wire:target="applyNow"
                                        class="inline-flex items-center justify-center w-6 h-6 rounded-lg text-[10px] font-bold bg-primary-600 hover:bg-primary-700 text-white transition shadow-2xs cursor-pointer"
                                        title="Aktivasi mutasi sekarang (mendahului TMT)">
                                        <i class="fas fa-bolt text-[10px]" aria-hidden="true"></i>
                                    </button>

                                    <!-- Tombol Batalkan Jadwal -->
                                    <button type="button" wire:click="cancelScheduled({{ $record->id }})" wire:confirm="Yakin ingin membatalkan jadwal mutasi ini? Formasi target akan dilepaskan kembali." wire:loading.attr="disabled" wire:target="cancelScheduled"
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
                @empty
                    @if ($this->hasActiveFilters())
                        <x-admin.table.empty-state :colspan="$columnCount" icon="fas fa-filter" message="Tidak ada data yang sesuai dengan pencarian atau filter.">
                            <x-admin.button variant="secondary" size="sm" icon="fas fa-rotate-left" wire:click="resetFilters" target="resetFilters">Reset filter</x-admin.button>
                        </x-admin.table.empty-state>
                    @else
                        <x-admin.table.empty-state :colspan="$columnCount" icon="fas fa-history" message="Belum ada mutasi atau alih tugas yang tercatat dalam sistem." />
                    @endif
                @endforelse
            </tbody>

            <x-admin.table.skeleton :columns="$columnCount" :target="$loadingTargets" />
        </table>
    </div>

    <x-admin.table.pagination :paginator="$rows" noun="riwayat" :per-page-options="$this->perPageOptions()" />
</x-admin.table.card>
