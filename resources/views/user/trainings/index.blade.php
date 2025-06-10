@extends('layouts.user')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <h1 class="text-3xl font-bold text-gray-900 mb-8 text-center">Daftar Pelatihan Karyawan</h1>

    <div class="grid gap-6 sm:grid-cols-1 md:grid-cols-2 lg:grid-cols-3">
        @forelse ($trainings as $training)
            <div class="bg-white shadow-lg rounded-2xl p-6 transition-transform transform hover:scale-105">
                <h2 class="text-xl font-semibold text-gray-800 mb-2">{{ $training->judul }}</h2>
                
                <ul class="text-sm text-gray-600 space-y-1 mb-4">
                    <li><span class="font-medium">Tanggal:</span> {{ $training->tanggal_mulai }}</li>
                    <li><span class="font-medium">Jenis:</span> {{ $training->jenis }}</li>
                    <li><span class="font-medium">Metode:</span> {{ $training->metode }}</li>
                    <li><span class="font-medium">Penyelenggara:</span> {{ $training->penyelenggara }}</li>
                    <li><span class="font-medium">Jumlah Peserta:</span> {{ $training->employees_count ?? count($training->employees) }}</li>
                </ul>

                <div class="text-right">
                    <a href="{{ route('user.trainings.show', $training->id) }}"
                        class="inline-block bg-blue-600 text-white px-4 py-2 rounded-md text-sm hover:bg-blue-700 transition-colors duration-200">
                        Lihat Detail
                    </a>
                </div>
            </div>
        @empty
            <div class="col-span-full text-center text-gray-500">
                Tidak ada data pelatihan yang tersedia.
            </div>
        @endforelse
    </div>
</div>
@endsection
