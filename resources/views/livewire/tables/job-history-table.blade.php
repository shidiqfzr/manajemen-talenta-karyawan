<div class="space-y-4">
    <!-- Controls Header -->
    <div class="flex flex-col sm:flex-row justify-between items-center gap-3">
        <div class="flex items-center gap-2 w-full sm:w-auto">
            <label class="text-xs text-gray-500 font-medium">Tampilkan:</label>
            <select wire:model.live="perPage" class="px-2.5 py-1.5 text-xs border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500 bg-white">
                <option value="5">5</option>
                <option value="10">10</option>
                <option value="25">25</option>
            </select>
        </div>

        <div class="w-full sm:w-64">
            <div class="relative">
                <input type="text"
                       wire:model.live.debounce.300ms="search"
                       placeholder="Cari jabatan, unit, SK..."
                       class="w-full pl-8 pr-3 py-1.5 text-xs border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500 bg-white placeholder-gray-400">
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
                    <th scope="col" wire:click="sortBy('tmt_awal')" class="px-4 py-3 text-left cursor-pointer hover:bg-gray-100 transition">
                        <div class="flex items-center gap-1">
                            <span>Periode</span>
                            @if ($sortField === 'tmt_awal')
                                <i class="fas fa-sort-{{ $sortDirection === 'asc' ? 'up' : 'down' }} text-green-600"></i>
                            @else
                                <i class="fas fa-sort text-gray-300"></i>
                            @endif
                        </div>
                    </th>
                    <th scope="col" wire:click="sortBy('jabatan')" class="px-4 py-3 text-left cursor-pointer hover:bg-gray-100 transition">
                        <div class="flex items-center gap-1">
                            <span>Jabatan</span>
                            @if ($sortField === 'jabatan')
                                <i class="fas fa-sort-{{ $sortDirection === 'asc' ? 'up' : 'down' }} text-green-600"></i>
                            @else
                                <i class="fas fa-sort text-gray-300"></i>
                            @endif
                        </div>
                    </th>
                    <th scope="col" wire:click="sortBy('unit_kerja')" class="px-4 py-3 text-left cursor-pointer hover:bg-gray-100 transition">
                        <div class="flex items-center gap-1">
                            <span>Unit Kerja</span>
                            @if ($sortField === 'unit_kerja')
                                <i class="fas fa-sort-{{ $sortDirection === 'asc' ? 'up' : 'down' }} text-green-600"></i>
                            @else
                                <i class="fas fa-sort text-gray-300"></i>
                            @endif
                        </div>
                    </th>
                    <th scope="col" class="px-4 py-3 text-left">Level / Golongan</th>
                    <th scope="col" class="px-4 py-3 text-left">Mutasi</th>
                    <th scope="col" class="px-4 py-3 text-left">Nomor & Tanggal SK</th>
                    <th scope="col" class="px-4 py-3 text-center">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 bg-white">
                @forelse ($histories as $history)
                    <tr class="hover:bg-gray-50 transition-colors">
                        <td class="px-4 py-3 whitespace-nowrap text-gray-900 font-medium">
                            {{ $history->tmt_awal?->format('d M Y') ?? '-' }} &ndash;
                            <span class="{{ is_null($history->tmt_akhir) ? 'text-green-600 font-semibold' : 'text-gray-600' }}">
                                {{ $history->tmt_akhir?->format('d M Y') ?? 'Sekarang' }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-gray-900 font-semibold">
                            {{ $history->jabatan }}
                        </td>
                        <td class="px-4 py-3 text-gray-700">
                            {{ $history->unit_kerja }}
                        </td>
                        <td class="px-4 py-3 text-gray-600">
                            {{ $history->level ?? '-' }} / {{ $history->golongan ?? '-' }}
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap">
                            @if ($history->jenis_mutasi)
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-blue-50 text-blue-700 border border-blue-200">
                                    {{ $history->jenis_mutasi_label }}
                                </span>
                            @else
                                <span class="text-gray-400">&mdash;</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-gray-600">
                            @if ($history->nomor_sk || $history->tanggal_sk)
                                <div>{{ $history->nomor_sk ?? '&mdash;' }}</div>
                                <div class="text-[11px] text-gray-400">{{ $history->tanggal_sk?->format('d M Y') ?? '' }}</div>
                            @else
                                <span class="text-gray-400">&mdash;</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-center whitespace-nowrap">
                            @include('admin.employees.partials.job-history-actions', ['row' => $history])
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-8 text-center text-gray-400">
                            <i class="fas fa-briefcase text-2xl mb-2"></i>
                            <p>Belum ada data riwayat jabatan.</p>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <!-- Pagination Footer -->
    <div class="mt-4">
        {{ $histories->links() }}
    </div>
</div>
