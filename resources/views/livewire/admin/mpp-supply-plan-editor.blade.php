<div>
    @if($open)
        <div class="fixed inset-0 z-50 overflow-hidden" aria-labelledby="supply-plan-title" role="dialog" aria-modal="true" x-data x-trap.noscroll="true" tabindex="-1">
            <div class="fixed inset-0 bg-surface-900/30 transition-opacity duration-300" wire:click="requestClose"></div>

            <div class="fixed inset-y-0 right-0 max-w-full flex pl-6 sm:pl-10">
                <div class="w-screen max-w-md sm:max-w-2xl bg-white shadow-2xl border-l border-surface-200 flex flex-col">
                    <!-- Header -->
                    <div class="px-5 py-4 bg-surface-50 border-b border-surface-200 flex items-start justify-between gap-3">
                        <div class="space-y-1">
                            <div class="flex items-center gap-2">
                                <span class="w-2.5 h-2.5 rounded-full bg-primary-600 inline-block"></span>
                                <h3 class="text-sm font-bold text-surface-900" id="supply-plan-title">Rencana Pasokan Talenta {{ $year }}</h3>
                            </div>
                            <p class="text-xs text-surface-500">CKP Internal, Talent Scouting, dan RBB mengurangi Kebutuhan. Jabatan Prioritas Diisi bersifat informasi.</p>
                            <p class="text-xs text-surface-500">Terealisasi s.d. {{ $asOfLabel }}: pegawai jalur tersebut yang sudah ditempatkan sebagai Karpim tahun ini. Matriks hanya mengurangkan sisa rencana.</p>
                        </div>
                        <button type="button" wire:click="requestClose" class="p-1.5 rounded-lg text-surface-500 hover:bg-surface-200 hover:text-surface-800 transition" aria-label="Tutup">
                            <i class="fas fa-xmark" aria-hidden="true"></i>
                        </button>
                    </div>

                    <!-- Body -->
                    <div class="flex-1 overflow-y-auto px-5 py-4 space-y-5">
                        @if($confirmingDiscard)
                            <div role="alertdialog" aria-labelledby="discard-title" class="rounded-lg border border-warning-200 bg-warning-50 px-3.5 py-3 text-xs text-warning-800 space-y-2">
                                <p id="discard-title" class="font-bold">Buang perubahan yang belum disimpan?</p>
                                <p>Angka yang sudah diketik akan hilang dan rencana tetap seperti tersimpan.</p>
                                <div class="flex items-center gap-2">
                                    <button type="button" wire:click="keepEditing"
                                        class="px-3 py-1.5 text-xs font-semibold rounded-lg bg-white border border-surface-200 text-surface-700 hover:bg-surface-50 transition">
                                        Lanjut Mengedit
                                    </button>
                                    <button type="button" wire:click="discardChanges"
                                        class="px-3 py-1.5 text-xs font-semibold rounded-lg bg-danger text-white hover:opacity-90 transition">
                                        Buang Perubahan
                                    </button>
                                </div>
                            </div>
                        @endif

                        @error('plan')
                            <div role="alert" class="rounded-lg border border-danger-200 bg-danger-50 px-3.5 py-2.5 text-xs font-semibold text-danger-700">
                                {{ $message }}
                            </div>
                        @enderror

                        @if(! $confirming && $overPlan)
                            <div role="status" class="rounded-lg border border-warning-200 bg-warning-50 px-3.5 py-2.5 text-xs text-warning-800 space-y-0.5">
                                @foreach($overPlan as $label => $cellsOver)
                                    <p><strong>{{ $label }}: terealisasi melebihi rencana</strong> pada {{ implode(', ', $cellsOver) }}.</p>
                                @endforeach
                            </div>
                        @endif

                        @if($confirming)
                            <!-- Konfirmasi perubahan -->
                            <div class="space-y-4">
                                <div class="rounded-lg border border-warning-200 bg-warning-50 px-3.5 py-3 text-xs text-warning-800 space-y-2">
                                    <p class="font-bold">{{ $summary['changed'] }} sel berubah</p>
                                    <ul class="space-y-0.5">
                                        @foreach($summary['totals'] as $label => $total)
                                            <li class="font-mono">{{ $label }}: {{ $total['old'] }} → {{ $total['new'] }}</li>
                                        @endforeach
                                    </ul>
                                </div>

                                <div>
                                    <label for="supply-plan-reason" class="block text-xs font-semibold text-surface-700 mb-1.5">
                                        Alasan perubahan <span class="text-danger-700">*</span>
                                    </label>
                                    <textarea id="supply-plan-reason" wire:model="reason" rows="3" required aria-required="true"
                                        placeholder="Contoh: Penyesuaian hasil rapat perencanaan SDM"
                                        class="w-full px-3 py-2 bg-surface-50/70 border border-surface-200 text-surface-800 rounded-lg text-xs focus:outline-none focus:bg-white focus-visible:ring-2 focus-visible:ring-primary-500/30 focus-visible:border-primary-500"></textarea>
                                    <p class="text-[11px] text-surface-500 mt-1">Minimal 10 karakter. Alasan tercatat pada riwayat perubahan.</p>
                                </div>
                            </div>
                        @else
                            <!-- Grid rencana per sumber -->
                            @foreach($sources as $source => $label)
                                <section class="space-y-2">
                                    <div class="flex items-baseline justify-between gap-2">
                                        <h4 class="text-xs font-bold text-surface-800">{{ $label }}</h4>
                                        @unless(\App\Models\MppSupplyPlan::allowsCell($source, 'RM-1'))
                                            <span class="text-[11px] text-surface-500">Hanya formasi RM-3; RM-1 dan RM-2 diisi lewat promosi.</span>
                                        @endunless
                                    </div>
                                    <div class="overflow-x-auto rounded-lg border border-surface-200">
                                        <table class="w-full text-xs">
                                            <thead class="bg-surface-100 text-surface-600">
                                                <tr>
                                                    <th scope="col" class="px-2.5 py-2 text-left font-bold">RM Band</th>
                                                    @foreach($bidangList as $bidang)
                                                        <th scope="col" class="px-2 py-2 text-center font-bold">{{ $bidang }}</th>
                                                    @endforeach
                                                    <th scope="col" class="px-2 py-2 text-center font-bold">JUMLAH</th>
                                                </tr>
                                            </thead>
                                            <tbody class="divide-y divide-surface-200">
                                                @foreach($levels as $rm)
                                                    <tr>
                                                        <th scope="row" class="px-2.5 py-1.5 text-left font-bold text-surface-800">{{ $rm }}</th>
                                                        @foreach($bidangList as $bidang)
                                                            <td class="px-1.5 py-1.5">
                                                                @if(\App\Models\MppSupplyPlan::allowsCell($source, $rm))
                                                                    <input type="number" min="0" max="{{ $maxJumlah }}" step="1" inputmode="numeric"
                                                                        wire:model="cells.{{ $source }}.{{ $rm }}.{{ $bidang }}"
                                                                        aria-label="{{ $label }} {{ $rm }} {{ $bidang }}"
                                                                        class="w-full min-w-[3.5rem] px-2 py-1 text-center font-mono tabular-nums bg-white border border-surface-200 rounded-md focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/30 focus-visible:border-primary-500">
                                                                    @isset($realised[$source][$rm][$bidang])
                                                                        <p class="mt-0.5 text-center text-[10px] text-surface-500 whitespace-nowrap">terealisasi <span class="font-mono font-semibold text-surface-700" data-realised="{{ $source }}-{{ $rm }}-{{ $bidang }}">{{ $realised[$source][$rm][$bidang] }}</span></p>
                                                                    @endisset
                                                                @else
                                                                    <span class="block text-center font-mono text-surface-300" title="Hanya formasi RM-3">–</span>
                                                                @endif
                                                            </td>
                                                        @endforeach
                                                        <td class="px-2 py-1.5 text-center font-mono font-bold tabular-nums text-surface-900 bg-surface-50">
                                                            {{ collect($cells[$source][$rm] ?? [])->sum(fn ($v) => is_numeric($v) ? (int) $v : 0) }}
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                </section>
                            @endforeach
                        @endif
                    </div>

                    <!-- Footer -->
                    <div class="px-5 py-3.5 bg-surface-50 border-t border-surface-200 flex items-center justify-end gap-2">
                        @if($confirming)
                            <button type="button" wire:click="cancelReview"
                                class="px-4 py-1.5 text-xs font-semibold rounded-lg bg-surface-200 text-surface-700 hover:bg-surface-300 transition">
                                Kembali
                            </button>
                            <button type="button" wire:click="save" wire:loading.attr="disabled" wire:target="save"
                                class="inline-flex items-center gap-1.5 px-4 py-1.5 text-xs font-semibold rounded-lg bg-primary-700 text-white hover:bg-primary-800 disabled:opacity-60 transition">
                                <i class="fas fa-circle-notch fa-spin" wire:loading wire:target="save" aria-hidden="true"></i>
                                <span wire:loading.remove wire:target="save">Simpan Rencana</span>
                                <span wire:loading wire:target="save">Menyimpan…</span>
                            </button>
                        @else
                            <button type="button" wire:click="requestClose"
                                class="px-4 py-1.5 text-xs font-semibold rounded-lg bg-surface-200 text-surface-700 hover:bg-surface-300 transition">
                                Batal
                            </button>
                            <button type="button" wire:click="review" wire:loading.attr="disabled" wire:target="review"
                                class="inline-flex items-center gap-1.5 px-4 py-1.5 text-xs font-semibold rounded-lg bg-primary-700 text-white hover:bg-primary-800 disabled:opacity-60 transition">
                                <i class="fas fa-circle-notch fa-spin" wire:loading wire:target="review" aria-hidden="true"></i>
                                <span>Tinjau Perubahan</span>
                            </button>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
