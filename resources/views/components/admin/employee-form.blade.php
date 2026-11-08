@props([
    'employee' => null,
    'action',
    'method' => 'POST',
    'prefill' => []
])
{{-- Jalur rekrutmen mengikuti daftar baku Employee::ENTRY_CHANNELS (DOMAIN.md Bab 2.1): CKP, Talent Scouting, RBB, Reguler --}}
@php
    $genders =['L' => 'Laki-laki', 'P' => 'Perempuan'];
    $religions = ['Islam', 'Kristen', 'Katolik', 'Hindu', 'Buddha', 'Konghucu'];
    $familyStatuses = ['K/0', 'K/1', 'K/2', 'K/3', 'TK/0', 'TK/1', 'TK/2', 'TK/3'];
    $educationLevels = ['SD', 'SMP', 'SMA/SMK', 'D1', 'D2', 'D3', 'D4', 'S1', 'S2', 'S3'];
    $levels = ['Karpim' => 'Karyawan Pimpinan', 'Karpel' => 'Karyawan Pelaksana'];
    $entryChannels = \App\Models\Employee::ENTRY_CHANNELS;
    $activeUnits = \App\Models\Unit::where('is_active', true)->orderBy('urutan')->orderBy('nama')->get();
    if ($activeUnits->isNotEmpty()) {
        $groupedUnits = $activeUnits->groupBy('wilayah')
            ->map(fn ($units) => $units->pluck('nama')->toArray())
            ->toArray();
    } else {
        $groupedUnits = \App\Models\Employee::GROUPED_UNITS;
    }
    
    // Default values
    $defaultNik = $employee ? $employee->nik : old('nik', '');
    $defaultNama = old('nama', $employee ? $employee->nama : '');
    $defaultLevel = old('level', $employee ? $employee->level : ($prefill['level'] ?? request('level', 'Karpim')));
    $defaultJabatan = old('jabatan', $employee ? $employee->jabatan : ($prefill['jabatan'] ?? request('jabatan', '')));
    $defaultUnitKerja = old('unit_kerja', $employee ? $employee->unit_kerja : ($prefill['unit_kerja'] ?? request('unit_kerja', '')));
    $defaultGolongan = old('golongan', $employee ? $employee->golongan : ($prefill['golongan'] ?? request('golongan', 'IIIA/00')));
    $defaultJobGrade = old('job_grade', $employee ? $employee->job_grade : '');
    $defaultPersonGrade = old('person_grade', $employee ? $employee->person_grade : '');
    $defaultJalurMasuk = old('jalur_masuk', $employee ? $employee->jalur_masuk : 'Reguler');
    $defaultTanggalLahir = old('tanggal_lahir', $employee && $employee->tanggal_lahir ? $employee->tanggal_lahir->format('Y-m-d') : '');
    $defaultPhoto = $employee && $employee->foto && Storage::disk('public')->exists($employee->foto) ? Storage::disk('public')->url($employee->foto) : '';
    $defaultRmLevel = old('rm_level', $employee ? $employee->rm_level : '');
    $defaultStatusKaryawan = old('status_karyawan', $employee ? $employee->status_karyawan : 'Tetap (PKWTT)');
@endphp

