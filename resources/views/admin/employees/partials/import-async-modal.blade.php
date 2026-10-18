{{-- ASYNCHRONOUS BACKGROUND JOB MODAL (HYBRID EXECUTION) --}}
<div x-show="isAsync" x-cloak x-transition.opacity class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl max-w-md w-full p-6 text-center space-y-4 shadow-2xl border border-slate-100 animate-fade-in">
        <div class="w-14 h-14 mx-auto rounded-2xl bg-emerald-50 text-emerald-600 flex items-center justify-center text-2xl border border-emerald-200 shadow-inner">
            <i class="fas fa-arrows-rotate fa-spin"></i>
        </div>
        <div class="space-y-1">
            <h4 class="text-base font-extrabold text-slate-900">Pemrosesan Antrean Latar Belakang</h4>
            <p class="text-xs text-slate-500" x-text="jobMessage || 'Sedang memproses berkas spreadsheet di latar belakang...'"></p>
        </div>
        <!-- Progress bar -->
        <div class="w-full bg-slate-100 rounded-full h-2.5 overflow-hidden border border-slate-200">
            <div class="bg-emerald-600 h-2.5 rounded-full transition-all duration-300" :style="'width: ' + jobProgress + '%'"></div>
        </div>
        <div class="flex items-center justify-between text-[11px] text-slate-400 font-medium">
            <span>Arsitektur Hybrid Queue Job</span>
            <span x-text="jobProgress + '% Selesai'"></span>
        </div>
    </div>
</div>
