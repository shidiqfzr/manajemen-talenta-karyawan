{{--
    Bilah aksi massal tabel data Livewire (DESIGN.md §7.5 "Seleksi & aksi massal").
    Wilayah aria-live selalu ada agar perubahan jumlah terpilih diumumkan; isinya hanya tampil bila ada pilihan.

    <x-admin.table.bulk-bar
        :actions="$this->bulkActions()"
        :selected="$selected"
        :select-all-matching="$selectAllMatching"
        :selected-count="$this->selectedCount()"
        :matching-count="$this->rows->total()"
        :page-keys="$pageKeys" />

    actions        : list<App\Support\DataTable\BulkAction>; satu tombol per aksi.
    matching-count : jumlah seluruh data yang sesuai pencarian/filter (total paginator, tanpa query tambahan).
    Aksi danger meminta konfirmasi yang menyebut jumlah data; ":count" (jumlah terpilih) dan ":label" pada teks
    konfirmasi diganti otomatis.
--}}
@props([
    'actions' => [],
    'selected' => [],
    'selectAllMatching' => false,
    'selectedCount' => 0,
    'matchingCount' => 0,
    'pageKeys' => [],
])

@php
    $formatCount = fn (int $value) => number_format($value, 0, ',', '.');
    $wholePageSelected = $pageKeys !== [] && array_diff($pageKeys, $selected) === [];
    $offerSelectAll = ! $selectAllMatching && $wholePageSelected && $matchingCount > count($selected);
@endphp

<div aria-live="polite">
    @if ($actions !== [] && $selectedCount > 0)
        <div class="flex flex-wrap items-center justify-between gap-3 px-3 py-2.5 sm:px-4 bg-surface-50 border-b border-surface-200 text-xs text-surface-700">
            <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
                <span class="font-bold text-surface-900 tabular-nums">{{ $formatCount($selectedCount) }} terpilih</span>

                @if ($selectAllMatching)
                    <span>Semua {{ $formatCount($matchingCount) }} data yang sesuai terpilih.</span>
                @elseif ($offerSelectAll)
                    <button type="button" wire:click="selectAllMatchingRows"
                        class="font-semibold text-primary-700 hover:text-primary-800 underline underline-offset-2 cursor-pointer rounded focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500">Pilih semua {{ $formatCount($matchingCount) }} data yang sesuai</button>
                @endif

                <button type="button" wire:click="clearSelection"
                    class="font-semibold text-surface-600 hover:text-surface-900 cursor-pointer rounded focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500">Batalkan pilihan</button>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                @foreach ($actions as $action)
                    @php
                        $confirm = str_replace(
                            [':label', ':count'],
                            [$action->label, $formatCount($selectedCount)],
                            $action->confirmText() ?? 'Jalankan tindakan ":label" untuk :count data terpilih?'
                        );
                    @endphp
                    @if ($action->needsReason())
                        {{-- Aksi yang wajib beralasan: modal dengan kolom alasan (10–500 karakter) menggantikan wire:confirm. --}}
                        <div x-data="{ open: false }" @keydown.escape.window="open = false" wire:key="bulk-{{ $action->name }}">
                            <x-admin.button variant="danger" size="sm" :icon="$action->icon" type="button" @click="open = true">{{ $action->label }}</x-admin.button>

                            <div x-show="open" x-cloak class="fixed inset-0 z-50 overflow-y-auto text-left"
                                role="dialog" aria-modal="true" aria-labelledby="bulk-reason-title-{{ $action->name }}" x-trap.noscroll="open" tabindex="-1">
                                <div class="fixed inset-0 bg-neutral-900/60 backdrop-blur-xs" @click="open = false"></div>
                                <div class="flex min-h-full items-center justify-center p-4 sm:p-0">
                                    <div class="relative w-full sm:max-w-md transform overflow-hidden rounded-2xl bg-white shadow-xl border border-surface-200">
                                        <div class="p-6 space-y-3">
                                            <div class="flex items-start gap-4">
                                                <div class="w-12 h-12 rounded-2xl bg-danger-100 text-danger flex items-center justify-center text-xl shrink-0 border border-danger-200">
                                                    <i class="fas fa-triangle-exclamation" aria-hidden="true"></i>
                                                </div>
                                                <div class="space-y-1.5 flex-1">
                                                    <h3 class="text-lg font-bold text-surface-900" id="bulk-reason-title-{{ $action->name }}">{{ $action->label }} {{ $formatCount($selectedCount) }} Data</h3>
                                                    <p class="text-xs sm:text-sm text-surface-600 leading-relaxed">{{ $confirm }}</p>
                                                </div>
                                            </div>
                                            <div>
                                                <label for="bulk-reason-{{ $action->name }}" class="block text-xs font-semibold text-surface-700 mb-1">Alasan penghapusan <span class="text-danger">*</span></label>
                                                <textarea id="bulk-reason-{{ $action->name }}" rows="3" maxlength="500" wire:model="bulkReason"
                                                    placeholder="Contoh: data ganda hasil impor, karyawan keluar sesuai SK Direksi (minimal 10 karakter)"
                                                    class="w-full rounded-lg border border-surface-300 px-3 py-2 text-sm text-surface-900 placeholder:text-surface-400 focus:outline-none focus:ring-2 focus:ring-primary-500"></textarea>
                                                <p class="mt-1 text-[11px] text-surface-500">Alasan dicatat pada riwayat bersama arsip data karyawan yang dihapus.</p>
                                            </div>
                                        </div>
                                        <div class="px-6 py-4 bg-surface-50 border-t border-surface-200 flex items-center justify-end gap-2.5">
                                            <x-admin.button variant="secondary" type="button" @click="open = false">Batal</x-admin.button>
                                            <x-admin.button variant="danger" type="button" :icon="$action->icon"
                                                wire:click="runBulkAction({{ \Illuminate\Support\Js::from($action->name) }})" target="runBulkAction"
                                                @click="open = false">Ya, {{ $action->label }}</x-admin.button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @elseif ($action->isDanger())
                        <x-admin.button variant="danger" size="sm" :icon="$action->icon"
                            wire:click="runBulkAction({{ \Illuminate\Support\Js::from($action->name) }})" target="runBulkAction"
                            wire:confirm="{{ $confirm }}"
                            wire:key="bulk-{{ $action->name }}">{{ $action->label }}</x-admin.button>
                    @else
                        <x-admin.button variant="secondary" size="sm" :icon="$action->icon"
                            wire:click="runBulkAction({{ \Illuminate\Support\Js::from($action->name) }})" target="runBulkAction"
                            wire:key="bulk-{{ $action->name }}">{{ $action->label }}</x-admin.button>
                    @endif
                @endforeach
            </div>
        </div>
    @endif
</div>
