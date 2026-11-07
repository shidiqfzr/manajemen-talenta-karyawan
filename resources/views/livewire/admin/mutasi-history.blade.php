<div class="space-y-6">

    @include('livewire.admin.partials.mutasi-history.flash')

    <!-- ── 2. EXECUTIVE KPI SUMMARY CARDS (INTERACTIVE FILTER TABS) ── -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3.5">
        <x-admin.kpi-card
            title="Total Riwayat Mutasi"
            :value="$totalCount"
            subtitle="Semua riwayat mutasi & SK"
            icon="fas fa-history"
            theme="primary"
            :coloredValue="false"
            :active="$statusFilter === ''"
            wire:click="toggleStatusFilter('')"
        />
        <x-admin.kpi-card
            title="Aktif Menjabat"
            :value="$activeCount"
            :badge="$totalCount > 0 ? round(($activeCount / $totalCount) * 100) . '%' : null"
            subtitle="Pejabat definitif aktif"
            icon="fas fa-user-check"
            theme="info"
            :coloredValue="false"
            :active="$statusFilter === 'active'"
            wire:click="toggleStatusFilter('active')"
        />
        <x-admin.kpi-card
            title="Terjadwal (Staging)"
            :value="$scheduledCount"
            :badge="$totalCount > 0 && $scheduledCount > 0 ? round(($scheduledCount / $totalCount) * 100) . '%' : null"
            subtitle="Menunggu jadwal TMT efektif"
            icon="fas fa-clock"
            theme="earth"
            :coloredValue="false"
            :active="$statusFilter === 'scheduled'"
            wire:click="toggleStatusFilter('scheduled')"
        />
        <x-admin.kpi-card
            title="Siap Diaktifkan"
            :value="$dueCount"
            :badge="$dueCount > 0 ? 'Perlu Tindakan' : null"
            subtitle="Jatuh tempo perlu aktivasi"
            icon="fas fa-bolt"
            theme="danger"
            :coloredValue="true"
            :active="$statusFilter === 'due'"
            wire:click="toggleStatusFilter('due')"
        />
    </div>

    <!-- ── 3. DUE SCHEDULED MUTATIONS NOTIFICATION BANNER ── -->
    @if ($dueCount > 0)
        <div class="p-4 bg-warning-50 border border-warning-200 rounded-2xl flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 shadow-xs animate-fade-in">
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-xl bg-warning-700 text-white flex items-center justify-center shrink-0 shadow-2xs">
                    <i class="fas fa-clock text-sm" aria-hidden="true"></i>
                </div>
                <div>
                    <h4 class="text-xs sm:text-sm font-bold text-warning-800 flex items-center gap-2">
                        <span>Aktivasi TMT Jatuh Tempo</span>
                        <span class="px-2 py-0.2 rounded-full text-[10px] font-bold bg-warning-200 text-warning-800 border border-warning-200">{{ $dueCount }} Antrean</span>
                    </h4>
                    <p class="text-xs text-warning-800 mt-0.5">
                        Terdapat <strong>{{ $dueCount }}</strong> mutasi terjadwal yang telah mencapai tanggal TMT efektif dan siap diaktifkan ke data master pegawai.
                    </p>
                </div>
            </div>
            <button wire:click="applyAllDue" wire:loading.attr="disabled"
                class="px-4 py-2 rounded-xl bg-warning-700 hover:bg-warning-700 text-white text-xs font-bold shadow-xs transition flex items-center justify-center gap-1.5 cursor-pointer shrink-0">
                <i class="fas fa-bolt text-xs" aria-hidden="true"></i>
                <span>Aktivasi Semua Sekarang</span>
            </button>
        </div>
    @endif

    @include('livewire.admin.partials.mutasi-history.history-table')

    @include('livewire.admin.partials.mutasi-history.detail-modal')

    @include('livewire.admin.partials.mutasi-history.batch-modal')


</div>
