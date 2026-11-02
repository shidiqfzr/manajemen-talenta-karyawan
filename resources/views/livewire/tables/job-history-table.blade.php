<x-admin.table.wrapper :models="$histories" searchPlaceholder="Cari jabatan, unit, SK...">
    <x-slot name="header">
        <x-admin.table.th sortable field="tmt_awal" :sortField="$sortField" :sortDirection="$sortDirection">
            Periode
        </x-admin.table.th>
        <x-admin.table.th sortable field="jabatan" :sortField="$sortField" :sortDirection="$sortDirection">
            Jabatan
        </x-admin.table.th>
        <x-admin.table.th sortable field="unit_kerja" :sortField="$sortField" :sortDirection="$sortDirection">
            Unit Kerja
        </x-admin.table.th>
        <x-admin.table.th>Level / Golongan</x-admin.table.th>
        <x-admin.table.th>Mutasi</x-admin.table.th>
        <x-admin.table.th>Nomor & Tanggal SK</x-admin.table.th>
        <x-admin.table.th class="text-center">Aksi</x-admin.table.th>
    </x-slot>

    @forelse ($histories as $history)
        <tr class="hover:bg-surface-50 transition-colors">
            <td class="px-4 py-3 whitespace-nowrap text-surface-900 font-medium">
                {{ $history->tmt_awal?->format('d M Y') ?? '-' }} &ndash;
                <span class="{{ is_null($history->tmt_akhir) ? 'text-primary-600 font-semibold' : 'text-surface-600' }}">
                    {{ $history->tmt_akhir?->format('d M Y') ?? 'Sekarang' }}
                </span>
            </td>
            <td class="px-4 py-3 text-surface-900 font-semibold">
                {{ $history->jabatan }}
            </td>
            <td class="px-4 py-3 text-surface-700">
                {{ $history->unit_kerja }}
            </td>
            <td class="px-4 py-3 text-surface-600">
                {{ $history->level ?? '-' }} / {{ $history->golongan ?? '-' }}
            </td>
            <td class="px-4 py-3 whitespace-nowrap">
                @if ($history->jenis_mutasi)
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-info-50 text-info-700 border border-info-200">
                        {{ $history->jenis_mutasi_label }}
                    </span>
                @else
                    <span class="text-surface-500">&mdash;</span>
                @endif
            </td>
            <td class="px-4 py-3 text-surface-600">
                @if ($history->nomor_sk || $history->tanggal_sk)
                    <div class="flex items-center gap-1.5">
                        <span>{{ $history->nomor_sk ?? '&mdash;' }}</span>
                        @if ($history->file_sk)
                            <a href="{{ route('admin.mutasi.downloadSk', $history->id) }}" target="_blank" class="text-danger hover:text-danger-700 transition" title="Unduh Berkas SK PDF (Aman)">
                                <i class="fas fa-file-pdf text-xs" aria-hidden="true"></i>
                            </a>
                        @endif
                    </div>
                    <div class="text-[11px] text-surface-500">{{ $history->tanggal_sk?->format('d M Y') ?? '' }}</div>
                @else
                    <span class="text-surface-500">&mdash;</span>
                @endif
            </td>
            <td class="px-4 py-3 text-center whitespace-nowrap">
                @include('admin.employees.partials.job-history-actions', ['row' => $history])
            </td>
        </tr>
    @empty
        <x-admin.table.empty-state colspan="7" message="Belum ada data riwayat jabatan." icon="fas fa-briefcase" />
    @endforelse
</x-admin.table.wrapper>
