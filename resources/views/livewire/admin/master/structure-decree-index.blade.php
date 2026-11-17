{{-- Daftar SK struktur organisasi (DESIGN.md §8.5a). --}}
<div>
    <x-admin.table.card>
        <div class="px-4 py-3 border-b border-surface-200 flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="text-sm font-bold text-surface-900">Surat Keputusan Struktur</h2>
                <p class="text-xs text-surface-500">{{ $decrees->count() }} SK tercatat</p>
            </div>
            <x-admin.button href="{{ route('admin.master.sk.create') }}" icon="fas fa-plus" wire:navigate>Catat SK dari Holding</x-admin.button>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-xs">
                <thead class="bg-surface-50 text-surface-600">
                    <tr>
                        <x-admin.table.th>Nomor SK</x-admin.table.th>
                        <x-admin.table.th>Tentang</x-admin.table.th>
                        <x-admin.table.th>Tanggal SK</x-admin.table.th>
                        <x-admin.table.th>Berlaku</x-admin.table.th>
                        <x-admin.table.th>Status</x-admin.table.th>
                        <x-admin.table.th><span class="sr-only">Aksi</span></x-admin.table.th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-surface-200 text-surface-800 align-top">
                    @forelse ($decrees as $decree)
                        <tr wire:key="decree-{{ $decree->id }}" class="hover:bg-surface-50/75 transition">
                            <td class="px-4 py-3 font-bold whitespace-nowrap">{{ $decree->sk_number }}</td>
                            <td class="px-4 py-3 text-surface-600">{{ $decree->title }}</td>
                            <td class="px-4 py-3 text-surface-600 whitespace-nowrap tabular-nums">{{ $decree->sk_date->translatedFormat('d M Y') }}</td>
                            <td class="px-4 py-3 text-surface-600 whitespace-nowrap tabular-nums">{{ $decree->effective_date->translatedFormat('d M Y') }}</td>
                            <td class="px-4 py-3"><x-admin.decree-status :status="$decree->status" :effective-date="$decree->effective_date" /></td>
                            <td class="px-4 py-3 text-right">
                                <x-admin.button href="{{ route('admin.master.sk.show', $decree) }}" variant="secondary" size="sm" wire:navigate>Lihat</x-admin.button>
                            </td>
                        </tr>
                    @empty
                        <x-admin.table.empty-state :colspan="6" icon="fas fa-file-signature" message="Belum ada SK struktur organisasi." />
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-admin.table.card>
</div>
