<!DOCTYPE html>
<html lang="en">

<head>
    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ config('app.name', 'Admin Panel') }}</title>

    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>

    <style>
        .submenu {
            display: none;
        }

        .submenu.open {
            display: block;
        }

        /* Scrollbar Styling */
        ::-webkit-scrollbar {
            width: 6px;
        }

        ::-webkit-scrollbar-thumb {
            background: #94a3b8;
            border-radius: 6px;
        }
    </style>

    @stack('styles')
</head>

<body class="bg-gray-100 text-gray-800 min-h-screen flex">

    <!-- Sidebar -->
    <aside id="sidebar"
        class="w-56 bg-white shadow-lg border-r border-gray-200 hidden md:flex flex-col fixed h-screen z-50 sidebar">
        <div class="p-6 text-2xl font-bold text-blue-600 border-b border-gray-100 flex justify-between items-center">
            <span>Admin Panel</span>
            <button id="sidebarToggle" class="md:hidden text-gray-600">
                <i class="fas fa-bars"></i>
            </button>
        </div>
        <nav class="flex-1 overflow-y-auto p-4 space-y-2">

            <!-- Karyawan Menu -->
            <div class="space-y-1">
                <button id="karyawanDropdownToggle" class="flex items-center w-full px-4 py-2 rounded-lg transition
                    {{ request()->routeIs('admin.employees.*') ? 'bg-blue-100 text-blue-700 font-semibold' : 'text-gray-700 hover:bg-blue-50 hover:text-blue-700' }}">
                    <i class="fas fa-users mr-2"></i> Karyawan
                    <i class="fas fa-chevron-down ml-auto transition-transform duration-200" id="karyawanChevron"></i>
                </button>
                <div id="karyawanDropdown" class="submenu pl-6 space-y-1">
                    <a href="{{ route('admin.employees.index') }}"
                        class="flex items-center px-3 py-2 rounded-lg transition text-sm
                        {{ request()->routeIs('admin.employees.index') ? 'bg-blue-100 text-blue-700 font-semibold' : 'text-gray-700 hover:bg-blue-50 hover:text-blue-700' }}">
                        <i class="fas fa-cogs mr-2 text-sm"></i> Manajemen
                    </a>
                    <a href="{{ route('admin.employees.statistics.index') }}"
                        class="flex items-center px-3 py-2 rounded-lg transition text-sm
                        {{ request()->routeIs('admin.employees.statistics.*') ? 'bg-blue-100 text-blue-700 font-semibold' : 'text-gray-700 hover:bg-blue-50 hover:text-blue-700' }}">
                        <i class="fas fa-chart-line mr-2 text-sm"></i> Statistik
                    </a>
                </div>
            </div>

            <!-- Pelatihan -->
            <a href="{{ route('admin.trainings.index') }}"
                class="flex items-center px-4 py-2 rounded-lg transition
                    {{ request()->routeIs('admin.trainings.*') ? 'bg-blue-100 text-blue-700 font-semibold' : 'text-gray-700 hover:bg-blue-50 hover:text-blue-700' }}">
                <i class="fas fa-chalkboard-teacher mr-2"></i> Pelatihan
            </a>

            <!-- Penilaian -->
            <a href="{{ route('admin.evaluations.index') }}"
                class="flex items-center px-4 py-2 rounded-lg transition
                    {{ request()->routeIs('admin.evaluations.*') ? 'bg-blue-100 text-blue-700 font-semibold' : 'text-gray-700 hover:bg-blue-50 hover:text-blue-700' }}">
                <i class="fas fa-clipboard-check mr-2"></i> Penilaian
            </a>
        </nav>
    </aside>

    <!-- Mobile Sidebar Overlay -->
    <div id="mobileSidebar" class="fixed inset-0 bg-black bg-opacity-50 z-40 hidden">
        <div class="w-64 bg-white h-full p-4 space-y-2 shadow-xl">
            <div class="flex justify-between items-center mb-4">
                <span class="text-lg font-bold text-blue-600">Menu</span>
                <button id="closeMobileSidebar" class="text-gray-600">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <a href="{{ route('admin.employees.index') }}"
                class="flex items-center px-4 py-2 rounded-lg text-gray-700 hover:bg-blue-50 hover:text-blue-700 transition">
                <i class="fas fa-users mr-2"></i> Karyawan
            </a>
            <a href="{{ route('admin.trainings.index') }}"
                class="flex items-center px-4 py-2 rounded-lg text-gray-700 hover:bg-blue-50 hover:text-blue-700 transition">
                <i class="fas fa-chalkboard-teacher mr-2"></i> Pelatihan
            </a>
            <a href="{{ route('admin.evaluations.index') }}"
                class="flex items-center px-4 py-2 rounded-lg text-gray-700 hover:bg-blue-50 hover:text-blue-700 transition">
                <i class="fas fa-clipboard-check mr-2"></i> Penilaian
            </a>
        </div>
    </div>

    <!-- Main Content -->
    <main class="flex-1 ml-0 md:ml-56 p-6 transition-all">
        <div class="max-w-7xl mx-auto">
            @yield('content')
        </div>
    </main>

    <!-- Scripts -->
    <script>
        const karyawanDropdownToggle = document.getElementById('karyawanDropdownToggle');
        const karyawanDropdown = document.getElementById('karyawanDropdown');
        const karyawanChevron = document.getElementById('karyawanChevron');

        karyawanDropdownToggle?.addEventListener('click', () => {
            karyawanDropdown.classList.toggle('open');
            karyawanChevron.classList.toggle('rotate-180');
        });

        // Mobile sidebar toggling
        const sidebarToggle = document.getElementById('sidebarToggle');
        const mobileSidebar = document.getElementById('mobileSidebar');
        const closeMobileSidebar = document.getElementById('closeMobileSidebar');

        sidebarToggle?.addEventListener('click', () => {
            mobileSidebar.classList.remove('hidden');
        });

        closeMobileSidebar?.addEventListener('click', () => {
            mobileSidebar.classList.add('hidden');
        });

        // Close sidebar on outside click
        window.addEventListener('click', (e) => {
            if (mobileSidebar && !mobileSidebar.contains(e.target) && !sidebarToggle.contains(e.target)) {
                mobileSidebar.classList.add('hidden');
            }
        });
    </script>

    @stack('scripts')
</body>

</html>
