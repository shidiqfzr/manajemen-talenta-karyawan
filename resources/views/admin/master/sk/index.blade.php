@extends('layouts.admin')

@section('content')
<div class="space-y-6">
    <x-admin.module-header title="Surat Keputusan Struktur" subtitle="SK struktur organisasi diterbitkan Holding dan diterapkan di Regional V: catat SK yang diterima, tinjau selisih, setujui, dan terapkan pada tanggal berlaku." :breadcrumbs="['Core HR', 'Master Formasi', 'Surat Keputusan Struktur']" />

    <x-admin.master.section-nav />

    <x-admin.flash />

    <livewire:admin.master.structure-decree-index />
</div>
@endsection
