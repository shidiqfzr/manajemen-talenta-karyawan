@props([
    'show' => false,
    'nomorSk' => '',
    'tmtAwal' => '',
    'nodes' => [],
    'clusters' => [],
    'mode' => 'execute',
])

@php
    // The confirm action follows the screen's review mode: issue the SK, submit for approval, or approve
    $confirm = match ($mode) {
        'submit' => ['action' => 'submitForApproval', 'title' => 'Konfirmasi Pengajuan Usulan Mutasi Kolektif', 'label' => 'Ajukan untuk Persetujuan', 'busy' => 'Mengajukan Usulan...', 'icon' => 'fa-paper-plane',
            'notice' => 'Usulan dikunci setelah diajukan. SK diterbitkan hanya setelah admin lain menyetujuinya; Anda dapat menarik usulan selama belum diputuskan.'],
        'approve' => ['action' => 'approveProposal', 'title' => 'Konfirmasi Persetujuan Usulan Mutasi Kolektif', 'label' => 'Setujui & Terbitkan SK Mutasi', 'busy' => 'Memproses Transaksi Rantai...', 'icon' => 'fa-check',
            'notice' => 'Data karyawan telah diperiksa ulang terhadap kondisi terkini. Setelah disetujui, seluruh data kepegawaian dan riwayat penugasan disahkan sekaligus.'],
        default => ['action' => 'submitChain', 'title' => 'Konfirmasi Eksekusi Mutasi Kolektif', 'label' => 'Konfirmasi & Terbitkan SK Mutasi', 'busy' => 'Memproses Transaksi Rantai...', 'icon' => 'fa-check',
            'notice' => 'Setelah dikonfirmasi, seluruh data kepegawaian dan riwayat penugasan disahkan sekaligus.'],
    };
@endphp

{{-- Tinjauan mutasi kolektif sebelum eksekusi: cangkang x-admin.modal (DESIGN.md §7.6) --}}
@if ($show)
    <x-admin.modal open="true" close="$wire.closeReviewModal()" size="3xl" icon="fas fa-clipboard-check" title-id="modal-review-title" cancel="Periksa Kembali" scroll :title="$confirm['title']">
        <x-slot:subtitle>Nomor SK: <span class="font-mono font-bold text-surface-800">{{ $nomorSk }}</span> | TMT Efektif: <span class="font-bold text-surface-800">{{ !empty($tmtAwal) ? \Carbon\Carbon::parse($tmtAwal)->translatedFormat('d F Y') : '—' }}</span></x-slot:subtitle>
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
                                        <i class="fas fa-check-circle text-primary-600 text-[10px]" aria-hidden="true"></i> Tanpa Lowongan Baru
                                    </span>
                                @endif
                                <span class="px-2 py-0.5 rounded text-[10px] font-semibold bg-surface-200/80 text-surface-700">
                                    {{ count($cluster['nodes']) }} Karyawan
                                </span>
                            </div>
                        </div>

                        <!-- Cluster Table -->
                        <div class="overflow-x-auto text-xs">
                            <table class="w-full border-collapse">
                                <thead class="bg-surface-50 border-b border-surface-200">
                                    <tr>
                                        <x-admin.table.th class="py-2 px-3 w-12">Slot</x-admin.table.th>
                                        <x-admin.table.th class="py-2 px-3">Nama Karyawan &amp; NIK</x-admin.table.th>
                                        <x-admin.table.th class="py-2 px-3">Posisi Asal</x-admin.table.th>
                                        <x-admin.table.th class="py-2 px-3">Penempatan Baru</x-admin.table.th>
                                        <x-admin.table.th class="py-2 px-3">Status Alur</x-admin.table.th>
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
                                                        <i class="fas fa-arrows-rotate text-[10px]" aria-hidden="true"></i> Mengisi {{ !empty($n['occupant_nama']) ? explode(' ', $n['occupant_nama'])[0] : 'Karyawan' }}
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
                    <thead class="bg-surface-50 border-b border-surface-200">
                        <tr>
                            <x-admin.table.th class="py-2.5 px-3">No</x-admin.table.th>
                            <x-admin.table.th class="py-2.5 px-3">Nama Karyawan &amp; NIK</x-admin.table.th>
                            <x-admin.table.th class="py-2.5 px-3">Posisi Asal</x-admin.table.th>
                            <x-admin.table.th class="py-2.5 px-3">Penempatan Baru</x-admin.table.th>
                            <x-admin.table.th class="py-2.5 px-3">Status Alur</x-admin.table.th>
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
                                            <i class="fas fa-arrows-rotate text-[10px]" aria-hidden="true"></i> Mengisi {{ !empty($n['occupant_nama']) ? explode(' ', $n['occupant_nama'])[0] : 'Karyawan' }}
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
            <span>{{ $confirm['notice'] }}</span>
        </div>
        <x-slot:footer>
            <x-admin.button :icon="'fas '.$confirm['icon']" wire:click="{{ $confirm['action'] }}" target="{{ $confirm['action'] }}">{{ $confirm['label'] }}</x-admin.button>
        </x-slot:footer>
    </x-admin.modal>
@endif
