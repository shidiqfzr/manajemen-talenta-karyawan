{{--
    Penanda tahap alur multi-langkah (DESIGN.md §7.1): tahap berjalan diberi aria-current="step", tahap selesai bercentang.

    <x-admin.stepper :steps="['Data SK', 'Tinjau Perubahan', 'Ajukan']" :current="2" label="Tahap pencatatan SK" />

    steps: daftar label berurutan; current: nomor tahap berjalan (mulai dari 1); label: nama daftar untuk pembaca layar.
--}}
@props(['steps', 'current' => 1, 'label' => 'Tahap'])

<ol class="flex flex-wrap items-center gap-3 text-xs font-semibold" aria-label="{{ $label }}">
    @foreach ($steps as $index => $step)
        @php $number = $index + 1; @endphp
        <li class="inline-flex items-center gap-2 {{ $current === $number ? 'text-primary-800' : 'text-surface-500' }}" @if ($current === $number) aria-current="step" @endif>
            <span class="w-6 h-6 rounded-full inline-flex items-center justify-center border {{ $current === $number ? 'bg-primary-600 text-white border-primary-600' : ($current > $number ? 'bg-primary-100 text-primary-800 border-primary-200' : 'bg-white border-surface-300') }}">
                @if ($current > $number)
                    <i class="fas fa-check text-[10px]" aria-hidden="true"></i><span class="sr-only">{{ $number }}</span>
                @else
                    {{ $number }}
                @endif
            </span>
            {{ $step }}
        </li>
    @endforeach
</ol>
