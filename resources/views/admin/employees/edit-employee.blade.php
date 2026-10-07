@extends('layouts.admin')

@section('content')
<div class="space-y-6 max-w-5xl mx-auto"
    x-data="{
        nik: '{{ $employee->nik }}',
        nama: '{{ old('nama', $employee->nama) }}',
        level: '{{ old('level', $employee->level) }}',
        jabatan: '{{ old('jabatan', $employee->jabatan) }}',
        unitKerja: '{{ old('unit_kerja', $employee->unit_kerja) }}',
        golongan: '{{ old('golongan', $employee->golongan) }}',
        jobGrade: '{{ old('job_grade', $employee->job_grade) }}',
        personGrade: '{{ old('person_grade', $employee->person_grade) }}',
        jalurMasuk: '{{ old('jalur_masuk', $employee->jalur_masuk ?? 'Reguler') }}',
        tanggalLahir: '{{ old('tanggal_lahir', $employee->tanggal_lahir?->format('Y-m-d')) }}',
        photoPreview: '{{ $employee->foto && Storage::disk('public')->exists($employee->foto) ? Storage::disk('public')->url($employee->foto) : '' }}',

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

        // Automated Domain Predictions (SSOT from Backend via API)
        serverBidang: 'UMU',
        serverRmBand: 'RM-3',

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
            const map = {
                'KEU': 'bg-blue-600 text-white',
                'TAN': 'bg-emerald-600 text-white',
                'TEK': 'bg-amber-600 text-white',
                'UMU': 'bg-purple-600 text-white'
            };
            return map[this.serverBidang] || 'bg-slate-600 text-white';
        },

        selectedRmLevel: '{{ old('rm_level', $employee->rm_level ?? '') }}',
        isRmOverridden: {{ old('rm_level', $employee->rm_level) ? 'true' : 'false' }},
        triggerPulse: false,

        animatePulse() {
            this.triggerPulse = true;
            setTimeout(() => this.triggerPulse = false, 400);
        },

        async fetchClassification() {
            const params = new URLSearchParams({
                jabatan: this.jabatan || '',
                unit_kerja: this.unitKerja || '',
                level: this.level || 'Karpim',
                golongan: this.golongan || '',
                job_grade: this.jobGrade || ''
            });
            
            try {
                const res = await fetch(`{{ route('admin.api.classify-position') }}?${params.toString()}`);
                if (!res.ok) throw new Error('API Error');
                const data = await res.json();
                
                this.serverBidang = data.bidang;
                this.serverRmBand = data.rm_band;
                
                if (!this.isRmOverridden) {
                    this.selectedRmLevel = this.serverRmBand;
                }
                this.animatePulse();
            } catch (e) {
                console.error('Gagal mengambil inferensi klasifikasi', e);
            }
        },

        init() {
            // Initial load
            this.fetchClassification();
            
            // Watchers with 300ms debounce
            let timeout;
            const debouncedFetch = () => {
                clearTimeout(timeout);
                timeout = setTimeout(() => this.fetchClassification(), 300);
            };
            
            this.$watch('jabatan', debouncedFetch);
            this.$watch('level', debouncedFetch);
            this.$watch('golongan', debouncedFetch);
            this.$watch('jobGrade', debouncedFetch);
            this.$watch('unitKerja', debouncedFetch);
        },

        get computedRmBand() {
            return this.serverRmBand;
        },

        get activeRmLevel() {
            return this.selectedRmLevel || this.serverRmBand;
        },

        get rmBandTitle() {
            const map = {
                'RM-1': 'Pimpinan Puncak (Eselon RM-1)',
                'RM-2': 'Manajemen Madya (Eselon RM-2)',
                'RM-3': 'Manajemen Pratama (Eselon RM-3)',
                'RM-4': 'Karyawan Pelaksana (Eselon RM-4)'
            };
            return map[this.activeRmLevel] || this.activeRmLevel;
        },

        resetRmToAuto() {
            this.isRmOverridden = false;
            this.selectedRmLevel = this.serverRmBand;
        },

        onRmChange() {
            this.isRmOverridden = (this.selectedRmLevel !== this.serverRmBand);
        },

        // DOMAIN.md Bab 5.1: Batas Usia Pensiun (56 tahun) & MBT (55 tahun)
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
            let year = parseInt(parts[0]) + 55;
            let month = parts[1];
            return `01-${month}-${year}`;
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
    }"
    @unit-selected.window="unitKerja = $event.detail.unit">

    <!-- Header Section -->
    <div class="bg-white rounded-2xl border border-slate-200/80 p-6 shadow-xs flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
        <div class="flex items-center gap-4">
            <div class="w-12 h-12 rounded-2xl bg-gradient-to-br from-emerald-600 to-teal-700 text-white flex items-center justify-center text-xl shadow-md shadow-emerald-600/20 shrink-0">
                <i class="fas fa-user-pen"></i>
            </div>
            <div>
                <div class="flex items-center gap-2">
                    <h1 class="text-xl sm:text-2xl font-extrabold text-slate-900 tracking-tight">Edit Data Pegawai</h1>
                    <span class="px-2.5 py-0.5 rounded-full text-[11px] font-mono font-bold bg-slate-100 text-slate-700 border border-slate-200">
                        {{ $employee->nik }}
                    </span>
                </div>
                <p class="text-slate-500 text-xs sm:text-sm mt-0.5">Perbarui profil induk, formasi penempatan, dan pangkat dinas pegawai</p>
            </div>
        </div>

        <div class="flex items-center gap-2">
            <a href="{{ route('admin.employees.show', $employee->nik) }}"
                class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl border border-slate-200 text-slate-600 hover:bg-slate-50 text-xs sm:text-sm font-semibold transition shadow-xs">
                <i class="fas fa-arrow-left text-xs"></i>
                <span>Kembali</span>
            </a>
        </div>
    </div>

    @if (session('success'))
        <div class="p-4 bg-emerald-50 border border-emerald-200 rounded-2xl text-xs font-semibold text-emerald-800 flex items-center gap-2">
            <i class="fas fa-check-circle text-emerald-600 text-sm"></i>
            <span>{{ session('success') }}</span>
        </div>
    @endif

    <!-- Form & Single-Column Streamlined Layout -->
    <form action="{{ route('admin.employees.update', $employee->nik) }}" method="POST" enctype="multipart/form-data" class="space-y-6">
        @csrf
        @method('PUT')

        <!-- SEGMEN 1: IDENTITAS PRIBADI & DEMOGRAFI -->
        <div class="bg-white rounded-2xl border border-slate-200/80 shadow-xs p-6 sm:p-7 space-y-6">
            <div class="flex items-center gap-3 pb-4 border-b border-slate-100">
                <span class="w-8 h-8 rounded-xl bg-emerald-100 text-emerald-800 text-xs font-extrabold flex items-center justify-center shrink-0">1</span>
                <div>
                    <h3 class="text-base font-extrabold text-slate-900">Identitas Pribadi &amp; Demografi Pegawai</h3>
                    <p class="text-xs text-slate-500">Nomor Induk Karyawan, kependudukan KTP, dan kualifikasi pendidikan formal</p>
                </div>
            </div>

            <!-- Inline Modern Avatar Upload Bar -->
            <div class="p-4 sm:p-5 rounded-2xl bg-gradient-to-r from-slate-50 via-emerald-50/20 to-slate-50 border border-slate-200/80 flex flex-col sm:flex-row items-center gap-5">
                <div class="relative group cursor-pointer shrink-0" @click="$refs.photoInput.click()">
                    <div class="w-24 h-24 rounded-2xl border-2 border-emerald-500/30 shadow-sm overflow-hidden bg-white flex items-center justify-center transition group-hover:border-emerald-500">
                        <template x-if="photoPreview">
                            <img :src="photoPreview" alt="Foto Pegawai" class="w-full h-full object-cover">
                        </template>
                        <template x-if="!photoPreview">
                            <div class="text-slate-300 flex flex-col items-center justify-center">
                                <i class="fas fa-user-circle text-4xl text-slate-300 mb-1"></i>
                                <span class="text-[10px] text-slate-400 font-semibold">Unggah Foto</span>
                            </div>
                        </template>
                    </div>
                    <div class="absolute -bottom-1 -right-1 w-7 h-7 rounded-xl bg-emerald-600 text-white flex items-center justify-center text-xs shadow-md border-2 border-white group-hover:scale-110 transition">
                        <i class="fas fa-camera text-[10px]"></i>
                    </div>
                </div>

                <div class="space-y-1.5 text-center sm:text-left flex-1 min-w-0">
                    <div class="flex flex-wrap items-center justify-center sm:justify-start gap-2">
                        <h4 class="text-xs font-bold text-slate-800">Foto Profil Pegawai</h4>
                        <span class="px-2 py-0.5 rounded-md text-[10px] font-semibold bg-slate-200/70 text-slate-600">Maks 2MB</span>
                    </div>
                    <p class="text-xs text-slate-500 leading-relaxed max-w-xl">
                        Unggah foto resmi berlatar belakang polos. Format <span class="font-semibold text-slate-700">JPG, PNG, atau WebP</span>.
                    </p>
                    <div class="pt-1 flex flex-wrap items-center justify-center sm:justify-start gap-2">
                        <button type="button" 
                                @click="$refs.photoInput.click()"
                                class="px-3.5 py-1.5 rounded-xl border border-slate-200 bg-white text-slate-700 hover:bg-slate-50 text-xs font-semibold transition cursor-pointer shadow-2xs inline-flex items-center gap-1.5">
                            <i class="fas fa-upload text-[11px] text-emerald-600"></i>
                            <span x-text="photoPreview ? 'Ganti Foto' : 'Pilih Foto'"></span>
                        </button>
                        <button type="button" 
                                x-show="photoPreview" 
                                x-cloak
                                @click="removePhoto()"
                                class="px-3 py-1.5 rounded-xl border border-rose-200 bg-white text-rose-600 hover:bg-rose-50 text-xs font-semibold transition cursor-pointer inline-flex items-center gap-1.5">
                            <i class="fas fa-trash-can text-[11px]"></i>
                            <span>Hapus</span>
                        </button>
                    </div>
                    @error('foto') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
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
                <!-- NIK (Read-only) -->
                <div>
                    <div class="flex items-center justify-between mb-1.5">
                        <label class="block text-xs font-bold text-slate-700">NIK (Nomor Induk Karyawan)</label>
                        <span class="text-[10px] text-slate-400"><i class="fas fa-lock text-[9px] mr-1"></i>Terkunci</span>
                    </div>
                    <div class="relative">
                        <span class="absolute left-3.5 inset-y-0 flex items-center text-slate-400 text-xs">
                            <i class="fas fa-id-card"></i>
                        </span>
                        <input type="text" 
                               value="{{ $employee->nik }}" 
                               readonly
                               class="w-full pl-9 pr-3.5 py-2.5 bg-slate-100 border border-slate-200 rounded-xl text-xs sm:text-sm font-mono font-bold text-slate-600 cursor-not-allowed">
                    </div>
                    <p class="text-[11px] text-slate-400 mt-1">NIK adalah primary key tetap.</p>
                </div>

                <!-- Nama Lengkap (Spans 2 cols on lg) -->
                <div class="sm:col-span-1 lg:col-span-2">
                    <label class="block text-xs font-bold text-slate-700 mb-1.5">Nama Lengkap <span class="text-red-500">*</span></label>
                    <div class="relative">
                        <span class="absolute left-3.5 inset-y-0 flex items-center text-slate-400 text-xs">
                            <i class="fas fa-user"></i>
                        </span>
                        <input type="text" 
                               name="nama" 
                               x-model="nama" 
                               value="{{ old('nama', $employee->nama) }}" 
                               required 
                               class="w-full pl-9 pr-3.5 py-2.5 bg-slate-50/70 border border-slate-200 rounded-xl text-xs sm:text-sm font-medium text-slate-800 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500/30 focus:border-emerald-500 transition">
                    </div>
                    @error('nama') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                <!-- Jenis Kelamin -->
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1.5">Jenis Kelamin</label>
                    <select name="jenis_kelamin"
                        class="w-full px-3.5 py-2.5 bg-slate-50/70 border border-slate-200 rounded-xl text-xs sm:text-sm font-medium text-slate-800 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500/30 focus:border-emerald-500 transition">
                        <option value="">-- Pilih Jenis Kelamin --</option>
                        @foreach($genders as $val => $lbl)
                            <option value="{{ $val }}" {{ old('jenis_kelamin', $employee->jenis_kelamin) === $val ? 'selected' : '' }}>{{ $lbl }} ({{ $val }})</option>
                        @endforeach
                    </select>
                    @error('jenis_kelamin') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                <!-- Agama -->
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1.5">Agama</label>
                    <select name="agama"
                        class="w-full px-3.5 py-2.5 bg-slate-50/70 border border-slate-200 rounded-xl text-xs sm:text-sm font-medium text-slate-800 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500/30 focus:border-emerald-500 transition">
                        <option value="">-- Pilih Agama --</option>
                        @foreach($religions as $rel)
                            <option value="{{ $rel }}" {{ old('agama', $employee->agama) === $rel ? 'selected' : '' }}>{{ $rel }}</option>
                        @endforeach
                    </select>
                    @error('agama') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                <!-- Susunan Keluarga PTKP -->
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1.5">Susunan Keluarga (PTKP)</label>
                    <select name="susunan_keluarga"
                        class="w-full px-3.5 py-2.5 bg-slate-50/70 border border-slate-200 rounded-xl text-xs sm:text-sm font-medium text-slate-800 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500/30 focus:border-emerald-500 transition">
                        <option value="">-- Pilih Susunan Keluarga --</option>
                        @foreach($familyStatuses as $fs)
                            <option value="{{ $fs }}" {{ old('susunan_keluarga', $employee->susunan_keluarga) === $fs ? 'selected' : '' }}>{{ $fs }}</option>
                        @endforeach
                    </select>
                    @error('susunan_keluarga') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                <!-- Tempat Lahir -->
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1.5">Tempat Lahir</label>
                    <input type="text" name="tempat_lahir" value="{{ old('tempat_lahir', $employee->tempat_lahir) }}"
                        class="w-full px-3.5 py-2.5 bg-slate-50/70 border border-slate-200 rounded-xl text-xs sm:text-sm font-medium text-slate-800 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500/30 focus:border-emerald-500 transition">
                </div>

                <!-- Tanggal Lahir & Contextual Retirement Calculation -->
                <div>
                    <div class="flex items-center justify-between mb-1.5">
                        <label class="block text-xs font-bold text-slate-700">Tanggal Lahir</label>
                        <span class="text-[10px] text-slate-400">Usia Pensiun 56 thn</span>
                    </div>
                    <input type="date" 
                           name="tanggal_lahir" 
                           x-model="tanggalLahir" 
                           value="{{ old('tanggal_lahir', $employee->tanggal_lahir?->format('Y-m-d')) }}"
                           class="w-full px-3.5 py-2.5 bg-slate-50/70 border border-slate-200 rounded-xl text-xs sm:text-sm font-medium text-slate-800 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500/30 focus:border-emerald-500 transition">
                    
                    <!-- Contextual Live Pill Badges for MBT & BUP -->
                    <div x-show="tanggalLahir" x-cloak class="mt-2 flex flex-wrap items-center gap-1.5">
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-amber-50 text-amber-800 border border-amber-200 text-[10px] font-medium">
                            <span class="font-bold">MBT (55):</span>
                            <span class="font-mono" x-text="calculateMbt() || '{{ $employee->tanggal_mbt?->format('d-m-Y') ?? '-' }}'"></span>
                        </span>
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-emerald-50 text-emerald-800 border border-emerald-200 text-[10px] font-medium">
                            <span class="font-bold">BUP (56):</span>
                            <span class="font-mono" x-text="calculatePensiun() || '{{ $employee->tanggal_pensiun?->format('d-m-Y') ?? '-' }}'"></span>
                        </span>
                    </div>
                </div>

                <!-- Jenjang Pendidikan Terakhir -->
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1.5">Jenjang Pendidikan Terakhir</label>
                    <select name="pendidikan_terakhir"
                        class="w-full px-3.5 py-2.5 bg-slate-50/70 border border-slate-200 rounded-xl text-xs sm:text-sm font-medium text-slate-800 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500/30 focus:border-emerald-500 transition">
                        <option value="">-- Pilih Jenjang Pendidikan --</option>
                        @foreach($educationLevels as $edu)
                            <option value="{{ $edu }}" {{ old('pendidikan_terakhir', $employee->pendidikan_terakhir) === $edu ? 'selected' : '' }}>{{ $edu }}</option>
                        @endforeach
                    </select>
                    @error('pendidikan_terakhir') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                <!-- Nama Universitas / Sekolah (Full width 3 cols) -->
                <div class="sm:col-span-2 lg:col-span-3">
                    <label class="block text-xs font-bold text-slate-700 mb-1.5">Nama Universitas / Institusi Pendidikan</label>
                    <input type="text" name="sekolah" value="{{ old('sekolah', $employee->sekolah) }}"
                        class="w-full px-3.5 py-2.5 bg-slate-50/70 border border-slate-200 rounded-xl text-xs sm:text-sm font-medium text-slate-800 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500/30 focus:border-emerald-500 transition">
                </div>
            </div>
        </div>

        <!-- SEGMEN 2: FORMASI JABATAN & PENEMPATAN KERJA -->
        <div class="bg-white rounded-2xl border border-slate-200/80 shadow-xs p-6 sm:p-7 space-y-6">
            <div class="flex items-center gap-3 pb-4 border-b border-slate-100">
                <span class="w-8 h-8 rounded-xl bg-emerald-100 text-emerald-800 text-xs font-extrabold flex items-center justify-center shrink-0">2</span>
                <div>
                    <h3 class="text-base font-extrabold text-slate-900">Formasi Jabatan &amp; Penempatan Unit Kerja</h3>
                        <p class="text-xs text-slate-500">Struktur posisi, 43 unit kerja definitif, dan strata Karpim/Karpel</p>
                    </div>
                </div>

            <div class="space-y-5">
                <!-- Level Pegawai & Jalur Masuk (DOMAIN.md Bab 2.1) -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    <!-- Level Pegawai (Radio Cards) -->
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-2">Level Strata Pegawai <span class="text-red-500">*</span></label>
                        <div class="grid grid-cols-2 gap-3">
                            @foreach($levels as $lvlKey => $lvlName)
                                <label class="relative flex items-center px-3 py-1.5 rounded-xl border cursor-pointer transition"
                                       :class="level === '{{ $lvlKey }}' 
                                            ? 'bg-emerald-50/80 border-emerald-500 ring-2 ring-emerald-500/20' 
                                            : 'bg-slate-50/60 border-slate-200 hover:bg-slate-100/60'">
                                    <input type="radio" 
                                           name="level" 
                                           value="{{ $lvlKey }}" 
                                           x-model="level" 
                                           @change="onLevelChange()"
                                           class="sr-only">
                                    <div class="flex items-center gap-3 min-w-0">
                                        <div class="w-8 h-8 rounded-xl flex items-center justify-center text-xs shrink-0"
                                             :class="level === '{{ $lvlKey }}' ? 'bg-emerald-600 text-white shadow-xs' : 'bg-slate-200 text-slate-600'">
                                            <i class="fas {{ $lvlKey === 'Karpim' ? 'fa-user-tie' : 'fa-people-carry-box' }}"></i>
                                        </div>
                                        <div class="min-w-0">
                                            <div class="text-xs sm:text-sm font-bold text-slate-900 truncate">{{ $lvlKey === 'Karpim' ? 'Karyawan Pimpinan' : 'Karyawan Pelaksana' }}</div>
                                        </div>
                                    </div>
                                </label>
                            @endforeach
                        </div>
                        @error('level') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>

                    <!-- Jalur Masuk / Rekrutmen Pegawai (DOMAIN.md Bab 2.1) -->
                    <div>
                        <div class="flex items-center justify-between mb-2">
                            <label class="block text-xs font-bold text-slate-700">Jalur Masuk / Rekrutmen <span class="text-red-500">*</span></label>

                        </div>
                        <div class="relative">
                            <span class="absolute left-3.5 inset-y-0 flex items-center text-slate-400 text-xs">
                                <i class="fas fa-route"></i>
                            </span>
                            <select name="jalur_masuk" 
                                    x-model="jalurMasuk"
                                    required
                                    class="w-full pl-9 pr-3.5 py-2.5 bg-slate-50/70 border border-slate-200 rounded-xl text-xs sm:text-sm font-medium text-slate-800 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500/30 focus:border-emerald-500 transition">
                                @foreach($entryChannels as $key => $label)
                                    <option value="{{ $key }}" {{ old('jalur_masuk', $employee->jalur_masuk ?? 'Reguler') === $key ? 'selected' : '' }}>
                                        {{ $label }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        @error('jalur_masuk') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                </div>

                <!-- Jabatan & Unit Kerja Form Inputs -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    <!-- Jabatan Input with Datalist & Quick Add -->
                    <div>
                        <div class="flex items-center justify-between mb-1.5">
                            <label class="block text-xs font-bold text-slate-700">Jabatan Formasi <span class="text-red-500">*</span></label>
                            <button type="button" @click="$dispatch('open-quick-add-position')"
                                class="text-[11px] font-bold text-emerald-600 hover:text-emerald-700 hover:underline inline-flex items-center gap-1 cursor-pointer"
                                title="Tambah Jabatan Baru ke Master Data">
                                <i class="fas fa-plus text-[9px]"></i>
                                <span>Jabatan Baru</span>
                            </button>
                        </div>
                        <div class="relative">
                            <span class="absolute left-3.5 inset-y-0 flex items-center text-slate-400 text-xs">
                                <i class="fas fa-briefcase"></i>
                            </span>
                            <input type="text" 
                                   name="jabatan" 
                                   id="jabatanInputEdit" 
                                   list="positionOptionsEdit" 
                                   x-model="jabatan"
                                   value="{{ old('jabatan', $employee->jabatan) }}" 
                                   required 
                                   placeholder="Ketik atau pilih nama jabatan formasi..."
                                   class="w-full pl-9 pr-3.5 py-2.5 bg-slate-50/70 border border-slate-200 rounded-xl text-xs sm:text-sm font-medium text-slate-800 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500/30 focus:border-emerald-500 transition">
                        </div>
                        <datalist id="positionOptionsEdit">
                            @foreach($activePositions ?? [] as $posName)
                                <option value="{{ $posName }}"></option>
                            @endforeach
                        </datalist>
                        <!-- Inline Bidang Indicator removed -->
                        @error('jabatan') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>

                    <!-- Unit Kerja Combobox (Enhanced with Region Pills) -->
                    <div>
                        <div class="flex items-center justify-between mb-1.5">
                            <label class="block text-xs font-bold text-slate-700">Unit Kerja Penempatan <span class="text-red-500">*</span></label>
                            <button type="button" @click="$dispatch('open-quick-add-unit')"
                                class="text-[11px] font-bold text-emerald-600 hover:text-emerald-700 hover:underline inline-flex items-center gap-1 cursor-pointer"
                                title="Tambah Unit Kerja Baru ke Master Data">
                                <i class="fas fa-plus text-[9px]"></i>
                                <span>Unit Baru</span>
                            </button>
                        </div>
                        <x-admin.unit-combobox 
                            :grouped-units="$groupedUnits" 
                            :selected="old('unit_kerja', $employee->unit_kerja)" 
                            id="unitKerjaInputEdit"
                            name="unit_kerja" />
                        @error('unit_kerja') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                </div>

                <!-- Auto-populated Fields (Bidang & RM Band) -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-5 mt-5">
                    <!-- Bidang Fungsional (Read Only / Auto-filled) -->
                    <div>
                        <div class="flex items-center justify-between mb-1.5">
                            <label class="block text-xs font-bold text-slate-700">Bidang Fungsional</label>
                            <span x-show="bidang" x-transition class="text-[10px] font-bold text-emerald-600 bg-emerald-50 px-2 py-0.5 rounded-md flex items-center gap-1 border border-emerald-100">
                                <i class="fas fa-magic text-[9px]"></i> Auto-filled
                            </span>
                        </div>
                        <div class="relative">
                            <span class="absolute left-3.5 inset-y-0 flex items-center text-xs transition-colors duration-300" :class="bidang ? 'text-emerald-500' : 'text-slate-400'">
                                <i class="fas fa-sitemap"></i>
                            </span>
                            <input type="text" 
                                   readonly 
                                   :value="bidangLabel" 
                                   placeholder="Terisi otomatis..."
                                   class="w-full pl-9 pr-3.5 py-2.5 border rounded-xl text-xs sm:text-sm font-medium transition-all duration-300 cursor-not-allowed focus:outline-none"
                                   :class="bidang ? 'bg-emerald-50/30 border-emerald-200 text-emerald-900' : 'bg-slate-50 border-slate-200 text-slate-500'">
                        </div>
                        <p class="text-[11px] text-slate-400 mt-1">Ditentukan otomatis berdasarkan jabatan.</p>
                    </div>

                    <!-- Eselon / RM Band (Auto-filled Dropdown) -->
                    <div>
                        <div class="flex items-center justify-between mb-1.5">
                            <label class="block text-xs font-bold text-slate-700">Eselon / RM Band <span class="text-red-500">*</span></label>
                            <span x-show="!isRmOverridden && computedRmBand" x-transition class="text-[10px] font-bold text-emerald-600 bg-emerald-50 px-2 py-0.5 rounded-md flex items-center gap-1 border border-emerald-100">
                                <i class="fas fa-magic text-[9px]"></i> Auto-filled
                            </span>
                            <button type="button" x-show="isRmOverridden" x-cloak @click="resetRmToAuto()" class="text-[10px] font-bold text-amber-600 bg-amber-50 hover:bg-amber-100 px-2 py-0.5 rounded-md flex items-center gap-1 border border-amber-200 transition-colors focus:outline-none">
                                <i class="fas fa-undo text-[9px]"></i> Reset ke Auto
                            </button>
                        </div>
                        <div class="relative">
                            <span class="absolute left-3.5 inset-y-0 flex items-center text-xs z-10 transition-colors duration-300" :class="isRmOverridden ? 'text-amber-500' : (computedRmBand ? 'text-emerald-500' : 'text-slate-400')">
                                <i class="fas fa-layer-group"></i>
                            </span>
                            <select name="rm_level" 
                                    id="rmLevelInputEdit"
                                    x-model="selectedRmLevel"
                                    @change="onRmChange()"
                                    class="w-full pl-9 pr-3.5 py-2.5 bg-white border rounded-xl text-xs sm:text-sm font-medium text-slate-800 focus:outline-none focus:ring-2 transition-all duration-300 appearance-none"
                                    :class="isRmOverridden ? 'border-amber-300 ring-2 ring-amber-500/20 bg-amber-50/10 focus:border-amber-500 focus:ring-amber-500/30' : (triggerPulse ? 'border-emerald-300 ring-2 ring-emerald-500/30 bg-emerald-50/30' : 'border-slate-200 focus:border-emerald-500 focus:ring-emerald-500/30')">
                                @foreach($rmLevels ?? \App\Models\Employee::RM_LEVELS ?? ['RM-1'=>'RM-1', 'RM-2'=>'RM-2', 'RM-3'=>'RM-3', 'RM-4'=>'RM-4'] as $key => $label)
                                    <option value="{{ $key }}">{{ $label }}</option>
                                @endforeach
                            </select>
                            <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-3.5 text-slate-400">
                                <i class="fas fa-chevron-down text-[10px]"></i>
                            </div>
                        </div>
                        <p class="text-[11px] mt-1 transition-colors" :class="isRmOverridden ? 'text-amber-600' : 'text-slate-400'">
                            <span x-show="!isRmOverridden">Standar klasifikasi formasi. Bisa diubah manual.</span>
                            <span x-show="isRmOverridden" x-cloak><i class="fas fa-info-circle"></i> Diubah manual (Override).</span>
                        </p>
                        @error('rm_level') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                </div>
            </div>
            </div>

        <!-- SEGMEN 3: GOLONGAN, GRADE, & KETETAPAN SK -->
        <div class="bg-white rounded-2xl border border-slate-200/80 shadow-xs p-6 sm:p-7 space-y-6">
            <div class="flex items-center gap-3 pb-4 border-b border-slate-100">
                <span class="w-8 h-8 rounded-xl bg-emerald-100 text-emerald-800 text-xs font-extrabold flex items-center justify-center shrink-0">3</span>
                <div>
                    <h3 class="text-base font-extrabold text-slate-900">Golongan, Bobot Grade, &amp; Tanggal SK Dinas</h3>
                        <p class="text-xs text-slate-500">Pangkat kepangkatan, rentang grade, dan riwayat Terhitung Mulai Tanggal (TMT)</p>
                    </div>
                </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-5">
                <!-- Golongan -->
                <div>
                    <div class="flex items-center justify-between mb-1.5">
                        <label class="block text-xs font-bold text-slate-700">Golongan &amp; Berkala</label>
                        <span class="text-[10px] font-semibold" :class="isGolonganWarning ? 'text-amber-600 font-bold' : 'text-slate-400'" x-text="level === 'Karpim' ? 'Standar: Gol III - IV' : 'Standar: Gol I - II'"></span>
                    </div>
                    <input type="text" 
                           name="golongan" 
                           x-model="golongan"
                           value="{{ old('golongan', $employee->golongan) }}"
                           :placeholder="level === 'Karpim' ? 'Contoh: IIIA/00, IIID/02' : 'Contoh: IA/00, IID/00'"
                           class="w-full px-3.5 py-2.5 rounded-xl text-xs sm:text-sm font-mono font-medium focus:bg-white focus:outline-none focus:ring-2 transition"
                           :class="isGolonganWarning 
                                ? 'bg-amber-50/50 border border-amber-400 text-amber-950 focus:ring-amber-400/30 focus:border-amber-500' 
                                : 'bg-slate-50/70 border border-slate-200 text-slate-800 focus:ring-emerald-500/30 focus:border-emerald-500'">
                    <p x-show="!isGolonganWarning" class="text-[10px] text-slate-400 mt-1">Format: [Gol]/[Berkala]</p>
                    <p x-show="isGolonganWarning" x-cloak class="text-[10px] text-amber-600 font-semibold mt-1 flex items-center gap-1">
                        <i class="fas fa-triangle-exclamation text-[9px]"></i>
                        <span>Standar <strong x-text="level"></strong>: <span x-text="level === 'Karpim' ? 'Gol III / IV' : 'Gol I / II'"></span>.</span>
                    </p>
                </div>

                <!-- Job Grade -->
                <div>
                    <div class="flex items-center justify-between mb-1.5">
                        <label class="block text-xs font-bold text-slate-700">Job Grade</label>
                        <span class="text-[10px] font-semibold" :class="isJobGradeWarning ? 'text-amber-600 font-bold' : 'text-emerald-700'" x-text="level === 'Karpim' ? 'Rentang: 11 - 16' : 'Rentang: 1 - 10'"></span>
                    </div>
                    <input type="number" 
                           name="job_grade" 
                           x-model="jobGrade"
                           value="{{ old('job_grade', $employee->job_grade) }}"
                           placeholder="Grade jabatan"
                           class="w-full px-3.5 py-2.5 rounded-xl text-xs sm:text-sm font-medium focus:bg-white focus:outline-none focus:ring-2 transition"
                           :class="isJobGradeWarning 
                                ? 'bg-amber-50/50 border border-amber-400 text-amber-950 focus:ring-amber-400/30 focus:border-amber-500' 
                                : 'bg-slate-50/70 border border-slate-200 text-slate-800 focus:ring-emerald-500/30 focus:border-emerald-500'">
                    <p x-show="!isJobGradeWarning" class="text-[10px] text-slate-400 mt-1">Bobot posisi jabatan</p>
                    <p x-show="isJobGradeWarning" x-cloak class="text-[10px] text-amber-600 font-semibold mt-1 flex items-center gap-1">
                        <i class="fas fa-triangle-exclamation text-[9px]"></i>
                        <span>Di luar standar <strong x-text="level"></strong> (<span x-text="level === 'Karpim' ? '11 - 16' : '1 - 10'"></span>).</span>
                    </p>
                    @error('job_grade') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                <!-- Person Grade -->
                <div>
                    <div class="flex items-center justify-between mb-1.5">
                        <label class="block text-xs font-bold text-slate-700">Person Grade</label>
                        <span class="text-[10px] font-semibold" :class="isPersonGradeWarning ? 'text-amber-600 font-bold' : 'text-emerald-700'" x-text="level === 'Karpim' ? 'Rentang: 11 - 16' : 'Rentang: 1 - 10'"></span>
                    </div>
                    <input type="number" 
                           name="person_grade" 
                           x-model="personGrade"
                           value="{{ old('person_grade', $employee->person_grade) }}"
                           placeholder="Grade individu"
                           class="w-full px-3.5 py-2.5 rounded-xl text-xs sm:text-sm font-medium focus:bg-white focus:outline-none focus:ring-2 transition"
                           :class="isPersonGradeWarning 
                                ? 'bg-amber-50/50 border border-amber-400 text-amber-950 focus:ring-amber-400/30 focus:border-amber-500' 
                                : 'bg-slate-50/70 border border-slate-200 text-slate-800 focus:ring-emerald-500/30 focus:border-emerald-500'">
                    <p x-show="!isPersonGradeWarning" class="text-[10px] text-slate-400 mt-1">Kompetensi personal</p>
                    <p x-show="isPersonGradeWarning" x-cloak class="text-[10px] text-amber-600 font-semibold mt-1 flex items-center gap-1">
                        <i class="fas fa-triangle-exclamation text-[9px]"></i>
                        <span>Di luar standar <strong x-text="level"></strong> (<span x-text="level === 'Karpim' ? '11 - 16' : '1 - 10'"></span>).</span>
                    </p>
                    @error('person_grade') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                <!-- TMT Bekerja Awal -->
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1.5">TMT Bekerja Awal</label>
                    <input type="date" name="tmt_bekerja" value="{{ old('tmt_bekerja', $employee->tmt_bekerja?->format('Y-m-d')) }}"
                        class="w-full px-3.5 py-2.5 bg-slate-50/70 border border-slate-200 rounded-xl text-xs sm:text-sm font-medium text-slate-800 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500/30 focus:border-emerald-500 transition">
                    <p class="text-[10px] text-slate-400 mt-1">Tanggal pertama kali diangkat di PTPN</p>
                </div>

                <!-- TMT Unit Kerja -->
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1.5">TMT Unit Kerja</label>
                    <input type="date" name="tmt_unit_kerja" value="{{ old('tmt_unit_kerja', $employee->tmt_unit_kerja?->format('Y-m-d')) }}"
                        class="w-full px-3.5 py-2.5 bg-slate-50/70 border border-slate-200 rounded-xl text-xs sm:text-sm font-medium text-slate-800 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500/30 focus:border-emerald-500 transition">
                    <p class="text-[10px] text-slate-400 mt-1">Dasar rotasi dinas unit</p>
                </div>

                <!-- Tanggal Dalam Jabatan -->
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1.5">Tanggal Dalam Jabatan</label>
                    <input type="date" name="tanggal_dalam_jabatan" value="{{ old('tanggal_dalam_jabatan', $employee->tanggal_dalam_jabatan?->format('Y-m-d')) }}"
                        class="w-full px-3.5 py-2.5 bg-slate-50/70 border border-slate-200 rounded-xl text-xs sm:text-sm font-medium text-slate-800 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500/30 focus:border-emerald-500 transition">
                    <p class="text-[10px] text-slate-400 mt-1">Tanggal SK pengangkatan posisi</p>
                </div>

                <!-- Tanggal Diangkat Staf (Adaptive: Disabled for Karpel per DOMAIN.md Bab 4.1) -->
                <div class="sm:col-span-3">
                    <div class="p-4 rounded-xl border transition"
                         :class="level === 'Karpim' ? 'bg-slate-50/60 border-slate-200' : 'bg-slate-100/70 border-slate-200/60'">
                        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3">
                            <div class="space-y-0.5">
                                <label class="block text-xs font-bold" :class="level === 'Karpim' ? 'text-slate-700' : 'text-slate-400'">
                                    Tanggal Diangkat Staf (Pimpinan)
                                </label>
                                <p class="text-[11px]" :class="level === 'Karpim' ? 'text-slate-500' : 'text-slate-400'">
                                    <template x-if="level === 'Karpim'">
                                        <span>Tanggal resmi pegawai diangkat menjadi staf pimpinan PTPN (Wajib untuk Karpim).</span>
                                    </template>
                                    <template x-if="level === 'Karpel'">
                                        <span><i class="fas fa-lock text-[10px] mr-1"></i> Tidak berlaku untuk Karyawan Pelaksana (Karpel). Kosong secara otomatis.</span>
                                    </template>
                                </p>
                            </div>
                            <div class="w-full sm:w-64">
                                <input type="date" 
                                       name="tanggal_diangkat_staf" 
                                       :disabled="level === 'Karpel'"
                                       value="{{ old('tanggal_diangkat_staf', $employee->tanggal_diangkat_staf?->format('Y-m-d')) }}"
                                       class="w-full px-3.5 py-2 rounded-xl text-xs sm:text-sm font-medium border transition"
                                       :class="level === 'Karpim' 
                                            ? 'bg-white border-slate-200 text-slate-800 focus:ring-2 focus:ring-emerald-500/30 focus:border-emerald-500' 
                                            : 'bg-slate-200/60 border-slate-200 text-slate-400 cursor-not-allowed'">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sticky/Floating Action Bar -->
        <div class="bg-white rounded-2xl border border-slate-200/80 p-4 sm:p-5 shadow-xs flex flex-col sm:flex-row items-center justify-between gap-3">
            <div class="text-xs text-slate-500 flex items-center gap-1.5">
                <i class="fas fa-shield-halved text-emerald-600"></i>
                <span>Perubahan data formasi akan tersimpan di profil dan log audit kepegawaian.</span>
            </div>

            <div class="flex items-center gap-3 w-full sm:w-auto justify-end">
                <a href="{{ route('admin.employees.show', $employee->nik) }}"
                    class="px-5 py-2.5 rounded-xl border border-slate-200 text-slate-600 hover:bg-slate-50 text-xs sm:text-sm font-semibold transition cursor-pointer">
                    Batal
                </a>
                <button type="submit"
                    class="inline-flex items-center gap-2 px-6 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs sm:text-sm font-bold shadow-md shadow-emerald-600/20 active:scale-95 transition cursor-pointer">
                    <i class="fas fa-check-circle text-xs"></i>
                    <span>Simpan Perubahan</span>
                </button>
            </div>
        </div>
    </form>

    <!-- Quick Add Modals -->
    <x-admin.quick-add-unit-modal />
    <x-admin.quick-add-position-modal />

    <!-- Script to dynamically append newly created units and positions -->
    <script>
        document.addEventListener('position-created', function(e) {
            const position = e.detail;
            const input = document.getElementById('jabatanInputEdit');
            const datalist = document.getElementById('positionOptionsEdit');
            if (input && position) {
                input.value = position.nama;
                input.dispatchEvent(new Event('input', { bubbles: true }));
            }
            if (datalist && position) {
                const option = document.createElement('option');
                option.value = position.nama;
                datalist.appendChild(option);
            }
            const levelRadio = document.querySelector(`input[name="level"][value="${position.level}"]`);
            if (levelRadio && position && position.level) {
                levelRadio.click();
            }
        });
    </script>
</div>
@endsection
