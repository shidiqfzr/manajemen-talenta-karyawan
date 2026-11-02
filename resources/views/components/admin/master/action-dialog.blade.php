{{--
    Dialog aksi Master Data (tambah, ubah, aktifkan/nonaktifkan, hapus).
    Dibuka lewat event window "master-action" dengan payload:
    { title, description, url, method, submit, tone: 'primary'|'danger', fields: [{ name, label, type, value, required, options }] }
    Formulir dikirim biasa (POST + @method) agar aturan server menjadi satu-satunya sumber kebenaran.
--}}
<div x-data="{
        show: false,
        submitting: false,
        action: null,
        open(detail) {
            this.action = detail;
            this.submitting = false;
            this.show = true;
        },
        close() {
            if (!this.submitting) this.show = false;
        }
    }"
    @master-action.window="open($event.detail)"
    @keydown.escape.window="close()"
    x-cloak>

    <div x-show="show"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        class="fixed inset-0 z-50 overflow-y-auto bg-surface-900/60 backdrop-blur-xs flex items-center justify-center p-4"
        role="dialog" aria-modal="true" aria-labelledby="master-action-title" x-trap.noscroll="show" tabindex="-1">

        <div @click.outside="close()" class="bg-white rounded-2xl border border-surface-200 shadow-2xl max-w-lg w-full overflow-hidden">
            <form method="POST" :action="action ? action.url : ''" @submit="submitting = true">
                @csrf
                <input type="hidden" name="_method" :value="action ? action.method : 'POST'">

                <div class="px-6 py-4 border-b border-surface-100 flex items-start justify-between gap-3 bg-surface-50/50">
                    <div class="min-w-0">
                        <h3 id="master-action-title" class="text-sm font-extrabold text-surface-900 leading-tight" x-text="action ? action.title : ''"></h3>
                        <p class="text-[11px] text-surface-500 mt-0.5" x-text="action ? action.description : ''"></p>
                    </div>
                    <button aria-label="Tutup" type="button" @click="close()"
                        class="text-surface-500 hover:text-surface-600 p-1.5 rounded-lg hover:bg-surface-100 transition cursor-pointer">
                        <i class="fas fa-times text-xs" aria-hidden="true"></i>
                    </button>
                </div>

                <div class="p-6 space-y-4">
                    <template x-for="field in (action ? action.fields : [])" :key="field.name">
                        <div>
                            <template x-if="field.type === 'hidden'">
                                <input type="hidden" :name="field.name" :value="field.value">
                            </template>

                            <template x-if="field.type === 'text' || field.type === 'number'">
                                <div>
                                    <label class="block text-xs font-bold text-surface-700 mb-1.5">
                                        <span x-text="field.label"></span>
                                        <span x-show="field.required" class="text-danger-700" aria-hidden="true">*</span>
                                    </label>
                                    <input :type="field.type" :name="field.name" :value="field.value" :required="field.required"
                                        class="w-full px-3.5 py-2.5 bg-surface-50/70 border border-surface-200 rounded-xl text-xs sm:text-sm font-medium text-surface-800 focus:bg-white focus:outline-none focus:ring-2 focus:ring-primary-500/30 focus:border-primary-500 transition">
                                </div>
                            </template>

                            <template x-if="field.type === 'select'">
                                <div>
                                    <label class="block text-xs font-bold text-surface-700 mb-1.5">
                                        <span x-text="field.label"></span>
                                        <span x-show="field.required" class="text-danger-700" aria-hidden="true">*</span>
                                    </label>
                                    <select :name="field.name" :required="field.required"
                                        class="w-full px-3.5 py-2.5 bg-surface-50/70 border border-surface-200 rounded-xl text-xs sm:text-sm font-medium text-surface-800 focus:bg-white focus:outline-none focus:ring-2 focus:ring-primary-500/30 focus:border-primary-500 transition">
                                        <template x-for="option in field.options" :key="option.value">
                                            <option :value="option.value" :selected="option.value === field.value" x-text="option.label"></option>
                                        </template>
                                    </select>
                                </div>
                            </template>

                            <template x-if="field.type === 'textarea'">
                                <div>
                                    <label class="block text-xs font-bold text-surface-700 mb-1.5">
                                        <span x-text="field.label"></span>
                                        <span x-show="field.required" class="text-danger-700" aria-hidden="true">*</span>
                                    </label>
                                    <textarea :name="field.name" rows="3" :required="field.required" :minlength="field.required ? 10 : null"
                                        :placeholder="field.placeholder || ''"
                                        class="w-full px-3.5 py-2.5 bg-surface-50/70 border border-surface-200 rounded-xl text-xs sm:text-sm font-medium text-surface-800 focus:bg-white focus:outline-none focus:ring-2 focus:ring-primary-500/30 focus:border-primary-500 transition" x-text="field.value"></textarea>
                                </div>
                            </template>
                        </div>
                    </template>
                </div>

                <div class="px-6 py-4 bg-surface-50/80 border-t border-surface-100 flex items-center justify-end gap-2.5">
                    <button type="button" @click="close()" :disabled="submitting"
                        class="px-4 py-2 border border-surface-200 text-surface-600 hover:bg-surface-100 text-xs font-semibold rounded-xl transition cursor-pointer">
                        Batal
                    </button>
                    <button type="submit" :disabled="submitting"
                        :class="action && action.tone === 'danger' ? 'bg-danger-700 hover:bg-danger-800' : 'bg-primary-600 hover:bg-primary-700'"
                        class="inline-flex items-center gap-2 px-5 py-2 text-white text-xs font-bold rounded-xl shadow-md transition cursor-pointer disabled:opacity-60 disabled:cursor-wait">
                        <i class="fas fa-spinner fa-spin text-xs" x-show="submitting" x-cloak aria-hidden="true"></i>
                        <span x-text="submitting ? 'Memproses…' : (action ? action.submit : 'Simpan')"></span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
