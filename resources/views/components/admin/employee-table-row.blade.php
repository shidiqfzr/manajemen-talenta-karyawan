@props(['employee'])

<tr :class="selectedNiks.includes('{{ $employee->nik }}') ? 'bg-slate-50/70 border-l-4 border-l-primary-600' : ''"
    class="cursor-pointer hover:bg-slate-50/80 transition duration-150 group select-none"
    @click="handleRowClick('{{ $employee->nik }}', $event, '{{ route('admin.employees.show', $employee->nik) }}')">
    
    <!-- Checkbox Kolom Tunggal Rapi -->
    <td class="py-3.5 pl-5 pr-2 w-10 text-center whitespace-nowrap" @click.stop="toggleSelect('{{ $employee->nik }}', $event)">
        <div class="w-4 h-4 rounded border flex items-center justify-center cursor-pointer transition mx-auto shrink-0"
             :class="selectedNiks.includes('{{ $employee->nik }}') ? 'bg-primary-600 border-primary-600 text-white shadow-xs' : 'bg-white border-slate-300 hover:border-primary-500 shadow-2xs'"
             title="{{ $employee->nama }} (Klik untuk memilih, Shift+Klik untuk rentang)">
            <i class="fas fa-check text-[9px] text-white" x-show="selectedNiks.includes('{{ $employee->nik }}')" x-cloak></i>
        </div>
    </td>

    <!-- Pegawai (Nama, Foto Profil jika ada, NIK) -->
    <td class="py-3.5 px-3 whitespace-nowrap">
        <div class="flex items-center gap-3">
            @if ($employee->foto && Storage::disk('public')->exists($employee->foto))
                <div class="w-9 h-9 rounded-full overflow-hidden border border-slate-200 shadow-2xs shrink-0">
                    <img src="{{ Storage::disk('public')->url($employee->foto) }}" alt="{{ $employee->nama }}"
                        class="w-full h-full object-cover">
                </div>
            @endif

            <!-- Name and NIK -->
            <div class="min-w-0" @click.stop>
                <a href="{{ route('admin.employees.show', $employee->nik) }}"
                    class="font-semibold text-surface-900 group-hover:text-primary-700 transition block truncate max-w-[200px] sm:max-w-xs"
                    title="{{ $employee->nama }}">
                    <x-highlight :text="$employee->nama" />
                </a>
                <div class="flex items-center gap-1.5 mt-0.5">
                    <span class="font-mono text-xs text-surface-500 tabular-nums">
                        <x-highlight :text="$employee->nik" />
                    </span>
                    <button type="button" @click.stop="copyNik('{{ $employee->nik }}')"
                        class="text-surface-400 hover:text-surface-700 text-[11px] transition cursor-pointer"
                        title="Salin NIK">
                        <i :class="copiedNik === '{{ $employee->nik }}' ? 'fas fa-check text-primary-600' : 'far fa-copy'"></i>
                    </button>
                </div>
            </div>
        </div>
    </td>

    <!-- Jabatan & Unit Kerja -->
    <td class="py-3.5 px-5">
        <div class="font-medium text-surface-900 text-sm flex items-center gap-2 flex-wrap">
            <span title="{{ $employee->jabatan }}"><x-highlight :text="$employee->jabatan" /></span>

            {{-- Badge Kasus Spesial 1: Sanksi Indisipliner Aktif / Kedaluwarsa --}}
            @if($employee->isSanctionActive())
                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-bold bg-red-50 text-red-700 border border-red-200 shadow-2xs"
                    title="Pegawai dalam masa sanksi indisipliner (Diperban) hingga {{ $employee->tanggal_berakhir_sanksi?->format('d M Y') }}">
                    <i class="fas fa-gavel text-[9px]"></i>
                    Masa Sanksi s/d {{ $employee->tanggal_berakhir_sanksi?->format('d M Y') }}
                </span>
            @elseif($employee->isSanctionExpired())
                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-bold bg-amber-50 text-amber-800 border border-amber-200 shadow-2xs animate-pulse"
                    title="Masa sanksi kedaluwarsa sejak {{ $employee->tanggal_berakhir_sanksi?->format('d M Y') }} - Harap terbitkan Mutasi Pencabutan Sanksi">
                    <i class="fas fa-exclamation-triangle text-[9px] text-amber-600"></i>
                    Sanksi Kedaluwarsa
                </span>
            @endif

            {{-- Badge Kasus Spesial 2: Jabatan Overflow / Pool Penampungan (Advisor) --}}
            @if($employee->isOverflow())
                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-medium bg-slate-100 text-slate-700 border border-slate-200 shadow-2xs"
                    title="Formasi penampungan fungsional non-struktural (Advisor/Overflow Pool)">
                    <i class="fas fa-layer-group text-[9px] text-slate-500"></i>
                    Fungsional Pool
                </span>
            @endif

            {{-- Badge Kasus Spesial 3: Batas Usia Pensiun / MBT --}}
            @if($employee->isPensionReached())
                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-bold bg-red-100 text-red-800 border border-red-300 shadow-2xs"
                    title="Pegawai telah mencapai Batas Usia Pensiun (BUP 56 Tahun) per {{ $employee->effective_tanggal_pensiun?->format('d M Y') }}">
                    <i class="fas fa-user-clock text-[9px] text-red-700"></i>
                    Masa Pensiun (BUP)
                </span>
            @elseif($employee->isMbtActive())
                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-bold bg-amber-100 text-amber-900 border border-amber-300 shadow-2xs"
                    title="Pegawai dalam Masa Bebas Tugas (MBT 55 Tahun) sejak {{ $employee->effective_tanggal_mbt?->format('d M Y') }} s/d {{ $employee->effective_tanggal_pensiun?->format('d M Y') }}">
                    <i class="fas fa-hourglass-half text-[9px] text-amber-700"></i>
                    Masa Bebas Tugas (MBT)
                </span>
            @elseif($employee->isApproachingMbt())
                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-medium bg-amber-50 text-amber-800 border border-amber-200 shadow-2xs"
                    title="Pegawai menjelang MBT pada {{ $employee->effective_tanggal_mbt?->format('d M Y') }} (sisa {{ $employee->mbt_countdown }})">
                    <i class="fas fa-clock text-[9px] text-amber-600"></i>
                    Menjelang MBT ({{ $employee->mbt_countdown }})
                </span>
            @endif
        </div>
        <div class="text-xs text-surface-500 flex items-center gap-1.5 mt-0.5 flex-wrap" title="{{ $employee->unit_kerja }}">
            <i class="fas fa-building text-[10px] text-surface-400 shrink-0"></i>
            <span class="truncate max-w-[240px] sm:max-w-md"><x-highlight :text="$employee->unit_kerja" /></span>
            <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-bold border shadow-2xs {{ $employee->bidang_theme }}"
                title="Bidang Fungsional: {{ $employee->bidang }}">
                {{ $employee->bidang }}
            </span>
        </div>
        @if($employee->concurrentJobs->isNotEmpty())
            <div class="mt-1.5 flex flex-wrap items-center gap-1.5">
                @foreach($employee->concurrentJobs as $cj)
                    @php
                        $badgeStyle = 'bg-purple-50 text-purple-700 border-purple-200';
                        if ($cj->status_penugasan === 'Plt') {
                            $badgeStyle = 'bg-blue-50 text-blue-700 border-blue-200';
                        } elseif ($cj->status_penugasan === 'Diperbantukan') {
                            $badgeStyle = 'bg-amber-50 text-amber-700 border-amber-200';
                        }
                    @endphp
                    <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded text-[11px] font-semibold border shadow-2xs {{ $badgeStyle }}"
                        title="Tugas Tambahan Aktif: {{ $cj->jabatan }} di {{ $cj->unit_kerja }} (sejak {{ $cj->tmt_awal?->format('d M Y') }})">
                        <i class="fas fa-briefcase text-[10px] opacity-75 shrink-0"></i>
                        <span>{{ $cj->status_penugasan }}: {{ $cj->jabatan }} <span class="font-normal opacity-85">— {{ $cj->unit_kerja }}</span></span>
                    </span>
                @endforeach
            </div>
        @endif
    </td>

    <!-- Strata (Level) - Clean Enterprise Typography -->
    <td class="py-3.5 px-5 whitespace-nowrap text-center">
        @if($employee->level)
            @php
                $levelTitle = match($employee->level) {
                    'Karpim' => 'Karyawan Pimpinan (Struktural & Manajerial)',
                    'Karpel' => 'Karyawan Pelaksana (Fungsional & Operasional)',
                    default => \App\Models\Employee::LEVELS[$employee->level] ?? $employee->level,
                };
            @endphp
            <span class="text-xs {{ $employee->is_karpim ? 'text-slate-800 font-semibold' : 'text-slate-500 font-medium' }}"
                title="{{ $levelTitle }}">
                {{ $employee->level }}
            </span>
        @else
            <span class="text-surface-400 text-xs">-</span>
        @endif
    </td>

    <!-- Eselon (RM Band) - Semantic Chip -->
    <td class="py-3.5 px-5 whitespace-nowrap text-center">
        @if($employee->rm_level)
            <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-semibold border shadow-2xs {{ $employee->rm_theme }}"
                title="{{ \App\Models\Employee::RM_LEVELS[$employee->rm_level] ?? $employee->rm_level }}">
                {{ $employee->rm_level }}
            </span>
        @else
            <span class="text-surface-400 text-xs">-</span>
        @endif
    </td>

    <!-- Golongan - Clean Tabular Monospaced Text -->
    <td class="py-3.5 px-5 whitespace-nowrap text-center">
        @if($employee->golongan)
            <span class="font-mono text-xs font-semibold text-slate-800 tabular-nums"
                title="Tingkat Golongan: {{ $employee->golongan }}">
                {{ $employee->golongan }}
            </span>
        @else
            <span class="text-surface-400 text-xs">-</span>
        @endif
    </td>

    <!-- Aksi (Enterprise Context Menu & Quick Preview) -->
    <td class="py-3.5 px-5 whitespace-nowrap text-right" @click.stop>
        <div class="relative inline-block text-right" x-data="{ open: false }">
            <button type="button" @click="open = !open"
                class="p-2 text-slate-400 hover:text-slate-700 hover:bg-slate-100 rounded-lg transition cursor-pointer inline-flex items-center justify-center"
                title="Menu Aksi">
                <i class="fas fa-ellipsis-v text-xs"></i>
            </button>

                <div x-show="open" @click.away="open = false" x-cloak
                    x-transition:enter="transition ease-out duration-100"
                    x-transition:enter-start="transform opacity-0 scale-95"
                    x-transition:enter-end="transform opacity-100 scale-100"
                    x-transition:leave="transition ease-in duration-75"
                    x-transition:leave-start="transform opacity-100 scale-100"
                    x-transition:leave-end="transform opacity-0 scale-95"
                    class="absolute right-0 z-30 mt-1 w-48 origin-top-right rounded-xl bg-white p-1 text-xs shadow-lg ring-1 ring-slate-900/5 focus:outline-none border border-slate-200 text-left">
                    
                    <a href="{{ route('admin.employees.show', $employee->nik) }}"
                        class="flex items-center gap-2 px-3 py-2 text-slate-700 hover:bg-slate-50 rounded-lg transition font-medium">
                        <i class="fas fa-id-card text-slate-400 w-3.5"></i>
                        <span>Profil Lengkap</span>
                    </a>

                    <a href="{{ route('admin.employees.edit', $employee->nik) }}"
                        class="flex items-center gap-2 px-3 py-2 text-slate-700 hover:bg-slate-50 rounded-lg transition font-medium">
                        <i class="fas fa-pencil-alt text-slate-400 w-3.5"></i>
                        <span>Ubah Data Pegawai</span>
                    </a>

                    <a href="{{ route('admin.mutasi.create', ['nik' => $employee->nik]) }}"
                        class="flex items-center gap-2 px-3 py-2 text-slate-700 hover:bg-slate-50 rounded-lg transition font-medium">
                        <i class="fas fa-exchange-alt text-emerald-600 w-3.5"></i>
                        <span>Ajukan Mutasi</span>
                    </a>

                    <div class="my-1 border-t border-slate-100"></div>

                    <button type="button"
                        @click="open = false; confirmDelete('{{ $employee->nik }}', '{{ addslashes($employee->nama) }}', '{{ route('admin.employees.destroy', $employee->nik) }}')"
                        class="flex w-full items-center gap-2 px-3 py-2 text-danger hover:bg-red-50 rounded-lg transition cursor-pointer font-medium">
                        <i class="fas fa-trash-alt text-danger w-3.5"></i>
                        <span>Hapus Pegawai</span>
                    </button>
                </div>
            </div>
        </td>
</tr>
