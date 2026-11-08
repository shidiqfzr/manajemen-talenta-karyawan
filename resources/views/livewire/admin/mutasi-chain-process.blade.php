<div class="space-y-6 pb-24" x-data="{ allEmployeesList: @js($allEmployees) }">

    <!-- Ambient Loading Progress Bar -->
    <div wire:loading.delay class="fixed top-0 left-0 right-0 z-50 h-1 bg-gradient-to-r from-primary-600 via-primary-400 to-primary-600 motion-safe:animate-pulse"></div>

    @include('livewire.admin.partials.mutasi-chain-process.header-sk')

    @include('livewire.admin.partials.mutasi-chain-process.topology-monitor')

    <!-- ── 3. LIST OF NODES (CARDS) ── -->
    <div class="space-y-5">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div class="flex items-center gap-3">
                <h3 class="text-base font-bold text-surface-900 tracking-tight">Daftar Mata Rantai Karyawan</h3>
                <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-primary-50 text-primary-800 border border-primary-200">
                    {{ count($nodes) }} Orang
                </span>
            </div>

            <div class="flex items-center gap-2.5 self-end sm:self-center">
                <!-- Expand / Collapse All Toggles -->
                <div class="flex items-center bg-surface-100 p-0.5 rounded-xl border border-surface-200 text-xs text-surface-600">
                    <button type="button" @click="$dispatch('expand-all-nodes')"
                        class="px-2.5 py-1 rounded-lg hover:bg-white hover:text-surface-900 transition font-medium cursor-pointer"
                        title="Buka semua kartu slot karyawan">
                        <i class="fas fa-arrows-up-down text-[10px] mr-1 text-surface-400" aria-hidden="true"></i> Buka Semua
                    </button>
                    <button type="button" @click="$dispatch('collapse-all-nodes')"
                        class="px-2.5 py-1 rounded-lg hover:bg-white hover:text-surface-900 transition font-medium cursor-pointer"
                        title="Lipat semua kartu slot karyawan agar halaman ringkas">
                        <i class="fas fa-bars-staggered text-[10px] mr-1 text-surface-400" aria-hidden="true"></i> Lipat Semua
                    </button>
                </div>

                <button type="button" wire:click="addNode"
                    wire:loading.attr="disabled"
                    wire:target="addNode"
                    class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-primary-600 hover:bg-primary-700 text-white text-xs font-bold transition shadow-xs cursor-pointer disabled:opacity-60 disabled:cursor-not-allowed">
                    <i wire:loading.remove wire:target="addNode" class="fas fa-user-plus text-xs" aria-hidden="true"></i>
                    <i wire:loading wire:target="addNode" class="fas fa-spinner fa-spin text-xs" aria-hidden="true"></i>
                    <span>Tambah Slot Karyawan</span>
                </button>
            </div>
        </div>

        @foreach ($nodes as $index => $node)
            @php
                $filteredJobs = $this->getAvailableTargetPositionsForNode($index);
            @endphp
            <x-admin.mutasi.node-card 
                :node="$node"
                :index="$index"
                :total-nodes="count($nodes)"
                :grouped-units="$groupedUnits"
                :filtered-jobs="$filteredJobs"
                wire:key="node-card-slot-{{ $index }}"
            />
        @endforeach
    </div>

    @include('livewire.admin.partials.mutasi-chain-process.submit-bar')

    <!-- ── 5. REVIEW & CONFIRMATION MODAL ── -->
    <x-admin.mutasi.review-modal 
        :show="$showReviewModal"
        :nomor-sk="$nomorSk"
        :tmt-awal="$tmtAwal"
        :nodes="$nodes"
        :clusters="$this->getOrderedFlowClusters()"
    />

    <!-- ── 6. TALENT INTELLIGENCE SLIDE-OVER DRAWER (9-BOX & TENURE) ── -->
    <x-admin.mutasi.talent-drawer 
        :show="$showTalentDrawer"
        :data="$selectedTalentData"
    />

</div>
