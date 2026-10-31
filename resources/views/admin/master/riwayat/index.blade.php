@extends('layouts.admin')

@section('content')
@php
    $fieldClass = 'w-full px-3 py-2 bg-surface-50/70 border border-surface-200 rounded-xl text-xs font-medium text-surface-800 focus:bg-white focus:outline-none focus:ring-2 focus:ring-primary-500/30 focus:border-primary-500 transition';
@endphp
<div class="space-y-6">
    <div>
        <h1 class="text-2xl font-black text-surface-800">Riwayat Perubahan Master</h1>
        <p class="text-xs text-surface-500">Catatan siapa mengubah apa pada unit kerja, jabatan, dan kursi formasi. Catatan tidak dapat diubah atau dihapus.</p>
    </div>

    <x-admin.master.section-nav />

    <form method="GET" action="{{ route('admin.master.riwayat.index') }}" class="bg-white rounded-2xl border border-surface-200/80 p-5 shadow-xs grid grid-cols-1 md:grid-cols-5 gap-3 items-end">
        <div>
            <label for="riwayat-entitas" class="block text-xs font-bold text-surface-700 mb-1.5">Jenis Data</label>
            <select id="riwayat-entitas" name="entity_type" class="{{ $fieldClass }}">
                <option value="">Semua</option>
                @foreach ($entityLabels as $value => $label)
                    <option value="{{ $value }}" @selected(($filters['entity_type'] ?? '') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="riwayat-admin" class="block text-xs font-bold text-surface-700 mb-1.5">Admin</label>
            <select id="riwayat-admin" name="user_id" class="{{ $fieldClass }}">
                <option value="">Semua</option>
                @foreach ($admins as $admin)
                    <option value="{{ $admin->id }}" @selected((string) ($filters['user_id'] ?? '') === (string) $admin->id)>{{ $admin->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="riwayat-dari" class="block text-xs font-bold text-surface-700 mb-1.5">Dari Tanggal</label>
            <input id="riwayat-dari" type="date" name="from" value="{{ $filters['from'] ?? '' }}" class="{{ $fieldClass }}">
        </div>
        <div>
            <label for="riwayat-sampai" class="block text-xs font-bold text-surface-700 mb-1.5">Sampai Tanggal</label>
            <input id="riwayat-sampai" type="date" name="to" value="{{ $filters['to'] ?? '' }}" class="{{ $fieldClass }}">
        </div>
        <div class="flex gap-2">
            <button type="submit" class="px-4 py-2 bg-primary-600 hover:bg-primary-700 text-white text-xs font-bold rounded-xl shadow-md transition cursor-pointer">Terapkan</button>
            <a href="{{ route('admin.master.riwayat.index') }}" class="px-4 py-2 border border-surface-200 text-surface-600 hover:bg-surface-100 text-xs font-semibold rounded-xl transition">Atur Ulang</a>
        </div>
    </form>

    <div class="bg-white rounded-2xl border border-surface-200/80 p-6 shadow-xs">
        <div class="overflow-x-auto">
            <table class="w-full text-xs text-left">
                <thead class="bg-surface-50 text-surface-700 font-bold uppercase text-[10px]">
                    <tr>
                        <th scope="col" class="p-3">Waktu</th>
                        <th scope="col" class="p-3">Admin</th>
                        <th scope="col" class="p-3">Aksi</th>
                        <th scope="col" class="p-3">Data</th>
                        <th scope="col" class="p-3">Perubahan</th>
                        <th scope="col" class="p-3">Alasan</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-surface-100 font-medium align-top">
                    @forelse ($logs as $log)
                        <tr>
                            <td class="p-3 text-surface-600 whitespace-nowrap">{{ $log->created_at->translatedFormat('d M Y H:i') }}</td>
                            <td class="p-3 text-surface-800">{{ $log->user_name }}</td>
                            <td class="p-3 font-bold text-surface-800">{{ \App\Models\MasterAuditLog::actionLabel($log->action) }}</td>
                            <td class="p-3 text-surface-600">
                                <span class="text-[10px] font-bold uppercase text-surface-500">{{ $entityLabels[$log->entity_type] ?? $log->entity_type }}</span><br>
                                {{ $log->entity_label }}
                            </td>
                            <td class="p-3 text-surface-600">
                                @forelse ($log->changeSummary() as $line)
                                    <div>{{ $line }}</div>
                                @empty
                                    -
                                @endforelse
                            </td>
                            <td class="p-3 text-surface-600">{{ $log->reason ?: '-' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="p-4 text-center text-surface-500">Belum ada perubahan master yang tercatat.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">{{ $logs->links() }}</div>
    </div>
</div>
@endsection
