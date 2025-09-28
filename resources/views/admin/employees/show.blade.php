@extends('layouts.admin')
@section('content')
    <div class="max-w-7xl mx-auto bg-white p-6 rounded-xl shadow-lg">
        <div class="max-w-5xl mx-auto">

            <!-- Header Card -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                <div class="bg-gradient-to-r from-blue-600 to-blue-700 px-6 py-4">
                    <h1 class="text-xl sm:text-2xl font-bold text-white flex items-center">
                        <i class="fas fa-user w-6 h-6 mr-3"></i>
                        Detail Karyawan
                    </h1>
                </div>

                <!-- Profile Section -->
                <div class="p-6">
                    <div class="flex flex-col lg:flex-row items-start gap-6">
                        <!-- Profile Photo -->
                        <div
                            class="w-32 h-32 sm:w-40 sm:h-40 bg-gradient-to-br from-gray-100 to-gray-200 rounded-xl overflow-hidden shadow-md border-4 border-white mx-auto lg:mx-0 flex-shrink-0">
                            @if ($employee->foto && Storage::disk('public')->exists($employee->foto))
                                <img src="{{ Storage::disk('public')->url($employee->foto) }}" alt="Foto Karyawan"
                                    class="w-full h-full object-cover">
                            @else
                                <div class="flex flex-col items-center justify-center w-full h-full text-gray-400">
                                    <i class="fas fa-user text-4xl mb-2"></i>
                                    <span class="text-xs text-center">Tidak ada foto</span>
                                </div>
                            @endif
                        </div>

                        <!-- Basic Info -->
                        <div class="flex-1 text-center lg:text-left">
                            <h2 class="text-2xl sm:text-3xl font-bold text-gray-900 mb-2">{{ $employee->nama }}</h2>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mt-4">
                                <div class="bg-blue-50 px-4 py-3 rounded-lg border border-blue-200">
                                    <label class="text-sm font-medium text-blue-700 block">NIK</label>
                                    <p class="text-blue-900 font-semibold">{{ $employee->nik }}</p>
                                </div>
                                <div class="bg-green-50 px-4 py-3 rounded-lg border border-green-200">
                                    <label class="text-sm font-medium text-green-700 block">Jabatan</label>
                                    <p class="text-green-900 font-semibold">{{ $employee->jabatan }}</p>
                                </div>
                                <div class="bg-purple-50 px-4 py-3 rounded-lg border border-purple-200 sm:col-span-2">
                                    <label class="text-sm font-medium text-purple-700 block">Unit Kerja</label>
                                    <p class="text-purple-900 font-semibold">{{ $employee->unit_kerja }}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Personal Information Card -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 mt-6 overflow-hidden">
                <div class="border-b border-gray-200 px-6 py-4 bg-gradient-to-r from-orange-50 to-orange-50">
                    <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                        <i class="fas fa-id-card text-orange-600 mr-2"></i>
                        Informasi Pribadi
                    </h3>
                </div>
                <div class="p-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
                        <div class="bg-gray-50 p-4 rounded-lg border">
                            <label class="text-sm font-medium text-gray-600 block mb-1">Tempat Lahir</label>
                            <p class="text-gray-900 font-semibold">{{ $employee->tempat_lahir }}</p>
                        </div>
                        <div class="bg-gray-50 p-4 rounded-lg border">
                            <label class="text-sm font-medium text-gray-600 block mb-1">Tanggal Lahir</label>
                            @if ($employee->tanggal_lahir)
                                <div class="flex items-center gap-2">
                                    <span class="text-gray-900 font-semibold">
                                        {{ $employee->tanggal_lahir->translatedFormat('d M Y') }}
                                    </span>
                                    <span
                                        class="inline-flex items-center px-2 py-0.5 rounded-full bg-blue-100 text-blue-700 text-xs font-medium">
                                        {{ $employee->tanggal_lahir->age }} tahun
                                    </span>
                                </div>
                            @else
                                <span class="text-gray-400">–</span>
                            @endif
                        </div>

                        <div class="bg-gray-50 p-4 rounded-lg border">
                            <label class="text-sm font-medium text-gray-600 block mb-1">Agama</label>
                            <p class="text-gray-900 font-semibold">{{ $employee->agama }}</p>
                        </div>
                        <div class="bg-gray-50 p-4 rounded-lg border">
                            <label class="text-sm font-medium text-gray-600 block mb-1">Susunan Keluarga</label>
                            <p class="text-gray-900 font-semibold">{{ $employee->susunan_keluarga }}</p>
                        </div>
                        <div class="bg-gray-50 p-4 rounded-lg border">
                            <label class="text-sm font-medium text-gray-600 block mb-1">Pendidikan Terakhir</label>
                            <p class="text-gray-900 font-semibold">{{ $employee->pendidikan_terakhir }}</p>
                        </div>
                        <div class="bg-gray-50 p-4 rounded-lg border">
                            <label class="text-sm font-medium text-gray-600 block mb-1">Sekolah</label>
                            <p class="text-gray-900 font-semibold">{{ $employee->sekolah }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Work Information Card -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 mt-6 overflow-hidden">
                <div class="border-b border-gray-200 px-6 py-4 bg-gradient-to-r from-blue-50 to-blue-50">
                    <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                        <i class="fas fa-briefcase text-blue-600 mr-2"></i>
                        Informasi Pekerjaan
                    </h3>
                </div>
                <div class="p-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
                        <div class="bg-gradient-to-br from-blue-50 to-blue-100 p-4 rounded-lg border border-blue-200">
                            <label class="text-sm font-medium text-blue-700 block mb-1">Level</label>
                            <p class="text-blue-900 font-semibold">{{ $employee->level }}</p>
                        </div>
                        <div class="bg-gradient-to-br from-green-50 to-green-100 p-4 rounded-lg border border-green-200">
                            <label class="text-sm font-medium text-green-700 block mb-1">Golongan</label>
                            <p class="text-green-900 font-semibold">{{ $employee->golongan }}</p>
                        </div>
                        <div class="bg-gradient-to-br from-purple-50 to-purple-100 p-4 rounded-lg border border-purple-200">
                            <label class="text-sm font-medium text-purple-700 block mb-1">Job Grader</label>
                            <p class="text-purple-900 font-semibold">{{ $employee->job_grader }}</p>
                        </div>
                        <div class="bg-gray-50 p-4 rounded-lg border">
                            <label class="text-sm font-medium text-gray-600 block mb-1">Person Grade</label>
                            <p class="text-gray-900 font-semibold">
                                {{ $employee->person_grade }}</p>
                        </div>
                        <div class="bg-gray-50 p-4 rounded-lg border">
                            <label class="text-sm font-medium text-gray-600 block mb-1">Tanggal Dalam Jabatan</label>
                            <p class="text-gray-900 font-semibold">
                                {{ $employee->tanggal_dalam_jabatan?->format('d M Y') ?? '–' }}</p>
                        </div>
                        <div class="bg-gray-50 p-4 rounded-lg border">
                            <label class="text-sm font-medium text-gray-600 block mb-1">Tanggal MBT</label>
                            <p class="text-gray-900 font-semibold">{{ $employee->tanggal_mbt?->format('d M Y') ?? '–' }}
                            </p>
                        </div>
                        <div class="bg-gray-50 p-4 rounded-lg border">
                            <label class="text-sm font-medium text-gray-600 block mb-1">TMT Bekerja</label>
                            <p class="text-gray-900 font-semibold">{{ $employee->tmt_bekerja?->format('d M Y') ?? '–' }}
                            </p>
                        </div>
                        <div class="bg-gray-50 p-4 rounded-lg border">
                            <label class="text-sm font-medium text-gray-600 block mb-1">Tanggal Diangkat Staf</label>
                            <p class="text-gray-900 font-semibold">
                                {{ $employee->tanggal_diangkat_staf?->format('d M Y') ?? '–' }}</p>
                        </div>
                        <div class="bg-gray-50 p-4 rounded-lg border">
                            <label class="text-sm font-medium text-gray-600 block mb-1">TMT Unit Kerja</label>
                            <p class="text-gray-900 font-semibold">{{ $employee->tmt_unit_kerja?->format('d M Y') ?? '–' }}
                            </p>
                        </div>
                        <div class="bg-gradient-to-br from-red-50 to-red-100 p-4 rounded-lg border border-red-200">
                            <label class="text-sm font-medium text-red-700 block mb-1">Tanggal Pensiun</label>
                            <p class="text-red-900 font-semibold">{{ $employee->tanggal_pensiun?->format('d M Y') ?? '–' }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Job History Information -->
                <section id="riwayat-jabatan"
                    class="bg-white rounded-xl shadow-sm border border-gray-200 mt-6 overflow-hidden">
                    <div
                        class="border-b border-gray-200 px-6 py-4 bg-gradient-to-r from-green-50 to-green-50 flex items-center justify-between gap-3">
                        <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                            <i class="fas fa-briefcase text-green-600 mr-2"></i>
                            Riwayat Jabatan
                        </h3>

                        <a href="{{ route('admin.employees.job-history.create', $employee) }}"
                            class="inline-flex items-center px-4 py-2 rounded-lg bg-green-600 text-white text-sm font-medium hover:bg-green-700 transition">
                            <i class="fas fa-plus mr-2"></i> Tambah Riwayat
                        </a>
                    </div>

                    @if ($employee->currentJob)
                        <div class="px-6 pt-4">
                            <div class="rounded-lg border border-green-200 bg-green-50 p-4">
                                <div class="flex items-start justify-between gap-4">
                                    <div>
                                        <div class="text-sm text-green-700 font-medium">Jabatan Aktif</div>
                                        <div class="mt-1 font-semibold text-green-900">
                                            {{ $employee->currentJob->jabatan }} — {{ $employee->currentJob->unit_kerja }}
                                        </div>
                                        <div class="mt-1 text-sm text-green-800">
                                            Periode:
                                            {{ $employee->currentJob->tmt_awal?->format('d M Y') }}
                                            –
                                            {{ $employee->currentJob->tmt_akhir?->format('d M Y') ?? 'Sekarang' }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endif

                    <!-- History Table -->
                    <div class="p-6">
                        @if (session()->has('message'))
                            <div class="text-sm text-green-600 mb-4 px-2">{{ session('message') }}</div>
                        @endif

                        @livewire('tables.job-history-table', ['employeeId' => $employee->nik], key('riwayat-jabatan-' . $employee->nik))
                    </div>
                </section>

                <!-- Training History Information -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 mt-6 overflow-visible">
                    <div class="border-b border-gray-200 px-6 py-4 bg-gradient-to-r from-indigo-50 to-purple-50">
                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between">
                            <h3 class="text-lg font-semibold text-gray-900 flex items-center mb-2 sm:mb-0">
                                <i class="fas fa-chalkboard-teacher text-indigo-600 mr-2"></i>
                                Riwayat Pelatihan
                            </h3>
                            @if ($trainings->count())
                                <div
                                    class="flex items-center text-sm text-gray-600 bg-white px-3 py-1 rounded-full border">
                                    <i class="fas fa-certificate text-indigo-500 mr-1"></i>
                                    {{ $trainings->total() }} Pelatihan
                                </div>
                            @endif
                        </div>
                    </div>

                    <div>
                        @if ($trainings->isEmpty())
                            <!-- Empty State -->
                            <div class="text-center py-12">
                                <div
                                    class="w-24 h-24 mx-auto mb-4 bg-gray-100 rounded-full flex items-center justify-center">
                                    <i class="fas fa-chalkboard-teacher text-3xl text-gray-400"></i>
                                </div>
                                <h4 class="text-lg font-medium text-gray-900 mb-2">Belum Ada Riwayat Pelatihan</h4>
                                <p class="text-gray-500 max-w-sm mx-auto">
                                    Karyawan ini belum mengikuti pelatihan apapun. Riwayat pelatihan akan muncul di sini
                                    ketika
                                    tersedia.
                                </p>
                            </div>
                        @else
                            <div class="p-6">
                                @if (session()->has('message'))
                                    <div class="text-sm text-green-600 mb-4 px-2">{{ session('message') }}</div>
                                @endif

                                @livewire('tables.training-table', ['employeeId' => $employee->nik], key('riwayat-pelatihan-' . $employee->nik))
                            </div>
                        @endif
                    </div>
                </div>

                <!-- Evaluation History Information -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 mt-6 overflow-visible">
                    <div class="border-b border-gray-200 px-6 py-4 bg-gradient-to-r from-emerald-50 to-teal-50">
                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between">
                            <h3 class="text-lg font-semibold text-gray-900 flex items-center mb-2 sm:mb-0">
                                <i class="fas fa-clipboard-check text-emerald-600 mr-2"></i>
                                Riwayat Penilaian
                            </h3>
                            @isset($evaluations)
                                @if ($evaluations->count())
                                    <div
                                        class="flex items-center text-sm text-gray-600 bg-white px-3 py-1 rounded-full border">
                                        <i class="fas fa-certificate text-emerald-500 mr-1"></i>
                                        {{ $evaluations->total() }} Penilaian
                                    </div>
                                @endif
                            @endisset
                        </div>
                    </div>

                    <div>
                        @if (empty($evaluations) || $evaluations->isEmpty())
                            {{-- Empty State --}}
                            <div class="text-center py-12">
                                <div
                                    class="w-24 h-24 mx-auto mb-4 bg-gray-100 rounded-full flex items-center justify-center">
                                    <i class="fas fa-clipboard-list text-3xl text-gray-400"></i>
                                </div>
                                <h4 class="text-lg font-medium text-gray-900 mb-2">Belum Ada Riwayat Penilaian</h4>
                                <p class="text-gray-500 max-w-sm mx-auto">
                                    Karyawan ini belum mengikuti penialian apapun. Riwayat penilaian akan muncul di sini
                                    ketika
                                    tersedia.
                                </p>
                            </div>
                        @else
                            @php
                                $ev = $evaluations->first();
                            @endphp

                            {{-- Ringkasan Evaluasi Terbaru --}}
                            <div class="p-6">
                                <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-6">
                                    {{-- Nilai Tertimbang Card --}}
                                    <div
                                        class="bg-gradient-to-br from-emerald-50 to-emerald-100 p-5 rounded-xl border border-emerald-200 hover:shadow-md transition-all duration-200">
                                        <div class="flex items-center justify-between mb-3">
                                            <i class="fas fa-balance-scale text-emerald-600 text-lg"></i>
                                            <span class="text-3xl font-extrabold text-emerald-900">
                                                {{ number_format($ev->nilai_tertimbang ?? 0, 1) }}
                                            </span>
                                        </div>

                                        <div class="flex items-center space-x-2 mb-3">
                                            <span class="text-sm font-semibold text-emerald-700">
                                                Nilai Tertimbang
                                            </span>
                                            <span class="relative inline-block group">
                                                <button type="button"
                                                    class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-emerald-200 text-emerald-700 text-sm leading-none focus:outline-none focus:ring-2 focus:ring-emerald-300"
                                                    aria-describedby="tooltip-nilai-tertimbang"
                                                    aria-label="Info tentang nilai tertimbang">
                                                    <i class="fas fa-info-circle text-[0.8rem] align-middle"
                                                        aria-hidden="true"></i>
                                                </button>
                                                <div id="tooltip-nilai-tertimbang" role="tooltip"
                                                    class="pointer-events-none absolute left-1/2 transform -translate-x-1/2 top-full mt-2 w-64 max-w-xs rounded-md bg-gray-800 text-white text-xs leading-snug p-3 opacity-0 scale-95 group-hover:opacity-100 group-focus-within:opacity-100 group-hover:scale-100 transition-all duration-150 shadow-lg z-50">
                                                    Ringkasan gabungan bobot (Kepemimpinan 40%, Perilaku 30%, Pengalaman
                                                    20%,
                                                    Kematangan 10%).
                                                </div>
                                            </span>
                                        </div>

                                        @if (!is_null($ev->nilai_tertimbang))
                                            @php
                                                $pct = max(0, min(100, (float) $ev->nilai_tertimbang));
                                            @endphp
                                            <div class="space-y-2">
                                                <div class="w-full bg-white/60 rounded-full h-2 overflow-hidden">
                                                    <div class="h-2 rounded-full bg-emerald-600 transition-all duration-500 ease-out"
                                                        style="width: {{ $pct }}%"></div>
                                                </div>
                                                <div class="flex justify-between items-center">
                                                    <span
                                                        class="text-xs text-emerald-700 opacity-75">{{ $pct }}%</span>
                                                    <span class="text-xs text-emerald-700 font-medium">
                                                        @if ($pct >= 90)
                                                            Outstanding
                                                        @elseif($pct >= 80)
                                                            Excellent
                                                        @elseif($pct >= 70)
                                                            Good
                                                        @elseif($pct >= 60)
                                                            Fair
                                                        @else
                                                            Needs Improvement
                                                        @endif
                                                    </span>
                                                </div>
                                            </div>
                                        @endif
                                    </div>

                                    {{-- 9-Box Card --}}
                                    <div
                                        class="bg-gradient-to-br from-indigo-50 to-indigo-100 p-5 rounded-xl border border-indigo-200 hover:shadow-md transition-all duration-200">
                                        <div class="flex items-center justify-between mb-3">
                                            <i class="fas fa-th text-indigo-600 text-lg"></i>
                                            <span
                                                class="inline-flex items-center px-3 py-1.5 rounded-full text-xs font-medium {{ $ev->kategori_9box ? 'bg-indigo-600 text-white' : 'bg-gray-200 text-gray-700' }}">
                                                {{ $ev->kategori_9box ?? 'Tidak dikategorikan' }}
                                            </span>
                                        </div>

                                        <h5 class="text-sm font-semibold text-indigo-700 mb-3">9-Box Performance</h5>

                                        <div class="space-y-3">
                                            {{-- SMKBK Progress --}}
                                            <div class="space-y-1">
                                                <div class="flex justify-between items-center">
                                                    <span class="text-xs font-medium text-indigo-700">SMKBK</span>
                                                    <span
                                                        class="text-xs font-semibold text-indigo-900">{{ number_format($ev->skor_smkbk_9box ?? 0, 1) }}</span>
                                                </div>
                                                @if (!is_null($ev->skor_smkbk_9box))
                                                    @php $smkbk_pct = max(0, min(100, (float) $ev->skor_smkbk_9box)); @endphp
                                                    <div class="w-full bg-white/60 rounded-full h-1.5 overflow-hidden">
                                                        <div class="h-1.5 rounded-full bg-indigo-600 transition-all duration-500 ease-out"
                                                            style="width: {{ $smkbk_pct }}%"></div>
                                                    </div>
                                                @endif
                                            </div>

                                            {{-- CLI Progress --}}
                                            <div class="space-y-1">
                                                <div class="flex justify-between items-center">
                                                    <span class="text-xs font-medium text-indigo-700">CLI</span>
                                                    <span
                                                        class="text-xs font-semibold text-indigo-900">{{ number_format($ev->skor_cli_9box ?? 0, 1) }}</span>
                                                </div>
                                                @if (!is_null($ev->skor_cli_9box))
                                                    @php $cli_pct = max(0, min(100, (float) $ev->skor_cli_9box)); @endphp
                                                    <div class="w-full bg-white/60 rounded-full h-1.5 overflow-hidden">
                                                        <div class="h-1.5 rounded-full bg-indigo-500 transition-all duration-500 ease-out"
                                                            style="width: {{ $cli_pct }}%"></div>
                                                    </div>
                                                @endif
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Bidang Tugas Card -->
                                    <div
                                        class="bg-gradient-to-br from-amber-50 to-amber-100 p-5 rounded-xl border border-amber-200 hover:shadow-md transition-all duration-200">
                                        <div class="flex items-center justify-between mb-3">
                                            <i class="fas fa-briefcase text-amber-700 text-lg"></i>
                                            <div class="flex items-center space-x-1">
                                                @if ($ev->bidang_tugas && $ev->bidang_tugas !== '-')
                                                    <span
                                                        class="inline-flex items-center justify-center w-2 h-2 bg-amber-600 rounded-full"></span>
                                                    <span class="text-xs text-amber-700 font-medium">Active</span>
                                                @else
                                                    <span
                                                        class="inline-flex items-center justify-center w-2 h-2 bg-gray-400 rounded-full"></span>
                                                    <span class="text-xs text-gray-600 font-medium">Unassigned</span>
                                                @endif
                                            </div>
                                        </div>
                                        <h5 class="text-sm font-semibold text-amber-700 mb-3">Bidang Tugas</h5>

                                        <div class="space-y-2">
                                            <div class="bg-white/60 rounded-lg p-3">
                                                <p class="text-amber-900 font-semibold text-sm leading-tight">
                                                    {{ $ev->bidang_tugas ?? 'Belum ditentukan' }}
                                                </p>
                                            </div>

                                            @if ($ev->bidang_tugas && $ev->bidang_tugas !== '-')
                                                <div class="flex items-center justify-between">
                                                    <span class="text-xs text-amber-700 opacity-75">Status</span>
                                                    <span class="text-xs text-amber-900 font-medium">Assigned</span>
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                </div>

                                <!-- Performance Breakdown -->
                                <div class="mb-6">
                                    <h4 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
                                        <i class="fas fa-chart-bar text-teal-600 mr-2"></i>
                                        Penilaian Per Kriteria
                                    </h4>
                                    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
                                        @php
                                            $kriterias = [
                                                [
                                                    'label' => 'Kepemimpinan',
                                                    'value' => $ev->nilai_kepemimpinan,
                                                    'color_class' => 'bg-blue-500',
                                                    'bg_class' => 'from-blue-50 to-blue-100',
                                                    'border_class' => 'border-blue-200',
                                                    'text_class' => 'text-blue-900',
                                                    'icon' => 'fas fa-users',
                                                ],
                                                [
                                                    'label' => 'Perilaku Budaya',
                                                    'value' => $ev->nilai_perilaku_budaya,
                                                    'color_class' => 'bg-green-500',
                                                    'bg_class' => 'from-green-50 to-green-100',
                                                    'border_class' => 'border-green-200',
                                                    'text_class' => 'text-green-900',
                                                    'icon' => 'fas fa-heart',
                                                ],
                                                [
                                                    'label' => 'Pengalaman Teknis',
                                                    'value' => $ev->nilai_pengalaman_teknis,
                                                    'color_class' => 'bg-purple-500',
                                                    'bg_class' => 'from-purple-50 to-purple-100',
                                                    'border_class' => 'border-purple-200',
                                                    'text_class' => 'text-purple-900',
                                                    'icon' => 'fas fa-cogs',
                                                ],
                                                [
                                                    'label' => 'Kematangan Pribadi',
                                                    'value' => $ev->nilai_kematangan_pribadi,
                                                    'color_class' => 'bg-orange-500',
                                                    'bg_class' => 'from-orange-50 to-orange-100',
                                                    'border_class' => 'border-orange-200',
                                                    'text_class' => 'text-orange-900',
                                                    'icon' => 'fas fa-user-check',
                                                ],
                                            ];
                                        @endphp

                                        @foreach ($kriterias as $kr)
                                            <div
                                                class="bg-gradient-to-br {{ $kr['bg_class'] }} p-5 rounded-xl border {{ $kr['border_class'] }} hover:shadow-md transition-all duration-200">
                                                <div class="flex items-center justify-between mb-3">
                                                    <i class="{{ $kr['icon'] }} {{ $kr['text_class'] }} text-lg"></i>
                                                    <span class="text-lg font-bold {{ $kr['text_class'] }}">
                                                        {{ is_null($kr['value']) ? '-' : number_format($kr['value'], 1) }}
                                                    </span>
                                                </div>
                                                <h5 class="text-sm font-semibold {{ $kr['text_class'] }} mb-3">
                                                    {{ $kr['label'] }}</h5>

                                                @if (!is_null($kr['value']))
                                                    @php
                                                        $pct = max(0, min(100, (float) $kr['value']));
                                                        $barClass = $kr['color_class'] ?? 'bg-blue-500';
                                                    @endphp

                                                    <div class="space-y-2">
                                                        <div class="w-full bg-white/60 rounded-full h-2 overflow-hidden">
                                                            <div class="h-2 rounded-full {{ $barClass }} transition-all duration-500 ease-out"
                                                                style="width: {{ $pct }}%"></div>
                                                        </div>
                                                        <div class="flex justify-between items-center">
                                                            <span
                                                                class="text-xs {{ $kr['text_class'] }} opacity-75">{{ $pct }}%</span>
                                                            <span class="text-xs {{ $kr['text_class'] }} font-medium">
                                                                @if ($pct >= 90)
                                                                    Excellent
                                                                @elseif($pct >= 80)
                                                                    Very Good
                                                                @elseif($pct >= 70)
                                                                    Good
                                                                @else
                                                                    Needs Improvement
                                                                @endif
                                                            </span>
                                                        </div>
                                                    </div>
                                                @endif
                                            </div>
                                        @endforeach
                                    </div>
                                </div>

                                {{-- Informasi Asesmen --}}
                                <div class="mb-6">
                                    <h4 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
                                        <i class="fas fa-user-check text-teal-600 mr-2"></i>
                                        Informasi Asesmen
                                    </h4>
                                    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
                                        <div class="bg-white p-4 rounded-lg border">
                                            <label class="text-sm font-medium text-gray-600 block mb-1">Lembaga
                                                Asesmen</label>
                                            <p class="text-gray-900 font-semibold">{{ $ev->lembaga_asesmen ?? '-' }}</p>
                                        </div>
                                        <div class="bg-white p-4 rounded-lg border">
                                            <label class="text-sm font-medium text-gray-600 block mb-1">Tanggal
                                                Pelaksanaan</label>
                                            <p class="text-gray-900 font-semibold">
                                                {{ $ev->tanggal_pelaksanaan_asesmen ? \Carbon\Carbon::parse($ev->tanggal_pelaksanaan_asesmen)->format('d M Y') : '-' }}
                                            </p>
                                        </div>
                                        <div class="bg-white p-4 rounded-lg border">
                                            <label class="text-sm font-medium text-gray-600 block mb-1">Hasil Skor</label>
                                            <p class="text-gray-900 font-semibold">
                                                {{ is_null($ev->hasil_skor_asesmen) ? '-' : number_format($ev->hasil_skor_asesmen, 2) }}
                                            </p>
                                        </div>
                                        <div class="bg-white p-4 rounded-lg border">
                                            <label class="text-sm font-medium text-gray-600 block mb-1">Kategori
                                                Asesmen</label>
                                            <p class="text-gray-900 font-semibold">{{ $ev->kategori_asesmen ?? '-' }}</p>
                                        </div>
                                        <div class="bg-white p-4 rounded-lg border">
                                            <label class="text-sm font-medium text-gray-600 block mb-1">Keterangan</label>
                                            <p class="text-gray-900">{{ $ev->keterangan_asesmen ?? '-' }}</p>
                                        </div>
                                        <div class="bg-white p-4 rounded-lg border">
                                            <label class="text-sm font-medium text-gray-600 block mb-1">Masa
                                                Berlaku</label>
                                            <p class="text-gray-900 font-semibold">
                                                {{ $ev->expired_asesmen ? \Carbon\Carbon::parse($ev->expired_asesmen)->format('d M Y') : '-' }}
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="overflow-x-auto px-6 pb-6">
                                @if (session()->has('message'))
                                    <div class="text-sm text-green-600 mb-4 px-2">{{ session('message') }}</div>
                                @endif

                                <div class="livewire-evaluation-table overflow-auto relative">
                                    @livewire('tables.evaluation-table', ['employeeId' => $employee->nik], key('riwayat-evaluasi-' . $employee->nik))
                                </div>
                            </div>
                        @endif
                    </div>
                </div>

                <!-- Back Button -->
                <div class="mt-8 flex justify-center sm:justify-start">
                    <a href="{{ route('admin.employees.index') }}"
                        class="inline-flex items-center px-6 py-3 rounded-lg border border-gray-300 text-gray-700 hover:bg-gray-50 transition-colors duration-200">
                        <i class="fas fa-arrow-left mr-2"></i> Kembali
                    </a>
                </div>
            </div>
        </div>
    @endsection
