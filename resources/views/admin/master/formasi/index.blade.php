@extends('layouts.admin')

@section('content')
@php($dialogs = app(\App\Services\Formasi\FormasiDialogs::class))
<div class="space-y-6">
    <x-admin.module-header title="Formasi Unit Kerja" subtitle="Kelola unit kerja dan formasi jabatan. Setiap perubahan tercatat pada Riwayat Perubahan." :breadcrumbs="['Core HR', 'Master Formasi']">
        <x-admin.button icon="fas fa-plus" @click="$dispatch('master-action', {{ \Illuminate\Support\Js::from($dialogs->createUnit()) }})">Tambah Unit</x-admin.button>
    </x-admin.module-header>

    <x-admin.master.section-nav />

    <x-admin.flash />

    <livewire:admin.master.formasi-editor />
</div>
@endsection
