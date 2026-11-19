@extends('layouts.admin')

@section('content')
@php
    $requiresApproval = (bool) config('hris.structure.require_approval');
@endphp
<div class="space-y-6">
    <x-admin.module-header title="Surat Keputusan Struktur" subtitle="SK struktur organisasi diterbitkan Holding dan diterapkan di Regional V: catat SK yang diterima, tinjau selisih, setujui, dan terapkan pada tanggal berlaku." :breadcrumbs="['Core HR', ['label' => 'Formasi', 'url' => route('admin.master.formasi.index')], 'Surat Keputusan Struktur']">
        <x-admin.button href="{{ route('admin.master.sk.create') }}" variant="primary" icon="fas fa-file-circle-plus">Catat SK dari Holding</x-admin.button>
    </x-admin.module-header>

    <x-admin.master.section-nav />

    <x-admin.flash />

    <x-admin.approval-policy-notice :active="$requiresApproval">
        <x-slot:enabled>SK harus diajukan lalu disetujui admin lain sebelum struktur organisasi berubah.</x-slot:enabled>
        <x-slot:disabled>SK yang diajukan dapat langsung disetujui oleh pengajunya.</x-slot:disabled>
        SK yang sudah disetujui diterapkan pada tanggal berlakunya, dan SK yang sudah diterapkan tidak dapat dibatalkan; koreksi dilakukan dengan SK baru.
    </x-admin.approval-policy-notice>

    <livewire:admin.master.structure-decree-index />
</div>
@endsection
