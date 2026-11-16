@extends('layouts.admin')

@section('content')
<div class="space-y-6 max-w-5xl mx-auto">
    <x-admin.page-header
        title="Edit Data Pelatihan"
        subtitle="Perbarui detail pelatihan {{ $training->judul }}"
        icon="fas fa-chalkboard-teacher"
        :breadcrumbs="[
            ['label' => 'Core HR'],
            ['label' => 'Pelatihan', 'url' => route('admin.trainings.index')],
            ['label' => $training->judul, 'url' => route('admin.trainings.show', $training)],
            ['label' => 'Edit Pelatihan']
        ]"
        back-url="{{ route('admin.trainings.show', $training) }}"
        back-label="Kembali ke Detail Pelatihan"
    />

    <x-admin.training-form
        :training="$training"
        action="{{ route('admin.trainings.update', $training->id) }}"
        method="PUT"
    />
</div>
@endsection
