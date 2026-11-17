{{-- Detail SK struktur: pola layar Riwayat & Persetujuan Impor (admin/employees/import-batches/show.blade.php), DESIGN.md §8.5a. --}}
<div class="space-y-6" x-data="{ approveOpen: false, rejectOpen: false, cancelOpen: false }"
    @decree-action-finished.window="approveOpen = false; rejectOpen = false; cancelOpen = false">
    <x-admin.flash />

    @if ($decree->status === 'failed')
        <div role="alert" class="flex items-start gap-3 rounded-xl border border-danger-200 bg-danger-50 px-4 py-3 text-sm text-danger-800">
            <i class="fas fa-circle-xmark mt-1" aria-hidden="true"></i>
            <p><span class="font-semibold">Penerapan SK gagal; tidak ada perubahan yang tersimpan.</span>@if ($decree->failure_message) {{ $decree->failure_message }}@endif Perbaiki penyebabnya lalu terapkan ulang, atau batalkan SK ini.</p>
        </div>
    @endif

    @if ($decree->status === 'stale')
        <div role="status" class="flex items-start gap-3 rounded-xl border border-warning-200 bg-warning-50 px-4 py-3 text-sm text-warning-800">
            <i class="fas fa-triangle-exclamation mt-1" aria-hidden="true"></i>
            <p><span class="font-semibold">SK perlu ditinjau ulang.</span> Struktur organisasi berubah sejak SK diajukan, sehingga tidak ada perubahan yang diterapkan. Tinjau ulang lalu ajukan kembali.</p>
        </div>
    @endif

    @if ($warnings !== [])
        <div role="status" class="flex items-start gap-3 rounded-xl border border-warning-200 bg-warning-50 px-4 py-3 text-sm text-warning-800">
            <i class="fas fa-triangle-exclamation mt-1" aria-hidden="true"></i>
            <div>
                <p class="font-semibold">Catatan penerapan</p>
                <ul class="list-disc pl-5">
                    @foreach ($warnings as $warning)
                        <li>{{ $warning }}</li>
                    @endforeach
                </ul>
            </div>
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
        {{-- KOLOM KIRI: ringkasan, perubahan, dampak, keputusan, dan penempatan ulang --}}
        <div class="lg:col-span-8 space-y-6">
            <section class="bg-white rounded-2xl border border-surface-200/80 p-5 shadow-xs space-y-4" aria-labelledby="summary-title">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <h3 id="summary-title" class="text-base font-bold text-surface-900">Ringkasan SK</h3>
                    <x-admin.decree-status :status="$decree->status" :effective-date="$decree->effective_date" />
                </div>
                <dl class="flex flex-wrap items-center gap-x-5 gap-y-1 text-sm text-surface-600">
                    <div class="flex items-center gap-1.5"><dt class="sr-only">Tanggal SK</dt><dd><i class="far fa-calendar text-surface-400 mr-1" aria-hidden="true"></i>SK {{ $decree->sk_date->translatedFormat('d M Y') }}</dd></div>
                    <div class="flex items-center gap-1.5"><dt class="sr-only">Tanggal berlaku</dt><dd><i class="far fa-calendar-check text-surface-400 mr-1" aria-hidden="true"></i>Berlaku {{ $decree->effective_date->translatedFormat('d M Y') }}</dd></div>
                    @if ($decree->submitter)
                        <div class="flex items-center gap-1.5"><dt class="sr-only">Pengaju</dt><dd><i class="fas fa-user text-surface-400 mr-1" aria-hidden="true"></i>{{ $decree->submitter->name }}</dd></div>
                    @endif
                </dl>
                <p class="text-sm text-surface-800">{{ $decree->title }}</p>
                @if ($decree->revokes)
                    <p class="text-xs text-surface-600"><span class="font-semibold text-surface-700">Mencabut / menggantikan:</span> {{ $decree->revokes }}</p>
                @endif
                @if ($decree->note)
                    <p class="text-xs text-surface-600"><span class="font-semibold text-surface-700">Catatan:</span> {{ $decree->note }}</p>
                @endif

                {{-- Kartu bernilai tetap dibuat netral agar perhatian tertuju pada angka yang berubah (DESIGN.md §5.3). --}}
                @php $before = $summary['before']; @endphp
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                    <x-admin.kpi-card title="Unit" :value="$summary['units']" :subtitle="$before ? 'Sebelumnya '.$before['units'] : 'Struktur setelah SK'" icon="fas fa-building" :theme="$before && $before['units'] !== $summary['units'] ? 'primary' : 'neutral'" />
                    <x-admin.kpi-card title="Formasi Jabatan" :value="$summary['pairs']" :subtitle="$before ? 'Sebelumnya '.$before['pairs'] : 'Struktur setelah SK'" icon="fas fa-table-list" :theme="$before && $before['pairs'] !== $summary['pairs'] ? 'primary' : 'neutral'" />
                    <x-admin.kpi-card title="Total Kuota" :value="$summary['kuota']" :subtitle="$before ? 'Sebelumnya '.$before['kuota'] : 'Struktur setelah SK'" icon="fas fa-users" :theme="$before && $before['kuota'] !== $summary['kuota'] ? 'primary' : 'neutral'" />
                    <x-admin.kpi-card title="Jumlah Perubahan" :value="$summary['changes']" :subtitle="$summary['baseline'] ? 'SK dasar instalasi' : 'Terhadap struktur sebelumnya'" icon="fas fa-list-check" :theme="$summary['changes'] > 0 ? 'warning' : 'neutral'" />
                </div>
            </section>

            <section class="bg-white rounded-2xl border border-surface-200/80 p-5 shadow-xs" aria-labelledby="detail-selisih">
                <h3 id="detail-selisih" class="text-base font-bold text-surface-900 mb-3">Perubahan Struktur</h3>
                @if ($isBaseline)
                    <p class="text-sm text-surface-600">SK dasar instalasi: struktur awal dimuat dari data baseline, sehingga tidak ada selisih yang tercatat. Perubahan oleh SK berikutnya dibandingkan dengan struktur ini.</p>
                @else
                    <x-admin.structure.diff-list :diff="$diff" />
                @endif
            </section>

            @if ($impact !== null)
                <section class="bg-white rounded-2xl border border-surface-200/80 p-5 shadow-xs space-y-3" aria-labelledby="detail-dampak">
                    <h3 id="detail-dampak" class="text-base font-bold text-surface-900">Dampak</h3>
                    <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-2 text-sm">
                        <div class="flex items-baseline justify-between gap-3"><dt class="text-surface-600">Karyawan terdampak</dt><dd class="font-mono tabular-nums font-semibold text-surface-900">{{ $impact['affected_employees'] }}</dd></div>
                        <div class="flex items-baseline justify-between gap-3"><dt class="text-surface-600">Formasi jabatan melebihi kuota</dt><dd class="font-mono tabular-nums font-semibold {{ $impact['over_quota_seats'] > 0 ? 'text-warning-800' : 'text-surface-900' }}">{{ $impact['over_quota_seats'] }}</dd></div>
                    </dl>
                    @if ($impact['scheduled_mutations'] !== [])
                        <div role="status" class="rounded-xl border border-warning-200 bg-warning-50 px-4 py-3 text-sm text-warning-800 space-y-1">
                            <p class="font-semibold"><i class="fas fa-triangle-exclamation mr-1.5" aria-hidden="true"></i>{{ count($impact['scheduled_mutations']) }} mutasi terjadwal terkena perubahan ini:</p>
                            <ul class="list-disc pl-5">
                                @foreach ($impact['scheduled_mutations'] as $item)
                                    <li>{{ $item }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                </section>
            @endif

            {{-- Keputusan --}}
            <section class="bg-white rounded-2xl border border-surface-200/80 p-5 shadow-xs space-y-3" aria-labelledby="detail-keputusan">
                <h3 id="detail-keputusan" class="text-base font-bold text-surface-900">Keputusan</h3>
                <div class="flex flex-wrap items-center gap-3">
                    @if ($canApprove)
                        <x-admin.button icon="fas fa-check" @click="approveOpen = true">Setujui SK</x-admin.button>
                        <x-admin.button variant="secondary" icon="fas fa-ban" @click="rejectOpen = true">Tolak SK</x-admin.button>
                    @elseif ($blockedByFourEyes)
                        <p class="flex-1 min-w-[16rem] text-sm text-surface-600 leading-relaxed">
                            <i class="fas fa-user-lock mr-1 text-surface-500" aria-hidden="true"></i>
                            Anda adalah pengaju SK ini sehingga tidak dapat menyetujuinya sendiri (prinsip empat mata). Admin lain perlu meninjau dan menyetujui. Anda dapat menarik pengajuan bila ada kekeliruan.
                        </p>
                        <x-admin.button variant="secondary" icon="fas fa-ban" @click="rejectOpen = true">Tarik / Tolak SK</x-admin.button>
                    @endif

                    @if ($canPrepare && in_array($decree->status, ['draft', 'stale']))
                        <x-admin.button href="{{ route('admin.master.sk.review', $decree) }}" icon="fas fa-pen-to-square" wire:navigate>Lanjutkan Tinjauan</x-admin.button>
                    @endif

                    @if ($decree->status === 'failed')
                        <x-admin.button icon="fas fa-rotate" wire:click="reapply" target="reapply">Terapkan Ulang</x-admin.button>
                    @endif
                </div>

                @if ($decree->status === 'approved')
                    <p class="text-sm text-surface-600">
                        @if ($decree->effective_date->toDateString() > now()->toDateString())
                            SK disetujui dan diterapkan otomatis pada {{ $decree->effective_date->translatedFormat('d M Y') }}.
                        @else
                            SK disetujui dan menunggu penerapan otomatis oleh penjadwal harian.
                        @endif
                    </p>
                @elseif ($decree->status === 'applied')
                    <p class="text-sm text-surface-600"><i class="fas fa-lock mr-1" aria-hidden="true"></i>SK yang sudah diterapkan tidak dapat dibatalkan; koreksi dilakukan dengan SK baru.</p>
                @elseif (in_array($decree->status, ['rejected', 'cancelled'], true))
                    <p class="text-sm text-surface-600">SK ini sudah ditutup dan tidak mengubah struktur organisasi.</p>
                @endif
            </section>

            {{-- Tindakan berisiko: pembatalan SK yang belum diterapkan --}}
            @if ($canPrepare && $isOpen)
                <section class="bg-white rounded-2xl border border-danger-200 shadow-xs overflow-hidden" aria-labelledby="risk-title">
                    <div class="px-5 py-3.5 bg-danger-50 border-b border-danger-200">
                        <h3 id="risk-title" class="text-base font-bold text-danger-800"><i class="fas fa-triangle-exclamation mr-1.5" aria-hidden="true"></i>Tindakan Berisiko: Batalkan SK</h3>
                    </div>
                    <div class="p-5 space-y-3">
                        <p class="text-sm text-surface-700">Pembatalan hanya mungkin sebelum SK diterapkan, tidak mengubah struktur organisasi, dan membebaskan slot SK terbuka. Alasan pembatalan dicatat pada jejak keputusan.</p>
                        <x-admin.button variant="danger" icon="fas fa-ban" @click="cancelOpen = true">Batalkan SK</x-admin.button>
                    </div>
                </section>
            @endif

            {{-- Daftar penempatan ulang setelah SK diterapkan --}}
            @if ($decree->status === 'applied')
                <section class="bg-white rounded-2xl border border-surface-200/80 shadow-xs overflow-hidden" aria-labelledby="detail-worklist">
                    <div class="px-5 py-3.5 border-b border-surface-200 bg-surface-50 flex flex-wrap items-center justify-between gap-2">
                        <h3 id="detail-worklist" class="text-base font-bold text-surface-900">Karyawan Perlu Penempatan Ulang</h3>
                        <x-admin.button href="{{ route('admin.mutasi.index') }}" variant="secondary" size="sm" icon="fas fa-right-left">Buka Mutasi</x-admin.button>
                    </div>
                    <p class="px-5 pt-3 text-xs text-surface-500">Penerapan SK tidak memindahkan karyawan. Pindahkan melalui modul Mutasi.</p>
                    <div class="relative overflow-x-auto">
                        <table class="w-full text-left border-collapse text-xs">
                            <thead class="bg-surface-50">
                                <tr class="border-b border-surface-200">
                                    <x-admin.table.th>Nama</x-admin.table.th>
                                    <x-admin.table.th>Jabatan</x-admin.table.th>
                                    <x-admin.table.th>Unit Kerja</x-admin.table.th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-surface-100 text-surface-800">
                                @forelse ($worklist as $employee)
                                    {{-- Kunci baris memakai urutan, bukan NIK, agar NIK tidak muncul pada HTML (UU PDP). --}}
                                    <tr wire:key="worklist-{{ $loop->index }}" class="hover:bg-surface-50/75 transition">
                                        <td class="px-4 py-3 font-semibold text-surface-900">{{ $employee->nama }}</td>
                                        <td class="px-4 py-3">{{ $employee->jabatan }}</td>
                                        <td class="px-4 py-3">{{ $employee->unit_kerja }}</td>
                                    </tr>
                                @empty
                                    <x-admin.table.empty-state :colspan="3" icon="fas fa-circle-check" message="Tidak ada karyawan yang perlu ditempatkan ulang akibat SK ini." />
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </section>
            @endif
        </div>

        {{-- KOLOM KANAN: jejak keputusan, berkas, dan verifikasi --}}
        <aside class="lg:col-span-4 space-y-6">
            <section class="bg-white rounded-2xl border border-surface-200/80 p-5 shadow-xs" aria-labelledby="timeline-title">
                <h3 id="timeline-title" class="text-base font-bold text-surface-900 mb-4">Jejak Keputusan</h3>
                <x-admin.decision-timeline :steps="$steps" />
            </section>

            @if (! $isBaseline)
                <section class="bg-white rounded-2xl border border-surface-200/80 p-5 shadow-xs space-y-3" aria-labelledby="files-title">
                    <h3 id="files-title" class="text-base font-bold text-surface-900">Berkas SK</h3>
                    <div class="flex flex-col gap-2">
                        <x-admin.button href="{{ route('admin.master.sk.download', [$decree, 'lampiran']) }}" variant="secondary" size="sm" icon="fas fa-file-excel">Unduh Lampiran SK</x-admin.button>
                        @if ($decree->pdf_path)
                            <x-admin.button href="{{ route('admin.master.sk.download', [$decree, 'salinan']) }}" variant="secondary" size="sm" icon="fas fa-file-pdf">Unduh Salinan SK</x-admin.button>
                        @endif
                    </div>
                </section>
            @endif

            <x-admin.file-verification :hash="$decree->source_sha256" />
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

    @if ($canApprove || $blockedByFourEyes)
        <x-admin.modal open="rejectOpen" tone="danger" title="Tolak atau tarik SK ini?" description="SK kembali tidak berlaku dan tidak mengubah struktur. Alasan dicatat pada jejak keputusan.">
            <x-admin.form.reason-field id="reject-reason" name="reason" label="Alasan" wire:model="reason" :native-validation="false" hint="Alasan dicatat pada jejak keputusan (minimal 10 karakter)." />
            <x-slot:footer>
                <x-admin.button variant="danger" icon="fas fa-ban" wire:click="reject" target="reject">Tolak SK</x-admin.button>
            </x-slot:footer>
        </x-admin.modal>
    @endif

    @if ($canPrepare && $isOpen)
        <x-admin.modal open="cancelOpen" tone="danger" title="Batalkan SK ini?" description="Pembatalan hanya mungkin sebelum SK diterapkan dan membebaskan slot SK terbuka.">
            <x-admin.form.reason-field id="cancel-reason" name="reason" label="Alasan" wire:model="reason" :native-validation="false" hint="Alasan dicatat pada jejak keputusan (minimal 10 karakter)." />
            <x-slot:footer>
                <x-admin.button variant="danger" icon="fas fa-ban" wire:click="cancel" target="cancel">Batalkan SK</x-admin.button>
            </x-slot:footer>
        </x-admin.modal>
    @endif
</div>
