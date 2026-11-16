{{--
    Dasar persetujuan Dispensasi Direksi - satu desain untuk Mutasi Tunggal & Mutasi Berantai,
    dan untuk setiap jenis aturan (masa dinas unit, kualifikasi promosi) (DESIGN.md §7.2).

    Persetujuan dibuktikan dengan dokumen, bukan centang pernyataan sendiri: nomor nota dinas / SK persetujuan Direksi
    + tanggal persetujuan. Keduanya wajib; status "disetujui" diturunkan dari kelengkapan bukti ini (DispensasiBasis).
    Dicatat pada riwayat jabatan bersama petugas pencatat dan waktu pencatatan sebagai bahan audit.

    - locked : true = kendala formasi belum selesai; isian dikunci dan alasannya tampil (DESIGN.md §7.2 "Terblokir").

    <x-admin.mutasi.dispensasi-field reference-model="..." date-model="..." :locked="false" />
--}}
@props([
    'referenceModel',
    'dateModel',
    'locked' => false,
    'lockedMessage' => 'Tersedia setelah kendala penempatan di atas selesai.',
    'errorReferenceKey' => null,
    'errorDateKey' => null,
    'placeholder' => 'Contoh: ND-012/DIR/2026',
])

@php
    $uid = 'dispensasi-'.trim(preg_replace('/[^a-z0-9]+/i', '-', $referenceModel), '-');
    $errorReferenceKey ??= $referenceModel;
    $errorDateKey ??= $dateModel;
    $hintId = $uid.'-hint';
    $referenceErrorId = $uid.'-error';
    $dateErrorId = $uid.'-date-error';
    $hasReferenceError = ! $locked && $errors->has($errorReferenceKey);
    $hasDateError = ! $locked && $errors->has($errorDateKey);
    $inputBase = 'w-full h-10 px-3 rounded-lg text-sm font-medium transition shadow-2xs focus-visible:outline-none focus-visible:ring-2 ';
    $inputState = $locked
        ? 'bg-surface-100 border border-surface-200 text-surface-500 placeholder-surface-400 cursor-not-allowed shadow-none'
        : 'bg-white border text-surface-900 placeholder-surface-400 focus-visible:border-primary-600 focus-visible:ring-primary-200';
@endphp

<div class="pt-4 border-t border-surface-200" data-dispensasi-block @if ($locked) data-dispensasi-locked @endif role="group" aria-labelledby="{{ $uid }}-title">
    <div class="flex items-center gap-2">
        <h5 id="{{ $uid }}-title" class="text-sm font-semibold {{ $locked ? 'text-surface-600' : 'text-surface-900' }}">Dasar Persetujuan Direksi</h5>
        {{-- Ikon kunci setelah judul: judul tetap di tepi kiri yang sama pada keadaan aktif maupun terkunci --}}
        @if ($locked)
            <i class="fas fa-lock text-surface-500 text-xs" aria-hidden="true"></i>
        @endif
    </div>
    <p id="{{ $hintId }}" class="text-xs text-surface-600 mt-1 leading-relaxed">
        @if ($locked)
            {{ $lockedMessage }}
        @else
            Cantumkan dokumen persetujuan Direksi. Dicatat bersama nama petugas dan waktu pencatatan sebagai bahan audit.
        @endif
    </p>

    <div class="mt-3 grid grid-cols-1 sm:grid-cols-[minmax(0,1fr)_11rem] gap-3">
        <div class="space-y-1">
            <label for="{{ $uid }}" class="block text-xs font-semibold {{ $locked ? 'text-surface-500' : 'text-surface-800' }}">
                Nomor Nota Dinas / SK Persetujuan
                <span class="text-danger-700" aria-hidden="true">*</span><span class="sr-only">(wajib, minimal {{ \App\Services\Mutasi\DispensasiBasis::MIN_REFERENCE_LENGTH }} karakter)</span>
            </label>
            <input type="text" id="{{ $uid }}" wire:model.live.debounce.300ms="{{ $referenceModel }}"
                maxlength="{{ \App\Services\Mutasi\DispensasiBasis::MAX_REFERENCE_LENGTH }}"
                wire:loading.class="opacity-60" wire:target="{{ $referenceModel }}"
                placeholder="{{ $placeholder }}"
                aria-required="true"
                aria-describedby="{{ $hintId }}{{ $hasReferenceError ? ' '.$referenceErrorId : '' }}"
                @if ($hasReferenceError) aria-invalid="true" @endif
                @disabled($locked)
                class="{{ $inputBase }}{{ $inputState }} {{ $hasReferenceError ? 'border-danger-300' : ($locked ? '' : 'border-surface-300') }}">
            @if ($hasReferenceError)
                <p id="{{ $referenceErrorId }}" class="text-danger-700 text-xs" role="alert">{{ $errors->first($errorReferenceKey) }}</p>
            @endif
        </div>

        <div class="space-y-1">
            <label for="{{ $uid }}-date" class="block text-xs font-semibold {{ $locked ? 'text-surface-500' : 'text-surface-800' }}">
                Tanggal Persetujuan
                <span class="text-danger-700" aria-hidden="true">*</span><span class="sr-only">(wajib)</span>
            </label>
            <input type="date" id="{{ $uid }}-date" wire:model.live="{{ $dateModel }}"
                max="{{ now()->toDateString() }}"
                aria-required="true"
                aria-describedby="{{ $hintId }}{{ $hasDateError ? ' '.$dateErrorId : '' }}"
                @if ($hasDateError) aria-invalid="true" @endif
                @disabled($locked)
                class="{{ $inputBase }}{{ $inputState }} {{ $hasDateError ? 'border-danger-300' : ($locked ? '' : 'border-surface-300') }}">
            @if ($hasDateError)
                <p id="{{ $dateErrorId }}" class="text-danger-700 text-xs" role="alert">{{ $errors->first($errorDateKey) }}</p>
            @endif
        </div>
    </div>
</div>
