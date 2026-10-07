@extends('layouts.admin')

@section('content')
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-black text-slate-800">Master Unit Kerja</h1>
            <p class="text-xs text-slate-500">Kelola daftar 43 unit kerja PTPN IV Regional V</p>
        </div>
    </div>

    <div class="bg-white rounded-2xl border border-slate-200/80 p-6 shadow-xs">
        <div class="overflow-x-auto">
            <table class="w-full text-xs text-left">
                <thead class="bg-slate-50 text-slate-700 font-bold uppercase text-[10px]">
                    <tr>
                        <th class="p-3">Nama Unit</th>
                        <th class="p-3">Wilayah</th>
                        <th class="p-3">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 font-medium">
                    @forelse($units as $unit)
                        <tr>
                            <td class="p-3 font-bold text-slate-800">{{ $unit->nama }}</td>
                            <td class="p-3 text-slate-600">{{ $unit->wilayah }}</td>
                            <td class="p-3">
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold {{ $unit->is_active ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-600' }}">
                                    {{ $unit->is_active ? 'Aktif' : 'Non-Aktif' }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="p-4 text-center text-slate-400">Belum ada unit kerja.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
