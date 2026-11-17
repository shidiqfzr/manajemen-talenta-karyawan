@extends('layouts.admin')

@section('content')
<div class="space-y-6">
    <x-admin.module-header
        :title="$decree->sk_number"
        :subtitle="$decree->title"
        :breadcrumbs="[
            'Core HR',
            ['label' => 'Formasi', 'url' => route('admin.master.formasi.index')],
            ['label' => 'Surat Keputusan Struktur', 'url' => route('admin.master.sk.index')],
            $decree->sk_number,
        ]">
        @if ($decree->status === \App\Models\StructureDecree::STATUS_APPLIED)
            <x-admin.button href="{{ route('admin.master.versi.index', ['date' => $decree->effective_date->toDateString()]) }}" variant="secondary" icon="fas fa-code-branch">Lihat struktur setelah SK ini</x-admin.button>
        @endif
        <x-admin.button href="{{ route('admin.master.sk.index') }}" variant="secondary" icon="fas fa-arrow-left">Kembali ke Daftar SK</x-admin.button>
    </x-admin.module-header>

    <x-admin.master.section-nav />

    <livewire:admin.master.structure-decree-detail :decree="$decree" />
</div>
@endsection
