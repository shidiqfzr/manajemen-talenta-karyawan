{{--
    Kolom alasan wajib untuk tindakan destruktif (SECURITY.md butir 6a): panjang 10–500 karakter, sama dengan
    aturan penghapusan karyawan (EmployeeDeletionService::REASON_*), sehingga formulir dan validasi server sejalan.
    Atribut tambahan diteruskan ke <textarea> (mis. wire:model, x-model).

    Form biasa : <x-admin.form.reason-field id="alasan-hapus" name="alasan" x-model="reason" />
    Livewire   : <x-admin.form.reason-field id="bulk-reason" wire:model="bulkReason" :native-validation="false" />
--}}
@props([
    'id',
    'name' => null,
    'label' => 'Alasan penghapusan',
    'placeholder' => 'Contoh: data ganda, salah input, atau diganti dokumen terbaru (minimal 10 karakter)',
    'hint' => 'Alasan dicatat pada riwayat perubahan data.',
    'rows' => 3,
    'nativeValidation' => true, // false bila validasi dilakukan komponen Livewire
])

@php
    use App\Services\EmployeeDeletionService;

    $fieldName = $name ?? $id;
@endphp

<div>
    <label for="{{ $id }}" class="block text-xs font-semibold text-surface-700 mb-1">{{ $label }} <span class="text-danger">*</span></label>
    <textarea id="{{ $id }}"
        @if($name) name="{{ $name }}" @endif
        rows="{{ $rows }}"
        maxlength="{{ EmployeeDeletionService::REASON_MAX_LENGTH }}"
        @if($nativeValidation) required aria-required="true" minlength="{{ EmployeeDeletionService::REASON_MIN_LENGTH }}" @endif
        placeholder="{{ $placeholder }}"
        @error($fieldName) aria-invalid="true" aria-describedby="{{ $id }}-error" @enderror
        {{ $attributes->merge(['class' => 'w-full rounded-lg border border-surface-300 px-3 py-2 text-sm text-surface-900 placeholder:text-surface-400 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500']) }}></textarea>
    <p class="mt-1 text-[11px] text-surface-500">{{ $hint }}</p>
    @error($fieldName)
        <p id="{{ $id }}-error" class="text-danger-700 text-sm mt-1">{{ $message }}</p>
    @enderror
</div>
