    <!-- ── 1. FLASH NOTIFICATIONS ── -->
    @if (session()->has('mutasi_success'))
        <div role="status" class="p-4 bg-primary-50 border border-primary-200 text-primary-900 rounded-2xl flex items-center justify-between shadow-xs animate-fade-in">
            <div class="flex items-center gap-3">
                <div class="w-8 h-8 rounded-xl bg-primary-600 text-white flex items-center justify-center shrink-0 shadow-2xs">
                    <i class="fas fa-check text-xs" aria-hidden="true"></i>
                </div>
                <div>
                    <h4 class="text-sm font-bold text-primary-950">Berhasil!</h4>
                    <p class="text-xs text-primary-700 mt-0.5">{{ session('mutasi_success') }}</p>
                </div>
            </div>
            <button aria-label="Tutup notifikasi" onclick="this.parentElement.remove()" class="text-primary-500 hover:text-primary-800 transition p-1 cursor-pointer">
                <i class="fas fa-times text-xs" aria-hidden="true"></i>
            </button>
        </div>
    @endif

    @if (session()->has('mutasi_info'))
        <div role="status" class="p-4 bg-info-50 border border-info-200 text-info-800 rounded-2xl flex items-center justify-between shadow-xs animate-fade-in">
            <div class="flex items-center gap-3">
                <div class="w-8 h-8 rounded-xl bg-info text-white flex items-center justify-center shrink-0 shadow-2xs">
                    <i class="fas fa-circle-info text-xs" aria-hidden="true"></i>
                </div>
                <p class="text-xs font-medium text-info-800">{{ session('mutasi_info') }}</p>
            </div>
            <button aria-label="Tutup notifikasi" onclick="this.parentElement.remove()" class="text-info hover:text-info-800 transition p-1 cursor-pointer">
                <i class="fas fa-times text-xs" aria-hidden="true"></i>
            </button>
        </div>
    @endif

    @if (session()->has('mutasi_error'))
        <div role="alert" class="p-4 bg-danger-50 border border-danger-200 text-danger-800 rounded-2xl flex items-center justify-between shadow-xs animate-fade-in">
            <div class="flex items-center gap-3">
                <div class="w-8 h-8 rounded-xl bg-danger text-white flex items-center justify-center shrink-0 shadow-2xs">
                    <i class="fas fa-triangle-exclamation text-xs" aria-hidden="true"></i>
                </div>
                <div>
                    <h4 class="text-sm font-bold text-danger-800">Terjadi Kendala</h4>
                    <p class="text-xs text-danger-700 mt-0.5">{{ session('mutasi_error') }}</p>
                </div>
            </div>
            <button aria-label="Tutup notifikasi" onclick="this.parentElement.remove()" class="text-danger hover:text-danger-800 transition p-1 cursor-pointer">
                <i class="fas fa-times text-xs" aria-hidden="true"></i>
            </button>
        </div>
    @endif
