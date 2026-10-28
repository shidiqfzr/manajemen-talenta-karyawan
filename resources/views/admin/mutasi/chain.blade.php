@extends('layouts.admin')

@section('content')
<div class="space-y-6 max-w-6xl mx-auto">

    <!-- Page Header Card -->
    <x-admin.page-header 
        title="Mutasi Berantai & Penugasan Kolektif"
        subtitle="Penyusunan rotasi gerbong (cyclic & domino chain transfer) multi-karyawan dalam 1 SK Direksi Kolektif"
        icon="fas fa-diagram-project"
        :breadcrumbs="[
            ['label' => 'Core HR'],
            ['label' => 'Mutasi & Penempatan', 'url' => route('admin.mutasi.index')],
            ['label' => 'Mutasi Berantai (Kolektif)']
        ]"
        back-url="{{ route('admin.mutasi.index') }}"
        back-label="Kembali ke Hub Mutasi"
    />

    <!-- Main Chain Builder Component -->
    @livewire('admin.mutasi-chain-process')

</div>
@endsection
