@extends('layouts.admin')

@section('content')
<div class="space-y-6">
    <x-admin.module-header title="Versi Struktur" subtitle="Telusuri struktur organisasi dan pemangku jabatan menurut SK yang berlaku pada versi atau tanggal tertentu." :breadcrumbs="['Core HR', ['label' => 'Formasi', 'url' => route('admin.master.formasi.index')], 'Versi Struktur']" />

    <x-admin.master.section-nav />

    <livewire:admin.master.structure-versions-page />
</div>
@endsection
