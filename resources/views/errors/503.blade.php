@extends('errors.layout')

@section('code', '503')
@section('title', 'Sistem Sedang Dalam Pemeliharaan')
@section('message', 'Talenta Hub sedang diperbarui agar layanan tetap andal. Mohon kembali beberapa saat lagi.')
@section('hint', 'Halaman ini akan bisa dibuka kembali setelah pemeliharaan selesai.')
@section('actions')
    <x-admin.button type="button" icon="fas fa-rotate-right" onclick="location.reload()">Muat Ulang</x-admin.button>
@endsection
