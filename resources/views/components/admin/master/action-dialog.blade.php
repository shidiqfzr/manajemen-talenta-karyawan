{{--
    Dialog aksi Master Data (tambah, ubah, aktifkan/nonaktifkan, hapus), dibangun di atas cangkang x-admin.modal.
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
    @master-action.window="open($event.detail)">

    <x-admin.modal open="show" close="close()" busy="submitting" form size="lg"
        tone-expression="action ? action.tone : 'primary'" icon="fas fa-pen-to-square"
        icon-expression="action && action.tone === 'danger' ? 'fas fa-triangle-exclamation' : 'fas fa-pen-to-square'"
        title-id="master-action-title" ::action="action ? action.url : ''" @submit="submitting = true">
        <x-slot:heading><span x-text="action ? action.title : ''"></span></x-slot:heading>
        <x-slot:subheading><span x-text="action ? action.description : ''"></span></x-slot:subheading>

        <input type="hidden" name="_method" :value="action ? action.method : 'POST'">

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
                            class="w-full px-3.5 py-2.5 bg-surface-50/70 border border-surface-200 rounded-lg text-xs sm:text-sm font-medium text-surface-800 focus:bg-white focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/30 focus-visible:border-primary-500 transition">
                    </div>
                </template>

                <template x-if="field.type === 'select'">
                    <div>
                        <label class="block text-xs font-bold text-surface-700 mb-1.5">
                            <span x-text="field.label"></span>
                            <span x-show="field.required" class="text-danger-700" aria-hidden="true">*</span>
                        </label>
                        <select :name="field.name" :required="field.required"
                            class="w-full px-3.5 py-2.5 bg-surface-50/70 border border-surface-200 rounded-lg text-xs sm:text-sm font-medium text-surface-800 focus:bg-white focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/30 focus-visible:border-primary-500 transition">
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
                            class="w-full px-3.5 py-2.5 bg-surface-50/70 border border-surface-200 rounded-lg text-xs sm:text-sm font-medium text-surface-800 focus:bg-white focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/30 focus-visible:border-primary-500 transition" x-text="field.value"></textarea>
                    </div>
                </template>
            </div>
        </template>

        <x-slot:footer>
            <x-admin.button type="submit" variant="primary" x-show="!(action && action.tone === 'danger')" x-bind:disabled="submitting">
                <span x-text="submitting ? 'Memproses…' : (action ? action.submit : 'Simpan')"></span>
            </x-admin.button>
            <x-admin.button type="submit" variant="danger" x-show="action && action.tone === 'danger'" x-cloak x-bind:disabled="submitting">
                <span x-text="submitting ? 'Memproses…' : (action ? action.submit : 'Simpan')"></span>
            </x-admin.button>
        </x-slot:footer>
    </x-admin.modal>
</div>
