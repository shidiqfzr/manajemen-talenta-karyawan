<div>
    <!-- Daftar Penugasan Aktif -->
    @if(count($penugasanAktif) > 0)
        <div class="mb-6 space-y-3">
            <div class="flex items-center justify-between mb-2">
                <h4 class="text-sm font-bold text-slate-800 flex items-center gap-2">
                    <i class="fas fa-briefcase text-emerald-600"></i> Penugasan Sementara (Aktif)
                </h4>
                <button wire:click="openModal" class="text-xs font-semibold text-emerald-600 hover:text-emerald-700 bg-emerald-50 hover:bg-emerald-100 px-3 py-1.5 rounded-lg transition-colors">
                    <i class="fas fa-plus mr-1"></i> Tambah Baru
                </button>
            </div>
            
            @foreach($penugasanAktif as $penugasan)
                <div class="bg-white border border-emerald-200 rounded-xl p-4 shadow-sm relative overflow-hidden group">
                    <div class="absolute top-0 left-0 w-1 h-full bg-emerald-500"></div>
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pl-2">
                        <div>
                            <div class="flex items-center gap-2 mb-1">
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-100 text-emerald-800 uppercase tracking-wider">
                                    {{ $penugasan->status_penugasan }}
                                </span>
                                <span class="text-xs text-slate-500">Sejak {{ \Carbon\Carbon::parse($penugasan->tmt_awal)->translatedFormat('d M Y') }}</span>
                            </div>
                            <h5 class="text-sm font-bold text-slate-900">{{ $penugasan->jabatan }}</h5>
                            <p class="text-xs text-slate-600">{{ $penugasan->unit_kerja }}</p>
                            @if($penugasan->nomor_sk)
                                <p class="text-[11px] text-slate-500 mt-1"><i class="fas fa-file-alt mr-1"></i> SK: {{ $penugasan->nomor_sk }}</p>
                            @endif
                        </div>
                        
                        <div>
                            <button wire:click="cabutPenugasan({{ $penugasan->id }})" 
                                wire:confirm="Apakah Anda yakin ingin mencabut penugasan ini? Statusnya akan masuk ke riwayat historis."
                                class="text-xs font-medium text-red-600 hover:text-red-700 bg-red-50 hover:bg-red-100 px-3 py-2 rounded-lg transition-colors flex items-center gap-1.5">
                                <i class="fas fa-times-circle"></i> Cabut Penugasan
                            </button>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @else
        <div class="text-center py-6 bg-slate-50 border border-slate-200 border-dashed rounded-xl mb-6">
            <div class="w-12 h-12 rounded-full bg-slate-100 flex items-center justify-center mx-auto mb-3">
                <i class="fas fa-briefcase text-slate-400"></i>
            </div>
            <h4 class="text-sm font-semibold text-slate-700 mb-1">Tidak Ada Penugasan Sementara</h4>
            <p class="text-xs text-slate-500 mb-4">Karyawan ini tidak memiliki tugas tambahan (Plt/Pjs) yang sedang aktif.</p>
            <button wire:click="openModal" class="inline-flex items-center text-xs font-semibold text-white bg-slate-800 hover:bg-slate-700 px-4 py-2 rounded-lg transition-colors">
                <i class="fas fa-plus mr-1.5"></i> Tambah Penugasan Sementara
            </button>
        </div>
    @endif

    <!-- Modal Form Tambah Penugasan -->
    <div x-data="{ open: @entangle('showModal') }" x-show="open" x-cloak class="relative z-50" aria-labelledby="modal-title" role="dialog" aria-modal="true">
        <!-- Backdrop -->
        <div x-show="open" x-transition.opacity class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm transition-opacity"></div>

        <div class="fixed inset-0 z-10 w-screen overflow-y-auto">
            <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
                <div x-show="open" x-transition
                    class="relative transform overflow-hidden rounded-2xl bg-white text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-lg">
                    
                    <div class="bg-white px-4 pb-4 pt-5 sm:p-6 sm:pb-4">
                        <div class="flex items-center justify-between mb-5 pb-4 border-b border-slate-100">
                            <h3 class="text-lg font-bold text-slate-900" id="modal-title">Tambah Penugasan Sementara</h3>
                            <button @click="open = false" type="button" class="text-slate-400 hover:text-slate-500">
                                <i class="fas fa-times text-lg"></i>
                            </button>
                        </div>

                        <form wire:submit="simpanPenugasan" class="space-y-4">
                            <!-- Status Penugasan -->
                            <div>
                                <label class="block text-xs font-bold text-slate-700 mb-1.5">Jenis Penugasan <span class="text-red-500">*</span></label>
                                <select wire:model="statusPenugasan" required
                                    class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-sm text-slate-800 focus:ring-2 focus:ring-emerald-500/30 focus:border-emerald-500">
                                    <option value="Plt">Pelaksana Tugas (Plt)</option>
                                    <option value="Pejabat Sementara (Pjs)">Pejabat Sementara (Pjs)</option>
                                    <option value="Diperbantukan">Diperbantukan / BKO</option>
                                </select>
                                @error('statusPenugasan') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
                            </div>

                            <!-- Unit Kerja -->
                            <div>
                                <label class="block text-xs font-bold text-slate-700 mb-1.5">Unit Kerja Tujuan <span class="text-red-500">*</span></label>
                                <select wire:model.live="unitKerja" required
                                    class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-sm text-slate-800 focus:ring-2 focus:ring-emerald-500/30 focus:border-emerald-500">
                                    <option value="">-- Pilih Unit Kerja --</option>
                                    @foreach($units as $u)
                                        <option value="{{ $u->nama }}">{{ $u->nama }}</option>
                                    @endforeach
                                </select>
                                @error('unitKerja') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
                            </div>

                            <!-- Jabatan -->
                            <div>
                                <label class="block text-xs font-bold text-slate-700 mb-1.5">Jabatan Tujuan <span class="text-red-500">*</span></label>
                                <select wire:model="jabatan" required @if(empty($positions)) disabled @endif
                                    class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-sm text-slate-800 focus:ring-2 focus:ring-emerald-500/30 focus:border-emerald-500 disabled:opacity-60 disabled:cursor-not-allowed">
                                    <option value="">-- Pilih Jabatan --</option>
                                    @foreach($positions as $p)
                                        <option value="{{ $p->nama }}">{{ $p->nama }}</option>
                                    @endforeach
                                </select>
                                @error('jabatan') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
                            </div>

                            <div class="grid grid-cols-2 gap-4">
                                <!-- TMT Awal -->
                                <div>
                                    <label class="block text-xs font-bold text-slate-700 mb-1.5">TMT Penugasan <span class="text-red-500">*</span></label>
                                    <input type="date" wire:model="tmtAwal" required
                                        class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-sm text-slate-800 focus:ring-2 focus:ring-emerald-500/30 focus:border-emerald-500">
                                    @error('tmtAwal') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
                                </div>
                                <!-- Nomor SK -->
                                <div>
                                    <label class="block text-xs font-bold text-slate-700 mb-1.5">Nomor SK/Surat</label>
                                    <input type="text" wire:model="nomorSk" placeholder="Opsional"
                                        class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-sm text-slate-800 focus:ring-2 focus:ring-emerald-500/30 focus:border-emerald-500">
                                </div>
                            </div>

                            <!-- Catatan -->
                            <div>
                                <label class="block text-xs font-bold text-slate-700 mb-1.5">Catatan Tambahan</label>
                                <textarea wire:model="catatan" rows="2" placeholder="Keterangan penugasan..."
                                    class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-sm text-slate-800 focus:ring-2 focus:ring-emerald-500/30 focus:border-emerald-500"></textarea>
                            </div>

                        </form>
                    </div>
                    <div class="bg-slate-50 px-4 py-4 sm:flex sm:flex-row-reverse sm:px-6 border-t border-slate-200">
                        <button type="button" wire:click="simpanPenugasan" wire:loading.attr="disabled"
                            class="inline-flex w-full justify-center rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-emerald-700 sm:ml-3 sm:w-auto transition-colors">
                            <span wire:loading.remove wire:target="simpanPenugasan">Simpan Penugasan</span>
                            <span wire:loading wire:target="simpanPenugasan"><i class="fas fa-spinner fa-spin mr-1"></i> Menyimpan...</span>
                        </button>
                        <button type="button" @click="open = false"
                            class="mt-3 inline-flex w-full justify-center rounded-lg bg-white px-4 py-2 text-sm font-semibold text-slate-900 shadow-sm ring-1 ring-inset ring-slate-300 hover:bg-slate-50 sm:mt-0 sm:w-auto transition-colors">
                            Batal
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
