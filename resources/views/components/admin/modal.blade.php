{{--
    Cangkang modal tunggal seluruh aplikasi (DESIGN.md §7.6): overlay, panel, kepala (ikon + judul + deskripsi + tombol tutup),
    badan, dan footer. Kepala hanya memuat ikon, judul, dan satu baris konteks; penjelasan tindakan ada di badan dengan lebar penuh. Semantik dialog, kunci fokus, Esc, klik di luar, serta transisi diatur di sini, bukan di tiap layar.

    Dengan state Alpine milik layar:
    <x-admin.modal open="rejectOpen" tone="danger" title="Tolak pengajuan impor ini?" description="Data tidak akan berubah.">
        ...isi / kolom formulir...
        <x-slot:footer><x-admin.button variant="danger" type="submit" once>Tolak</x-admin.button></x-slot:footer>
    </x-admin.modal>

    Prop:
    - open     : ekspresi Alpine penentu tampil (bawaan "open"). Modal yang dirender server di dalam @if memakai open="true".
    - close    : ekspresi penutup (bawaan "{open} = false"); modal Livewire: close="$wire.closeModal()".
    - tone     : primary | warning | danger (warna ikon dan nada konfirmasi). danger/warning memfokuskan Batal lebih dulu.
    - size     : sm | md | lg | xl | 2xl | 3xl (lebar maksimum).
    - form     : true membungkus badan + footer dalam <form method="POST"> ber-CSRF; atribut action, :action, @submit,
                 wire:submit diteruskan ke <form>; method="DELETE|PUT|PATCH" ditulis sebagai _method.
    - scroll   : true membatasi tinggi badan dan menggulirnya (isi panjang seperti tabel).
    - cancel   : label tombol Batal (netral, kiri); false menyembunyikannya (dialog informasi saja).
    - description / slot subheading : penjelasan tindakan, tampil di badan (lebar penuh) sebelum isian.
    - slot subtitle : satu baris konteks di bawah judul (mis. NIK, nomor SK); dipangkas bila panjang.
    - toneExpression : ekspresi Alpine yang menghasilkan 'primary'|'warning'|'danger' bila nada baru diketahui saat dibuka
                 (dialog bersama yang dipakai banyak aksi). Slot heading/subheading menggantikan judul/penjelasan statis.
    - iconExpression : ekspresi Alpine yang menghasilkan kelas ikon (mis. ikon mengikuti nada dialog bersama).
    - busy     : ekspresi Alpine; selama benar, Batal dan tombol Tutup dinonaktifkan (formulir sedang dikirim).

    Slot footerStart (opsional) menaruh tautan atau keterangan di sisi kiri footer, mis. unduh berkas.
    Aksi utama masuk ke slot footer di kanan Batal. Tombol varian danger wajib memakai x-admin.button (once/target).
--}}
@props([
    'title' => '',
    'icon' => null,
    'tone' => 'primary',
    'size' => 'md',
    'description' => null,
    'open' => 'open',
    'close' => null,
    'scroll' => false,
    'cancel' => 'Batal',
    'form' => false,
    'method' => null,
    'titleId' => null,
    'toneExpression' => null,
    'iconExpression' => null,
    'busy' => null,
])

@php
    $closeExpression = $close ?? "{$open} = false";
    $titleId ??= 'modal-title-'.substr(md5($title.$open), 0, 8);
    $descriptionId = $titleId.'-desc';
    $busyExpression = $busy ?: 'false';

    $toneMap = [
        'primary' => 'bg-primary-100 text-primary-700 border-primary-200',
        'warning' => 'bg-warning-100 text-warning-800 border-warning-200',
        'danger' => 'bg-danger-100 text-danger border-danger-200',
    ];
    $toneClasses = $toneExpression ? '' : ($toneMap[$tone] ?? $toneMap['primary']);

    $icon ??= $tone === 'primary' && ! $toneExpression ? 'fas fa-circle-info' : 'fas fa-triangle-exclamation';

    $sizeClass = [
        'sm' => 'sm:max-w-sm',
        'md' => 'sm:max-w-md',
        'lg' => 'sm:max-w-lg',
        'xl' => 'sm:max-w-2xl',
        '2xl' => 'sm:max-w-4xl',
        '3xl' => 'sm:max-w-6xl',
    ][$size] ?? 'sm:max-w-md';

    $formKeys = ['action', ':action', '@submit', 'x-on:submit', 'wire:submit', 'wire:submit.prevent', 'enctype'];
    $formAttributes = $attributes->only($formKeys);
    $rootAttributes = $attributes->except($formKeys);
    $hasBody = trim((string) $slot) !== '';
    $hasIntro = $description || isset($subheading);
    $hasContent = $hasBody || $hasIntro;
