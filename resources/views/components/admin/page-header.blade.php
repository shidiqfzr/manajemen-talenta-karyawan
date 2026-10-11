@props(['title', 'subtitle' => '', 'icon', 'backUrl' => null, 'backLabel' => 'Kembali', 'breadcrumbs' => []])

<div class="bg-white rounded-xl sm:rounded-2xl border border-slate-200/80 p-5 sm:p-6 shadow-xs flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
    <div class="flex items-center gap-4">
        <div class="w-12 h-12 rounded-xl sm:rounded-2xl bg-gradient-to-br from-emerald-600 to-teal-700 text-white flex items-center justify-center text-xl shadow-md shadow-emerald-600/20 shrink-0">
            <i class="{{ $icon }}"></i>
        </div>
        <div>
            @if(count($breadcrumbs) > 0)
                <nav class="flex items-center gap-2 text-[11px] sm:text-xs font-medium text-slate-500 mb-1" aria-label="Breadcrumb">
                    @foreach($breadcrumbs as $index => $crumb)
                        @if(isset($crumb['url']))
                            <a href="{{ $crumb['url'] }}" class="text-slate-600 hover:text-emerald-700 transition">{{ $crumb['label'] }}</a>
                        @else
                            <span class="{{ $loop->last ? 'text-emerald-700 font-semibold' : '' }}">{{ $crumb['label'] }}</span>
                        @endif

                        @if(!$loop->last)
                            <i class="fas fa-chevron-right text-[10px] text-slate-400"></i>
                        @endif
                    @endforeach
                </nav>
            @endif
            
            <h1 class="text-xl sm:text-2xl font-extrabold text-slate-900 tracking-tight">{{ $title }}</h1>
            
            @if($subtitle)
                <p class="text-slate-500 text-xs sm:text-sm mt-0.5 max-w-2xl">{{ $subtitle }}</p>
            @endif
        </div>
    </div>

    @if($backUrl && $backLabel)
        <x-admin.button href="{{ $backUrl }}" variant="secondary" icon="fas fa-arrow-left" class="whitespace-nowrap shrink-0">
            {{ $backLabel }}
        </x-admin.button>
    @endif
</div>
