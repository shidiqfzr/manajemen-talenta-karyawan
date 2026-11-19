{{--
    Kartu tabel data Livewire (DESIGN.md §7.5): wadah toolbar, bilah aksi massal, tabel, dan footer paginasi.
    Pemberitahuan hasil aksi tabel tampil di bagian atas kartu.

    <x-admin.table.card :notice="$tableNotice">
        <x-admin.table.toolbar ... />
        <x-admin.table.bulk-bar ... />
        <div class="overflow-x-auto"><table> ... </table></div>
        <x-admin.table.pagination ... />
    </x-admin.table.card>
--}}
@props(['notice' => null])

<div {{ $attributes->merge(['class' => 'bg-white rounded-2xl border border-surface-200 shadow-xs overflow-hidden']) }}>
    <x-admin.table.notice :notice="$notice" />

    {{ $slot }}
</div>
