<!-- ============================================================ -->
<!-- STEP 2: ANALISIS & PRATINJAU DIFF WORKBENCH (FULL VIEWPORT)  -->
<!-- ============================================================ -->
<div x-show="currentStep === 2" x-transition.opacity.duration.300ms class="space-y-6">
    
    <!-- Compact File Context Strip & Quick Actions -->
    <div class="bg-white rounded-xl border border-surface-200/80 px-4 py-2.5 shadow-2xs flex flex-wrap items-center justify-between gap-3 text-xs">
        <div class="flex items-center gap-2.5 min-w-0">
            <div class="w-7 h-7 rounded-lg bg-primary-50 text-primary-700 border border-primary-200/80 flex items-center justify-center text-xs shrink-0">
                <i class="fas fa-file-excel" aria-hidden="true"></i>
            </div>
            <div class="flex items-center gap-2 min-w-0 flex-wrap">
                <span class="text-surface-500 font-medium">Berkas Aktif:</span>
                <strong class="font-bold text-surface-800 truncate max-w-[200px] sm:max-w-xs md:max-w-md" x-text="importFileName"></strong>
                <span class="text-surface-300">•</span>
                <span class="text-surface-500 tabular-nums font-mono text-[11px]" x-text="importFileSize"></span>
                <span class="hidden md:inline-flex items-center gap-1 text-[11px] font-semibold text-primary-700 bg-primary-50/80 px-2 py-0.5 rounded-full border border-primary-200/60">
                    <i class="fas fa-check-circle text-[10px]" aria-hidden="true"></i> Analisis Selesai
                </span>
            </div>
        </div>

        <div class="flex items-center gap-2 shrink-0">
            <x-admin.button type="button" variant="secondary" size="sm" icon="fas fa-arrow-left" @click="resetToStep1()">Ganti Berkas</x-admin.button>
        </div>
    </div>

    <!-- 5-CARD UNIFIED KPI TABS (PRIMARY FILTER WORKBENCH) -->
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3.5">
        <x-admin.kpi-card
            title="Semua Data"
            tab-key="all"
            x-text="validationSummary?.total_processed ?? 0"
            subtitle="Total spreadsheet"
            icon="fas fa-list"
            theme="neutral" />

        <x-admin.kpi-card
            title="Perubahan"
            tab-key="updated"
            x-text="validationSummary?.updated ?? 0"
            subtitle="Ada penyesuaian atribut"
            icon="fas fa-pen-to-square"
            theme="warning" />

        <x-admin.kpi-card
            title="Karyawan Baru"
            tab-key="created"
            x-text="validationSummary?.created ?? 0"
            subtitle="Akan ditambahkan"
            icon="fas fa-user-plus"
            theme="primary" />

        <x-admin.kpi-card
            title="Data Tetap"
            tab-key="unchanged"
            x-text="validationSummary?.unchanged ?? 0"
            subtitle="Data sudah mutakhir"
            icon="fas fa-shield-halved"
            theme="info" />

        <x-admin.kpi-card
            title="Baris Terkendala"
            tab-key="errors"
            x-text="validationSummary?.skipped ?? 0"
            theme="danger"
            icon="fas fa-triangle-exclamation"
            class="col-span-2 sm:col-span-1">
            <div class="flex items-center justify-between"
                :class="(validationSummary?.skipped ?? 0) > 0 ? 'text-danger-700 font-semibold' : 'text-surface-500'">
                <span x-text="(validationSummary?.skipped ?? 0) > 0 ? 'Format belum sesuai' : 'Semua data valid'"></span>
            </div>
        </x-admin.kpi-card>
    </div>

    <!-- Peringatan tinjauan: informatif, tidak menghalangi impor -->
    <div x-show="(validationSummary?.warning_count ?? 0) > 0" x-cloak role="status"
        class="flex items-start gap-3 px-4 py-3 rounded-xl bg-warning-50 border border-warning-200 text-warning-800">
        <i class="fas fa-circle-info mt-0.5 text-warning-700" aria-hidden="true"></i>
        <p class="text-xs leading-relaxed">
            <span class="font-bold" x-text="validationSummary?.warning_count"></span> karyawan tercatat pada formasi yang sudah tidak sesuai Master Formasi
            (unit tidak aktif, jabatan tidak lagi tercantum, atau melebihi kuota). Impor tetap dapat dilanjutkan; tinjau melalui Master Formasi atau modul Mutasi.
        </p>
    </div>

    {{-- Tabel pratinjau: komponen Livewire pada komponen tabel bersama; tab dipilih dari kartu KPI di atas (peristiwa import-preview-tab). --}}
    <livewire:admin.employees.import-preview-table />

    {{-- Bar aksi bersama (DESIGN.md §7.1): Batal di kiri, aksi utama di kanan; menempel di bawah layar agar tetap terjangkau di atas tabel panjang. --}}
    <x-admin.form.footer cancel-click="resetToStep1()">
        <x-slot:status>
            <span x-show="(validationSummary?.valid_count ?? 0) > 0">
                Siap mengimpor <strong class="text-primary-700 font-extrabold" x-text="validationSummary?.valid_count ?? 0"></strong> data karyawan yang valid.
            </span>
            <span x-show="(validationSummary?.valid_count ?? 0) === 0" class="text-danger font-semibold">
                Semua data ditolak. Silakan unduh baris terkendala untuk diperbaiki.
            </span>
        </x-slot:status>

        <x-slot:primary>
            <x-admin.button type="button" variant="primary" class="shadow-primary-600/20 shadow-md" @click="executeCommitImport()" x-bind:disabled="(validationSummary?.valid_count ?? 0) === 0 || isCommitting">
                <template x-if="!isCommitting">
                    <span class="inline-flex items-center gap-2">
                        <i class="fas fa-check-circle" aria-hidden="true"></i>
                        <span x-text="(requiresApproval ? 'Ajukan Persetujuan untuk ' : 'Konfirmasi & Impor ') + (validationSummary?.valid_count ?? 0) + ' Data Valid'"></span>
                    </span>
                </template>

                <template x-if="isCommitting">
                    <span class="inline-flex items-center gap-2">
                        <i class="fas fa-circle-notch fa-spin" aria-hidden="true"></i>
                        <span>Menyimpan Data Karyawan...</span>
                    </span>
                </template>
            </x-admin.button>
        </x-slot:primary>
    </x-admin.form.footer>
</div>
