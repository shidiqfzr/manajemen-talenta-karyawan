{{--
    Bar aksi formulir yang menempel di bawah layar: Batal di kiri, aksi utama di kanan (DESIGN.md §7.1).
    Tombol simpan memakai `once` sehingga tidak dapat terkirim ganda.

    <x-admin.form.footer :cancel-url="route('admin.trainings.index')" submit-label="Simpan Pelatihan"
        note="Perubahan data pelatihan tercatat pada riwayat perubahan." />
--}}
@props([
    'cancelUrl',
    'submitLabel' => 'Simpan',
    'note' => null,
    'icon' => 'fas fa-check-circle',
])

<div class="sticky bottom-4 z-10 bg-white rounded-2xl border border-surface-200/80 p-4 sm:p-5 shadow-xs flex flex-col sm:flex-row items-center justify-between gap-3">
    <div class="text-xs text-surface-500 flex items-center gap-1.5">
        @if($note)
            <i class="fas fa-shield-halved text-primary-600" aria-hidden="true"></i>
            <span>{{ $note }}</span>
        @endif
    </div>

    <div class="flex items-center gap-3 w-full sm:w-auto justify-end">
        <x-admin.button href="{{ $cancelUrl }}" variant="secondary">Batal</x-admin.button>
        <x-admin.button type="submit" once variant="primary" :icon="$icon" class="shadow-primary-600/20 shadow-md">{{ $submitLabel }}</x-admin.button>
    </div>
</div>
