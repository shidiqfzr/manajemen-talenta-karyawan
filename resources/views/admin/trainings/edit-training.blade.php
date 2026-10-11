@extends('layouts.admin')

@section('content')
<div class="max-w-5xl mx-auto p-6">
    <x-admin.page-header title="Edit Data Pelatihan" subtitle="Perbarui detail pelatihan {{ $training->judul }}" />
    
    <div class="bg-white rounded-2xl border border-slate-200/80 shadow-xs p-6 sm:p-7 mt-6">
        <x-admin.training-form 
            :training="$training" 
            action="{{ route('admin.trainings.update', $training->id) }}" 
            method="PUT" 
        />
    </div>
</div>
@endsection
