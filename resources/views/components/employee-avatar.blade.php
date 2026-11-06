{{--
    Avatar pegawai: foto asli bila berkas tersedia, selain itu lingkaran inisial (DESIGN.md: tanpa foto → inisial).
    Tidak memakai gambar bawaan agar tidak ada tautan gambar rusak.
--}}
@props(['employee', 'size' => 'w-full h-full', 'text' => 'text-4xl'])

@php
    $hasPhoto = $employee->foto && \Illuminate\Support\Facades\Storage::disk('public')->exists($employee->foto);
@endphp

@if ($hasPhoto)
    <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($employee->foto) }}"
        alt="{{ $employee->nama }}" loading="lazy"
        {{ $attributes->merge(['class' => $size.' object-cover']) }}>
@else
    <div role="img" aria-label="{{ $employee->nama }}"
        {{ $attributes->merge(['class' => $size.' bg-gradient-to-br from-primary-100 to-teal-50 flex items-center justify-center text-primary-700 font-extrabold tracking-tight '.$text]) }}>
        {{ $employee->initials }}
    </div>
@endif
