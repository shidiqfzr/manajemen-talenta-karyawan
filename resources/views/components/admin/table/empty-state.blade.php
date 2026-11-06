{{--
    Baris kosong tabel (DESIGN.md §7.5): kalimat yang mengarahkan tindakan, slot opsional untuk tombol tindakan.

    <x-admin.table.empty-state :colspan="4" message="Belum ada data." />
    <x-admin.table.empty-state :colspan="4" icon="fas fa-filter" message="Tidak ada data yang sesuai dengan pencarian atau filter.">
        <x-admin.button variant="secondary" size="sm" wire:click="resetFilters">Reset filter</x-admin.button>
    </x-admin.table.empty-state>
--}}
@props(['colspan' => 1, 'message' => 'Data tidak ditemukan.', 'icon' => 'fas fa-clipboard-check'])

<tr>
    <td colspan="{{ $colspan }}" class="px-4 py-8 text-center text-surface-500">
        <i class="{{ $icon }} text-2xl mb-2" aria-hidden="true"></i>
        <p>{{ $message }}</p>
        @if ($slot->isNotEmpty())
            <div class="mt-3 flex justify-center">{{ $slot }}</div>
        @endif
    </td>
</tr>
