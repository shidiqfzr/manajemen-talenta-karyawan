{{--
    Kartu segmen formulir panjang dengan judul bernomor dan deskripsi satu baris (DESIGN.md §7.1).
    Dipakai berurutan di dalam <form class="space-y-6">; legenda "wajib diisi" cukup di segmen pertama.

    <x-admin.form.section number="1" title="Informasi Program" description="Judul, jenis, dan metode pelatihan" legend>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6"> ...field... </div>
    </x-admin.form.section>
--}}
@props([
    'number',
    'title',
    'description' => null,
    'legend' => false, // tampilkan legenda "Kolom bertanda * wajib diisi"
])

<section aria-labelledby="form-section-{{ $number }}-title" {{ $attributes->merge(['class' => 'bg-white rounded-2xl border border-surface-200/80 shadow-xs p-6 sm:p-7 space-y-6']) }}>
    <div class="flex items-center gap-3 pb-4 border-b border-surface-100">
        <span class="w-8 h-8 rounded-xl bg-primary-100 text-primary-800 text-xs font-extrabold flex items-center justify-center shrink-0" aria-hidden="true">{{ $number }}</span>
        <div>
            <h3 id="form-section-{{ $number }}-title" class="text-base font-extrabold text-surface-900">{{ $title }}</h3>
            @if($description)
                <p class="text-xs text-surface-500">{{ $description }}</p>
            @endif
        </div>
        @if($legend)
            <p class="ml-auto self-start text-[11px] text-surface-500 whitespace-nowrap hidden sm:block">Kolom bertanda <span class="text-danger font-bold" aria-hidden="true">*</span><span class="sr-only">bintang</span> wajib diisi</p>
        @endif
    </div>

    {{ $slot }}
</section>
