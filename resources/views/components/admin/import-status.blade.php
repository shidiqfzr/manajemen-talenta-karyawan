{{--
    Lencana status impor data pegawai. Label, warna, dan ikon mengikuti Tabel Status Induk (DESIGN.md §5.1);
    warna tidak menjadi satu-satunya penanda karena setiap status memiliki ikon dan label.

    <x-admin.import-status :status="$log->status" :skipped="$log->skipped_count" />

    Impor yang tersimpan namun melewati sebagian baris tampil sebagai "Tersimpan Sebagian".
--}}
@props(['status', 'skipped' => 0])

@php
    $key = $status === 'completed' && $skipped > 0 ? 'partial' : $status;
    [$label, $classes, $icon] = match ($key) {
        'pending_approval' => ['Menunggu Persetujuan', 'bg-warning-100 text-warning-800 border-transparent', 'fa-clock'],
        'approved' => ['Menyimpan', 'bg-info-50 text-info-800 border-info-200', 'fa-rotate'],
        'completed' => ['Tersimpan', 'bg-primary-100 text-primary-800 border-transparent', 'fa-check'],
        'partial' => ['Tersimpan Sebagian', 'bg-warning-50 text-warning-800 border-warning-200', 'fa-triangle-exclamation'],
        'rejected' => ['Ditolak', 'bg-surface-100 text-surface-600 border-surface-300', 'fa-ban'],
        'rolled_back' => ['Dibatalkan', 'bg-surface-100 text-surface-600 border-surface-300', 'fa-rotate-left'],
        'expired' => ['Kedaluwarsa', 'bg-surface-100 text-surface-600 border-surface-300', 'fa-hourglass-end'],
        default => [$status, 'bg-surface-100 text-surface-600 border-surface-300', 'fa-circle'],
    };
@endphp
<span {{ $attributes->merge(['class' => "inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full border text-xs font-semibold whitespace-nowrap {$classes}"]) }}>
    <i class="fas {{ $icon }} text-[10px]" aria-hidden="true"></i>{{ $label }}
</span>
