@extends('layouts.admin')

@section('content')
@php($dialogs = app(\App\Services\Formasi\FormasiDialogs::class))
<div class="space-y-6">
    <x-admin.module-header :title="$unit->nama" :subtitle="$unit->wilayah.($unit->is_active ? '' : ' · Nonaktif')"
        :breadcrumbs="['Core HR', ['label' => 'Master Formasi', 'url' => route('admin.master.formasi.index')], $unit->nama]">
        <x-admin.button href="{{ route('admin.master.formasi.index') }}" wire:navigate variant="secondary" icon="fas fa-arrow-left">Kembali ke Daftar Unit</x-admin.button>
        <x-admin.row-menu :label="'unit '.$unit->nama" text="Aksi Unit">
            <x-admin.master.menu-item icon="fas fa-pencil-alt" :action="$dialogs->editUnit($unit)">Ubah Unit</x-admin.master.menu-item>
            <x-admin.master.menu-item :icon="$unit->is_active ? 'fas fa-ban' : 'fas fa-circle-check'" :action="$dialogs->toggleUnit($unit)">{{ $unit->is_active ? 'Nonaktifkan Unit' : 'Aktifkan Unit' }}</x-admin.master.menu-item>
            <x-admin.master.menu-item icon="fas fa-trash-can" tone="danger" separated :action="$dialogs->deleteUnit($unit)">Hapus Unit</x-admin.master.menu-item>
        </x-admin.row-menu>
    </x-admin.module-header>

    <x-admin.master.section-nav />

    <x-admin.flash />

    <livewire:admin.master.formasi-editor :unit-id="$unit->id" />
</div>
@endsection