@endphp

<div @if($open === 'true') x-data @endif
    x-show="{{ $open }}" x-cloak
    {{ $rootAttributes->merge(['class' => 'fixed inset-0 z-50 overflow-y-auto']) }}
    role="dialog" aria-modal="true" aria-labelledby="{{ $titleId }}" @if($hasIntro) aria-describedby="{{ $descriptionId }}" @endif
    x-trap.noscroll="{{ $open }}" tabindex="-1"
    @keydown.escape.window="{{ $closeExpression }}">

    {{-- Overlay: satu token untuk semua modal --}}
    <div x-show="{{ $open }}"
        x-transition:enter="ease-out duration-200"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="ease-in duration-150"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        class="fixed inset-0 bg-neutral-900/60 backdrop-blur-xs"
        @click="{{ $closeExpression }}"></div>

    <div class="flex min-h-full items-center justify-center p-4 text-center sm:p-0">
        <div x-show="{{ $open }}"
            x-transition:enter="ease-out duration-200"
            x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
            x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
            x-transition:leave="ease-in duration-150"
            x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
            x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
            class="relative transform overflow-hidden rounded-2xl bg-white text-left shadow-xl transition-all sm:my-8 sm:w-full {{ $sizeClass }} border border-surface-200">

            @if($form)
                <form method="POST" {{ $formAttributes }}>
                    @csrf
                    @if($method)
                        @method($method)
                    @endif
            @endif

            <div class="px-6 pt-5 {{ $hasContent ? 'pb-0' : 'pb-5' }}">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-lg {{ $toneClasses }} flex items-center justify-center text-base shrink-0 border"
                        @if($toneExpression)
                            :class="{ '{{ $toneMap['danger'] }}': ({{ $toneExpression }}) === 'danger', '{{ $toneMap['warning'] }}': ({{ $toneExpression }}) === 'warning', '{{ $toneMap['primary'] }}': ({{ $toneExpression }}) !== 'danger' && ({{ $toneExpression }}) !== 'warning' }"
                        @endif>
                        <i @if($iconExpression) :class="{{ $iconExpression }}" @else class="{{ $icon }}" @endif aria-hidden="true"></i>
                    </div>
                    <div class="flex-1 min-w-0">
                        <h3 id="{{ $titleId }}" class="text-base font-semibold leading-snug text-surface-900">{{ $heading ?? $title }}</h3>
                        @isset($subtitle)
                            <p class="mt-0.5 text-xs text-surface-500 truncate">{{ $subtitle }}</p>
                        @endisset
                    </div>
                    <button type="button" aria-label="Tutup" @click="{{ $closeExpression }}" x-bind:disabled="{{ $busyExpression }}"
                        class="shrink-0 -mr-1.5 p-1.5 rounded-lg text-surface-500 hover:text-surface-700 hover:bg-surface-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 transition cursor-pointer disabled:opacity-60 disabled:cursor-not-allowed">
                        <i class="fas fa-xmark text-sm" aria-hidden="true"></i>
                    </button>
                </div>
            </div>

            @if($hasContent)
                <div class="px-6 pt-4 pb-6 space-y-4 {{ $scroll ? 'max-h-[60vh] overflow-y-auto' : '' }}">
                    @if($hasIntro)
                        {{-- Penjelasan tindakan: lebar penuh di badan, bukan di sisi ikon, agar tepi kanan rata dengan isian --}}
                        <p id="{{ $descriptionId }}" class="text-sm text-surface-600 leading-relaxed">{{ $subheading ?? $description }}</p>
                    @endif
                    {{ $slot }}
                </div>
            @endif

            @if($cancel !== false || isset($footer) || isset($footerStart))
                <div class="px-6 py-4 bg-surface-50 border-t border-surface-200 flex flex-wrap items-center justify-end gap-2.5">
                    @isset($footerStart)
                        <div class="mr-auto">{{ $footerStart }}</div>
                    @endisset
                    @if($cancel !== false)
                        <x-admin.button variant="secondary" :autofocus="$tone !== 'primary' || (bool) $toneExpression" @click="{{ $closeExpression }}" x-bind:disabled="{{ $busyExpression }}">{{ $cancel }}</x-admin.button>
                    @endif
                    @isset($footer)
                        {{ $footer }}
                    @endisset
                </div>
            @endif

            @if($form)
                </form>
            @endif
        </div>
    </div>
</div>