<div class="space-y-6"
    x-data="employeeForm({
        nik: '{{ $defaultNik }}',
        nama: '{{ $defaultNama }}',
        level: '{{ $defaultLevel }}',
        jabatan: '{{ $defaultJabatan }}',
        unitKerja: '{{ $defaultUnitKerja }}',
        golongan: '{{ $defaultGolongan }}',
        jobGrade: '{{ $defaultJobGrade }}',
        personGrade: '{{ $defaultPersonGrade }}',
        jalurMasuk: '{{ $defaultJalurMasuk }}',
        tanggalLahir: '{{ $defaultTanggalLahir }}',
        photoPreview: '{{ $defaultPhoto }}',
        selectedRmLevel: '{{ $defaultRmLevel }}',
        statusKaryawan: '{{ $defaultStatusKaryawan }}',
        isEdit: {{ $employee ? 'true' : 'false' }},
        nikCheckUrl: '{{ route('admin.employees.checkNik') }}'
    })"
    @unit-selected.window="unitKerja = $event.detail.unit">

    <form id="employeeForm" method="POST" action="{{ $action }}" enctype="multipart/form-data" class="space-y-6">
        @csrf
        @if($method === 'PUT')
            @method('PUT')
        @endif

        @if($errors->any())
            @php
                $errorTargets = [
                    'unit_kerja' => 'unitKerjaLabel', 'level' => 'levelLabel', 'nik' => 'nikInput', 'jabatan' => 'jabatanInput',
                    'status_karyawan' => 'statusKaryawanInput', 'rm_level' => 'rmLevelInput', 'person_grade' => 'personGradeInput',
                    'foto' => 'photoTrigger',
                ];
            @endphp
            <div id="errorSummary" tabindex="-1" role="alert" x-init="$nextTick(() => $el.focus())"
                 class="rounded-xl border border-danger-200 bg-danger-50 p-4 sm:p-5 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500">
                <p class="text-sm font-bold text-danger-700">
                    <i class="fas fa-circle-exclamation mr-1.5" aria-hidden="true"></i>
                    {{ $errors->count() }} isian perlu diperbaiki sebelum data dapat disimpan
                </p>
                <ul class="mt-2 list-disc pl-5 space-y-1 text-sm text-danger-700">
                    @foreach($errors->messages() as $field => $messages)
                        <li><a href="#{{ $errorTargets[$field] ?? $field }}" class="underline font-medium rounded focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500">{{ $messages[0] }}</a></li>
                    @endforeach
                </ul>
            </div>
        @endif

        <!-- SEGMEN 1: IDENTITAS PRIBADI & DEMOGRAFI -->
        <div class="bg-white rounded-2xl border border-surface-200/80 shadow-xs p-6 sm:p-7 space-y-6">
            <div class="flex items-center gap-3 pb-4 border-b border-surface-100">
                <span class="w-8 h-8 rounded-xl bg-primary-100 text-primary-800 text-xs font-extrabold flex items-center justify-center shrink-0">1</span>
                <div>
                    <h3 class="text-base font-extrabold text-surface-900">Identitas Pribadi &amp; Demografi Karyawan</h3>
                    <p class="text-xs text-surface-500">Foto resmi, Nomor Induk Karyawan, kependudukan, dan kualifikasi pendidikan formal</p>
                </div>
                <p class="ml-auto self-start text-[11px] text-surface-500 whitespace-nowrap hidden sm:block">Kolom bertanda <span class="text-danger font-bold" aria-hidden="true">*</span><span class="sr-only">bintang</span> wajib diisi</p>
            </div>

            <!-- Inline Modern Avatar Upload Bar -->
            <div class="p-4 sm:p-5 rounded-2xl bg-surface-50 border border-surface-200/80 flex flex-col sm:flex-row items-center gap-5">
                <div class="relative group cursor-pointer shrink-0" @click="$refs.photoInput.click()">
                    <div class="w-24 h-24 rounded-2xl border-2 border-primary-500/30 shadow-sm overflow-hidden bg-white flex items-center justify-center transition group-hover:border-primary-500">
                        <template x-if="photoPreview">
                            <img :src="photoPreview" alt="Preview Foto" class="w-full h-full object-cover">
                        </template>
                        <template x-if="!photoPreview">
                            <div class="text-surface-300 flex flex-col items-center justify-center">
                                <i class="fas fa-user-circle text-4xl text-surface-300 mb-1" aria-hidden="true"></i>
                                <span class="text-[10px] text-surface-500 font-semibold">Unggah</span>
                            </div>
                        </template>
                    </div>
                    <div class="absolute -bottom-1 -right-1 w-7 h-7 rounded-xl bg-primary-600 text-white flex items-center justify-center text-xs shadow-md border-2 border-white group-hover:scale-110 transition">
                        <i class="fas fa-camera text-[10px]" aria-hidden="true"></i>
                    </div>
                </div>

                <div class="space-y-1.5 text-center sm:text-left flex-1 min-w-0">
                    <div class="flex flex-wrap items-center justify-center sm:justify-start gap-2">
                        <h4 class="text-xs font-bold text-surface-800">Foto Profil Karyawan</h4>
                    </div>
                    <p class="text-xs text-surface-500 leading-relaxed max-w-xl">
                        Unggah foto resmi berlatar belakang polos. Format <span class="font-semibold text-surface-700">JPG, PNG, atau WebP</span> (maks. 2 MB).
                    </p>
                    <div class="pt-1 flex flex-wrap items-center justify-center sm:justify-start gap-2">
                        <button type="button" id="photoTrigger"
                                @click="$refs.photoInput.click()"
                                class="px-3.5 py-1.5 rounded-xl border border-surface-200 bg-white text-surface-700 hover:bg-surface-50 text-xs font-semibold transition cursor-pointer shadow-2xs inline-flex items-center gap-1.5">
                            <i class="fas fa-upload text-[11px] text-primary-600" aria-hidden="true"></i>
                            <span x-text="photoPreview ? 'Ganti Foto' : 'Pilih Foto'"></span>
                        </button>
                        <button type="button" 
                                x-show="photoPreview" 
                                x-cloak
                                @click="removePhoto()"
                                class="px-3 py-1.5 rounded-xl border border-danger-200 bg-white text-danger hover:bg-danger-50 text-xs font-semibold transition cursor-pointer inline-flex items-center gap-1.5">
                            <i class="fas fa-trash-can text-[11px]" aria-hidden="true"></i>
                            <span>Hapus</span>
                        </button>
                    </div>
                    @error('foto') <p class="text-danger-700 text-sm mt-1">{{ $message }}</p> @enderror
                </div>

                <input type="file" 
                       name="foto" 
                       x-ref="photoInput" 
                       accept="image/jpeg,image/png,image/jpg,image/webp" 
                       @change="handlePhotoChange($event)"
                       class="hidden">
            </div>

            <!-- Form Fields Grid: 3-Columns Layout on Desktop -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
                <!-- NIK -->
                <div>
                    <div class="flex items-center justify-between mb-1.5">
                        <label @if(!$employee) for="nikInput" @endif class="block text-xs font-bold text-surface-700">NIK (Nomor Induk Karyawan) @if(!$employee)<span class="text-danger">*</span>@endif</label>
                        @if($employee)
                        <span class="text-[10px] text-surface-500"><i class="fas fa-lock text-[10px] mr-1" aria-hidden="true"></i>Terkunci</span>
                        @else
                        <span class="text-[10px] font-mono" :class="nik.length === 8 ? 'text-primary-600 font-bold' : 'text-surface-500'">
                            <span x-text="nik.length"></span>/8 digit
                        </span>
                        @endif
                    </div>
                    <div class="relative">
                        <span class="absolute left-3.5 inset-y-0 flex items-center text-surface-500 text-xs">
                            <i class="fas fa-id-card" aria-hidden="true"></i>
                        </span>
                        @if($employee)
                        <input type="text" 
                               value="{{ $employee->nik }}" 
                               readonly
                               class="w-full pl-9 pr-3.5 py-2.5 bg-surface-100 border border-surface-200 rounded-lg text-xs sm:text-sm font-mono font-bold text-surface-600 cursor-not-allowed">
                        @else
                        <input type="text" 
                               name="nik" 
                               id="nikInput"
                               aria-required="true"
                               inputmode="numeric"
                               autocomplete="off"
                               x-model="nik" 
                               value="{{ old('nik') }}" 
                               required 
                               maxlength="8" 
                               pattern="[0-9]{8}" 
                               placeholder="Contoh: 13009988"
                               :aria-invalid="nikStatus === 'taken' ? 'true' : null"
                               aria-describedby="nikStatusText"
                               :class="nikStatus === 'taken' || {{ $errors->has('nik') ? 'true' : 'false' }} ? 'border-danger ring-2 ring-danger/20' : 'border-surface-200'"
                               class="w-full pl-9 pr-3.5 py-2.5 bg-surface-50/70 border rounded-lg text-xs sm:text-sm font-mono font-medium text-surface-800 focus:bg-white focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/30 focus-visible:border-primary-500 transition">
                        @endif
                    </div>
                    @if($employee)
                    <p class="text-[11px] text-surface-500 mt-1">NIK adalah primary key tetap.</p>
                    @endif
                    @if(!$employee)
                    <div id="nikStatusText" aria-live="polite">
                        <p x-show="nikStatus === 'checking'" x-cloak class="text-[11px] text-surface-500 mt-1">Memeriksa NIK...</p>
                        <p x-show="nikStatus === 'taken'" x-cloak class="text-danger-700 text-sm mt-1">NIK sudah terdaftar. Periksa kembali atau <a :href="nikShowUrl" class="underline font-semibold rounded focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500">buka data karyawan tersebut</a>.</p>
                        <p x-show="nikStatus === 'ok'" x-cloak class="text-[11px] text-primary-700 mt-1"><i class="fas fa-check-circle mr-1" aria-hidden="true"></i>NIK tersedia.</p>
                    </div>
                    @endif
                    @error('nik') <p class="text-danger-700 text-sm mt-1">{{ $message }}</p> @enderror
                </div>

                <!-- Nama Lengkap (Spans 2 cols on lg) -->
                <div class="sm:col-span-1 lg:col-span-2">
                    <x-admin.form.input 
                        name="nama" 
                        label="Nama Lengkap" 
                        icon="fas fa-user"
                        x-model="nama" 
                        required 
                        placeholder="Nama lengkap sesuai KTP"
                    />
                </div>

                <!-- Jenis Kelamin -->
                <div>
                    <x-admin.form.select 
                        name="jenis_kelamin" 
                        label="Jenis Kelamin" 
                        :options="$genders" 
                        placeholder="-- Pilih Jenis Kelamin --"
                    />
                </div>

                <!-- Agama -->
                <div>
                    <x-admin.form.select 
                        name="agama" 
                        label="Agama" 
                        :options="$religions" 
                        selected="Islam"
                        placeholder="-- Pilih Agama --"
                    />
                </div>

                <!-- Susunan Keluarga PTKP -->
                <div>
                    <x-admin.form.select 
                        name="susunan_keluarga" 
                        label="Susunan Keluarga (PTKP)" 
                        :options="$familyStatuses" 
                        placeholder="-- Pilih Susunan Keluarga --"
                    />
                </div>

                <!-- Tempat Lahir -->
                <div>
                    <x-admin.form.input 
                        name="tempat_lahir" 
                        label="Tempat Lahir" 
                        placeholder="Kota kelahiran sesuai KTP"
                    />
                </div>

                <!-- Tanggal Lahir & Contextual Retirement Calculation -->
                <div>
                    <x-admin.form.input 
                        type="date"
                        name="tanggal_lahir" 
                        label="Tanggal Lahir"
                        x-model="tanggalLahir" 
                        :value="old('tanggal_lahir')"
                    >
                        <x-slot name="headerRight">
                            <span class="text-[10px] text-surface-500">Usia Pensiun 56 thn</span>
                        </x-slot>
                    </x-admin.form.input>
                    
                    <!-- Contextual Live Pill Badges for MBT & BUP -->
                    <div x-show="tanggalLahir" x-cloak class="mt-2 flex flex-wrap items-center gap-1.5">
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-warning-50 text-warning-800 border border-warning-200 text-[10px] font-medium">
                            <abbr title="Masa Bebas Tugas: 6 bulan sebelum pensiun" class="font-bold no-underline cursor-help">MBT (H-6 bln):</abbr>
                            <span class="font-mono" x-text="calculateMbt()"></span>
                        </span>
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-primary-50 text-primary-800 border border-primary-200 text-[10px] font-medium">
                            <abbr title="Batas Usia Pensiun: 56 tahun" class="font-bold no-underline cursor-help">BUP (56):</abbr>
                            <span class="font-mono" x-text="calculatePensiun()"></span>
                        </span>
                    </div>
                </div>

                <!-- Jenjang Pendidikan Terakhir -->
                <div>
                    <x-admin.form.select 
                        name="pendidikan_terakhir" 
                        label="Jenjang Pendidikan Terakhir" 
                        :options="$educationLevels" 
                        placeholder="-- Pilih Jenjang Pendidikan --"
                    />
                </div>

                <!-- Nama Universitas / Sekolah (Full width 3 cols) -->
                <div class="sm:col-span-2 lg:col-span-3">
                    <x-admin.form.input 
                        name="sekolah" 
                        label="Nama Universitas / Institusi Pendidikan" 
                        placeholder="Contoh: Institut Pertanian Stiper (INSTIPER), Universitas Gadjah Mada, IPB"
                    />
                </div>
            </div>
        </div>

        <!-- SEGMEN 2: FORMASI JABATAN & PENEMPATAN KERJA -->
        <div class="bg-white rounded-2xl border border-surface-200/80 shadow-xs p-6 sm:p-7 space-y-6">
            <div class="flex items-center gap-3 pb-4 border-b border-surface-100">
                <span class="w-8 h-8 rounded-xl bg-primary-100 text-primary-800 text-xs font-extrabold flex items-center justify-center shrink-0">2</span>
                <div>
                    <h3 class="text-base font-extrabold text-surface-900">Formasi Jabatan &amp; Penempatan Unit Kerja</h3>
                        <p class="text-xs text-surface-500">Struktur posisi, unit kerja definitif, dan strata Karpim/Karpel</p>
                    </div>
                </div>

            <div class="space-y-5">
                {{-- Level Karyawan & Jalur Masuk (DOMAIN.md Bab 2.1) --}}
                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    <!-- Level Karyawan -->
                    <div>
                        <div class="flex items-center justify-between mb-1.5">
                            <label id="levelLabel" class="block text-xs font-bold text-surface-700">Level Strata Karyawan <span class="text-danger">*</span></label>
                            @if($employee)
                            <span class="text-[10px] text-surface-500"><i class="fas fa-lock text-[10px] mr-1" aria-hidden="true"></i>Terkunci</span>
                            @endif
                        </div>
                        <div role="radiogroup" aria-labelledby="levelLabel" aria-required="true" class="grid grid-cols-2 gap-3 @if($employee) opacity-70 pointer-events-none @endif">
                            @foreach($levels as $lvlKey => $lvlName)
                                <label class="relative flex items-center min-h-[42px] px-3 rounded-xl border transition has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-primary-500"
                                       :class="level === '{{ $lvlKey }}' 
                                            ? 'bg-primary-50/80 border-primary-500 ring-2 ring-primary-500/20' 
                                            : 'bg-surface-50/60 border-surface-200 @if(!$employee) hover:bg-surface-100/60 cursor-pointer @endif'">
                                    <input type="radio" 
                                           name="level" 
                                           value="{{ $lvlKey }}" 
                                           x-model="level" 
                                           @if(!$employee) @change="onLevelChange()" @else disabled @endif
                                           class="sr-only">
                                    <div class="flex items-center gap-3 min-w-0">
                                        <div class="w-7 h-7 rounded-lg flex items-center justify-center text-[11px] shrink-0"
                                             :class="level === '{{ $lvlKey }}' ? 'bg-primary-600 text-white shadow-xs' : 'bg-surface-200 text-surface-600'">
                                            <i class="fas {{ $lvlKey === 'Karpim' ? 'fa-user-tie' : 'fa-people-carry-box' }}" aria-hidden="true"></i>
                                        </div>
                                        <div class="min-w-0">
                                            <div class="text-xs sm:text-sm font-bold text-surface-900 truncate">{{ $lvlKey === 'Karpim' ? 'Karyawan Pimpinan' : 'Karyawan Pelaksana' }}</div>
                                        </div>
                                    </div>
                                </label>
                            @endforeach
                        </div>
                        @if($employee)
                        <p class="text-[11px] text-surface-500 mt-1.5">Gunakan modul Mutasi untuk mengubah strata/promosi.</p>
                        @endif
                        @error('level') <p class="text-danger-700 text-sm mt-1">{{ $message }}</p> @enderror
                    </div>

                    {{-- Jalur Masuk / Rekrutmen Karyawan (DOMAIN.md Bab 2.1) --}}
                    <div>
                        <x-admin.form.select 
                            name="jalur_masuk" 
                            label="Jalur Masuk / Rekrutmen" 
                            icon="fas fa-route"
                            x-model="jalurMasuk"
                            required
                            placeholder=""
                            :options="$entryChannels"
                        />
                    </div>
                </div>

                <!-- Jabatan & Unit Kerja Form Inputs -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    <!-- Unit Kerja -->
                    <div>
                        <div class="flex items-center justify-between mb-1.5">
                            <label id="unitKerjaLabel" class="block text-xs font-bold text-surface-700">Unit Kerja Penempatan <span class="text-danger">*</span></label>
                        </div>
                        @if($employee)
                        <div class="relative">
                            <span class="absolute left-3.5 inset-y-0 flex items-center text-surface-500 text-xs">
                                <i class="fas fa-building" aria-hidden="true"></i>
                            </span>
                            <input type="text" 
                                   disabled
                                   value="{{ $employee->unit_kerja }}" 
                                   class="w-full pl-9 pr-3.5 py-2.5 bg-surface-100 border border-surface-200 rounded-lg text-xs sm:text-sm font-medium text-surface-500 cursor-not-allowed">
                            <input type="hidden" name="unit_kerja" value="{{ $employee->unit_kerja }}">
                        </div>
                        @else
                        <x-admin.unit-combobox 
                            :grouped-units="$groupedUnits" 
                            :selected="old('unit_kerja', $prefill['unit_kerja'] ?? '')" 
                            id="unitKerjaInput"
                            label="Unit Kerja Penempatan"
                            name="unit_kerja" />
                        <p x-show="!unitKerja" class="text-[11px] text-surface-500 mt-1">Pilih Unit Kerja terlebih dahulu untuk memunculkan formasi Jabatan.</p>
                        @endif
                        @error('unit_kerja') <p class="text-danger-700 text-sm mt-1">{{ $message }}</p> @enderror
                    </div>

                    <!-- Jabatan -->
                    <div>
                        <div class="flex items-center justify-between mb-1.5">
                            <label for="jabatanInput" class="block text-xs font-bold text-surface-700">Jabatan Formasi <span class="text-danger">*</span></label>
                        </div>
                        <div class="relative">
                            <span class="absolute left-3.5 inset-y-0 flex items-center text-xs transition-colors duration-300" :class="unitKerja ? 'text-primary-600' : 'text-surface-500'">
                                <i class="fas fa-briefcase" aria-hidden="true"></i>
                            </span>
                            @if($employee)
                            <input type="text" 
                                   disabled
                                   value="{{ $employee->jabatan }}" 
                                   class="w-full pl-9 pr-3.5 py-2.5 bg-surface-100 border border-surface-200 rounded-lg text-xs sm:text-sm font-medium text-surface-500 cursor-not-allowed">
                            <input type="hidden" name="jabatan" value="{{ $employee->jabatan }}">
                            @else
                            <select name="jabatan" 
                                    id="jabatanInput" 
                                    aria-required="true"
                                    x-model="jabatan"
                                    :disabled="!unitKerja || activePositionsList.length === 0"
                                    required 
                                    class="w-full pl-9 pr-8 py-2.5 rounded-lg text-xs sm:text-sm font-medium focus:outline-none focus-visible:ring-2 transition-all duration-300 appearance-none"
                                    :class="!unitKerja ? 'bg-surface-100/70 border border-surface-200/60 text-surface-500 cursor-not-allowed' : '{{ $errors->has('jabatan') ? 'border-danger ring-2 ring-danger/20' : 'border-surface-200' }} bg-surface-50/70 border text-surface-800 focus:bg-white focus-visible:ring-primary-500/30 focus-visible:border-primary-500'">
                                <option value="" disabled selected x-text="!unitKerja ? '-- Pilih Unit Kerja Terlebih Dahulu --' : (activePositionsList.length === 0 ? '-- Formasi Kosong / Memuat --' : '-- Pilih Formasi Jabatan --')"></option>
                                <template x-for="pos in activePositionsList" :key="pos">
                                    <option :value="pos" x-text="pos" :selected="jabatan === pos"></option>
                                </template>
                            </select>
                            <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-3.5 text-surface-500">
                                <i class="fas fa-chevron-down text-[10px]" aria-hidden="true"></i>
                            </div>
                            @endif
                        </div>
                        @error('jabatan') <p class="text-danger-700 text-sm mt-1">{{ $message }}</p> @enderror
                    </div>
                </div>

                <!-- Status Penugasan, Karyawan & Kontrak (Dynamic Grid) -->
                <div x-data="{ statusKaryawan: '{{ old('status_karyawan', 'Tetap (PKWTT)') }}' }" 
                     :class="statusKaryawan !== 'Tetap (PKWTT)' ? 'md:grid-cols-3' : 'md:grid-cols-2'" 
                     class="grid grid-cols-1 gap-5 mt-5 transition-all duration-300">
                    
                    <!-- Status Penugasan -->
                    <div>
                        <div class="flex items-center justify-between mb-1.5">
                            <label for="statusPenugasanInput" class="block text-xs font-bold text-surface-700">Status Penugasan <span class="text-danger">*</span></label>
                        </div>
                        <div class="relative">
                            <span class="absolute left-3.5 inset-y-0 flex items-center text-xs text-surface-500">
                                <i class="fas fa-tag" aria-hidden="true"></i>
                            </span>
                            <input type="text" id="statusPenugasanInput" disabled value="Definitif"
                                    class="w-full pl-9 pr-8 py-2.5 bg-surface-100 border border-surface-200 rounded-lg text-xs sm:text-sm font-medium text-surface-500 cursor-not-allowed">
                            <input type="hidden" name="status_penugasan" value="Definitif">
                        </div>

                    </div>

                    <!-- Status Karyawan -->
                    <div>
                        <div class="flex items-center mb-1.5">
                            <label for="statusKaryawanInput" class="block text-xs font-bold text-surface-700">Status Kepegawaian <span class="text-danger">*</span></label>
                        </div>
                        <div class="relative">
                            <span class="absolute left-3.5 inset-y-0 flex items-center text-xs text-surface-500">
                                <i class="fas fa-id-badge" aria-hidden="true"></i>
                            </span>
                            <select name="status_karyawan" id="statusKaryawanInput" aria-required="true" x-model="statusKaryawan" required class="w-full pl-9 pr-8 py-2.5 bg-surface-50/70 border border-surface-200 text-surface-800 rounded-lg text-xs sm:text-sm font-medium focus:outline-none focus:bg-white focus-visible:ring-2 focus-visible:ring-primary-500/30 focus-visible:border-primary-500 transition-all duration-300 appearance-none">
                                @foreach(\App\Models\Employee::EMPLOYMENT_STATUSES as $val => $label)
                                    <option value="{{ $val }}">{{ $label }}</option>
                                @endforeach
                            </select>
                            <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-3.5 text-surface-500">
                                <i class="fas fa-chevron-down text-[10px]" aria-hidden="true"></i>
                            </div>
                        </div>
                        @error('status_karyawan') <p class="text-danger-700 text-sm mt-1">{{ $message }}</p> @enderror
                    </div>

                    <!-- Tanggal Berakhir Kontrak (Hanya tampil jika PKWT / Calon) -->
                    <div x-show="statusKaryawan !== 'Tetap (PKWTT)'" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 translate-x-4" x-transition:enter-end="opacity-100 translate-x-0" style="display: none;">
                        <x-admin.form.input 
                            type="date"
                            name="tanggal_berakhir_kontrak" 
                            label="Batas Berakhir Kontrak"
                            icon="fas fa-calendar-times"
                            :value="old('tanggal_berakhir_kontrak')"
                        />
                    </div>
                </div>

                <!-- Auto-populated Fields (Bidang & RM Band) -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-5 mt-5">
                    <!-- Bidang Fungsional (Read Only / Auto-filled) -->
                    <div>
                        <div class="flex items-center justify-between mb-1.5">
                            <label class="block text-xs font-bold text-surface-700">Bidang Fungsional</label>
                            <span x-show="bidang && bidangFromFormasi" x-transition class="text-[10px] font-bold text-primary-600 bg-primary-50 px-2 py-0.5 rounded-md flex items-center gap-1 border border-primary-100">
                                <i class="fas fa-check-circle text-[10px]" aria-hidden="true"></i> Master Formasi
                            </span>
                            <span x-show="bidang && !bidangFromFormasi" x-cloak x-transition class="text-[10px] font-bold text-warning-700 bg-warning-50 px-2 py-0.5 rounded-md flex items-center gap-1 border border-warning-200">
                                <i class="fas fa-info-circle text-[10px]" aria-hidden="true"></i> Perkiraan
                            </span>
                        </div>
                        <div class="relative">
                            <span class="absolute left-3.5 inset-y-0 flex items-center text-xs transition-colors duration-300" :class="bidang ? 'text-primary-500' : 'text-surface-500'">
                                <i class="fas fa-sitemap" aria-hidden="true"></i>
                            </span>
                            <input type="text" 
                                   readonly 
                                   :value="bidangLabel" 
                                   placeholder="Terisi otomatis..."
                                   class="w-full pl-9 pr-3.5 py-2.5 border rounded-lg text-xs sm:text-sm font-medium transition-all duration-300 cursor-not-allowed focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500"
                                   :class="bidang ? 'bg-primary-50/30 border-primary-200 text-primary-900' : 'bg-surface-50 border-surface-200 text-surface-500'">
                        </div>
                        <p class="text-[11px] mt-1" :class="!jabatan || bidangFromFormasi ? 'text-surface-500' : 'text-warning-700'">
                            <span x-show="!jabatan">Terisi otomatis setelah Jabatan Formasi dipilih.</span>
                            <span x-show="jabatan && bidangFromFormasi" x-cloak>Mengikuti formasi jabatan pada unit kerja di Master Formasi.</span>
                            <span x-show="jabatan && !bidangFromFormasi" x-cloak>Jabatan ini tidak menempati formasi pada unit kerja terpilih; bidang diperkirakan dari nama jabatan dan unit.</span>
                        </p>
                    </div>

                    <!-- Eselon / RM Band (Auto-filled Dropdown) -->
                    <div>
                        <div class="flex items-center justify-between mb-1.5">
                            <label for="rmLevelInput" class="block text-xs font-bold text-surface-700">Eselon / <abbr title="Rentang Manajemen: tingkat band jabatan RM-1 sampai RM-7" class="no-underline cursor-help">RM</abbr> Band <span class="text-danger">*</span></label>
                            <span x-show="computedRmBand && rmFromFormasi" x-transition class="text-[10px] font-bold text-primary-600 bg-primary-50 px-2 py-0.5 rounded-md flex items-center gap-1 border border-primary-100">
                                <i class="fas fa-check-circle text-[10px]" aria-hidden="true"></i> Master Formasi
                            </span>
                            <span x-show="computedRmBand && !rmFromFormasi" x-cloak x-transition class="text-[10px] font-bold text-warning-700 bg-warning-50 px-2 py-0.5 rounded-md flex items-center gap-1 border border-warning-200">
                                <i class="fas fa-info-circle text-[10px]" aria-hidden="true"></i> Perkiraan
                            </span>
                        </div>
                        <div class="relative">
                            <span class="absolute left-3.5 inset-y-0 flex items-center text-xs z-10 transition-colors duration-300" :class="computedRmBand ? 'text-primary-500' : 'text-surface-500'">
                                <i class="fas fa-layer-group" aria-hidden="true"></i>
                            </span>
                            {{-- RM Band melekat pada jabatan di Master Formasi: tampil read-only, nilai dikirim lewat input tersembunyi (DOMAIN.md §3.6, §4) --}}
                            <input type="hidden" name="rm_level" :value="selectedRmLevel">
                            <input type="text" id="rmLevelInput" readonly aria-describedby="rmLevelHint" placeholder="Terisi otomatis..."
                                   :value="selectedRmLevel"
                                   class="w-full pl-9 pr-3.5 py-2.5 bg-surface-50 border rounded-lg text-xs sm:text-sm font-mono font-semibold text-surface-800 cursor-default focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500"
                                   :class="triggerPulse ? 'border-primary-300 ring-2 ring-primary-500/30 bg-primary-50/30' : 'border-surface-200'">
                        </div>
                        <p id="rmLevelHint" class="text-[11px] mt-1" :class="!jabatan || rmFromFormasi ? 'text-surface-500' : 'text-warning-700'">
                            <span x-show="!jabatan">Terisi otomatis setelah Jabatan Formasi dipilih.</span>
                            <span x-show="jabatan && rmFromFormasi" x-cloak>Mengikuti formasi jabatan pada Master Formasi dan tidak dapat diubah manual.</span>
                            <span x-show="jabatan && !rmFromFormasi" x-cloak>Jabatan ini belum memiliki RM Band pada Master Formasi; nilai diperkirakan dari strata dan golongan.</span>
                        </p>
                        @error('rm_level') <p class="text-danger-700 text-sm mt-1">{{ $message }}</p> @enderror
                    </div>
                </div>
            </div>
        </div>

        <!-- SEGMEN 3: GOLONGAN, GRADE, & KETETAPAN SK -->
        <div class="bg-white rounded-2xl border border-surface-200/80 shadow-xs p-6 sm:p-7 space-y-6">
            <div class="flex items-center gap-3 pb-4 border-b border-surface-100">
                <span class="w-8 h-8 rounded-xl bg-primary-100 text-primary-800 text-xs font-extrabold flex items-center justify-center shrink-0">3</span>
                <div>
                    <h3 class="text-base font-extrabold text-surface-900">Golongan, Bobot Grade, &amp; Tanggal SK Dinas</h3>
                        <p class="text-xs text-surface-500">Pangkat kepangkatan, rentang grade, dan riwayat Terhitung Mulai Tanggal (TMT)</p>
                    </div>
                </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-5">
                <!-- Golongan -->
                <div>
                    <x-admin.form.input 
                        name="golongan" 
                        label="Golongan &amp; Berkala"
                        x-model="golongan"
                        ::placeholder="level === 'Karpim' ? 'Contoh: IIIA/00, IIID/02' : 'Contoh: IA/00, IID/00'"
                        ::class="isGolonganWarning ? 'bg-warning-50/50 border-warning text-warning-800 focus-visible:ring-warning/30 focus-visible:border-warning' : ''"
                    >
                        <x-slot name="headerRight">
                            <span class="text-[10px] font-semibold" :class="isGolonganWarning ? 'text-warning-700 font-bold' : 'text-surface-500'" x-text="level === 'Karpim' ? 'Standar: Gol III - IV' : 'Standar: Gol I - II'"></span>
                        </x-slot>
                    </x-admin.form.input>
                    <p x-show="!isGolonganWarning" class="text-[10px] text-surface-500 mt-1">Format: [Gol]/[Berkala]</p>
                    <p x-show="isGolonganWarning" x-cloak class="text-[10px] text-warning-700 font-semibold mt-1 flex items-center gap-1">
                        <i class="fas fa-triangle-exclamation text-[10px]" aria-hidden="true"></i>
                        <span>Standar <strong x-text="level"></strong>: <span x-text="level === 'Karpim' ? 'Gol III / IV' : 'Gol I / II'"></span>.</span>
                    </p>
                </div>

                <!-- Job Grade -->
                <div>
                    <x-admin.form.input 
                        type="number"
                        name="job_grade" 
                        label="Job Grade"
                        x-model="jobGrade"
                        placeholder="Grade jabatan"
                        ::class="isJobGradeWarning ? 'bg-warning-50/50 border-warning text-warning-800 focus-visible:ring-warning/30 focus-visible:border-warning' : ''"
                    >
                        <x-slot name="headerRight">
                            <span class="text-[10px] font-semibold" :class="isJobGradeWarning ? 'text-warning-700 font-bold' : 'text-primary-700'" x-text="level === 'Karpim' ? 'Rentang: 11 - 16' : 'Rentang: 1 - 10'"></span>
                        </x-slot>
                    </x-admin.form.input>
                    <p x-show="!isJobGradeWarning" class="text-[10px] text-surface-500 mt-1">Bobot posisi jabatan</p>
                    <p x-show="isJobGradeWarning" x-cloak class="text-[10px] text-warning-700 font-semibold mt-1 flex items-center gap-1">
                        <i class="fas fa-triangle-exclamation text-[10px]" aria-hidden="true"></i>
                        <span>Di luar standar <strong x-text="level"></strong> (<span x-text="level === 'Karpim' ? '11 - 16' : '1 - 10'"></span>).</span>
                    </p>
                </div>

                <!-- Person Grade -->
                <div>
                    <div class="flex items-center justify-between mb-1.5">
                        <label for="personGradeInput" class="block text-xs font-bold text-surface-700">Person Grade</label>
                        <span class="text-[10px] font-semibold" :class="isPersonGradeWarning ? 'text-warning-700 font-bold' : 'text-primary-700'" x-text="level === 'Karpim' ? 'Rentang: 11 - 16' : 'Rentang: 1 - 10'"></span>
                    </div>
                    <input type="number" 
                           name="person_grade" 
                           id="personGradeInput"
                           x-model="personGrade"
                           placeholder="Grade individu"
                           class="w-full px-3.5 py-2.5 rounded-lg text-xs sm:text-sm font-medium focus:bg-white focus:outline-none focus-visible:ring-2 transition"
                           :class="isPersonGradeWarning 
                                ? 'bg-warning-50/50 border border-warning text-warning-800 focus-visible:ring-warning/30 focus-visible:border-warning' 
                                : 'bg-surface-50/70 border border-surface-200 text-surface-800 focus-visible:ring-primary-500/30 focus-visible:border-primary-500'">
                    <p x-show="!isPersonGradeWarning" class="text-[10px] text-surface-500 mt-1">Kompetensi personal</p>
                    <p x-show="isPersonGradeWarning" x-cloak class="text-[10px] text-warning-700 font-semibold mt-1 flex items-center gap-1">
                        <i class="fas fa-triangle-exclamation text-[10px]" aria-hidden="true"></i>
                        <span>Di luar standar <strong x-text="level"></strong> (<span x-text="level === 'Karpim' ? '11 - 16' : '1 - 10'"></span>).</span>
                    </p>
                    @error('person_grade') <p class="text-danger-700 text-sm mt-1">{{ $message }}</p> @enderror
                </div>

                <!-- TMT Bekerja Awal -->
                <div>
                    <x-admin.form.input 
                        type="date"
                        name="tmt_bekerja" 
                        label="TMT Bekerja Awal"
                        :value="old('tmt_bekerja', now()->toDateString())"
                        hint="Tanggal pertama kali diangkat di PTPN"
                    />
                </div>

                <!-- TMT Unit Kerja -->
                <div>
                    <x-admin.form.input 
                        type="date"
                        name="tmt_unit_kerja" 
                        label="TMT Unit Kerja"
                        :value="old('tmt_unit_kerja', now()->toDateString())"
                        hint="Dasar rotasi dinas unit"
                    />
                </div>

                <!-- Tanggal Dalam Jabatan -->
                <div>
                    <x-admin.form.input 
                        type="date"
                        name="tanggal_dalam_jabatan" 
                        label="Tanggal Dalam Jabatan"
                        :value="old('tanggal_dalam_jabatan', now()->toDateString())"
                        hint="Tanggal SK pengangkatan posisi"
                    />
                </div>

                {{-- Tanggal Diangkat Staf (Adaptive: Disabled for Karpel per DOMAIN.md Bab 4.1) --}}
                <div class="sm:col-span-3">
                    <div class="p-4 rounded-xl border transition"
                         :class="level === 'Karpim' ? 'bg-surface-50/60 border-surface-200' : 'bg-surface-100/70 border-surface-200/60'">
                        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3">
                            <div class="space-y-0.5">
                                <label for="tanggalDiangkatStafInput" class="block text-xs font-bold" :class="level === 'Karpim' ? 'text-surface-700' : 'text-surface-500'">
                                    Tanggal Diangkat Staf (Pimpinan)
                                </label>
                                <p class="text-[11px]" :class="level === 'Karpim' ? 'text-surface-500' : 'text-surface-500'">
                                    <template x-if="level === 'Karpim'">
                                        <span>Tanggal resmi karyawan diangkat menjadi staf pimpinan PTPN (Wajib untuk Karpim).</span>
                                    </template>
                                    <template x-if="level === 'Karpel'">
                                        <span><i class="fas fa-lock text-[10px] mr-1" aria-hidden="true"></i> Tidak berlaku untuk Karyawan Pelaksana (Karpel). Kosong secara otomatis.</span>
                                    </template>
                                </p>
                            </div>
                            <div class="w-full sm:w-64">
                                <input type="date"
                                       id="tanggalDiangkatStafInput"
                                       name="tanggal_diangkat_staf"
                                       :disabled="level === 'Karpel'"
                                       value="{{ old('tanggal_diangkat_staf') }}"
                                       class="w-full px-3.5 py-2 rounded-lg text-xs sm:text-sm font-medium border transition"
                                       :class="level === 'Karpim' 
                                            ? 'bg-white border-surface-200 text-surface-800 focus-visible:ring-2 focus-visible:ring-primary-500/30 focus-visible:border-primary-500' 
                                            : 'bg-surface-200/60 border-surface-200 text-surface-500 cursor-not-allowed'">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sticky / Floating Action Bar -->
        <div class="sticky bottom-4 z-10 bg-white rounded-2xl border border-surface-200/80 p-4 sm:p-5 shadow-xs flex flex-col sm:flex-row items-center justify-between gap-3">
            <div class="text-xs text-surface-500 flex items-center gap-1.5">
                <i class="fas fa-shield-halved text-primary-600" aria-hidden="true"></i>
                <span>Audit trail penempatan akan otomatis tercatat di riwayat jabatan.</span>
            </div>

            <div class="flex items-center gap-3 w-full sm:w-auto justify-end">
                <x-admin.button href="{{ route('admin.employees.index') }}" variant="secondary">
                    Batal
                </x-admin.button>
                <x-admin.button type="submit" once variant="primary" icon="fas fa-check-circle" class="shadow-primary-600/20 shadow-md">
                    {{ $employee ? 'Simpan Perubahan' : 'Simpan Karyawan Baru' }}
                </x-admin.button>
            </div>
        </div>
    </form>

    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('employeeForm', (initialData) => ({
                nik: initialData.nik,
                nama: initialData.nama,
                level: initialData.level,
                jabatan: initialData.jabatan,
                unitKerja: initialData.unitKerja,
                golongan: initialData.golongan,
                jobGrade: initialData.jobGrade,
                personGrade: initialData.personGrade,
                jalurMasuk: initialData.jalurMasuk,
                tanggalLahir: initialData.tanggalLahir,
                photoPreview: initialData.photoPreview,
                selectedRmLevel: initialData.selectedRmLevel,
                statusKaryawan: initialData.statusKaryawan,
                isEdit: initialData.isEdit,
                nikCheckUrl: initialData.nikCheckUrl,
                nikStatus: 'idle',
                nikShowUrl: '#',
                nikTimer: null,

                checkNik() {
                    clearTimeout(this.nikTimer);
                    if (this.isEdit || !/^[0-9]{8}$/.test(this.nik || '')) {
                        this.nikStatus = 'idle';
                        return;
                    }
                    this.nikStatus = 'checking';
                    this.nikTimer = setTimeout(async () => {
                        const nik = this.nik;
                        try {
                            const res = await fetch(`${this.nikCheckUrl}?nik=${encodeURIComponent(nik)}`, {
                                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                            });
                            if (!res.ok || nik !== this.nik) { this.nikStatus = 'idle'; return; }
                            const data = await res.json();
                            this.nikShowUrl = data.url || '#';
                            this.nikStatus = data.exists ? 'taken' : 'ok';
                        } catch (e) {
                            this.nikStatus = 'idle';
                        }
                    }, 300);
                },

                handlePhotoChange(event) {
                    const file = event.target.files[0];
                    if (file) {
                        const reader = new FileReader();
                        reader.onload = (e) => {
                            this.photoPreview = e.target.result;
                        };
                        reader.readAsDataURL(file);
                    }
                },

                removePhoto() {
                    this.photoPreview = null;
                    if (this.$refs.photoInput) {
                        this.$refs.photoInput.value = '';
                    }
                },

                serverBidang: '',
                serverRmBand: '',
                bidangFromFormasi: false,
                rmFromFormasi: false,

                get bidang() {
                    return this.serverBidang;
                },

                get bidangLabel() {
                    const map = {
                        'KEU': 'Keuangan & Akuntansi',
                        'TAN': 'Tanaman & Agronomi',
                        'TEK': 'Teknik & Pengolahan',
                        'UMU': 'Umum & Tata Kelola'
                    };
                    return map[this.serverBidang] || this.serverBidang;
                },

                get bidangColor() {
                    // Bidang adalah metadata deskriptif: netral, tidak diberi warna per bidang.
                    return 'bg-surface-700 text-white';
                },

                triggerPulse: false,

                animatePulse() {
                    this.triggerPulse = true;
                    setTimeout(() => this.triggerPulse = false, 400);
                },

                async fetchClassification() {
                    // Belum ada jabatan: kosongkan hasil agar tidak menampilkan nilai bawaan yang menyesatkan
                    if (!this.jabatan) {
                        this.serverBidang = '';
                        this.serverRmBand = '';
                        this.bidangFromFormasi = false;
                        this.rmFromFormasi = false;
                        this.selectedRmLevel = '';
                        return;
                    }

                    const params = new URLSearchParams({
                        jabatan: this.jabatan || '',
                        unit_kerja: this.unitKerja || '',
                        level: this.level || 'Karpim',
                        golongan: this.golongan || '',
                        job_grade: this.jobGrade || ''
                    });
                    
                    try {
                        const res = await fetch(`/admin/api/classify-position?${params.toString()}`);
                        if (!res.ok) throw new Error('API Error');
                        const data = await res.json();
                        
                        this.serverBidang = data.bidang;
                        this.serverRmBand = data.rm_band;
                        this.bidangFromFormasi = !!data.bidang_from_formasi;
                        this.rmFromFormasi = !!data.rm_from_formasi;

                        this.selectedRmLevel = this.serverRmBand;
                        this.animatePulse();
                    } catch (e) {
                        console.error('Gagal mengambil inferensi klasifikasi', e);
                    }
                },

                init() {
                    this.fetchClassification();
                    this.$watch('nik', () => this.checkNik());
                    
                    let timeout;
                    const debouncedFetch = () => {
                        clearTimeout(timeout);
                        timeout = setTimeout(() => this.fetchClassification(), 300);
                    };
                    
                    this.$watch('jabatan', debouncedFetch);
                    this.$watch('level', () => {
                        debouncedFetch();
                        this.fetchUnitPositions();
                    });
                    this.$watch('golongan', debouncedFetch);
                    this.$watch('jobGrade', debouncedFetch);
                    this.$watch('unitKerja', () => {
                        debouncedFetch();
                        this.fetchUnitPositions();
                    });
                    
                    if (this.unitKerja) {
                        this.fetchUnitPositions();
                    }
                },
                
                activePositionsList: [],
                
                async fetchUnitPositions() {
                    if (!this.unitKerja) {
                        this.activePositionsList = [];
                        return;
                    }
                    try {
                        const res = await fetch(`/admin/api/unit-positions?unit_name=${encodeURIComponent(this.unitKerja)}&level=${encodeURIComponent(this.level)}`, {
                            headers: {
                                'Accept': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest'
                            }
                        });
                        if (!res.ok) throw new Error('API Error');
                        const data = await res.json();
                        this.activePositionsList = data.positions || [];
                    } catch (e) {
                        console.error('Gagal mengambil daftar jabatan unit', e);
                        this.activePositionsList = [];
                    }
                },

                get computedRmBand() {
                    return this.serverRmBand;
                },

                get activeRmLevel() {
                    return this.selectedRmLevel || this.serverRmBand;
                },

                calculatePensiun() {
                    if (!this.tanggalLahir) return null;
                    let parts = this.tanggalLahir.split('-');
                    if (parts.length !== 3) return null;
                    let year = parseInt(parts[0]) + 56;
                    let month = parts[1];
                    return `01-${month}-${year}`;
                },

                calculateMbt() {
                    if (!this.tanggalLahir) return null;
                    let parts = this.tanggalLahir.split('-');
                    if (parts.length !== 3) return null;
                    // Pensiun = awal bulan lahir + 56 tahun; MBT = 6 bulan sebelumnya (KepegawaianPolicy::MBT_BULAN_SEBELUM_PENSIUN).
                    let year = parseInt(parts[0]) + 56;
                    let month = parseInt(parts[1]) - 6;
                    if (month < 1) { month += 12; year -= 1; }
                    return `01-${String(month).padStart(2, '0')}-${year}`;
                },

                get isJobGradeWarning() {
                    if (!this.jobGrade) return false;
                    const val = parseInt(this.jobGrade);
                    if (this.level === 'Karpim') return val < 11 || val > 16;
                    if (this.level === 'Karpel') return val < 1 || val > 10;
                    return false;
                },

                get isPersonGradeWarning() {
                    if (!this.personGrade) return false;
                    const val = parseInt(this.personGrade);
                    if (this.level === 'Karpim') return val < 11 || val > 16;
                    if (this.level === 'Karpel') return val < 1 || val > 10;
                    return false;
                },

                get isGolonganWarning() {
                    if (!this.golongan) return false;
                    const g = this.golongan.toUpperCase().trim();
                    if (this.level === 'Karpim') {
                        return g.startsWith('I/') || g.startsWith('IA') || g.startsWith('IB') || g.startsWith('IC') || g.startsWith('ID') ||
                               g.startsWith('II/') || g.startsWith('IIA') || g.startsWith('IIB') || g.startsWith('IIC') || g.startsWith('IID');
                    }
                    if (this.level === 'Karpel') {
                        return g.startsWith('III') || g.startsWith('IV');
                    }
                    return false;
                },

                onLevelChange() {
                    if (this.level === 'Karpel') {
                        if (this.golongan && this.golongan.startsWith('III')) {
                            this.golongan = 'IIA/00';
                        }
                    } else {
                        if (this.golongan && (this.golongan.startsWith('I/') || this.golongan.startsWith('II/') || this.golongan.startsWith('IIA'))) {
                            this.golongan = 'IIIA/00';
                        }
                    }
                }
            }));
        });
    </script>
</div>
