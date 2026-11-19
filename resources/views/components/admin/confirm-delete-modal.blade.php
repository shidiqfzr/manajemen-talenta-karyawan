{{--
    Modal konfirmasi hapus untuk satu baris tabel (DESIGN.md §6.1, §7.6): varian danger wajib didahului modal.
    Dibangun di atas cangkang x-admin.modal.

    Letakkan satu kali di dalam komponen tabel; butir menu baris membukanya lewat event:
    <x-admin.master.menu-item icon="fas fa-trash-can" tone="danger" separated
        @click="$dispatch('confirm-delete', { action: '{{ route('admin.trainings.destroy', $id) }}', name: @js($judul) })">Hapus Pelatihan</x-admin.master.menu-item>

    Penghapusan dikirim sebagai form DELETE biasa ber-CSRF, sehingga validasi dan audit tetap di controller modul.

    Data yang wajib beralasan (SECURITY.md butir 6a) memakai `reason`: kolom "alasan" 10–500 karakter ikut terkirim
    dan dicatat pada riwayat perubahan.
    <x-admin.confirm-delete-modal title="Hapus Pelatihan" noun="pelatihan" reason />
--}}
@props(['title' => 'Hapus Data', 'noun' => 'data', 'consequence' => 'Tindakan ini tidak dapat dibatalkan.', 'reason' => false])

<div x-data="{ open: false, action: '', name: '', reason: '' }"
    @confirm-delete.window="action = $event.detail.action; name = $event.detail.name; reason = ''; open = true">
    <x-admin.modal tone="danger" title-id="confirm-delete-title" :title="$title" icon="fas fa-triangle-exclamation" form method="DELETE" ::action="action">
        <p class="text-sm text-surface-600 leading-relaxed">
            Apakah Anda yakin ingin menghapus {{ $noun }} <strong class="text-surface-900 font-semibold" x-text="name"></strong>?
        </p>
        <p class="text-xs text-danger font-medium">
            <i class="fas fa-circle-exclamation mr-1" aria-hidden="true"></i> {{ $consequence }}
        </p>
        @if ($reason)
            <x-admin.form.reason-field id="confirm-delete-reason" name="alasan" x-model="reason" />
        @endif
        <x-slot:footer>
            <x-admin.button variant="danger" type="submit" icon="fas fa-trash-alt" once>Ya, Hapus</x-admin.button>
        </x-slot:footer>
    </x-admin.modal>
</div>
