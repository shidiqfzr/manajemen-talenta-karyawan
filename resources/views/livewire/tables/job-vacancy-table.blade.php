<div class="space-y-4">
    <!-- Success Flash Message -->
    @if (session()->has('vacancy_success'))
        <div class="p-4 bg-primary-50 border border-primary-200 text-primary-900 rounded-lg shadow-sm flex items-center justify-between animate-fade-in">
            <div class="flex items-center gap-3">
                <div class="w-8 h-8 rounded-full bg-primary-600 text-white flex items-center justify-center shrink-0">
                    <i class="fas fa-check text-xs"></i>
                </div>
                <span class="text-sm font-semibold">{{ session('vacancy_success') }}</span>
            </div>
            <button type="button" class="text-primary-700 hover:text-primary-900 p-1" onclick="this.parentElement.remove()">
                <i class="fas fa-times text-xs"></i>
            </button>
        </div>
    @endif

    <!-- Integrated Vacancies Table & Filter Card -->
    <div class="bg-white rounded-xl border border-surface-200 shadow-sm overflow-hidden">
        <!-- Livewire Loading Bar -->
        <div wire:loading.delay class="w-full h-1 bg-gradient-to-r from-primary-600 via-primary-400 to-primary-600 animate-pulse"></div>

        <!-- Tier 1: Search & Filter Control Bar -->
        <div class="p-4 sm:px-5 sm:py-3.5 bg-white flex flex-col md:flex-row items-center justify-between gap-3">
            <!-- Search Input -->
            <div class="relative w-full md:max-w-md">
                <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-surface-400">
                    <i class="fas fa-search text-xs"></i>
                </div>
                <input type="text" wire:model.live.debounce.300ms="search"
                    placeholder="Cari berdasarkan jabatan, unit kerja, nama, atau NIK..."
                    class="w-full pl-9 pr-9 py-2 bg-surface-50 hover:bg-white focus:bg-white border border-surface-300 rounded-lg text-xs sm:text-sm text-surface-900 placeholder-surface-400 focus:outline-none focus:ring-2 focus:ring-primary-300 focus:border-primary-600 transition shadow-2xs">
                @if ($search)
                    <button wire:click="$set('search', '')" class="absolute inset-y-0 right-0 pr-3 flex items-center text-surface-400 hover:text-surface-600 transition cursor-pointer">
                        <i class="fas fa-times-circle text-xs"></i>
                    </button>
                @endif
            </div>

            <!-- Filter Controls -->
            <div class="flex items-center gap-2.5 w-full md:w-auto justify-end">
                <!-- Unit Filter -->
                <div class="relative w-full sm:w-auto min-w-[210px]">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-surface-400">
                        <i class="fas fa-building text-xs"></i>
                    </div>
                    <select wire:model.live="selectedUnit"
                        class="w-full pl-8 pr-8 py-2 bg-white border border-surface-300 rounded-lg text-xs sm:text-sm text-surface-800 focus:outline-none focus:ring-2 focus:ring-primary-300 focus:border-primary-600 transition appearance-none cursor-pointer shadow-2xs">
                        <option value="">Semua Unit Kerja ({{ count($units) }})</option>
                        @foreach ($units as $u)
                            <option value="{{ $u }}">{{ $u }}</option>
                        @endforeach
                    </select>
                    <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none text-surface-400">
                        <i class="fas fa-chevron-down text-[10px]"></i>
                    </div>
                </div>

                <!-- Reset Button -->
                @if ($search || $selectedUnit || $severityFilter)
                    <button wire:click="resetFilters"
                        title="Reset semua filter"
                        class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg bg-surface-100 hover:bg-surface-200 text-surface-700 text-xs font-semibold border border-surface-300 transition shadow-2xs shrink-0 cursor-pointer">
                        <i class="fas fa-undo text-[10px]"></i>
                        <span>Reset</span>
                    </button>
                @endif
            </div>
        </div>

        <!-- Tier 2: Sub-Header Urgency Status Strip -->
        <div class="px-5 py-2.5 bg-surface-50 border-t border-b border-surface-200 flex flex-wrap items-center justify-between gap-3">
            <div class="flex flex-wrap items-center gap-1.5 sm:gap-2">
                <span class="text-xs font-medium text-surface-500 mr-1 flex items-center gap-1.5">
                    <i class="fas fa-clock text-[11px] text-surface-400"></i> Status Durasi:
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
                    class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold transition cursor-pointer {{ $severityFilter === 'red' ? 'bg-red-600 text-white shadow-2xs' : 'bg-white text-red-700 hover:bg-red-50 border border-red-200' }}">
                    <span class="w-1.5 h-1.5 rounded-full {{ $severityFilter === 'red' ? 'bg-white' : 'bg-red-600' }}"></span>
                    <span>Kritis (&gt; 90 hari)</span>
                    <span class="px-1.5 py-0.2 rounded-full text-[10px] {{ $severityFilter === 'red' ? 'bg-white/20 text-white' : 'bg-red-100 text-red-700 font-bold' }}">
                        {{ $counts['critical'] }}
                    </span>
                </button>

                <!-- Moderate (30-90 days) -->
                <button type="button" wire:click="$set('severityFilter', 'yellow')"
                    class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold transition cursor-pointer {{ $severityFilter === 'yellow' ? 'bg-amber-600 text-white shadow-2xs' : 'bg-white text-amber-800 hover:bg-amber-50 border border-amber-200' }}">
                    <span class="w-1.5 h-1.5 rounded-full {{ $severityFilter === 'yellow' ? 'bg-white' : 'bg-amber-600' }}"></span>
                    <span>Perhatian (30 - 90 hari)</span>
                    <span class="px-1.5 py-0.2 rounded-full text-[10px] {{ $severityFilter === 'yellow' ? 'bg-white/20 text-white' : 'bg-amber-100 text-amber-800 font-bold' }}">
                        {{ $counts['moderate'] }}
                    </span>
                </button>

                <!-- Recent (<30 days) -->
                <button type="button" wire:click="$set('severityFilter', 'green')"
                    class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold transition cursor-pointer {{ $severityFilter === 'green' ? 'bg-emerald-600 text-white shadow-2xs' : 'bg-white text-emerald-800 hover:bg-emerald-50 border border-emerald-200' }}">
                    <span class="w-1.5 h-1.5 rounded-full {{ $severityFilter === 'green' ? 'bg-white' : 'bg-emerald-600' }}"></span>
                    <span>Baru Kosong (&lt; 30 hari)</span>
                    <span class="px-1.5 py-0.2 rounded-full text-[10px] {{ $severityFilter === 'green' ? 'bg-white/20 text-white' : 'bg-emerald-100 text-emerald-800 font-bold' }}">
                        {{ $counts['recent'] }}
                    </span>
                </button>
            </div>

            <!-- Summary counter -->
            <div class="text-xs text-surface-500 font-medium">
                Total <span class="font-bold text-surface-900 font-mono">{{ $vacancies->total() }}</span> formasi lowong
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-surface-50 border-b border-surface-200 text-xs font-semibold text-surface-700 uppercase tracking-wider">
                        <th scope="col" class="py-3.5 px-5">Unit Kerja</th>
                        <th scope="col" class="py-3.5 px-5">Jabatan Lowong</th>
                        <th scope="col" class="py-3.5 px-5">Pejabat Terakhir</th>
                        <th scope="col" class="py-3.5 px-5">Kosong Sejak</th>
                        <th scope="col" class="py-3.5 px-5 text-center">Durasi &amp; Urgensi</th>
                        <th scope="col" class="py-3.5 px-5 text-center w-36">Opsi Pengisian</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-surface-200 text-sm">
                    @forelse ($vacancies as $item)
                        <tr class="hover:bg-surface-50/75 transition duration-150 group">
                            <!-- Unit Kerja -->
                            <td class="py-3.5 px-5 align-top">
                                <div class="flex items-start gap-2.5">
                                    <div class="w-7 h-7 rounded-md bg-earth-100 text-earth-700 border border-earth-300 flex items-center justify-center text-xs shrink-0 mt-0.5">
                                        <i class="fas fa-building"></i>
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
                                    <span class="inline-flex items-center gap-1 text-surface-400 italic text-xs">
                                        <i class="fas fa-minus text-[10px]"></i> Formasi Baru
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
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-bold bg-red-100 text-danger border border-red-200 tabular-nums">
                                            <span class="w-1.5 h-1.5 rounded-full bg-danger animate-pulse"></span>
                                            {{ $item->days_vacant }} Hari
                                        </span>
                                        <span class="text-[10px] font-bold text-danger mt-1 uppercase tracking-wider">
                                            Kritis (&gt;90 Hari)
                                        </span>
                                    </div>
                                @elseif ($item->severity === 'yellow')
                                    <div class="inline-flex flex-col items-center">
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-amber-100 text-amber-900 border border-amber-200 tabular-nums">
                                            <i class="fas fa-clock text-amber-700 text-[10px]"></i>
                                            {{ $item->days_vacant }} Hari
                                        </span>
                                        <span class="text-[10px] font-semibold text-amber-800 mt-1 uppercase tracking-wider">
                                            Perhatian (30-90 Hari)
                                        </span>
                                    </div>
                                @else
                                    <div class="inline-flex flex-col items-center">
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-primary-100 text-primary-800 border border-primary-200 tabular-nums">
                                            <i class="fas fa-calendar-check text-primary-700 text-[10px]"></i>
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
                                <div class="inline-flex flex-col gap-1.5 w-32 items-stretch ml-auto">
                                    <!-- 1. Mutasi / Transfer Internal -->
                                    <a href="{{ route('admin.mutasi.create', ['jabatan' => $item->jabatan, 'unit' => $item->unit_kerja]) }}"
                                        class="inline-flex items-center justify-center gap-1.5 w-full py-1.5 px-2.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-semibold transition-all shadow-2xs hover:shadow-xs active:scale-95 cursor-pointer text-center"
                                        title="Isi melalui transfer / promosi staf internal">
                                        <i class="fas fa-exchange-alt text-[10px]"></i>
                                        <span>Mutasi Internal</span>
                                    </a>

                                    <!-- 2. Rekrut Pegawai Baru -->
                                    <a href="{{ route('admin.employees.create', ['jabatan' => $item->jabatan, 'unit_kerja' => $item->unit_kerja, 'level' => $item->level, 'golongan' => $item->golongan]) }}"
                                        class="inline-flex items-center justify-center gap-1.5 w-full py-1.5 px-2.5 rounded-lg bg-white hover:bg-surface-50 text-surface-700 hover:text-surface-900 border border-surface-300 text-xs font-medium transition-all shadow-2xs hover:shadow-xs active:scale-95 cursor-pointer text-center"
                                        title="Rekrut pegawai baru dari luar untuk mengisi posisi ini">
                                        <i class="fas fa-user-plus text-[10px] text-surface-400"></i>
                                        <span>Rekrut Baru</span>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-14 text-center">
                                <div class="max-w-sm mx-auto space-y-3">
                                    <div class="w-16 h-16 mx-auto rounded-full bg-primary-50 text-primary-700 flex items-center justify-center text-2xl border border-primary-200">
                                        <i class="fas fa-check-double"></i>
                                    </div>
                                    <h4 class="text-base font-bold text-surface-900">Tidak Ada Formasi Kosong</h4>
                                    <p class="text-xs text-surface-500 leading-relaxed">
                                        @if ($search || $selectedUnit || $severityFilter)
                                            Tidak ditemukan formasi dengan kriteria filter yang Anda pilih. Coba sesuaikan kata kunci atau reset filter.
                                        @else
                                            Semua formasi jabatan di seluruh unit kerja saat ini terisi aktif oleh karyawan.
                                        @endif
                                    </p>
                                    @if ($search || $selectedUnit || $severityFilter)
                                        <button wire:click="resetFilters"
                                            class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-md bg-surface-100 hover:bg-surface-200 text-surface-800 text-xs font-semibold border border-surface-300 transition">
                                            <i class="fas fa-undo text-[10px]"></i>
                                            Reset Filter
                                        </button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($vacancies->hasPages())
            <div class="px-6 py-4 border-t border-surface-200 bg-surface-50">
                {{ $vacancies->links() }}
            </div>
        @endif
    </div>
</div>
