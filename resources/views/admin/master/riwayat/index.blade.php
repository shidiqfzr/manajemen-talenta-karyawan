@extends('layouts.admin')

@section('content')
<div class="space-y-6">
    <x-admin.module-header title="Riwayat Perubahan Master" subtitle="Catatan siapa mengubah apa pada unit kerja, jabatan, dan formasi jabatan. Catatan tidak dapat diubah atau dihapus." :breadcrumbs="['Core HR', 'Master Formasi', 'Riwayat Perubahan']" />

    <x-admin.master.section-nav />

    <livewire:admin.master.audit-history />
</div>
@endsection
