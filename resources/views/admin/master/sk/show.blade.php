@extends('layouts.admin')

@section('content')
<div class="space-y-6">
    <x-admin.module-header title="Detail SK Struktur" subtitle="Keputusan, perubahan struktur, dan riwayat penerapan SK." :breadcrumbs="['Core HR', 'Master Formasi', 'Surat Keputusan Struktur', $decree->sk_number]" />

    <x-admin.master.section-nav />

    <livewire:admin.master.structure-decree-detail :decree="$decree" />
</div>
@endsection
