@extends('layouts.admin')

@section('content')
<div class="space-y-6">
    <x-admin.module-header title="Jabatan Penampungan" subtitle="Kelola jabatan Advisor dan Diperbantukan yang opsional pada sebuah unit dan berada di luar formasi, serta lihat bezetting-nya per unit." :breadcrumbs="['Core HR', ['label' => 'Master Formasi', 'url' => route('admin.master.formasi.index')], 'Jabatan Penampungan']" />

    <x-admin.master.section-nav />

    <livewire:admin.master.pool-positions />
</div>
@endsection
