@extends('layouts.user')

@section('content')
    <h1 class="text-4xl font-bold mb-10 text-center text-blue-600">
        MANAJEMEN TALENTA KARYAWAN
    </h1>

    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 xl:grid-cols-4 2xl:grid-cols-5 gap-6">
        @foreach ($employees as $employee)
            <a href="{{ route('user.employees.show', $employee->nik) }}" class="group block h-full">
                <div class="bg-white rounded-2xl shadow hover:shadow-lg transition p-5 flex flex-col h-full">
                    <div class="w-full h-60 overflow-hidden rounded-xl mb-4">
                        <img src="{{ $employee->foto ? asset('storage/' . $employee->foto) : asset('images/default.png') }}"
                            alt="{{ $employee->nama }}"
                            class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300">
                    </div>
                    <div class="flex-1">
                        <h2 class="text-lg font-semibold mb-1 truncate" title="{{ $employee->nama }}">
                            {{ $employee->nama }}
                        </h2>
                        <p class="text-gray-600 text-sm truncate" title="{{ $employee->jabatan }}">
                            {{ $employee->jabatan }}
                        </p>
                        <p class="text-xs text-gray-400 mt-1 truncate" title="{{ $employee->email }}">
                            {{ $employee->email }}
                        </p>
                    </div>
                    <span class="inline-block mt-4 text-sm text-blue-500 hover:underline">
                        View Details →
                    </span>
                </div>
            </a>
        @endforeach
    </div>
@endsection
