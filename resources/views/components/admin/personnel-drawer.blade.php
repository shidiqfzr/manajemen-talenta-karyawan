{{--
    Drawer kanan rincian personil (DESIGN.md §7.6): drilldown yang tetap memperlihatkan tabel di belakangnya.
    Dipakai layar MPP dan Master Formasi agar pola drilldown personil seragam.

    <x-admin.personnel-drawer title="Pemegang Jabatan" close-action="closeOccupants" :employees="$rows" :count="count($rows)">
        <x-slot:badges> ...chip konteks... </x-slot:badges>
        <x-slot:header> ...kartu rumus/ringkasan di bawah judul... </x-slot:header>
        <x-slot:footer> ...aksi tambahan di kiri footer (mis. Export Excel)... </x-slot:footer>
    </x-admin.personnel-drawer>

    subtitle: satu baris konteks di bawah judul (mis. jabatan dan kuota).
    Nama karyawan menjadi tautan ke profil (tab baru) bila NIK ada dan karyawan masih terdaftar.

    employees: daftar array dengan kunci nama, nik (hanya untuk tautan profil), status_karyawan, jabatan, unit_kerja,
    golongan, tanggal_mbt, tanggal_pensiun, tanggal_penempatan, terdaftar. Kunci yang tidak ada tidak dirender.
    show-nik=false (bawaan) menyembunyikan NIK sebagai teks (UU PDP, SECURITY.md); tautan profil tetap memakai NIK.
    Pencarian di panel berjalan lokal (Alpine) atas data yang sudah dimuat, tanpa request.
--}}
@props([
    'title',
    'subtitle' => null,
    'closeAction' => 'closeDrilldown',
    'employees' => [],
    'count' => null,
    'showNik' => false,
    'showPlacement' => false,
    'hideList' => false,
    'emptyText' => 'Tidak ada personil pada sel ini',
    'titleId' => 'personnel-drawer-title',
    'searchPlaceholder' => null,
])

@php
    $searchPlaceholder ??= $showNik ? 'Cari nama, NIK, atau jabatan di panel...' : 'Cari nama atau golongan di panel...';
@endphp

