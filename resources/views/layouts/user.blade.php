<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ config('app.name', 'Laravel App') }}</title>

    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>

</head>

<body class="bg-gray-50 text-gray-800 flex flex-col min-h-screen">

    <!-- Navbar -->
    <header class="bg-white shadow sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center py-4">
                <!-- Brand -->
                <div class="text-2xl font-bold text-blue-700 tracking-wide">
                    Perkebunan Nusantara
                </div>

                <!-- Hamburger (mobile) -->
                <div class="sm:hidden">
                    <button id="navToggle" class="text-gray-700 focus:outline-none text-2xl">
                        ☰
                    </button>
                </div>

                <!-- Desktop Nav -->
                <nav class="hidden sm:flex items-center space-x-6 font-medium text-sm sm:text-base">
                    <a href="{{ route('user.employees.index') }}" class="text-gray-700 hover:text-blue-600 transition">Karyawan</a>
                    <a href="{{ route('user.trainings.index') }}" class="text-gray-700 hover:text-blue-600 transition">Pelatihan</a>

                    <!-- Dropdown -->
                    <div class="relative">
                        <button id="dataDropdownBtn" class="flex items-center text-gray-700 hover:text-blue-600 transition focus:outline-none">
                            Data
                            <svg class="ml-1 w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>

                        <!-- Dropdown Menu -->
                        <div id="dataDropdown" class="absolute right-0 hidden bg-white shadow-lg rounded-lg mt-2 w-48 z-50 border border-gray-200 transition-all duration-200 ease-in-out">
                            <div class="py-2">
                                <a href="{{ route('data.rkp') }}" class="block px-4 py-2 text-gray-700 hover:bg-blue-100 focus:outline-none">Data RKP</a>
                                <a href="{{ route('data.bzting') }}" class="block px-4 py-2 text-gray-700 hover:bg-blue-100 focus:outline-none">Data Bzting</a>
                                <a href="{{ route('data.pelatihan') }}" class="block px-4 py-2 text-gray-700 hover:bg-blue-100 focus:outline-none">Data Pelatihan</a>
                                <a href="{{ route('data.ckp') }}" class="block px-4 py-2 text-gray-700 hover:bg-blue-100 focus:outline-none">Data CKP</a>
                                <a href="{{ route('data.manajemen-talenta') }}" class="block px-4 py-2 text-gray-700 hover:bg-blue-100 focus:outline-none">Data Manajemen Talenta</a>
                            </div>
                        </div>
                    </div>
                </nav>
            </div>

            <!-- Mobile Nav -->
            <div id="mobileMenu" class="sm:hidden hidden pb-4 space-y-2 text-sm font-medium border-t pt-4">
                <a href="{{ route('user.employees.index') }}" class="block text-gray-700 hover:text-blue-600 transition px-2">Employee</a>
                <details class="px-2">
                    <summary class="cursor-pointer text-gray-700 hover:text-blue-600">Data</summary>
                    <div class="mt-2 space-y-1 pl-4">
                        <a href="{{ route('data.rkp') }}" class="block hover:bg-gray-100 px-2 py-1 rounded text-gray-700">Data RKP</a>
                        <a href="{{ route('data.bzting') }}" class="block hover:bg-gray-100 px-2 py-1 rounded text-gray-700">Data Bzting</a>
                        <a href="{{ route('data.pelatihan') }}" class="block hover:bg-gray-100 px-2 py-1 rounded text-gray-700">Data Pelatihan</a>
                        <a href="{{ route('data.ckp') }}" class="block hover:bg-gray-100 px-2 py-1 rounded text-gray-700">Data CKP</a>
                        <a href="{{ route('data.manajemen-talenta') }}" class="block hover:bg-gray-100 px-2 py-1 rounded text-gray-700">Data Manajemen Talenta</a>
                    </div>
                </details>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="flex-1 py-10 px-4 sm:px-6 lg:px-8">
        <div class="max-w-7xl mx-auto">
            @yield('content')
        </div>
    </main>

    <!-- Footer -->
    <footer class="bg-white border-t py-4 text-center text-sm text-gray-500">
        &copy; {{ date('Y') }} {{ config('app.name', 'Laravel App') }}. All rights reserved.
    </footer>

    <!-- Dropdown Script -->
    <script>
        const dropdownBtn = document.getElementById('dataDropdownBtn');
        const dropdown = document.getElementById('dataDropdown');
    
        dropdownBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            dropdown.classList.toggle('hidden');
        });
    
        // Hide dropdown if clicking outside
        window.addEventListener('click', () => {
            dropdown.classList.add('hidden');
        });
    </script>

    <!-- Mobile Toggle Script -->
    <script>
        const navToggle = document.getElementById('navToggle');
        const mobileMenu = document.getElementById('mobileMenu');

        navToggle.addEventListener('click', () => {
            mobileMenu.classList.toggle('hidden');
        });
    </script>
</body>

</html>
