{{--
    Footer tabel Livewire (DESIGN.md §7.5): pilihan jumlah baris per halaman, rentang yang tampil, dan penomoran halaman.
    Komponen Livewire harus memakai WithPagination dan menyediakan properti publik $perPage.
    Id pilihan baris dan tombol halaman mengikuti nama halaman paginator, sehingga beberapa tabel
    dengan nama halaman berbeda dapat tampil di satu halaman.

    <x-admin.table.pagination :paginator="$this->pagedSeats" noun="jabatan" :per-page-options="[10, 25, 50, 100]" />
--}}
@props(['paginator', 'noun' => 'data', 'perPageOptions' => [10, 25, 50, 100]])

@php
    $current = $paginator->currentPage();
    $last = $paginator->lastPage();
    $window = collect([1, $last, $current - 1, $current, $current + 1])
        ->filter(fn ($page) => $page >= 1 && $page <= $last)
        ->unique()
        ->sort()
        ->values();
    $pageName = $paginator->getPageName();
    $perPageId = 'per-page-'.$pageName;
    $buttonBase = 'inline-flex items-center justify-center min-w-8 h-8 px-2 rounded-lg border text-xs font-semibold transition focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500';
@endphp

<div class="px-5 py-3.5 border-t border-surface-200 bg-surface-50 flex flex-col lg:flex-row lg:items-center justify-between gap-3 text-xs text-surface-500">
    <div class="flex flex-wrap items-center gap-x-4 gap-y-2">
        <div class="flex items-center gap-2">
            <label for="{{ $perPageId }}" class="whitespace-nowrap">Tampilkan</label>
            <select id="{{ $perPageId }}" wire:model.live="perPage" class="py-1.5 pl-2.5 pr-7 bg-white border border-surface-300 rounded-lg text-xs text-surface-800 focus:outline-none focus:ring-2 focus:ring-primary-100 focus:border-primary-600 cursor-pointer shadow-2xs">
                @foreach ($perPageOptions as $option)
                    <option value="{{ $option }}">{{ $option }}</option>
                @endforeach
            </select>
            <span class="whitespace-nowrap">baris per halaman</span>
        </div>
        <p aria-live="polite">
            Menampilkan <span class="font-bold text-surface-800">{{ $paginator->firstItem() ?? 0 }}</span>–<span class="font-bold text-surface-800">{{ $paginator->lastItem() ?? 0 }}</span>
            dari <span class="font-bold text-surface-800">{{ number_format($paginator->total()) }}</span> {{ $noun }}
        </p>
    </div>

    @if ($paginator->hasPages())
        <nav aria-label="Halaman {{ $noun }}" class="flex items-center gap-1">
            <button type="button" wire:click="previousPage('{{ $pageName }}')" @disabled($paginator->onFirstPage()) aria-label="Halaman sebelumnya"
                class="{{ $buttonBase }} bg-white border-surface-300 text-surface-700 hover:bg-surface-100 disabled:opacity-40 disabled:cursor-not-allowed cursor-pointer">
                <i class="fas fa-chevron-left text-[10px]" aria-hidden="true"></i>
            </button>
            @foreach ($window as $index => $page)
                @if ($index > 0 && $page - $window[$index - 1] > 1)
                    <span class="px-1 text-surface-400" aria-hidden="true">…</span>
                @endif
                <button type="button" wire:click="gotoPage({{ $page }}, '{{ $pageName }}')" wire:key="page-{{ $page }}"
                    @if ($page === $current) aria-current="page" @endif aria-label="Halaman {{ $page }}"
                    class="{{ $buttonBase }} cursor-pointer {{ $page === $current ? 'bg-primary-600 border-primary-600 text-white' : 'bg-white border-surface-300 text-surface-700 hover:bg-surface-100' }}">{{ $page }}</button>
            @endforeach
            <button type="button" wire:click="nextPage('{{ $pageName }}')" @disabled(! $paginator->hasMorePages()) aria-label="Halaman berikutnya"
                class="{{ $buttonBase }} bg-white border-surface-300 text-surface-700 hover:bg-surface-100 disabled:opacity-40 disabled:cursor-not-allowed cursor-pointer">
                <i class="fas fa-chevron-right text-[10px]" aria-hidden="true"></i>
            </button>
        </nav>
    @endif
</div>
