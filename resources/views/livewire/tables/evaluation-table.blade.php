<x-admin.table.wrapper :models="$evaluations" searchPlaceholder="Cari bidang tugas, lembaga, 9-box...">
    <x-slot name="header">
        <x-admin.table.th sortable field="tanggal_pelaksanaan_asesmen" :sortField="$sortField" :sortDirection="$sortDirection">
            Periode Asesmen
        </x-admin.table.th>
        <x-admin.table.th sortable field="nilai_tertimbang" :sortField="$sortField" :sortDirection="$sortDirection">
            Nilai Tertimbang
        </x-admin.table.th>
        <x-admin.table.th>9-Box Performance</x-admin.table.th>
        <x-admin.table.th sortable field="bidang_tugas" :sortField="$sortField" :sortDirection="$sortDirection">
            Bidang Tugas
        </x-admin.table.th>
        <x-admin.table.th>Lembaga & Tanggal</x-admin.table.th>
        <x-admin.table.th class="text-center">Aksi</x-admin.table.th>
    </x-slot>

    @forelse ($evaluations as $evaluation)
        <tr class="hover:bg-gray-50 transition-colors">
            <td class="px-4 py-3 whitespace-nowrap text-gray-900 font-medium">
                {{ $evaluation->tanggal_pelaksanaan_asesmen ? \Carbon\Carbon::parse($evaluation->tanggal_pelaksanaan_asesmen)->format('d M Y') : '-' }}
                @if ($evaluation->expired_asesmen)
                    <div class="text-[11px] text-gray-400">s/d {{ \Carbon\Carbon::parse($evaluation->expired_asesmen)->format('d M Y') }}</div>
                @endif
            </td>
            <td class="px-4 py-3 whitespace-nowrap">
                <span class="inline-flex items-center px-2.5 py-1 rounded-lg text-xs font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">
                    {{ is_null($evaluation->nilai_tertimbang) ? '-' : number_format($evaluation->nilai_tertimbang, 2) }}
                </span>
            </td>
            <td class="px-4 py-3">
                <div class="space-y-0.5">
                    <div class="text-gray-700">
                        SMKBK: <span class="font-medium text-gray-900">{{ number_format($evaluation->skor_smkbk_9box ?? 0, 1) }}</span> /
                        CLI: <span class="font-medium text-gray-900">{{ number_format($evaluation->skor_cli_9box ?? 0, 1) }}</span>
                    </div>
                    @if ($evaluation->kategori_9box)
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium bg-indigo-600 text-white">
                            {{ $evaluation->kategori_9box }}
                        </span>
                    @endif
                </div>
            </td>
            <td class="px-4 py-3 text-gray-700">
                {{ $evaluation->bidang_tugas ?? '-' }}
            </td>
            <td class="px-4 py-3 text-gray-600">
                <div>{{ $evaluation->lembaga_asesmen ?? '-' }}</div>
                <div class="text-[11px] text-gray-400">{{ $evaluation->kategori_asesmen ?? '' }}</div>
            </td>
            <td class="px-4 py-3 text-center whitespace-nowrap">
                @include('admin.employees.partials.evaluation-actions', ['row' => $evaluation])
            </td>
        </tr>
    @empty
        <x-admin.table.empty-state colspan="6" message="Belum ada riwayat penilaian." />
    @endforelse
</x-admin.table.wrapper>
