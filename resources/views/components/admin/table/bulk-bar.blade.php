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
                    @if ($action->isDanger())
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
