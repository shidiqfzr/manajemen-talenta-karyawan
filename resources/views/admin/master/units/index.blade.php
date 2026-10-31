@extends('layouts.admin')

@section('content')
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-black text-surface-800">Master Unit Kerja</h1>
            <p class="text-xs text-surface-500">Kelola daftar unit kerja PTPN IV Regional V</p>
        </div>
    </div>

    <div class="bg-white rounded-2xl border border-surface-200/80 p-6 shadow-xs">
        <div class="overflow-x-auto">
            <table class="w-full text-xs text-left">
                <thead class="bg-surface-50 text-surface-700 font-bold uppercase text-[10px]">
                    <tr>
                        <th scope="col" class="p-3">Nama Unit</th>
                        <th scope="col" class="p-3">Wilayah</th>
                        <th scope="col" class="p-3">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-surface-100 font-medium">
                    @forelse($units as $unit)
                        <tr>
                            <td class="p-3 font-bold text-surface-800">{{ $unit->nama }}</td>
                            <td class="p-3 text-surface-600">{{ $unit->wilayah }}</td>
                            <td class="p-3">
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold {{ $unit->is_active ? 'bg-primary-100 text-primary-800' : 'bg-surface-100 text-surface-600' }}">
                                    {{ $unit->is_active ? 'Aktif' : 'Non-Aktif' }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="p-4 text-center text-surface-500">Belum ada unit kerja.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
