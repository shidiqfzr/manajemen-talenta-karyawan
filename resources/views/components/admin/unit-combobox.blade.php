@props([
    'groupedUnits' => [],
    'selected' => '',
    'name' => 'unit_kerja',
    'id' => 'unitKerjaInput',
    'required' => true,
])

@php
    $initialUnits = [];
    foreach ($groupedUnits as $region => $unitList) {
        foreach ($unitList as $u) {
            $initialUnits[] = [
                'nama' => $u,
                'wilayah' => $region,
            ];
        }
    }
    // Include selected unit if not already in initialUnits (e.g. legacy/custom unit)
    if ($selected && !collect($initialUnits)->pluck('nama')->contains($selected)) {
        $initialUnits[] = [
            'nama' => $selected,
            'wilayah' => 'Lainnya / Unit Khusus',
        ];
    }

    // Extract unique regions for tab pills
    $rawRegions = array_keys($groupedUnits);
@endphp

<div x-data="{
        isOpen: false,
        search: @js($selected ?? ''),
        selectedUnit: @js($selected ?? ''),
        activeWilayah: 'all',
        units: @js($initialUnits),
        
        get regions() {
            const set = new Set();
            this.units.forEach(u => {
                if (u.wilayah) set.add(u.wilayah);
            });
            return Array.from(set);
        },

        getRegionShortLabel(wilayah) {
            if (!wilayah) return 'Lainnya';
            if (wilayah.includes('Regional Office') || wilayah.includes('Direksi')) return 'Kandir';
            if (wilayah.includes('Kalimantan Barat') || wilayah.includes('Kalbar')) return 'Kalbar';
            if (wilayah.includes('Selatan') || wilayah.includes('Tengah') || wilayah.includes('Kalselteng')) return 'Kalselteng';
            if (wilayah.includes('Timur') || wilayah.includes('Kaltim')) return 'Kaltim';
            return wilayah.length > 12 ? wilayah.substring(0, 10) + '...' : wilayah;
        },

        getRegionCount(wilayah) {
            if (wilayah === 'all') return this.units.length;
            return this.units.filter(u => u.wilayah === wilayah).length;
        },

        get filteredUnits() {
            let list = this.units;
            
            // Filter by active region pill if not 'all'
            if (this.activeWilayah !== 'all') {
                list = list.filter(u => u.wilayah === this.activeWilayah);
            }

            // Filter by search query
            if (this.search && this.search !== this.selectedUnit) {
                const query = this.search.toLowerCase().trim();
                list = list.filter(u => 
                    u.nama.toLowerCase().includes(query) || 
                    (u.wilayah && u.wilayah.toLowerCase().includes(query))
                );
            }

            return list;
        },

        getUnitIcon(nama) {
            const lower = (nama || '').toLowerCase();
            if (lower.includes('pks') || lower.includes('pabrik')) {
                return { icon: 'fa-industry', color: 'text-amber-600 bg-amber-50' };
            }
            if (lower.includes('kebun') || lower.includes('tanaman')) {
                return { icon: 'fa-leaf', color: 'text-emerald-600 bg-emerald-50' };
            }
            if (lower.includes('bagian') || lower.includes('divisi') || lower.includes('kantor') || lower.includes('sekretariat') || lower.includes('spi') || lower.includes('pmo')) {
                return { icon: 'fa-building', color: 'text-blue-600 bg-blue-50' };
            }
            if (lower.includes('proyek') || lower.includes('batu')) {
                return { icon: 'fa-screwdriver-wrench', color: 'text-slate-600 bg-slate-100' };
            }
            return { icon: 'fa-map-pin', color: 'text-teal-600 bg-teal-50' };
        },

        select(unitName) {
            this.selectedUnit = unitName;
            this.search = unitName;
            this.isOpen = false;
            // Dispatch event for live preview in parent form
            this.$dispatch('unit-selected', { unit: unitName });
        },

        clear() {
            this.selectedUnit = '';
            this.search = '';
            this.isOpen = true;
            this.$dispatch('unit-selected', { unit: '' });
            $nextTick(() => {
                if (this.$refs.searchInput) this.$refs.searchInput.focus();
            });
        },

        openDropdown() {
            this.isOpen = true;
            $nextTick(() => {
                if (this.$refs.searchInput) this.$refs.searchInput.select();
            });
        },

        handleQuickAddPrompt() {
            $dispatch('open-quick-add-unit', { nama: this.search });
            this.isOpen = false;
        }
    }"
    @unit-created.window="
        const newUnit = $event.detail;
        if (newUnit && newUnit.nama) {
            if (!units.some(u => u.nama.toLowerCase() === newUnit.nama.toLowerCase())) {
                units.unshift({
                    nama: newUnit.nama,
                    wilayah: newUnit.wilayah || 'Unit Baru'
                });
            }
            selectedUnit = newUnit.nama;
            search = newUnit.nama;
            isOpen = false;
            $dispatch('unit-selected', { unit: newUnit.nama });
        }
    "
    @click.outside="isOpen = false"
    @keydown.escape.window="isOpen = false"
    class="relative">

    <!-- Hidden Input for Form Submission -->
    <input type="hidden" 
           name="{{ $name }}" 
           id="{{ $id }}" 
           :value="selectedUnit" 
           {{ $required ? 'required' : '' }}>

    <!-- Combobox Input Trigger -->
    <div class="relative">
        <div class="relative flex items-center">
            <span class="absolute left-3 text-emerald-600 pointer-events-none text-xs">
                <i class="fas fa-building-circle-check"></i>
            </span>

            <input type="text"
                   x-ref="searchInput"
                   x-model="search"
                   @focus="openDropdown()"
                   @click="openDropdown()"
                   @input="isOpen = true; $dispatch('unit-selected', { unit: search })"
                   @keydown.arrow-down.prevent="isOpen = true"
                   placeholder="Cari atau pilih dari 43 unit kerja..."
                   autocomplete="off"
                   class="w-full pl-8 pr-20 py-2.5 bg-slate-50/70 border border-slate-200 rounded-xl text-xs sm:text-sm font-medium text-slate-800 placeholder-slate-400 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500/30 focus:border-emerald-500 transition truncate">
            
            <div class="absolute right-2 flex items-center gap-1 bg-slate-100/90 backdrop-blur-xs px-1.5 py-1 rounded-lg border border-slate-200/60">
                <button type="button" 
                        x-show="search.length > 0" 
                        @click.stop="clear()"
                        class="text-slate-400 hover:text-rose-600 p-0.5 rounded transition text-xs cursor-pointer"
                        title="Hapus pilihan">
                    <i class="fas fa-times"></i>
                </button>
                <button type="button" 
                        @click.stop="isOpen = !isOpen"
                        class="text-slate-400 hover:text-slate-700 p-0.5 rounded transition text-xs cursor-pointer">
                    <i class="fas fa-chevron-down text-[10px] transition-transform duration-200" :class="{ 'rotate-180': isOpen }"></i>
                </button>
            </div>
        </div>
    </div>

    <!-- Dropdown Options Panel -->
    <div x-show="isOpen"
         x-transition:enter="transition ease-out duration-150"
         x-transition:enter-start="opacity-0 translate-y-1 scale-98"
         x-transition:enter-end="opacity-100 translate-y-0 scale-100"
         x-transition:leave="transition ease-in duration-100"
         x-transition:leave-start="opacity-100 translate-y-0 scale-100"
         x-transition:leave-end="opacity-0 translate-y-1 scale-98"
         x-cloak
         class="absolute z-50 left-0 right-0 mt-1.5 bg-white rounded-2xl border border-slate-200 shadow-2xl overflow-hidden focus:outline-none ring-1 ring-black/5">
        
        <!-- Header: Wilayah Filter Pills -->
        <div class="p-2.5 bg-slate-50/90 border-b border-slate-100 space-y-2">
            <div class="flex items-center justify-between text-[11px] font-semibold text-slate-500 px-0.5">
                <span class="flex items-center gap-1.5 text-slate-700">
                    <i class="fas fa-layer-group text-emerald-600 text-xs"></i>
                    <span>Filter Wilayah Kerja</span>
                </span>
                <span class="text-[10px] text-slate-400">
                    Menampilkan <strong class="text-slate-700 font-bold" x-text="filteredUnits.length"></strong> unit
                </span>
            </div>

            <!-- Region Filter Buttons (Horizontal Scrollable Pills) -->
            <div class="flex items-center gap-1.5 overflow-x-auto pb-1 text-xs scrollbar-none">
                <button type="button"
                        @click="activeWilayah = 'all'"
                        class="px-2.5 py-1 rounded-lg text-[11px] font-semibold transition shrink-0 flex items-center gap-1 cursor-pointer"
                        :class="activeWilayah === 'all' 
                            ? 'bg-emerald-600 text-white shadow-xs' 
                            : 'bg-white border border-slate-200 text-slate-600 hover:bg-slate-100'">
                    <span>Semua</span>
                    <span class="text-[9px] px-1.5 py-0.2 rounded-full"
                          :class="activeWilayah === 'all' ? 'bg-white/25 text-white' : 'bg-slate-100 text-slate-500'"
                          x-text="units.length"></span>
                </button>

                <template x-for="reg in regions" :key="reg">
                    <button type="button"
                            @click="activeWilayah = reg"
                            class="px-2.5 py-1 rounded-lg text-[11px] font-semibold transition shrink-0 flex items-center gap-1 cursor-pointer"
                            :class="activeWilayah === reg 
                                ? 'bg-emerald-600 text-white shadow-xs' 
                                : 'bg-white border border-slate-200 text-slate-600 hover:bg-slate-100'">
                        <span x-text="getRegionShortLabel(reg)"></span>
                        <span class="text-[9px] px-1.5 py-0.2 rounded-full"
                              :class="activeWilayah === reg ? 'bg-white/25 text-white' : 'bg-slate-100 text-slate-500'"
                              x-text="getRegionCount(reg)"></span>
                    </button>
                </template>
            </div>
        </div>

        <!-- Scrollable Options List (Height constrained strictly to max-h-60) -->
        <div class="max-h-60 overflow-y-auto divide-y divide-slate-100/70 p-1">
            <template x-for="item in filteredUnits" :key="item.nama">
                <button type="button"
                        @click="select(item.nama)"
                        class="w-full text-left px-3 py-2 rounded-xl hover:bg-emerald-50/70 transition flex items-center justify-between group cursor-pointer"
                        :class="selectedUnit === item.nama ? 'bg-emerald-50/80 text-emerald-950 font-bold border border-emerald-200/60' : 'text-slate-700'">
                    <div class="flex items-center gap-2.5 min-w-0 pr-2">
                        <!-- Icon Badge -->
                        <div class="w-7 h-7 rounded-lg flex items-center justify-center text-xs shrink-0 transition"
                             :class="getUnitIcon(item.nama).color">
                            <i class="fas" :class="getUnitIcon(item.nama).icon"></i>
                        </div>
                        <div class="min-w-0">
                            <div class="text-xs font-semibold group-hover:text-emerald-800 truncate" x-text="item.nama"></div>
                            <div class="text-[10px] text-slate-400 group-hover:text-emerald-600/90 font-normal truncate" x-text="item.wilayah"></div>
                        </div>
                    </div>
                    <div class="shrink-0 flex items-center">
                        <span x-show="selectedUnit === item.nama" class="w-5 h-5 rounded-full bg-emerald-600 text-white flex items-center justify-center text-[10px]">
                            <i class="fas fa-check"></i>
                        </span>
                        <i x-show="selectedUnit !== item.nama" class="fas fa-chevron-right text-[10px] text-slate-300 group-hover:text-emerald-500 opacity-0 group-hover:opacity-100 transition"></i>
                    </div>
                </button>
            </template>
        </div>

        <!-- Empty State / No Match -->
        <div x-show="filteredUnits.length === 0" class="p-5 text-center bg-slate-50/50">
            <div class="w-10 h-10 rounded-2xl bg-amber-50 text-amber-600 flex items-center justify-center mx-auto mb-2 text-sm shadow-xs">
                <i class="fas fa-search-location"></i>
            </div>
            <p class="text-xs font-bold text-slate-800">Unit Kerja Tidak Ditemukan</p>
            <p class="text-[11px] text-slate-500 mt-0.5 max-w-xs mx-auto">
                "<span x-text="search" class="font-semibold text-slate-700"></span>" belum terdaftar di filter wilayah yang dipilih.
            </p>
            <div class="mt-3 flex items-center justify-center gap-2">
                <button type="button"
                        x-show="activeWilayah !== 'all'"
                        @click="activeWilayah = 'all'"
                        class="inline-flex items-center gap-1 px-3 py-1.5 bg-slate-200 hover:bg-slate-300 text-slate-700 rounded-lg text-xs font-semibold transition cursor-pointer">
                    <i class="fas fa-globe text-[10px]"></i>
                    <span>Cari di Semua Wilayah</span>
                </button>
                <button type="button"
                        @click="handleQuickAddPrompt()"
                        class="inline-flex items-center gap-1.5 px-3.5 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg text-xs font-bold shadow-xs transition cursor-pointer">
                    <i class="fas fa-plus text-[10px]"></i>
                    <span>Tambah Unit Baru</span>
                </button>
            </div>
        </div>
    </div>
</div>
