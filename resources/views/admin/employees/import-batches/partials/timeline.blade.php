{{--
    Linimasa keputusan impor: diajukan/diimpor, keputusan penyetuju, disimpan, dan pembatalan, beserta pelaku,
    waktu, dan alasan. Langkah yang belum terjadi tidak ditampilkan, kecuali menunggu persetujuan.

    @include('admin.employees.import-batches.partials.timeline', ['log' => $log])
--}}
@php
    $steps = [];

    $steps[] = $log->submitted_at
        ? ['icon' => 'fa-paper-plane', 'tone' => 'done', 'title' => 'Diajukan', 'who' => $log->user_name, 'at' => $log->submitted_at, 'note' => null]
        : ['icon' => 'fa-file-import', 'tone' => 'done', 'title' => 'Diimpor', 'who' => $log->user_name, 'at' => $log->created_at, 'note' => null];

    if ($log->status === 'pending_approval') {
        $steps[] = ['icon' => 'fa-clock', 'tone' => 'current', 'title' => 'Menunggu persetujuan admin lain', 'who' => null, 'at' => null, 'note' => null];
    } elseif ($log->status === 'expired') {
        $steps[] = ['icon' => 'fa-hourglass-end', 'tone' => 'closed', 'title' => 'Kedaluwarsa', 'who' => null, 'at' => $log->decided_at, 'note' => $log->decision_note];
    } elseif ($log->checker_name) {
        $rejected = $log->status === 'rejected';
        $steps[] = ['icon' => $rejected ? 'fa-ban' : 'fa-user-check', 'tone' => $rejected ? 'closed' : 'done', 'title' => $rejected ? 'Ditolak' : 'Disetujui', 'who' => $log->checker_name, 'at' => $log->decided_at, 'note' => $log->decision_note];
    }

    if ($log->committed_at) {
        $steps[] = ['icon' => 'fa-check', 'tone' => 'done', 'title' => 'Disimpan', 'who' => null, 'at' => $log->committed_at, 'note' => null];
    }

    if ($log->rolled_back_at) {
        $steps[] = ['icon' => 'fa-rotate-left', 'tone' => 'closed', 'title' => 'Dibatalkan', 'who' => $log->rolled_back_by_name, 'at' => $log->rolled_back_at, 'note' => $log->rollback_reason];
    }

    $dot = ['done' => 'bg-primary-100 text-primary-800', 'current' => 'bg-warning-100 text-warning-800', 'closed' => 'bg-surface-200 text-surface-700'];
@endphp

<ol class="relative space-y-5 border-l border-surface-200 ml-3 pl-6">
    @foreach ($steps as $step)
        <li class="relative">
            <span class="absolute -left-[2.25rem] top-0 flex items-center justify-center w-6 h-6 rounded-full ring-4 ring-white {{ $dot[$step['tone']] }}">
                <i class="fas {{ $step['icon'] }} text-[10px]" aria-hidden="true"></i>
            </span>
            <p class="text-sm font-semibold text-surface-900">{{ $step['title'] }}</p>
            @if ($step['who'])
                <p class="text-xs text-surface-600">{{ $step['who'] }}</p>
            @endif
            @if ($step['at'])
                <p class="text-xs text-surface-500">{{ $step['at']->format('d/m/Y H:i') }}</p>
            @endif
            @if ($step['note'])
                <p class="mt-1 text-xs text-surface-700">&ldquo;{{ $step['note'] }}&rdquo;</p>
            @endif
        </li>
    @endforeach
</ol>
