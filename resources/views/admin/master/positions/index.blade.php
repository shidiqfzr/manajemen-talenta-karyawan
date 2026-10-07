@extends('layouts.admin')

@section('content')
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-black text-slate-800">Master Jabatan &amp; Posisi</h1>
            <p class="text-xs text-slate-500">Kelola formasi jabatan, bidang fungsional, dan eselon RM Band</p>
        </div>
    </div>

    <div class="bg-white rounded-2xl border border-slate-200/80 p-6 shadow-xs">
        <div class="overflow-x-auto">
            <table class="w-full text-xs text-left">
                <thead class="bg-slate-50 text-slate-700 font-bold uppercase text-[10px]">
                    <tr>
                        <th class="p-3">Nama Jabatan</th>
                        <th class="p-3">Bidang</th>
                        <th class="p-3">Strata</th>
                        <th class="p-3">RM Band</th>
                        <th class="p-3">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 font-medium">
                    @forelse($positions as $position)
                        <tr>
                            <td class="p-3 font-bold text-slate-800">{{ $position->nama }}</td>
                            <td class="p-3 text-slate-600">{{ $position->bidang }}</td>
                            <td class="p-3 text-slate-600">{{ $position->level }}</td>
                            <td class="p-3">
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-amber-50 text-amber-800 border border-amber-200">
                                    {{ $position->rm_level ?? '-' }}
                                </span>
                            </td>
                            <td class="p-3">
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold {{ $position->is_active ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-600' }}">
                                    {{ $position->is_active ? 'Aktif' : 'Non-Aktif' }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="p-4 text-center text-slate-400">Belum ada posisi jabatan.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
