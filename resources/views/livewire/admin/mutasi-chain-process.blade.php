<div class="space-y-6 pb-24" x-data="{ allEmployeesList: @js($allEmployees) }"
    @chain-slot-added.window="$nextTick(() => { $dispatch('expand-node', { index: $event.detail.index }); document.getElementById('node-card-' + $event.detail.index)?.scrollIntoView({ behavior: 'smooth', block: 'center' }); })">

    <!-- Ambient Loading Progress Bar -->
    <div wire:loading.delay class="fixed top-0 left-0 right-0 z-50 h-1 bg-gradient-to-r from-primary-600 via-primary-400 to-primary-600 motion-safe:animate-pulse"></div>

    @php
        $locked = $this->isLocked;
        $proposal = $this->proposal;
    @endphp

    {{-- Status usulan (draf / menunggu persetujuan / dikembalikan / terbit) dan jejak keputusannya --}}
    @include('livewire.admin.partials.mutasi-chain-process.proposal-status')

    {{-- Usulan terkunci: seluruh isian SK dinonaktifkan; server juga menolak perubahan --}}
    {{-- Tanpa m-0: margin fieldset akan menimpa jarak space-y-6 antar kartu (Tailwind v4 memakai :where, spesifisitas 0) --}}
    <fieldset @disabled($locked) class="min-w-0 border-0 p-0">
        @include('livewire.admin.partials.mutasi-chain-process.header-sk')
    </fieldset>

    @include('livewire.admin.partials.mutasi-chain-process.topology-monitor')

    <!-- ── 3. LIST OF NODES (CARDS) — shown to users as "Rincian Mutasi Karyawan" ── -->
    <div class="space-y-5">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div class="flex items-center gap-3">
                <h3 class="text-base font-bold text-surface-900 tracking-tight">Rincian Mutasi Karyawan</h3>
                <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-primary-50 text-primary-800 border border-primary-200">
                    {{ count($nodes) }} Karyawan
                </span>
            </div>

            <div class="flex items-center gap-2.5 self-end sm:self-center">
                <!-- Expand / Collapse All Toggles -->
                <div class="flex items-center bg-surface-100 p-0.5 rounded-xl border border-surface-200 text-xs text-surface-600">
                    <button type="button" @click="$dispatch('expand-all-nodes')"
                        class="px-2.5 py-1 rounded-lg hover:bg-white hover:text-surface-900 transition font-medium cursor-pointer"
                        title="Buka semua kartu karyawan">
                        <i class="fas fa-arrows-up-down text-[10px] mr-1 text-surface-400" aria-hidden="true"></i> Buka Semua
                    </button>
                    <button type="button" @click="$dispatch('collapse-all-nodes')"
                        class="px-2.5 py-1 rounded-lg hover:bg-white hover:text-surface-900 transition font-medium cursor-pointer"
                        title="Lipat semua kartu karyawan agar halaman ringkas">
                        <i class="fas fa-bars-staggered text-[10px] mr-1 text-surface-400" aria-hidden="true"></i> Lipat Semua
                    </button>
                </div>

                @unless ($locked)
                    {{-- Tetap global: alur mutasi baru terbentuk sendiri begitu dua slot saling terhubung --}}
                    <button type="button" wire:click="addNode"
                        wire:loading.attr="disabled"
                        wire:target="addNode"
                        class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-primary-600 hover:bg-primary-700 text-white text-xs font-bold transition shadow-xs cursor-pointer disabled:opacity-60 disabled:cursor-not-allowed">
                        <i wire:loading.remove wire:target="addNode" class="fas fa-user-plus text-xs" aria-hidden="true"></i>
                        <i wire:loading wire:target="addNode" class="fas fa-spinner fa-spin text-xs" aria-hidden="true"></i>
                        <span>Tambah Karyawan</span>
                    </button>
                @endunless
            </div>
        </div>

        {{-- Daftar mata rantai dikelompokkan per alur mutasi; penomoran slot tetap global (#1..#N) --}}
        @foreach ($this->getSlotGroups() as $group)
            @php
                $unfilled = $group['unfilled_vacancies'];
                $canFill = ! $locked && $group['cluster_id'] !== null && ! empty($unfilled);
            @endphp
            <section id="rincian-{{ $group['key'] }}" class="space-y-3 scroll-mt-24" aria-labelledby="slot-group-{{ $group['key'] }}" wire:key="slot-group-{{ $group['key'] }}">
                <div class="px-4 py-2.5 rounded-xl border space-y-2 {{ $group['is_loose'] ? 'bg-warning-50/70 border-warning-200' : ($group['type'] === 'cyclic' ? 'bg-primary-50/70 border-primary-200' : ($group['type'] === 'domino' ? 'bg-info-50/70 border-info-200' : 'bg-white border-surface-200 shadow-xs')) }}">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <div class="flex flex-wrap items-center gap-2.5 min-w-0">
                            <i class="fas {{ $group['is_loose'] ? 'fa-circle-info text-warning-700' : ($group['type'] === 'cyclic' ? 'fa-arrows-rotate text-primary-700' : ($group['type'] === 'domino' ? 'fa-forward-step text-info-700' : 'fa-network-wired text-surface-600')) }} text-xs" aria-hidden="true"></i>
                            <h4 id="slot-group-{{ $group['key'] }}" class="text-sm font-bold text-surface-900 tracking-tight truncate">{{ $group['title'] }}</h4>
                            <span class="px-2 py-0.5 rounded-md text-[10px] font-bold bg-white text-surface-700 border border-surface-200">
                                {{ count($group['indices']) }} Karyawan
                            </span>

                            {{-- Status alur: satu pil, kendala lebih dulu --}}
                            @if ($group['conflict_count'] > 0)
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-danger-50 text-danger-800 border border-danger-200">
                                    <i class="fas fa-circle-xmark text-danger" aria-hidden="true"></i> {{ $group['conflict_count'] }} formasi tujuan masih terisi
                                </span>
                            @elseif ($group['is_zero_vacancy'])
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-primary-50 text-primary-800 border border-primary-300">
                                    <i class="fas fa-check-circle text-primary-600" aria-hidden="true"></i> Tanpa Lowongan Baru
                                </span>
                            @elseif (! empty($unfilled))
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-warning-50 text-warning-800 border border-warning-200">
                                    <i class="fas fa-door-open text-warning-700" aria-hidden="true"></i> {{ count($unfilled) }} lowongan baru
                                </span>
                            @endif
                        </div>
                        <div class="flex items-center gap-1 text-[11px] text-surface-600">
                            @if ($group['in_diagram'])
                                <a href="#alur-{{ $group['key'] }}"
                                    class="px-2 py-1 rounded-lg hover:bg-white hover:text-surface-900 transition font-medium cursor-pointer inline-flex items-center gap-1"
                                    title="Kembali ke peta alur ini">
                                    <i class="fas fa-arrow-up-long text-[10px]" aria-hidden="true"></i> Lihat di Peta
                                </a>
                            @endif
                            <button type="button" @click="$dispatch('expand-group', { group: @js($group['key']) })"
                                class="px-2 py-1 rounded-lg hover:bg-white hover:text-surface-900 transition font-medium cursor-pointer"
                                title="Buka kartu karyawan pada alur ini">Buka</button>
                            <button type="button" @click="$dispatch('collapse-group', { group: @js($group['key']) })"
                                class="px-2 py-1 rounded-lg hover:bg-white hover:text-surface-900 transition font-medium cursor-pointer"
                                title="Lipat kartu karyawan pada alur ini">Lipat</button>
                        </div>
                    </div>

                    @if ($group['type'] === 'draft')
                        {{-- Karyawan belum saling terhubung: jelaskan langkah berikutnya, bukan istilah teknis. Nada warning hanya bila ada alur lain (is_loose) --}}
                        <p class="text-[11px] pt-2 border-t {{ $group['is_loose'] ? 'text-warning-800 border-warning-200/70' : 'text-surface-600 border-surface-100' }}">
                            Pilih jabatan tujuan yang saat ini diduduki karyawan lain, atau formasi yang kosong, agar karyawan ini masuk ke dalam alur mutasi.
                        </p>
                    @endif

                    @if ($canFill)
                        {{-- Isi Lowongan Tersisa: slot baru langsung menargetkan formasi yang ditinggalkan alur ini --}}
                        <div class="flex flex-wrap items-center justify-between gap-2 pt-2 border-t border-warning-200/70">
                            <p class="text-[11px] text-warning-800">
                                Formasi <strong>{{ $unfilled[0]['jabatan'] }}</strong> di {{ $unfilled[0]['unit_kerja'] }} akan kosong setelah {{ $unfilled[0]['left_by'] }} pindah.
                            </p>
                            <button type="button" wire:click="fillVacancy({{ $group['cluster_id'] }}, 0)"
                                wire:loading.attr="disabled" wire:target="fillVacancy"
                                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-white border border-warning-300 text-warning-800 hover:bg-warning-50 text-[11px] font-bold transition cursor-pointer disabled:opacity-60">
                                <i class="fas fa-user-plus text-[10px]" aria-hidden="true"></i> Isi Lowongan Tersisa
                            </button>
                        </div>
                    @endif
                </div>

                @foreach ($group['indices'] as $index)
                    @php
                        $node = $nodes[$index];
                        $filteredJobs = $this->getAvailableTargetPositionsForNode($index);
                    @endphp
                    <x-admin.mutasi.node-card
                        :node="$node"
                        :index="$index"
                        :total-nodes="count($nodes)"
                        :grouped-units="$groupedUnits"
                        :filtered-jobs="$filteredJobs"
                        :group="$group['key']"
                        :locked="$locked"
                        wire:key="node-card-slot-{{ $index }}"
                    />
                @endforeach
            </section>
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
        :mode="$this->reviewMode"
    />

    @include('livewire.admin.partials.mutasi-chain-process.reject-modal')

    <!-- ── 6. TALENT INTELLIGENCE SLIDE-OVER DRAWER (9-BOX & TENURE) ── -->
    <x-admin.mutasi.talent-drawer
        :show="$showTalentDrawer"
        :data="$selectedTalentData"
    />

</div>
