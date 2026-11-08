@extends('layouts.admin')

@section('content')
<div x-data="{
    activeTab: 'biodata',
    tabKeys(event) {
        const tabs = Array.from(event.currentTarget.querySelectorAll('[role=tab]'));
        const current = tabs.indexOf(document.activeElement);
        if (current === -1) return;
        let next = null;
        if (event.key === 'ArrowRight') next = (current + 1) % tabs.length;
        else if (event.key === 'ArrowLeft') next = (current - 1 + tabs.length) % tabs.length;
        else if (event.key === 'Home') next = 0;
        else if (event.key === 'End') next = tabs.length - 1;
        if (next === null) return;
        event.preventDefault();
        tabs[next].focus();
        tabs[next].click();
    },
    copiedNik: false,
    copyNik(nik) {
        navigator.clipboard.writeText(nik);
        this.copiedNik = true;
        setTimeout(() => { this.copiedNik = false; }, 2000);
    }
}" class="max-w-6xl mx-auto space-y-6">

    <!-- Top Breadcrumbs & Back Navigation -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <nav class="flex items-center gap-2 text-xs font-medium text-surface-500" aria-label="Breadcrumb">
            <a href="{{ route('admin.employees.index') }}" class="hover:text-primary-700 transition">Core HR</a>
            <i class="fas fa-chevron-right text-[10px] text-surface-400" aria-hidden="true"></i>
            <a href="{{ route('admin.employees.index') }}" class="hover:text-primary-700 transition">Manajemen Karyawan</a>
            <i class="fas fa-chevron-right text-[10px] text-surface-400" aria-hidden="true"></i>
            <span class="text-surface-900 font-semibold truncate max-w-xs">{{ $employee->nama }}</span>
        </nav>

        <a href="{{ route('admin.employees.index') }}"
            class="inline-flex items-center gap-2 text-xs font-semibold text-surface-600 hover:text-surface-900 hover:underline transition self-start sm:self-auto">
            <i class="fas fa-arrow-left text-[11px]" aria-hidden="true"></i>
            <span>Kembali ke Direktori</span>
        </a>
    </div>

    <!-- 1. PROFILE HERO BANNER (Standard Workday / Mekari Talenta) -->
    <div class="bg-white rounded-2xl border border-surface-200/80 shadow-xs overflow-hidden">
        <div class="p-6 sm:p-8">
            <div class="flex flex-col md:flex-row items-start md:items-center justify-between gap-6">
                <!-- Avatar & Identity Info -->
                <div class="flex flex-col sm:flex-row items-center sm:items-start md:items-center gap-5 text-center sm:text-left w-full md:w-auto">
                    <!-- Photo Avatar -->
                    <div class="relative shrink-0">
                        <div class="w-24 h-24 sm:w-28 sm:h-28 rounded-2xl overflow-hidden bg-surface-100 border-2 border-surface-100 shadow-sm flex items-center justify-center">
                            @if ($employee->foto && Storage::disk('public')->exists($employee->foto))
                                <img src="{{ Storage::disk('public')->url($employee->foto) }}" alt="{{ $employee->nama }}" class="w-full h-full object-cover">
                            @else
                                <div class="w-full h-full bg-gradient-to-br from-primary-100 to-teal-50 flex flex-col items-center justify-center text-primary-700">
                                    <span class="text-2xl font-extrabold uppercase tracking-tight">
                                        {{ substr($employee->nama, 0, 2) }}
                                    </span>
                                </div>
                            @endif
                        </div>
                        <span class="absolute -bottom-1 -right-1 w-5 h-5 rounded-full bg-primary-500 border-2 border-white flex items-center justify-center shadow-xs" title="Karyawan Aktif">
                            <i class="fas fa-check text-[10px] text-white" aria-hidden="true"></i>
                        </span>
                    </div>

                    <!-- Name & Key Indicators -->
                    <div class="space-y-1.5 min-w-0">
                        <div class="flex flex-wrap items-center justify-center sm:justify-start gap-2.5">
                            <h1 class="text-2xl sm:text-3xl font-extrabold text-surface-900 tracking-tight leading-tight">
                                {{ $employee->nama }}
                            </h1>
                            <!-- Level Pill -->
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold
                                {{ str_contains(strtolower($employee->level), 'pimpinan') || str_contains(strtolower($employee->level), 'karpim')
                                    ? 'bg-primary-50 text-primary-800 border border-primary-200'
                                    : 'bg-surface-100 text-surface-700 border border-surface-200' }}">
                                {{ $employee->level ?? 'Pelaksana' }}
                            </span>
                            @if($employee->golongan)
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-warning-50 text-warning-800 border border-warning-200">
                                    Gol. {{ $employee->golongan }}
                                </span>
                            @endif
                            @if($employee->jalur_masuk)
                                <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-teal-50 text-teal-800 border border-teal-200" title="Jalur Pengadaan / Rekrutmen">
                                    <i class="fas fa-route text-[10px] text-teal-600" aria-hidden="true"></i>
                                    <span>Jalur: {{ $employee->jalur_masuk }}</span>
                                </span>
                            @endif
                        </div>

                        <!-- Role & Unit -->
                        <p class="text-sm font-medium text-surface-600 flex flex-wrap items-center justify-center sm:justify-start gap-x-2 gap-y-1">
                            <span class="text-surface-900 font-semibold">{{ $employee->jabatan }}</span>
                            <span class="text-surface-300 hidden sm:inline">&bull;</span>
                            <span class="inline-flex items-center gap-1.5 text-surface-600">
                                <i class="fas fa-building text-surface-400 text-xs" aria-hidden="true"></i>
                                {{ $employee->unit_kerja }}
                            </span>
                        </p>

                        <!-- Meta Strip: NIK & TMT -->
                        <div class="flex flex-wrap items-center justify-center sm:justify-start gap-3 pt-1 text-xs text-surface-500">
                            <!-- NIK Pill with Copy -->
                            <button type="button" @click="copyNik('{{ $employee->nik }}')"
                                class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-surface-100 hover:bg-surface-200 text-surface-700 font-mono font-medium transition cursor-pointer"
                                title="Klik untuk salin NIK">
                                <i class="fas fa-id-badge text-surface-400" aria-hidden="true"></i>
                                <span>NIK: {{ $employee->nik }}</span>
                                <i :class="copiedNik ? 'fa-check text-primary-600' : 'fa-copy text-surface-400'" class="fas text-[10px]" aria-hidden="true"></i>
                                <span x-show="copiedNik" x-cloak class="text-[10px] text-primary-600 font-bold ml-0.5">Tersalin!</span>
                            </button>

                            @if($employee->tmt_bekerja)
                                <span class="inline-flex items-center gap-1 text-surface-500">
                                    <i class="fas fa-calendar-alt text-surface-400" aria-hidden="true"></i>
                                    <span>TMT: {{ $employee->tmt_bekerja->format('d M Y') }}</span>
                                </span>
                            @endif
                        </div>
                    </div>
                </div>

                <!-- Quick Action Buttons -->
                <div class="flex items-center gap-2.5 shrink-0 self-stretch sm:self-auto justify-center">
                    <a href="{{ route('admin.employees.edit', $employee) }}"
                        class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl border border-surface-200 bg-white text-surface-700 hover:bg-surface-50 hover:text-surface-900 text-xs sm:text-sm font-semibold transition shadow-xs">
                        <i class="fas fa-pen-to-square text-xs text-surface-500" aria-hidden="true"></i>
                        <span>Edit Profil</span>
                    </a>

                    @if(Route::has('admin.mutasi.create'))
                        <a href="{{ route('admin.mutasi.create', ['employee_nik' => $employee->nik]) }}"
                            class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-primary-600 hover:bg-primary-700 text-white text-xs sm:text-sm font-semibold transition shadow-sm hover:shadow">
                            <i class="fas fa-arrow-right-arrow-left text-xs" aria-hidden="true"></i>
                            <span>Ajukan Mutasi</span>
                        </a>
                    @endif
                </div>
            </div>
        </div>

        <!-- 2. HORIZONTAL NAVIGATION TABS (Anti-Boxitis) -->
        <div class="px-6 border-t border-surface-200/80 bg-surface-50/50">
            <div role="tablist" aria-label="Bagian profil karyawan" @keydown="tabKeys($event)" class="flex space-x-1 sm:space-x-4 overflow-x-auto no-scrollbar">
                <button type="button" role="tab" id="tab-biodata" aria-controls="panel-biodata" :aria-selected="(activeTab === 'biodata').toString()" :tabindex="activeTab === 'biodata' ? 0 : -1" @click="activeTab = 'biodata'"
                    :class="activeTab === 'biodata'
                        ? 'border-primary-600 text-primary-800 font-bold bg-white'
                        : 'border-transparent text-surface-600 hover:text-surface-900 hover:border-surface-300 font-medium'"
                    class="inline-flex items-center gap-2 py-3.5 px-4 border-b-2 text-xs sm:text-sm transition-all whitespace-nowrap cursor-pointer">
                    <i class="fas fa-user-circle text-sm" :class="activeTab === 'biodata' ? 'text-primary-600' : 'text-surface-400'" aria-hidden="true"></i>
                    <span>Biodata Pribadi</span>
                </button>

                <button type="button" role="tab" id="tab-kepegawaian" aria-controls="panel-kepegawaian" :aria-selected="(activeTab === 'kepegawaian').toString()" :tabindex="activeTab === 'kepegawaian' ? 0 : -1" @click="activeTab = 'kepegawaian'"
                    :class="activeTab === 'kepegawaian'
                        ? 'border-primary-600 text-primary-800 font-bold bg-white'
                        : 'border-transparent text-surface-600 hover:text-surface-900 hover:border-surface-300 font-medium'"
                    class="inline-flex items-center gap-2 py-3.5 px-4 border-b-2 text-xs sm:text-sm transition-all whitespace-nowrap cursor-pointer">
                    <i class="fas fa-briefcase text-sm" :class="activeTab === 'kepegawaian' ? 'text-primary-600' : 'text-surface-400'" aria-hidden="true"></i>
                    <span>Kepegawaian &amp; Formasi</span>
                </button>

                <button type="button" role="tab" id="tab-riwayat" aria-controls="panel-riwayat" :aria-selected="(activeTab === 'riwayat').toString()" :tabindex="activeTab === 'riwayat' ? 0 : -1" @click="activeTab = 'riwayat'"
                    :class="activeTab === 'riwayat'
                        ? 'border-primary-600 text-primary-800 font-bold bg-white'
                        : 'border-transparent text-surface-600 hover:text-surface-900 hover:border-surface-300 font-medium'"
                    class="inline-flex items-center gap-2 py-3.5 px-4 border-b-2 text-xs sm:text-sm transition-all whitespace-nowrap cursor-pointer">
                    <i class="fas fa-timeline text-sm" :class="activeTab === 'riwayat' ? 'text-primary-600' : 'text-surface-400'" aria-hidden="true"></i>
                    <span>Riwayat Karir</span>
                    @if(isset($histories) && $histories->total() > 0)
                        <span class="ml-1 px-1.5 py-0.5 rounded-full text-[10px] font-bold bg-surface-200 text-surface-700">
                            {{ $histories->total() }}
                        </span>
                    @endif
                </button>

                <button type="button" role="tab" id="tab-pelatihan" aria-controls="panel-pelatihan" :aria-selected="(activeTab === 'pelatihan').toString()" :tabindex="activeTab === 'pelatihan' ? 0 : -1" @click="activeTab = 'pelatihan'"
                    :class="activeTab === 'pelatihan'
                        ? 'border-primary-600 text-primary-800 font-bold bg-white'
                        : 'border-transparent text-surface-600 hover:text-surface-900 hover:border-surface-300 font-medium'"
                    class="inline-flex items-center gap-2 py-3.5 px-4 border-b-2 text-xs sm:text-sm transition-all whitespace-nowrap cursor-pointer">
                    <i class="fas fa-award text-sm" :class="activeTab === 'pelatihan' ? 'text-primary-600' : 'text-surface-400'" aria-hidden="true"></i>
                    <span>Pelatihan &amp; Kinerja</span>
                    @if(isset($trainings) && $trainings->total() > 0)
                        <span class="ml-1 px-1.5 py-0.5 rounded-full text-[10px] font-bold bg-primary-100 text-primary-800">
                            {{ $trainings->total() }}
                        </span>
                    @endif
                </button>
            </div>
        </div>
    </div>

    <!-- 3. TAB CONTENTS (Clean Definition Lists, Zero Wireframe Boxitis) -->

    <!-- TAB 1: BIODATA PRIBADI -->
    <div x-show="activeTab === 'biodata'" role="tabpanel" id="panel-biodata" aria-labelledby="tab-biodata" tabindex="0" x-cloak class="space-y-6">
        <div class="bg-white rounded-2xl border border-surface-200/80 shadow-xs p-6 sm:p-8">
            <div class="pb-4 mb-6 border-b border-surface-100 flex items-center justify-between">
                <div>
                    <h3 class="text-base font-bold text-surface-900">Informasi Pribadi &amp; Kependudukan</h3>
                    <p class="text-xs text-surface-500 mt-0.5">Data identitas kependudukan, latar belakang keluarga, dan pendidikan formal</p>
                </div>
            </div>

            <!-- Definition List without individual boxed cards -->
            <dl class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-x-8 gap-y-6">
                <div class="border-b border-surface-100 pb-4">
                    <dt class="text-xs font-medium text-surface-500">Tempat Lahir</dt>
                    <dd class="mt-1 text-sm font-semibold text-surface-900">{{ $employee->tempat_lahir ?? '–' }}</dd>
                </div>

                <div class="border-b border-surface-100 pb-4">
                    <dt class="text-xs font-medium text-surface-500">Tanggal Lahir &amp; Usia</dt>
                    <dd class="mt-1 text-sm font-semibold text-surface-900 flex items-center gap-2">
                        @if ($employee->tanggal_lahir)
                            <span>{{ $employee->tanggal_lahir->translatedFormat('d F Y') }}</span>
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full bg-surface-100 text-surface-700 text-xs font-normal">
                                {{ $employee->tanggal_lahir->age }} tahun
                            </span>
                        @else
                            <span class="text-surface-500">–</span>
                        @endif
                    </dd>
                </div>

                <div class="border-b border-surface-100 pb-4">
                    <dt class="text-xs font-medium text-surface-500">Jenis Kelamin</dt>
                    <dd class="mt-1 text-sm font-semibold text-surface-900">
                        @if($employee->jenis_kelamin === 'L')
                            <span class="inline-flex items-center gap-1.5 text-surface-800">
                                <i class="fas fa-mars text-surface-500" aria-hidden="true"></i>
                                <span>Laki-laki (L)</span>
                            </span>
                        @elseif($employee->jenis_kelamin === 'P')
                            <span class="inline-flex items-center gap-1.5 text-surface-800">
                                <i class="fas fa-venus text-surface-500" aria-hidden="true"></i>
                                <span>Perempuan (P)</span>
                            </span>
                        @else
                            <span class="text-surface-500">{{ $employee->jenis_kelamin ?? '–' }}</span>
                        @endif
                    </dd>
                </div>

                <div class="border-b border-surface-100 pb-4">
                    <dt class="text-xs font-medium text-surface-500">Agama</dt>
                    <dd class="mt-1 text-sm font-semibold text-surface-900">{{ $employee->agama ?? '–' }}</dd>
                </div>

                <div class="border-b border-surface-100 pb-4">
                    <dt class="text-xs font-medium text-surface-500">Susunan Keluarga (PTKP)</dt>
                    <dd class="mt-1 text-sm font-semibold text-surface-900">{{ $employee->susunan_keluarga ?? '–' }}</dd>
                </div>

                <div class="border-b border-surface-100 pb-4">
                    <dt class="text-xs font-medium text-surface-500">Jenjang Pendidikan Terakhir</dt>
                    <dd class="mt-1 text-sm font-semibold text-surface-900">{{ $employee->pendidikan_terakhir ?? '–' }}</dd>
                </div>

                <div class="border-b border-surface-100 pb-4">
                    <dt class="text-xs font-medium text-surface-500">Institusi / Universitas / Sekolah</dt>
                    <dd class="mt-1 text-sm font-semibold text-surface-900">{{ $employee->sekolah ?? '–' }}</dd>
                </div>
            </dl>
        </div>
    </div>

    <!-- TAB 2: KEPEGAWAIAN & FORMASI -->
    <div x-show="activeTab === 'kepegawaian'" role="tabpanel" id="panel-kepegawaian" aria-labelledby="tab-kepegawaian" tabindex="0" x-cloak class="space-y-6">
        <div class="bg-white rounded-2xl border border-surface-200/80 shadow-xs p-6 sm:p-8">
            <div class="pb-4 mb-6 border-b border-surface-100 flex items-center justify-between">
                <div>
                    <h3 class="text-base font-bold text-surface-900">Struktur Jabatan &amp; Penempatan Kerja</h3>
                    <p class="text-xs text-surface-500 mt-0.5">Rincian formasi jabatan aktif, jenjang grade, dan ketetapan masa kerja</p>
                </div>
            </div>

            <dl class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-x-8 gap-y-6">
                <div class="border-b border-surface-100 pb-4">
                    <dt class="text-xs font-medium text-surface-500">Jabatan Formasi</dt>
                    <dd class="mt-1 text-sm font-semibold text-surface-900">{{ $employee->jabatan }}</dd>
                </div>

                <div class="border-b border-surface-100 pb-4">
                    <dt class="text-xs font-medium text-surface-500">Unit Kerja / Entitas</dt>
                    <dd class="mt-1 text-sm font-semibold text-surface-900">{{ $employee->unit_kerja }}</dd>
                </div>

                <div class="border-b border-surface-100 pb-4">
                    <dt class="text-xs font-medium text-surface-500">Level Karyawan</dt>
                    <dd class="mt-1 text-sm font-semibold text-surface-900">{{ $employee->level }}</dd>
                </div>

                <div class="border-b border-surface-100 pb-4">
                    <dt class="text-xs font-medium text-surface-500">Jalur Masuk / Rekrutmen</dt>
                    <dd class="mt-1 text-sm font-semibold text-teal-800 flex items-center gap-1.5">
                        <i class="fas fa-route text-xs text-teal-600" aria-hidden="true"></i>
                        <span>{{ $employee->jalur_masuk ?? 'Reguler' }}</span>
                    </dd>
                </div>

                <div class="border-b border-surface-100 pb-4">
                    <dt class="text-xs font-medium text-surface-500">Golongan Ruang</dt>
                    <dd class="mt-1 text-sm font-semibold text-surface-900">{{ $employee->golongan ?? '–' }}</dd>
                </div>

                <div class="border-b border-surface-100 pb-4">
                    <dt class="text-xs font-medium text-surface-500">Job Grader</dt>
                    <dd class="mt-1 text-sm font-semibold text-surface-900">{{ $employee->job_grade ?? '–' }}</dd>
                </div>

                <div class="border-b border-surface-100 pb-4">
                    <dt class="text-xs font-medium text-surface-500">Person Grade</dt>
                    <dd class="mt-1 text-sm font-semibold text-surface-900">{{ $employee->person_grade ?? '–' }}</dd>
                </div>

                <div class="border-b border-surface-100 pb-4">
                    <dt class="text-xs font-medium text-surface-500">Tanggal Dalam Jabatan</dt>
                    <dd class="mt-1 text-sm font-semibold text-surface-900">{{ $employee->tanggal_dalam_jabatan?->translatedFormat('d F Y') ?? '–' }}</dd>
                </div>

                <div class="border-b border-surface-100 pb-4">
                    <dt class="text-xs font-medium text-surface-500">TMT Bekerja Awal</dt>
                    <dd class="mt-1 text-sm font-semibold text-surface-900">{{ $employee->tmt_bekerja?->translatedFormat('d F Y') ?? '–' }}</dd>
                </div>

                <div class="border-b border-surface-100 pb-4">
                    <dt class="text-xs font-medium text-surface-500">TMT Unit Kerja Aktif</dt>
                    <dd class="mt-1 text-sm font-semibold text-surface-900">{{ $employee->tmt_unit_kerja?->translatedFormat('d F Y') ?? '–' }}</dd>
                </div>

                <div class="border-b border-surface-100 pb-4">
                    <dt class="text-xs font-medium text-surface-500">Tanggal Diangkat Staf</dt>
                    <dd class="mt-1 text-sm font-semibold text-surface-900">{{ $employee->tanggal_diangkat_staf?->translatedFormat('d F Y') ?? '–' }}</dd>
                </div>

                <div class="border-b border-surface-100 pb-4">
                    <dt class="text-xs font-medium text-surface-500">Masa Bebas Tugas (MBT)</dt>
                    <dd class="mt-1 text-sm font-semibold text-warning-700 font-mono">{{ $employee->tanggal_mbt?->translatedFormat('d F Y') ?? '–' }}</dd>
                </div>

                <div class="border-b border-surface-100 pb-4">
                    <dt class="text-xs font-medium text-surface-500">Perkiraan Tanggal Pensiun</dt>
                    <dd class="mt-1 text-sm font-semibold text-surface-900 font-mono">{{ $employee->tanggal_pensiun?->translatedFormat('d F Y') ?? '–' }}</dd>
                </div>
            </dl>
        </div>
    </div>

    <!-- TAB 3: RIWAYAT KARIR & MUTASI -->
    <div x-show="activeTab === 'riwayat'" role="tabpanel" id="panel-riwayat" aria-labelledby="tab-riwayat" tabindex="0" x-cloak class="space-y-6">
        <div class="bg-white rounded-2xl border border-surface-200/80 shadow-xs overflow-hidden">
            <!-- Header Riwayat -->
            <div class="p-6 border-b border-surface-200/80 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div>
                    <h3 class="text-base font-bold text-surface-900">Rekam Jejak Mutasi &amp; Riwayat Jabatan</h3>
                    <p class="text-xs text-surface-500 mt-0.5">Histori pergerakan rotasi, promosi, dan penempatan kerja karyawan</p>
                </div>

                <a href="{{ route('admin.employees.job-history.create', $employee) }}"
                    class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-primary-600 hover:bg-primary-700 text-white text-xs font-semibold transition shadow-xs">
                    <i class="fas fa-plus text-xs" aria-hidden="true"></i>
                    <span>Tambah Riwayat Jabatan</span>
                </a>
            </div>

            <!-- Active Position Alert/Pill if currentJob exists -->
            @if ($employee->currentJob)
                <div class="m-6 p-4 rounded-xl bg-primary-50/70 border border-primary-200/80 flex items-start gap-3.5">
                    <div class="w-8 h-8 rounded-lg bg-primary-600 text-white flex items-center justify-center text-xs shrink-0 mt-0.5">
                        <i class="fas fa-circle-check" aria-hidden="true"></i>
                    </div>
                    <div class="text-xs text-primary-950 space-y-0.5">
                        <span class="text-[11px] font-bold text-primary-800 uppercase tracking-wider">Jabatan Definitif Saat Ini</span>
                        <p class="text-sm font-bold text-surface-900">
                            {{ $employee->currentJob->jabatan }} &mdash; <span class="font-semibold text-surface-700">{{ $employee->currentJob->unit_kerja }}</span>
                        </p>
                        <p class="text-surface-600 text-[11px]">
                            Periode Aktif: <strong>{{ $employee->currentJob->tmt_awal?->format('d M Y') }}</strong> s/d <strong>{{ $employee->currentJob->tmt_akhir?->format('d M Y') ?? 'Sekarang' }}</strong>
                        </p>
                    </div>
                </div>
            @endif

            <div class="p-6 pt-2">
                @if (session()->has('message'))
                    <div role="status" class="p-3 mb-4 rounded-xl bg-primary-50 border border-primary-200 text-xs font-semibold text-primary-800">
                        {{ session('message') }}
                    </div>
                @endif

                <!-- Komponen Penugasan Sementara (Plt/Pjs) -->
                @livewire('admin.employees.penugasan-sementara-manager', ['employee' => $employee], key('penugasan-sementara-' . $employee->nik))


                @livewire('tables.job-history-table', ['employeeId' => $employee->nik], key('riwayat-jabatan-' . $employee->nik))
            </div>
        </div>
    </div>

    <!-- TAB 4: PELATIHAN & KINERJA -->
    <div x-show="activeTab === 'pelatihan'" role="tabpanel" id="panel-pelatihan" aria-labelledby="tab-pelatihan" tabindex="0" x-cloak class="space-y-6">
        <!-- 4.1 Evaluation Summary Cards if available -->
        @isset($evaluations)
            @if (!$evaluations->isEmpty())
                @php $ev = $evaluations->first(); @endphp
                <div class="bg-white rounded-2xl border border-surface-200/80 shadow-xs p-6 sm:p-8 space-y-6">
                    <div class="flex items-center justify-between border-b border-surface-100 pb-4">
                        <div>
                            <h3 class="text-base font-bold text-surface-900">Hasil Evaluasi Kinerja Terakhir</h3>
                            <p class="text-xs text-surface-500 mt-0.5">Ringkasan matriks kinerja dan asesmen kompetensi talenta</p>
                        </div>
                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-primary-50 text-primary-800 border border-primary-200">
                            {{ $evaluations->total() }} Periode Penilaian
                        </span>
                    </div>

                    <!-- Modern Score Cards -->
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <!-- Nilai Tertimbang -->
                        <div class="p-5 rounded-2xl bg-primary-50/60 border border-primary-200/80 space-y-3">
                            <div class="flex items-center justify-between">
                                <span class="text-xs font-bold text-primary-800 uppercase tracking-wider">Nilai Tertimbang</span>
                                <div class="w-8 h-8 rounded-xl bg-primary-600 text-white flex items-center justify-center text-xs">
                                    <i class="fas fa-balance-scale" aria-hidden="true"></i>
                                </div>
                            </div>
                            <div class="flex items-baseline gap-2">
                                <span class="text-3xl font-extrabold text-primary-950 font-mono">
                                    {{ number_format($ev->nilai_tertimbang ?? 0, 1) }}
                                </span>
                                <span class="text-xs font-semibold text-primary-700">/ 100</span>
                            </div>
                            <div class="w-full bg-primary-200/60 rounded-full h-1.5 overflow-hidden">
                                <div class="h-1.5 rounded-full bg-primary-600" style="width: {{ max(0, min(100, (float)($ev->nilai_tertimbang ?? 0))) }}%"></div>
                            </div>
                        </div>

                        <!-- 9-Box Grid Position -->
                        <div class="p-5 rounded-2xl bg-info-50/60 border border-info-200/80 space-y-3">
                            <div class="flex items-center justify-between">
                                <span class="text-xs font-bold text-info-800 uppercase tracking-wider">Kategori 9-Box</span>
                                <div class="w-8 h-8 rounded-xl bg-info text-white flex items-center justify-center text-xs">
                                    <i class="fas fa-table-cells-large" aria-hidden="true"></i>
                                </div>
                            </div>
                            <div class="text-base font-extrabold text-info-800 pt-1">
                                {{ $ev->kategori_9box ?? 'Belum Dikategorikan' }}
                            </div>
                            <div class="flex items-center justify-between text-xs text-info-800 font-medium pt-1">
                                <span>SMKBK: <strong>{{ number_format($ev->skor_smkbk_9box ?? 0, 1) }}</strong></span>
                                <span>CLI: <strong>{{ number_format($ev->skor_cli_9box ?? 0, 1) }}</strong></span>
                            </div>
                        </div>

                        <!-- Bidang Tugas -->
                        <div class="p-5 rounded-2xl bg-warning-50/60 border border-warning-200/80 space-y-3">
                            <div class="flex items-center justify-between">
                                <span class="text-xs font-bold text-warning-800 uppercase tracking-wider">Bidang Tugas</span>
                                <div class="w-8 h-8 rounded-xl bg-warning-700 text-white flex items-center justify-center text-xs">
                                    <i class="fas fa-briefcase" aria-hidden="true"></i>
                                </div>
                            </div>
                            <div class="text-base font-extrabold text-warning-800 pt-1 truncate">
                                {{ $ev->bidang_tugas ?? 'Umum' }}
                            </div>
                            <div class="text-xs text-warning-800 font-medium pt-1">
                                Lembaga: <strong>{{ $ev->lembaga_asesmen ?? 'Internal PTPN' }}</strong>
                            </div>
                        </div>
                    </div>

                    <!-- Evaluation Table -->
                    <div class="pt-4 border-t border-surface-100">
                        <h4 class="text-xs font-bold text-surface-500 uppercase tracking-wider mb-3">Tabel Histori Penilaian</h4>
                        @livewire('tables.evaluation-table', ['employeeId' => $employee->nik], key('riwayat-evaluasi-' . $employee->nik))
                    </div>
                </div>
            @endif
        @endisset

        <!-- 4.2 Training History Livewire Section -->
        <div class="bg-white rounded-2xl border border-surface-200/80 shadow-xs overflow-hidden">
            <div class="p-6 border-b border-surface-200/80 flex items-center justify-between">
                <div>
                    <h3 class="text-base font-bold text-surface-900">Riwayat Pelatihan &amp; Sertifikasi</h3>
                    <p class="text-xs text-surface-500 mt-0.5">Daftar kursus, workshop, dan sertifikasi teknis yang telah diselesaikan</p>
                </div>

                @if (isset($trainings) && $trainings->count())
                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-primary-50 text-primary-800 border border-primary-200">
                        <i class="fas fa-certificate text-[11px]" aria-hidden="true"></i>
                        {{ $trainings->total() }} Pelatihan
                    </span>
                @endif
            </div>

            <div class="p-6">
                @if (isset($trainings) && $trainings->isEmpty())
                    <div class="text-center py-10">
                        <div class="w-14 h-14 mx-auto mb-3 bg-surface-100 rounded-2xl flex items-center justify-center text-surface-500">
                            <i class="fas fa-chalkboard-user text-2xl" aria-hidden="true"></i>
                        </div>
                        <h4 class="text-sm font-bold text-surface-800">Belum Ada Riwayat Pelatihan</h4>
                        <p class="text-xs text-surface-500 mt-1 max-w-sm mx-auto">
                            Karyawan ini belum memiliki catatan partisipasi pelatihan atau sertifikasi aktif di sistem.
                        </p>
                    </div>
                @else
                    @livewire('tables.training-table', ['employeeId' => $employee->nik], key('riwayat-pelatihan-' . $employee->nik))
                @endif
            </div>
        </div>
    </div>

</div>
@endsection
