@extends('layouts.admin')

@section('content')
<div class="max-w-5xl mx-auto p-6">
    <x-admin.page-header title="Edit Penilaian Karyawan" subtitle="Perbarui data evaluasi untuk NIK {{ $employee->nik ?? $evaluation->employee_nik }}" />
    
    <div class="bg-white rounded-2xl border border-slate-200/80 shadow-xs p-6 sm:p-7 mt-6">
        <x-admin.evaluation-form 
            :evaluation="$evaluation"
            :employee="$employee ?? null"
            action="{{ route('admin.evaluations.update', $evaluation->id) }}" 
            method="PUT"
        />
    </div>
</div>
@endsection
