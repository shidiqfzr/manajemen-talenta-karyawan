<div>
    <!-- Daftar Penugasan Aktif -->
    @if(count($penugasanAktif) > 0)
        <div class="mb-6 space-y-3">
            <div class="flex items-center justify-between mb-2">
                <h4 class="text-xs font-bold text-surface-800 uppercase tracking-wider flex items-center gap-2">
                    <i class="fas fa-briefcase text-primary-600" aria-hidden="true"></i> Penugasan Sementara (Aktif)
                </h4>
                <x-admin.button variant="soft" size="sm" icon="fas fa-plus" wire:click="openModal" target="openModal">Tambah Penugasan</x-admin.button>
            </div>
            
            @foreach($penugasanAktif as $penugasan)
                <div class="bg-white border border-primary-200/80 rounded-xl p-4 shadow-xs relative overflow-hidden group">
                    <div class="absolute top-0 left-0 w-1.5 h-full bg-primary-600"></div>
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pl-2">
                        <div>
                            <div class="flex items-center gap-2 mb-1">
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-primary-100 text-primary-800 uppercase tracking-wider">
                                    {{ $penugasan->status_penugasan }}
                                </span>
                                <span class="text-xs text-surface-500">Sejak {{ \Carbon\Carbon::parse($penugasan->tmt_awal)->translatedFormat('d M Y') }}</span>
                            </div>
                            <h5 class="text-sm font-bold text-surface-900">{{ $penugasan->jabatan }}</h5>
                            <p class="text-xs text-surface-600">{{ $penugasan->unit_kerja }}</p>
                            @if($penugasan->nomor_sk)
                                <p class="text-[11px] text-surface-500 mt-1"><i class="fas fa-file-alt mr-1" aria-hidden="true"></i> SK: {{ $penugasan->nomor_sk }}</p>
                            @endif
                        </div>
                        
                        <div>
                            <button wire:click="cabutPenugasan({{ $penugasan->id }})" 
                                wire:confirm="Apakah Anda yakin ingin mencabut penugasan ini? Statusnya akan dialihkan ke riwayat historis."
                                class="text-xs font-semibold text-danger hover:text-danger-700 bg-danger-50 hover:bg-danger-100 px-3 py-2 rounded-lg transition-colors flex items-center gap-1.5">
                                <i class="fas fa-times-circle" aria-hidden="true"></i> Cabut Penugasan
                            </button>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @else
        <div class="text-center py-6 bg-surface-50 border border-surface-200 border-dashed rounded-xl mb-6">
            <div class="w-12 h-12 rounded-full bg-surface-100 flex items-center justify-center mx-auto mb-3 text-surface-500">
                <i class="fas fa-briefcase text-base" aria-hidden="true"></i>
            </div>
            <h4 class="text-xs font-bold text-surface-700 uppercase tracking-wider mb-1">Tidak Ada Penugasan Sementara</h4>
            <p class="text-xs text-surface-500 mb-3">Karyawan ini tidak memiliki tugas rangkap (Plt/Pjs) yang sedang aktif saat ini.</p>
            <x-admin.button wire:click="openModal" size="sm" icon="fas fa-plus" target="openModal">Tambah Penugasan Sementara</x-admin.button>
        </div>
    @endif

    {{-- Modal tambah penugasan sementara (cangkang x-admin.modal, DESIGN.md §7.6) --}}
    <div x-data="{ open: @entangle('showModal') }">
        <x-admin.modal size="lg" icon="fas fa-briefcase" title="Tambah Penugasan Sementara" form wire:submit="simpanPenugasan">
            <!-- Status Penugasan -->
            <div>
                <label class="block text-xs font-bold text-surface-700 mb-1.5">Jenis Penugasan <span class="text-danger">*</span></label>
                <select wire:model="statusPenugasan" required
                    class="w-full px-3 py-2 bg-surface-50 border border-surface-200 rounded-lg text-xs font-medium text-surface-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 focus:border-primary-500 transition">
                    <option value="Plt">Pelaksana Tugas (Plt)</option>
                    <option value="Pejabat Sementara (Pjs)">Pejabat Sementara (Pjs)</option>
                    <option value="Diperbantukan">Diperbantukan / BKO</option>
                </select>
                @error('statusPenugasan') <span class="text-danger text-xs mt-1">{{ $message }}</span> @enderror
            </div>

            <!-- Unit Kerja -->
            <div>
                <label class="block text-xs font-bold text-surface-700 mb-1.5">Unit Kerja Tujuan <span class="text-danger">*</span></label>
                <select wire:model.live="unitKerja" required
                    class="w-full px-3 py-2 bg-surface-50 border border-surface-200 rounded-lg text-xs font-medium text-surface-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 focus:border-primary-500 transition">
                    <option value="">-- Pilih Unit Kerja --</option>
                    @foreach($units as $u)
                        <option value="{{ $u->nama }}">{{ $u->nama }}</option>
                    @endforeach
                </select>
                @error('unitKerja') <span class="text-danger text-xs mt-1">{{ $message }}</span> @enderror
            </div>

            <!-- Jabatan -->
            <div>
                <label class="block text-xs font-bold text-surface-700 mb-1.5">Jabatan Tujuan <span class="text-danger">*</span></label>
                <select wire:model="jabatan" required @if(empty($positions)) disabled @endif
                    class="w-full px-3 py-2 bg-surface-50 border border-surface-200 rounded-lg text-xs font-medium text-surface-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 focus:border-primary-500 disabled:opacity-60 disabled:cursor-not-allowed transition">
                    <option value="">-- Pilih Jabatan --</option>
                    @foreach($positions as $p)
                        <option value="{{ $p->nama }}">{{ $p->nama }}</option>
                    @endforeach
                </select>
                @error('jabatan') <span class="text-danger text-xs mt-1">{{ $message }}</span> @enderror
            </div>

            <div class="grid grid-cols-2 gap-4">
                <!-- TMT Awal -->
                <div>
                    <label class="block text-xs font-bold text-surface-700 mb-1.5">TMT Penugasan <span class="text-danger">*</span></label>
                    <input type="date" wire:model="tmtAwal" required
                        class="w-full px-3 py-2 bg-surface-50 border border-surface-200 rounded-lg text-xs font-medium text-surface-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 focus:border-primary-500 transition">
                    @error('tmtAwal') <span class="text-danger text-xs mt-1">{{ $message }}</span> @enderror
                </div>
                <!-- Nomor SK -->
                <div>
                    <label class="block text-xs font-bold text-surface-700 mb-1.5">Nomor SK/Surat</label>
                    <input type="text" wire:model="nomorSk" placeholder="Opsional"
                        class="w-full px-3 py-2 bg-surface-50 border border-surface-200 rounded-lg text-xs font-medium text-surface-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 focus:border-primary-500 transition">
                </div>
            </div>

            <!-- Catatan -->
            <div>
                <label class="block text-xs font-bold text-surface-700 mb-1.5">Catatan Tambahan</label>
                <textarea wire:model="catatan" rows="2" placeholder="Keterangan penugasan..."
                    class="w-full px-3 py-2 bg-surface-50 border border-surface-200 rounded-lg text-xs font-medium text-surface-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 focus:border-primary-500 transition"></textarea>
            </div>

            <x-slot:footer>
                <x-admin.button type="submit" icon="fas fa-check" target="simpanPenugasan">Simpan Penugasan</x-admin.button>
            </x-slot:footer>
        </x-admin.modal>
    </div>
</div>
