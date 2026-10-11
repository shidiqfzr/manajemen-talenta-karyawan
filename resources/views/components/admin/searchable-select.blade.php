@props([
    'model',         // string: The livewire model to entangle
    'options',       // array/json: The list of options to display
    'placeholder' => '-- Pilih Opsi --',
    'icon' => null,  // e.g. 'fas fa-filter'
    'emptyText' => 'Opsi tidak ditemukan',
    'hasAllOption' => false,
    'allOptionText' => '-- Semua --'
])

<div x-data="{
        open: false,
        search: '',
        selectedValue: @entangle($model).live,
        options: {{ is_string($options) ? $options : json_encode($options) }},
        
        get normalizedOptions() {
            // Convert flat array to array of objects
            return this.options.map(opt => {
                if (typeof opt === 'object' && opt !== null && 'value' in opt) {
                    return opt;
                }
                return { value: opt, label: opt };
            });
        },
        
        get selectedLabel() {
            let opt = this.normalizedOptions.find(o => o.value == this.selectedValue);
            return opt ? opt.label : '{{ $placeholder }}';
        },
        
        get filteredOptions() {
            if (this.search === '') return this.normalizedOptions;
            let s = this.search.toLowerCase();
            return this.normalizedOptions.filter(i => String(i.label).toLowerCase().includes(s));
        },
        
        selectOption(val) {
            this.selectedValue = val;
            this.open = false;
            this.search = '';
        }
    }" class="relative w-full" @click.away="open = false">
    
    <div @click="open = !open; if(open) $nextTick(() => $refs.searchInput.focus())"
        class="w-full px-3.5 py-2.5 bg-white border border-surface-300 rounded-lg text-xs sm:text-sm font-medium flex items-center justify-between transition cursor-pointer hover:border-primary-400 focus-within:ring-2 focus-within:ring-primary-300 focus-within:border-primary-600"
        :class="selectedValue ? 'text-surface-800' : 'text-surface-400'">
        <div class="flex items-center gap-2">
            @if($icon)
            <i class="{{ $icon }} text-[10px]" :class="selectedValue ? 'text-primary-600' : 'text-surface-400'"></i>
            @endif
            <span x-text="selectedValue ? selectedLabel : '{{ $placeholder }}'"></span>
        </div>
        <div class="flex items-center gap-2">
            <button x-show="selectedValue" @click.stop="selectOption('')" type="button" class="text-surface-400 hover:text-danger p-1 rounded transition" title="Hapus Filter">
                <i class="fas fa-times text-[10px]"></i>
            </button>
            <i class="fas fa-chevron-down text-surface-400 text-[10px] transition-transform duration-200" :class="open ? 'rotate-180' : ''"></i>
        </div>
    </div>

    <div x-show="open" 
        x-transition:enter="transition ease-out duration-100"
        x-transition:enter-start="opacity-0 scale-95"
        x-transition:enter-end="opacity-100 scale-100"
        x-transition:leave="transition ease-in duration-75"
        x-transition:leave-start="opacity-100 scale-100"
        x-transition:leave-end="opacity-0 scale-95"
        x-cloak
        class="absolute z-50 mt-1 w-full bg-white rounded-lg border border-surface-200 shadow-xl overflow-hidden">
        
        <div class="p-2 border-b border-surface-100 bg-surface-50">
            <div class="relative">
                <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-surface-400 text-[10px]"></i>
                <input x-ref="searchInput" type="text" x-model="search" placeholder="Cari..." 
                    class="w-full pl-8 pr-3 py-1.5 bg-white border border-surface-200 rounded-md text-xs focus:outline-none focus:ring-1 focus:ring-primary-500 focus:border-primary-500">
            </div>
        </div>
        
        <ul class="overflow-y-auto py-1" style="max-height: 250px;">
            @if($hasAllOption)
            <li @click="selectOption('')" 
                class="px-3.5 py-2 text-xs font-medium cursor-pointer transition flex items-center justify-between hover:bg-surface-100"
                :class="selectedValue === '' ? 'bg-primary-50 text-primary-700' : 'text-surface-700'">
                <span>{{ $allOptionText }}</span>
                <i x-show="selectedValue === ''" class="fas fa-check text-primary-600 text-[10px]"></i>
            </li>
            @endif
            <template x-for="opt in filteredOptions" :key="opt.value">
                <li @click="selectOption(opt.value)" 
                    class="px-3.5 py-2 text-xs font-medium cursor-pointer transition flex items-center justify-between"
                    :class="selectedValue === opt.value ? 'bg-primary-50 text-primary-700' : 'text-surface-700 hover:bg-surface-100'">
                    <span x-text="opt.label"></span>
                    <i x-show="selectedValue === opt.value" class="fas fa-check text-primary-600 text-[10px]"></i>
                </li>
            </template>
            <li x-show="filteredOptions.length === 0" class="px-3.5 py-3 text-xs text-center text-surface-400 font-medium">
                {{ $emptyText }}
            </li>
        </ul>
    </div>
</div>
