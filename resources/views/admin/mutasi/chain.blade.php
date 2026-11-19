@extends('layouts.admin')

@section('content')
<div class="space-y-6 max-w-6xl mx-auto">

    <!-- Page Header Card -->
    <x-admin.page-header 
        title="Mutasi Berantai & Penugasan Kolektif"
        subtitle="Penyusunan rotasi gerbong (rantai tertutup & rantai terbuka) multi-karyawan dalam 1 SK Direksi Kolektif"
        icon="fas fa-diagram-project"
        :breadcrumbs="[
            ['label' => 'Core HR'],
            ['label' => 'Mutasi', 'url' => route('admin.mutasi.index')],
            ['label' => 'Mutasi Berantai (Kolektif)']
        ]"
        back-url="{{ route('admin.mutasi.index') }}"
        back-label="Kembali ke Hub Mutasi"
    />

    <!-- Main Chain Builder Component -->
    {{-- ?usulan={id} membuka draf / usulan tersimpan --}}
    @livewire('admin.mutasi-chain-process', ['usulan' => request()->integer('usulan') ?: null])

</div>
@endsection
