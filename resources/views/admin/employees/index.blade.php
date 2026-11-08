@extends('layouts.admin')

@section('content')
<div x-data="{
    deleteModalOpen: false,
    deleteNik: '',
    deleteNama: '',
    deleteAction: '',
    deleteReason: '',
    copiedNik: null,
    // Row selection, bulk delete and export are handled by the employee table component (shared table layer).
    copyNik(nik) {
        navigator.clipboard.writeText(nik);
        this.copiedNik = nik;
        setTimeout(() => { this.copiedNik = null; }, 2000);
    },
    confirmDelete(nik, nama, deleteUrl) {
        this.deleteNik = nik;
        this.deleteNama = nama;
        this.deleteAction = deleteUrl;
        this.deleteReason = '';
        this.deleteModalOpen = true;
    }
}" class="space-y-6">

    <!-- Page Header & Action Bar -->
    <x-admin.module-header title="Manajemen Karyawan" subtitle="Kelola data induk karyawan, struktur jabatan, formasi, dan rekam jejak talenta." :breadcrumbs="['Core HR', 'Manajemen Karyawan']">
        <!-- Import Page Navigation Button (Enterprise Workday / SAP Multi-Step Standard) -->
        <a href="{{ route('admin.employees.import.page') }}"
            class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl border border-surface-200 bg-white text-surface-700 hover:bg-surface-50 hover:text-surface-900 text-xs sm:text-sm font-semibold transition shadow-xs cursor-pointer">
            <i class="fas fa-file-arrow-up text-primary-600 text-xs" aria-hidden="true"></i>
            <span>Import Excel</span>
        </a>

        <!-- Tambah Karyawan (Primary CTA) -->
        <a href="{{ route('admin.employees.create') }}"
            class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-primary-600 hover:bg-primary-700 text-white text-xs sm:text-sm font-semibold transition shadow-sm hover:shadow">
            <i class="fas fa-plus text-xs" aria-hidden="true"></i>
            <span>Tambah Karyawan</span>
        </a>
    </x-admin.module-header>

    <!-- Import Result Banner (Shown if an import was recently executed) -->
    @if(session('import_summary'))
        @php $summary = session('import_summary'); @endphp
        <div role="status" x-data="{ showErrors: false }" class="bg-white rounded-xl border border-surface-200 p-4 shadow-sm space-y-3 animate-fade-in">
            <div class="flex items-start justify-between gap-4">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl {{ $summary['has_errors'] ? 'bg-warning-50 text-warning border border-warning-200' : 'bg-primary-50 text-primary-700 border border-primary-200' }} flex items-center justify-center text-lg shrink-0">
                        <i class="{{ $summary['has_errors'] ? 'fas fa-exclamation-triangle' : 'fas fa-check-circle' }}" aria-hidden="true"></i>
                    </div>
                    <div>
                        <h4 class="text-sm font-bold text-surface-900">
                            {{ $summary['has_errors'] ? 'Import Selesai dengan Beberapa Catatan' : 'Import Data Berhasil Diproses' }}
                        </h4>
                        <p class="text-xs text-surface-500 mt-0.5">
                            Total diproses: <strong class="text-surface-800">{{ $summary['total_processed'] }} baris</strong>
                        </p>
                    </div>
                </div>

                <!-- Status Badges -->
                <div class="flex flex-wrap items-center gap-2">
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-xs font-semibold bg-primary-50 text-primary-800 border border-primary-200">
                        <i class="fas fa-plus-circle text-[10px]" aria-hidden="true"></i>
                        {{ $summary['created'] }} Baru
                    </span>
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-xs font-semibold bg-surface-100 text-surface-800 border border-surface-200">
                        <i class="fas fa-rotate text-[10px]" aria-hidden="true"></i>
                        {{ $summary['updated'] }} Diperbarui
                    </span>
                    @if($summary['skipped'] > 0)
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-xs font-semibold bg-warning-50 text-warning-800 border border-warning-200">
                            <i class="fas fa-triangle-exclamation text-[10px]" aria-hidden="true"></i>
                            {{ $summary['skipped'] }} Dilewati
                        </span>
                    @endif
                </div>
            </div>

            <!-- Error List Toggle if errors exist -->
            @if(!empty($summary['errors']))
                <div class="pt-2 border-t border-surface-200">
                    <button type="button" @click="showErrors = !showErrors"
                        class="text-xs font-semibold text-danger hover:underline inline-flex items-center gap-1.5">
                        <i class="fas fa-chevron-down text-[10px] transition-transform duration-200" :class="{ 'rotate-180': showErrors }" aria-hidden="true"></i>
                        <span x-text="showErrors ? 'Sembunyikan Detail Kendala Baris' : 'Lihat Detail Baris yang Mengalami Kendala (' + {{ count($summary['errors']) }} + ')'"></span>
                    </button>

                    <div x-show="showErrors" x-cloak class="mt-2.5 space-y-2 bg-danger-50/60 border border-danger-200/80 rounded-lg p-3 text-xs text-danger-800 max-h-56 overflow-y-auto">
                        @foreach($summary['errors'] as $errorItem)
                            <div class="flex items-start gap-2.5 py-1.5 border-b border-danger-100 last:border-b-0">
                                <span class="inline-flex items-center justify-center px-1.5 py-0.5 text-[10px] font-bold text-danger-700 bg-danger-100 rounded shrink-0">
                                    Baris {{ is_array($errorItem) ? ($errorItem['row'] ?? '?') : '?' }}
                                </span>
                                <div class="flex-1 min-w-0">
                                    @if(is_array($errorItem) && !empty($errorItem['nik']) && $errorItem['nik'] !== 'Unknown')
                                        <span class="font-mono font-semibold text-surface-800">[{{ $errorItem['nik'] }}]</span>
                                    @endif
                                    <span class="text-surface-700 leading-relaxed">
                                        @if(is_array($errorItem) && isset($errorItem['errors']) && is_array($errorItem['errors']))
                                            {{ implode('; ', $errorItem['errors']) }}
                                        @elseif(is_array($errorItem) && isset($errorItem['errors']))
                                            {{ $errorItem['errors'] }}
                                        @elseif(is_string($errorItem))
                                            {{ $errorItem }}
                                        @else
                                            Kesalahan format baris data.
                                        @endif
                                    </span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    @endif

    @if(isset($expiredSanksiEmployees) && $expiredSanksiEmployees->isNotEmpty())
        <div class="bg-danger-50 border border-danger-200 rounded-xl p-4 shadow-sm animate-fade-in relative overflow-hidden">
            <!-- Decorative background pattern -->
            <div class="absolute top-0 right-0 -mt-4 -mr-4 w-24 h-24 bg-danger-100 rounded-full opacity-50 pointer-events-none"></div>

            <div class="relative flex items-start gap-4">
                <div class="w-10 h-10 rounded-full bg-danger-100 text-danger flex items-center justify-center text-lg shrink-0 border border-danger-200 shadow-inner">
                    <i class="fas fa-exclamation-circle motion-safe:animate-pulse" aria-hidden="true"></i>
                </div>
                <div class="space-y-1.5 flex-1">
                    <h4 class="text-sm font-bold text-danger-800 uppercase tracking-wide flex items-center gap-2">
                        Peringatan: Masa Sanksi Kedaluwarsa
                        <span class="bg-danger text-white text-[10px] font-bold px-1.5 py-0.5 rounded-full shadow-sm">{{ $expiredSanksiEmployees->count() }}</span>
                    </h4>
                    <p class="text-xs text-danger-800 leading-relaxed max-w-4xl">
                        Terdapat <strong class="text-danger-800 font-extrabold">{{ $expiredSanksiEmployees->count() }} karyawan</strong> yang masa sanksi (Diperban)-nya telah berakhir hari ini atau sebelumnya. Mohon segera evaluasi dan terbitkan Mutasi Pencabutan Sanksi untuk memulihkan status mereka.
                    </p>

                    <!-- Expandable List -->
                    <div x-data="{ showList: false }" class="mt-3">
                        <button type="button" @click="showList = !showList"
                            class="text-xs font-semibold text-danger-700 hover:text-danger-800 hover:underline inline-flex items-center gap-1.5 transition-colors">
                            <span x-text="showList ? 'Sembunyikan Daftar Karyawan' : 'Lihat Daftar Karyawan'"></span>
                            <i class="fas fa-chevron-down text-[10px] transition-transform duration-200" :class="{ 'rotate-180': showList }" aria-hidden="true"></i>
                        </button>

                        <div x-show="showList" x-collapse x-cloak class="mt-3 bg-white/60 border border-danger-200 rounded-lg p-3 backdrop-blur-sm shadow-inner">
                            <div class="max-h-56 overflow-y-auto space-y-2 pr-2" style="scrollbar-width: thin; scrollbar-color: #fca5a5 transparent;">
                                @foreach($expiredSanksiEmployees as $emp)
                                    <div class="flex items-center justify-between gap-4 py-2 border-b border-danger-100 last:border-0 hover:bg-danger-50/50 transition-colors px-2 rounded-md">
                                        <div>
                                            <a href="{{ route('admin.employees.show', $emp->nik) }}" class="text-xs font-bold text-danger-800 hover:underline hover:text-danger-700">
                                                {{ $emp->nama }}
                                            </a>
                                            <p class="text-[10px] text-danger-700 mt-0.5 font-medium"><i class="fas fa-id-badge mr-1" aria-hidden="true"></i>{{ $emp->nik }} <span class="mx-1 text-danger-200">|</span> <i class="fas fa-briefcase mr-1" aria-hidden="true"></i>{{ $emp->jabatan }}</p>
                                        </div>
                                        <div class="text-right shrink-0">
                                            <span class="text-[10px] font-bold text-danger-800 bg-danger-100/80 px-2 py-1 rounded border border-danger-200 shadow-sm flex items-center gap-1.5">
                                                <i class="fas fa-calendar-times text-danger" aria-hidden="true"></i>
                                                Tgl. Berakhir: {{ \Carbon\Carbon::parse($emp->tanggal_berakhir_sanksi)->format('d M Y') }}
                                            </span>
                                            <a href="{{ route('admin.mutasi.create', ['nik' => $emp->nik]) }}" class="inline-flex mt-1 text-[10px] font-semibold text-danger hover:text-danger-800 hover:underline">
                                                Cabut Sanksi (Mutasi) &rarr;
                                            </a>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- Daftar Karyawan: kartu KPI, strip tata kelola, pencarian, filter, urutan, dan paginasi ada di komponen tabel bersama
         (App\Livewire\Admin\Employees\EmployeeTable). Kartu dan strip mengubah filter tabel secara langsung; angka KPI dihitung di controller. --}}
    <livewire:admin.employees.employee-table :kpi="$kpi" />

    {{-- MODAL: Delete Confirmation Modal (Destructive Action - DESIGN.md 6.5) --}}
    <div x-show="deleteModalOpen"
        x-cloak
        class="fixed inset-0 z-50 overflow-y-auto"
        aria-labelledby="delete-dialog-title" role="dialog" aria-modal="true" x-trap.noscroll="deleteModalOpen" tabindex="-1">
        
        <!-- Backdrop -->
        <div x-show="deleteModalOpen"
            x-transition:enter="ease-out duration-300"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="ease-in duration-200"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            class="fixed inset-0 bg-neutral-900/60 backdrop-blur-xs transition-opacity"
            @click="deleteModalOpen = false"></div>

        <div class="flex min-h-full items-center justify-center p-4 text-center sm:p-0">
            <div x-show="deleteModalOpen"
                x-transition:enter="ease-out duration-300"
                x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                x-transition:leave="ease-in duration-200"
                x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
                x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                class="relative transform overflow-hidden rounded-2xl bg-white text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-md border border-surface-200">
                
                <div class="p-6">
                    <div class="flex items-start gap-4">
                        <div class="w-12 h-12 rounded-2xl bg-danger-100 text-danger flex items-center justify-center text-xl shrink-0 border border-danger-200">
                            <i class="fas fa-triangle-exclamation" aria-hidden="true"></i>
                        </div>
                        <div class="space-y-1.5 flex-1">
                            <h3 class="text-lg font-bold text-surface-900" id="delete-dialog-title">Hapus Data Karyawan</h3>
                            <p class="text-xs sm:text-sm text-surface-600 leading-relaxed">
                                Apakah Anda yakin ingin menghapus data karyawan <strong class="text-surface-900 font-semibold" x-text="deleteNama"></strong> (NIK: <span class="font-mono text-surface-700 font-semibold" x-text="deleteNik"></span>)?
                            </p>
                            <p class="text-xs text-danger font-medium mt-2">
                                <i class="fas fa-circle-exclamation mr-1" aria-hidden="true"></i> Data riwayat mutasi, pelatihan, dan evaluasi terkait akan ikut terhapus permanen. Tindakan ini tidak dapat dibatalkan.
                            </p>
                        </div>
                    </div>
                </div>

                <form :action="deleteAction" method="POST">
                    @csrf
                    @method('DELETE')
                    <div class="px-6 pb-4">
                        <label for="delete-reason" class="block text-xs font-semibold text-surface-700 mb-1">Alasan penghapusan <span class="text-danger">*</span></label>
                        <textarea id="delete-reason" name="reason" rows="3" minlength="10" maxlength="500" required x-model="deleteReason"
                            placeholder="Contoh: data ganda hasil impor, karyawan keluar sesuai SK Direksi (minimal 10 karakter)"
                            class="w-full rounded-lg border border-surface-300 px-3 py-2 text-sm text-surface-900 placeholder:text-surface-400 focus:outline-none focus:ring-2 focus:ring-primary-500"></textarea>
                        <p class="mt-1 text-[11px] text-surface-500">Alasan dicatat bersama arsip data karyawan yang dihapus.</p>
                    </div>
                    <div class="px-6 py-4 bg-surface-50 border-t border-surface-200 flex items-center justify-end gap-2.5">
                    <button type="button" autofocus @click="deleteModalOpen = false"
                        class="px-4 py-2.5 rounded-lg border border-surface-300 bg-white text-surface-700 hover:bg-surface-100 text-xs sm:text-sm font-medium transition shadow-xs">
                        Batal
                    </button>
                        <button type="submit"
                            class="inline-flex items-center gap-2 px-4 py-2.5 rounded-lg bg-danger hover:bg-danger-700 text-white text-xs sm:text-sm font-semibold transition shadow-sm">
                            <i class="fas fa-trash-alt text-xs" aria-hidden="true"></i>
                            <span>Ya, Hapus Data</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

</div>

<x-toast />
@endsection
