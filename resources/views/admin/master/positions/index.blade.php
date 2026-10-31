@extends('layouts.admin')

@section('content')
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-black text-surface-800">Master Jabatan &amp; Posisi</h1>
            <p class="text-xs text-surface-500">Kelola formasi jabatan, bidang fungsional, dan eselon RM Band</p>
        </div>
    </div>

    <div class="bg-white rounded-2xl border border-surface-200/80 p-6 shadow-xs">
        <div class="overflow-x-auto">
            <table class="w-full text-xs text-left">
                <thead class="bg-surface-50 text-surface-700 font-bold uppercase text-[10px]">
                    <tr>
                        <th scope="col" class="p-3">Nama Jabatan</th>
                        <th scope="col" class="p-3">Bidang</th>
                        <th scope="col" class="p-3">Strata</th>
                        <th scope="col" class="p-3">RM Band</th>
                        <th scope="col" class="p-3">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-surface-100 font-medium">
                    @forelse($positions as $position)
                        <tr>
                            <td class="p-3 font-bold text-surface-800">{{ $position->nama }}</td>
                            <td class="p-3 text-surface-600">{{ $position->units->pluck('pivot.bidang')->filter()->unique()->sort()->implode(', ') ?: '-' }}</td>
                            <td class="p-3 text-surface-600">{{ $position->level }}</td>
                            <td class="p-3">
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-warning-50 text-warning-800 border border-warning-200">
                                    {{ $position->rm_level ?? '-' }}
                                </span>
                            </td>
                            <td class="p-3">
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold {{ $position->is_active ? 'bg-primary-100 text-primary-800' : 'bg-surface-100 text-surface-600' }}">
                                    {{ $position->is_active ? 'Aktif' : 'Non-Aktif' }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="p-4 text-center text-surface-500">Belum ada posisi jabatan.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
