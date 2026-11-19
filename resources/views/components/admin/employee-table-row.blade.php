{{--
    term             : kata kunci pencarian untuk penyorotan; wajib diisi dari komponen Livewire karena request pembaruan tidak membawa ?search.
    selected         : baris ini termasuk pilihan (atau "pilih semua yang sesuai" aktif) — hanya untuk sorotan baris.
    selection-active : ada pilihan aktif; klik baris lalu memilih/melepas baris alih-alih membuka profil.
    Kotak centang terikat ke properti $selected milik lapisan tabel bersama (wire:model.live).
--}}
@props(['employee', 'term' => null, 'selected' => false, 'selectionActive' => false])

<tr {{ $attributes->only('wire:key') }}
    @class(['cursor-pointer hover:bg-surface-50/80 transition duration-150 group select-none', 'border-l-4', 'bg-surface-50/70 border-l-primary-600' => $selected, 'border-l-transparent' => ! $selected])
    @click="if ($event.ctrlKey || $event.metaKey || $event.shiftKey || {{ $selectionActive ? 'true' : 'false' }}) { $event.preventDefault(); $el.querySelector('input[type=checkbox]').click(); } else { window.location.href = '{{ route('admin.employees.show', $employee->nik) }}'; }">

    {{-- Avatar (foto/inisial) yang berubah menjadi kotak centang: komponen bersama x-admin.table.select-cell (DESIGN.md §7.5, §8.4). --}}
    <x-admin.table.select-cell :key="(string) $employee->nik" :label="$employee->nama" :employee="$employee" />

    <!-- Karyawan (Nama, Foto Profil jika ada, NIK) -->
    <td class="py-3.5 pl-2 pr-3 whitespace-nowrap">
        <div class="flex items-center gap-3">
            {{-- Foto/inisial kini ada di kolom pilihan di kiri (avatar yang berubah menjadi kotak centang). --}}
            <!-- Name and NIK -->
            <div class="min-w-0" @click.stop>
                <a href="{{ route('admin.employees.show', $employee->nik) }}"
                    class="font-semibold text-surface-900 group-hover:text-primary-700 transition block truncate max-w-[200px] xl:max-w-[240px]"
                    title="{{ $employee->nama }}">
                    <x-highlight :term="$term" :text="$employee->nama" />
                </a>
                <div class="flex items-center gap-1.5 mt-0.5">
                    <span class="font-mono text-xs text-surface-500 tabular-nums">
                        <x-highlight :term="$term" :text="$employee->nik" />
                    </span>
                    <button type="button" @click.stop="copyNik('{{ $employee->nik }}')"
                        class="text-surface-500 hover:text-surface-700 text-[11px] transition cursor-pointer"
                        title="Salin NIK">
                        <i :class="copiedNik === '{{ $employee->nik }}' ? 'fas fa-check text-primary-600' : 'far fa-copy'" aria-hidden="true"></i>
                    </button>
                    {{-- Penanda masa pensiun melekat pada orangnya: kode singkat + ikon di samping NIK, hanya pada baris yang menyimpang (DESIGN.md §5.1). Teks lengkap untuk pembaca layar (sr-only) dan tooltip. --}}
                    @if($employee->isSeparated())
                        <span class="inline-flex shrink-0 items-center gap-1 rounded border border-surface-200 bg-surface-100 px-1.5 py-0.5 text-[10px] font-bold text-surface-700"
                            title="Karyawan telah pensiun per {{ $employee->tanggal_berhenti?->format('d M Y') }}; tidak lagi menempati jabatan">
                            <i class="fas fa-user-check text-[10px]" aria-hidden="true"></i>
                            <span aria-hidden="true">Pensiun</span>
                            <span class="sr-only">Karyawan sudah pensiun</span>
                        </span>
                    @elseif($employee->isPensionReached())
                        <span class="inline-flex shrink-0 items-center gap-1 rounded border border-danger-200 bg-danger-100 px-1.5 py-0.5 text-[10px] font-bold text-danger-800"
                            title="Karyawan telah mencapai Batas Usia Pensiun (BUP {{ \App\Services\KepegawaianPolicy::BATAS_USIA_PENSIUN }} Tahun) per {{ $employee->effective_tanggal_pensiun?->format('d M Y') }}">
                            <i class="fas fa-user-clock text-[10px]" aria-hidden="true"></i>
                            <span aria-hidden="true">BUP</span>
                            <span class="sr-only">Masa Pensiun (BUP)</span>
                        </span>
                    @elseif($employee->isMbtActive())
                        <span class="inline-flex shrink-0 items-center gap-1 rounded border border-warning-200 bg-warning-100 px-1.5 py-0.5 text-[10px] font-bold text-warning-800"
                            title="Karyawan dalam Masa Bebas Tugas (MBT, {{ \App\Services\KepegawaianPolicy::MBT_BULAN_SEBELUM_PENSIUN }} bulan sebelum pensiun) sejak {{ $employee->effective_tanggal_mbt?->format('d M Y') }} s/d {{ $employee->effective_tanggal_pensiun?->format('d M Y') }}">
                            <i class="fas fa-hourglass-half text-[10px]" aria-hidden="true"></i>
                            <span aria-hidden="true">MBT</span>
                            <span class="sr-only">Masa Bebas Tugas (MBT)</span>
                        </span>
                    @endif
                </div>
                {{-- Sanksi jarang dan perlu tindakan: tetap sebagai lencana terbaca di baris sendiri di bawah NIK. --}}
                @if($employee->isSanctionActive() || $employee->isSanctionExpired())
                    <div class="mt-1 flex max-w-[240px] flex-wrap items-center gap-1">
                        {{-- Badge Kasus Spesial 1: Sanksi Indisipliner Aktif / Kedaluwarsa --}}
                        @if($employee->isSanctionActive())
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-bold bg-danger-50 text-danger-700 border border-danger-200 shadow-2xs"
                                title="Karyawan dalam masa sanksi indisipliner (Diperban) hingga {{ $employee->tanggal_berakhir_sanksi?->format('d M Y') }}">
                                <i class="fas fa-gavel text-[10px]" aria-hidden="true"></i>
                                Masa Sanksi s/d {{ $employee->tanggal_berakhir_sanksi?->format('d M Y') }}
                            </span>
                        @elseif($employee->isSanctionExpired())
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-bold bg-warning-50 text-warning-800 border border-warning-200 shadow-2xs motion-safe:animate-pulse"
                                title="Masa sanksi kedaluwarsa sejak {{ $employee->tanggal_berakhir_sanksi?->format('d M Y') }} - Harap terbitkan Mutasi Pencabutan Sanksi">
                                <i class="fas fa-exclamation-triangle text-[10px] text-warning-700" aria-hidden="true"></i>
                                Sanksi Kedaluwarsa
                            </span>
                        @endif
                    </div>
                @endif
            </div>
        </div>
    </td>

    <!-- Jabatan & Unit Kerja -->
    <td class="py-3.5 px-4">
        <div class="font-medium text-surface-900 text-sm flex items-center gap-2 flex-wrap">
            <span title="{{ $employee->jabatan }}"><x-highlight :term="$term" :text="$employee->jabatan" /></span>

            {{-- Badge Kasus Spesial 2: Jabatan Overflow / Pool Penampungan (Advisor) --}}
            @if($employee->isOverflow())
                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-medium bg-surface-100 text-surface-700 border border-surface-200 shadow-2xs"
                    title="Formasi penampungan fungsional non-struktural (Advisor/Overflow Pool)">
                    <i class="fas fa-layer-group text-[10px] text-surface-500" aria-hidden="true"></i>
                    Fungsional Pool
                </span>
            @endif
        </div>
        <div class="text-xs text-surface-500 flex items-center gap-1.5 mt-0.5 flex-wrap" title="{{ $employee->unit_kerja }}">
            <i class="fas fa-building text-[10px] text-surface-400 shrink-0" aria-hidden="true"></i>
            <span class="min-w-0 break-words"><x-highlight :term="$term" :text="$employee->unit_kerja" /></span>
        </div>
        @if($employee->concurrentJobs->isNotEmpty())
            <div class="mt-1.5 flex flex-wrap items-center gap-1.5">
                @foreach($employee->concurrentJobs as $cj)
                    @php
                        // Tugas rangkap satu perlakuan warna; jenisnya dibedakan oleh label teks.
                        $badgeStyle = 'bg-info-50 text-info-800 border-info-200';
                    @endphp
                    <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded text-[11px] font-semibold border shadow-2xs {{ $badgeStyle }}"
                        title="Tugas Tambahan Aktif: {{ $cj->jabatan }} di {{ $cj->unit_kerja }} (sejak {{ $cj->tmt_awal?->format('d M Y') }})">
                        <i class="fas fa-briefcase text-[10px] opacity-75 shrink-0" aria-hidden="true"></i>
                        <span>{{ $cj->status_penugasan }}: {{ $cj->jabatan }} <span class="font-normal opacity-85">— {{ $cj->unit_kerja }}</span></span>
                    </span>
                @endforeach
            </div>
        @endif
    </td>

    <!-- Bidang Fungsional (KEU/TAN/TEK/UMU) via accessor Employee::bidang -> ManPowerPlanningService; teks polos (metadata Tier 4), bukan chip -->
    <td class="py-3.5 px-4 whitespace-nowrap text-center">
        <span class="text-xs font-medium text-surface-700"
            title="Bidang Fungsional: {{ \App\Models\Position::BIDANG_LIST[$employee->bidang] ?? $employee->bidang }}">
            {{ $employee->bidang }}
        </span>
    </td>

    <!-- Strata (Level) - Clean Enterprise Typography -->
    <td class="py-3.5 px-4 whitespace-nowrap text-center">
        @if($employee->level)
            @php
                $levelTitle = match($employee->level) {
                    'Karpim' => 'Karyawan Pimpinan (Struktural & Manajerial)',
                    'Karpel' => 'Karyawan Pelaksana (Fungsional & Operasional)',
                    default => \App\Models\Employee::LEVELS[$employee->level] ?? $employee->level,
                };
            @endphp
            <span class="text-xs {{ $employee->is_karpim ? 'text-surface-800 font-semibold' : 'text-surface-500 font-medium' }}"
                title="{{ $levelTitle }}">
                {{ $employee->level }}
            </span>
        @else
            <span class="text-surface-500 text-xs">-</span>
        @endif
    </td>

    <!-- Eselon (RM Band) - Semantic Chip -->
    <td class="py-3.5 px-4 whitespace-nowrap text-center">
        @if($employee->rm_level)
            <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-semibold border shadow-2xs {{ $employee->rm_theme }}"
                title="{{ \App\Models\Position::RM_LEVEL_LIST[$employee->rm_level] ?? $employee->rm_level }}">
                {{ $employee->rm_level }}
            </span>
        @else
            <span class="text-surface-500 text-xs">-</span>
        @endif
    </td>

    <!-- Golongan - Clean Tabular Monospaced Text -->
    <td class="py-3.5 px-4 whitespace-nowrap text-center">
        @if($employee->golongan)
            <span class="font-mono text-xs font-semibold text-surface-800 tabular-nums"
                title="Tingkat Golongan: {{ $employee->golongan }}">
                {{ $employee->golongan }}
            </span>
        @else
            <span class="text-surface-500 text-xs">-</span>
        @endif
    </td>

    <!-- Masa Kerja - lama bekerja sejak TMT bekerja (accessor Employee::masa_kerja) -->
    <td class="py-3.5 px-4 whitespace-nowrap text-center">
        @if($employee->masa_kerja)
            <span class="font-mono text-xs font-medium text-surface-800 tabular-nums"
                title="Bekerja sejak {{ $employee->tmt_bekerja->format('d M Y') }}">
                {{ $employee->masa_kerja }}
            </span>
        @else
            <span class="text-surface-500 text-xs">-</span>
        @endif
    </td>

    {{-- Aksi: memakai x-admin.row-menu agar tampilan, hover, dan navigasi keyboard sama dengan seluruh tabel admin (DESIGN.md §7.5, §14) --}}
    <td class="py-3.5 px-4 whitespace-nowrap text-right" @click.stop>
        <x-admin.row-menu :label="$employee->nama">
            <x-admin.master.menu-item icon="fas fa-id-card" :href="route('admin.employees.show', $employee->nik)">Profil Lengkap</x-admin.master.menu-item>
            <x-admin.master.menu-item icon="fas fa-pencil-alt" :href="route('admin.employees.edit', $employee->nik)">Ubah Data Karyawan</x-admin.master.menu-item>
            <x-admin.master.menu-item icon="fas fa-exchange-alt" :href="route('admin.mutasi.create', ['nik' => $employee->nik])">Ajukan Mutasi</x-admin.master.menu-item>
            <x-admin.master.menu-item icon="fas fa-trash-alt" tone="danger" separated
                @click="confirmDelete('{{ $employee->nik }}', '{{ addslashes($employee->nama) }}', '{{ route('admin.employees.destroy', $employee->nik) }}')">Hapus Karyawan</x-admin.master.menu-item>
        </x-admin.row-menu>
    </td>
</tr>
