<div x-data="{
        show: false,
        loading: false,
        errorMessage: '',
        formData: {
            nama: '',
            level: 'Karpim',
            bidang: 'TAN',
            rm_level: 'RM-3'
        },
        resetForm() {
            this.formData = {
                nama: '',
                level: 'Karpim',
                bidang: 'TAN',
                rm_level: 'RM-3'
            };
            this.errorMessage = '';
            this.loading = false;
        },
        async submitPosition() {
            if (!this.formData.nama.trim()) {
                this.errorMessage = 'Nama jabatan wajib diisi.';
                return;
            }
            this.loading = true;
            this.errorMessage = '';

            try {
                const response = await fetch('{{ route('admin.master.positions.store') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify(this.formData)
                });

                const data = await response.json();

                if (!response.ok) {
                    if (data.errors && data.errors.nama) {
                        this.errorMessage = data.errors.nama[0];
                    } else {
                        this.errorMessage = data.message || 'Terjadi kesalahan saat menyimpan jabatan.';
                    }
                    this.loading = false;
                    return;
                }

                // Dispatch global event with new position
                window.dispatchEvent(new CustomEvent('position-created', { detail: data.position }));
                this.resetForm();
                this.show = false;
            } catch (err) {
                this.errorMessage = 'Koneksi gagal atau sesi telah berakhir.';
                this.loading = false;
            }
        }
    }"
    @open-quick-add-position.window="
        show = true; 
        errorMessage = ''; 
        if ($event.detail && $event.detail.nama) { 
            formData.nama = $event.detail.nama; 
        }
    "
    @keydown.escape.window="if (show && !loading) show = false;"
    x-cloak>

    <!-- Modal Backdrop & Dialog -->
    <div x-show="show" 
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">

        <div x-show="show"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 scale-95 translate-y-2"
            x-transition:enter-end="opacity-100 scale-100 translate-y-0"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100 scale-100 translate-y-0"
            x-transition:leave-end="opacity-0 scale-95 translate-y-2"
            @click.outside="if (!loading) show = false;"
            class="bg-white rounded-2xl border border-slate-200 shadow-2xl max-w-lg w-full overflow-hidden">

            <!-- Modal Header -->
            <div class="px-6 py-4.5 border-b border-slate-100 flex items-center justify-between bg-slate-50/50">
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-xl bg-emerald-100 text-emerald-700 flex items-center justify-center text-sm font-bold shrink-0">
                        <i class="fas fa-id-card-clip"></i>
                    </div>
                    <div>
                        <h3 class="text-sm font-extrabold text-slate-900 leading-tight">Tambah Jabatan Formasi Baru</h3>
                        <p class="text-[11px] text-slate-500">Jabatan akan langsung diterapkan otomatis pada input form</p>
                    </div>
                </div>
                <button type="button" @click="if (!loading) show = false;"
                    class="text-slate-400 hover:text-slate-600 p-1.5 rounded-lg hover:bg-slate-100 transition cursor-pointer">
                    <i class="fas fa-times text-xs"></i>
                </button>
            </div>

            <!-- Modal Body -->
            <div class="p-6 space-y-4">
                <!-- Error Alert -->
                <div x-show="errorMessage" x-cloak class="p-3 bg-red-50 border border-red-200 rounded-xl text-xs text-red-700 font-semibold flex items-center gap-2">
                    <i class="fas fa-circle-exclamation text-red-500 shrink-0"></i>
                    <span x-text="errorMessage"></span>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1.5">Nama Jabatan Formasi <span class="text-red-500">*</span></label>
                    <input type="text" x-model="formData.nama" placeholder="Contoh: Asisten Afdeling / Manajer Kebun"
                        @keydown.enter.prevent="submitPosition()"
                        class="w-full px-3.5 py-2.5 bg-slate-50/70 border border-slate-200 rounded-xl text-xs sm:text-sm font-medium text-slate-800 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500/30 focus:border-emerald-500 transition">
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1.5">Level Pegawai <span class="text-red-500">*</span></label>
                        <select x-model="formData.level"
                            class="w-full px-3.5 py-2.5 bg-slate-50/70 border border-slate-200 rounded-xl text-xs sm:text-sm font-medium text-slate-800 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500/30 focus:border-emerald-500 transition">
                            <option value="Karpim">Karpim (Pimpinan)</option>
                            <option value="Karpel">Karpel (Pelaksana)</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1.5">Bidang Fungsional</label>
                        <select x-model="formData.bidang"
                            class="w-full px-3.5 py-2.5 bg-slate-50/70 border border-slate-200 rounded-xl text-xs sm:text-sm font-medium text-slate-800 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500/30 focus:border-emerald-500 transition">
                            <option value="TAN">TAN (Tanaman)</option>
                            <option value="TEK">TEK (Teknik &amp; Pengolahan)</option>
                            <option value="KEU">KEU (Keuangan)</option>
                            <option value="UMU">UMU (Umum &amp; SDM)</option>
                        </select>
                    </div>
                </div>

                <!-- RM Band Eselon (Karpim only) -->
                <div x-show="formData.level === 'Karpim'" x-cloak>
                    <label class="block text-xs font-bold text-slate-700 mb-1.5">RM Band (Eselon Pimpinan)</label>
                    <select x-model="formData.rm_level"
                        class="w-full px-3.5 py-2.5 bg-slate-50/70 border border-slate-200 rounded-xl text-xs sm:text-sm font-medium text-slate-800 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500/30 focus:border-emerald-500 transition">
                        <option value="RM-1">RM-1 (Pimpinan / Senior Management)</option>
                        <option value="RM-2">RM-2 (Madya / Middle Management)</option>
                        <option value="RM-3">RM-3 (Pratama / First Line Management)</option>
                    </select>
                </div>
            </div>

            <!-- Modal Footer -->
            <div class="px-6 py-4 bg-slate-50/80 border-t border-slate-100 flex items-center justify-end gap-2.5">
                <button type="button" @click="if (!loading) show = false;" :disabled="loading"
                    class="px-4 py-2 border border-slate-200 text-slate-600 hover:bg-slate-100 text-xs font-semibold rounded-xl transition cursor-pointer">
                    Batal
                </button>
                <button type="button" @click="submitPosition()" :disabled="loading"
                    class="inline-flex items-center gap-2 px-5 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold rounded-xl shadow-md shadow-emerald-600/20 active:scale-95 transition cursor-pointer disabled:opacity-50">
                    <i class="fas fa-spinner fa-spin text-xs" x-show="loading" x-cloak></i>
                    <i class="fas fa-check text-xs" x-show="!loading"></i>
                    <span>Simpan &amp; Terapkan</span>
                </button>
            </div>
        </div>
    </div>
</div>
