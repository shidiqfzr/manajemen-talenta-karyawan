{{--
    Pemberitahuan kebijakan persetujuan dua pihak (empat mata) di atas daftar yang memakainya (Riwayat & Persetujuan Impor,
    Surat Keputusan). Satu komponen agar teks judul, warna, ikon, dan peran aksesibilitasnya sama di setiap layar (DESIGN.md §7.7).

    <x-admin.approval-policy-notice :active="$requiresApproval">
        <x-slot:enabled>Impor harus diajukan lalu disetujui admin lain sebelum data karyawan berubah.</x-slot:enabled>
        <x-slot:disabled>Impor langsung disimpan setelah pratinjau dikonfirmasi.</x-slot:disabled>
        Kalimat penutup yang berlaku pada kedua keadaan.
    </x-admin.approval-policy-notice>

    active : true bila kebijakan aktif (ikon perisai hijau); false bila belum diaktifkan (ikon info abu). Kartunya sama pada kedua keadaan: hanya ikon dan judul yang membedakan, agar layar terlihat konsisten.
    enabled/disabled : penjelasan sesuai keadaan kebijakan, ditulis setelah judul tebal.
    slot   : kalimat penutup yang sama untuk kedua keadaan (opsional).
--}}
@props([
    'active' => false,
])

<div class="flex items-start gap-3 rounded-xl border px-4 py-3 text-sm leading-relaxed border-surface-200 bg-surface-50 text-surface-700" role="status">
    <i class="fas {{ $active ? 'fa-user-shield text-primary-600' : 'fa-circle-info text-surface-500' }} mt-1" aria-hidden="true"></i>
    <p>
        @if ($active)
            <span class="font-semibold">Persetujuan dua pihak (empat mata) aktif.</span> {{ $enabled ?? '' }}
        @else
            <span class="font-semibold">Persetujuan dua pihak (empat mata) belum diaktifkan.</span> {{ $disabled ?? '' }}
        @endif
        {{ $slot }}
    </p>
</div>
