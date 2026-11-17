@extends('layouts.admin')

@section('content')
@php
    $requiresApproval = (bool) config('hris.structure.require_approval');
@endphp
<div class="space-y-6">
    <x-admin.module-header title="Surat Keputusan Struktur" subtitle="SK struktur organisasi diterbitkan Holding dan diterapkan di Regional V: catat SK yang diterima, tinjau selisih, setujui, dan terapkan pada tanggal berlaku." :breadcrumbs="['Core HR', 'Master Formasi', 'Surat Keputusan Struktur']">
        <x-admin.button href="{{ route('admin.master.sk.versions') }}" variant="secondary" icon="fas fa-code-branch" wire:navigate>Versi Struktur</x-admin.button>
        <x-admin.button href="{{ route('admin.master.sk.create') }}" variant="primary" icon="fas fa-file-circle-plus">Catat SK dari Holding</x-admin.button>
    </x-admin.module-header>

    <x-admin.master.section-nav />

    <x-admin.flash />

    <div class="flex items-start gap-3 rounded-xl border px-4 py-3 text-sm leading-relaxed {{ $requiresApproval ? 'border-primary-200 bg-primary-50 text-primary-900' : 'border-surface-200 bg-surface-50 text-surface-700' }}" role="status">
        <i class="fas {{ $requiresApproval ? 'fa-user-shield' : 'fa-circle-info' }} mt-1" aria-hidden="true"></i>
        <p>
            @if ($requiresApproval)
                <span class="font-semibold">Persetujuan dua pihak (empat mata) aktif.</span> SK harus diajukan lalu disetujui admin lain sebelum struktur organisasi berubah.
            @else
                <span class="font-semibold">Persetujuan dua pihak (empat mata) belum diaktifkan.</span> SK yang diajukan dapat langsung disetujui oleh pengajunya.
            @endif
            SK yang sudah disetujui diterapkan pada tanggal berlakunya, dan SK yang sudah diterapkan tidak dapat dibatalkan; koreksi dilakukan dengan SK baru.
        </p>
    </div>

    <livewire:admin.master.structure-decree-index />
</div>
@endsection
