{{--
    Linimasa keputusan impor: diajukan/diimpor, keputusan penyetuju, disimpan, dan pembatalan, beserta pelaku,
    waktu, dan alasan. Langkah yang belum terjadi tidak ditampilkan, kecuali menunggu persetujuan.
    Tampilannya dirender oleh x-admin.decision-timeline (komponen bersama dengan Surat Keputusan Struktur).

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
@endphp

<x-admin.decision-timeline :steps="$steps" />