<div class="fixed inset-0 z-50 overflow-hidden" aria-labelledby="{{ $titleId }}" role="dialog" aria-modal="true"
    x-data x-trap.noscroll="true" tabindex="-1" @keydown.escape.window="$wire.{{ $closeAction }}()">
    {{-- Overlay tanpa blur (DESIGN.md §7.6) --}}
    <div class="fixed inset-0 bg-surface-950/30 transition-opacity duration-300" wire:click="{{ $closeAction }}"></div>

    <div class="fixed inset-y-0 right-0 max-w-full flex pl-6 sm:pl-10">
        <div class="w-screen max-w-md sm:max-w-lg bg-white shadow-2xl border-l border-surface-200 flex flex-col" x-data="{ search: '' }">
            <!-- Header & kontrol -->
            <div class="px-5 py-4 bg-surface-50 border-b border-surface-200 space-y-3">
                <div class="flex items-start justify-between gap-3">
                    <div class="space-y-1 min-w-0">
                        <div class="flex items-center gap-2 flex-wrap">
                            <span class="w-2.5 h-2.5 rounded-full bg-primary-600 inline-block" aria-hidden="true"></span>
                            <h3 class="text-sm font-bold text-surface-900" id="{{ $titleId }}">{{ $title }}</h3>
                            @if ($count !== null)
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold font-mono bg-primary-100 text-primary-800">
                                    {{ $count }} Personil
                                </span>
                            @endif
                        </div>

                        @if ($subtitle)
                            <p class="text-[11px] text-surface-500">{{ $subtitle }}</p>
                        @endif

                        @isset($badges)
                            <div class="flex flex-wrap items-center gap-1.5 text-xs">{{ $badges }}</div>
                        @endisset
                    </div>

                    <button type="button" wire:click="{{ $closeAction }}"
                        class="w-8 h-8 rounded-lg text-surface-500 hover:text-surface-700 hover:bg-surface-200/60 flex items-center justify-center transition focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-primary-500/40"
                        title="Tutup Panel" aria-label="Tutup Panel">
                        <i class="fas fa-times text-sm" aria-hidden="true"></i>
                    </button>
                </div>

                @if (! $hideList && ! empty($employees))
                    <div class="relative">
                        <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-surface-400 text-xs" aria-hidden="true"></i>
                        <input x-model="search" type="text" placeholder="{{ $searchPlaceholder }}" aria-label="{{ $searchPlaceholder }}"
                            class="w-full pl-8 pr-3 py-1.5 text-xs rounded-lg border border-surface-200 bg-white placeholder:text-surface-400 focus:outline-hidden focus:ring-2 focus:ring-primary-500/20 focus:border-primary-600 transition shadow-2xs">
                    </div>
                @endif

                @isset($header){{ $header }}@endisset
            </div>

            <!-- Isi: kartu personil -->
            <div class="flex-1 overflow-y-auto p-4 bg-surface-50/40">
                @if ($hideList)
                    {{-- Hanya header (mis. rincian rumus) yang ditampilkan. --}}
                @elseif (empty($employees))
                    <div class="text-center py-12 px-4 bg-white rounded-xl border border-surface-200">
                        <div class="w-12 h-12 rounded-full bg-surface-100 text-surface-500 flex items-center justify-center mx-auto mb-3 text-lg">
                            <i class="fas fa-folder-open" aria-hidden="true"></i>
                        </div>
                        <h4 class="text-sm font-bold text-surface-800">{{ $emptyText }}</h4>
                    </div>
                @else
                    <div class="space-y-2">
                        @foreach ($employees as $emp)
                            <div x-show="search === '' || $el.innerText.toLowerCase().includes(search.toLowerCase())"
                                class="bg-white rounded-xl border border-surface-200 px-3.5 py-3 shadow-2xs hover:shadow-xs transition duration-150"
                                wire:key="personnel-{{ $emp['id'] ?? $emp['nik'] ?? $loop->index }}">
                                <div class="flex items-start justify-between gap-4">
                                    <div class="flex items-start gap-3 min-w-0">
                                        <div class="w-9 h-9 rounded-full bg-primary-50 text-primary-800 border border-primary-200 flex items-center justify-center font-bold text-xs shrink-0" aria-hidden="true">
                                            {{ strtoupper(substr($emp['nama'], 0, 2)) }}
                                        </div>
                                        <div class="min-w-0 space-y-0.5">
                                            <h4 class="text-xs font-bold text-surface-900 truncate">
                                                @if (! empty($emp['nik']) && ($emp['terdaftar'] ?? true))
                                                    <a href="{{ route('admin.employees.show', $emp['nik']) }}" target="_blank" rel="noopener noreferrer"
                                                        class="rounded text-primary-800 hover:text-primary-700 hover:underline underline-offset-2 transition focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-primary-500/40"
                                                        title="Buka profil lengkap (tab baru)" aria-label="Buka profil {{ $emp['nama'] }} (tab baru)">{{ $emp['nama'] }}</a>
                                                @else
                                                    {{ $emp['nama'] }}
                                                @endif
                                            </h4>
                                            <p class="text-[11px] font-mono text-surface-500 truncate">
                                                @if ($showNik)
                                                    NIK: {{ $emp['nik'] }}<span class="text-surface-300" aria-hidden="true"> • </span>
                                                @endif
                                                <span class="font-medium text-primary-700">{{ $emp['status_karyawan'] ?? '—' }}</span>
                                            </p>
                                        </div>
                                    </div>

                                    <dl class="shrink-0 text-right">
                                        <dt class="text-[10px] text-surface-500">Golongan</dt>
                                        <dd class="font-mono tabular-nums font-bold text-xs text-surface-900">{{ $emp['golongan'] ?? '—' }}</dd>
                                    </dl>
                                </div>

                                @php
                                    $hasRole = isset($emp['jabatan']) || isset($emp['unit_kerja']);
                                    $hasDates = isset($emp['tanggal_mbt']) || isset($emp['tanggal_pensiun']);
                                @endphp
                                @if ($hasRole || $hasDates || ($showPlacement && isset($emp['tanggal_penempatan'])))
                                    <div class="mt-2.5 pt-2.5 border-t border-surface-100 text-[11px] space-y-1">
                                        @if ($hasRole)
                                            <div class="flex items-center justify-between gap-4">
                                                <p class="flex items-center gap-1.5 min-w-0 text-surface-800 font-medium">
                                                    <i class="fas fa-briefcase text-surface-400 text-[10px] w-3 shrink-0" aria-hidden="true"></i>
                                                    <span class="truncate">{{ $emp['jabatan'] ?? '—' }}</span>
                                                </p>
                                                @isset($emp['tanggal_mbt'])
                                                    <p class="shrink-0 text-surface-500">MBT <span class="font-semibold text-surface-800 tabular-nums">{{ $emp['tanggal_mbt'] }}</span></p>
                                                @endisset
                                            </div>
                                            <div class="flex items-center justify-between gap-4">
                                                <p class="flex items-center gap-1.5 min-w-0 text-surface-500">
                                                    <i class="fas fa-location-dot text-surface-400 text-[10px] w-3 shrink-0" aria-hidden="true"></i>
                                                    <span class="truncate">{{ $emp['unit_kerja'] ?? '—' }}</span>
                                                </p>
                                                @isset($emp['tanggal_pensiun'])
                                                    <p class="shrink-0 text-surface-500">Pensiun <span class="font-semibold text-surface-800 tabular-nums">{{ $emp['tanggal_pensiun'] }}</span></p>
                                                @endisset
                                            </div>
                                        @elseif ($hasDates)
                                            {{-- Tanpa jabatan/unit (mis. Master Formasi): tanggal berbagi satu baris. --}}
                                            <div class="flex items-center justify-between gap-4 text-surface-500">
                                                @isset($emp['tanggal_mbt'])
                                                    <p><i class="fas fa-calendar-day text-warning-700 text-[10px] w-3" aria-hidden="true"></i> MBT <span class="font-semibold text-surface-800 tabular-nums">{{ $emp['tanggal_mbt'] }}</span></p>
                                                @endisset
                                                @isset($emp['tanggal_pensiun'])
                                                    <p>Pensiun <span class="font-semibold text-surface-800 tabular-nums">{{ $emp['tanggal_pensiun'] }}</span></p>
                                                @endisset
                                            </div>
                                        @endif
                                        @if ($showPlacement && isset($emp['tanggal_penempatan']))
                                            <p class="flex items-center gap-1.5 text-surface-500">
                                                <i class="fas fa-calendar-check text-surface-400 text-[10px] w-3 shrink-0" aria-hidden="true"></i>
                                                <span>Penempatan Karpim: <span class="font-semibold text-surface-800 tabular-nums">{{ $emp['tanggal_penempatan'] }}</span></span>
                                            </p>
                                        @endif
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            <!-- Footer -->
            <div class="px-5 py-3.5 bg-surface-50 border-t border-surface-200 flex items-center justify-between gap-2">
                @isset($footer)
                    {{ $footer }}
                @else
                    <span></span>
                @endisset

                <button type="button" wire:click="{{ $closeAction }}"
                    class="px-4 py-1.5 text-xs font-semibold rounded-lg bg-surface-200 text-surface-700 hover:bg-surface-300 transition focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-primary-500/40">
                    Tutup Panel
                </button>
            </div>
        </div>
    </div>
</div>
