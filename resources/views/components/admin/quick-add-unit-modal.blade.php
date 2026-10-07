<div x-data="{
        show: false,
        loading: false,
        errorMessage: '',
        formData: {
            nama: '',
            wilayah: 'Wilayah Kalimantan Barat',
            kode: ''
        },
        resetForm() {
            this.formData = {
                nama: '',
                wilayah: 'Wilayah Kalimantan Barat',
                kode: ''
            };
            this.errorMessage = '';
            this.loading = false;
        },
        async submitUnit() {
            if (!this.formData.nama.trim()) {
                this.errorMessage = 'Nama unit kerja wajib diisi.';
                return;
            }
            this.loading = true;
            this.errorMessage = '';

            try {
                const response = await fetch('{{ route('admin.master.units.store') }}', {
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
                        this.errorMessage = data.message || 'Terjadi kesalahan saat menyimpan unit kerja.';
                    }
                    this.loading = false;
                    return;
                }

                // Dispatch global event with new unit
                window.dispatchEvent(new CustomEvent('unit-created', { detail: data.unit }));
                this.resetForm();
                this.show = false;
            } catch (err) {
                this.errorMessage = 'Koneksi gagal atau sesi telah berakhir.';
                this.loading = false;
            }
        }
    }"
    @open-quick-add-unit.window="
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
                        <i class="fas fa-building-flag"></i>
                    </div>
                    <div>
                        <h3 class="text-sm font-extrabold text-slate-900 leading-tight">Tambah Unit Kerja Baru</h3>
                        <p class="text-[11px] text-slate-500">Unit akan langsung terpilih otomatis di form</p>
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
                    <label class="block text-xs font-bold text-slate-700 mb-1.5">Nama Unit Kerja <span class="text-red-500">*</span></label>
                    <input type="text" x-model="formData.nama" placeholder="Contoh: Kebun Inti Gunung Meliau / PKS Samuntai"
                        @keydown.enter.prevent="submitUnit()"
                        class="w-full px-3.5 py-2.5 bg-slate-50/70 border border-slate-200 rounded-xl text-xs sm:text-sm font-medium text-slate-800 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500/30 focus:border-emerald-500 transition">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1.5">Wilayah Operasional <span class="text-red-500">*</span></label>
                    <select x-model="formData.wilayah"
                        class="w-full px-3.5 py-2.5 bg-slate-50/70 border border-slate-200 rounded-xl text-xs sm:text-sm font-medium text-slate-800 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500/30 focus:border-emerald-500 transition">
                        <option value="Regional Office (Kantor Direksi Pontianak)">Regional Office (Kantor Direksi Pontianak)</option>
                        <option value="Wilayah Kalimantan Barat">Wilayah Kalimantan Barat</option>
                        <option value="Wilayah Kalimantan Selatan/Tengah">Wilayah Kalimantan Selatan/Tengah</option>
                        <option value="Wilayah Kalimantan Timur">Wilayah Kalimantan Timur</option>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1.5">Kode Singkatan (Opsional)</label>
                    <input type="text" x-model="formData.kode" placeholder="Contoh: KGM / PKSM"
                        @keydown.enter.prevent="submitUnit()"
                        class="w-full px-3.5 py-2.5 bg-slate-50/70 border border-slate-200 rounded-xl text-xs sm:text-sm font-mono text-slate-800 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500/30 focus:border-emerald-500 transition">
                </div>
            </div>

            <!-- Modal Footer -->
            <div class="px-6 py-4 bg-slate-50/80 border-t border-slate-100 flex items-center justify-end gap-2.5">
                <button type="button" @click="if (!loading) show = false;" :disabled="loading"
                    class="px-4 py-2 border border-slate-200 text-slate-600 hover:bg-slate-100 text-xs font-semibold rounded-xl transition cursor-pointer">
                    Batal
                </button>
                <button type="button" @click="submitUnit()" :disabled="loading"
                    class="inline-flex items-center gap-2 px-5 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold rounded-xl shadow-md shadow-emerald-600/20 active:scale-95 transition cursor-pointer disabled:opacity-50">
                    <i class="fas fa-spinner fa-spin text-xs" x-show="loading" x-cloak></i>
                    <i class="fas fa-check text-xs" x-show="!loading"></i>
                    <span>Simpan &amp; Terapkan</span>
                </button>
            </div>
        </div>
    </div>
</div>
