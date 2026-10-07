<div class="space-y-5">

    {{-- Success Flash Message --}}
    @if (session()->has('mutasi_success'))
        <div class="p-4 bg-primary-50 border border-primary-200 text-primary-900 rounded-lg flex items-center justify-between shadow-sm animate-fade-in">
            <div class="flex items-center gap-3">
                <div class="w-8 h-8 rounded-full bg-primary-600 text-white flex items-center justify-center shrink-0">
                    <i class="fas fa-check text-xs"></i>
                </div>
                <div>
                    <h4 class="text-sm font-bold text-primary-900">Mutasi Berhasil Diproses!</h4>
                    <p class="text-xs text-primary-700 mt-0.5">{{ session('mutasi_success') }}</p>
                </div>
            </div>
            <button onclick="this.parentElement.remove()" class="text-primary-600 hover:text-primary-800 transition p-1">
                <i class="fas fa-times text-xs"></i>
            </button>
        </div>
    @endif

    {{-- Guided Banner --}}
    <div class="bg-primary-950 text-white rounded-lg p-5 border border-primary-900 shadow-sm flex items-start gap-4">
        <div class="w-10 h-10 rounded-lg bg-white/10 text-primary-300 flex items-center justify-center text-lg shrink-0 border border-white/10">
            <i class="fas fa-lightbulb"></i>
        </div>
        <div class="space-y-1">
            <h4 class="text-xs sm:text-sm font-bold text-white uppercase tracking-wider">Sistem Penempatan Cerdas (*Smart Assignment*)</h4>
            <p class="text-xs text-primary-100/80 leading-relaxed">
                Pilih jenis mutasi dan karyawan. Sistem akan <strong>secara otomatis memfilter</strong> jabatan tujuan yang sesuai (misal: Promosi hanya menampilkan jabatan eselon lebih tinggi). Status formasi (<span class="text-primary-300 font-semibold">Transfer Bersih</span> atau <span class="text-earth-300 font-semibold">Tukar Jabatan</span>) akan dikalkulasi real-time.
            </p>
        </div>
    </div>

    <form wire:submit="submitMutasi" class="space-y-5">

        {{-- ═══════════════════ MAIN ASSIGNMENT CARDS ═══════════════════ --}}
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-5 items-stretch">

            {{-- ── 1. SOURCE EMPLOYEE (5 Cols) ── --}}
            <div class="lg:col-span-5 bg-white rounded-lg border border-surface-200 shadow-sm p-5 space-y-4 flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between pb-3 border-b border-surface-100">
                        <div class="flex items-center gap-2.5">
                            <span class="w-6 h-6 rounded-full bg-primary-100 text-primary-800 text-xs font-bold flex items-center justify-center">1</span>
                            <h3 class="text-sm font-bold text-surface-900">Jenis Mutasi &amp; Karyawan</h3>
                        </div>
                        <span class="text-[11px] font-semibold text-surface-400 uppercase tracking-wider">Asal Posisi</span>
                    </div>

                    <div class="mt-4 space-y-4">
                        {{-- JENIS MUTASI --}}
                        <div>
                            <label class="block text-xs font-bold text-surface-700 mb-1.5">
                                Jenis Mutasi <span class="text-danger">*</span>
                            </label>
                            <div class="relative">
                                <select wire:model.live="jenisMutasi"
                                    class="w-full px-3.5 py-2.5 bg-white border border-surface-300 rounded-lg text-xs sm:text-sm font-medium text-surface-800 focus:outline-none focus:ring-2 focus:ring-primary-300 focus:border-primary-600 transition appearance-none cursor-pointer">
                                    @foreach ($mutasiTypes as $type)
                                        <option value="{{ $type }}">{{ ucwords(strtolower(str_replace('_', ' ', $type))) }}</option>
                                    @endforeach
                                </select>
                                <div class="absolute inset-y-0 right-0 pr-3.5 flex items-center pointer-events-none text-surface-400">
                                    <i class="fas fa-chevron-down text-xs"></i>
                                </div>
                            </div>
                        </div>

                        {{-- KARYAWAN --}}
                        <div>
                            <label class="block text-xs font-bold text-surface-700 mb-1.5">
                                Pilih Karyawan Aktif <span class="text-danger">*</span>
                            </label>
                            <div class="relative">
                                <select wire:model.live="employeeNik"
                                    class="w-full px-3.5 py-2.5 bg-white border border-surface-300 rounded-lg text-xs sm:text-sm font-medium text-surface-800 focus:outline-none focus:ring-2 focus:ring-primary-300 focus:border-primary-600 transition appearance-none cursor-pointer">
                                    <option value="">-- Pilih Karyawan --</option>
                                    @foreach ($employees as $emp)
                                        <option value="{{ $emp->nik }}">{{ $emp->nama }} — {{ $emp->nik }}</option>
                                    @endforeach
                                </select>
                                <div class="absolute inset-y-0 right-0 pr-3.5 flex items-center pointer-events-none text-surface-400">
                                    <i class="fas fa-chevron-down text-xs"></i>
                                </div>
                            </div>
                            @error('employeeNik')
                                <p class="text-danger text-xs mt-1.5 flex items-center gap-1">
                                    <i class="fas fa-exclamation-circle text-xs"></i> {{ $message }}
                                </p>
                            @enderror
                        </div>

                        {{-- Current Position Details Card --}}
                        @if ($employeeDetails)
                            <div class="p-4 rounded-lg {{ $employeeDetails['has_job'] ? 'bg-surface-50 border border-surface-200' : 'bg-amber-50 border border-amber-200' }} transition">
                                <div class="flex items-center justify-between mb-2">
                                    <span class="text-[10px] font-bold uppercase tracking-wider {{ $employeeDetails['has_job'] ? 'text-surface-500' : 'text-amber-800' }}">
                                        Jabatan Terkini
                                    </span>
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-semibold bg-earth-100 text-earth-800 border border-earth-300 tabular-nums">
                                        Gol: {{ $employeeDetails['golongan'] ?? '—' }} | {{ $employeeDetails['rm_level'] ?? '—' }}
                                    </span>
                                </div>
                                @if ($employeeDetails['has_job'])
                                    <h4 class="text-sm font-bold text-surface-900 leading-snug">{{ $employeeDetails['jabatan'] }}</h4>
                                    <p class="text-xs text-surface-600 mt-1 flex items-center gap-1.5">
                                        <i class="fas fa-building text-surface-400 text-[11px]"></i>
                                        {{ $employeeDetails['unit_kerja'] }}
                                    </p>
                                    @if($employeeDetails['level'])
                                        <p class="text-[11px] text-surface-500 mt-1">Level: {{ $employeeDetails['level'] }}</p>
                                    @endif
                                @else
                                    <p class="text-xs font-semibold text-amber-800 flex items-center gap-1.5">
                                        <i class="fas fa-exclamation-triangle"></i>
                                        Belum memiliki data jabatan aktif
                                    </p>
                                @endif
                            </div>
                        @else
                            <div class="p-6 rounded-lg bg-surface-50 border border-dashed border-surface-300 text-center">
                                <div class="w-10 h-10 mx-auto rounded-full bg-surface-100 text-surface-400 flex items-center justify-center text-sm mb-2">
                                    <i class="fas fa-user"></i>
                                </div>
                                <p class="text-xs font-medium text-surface-500">Pilih karyawan di atas untuk melihat rincian jabatan saat ini</p>
                            </div>
                        @endif
                    </div>
                </div>

                @if ($employeeDetails && $targetAnalysis && $targetAnalysis['type'] === 'vacant')
                    <div class="mt-4 p-3 bg-amber-50 border border-amber-200 rounded-lg text-xs text-amber-800 flex items-start gap-2">
                        <i class="fas fa-info-circle text-amber-600 mt-0.5 shrink-0"></i>
                        <span>
                            <strong>Peringatan Formasi Kosong:</strong> Memindahkan karyawan ini akan otomatis meninggalkan formasi kosong pada <em>{{ $employeeDetails['jabatan'] }}</em> di unit saat ini.
                        </span>
                    </div>
                @endif
            </div>

            {{-- ── 2. CENTER CONNECTOR (2 Cols) ── --}}
            <div class="lg:col-span-2 flex flex-col items-center justify-center py-4 lg:py-0">
                @php
                    $type = $targetAnalysis['type'] ?? null;
                    $badgeBg = match($type) {
                        'vacant'   => 'bg-primary-50 border-primary-200 text-primary-800',
                        'occupied' => 'bg-earth-100 border-earth-300 text-earth-800',
                        'same'     => 'bg-red-50 border-red-200 text-danger',
                        default    => 'bg-surface-100 border-surface-200 text-surface-500',
                    };
                    $iconClass = match($type) {
                        'vacant'   => 'bg-primary-600 text-white shadow-sm',
                        'occupied' => 'bg-earth-600 text-white shadow-sm',
                        'same'     => 'bg-danger text-white shadow-sm',
                        default    => 'bg-surface-200 text-surface-500 shadow-none',
                    };
                    $icon = match($type) {
                        'vacant'   => 'fa-arrow-right',
                        'occupied' => 'fa-exchange-alt',
                        'same'     => 'fa-ban',
                        default    => 'fa-arrow-right',
                    };
                @endphp

                <div class="flex flex-col items-center gap-2">
                    <div class="w-11 h-11 rounded-full {{ $iconClass }} flex items-center justify-center text-sm transition-all duration-300">
                        <i class="fas {{ $icon }}"></i>
                    </div>
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold border {{ $badgeBg }} text-center transition">
                        @if ($type === 'vacant')
                            Transfer Bersih
                        @elseif ($type === 'occupied')
                            Tukar Jabatan
                        @elseif ($type === 'same')
                            Posisi Sama
                        @else
                            Menunggu Target
                        @endif
                    </span>
                </div>
            </div>

            {{-- ── 3. TARGET POSITION (5 Cols) ── --}}
            <div class="lg:col-span-5 bg-white rounded-lg border border-surface-200 shadow-sm p-5 space-y-4 flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between pb-3 border-b border-surface-100">
                        <div class="flex items-center gap-2.5">
                            <span class="w-6 h-6 rounded-full bg-primary-100 text-primary-800 text-xs font-bold flex items-center justify-center">2</span>
                            <h3 class="text-sm font-bold text-surface-900">Formasi &amp; Posisi Tujuan</h3>
                        </div>
                        <span class="text-[11px] font-semibold text-surface-400 uppercase tracking-wider">Tujuan Penempatan</span>
                    </div>

                    <div class="mt-4 space-y-3">
                        <div>
                            <label class="block text-xs font-bold text-surface-700 mb-1.5">
                                Jabatan Tujuan <span class="text-danger">*</span>
                            </label>
                            <input wire:model.live="targetJabatan" list="jabatan-list"
                                placeholder="Pilih atau ketik nama jabatan tujuan..."
                                @if(!$employeeNik) disabled @endif
                                class="w-full px-3.5 py-2.5 bg-white border border-surface-300 rounded-lg text-xs sm:text-sm font-medium text-surface-800 placeholder-surface-400 focus:outline-none focus:ring-2 focus:ring-primary-300 focus:border-primary-600 transition disabled:bg-surface-100 disabled:cursor-not-allowed">
                            @if(!$employeeNik)
                                <p class="text-xs text-surface-500 mt-1">Pilih karyawan terlebih dahulu untuk memfilter daftar jabatan.</p>
                            @endif
                            <datalist id="jabatan-list">
                                @foreach ($jabatanList as $j)
                                    <option value="{{ $j }}">
                                @endforeach
                            </datalist>
                            @error('targetJabatan')
                                <p class="text-danger text-xs mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-surface-700 mb-1.5">
                                Unit Kerja Tujuan <span class="text-danger">*</span>
                            </label>
                            <input wire:model.live="targetUnitKerja" list="unit-list"
                                placeholder="Pilih atau ketik unit kerja tujuan..."
                                @if(!$targetJabatan) disabled @endif
                                class="w-full px-3.5 py-2.5 bg-white border border-surface-300 rounded-lg text-xs sm:text-sm font-medium text-surface-800 placeholder-surface-400 focus:outline-none focus:ring-2 focus:ring-primary-300 focus:border-primary-600 transition disabled:bg-surface-100 disabled:cursor-not-allowed">
                            <datalist id="unit-list">
                                @foreach ($unitList as $u)
                                    <option value="{{ $u }}">
                                @endforeach
                            </datalist>
                            @error('targetUnitKerja')
                                <p class="text-danger text-xs mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Target Analysis Result Banner --}}
                        @if ($targetAnalysis)
                            @if ($targetAnalysis['type'] === 'vacant')
                                <div class="p-3.5 bg-primary-50 border border-primary-200 rounded-lg flex items-start gap-2.5">
                                    <div class="w-6 h-6 rounded-md bg-primary-600 text-white flex items-center justify-center text-xs shrink-0 mt-0.5">
                                        <i class="fas fa-door-open"></i>
                                    </div>
                                    <div class="text-xs text-primary-950 leading-relaxed">
                                        <span class="font-bold text-primary-900">Formasi Kosong (Open Seat)</span><br>
                                        Tidak ada pejabat aktif di posisi ini. Karyawan akan langsung ditugaskan mengisi formasi ini secara bersih.
                                    </div>
                                </div>
                            @elseif ($targetAnalysis['type'] === 'occupied')
                                <div class="p-3.5 bg-earth-100/70 border border-earth-300 rounded-lg space-y-3">
                                    <div class="flex items-start gap-2.5">
                                        <div class="w-6 h-6 rounded-md bg-earth-600 text-white flex items-center justify-center text-xs shrink-0 mt-0.5">
                                            <i class="fas fa-user-check"></i>
                                        </div>
                                        <div class="text-xs text-surface-900 leading-relaxed">
                                            <span class="font-bold text-earth-800">Posisi Sedang Ditempati:</span>
                                            <div class="font-bold text-surface-900 text-sm mt-0.5">{{ $targetAnalysis['occupant']['nama'] }}</div>
                                            <div class="text-[11px] text-surface-600 font-mono">NIK: {{ $targetAnalysis['occupant']['nik'] }} | Gol: {{ $targetAnalysis['occupant']['golongan'] ?? '—' }}</div>
                                            <p class="mt-1 text-surface-700">
                                                Sistem akan menjalankan <strong>Tukar Jabatan 2 Arah</strong> secara simultan. Tidak ada lowongan baru yang tertinggal.
                                            </p>
                                        </div>
                                    </div>

                                    {{-- Swap Guard Checkbox --}}
                                    <label class="flex items-start gap-2.5 cursor-pointer pt-2.5 border-t border-earth-300">
                                        <input type="checkbox" wire:model.live="swapConfirmed"
                                            class="mt-0.5 w-4 h-4 rounded text-earth-600 border-earth-400 focus:ring-earth-500 cursor-pointer">
                                        <span class="text-xs font-semibold text-surface-900 leading-tight">
                                            Saya mengonfirmasi bahwa pertukaran posisi kedua karyawan ini telah disetujui secara resmi.
                                        </span>
                                    </label>
                                    @error('swapConfirmed')
                                        <p class="text-danger text-xs font-semibold">{{ $message }}</p>
                                    @enderror
                                </div>
                            @elseif ($targetAnalysis['type'] === 'same')
                                <div class="p-3.5 bg-red-50 border border-red-200 rounded-lg flex items-center gap-2.5">
                                    <i class="fas fa-ban text-danger text-sm shrink-0"></i>
                                    <p class="text-xs text-danger font-bold">Karyawan sudah berada di posisi dan unit kerja ini.</p>
                                </div>
                            @endif
                        @else
                            <div class="p-6 rounded-lg bg-surface-50 border border-dashed border-surface-300 text-center">
                                <div class="w-10 h-10 mx-auto rounded-full bg-surface-100 text-surface-400 flex items-center justify-center text-sm mb-2">
                                    <i class="fas fa-map-marker-alt"></i>
                                </div>
                                <p class="text-xs font-medium text-surface-500">Pilih nama jabatan &amp; unit tujuan untuk melihat analisis formasi</p>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        {{-- ═══════════════════ ROW 2: AFTER-SWAP PREVIEW (Only for Occupied) ═══════════════════ --}}
        @if ($targetAnalysis && $targetAnalysis['type'] === 'occupied' && $employeeDetails)
            <div class="bg-earth-50 rounded-lg border border-earth-200 p-5 shadow-sm space-y-3">
                <div class="flex items-center gap-2">
                    <i class="fas fa-eye text-earth-700 text-sm"></i>
                    <h3 class="text-xs sm:text-sm font-bold text-earth-900 uppercase tracking-wider">
                        Hasil Akhir Setelah Pertukaran Jabatan
                    </h3>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div class="p-3.5 bg-white rounded-lg border border-earth-300 shadow-xs">
                        <span class="text-[10px] font-bold text-earth-700 uppercase tracking-wider block mb-1">
                            {{ $employeeDetails['nama'] }} akan menjabat:
                        </span>
                        <p class="text-sm font-bold text-surface-900">{{ $targetAnalysis['occupant']['jabatan'] }}</p>
                        <p class="text-xs text-surface-600 mt-0.5">{{ $targetAnalysis['occupant']['unit_kerja'] }}</p>
                    </div>
                    <div class="p-3.5 bg-white rounded-lg border border-earth-300 shadow-xs">
                        <span class="text-[10px] font-bold text-primary-700 uppercase tracking-wider block mb-1">
                            {{ $targetAnalysis['occupant']['nama'] }} akan menjabat:
                        </span>
                        <p class="text-sm font-bold text-surface-900">{{ $employeeDetails['jabatan'] }}</p>
                        <p class="text-xs text-surface-600 mt-0.5">{{ $employeeDetails['unit_kerja'] }}</p>
                    </div>
                </div>
            </div>
        @endif

        {{-- ═══════════════════ ROW 3: LEGALITY & DECREE (SK) ═══════════════════ --}}
        <div class="bg-white rounded-lg border border-surface-200 shadow-sm p-5 space-y-4">
            <div class="flex items-center justify-between pb-3 border-b border-surface-100">
                <div class="flex items-center gap-2.5">
                    <span class="w-6 h-6 rounded-full bg-primary-100 text-primary-800 text-xs font-bold flex items-center justify-center">3</span>
                    <h3 class="text-sm font-bold text-surface-900">Detail Surat Keputusan (SK) &amp; Legalitas</h3>
                </div>
                <span class="text-[11px] font-semibold text-surface-400 uppercase tracking-wider">Audit Trail</span>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-surface-700 mb-1.5">
                        TMT Berlaku (Efektif) <span class="text-danger">*</span>
                    </label>
                    <input type="date" wire:model="tmtAwal"
                        class="w-full px-3.5 py-2.5 bg-white border border-surface-300 rounded-lg text-xs sm:text-sm font-medium text-surface-800 focus:outline-none focus:ring-2 focus:ring-primary-300 focus:border-primary-600 transition">
                    @error('tmtAwal')
                        <p class="text-danger text-xs mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label class="block text-xs font-bold text-surface-700 mb-1.5">
                        Nomor SK / Surat Tugas
                    </label>
                    <input type="text" wire:model="nomorSk" placeholder="Contoh: SK.DIR/REG5/MUT-SDM/01/2026"
                        class="w-full px-3.5 py-2.5 bg-white border border-surface-300 rounded-lg text-xs sm:text-sm font-medium text-surface-800 placeholder-surface-400 focus:outline-none focus:ring-2 focus:ring-primary-300 focus:border-primary-600 transition">
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-surface-700 mb-1.5">
                        Tanggal Penerbitan SK
                    </label>
                    <input type="date" wire:model="tanggalSk"
                        class="w-full px-3.5 py-2.5 bg-white border border-surface-300 rounded-lg text-xs sm:text-sm font-medium text-surface-800 focus:outline-none focus:ring-2 focus:ring-primary-300 focus:border-primary-600 transition">
                </div>

                <div>
                    <label class="block text-xs font-bold text-surface-700 mb-1.5">
                        Catatan / Dasar Pertimbangan
                    </label>
                    <input type="text" wire:model="catatan" placeholder="Keterangan rotasi unit, promosi, dsb..."
                        class="w-full px-3.5 py-2.5 bg-white border border-surface-300 rounded-lg text-xs sm:text-sm font-medium text-surface-800 placeholder-surface-400 focus:outline-none focus:ring-2 focus:ring-primary-300 focus:border-primary-600 transition">
                </div>
            </div>
        </div>

        {{-- ═══════════════════ SUBMIT ACTIONS ═══════════════════ --}}
        <div class="flex items-center justify-between pt-2">
            <button type="button" @click="setTab('vacancies')"
                class="inline-flex items-center gap-2 px-4 py-2.5 rounded-md border border-surface-300 text-surface-700 hover:bg-surface-50 text-xs sm:text-sm font-medium transition shadow-xs">
                <i class="fas fa-arrow-left text-xs"></i>
                <span>Kembali ke Monitoring Formasi</span>
            </button>

            <button type="submit"
                wire:loading.attr="disabled"
                wire:target="submitMutasi"
                class="inline-flex items-center gap-2 px-6 py-2.5 rounded-md bg-primary-600 hover:bg-primary-700 text-white text-xs sm:text-sm font-semibold shadow-sm transition disabled:opacity-50 disabled:cursor-not-allowed">
                <span wire:loading.remove wire:target="submitMutasi">
                    <i class="fas fa-check-circle text-xs mr-1"></i> Simpan &amp; Sahkan Mutasi
                </span>
                <span wire:loading wire:target="submitMutasi" class="flex items-center gap-2">
                    <i class="fas fa-spinner fa-spin text-xs"></i> Menyimpan Perubahan...
                </span>
            </button>
        </div>

    </form>
</div>
