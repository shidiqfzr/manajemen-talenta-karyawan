@props([
    'wireModel' => 'fileSk',
    'file' => null,
    'label' => 'Unggah Berkas PDF',
    'helper' => '(Opsional, Dokumen Resmi Maks. 5MB)',
    'accept' => 'application/pdf',
    'buttonText' => 'Pilih Berkas',
    'changeButtonText' => 'Ganti Berkas',
    'errorKey' => 'fileSk',
    'id' => 'fileUpload_' . uniqid(),
])

<div>
    @if ($label)
        <label class="block text-xs font-semibold text-surface-700 mb-1.5">
            {{ $label }}
            @if ($helper)
                <span class="text-surface-500 font-normal text-xs">{{ $helper }}</span>
            @endif
        </label>
    @endif

    <div class="relative w-full">
        {{-- Ikon Kiri Statis: Posisi absolut inset-y-0 left-0 pl-3.5 persis 1:1 dengan input standar --}}
        <div class="pointer-events-none absolute inset-y-0 left-0 pl-3.5 flex items-center text-surface-500 z-10">
            <i class="fas fa-file-pdf text-xs {{ $file ? 'text-danger' : 'text-surface-400' }}" aria-hidden="true"></i>
        </div>

        {{-- Bingkai Kontrol Input Penuh: pl-9.5 memberi jeda presisi bebas tumpang tindih --}}
        <div class="w-full pl-9.5 pr-1.5 py-1 rounded-xl border {{ $errors->has($errorKey) ? 'border-danger bg-danger-50/20' : 'border-surface-300 bg-white hover:border-surface-400' }} transition flex items-center justify-between min-h-[42px] shadow-2xs">
            {{-- Area Tengah: Teks Placeholder / File Terpilih / Loading State --}}
            <div class="flex-1 min-w-0 pr-3">
                <div wire:loading wire:target="{{ $wireModel }}" class="text-xs sm:text-sm text-primary-600 flex items-center gap-1.5 font-medium">
                    <i class="fas fa-spinner fa-spin text-xs" aria-hidden="true"></i>
                    <span class="truncate">Mengunggah berkas...</span>
                </div>

                @if ($file)
                    <div wire:loading.remove wire:target="{{ $wireModel }}" class="flex items-center gap-2 min-w-0">
                        <span class="text-xs sm:text-sm font-semibold text-surface-800 truncate" title="{{ $file->getClientOriginalName() }}">
                            {{ $file->getClientOriginalName() }}
                        </span>
                        <button type="button" wire:click="$set('{{ $wireModel }}', null)"
                            class="text-surface-500 hover:text-danger p-0.5 transition cursor-pointer shrink-0"
                            title="Hapus berkas">
                            <i class="fas fa-times-circle text-xs" aria-hidden="true"></i>
                        </button>
                    </div>
                @else
                    <div wire:loading.remove wire:target="{{ $wireModel }}" class="text-xs sm:text-sm text-surface-500 font-normal truncate select-none">
                        Belum ada dokumen PDF dipilih
                    </div>
                @endif
            </div>

            {{-- Tombol Aksi Kanan (Action Button) --}}
            <label for="{{ $id }}"
                class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-surface-100 hover:bg-surface-200 text-surface-700 hover:text-surface-900 border border-surface-200/80 rounded-lg text-xs font-semibold cursor-pointer transition shrink-0 shadow-2xs select-none">
                <i class="fas fa-arrow-up-from-bracket text-xs text-surface-500" aria-hidden="true"></i>
                <span>{{ $file ? $changeButtonText : $buttonText }}</span>
                <input type="file" wire:model="{{ $wireModel }}" accept="{{ $accept }}" id="{{ $id }}" class="sr-only">
            </label>
        </div>
    </div>

    @if ($errorKey)
        @error($errorKey)
            <p class="text-danger text-[11px] font-medium mt-1 flex items-center gap-1">
                <i class="fas fa-circle-exclamation text-xs" aria-hidden="true"></i> {{ $message }}
            </p>
        @enderror
    @endif
</div>
