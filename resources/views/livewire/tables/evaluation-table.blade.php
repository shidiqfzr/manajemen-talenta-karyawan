<div class="space-y-4">
    <!-- Controls Header -->
    <div class="flex flex-col sm:flex-row justify-between items-center gap-3">
        <div class="flex items-center gap-2 w-full sm:w-auto">
            <label class="text-xs text-gray-500 font-medium">Tampilkan:</label>
            <select wire:model.live="perPage" class="px-2.5 py-1.5 text-xs border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 bg-white">
                <option value="5">5</option>
                <option value="10">10</option>
                <option value="25">25</option>
            </select>
        </div>

        <div class="w-full sm:w-64">
            <div class="relative">
                <input type="text"
                       wire:model.live.debounce.300ms="search"
                       placeholder="Cari bidang tugas, lembaga, 9-box..."
                       class="w-full pl-8 pr-3 py-1.5 text-xs border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 bg-white placeholder-gray-400">
                <div class="absolute inset-y-0 left-0 pl-2.5 flex items-center pointer-events-none text-gray-400">
                    <i class="fas fa-search text-xs"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Table Container -->
    <div class="overflow-x-auto rounded-lg border border-gray-200 shadow-sm">
        <table class="min-w-full divide-y divide-gray-200 text-xs">
            <thead class="bg-gray-50 text-gray-700 font-semibold">
                <tr>
                    <th scope="col" wire:click="sortBy('tanggal_pelaksanaan_asesmen')" class="px-4 py-3 text-left cursor-pointer hover:bg-gray-100 transition">
                        <div class="flex items-center gap-1">
                            <span>Periode Asesmen</span>
                            @if ($sortField === 'tanggal_pelaksanaan_asesmen')
                                <i class="fas fa-sort-{{ $sortDirection === 'asc' ? 'up' : 'down' }} text-emerald-600"></i>
                            @else
                                <i class="fas fa-sort text-gray-300"></i>
                            @endif
                        </div>
                    </th>
                    <th scope="col" wire:click="sortBy('nilai_tertimbang')" class="px-4 py-3 text-left cursor-pointer hover:bg-gray-100 transition">
                        <div class="flex items-center gap-1">
                            <span>Nilai Tertimbang</span>
                            @if ($sortField === 'nilai_tertimbang')
                                <i class="fas fa-sort-{{ $sortDirection === 'asc' ? 'up' : 'down' }} text-emerald-600"></i>
                            @else
                                <i class="fas fa-sort text-gray-300"></i>
                            @endif
                        </div>
                    </th>
                    <th scope="col" class="px-4 py-3 text-left">9-Box Performance</th>
                    <th scope="col" wire:click="sortBy('bidang_tugas')" class="px-4 py-3 text-left cursor-pointer hover:bg-gray-100 transition">
                        <div class="flex items-center gap-1">
                            <span>Bidang Tugas</span>
                            @if ($sortField === 'bidang_tugas')
                                <i class="fas fa-sort-{{ $sortDirection === 'asc' ? 'up' : 'down' }} text-emerald-600"></i>
                            @else
                                <i class="fas fa-sort text-gray-300"></i>
                            @endif
                        </div>
                    </th>
                    <th scope="col" class="px-4 py-3 text-left">Lembaga & Tanggal</th>
                    <th scope="col" class="px-4 py-3 text-center">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 bg-white">
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
                    <tr>
                        <td colspan="6" class="px-4 py-8 text-center text-gray-400">
                            <i class="fas fa-clipboard-check text-2xl mb-2"></i>
                            <p>Belum ada riwayat penilaian.</p>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <!-- Pagination Footer -->
    <div class="mt-4">
        {{ $evaluations->links() }}
    </div>
</div>
