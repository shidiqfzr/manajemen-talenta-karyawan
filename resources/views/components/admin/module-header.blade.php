{{--
    Kepala halaman modul (DESIGN.md §6.1, §8.5): breadcrumb, judul, subjudul, dan slot aksi utama.
    Dipakai bersama oleh modul Karyawan, Mutasi, dan Master Formasi agar tampilannya identik.
    Item breadcrumb berupa teks atau ['label' => ..., 'url' => ...]; item terakhir adalah halaman aktif.

    <x-admin.module-header title="Manajemen Karyawan" subtitle="..." :breadcrumbs="['Core HR', 'Manajemen Karyawan']">
        <x-admin.button ...>Tambah</x-admin.button>
    </x-admin.module-header>
--}}
@props(['title', 'subtitle' => '', 'breadcrumbs' => ['Core HR']])

<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
    <div class="min-w-0">
        <nav class="flex flex-wrap items-center gap-2 text-xs font-medium text-surface-500 mb-1.5" aria-label="Breadcrumb">
            @foreach ($breadcrumbs as $crumb)
                @if ($loop->last)
                    <span class="text-primary-700 font-semibold" aria-current="page">{{ is_array($crumb) ? $crumb['label'] : $crumb }}</span>
                @else
                    @if (is_array($crumb))
                        <a href="{{ $crumb['url'] }}" wire:navigate class="hover:text-primary-700 hover:underline transition">{{ $crumb['label'] }}</a>
                    @else
                        <span>{{ $crumb }}</span>
                    @endif
                    <i class="fas fa-chevron-right text-[10px] text-surface-400" aria-hidden="true"></i>
                @endif
            @endforeach
        </nav>
        <h1 class="text-2xl lg:text-3xl font-bold tracking-tight text-surface-900">{{ $title }}</h1>
        @if ($subtitle)
            <p class="text-sm text-surface-500 mt-0.5">{{ $subtitle }}</p>
        @endif
    </div>
    @if (! $slot->isEmpty())
        <div class="flex items-center gap-2.5 shrink-0">{{ $slot }}</div>
    @endif
</div>
