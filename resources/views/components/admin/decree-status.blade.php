{{--
    Lencana status SK struktur organisasi. Label, warna, dan ikon mengikuti Tabel Status Induk (DESIGN.md §5.1 dan §8.5a);
    warna tidak menjadi satu-satunya penanda karena setiap status memiliki ikon dan label.

    <x-admin.decree-status :status="$decree->status" :effective-date="$decree->effective_date" />

    SK yang disetujui tampil "Terjadwal" selama tanggal berlakunya belum tiba.
--}}
@props(['status', 'effectiveDate' => null])

@php
    $scheduled = $status === 'approved' && $effectiveDate !== null
        && \Illuminate\Support\Carbon::parse($effectiveDate)->toDateString() > now()->toDateString();

    [$label, $classes, $icon] = match (true) {
        $status === 'draft' => ['Draf', 'bg-surface-100 text-surface-600 border-surface-300', 'fa-pen'],
        $status === 'submitted' => ['Diajukan', 'bg-info-50 text-info-800 border-info-200', 'fa-paper-plane'],
        $scheduled => ['Terjadwal', 'bg-primary-50 text-primary-800 border-primary-200', 'fa-calendar-check'],
        $status === 'approved' => ['Disetujui', 'bg-primary-50 text-primary-800 border-primary-200', 'fa-circle-check'],
        $status === 'applied' => ['Diterapkan', 'bg-primary-100 text-primary-800 border-transparent', 'fa-check'],
        $status === 'rejected' => ['Ditolak', 'bg-surface-100 text-surface-600 border-surface-300', 'fa-ban'],
        $status === 'cancelled' => ['Dibatalkan', 'bg-surface-100 text-surface-600 border-surface-300', 'fa-rotate-left'],
        $status === 'stale' => ['Perlu Tinjau Ulang', 'bg-warning-50 text-warning-800 border-warning-200', 'fa-triangle-exclamation'],
        $status === 'failed' => ['Gagal', 'bg-danger-50 text-danger-700 border-danger-200', 'fa-circle-xmark'],
        default => [$status, 'bg-surface-100 text-surface-600 border-surface-300', 'fa-circle'],
    };
@endphp
<span {{ $attributes->merge(['class' => "inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full border text-xs font-semibold whitespace-nowrap {$classes}"]) }}>
    <i class="fas {{ $icon }} text-[10px]" aria-hidden="true"></i>{{ $label }}
</span>
