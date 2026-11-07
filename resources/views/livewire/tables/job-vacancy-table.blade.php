<div class="space-y-4">
    <!-- Success Flash Message -->
    @if (session()->has('vacancy_success'))
        <div role="status" class="p-4 bg-primary-50 border border-primary-200 text-primary-900 rounded-lg shadow-sm flex items-center justify-between animate-fade-in">
            <div class="flex items-center gap-3">
                <div class="w-8 h-8 rounded-full bg-primary-600 text-white flex items-center justify-center shrink-0">
                    <i class="fas fa-check text-xs" aria-hidden="true"></i>
                </div>
                <span class="text-sm font-semibold">{{ session('vacancy_success') }}</span>
            </div>
            <button aria-label="Tutup notifikasi" type="button" class="text-primary-700 hover:text-primary-900 p-1" onclick="this.parentElement.remove()">
                <i class="fas fa-times text-xs" aria-hidden="true"></i>
            </button>
        </div>
    @endif

    {{-- Vacancy list on the shared table components (collection-backed, so no WithDataTable). --}}
    <x-admin.table.card>
        <x-admin.table.toolbar
            :table-id="$this->getId()"
            :filters="$filters"
            :filter-values="$filterValues"
            :has-active-filters="$hasActiveFilters"
            :search="$search"
            search-placeholder="Cari jabatan, unit kerja, nama, atau NIK..." />
        <x-admin.table.header title="Daftar Jabatan Lowong" :paginator="$vacancies" noun="Lowongan" />

        <!-- Tier 2: Sub-Header Urgency Status Strip -->
        <div class="px-5 py-2.5 bg-surface-50 border-t border-b border-surface-200 flex flex-wrap items-center justify-between gap-3">
            <div class="flex flex-wrap items-center gap-1.5 sm:gap-2">
                <span class="text-xs font-medium text-surface-500 mr-1 flex items-center gap-1.5">
                    <i class="fas fa-clock text-[11px] text-surface-400" aria-hidden="true"></i> Status Durasi:
                </span>

                <!-- All -->
                <button type="button" wire:click="$set('severityFilter', '')"
                    class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold transition cursor-pointer {{ $severityFilter === '' ? 'bg-surface-900 text-white shadow-2xs' : 'bg-white text-surface-700 hover:bg-surface-100 border border-surface-200' }}">
                    <span>Semua Formasi</span>
                    <span class="px-1.5 py-0.2 rounded-full text-[10px] {{ $severityFilter === '' ? 'bg-white/20 text-white' : 'bg-surface-100 text-surface-700 font-bold' }}">
                        {{ $counts['all'] }}
                    </span>
                </button>

                <!-- Critical (>90 days) -->
                <button type="button" wire:click="$set('severityFilter', 'red')"
                    class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold transition cursor-pointer {{ $severityFilter === 'red' ? 'bg-danger text-white shadow-2xs' : 'bg-white text-danger-700 hover:bg-danger-50 border border-danger-200' }}">
                    <span class="w-1.5 h-1.5 rounded-full {{ $severityFilter === 'red' ? 'bg-white' : 'bg-danger' }}"></span>
                    <span>Kritis (&gt; 90 hari)</span>
                    <span class="px-1.5 py-0.2 rounded-full text-[10px] {{ $severityFilter === 'red' ? 'bg-white/20 text-white' : 'bg-danger-100 text-danger-700 font-bold' }}">
                        {{ $counts['critical'] }}
                    </span>
                </button>

                <!-- Moderate (30-90 days) -->
                <button type="button" wire:click="$set('severityFilter', 'yellow')"
                    class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold transition cursor-pointer {{ $severityFilter === 'yellow' ? 'bg-warning-700 text-white shadow-2xs' : 'bg-white text-warning-800 hover:bg-warning-50 border border-warning-200' }}">
                    <span class="w-1.5 h-1.5 rounded-full {{ $severityFilter === 'yellow' ? 'bg-white' : 'bg-warning-700' }}"></span>
                    <span>Perhatian (30 - 90 hari)</span>
                    <span class="px-1.5 py-0.2 rounded-full text-[10px] {{ $severityFilter === 'yellow' ? 'bg-white/20 text-white' : 'bg-warning-100 text-warning-800 font-bold' }}">
                        {{ $counts['moderate'] }}
                    </span>
                </button>

                <!-- Recent (<30 days) -->
                <button type="button" wire:click="$set('severityFilter', 'green')"
                    class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold transition cursor-pointer {{ $severityFilter === 'green' ? 'bg-primary-600 text-white shadow-2xs' : 'bg-white text-primary-800 hover:bg-primary-50 border border-primary-200' }}">
                    <span class="w-1.5 h-1.5 rounded-full {{ $severityFilter === 'green' ? 'bg-white' : 'bg-primary-600' }}"></span>
                    <span>Baru Kosong (&lt; 30 hari)</span>
                    <span class="px-1.5 py-0.2 rounded-full text-[10px] {{ $severityFilter === 'green' ? 'bg-white/20 text-white' : 'bg-primary-100 text-primary-800 font-bold' }}">
                        {{ $counts['recent'] }}
                    </span>
                </button>
            </div>

        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-surface-50 border-b border-surface-200 text-xs font-semibold text-surface-700 uppercase tracking-wider">
                        <x-admin.table.th class="py-3.5 px-5">Unit Kerja</x-admin.table.th>
                        <x-admin.table.th class="py-3.5 px-5">Jabatan Lowong</x-admin.table.th>
                        <x-admin.table.th class="py-3.5 px-5">Pejabat Terakhir</x-admin.table.th>
                        <x-admin.table.th class="py-3.5 px-5">Kosong Sejak</x-admin.table.th>
                        <x-admin.table.th align="center" class="py-3.5 px-5">Durasi &amp; Urgensi</x-admin.table.th>
                        <x-admin.table.th align="center" class="py-3.5 px-5 w-36">Opsi Pengisian</x-admin.table.th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-surface-200 text-sm">
                    @forelse ($vacancies as $item)
                        <tr class="hover:bg-surface-50/75 transition duration-150 group">
                            <!-- Unit Kerja -->
                            <td class="py-3.5 px-5 align-top">
                                <div class="flex items-start gap-2.5">
                                    <div class="w-7 h-7 rounded-md bg-earth-100 text-earth-700 border border-earth-300 flex items-center justify-center text-xs shrink-0 mt-0.5">
                                        <i class="fas fa-building" aria-hidden="true"></i>
                                    </div>
                                    <div class="min-w-0">
                                        <p class="font-semibold text-surface-900 text-xs leading-snug">
                                            {{ $item->unit_kerja }}
                                        </p>
                                        <span class="inline-block mt-0.5 text-[11px] font-normal text-surface-500">
                                            Formasi Operasional
                                        </span>
                                    </div>
                                </div>
                            </td>

                            <!-- Jabatan Lowong -->
                            <td class="py-3.5 px-5 align-top">
                                <div class="font-semibold text-surface-900 text-sm leading-tight group-hover:text-primary-700 transition">
                                    {{ $item->jabatan }}
                                </div>
                                <div class="flex items-center gap-1.5 mt-1.5">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-medium bg-surface-100 text-surface-700 border border-surface-200">
                                        Level: {{ $item->level ?: '—' }}
                                    </span>
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-semibold bg-earth-100 text-earth-800 border border-earth-300 tabular-nums">
                                        Gol: {{ $item->golongan ?: '—' }}
                                    </span>
                                    @if ($item->is_reserved)
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[10px] font-bold bg-warning-100 text-warning-800 border border-warning-200" title="Dipesan oleh {{ $item->reserved_by_name }} (TMT: {{ $item->reserved_tmt }})">
                                            <i class="fas fa-lock text-[10px] text-warning-700" aria-hidden="true"></i> Dipesan: {{ $item->reserved_tmt }}
                                        </span>
                                    @endif
                                </div>
                            </td>

                            <!-- Pejabat Terakhir -->
                            <td class="py-3.5 px-5 align-top">
                                @if ($item->last_occupant_name)
                                    <div class="flex items-center gap-2.5">
                                        <div class="w-8 h-8 rounded-full bg-primary-100 text-primary-800 border border-primary-200 flex items-center justify-center text-xs font-bold shrink-0 overflow-hidden">
                                            @if ($item->last_occupant_photo && Storage::disk('public')->exists($item->last_occupant_photo))
                                                <img src="{{ Storage::disk('public')->url($item->last_occupant_photo) }}" alt="" class="w-full h-full object-cover">
                                            @else
                                                {{ strtoupper(substr($item->last_occupant_name, 0, 1)) }}
                                            @endif
                                        </div>
                                        <div class="min-w-0">
                                            <p class="font-semibold text-surface-900 text-xs truncate max-w-[160px]" title="{{ $item->last_occupant_name }}">
                                                {{ $item->last_occupant_name }}
                                            </p>
                                            <span class="text-[11px] font-mono text-surface-500 block tabular-nums">
                                                {{ $item->last_occupant_nik }}
                                            </span>
                                        </div>
                                    </div>
                                @else
                                    <span class="inline-flex items-center gap-1 text-surface-500 italic text-xs">
                                        <i class="fas fa-minus text-[10px]" aria-hidden="true"></i> Formasi Baru
                                    </span>
                                @endif
                            </td>

                            <!-- Kosong Sejak -->
                            <td class="py-3.5 px-5 align-top text-xs">
                                <div class="font-medium text-surface-900 tabular-nums">{{ $item->vacant_since_formatted }}</div>
                                <span class="text-[11px] text-surface-500 block mt-0.5">{{ $item->duration_human }}</span>
                            </td>

                            <!-- Durasi & Urgensi -->
                            <td class="py-3.5 px-5 align-top text-center">
                                @if ($item->severity === 'red')
                                    <div class="inline-flex flex-col items-center">
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-bold bg-danger-100 text-danger border border-danger-200 tabular-nums">
                                            <span class="w-1.5 h-1.5 rounded-full bg-danger motion-safe:animate-pulse"></span>
                                            {{ $item->days_vacant }} Hari
                                        </span>
                                        <span class="text-[10px] font-bold text-danger mt-1 uppercase tracking-wider">
                                            Kritis (&gt;90 Hari)
                                        </span>
                                    </div>
                                @elseif ($item->severity === 'yellow')
                                    <div class="inline-flex flex-col items-center">
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-warning-100 text-warning-800 border border-warning-200 tabular-nums">
                                            <i class="fas fa-clock text-warning-700 text-[10px]" aria-hidden="true"></i>
                                            {{ $item->days_vacant }} Hari
                                        </span>
                                        <span class="text-[10px] font-semibold text-warning-800 mt-1 uppercase tracking-wider">
                                            Perhatian (30-90 Hari)
                                        </span>
                                    </div>
                                @else
                                    <div class="inline-flex flex-col items-center">
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-primary-100 text-primary-800 border border-primary-200 tabular-nums">
                                            <i class="fas fa-calendar-check text-primary-700 text-[10px]" aria-hidden="true"></i>
                                            {{ $item->days_vacant }} Hari
                                        </span>
                                        <span class="text-[10px] font-semibold text-primary-800 mt-1 uppercase tracking-wider">
                                            Baru Kosong (&lt;30 Hari)
                                        </span>
                                    </div>
                                @endif
                            </td>

                            <!-- Aksi Penempatan / Pengisian Formasi -->
                            <td class="py-3.5 px-5 align-middle text-right whitespace-nowrap">
                                @if ($item->is_reserved)
                                    <div class="p-2 rounded-xl bg-warning-50 border border-warning-200 text-left w-32 ml-auto shadow-2xs">
                                        <div class="flex items-center gap-1.5 text-[11px] font-bold text-warning-800">
                                            <i class="fas fa-lock text-warning-700 text-xs" aria-hidden="true"></i>
                                            <span>Terjadwal (SK)</span>
                                        </div>
                                        <div class="text-[11px] font-semibold text-surface-900 truncate mt-1" title="{{ $item->reserved_by_name }}">
                                            {{ $item->reserved_by_name }}
                                        </div>
                                        <div class="text-[10px] text-warning-700 font-mono mt-0.5">
                                            TMT: {{ $item->reserved_tmt }}
                                        </div>
                                    </div>
                                @else
                                    <div class="inline-flex flex-col gap-1.5 w-32 items-stretch ml-auto">
                                        <!-- 1. Mutasi / Transfer Internal -->
                                        <a href="{{ route('admin.mutasi.create', ['jabatan' => $item->jabatan, 'unit' => $item->unit_kerja]) }}"
                                            class="inline-flex items-center justify-center gap-1.5 w-full py-1.5 px-2.5 rounded-lg bg-primary-600 hover:bg-primary-700 text-white text-xs font-semibold transition-all shadow-2xs hover:shadow-xs active:scale-95 cursor-pointer text-center"
                                            title="Isi melalui transfer / promosi staf internal">
                                            <i class="fas fa-exchange-alt text-[10px]" aria-hidden="true"></i>
                                            <span>Mutasi Internal</span>
                                        </a>

                                        <!-- 2. Rekrut Pegawai Baru -->
                                        <a href="{{ route('admin.employees.create', ['jabatan' => $item->jabatan, 'unit_kerja' => $item->unit_kerja, 'level' => $item->level, 'golongan' => $item->golongan]) }}"
                                            class="inline-flex items-center justify-center gap-1.5 w-full py-1.5 px-2.5 rounded-lg bg-white hover:bg-surface-50 text-surface-700 hover:text-surface-900 border border-surface-300 text-xs font-medium transition-all shadow-2xs hover:shadow-xs active:scale-95 cursor-pointer text-center"
                                            title="Rekrut pegawai baru dari luar untuk mengisi posisi ini">
                                            <i class="fas fa-user-plus text-[10px] text-surface-400" aria-hidden="true"></i>
                                            <span>Rekrut Baru</span>
                                        </a>
                                    </div>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <x-admin.table.empty-state :colspan="6" icon="fas fa-check-double"
                            :message="$hasActiveFilters ? 'Tidak ditemukan formasi dengan kriteria filter yang Anda pilih. Coba sesuaikan kata kunci atau reset filter.' : 'Semua formasi jabatan di seluruh unit kerja saat ini terisi aktif oleh karyawan.'">
                            @if ($hasActiveFilters)
                                <x-admin.button variant="secondary" size="sm" wire:click="resetFilters">Reset Filter</x-admin.button>
                            @endif
                        </x-admin.table.empty-state>
                    @endforelse
                </tbody>
            </table>
        </div>

        <x-admin.table.pagination :paginator="$vacancies" noun="lowongan" :per-page-options="$perPageOptions" />
    </x-admin.table.card>
</div>
