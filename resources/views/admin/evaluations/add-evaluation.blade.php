@extends('layouts.admin')

@section('content')
<div class="max-w-5xl mx-auto p-6">
    <x-admin.page-header title="Tambah Penilaian Karyawan" subtitle="Masukkan data evaluasi dan asesmen" />
    
    <div class="bg-white rounded-2xl border border-slate-200/80 shadow-xs p-6 sm:p-7 mt-6">
        <x-admin.evaluation-form 
            :employee="$employee ?? null"
            :employees="$employees ?? []"
            action="{{ route('admin.evaluations.store') }}" 
        />
    </div>
</div>
@endsection
