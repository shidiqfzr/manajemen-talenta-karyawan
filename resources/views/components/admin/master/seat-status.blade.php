{{--
    Lencana status formasi jabatan (Penuh, Sebagian kosong, Diisi Pj/Plt, Kosong, Melebihi kuota). Kunci dan label berasal dari
    JobVacancyService::SEAT_STATUS_LABELS; tampilan tidak menentukan status sendiri. Warna selalu disertai teks (WCAG 1.4.1).

    <x-admin.master.seat-status :status="$seat->status" :label="$seat->status_label" />
--}}
@props(['status', 'label'])

@php
    $tone = match ($status) {
        'full' => ['bg-primary-100 text-primary-800 border-primary-200', 'bg-primary-600'],
        'partial' => ['bg-warning-50 text-warning-800 border-warning-200', 'bg-warning'],
        'acting' => ['bg-earth-100 text-earth-800 border-earth-300', 'bg-earth-700'],
        default => ['bg-danger-50 text-danger-700 border-danger-200', 'bg-danger'],
    };
@endphp
<span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-[11px] font-semibold border {{ $tone[0] }}"><span class="w-1.5 h-1.5 rounded-full {{ $tone[1] }}" aria-hidden="true"></span>{{ $label }}</span>
