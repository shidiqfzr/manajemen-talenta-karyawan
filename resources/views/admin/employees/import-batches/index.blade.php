@extends('layouts.admin')

@section('content')
<div class="space-y-6">
    <x-admin.module-header
        title="Riwayat & Persetujuan Impor"
        subtitle="Pengajuan, persetujuan, dan pembatalan impor data pegawai. Setiap keputusan tercatat beserta pelaku dan alasannya."
        :breadcrumbs="[
            'Core HR',
            ['label' => 'Direktori Pegawai', 'url' => route('admin.employees.index')],
            ['label' => 'Impor Data Spreadsheet', 'url' => route('admin.employees.import.page')],
            'Riwayat & Persetujuan',
        ]">
        <x-admin.button href="{{ route('admin.employees.import.page') }}" variant="primary" icon="fas fa-file-import">Impor Data Pegawai</x-admin.button>
    </x-admin.module-header>

    <x-admin.flash />

    <div class="flex items-start gap-3 rounded-xl border px-4 py-3 text-sm leading-relaxed {{ $requiresApproval ? 'border-primary-200 bg-primary-50 text-primary-900' : 'border-surface-200 bg-surface-50 text-surface-700' }}" role="status">
        <i class="fas {{ $requiresApproval ? 'fa-user-shield' : 'fa-circle-info' }} mt-1" aria-hidden="true"></i>
        <p>
            @if ($requiresApproval)
                <span class="font-semibold">Persetujuan dua pihak (empat mata) aktif.</span> Impor harus diajukan lalu disetujui admin lain sebelum data pegawai berubah.
                Pengajuan yang tidak diputuskan dalam {{ $approvalTtlDays }} hari kedaluwarsa otomatis.
            @else
                <span class="font-semibold">Persetujuan dua pihak (empat mata) belum diaktifkan.</span> Impor langsung disimpan setelah pratinjau dikonfirmasi.
            @endif
            Impor yang sudah tersimpan dapat dibatalkan dalam {{ $undoWindowDays }} hari, selama pegawai terkait belum berubah.
        </p>
    </div>

    <div class="bg-white rounded-2xl border border-surface-200/80 shadow-xs overflow-hidden">
        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-3 px-4 pt-3 border-b border-surface-200">
            <nav class="flex flex-wrap items-center gap-1" aria-label="Filter status impor">
                @foreach ($tabs as $key => $label)
                    <a href="{{ route('admin.employees.import.batches.index', array_filter(['status' => $key === 'all' ? null : $key, 'q' => $search !== '' ? $search : null])) }}"
                        @if ($tab === $key) aria-current="page" @endif
                        @if ($key === 'pending' && ! $requiresApproval) title="Persetujuan dua pihak belum diaktifkan, sehingga impor langsung tersimpan." @endif
                        class="px-4 py-2.5 text-sm font-semibold border-b-2 -mb-px rounded-t transition focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 {{ $tab === $key ? 'border-primary-600 text-primary-800' : 'border-transparent text-surface-600 hover:text-surface-900 hover:border-surface-300' }}">
                        {{ $label }}
                        <span class="ml-1 font-mono tabular-nums text-xs {{ $key === 'pending' && $counts[$key] > 0 ? 'text-warning-800' : 'text-surface-500' }}">{{ $counts[$key] }}</span>
                    </a>
                @endforeach
            </nav>

            <form method="GET" action="{{ route('admin.employees.import.batches.index') }}" role="search" class="flex items-center gap-2 pb-3">
                @if ($tab !== 'all')
                    <input type="hidden" name="status" value="{{ $tab }}">
                @endif
                <label for="batch-search" class="sr-only">Cari nama berkas atau pengaju</label>
                <div class="relative">
                    <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-xs text-surface-500 pointer-events-none" aria-hidden="true"></i>
                    <input id="batch-search" type="search" name="q" value="{{ $search }}" placeholder="Cari berkas atau pengaju..."
                        class="w-full sm:w-64 pl-8 pr-3 py-2 text-sm border border-surface-200 rounded-lg bg-white text-surface-800 placeholder-surface-500 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                </div>
                <x-admin.button type="submit" variant="secondary" size="sm">Cari</x-admin.button>
            </form>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full text-left text-sm">
                <thead class="sticky top-0 z-10 bg-surface-50 border-b border-surface-200 text-xs font-semibold text-surface-600">
                    <tr>
                        <th scope="col" class="px-4 py-3">Berkas</th>
                        <th scope="col" class="px-4 py-3">Pengaju</th>
                        <th scope="col" class="px-4 py-3">Hasil</th>
                        <th scope="col" class="px-4 py-3">Status</th>
                        <th scope="col" class="px-4 py-3 w-16 text-right"><span class="sr-only">Aksi</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-surface-100 bg-white">
                    @forelse ($logs as $log)
                        <tr class="hover:bg-surface-50/70 transition-colors">
                            <td class="px-4 py-3 max-w-[280px]">
                                <a href="{{ route('admin.employees.import.batches.show', $log) }}" class="block font-semibold text-surface-900 truncate hover:text-primary-700 hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 rounded" title="{{ $log->file_name }}">{{ $log->file_name }}</a>
                                <span class="block text-xs text-surface-500"><span class="font-mono tabular-nums">{{ number_format($log->total_rows, 0, ',', '.') }}</span> baris &bull; Impor #{{ $log->id }}</span>
                            </td>
                            <td class="px-4 py-3">
                                <span class="block text-surface-900">{{ $log->user_name ?? '-' }}</span>
                                <span class="block text-xs text-surface-500">{{ ($log->submitted_at ?? $log->created_at)->format('d/m/Y H:i') }}</span>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex flex-wrap gap-x-3 gap-y-0.5 text-xs text-surface-600">
                                    <span><span class="font-mono tabular-nums font-semibold text-surface-900">{{ $log->created_count }}</span> baru</span>
                                    <span><span class="font-mono tabular-nums font-semibold {{ $log->updated_count > 0 ? 'text-surface-900' : 'text-surface-500' }}">{{ $log->updated_count }}</span> diperbarui</span>
                                    <span class="{{ $log->skipped_count > 0 ? 'text-danger-700 font-semibold' : '' }}"><span class="font-mono tabular-nums">{{ $log->skipped_count }}</span> dilewati</span>
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                <x-admin.import-status :status="$log->status" :skipped="$log->skipped_count" />
                                @if ($log->checker_name && in_array($log->status, ['completed', 'rejected', 'approved']))
                                    <span class="block mt-1 text-xs text-surface-500">{{ $log->status === 'rejected' ? 'Ditolak' : 'Disetujui' }} {{ $log->checker_name }}</span>
                                @elseif ($log->status === 'rolled_back' && $log->rolled_back_by_name)
                                    <span class="block mt-1 text-xs text-surface-500">Oleh {{ $log->rolled_back_by_name }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right">
                                <x-admin.row-menu :label="'impor '.$log->file_name">
                                    <x-admin.master.menu-item :href="route('admin.employees.import.batches.show', $log)" :icon="$log->status === 'pending_approval' ? 'fas fa-clipboard-check' : 'fas fa-eye'">
                                        {{ $log->status === 'pending_approval' ? 'Tinjau Pengajuan' : 'Lihat Detail' }}
                                    </x-admin.master.menu-item>
                                </x-admin.row-menu>
                            </td>
                        </tr>
                    @empty
                        <x-admin.table.empty-state
                            :colspan="5"
                            icon="fas fa-clipboard-list"
                            :message="($tab !== 'all' || $search !== '') ? 'Tidak ada impor yang cocok dengan filter ini. Ubah filter atau kata kunci pencarian.' : 'Belum ada riwayat impor. Mulai dari Impor Data Pegawai.'" />
                    @endforelse
                </tbody>
            </table>
        </div>

        <x-pagination-ui :data="$logs" />
    </div>
</div>
@endsection
