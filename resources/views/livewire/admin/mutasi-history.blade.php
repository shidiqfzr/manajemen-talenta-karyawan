<div class="space-y-4">

    <!-- Filter Bar -->
    <div class="bg-white rounded-lg border border-surface-200 shadow-sm p-4 sm:p-5">
        <div class="grid grid-cols-1 md:grid-cols-12 gap-3 items-center">
            <!-- Search Input -->
            <div class="md:col-span-6">
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-surface-400">
                        <i class="fas fa-search text-xs"></i>
                    </div>
                    <input wire:model.live.debounce.350ms="search"
                        placeholder="Cari nama karyawan, NIK, jabatan, atau unit..."
                        class="w-full pl-9 pr-9 py-2.5 bg-white border border-surface-300 rounded-lg text-xs sm:text-sm text-surface-900 placeholder-surface-400 focus:outline-none focus:ring-2 focus:ring-primary-300 focus:border-primary-600 transition">
                    @if ($search)
                        <button wire:click="$set('search', '')" class="absolute inset-y-0 right-0 pr-3 flex items-center text-surface-400 hover:text-surface-600">
                            <i class="fas fa-times-circle text-xs"></i>
                        </button>
                    @endif
                </div>
            </div>

            <!-- Unit Filter -->
            <div class="md:col-span-3">
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-surface-400">
                        <i class="fas fa-building text-xs"></i>
                    </div>
                    <select wire:model.live="unitFilter"
                        class="w-full pl-9 pr-8 py-2.5 bg-white border border-surface-300 rounded-lg text-xs sm:text-sm text-surface-800 focus:outline-none focus:ring-2 focus:ring-primary-300 focus:border-primary-600 transition appearance-none cursor-pointer">
                        <option value="">Semua Unit Kerja</option>
                        @foreach ($units as $unit)
                            <option value="{{ $unit }}">{{ $unit }}</option>
                        @endforeach
                    </select>
                    <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none text-surface-400">
                        <i class="fas fa-chevron-down text-[10px]"></i>
                    </div>
                </div>
            </div>

            <!-- Jenis Mutasi Filter -->
            <div class="md:col-span-3">
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-surface-400">
                        <i class="fas fa-tag text-xs"></i>
                    </div>
                    <select wire:model.live="mutasiFilter"
                        class="w-full pl-9 pr-8 py-2.5 bg-white border border-surface-300 rounded-lg text-xs sm:text-sm text-surface-800 focus:outline-none focus:ring-2 focus:ring-primary-300 focus:border-primary-600 transition appearance-none cursor-pointer">
                        <option value="">Semua Jenis Mutasi</option>
                        @foreach ($mutasiTypes as $type)
                            <option value="{{ $type }}">{{ ucwords(strtolower(str_replace('_', ' ', $type))) }}</option>
                        @endforeach
                    </select>
                    <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none text-surface-400">
                        <i class="fas fa-chevron-down text-[10px]"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Table Card -->
    <div class="bg-white rounded-lg border border-surface-200 shadow-sm overflow-hidden">
        <div wire:loading.delay class="w-full h-1 bg-gradient-to-r from-primary-600 via-primary-400 to-primary-600 animate-pulse"></div>

        @if ($history->isEmpty())
            <div class="flex flex-col items-center justify-center py-16 px-4 text-center">
                <div class="w-16 h-16 bg-surface-100 rounded-full flex items-center justify-center text-surface-400 text-2xl mb-3 border border-surface-200">
                    <i class="fas fa-history"></i>
                </div>
                <h4 class="text-base font-bold text-surface-900">Tidak Ada Catatan Riwayat Mutasi</h4>
                <p class="text-xs text-surface-500 max-w-sm mt-1 leading-relaxed">
                    @if ($search || $unitFilter || $mutasiFilter)
                        Tidak ditemukan catatan dengan kriteria filter saat ini. Coba bersihkan filter pencarian.
                    @else
                        Belum ada mutasi atau transfer yang dicatat dalam sistem.
                    @endif
                </p>
                @if ($search || $unitFilter || $mutasiFilter)
                    <button wire:click="$set('search', ''); $set('unitFilter', ''); $set('mutasiFilter', '');"
                        class="mt-3 inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-md bg-surface-100 hover:bg-surface-200 text-surface-800 text-xs font-semibold border border-surface-300 transition">
                        <i class="fas fa-undo text-[10px]"></i>
                        Reset Filter
                    </button>
                @endif
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-surface-50 border-b border-surface-200 text-xs font-semibold text-surface-700 uppercase tracking-wider">
                            <th scope="col" class="py-3.5 px-5 w-64">Karyawan</th>
                            <th scope="col" class="py-3.5 px-5 w-72">Jabatan &amp; Unit Kerja</th>
                            <th scope="col" class="py-3.5 px-5 w-36 text-center">Jenis Mutasi</th>
                            <th scope="col" class="py-3.5 px-5 w-36">TMT Efektif</th>
                            <th scope="col" class="py-3.5 px-5 w-32 text-center">Status</th>
                            <th scope="col" class="py-3.5 px-5 w-52">Dasar SK / Catatan</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-surface-200 text-sm">
                        @foreach ($history as $record)
                            @php
                                $isActive = $record->tmt_akhir === null;
                                $badgeStyle = match($record->jenis_mutasi) {
                                    'PROMOSI'     => 'bg-primary-50 text-primary-800 border-primary-200',
                                    'ROTASI'      => 'bg-blue-50 text-info border-blue-200',
                                    'MUTASI_UNIT' => 'bg-earth-100 text-earth-800 border-earth-300',
                                    'PENUGASAN'   => 'bg-surface-100 text-surface-800 border-surface-300',
                                    'DEMOSI'      => 'bg-red-50 text-danger border-red-200',
                                    default       => 'bg-surface-100 text-surface-700 border-surface-200',
                                };
                            @endphp
                            <tr class="hover:bg-surface-50/75 transition duration-150 {{ $isActive ? 'bg-primary-50/20' : '' }}">
                                <!-- Employee -->
                                <td class="py-3.5 px-5 align-top">
                                    <div class="flex items-center gap-3">
                                        <div class="w-8 h-8 rounded-full bg-primary-100 text-primary-800 border border-primary-200 flex items-center justify-center text-xs font-bold shrink-0">
                                            {{ strtoupper(substr($record->employee?->nama ?? '?', 0, 1)) }}
                                        </div>
                                        <div class="min-w-0">
                                            @if ($record->employee)
                                                <a href="{{ route('admin.employees.show', $record->employee_nik) }}"
                                                    class="font-semibold text-xs text-surface-900 hover:text-primary-700 transition block truncate"
                                                    title="{{ $record->employee->nama }}">
                                                    {{ $record->employee->nama }}
                                                </a>
                                            @else
                                                <span class="font-semibold text-xs text-surface-900 block truncate">{{ $record->employee_nik }}</span>
                                            @endif
                                            <span class="text-[11px] text-surface-500 font-mono tabular-nums">NIK: {{ $record->employee_nik }}</span>
                                        </div>
                                    </div>
                                </td>

                                <!-- Position & Unit -->
                                <td class="py-3.5 px-5 align-top">
                                    <div class="font-semibold text-surface-900 text-xs leading-snug">
                                        {{ $record->jabatan }}
                                    </div>
                                    <div class="text-[11px] text-surface-500 mt-1 flex items-center gap-1.5">
                                        <i class="fas fa-building text-[10px] text-surface-400"></i>
                                        <span>{{ $record->unit_kerja }}</span>
                                    </div>
                                </td>

                                <!-- Mutation Type -->
                                <td class="py-3.5 px-5 align-top text-center whitespace-nowrap">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold border {{ $badgeStyle }}">
                                        {{ $record->jenis_mutasi_label }}
                                    </span>
                                </td>

                                <!-- TMT Efektif -->
                                <td class="py-3.5 px-5 align-top text-xs">
                                    <div class="font-medium text-surface-900 tabular-nums">
                                        {{ $record->tmt_awal ? \Carbon\Carbon::parse($record->tmt_awal)->translatedFormat('d M Y') : '—' }}
                                    </div>
                                    <span class="text-[11px] text-surface-500 block mt-0.5 tabular-nums">
                                        Sampai: {{ $record->tmt_akhir ? \Carbon\Carbon::parse($record->tmt_akhir)->translatedFormat('d M Y') : 'Sekarang' }}
                                    </span>
                                </td>

                                <!-- Status -->
                                <td class="py-3.5 px-5 align-top text-center whitespace-nowrap">
                                    @if ($isActive)
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full bg-primary-100 text-primary-800 border border-primary-200 text-xs font-semibold">
                                            <span class="w-1.5 h-1.5 rounded-full bg-primary-600 animate-pulse"></span>
                                            Aktif Menjabat
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full bg-surface-100 text-surface-700 border border-surface-200 text-xs font-medium">
                                            Selesai (Mutasi)
                                        </span>
                                    @endif
                                </td>

                                <!-- SK & Catatan -->
                                <td class="py-3.5 px-5 align-top text-xs">
                                    @if ($record->nomor_sk)
                                        <div class="font-mono text-[11px] font-semibold text-surface-800 bg-surface-100 px-2 py-0.5 rounded border border-surface-200 inline-block tabular-nums">
                                            {{ $record->nomor_sk }}
                                        </div>
                                    @endif
                                    @if ($record->catatan)
                                        <p class="text-[11px] text-surface-500 mt-1 line-clamp-2" title="{{ $record->catatan }}">
                                            {{ $record->catatan }}
                                        </p>
                                    @elseif (!$record->nomor_sk)
                                        <span class="text-surface-400 italic text-[11px]">—</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($history->hasPages())
                <div class="px-6 py-4 border-t border-surface-200 bg-surface-50">
                    {{ $history->links() }}
                </div>
            @endif
        @endif
    </div>
</div>
