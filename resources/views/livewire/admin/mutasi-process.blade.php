<div class="space-y-6 pb-32">

    <!-- Ambient Loading Progress Bar -->
    <div wire:loading.delay class="fixed top-0 left-0 right-0 z-50 h-1 bg-gradient-to-r from-primary-600 via-primary-400 to-primary-600 motion-safe:animate-pulse"></div>

    {{-- Success Flash Message --}}
    @if (session()->has('mutasi_success'))
        <div role="status" class="p-4 bg-primary-50 border border-primary-200 text-primary-900 rounded-2xl flex items-center justify-between shadow-xs animate-fade-in">
            <div class="flex items-center gap-3">
                <div class="w-8 h-8 rounded-xl bg-primary-600 text-white flex items-center justify-center shrink-0 shadow-2xs">
                    <i class="fas fa-check text-xs" aria-hidden="true"></i>
                </div>
                <div>
                    <h4 class="text-sm font-bold text-primary-900">Mutasi Berhasil Diproses!</h4>
                    <p class="text-xs text-primary-700 mt-0.5">{{ session('mutasi_success') }}</p>
                </div>
            </div>
            <button aria-label="Tutup notifikasi" onclick="this.parentElement.remove()" class="text-primary-600 hover:text-primary-800 transition p-1 cursor-pointer">
                <i class="fas fa-times text-xs" aria-hidden="true"></i>
            </button>
        </div>
    @endif

    @include('livewire.admin.partials.mutasi-process.workspace')

    @include('livewire.admin.partials.mutasi-process.compliance')

    @include('livewire.admin.partials.mutasi-process.swap-simulation')

    @include('livewire.admin.partials.mutasi-process.sk-legal')

    @include('livewire.admin.partials.mutasi-process.action-bar')

    @include('livewire.admin.partials.mutasi-process.review-modal')

    <!-- ── 5. TALENT INTELLIGENCE SLIDE-OVER DRAWER (9-BOX & TENURE) ── -->
    <x-admin.mutasi.talent-drawer 
        :show="$showTalentDrawer"
        :data="$selectedTalentData"
    />

</div>
