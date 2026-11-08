{{--
    Modal konfirmasi hapus untuk satu baris tabel (DESIGN.md §6.1, §7.6): varian danger wajib didahului modal.

    Letakkan satu kali di dalam komponen tabel; butir menu baris membukanya lewat event:
    <x-admin.master.menu-item icon="fas fa-trash-can" tone="danger" separated
        @click="$dispatch('confirm-delete', { action: '{{ route('admin.trainings.destroy', $id) }}', name: @js($judul) })">Hapus Pelatihan</x-admin.master.menu-item>

    Penghapusan dikirim sebagai form DELETE biasa ber-CSRF, sehingga validasi dan audit tetap di controller modul.
--}}
@props(['title' => 'Hapus Data', 'noun' => 'data', 'consequence' => 'Tindakan ini tidak dapat dibatalkan.'])

<div x-data="{ open: false, action: '', name: '' }"
    @confirm-delete.window="action = $event.detail.action; name = $event.detail.name; open = true"
    @keydown.escape.window="open = false">
    <div x-show="open" x-cloak
        class="fixed inset-0 z-50 overflow-y-auto"
        role="dialog" aria-modal="true" aria-labelledby="confirm-delete-title" x-trap.noscroll="open" tabindex="-1">
        <div x-show="open"
            x-transition:enter="ease-out duration-200"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="ease-in duration-150"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            class="fixed inset-0 bg-neutral-900/60 backdrop-blur-xs"
            @click="open = false"></div>

        <div class="flex min-h-full items-center justify-center p-4 text-center sm:p-0">
            <div x-show="open"
                x-transition:enter="ease-out duration-200"
                x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                class="relative transform overflow-hidden rounded-2xl bg-white text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-md border border-surface-200">
                <div class="p-6">
                    <div class="flex items-start gap-4">
                        <div class="w-12 h-12 rounded-2xl bg-danger-100 text-danger flex items-center justify-center text-xl shrink-0 border border-danger-200">
                            <i class="fas fa-triangle-exclamation" aria-hidden="true"></i>
                        </div>
                        <div class="space-y-1.5 flex-1">
                            <h3 class="text-lg font-bold text-surface-900" id="confirm-delete-title">{{ $title }}</h3>
                            <p class="text-xs sm:text-sm text-surface-600 leading-relaxed">
                                Apakah Anda yakin ingin menghapus {{ $noun }} <strong class="text-surface-900 font-semibold" x-text="name"></strong>?
                            </p>
                            <p class="text-xs text-danger font-medium mt-2">
                                <i class="fas fa-circle-exclamation mr-1" aria-hidden="true"></i> {{ $consequence }}
                            </p>
                        </div>
                    </div>
                </div>

                <div class="px-6 py-4 bg-surface-50 border-t border-surface-200 flex items-center justify-end gap-2.5">
                    <x-admin.button variant="secondary" autofocus @click="open = false">Batal</x-admin.button>
                    <form :action="action" method="POST" class="inline">
                        @csrf
                        @method('DELETE')
                        <x-admin.button variant="danger" type="submit" icon="fas fa-trash-alt" once>Ya, Hapus</x-admin.button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
