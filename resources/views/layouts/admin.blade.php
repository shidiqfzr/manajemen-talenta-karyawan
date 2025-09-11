<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ config('app.name', 'Admin Panel') }}</title>

    <!-- External Libraries -->
    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">

    <!-- Vite Assets -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        /* Custom Scrollbar */
        ::-webkit-scrollbar {
            width: 6px;
        }

        ::-webkit-scrollbar-track {
            background: #f1f5f9;
        }

        ::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 6px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: #94a3b8;
        }

        /* Smooth Transitions */
        .transition-smooth {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        /* Mobile Sidebar Animation */
        .sidebar-mobile {
            transform: translateX(-100%);
            transition: transform 0.3s ease-in-out;
        }

        .sidebar-mobile.open {
            transform: translateX(0);
        }

        /* Dropdown Animation */
        .dropdown-menu {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s ease-in-out;
        }

        .dropdown-menu.open {
            max-height: 200px;
        }
    </style>

    @stack('styles')
</head>

<body class="bg-gray-50 text-gray-900 min-h-screen">
    <div class="lg:flex">
        <!-- Mobile Menu Overlay -->
        <div id="mobileOverlay"
            class="fixed inset-0 bg-black bg-opacity-50 z-40 lg:hidden hidden transition-opacity duration-300">
        </div>

        <!-- Sidebar -->
        <aside id="sidebar"
            class="fixed top-0 left-0 z-50 w-56 h-screen bg-white shadow-xl border-r border-gray-200 
                    sidebar-mobile lg:translate-x-0 lg:static lg:z-auto">

            <!-- Sidebar Header -->
            <div class="relative flex items-center justify-between p-6 border-b border-gray-100">
                <div class="flex-1 flex items-center justify-center gap-3">
                    <img src="{{ asset('images/logo-ptpn4.png') }}" alt="Logo" class="h-8 w-auto object-contain">
                    <h1 class="text-xl font-bold text-green-600">Admin Panel</h1>
                </div>
                <button id="closeSidebar"
                    class="lg:hidden p-2 rounded-lg text-gray-500 hover:bg-gray-100 transition-colors">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <!-- Navigation -->
            <nav class="flex-1 p-4 space-y-2 overflow-y-auto">
                <!-- Karyawan Dropdown -->
                <div class="space-y-1">
                    <button id="karyawanToggle"
                        class="flex items-center justify-between w-full px-4 py-3 rounded-lg text-left 
                                transition-smooth group
                                {{ request()->routeIs('admin.employees.*')
                                    ? 'bg-green-50 text-green-700 shadow-sm'
                                    : 'text-gray-700 hover:bg-gray-50 hover:text-green-600' }}">
                        <div class="flex items-center">
                            <i class="fas fa-users w-5 text-center mr-3 text-sm"></i>
                            <span class="font-medium">Karyawan</span>
                        </div>
                        <i id="karyawanChevron"
                            class="fas fa-chevron-down text-xs transition-transform duration-200"></i>
                    </button>

                    <div id="karyawanDropdown" class="dropdown-menu pl-4">
                        <div class="space-y-1 py-2">
                            <a href="{{ route('admin.employees.index') }}"
                                class="flex items-center px-4 py-2 rounded-lg text-sm transition-smooth
                                    {{ request()->routeIs('admin.employees.index')
                                        ? 'bg-green-100 text-green-700 font-medium'
                                        : 'text-gray-600 hover:bg-gray-50 hover:text-green-600' }}">
                                <i class="fas fa-cogs w-4 text-center mr-3 text-xs"></i>
                                Manajemen
                            </a>
                            <a href="{{ route('admin.employees.statistics.index') }}"
                                class="flex items-center px-4 py-2 rounded-lg text-sm transition-smooth
                                    {{ request()->routeIs('admin.employees.statistics.*')
                                        ? 'bg-green-100 text-green-700 font-medium'
                                        : 'text-gray-600 hover:bg-gray-50 hover:text-green-600' }}">
                                <i class="fas fa-chart-line w-4 text-center mr-3 text-xs"></i>
                                Statistik
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Pelatihan -->
                <a href="{{ route('admin.trainings.index') }}"
                    class="flex items-center px-4 py-3 rounded-lg transition-smooth group
                        {{ request()->routeIs('admin.trainings.*')
                            ? 'bg-green-50 text-green-700 shadow-sm font-medium'
                            : 'text-gray-700 hover:bg-gray-50 hover:text-green-600' }}">
                    <i class="fas fa-chalkboard-teacher w-5 text-center mr-3 text-sm"></i>
                    <span class="font-medium">Pelatihan</span>
                </a>

                <!-- Penilaian -->
                <a href="{{ route('admin.evaluations.index') }}"
                    class="flex items-center px-4 py-3 rounded-lg transition-smooth group
                        {{ request()->routeIs('admin.evaluations.*')
                            ? 'bg-green-50 text-green-700 shadow-sm font-medium'
                            : 'text-gray-700 hover:bg-gray-50 hover:text-green-600' }}">
                    <i class="fas fa-clipboard-check w-5 text-center mr-3 text-sm"></i>
                    <span class="font-medium">Penilaian</span>
                </a>
            </nav>

            <!-- Logout Button -->
            <form method="POST" action="{{ route('logout') }}" id="logoutForm">
                @csrf
                <button type="submit"
                    class="flex items-center w-full px-4 py-3 rounded-lg transition-smooth
                text-red-700 hover:bg-gray-50 hover:text-red-600"
                    id="logoutButton">
                    <i class="fas fa-sign-out-alt w-5 text-center mr-3 text-sm"></i>
                    <span class="font-medium">Logout</span>
                </button>
            </form>
        </aside>

        <!-- Main Layout -->
        <div class="flex-1 h-screen overflow-y-auto">
            <!-- Top Navigation Bar (Mobile) -->
            <header class="lg:hidden bg-white shadow-sm border-b border-gray-200 p-4">
                <div class="flex items-center justify-between">
                    <button id="openSidebar" class="p-2 rounded-lg text-gray-600 hover:bg-gray-100 transition-colors">
                        <i class="fas fa-bars"></i>
                    </button>
                    <h1 class="text-xl font-bold text-center text-gray-900">Admin Panel</h1>
                    <div class="w-10"></div> <!-- Spacer for centering -->
                </div>
            </header>

            <!-- Main Content -->
            <main class="min-h-screen p-4 lg:p-6">
                <div class="max-w-7xl mx-auto">
                    @yield('content')
                </div>
            </main>
        </div>
    </div>

    <!-- JavaScript -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Elements
            const openSidebar = document.getElementById('openSidebar');
            const closeSidebar = document.getElementById('closeSidebar');
            const sidebar = document.getElementById('sidebar');
            const mobileOverlay = document.getElementById('mobileOverlay');
            const karyawanToggle = document.getElementById('karyawanToggle');
            const karyawanDropdown = document.getElementById('karyawanDropdown');
            const karyawanChevron = document.getElementById('karyawanChevron');
            const logoutBtn = document.getElementById('logoutButton');
            const logoutForm = document.getElementById('logoutForm');

            // Mobile Sidebar Controls
            function openMobileSidebar() {
                sidebar.classList.add('open');
                mobileOverlay.classList.remove('hidden');
                document.body.style.overflow = 'hidden';
            }

            function closeMobileSidebar() {
                sidebar.classList.remove('open');
                mobileOverlay.classList.add('hidden');
                document.body.style.overflow = '';
            }

            // Event Listeners
            openSidebar?.addEventListener('click', openMobileSidebar);
            closeSidebar?.addEventListener('click', closeMobileSidebar);
            mobileOverlay?.addEventListener('click', closeMobileSidebar);

            // Dropdown Toggle
            karyawanToggle?.addEventListener('click', function() {
                const isOpen = karyawanDropdown.classList.contains('open');

                if (isOpen) {
                    karyawanDropdown.classList.remove('open');
                    karyawanChevron.classList.remove('rotate-180');
                } else {
                    karyawanDropdown.classList.add('open');
                    karyawanChevron.classList.add('rotate-180');
                }
            });

            // Auto-open dropdown if current route is inside
            if ({{ request()->routeIs('admin.employees.*') ? 'true' : 'false' }}) {
                karyawanDropdown?.classList.add('open');
                karyawanChevron?.classList.add('rotate-180');
            }

            // Close sidebar on window resize (desktop)
            window.addEventListener('resize', function() {
                if (window.innerWidth >= 1024) {
                    closeMobileSidebar();
                }
            });

            // Escape key to close sidebar
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape' && !mobileOverlay.classList.contains('hidden')) {
                    closeMobileSidebar();
                }
            });

            logoutBtn?.addEventListener('click', function(e) {
                const confirmed = confirm("Apakah Anda yakin ingin keluar?");
                if (!confirmed) {
                    e.preventDefault();
                }
            });
        });
    </script>

    @stack('scripts')
</body>

</html>
