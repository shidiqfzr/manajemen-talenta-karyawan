    <!-- ── 4. ACTION SUBMIT / REVIEW BAR ── -->
    <div class="pt-2 space-y-2 sticky bottom-4 z-30">
        @if ($errors->any())
            <div class="bg-danger-50 border border-danger-200 rounded-2xl p-4 shadow-sm flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 text-xs animate-fade-in"
                role="alert" x-data="{ show: true }" x-show="show">
                <div class="flex items-start gap-2.5">
                    <div class="w-7 h-7 rounded-lg bg-danger-100 text-danger-700 flex items-center justify-center font-bold text-xs shrink-0 mt-0.5">
                        <i class="fas fa-triangle-exclamation" aria-hidden="true"></i>
                    </div>
                    <div class="space-y-0.5 text-danger-800">
                        <span class="font-bold block">Pemberitahuan Kelengkapan Data:</span>
                        <p class="font-medium text-danger-800 leading-relaxed">
                            {{ $errors->first() }}
                        </p>
                    </div>
                </div>
                <button type="button" @click="show = false" class="text-danger hover:text-danger-700 p-1.5 cursor-pointer self-end sm:self-center" title="Tutup pemberitahuan">
                    <i class="fas fa-times text-xs" aria-hidden="true"></i>
                </button>
            </div>
        @endif

        <div data-blocked="{{ $this->blockingReason ? 'true' : 'false' }}"
            class="bg-white rounded-2xl border border-surface-200 shadow-xs p-3 sm:p-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 sm:gap-4">
            <div class="flex items-center gap-3 text-xs text-surface-600 min-w-0">
                <div class="w-8 h-8 rounded-lg bg-surface-100 flex items-center justify-center font-bold text-surface-700 shrink-0">
                    <i class="fas fa-user-check text-xs" aria-hidden="true"></i>
                </div>
                <div class="min-w-0">
                    @if ($employeeDetails && $form->targetJabatan)
                        {{-- Satu baris: nama lengkap tetap tersedia lewat title bila terpotong --}}
                        <p class="truncate" title="{{ $employeeDetails['nama'] }} → {{ $form->targetJabatan }} ({{ $form->targetUnitKerja }})">
                            <span class="font-bold text-surface-900">{{ $employeeDetails['nama'] }}</span> &rarr; <span class="font-semibold text-primary-800">{{ $form->targetJabatan }}</span> ({{ $form->targetUnitKerja }})
                        </p>
                    @else
                        <span class="font-bold text-surface-900">Formulir Mutasi Individual</span>
                    @endif
                    {{-- Ringkasan kendala (DESIGN.md §7.2): jumlah lebih dulu bila lebih dari satu, lalu rincian --}}
                    <div aria-live="polite" aria-atomic="true">
                        @php $issues = $this->outstandingIssues; @endphp
                        @if (count($issues) > 1)
                            <div @if ($this->blockingReason) id="blocking-reason" @endif>
                                <p class="text-xs text-danger-800 font-semibold">{{ count($issues) }} hal perlu diselesaikan:</p>
                                <ul class="text-xs text-danger-800 font-medium list-disc pl-4">
                                    @foreach ($issues as $issue)
                                        <li>{{ $issue }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @elseif (count($issues) === 1)
                            <p @if ($this->blockingReason) id="blocking-reason" @endif class="text-xs {{ $this->blockingReason ? 'text-danger-800' : 'text-warning-800' }} font-medium">{{ $issues[0] }}</p>
                        @else
                            <p class="text-xs text-surface-600">Seluruh perubahan disimpan sekaligus; bila gagal, tidak ada data yang berubah.</p>
                        @endif
                    </div>
                </div>
            </div>

            <div class="flex items-center gap-3">
                <x-admin.button href="{{ route('admin.mutasi.index') }}" variant="secondary" class="min-h-11">Batal</x-admin.button>

                {{-- Satu aksi utama per area: tautan Mutasi Berantai hanya ada di kartu kendala; di sini tombol tetap nonaktif beserta alasannya --}}
                <x-admin.button wire:click="openSummaryModal"
                    :disabled="(bool) $this->blockingReason"
                    :aria-describedby="$this->blockingReason ? 'blocking-reason' : null"
                    icon="fas fa-magnifying-glass-check"
                    target="openSummaryModal, submitMutasi"
                    class="min-h-11">Tinjau &amp; Eksekusi Mutasi</x-admin.button>
            </div>
        </div>
    </div>
