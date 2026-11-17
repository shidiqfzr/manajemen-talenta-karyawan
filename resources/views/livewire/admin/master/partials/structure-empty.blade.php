{{-- Baris kosong tabel Versi Struktur: karena pencarian atau filter, atau karena SK tidak memuat formasi. --}}
@if ($search)
    <x-admin.table.empty-state :colspan="$colspan" icon="fas fa-filter" message="Tidak ada yang cocok dengan pencarian atau filter ini. Ubah kata kunci atau filter.">
        <x-admin.button variant="secondary" size="sm" icon="fas fa-rotate-left" wire:click="resetFilters" target="resetFilters">Reset Filter</x-admin.button>
    </x-admin.table.empty-state>
@else
    <x-admin.table.empty-state :colspan="$colspan" icon="fas fa-sitemap" message="SK ini tidak memuat formasi jabatan." />
@endif