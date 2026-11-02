@props([
    'name' => null,          // HTML input name for standard GET/POST forms
    'id' => null,            // HTML input ID
    'model' => null,         // Livewire property model for @entangle
    'options' => [],         // Array, collection, or json of options
    'selected' => null,      // Initial selected value (standard forms)
    'value' => null,         // Alias for selected
    'placeholder' => '-- Pilih Opsi --',
    'searchPlaceholder' => 'Cari opsi...',
    'icon' => null,          // FontAwesome icon class e.g. 'fas fa-briefcase'
    'emptyText' => 'Opsi tidak ditemukan',
    'hasAllOption' => false,
    'allOptionText' => '-- Semua Opsi --',
    'maxHeight' => 'max-h-60',
    'containerClass' => '',
])

@php
    $resolvedSelected = $selected ?? $value ?? ($name ? request($name) : null) ?? '';

    // Normalize options into uniform array: [['value' => ..., 'label' => ...]]
    $normalized = [];
    if (is_iterable($options)) {
        foreach ($options as $key => $val) {
            if (is_array($val) && isset($val['value'])) {
                $normalized[] = [
                    'value' => (string) $val['value'],
                    'label' => (string) ($val['label'] ?? $val['value']),
                ];
            } elseif (is_object($val) && isset($val->value)) {
                $normalized[] = [
                    'value' => (string) $val->value,
                    'label' => (string) ($val->label ?? $val->value),
                ];
            } elseif (is_string($key) && !is_numeric($key)) {
                $normalized[] = [
                    'value' => (string) $key,
                    'label' => (string) $val,
                ];
            } else {
                $normalized[] = [
                    'value' => (string) $val,
                    'label' => (string) $val,
                ];
            }
        }
    }
@endphp

