<div class="space-y-4">
    <!-- Controls Header -->
    <div class="flex flex-col sm:flex-row justify-between items-center gap-3">
        <div class="flex items-center gap-2 w-full sm:w-auto">
            <label class="text-xs text-gray-500 font-medium">Tampilkan:</label>
            <select wire:model.live="perPage" class="px-2.5 py-1.5 text-xs border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 bg-white">
                <option value="5">5</option>
                <option value="10">10</option>
                <option value="25">25</option>
            </select>
        </div>

        <div class="w-full sm:w-64">
            <div class="relative">
                <input type="text"
                       wire:model.live.debounce.300ms="search"
                       placeholder="Cari judul, penyelenggara..."
                       class="w-full pl-8 pr-3 py-1.5 text-xs border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 bg-white placeholder-gray-400">
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
                    <th scope="col" wire:click="sortBy('judul')" class="px-4 py-3 text-left cursor-pointer hover:bg-gray-100 transition">
                        <div class="flex items-center gap-1">
                            <span>Judul Pelatihan</span>
                            @if ($sortField === 'judul')
                                <i class="fas fa-sort-{{ $sortDirection === 'asc' ? 'up' : 'down' }} text-indigo-600"></i>
                            @else
                                <i class="fas fa-sort text-gray-300"></i>
                            @endif
                        </div>
                    </th>
                    <th scope="col" wire:click="sortBy('tanggal_mulai')" class="px-4 py-3 text-left cursor-pointer hover:bg-gray-100 transition">
                        <div class="flex items-center gap-1">
                            <span>Tanggal</span>
                            @if ($sortField === 'tanggal_mulai')
                                <i class="fas fa-sort-{{ $sortDirection === 'asc' ? 'up' : 'down' }} text-indigo-600"></i>
                            @else
                                <i class="fas fa-sort text-gray-300"></i>
                            @endif
                        </div>
                    </th>
                    <th scope="col" wire:click="sortBy('penyelenggara')" class="px-4 py-3 text-left cursor-pointer hover:bg-gray-100 transition">
                        <div class="flex items-center gap-1">
                            <span>Penyelenggara</span>
                            @if ($sortField === 'penyelenggara')
                                <i class="fas fa-sort-{{ $sortDirection === 'asc' ? 'up' : 'down' }} text-indigo-600"></i>
                            @else
                                <i class="fas fa-sort text-gray-300"></i>
                            @endif
                        </div>
                    </th>
                    <th scope="col" class="px-4 py-3 text-left">Jenis / Metode</th>
                    <th scope="col" class="px-4 py-3 text-center">Sertifikat</th>
                    <th scope="col" class="px-4 py-3 text-center">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 bg-white">
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
                    <tr>
                        <td colspan="6" class="px-4 py-8 text-center text-gray-400">
                            <i class="fas fa-chalkboard-teacher text-2xl mb-2"></i>
                            <p>Belum ada riwayat pelatihan.</p>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <!-- Pagination Footer -->
    <div class="mt-4">
        {{ $trainings->links() }}
    </div>
</div>
