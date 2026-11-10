@extends('layouts.admin')

@section('content')
<div class="space-y-6">
    <x-admin.module-header title="Sebaran Formasi" subtitle="Lihat formasi jabatan yang terisi dan kosong di seluruh unit kerja, per jabatan atau per unit." :breadcrumbs="['Core HR', ['label' => 'Master Formasi', 'url' => route('admin.master.formasi.index')], 'Sebaran Formasi']" />

    <x-admin.master.section-nav />

    <livewire:admin.master.formasi-distribution />
</div>
@endsection