<div x-data="{
        open: false,
        search: '',
        activeIndex: -1,
        @if($model)
        selectedValue: @entangle($model).live,
        @else
        selectedValue: @js((string) $resolvedSelected),
        @endif
        optionsList: @js($normalized),

        get selectedLabel() {
            if (!this.selectedValue || this.selectedValue === '') {
                return '{{ $placeholder }}';
            }
            let opt = this.optionsList.find(o => String(o.value) === String(this.selectedValue));
            return opt ? opt.label : this.selectedValue;
        },

        get filteredOptions() {
            if (!this.search || this.search.trim() === '') {
                return this.optionsList;
            }
            let s = this.search.toLowerCase().trim();
            return this.optionsList.filter(i =>
                String(i.label).toLowerCase().includes(s) ||
                String(i.value).toLowerCase().includes(s)
            );
        },

        selectOption(val) {
            this.selectedValue = val;
            this.open = false;
            this.search = '';
            this.activeIndex = -1;
            this.$dispatch('input', val);
            this.$dispatch('change', val);
        },

        clearSelection() {
            this.selectOption('');
        },

        toggleOpen() {
            this.open = !this.open;
            if (this.open) {
                this.search = '';
                this.activeIndex = -1;
                $nextTick(() => {
                    if (this.$refs.searchInput) this.$refs.searchInput.focus();
                });
            }
        },

        navigateDown() {
            if (!this.open) {
                this.toggleOpen();
                return;
            }
            if (this.activeIndex < this.filteredOptions.length - 1) {
                this.activeIndex++;
                this.scrollToActive();
            }
        },

        navigateUp() {
            if (!this.open) {
                this.toggleOpen();
                return;
            }
            if (this.activeIndex > 0) {
                this.activeIndex--;
                this.scrollToActive();
            }
        },

        selectActive() {
            if (this.activeIndex >= 0 && this.activeIndex < this.filteredOptions.length) {
                this.selectOption(this.filteredOptions[this.activeIndex].value);
            } else if (this.filteredOptions.length === 1) {
                this.selectOption(this.filteredOptions[0].value);
            }
        },

        scrollToActive() {
            $nextTick(() => {
                const listEl = this.$refs.optionsList;
                if (!listEl) return;
                const items = listEl.querySelectorAll('[data-option-item]');
                if (items[this.activeIndex]) {
                    items[this.activeIndex].scrollIntoView({ block: 'nearest' });
                }
            });
        }
    }"
    @click.away="open = false"
    @keydown.escape.window="open = false"
    class="relative w-full {{ $containerClass }}">

    @if($name)
        <!-- Hidden input to submit with regular HTML GET/POST forms -->
        <input type="hidden" name="{{ $name }}" id="{{ $id ?? $name }}" :value="selectedValue">
    @endif

    <!-- Trigger Button -->
    <div @click="toggleOpen()"
        @keydown.arrow-down.prevent="navigateDown()"
        @keydown.arrow-up.prevent="navigateUp()"
        @keydown.enter.prevent="if(!open) toggleOpen(); else selectActive();"
        tabindex="0"
        class="w-full px-3 py-2 bg-white border border-surface-300 rounded-lg text-xs font-normal flex items-center justify-between transition cursor-pointer hover:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/20 focus:border-primary-600 shadow-2xs select-none"
        :class="selectedValue ? 'text-surface-800' : 'text-surface-500'">
        
        <div class="flex items-center gap-2 min-w-0 pr-1 flex-1">
            @if($icon)
                <i class="{{ $icon }} text-[11px] shrink-0 text-surface-400" :class="selectedValue ? 'text-primary-600' : ''"></i>
            @endif
            <span class="truncate block" x-text="selectedLabel"></span>
        </div>

        <div class="flex items-center gap-1.5 shrink-0 pl-1">
            <!-- Clear Button -->
            <button x-show="selectedValue"
                @click.stop="clearSelection()"
                type="button"
                class="text-surface-500 hover:text-danger p-0.5 rounded transition cursor-pointer"
                title="Hapus Pilihan">
                <i class="fas fa-times text-[10px]" aria-hidden="true"></i>
            </button>
            <i class="fas fa-chevron-down text-surface-400 text-[10px] transition-transform duration-200"
                :class="open ? 'rotate-180 text-primary-600' : ''" aria-hidden="true"></i>
        </div>
    </div>

    <!-- Dropdown Menu -->
    <div x-show="open"
        x-transition:enter="transition ease-out duration-150"
        x-transition:enter-start="opacity-0 -translate-y-1 scale-98"
        x-transition:enter-end="opacity-100 translate-y-0 scale-100"
        x-transition:leave="transition ease-in duration-100"
        x-transition:leave-start="opacity-100 translate-y-0 scale-100"
        x-transition:leave-end="opacity-0 -translate-y-1 scale-98"
        x-cloak
        class="absolute z-50 mt-1.5 w-full bg-white rounded-xl border border-surface-200 shadow-xl overflow-hidden ring-1 ring-black/5">

        <!-- Sticky Search Bar Header -->
        <div class="p-2 border-b border-surface-100 bg-surface-50/70">
            <div class="relative">
                <i class="fas fa-search absolute left-2.5 top-1/2 -translate-y-1/2 text-surface-400 text-[10px]" aria-hidden="true"></i>
                <input x-ref="searchInput"
                    type="text"
                    x-model="search"
                    @keydown.arrow-down.prevent="navigateDown()"
                    @keydown.arrow-up.prevent="navigateUp()"
                    @keydown.enter.prevent="selectActive()"
                    placeholder="{{ $searchPlaceholder }}"
                    class="w-full pl-7 pr-7 py-1.5 bg-white border border-surface-200 rounded-lg text-xs text-surface-800 placeholder-surface-400 focus:outline-none focus:ring-2 focus:ring-primary-500/20 focus:border-primary-600 shadow-2xs">
                <button aria-label="Hapus pencarian" x-show="search.length > 0"
                    @click="search = ''; $refs.searchInput.focus();"
                    type="button"
                    class="absolute right-2.5 top-1/2 -translate-y-1/2 text-surface-500 hover:text-surface-600 text-[10px]">
                    <i class="fas fa-times-circle" aria-hidden="true"></i>
                </button>
            </div>
            <div class="mt-1 flex items-center justify-between px-1 text-[10px] text-surface-500 font-medium">
                <span x-text="filteredOptions.length + ' opsi tersedia'"></span>
                <span class="text-[10px] text-surface-500 hidden sm:inline">&uarr;&darr; navigasi &bull; Enter pilih</span>
            </div>
        </div>

        <!-- Scrollable Options List -->
        <ul x-ref="optionsList"
            class="overflow-y-auto py-1 divide-y divide-surface-50 {{ $maxHeight }}"
            style="scrollbar-width: thin; scrollbar-color: #cbd5e1 transparent;">

            @if($hasAllOption)
                <li @click="selectOption('')"
                    class="px-3 py-2 text-xs font-medium cursor-pointer transition flex items-center justify-between hover:bg-surface-50"
                    :class="!selectedValue ? 'bg-primary-50/80 text-primary-800 font-semibold' : 'text-surface-600'">
                    <span class="truncate">{{ $allOptionText }}</span>
                    <i x-show="!selectedValue" class="fas fa-check text-primary-600 text-[10px]" aria-hidden="true"></i>
                </li>
            @endif

            <template x-for="(opt, idx) in filteredOptions" :key="opt.value + '_' + idx">
                <li data-option-item
                    @click="selectOption(opt.value)"
                    @mouseenter="activeIndex = idx"
                    class="px-3 py-2 text-xs cursor-pointer transition flex items-center justify-between"
                    :class="{
                        'bg-primary-50 text-primary-800 font-medium': activeIndex === idx && String(selectedValue) !== String(opt.value),
                        'bg-primary-50/90 text-primary-800 font-semibold': String(selectedValue) === String(opt.value),
                        'text-surface-700 hover:bg-surface-50': activeIndex !== idx && String(selectedValue) !== String(opt.value)
                    }">
                    <span class="truncate pr-2" x-text="opt.label"></span>
                    <i x-show="String(selectedValue) === String(opt.value)" class="fas fa-check text-primary-600 text-[10px] shrink-0" aria-hidden="true"></i>
                </li>
            </template>

            <!-- Empty State -->
            <li x-show="filteredOptions.length === 0" class="px-3 py-6 text-center text-xs text-surface-500 font-normal">
                <i class="fas fa-folder-open text-surface-300 text-base mb-1 block" aria-hidden="true"></i>
                <span>{{ $emptyText }}</span>
            </li>
        </ul>
    </div>
</div>

