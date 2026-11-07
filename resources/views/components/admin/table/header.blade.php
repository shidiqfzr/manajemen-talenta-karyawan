{{--
    Bilah judul tabel data Livewire (DESIGN.md §7.5), mengikuti Direktori Pegawai: judul opsional, lencana jumlah data,
    rentang yang tampil, dan tombol Ekspor Excel di kanan (posisi sama di semua modul). Dipasang tepat di atas <table>,
    di bawah x-admin.table.toolbar, di dalam x-admin.table.card.

    <x-admin.table.header title="Riwayat Mutasi" :paginator="$rows" noun="Riwayat" :export="$this->exportName() !== null" />

    title     : judul tabel (opsional; tabel sematan di profil pegawai tidak memakainya).
    paginator : hasil paginasi saat ini ($rows); jumlah dan rentang diambil darinya.
    noun      : sebutan data berhuruf kapital awal (mis. Catatan, Riwayat) untuk lencana "339 Riwayat".
    export    : true menampilkan Ekspor Excel dengan lencana jumlah; tombolnya memanggil export() milik komponen.
    actions   : slot opsional untuk tombol tambahan milik modul di sisi kanan (sebelum Ekspor Excel).
    Jumlah dan rentang berada dalam satu wilayah live permanen (role="status"), sehingga pembaca layar mendengar
    jumlah baru setiap pencarian atau filter berubah. Modul tidak boleh menambah wilayah pengumuman jumlah sendiri.
--}}
@props(['title' => null, 'paginator', 'noun' => 'Data', 'export' => false])

@php
    $format = fn (int $number) => number_format($number, 0, ',', '.');
    $total = (int) $paginator->total();
@endphp

<div class="px-5 py-3 border-b border-surface-200 bg-white flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
    <div class="flex flex-wrap items-center gap-2.5">
        @if (filled($title))
            <span class="text-sm font-bold text-surface-900">{{ $title }}</span>
        @endif
        <p role="status" aria-live="polite" aria-atomic="true" class="flex flex-wrap items-center gap-2.5">
            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-surface-100 text-surface-700">{{ $format($total) }} {{ $noun }}</span>
            @if ($paginator->count() > 0)
                <span class="hidden md:inline text-xs text-surface-500" aria-hidden="true">|</span>
                <span class="hidden md:inline text-xs text-surface-500 tabular-nums">Menampilkan <strong class="text-surface-800">{{ $format((int) $paginator->firstItem()) }}</strong> – <strong class="text-surface-800">{{ $format((int) $paginator->lastItem()) }}</strong> dari <strong class="text-surface-800">{{ $format($total) }}</strong></span>
            @endif
        </p>
    </div>

    @if (isset($actions) || $export)
        <div class="flex items-center gap-2">
            {{ $actions ?? '' }}
            @if ($export)
            <x-admin.button variant="secondary" size="sm" icon="fas fa-file-excel" wire:click="export" target="export">Ekspor Excel <span class="ml-1.5 px-1.5 py-0.5 rounded-md bg-surface-100 text-surface-600 font-mono text-[10px]" aria-hidden="true">{{ $format($total) }}</span><span class="sr-only">untuk {{ $format($total) }} data</span></x-admin.button>
            @endif
        </div>
    @endif
</div>
