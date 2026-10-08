@extends('layouts.admin')

@section('content')
<div x-data="{
    activeTab: 'biodata',
    copiedNik: false,
    copyNik(nik) {
        navigator.clipboard.writeText(nik);
        this.copiedNik = true;
        setTimeout(() => { this.copiedNik = false; }, 2000);
    }
}" class="max-w-6xl mx-auto space-y-6">

    <!-- Top Breadcrumbs & Back Navigation -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <nav class="flex items-center gap-2 text-xs font-medium text-slate-500" aria-label="Breadcrumb">
            <a href="{{ route('admin.employees.index') }}" class="hover:text-emerald-700 transition">Core HR</a>
            <i class="fas fa-chevron-right text-[10px] text-slate-400"></i>
            <a href="{{ route('admin.employees.index') }}" class="hover:text-emerald-700 transition">Manajemen Karyawan</a>
            <i class="fas fa-chevron-right text-[10px] text-slate-400"></i>
            <span class="text-slate-900 font-semibold truncate max-w-xs">{{ $employee->nama }}</span>
        </nav>

        <a href="{{ route('admin.employees.index') }}"
            class="inline-flex items-center gap-2 text-xs font-semibold text-slate-600 hover:text-slate-900 hover:underline transition self-start sm:self-auto">
            <i class="fas fa-arrow-left text-[11px]"></i>
            <span>Kembali ke Direktori</span>
        </a>
    </div>

    <!-- 1. PROFILE HERO BANNER (Standard Workday / Mekari Talenta) -->
    <div class="bg-white rounded-2xl border border-slate-200/80 shadow-xs overflow-hidden">
        <div class="p-6 sm:p-8">
            <div class="flex flex-col md:flex-row items-start md:items-center justify-between gap-6">
                <!-- Avatar & Identity Info -->
                <div class="flex flex-col sm:flex-row items-center sm:items-start md:items-center gap-5 text-center sm:text-left w-full md:w-auto">
                    <!-- Photo Avatar -->
                    <div class="relative shrink-0">
                        <div class="w-24 h-24 sm:w-28 sm:h-28 rounded-2xl overflow-hidden bg-slate-100 border-2 border-slate-100 shadow-sm flex items-center justify-center">
                            @if ($employee->foto && Storage::disk('public')->exists($employee->foto))
                                <img src="{{ Storage::disk('public')->url($employee->foto) }}" alt="{{ $employee->nama }}" class="w-full h-full object-cover">
                            @else
                                <div class="w-full h-full bg-gradient-to-br from-emerald-100 to-teal-50 flex flex-col items-center justify-center text-emerald-700">
                                    <span class="text-2xl font-extrabold uppercase tracking-tight">
                                        {{ substr($employee->nama, 0, 2) }}
                                    </span>
                                </div>
                            @endif
                        </div>
                        <span class="absolute -bottom-1 -right-1 w-5 h-5 rounded-full bg-emerald-500 border-2 border-white flex items-center justify-center shadow-xs" title="Pegawai Aktif">
                            <i class="fas fa-check text-[9px] text-white"></i>
                        </span>
                    </div>

                    <!-- Name & Key Indicators -->
                    <div class="space-y-1.5 min-w-0">
                        <div class="flex flex-wrap items-center justify-center sm:justify-start gap-2.5">
                            <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-900 tracking-tight leading-tight">
                                {{ $employee->nama }}
                            </h1>
                            <!-- Level Pill -->
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold
                                {{ str_contains(strtolower($employee->level), 'pimpinan') || str_contains(strtolower($employee->level), 'karpim')
                                    ? 'bg-emerald-50 text-emerald-800 border border-emerald-200'
                                    : 'bg-slate-100 text-slate-700 border border-slate-200' }}">
                                {{ $employee->level ?? 'Pelaksana' }}
                            </span>
                            @if($employee->golongan)
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-amber-50 text-amber-800 border border-amber-200">
                                    Gol. {{ $employee->golongan }}
                                </span>
                            @endif
                            @if($employee->jalur_masuk)
                                <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-teal-50 text-teal-800 border border-teal-200" title="Jalur Pengadaan / Rekrutmen">
                                    <i class="fas fa-route text-[10px] text-teal-600"></i>
                                    <span>Jalur: {{ $employee->jalur_masuk }}</span>
                                </span>
                            @endif
                        </div>

                        <!-- Role & Unit -->
                        <p class="text-sm font-medium text-slate-600 flex flex-wrap items-center justify-center sm:justify-start gap-x-2 gap-y-1">
                            <span class="text-slate-900 font-semibold">{{ $employee->jabatan }}</span>
                            <span class="text-slate-300 hidden sm:inline">&bull;</span>
                            <span class="inline-flex items-center gap-1.5 text-slate-600">
                                <i class="fas fa-building text-slate-400 text-xs"></i>
                                {{ $employee->unit_kerja }}
                            </span>
                        </p>

                        <!-- Meta Strip: NIK & TMT -->
                        <div class="flex flex-wrap items-center justify-center sm:justify-start gap-3 pt-1 text-xs text-slate-500">
                            <!-- NIK Pill with Copy -->
                            <button type="button" @click="copyNik('{{ $employee->nik }}')"
                                class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 font-mono font-medium transition cursor-pointer"
                                title="Klik untuk salin NIK">
                                <i class="fas fa-id-badge text-slate-400"></i>
                                <span>NIK: {{ $employee->nik }}</span>
                                <i :class="copiedNik ? 'fa-check text-emerald-600' : 'fa-copy text-slate-400'" class="fas text-[10px]"></i>
                                <span x-show="copiedNik" x-cloak class="text-[10px] text-emerald-600 font-bold ml-0.5">Tersalin!</span>
                            </button>

                            @if($employee->tmt_bekerja)
                                <span class="inline-flex items-center gap-1 text-slate-500">
                                    <i class="fas fa-calendar-alt text-slate-400"></i>
                                    <span>TMT: {{ $employee->tmt_bekerja->format('d M Y') }}</span>
                                </span>
                            @endif
                        </div>
                    </div>
                </div>

                <!-- Quick Action Buttons -->
                <div class="flex items-center gap-2.5 shrink-0 self-stretch sm:self-auto justify-center">
                    <a href="{{ route('admin.employees.edit', $employee) }}"
                        class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl border border-slate-200 bg-white text-slate-700 hover:bg-slate-50 hover:text-slate-900 text-xs sm:text-sm font-semibold transition shadow-xs">
                        <i class="fas fa-pen-to-square text-xs text-slate-500"></i>
                        <span>Edit Profil</span>
                    </a>

                    @if(Route::has('admin.mutasi.create'))
                        <a href="{{ route('admin.mutasi.create', ['employee_nik' => $employee->nik]) }}"
                            class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs sm:text-sm font-semibold transition shadow-sm hover:shadow">
                            <i class="fas fa-arrow-right-arrow-left text-xs"></i>
                            <span>Ajukan Mutasi</span>
                        </a>
                    @endif
                </div>
            </div>
        </div>

        <!-- 2. HORIZONTAL NAVIGATION TABS (Anti-Boxitis) -->
        <div class="px-6 border-t border-slate-200/80 bg-slate-50/50">
            <nav class="flex space-x-1 sm:space-x-4 overflow-x-auto no-scrollbar" aria-label="Tabs">
                <button type="button" @click="activeTab = 'biodata'"
                    :class="activeTab === 'biodata'
                        ? 'border-emerald-600 text-emerald-800 font-bold bg-white'
                        : 'border-transparent text-slate-600 hover:text-slate-900 hover:border-slate-300 font-medium'"
                    class="inline-flex items-center gap-2 py-3.5 px-4 border-b-2 text-xs sm:text-sm transition-all whitespace-nowrap cursor-pointer">
                    <i class="fas fa-user-circle text-sm" :class="activeTab === 'biodata' ? 'text-emerald-600' : 'text-slate-400'"></i>
                    <span>Biodata Pribadi</span>
                </button>

                <button type="button" @click="activeTab = 'kepegawaian'"
                    :class="activeTab === 'kepegawaian'
                        ? 'border-emerald-600 text-emerald-800 font-bold bg-white'
                        : 'border-transparent text-slate-600 hover:text-slate-900 hover:border-slate-300 font-medium'"
                    class="inline-flex items-center gap-2 py-3.5 px-4 border-b-2 text-xs sm:text-sm transition-all whitespace-nowrap cursor-pointer">
                    <i class="fas fa-briefcase text-sm" :class="activeTab === 'kepegawaian' ? 'text-emerald-600' : 'text-slate-400'"></i>
                    <span>Kepegawaian &amp; Formasi</span>
                </button>

                <button type="button" @click="activeTab = 'riwayat'"
                    :class="activeTab === 'riwayat'
                        ? 'border-emerald-600 text-emerald-800 font-bold bg-white'
                        : 'border-transparent text-slate-600 hover:text-slate-900 hover:border-slate-300 font-medium'"
                    class="inline-flex items-center gap-2 py-3.5 px-4 border-b-2 text-xs sm:text-sm transition-all whitespace-nowrap cursor-pointer">
                    <i class="fas fa-timeline text-sm" :class="activeTab === 'riwayat' ? 'text-emerald-600' : 'text-slate-400'"></i>
                    <span>Riwayat Karir</span>
                    @if(isset($histories) && $histories->total() > 0)
                        <span class="ml-1 px-1.5 py-0.5 rounded-full text-[10px] font-bold bg-slate-200 text-slate-700">
                            {{ $histories->total() }}
                        </span>
                    @endif
                </button>

                <button type="button" @click="activeTab = 'pelatihan'"
                    :class="activeTab === 'pelatihan'
                        ? 'border-emerald-600 text-emerald-800 font-bold bg-white'
                        : 'border-transparent text-slate-600 hover:text-slate-900 hover:border-slate-300 font-medium'"
                    class="inline-flex items-center gap-2 py-3.5 px-4 border-b-2 text-xs sm:text-sm transition-all whitespace-nowrap cursor-pointer">
                    <i class="fas fa-award text-sm" :class="activeTab === 'pelatihan' ? 'text-emerald-600' : 'text-slate-400'"></i>
                    <span>Pelatihan &amp; Kinerja</span>
                    @if(isset($trainings) && $trainings->total() > 0)
                        <span class="ml-1 px-1.5 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-800">
                            {{ $trainings->total() }}
                        </span>
                    @endif
                </button>
            </nav>
        </div>
    </div>

    <!-- 3. TAB CONTENTS (Clean Definition Lists, Zero Wireframe Boxitis) -->

    <!-- TAB 1: BIODATA PRIBADI -->
    <div x-show="activeTab === 'biodata'" x-cloak class="space-y-6">
        <div class="bg-white rounded-2xl border border-slate-200/80 shadow-xs p-6 sm:p-8">
            <div class="pb-4 mb-6 border-b border-slate-100 flex items-center justify-between">
                <div>
                    <h3 class="text-base font-bold text-slate-900">Informasi Pribadi &amp; Kependudukan</h3>
                    <p class="text-xs text-slate-500 mt-0.5">Data identitas kependudukan, latar belakang keluarga, dan pendidikan formal</p>
                </div>
            </div>

            <!-- Definition List without individual boxed cards -->
            <dl class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-x-8 gap-y-6">
                <div class="border-b border-slate-100 pb-4">
                    <dt class="text-xs font-medium text-slate-500">Tempat Lahir</dt>
                    <dd class="mt-1 text-sm font-semibold text-slate-900">{{ $employee->tempat_lahir ?? '–' }}</dd>
                </div>

                <div class="border-b border-slate-100 pb-4">
                    <dt class="text-xs font-medium text-slate-500">Tanggal Lahir &amp; Usia</dt>
                    <dd class="mt-1 text-sm font-semibold text-slate-900 flex items-center gap-2">
                        @if ($employee->tanggal_lahir)
                            <span>{{ $employee->tanggal_lahir->translatedFormat('d F Y') }}</span>
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full bg-slate-100 text-slate-700 text-xs font-normal">
                                {{ $employee->tanggal_lahir->age }} tahun
                            </span>
                        @else
                            <span class="text-slate-400">–</span>
                        @endif
                    </dd>
                </div>

                <div class="border-b border-slate-100 pb-4">
                    <dt class="text-xs font-medium text-slate-500">Jenis Kelamin</dt>
                    <dd class="mt-1 text-sm font-semibold text-slate-900">
                        @if($employee->jenis_kelamin === 'L')
                            <span class="inline-flex items-center gap-1.5 text-slate-800">
                                <i class="fas fa-mars text-blue-500"></i>
                                <span>Laki-laki (L)</span>
                            </span>
                        @elseif($employee->jenis_kelamin === 'P')
                            <span class="inline-flex items-center gap-1.5 text-slate-800">
                                <i class="fas fa-venus text-pink-500"></i>
                                <span>Perempuan (P)</span>
                            </span>
                        @else
                            <span class="text-slate-400">{{ $employee->jenis_kelamin ?? '–' }}</span>
                        @endif
                    </dd>
                </div>

                <div class="border-b border-slate-100 pb-4">
                    <dt class="text-xs font-medium text-slate-500">Agama</dt>
                    <dd class="mt-1 text-sm font-semibold text-slate-900">{{ $employee->agama ?? '–' }}</dd>
                </div>

                <div class="border-b border-slate-100 pb-4">
                    <dt class="text-xs font-medium text-slate-500">Susunan Keluarga (PTKP)</dt>
                    <dd class="mt-1 text-sm font-semibold text-slate-900">{{ $employee->susunan_keluarga ?? '–' }}</dd>
                </div>

                <div class="border-b border-slate-100 pb-4">
                    <dt class="text-xs font-medium text-slate-500">Jenjang Pendidikan Terakhir</dt>
                    <dd class="mt-1 text-sm font-semibold text-slate-900">{{ $employee->pendidikan_terakhir ?? '–' }}</dd>
                </div>

                <div class="border-b border-slate-100 pb-4">
                    <dt class="text-xs font-medium text-slate-500">Institusi / Universitas / Sekolah</dt>
                    <dd class="mt-1 text-sm font-semibold text-slate-900">{{ $employee->sekolah ?? '–' }}</dd>
                </div>
            </dl>
        </div>
    </div>

    <!-- TAB 2: KEPEGAWAIAN & FORMASI -->
    <div x-show="activeTab === 'kepegawaian'" x-cloak class="space-y-6">
        <div class="bg-white rounded-2xl border border-slate-200/80 shadow-xs p-6 sm:p-8">
            <div class="pb-4 mb-6 border-b border-slate-100 flex items-center justify-between">
                <div>
                    <h3 class="text-base font-bold text-slate-900">Struktur Jabatan &amp; Penempatan Kerja</h3>
                    <p class="text-xs text-slate-500 mt-0.5">Rincian formasi jabatan aktif, jenjang grade, dan ketetapan masa kerja</p>
                </div>
            </div>

            <dl class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-x-8 gap-y-6">
                <div class="border-b border-slate-100 pb-4">
                    <dt class="text-xs font-medium text-slate-500">Jabatan Formasi</dt>
                    <dd class="mt-1 text-sm font-semibold text-slate-900">{{ $employee->jabatan }}</dd>
                </div>

                <div class="border-b border-slate-100 pb-4">
                    <dt class="text-xs font-medium text-slate-500">Unit Kerja / Entitas</dt>
                    <dd class="mt-1 text-sm font-semibold text-slate-900">{{ $employee->unit_kerja }}</dd>
                </div>

                <div class="border-b border-slate-100 pb-4">
                    <dt class="text-xs font-medium text-slate-500">Level Pegawai</dt>
                    <dd class="mt-1 text-sm font-semibold text-slate-900">{{ $employee->level }}</dd>
                </div>

                <div class="border-b border-slate-100 pb-4">
                    <dt class="text-xs font-medium text-slate-500">Jalur Masuk / Rekrutmen</dt>
                    <dd class="mt-1 text-sm font-semibold text-teal-800 flex items-center gap-1.5">
                        <i class="fas fa-route text-xs text-teal-600"></i>
                        <span>{{ $employee->jalur_masuk ?? 'Reguler' }}</span>
                    </dd>
                </div>

                <div class="border-b border-slate-100 pb-4">
                    <dt class="text-xs font-medium text-slate-500">Golongan Ruang</dt>
                    <dd class="mt-1 text-sm font-semibold text-slate-900">{{ $employee->golongan ?? '–' }}</dd>
                </div>

                <div class="border-b border-slate-100 pb-4">
                    <dt class="text-xs font-medium text-slate-500">Job Grader</dt>
                    <dd class="mt-1 text-sm font-semibold text-slate-900">{{ $employee->job_grade ?? '–' }}</dd>
                </div>

                <div class="border-b border-slate-100 pb-4">
                    <dt class="text-xs font-medium text-slate-500">Person Grade</dt>
                    <dd class="mt-1 text-sm font-semibold text-slate-900">{{ $employee->person_grade ?? '–' }}</dd>
                </div>

                <div class="border-b border-slate-100 pb-4">
                    <dt class="text-xs font-medium text-slate-500">Tanggal Dalam Jabatan</dt>
                    <dd class="mt-1 text-sm font-semibold text-slate-900">{{ $employee->tanggal_dalam_jabatan?->translatedFormat('d F Y') ?? '–' }}</dd>
                </div>

                <div class="border-b border-slate-100 pb-4">
                    <dt class="text-xs font-medium text-slate-500">TMT Bekerja Awal</dt>
                    <dd class="mt-1 text-sm font-semibold text-slate-900">{{ $employee->tmt_bekerja?->translatedFormat('d F Y') ?? '–' }}</dd>
                </div>

                <div class="border-b border-slate-100 pb-4">
                    <dt class="text-xs font-medium text-slate-500">TMT Unit Kerja Aktif</dt>
                    <dd class="mt-1 text-sm font-semibold text-slate-900">{{ $employee->tmt_unit_kerja?->translatedFormat('d F Y') ?? '–' }}</dd>
                </div>

                <div class="border-b border-slate-100 pb-4">
                    <dt class="text-xs font-medium text-slate-500">Tanggal Diangkat Staf</dt>
                    <dd class="mt-1 text-sm font-semibold text-slate-900">{{ $employee->tanggal_diangkat_staf?->translatedFormat('d F Y') ?? '–' }}</dd>
                </div>

                <div class="border-b border-slate-100 pb-4">
                    <dt class="text-xs font-medium text-slate-500">Masa Bebas Tugas (MBT)</dt>
                    <dd class="mt-1 text-sm font-semibold text-amber-700 font-mono">{{ $employee->tanggal_mbt?->translatedFormat('d F Y') ?? '–' }}</dd>
                </div>

                <div class="border-b border-slate-100 pb-4">
                    <dt class="text-xs font-medium text-slate-500">Perkiraan Tanggal Pensiun</dt>
                    <dd class="mt-1 text-sm font-semibold text-slate-900 font-mono">{{ $employee->tanggal_pensiun?->translatedFormat('d F Y') ?? '–' }}</dd>
                </div>
            </dl>
        </div>
    </div>

    <!-- TAB 3: RIWAYAT KARIR & MUTASI -->
    <div x-show="activeTab === 'riwayat'" x-cloak class="space-y-6">
        <div class="bg-white rounded-2xl border border-slate-200/80 shadow-xs overflow-hidden">
            <!-- Header Riwayat -->
            <div class="p-6 border-b border-slate-200/80 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div>
                    <h3 class="text-base font-bold text-slate-900">Rekam Jejak Mutasi &amp; Riwayat Jabatan</h3>
                    <p class="text-xs text-slate-500 mt-0.5">Histori pergerakan rotasi, promosi, dan penempatan kerja pegawai</p>
                </div>

                <a href="{{ route('admin.employees.job-history.create', $employee) }}"
                    class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-semibold transition shadow-xs">
                    <i class="fas fa-plus text-xs"></i>
                    <span>Tambah Riwayat Jabatan</span>
                </a>
            </div>

            <!-- Active Position Alert/Pill if currentJob exists -->
            @if ($employee->currentJob)
                <div class="m-6 p-4 rounded-xl bg-emerald-50/70 border border-emerald-200/80 flex items-start gap-3.5">
                    <div class="w-8 h-8 rounded-lg bg-emerald-600 text-white flex items-center justify-center text-xs shrink-0 mt-0.5">
                        <i class="fas fa-circle-check"></i>
                    </div>
                    <div class="text-xs text-emerald-950 space-y-0.5">
                        <span class="text-[11px] font-bold text-emerald-800 uppercase tracking-wider">Jabatan Definitif Saat Ini</span>
                        <p class="text-sm font-bold text-slate-900">
                            {{ $employee->currentJob->jabatan }} &mdash; <span class="font-semibold text-slate-700">{{ $employee->currentJob->unit_kerja }}</span>
                        </p>
                        <p class="text-slate-600 text-[11px]">
                            Periode Aktif: <strong>{{ $employee->currentJob->tmt_awal?->format('d M Y') }}</strong> s/d <strong>{{ $employee->currentJob->tmt_akhir?->format('d M Y') ?? 'Sekarang' }}</strong>
                        </p>
                    </div>
                </div>
            @endif

            <div class="p-6 pt-2">
                @if (session()->has('message'))
                    <div class="p-3 mb-4 rounded-xl bg-emerald-50 border border-emerald-200 text-xs font-semibold text-emerald-800">
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
    <div x-show="activeTab === 'pelatihan'" x-cloak class="space-y-6">
        <!-- 4.1 Evaluation Summary Cards if available -->
        @isset($evaluations)
            @if (!$evaluations->isEmpty())
                @php $ev = $evaluations->first(); @endphp
                <div class="bg-white rounded-2xl border border-slate-200/80 shadow-xs p-6 sm:p-8 space-y-6">
                    <div class="flex items-center justify-between border-b border-slate-100 pb-4">
                        <div>
                            <h3 class="text-base font-bold text-slate-900">Hasil Evaluasi Kinerja Terakhir</h3>
                            <p class="text-xs text-slate-500 mt-0.5">Ringkasan matriks kinerja dan asesmen kompetensi talenta</p>
                        </div>
                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-800 border border-emerald-200">
                            {{ $evaluations->total() }} Periode Penilaian
                        </span>
                    </div>

                    <!-- Modern Score Cards -->
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <!-- Nilai Tertimbang -->
                        <div class="p-5 rounded-2xl bg-emerald-50/60 border border-emerald-200/80 space-y-3">
                            <div class="flex items-center justify-between">
                                <span class="text-xs font-bold text-emerald-800 uppercase tracking-wider">Nilai Tertimbang</span>
                                <div class="w-8 h-8 rounded-xl bg-emerald-600 text-white flex items-center justify-center text-xs">
                                    <i class="fas fa-balance-scale"></i>
                                </div>
                            </div>
                            <div class="flex items-baseline gap-2">
                                <span class="text-3xl font-extrabold text-emerald-950 font-mono">
                                    {{ number_format($ev->nilai_tertimbang ?? 0, 1) }}
                                </span>
                                <span class="text-xs font-semibold text-emerald-700">/ 100</span>
                            </div>
                            <div class="w-full bg-emerald-200/60 rounded-full h-1.5 overflow-hidden">
                                <div class="h-1.5 rounded-full bg-emerald-600" style="width: {{ max(0, min(100, (float)($ev->nilai_tertimbang ?? 0))) }}%"></div>
                            </div>
                        </div>

                        <!-- 9-Box Grid Position -->
                        <div class="p-5 rounded-2xl bg-indigo-50/60 border border-indigo-200/80 space-y-3">
                            <div class="flex items-center justify-between">
                                <span class="text-xs font-bold text-indigo-800 uppercase tracking-wider">Kategori 9-Box</span>
                                <div class="w-8 h-8 rounded-xl bg-indigo-600 text-white flex items-center justify-center text-xs">
                                    <i class="fas fa-table-cells-large"></i>
                                </div>
                            </div>
                            <div class="text-base font-extrabold text-indigo-950 pt-1">
                                {{ $ev->kategori_9box ?? 'Belum Dikategorikan' }}
                            </div>
                            <div class="flex items-center justify-between text-xs text-indigo-800 font-medium pt-1">
                                <span>SMKBK: <strong>{{ number_format($ev->skor_smkbk_9box ?? 0, 1) }}</strong></span>
                                <span>CLI: <strong>{{ number_format($ev->skor_cli_9box ?? 0, 1) }}</strong></span>
                            </div>
                        </div>

                        <!-- Bidang Tugas -->
                        <div class="p-5 rounded-2xl bg-amber-50/60 border border-amber-200/80 space-y-3">
                            <div class="flex items-center justify-between">
                                <span class="text-xs font-bold text-amber-800 uppercase tracking-wider">Bidang Tugas</span>
                                <div class="w-8 h-8 rounded-xl bg-amber-600 text-white flex items-center justify-center text-xs">
                                    <i class="fas fa-briefcase"></i>
                                </div>
                            </div>
                            <div class="text-base font-extrabold text-amber-950 pt-1 truncate">
                                {{ $ev->bidang_tugas ?? 'Umum' }}
                            </div>
                            <div class="text-xs text-amber-800 font-medium pt-1">
                                Lembaga: <strong>{{ $ev->lembaga_asesmen ?? 'Internal PTPN' }}</strong>
                            </div>
                        </div>
                    </div>

                    <!-- Evaluation Table -->
                    <div class="pt-4 border-t border-slate-100">
                        <h4 class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-3">Tabel Histori Penilaian</h4>
                        @livewire('tables.evaluation-table', ['employeeId' => $employee->nik], key('riwayat-evaluasi-' . $employee->nik))
                    </div>
                </div>
            @endif
        @endisset

        <!-- 4.2 Training History Livewire Section -->
        <div class="bg-white rounded-2xl border border-slate-200/80 shadow-xs overflow-hidden">
            <div class="p-6 border-b border-slate-200/80 flex items-center justify-between">
                <div>
                    <h3 class="text-base font-bold text-slate-900">Riwayat Pelatihan &amp; Sertifikasi</h3>
                    <p class="text-xs text-slate-500 mt-0.5">Daftar kursus, workshop, dan sertifikasi teknis yang telah diselesaikan</p>
                </div>

                @if (isset($trainings) && $trainings->count())
                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-800 border border-emerald-200">
                        <i class="fas fa-certificate text-[11px]"></i>
                        {{ $trainings->total() }} Pelatihan
                    </span>
                @endif
            </div>

            <div class="p-6">
                @if (isset($trainings) && $trainings->isEmpty())
                    <div class="text-center py-10">
                        <div class="w-14 h-14 mx-auto mb-3 bg-slate-100 rounded-2xl flex items-center justify-center text-slate-400">
                            <i class="fas fa-chalkboard-user text-2xl"></i>
                        </div>
                        <h4 class="text-sm font-bold text-slate-800">Belum Ada Riwayat Pelatihan</h4>
                        <p class="text-xs text-slate-500 mt-1 max-w-sm mx-auto">
                            Pegawai ini belum memiliki catatan partisipasi pelatihan atau sertifikasi aktif di sistem.
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
