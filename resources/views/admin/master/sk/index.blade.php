@extends('layouts.admin')

@section('content')
<div class="space-y-6">
    <x-admin.module-header title="Surat Keputusan Struktur" subtitle="Penerapan perubahan struktur organisasi berdasarkan Surat Keputusan (SK): tinjau selisih, setujui, dan terapkan pada tanggal berlaku." :breadcrumbs="['Core HR', 'Master Formasi', 'Surat Keputusan Struktur']" />

    <x-admin.master.section-nav />

    <x-admin.flash />

    <livewire:admin.master.structure-decree-index />
</div>
@endsection
