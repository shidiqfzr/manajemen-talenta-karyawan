@extends('layouts.admin')

@section('content')
<div class="space-y-6">
    <x-admin.module-header title="Formasi Lowong" subtitle="Pantau formasi jabatan yang belum terisi pejabat definitif di seluruh unit kerja, lengkap dengan lama kosong dan jalur pengisiannya." :breadcrumbs="['Core HR', ['label' => 'Master Formasi', 'url' => route('admin.master.formasi.index')], 'Formasi Lowong']" />

    <x-admin.master.section-nav />

    <livewire:admin.master.formasi-vacancy-table />
</div>
@endsection
