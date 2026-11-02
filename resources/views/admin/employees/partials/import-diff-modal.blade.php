{{-- MODAL INSPEKSI PERUBAHAN DATA (DIFF INSPECTOR DIALOG) --}}
<div x-show="showDiffModal" x-cloak class="relative z-50" aria-labelledby="import-diff-title" role="dialog" aria-modal="true" x-trap.noscroll="showDiffModal" tabindex="-1" @keydown.window.escape="closeDiffModal()">
    <div x-show="showDiffModal"
         x-transition:enter="ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed inset-0 bg-surface-900/60 backdrop-blur-xs transition-opacity"
         @click="closeDiffModal()"></div>

    <div class="fixed inset-0 z-50 overflow-y-auto">
        <div class="flex min-h-full items-center justify-center p-4 text-center">
            <div x-show="showDiffModal"
                 x-transition:enter="ease-out duration-300"
                 x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                 x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                 x-transition:leave="ease-in duration-200"
                 class="relative transform overflow-hidden rounded-2xl bg-white text-left shadow-2xl transition-all my-8 w-full max-w-2xl border border-surface-200">
                
                <!-- Header -->
                <div class="px-6 py-4 border-b border-surface-200 bg-surface-50/90 flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-warning-100 text-warning-800 flex items-center justify-center text-lg border border-warning-200 shrink-0">
                            <i class="fas fa-code-compare" aria-hidden="true"></i>
                        </div>
                        <div>
                            <h3 id="import-diff-title" class="text-sm sm:text-base font-extrabold text-surface-900 flex items-center gap-2">
                                <span>Inspeksi Perubahan Atribut</span>
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-extrabold bg-warning-100 text-warning-800 border border-warning-200">
                                    UPDATE
                                </span>
                            </h3>
                            <p class="text-xs text-surface-500 mt-0.5">
                                Pegawai: <strong class="text-surface-800" x-text="activeDiffRow?.nama"></strong> &bull; NIK: <strong class="font-mono text-surface-800" x-text="activeDiffRow?.nik"></strong>
                            </p>
                        </div>
                    </div>
                    <button aria-label="Tutup" type="button" @click="closeDiffModal()" class="w-8 h-8 rounded-lg text-surface-500 hover:text-surface-700 hover:bg-surface-200/60 transition flex items-center justify-center cursor-pointer">
                        <i class="fas fa-xmark text-sm" aria-hidden="true"></i>
                    </button>
                </div>

                <!-- Body: Diff Table -->
                <div class="p-6 max-h-[60vh] overflow-y-auto">
                    <div class="border border-surface-200 rounded-xl overflow-hidden shadow-2xs">
                        <table class="w-full text-left border-collapse text-xs">
                            <thead class="bg-surface-100 border-b border-surface-200 text-surface-700 text-[11px] uppercase font-bold">
                                <tr>
                                    <th scope="col" class="py-2.5 px-3.5 w-1/3">Atribut Kepegawaian</th>
                                    <th scope="col" class="py-2.5 px-3.5 w-1/3 text-danger-700 bg-danger-50/50">Nilai Saat Ini (Database)</th>
                                    <th scope="col" class="py-2.5 px-3.5 w-1/3 text-primary-700 bg-primary-50/50">Nilai Baru (Spreadsheet)</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-surface-100 text-[11px] bg-white">
                                <template x-for="(item, idx) in (activeDiffRow?.diff || [])" :key="'diff-' + idx">
                                    <tr class="hover:bg-surface-50 transition">
                                        <td class="py-2.5 px-3.5 font-bold text-surface-800" x-text="item.label || item.field"></td>
                                        <td class="py-2.5 px-3.5 text-danger-700 bg-danger-50/20 font-mono">
                                            <span class="line-through opacity-80" x-text="item.old"></span>
                                        </td>
                                        <td class="py-2.5 px-3.5 text-primary-800 bg-primary-50/20 font-mono font-bold">
                                            <span class="inline-flex items-center gap-1">
                                                <i class="fas fa-arrow-right text-[10px] text-primary-600" aria-hidden="true"></i>
                                                <span x-text="item.new"></span>
                                            </span>
                                        </td>
                                    </tr>
                                </template>
                                <template x-if="!activeDiffRow?.diff || activeDiffRow?.diff.length === 0">
                                    <tr>
                                        <td colspan="3" class="py-4 text-center text-surface-500 italic">
                                            Tidak ada perbedaan atribut spesifik yang terdeteksi.
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Footer -->
                <div class="px-6 py-3.5 bg-surface-50 border-t border-surface-200 flex items-center justify-between">
                    <span class="text-[11px] text-surface-500">
                        Total <strong class="text-warning-800" x-text="(activeDiffRow?.diff || []).length"></strong> atribut yang akan disesuaikan.
                    </span>
                    <button type="button" @click="closeDiffModal()"
                        class="px-4 py-2 rounded-xl bg-surface-800 hover:bg-surface-900 text-white text-xs font-bold transition cursor-pointer">
                        Tutup
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
