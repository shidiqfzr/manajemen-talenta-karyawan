{{--
    Linimasa keputusan (DESIGN.md §8.6, §8.5a): urutan langkah persetujuan dengan pelaku, waktu, dan alasan.
    Dipakai oleh Riwayat & Persetujuan Impor dan Surat Keputusan Struktur. Langkah yang belum terjadi tidak
    ditampilkan, kecuali langkah berjalan (tone "current").

    <x-admin.decision-timeline :steps="[
        ['icon' => 'fa-paper-plane', 'tone' => 'done', 'title' => 'Diajukan', 'who' => 'Admin', 'at' => $carbon, 'note' => null],
        ['icon' => 'fa-clock', 'tone' => 'current', 'title' => 'Menunggu persetujuan admin lain', 'who' => null, 'at' => null, 'note' => null],
    ]" />

    tone: done (selesai) · current (menunggu) · closed (ditutup: ditolak, dibatalkan, kedaluwarsa).
    at: Carbon atau null; note: teks alasan atau null.
--}}
@props(['steps'])

@php
    $dot = ['done' => 'bg-primary-100 text-primary-800', 'current' => 'bg-warning-100 text-warning-800', 'closed' => 'bg-surface-200 text-surface-700'];
@endphp

<ol class="relative space-y-5 border-l border-surface-200 ml-3 pl-6">
    @foreach ($steps as $step)
        <li class="relative">
            <span class="absolute -left-[2.25rem] top-0 flex items-center justify-center w-6 h-6 rounded-full ring-4 ring-white {{ $dot[$step['tone']] ?? $dot['done'] }}">
                <i class="fas {{ $step['icon'] }} text-[10px]" aria-hidden="true"></i>
            </span>
            <p class="text-sm font-semibold text-surface-900">{{ $step['title'] }}</p>
            @if (! empty($step['who']))
                <p class="text-xs text-surface-600">{{ $step['who'] }}</p>
            @endif
            @if (! empty($step['at']))
                <p class="text-xs text-surface-500">{{ $step['at']->format('d/m/Y H:i') }}</p>
            @endif
            @if (! empty($step['note']))
                <p class="mt-1 text-xs text-surface-700">&ldquo;{{ $step['note'] }}&rdquo;</p>
            @endif
        </li>
    @endforeach
</ol>
