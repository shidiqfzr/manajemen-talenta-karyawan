{{-- MODAL INSPEKSI PERUBAHAN DATA (DIFF INSPECTOR DIALOG) --}}
<div x-show="showDiffModal" x-cloak class="relative z-50" role="dialog" aria-modal="true" @keydown.window.escape="closeDiffModal()">
    <div x-show="showDiffModal"
         x-transition:enter="ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs transition-opacity"
         @click="closeDiffModal()"></div>

    <div class="fixed inset-0 z-50 overflow-y-auto">
        <div class="flex min-h-full items-center justify-center p-4 text-center">
            <div x-show="showDiffModal"
                 x-transition:enter="ease-out duration-300"
                 x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                 x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                 x-transition:leave="ease-in duration-200"
                 class="relative transform overflow-hidden rounded-2xl bg-white text-left shadow-2xl transition-all my-8 w-full max-w-2xl border border-slate-200">
                
                <!-- Header -->
                <div class="px-6 py-4 border-b border-slate-200 bg-slate-50/90 flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-amber-100 text-amber-800 flex items-center justify-center text-lg border border-amber-200 shrink-0">
                            <i class="fas fa-code-compare"></i>
                        </div>
                        <div>
                            <h3 class="text-sm sm:text-base font-extrabold text-slate-900 flex items-center gap-2">
                                <span>Inspeksi Perubahan Atribut</span>
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-extrabold bg-amber-100 text-amber-800 border border-amber-200">
                                    UPDATE
                                </span>
                            </h3>
                            <p class="text-xs text-slate-500 mt-0.5">
                                Pegawai: <strong class="text-slate-800" x-text="activeDiffRow?.nama"></strong> &bull; NIK: <strong class="font-mono text-slate-800" x-text="activeDiffRow?.nik"></strong>
                            </p>
                        </div>
                    </div>
                    <button type="button" @click="closeDiffModal()" class="w-8 h-8 rounded-lg text-slate-400 hover:text-slate-700 hover:bg-slate-200/60 transition flex items-center justify-center cursor-pointer">
                        <i class="fas fa-xmark text-sm"></i>
                    </button>
                </div>

                <!-- Body: Diff Table -->
                <div class="p-6 max-h-[60vh] overflow-y-auto">
                    <div class="border border-slate-200 rounded-xl overflow-hidden shadow-2xs">
                        <table class="w-full text-left border-collapse text-xs">
                            <thead class="bg-slate-100 border-b border-slate-200 text-slate-700 text-[11px] uppercase font-bold">
                                <tr>
                                    <th class="py-2.5 px-3.5 w-1/3">Atribut Kepegawaian</th>
                                    <th class="py-2.5 px-3.5 w-1/3 text-rose-700 bg-rose-50/50">Nilai Saat Ini (Database)</th>
                                    <th class="py-2.5 px-3.5 w-1/3 text-emerald-700 bg-emerald-50/50">Nilai Baru (Spreadsheet)</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 text-[11px] bg-white">
                                <template x-for="(item, idx) in (activeDiffRow?.diff || [])" :key="'diff-' + idx">
                                    <tr class="hover:bg-slate-50 transition">
                                        <td class="py-2.5 px-3.5 font-bold text-slate-800" x-text="item.label || item.field"></td>
                                        <td class="py-2.5 px-3.5 text-rose-700 bg-rose-50/20 font-mono">
                                            <span class="line-through opacity-80" x-text="item.old"></span>
                                        </td>
                                        <td class="py-2.5 px-3.5 text-emerald-800 bg-emerald-50/20 font-mono font-bold">
                                            <span class="inline-flex items-center gap-1">
                                                <i class="fas fa-arrow-right text-[9px] text-emerald-600"></i>
                                                <span x-text="item.new"></span>
                                            </span>
                                        </td>
                                    </tr>
                                </template>
                                <template x-if="!activeDiffRow?.diff || activeDiffRow?.diff.length === 0">
                                    <tr>
                                        <td colspan="3" class="py-4 text-center text-slate-400 italic">
                                            Tidak ada perbedaan atribut spesifik yang terdeteksi.
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Footer -->
                <div class="px-6 py-3.5 bg-slate-50 border-t border-slate-200 flex items-center justify-between">
                    <span class="text-[11px] text-slate-500">
                        Total <strong class="text-amber-800" x-text="(activeDiffRow?.diff || []).length"></strong> atribut yang akan disesuaikan.
                    </span>
                    <button type="button" @click="closeDiffModal()"
                        class="px-4 py-2 rounded-xl bg-slate-800 hover:bg-slate-900 text-white text-xs font-bold transition cursor-pointer">
                        Tutup
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
