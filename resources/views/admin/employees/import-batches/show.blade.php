@extends('layouts.admin')

@section('content')
@php
    $isPending = $log->status === 'pending_approval';
    // Tombol pembatalan impor besar baru aktif setelah kata konfirmasi diketik.
    $confirmExpression = $typedConfirmation ? "confirmText.trim().toUpperCase() !== '".$confirmationWord."'" : 'false';
@endphp
<div x-data="{ rejectOpen: false, rollbackOpen: false, confirmText: '' }" @keydown.escape.window="rejectOpen = false; rollbackOpen = false" class="space-y-6">
    <x-admin.module-header
        title="Impor #{{ $log->id }}"
        subtitle="{{ $log->file_name }}"
        :breadcrumbs="[
            'Core HR',
            ['label' => 'Daftar Karyawan', 'url' => route('admin.employees.index')],
            ['label' => 'Riwayat & Persetujuan', 'url' => route('admin.employees.import.batches.index')],
            'Impor #'.$log->id,
        ]">
        <x-admin.button href="{{ route('admin.employees.import.batches.index') }}" variant="secondary" icon="fas fa-arrow-left">Kembali ke Riwayat</x-admin.button>
    </x-admin.module-header>

    <x-admin.flash />

    @error('reason')
        <div role="alert" class="flex items-start gap-2.5 p-4 bg-danger-50 text-danger-700 border border-danger-200 rounded-xl shadow-xs text-sm font-medium">
            <i class="fas fa-triangle-exclamation mt-0.5" aria-hidden="true"></i><span>{{ $message }}</span>
        </div>
    @enderror

    @if ($log->status === 'completed' && $log->skipped_count > 0)
        <div role="status" class="flex items-start gap-3 rounded-xl border border-warning-200 bg-warning-50 px-4 py-3 text-sm text-warning-800">
            <i class="fas fa-triangle-exclamation mt-1" aria-hidden="true"></i>
            <p><span class="font-semibold">{{ $log->created_count + $log->updated_count }} karyawan tersimpan, {{ $log->skipped_count }} baris dilewati.</span> Lihat alasannya pada panel di bawah untuk memperbaiki baris tersebut.</p>
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
        {{-- KOLOM KIRI: ringkasan, baris dilewati, peninjauan, dan aksi --}}
        <div class="lg:col-span-8 space-y-6">
            <section class="bg-white rounded-2xl border border-surface-200/80 p-5 shadow-xs space-y-4" aria-labelledby="result-title">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <h3 id="result-title" class="text-base font-bold text-surface-900">Ringkasan Hasil</h3>
                    <x-admin.import-status :status="$log->status" :skipped="$log->skipped_count" />
                </div>
                <dl class="flex flex-wrap items-center gap-x-5 gap-y-1 text-sm text-surface-600">
                    <div class="flex items-center gap-1.5"><dt class="sr-only">Total baris</dt><dd><span class="font-mono tabular-nums font-semibold text-surface-900">{{ number_format($log->total_rows, 0, ',', '.') }}</span> baris dalam berkas</dd></div>
                    <div class="flex items-center gap-1.5"><dt class="sr-only">Pengaju</dt><dd><i class="fas fa-user text-surface-400 mr-1" aria-hidden="true"></i>{{ $log->user_name ?? '-' }}</dd></div>
                    <div class="flex items-center gap-1.5"><dt class="sr-only">Waktu</dt><dd><i class="far fa-clock text-surface-400 mr-1" aria-hidden="true"></i>{{ ($log->submitted_at ?? $log->created_at)->format('d/m/Y H:i') }}</dd></div>
                </dl>
                {{-- Kartu bernilai nol dibuat netral agar perhatian tertuju pada angka yang bermakna (DESIGN.md §5.3). --}}
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                    <x-admin.kpi-card title="Baru" :value="$log->created_count" subtitle="Ditambahkan" icon="fas fa-user-plus" :theme="$log->created_count > 0 ? 'primary' : 'neutral'" />
                    <x-admin.kpi-card title="Diperbarui" :value="$log->updated_count" subtitle="Atribut disesuaikan" icon="fas fa-pen-to-square" :theme="$log->updated_count > 0 ? 'warning' : 'neutral'" />
                    <x-admin.kpi-card title="Tetap" :value="$log->unchanged_count" subtitle="Sudah mutakhir" icon="fas fa-shield-halved" :theme="$log->unchanged_count > 0 ? 'info' : 'neutral'" />
                    <x-admin.kpi-card title="Dilewati" :value="$log->skipped_count" subtitle="Tidak diproses" icon="fas fa-triangle-exclamation" :theme="$log->skipped_count > 0 ? 'danger' : 'neutral'" />
                </div>
            </section>

            @include('admin.employees.import-batches.partials.skipped-rows', ['groups' => $skippedGroups, 'rows' => $skippedRows, 'log' => $log, 'canDownloadFailed' => $canDownloadFailed])

            {{-- Peninjauan pengajuan (checker) --}}
            @if ($isPending)
                <x-admin.table.card role="region" aria-labelledby="review-title">
                    <div class="px-5 py-3.5 border-b border-surface-200 bg-surface-50 flex flex-wrap items-center justify-between gap-2">
                        <h3 id="review-title" class="text-base font-bold text-surface-900">Pratinjau Terkini terhadap Master Formasi</h3>
                        <a href="{{ route('admin.employees.import.batches.show', [$log, 'refresh' => 1]) }}" class="text-sm font-semibold text-primary-700 hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 rounded">
                            <i class="fas fa-rotate mr-1" aria-hidden="true"></i>Muat ulang pratinjau
                        </a>
                    </div>

                    @if ($reviewError)
                        <div role="alert" class="m-5 flex items-start gap-2.5 p-4 bg-danger-50 text-danger-700 border border-danger-200 rounded-xl text-sm font-medium">
                            <i class="fas fa-triangle-exclamation mt-0.5" aria-hidden="true"></i><span>{{ $reviewError }}</span>
                        </div>
                    @else
                        @php $reviewSummary = $review['summary']; @endphp
                        <div class="px-5 py-3 text-sm text-surface-700 flex flex-wrap gap-x-5 gap-y-1 border-b border-surface-100">
                            <span><strong class="font-mono tabular-nums">{{ $reviewSummary['created'] ?? 0 }}</strong> baru</span>
                            <span><strong class="font-mono tabular-nums">{{ $reviewSummary['updated'] ?? 0 }}</strong> diperbarui</span>
                            <span><strong class="font-mono tabular-nums">{{ $reviewSummary['unchanged'] ?? 0 }}</strong> tetap</span>
                            <span class="{{ ($reviewSummary['skipped'] ?? 0) > 0 ? 'text-danger-700 font-semibold' : '' }}"><strong class="font-mono tabular-nums">{{ $reviewSummary['skipped'] ?? 0 }}</strong> dilewati</span>
                            <span class="{{ ($reviewSummary['warning_count'] ?? 0) > 0 ? 'text-warning-800 font-semibold' : '' }}"><strong class="font-mono tabular-nums">{{ $reviewSummary['warning_count'] ?? 0 }}</strong> peringatan formasi</span>
                        </div>

                        <x-admin.table.header title="Pratinjau Perubahan" :paginator="$reviewRows" noun="Baris" />
                        <div class="overflow-x-auto">
                            <table class="w-full text-left border-collapse text-xs">
                                <thead class="bg-surface-50 sticky top-0 z-10">
                                    <tr class="border-b border-surface-200">
                                        <x-admin.table.th>Karyawan</x-admin.table.th>
                                        <x-admin.table.th>Formasi &amp; Penempatan</x-admin.table.th>
                                        <x-admin.table.th>Strata &amp; RM</x-admin.table.th>
                                        <x-admin.table.th>Aksi &amp; Perubahan</x-admin.table.th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-surface-100 text-surface-800">
                                    @foreach ($reviewRows as $row)
                                        <tr class="align-top hover:bg-surface-50/75 transition">
                                            <td class="px-4 py-3">
                                                <span class="block font-semibold text-surface-900">{{ $row['nama'] }}</span>
                                                <span class="block font-mono tabular-nums text-xs text-surface-500">NIK {{ $row['nik'] }}</span>
                                            </td>
                                            <td class="px-4 py-3">
                                                <span class="block text-surface-900">{{ $row['jabatan'] }}</span>
                                                <span class="block text-xs text-surface-500">{{ $row['unit_kerja'] }}</span>
                                                @foreach ($row['warnings'] ?? [] as $warning)
                                                    <span class="mt-1 flex items-start gap-1 text-xs font-medium text-warning-800"><i class="fas fa-triangle-exclamation mt-0.5" aria-hidden="true"></i>{{ $warning }}</span>
                                                @endforeach
                                            </td>
                                            <td class="px-4 py-3 font-mono tabular-nums text-xs text-surface-700">{{ $row['level'] }} &bull; {{ $row['rm_level'] }}</td>
                                            <td class="px-4 py-3 text-xs text-surface-600">
                                                <span class="block font-semibold text-surface-900">{{ ['CREATE' => 'Baru', 'UPDATE' => 'Perubahan', 'UNCHANGED' => 'Tetap'][$row['action']] ?? $row['action'] }}</span>
                                                @foreach ($row['diff'] ?? [] as $diff)
                                                    <span class="block">{{ $diff['label'] ?? $diff['field'] }}</span>
                                                @endforeach
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <x-pagination-ui :data="$reviewRows" />
                    @endif
                </x-admin.table.card>

                {{-- Aksi keputusan --}}
                <div class="bg-white rounded-2xl border border-surface-200/80 p-5 shadow-xs flex flex-wrap items-center gap-3">
                    @if ($isMaker)
                        <p class="flex-1 min-w-[16rem] text-sm text-surface-600 leading-relaxed">
                            <i class="fas fa-user-lock mr-1 text-surface-500" aria-hidden="true"></i>
                            Anda pengaju impor ini, sehingga tidak dapat menyetujuinya sendiri. Admin lain perlu meninjau dan menyetujui.
                            Anda dapat menarik pengajuan bila ada kekeliruan.
                        </p>
                    @elseif ($review && ! $reviewError)
                        <form method="POST" action="{{ route('admin.employees.import.batches.approve', $log) }}">
                            @csrf
                            <input type="hidden" name="reviewed_digest" value="{{ $review['digest'] }}">
                            <x-admin.button type="submit" variant="primary" icon="fas fa-check" once>Setujui &amp; Simpan Data</x-admin.button>
                        </form>
                    @endif
                    <x-admin.button type="button" variant="secondary" icon="fas fa-ban" @click="rejectOpen = true">{{ $isMaker ? 'Tarik Pengajuan' : 'Tolak Pengajuan' }}</x-admin.button>
                </div>
            @endif

            {{-- Tindakan berisiko: pembatalan impor (undo) --}}
            @if ($log->status === 'completed')
                <section class="bg-white rounded-2xl border border-danger-200 shadow-xs overflow-hidden" aria-labelledby="risk-title">
                    <div class="px-5 py-3.5 bg-danger-50 border-b border-danger-200">
                        <h3 id="risk-title" class="text-base font-bold text-danger-800"><i class="fas fa-triangle-exclamation mr-1.5" aria-hidden="true"></i>Tindakan Berisiko: Batalkan Impor</h3>
                    </div>
                    <div class="p-5 space-y-4">
                        <ul class="text-sm text-surface-700 space-y-1.5 list-disc pl-5">
                            <li>Karyawan baru <strong>dihapus</strong> dan karyawan yang diperbarui <strong>dikembalikan</strong> ke kondisi sebelum impor, dalam satu proses.</li>
                            @if ($log->skipped_count > 0)
                                <li>Baris yang dilewati tidak pernah disimpan sehingga tidak terpengaruh.</li>
                            @endif
                            <li>Riwayat penugasan tidak dihapus; pembatalan dicatat sebagai riwayat baru beserta alasannya.</li>
                        </ul>

                        @if ($canRollback)
                            <div class="flex items-start gap-2.5 rounded-xl border px-4 py-3 text-sm {{ $readiness['ready'] ? 'border-primary-200 bg-primary-50 text-primary-900' : 'border-warning-200 bg-warning-50 text-warning-800' }}" role="status">
                                @if ($readiness['ready'])
                                    <i class="fas fa-circle-check mt-1" aria-hidden="true"></i>
                                    <p><span class="font-semibold">Siap dibatalkan.</span> <span class="font-mono tabular-nums">{{ $readiness['total'] }}</span> dari <span class="font-mono tabular-nums">{{ $readiness['total'] }}</span> karyawan belum berubah sejak impor.</p>
                                @else
                                    <i class="fas fa-triangle-exclamation mt-1" aria-hidden="true"></i>
                                    <p>
                                        <span class="font-semibold">Belum dapat dibatalkan.</span>
                                        <span class="font-mono tabular-nums">{{ count($readiness['conflicts']) }}</span> dari <span class="font-mono tabular-nums">{{ $readiness['total'] }}</span> karyawan sudah berubah atau memiliki data baru setelah impor
                                        ({{ implode(', ', array_slice($readiness['conflicts'], 0, 5)) }}{{ count($readiness['conflicts']) > 5 ? ', ...' : '' }}).
                                        Rapikan data tersebut lebih dulu; pembatalan dilakukan seluruhnya atau tidak sama sekali.
                                    </p>
                                @endif
                            </div>
                            <p class="text-xs text-surface-500">Dapat dibatalkan sampai {{ $undoDeadline->format('d/m/Y H:i') }}.</p>
                            <x-admin.button type="button" variant="danger" icon="fas fa-rotate-left" :disabled="! $readiness['ready']" @click="rollbackOpen = true">Batalkan Impor</x-admin.button>
                        @else
                            <p class="text-sm font-semibold text-surface-600">
                                <i class="fas fa-lock mr-1" aria-hidden="true"></i>
                                Impor ini tidak dapat lagi dibatalkan (masa pembatalan berakhir atau rekaman perubahan sudah dihapus).
                            </p>
                        @endif
                    </div>
                </section>
            @endif
        </div>

        {{-- KOLOM KANAN: linimasa dan verifikasi berkas --}}
        <aside class="lg:col-span-4 space-y-6">
            <section class="bg-white rounded-2xl border border-surface-200/80 p-5 shadow-xs" aria-labelledby="timeline-title">
                <h3 id="timeline-title" class="text-base font-bold text-surface-900 mb-4">Jejak Keputusan</h3>
                @include('admin.employees.import-batches.partials.timeline', ['log' => $log])
            </section>

            <section class="bg-white rounded-2xl border border-surface-200/80 p-5 shadow-xs" aria-labelledby="verify-title">
                <h3 id="verify-title" class="text-base font-bold text-surface-900 mb-3">Verifikasi Berkas</h3>
                @php $fileHash = $log->file_sha256 ?? ($log->metadata['file_sha256'] ?? null); @endphp
                <dl class="space-y-3">
                    <div>
                        <dt class="text-xs font-medium text-surface-500">Ukuran</dt>
                        <dd class="text-sm font-semibold text-surface-900">{{ number_format($log->file_size / 1024, 1, ',', '.') }} KB</dd>
                    </div>
                    <div x-data="{ copied: false }">
                        <dt class="text-xs font-medium text-surface-500" title="Kode unik berkas (SHA-256) untuk memastikan berkas yang disimpan sama dengan yang ditinjau.">Kode Verifikasi Berkas</dt>
                        <dd class="mt-1 flex items-start gap-2">
                            <span class="flex-1 font-mono text-xs text-surface-700 break-all">{{ $fileHash ?? '-' }}</span>
                            @if ($fileHash)
                                <button type="button" @click="navigator.clipboard.writeText('{{ $fileHash }}'); copied = true; setTimeout(() => copied = false, 2000)"
                                    aria-label="Salin kode verifikasi berkas"
                                    class="shrink-0 w-8 h-8 rounded-lg border border-surface-200 text-surface-600 hover:bg-surface-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500">
                                    <i class="fas" :class="copied ? 'fa-check text-primary-700' : 'fa-copy'" aria-hidden="true"></i>
                                </button>
                            @endif
                        </dd>
                    </div>
                </dl>
            </section>
        </aside>
    </div>

    {{-- Modal: tolak / tarik pengajuan --}}
    @if ($isPending)
        <div x-show="rejectOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-surface-950/30" role="dialog" aria-modal="true" aria-labelledby="reject-title" x-trap.noscroll="rejectOpen" tabindex="-1">
            <form method="POST" action="{{ route('admin.employees.import.batches.reject', $log) }}" @click.outside="rejectOpen = false" class="w-full max-w-md bg-white rounded-2xl shadow-xl p-6 space-y-4">
                @csrf
                <h3 id="reject-title" class="text-lg font-bold text-surface-900">{{ $isMaker ? 'Tarik pengajuan impor ini?' : 'Tolak pengajuan impor ini?' }}</h3>
                <p class="text-sm text-surface-600 leading-relaxed">Data karyawan tidak akan berubah dan berkas pengajuan dihapus. Tindakan ini tidak dapat dibatalkan.</p>
                <div>
                    <label for="reject-reason" class="block text-sm font-medium text-surface-700 mb-1.5">Alasan <span class="text-danger" aria-hidden="true">*</span></label>
                    <textarea id="reject-reason" name="reason" rows="3" minlength="10" required placeholder="Jelaskan alasan (minimal 10 karakter)" class="w-full rounded-xl border border-surface-200 px-3 py-2 text-sm focus:outline-hidden focus:border-primary-500 focus:ring-1 focus:ring-primary-500"></textarea>
                </div>
                <div class="flex justify-end gap-2">
                    <x-admin.button type="button" variant="secondary" autofocus @click="rejectOpen = false">Batal</x-admin.button>
                    <x-admin.button type="submit" variant="danger" once>{{ $isMaker ? 'Tarik Pengajuan' : 'Tolak Pengajuan' }}</x-admin.button>
                </div>
            </form>
        </div>
    @endif

    {{-- Modal: batalkan impor (undo) --}}
    @if ($canRollback && ($readiness['ready'] ?? false))
        <div x-show="rollbackOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-surface-950/30" role="dialog" aria-modal="true" aria-labelledby="rollback-title" x-trap.noscroll="rollbackOpen" tabindex="-1">
            <form method="POST" action="{{ route('admin.employees.import.batches.rollback', $log) }}" @click.outside="rollbackOpen = false" class="w-full max-w-md bg-white rounded-2xl shadow-xl p-6 space-y-4">
                @csrf
                <h3 id="rollback-title" class="text-lg font-bold text-surface-900">Batalkan impor #{{ $log->id }}?</h3>
                <p class="text-sm text-surface-600 leading-relaxed">
                    <strong>{{ $log->created_count }}</strong> karyawan baru akan dihapus dan <strong>{{ $log->updated_count }}</strong> karyawan dikembalikan ke kondisi sebelum impor.
                    Karyawan baru yang dihapus tidak dapat dipulihkan dari layar ini.
                </p>
                <div>
                    <label for="rollback-reason" class="block text-sm font-medium text-surface-700 mb-1.5">Alasan pembatalan <span class="text-danger" aria-hidden="true">*</span></label>
                    <textarea id="rollback-reason" name="reason" rows="3" minlength="10" required placeholder="Jelaskan alasan (minimal 10 karakter)" class="w-full rounded-xl border border-surface-200 px-3 py-2 text-sm focus:outline-hidden focus:border-primary-500 focus:ring-1 focus:ring-primary-500"></textarea>
                </div>
                @if ($typedConfirmation)
                    <div>
                        <label for="rollback-confirmation" class="block text-sm font-medium text-surface-700 mb-1.5">Ketik <span class="font-mono font-semibold">{{ $confirmationWord }}</span> untuk mengonfirmasi <span class="text-danger" aria-hidden="true">*</span></label>
                        <input id="rollback-confirmation" name="confirmation" type="text" x-model="confirmText" autocomplete="off" required class="w-full rounded-xl border border-surface-200 px-3 py-2 text-sm font-mono focus:outline-hidden focus:border-primary-500 focus:ring-1 focus:ring-primary-500">
                    </div>
                @endif
                <div class="flex justify-end gap-2">
                    <x-admin.button type="button" variant="secondary" autofocus @click="rollbackOpen = false">Batal</x-admin.button>
                    <x-admin.button type="submit" variant="danger" once x-bind:disabled="{{ $confirmExpression }}">Batalkan Impor</x-admin.button>
                </div>
            </form>
        </div>
    @endif
</div>
@endsection
