@extends('layouts.admin')

@section('content')
<div class="space-y-6 max-w-5xl mx-auto"
    x-data="{
        nik: '{{ old('nik', '') }}',
        nama: '{{ old('nama', '') }}',
        level: '{{ old('level', $prefill['level'] ?? request('level', 'Karpim')) }}',
        jabatan: '{{ old('jabatan', $prefill['jabatan'] ?? request('jabatan', '')) }}',
        unitKerja: '{{ old('unit_kerja', $prefill['unit_kerja'] ?? request('unit_kerja', '')) }}',
        golongan: '{{ old('golongan', $prefill['golongan'] ?? request('golongan', 'IIIA/00')) }}',
        jobGrade: '{{ old('job_grade', '') }}',
        personGrade: '{{ old('person_grade', '') }}',
        jalurMasuk: '{{ old('jalur_masuk', 'Reguler') }}',
        tanggalLahir: '{{ old('tanggal_lahir', '') }}',
        photoPreview: null,

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

        selectedRmLevel: '{{ old('rm_level', '') }}',
        isRmOverridden: {{ old('rm_level') ? 'true' : 'false' }},
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
            
            // Initial fetch for unit positions if unit is already selected
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
                // Fetch positions linked to the selected unit, filtered by level
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
                <i class="fas fa-user-plus"></i>
            </div>
            <div>
                <div class="flex items-center gap-2">
                    <h1 class="text-xl sm:text-2xl font-extrabold text-slate-900 tracking-tight">Tambah Pegawai Baru</h1>

                </div>
                <p class="text-slate-500 text-xs sm:text-sm mt-0.5">Input data induk pegawai untuk penempatan baru atau pengisian formasi kosong PTPN IV Regional V</p>
            </div>
        </div>

        <div class="flex items-center gap-2">
            <a href="{{ route('admin.employees.index') }}"
                class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl border border-slate-200 text-slate-600 hover:bg-slate-50 text-xs sm:text-sm font-semibold transition shadow-xs">
                <i class="fas fa-arrow-left text-xs"></i>
                <span>Kembali ke Direktori</span>
            </a>
        </div>
    </div>

    <!-- Vacancy Closure Alert Banner (If prefilled from Monitoring Kosong) -->
    @php
        $prefilledJabatan = old('jabatan', $prefill['jabatan'] ?? request('jabatan'));
        $prefilledUnit = old('unit_kerja', $prefill['unit_kerja'] ?? request('unit_kerja'));
    @endphp

    @if ($prefilledJabatan && $prefilledUnit)
        <div class="p-4 bg-emerald-50 border border-emerald-200/90 rounded-2xl shadow-xs flex items-start gap-3.5">
            <div class="w-9 h-9 rounded-xl bg-emerald-600 text-white flex items-center justify-center text-sm shrink-0 mt-0.5 shadow-xs">
                <i class="fas fa-door-closed"></i>
            </div>
            <div class="text-xs text-emerald-950 space-y-1">
                <h4 class="font-extrabold text-emerald-900 text-sm">Rekrutmen Penutup Rantai Formasi Kosong</h4>
                <p class="leading-relaxed">
                    Anda sedang mendaftarkan pegawai baru untuk mengisi lowongan posisi: <strong class="text-slate-900 font-bold">{{ $prefilledJabatan }}</strong> di <strong class="text-slate-900 font-bold">{{ $prefilledUnit }}</strong>.
                </p>
                <p class="text-emerald-700 flex items-center gap-1 font-semibold">
                    <i class="fas fa-check-circle text-xs"></i> Setelah disimpan, lowongan ini otomatis terisi dan tuntas dari <strong>Monitoring Jabatan Kosong</strong>.
                </p>
            </div>
        </div>
    @endif

    <!-- Form & Single-Column Streamlined Layout -->
    <form id="employeeForm" method="POST" action="{{ route('admin.employees.store') }}" enctype="multipart/form-data" class="space-y-6">
        @csrf

        <!-- SEGMEN 1: IDENTITAS PRIBADI & DEMOGRAFI -->
        <div class="bg-white rounded-2xl border border-slate-200/80 shadow-xs p-6 sm:p-7 space-y-6">
            <div class="flex items-center gap-3 pb-4 border-b border-slate-100">
                <span class="w-8 h-8 rounded-xl bg-emerald-100 text-emerald-800 text-xs font-extrabold flex items-center justify-center shrink-0">1</span>
                <div>
                    <h3 class="text-base font-extrabold text-slate-900">Identitas Pribadi &amp; Demografi Pegawai</h3>
                    <p class="text-xs text-slate-500">Foto resmi, Nomor Induk Karyawan, kependudukan, dan kualifikasi pendidikan formal</p>
                </div>
            </div>

            <!-- Inline Modern Avatar Upload Bar -->
            <div class="p-4 sm:p-5 rounded-2xl bg-gradient-to-r from-slate-50 via-emerald-50/20 to-slate-50 border border-slate-200/80 flex flex-col sm:flex-row items-center gap-5">
                <div class="relative group cursor-pointer shrink-0" @click="$refs.photoInput.click()">
                    <div class="w-24 h-24 rounded-2xl border-2 border-emerald-500/30 shadow-sm overflow-hidden bg-white flex items-center justify-center transition group-hover:border-emerald-500">
                        <template x-if="photoPreview">
                            <img :src="photoPreview" alt="Preview Foto" class="w-full h-full object-cover">
                        </template>
                        <template x-if="!photoPreview">
                            <div class="text-slate-300 flex flex-col items-center justify-center">
                                <i class="fas fa-user-circle text-4xl text-slate-300 mb-1"></i>
                                <span class="text-[10px] text-slate-400 font-semibold">Unggah</span>
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
                        <span class="px-2 py-0.5 rounded-md text-[10px] font-semibold bg-slate-200/70 text-slate-600">Opsional</span>
                    </div>
                    <p class="text-xs text-slate-500 leading-relaxed max-w-xl">
                        Unggah foto resmi berlatar belakang polos. Format <span class="font-semibold text-slate-700">JPG, PNG, atau WebP</span> (maks. 2 MB).
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
                <!-- NIK -->
                <div>
                    <div class="flex items-center justify-between mb-1.5">
                        <label class="block text-xs font-bold text-slate-700">NIK (Nomor Induk Karyawan) <span class="text-red-500">*</span></label>
                        <span class="text-[10px] font-mono" :class="nik.length === 8 ? 'text-emerald-600 font-bold' : 'text-slate-400'">
                            <span x-text="nik.length"></span>/8 digit
                        </span>
                    </div>
                    <div class="relative">
                        <span class="absolute left-3.5 inset-y-0 flex items-center text-slate-400 text-xs">
                            <i class="fas fa-id-card"></i>
                        </span>
                        <input type="text" 
                               name="nik" 
                               x-model="nik" 
                               value="{{ old('nik') }}" 
                               required 
                               maxlength="8" 
                               pattern="[0-9]{8}" 
                               placeholder="Contoh: 13009988"
                               class="w-full pl-9 pr-3.5 py-2.5 bg-slate-50/70 border border-slate-200 rounded-xl text-xs sm:text-sm font-mono font-medium text-slate-800 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500/30 focus:border-emerald-500 transition">
                    </div>
                        @error('nik') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
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
                               value="{{ old('nama') }}" 
                               required 
                               placeholder="Nama lengkap sesuai KTP"
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
                            <option value="{{ $val }}" {{ old('jenis_kelamin') === $val ? 'selected' : '' }}>{{ $lbl }} ({{ $val }})</option>
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
                            <option value="{{ $rel }}" {{ old('agama', 'Islam') === $rel ? 'selected' : '' }}>{{ $rel }}</option>
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
                            <option value="{{ $fs }}" {{ old('susunan_keluarga', 'L') === $fs ? 'selected' : '' }}>{{ $fs }}</option>
                        @endforeach
                    </select>
                    @error('susunan_keluarga') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                <!-- Tempat Lahir -->
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1.5">Tempat Lahir</label>
                    <input type="text" name="tempat_lahir" value="{{ old('tempat_lahir') }}" placeholder="Kota kelahiran sesuai KTP"
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
                           value="{{ old('tanggal_lahir') }}"
                           class="w-full px-3.5 py-2.5 bg-slate-50/70 border border-slate-200 rounded-xl text-xs sm:text-sm font-medium text-slate-800 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500/30 focus:border-emerald-500 transition">
                    
                    <!-- Contextual Live Pill Badges for MBT & BUP -->
                    <div x-show="tanggalLahir" x-cloak class="mt-2 flex flex-wrap items-center gap-1.5">
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-amber-50 text-amber-800 border border-amber-200 text-[10px] font-medium">
                            <span class="font-bold">MBT (55):</span>
                            <span class="font-mono" x-text="calculateMbt()"></span>
                        </span>
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-emerald-50 text-emerald-800 border border-emerald-200 text-[10px] font-medium">
                            <span class="font-bold">BUP (56):</span>
                            <span class="font-mono" x-text="calculatePensiun()"></span>
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
                            <option value="{{ $edu }}" {{ old('pendidikan_terakhir') === $edu ? 'selected' : '' }}>{{ $edu }}</option>
                        @endforeach
                    </select>
                    @error('pendidikan_terakhir') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                <!-- Nama Universitas / Sekolah (Full width 3 cols) -->
                <div class="sm:col-span-2 lg:col-span-3">
                    <label class="block text-xs font-bold text-slate-700 mb-1.5">Nama Universitas / Institusi Pendidikan</label>
                    <input type="text" name="sekolah" value="{{ old('sekolah') }}" placeholder="Contoh: Institut Pertanian Stiper (INSTIPER), Universitas Gadjah Mada, IPB"
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
                        <div class="flex items-center justify-between min-h-[22px] mb-1.5">
                            <label class="block text-xs font-bold text-slate-700">Level Strata Pegawai <span class="text-red-500">*</span></label>
                        </div>
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
                                        <div class="w-7 h-7 rounded-lg flex items-center justify-center text-[11px] shrink-0"
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
                        <div class="flex items-center justify-between min-h-[22px] mb-1.5">
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
                                    <option value="{{ $key }}" {{ old('jalur_masuk', 'Reguler') === $key ? 'selected' : '' }}>
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
                    <!-- Unit Kerja Combobox (Enhanced with Region Pills) - Dipindahkan ke kiri -->
                    <div>
                        <div class="flex items-center justify-between mb-1.5">
                            <label class="block text-xs font-bold text-slate-700">Unit Kerja Penempatan <span class="text-red-500">*</span></label>
                            <button type="button" @click="$dispatch('open-quick-add-unit')"
                                class="text-[10px] font-bold text-emerald-700 bg-emerald-50 hover:bg-emerald-100 border border-emerald-200 px-2 py-1 rounded-md transition-colors inline-flex items-center gap-1 cursor-pointer shadow-xs"
                                title="Tambah Unit Kerja Baru ke Master Data">
                                <i class="fas fa-plus text-[9px]"></i>
                                <span>Unit Baru</span>
                            </button>
                        </div>
                        <x-admin.unit-combobox 
                            :grouped-units="$groupedUnits" 
                            :selected="old('unit_kerja', $prefilledUnit)" 
                            id="unitKerjaInput"
                            name="unit_kerja" />
                        <p class="text-[11px] text-slate-400 mt-1">Pilih Unit Kerja terlebih dahulu untuk memunculkan formasi Jabatan.</p>
                        @error('unit_kerja') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>

                    <!-- Jabatan Dropdown (Strict Select) - Dipindahkan ke kanan -->
                    <div>
                        <div class="flex items-center justify-between mb-1.5">
                            <label class="block text-xs font-bold text-slate-700">Jabatan Formasi <span class="text-red-500">*</span></label>
                            <button type="button" @click="if(unitKerja) $dispatch('open-quick-add-position', { unitKerja: unitKerja })"
                                :disabled="!unitKerja"
                                :class="!unitKerja ? 'bg-slate-100 text-slate-400 border border-slate-200 cursor-not-allowed opacity-60' : 'text-emerald-700 bg-emerald-50 hover:bg-emerald-100 border border-emerald-200 cursor-pointer'"
                                class="text-[10px] font-bold px-2 py-1 rounded-md transition-colors inline-flex items-center gap-1 shadow-xs"
                                title="Pilih Unit Kerja terlebih dahulu untuk menambah formasi">
                                <i class="fas fa-plus text-[9px]"></i>
                                <span>Jabatan Baru</span>
                            </button>
                        </div>
                        <div class="relative">
                            <span class="absolute left-3.5 inset-y-0 flex items-center text-xs transition-colors duration-300" :class="unitKerja ? 'text-emerald-600' : 'text-slate-400'">
                                <i class="fas fa-briefcase"></i>
                            </span>
                            <select name="jabatan" 
                                    id="jabatanInput" 
                                    x-model="jabatan"
                                    :disabled="!unitKerja || activePositionsList.length === 0"
                                    required 
                                    class="w-full pl-9 pr-8 py-2.5 rounded-xl text-xs sm:text-sm font-medium focus:outline-none focus:ring-2 transition-all duration-300 appearance-none"
                                    :class="!unitKerja ? 'bg-slate-100/70 border border-slate-200/60 text-slate-400 cursor-not-allowed' : 'bg-slate-50/70 border border-slate-200 text-slate-800 focus:bg-white focus:ring-emerald-500/30 focus:border-emerald-500'">
                                <option value="" disabled selected x-text="!unitKerja ? '-- Pilih Unit Kerja Terlebih Dahulu --' : (activePositionsList.length === 0 ? '-- Formasi Kosong / Memuat --' : '-- Pilih Formasi Jabatan --')"></option>
                                <template x-for="pos in activePositionsList" :key="pos">
                                    <option :value="pos" x-text="pos" :selected="jabatan === pos"></option>
                                </template>
                            </select>
                            <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-3.5 text-slate-400">
                                <i class="fas fa-chevron-down text-[10px]"></i>
                            </div>
                        </div>
                        @error('jabatan') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                </div>

                <!-- Status Penugasan, Karyawan & Kontrak (Dynamic Grid) -->
                <div x-data="{ statusKaryawan: '{{ old('status_karyawan', 'Tetap (PKWTT)') }}' }" 
                     :class="statusKaryawan !== 'Tetap (PKWTT)' ? 'md:grid-cols-3' : 'md:grid-cols-2'" 
                     class="grid grid-cols-1 gap-5 mt-5 transition-all duration-300">
                    
                    <!-- Status Penugasan -->
                    <div>
                        <div class="flex items-center justify-between min-h-[22px] mb-1.5">
                            <label class="block text-xs font-bold text-slate-700">Status Penugasan <span class="text-red-500">*</span></label>
                        </div>
                        <div class="relative">
                            <span class="absolute left-3.5 inset-y-0 flex items-center text-xs text-slate-400">
                                <i class="fas fa-tag"></i>
                            </span>
                            <input type="text" disabled value="Definitif"
                                    class="w-full pl-9 pr-8 py-2.5 bg-slate-100 border border-slate-200 rounded-xl text-xs sm:text-sm font-medium text-slate-500 cursor-not-allowed">
                            <input type="hidden" name="status_penugasan" value="Definitif">
                        </div>

                    </div>

                    <!-- Status Karyawan -->
                    <div>
                        <div class="flex items-center min-h-[22px] mb-1.5">
                            <label class="block text-xs font-bold text-slate-700">Status Kepegawaian <span class="text-red-500">*</span></label>
                        </div>
                        <div class="relative">
                            <span class="absolute left-3.5 inset-y-0 flex items-center text-xs text-slate-400">
                                <i class="fas fa-id-badge"></i>
                            </span>
                            <select name="status_karyawan" x-model="statusKaryawan" required class="w-full pl-9 pr-8 py-2.5 bg-slate-50/70 border border-slate-200 text-slate-800 rounded-xl text-xs sm:text-sm font-medium focus:outline-none focus:bg-white focus:ring-2 focus:ring-emerald-500/30 focus:border-emerald-500 transition-all duration-300 appearance-none">
                                @foreach(\App\Models\Employee::EMPLOYMENT_STATUSES as $val => $label)
                                    <option value="{{ $val }}">{{ $label }}</option>
                                @endforeach
                            </select>
                            <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-3.5 text-slate-400">
                                <i class="fas fa-chevron-down text-[10px]"></i>
                            </div>
                        </div>
                        @error('status_karyawan') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>

                    <!-- Tanggal Berakhir Kontrak (Hanya tampil jika PKWT / Calon) -->
                    <div x-show="statusKaryawan !== 'Tetap (PKWTT)'" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 translate-x-4" x-transition:enter-end="opacity-100 translate-x-0" style="display: none;">
                        <div class="flex items-center min-h-[22px] mb-1.5">
                            <label class="block text-xs font-bold text-slate-700">Batas Berakhir Kontrak <span class="text-red-500">*</span></label>
                        </div>
                        <div class="relative">
                            <span class="absolute left-3.5 inset-y-0 flex items-center text-xs text-slate-400">
                                <i class="fas fa-calendar-times"></i>
                            </span>
                            <input type="date" name="tanggal_berakhir_kontrak" value="{{ old('tanggal_berakhir_kontrak') }}"
                                class="w-full pl-9 pr-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs sm:text-sm font-medium text-slate-800 focus:outline-none focus:bg-white focus:ring-2 focus:ring-emerald-500/30 focus:border-emerald-500 transition-all duration-300">
                        </div>
                        @error('tanggal_berakhir_kontrak') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
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
                                    id="rmLevelInput"
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
                    <input type="date" name="tmt_bekerja" value="{{ old('tmt_bekerja', now()->toDateString()) }}"
                        class="w-full px-3.5 py-2.5 bg-slate-50/70 border border-slate-200 rounded-xl text-xs sm:text-sm font-medium text-slate-800 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500/30 focus:border-emerald-500 transition">
                    <p class="text-[10px] text-slate-400 mt-1">Tanggal pertama kali diangkat di PTPN</p>
                </div>

                <!-- TMT Unit Kerja -->
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1.5">TMT Unit Kerja</label>
                    <input type="date" name="tmt_unit_kerja" value="{{ old('tmt_unit_kerja', now()->toDateString()) }}"
                        class="w-full px-3.5 py-2.5 bg-slate-50/70 border border-slate-200 rounded-xl text-xs sm:text-sm font-medium text-slate-800 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500/30 focus:border-emerald-500 transition">
                    <p class="text-[10px] text-slate-400 mt-1">Dasar rotasi dinas unit</p>
                </div>

                <!-- Tanggal Dalam Jabatan -->
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1.5">Tanggal Dalam Jabatan</label>
                    <input type="date" name="tanggal_dalam_jabatan" value="{{ old('tanggal_dalam_jabatan', now()->toDateString()) }}"
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
                                       value="{{ old('tanggal_diangkat_staf') }}"
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

        <!-- Sticky / Floating Action Bar -->
        <div class="bg-white rounded-2xl border border-slate-200/80 p-4 sm:p-5 shadow-xs flex flex-col sm:flex-row items-center justify-between gap-3">
            <div class="text-xs text-slate-500 flex items-center gap-1.5">
                <i class="fas fa-shield-halved text-emerald-600"></i>
                <span>Audit trail penempatan akan otomatis tercatat di riwayat jabatan.</span>
            </div>

            <div class="flex items-center gap-3 w-full sm:w-auto justify-end">
                <a href="{{ route('admin.employees.index') }}"
                    class="px-5 py-2.5 rounded-xl border border-slate-200 text-slate-600 hover:bg-slate-50 text-xs sm:text-sm font-semibold transition cursor-pointer">
                    Batal
                </a>
                <button type="submit"
                    class="inline-flex items-center gap-2 px-6 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs sm:text-sm font-bold shadow-md shadow-emerald-600/20 active:scale-95 transition cursor-pointer">
                    <i class="fas fa-check-circle text-xs"></i>
                    <span>Simpan Pegawai Baru</span>
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
            const input = document.getElementById('jabatanInput');
            const datalist = document.getElementById('positionOptions');
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
