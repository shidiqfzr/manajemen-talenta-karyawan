    <!-- ── 5. AUDIT TRAIL & MUTASI DETAIL MODAL ── -->
    @if ($selectedJobId && $detailJob)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-surface-900/60 backdrop-blur-xs animate-fade-in"
             x-data x-trap.noscroll="true" tabindex="-1" @keydown.escape.window="$wire.closeDetailModal()"
             role="dialog"
             aria-modal="true"
             aria-labelledby="modal-detail-title">
            <div class="bg-white rounded-2xl border border-surface-200 shadow-2xl max-w-2xl w-full p-6 space-y-4 max-h-[90vh] flex flex-col animate-scale-in">
                <!-- Header -->
                <div class="flex items-center justify-between pb-3.5 border-b border-surface-200">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-primary-100 text-primary-800 flex items-center justify-center font-bold text-base shadow-2xs">
                            <i class="fas fa-file-invoice" aria-hidden="true"></i>
                        </div>
                        <div>
                            <h3 id="modal-detail-title" class="text-sm font-bold text-surface-900">Detail Riwayat &amp; Jejak Audit Mutasi</h3>
                            <p class="text-xs text-surface-500">ID Riwayat: #{{ $detailJob->id }} &bull; NIK: <span class="font-mono">{{ $detailJob->employee_nik }}</span></p>
                        </div>
                    </div>
                    <button type="button" wire:click="closeDetailModal" class="text-surface-500 hover:text-surface-700 transition p-1 cursor-pointer" title="Tutup (Esc)">
                        <i class="fas fa-times text-sm" aria-hidden="true"></i>
                    </button>
                </div>

                <!-- Body (Scrollable) -->
                <div class="overflow-y-auto space-y-3.5 flex-1 pr-1 text-xs">
                    <!-- Status Banner -->
                    <div class="p-3.5 rounded-xl border flex items-center justify-between gap-3 {{ $detailJob->is_applied ? 'bg-primary-50/70 border-primary-200 text-primary-900' : 'bg-warning-50/70 border-warning-200 text-warning-800' }}">
                        <div class="flex items-center gap-2.5">
                            <i class="fas {{ $detailJob->is_applied ? 'fa-check-circle text-primary-600' : 'fa-clock text-warning-700' }} text-base" aria-hidden="true"></i>
                            <div>
                                <span class="font-bold text-xs">{{ $detailJob->is_applied ? 'Status: Aktif Menjabat (Telah Diterapkan)' : 'Status: Menunggu Jadwal TMT Efektif (Staging)' }}</span>
                                <p class="text-[11px] {{ $detailJob->is_applied ? 'text-primary-700' : 'text-warning-700' }} mt-0.5">
                                    {{ $detailJob->is_applied ? 'SK telah resmi berlaku dan tercatat sebagai riwayat jabatan aktif di data master.' : 'Formasi jabatan terkunci untuk pegawai ini dan menunggu aktivasi TMT.' }}
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
                            <h4 class="text-[11px] font-bold text-surface-500 uppercase tracking-wide">Data Pegawai</h4>
                            <div>
                                <div class="font-bold text-surface-900 text-sm">{{ $detailJob->employee?->nama ?? 'Pegawai' }}</div>
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

                <!-- Footer Actions -->
                <div class="pt-3 border-t border-surface-200 flex flex-col sm:flex-row items-center justify-between gap-2.5">
                    <div>
                        @if ($detailJob->file_sk)
                            <a href="{{ route('admin.mutasi.downloadSk', $detailJob->id) }}" target="_blank"
                                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-surface-100 hover:bg-surface-200 text-surface-800 text-xs font-semibold border border-surface-300 transition">
                                <i class="fas fa-file-pdf text-danger" aria-hidden="true"></i>
                                <span>Unduh Berkas SK PDF</span>
                            </a>
                        @endif
                    </div>
                    <div class="flex items-center gap-2">
                        @if (! $detailJob->is_applied)
                            <button type="button" wire:click="cancelScheduled({{ $detailJob->id }})" wire:confirm="Batalkan jadwal mutasi ini?"
                                class="px-3 py-1.5 rounded-xl bg-danger-50 hover:bg-danger-100 text-danger-700 text-xs font-semibold border border-danger-200 transition cursor-pointer">
                                Batalkan Jadwal
                            </button>
                            <button type="button" wire:click="applyNow({{ $detailJob->id }})" wire:confirm="Aktifkan mutasi ini sekarang?"
                                class="px-3.5 py-1.5 rounded-xl bg-primary-600 hover:bg-primary-700 text-white text-xs font-bold transition cursor-pointer shadow-xs">
                                Aktivasi Sekarang
                            </button>
                        @endif
                        <button type="button" wire:click="closeDetailModal"
                            class="px-4 py-1.5 rounded-xl bg-surface-100 hover:bg-surface-200 text-surface-800 text-xs font-semibold transition cursor-pointer">
                            Tutup
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif
