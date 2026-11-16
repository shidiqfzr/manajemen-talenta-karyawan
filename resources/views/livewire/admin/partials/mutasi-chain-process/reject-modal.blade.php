{{-- Kembalikan usulan kepada penyusun (penyetuju wajib menulis alasan, min. 10 karakter); cangkang x-admin.modal --}}
@if ($showRejectModal)
    <x-admin.modal open="true" close="$wire.closeRejectModal()" tone="warning" icon="fas fa-reply"
        title="Kembalikan Usulan untuk Revisi"
        description="Tidak ada data karyawan yang berubah. Penyusun menerima catatan Anda dan dapat mengajukan ulang setelah memperbaikinya.">
        <div>
            <label for="rejectReason" class="block text-xs font-semibold text-surface-700 mb-1.5">
                Alasan pengembalian <span class="text-danger font-bold">*</span>
            </label>
            <textarea id="rejectReason" wire:model="rejectReason" rows="4" maxlength="1000"
                placeholder="Contoh: Nomor SK belum sesuai dengan nota dinas Direksi; mohon periksa kembali formasi tujuan slot #3."
                @error('rejectReason') aria-invalid="true" aria-describedby="rejectReason-error" @enderror
                class="w-full px-3.5 py-2.5 rounded-xl border text-sm bg-white text-surface-800 placeholder-surface-400 focus:outline-none transition {{ $errors->has('rejectReason') ? 'border-danger focus-visible:ring-2 focus-visible:ring-danger-200' : 'border-surface-300 focus:border-primary-600 focus-visible:ring-2 focus-visible:ring-primary-100' }}"></textarea>
            @error('rejectReason') <p id="rejectReason-error" class="text-[11px] text-danger font-medium mt-1">{{ $message }}</p> @enderror
        </div>
        <x-slot:footer>
            <x-admin.button variant="primary" icon="fas fa-reply" wire:click="rejectProposal" target="rejectProposal">Kembalikan Usulan</x-admin.button>
        </x-slot:footer>
    </x-admin.modal>
@endif
