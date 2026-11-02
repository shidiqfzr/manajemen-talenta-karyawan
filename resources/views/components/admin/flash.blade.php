{{--
    <x-admin.flash />

    Umpan balik hasil aksi modul admin (DESIGN.md §7.7): sukses = role="status", gagal = role="alert".
    Pesan gagal tidak menutup otomatis karena perlu dibaca lengkap; pesan sukses dapat ditutup manual.
--}}
@if (session('success'))
    <div role="status" x-data="{ shown: true }" x-show="shown" class="flex items-start justify-between gap-3 p-4 bg-primary-50 text-primary-800 border border-primary-200 rounded-xl shadow-xs text-sm font-medium">
        <div class="flex items-start gap-2.5">
            <i class="fas fa-circle-check mt-0.5" aria-hidden="true"></i>
            <span>{{ session('success') }}</span>
        </div>
        <button type="button" @click="shown = false" aria-label="Tutup pemberitahuan" class="text-primary-700 hover:text-primary-900 cursor-pointer rounded focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500">
            <i class="fas fa-times text-xs" aria-hidden="true"></i>
        </button>
    </div>
@endif
@if (session('error'))
    <div role="alert" class="flex items-start gap-2.5 p-4 bg-danger-50 text-danger-700 border border-danger-200 rounded-xl shadow-xs text-sm font-medium">
        <i class="fas fa-triangle-exclamation mt-0.5" aria-hidden="true"></i>
        <span>{{ session('error') }}</span>
    </div>
@endif
