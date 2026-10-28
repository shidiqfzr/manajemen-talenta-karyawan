@props([
    'show' => false,
    'nomorSk' => '',
    'tmtAwal' => '',
    'nodes' => [],
    'clusters' => [],
])

@if ($show)
    <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-surface-900/60 backdrop-blur-xs animate-fade-in"
        x-data x-trap.noscroll="true" tabindex="-1"
        @keydown.escape.window="$wire.closeReviewModal()"
        role="dialog"
        aria-modal="true"
        aria-labelledby="modal-review-title">
        <div class="bg-white rounded-2xl border border-surface-200 shadow-2xl max-w-4xl w-full p-6 space-y-5 animate-scale-in max-h-[90vh] overflow-y-auto">
            <!-- Modal Header -->
            <div class="flex items-start justify-between pb-3 border-b border-surface-100">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-primary-50 text-primary-700 flex items-center justify-center font-bold text-base shadow-2xs">
                        <i class="fas fa-clipboard-check" aria-hidden="true"></i>
                    </div>
                    <div>
                        <h3 id="modal-review-title" class="text-base font-bold text-surface-900">Konfirmasi Eksekusi Mutasi Kolektif</h3>
                        <p class="text-xs text-surface-500">Nomor SK: <span class="font-mono font-bold text-surface-800">{{ $nomorSk }}</span> | TMT Efektif: <span class="font-bold text-surface-800">{{ !empty($tmtAwal) ? \Carbon\Carbon::parse($tmtAwal)->translatedFormat('d F Y') : '—' }}</span></p>
                    </div>
                </div>
                <button type="button" wire:click="closeReviewModal" class="text-surface-500 hover:text-surface-700 cursor-pointer p-1" title="Tutup jendela (Esc)">
                    <i class="fas fa-times text-sm" aria-hidden="true"></i>
                </button>
            </div>

            <!-- Grouped Clusters or Single Matrix Table -->
            @if (!empty($clusters) && count($clusters) > 0)
                <div class="space-y-4">
                    @foreach ($clusters as $cluster)
                        <div class="border border-surface-200 rounded-xl overflow-hidden bg-white shadow-2xs">
                            <!-- Cluster Title Strip -->
                            <div class="px-4 py-2.5 bg-surface-50/90 border-b border-surface-200 flex flex-wrap items-center justify-between gap-2">
                                <div class="flex items-center gap-2.5">
                                    <span class="w-6 h-6 rounded-lg {{ $cluster['type'] === 'cyclic' ? 'bg-primary-100 text-primary-800' : ($cluster['type'] === 'domino' ? 'bg-info-100 text-info-800' : 'bg-surface-100 text-surface-700') }} flex items-center justify-center font-bold text-[10px]">
                                        <i class="fas {{ $cluster['type'] === 'cyclic' ? 'fa-arrows-rotate' : ($cluster['type'] === 'domino' ? 'fa-forward-step' : 'fa-network-wired') }}" aria-hidden="true"></i>
                                    </span>
                                    <div>
                                        <span class="font-bold text-xs text-surface-900">{{ $cluster['title'] }}</span>
                                    </div>
                                </div>
                                <div class="flex items-center gap-2">
                                    @if (!empty($cluster['is_zero_vacancy']))
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-primary-50 text-primary-800 border border-primary-300">
                                            <i class="fas fa-check-circle text-primary-600 text-[10px]" aria-hidden="true"></i> Net Zero Vacancy
                                        </span>
                                    @endif
                                    <span class="px-2 py-0.5 rounded text-[10px] font-semibold bg-surface-200/80 text-surface-700">
                                        {{ count($cluster['nodes']) }} Pegawai
                                    </span>
                                </div>
                            </div>

                            <!-- Cluster Table -->
                            <div class="overflow-x-auto text-xs">
                                <table class="w-full border-collapse">
                                    <thead class="bg-surface-50/50 text-surface-600 font-semibold border-b border-surface-100 text-[11px]">
                                        <tr>
                                            <th scope="col" class="py-2 px-3 text-left w-12">Slot</th>
                                            <th scope="col" class="py-2 px-3 text-left">Nama Pegawai &amp; NIK</th>
                                            <th scope="col" class="py-2 px-3 text-left">Posisi Asal</th>
                                            <th scope="col" class="py-2 px-3 text-left">Penempatan Baru</th>
                                            <th scope="col" class="py-2 px-3 text-left">Status Alur</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-surface-100 text-surface-800">
                                        @foreach ($cluster['nodes'] as $n)
                                            <tr class="hover:bg-surface-50/50">
                                                <td class="py-2.5 px-3 font-bold text-surface-500">#{{ $n['slot_number'] ?? ($loop->iteration) }}</td>
                                                <td class="py-2.5 px-3">
                                                    <div class="font-bold text-surface-900">{{ $n['employee_nama'] }}</div>
                                                    <div class="text-[11px] font-mono text-surface-500">{{ $n['employee_nik'] }}</div>
                                                </td>
                                                <td class="py-2.5 px-3">
                                                    <div class="font-medium text-surface-800">{{ $n['current_jabatan'] }}</div>
                                                    <div class="text-[11px] text-surface-500">{{ $n['current_unit'] }}</div>
                                                </td>
                                                <td class="py-2.5 px-3">
                                                    <div class="font-bold text-primary-900">{{ $n['target_jabatan'] }}</div>
                                                    <div class="text-[11px] text-primary-700">{{ $n['target_unit_kerja'] }}</div>
                                                    <span class="inline-block mt-1 px-1.5 py-0.5 rounded text-[10px] font-semibold bg-primary-50 text-primary-700 border border-primary-200">
                                                        {{ str_replace('_', ' ', $n['jenis_mutasi']) }}
                                                    </span>
                                                </td>
                                                <td class="py-2.5 px-3">
                                                    @if ($n['link_type'] === 'chain')
                                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-semibold bg-primary-100 text-primary-800">
                                                            <i class="fas fa-arrows-rotate text-[10px]" aria-hidden="true"></i> Mengisi {{ !empty($n['occupant_nama']) ? explode(' ', $n['occupant_nama'])[0] : 'Pegawai' }}
                                                        </span>
                                                    @elseif ($n['link_type'] === 'vacant')
                                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-semibold bg-info-100 text-info-800">
                                                            <i class="fas fa-door-open text-[10px]" aria-hidden="true"></i> Tutup Lowongan
                                                        </span>
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>

                            <!-- Cluster Loopback Footer -->
                            @if (!empty($cluster['loopback']))
                                <div class="px-4 py-2 bg-primary-50/70 border-t border-primary-100 flex items-center gap-2 text-[11px] text-primary-900">
                                    <i class="fas fa-arrows-rotate text-primary-600 text-xs shrink-0" aria-hidden="true"></i>
                                    <span><strong>Siklus Tertutup:</strong> Formasi awal <strong>{{ $cluster['loopback']['to_nama'] }}</strong> ({{ $cluster['loopback']['to_jabatan'] }}) diisi kembali oleh <strong>{{ $cluster['loopback']['from_nama'] }}</strong>.</span>
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            @else
                <!-- Fallback Flat Matrix Table -->
                <div class="border border-surface-200 rounded-xl overflow-hidden text-xs">
                    <table class="w-full border-collapse">
                        <thead class="bg-surface-50 text-surface-700 font-bold border-b border-surface-200">
                            <tr>
                                <th scope="col" class="py-2.5 px-3 text-left">No</th>
                                <th scope="col" class="py-2.5 px-3 text-left">Nama Pegawai &amp; NIK</th>
                                <th scope="col" class="py-2.5 px-3 text-left">Posisi Asal</th>
                                <th scope="col" class="py-2.5 px-3 text-left">Penempatan Baru</th>
                                <th scope="col" class="py-2.5 px-3 text-left">Status Alur</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-surface-100 text-surface-800">
                            @foreach ($nodes as $i => $n)
                                <tr class="hover:bg-surface-50/50">
                                    <td class="py-2.5 px-3 font-bold text-surface-500">{{ $i + 1 }}</td>
                                    <td class="py-2.5 px-3">
                                        <div class="font-bold text-surface-900">{{ $n['employee_nama'] }}</div>
                                        <div class="text-[11px] font-mono text-surface-500">{{ $n['employee_nik'] }}</div>
                                    </td>
                                    <td class="py-2.5 px-3">
                                        <div class="font-medium">{{ $n['current_jabatan'] }}</div>
                                        <div class="text-[11px] text-surface-500">{{ $n['current_unit'] }}</div>
                                    </td>
                                    <td class="py-2.5 px-3">
                                        <div class="font-bold text-primary-900">{{ $n['target_jabatan'] }}</div>
                                        <div class="text-[11px] text-primary-700">{{ $n['target_unit_kerja'] }}</div>
                                        <span class="inline-block mt-1 px-1.5 py-0.5 rounded text-[10px] font-semibold bg-primary-50 text-primary-700 border border-primary-200">
                                            {{ str_replace('_', ' ', $n['jenis_mutasi']) }}
                                        </span>
                                    </td>
                                    <td class="py-2.5 px-3">
                                        @if ($n['link_type'] === 'chain')
                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-semibold bg-primary-100 text-primary-800">
                                                <i class="fas fa-arrows-rotate text-[10px]" aria-hidden="true"></i> Mengisi {{ !empty($n['occupant_nama']) ? explode(' ', $n['occupant_nama'])[0] : 'Pegawai' }}
                                            </span>
                                        @elseif ($n['link_type'] === 'vacant')
                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-semibold bg-info-100 text-info-800">
                                                <i class="fas fa-door-open text-[10px]" aria-hidden="true"></i> Tutup Lowongan
                                            </span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            <!-- Policy & Transaction Notice -->
            <div class="p-3 bg-surface-50 rounded-xl border border-surface-200 text-xs text-surface-600 flex items-center gap-2.5">
                <i class="fas fa-info-circle text-primary-600 text-sm shrink-0" aria-hidden="true"></i>
                <span>Setelah dikonfirmasi, seluruh data kepegawaian dan riwayat penugasan disahkan sekaligus.</span>
            </div>

            <!-- Modal Action Buttons -->
            <div class="flex items-center justify-end gap-3 pt-2">
                <button type="button" wire:click="closeReviewModal"
                    wire:loading.attr="disabled"
                    wire:target="submitChain"
                    class="px-4 py-2 rounded-xl border border-surface-200 text-xs font-semibold text-surface-700 hover:bg-surface-50 transition cursor-pointer disabled:opacity-50">
                    Periksa Kembali
                </button>

                <button type="button" wire:click="submitChain"
                    wire:loading.attr="disabled"
                    wire:target="submitChain"
                    class="inline-flex items-center gap-2 px-5 py-2 rounded-xl bg-primary-600 hover:bg-primary-700 text-white text-xs font-bold transition shadow-sm cursor-pointer disabled:opacity-60 disabled:cursor-not-allowed">
                    <i wire:loading.remove wire:target="submitChain" class="fas fa-check text-xs" aria-hidden="true"></i>
                    <i wire:loading wire:target="submitChain" class="fas fa-spinner fa-spin text-xs" aria-hidden="true"></i>
                    <span wire:loading.remove wire:target="submitChain">Konfirmasi &amp; Terbitkan SK Mutasi</span>
                    <span wire:loading wire:target="submitChain">Memproses Transaksi Rantai...</span>
                </button>
            </div>
        </div>
    </div>
@endif
