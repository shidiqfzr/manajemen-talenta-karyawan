@extends('layouts.admin')

@section('content')
<div class="space-y-6">
    <x-admin.module-header title="Versi Struktur" subtitle="Setiap SK yang diterapkan menjadi satu versi struktur organisasi. Telusuri struktur dan pemangku jabatan pada versi atau tanggal tertentu." :breadcrumbs="['Core HR', 'Master Formasi', ['label' => 'Surat Keputusan Struktur', 'url' => route('admin.master.sk.index')], 'Versi Struktur']">
        <x-admin.button href="{{ route('admin.master.sk.index') }}" variant="secondary" icon="fas fa-arrow-left" wire:navigate>Daftar SK</x-admin.button>
    </x-admin.module-header>

    <x-admin.master.section-nav />

    <livewire:admin.master.structure-versions-page />
</div>
@endsection
