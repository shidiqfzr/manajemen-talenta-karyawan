@props([
    'node',
    'index',
])

<div class="lg:col-span-5 space-y-3.5 border-b lg:border-b-0 lg:border-r border-surface-100 lg:pr-6 pb-5 lg:pb-0">
    <x-admin.employee-combobox 
        wire-model="nodes.{{ $index }}.employee_nik"
        :selected-nik="$node['employee_nik'] ?? ''"
        :selected-nama="$node['employee_nama'] ?? ''"
        label="Pilih Pegawai yang Dimutasi"
        placeholder="Ketik nama, NIK, atau formasi..."
        :exclude-index="$index"
        error-key="nodes.{{ $index }}.employee_nik"
    />

    <x-admin.employee-profile-card 
        :employee="!empty($node['profile_data']) ? $node['profile_data'] : $node"
        :nik="$node['employee_nik'] ?? ''"
        :show-readiness-metrics="true"
        :show-empty-state="true"
        empty-message="Pilih pegawai pada pencarian di atas untuk melihat profil penempatan saat ini."
    />
</div>
