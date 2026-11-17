@extends('layouts.admin')

@section('content')
<div class="space-y-6">
    <x-admin.module-header title="Struktur per Tanggal" subtitle="Lihat struktur organisasi beserta pemangku jabatannya pada tanggal tertentu, mengikuti SK yang berlaku saat itu." :breadcrumbs="['Core HR', 'Master Formasi', ['label' => 'Surat Keputusan Struktur', 'url' => route('admin.master.sk.index')], 'Struktur per Tanggal']">
        <x-admin.button href="{{ route('admin.master.sk.index') }}" variant="secondary" icon="fas fa-arrow-left" wire:navigate>Daftar SK</x-admin.button>
    </x-admin.module-header>

    <x-admin.master.section-nav />

    <livewire:admin.master.structure-as-of-page />
</div>
@endsection
