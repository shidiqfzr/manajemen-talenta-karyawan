{{-- Status usulan mutasi berantai (draf & persetujuan empat mata, DOMAIN.md §5.2.6). Variabel: $proposal, $locked. --}}
@if (session('mutasi_success'))
    <div role="status" class="flex items-start gap-2.5 p-4 bg-primary-50 text-primary-800 border border-primary-200 rounded-xl shadow-xs text-sm font-medium">
        <i class="fas fa-circle-check mt-0.5" aria-hidden="true"></i>
        <span>{{ session('mutasi_success') }}</span>
    </div>
@endif

@if ($notice !== '')
    <div role="status" wire:key="draft-notice" class="flex items-start gap-2.5 p-3.5 bg-primary-50 text-primary-800 border border-primary-200 rounded-xl text-xs font-medium">
        <i class="fas fa-floppy-disk mt-0.5" aria-hidden="true"></i>
        <span>{{ $notice }}</span>
    </div>
@endif

@if ($proposal)
    @php
        $isMine = (int) $proposal->created_by === (int) auth()->id();
        $tone = match ($proposal->status) {
            \App\Models\MutasiChainProposal::STATUS_PENDING => ['bg-info-50 border-info-200', 'bg-info-100 text-info-800 border-info-200', 'fa-hourglass-half'],
            \App\Models\MutasiChainProposal::STATUS_REJECTED => ['bg-warning-50 border-warning-200', 'bg-warning-100 text-warning-800 border-warning-300', 'fa-rotate-left'],
            \App\Models\MutasiChainProposal::STATUS_EXECUTED => ['bg-primary-50 border-primary-200', 'bg-primary-100 text-primary-800 border-primary-300', 'fa-circle-check'],
            \App\Models\MutasiChainProposal::STATUS_EXPIRED => ['bg-surface-50 border-surface-200', 'bg-surface-100 text-surface-700 border-surface-300', 'fa-clock'],
            default => ['bg-white border-surface-200', 'bg-surface-100 text-surface-700 border-surface-300', 'fa-file-pen'],
        };
    @endphp
    <div class="rounded-2xl border shadow-xs p-5 space-y-3 {{ $tone[0] }}" aria-labelledby="proposal-status-title">
        <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3">
            <div class="space-y-1">
                <div class="flex flex-wrap items-center gap-2">
                    <h3 id="proposal-status-title" class="text-sm font-bold text-surface-900">Usulan Mutasi Berantai #{{ $proposal->id }}</h3>
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[11px] font-bold border {{ $tone[1] }}">
                        <i class="fas {{ $tone[2] }} text-[10px]" aria-hidden="true"></i> {{ $proposal->statusLabel() }}
                    </span>
                </div>
                <p class="text-xs text-surface-600">
                    Disusun oleh <strong>{{ $proposal->created_by_name ?? '—' }}</strong>
                    @if ($proposal->submitted_at)
                        &middot; diajukan {{ $proposal->submitted_at->translatedFormat('d M Y H:i') }}
                    @endif
                    @if ($proposal->isPending() && $proposal->expires_at)
                        &middot; batas persetujuan <strong>{{ $proposal->expires_at->translatedFormat('d M Y') }}</strong>
                    @endif
                    @if ($proposal->decided_at)
                        &middot; diputuskan oleh <strong>{{ $proposal->decided_by_name }}</strong> {{ $proposal->decided_at->translatedFormat('d M Y H:i') }}
                    @endif
                </p>
            </div>

            @if ($proposal->status === \App\Models\MutasiChainProposal::STATUS_EXECUTED)
                <a href="{{ route('admin.mutasi.index', ['tab' => 'history']) }}"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-white border border-primary-300 text-primary-800 text-xs font-bold hover:bg-primary-50 transition shrink-0">
                    <i class="fas fa-history text-[10px]" aria-hidden="true"></i> Lihat Riwayat Mutasi
                </a>
            @endif
        </div>

        {{-- Penjelasan satu kalimat: apa yang terjadi dan apa langkah berikutnya --}}
        <p class="text-xs text-surface-700 leading-relaxed">
            @switch ($proposal->status)
                @case (\App\Models\MutasiChainProposal::STATUS_PENDING)
                    @if ($isMine)
                        Menunggu persetujuan admin lain. Isian dikunci; tarik usulan bila perlu mengubahnya.
                    @elseif (! $locked || $this->reviewMode === 'approve')
                        Tinjau seluruh mata rantai di bawah, lalu setujui untuk menerbitkan SK atau kembalikan kepada penyusun dengan alasan.
                    @else
                        Usulan ini menunggu keputusan admin yang berwenang.
                    @endif
                    @break
                @case (\App\Models\MutasiChainProposal::STATUS_REJECTED)
                    Usulan dikembalikan. Perbaiki sesuai catatan penyetuju, lalu ajukan kembali.
                    @break
                @case (\App\Models\MutasiChainProposal::STATUS_EXECUTED)
                    SK telah diterbitkan dan seluruh perpindahan tercatat pada riwayat mutasi. Usulan ini hanya dapat dilihat.
                    @break
                @case (\App\Models\MutasiChainProposal::STATUS_EXPIRED)
                    Usulan melewati batas waktu persetujuan. Susun usulan baru bila mutasi ini masih diperlukan.
                    @break
                @default
                    @if ($isMine)
                        Draf tersimpan. Data kepegawaian diperiksa ulang setiap kali draf dibuka, sehingga hasil analisis selalu mengikuti kondisi terkini.
                    @else
                        Draf ini milik admin lain dan hanya dapat dilihat.
                    @endif
            @endswitch
        </p>

        @if ($proposal->decision_reason && $proposal->status === \App\Models\MutasiChainProposal::STATUS_REJECTED)
            <div class="p-3 rounded-xl bg-white border border-warning-200 text-xs text-surface-800">
                <span class="font-bold text-warning-800 block mb-0.5">Catatan penyetuju</span>
                {{ $proposal->decision_reason }}
            </div>
        @endif

        @if ($proposal->events->isNotEmpty())
            <details class="group text-xs">
                <summary class="cursor-pointer text-surface-600 hover:text-surface-900 font-semibold inline-flex items-center gap-1.5 select-none">
                    <i class="fas fa-chevron-right text-[10px] transition group-open:rotate-90" aria-hidden="true"></i>
                    Riwayat usulan ({{ $proposal->events->count() }})
                </summary>
                <ol class="mt-2 space-y-1.5 border-l-2 border-surface-200 pl-3">
                    @foreach ($proposal->events->reverse() as $event)
                        <li class="text-surface-700">
                            <span class="font-semibold text-surface-900">{{ $event->actionLabel() }}</span>
                            &middot; {{ $event->user_name }} &middot; {{ $event->created_at?->translatedFormat('d M Y H:i') }}
                            @if ($event->reason)
                                <span class="block text-surface-600">“{{ $event->reason }}”</span>
                            @endif
                        </li>
                    @endforeach
                </ol>
            </details>
        @endif
    </div>
@endif
