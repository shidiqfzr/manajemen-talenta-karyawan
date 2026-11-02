@extends('errors.layout')

@section('code', '500')
@section('title', 'Terjadi Kesalahan pada Sistem')
@section('message', 'Permintaan Anda belum dapat diproses karena gangguan pada sistem. Tim teknis dapat menelusurinya dari kode dan waktu kejadian di bawah.')
@section('hint', 'Coba lagi dalam beberapa saat. Bila Anda sedang menyimpan data, periksa dulu apakah perubahan sudah tercatat sebelum mengulang.')
@section('actions')
    <x-admin.button href="{{ url('/') }}" variant="secondary" icon="fas fa-house">Kembali ke Beranda</x-admin.button>
    <x-admin.button type="button" icon="fas fa-rotate-right" onclick="location.reload()">Coba Lagi</x-admin.button>
@endsection
