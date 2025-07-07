@extends('layouts.admin')
@section('content')
    <div class="bg-gray-100 min-h-screen">
        <div class="max-w-7xl mx-auto bg-white p-6 rounded-xl shadow-lg space-y-6">
            <!-- Page Header -->
            <div class="flex flex-col sm:flex-row justify-between items-center gap-4 mb-6">
                <div>
                    <h1 class="text-2xl font-bold text-gray-800">Manajemen Karyawan</h1>
                    <p class="text-gray-500 mt-1">Kelola data karyawan</p>
                </div>
                <a href="{{ route('admin.employees.create') }}"
                    class="w-full sm:w-auto bg-blue-600 text-white px-6 py-2.5 rounded-lg hover:bg-blue-700 transition ease-in-out duration-200 flex items-center justify-center shadow-sm">
                    <i class="fas fa-plus mr-2"></i> Tambah Karyawan
                </a>
            </div>
            <div class="space-y-6">

                <!-- Excel Import Form, Template, and Export Button -->
                <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
                    <div class="px-6 py-4 bg-gradient-to-r from-blue-50 to-white border-b border-gray-200">
                        <h2 class="text-lg font-semibold text-gray-800 flex items-center">
                            <i class="fas fa-file-excel text-blue-500 mr-2"></i> Import & Export Data
                        </h2>
                    </div>
                    <div class="p-6">
                        <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">

                            <!-- Template Download Section -->
                            <div class="flex flex-col justify-between bg-white border rounded-lg p-4 shadow-sm space-y-4">
                                <!-- Header -->
                                <div class="flex items-center mb-4">
                                    <div class="p-2 bg-blue-100 rounded-lg mr-3">
                                        <i class="fas fa-download text-blue-600"></i>
                                    </div>
                                    <div>
                                        <h3 class="font-semibold text-gray-800">Template Data</h3>
                                        <p class="text-xs text-gray-500">Download format yang benar</p>
                                    </div>
                                </div>

                                <!-- Content Area (grows to fill space) -->
                                <div class="flex-grow space-y-4">
                                    <!-- Instructions -->
                                    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                                        <div class="flex items-start">
                                            <i class="fas fa-lightbulb text-blue-500 mr-2 mt-0.5 flex-shrink-0"></i>
                                            <div>
                                                <p class="text-sm font-medium text-blue-800 mb-1">Langkah Import:</p>
                                                <ol class="text-xs text-blue-700 space-y-1 list-decimal list-inside">
                                                    <li>Download template Excel</li>
                                                    <li>Isi data sesuai format</li>
                                                    <li>Upload file yang sudah diisi</li>
                                                </ol>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Warning -->
                                    <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-3">
                                        <div class="flex items-start">
                                            <i
                                                class="fas fa-exclamation-triangle text-yellow-600 mr-2 mt-0.5 flex-shrink-0"></i>
                                            <p class="text-xs text-yellow-700">
                                                <strong>Penting:</strong> Pastikan format data sesuai dengan template untuk
                                                menghindari error saat import.
                                            </p>
                                        </div>
                                    </div>
                                </div>

                                <!-- Button Area -->
                                <div class="mt-4">
                                    <a href="{{ route('admin.employees.downloadTemplate') }}"
                                        class="w-full bg-blue-500 text-white px-4 py-3 rounded-lg hover:bg-blue-600 
                            transition duration-200 flex items-center justify-center font-medium shadow-sm group">
                                        <i class="fas fa-download mr-2 group-hover:animate-bounce"></i>
                                        Download Template
                                    </a>
                                </div>
                            </div>

                            <!-- Import Section -->
                            <div class="flex flex-col justify-between bg-white border rounded-lg p-4 shadow-sm space-y-4">
                                <!-- Header -->
                                <div class="flex items-center mb-4">
                                    <div class="p-2 bg-green-100 rounded-lg mr-3">
                                        <i class="fas fa-upload text-green-600"></i>
                                    </div>
                                    <div>
                                        <h3 class="font-semibold text-gray-800">Import Data</h3>
                                        <p class="text-xs text-gray-500">Upload file Excel karyawan</p>
                                    </div>
                                </div>

                                <!-- Content Area -->
                                <div class="flex-grow space-y-4">
                                    <form action="{{ route('admin.employees.import') }}" method="POST"
                                        enctype="multipart/form-data" id="importForm">
                                        @csrf
                                        <div class="relative">
                                            <label for="import_file" class="block text-sm font-medium text-gray-700 mb-2">
                                                Pilih File Excel
                                            </label>
                                            <div
                                                class="relative border-2 border-dashed border-gray-300 rounded-lg p-6 text-center hover:border-blue-400 transition-colors duration-200 cursor-pointer">
                                                <input type="file" name="import_file" id="import_file"
                                                    accept=".xls,.xlsx" required
                                                    class="absolute inset-0 w-full h-full opacity-0 cursor-pointer"
                                                    onchange="updateFileName(this)">
                                                <div id="file-upload-content">
                                                    <i class="fas fa-cloud-upload-alt text-2xl text-gray-400 mb-2"></i>
                                                    <p class="text-sm font-medium text-gray-700">Klik untuk pilih file</p>
                                                    <p class="text-xs text-gray-500 mt-1">atau drag & drop file Excel di
                                                        sini
                                                    </p>
                                                </div>
                                                <div id="file-selected" class="hidden">
                                                    <i class="fas fa-file-excel text-2xl text-green-500 mb-2"></i>
                                                    <p class="text-sm font-medium text-gray-700" id="file-name"></p>
                                                    <p class="text-xs text-green-600 mt-1">File siap untuk diupload</p>
                                                </div>
                                            </div>
                                            <div class="mt-2 flex items-center text-xs text-gray-500">
                                                <i class="fas fa-info-circle mr-1"></i>
                                                <span>Format: Excel (.xlsx, .xls), Maksimal: 10MB</span>
                                            </div>
                                        </div>
                                    </form>
                                </div>

                                <!-- Button Area (fixed at bottom) -->
                                <div class="mt-4">
                                    <button type="submit" form="importForm" id="importBtn"
                                        class="w-full bg-green-600 text-white px-4 py-3 rounded-lg hover:bg-green-700 
                            disabled:bg-gray-400 disabled:cursor-not-allowed
                            transition duration-200 flex items-center justify-center font-medium shadow-sm">
                                        <i class="fas fa-file-import mr-2"></i>
                                        <span id="importBtnText">Import Data</span>
                                        <i class="fas fa-spinner fa-spin ml-2 hidden" id="importSpinner"></i>
                                    </button>
                                </div>
                            </div>

                            <!-- Export Section -->
                            <div class="flex flex-col justify-between bg-white border rounded-lg p-4 shadow-sm space-y-4">
                                <!-- Header -->
                                <div class="flex items-center mb-4">
                                    <div class="p-2 bg-indigo-100 rounded-lg mr-3">
                                        <i class="fas fa-file-export text-indigo-600"></i>
                                    </div>
                                    <div>
                                        <h3 class="font-semibold text-gray-800">Export Data</h3>
                                        <p class="text-xs text-gray-500">Download data yang difilter</p>
                                    </div>
                                </div>

                                <!-- Content Area -->
                                <div class="flex-grow space-y-4">
                                    <!-- Filter Info -->
                                    <div class="bg-indigo-50 border border-indigo-200 rounded-lg p-4">
                                        <div class="flex items-start">
                                            <i class="fas fa-filter text-indigo-500 mr-2 mt-0.5 flex-shrink-0"></i>
                                            <div>
                                                <p class="text-sm font-medium text-indigo-800 mb-1">Export Berdasarkan
                                                    Filter:</p>
                                                <div class="text-xs text-indigo-700 space-y-1">
                                                    @if (request()->hasAny(['search', 'unit_kerja', 'jabatan', 'level', 'golongan']))
                                                        @if (request('search'))
                                                            <div class="flex items-center">
                                                                <i class="fas fa-search mr-1"></i>
                                                                <span>Pencarian: "{{ request('search') }}"</span>
                                                            </div>
                                                        @endif
                                                        @if (request('unit_kerja'))
                                                            <div class="flex items-center">
                                                                <i class="fas fa-building mr-1"></i>
                                                                <span>Unit: {{ request('unit_kerja') }}</span>
                                                            </div>
                                                        @endif
                                                        @if (request('jabatan'))
                                                            <div class="flex items-center">
                                                                <i class="fas fa-user-tie mr-1"></i>
                                                                <span>Jabatan: {{ request('jabatan') }}</span>
                                                            </div>
                                                        @endif
                                                        @if (request('level'))
                                                            <div class="flex items-center">
                                                                <i class="fas fa-layer-group mr-1"></i>
                                                                <span>Level: {{ request('level') }}</span>
                                                            </div>
                                                        @endif
                                                        @if (request('golongan'))
                                                            <div class="flex items-center">
                                                                <i class="fas fa-tags mr-1"></i>
                                                                <span>Golongan: {{ request('golongan') }}</span>
                                                            </div>
                                                        @endif
                                                    @else
                                                        <div class="flex items-center">
                                                            <i class="fas fa-users mr-1"></i>
                                                            <span>Semua data karyawan</span>
                                                        </div>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Success Info -->
                                    <div class="bg-green-50 border border-green-200 rounded-lg p-3">
                                        <div class="flex items-start">
                                            <i class="fas fa-check-circle text-green-600 mr-2 mt-0.5 flex-shrink-0"></i>
                                            <p class="text-xs text-green-700">
                                                File Excel akan otomatis terdownload berdasarkan filter yang diterapkan.
                                            </p>
                                        </div>
                                    </div>
                                </div>

                                <!-- Button Area -->
                                <div class="mt-4">
                                    <form action="{{ route('admin.employees.export') }}" method="GET" id="exportForm">
                                        <input type="hidden" name="search" value="{{ request('search') }}">
                                        <input type="hidden" name="jabatan" value="{{ request('jabatan') }}">
                                        <input type="hidden" name="level" value="{{ request('level') }}">
                                        <input type="hidden" name="unit_kerja" value="{{ request('unit_kerja') }}">
                                        <input type="hidden" name="golongan" value="{{ request('golongan') }}">

                                        <button type="submit" id="exportBtn"
                                            class="w-full bg-indigo-600 text-white px-4 py-3 rounded-lg hover:bg-indigo-700 
                                transition duration-200 flex items-center justify-center font-medium shadow-sm group">
                                            <i class="fas fa-file-export mr-2 group-hover:animate-pulse"></i>
                                            <span id="exportBtnText">Export Data</span>
                                            <i class="fas fa-spinner fa-spin ml-2 hidden" id="exportSpinner"></i>
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Search & Filter Form -->
                <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
                    <div class="px-6 py-4 bg-gradient-to-r from-purple-50 to-white border-b border-gray-200">
                        <h2 class="text-lg font-semibold text-gray-800 flex items-center">
                            <i class="fas fa-filter text-purple-500 mr-2"></i> Pencarian & Filter Data
                        </h2>
                    </div>
                    <div class="p-6">
                        <form method="GET" action="{{ route('admin.employees.index') }}" class="space-y-4">
                            <!-- Search Input Row -->
                            <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
                                <div class="lg:col-span-2">
                                    <label class="block text-sm font-medium text-gray-700 mb-2">
                                        <i class="fas fa-search mr-1 text-gray-500"></i>
                                        Pencarian
                                    </label>
                                    <input type="text" name="search" value="{{ request('search') }}"
                                        placeholder="Cari berdasarkan NIK atau Nama Karyawan..."
                                        class="w-full px-4 py-3 border border-gray-300 rounded-lg text-sm
                                            focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors
                                            placeholder-gray-400">
                                </div>
                                <div class="flex items-end">
                                    <div class="w-full space-y-2">
                                        <div class="flex gap-2">
                                            <button type="submit"
                                                class="flex-1 bg-blue-600 text-white px-6 py-3 rounded-lg hover:bg-blue-700 
                                                    transition duration-200 flex items-center justify-center font-medium shadow-sm">
                                                <i class="fas fa-search mr-2"></i>
                                                Cari & Filter
                                            </button>
                                            <a href="{{ route('admin.employees.index') }}"
                                                class="px-4 py-3 bg-gray-500 text-white rounded-lg hover:bg-gray-600 
                                                    transition duration-200 flex items-center justify-center shadow-sm">
                                                <i class="fas fa-undo mr-1"></i>
                                                Reset
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <!-- Filter Options Row -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-3">
                                    <i class="fas fa-sliders-h mr-1 text-gray-500"></i>
                                    Filter Berdasarkan
                                </label>
                                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                                    <div>
                                        <label class="block text-xs font-medium text-gray-600 mb-1">Unit Kerja</label>
                                        <select name="unit_kerja"
                                            class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm
                                                focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors
                                                bg-white">
                                            <option value="">Semua Unit</option>
                                            @foreach ($units as $unit)
                                                <option value="{{ $unit }}"
                                                    {{ request('unit_kerja') == $unit ? 'selected' : '' }}>
                                                    {{ $unit }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-gray-600 mb-1">Jabatan</label>
                                        <select name="jabatan"
                                            class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm
                                                focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors
                                                bg-white">
                                            <option value="">Semua Jabatan</option>
                                            @foreach ($jabatans as $jabatan)
                                                <option value="{{ $jabatan }}"
                                                    {{ request('jabatan') == $jabatan ? 'selected' : '' }}>
                                                    {{ $jabatan }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-gray-600 mb-1">Level</label>
                                        <select name="level"
                                            class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm
                                                focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors
                                                bg-white">
                                            <option value="">Semua Level</option>
                                            @foreach ($levels as $level)
                                                <option value="{{ $level }}"
                                                    {{ request('level') == $level ? 'selected' : '' }}>
                                                    {{ $level }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-gray-600 mb-1">Golongan</label>
                                        <select name="golongan"
                                            class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm
                                                focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors
                                                bg-white">
                                            <option value="">Semua Golongan</option>
                                            @foreach ($golongans as $golongan)
                                                <option value="{{ $golongan }}"
                                                    {{ request('golongan') == $golongan ? 'selected' : '' }}>
                                                    {{ $golongan }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <!-- Active Filters Display -->
                            @if (request()->hasAny(['search', 'unit_kerja', 'jabatan', 'level', 'golongan']))
                                <div class="bg-blue-50 border border-blue-200 rounded-lg p-3">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="text-sm font-medium text-blue-700">Filter Aktif:</span>
                                        @if (request('search'))
                                            <span
                                                class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                                Pencarian: "{{ request('search') }}"
                                            </span>
                                        @endif
                                        @if (request('unit_kerja'))
                                            <span
                                                class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                                Unit: {{ request('unit_kerja') }}
                                            </span>
                                        @endif
                                        @if (request('jabatan'))
                                            <span
                                                class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                                                Jabatan: {{ request('jabatan') }}
                                            </span>
                                        @endif
                                        @if (request('level'))
                                            <span
                                                class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-purple-100 text-purple-800">
                                                Level: {{ request('level') }}
                                            </span>
                                        @endif
                                        @if (request('golongan'))
                                            <span
                                                class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-indigo-100 text-indigo-800">
                                                Golongan: {{ request('golongan') }}
                                            </span>
                                        @endif
                                    </div>
                                </div>
                            @endif
                        </form>
                    </div>
                </div>

                <!-- Employee Table -->
                <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
                    <div
                        class="px-6 py-4 bg-gradient-to-r from-yellow-50 to-white border-b border-gray-200 flex flex-col sm:flex-row justify-between items-start sm:items-center">
                        <h2 class="text-lg font-semibold text-gray-800 flex items-center">
                            <i class="fa-solid fa-clipboard-list text-yellow-500 mr-2"></i>Daftar Karyawan
                        </h2>
                        <!-- Only show count if have employees -->
                        @if ($employees->isNotEmpty())
                            <div class="mt-2 sm:mt-0 text-sm text-gray-500">
                                @if (method_exists($employees, 'total') && method_exists($employees, 'count'))
                                    Menampilkan {{ $employees->count() }} dari {{ $employees->total() }} data
                                @else
                                    Menampilkan {{ $employees->count() }} data
                                @endif
                            </div>
                        @endif
                    </div>
                    <div class="overflow-x-auto bg-white shadow rounded-lg">
                        <table
                            class="min-w-full bg-white border divide-gray-200 rounded-lg overflow-hidden shadow-md table-auto">
                            <thead class="bg-gray-100 text-sm text-gray-700">
                                <tr>
                                    <th class="p-3 text-left">NIK</th>
                                    <th class="p-3 text-left">Nama</th>
                                    <th class="p-3 text-left">Jabatan</th>
                                    <th class="p-3 text-left">Unit</th>
                                    <th class="p-3 text-left">Level</th>
                                    <th class="p-3 text-left">Golongan</th>
                                    <th class="p-3 text-center">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="text-sm text-gray-700">
                                @forelse ($employees as $employee)
                                    <tr class="border-t hover:bg-gray-50 transition duration-150">
                                        <td class="p-3">{{ $employee->nik }}</td>
                                        <td class="p-3">{{ $employee->nama }}</td>
                                        <td class="p-3">{{ $employee->jabatan }}</td>
                                        <td class="p-3">{{ $employee->unit_kerja }}</td>
                                        <td class="p-3">{{ $employee->level ?? '-' }}</td>
                                        <td class="p-3">{{ $employee->golongan ?? '-' }}</td>
                                        <td class="p-3">
                                            <div class="flex items-center gap-2 justify-center">
                                                <a href="{{ route('admin.employees.show', $employee->nik) }}"
                                                    class="p-2 bg-blue-500 text-white rounded-md hover:bg-blue-600 transition"
                                                    title="Lihat Detail">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <a href="{{ route('admin.employees.edit', $employee->nik) }}"
                                                    class="p-2 bg-yellow-400 text-white rounded-md hover:bg-yellow-500 transition"
                                                    title="Edit Data">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <form action="{{ route('admin.employees.destroy', $employee->nik) }}"
                                                    method="POST" onsubmit="return confirm('Hapus karyawan ini?')"
                                                    class="inline">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit"
                                                        class="p-2 bg-red-500 text-white rounded-md hover:bg-red-600 transition"
                                                        title="Hapus Karyawan">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="8" class="px-6 py-12 text-center">
                                            <div class="flex flex-col items-center">
                                                <div class="bg-gray-100 rounded-full p-3 mb-4">
                                                    <i class="fas fa-inbox text-gray-400 text-5xl"></i>
                                                </div>
                                                <h3 class="text-lg font-medium text-gray-900 mb-1">Tidak ada data karyawan
                                                </h3>
                                                <p class="text-gray-500 max-w-full text-center mb-4">
                                                    Belum ada data karyawan yang tersedia untuk ditampilkan saat ini.
                                                </p>
                                            </div>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <x-pagination-ui :data="$employees" /> 
                </div>
            </div>

            <!-- JavaScript for Enhanced UX -->
            <script>
                // File upload enhancement
                function updateFileName(input) {
                    const fileContent = document.getElementById('file-upload-content');
                    const fileSelected = document.getElementById('file-selected');
                    const fileName = document.getElementById('file-name');

                    if (input.files && input.files[0]) {
                        const file = input.files[0];
                        fileName.textContent = file.name;
                        fileContent.classList.add('hidden');
                        fileSelected.classList.remove('hidden');
                    } else {
                        fileContent.classList.remove('hidden');
                        fileSelected.classList.add('hidden');
                    }
                }

                // Form submission loading states
                document.getElementById('importForm').addEventListener('submit', function(e) {
                    const btn = document.getElementById('importBtn');
                    const btnText = document.getElementById('importBtnText');
                    const spinner = document.getElementById('importSpinner');

                    btn.disabled = true;
                    btnText.textContent = 'Mengimport...';
                    spinner.classList.remove('hidden');
                });

                document.getElementById('exportForm').addEventListener('submit', function(e) {
                    const btn = document.getElementById('exportBtn');
                    const btnText = document.getElementById('exportBtnText');
                    const spinner = document.getElementById('exportSpinner');

                    btn.disabled = true;
                    btnText.textContent = 'Mengexport...';
                    spinner.classList.remove('hidden');

                    // Re-enable button after 3 seconds (typical download time)
                    setTimeout(function() {
                        btn.disabled = false;
                        btnText.textContent = 'Export Data Karyawan';
                        spinner.classList.add('hidden');
                    }, 3000);
                });

                // Drag and drop functionality
                const dropZone = document.querySelector('[onclick="document.getElementById(\'import_file\').click()"]');

                ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
                    dropZone.addEventListener(eventName, preventDefaults, false);
                });

                function preventDefaults(e) {
                    e.preventDefault();
                    e.stopPropagation();
                }

                ['dragenter', 'dragover'].forEach(eventName => {
                    dropZone.addEventListener(eventName, highlight, false);
                });

                ['dragleave', 'drop'].forEach(eventName => {
                    dropZone.addEventListener(eventName, unhighlight, false);
                });

                function highlight(e) {
                    dropZone.classList.add('border-blue-500', 'bg-blue-50');
                }

                function unhighlight(e) {
                    dropZone.classList.remove('border-blue-500', 'bg-blue-50');
                }

                dropZone.addEventListener('drop', handleDrop, false);

                function handleDrop(e) {
                    const dt = e.dataTransfer;
                    const files = dt.files;

                    if (files.length > 0) {
                        document.getElementById('import_file').files = files;
                        updateFileName(document.getElementById('import_file'));
                    }
                }
            </script>

            <x-toast />
        @endsection
