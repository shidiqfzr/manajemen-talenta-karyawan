@extends('layouts.admin')

@section('content')
@php
    $wilayahOptions = collect(\App\Models\Unit::WILAYAH_LIST)->map(fn ($w) => ['value' => $w, 'label' => $w])->values()->all();
    $reasonHint = 'Jelaskan alasan perubahan ini (minimal 10 karakter). Alasan dicatat pada riwayat perubahan master.';
@endphp
<div class="space-y-6">
    <div class="flex items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-black text-surface-800">Master Unit Kerja</h1>
            <p class="text-xs text-surface-500">Kelola daftar unit kerja PTPN IV Regional V</p>
        </div>
        <button type="button"
            @click="$dispatch('master-action', {{ \Illuminate\Support\Js::from([
                'title' => 'Tambah Unit Kerja',
                'description' => 'Unit baru langsung aktif dan dapat diberi kursi formasi.',
                'url' => route('admin.master.units.store'),
                'method' => 'POST',
                'submit' => 'Tambah Unit',
                'tone' => 'primary',
                'fields' => [
                    ['name' => 'nama', 'label' => 'Nama Unit Kerja', 'type' => 'text', 'value' => '', 'required' => true],
                    ['name' => 'kode', 'label' => 'Kode Singkatan', 'type' => 'text', 'value' => '', 'required' => false],
                    ['name' => 'wilayah', 'label' => 'Wilayah Operasional', 'type' => 'select', 'value' => $wilayahOptions[1]['value'], 'required' => true, 'options' => $wilayahOptions],
                ],
            ]) }})"
            class="inline-flex items-center gap-2 px-4 py-2 bg-primary-600 hover:bg-primary-700 text-white text-xs font-bold rounded-xl shadow-md transition cursor-pointer">
            <i class="fas fa-plus text-xs" aria-hidden="true"></i>
            <span>Tambah Unit Kerja</span>
        </button>
    </div>

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
                        <th scope="col" class="p-3">Nama Unit</th>
                        <th scope="col" class="p-3">Wilayah</th>
                        <th scope="col" class="p-3">Status</th>
                        <th scope="col" class="p-3 text-right">Aksi</th>
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
                            <td class="p-3">
                                <div class="flex items-center justify-end gap-1.5">
                                    <button type="button"
                                        @click="$dispatch('master-action', {{ \Illuminate\Support\Js::from([
                                            'title' => 'Ubah Unit Kerja',
                                            'description' => $unit->nama,
                                            'url' => route('admin.master.units.update', $unit),
                                            'method' => 'PUT',
                                            'submit' => 'Simpan Perubahan',
                                            'tone' => 'primary',
                                            'fields' => [
                                                ['name' => 'nama', 'label' => 'Nama Unit Kerja', 'type' => 'text', 'value' => $unit->nama, 'required' => true],
                                                ['name' => 'kode', 'label' => 'Kode Singkatan', 'type' => 'text', 'value' => (string) $unit->kode, 'required' => false],
                                                ['name' => 'wilayah', 'label' => 'Wilayah Operasional', 'type' => 'select', 'value' => $unit->wilayah, 'required' => true, 'options' => $wilayahOptions],
                                                ['name' => 'urutan', 'label' => 'Urutan Tampil', 'type' => 'number', 'value' => (string) $unit->urutan, 'required' => false],
                                                ['name' => 'is_active', 'type' => 'hidden', 'value' => $unit->is_active ? '1' : '0'],
                                                ['name' => 'reason', 'label' => 'Alasan Perubahan (wajib bila mengubah nama)', 'type' => 'textarea', 'value' => '', 'required' => false, 'placeholder' => $reasonHint],
                                            ],
                                        ]) }})"
                                        class="px-2.5 py-1 border border-surface-200 text-surface-700 hover:bg-surface-100 rounded-lg text-[11px] font-semibold transition cursor-pointer">
                                        Ubah
                                    </button>
                                    <button type="button"
                                        @click="$dispatch('master-action', {{ \Illuminate\Support\Js::from([
                                            'title' => $unit->is_active ? 'Nonaktifkan Unit Kerja' : 'Aktifkan Unit Kerja',
                                            'description' => $unit->is_active
                                                ? $unit->nama.' tidak akan muncul pada pilihan penempatan baru. Unit yang masih memiliki pegawai tidak dapat dinonaktifkan.'
                                                : $unit->nama.' akan kembali muncul pada pilihan penempatan.',
                                            'url' => route('admin.master.units.toggle', $unit),
                                            'method' => 'POST',
                                            'submit' => $unit->is_active ? 'Nonaktifkan' : 'Aktifkan',
                                            'tone' => $unit->is_active ? 'danger' : 'primary',
                                            'fields' => $unit->is_active
                                                ? [['name' => 'reason', 'label' => 'Alasan Penonaktifan', 'type' => 'textarea', 'value' => '', 'required' => true, 'placeholder' => $reasonHint]]
                                                : [],
                                        ]) }})"
                                        class="px-2.5 py-1 border border-surface-200 text-surface-700 hover:bg-surface-100 rounded-lg text-[11px] font-semibold transition cursor-pointer">
                                        {{ $unit->is_active ? 'Nonaktifkan' : 'Aktifkan' }}
                                    </button>
                                    <button type="button"
                                        @click="$dispatch('master-action', {{ \Illuminate\Support\Js::from([
                                            'title' => 'Hapus Unit Kerja',
                                            'description' => $unit->nama.' hanya dapat dihapus bila tidak dipakai pegawai, kursi formasi, maupun riwayat jabatan. Bila masih dipakai, gunakan Nonaktifkan.',
                                            'url' => route('admin.master.units.destroy', $unit),
                                            'method' => 'DELETE',
                                            'submit' => 'Hapus Unit',
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
                            <td colspan="4" class="p-4 text-center text-surface-500">Belum ada unit kerja.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if (method_exists($units, 'links'))
            <div class="mt-4">{{ $units->links() }}</div>
        @endif
    </div>
</div>

<x-admin.master.action-dialog />
@endsection
