{{-- Detail SK struktur organisasi: linimasa, selisih, dampak, keputusan, dan daftar penempatan ulang (DESIGN.md §8.5a). --}}
<div class="space-y-6" x-data="{ approveOpen: false, rejectOpen: false, cancelOpen: false }">
    <x-admin.flash />

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 space-y-6">
            {{-- Identitas --}}
            <section class="bg-white border border-surface-200 rounded-2xl p-5 sm:p-6 space-y-3">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-wide text-surface-500">Nomor SK</p>
                        <h2 class="text-lg font-bold text-surface-900">{{ $decree->sk_number }}</h2>
                    </div>
                    <x-admin.decree-status :status="$decree->status" :effective-date="$decree->effective_date" />
                </div>
                <p class="text-sm text-surface-700">{{ $decree->title }}</p>
                <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-2 text-sm">
                    <div><dt class="text-xs font-bold text-surface-500">Tanggal SK</dt><dd class="text-surface-800">{{ $decree->sk_date->translatedFormat('d M Y') }}</dd></div>
                    <div><dt class="text-xs font-bold text-surface-500">Tanggal Berlaku</dt><dd class="text-surface-800">{{ $decree->effective_date->translatedFormat('d M Y') }}</dd></div>
                    @if ($decree->revokes)
                        <div class="sm:col-span-2"><dt class="text-xs font-bold text-surface-500">Mencabut / Menggantikan</dt><dd class="text-surface-800">{{ $decree->revokes }}</dd></div>
                    @endif
                    @if ($decree->note)
                        <div class="sm:col-span-2"><dt class="text-xs font-bold text-surface-500">Catatan</dt><dd class="text-surface-800">{{ $decree->note }}</dd></div>
                    @endif
                    <div><dt class="text-xs font-bold text-surface-500">Kode Verifikasi Lampiran</dt><dd class="text-surface-800 font-mono text-xs break-all">{{ substr($decree->source_sha256, 0, 16) }}</dd></div>
                </dl>
                <div class="flex flex-wrap gap-2 pt-1">
                    @if ($decree->source_path && ! str_starts_with($decree->source_path, 'baseline:'))
                        <x-admin.button href="{{ route('admin.master.sk.download', [$decree, 'lampiran']) }}" variant="secondary" size="sm" icon="fas fa-file-excel">Unduh Lampiran SK</x-admin.button>
                    @endif
                    @if ($decree->pdf_path)
                        <x-admin.button href="{{ route('admin.master.sk.download', [$decree, 'salinan']) }}" variant="secondary" size="sm" icon="fas fa-file-pdf">Unduh Salinan SK</x-admin.button>
                    @endif
                </div>
            </section>

            @if ($decree->status === 'failed' && $decree->failure_message)
                <div role="alert" class="p-4 bg-danger-50 text-danger-700 border border-danger-200 rounded-xl text-sm space-y-1">
                    <p class="font-bold"><i class="fas fa-circle-xmark mr-1.5" aria-hidden="true"></i>Penerapan SK gagal; tidak ada perubahan yang tersimpan</p>
                    <p>{{ $decree->failure_message }}</p>
                </div>
            @endif

            @if ($decree->status === 'stale')
                <div role="alert" class="p-4 bg-warning-50 text-warning-800 border border-warning-200 rounded-xl text-sm">
                    Struktur organisasi berubah sejak SK diajukan, sehingga SK perlu ditinjau ulang dan diajukan kembali. Tidak ada perubahan yang diterapkan.
                </div>
            @endif

            @if ($warnings !== [])
                <div class="p-4 bg-warning-50 text-warning-800 border border-warning-200 rounded-xl text-sm space-y-1">
                    <p class="font-bold">Catatan penerapan</p>
                    <ul class="list-disc pl-5">
                        @foreach ($warnings as $warning)
                            <li>{{ $warning }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            {{-- Selisih --}}
            <section class="bg-white border border-surface-200 rounded-2xl p-5 sm:p-6" aria-labelledby="detail-selisih">
                <h3 id="detail-selisih" class="text-sm font-bold text-surface-900 mb-3">Perubahan Struktur</h3>
                <x-admin.structure.diff-list :diff="$diff" />
            </section>

            @if ($impact !== null)
                <section class="bg-white border border-surface-200 rounded-2xl p-5 sm:p-6 text-sm text-surface-700 space-y-1" aria-labelledby="detail-dampak">
                    <h3 id="detail-dampak" class="text-sm font-bold text-surface-900 mb-2">Dampak</h3>
                    <p>Karyawan terdampak: <strong class="tabular-nums">{{ $impact['affected_employees'] }}</strong></p>
                    <p>Formasi jabatan yang menjadi melebihi kuota: <strong class="tabular-nums">{{ $impact['over_quota_seats'] }}</strong></p>
                    @if ($impact['scheduled_mutations'] !== [])
                        <p class="font-semibold text-warning-800">Mutasi terjadwal yang terkena ({{ count($impact['scheduled_mutations']) }}):</p>
                        <ul class="list-disc pl-5">
                            @foreach ($impact['scheduled_mutations'] as $item)
                                <li>{{ $item }}</li>
                            @endforeach
                        </ul>
                    @endif
                </section>
            @endif

            {{-- Daftar penempatan ulang setelah SK diterapkan --}}
            @if ($decree->status === 'applied')
                <section class="bg-white border border-surface-200 rounded-2xl p-5 sm:p-6" aria-labelledby="detail-worklist">
                    <div class="flex flex-wrap items-center justify-between gap-3 mb-3">
                        <h3 id="detail-worklist" class="text-sm font-bold text-surface-900">Karyawan Perlu Penempatan Ulang</h3>
                        <x-admin.button href="{{ route('admin.mutasi.index') }}" variant="secondary" size="sm" icon="fas fa-right-left">Buka Mutasi</x-admin.button>
                    </div>
                    @if ($worklist->isEmpty())
                        <p class="text-sm text-surface-600">Tidak ada karyawan yang perlu ditempatkan ulang akibat SK ini.</p>
                    @else
                        <p class="text-xs text-surface-500 mb-2">Penerapan SK tidak memindahkan karyawan. Pindahkan melalui modul Mutasi.</p>
                        <div class="overflow-x-auto">
                            <table class="w-full text-left border-collapse text-xs">
                                <thead class="bg-surface-50 text-surface-600">
                                    <tr><x-admin.table.th>Nama</x-admin.table.th><x-admin.table.th>Jabatan</x-admin.table.th><x-admin.table.th>Unit Kerja</x-admin.table.th></tr>
                                </thead>
                                <tbody class="divide-y divide-surface-200 text-surface-800">
                                    @foreach ($worklist as $employee)
                                        {{-- Kunci baris memakai urutan, bukan NIK, agar NIK tidak muncul pada HTML (UU PDP). --}}
                                        <tr wire:key="worklist-{{ $loop->index }}"><td class="px-3 py-2">{{ $employee->nama }}</td><td class="px-3 py-2">{{ $employee->jabatan }}</td><td class="px-3 py-2">{{ $employee->unit_kerja }}</td></tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </section>
            @endif
        </div>

        {{-- Kolom kanan: keputusan dan linimasa --}}
        <aside class="space-y-6">
            <section class="bg-white border border-surface-200 rounded-2xl p-5 space-y-3" aria-labelledby="detail-keputusan">
                <h3 id="detail-keputusan" class="text-sm font-bold text-surface-900">Keputusan</h3>

                @if ($canApprove)
                    <x-admin.button class="w-full" icon="fas fa-check" @click="approveOpen = true">Setujui SK</x-admin.button>
                    <x-admin.button class="w-full" variant="secondary" icon="fas fa-xmark" @click="rejectOpen = true">Tolak SK</x-admin.button>
                @elseif ($blockedByFourEyes)
                    <p class="text-sm text-surface-600">Anda adalah pengaju SK ini sehingga tidak dapat menyetujuinya sendiri (prinsip empat mata). Persetujuan dilakukan admin lain.</p>
                    <x-admin.button class="w-full" variant="secondary" icon="fas fa-xmark" @click="rejectOpen = true">Tarik / Tolak SK</x-admin.button>
                @endif

                @if ($canPrepare && in_array($decree->status, ['draft', 'stale']))
                    <x-admin.button class="w-full" href="{{ route('admin.master.sk.review', $decree) }}" icon="fas fa-pen-to-square" wire:navigate>Lanjutkan Tinjauan</x-admin.button>
                @endif

                @if ($decree->status === 'failed')
                    <x-admin.button class="w-full" icon="fas fa-rotate" wire:click="reapply" target="reapply">Terapkan Ulang</x-admin.button>
                @endif

                @if ($canPrepare && $isOpen)
                    <x-admin.button class="w-full" variant="danger" icon="fas fa-ban" @click="cancelOpen = true">Batalkan SK</x-admin.button>
                @endif

                @if ($decree->status === 'approved')
                    <p class="text-xs text-surface-500">
                        @if ($decree->effective_date->toDateString() > now()->toDateString())
                            Diterapkan otomatis pada {{ $decree->effective_date->translatedFormat('d M Y') }}.
                        @else
                            Menunggu penerapan otomatis oleh penjadwal harian.
                        @endif
                    </p>
                @endif
                @if ($decree->status === 'applied')
                    <p class="text-xs text-surface-500">SK yang sudah diterapkan tidak dapat dibatalkan; koreksi dilakukan dengan SK baru.</p>
                @endif
            </section>

            <section class="bg-white border border-surface-200 rounded-2xl p-5" aria-labelledby="detail-linimasa">
                <h3 id="detail-linimasa" class="text-sm font-bold text-surface-900 mb-3">Riwayat Keputusan</h3>
                @if ($timeline->isEmpty())
                    <p class="text-xs text-surface-500">Belum ada keputusan tercatat.</p>
                @else
                    <ol class="space-y-3 border-l-2 border-surface-200 pl-4">
                        @foreach ($timeline as $log)
                            <li wire:key="log-{{ $log->id }}">
                                <p class="text-sm font-semibold text-surface-800">{{ \App\Models\MasterAuditLog::actionLabel($log->action) }}</p>
                                <p class="text-xs text-surface-500">{{ $log->user_name }} · {{ $log->created_at->translatedFormat('d M Y H:i') }}</p>
                                @if ($log->reason)<p class="text-xs text-surface-600 mt-0.5">{{ $log->reason }}</p>@endif
                            </li>
                        @endforeach
                    </ol>
                @endif
            </section>
        </aside>
    </div>

    {{-- Dialog konfirmasi (DESIGN.md §7.6): Batal menjadi fokus awal pada aksi berisiko --}}
    @if ($canApprove)
        <x-admin.modal open="approveOpen" title="Setujui SK ini?" description="SK yang disetujui akan diterapkan ke struktur organisasi pada tanggal berlakunya (langsung bila tanggalnya sudah tiba). Penerapan tidak memindahkan karyawan.">
            <x-slot:footer>
                <x-admin.button icon="fas fa-check" wire:click="approve" target="approve" @click="approveOpen = false">Setujui SK</x-admin.button>
            </x-slot:footer>
        </x-admin.modal>
    @endif

    <x-admin.modal open="rejectOpen" tone="danger" title="Tolak atau tarik SK ini?" description="SK kembali tidak berlaku dan tidak mengubah struktur. Alasan dicatat pada riwayat.">
        <x-admin.form.reason-field id="reject-reason" name="reason" label="Alasan" wire:model="reason" :native-validation="false" hint="Alasan dicatat pada riwayat keputusan (minimal 10 karakter)." />
        <x-slot:footer>
            <x-admin.button variant="danger" icon="fas fa-xmark" wire:click="reject" target="reject">Tolak SK</x-admin.button>
        </x-slot:footer>
    </x-admin.modal>

    <x-admin.modal open="cancelOpen" tone="danger" title="Batalkan SK ini?" description="Pembatalan hanya mungkin sebelum SK diterapkan dan membebaskan slot SK terbuka.">
        <x-admin.form.reason-field id="cancel-reason" name="reason" label="Alasan" wire:model="reason" :native-validation="false" hint="Alasan dicatat pada riwayat keputusan (minimal 10 karakter)." />
        <x-slot:footer>
            <x-admin.button variant="danger" icon="fas fa-ban" wire:click="cancel" target="cancel">Batalkan SK</x-admin.button>
        </x-slot:footer>
    </x-admin.modal>
</div>
