{{--
    Kontrol segmen untuk berpindah tampilan pada satu halaman (DESIGN.md §6.1, §8.5): grup radio yang dapat dioperasikan dengan
    papan ketik (panah kiri/kanan/atas/bawah memindahkan dan memilih; hanya pilihan aktif yang mendapat tabindex 0). Satu
    implementasi untuk Sebaran Formasi, Jabatan Penampungan, dan Versi Struktur.

    <x-admin.segmented label="Tampilan sebaran" :options="['jabatan' => 'Per Jabatan', 'unit' => 'Per Unit']" :current="$view" />

    label   : nama grup untuk pembaca layar (wajib).
    options : [kunci => label tampil].
    current : kunci pilihan aktif.
    action  : method Livewire yang dipanggil dengan kunci pilihan (bawaan setView).
--}}
@props(['label', 'options', 'current', 'action' => 'setView'])

<div role="radiogroup" aria-label="{{ $label }}"
    x-data="{ move(step) { const radios = [...$el.querySelectorAll('[role=radio]')]; const index = radios.indexOf(document.activeElement); if (index < 0) { return; } const target = radios[(index + step + radios.length) % radios.length]; target.focus(); target.click(); } }"
    @keydown.arrow-right.prevent="move(1)" @keydown.arrow-down.prevent="move(1)"
    @keydown.arrow-left.prevent="move(-1)" @keydown.arrow-up.prevent="move(-1)"
    {{ $attributes->merge(['class' => 'inline-flex rounded-lg border border-surface-300 bg-white p-0.5']) }}>
    @foreach ($options as $key => $text)
        <button type="button" role="radio" aria-checked="{{ $current === (string) $key ? 'true' : 'false' }}" tabindex="{{ $current === (string) $key ? '0' : '-1' }}" wire:click="{{ $action }}('{{ $key }}')"
            class="px-3 py-1.5 rounded-md text-xs font-semibold transition focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 {{ $current === (string) $key ? 'bg-primary-600 text-white shadow-2xs' : 'text-surface-600 hover:bg-surface-100' }}">{{ $text }}</button>
    @endforeach
</div>
