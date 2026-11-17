{{--
    Bar aksi formulir yang menempel di bawah layar: Batal di kiri, aksi utama di kanan (DESIGN.md §7.1).

    Form biasa (POST): tombol simpan memakai `once` sehingga tidak dapat terkirim ganda.
    <x-admin.form.footer :cancel-url="route('admin.trainings.index')" submit-label="Simpan Pelatihan"
        note="Perubahan data pelatihan tercatat pada riwayat perubahan." />

    Form Livewire: `:once="false"` (tombol tidak boleh terkunci permanen bila server menolak isian) dan `target="namaAction"`
    (disabled + spinner selama action berjalan). `disabled` untuk aksi yang diblokir aturan; alasannya ditulis pada `note`
    (diumumkan lewat aria-live, DESIGN.md §7.2). Slot `actions` menampung aksi tambahan di antara Batal dan aksi utama.
    <x-admin.form.footer :cancel-url="route('admin.master.sk.index')" cancel-label="Simpan sebagai Draf" submit-label="Ajukan"
        :once="false" target="submit" :disabled="$blockers !== []" note="...">
        <x-slot:actions><x-admin.button variant="soft" wire:click="recalculate" target="recalculate">Hitung Ulang</x-admin.button></x-slot:actions>
    </x-admin.form.footer>
--}}
@props([
    'cancelUrl',
    'cancelLabel' => 'Batal',
    'submitLabel' => 'Simpan',
    'note' => null,
    'icon' => 'fas fa-check-circle',
    'once' => true,
    'target' => null,
    'disabled' => false,
])

<div class="sticky bottom-4 z-10 bg-white rounded-2xl border border-surface-200/80 p-4 sm:p-5 shadow-xs flex flex-col sm:flex-row items-center justify-between gap-3">
    <div class="text-xs text-surface-500 flex items-center gap-1.5" aria-live="polite">
        @if($note)
            <i class="fas {{ $disabled ? 'fa-lock text-danger-700' : 'fa-shield-halved text-primary-600' }}" aria-hidden="true"></i>
            <span>{{ $note }}</span>
        @endif
    </div>

    <div class="flex flex-wrap items-center gap-3 w-full sm:w-auto justify-end">
        <x-admin.button href="{{ $cancelUrl }}" variant="secondary">{{ $cancelLabel }}</x-admin.button>
        @isset($actions)
            {{ $actions }}
        @endisset
        <x-admin.button type="submit" :once="$once" :target="$target" :disabled="$disabled" variant="primary" :icon="$icon" class="shadow-primary-600/20 shadow-md">{{ $submitLabel }}</x-admin.button>
    </div>
</div>
