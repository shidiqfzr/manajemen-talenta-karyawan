{{--
    Kepala halaman admin. Slot opsional memuat aksi tambahan di sebelah tombol kembali:
    <x-admin.page-header title="..." icon="fas fa-file" back-url="..."> <x-admin.button size="sm" variant="secondary">Aksi</x-admin.button> </x-admin.page-header>
--}}
@props(['title', 'subtitle' => '', 'icon', 'backUrl' => null, 'backLabel' => 'Kembali', 'breadcrumbs' => []])

<div class="bg-white rounded-xl sm:rounded-2xl border border-surface-200/80 p-5 sm:p-6 shadow-xs flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
    <div class="flex items-center gap-4 min-w-0 flex-1">
        <div class="w-12 h-12 rounded-xl sm:rounded-2xl bg-gradient-to-br from-primary-600 to-teal-700 text-white flex items-center justify-center text-xl shadow-md shadow-primary-600/20 shrink-0">
            <i class="{{ $icon }}"></i>
        </div>
        <div class="min-w-0 flex-1">
            @if(count($breadcrumbs) > 0)
                <nav class="flex items-center gap-2 text-[11px] sm:text-xs font-medium text-surface-500 mb-1" aria-label="Breadcrumb">
                    @foreach($breadcrumbs as $index => $crumb)
                        @if(isset($crumb['url']))
                            <a href="{{ $crumb['url'] }}" class="text-surface-600 hover:text-primary-700 transition">{{ $crumb['label'] }}</a>
                        @else
                            <span class="{{ $loop->last ? 'text-primary-700 font-semibold' : '' }}">{{ $crumb['label'] }}</span>
                        @endif

                        @if(!$loop->last)
                            <i class="fas fa-chevron-right text-[10px] text-surface-400" aria-hidden="true"></i>
                        @endif
                    @endforeach
                </nav>
            @endif
            
            <h1 class="text-xl sm:text-2xl font-extrabold text-surface-900 tracking-tight">{{ $title }}</h1>
            
            @if($subtitle)
                <p class="text-surface-500 text-xs sm:text-sm mt-0.5 max-w-4xl lg:max-w-none">{{ $subtitle }}</p>
            @endif
        </div>
    </div>

    @if (! $slot->isEmpty() || ($backUrl && $backLabel))
        <div class="flex flex-wrap items-center gap-2.5 shrink-0">
            {{ $slot }}
            @if($backUrl && $backLabel)
                <x-admin.button href="{{ $backUrl }}" variant="secondary" icon="fas fa-arrow-left" class="whitespace-nowrap shrink-0">
                    {{ $backLabel }}
                </x-admin.button>
            @endif
        </div>
    @endif
</div>
