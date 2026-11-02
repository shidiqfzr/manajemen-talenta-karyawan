{{--
    Layout mandiri halaman galat (DESIGN.md §15). Sengaja tidak memakai shell admin: halaman ini harus
    tetap tampil ketika sesi, database, atau Livewire yang bermasalah.

    Halaman turunan mengisi: @section('code'), @section('title'), @section('message'),
    opsional @section('hint') dan @section('actions') (default: Beranda + Kembali).
    Pesan pengecualian asli tidak pernah ditampilkan ke pengguna.
--}}
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex">
    <title>@yield('title') ({{ trim($__env->yieldContent('code')) }}) - Talenta Hub PTPN IV</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">

    {{-- Halaman galat tidak boleh ikut gagal bila aset belum dibangun. --}}
    @if (file_exists(public_path('hot')) || file_exists(public_path('build/manifest.json')))
        @vite(['resources/css/app.css'])
    @endif
</head>

<body class="bg-surface-50 text-surface-900 font-sans antialiased min-h-screen flex flex-col">

    <header class="px-4 sm:px-6 py-4 flex items-center gap-3">
        <img src="{{ asset('images/logo-ptpn4.png') }}" alt="" class="h-8 w-auto object-contain shrink-0">
        <div class="leading-tight">
            <p class="text-sm font-bold text-surface-900">Talenta Hub</p>
            <p class="text-xs text-surface-500">PTPN IV Regional V</p>
        </div>
    </header>

    <main class="flex-1 flex items-center justify-center px-4 py-10">
        <div class="w-full max-w-lg bg-white border border-surface-200 rounded-2xl shadow-xs p-6 sm:p-10 text-center">
            <p class="font-mono tabular-nums text-5xl sm:text-6xl font-black text-surface-300" aria-hidden="true">@yield('code')</p>

            <h1 class="mt-3 text-xl sm:text-2xl font-bold text-surface-900">@yield('title')</h1>
            <p class="mt-2 text-sm sm:text-base text-surface-600 leading-relaxed">@yield('message')</p>

            @hasSection('hint')
                <p class="mt-3 text-sm text-surface-500 leading-relaxed">@yield('hint')</p>
            @endif

            <div class="mt-8 flex flex-col-reverse sm:flex-row items-stretch sm:items-center justify-center gap-3">
                @hasSection('actions')
                    @yield('actions')
                @else
                    <x-admin.button type="button" variant="secondary" icon="fas fa-arrow-left" onclick="history.length > 1 ? history.back() : (location.href = '{{ url('/') }}')">Kembali</x-admin.button>
                    <x-admin.button href="{{ url('/') }}" icon="fas fa-house">Kembali ke Beranda</x-admin.button>
                @endif
            </div>
        </div>
    </main>

    <footer class="px-4 py-4 text-center text-xs text-surface-500">
        Bila masalah berlanjut, hubungi Admin SDM unit Anda dan sebutkan kode {{ trim($__env->yieldContent('code')) }} serta waktu kejadian: {{ now()->format('d/m/Y H:i') }}.
    </footer>
</body>

</html>
