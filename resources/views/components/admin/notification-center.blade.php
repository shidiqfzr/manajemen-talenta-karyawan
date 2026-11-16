{{--
    Pusat Pemberitahuan pada topbar (DESIGN.md §14). Butir dihitung dari data oleh NotificationCenterService,
    bukan peristiwa tersimpan: tidak ada angka tetap dan tidak ada status "dibaca".

    <x-admin.notification-center :items="$items" />
--}}
@props(['items' => []])

@php
    $total = count($items);
    $badge = $total > 99 ? '99+' : $total;
@endphp

<div class="relative" x-data="{ open: false }" @keydown.escape.window="if (open) { open = false; $refs.trigger.focus() }" @click.outside="open = false">
    <button type="button" x-ref="trigger" @click="open = !open"
        class="relative p-2 rounded-lg text-surface-500 hover:text-surface-900 hover:bg-surface-100 transition border border-surface-200 cursor-pointer focus-visible:ring-2 focus-visible:ring-primary-500"
        aria-haspopup="true" :aria-expanded="open.toString()" aria-controls="notification-panel"
        aria-label="Pemberitahuan: {{ $total > 0 ? $total.' butir perlu tindak lanjut' : 'tidak ada yang perlu ditindaklanjuti' }}">
        <i class="fas fa-bell text-sm" aria-hidden="true"></i>
        @if ($total > 0)
            <span class="absolute -top-1.5 -right-1.5 min-w-4 h-4 px-1 rounded-full bg-danger text-white text-[10px] font-bold flex items-center justify-center" aria-hidden="true">{{ $badge }}</span>
        @endif
    </button>

    <div id="notification-panel" x-show="open" x-cloak
        x-transition:enter="transition ease-out duration-150 transform"
        x-transition:enter-start="opacity-0 scale-95"
        x-transition:enter-end="opacity-100 scale-100"
        class="absolute right-0 mt-2 w-80 sm:w-96 bg-white rounded-xl shadow-xl border border-surface-200 py-2 z-50">
        <div class="px-4 py-2 border-b border-surface-100 flex items-center justify-between">
            <h2 class="text-sm font-bold text-surface-900">Perlu Tindak Lanjut</h2>
            @if ($total > 0)
                <span class="px-1.5 py-0.5 text-[11px] font-bold bg-primary-100 text-primary-800 rounded-full">{{ $total }} butir</span>
            @endif
        </div>

        @forelse ($items as $item)
            <a wire:navigate href="{{ $item['url'] }}"
                class="flex items-start gap-3 p-3 hover:bg-primary-50 focus-visible:bg-primary-50 focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-primary-500 transition border-b border-surface-100 last:border-b-0">
                <div class="w-8 h-8 rounded-lg bg-primary-100 text-primary-700 flex items-center justify-center shrink-0 mt-0.5">
                    <i class="{{ $item['icon'] }} text-xs" aria-hidden="true"></i>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-semibold text-surface-900">{{ $item['title'] }}</p>
                    <p class="text-xs text-surface-600 mt-0.5">{{ $item['detail'] }}</p>
                </div>
                <i class="fas fa-chevron-right text-[10px] text-surface-400 mt-2" aria-hidden="true"></i>
            </a>
        @empty
            <div class="px-4 py-8 text-center">
                <i class="fas fa-circle-check text-primary-600 text-lg" aria-hidden="true"></i>
                <p class="mt-2 text-sm font-semibold text-surface-900">Semua beres</p>
                <p class="text-xs text-surface-600">Tidak ada pekerjaan yang menunggu tindak lanjut.</p>
            </div>
        @endforelse
    </div>
</div>
