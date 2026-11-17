@extends('layouts.admin')

@section('content')
<div class="space-y-6">
    <x-admin.module-header :title="isset($decree) ? 'Tinjau SK Struktur' : 'Buat SK Struktur Baru'" subtitle="Unggah lampiran SK, tinjau perubahan terhadap struktur saat ini, lalu ajukan untuk persetujuan." :breadcrumbs="['Core HR', 'Master Formasi', 'Surat Keputusan Struktur', isset($decree) ? 'Tinjau' : 'Baru']" />

    <x-admin.master.section-nav />

    @isset($decree)
        <livewire:admin.master.structure-decree-wizard :decree="$decree" />
    @else
        <livewire:admin.master.structure-decree-wizard />
    @endisset
</div>
@endsection
