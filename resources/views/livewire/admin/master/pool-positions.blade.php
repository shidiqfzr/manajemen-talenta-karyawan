{{-- Jabatan Penampungan: Advisor dan Diperbantukan, opsional pada sebuah unit dan bukan bagian Master Formasi (komponen tabel bersama). --}}
@php
    $isJabatan = $view === 'jabatan';
    $loadingTargets = 'search,setView,perPage,saveCreate,saveOpen,saveClose,saveRename,saveDelete';
    $fieldClass = 'w-full px-3 py-2 bg-surface-50/70 border border-surface-200 rounded-xl text-xs font-medium text-surface-800 focus:bg-white focus:outline-none focus:ring-2 focus:ring-primary-500/30 focus:border-primary-500 transition';
    $fmt = fn (int $n) => number_format($n, 0, ',', '.');
    $jenisClass = fn (string $label) => $label === 'Advisor'
        ? 'bg-primary-100 text-primary-800 border-primary-200'
        : 'bg-earth-100 text-earth-800 border-earth-300';
@endphp
<div class="space-y-6">
    @if ($notice)
        <div role="status" class="flex items-start gap-2.5 p-4 bg-primary-50 text-primary-800 border border-primary-200 rounded-xl shadow-xs text-sm font-medium">
            <i class="fas fa-circle-check mt-0.5" aria-hidden="true"></i>
            <span>{{ $notice }}</span>
        </div>
    @endif

    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 sm:gap-3.5">
        <x-admin.kpi-card title="Jabatan Penampungan" :value="$stats['jabatan']" subtitle="Advisor dan Diperbantukan" icon="fas fa-user-tag" theme="primary" />
        <x-admin.kpi-card title="Unit Pengguna" :value="$stats['units']" subtitle="Unit yang membuka jabatan penampungan" icon="fas fa-building" theme="teal" />
        <x-admin.kpi-card title="Karyawan di Penampungan" :value="$stats['holders']" subtitle="Di luar formasi" icon="fas fa-users" theme="earth" />
    </div>

    <x-admin.table.card>
        <div wire:loading.delay wire:target="{{ $loadingTargets }}" class="w-full h-1 bg-gradient-to-r from-primary-600 via-primary-400 to-primary-600 motion-safe:animate-pulse"></div>
        <x-admin.table.toolbar
            :table-id="$this->getId()"
            :filters="[]"
            :filter-values="[]"
            :has-active-filters="$search !== ''"
            :search="$search"
            search-placeholder="Cari jabatan atau unit kerja..." />
        <x-admin.table.header :title="$isJabatan ? 'Daftar Jabatan Penampungan' : 'Bezetting per Unit'" :paginator="$rows" :noun="$isJabatan ? 'Jabatan' : 'Baris'">
            <x-slot:actions>
                <div role="radiogroup" aria-label="Tampilan jabatan penampungan" x-data="{ move(step) { const radios = [...$el.querySelectorAll('[role=radio]')]; const index = radios.indexOf(document.activeElement); if (index < 0) { return; } const target = radios[(index + step + radios.length) % radios.length]; target.focus(); target.click(); } }"
                    @keydown.arrow-right.prevent="move(1)" @keydown.arrow-down.prevent="move(1)"
                    @keydown.arrow-left.prevent="move(-1)" @keydown.arrow-up.prevent="move(-1)"
                    class="inline-flex rounded-lg border border-surface-300 bg-white p-0.5">
                    @foreach (['jabatan' => 'Daftar Jabatan', 'unit' => 'Per Unit'] as $key => $label)
                        <button type="button" role="radio" aria-checked="{{ $view === $key ? 'true' : 'false' }}" tabindex="{{ $view === $key ? '0' : '-1' }}" wire:click="setView('{{ $key }}')"
                            class="px-3 py-1.5 rounded-md text-xs font-semibold transition focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 {{ $view === $key ? 'bg-primary-600 text-white shadow-2xs' : 'text-surface-600 hover:bg-surface-100' }}">{{ $label }}</button>
                    @endforeach
                </div>
                <x-admin.button size="sm" icon="fas fa-plus" wire:click="openCreate">Tambah Jabatan</x-admin.button>
            </x-slot:actions>
        </x-admin.table.header>

        <p class="px-5 py-2.5 bg-surface-50 border-b border-surface-200 text-[11px] text-surface-500">
            Jabatan penampungan bersifat opsional pada sebuah unit dan bukan bagian dari Master Formasi: tanpa kuota, tidak mengisi atau mengosongkan formasi mana pun. Buka sebuah jabatan pada unit agar karyawan dapat ditempatkan padanya.
        </p>

        <div class="overflow-x-auto">
            @if ($isJabatan)
                <table class="w-full text-left border-collapse text-xs" wire:loading.attr="aria-busy" wire:target="{{ $loadingTargets }}">
                    <thead>
                        <tr class="bg-surface-50 border-b border-surface-200">
                            <x-admin.table.th class="py-3.5 px-5">Jabatan</x-admin.table.th>
                            <x-admin.table.th class="py-3.5 px-5">Jenis</x-admin.table.th>
                            <x-admin.table.th class="py-3.5 px-5">RM Band</x-admin.table.th>
                            <x-admin.table.th class="py-3.5 px-5">Dibuka di Unit</x-admin.table.th>
                            <x-admin.table.th class="py-3.5 px-5">Karyawan</x-admin.table.th>
                            <x-admin.table.th class="py-3.5 px-5 text-right">Aksi</x-admin.table.th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-surface-200 text-surface-800 transition-opacity" wire:loading.class="opacity-50" wire:target="{{ $loadingTargets }}">
                        @forelse ($rows as $row)
                            <tr class="hover:bg-surface-50/75 transition" wire:key="pool-{{ $row->id }}">
                                <td class="py-3.5 px-5 font-semibold text-surface-900 text-sm">
                                    {{ $row->nama }}
                                    @unless ($row->is_active)
                                        <span class="ml-1 inline-flex px-2 py-0.5 rounded-md text-[10px] font-semibold bg-surface-100 text-surface-600">Nonaktif</span>
                                    @endunless
                                </td>
                                <td class="py-3.5 px-5"><span class="inline-flex px-2 py-0.5 rounded-full text-[11px] font-semibold border {{ $jenisClass($row->jenis_label) }}">{{ $row->jenis_label }}</span></td>
                                <td class="py-3.5 px-5"><span class="inline-flex px-2 py-0.5 rounded-md text-[10px] font-semibold border {{ \App\Models\Position::rmTheme($row->rm_level) }}">{{ $row->rm_level ?? '-' }}</span></td>
                                <td class="py-3.5 px-5 tabular-nums">{{ $row->units }} unit</td>
                                <td class="py-3.5 px-5 tabular-nums">{{ $fmt($row->holders) }}</td>
                                <td class="py-3.5 px-5 text-right whitespace-nowrap">
                                    <div class="inline-flex items-center gap-2">
                                        @if ($row->is_active)
                                            <x-admin.button size="sm" variant="secondary" icon="fas fa-door-open" wire:click="startOpen({{ $row->id }})">Buka di Unit</x-admin.button>
                                        @endif
                                        <x-admin.button size="sm" variant="secondary" icon="fas fa-pen" wire:click="startRename({{ $row->id }})" aria-label="Ubah nama {{ $row->nama }}">Ubah Nama</x-admin.button>
                                        <x-admin.button size="sm" variant="secondary" icon="fas fa-trash-can" wire:click="startDelete({{ $row->id }})" aria-label="Hapus {{ $row->nama }}">Hapus</x-admin.button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <x-admin.table.empty-state :colspan="6" icon="fas fa-user-tag"
                                :message="$search !== '' ? 'Tidak ada jabatan penampungan yang sesuai dengan kata kunci. Ubah kata kunci pencarian.' : 'Belum ada jabatan penampungan. Gunakan Tambah Jabatan untuk membuat Advisor atau Diperbantukan.'" />
                        @endforelse
                    </tbody>
                </table>
            @else
                <table class="w-full text-left border-collapse text-xs" wire:loading.attr="aria-busy" wire:target="{{ $loadingTargets }}">
                    <thead>
                        <tr class="bg-surface-50 border-b border-surface-200">
                            <x-admin.table.th class="py-3.5 px-5">Unit Kerja</x-admin.table.th>
                            <x-admin.table.th class="py-3.5 px-5">Jabatan</x-admin.table.th>
                            <x-admin.table.th class="py-3.5 px-5">RM Band</x-admin.table.th>
                            <x-admin.table.th class="py-3.5 px-5">Karyawan</x-admin.table.th>
                            <x-admin.table.th class="py-3.5 px-5">Status</x-admin.table.th>
                            <x-admin.table.th class="py-3.5 px-5 text-right">Aksi</x-admin.table.th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-surface-200 text-surface-800 transition-opacity" wire:loading.class="opacity-50" wire:target="{{ $loadingTargets }}">
                        @forelse ($rows as $row)
                            <tr class="hover:bg-surface-50/75 transition" wire:key="pool-unit-{{ $row->position_id }}-{{ md5($row->unit_nama) }}">
                                <td class="py-3.5 px-5 font-semibold text-surface-900">{{ $row->unit_nama }}</td>
                                <td class="py-3.5 px-5">
                                    <span class="font-semibold text-surface-900 text-sm">{{ $row->nama }}</span>
                                    <span class="ml-1 inline-flex px-2 py-0.5 rounded-full text-[11px] font-semibold border {{ $jenisClass($row->jenis_label) }}">{{ $row->jenis_label }}</span>
                                </td>
                                <td class="py-3.5 px-5"><span class="inline-flex px-2 py-0.5 rounded-md text-[10px] font-semibold border {{ \App\Models\Position::rmTheme($row->rm_level) }}">{{ $row->rm_level ?? '-' }}</span></td>
                                <td class="py-3.5 px-5 tabular-nums">{{ $fmt($row->holders) }}</td>
                                <td class="py-3.5 px-5">
                                    @if ($row->opened)
                                        <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-[11px] font-semibold bg-primary-100 text-primary-800 border border-primary-200"><span class="w-1.5 h-1.5 rounded-full bg-primary-600" aria-hidden="true"></span>Dibuka</span>
                                    @else
                                        <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-[11px] font-semibold bg-surface-100 text-surface-700 border border-surface-200" title="Karyawan sudah tercatat pada jabatan ini tetapi unitnya belum membuka jabatan tersebut"><span class="w-1.5 h-1.5 rounded-full bg-surface-400" aria-hidden="true"></span>Belum dibuka di unit</span>
                                    @endif
                                </td>
                                <td class="py-3.5 px-5 text-right whitespace-nowrap">
                                    @if (! $row->opened && $row->unit_id)
                                        <x-admin.button size="sm" variant="secondary" icon="fas fa-door-open" wire:click="startOpen({{ $row->position_id }}, {{ $row->unit_id }})">Buka</x-admin.button>
                                    @elseif ($row->opened && $row->holders === 0)
                                        <x-admin.button size="sm" variant="secondary" icon="fas fa-door-closed" wire:click="startClose({{ $row->position_id }}, {{ $row->unit_id }})">Tutup</x-admin.button>
                                    @elseif ($row->opened)
                                        <span class="text-[11px] text-surface-500">Masih dijabat</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <x-admin.table.empty-state :colspan="6" icon="fas fa-building"
                                :message="$search !== '' ? 'Tidak ada baris yang sesuai dengan kata kunci. Ubah kata kunci pencarian.' : 'Belum ada jabatan penampungan yang dibuka pada sebuah unit.'" />
                        @endforelse
                    </tbody>
                </table>
            @endif
        </div>

        <x-admin.table.pagination :paginator="$rows" :noun="$isJabatan ? 'jabatan' : 'baris'" :per-page-options="$perPageOptions" />
    </x-admin.table.card>

    {{-- Dialog Tambah Jabatan Penampungan --}}
    @if ($creating)
        <div x-data="{ open: true }" @keydown.escape.window="$wire.closeCreate()" @click.self="$wire.closeCreate()"
            class="fixed inset-0 z-50 overflow-y-auto bg-surface-900/60 backdrop-blur-xs flex items-center justify-center p-4"
            role="dialog" aria-modal="true" aria-labelledby="pool-create-title" x-trap.noscroll="open" tabindex="-1">
            <div class="bg-white rounded-2xl border border-surface-200 shadow-2xl max-w-lg w-full overflow-hidden">
                <div class="px-6 py-4 border-b border-surface-100 bg-surface-50/50">
                    <h3 id="pool-create-title" class="text-sm font-extrabold text-surface-900">Tambah Jabatan Penampungan</h3>
                    <p class="text-[11px] text-surface-500">Jabatan baru tanpa kuota dan belum dibuka pada unit mana pun.</p>
                </div>
                <div class="p-6 space-y-4">
                    <div>
                        <label for="pool-new-nama" class="block text-xs font-bold text-surface-700 mb-1.5">Nama Jabatan <span class="text-danger-700" aria-hidden="true">*</span></label>
                        <input id="pool-new-nama" type="text" wire:model="newNama" maxlength="255" autocomplete="off" aria-required="true" placeholder="Mis. Asisten Diperbantukan" class="{{ $fieldClass }}">
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label for="pool-new-jenis" class="block text-xs font-bold text-surface-700 mb-1.5">Jenis <span class="text-danger-700" aria-hidden="true">*</span></label>
                            <select id="pool-new-jenis" wire:model="newJenis" aria-required="true" class="{{ $fieldClass }}">
                                <option value="">— Pilih jenis —</option>
                                @foreach ($jenisLabels as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="pool-new-rm" class="block text-xs font-bold text-surface-700 mb-1.5">RM Band <span class="text-danger-700" aria-hidden="true">*</span></label>
                            <select id="pool-new-rm" wire:model="newRm" aria-required="true" class="{{ $fieldClass }}">
                                <option value="">— Pilih RM Band —</option>
                                @foreach ($rmLevels as $rm)
                                    <option value="{{ $rm }}">{{ $rm }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    @error('create')
                        <div role="alert" class="p-3 bg-danger-50 border border-danger-200 rounded-xl text-xs text-danger-700 font-semibold">{{ $message }}</div>
                    @enderror
                </div>
                <div class="px-6 py-4 bg-surface-50/80 border-t border-surface-100 flex items-center justify-end gap-2.5">
                    <x-admin.button variant="secondary" wire:click="closeCreate">Batal</x-admin.button>
                    <x-admin.button wire:click="saveCreate" target="saveCreate" icon="fas fa-check">Simpan Jabatan</x-admin.button>
                </div>
            </div>
        </div>
    @endif

    {{-- Dialog Buka pada Unit --}}
    @if ($openingPosition)
        <div x-data="{ open: true }" @keydown.escape.window="$wire.cancelOpen()" @click.self="$wire.cancelOpen()"
            class="fixed inset-0 z-50 overflow-y-auto bg-surface-900/60 backdrop-blur-xs flex items-center justify-center p-4"
            role="dialog" aria-modal="true" aria-labelledby="pool-open-title" x-trap.noscroll="open" tabindex="-1">
            <div class="bg-white rounded-2xl border border-surface-200 shadow-2xl max-w-lg w-full overflow-hidden">
                <div class="px-6 py-4 border-b border-surface-100 bg-surface-50/50">
                    <h3 id="pool-open-title" class="text-sm font-extrabold text-surface-900">Buka Jabatan pada Unit</h3>
                    <p class="text-[11px] text-surface-500">{{ $openingPosition->nama }} ({{ $openingPosition->rm_level }})</p>
                </div>
                <div class="p-6 space-y-4">
                    <div>
                        <label for="pool-open-unit" class="block text-xs font-bold text-surface-700 mb-1.5">Unit Kerja <span class="text-danger-700" aria-hidden="true">*</span></label>
                        <select id="pool-open-unit" wire:model="openUnitId" aria-required="true" class="{{ $fieldClass }}">
                            <option value="">— Pilih unit kerja —</option>
                            @foreach ($openableUnits as $unit)
                                <option value="{{ $unit->id }}">{{ $unit->nama }}</option>
                            @endforeach
                        </select>
                        <p class="mt-1.5 text-[11px] text-surface-500">Setelah dibuka, karyawan dapat ditambahkan atau diimpor pada jabatan ini di unit tersebut tanpa menghabiskan kuota formasi.</p>
                    </div>
                    @error('open')
                        <div role="alert" class="p-3 bg-danger-50 border border-danger-200 rounded-xl text-xs text-danger-700 font-semibold">{{ $message }}</div>
                    @enderror
                </div>
                <div class="px-6 py-4 bg-surface-50/80 border-t border-surface-100 flex items-center justify-end gap-2.5">
                    <x-admin.button variant="secondary" wire:click="cancelOpen">Batal</x-admin.button>
                    <x-admin.button wire:click="saveOpen" target="saveOpen" icon="fas fa-door-open">Buka pada Unit</x-admin.button>
                </div>
            </div>
        </div>
    @endif

    {{-- Dialog Tutup pada Unit --}}
    @if ($closingPosition && $closingUnit)
        <div x-data="{ open: true }" @keydown.escape.window="$wire.cancelClose()" @click.self="$wire.cancelClose()"
            class="fixed inset-0 z-50 overflow-y-auto bg-surface-900/60 backdrop-blur-xs flex items-center justify-center p-4"
            role="dialog" aria-modal="true" aria-labelledby="pool-close-title" x-trap.noscroll="open" tabindex="-1">
            <div class="bg-white rounded-2xl border border-surface-200 shadow-2xl max-w-lg w-full overflow-hidden">
                <div class="px-6 py-4 border-b border-surface-100 bg-surface-50/50">
                    <h3 id="pool-close-title" class="text-sm font-extrabold text-surface-900">Tutup Jabatan pada Unit</h3>
                    <p class="text-[11px] text-surface-500">{{ $closingPosition->nama }} di {{ $closingUnit->nama }}</p>
                </div>
                <div class="p-6 space-y-4">
                    <div>
                        <label for="pool-close-reason" class="block text-xs font-bold text-surface-700 mb-1.5">Alasan Perubahan <span class="text-danger-700" aria-hidden="true">*</span></label>
                        <textarea id="pool-close-reason" wire:model="closeReason" rows="3" aria-required="true" placeholder="Jelaskan alasan perubahan ini (minimal 10 karakter). Alasan dicatat pada riwayat perubahan master." class="{{ $fieldClass }}"></textarea>
                    </div>
                    @error('close')
                        <div role="alert" class="p-3 bg-danger-50 border border-danger-200 rounded-xl text-xs text-danger-700 font-semibold">{{ $message }}</div>
                    @enderror
                </div>
                <div class="px-6 py-4 bg-surface-50/80 border-t border-surface-100 flex items-center justify-end gap-2.5">
                    <x-admin.button variant="secondary" wire:click="cancelClose">Batal</x-admin.button>
                    <x-admin.button wire:click="saveClose" target="saveClose" icon="fas fa-check">Tutup pada Unit</x-admin.button>
                </div>
            </div>
        </div>
    @endif

    {{-- Dialog Ubah Nama --}}
    @if ($renamingPosition)
        <div x-data="{ open: true }" @keydown.escape.window="$wire.cancelRename()" @click.self="$wire.cancelRename()"
            class="fixed inset-0 z-50 overflow-y-auto bg-surface-900/60 backdrop-blur-xs flex items-center justify-center p-4"
            role="dialog" aria-modal="true" aria-labelledby="pool-rename-title" x-trap.noscroll="open" tabindex="-1">
            <div class="bg-white rounded-2xl border border-surface-200 shadow-2xl max-w-lg w-full overflow-hidden">
                <div class="px-6 py-4 border-b border-surface-100 bg-surface-50/50">
                    <h3 id="pool-rename-title" class="text-sm font-extrabold text-surface-900">Ubah Nama Jabatan</h3>
                    <p class="text-[11px] text-surface-500">Jenis dan RM Band tidak berubah.</p>
                </div>
                <div class="p-6 space-y-4">
                    <div>
                        <label for="pool-rename-nama" class="block text-xs font-bold text-surface-700 mb-1.5">Nama Jabatan <span class="text-danger-700" aria-hidden="true">*</span></label>
                        <input id="pool-rename-nama" type="text" wire:model="renameNama" maxlength="255" autocomplete="off" aria-required="true" class="{{ $fieldClass }}">
                    </div>
                    <div>
                        <label for="pool-rename-reason" class="block text-xs font-bold text-surface-700 mb-1.5">Alasan Perubahan <span class="text-danger-700" aria-hidden="true">*</span></label>
                        <textarea id="pool-rename-reason" wire:model="renameReason" rows="3" aria-required="true" placeholder="Jelaskan alasan perubahan ini (minimal 10 karakter). Alasan dicatat pada riwayat perubahan master." class="{{ $fieldClass }}"></textarea>
                    </div>
                    @error('rename')
                        <div role="alert" class="p-3 bg-danger-50 border border-danger-200 rounded-xl text-xs text-danger-700 font-semibold">{{ $message }}</div>
                    @enderror
                </div>
                <div class="px-6 py-4 bg-surface-50/80 border-t border-surface-100 flex items-center justify-end gap-2.5">
                    <x-admin.button variant="secondary" wire:click="cancelRename">Batal</x-admin.button>
                    <x-admin.button wire:click="saveRename" target="saveRename" icon="fas fa-check">Simpan Nama</x-admin.button>
                </div>
            </div>
        </div>
    @endif

    {{-- Dialog Hapus Jabatan --}}
    @if ($deletingPosition)
        <div x-data="{ open: true }" @keydown.escape.window="$wire.cancelDelete()" @click.self="$wire.cancelDelete()"
            class="fixed inset-0 z-50 overflow-y-auto bg-surface-900/60 backdrop-blur-xs flex items-center justify-center p-4"
            role="dialog" aria-modal="true" aria-labelledby="pool-delete-title" x-trap.noscroll="open" tabindex="-1">
            <div class="bg-white rounded-2xl border border-surface-200 shadow-2xl max-w-lg w-full overflow-hidden">
                <div class="px-6 py-4 border-b border-surface-100 bg-surface-50/50">
                    <h3 id="pool-delete-title" class="text-sm font-extrabold text-surface-900">Hapus Jabatan Penampungan</h3>
                    <p class="text-[11px] text-surface-500">{{ $deletingPosition->nama }}. Hanya jabatan yang belum dibuka pada unit, belum dijabat, dan belum tercatat pada riwayat yang dapat dihapus.</p>
                </div>
                <div class="p-6 space-y-4">
                    <div>
                        <label for="pool-delete-reason" class="block text-xs font-bold text-surface-700 mb-1.5">Alasan Perubahan <span class="text-danger-700" aria-hidden="true">*</span></label>
                        <textarea id="pool-delete-reason" wire:model="deleteReason" rows="3" aria-required="true" placeholder="Jelaskan alasan penghapusan (minimal 10 karakter). Alasan dicatat pada riwayat perubahan master." class="{{ $fieldClass }}"></textarea>
                    </div>
                    @error('delete')
                        <div role="alert" class="p-3 bg-danger-50 border border-danger-200 rounded-xl text-xs text-danger-700 font-semibold">{{ $message }}</div>
                    @enderror
                </div>
                <div class="px-6 py-4 bg-surface-50/80 border-t border-surface-100 flex items-center justify-end gap-2.5">
                    <x-admin.button variant="secondary" wire:click="cancelDelete">Batal</x-admin.button>
                    <x-admin.button variant="danger" wire:click="saveDelete" target="saveDelete" icon="fas fa-trash-can">Hapus Jabatan</x-admin.button>
                </div>
            </div>
        </div>
    @endif
</div>
