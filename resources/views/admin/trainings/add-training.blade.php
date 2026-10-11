@extends('layouts.admin')

@section('content')
<div class="max-w-5xl mx-auto p-6">
    <x-admin.page-header title="Tambah Data Pelatihan" subtitle="Masukkan detail pelatihan baru" />
    
    <div class="bg-white rounded-2xl border border-slate-200/80 shadow-xs p-6 sm:p-7 mt-6">
        <x-admin.training-form action="{{ route('admin.trainings.store') }}" />
    </div>
</div>
@endsection
