{{--
    Penanda tahap alur multi-langkah (DESIGN.md §7.1, §6.1): bilah kartu bernomor yang sama untuk Impor Data Karyawan dan
    Catat & Terapkan SK Struktur. Tahap berjalan diberi aria-current="step", tahap selesai bercentang, tahap berikutnya berwarna netral (tanpa opasitas, agar kontras tetap memenuhi 4.5:1).

    Tahap ditentukan server (Livewire / Blade):
    <x-admin.stepper :steps="[['title' => 'Data SK', 'description' => 'Nomor, tanggal, dan lampiran'], 'Tinjau Perubahan']" :current="2" label="Tahap pencatatan SK" />

    Tahap ditentukan Alpine (ekspresi pada lingkup x-data pemanggil, mis. currentStep):
    <x-admin.stepper :steps="$steps" current-expression="currentStep" label="Tahap impor data karyawan" />

    steps             : daftar tahap berurutan, masing-masing teks judul atau ['title' => ..., 'description' => ...] (deskripsi satu baris).
    current           : nomor tahap berjalan (mulai dari 1) bila ditentukan server.
    current-expression: ekspresi Alpine untuk nomor tahap; bila diisi, `current` diabaikan.
    label             : nama daftar untuk pembaca layar.
    Tahap terakhir yang berjalan menampilkan ikon centang ganda (alur selesai). Jumlah tahap yang didukung: 2 atau 3.
--}}
@props(['steps', 'current' => 1, 'label' => 'Tahap', 'currentExpression' => null])

@php
    $alpine = $currentExpression !== null;
    $count = count($steps);
    $grid = $count === 2 ? 'md:grid-cols-2' : 'md:grid-cols-3';

    $card = [
        'current' => 'bg-primary-50/80 border border-primary-200/80 shadow-2xs',
        'done' => 'bg-surface-50 border border-surface-100',
        'upcoming' => 'bg-transparent border border-transparent',
    ];
    $badge = [
        'current' => 'bg-primary-600 text-white shadow-sm ring-4 ring-primary-100',
        'done' => 'bg-primary-700 text-white',
        'upcoming' => 'bg-surface-200 text-surface-700',
    ];
    $kicker = [
        'current' => 'text-primary-800',
        'done' => 'text-surface-700',
        'upcoming' => 'text-surface-600',
    ];
    $stateOf = fn (int $number): string => $current === $number ? 'current' : ($current > $number ? 'done' : 'upcoming');
    // Ekspresi Alpine: memilih satu dari tiga kelas menurut nomor tahap.
    $pick = fn (array $classes, int $number): string => "{$currentExpression} === {$number} ? '{$classes['current']}' : ({$currentExpression} > {$number} ? '{$classes['done']}' : '{$classes['upcoming']}')";
@endphp

<div class="bg-white rounded-2xl border border-surface-200/80 p-4 sm:p-5 shadow-xs">
    <ol class="grid grid-cols-1 {{ $grid }} gap-4" aria-label="{{ $label }}">
        @foreach ($steps as $index => $step)
            @php
                $number = $index + 1;
                $title = is_array($step) ? $step['title'] : $step;
                $description = is_array($step) ? ($step['description'] ?? null) : null;
                $isLast = $number === $count;
                $state = $stateOf($number);
            @endphp
            <li class="flex items-center gap-3.5 p-3 rounded-xl transition {{ $alpine ? '' : $card[$state] }}"
                @if ($alpine) :class="{{ $pick($card, $number) }}" :aria-current="{{ $currentExpression }} === {{ $number }} ? 'step' : false"
                @elseif ($state === 'current') aria-current="step" @endif>
                <div class="w-10 h-10 rounded-xl flex items-center justify-center text-sm font-bold shrink-0 transition {{ $alpine ? '' : $badge[$state] }}"
                    @if ($alpine) :class="{{ $pick($badge, $number) }}" @endif>
                    @if ($alpine)
                        @if ($isLast)
                            <template x-if="{{ $currentExpression }} === {{ $number }}"><i class="fas fa-check-double" aria-hidden="true"></i></template>
                            <template x-if="{{ $currentExpression }} < {{ $number }}"><span>{{ $number }}</span></template>
                        @else
                            <template x-if="{{ $currentExpression }} > {{ $number }}"><i class="fas fa-check" aria-hidden="true"></i></template>
                            <template x-if="{{ $currentExpression }} <= {{ $number }}"><span>{{ $number }}</span></template>
                        @endif
                    @elseif ($isLast && $current === $number)
                        <i class="fas fa-check-double" aria-hidden="true"></i><span class="sr-only">{{ $number }}</span>
                    @elseif ($current > $number)
                        <i class="fas fa-check" aria-hidden="true"></i><span class="sr-only">{{ $number }}</span>
                    @else
                        {{ $number }}
                    @endif
                </div>
                <div class="min-w-0">
                    <p class="text-[11px] font-bold uppercase tracking-wider transition {{ $alpine ? '' : $kicker[$state] }}"
                        @if ($alpine) :class="{{ $pick($kicker, $number) }}" @endif>Tahap {{ $number }}</p>
                    <h4 class="text-xs sm:text-sm font-extrabold text-surface-900 truncate">{{ $title }}</h4>
                    @if ($description)
                        <p class="text-[11px] text-surface-600 truncate">{{ $description }}</p>
                    @endif
                </div>
            </li>
        @endforeach
    </ol>
</div>
