<x-admin.table.wrapper :models="$trainings" searchPlaceholder="Cari judul, penyelenggara...">
    <x-slot name="header">
        <x-admin.table.th sortable field="judul" :sortField="$sortField" :sortDirection="$sortDirection">
            Judul Pelatihan
        </x-admin.table.th>
        <x-admin.table.th sortable field="tanggal_mulai" :sortField="$sortField" :sortDirection="$sortDirection">
            Tanggal
        </x-admin.table.th>
        <x-admin.table.th sortable field="penyelenggara" :sortField="$sortField" :sortDirection="$sortDirection">
            Penyelenggara
        </x-admin.table.th>
        <x-admin.table.th>Jenis / Metode</x-admin.table.th>
        <x-admin.table.th class="text-center">Sertifikat</x-admin.table.th>
        <x-admin.table.th class="text-center">Aksi</x-admin.table.th>
    </x-slot>

    @forelse ($trainings as $training)
        @php
            $empPivot = $training->employees->first();
            $sertifikat = $empPivot?->pivot?->sertifikat;
        @endphp
        <tr class="hover:bg-gray-50 transition-colors">
            <td class="px-4 py-3 text-gray-900 font-semibold">
                {{ $training->judul }}
            </td>
            <td class="px-4 py-3 whitespace-nowrap text-gray-700">
                {{ $training->tanggal_mulai?->format('d M Y') ?? '-' }}
                @if ($training->tanggal_akhir)
                    &ndash; {{ $training->tanggal_akhir->format('d M Y') }}
                @endif
            </td>
            <td class="px-4 py-3 text-gray-700">
                {{ $training->penyelenggara ?? '-' }}
            </td>
            <td class="px-4 py-3 text-gray-600">
                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-700">
                    {{ $training->jenis ?? '-' }}
                </span>
                @if ($training->metode)
                    <span class="text-xs text-gray-400 ml-1">({{ $training->metode }})</span>
                @endif
            </td>
            <td class="px-4 py-3 text-center whitespace-nowrap">
                @if ($sertifikat)
                    <a href="{{ asset('storage/' . $sertifikat) }}" target="_blank"
                       class="inline-flex items-center px-2.5 py-1 bg-green-100 text-green-700 text-xs font-medium rounded-full hover:bg-green-200 transition">
                        <i class="fas fa-download mr-1"></i>Unduh
                    </a>
                @else
                    <span class="inline-flex items-center px-2.5 py-1 bg-amber-50 text-amber-600 text-xs font-medium rounded-full border border-amber-200">
                        <i class="fas fa-minus-circle mr-1"></i>Tidak Ada
                    </span>
                @endif
            </td>
            <td class="px-4 py-3 text-center whitespace-nowrap">
                @include('admin.employees.partials.training-actions', ['row' => $training])
            </td>
        </tr>
    @empty
        <x-admin.table.empty-state colspan="6" message="Belum ada riwayat pelatihan." icon="fas fa-chalkboard-teacher" />
    @endforelse
</x-admin.table.wrapper>
