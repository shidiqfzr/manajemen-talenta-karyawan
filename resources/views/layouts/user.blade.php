<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ config('app.name', 'Laravel App') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])

</head>

<body class="bg-surface-50 text-surface-800 flex flex-col min-h-screen">

    <!-- Navbar -->
    <header class="bg-white shadow sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center py-4">
                <!-- Brand -->
                <div class="text-2xl font-bold text-info-700 tracking-wide">
                    Perkebunan Nusantara
                </div>

                <!-- Hamburger (mobile) -->
                <div class="sm:hidden">
                    <button id="navToggle" type="button" class="text-surface-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 text-2xl"
                        aria-label="Buka menu" aria-controls="mobileMenu" aria-expanded="false">
                        <span aria-hidden="true">☰</span>
                    </button>
                </div>

                <!-- Desktop Nav -->
                <nav aria-label="Navigasi utama" class="hidden sm:flex items-center space-x-6 font-medium text-sm sm:text-base">
                    <a href="{{ route('user.employees.index') }}" class="text-surface-700 hover:text-info transition" @if (request()->routeIs('user.employees.*')) aria-current="page" @endif>Karyawan</a>
                    <a href="{{ route('user.trainings.index') }}" class="text-surface-700 hover:text-info transition" @if (request()->routeIs('user.trainings.*')) aria-current="page" @endif>Pelatihan</a>

                    <!-- Dropdown -->
                    <div class="relative">
                        <button id="dataDropdownBtn" type="button" class="flex items-center text-surface-700 hover:text-info transition focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500"
                            aria-haspopup="true" aria-controls="dataDropdown" aria-expanded="false">
                            Data
                            <svg class="ml-1 w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>

                        <!-- Dropdown Menu -->
                        <div id="dataDropdown" class="absolute right-0 hidden bg-white shadow-lg rounded-lg mt-2 w-48 z-50 border border-surface-200 transition-all duration-200 ease-in-out">
                            <div class="py-2">
                                <a href="{{ route('data.rkp') }}" class="block px-4 py-2 text-surface-700 hover:bg-info-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500" @if (request()->routeIs('data.rkp')) aria-current="page" @endif>Data RKP</a>
                                <a href="{{ route('data.bzting') }}" class="block px-4 py-2 text-surface-700 hover:bg-info-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500" @if (request()->routeIs('data.bzting')) aria-current="page" @endif>Data Bzting</a>
                                <a href="{{ route('data.pelatihan') }}" class="block px-4 py-2 text-surface-700 hover:bg-info-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500" @if (request()->routeIs('data.pelatihan')) aria-current="page" @endif>Data Pelatihan</a>
                                <a href="{{ route('data.ckp') }}" class="block px-4 py-2 text-surface-700 hover:bg-info-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500" @if (request()->routeIs('data.ckp')) aria-current="page" @endif>Data CKP</a>
                                <a href="{{ route('data.manajemen-talenta') }}" class="block px-4 py-2 text-surface-700 hover:bg-info-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500" @if (request()->routeIs('data.manajemen-talenta')) aria-current="page" @endif>Data Manajemen Talenta</a>
                            </div>
                        </div>
                    </div>
                </nav>
            </div>

            <!-- Mobile Nav -->
            <nav id="mobileMenu" aria-label="Navigasi seluler" class="sm:hidden hidden pb-4 space-y-2 text-sm font-medium border-t pt-4">
                <a href="{{ route('user.employees.index') }}" class="block text-surface-700 hover:text-info transition px-2" @if (request()->routeIs('user.employees.*')) aria-current="page" @endif>Karyawan</a>
                <a href="{{ route('user.trainings.index') }}" class="block text-surface-700 hover:text-info transition px-2" @if (request()->routeIs('user.trainings.*')) aria-current="page" @endif>Pelatihan</a>
                <details class="px-2">
                    <summary class="cursor-pointer text-surface-700 hover:text-info">Data</summary>
                    <div class="mt-2 space-y-1 pl-4">
                        <a href="{{ route('data.rkp') }}" class="block hover:bg-surface-100 px-2 py-1 rounded text-surface-700" @if (request()->routeIs('data.rkp')) aria-current="page" @endif>Data RKP</a>
                        <a href="{{ route('data.bzting') }}" class="block hover:bg-surface-100 px-2 py-1 rounded text-surface-700" @if (request()->routeIs('data.bzting')) aria-current="page" @endif>Data Bzting</a>
                        <a href="{{ route('data.pelatihan') }}" class="block hover:bg-surface-100 px-2 py-1 rounded text-surface-700" @if (request()->routeIs('data.pelatihan')) aria-current="page" @endif>Data Pelatihan</a>
                        <a href="{{ route('data.ckp') }}" class="block hover:bg-surface-100 px-2 py-1 rounded text-surface-700" @if (request()->routeIs('data.ckp')) aria-current="page" @endif>Data CKP</a>
                        <a href="{{ route('data.manajemen-talenta') }}" class="block hover:bg-surface-100 px-2 py-1 rounded text-surface-700" @if (request()->routeIs('data.manajemen-talenta')) aria-current="page" @endif>Data Manajemen Talenta</a>
                    </div>
                </details>
            </nav>
        </div>
    </header>

    <!-- Main Content -->
    <main class="flex-1 py-10 px-4 sm:px-6 lg:px-8">
        <div class="max-w-7xl mx-auto">
            @yield('content')
        </div>
    </main>

    <!-- Footer -->
    <footer class="bg-white border-t py-4 text-center text-sm text-surface-500">
        &copy; {{ date('Y') }} {{ config('app.name', 'Laravel App') }}. All rights reserved.
    </footer>

    <!-- Dropdown Script -->
    <script>
        const dropdownBtn = document.getElementById('dataDropdownBtn');
        const dropdown = document.getElementById('dataDropdown');
    
        const setDropdown = (open) => {
            dropdown.classList.toggle('hidden', !open);
            dropdownBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
        };

        dropdownBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            setDropdown(dropdown.classList.contains('hidden'));
        });

        // Hide dropdown if clicking outside or pressing Escape
        window.addEventListener('click', () => setDropdown(false));
        window.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && !dropdown.classList.contains('hidden')) {
                setDropdown(false);
                dropdownBtn.focus();
            }
        });
    </script>

    <!-- Mobile Toggle Script -->
    <script>
        const navToggle = document.getElementById('navToggle');
        const mobileMenu = document.getElementById('mobileMenu');

        navToggle.addEventListener('click', () => {
            const open = mobileMenu.classList.toggle('hidden') === false;
            navToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
            navToggle.setAttribute('aria-label', open ? 'Tutup menu' : 'Buka menu');
        });
    </script>
</body>

</html>
