{{--
    Kartu rincian perhitungan pada drawer MPP; satu tampilan untuk rumus Kebutuhan dan rencana vs realisasi pasokan.

    @include('livewire.admin.partials.mpp-breakdown-card', [
        'kind' => 'supply',
        'title' => 'Rincian Rencana vs Realisasi Pasokan',
        'icon' => 'fas fa-people-arrows',
        'badge' => ['label' => 'Sisa 2', 'class' => 'bg-warning-100 text-warning-800'],
        'terms' => [
            ['op' => null, 'label' => 'Rencana 2026', 'value' => 4, 'class' => 'text-surface-900'],
            ['op' => '−', 'label' => 'Terealisasi', 'value' => 2, 'class' => 'text-primary-700'],
        ],
        'result' => ['label' => 'Sisa', 'value' => 2, 'class' => 'text-earth-700'],
        'notes' => [['text' => '...', 'class' => 'text-warning-800/80']],
    ])

    Persamaan disusun dalam satu baris flex: setiap operator (−, +, =) berdiri sebagai kolom sendiri di antara
    suku dan diratakan ke tengah secara vertikal, sehingga label suku tidak memuat simbol matematika.
--}}
<div data-breakdown-card="{{ $kind }}" class="bg-warning-50/70 border border-warning-200/90 rounded-xl p-3 space-y-2">
    <div class="flex items-center justify-between gap-2">
        <span class="text-xs font-bold text-warning-800 flex items-center gap-1.5">
            <i class="{{ $icon }} text-warning-700" aria-hidden="true"></i>
            {{ $title }}
        </span>
        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold whitespace-nowrap {{ $badge['class'] }}">
            {{ $badge['label'] }}
        </span>
    </div>

    <div class="flex items-stretch gap-1 text-center text-[11px] bg-white rounded-lg p-2 border border-warning-200/60 font-mono shadow-2xs">
        @foreach($terms as $term)
            @if($term['op'])
                <span data-op="{{ ['−' => 'minus', '+' => 'plus'][$term['op']] }}" class="self-center shrink-0 px-0.5 text-sm font-bold text-surface-400">{{ $term['op'] }}</span>
            @endif
            <div class="flex-1 min-w-0 space-y-0.5">
                <p class="text-[10px] text-surface-500 font-sans uppercase truncate">{{ $term['label'] }}</p>
                <p class="font-bold {{ $term['class'] }}">{{ $term['value'] }}</p>
            </div>
        @endforeach
        <span data-op="equals" class="self-center shrink-0 px-0.5 text-sm font-bold text-warning-700">=</span>
        <div class="flex-1 min-w-0 space-y-0.5 bg-warning-50/70 rounded py-0.5">
            <p class="text-[10px] text-warning-800 font-sans uppercase font-bold truncate">{{ $result['label'] }}</p>
            <p class="font-extrabold {{ $result['class'] }}">{{ $result['value'] }}</p>
        </div>
    </div>

    @foreach($notes as $note)
        <p class="text-[10px] leading-tight {{ $note['class'] }}">{{ $note['text'] }}</p>
    @endforeach
</div>
