    {{-- Detail riwayat & jejak audit mutasi: cangkang x-admin.modal (DESIGN.md §7.6) --}}
    @if ($selectedJobId && $detailJob)
        <x-admin.modal open="true" close="$wire.closeDetailModal()" size="xl" icon="fas fa-file-invoice" title-id="modal-detail-title" cancel="Tutup" scroll>
            <x-slot:heading>Detail Riwayat &amp; Jejak Audit Mutasi</x-slot:heading>
            <x-slot:subtitle>ID Riwayat: #{{ $detailJob->id }} &bull; NIK: <span class="font-mono">{{ $detailJob->employee_nik }}</span></x-slot:subtitle>
            <div class="text-xs space-y-3.5">
                <!-- Status Banner -->
                <div class="p-3.5 rounded-xl border flex items-center justify-between gap-3 {{ $detailJob->is_applied ? 'bg-primary-50/70 border-primary-200 text-primary-900' : 'bg-warning-50/70 border-warning-200 text-warning-800' }}">
                    <div class="flex items-center gap-2.5">
                        <i class="fas {{ $detailJob->is_applied ? 'fa-check-circle text-primary-600' : 'fa-clock text-warning-700' }} text-base" aria-hidden="true"></i>
                        <div>
                            <span class="font-bold text-xs">{{ $detailJob->is_applied ? 'Status: Aktif Menjabat (Telah Diterapkan)' : 'Status: Menunggu Jadwal TMT Efektif (Staging)' }}</span>
                            <p class="text-[11px] {{ $detailJob->is_applied ? 'text-primary-700' : 'text-warning-700' }} mt-0.5">
                                {{ $detailJob->is_applied ? 'SK telah resmi berlaku dan tercatat sebagai riwayat jabatan aktif di data master.' : 'Formasi jabatan terkunci untuk karyawan ini dan menunggu aktivasi TMT.' }}
                            </p>
                        </div>
                    </div>
                    <span class="px-2.5 py-1 rounded-full text-[10px] font-bold border {{ $detailJob->is_applied ? 'bg-primary-100 text-primary-800 border-primary-300' : 'bg-warning-100 text-warning-800 border-warning-200' }}">
                        {{ $detailJob->isRecordOnly() ? $detailJob->sumber_label : $detailJob->jenis_mutasi_label }}
                    </span>
                </div>

                <!-- Profil Karyawan & Penugasan Grid -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div class="p-3 bg-surface-50 rounded-xl border border-surface-200 space-y-2">
                        <h4 class="text-[11px] font-bold text-surface-500 uppercase tracking-wide">Data Karyawan</h4>
                        <div>
                            <div class="font-bold text-surface-900 text-sm">{{ $detailJob->employee?->nama ?? 'Karyawan' }}</div>
                            <div class="text-surface-500 font-mono text-[11px]">NIK: {{ $detailJob->employee_nik }}</div>
                        </div>
                        <div class="pt-2 border-t border-surface-200 flex items-center justify-between text-[11px]">
                            <span class="text-surface-500">Golongan / Level:</span>
                            <span class="font-semibold text-surface-800">{{ $detailJob->golongan ?? $detailJob->employee?->golongan ?? '—' }} ({{ $detailJob->level ?? $detailJob->employee?->level ?? '—' }})</span>
                        </div>
                    </div>

                    <div class="p-3 bg-surface-50 rounded-xl border border-surface-200 space-y-2">
                        <h4 class="text-[11px] font-bold text-surface-500 uppercase tracking-wide">Jabatan &amp; Formasi</h4>
                        <div>
                            <div class="font-bold text-primary-900 text-sm">{{ $detailJob->jabatan }}</div>
                            <div class="text-surface-600 text-[11px] flex items-center gap-1.5 mt-0.5">
                                <i class="fas fa-building text-[10px] text-surface-400" aria-hidden="true"></i>
                                <span>{{ $detailJob->unit_kerja }}</span>
                            </div>
                        </div>
                        <div class="pt-2 border-t border-surface-200 flex items-center justify-between text-[11px]">
                            <span class="text-surface-500">Status Penugasan:</span>
                            <span class="font-semibold text-surface-800">{{ $detailJob->status_penugasan ?? 'Definitif' }}</span>
                        </div>
                    </div>
                </div>

                <!-- Rincian Legalitas SK -->
                <div class="p-3.5 bg-white rounded-xl border border-surface-200 space-y-2.5">
                    <h4 class="text-[11px] font-bold text-surface-500 uppercase tracking-wide">Legalitas Surat Keputusan (SK)</h4>
                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 text-xs">
                        <div>
                            <span class="text-[10px] text-surface-500 block">Nomor SK:</span>
                            <span class="font-mono font-semibold text-surface-800">{{ $detailJob->nomor_sk ?? '—' }}</span>
                        </div>
                        <div>
                            <span class="text-[10px] text-surface-500 block">Tanggal SK:</span>
                            <span class="font-semibold text-surface-800">{{ $detailJob->tanggal_sk ? \Carbon\Carbon::parse($detailJob->tanggal_sk)->translatedFormat('d F Y') : '—' }}</span>
                        </div>
                        <div>
                            <span class="text-[10px] text-surface-500 block">TMT Efektif:</span>
                            <span class="font-semibold text-surface-800">{{ $detailJob->tmt_awal ? \Carbon\Carbon::parse($detailJob->tmt_awal)->translatedFormat('d F Y') : '—' }}</span>
                        </div>
                    </div>
                    @if ($detailJob->catatan)
                        <div class="pt-2 border-t border-surface-100 text-[11px]">
                            <span class="text-surface-500 block">Catatan / Uraian Tugas:</span>
                            <p class="text-surface-700 italic mt-0.5">{{ $detailJob->catatan }}</p>
                        </div>
                    @endif
                </div>

                <!-- Panel Kepatuhan Dispensasi (Jika Ada) -->
                @if ($detailJob->is_dispensasi)
                    <div class="p-3.5 bg-warning-50/80 rounded-xl border border-warning-200 space-y-1.5">
                        <div class="flex items-center gap-2 text-warning-800 font-bold text-xs">
                            <i class="fas fa-shield text-warning-700" aria-hidden="true"></i>
                            <span>Pengecualian / Dispensasi Khusus Direksi</span>
                        </div>
                        <p class="text-[11px] text-warning-800 leading-relaxed">
                            {{ $detailJob->alasan_dispensasi ?? 'Mutasi disetujui melalui pertimbangan operasional khusus oleh pimpinan/direksi.' }}
                        </p>
                    </div>
                @endif

                {{-- Catatan kepatuhan: peringatan kualifikasi promosi yang belum terpenuhi saat SK diproses (informasional) --}}
                @if ($detailJob->catatan_kepatuhan)
                    <div class="p-3.5 bg-warning-50/60 rounded-xl border border-warning-200 flex items-start gap-2.5">
                        <i class="fas fa-triangle-exclamation text-warning-700 text-sm mt-0.5 shrink-0" aria-hidden="true"></i>
                        <p class="text-[11px] text-warning-800 leading-relaxed">{{ $detailJob->catatan_kepatuhan }}</p>
                    </div>
                @endif

                {{-- Panel Jejak Audit (Audit Trail SSOT) --}}
                <div class="p-3.5 bg-surface-100/70 rounded-xl border border-surface-200 space-y-2">
                    <h4 class="text-[11px] font-bold text-surface-500 uppercase tracking-wide flex items-center gap-1.5">
                        <i class="fas fa-shield-halved text-surface-400" aria-hidden="true"></i>
                        <span>Jejak Audit Sistem (Audit Trail)</span>
                    </h4>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 text-[11px]">
                        <div>
                            <span class="text-surface-500 block">Waktu Pencatatan Sistem:</span>
                            <span class="font-semibold text-surface-800">{{ $detailJob->created_at ? $detailJob->created_at->translatedFormat('d F Y, H:i:s') : '—' }} WIB</span>
                        </div>
                        <div>
                            <span class="text-surface-500 block">Administrator Pembuat:</span>
                            <span class="font-semibold text-surface-800">{{ $detailJob->creator?->name ?? 'Administrator SDM' }}</span>
                        </div>
                    </div>
                    @if ($detailJob->batch_id)
                        <div class="pt-2 border-t border-surface-200/80 flex items-center justify-between text-[11px]">
                            <span class="text-surface-500">Kategori Transaksi:</span>
                            <span class="font-bold text-primary-800 bg-primary-100 px-2 py-0.5 rounded border border-primary-200">
                                Mutasi Berantai (Gerbong #{{ $detailJob->chain_order ?? 1 }})
                            </span>
                        </div>
                    @endif
                </div>
            </div>
            @if ($detailJob->file_sk)
                <x-slot:footerStart>
                    <a href="{{ route('admin.mutasi.downloadSk', $detailJob->id) }}" target="_blank"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-white hover:bg-surface-100 text-surface-800 text-xs font-semibold border border-surface-300 transition">
                        <i class="fas fa-file-pdf text-danger" aria-hidden="true"></i>
                        <span>Unduh Berkas SK PDF</span>
                    </a>
                </x-slot:footerStart>
            @endif
            @if (! $detailJob->is_applied)
                <x-slot:footer>
                    <x-admin.button variant="danger" size="sm" wire:click="cancelScheduled({{ $detailJob->id }})" wire:confirm="Batalkan jadwal mutasi ini?" target="cancelScheduled">Batalkan Jadwal</x-admin.button>
                    <x-admin.button size="sm" wire:click="applyNow({{ $detailJob->id }})" wire:confirm="Aktifkan mutasi ini sekarang?" target="applyNow">Aktivasi Sekarang</x-admin.button>
                </x-slot:footer>
            @endif
        </x-admin.modal>
    @endif
