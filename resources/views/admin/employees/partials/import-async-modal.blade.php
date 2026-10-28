{{-- ASYNCHRONOUS BACKGROUND JOB MODAL (HYBRID EXECUTION) --}}
<div x-show="isAsync" x-cloak x-transition.opacity role="status" aria-live="polite" class="fixed inset-0 z-50 bg-surface-900/60 backdrop-blur-xs flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl max-w-md w-full p-6 text-center space-y-4 shadow-2xl border border-surface-100 animate-fade-in">
        <div class="w-14 h-14 mx-auto rounded-2xl bg-primary-50 text-primary-600 flex items-center justify-center text-2xl border border-primary-200 shadow-inner">
            <i class="fas fa-arrows-rotate fa-spin" aria-hidden="true"></i>
        </div>
        <div class="space-y-1">
            <h4 class="text-base font-extrabold text-surface-900">Pemrosesan Antrean Latar Belakang</h4>
            <p class="text-xs text-surface-500" x-text="jobMessage || 'Sedang memproses berkas spreadsheet di latar belakang...'"></p>
        </div>
        <!-- Progress bar -->
        <div class="w-full bg-surface-100 rounded-full h-2.5 overflow-hidden border border-surface-200">
            <div class="bg-primary-600 h-2.5 rounded-full transition-all duration-300" :style="'width: ' + jobProgress + '%'"></div>
        </div>
        <div class="flex items-center justify-between text-[11px] text-surface-500 font-medium">
            <span>Arsitektur Hybrid Queue Job</span>
            <span x-text="jobProgress + '% Selesai'"></span>
        </div>
    </div>
</div>
