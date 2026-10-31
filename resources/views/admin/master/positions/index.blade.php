@extends('layouts.admin')

@section('content')
@php
    $levelOptions = collect(\App\Models\Position::LEVEL_LIST)->map(fn ($label, $value) => ['value' => $value, 'label' => $label])->values()->all();
    $rmOptions = collect([['value' => '', 'label' => '— (Karpel tidak memakai RM Band)']])
        ->merge(collect(['RM-1', 'RM-2', 'RM-3'])->map(fn ($rm) => ['value' => $rm, 'label' => \App\Models\Position::RM_LEVEL_LIST[$rm]]))
        ->values()->all();
    $rmOptionsFor = function (?string $current) use ($rmOptions): array {
        if ($current && ! collect($rmOptions)->contains('value', $current)) {
            $rmOptions[] = ['value' => $current, 'label' => $current.' (nilai saat ini)'];
        }

        return $rmOptions;
    };
    $reasonHint = 'Jelaskan alasan perubahan ini (minimal 10 karakter). Alasan dicatat pada riwayat perubahan master.';
@endphp
<div class="space-y-6">
    <div class="flex items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-black text-surface-800">Master Jabatan &amp; Posisi</h1>
            <p class="text-xs text-surface-500">Kelola formasi jabatan, bidang fungsional, dan eselon RM Band</p>
        </div>
        <button type="button"
            @click="$dispatch('master-action', {{ \Illuminate\Support\Js::from([
                'title' => 'Tambah Jabatan',
                'description' => 'Jabatan baru langsung aktif. Kursi per unit diatur pada layar Formasi.',
                'url' => route('admin.master.positions.store'),
                'method' => 'POST',
                'submit' => 'Tambah Jabatan',
                'tone' => 'primary',
                'fields' => [
                    ['name' => 'nama', 'label' => 'Nama Jabatan', 'type' => 'text', 'value' => '', 'required' => true],
                    ['name' => 'level', 'label' => 'Strata', 'type' => 'select', 'value' => 'Karpim', 'required' => true, 'options' => $levelOptions],
                    ['name' => 'rm_level', 'label' => 'RM Band', 'type' => 'select', 'value' => 'RM-3', 'required' => false, 'options' => $rmOptions],
                ],
            ]) }})"
            class="inline-flex items-center gap-2 px-4 py-2 bg-primary-600 hover:bg-primary-700 text-white text-xs font-bold rounded-xl shadow-md transition cursor-pointer">
            <i class="fas fa-plus text-xs" aria-hidden="true"></i>
            <span>Tambah Jabatan</span>
        </button>
    </div>

    <x-admin.master.section-nav />

    @if (session('success'))
        <div role="status" class="p-4 bg-primary-100 text-primary-800 border border-primary-200 rounded-lg shadow-sm text-xs font-semibold">
            {{ session('success') }}
        </div>
    @endif
    @if (session('error'))
        <div role="alert" class="p-4 bg-danger-50 text-danger-700 border border-danger-200 rounded-lg shadow-sm text-xs font-semibold">
            {{ session('error') }}
        </div>
    @endif

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
                        <th scope="col" class="p-3 text-right">Aksi</th>
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
                            <td class="p-3">
                                <div class="flex items-center justify-end gap-1.5">
                                    <button type="button"
                                        @click="$dispatch('master-action', {{ \Illuminate\Support\Js::from([
                                            'title' => 'Ubah Jabatan',
                                            'description' => $position->nama,
                                            'url' => route('admin.master.positions.update', $position),
                                            'method' => 'PUT',
                                            'submit' => 'Simpan Perubahan',
                                            'tone' => 'primary',
                                            'fields' => [
                                                ['name' => 'nama', 'label' => 'Nama Jabatan', 'type' => 'text', 'value' => $position->nama, 'required' => true],
                                                ['name' => 'level', 'label' => 'Strata', 'type' => 'select', 'value' => $position->level, 'required' => true, 'options' => $levelOptions],
                                                ['name' => 'rm_level', 'label' => 'RM Band (berlaku untuk seluruh unit pemakai jabatan)', 'type' => 'select', 'value' => (string) $position->rm_level, 'required' => false, 'options' => $rmOptionsFor($position->rm_level)],
                                                ['name' => 'is_active', 'type' => 'hidden', 'value' => $position->is_active ? '1' : '0'],
                                                ['name' => 'reason', 'label' => 'Alasan Perubahan (wajib bila mengubah nama atau RM Band)', 'type' => 'textarea', 'value' => '', 'required' => false, 'placeholder' => $reasonHint],
                                            ],
                                        ]) }})"
                                        class="px-2.5 py-1 border border-surface-200 text-surface-700 hover:bg-surface-100 rounded-lg text-[11px] font-semibold transition cursor-pointer">
                                        Ubah
                                    </button>
                                    <button type="button"
                                        @click="$dispatch('master-action', {{ \Illuminate\Support\Js::from([
                                            'title' => $position->is_active ? 'Nonaktifkan Jabatan' : 'Aktifkan Jabatan',
                                            'description' => $position->is_active
                                                ? $position->nama.' tidak akan muncul pada pilihan penempatan baru. Jabatan yang masih dipegang pegawai tidak dapat dinonaktifkan.'
                                                : $position->nama.' akan kembali muncul pada pilihan penempatan.',
                                            'url' => route('admin.master.positions.toggle', $position),
                                            'method' => 'POST',
                                            'submit' => $position->is_active ? 'Nonaktifkan' : 'Aktifkan',
                                            'tone' => $position->is_active ? 'danger' : 'primary',
                                            'fields' => $position->is_active
                                                ? [['name' => 'reason', 'label' => 'Alasan Penonaktifan', 'type' => 'textarea', 'value' => '', 'required' => true, 'placeholder' => $reasonHint]]
                                                : [],
                                        ]) }})"
                                        class="px-2.5 py-1 border border-surface-200 text-surface-700 hover:bg-surface-100 rounded-lg text-[11px] font-semibold transition cursor-pointer">
                                        {{ $position->is_active ? 'Nonaktifkan' : 'Aktifkan' }}
                                    </button>
                                    <button type="button"
                                        @click="$dispatch('master-action', {{ \Illuminate\Support\Js::from([
                                            'title' => 'Hapus Jabatan',
                                            'description' => $position->nama.' hanya dapat dihapus bila tidak dipegang pegawai, bukan kursi formasi, dan tidak tercatat pada riwayat jabatan. Bila masih dipakai, gunakan Nonaktifkan.',
                                            'url' => route('admin.master.positions.destroy', $position),
                                            'method' => 'DELETE',
                                            'submit' => 'Hapus Jabatan',
                                            'tone' => 'danger',
                                            'fields' => [['name' => 'reason', 'label' => 'Alasan Penghapusan', 'type' => 'textarea', 'value' => '', 'required' => true, 'placeholder' => $reasonHint]],
                                        ]) }})"
                                        class="px-2.5 py-1 border border-danger-200 text-danger-700 hover:bg-danger-50 rounded-lg text-[11px] font-semibold transition cursor-pointer">
                                        Hapus
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="p-4 text-center text-surface-500">Belum ada posisi jabatan.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if (method_exists($positions, 'links'))
            <div class="mt-4">{{ $positions->links() }}</div>
        @endif
    </div>
</div>

<x-admin.master.action-dialog />
@endsection
