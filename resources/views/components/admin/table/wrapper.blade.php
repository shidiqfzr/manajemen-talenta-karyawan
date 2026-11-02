@props(['searchPlaceholder' => 'Cari...', 'models'])

<div class="space-y-4 relative">
    <!-- Global Loading Overlay for Livewire -->
    <div wire:loading.delay class="absolute inset-0 z-10 bg-white/50 backdrop-blur-[1px] rounded-lg flex items-center justify-center">
        <div class="flex items-center gap-2 px-4 py-2 bg-white rounded-full shadow-md text-primary-600 font-semibold text-xs border border-primary-100">
            <i class="fas fa-circle-notch fa-spin" aria-hidden="true"></i> Memuat data...
        </div>
    </div>

    <!-- Controls Header -->
    <div class="flex flex-col sm:flex-row justify-between items-center gap-3">
        <div class="flex items-center gap-2 w-full sm:w-auto">
            <label class="text-xs text-surface-500 font-medium">Tampilkan:</label>
            <select wire:model.live="perPage" class="px-2.5 py-1.5 text-xs border border-surface-200 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 bg-white text-surface-700 shadow-sm transition">
                <option value="5">5</option>
                <option value="10">10</option>
                <option value="25">25</option>
                <option value="50">50</option>
            </select>
        </div>

        <div class="w-full sm:w-72 relative">
            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-surface-500">
                <i class="fas fa-search text-xs" aria-hidden="true"></i>
            </div>
            <input type="text"
                   wire:model.live.debounce.300ms="search"
                   placeholder="{{ $searchPlaceholder }}"
                   class="w-full pl-9 pr-3 py-1.5 text-xs border border-surface-200 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 bg-white placeholder-surface-400 text-surface-800 shadow-sm transition">
            
            <div wire:loading wire:target="search" class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none text-primary-500">
                <i class="fas fa-circle-notch fa-spin text-[10px]" aria-hidden="true"></i>
            </div>
        </div>
    </div>

    <!-- Table Container -->
    <div class="overflow-x-auto rounded-xl border border-surface-200 shadow-sm">
        <table class="min-w-full divide-y divide-surface-200 text-xs">
            <thead class="bg-surface-50 text-surface-700 font-bold tracking-wide uppercase text-[10px]">
                <tr>
                    {{ $header }}
                </tr>
            </thead>
            <tbody class="divide-y divide-surface-100 bg-white transition-opacity duration-200" wire:loading.class="opacity-50">
                {{ $slot }}
            </tbody>
        </table>
    </div>

    <!-- Pagination Footer -->
    <div class="mt-4 flex items-center justify-between">
        <div class="hidden sm:block text-xs text-surface-500">
            Menampilkan <span class="font-semibold text-surface-700">{{ $models->firstItem() ?? 0 }}</span> - <span class="font-semibold text-surface-700">{{ $models->lastItem() ?? 0 }}</span> dari <span class="font-semibold text-surface-700">{{ $models->total() }}</span> data
        </div>
        <div>
            {{ $models->links() }}
        </div>
    </div>
</div>
