<div class="space-y-4">
    {{-- Success Flash Message --}}
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
        <x-admin.table.header title="Daftar Formasi Kosong" :paginator="$vacancies" noun="Formasi" />

        {{-- Tier 2: Sub-Header Urgency Status Strip --}}
        <div class="px-5 py-2.5 bg-surface-50 border-t border-b border-surface-200 flex flex-wrap items-center justify-between gap-3">
            <div class="flex flex-wrap items-center gap-1.5 sm:gap-2">
                <span class="text-xs font-medium text-surface-500 mr-1 flex items-center gap-1.5">
                    <i class="fas fa-clock text-[11px] text-surface-400" aria-hidden="true"></i> Status Durasi:
                </span>

                {{-- All --}}
                <button type="button" wire:click="$set('severityFilter', '')"
                    class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold transition cursor-pointer {{ $severityFilter === '' ? 'bg-surface-900 text-white shadow-2xs' : 'bg-white text-surface-700 hover:bg-surface-100 border border-surface-200' }}">
                    <span>Semua Formasi</span>
                    <span class="px-1.5 py-0.2 rounded-full text-[10px] {{ $severityFilter === '' ? 'bg-white/20 text-white' : 'bg-surface-100 text-surface-700 font-bold' }}">
                        {{ $counts['all'] }}
                    </span>
                </button>

                {{-- Critical (>90 days) --}}
                <button type="button" wire:click="$set('severityFilter', 'red')"
                    class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold transition cursor-pointer {{ $severityFilter === 'red' ? 'bg-danger text-white shadow-2xs' : 'bg-white text-danger-700 hover:bg-danger-50 border border-danger-200' }}">
                    <span class="w-1.5 h-1.5 rounded-full {{ $severityFilter === 'red' ? 'bg-white' : 'bg-danger' }}"></span>
                    <span>Kritis (&gt; 90 hari)</span>
                    <span class="px-1.5 py-0.2 rounded-full text-[10px] {{ $severityFilter === 'red' ? 'bg-white/20 text-white' : 'bg-danger-100 text-danger-700 font-bold' }}">
                        {{ $counts['critical'] }}
                    </span>
                </button>

                {{-- Moderate (30-90 days) --}}
                <button type="button" wire:click="$set('severityFilter', 'yellow')"
                    class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold transition cursor-pointer {{ $severityFilter === 'yellow' ? 'bg-warning-700 text-white shadow-2xs' : 'bg-white text-warning-800 hover:bg-warning-50 border border-warning-200' }}">
                    <span class="w-1.5 h-1.5 rounded-full {{ $severityFilter === 'yellow' ? 'bg-white' : 'bg-warning-700' }}"></span>
                    <span>Perhatian (30 - 90 hari)</span>
                    <span class="px-1.5 py-0.2 rounded-full text-[10px] {{ $severityFilter === 'yellow' ? 'bg-white/20 text-white' : 'bg-warning-100 text-warning-800 font-bold' }}">
                        {{ $counts['moderate'] }}
                    </span>
                </button>

                {{-- Recent (<30 days) --}}
                <button type="button" wire:click="$set('severityFilter', 'green')"
                    class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold transition cursor-pointer {{ $severityFilter === 'green' ? 'bg-primary-600 text-white shadow-2xs' : 'bg-white text-primary-800 hover:bg-primary-50 border border-primary-200' }}">
                    <span class="w-1.5 h-1.5 rounded-full {{ $severityFilter === 'green' ? 'bg-white' : 'bg-primary-600' }}"></span>
                    <span>Baru Kosong (&lt; 30 hari)</span>
                    <span class="px-1.5 py-0.2 rounded-full text-[10px] {{ $severityFilter === 'green' ? 'bg-white/20 text-white' : 'bg-primary-100 text-primary-800 font-bold' }}">
                        {{ $counts['recent'] }}
                    </span>
                </button>

                {{-- Never filled: no departure date to age from --}}
                <button type="button" wire:click="$set('severityFilter', 'unknown')"
                    class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold transition cursor-pointer {{ $severityFilter === 'unknown' ? 'bg-surface-700 text-white shadow-2xs' : 'bg-white text-surface-700 hover:bg-surface-100 border border-surface-200' }}">
                    <span class="w-1.5 h-1.5 rounded-full {{ $severityFilter === 'unknown' ? 'bg-white' : 'bg-surface-400' }}"></span>
                    <span>Belum Pernah Terisi</span>
                    <span class="px-1.5 py-0.2 rounded-full text-[10px] {{ $severityFilter === 'unknown' ? 'bg-white/20 text-white' : 'bg-surface-100 text-surface-700 font-bold' }}">
                        {{ $counts['unknown'] }}
                    </span>
                </button>
            </div>

        </div>


        @php
            // Same wire:target list for the live rows and the skeleton, so the two swap at the same moment.
            $loadingTargets = 'search,selectedUnit,severityFilter,perPage,resetFilters,sortBy,gotoPage,nextPage,previousPage,setPage';
            $columnCount = 5;
            $severityTone = [
                'red' => ['label' => 'Kritis', 'class' => 'bg-danger-100 text-danger border-danger-200 font-bold'],
                'yellow' => ['label' => 'Perhatian', 'class' => 'bg-warning-100 text-warning-800 border-warning-200 font-semibold'],
                'green' => ['label' => 'Baru', 'class' => 'bg-primary-100 text-primary-800 border-primary-200 font-semibold'],
            ];
        @endphp

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse" wire:loading.attr="aria-busy" wire:target="{{ $loadingTargets }}">
                <thead>
                    <tr class="bg-surface-50 border-b border-surface-200">
                        <x-admin.table.th sortable field="unit_kerja" :sort-field="$sortField" :sort-direction="$sortDirection" class="px-4 py-3.5 w-[18%]">Unit Kerja</x-admin.table.th>
                        <x-admin.table.th sortable field="jabatan" :sort-field="$sortField" :sort-direction="$sortDirection" class="px-4 py-3.5 w-[30%]">Jabatan Kosong</x-admin.table.th>
                        <x-admin.table.th sortable field="pejabat" :sort-field="$sortField" :sort-direction="$sortDirection" class="px-4 py-3.5 w-[22%]">Pejabat Terakhir</x-admin.table.th>
                        <x-admin.table.th sortable field="lama_kosong" :sort-field="$sortField" :sort-direction="$sortDirection" class="px-4 py-3.5 w-[16%]">Lama Kosong</x-admin.table.th>
                        <x-admin.table.th align="right" class="px-4 py-3.5 w-[14%]">Opsi Pengisian</x-admin.table.th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-surface-200 text-sm" wire:loading.delay.remove wire:target="{{ $loadingTargets }}">
                    @forelse ($vacancies as $item)
                        <tr class="hover:bg-surface-50/75 transition duration-150 group">
                            {{-- Unit Kerja --}}
                            <td class="px-4 py-3 align-middle">
                                {{-- Ikon unit memberi jangkar pindai pada tabel padat; netral agar tidak bersaing dengan lencana RM Band. --}}
                                <div class="flex items-center gap-2.5">
                                    <div class="w-7 h-7 rounded-md bg-earth-100 text-earth-700 border border-earth-300 flex items-center justify-center text-xs shrink-0">
                                        <i class="fas fa-building" aria-hidden="true"></i>
                                    </div>
                                    <p class="font-medium text-surface-800 text-sm leading-snug">{{ $item->unit_kerja }}</p>
                                </div>
                            </td>

                            {{-- Jabatan Kosong: nama jabatan, lalu satu baris penanda yang boleh membungkus antar-lencana (bukan di dalam lencana) --}}
                            <td class="px-4 py-3 align-middle">
                                <div class="font-semibold text-surface-900 text-sm leading-snug">
                                    {{ $item->jabatan }}
                                </div>
                                @if ($item->rm_level || in_array($item->status, ['acting', 'partial'], true))
                                    <div class="flex flex-wrap items-center gap-x-1.5 gap-y-1 mt-1.5">
                                        {{-- RM Band is the structural anchor of a formasi (DESIGN.md §4.2/§5.2; DOMAIN.md Bab 4.2) and already implies Karpim/Karpel; the last holder's golongan is a person attribute, shown in Pejabat Terakhir. --}}
                                        @if ($item->rm_level)
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-semibold border whitespace-nowrap {{ \App\Models\Position::rmTheme($item->rm_level) }}"
                                                title="{{ \App\Models\Position::RM_LEVEL_LIST[$item->rm_level] ?? $item->rm_level }}">
                                                <span class="sr-only">RM Band: </span>{{ $item->rm_level }}
                                            </span>
                                        @endif
                                        @if (in_array($item->status, ['acting', 'partial'], true))
                                            {{-- Satu lencana: status + "N dari kuota" (jumlah kosong dari kuota); kalimat lengkap untuk tooltip dan pembaca layar. --}}
                                            <span class="whitespace-nowrap" title="{{ $item->vacant_slots }} dari {{ $item->kuota }} kosong">
                                                <x-admin.master.seat-status :status="$item->status" :label="$item->status_label.' · '.$item->vacant_slots.' dari '.$item->kuota" />
                                                <span class="sr-only">({{ $item->vacant_slots }} dari {{ $item->kuota }} kosong)</span>
                                            </span>
                                        @endif
                                    </div>
                                @endif
                            </td>

                            {{-- Pejabat Terakhir --}}
                            <td class="px-4 py-3 align-middle">
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
                                            <p class="font-semibold text-surface-900 text-sm leading-snug break-words">{{ $item->last_occupant_name }}</p>
                                            <span class="text-xs font-mono text-surface-500 block tabular-nums">
                                                {{ $item->last_occupant_nik }}@if ($item->golongan) <span aria-hidden="true">·</span> <span class="sr-only">Golongan </span>{{ $item->golongan }}@endif
                                            </span>
                                        </div>
                                    </div>
                                @elseif ($item->last_occupant_nik)
                                    <span class="text-xs italic text-surface-500">Data karyawan tidak tersedia</span>
                                @else
                                    {{-- Keterangan "Belum pernah terisi" cukup sekali, pada kolom Lama Kosong; di sini hanya tanda kosong. --}}
                                    <span class="text-surface-500" title="Belum ada pejabat sebelumnya" aria-hidden="true">—</span>
                                    <span class="sr-only">Belum ada pejabat sebelumnya</span>
                                @endif
                            </td>

                            {{-- Lama Kosong: satu lencana (warna + teks + hari) dan tanggal mulai kosong --}}
                            <td class="px-4 py-3 align-middle">
                                @if ($item->severity === 'unknown')
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-surface-100 text-surface-700 border border-surface-200 whitespace-nowrap">
                                        <i class="fas fa-minus text-surface-500 text-[10px]" aria-hidden="true"></i>
                                        Belum pernah terisi
                                    </span>
                                @else
                                    @php $tone = $severityTone[$item->severity] ?? $severityTone['green']; @endphp
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs border tabular-nums whitespace-nowrap {{ $tone['class'] }}"
                                        title="{{ $tone['label'] }}: kosong {{ $item->days_vacant }} hari">
                                        <span class="w-1.5 h-1.5 rounded-full bg-current {{ $item->severity === 'red' ? 'motion-safe:animate-pulse' : '' }}" aria-hidden="true"></span>
                                        {{ $tone['label'] }} · {{ $item->days_vacant }} Hari
                                    </span>
                                    <div class="mt-1 text-xs text-surface-500 tabular-nums">Sejak {{ $item->vacant_since_formatted }}</div>
                                @endif
                            </td>

                            {{-- Opsi Pengisian: satu tombol menu "Isi Jabatan" (dua cara pengisian); tinggi sel sama untuk semua keadaan --}}
                            <td class="px-4 py-3 align-middle text-right">
                                @if ($item->is_reserved)
                                    <div class="inline-flex flex-col items-end text-xs leading-snug"
                                        title="Dipesan oleh {{ $item->reserved_occupant_name }} (TMT: {{ $item->reserved_tmt }})">
                                        <span class="inline-flex items-center gap-1.5 font-semibold text-warning-800 whitespace-nowrap">
                                            <i class="fas fa-lock text-[10px] text-warning-700" aria-hidden="true"></i> Terjadwal (SK)
                                        </span>
                                        <span class="text-surface-700 font-medium">{{ $item->reserved_occupant_name }}</span>
                                        <span class="text-surface-500 tabular-nums">TMT {{ $item->reserved_tmt }}</span>
                                    </div>
                                @else
                                    @php
                                        $recruitUrl = route('admin.employees.create', ['jabatan' => $item->jabatan, 'unit_kerja' => $item->unit_kerja, 'level' => $item->level, 'golongan' => $item->golongan]);
                                    @endphp
                                    {{-- Mutasi hanya menempatkan ke formasi yang masih punya kuota kosong; yang seluruh kuotanya terisi ditolak layar Mutasi. --}}
                                    @if ($item->free_slots <= 0)
                                        {{-- Satu aksi saja: tombol langsung, bukan menu berisi satu butir. --}}
                                        <div class="inline-flex items-center justify-end gap-2">
                                            <span class="text-xs text-surface-500 leading-snug" title="Seluruh kuota terisi pemegang aktif, termasuk Pj/Plt. Pindahkan salah satu pemegang lebih dulu.">Ada pemegang aktif</span>
                                            <a href="{{ $recruitUrl }}"
                                                class="inline-flex items-center justify-center gap-1.5 h-8 px-3 rounded-lg bg-white hover:bg-surface-50 text-surface-700 border border-surface-300 text-xs font-medium transition cursor-pointer whitespace-nowrap shadow-2xs focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500"
                                                aria-label="Rekrut karyawan baru untuk {{ $item->jabatan }} di {{ $item->unit_kerja }}">
                                                <i class="fas fa-user-plus text-[10px] text-surface-500" aria-hidden="true"></i>
                                                <span>Rekrut Baru</span>
                                            </a>
                                        </div>
                                    @else
                                        {{-- Satu tombol berlabel bernada tenang per baris; pilihan cara pengisian ada di dalam menu (DESIGN.md §7.5). --}}
                                        <x-admin.row-menu :label="$item->jabatan.' di '.$item->unit_kerja" text="Isi Jabatan" size="sm">
                                            <x-admin.master.menu-item
                                                :href="route('admin.mutasi.create', ['jabatan' => $item->jabatan, 'unit' => $item->unit_kerja])"
                                                icon="fas fa-exchange-alt"
                                                title="Isi melalui transfer / promosi staf internal">
                                                <span class="block font-semibold">Mutasi Internal</span>
                                                <span class="block text-[11px] font-normal text-surface-500">Isi dari karyawan yang sudah ada</span>
                                            </x-admin.master.menu-item>
                                            <x-admin.master.menu-item
                                                :href="$recruitUrl"
                                                icon="fas fa-user-plus"
                                                title="Rekrut karyawan baru dari luar untuk mengisi posisi ini">
                                                <span class="block font-semibold">Rekrut Karyawan Baru</span>
                                                <span class="block text-[11px] font-normal text-surface-500">Isi dari luar perusahaan</span>
                                            </x-admin.master.menu-item>
                                        </x-admin.row-menu>
                                    @endif
                                @endif
                            </td>
                        </tr>
                    @empty
                        <x-admin.table.empty-state :colspan="$columnCount" icon="fas fa-check-double"
                            :message="$hasActiveFilters ? 'Tidak ditemukan formasi dengan kriteria filter yang Anda pilih. Coba sesuaikan kata kunci atau reset filter.' : 'Semua formasi jabatan di seluruh unit kerja saat ini terisi aktif oleh karyawan.'">
                            @if ($hasActiveFilters)
                                <x-admin.button variant="secondary" size="sm" wire:click="resetFilters">Reset Filter</x-admin.button>
                            @endif
                        </x-admin.table.empty-state>
                    @endforelse
                </tbody>
                <x-admin.table.skeleton :columns="$columnCount" :target="$loadingTargets" />
            </table>
        </div>

        <x-admin.table.pagination :paginator="$vacancies" noun="formasi" :per-page-options="$perPageOptions" />
    </x-admin.table.card>
</div>
