@props([
    'wireModel' => 'form.employeeNik',
    'selectedNik' => '',
    'selectedNama' => '',
    'placeholder' => 'Ketik nama, NIK, jabatan, atau unit...',
    'employees' => null,
    'label' => 'Pilih Karyawan Aktif',
    'required' => true,
    'errorKey' => 'employeeNik',
    'id' => 'employeeCombobox_' . uniqid(),
    'excludeIndex' => null,
])

<div>
    @if ($label)
        <label class="block text-xs font-semibold text-surface-700 mb-1.5">
            {{ $label }} @if ($required)<span class="text-danger font-bold">*</span>@endif
        </label>
    @endif

    <div x-data="{
        open: false,
        search: '',
        employeesList: {{ $employees ? json_encode($employees) : "(typeof allEmployeesList !== 'undefined' ? allEmployeesList : [])" }},
        isAlreadySelected(nik) {
            @if ($excludeIndex !== null)
                return $wire.nodes ? $wire.nodes.some((n, idx) => idx !== {{ $excludeIndex }} && n.employee_nik === nik) : false;
            @else
                return false;
            @endif
        },
        get filtered() {
            if (!this.search.trim()) return this.employeesList;
            const q = this.search.toLowerCase();
            return this.employeesList.filter(e =>
                (e.nama && e.nama.toLowerCase().includes(q)) ||
                (e.nik && e.nik.toLowerCase().includes(q)) ||
                (e.jabatan && e.jabatan.toLowerCase().includes(q)) ||
                (e.unit_kerja && e.unit_kerja.toLowerCase().includes(q))
            );
        },
        select(nik) {
            if (this.isAlreadySelected(nik)) return;
            $wire.set('{{ $wireModel }}', nik);
            this.open = false;
            this.search = '';
        },
        clear() {
            $wire.set('{{ $wireModel }}', '');
            this.search = '';
            this.open = false;
        }
    }" @click.outside="open = false" class="relative">
        <!-- Combobox Trigger Input -->
        <div class="relative">
            <input type="text"
                x-model="search"
                @focus="open = true"
                @click="open = true"
                @keydown.escape="open = false"
                placeholder="{{ $selectedNama && $selectedNik ? $selectedNama . ' (' . $selectedNik . ')' : ($selectedNik ? $selectedNik : $placeholder) }}"
                class="w-full pl-9.5 pr-9 py-2.5 rounded-xl border border-surface-300 bg-white text-surface-800 text-xs sm:text-sm font-medium focus:outline-none focus:border-primary-600 focus:ring-2 focus:ring-primary-100 transition shadow-2xs {{ empty($selectedNik) ? 'placeholder:text-surface-400' : 'font-semibold text-surface-900 placeholder:text-surface-800' }}">
            <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-surface-500">
                <i class="fas fa-search text-xs" aria-hidden="true"></i>
            </div>
            @if (!empty($selectedNik))
                <button type="button" @click="clear()"
                    class="absolute inset-y-0 right-0 pr-3 flex items-center text-surface-500 hover:text-danger transition cursor-pointer"
                    title="Hapus pilihan karyawan">
                    <i class="fas fa-times-circle text-xs" aria-hidden="true"></i>
                </button>
            @endif
        </div>

        @if ($errorKey)
            @error($errorKey)
                <p class="text-danger text-xs mt-1.5 flex items-center gap-1">
                    <i class="fas fa-circle-exclamation text-xs" aria-hidden="true"></i> {{ $message }}
                </p>
            @enderror
        @endif

        <!-- Dropdown Results Panel -->
        <div x-show="open" x-cloak
            x-transition:enter="transition ease-out duration-100"
            x-transition:enter-start="transform opacity-0 scale-95"
            x-transition:enter-end="transform opacity-100 scale-100"
            class="absolute left-0 right-0 top-full mt-1.5 bg-white border border-surface-200 rounded-2xl shadow-xl max-h-80 overflow-y-auto z-40 divide-y divide-surface-100 overflow-hidden">
            <template x-for="emp in filtered.slice(0, 30)" :key="emp.nik">
                <div @click="select(emp.nik)"
                    :class="isAlreadySelected(emp.nik) ? 'opacity-40 cursor-not-allowed bg-surface-50' : 'hover:bg-primary-50/60 cursor-pointer'"
                    class="p-3 text-surface-800 transition flex items-center justify-between gap-3 text-xs">
                    <div class="flex items-center gap-3 min-w-0">
                        <div class="w-9 h-9 rounded-xl bg-primary-100 text-primary-800 font-bold text-xs flex items-center justify-center shrink-0 shadow-2xs"
                            x-text="emp.nama ? emp.nama.charAt(0) : '?'">
                        </div>
                        <div class="min-w-0 space-y-0.5">
                            <div class="font-bold text-surface-900 truncate text-xs sm:text-sm tracking-tight" x-text="emp.nama"></div>
                            <div class="text-[11px] text-surface-700 font-medium truncate" x-text="emp.jabatan || '—'"></div>
                            <div class="text-[10.5px] text-surface-500 font-normal truncate" x-text="emp.unit_kerja || '—'"></div>
                        </div>
                    </div>
                    <div class="flex items-center gap-2 shrink-0">
                        <template x-if="isAlreadySelected(emp.nik)">
                            <span class="text-[10px] font-semibold bg-warning-100 text-warning-800 px-1.5 py-0.5 rounded-md">Di Slot Lain</span>
                        </template>
                        <span class="text-[11px] font-mono text-surface-500 font-medium tracking-tight" x-text="emp.nik"></span>
                    </div>
                </div>
            </template>
            <div x-show="filtered.length === 0" class="p-4 text-center text-xs text-surface-500 italic">
                Tidak ada pegawai yang cocok dengan kata kunci.
            </div>
        </div>
    </div>
</div>
