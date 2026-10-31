@extends('layouts.admin')

@section('content')
<div class="space-y-6">
    <div>
        <h1 class="text-2xl font-black text-surface-800">Formasi Unit Kerja</h1>
        <p class="text-xs text-surface-500">Kelola kursi formasi per unit: jabatan, bidang, dan kuota. Setiap perubahan dicatat pada riwayat perubahan master.</p>
    </div>

    <x-admin.master.section-nav />

    <livewire:admin.master.formasi-editor />
</div>
@endsection
