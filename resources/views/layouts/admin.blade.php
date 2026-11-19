<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ config('app.name', 'HRIS Talenta Hub') }} - PTPN IV</title>

    {{-- Apply the saved sidebar state before first paint so wire:navigate page swaps never flash the wrong state --}}
    <script>
        try {
            document.documentElement.classList.toggle('sidebar-collapsed', localStorage.getItem('sidebar_collapsed') === 'true');
        } catch (e) {}
    </script>

    @livewireStyles

    <!-- Google Fonts: Plus Jakarta Sans -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- External Libraries (Font Awesome 6) -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">

    <!-- Vite Assets -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        body {
            font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }

        /* Custom Scrollbar */
        ::-webkit-scrollbar {
            width: 6px;
            height: 6px;
        }

        ::-webkit-scrollbar-track {
            background: #f8fafc;
        }

        ::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 9999px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: #94a3b8;
        }

        [x-cloak] {
            display: none !important;
        }

        /* Pre-Alpine sidebar state, driven by the class set in <head> (mirrors the :class bindings below) */
        @media (min-width: 1024px) {
            #sidebar { width: 13rem; }
            html.sidebar-collapsed #sidebar { width: 5rem; }
        }

        /* Collapsed: logo and icons share one centre line (the 4px left border is offset by the padding) */
        html.sidebar-collapsed #sidebar-brand { justify-content: center; }
        html.sidebar-collapsed #sidebar-brand .brand-link { padding-inline: 0; }
        html.sidebar-collapsed #sidebar nav a { justify-content: center; padding-left: 0.5rem; padding-right: 0.75rem; }

        html.sidebar-collapsed #sidebar [x-show="!sidebarCollapsed"],
        html:not(.sidebar-collapsed) #sidebar [x-show="sidebarCollapsed"] {
            display: none;
        }
    </style>

    @stack('styles')
</head>

{{-- Topbar data comes from the service layer (DOMAIN.md §1): no counts or roles are written into this view. --}}
@php
    $authUser = auth()->user();
    $notificationItems = $authUser ? app(\App\Services\NotificationCenterService::class)->itemsFor($authUser) : [];
@endphp

<body class="bg-surface-50 text-surface-900 h-dvh overflow-hidden antialiased selection:bg-primary-500 selection:text-white"
    x-data="{
        sidebarCollapsed: localStorage.getItem('sidebar_collapsed') === 'true',
        mobileSidebarOpen: false,
        toggleCollapse() {
            this.sidebarCollapsed = !this.sidebarCollapsed;
            localStorage.setItem('sidebar_collapsed', this.sidebarCollapsed);
        }
    }"
    x-init="
        document.documentElement.classList.toggle('sidebar-collapsed', sidebarCollapsed);
        $watch('sidebarCollapsed', value => document.documentElement.classList.toggle('sidebar-collapsed', value));
    ">

    <div class="flex h-dvh overflow-hidden">
        <!-- Mobile Menu Overlay -->
        <div x-show="mobileSidebarOpen"
            x-transition:enter="transition-opacity ease-linear duration-200"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="transition-opacity ease-linear duration-200"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            @click="mobileSidebarOpen = false"
            class="fixed inset-0 bg-surface-900/60 backdrop-blur-xs z-40 lg:hidden"
            x-cloak>
        </div>

        <!-- Sidebar -->
        <aside id="sidebar"
            :class="{
                'translate-x-0': mobileSidebarOpen,
                '-translate-x-full lg:translate-x-0': !mobileSidebarOpen,
                'lg:w-20': sidebarCollapsed,
                'lg:w-52': !sidebarCollapsed
            }"
            class="fixed top-0 left-0 z-50 w-52 h-dvh bg-white border-r border-surface-200 transition-all duration-300 ease-in-out flex flex-col shrink-0 shadow-xl lg:shadow-none lg:static">

            <!-- Sidebar Header / Branding -->
            <div id="sidebar-brand" class="h-16 flex items-center justify-between px-3 border-b border-surface-200 shrink-0">
                <a wire:navigate href="{{ route('admin.employees.index') }}" class="brand-link flex items-center gap-3 px-4 overflow-hidden rounded-lg focus-visible:ring-2 focus-visible:ring-primary-500" aria-label="Talenta Hub PTPN IV Regional V">
                    <img src="{{ asset('images/logo-PTPN4.png') }}" alt="" class="h-8 w-8 object-contain shrink-0">
                    <div class="flex flex-col min-w-0" x-show="!sidebarCollapsed" x-transition>
                        <span class="text-base font-bold text-surface-900 tracking-tight leading-tight truncate">Talenta Hub</span>
                        <span class="text-xs font-medium text-primary-700 leading-tight truncate">PTPN IV Regional V</span>
                    </div>
                </a>

                <!-- Mobile Close Button -->
                <button aria-label="Tutup menu" type="button" @click="mobileSidebarOpen = false"
                    class="lg:hidden p-1.5 rounded-lg text-surface-500 hover:text-surface-700 hover:bg-surface-100 transition">
                    <i class="fas fa-times text-sm" aria-hidden="true"></i>
                </button>
            </div>

            {{-- Sidebar Navigation Menu (DESIGN.md §14: satu grup "Core HR" sesuai breadcrumb, satu entri per modul, urut menurut frekuensi kerja harian) --}}
            @php
                $navItems = [
                    ['label' => 'Karyawan', 'icon' => 'fa-users', 'route' => 'admin.employees.index', 'active' => ['admin.employees.*']],
                    ['label' => 'Mutasi', 'icon' => 'fa-arrows-split-up-and-left', 'route' => 'admin.mutasi.index', 'active' => ['admin.mutasi.*', 'admin.vacancies.*']],
                    ['label' => 'Formasi', 'icon' => 'fa-sitemap', 'route' => 'admin.master.formasi.index', 'active' => ['admin.master.*']],
                    ['label' => 'Pelatihan', 'icon' => 'fa-chalkboard-user', 'route' => 'admin.trainings.index', 'active' => ['admin.trainings.*']],
                    ['label' => 'Penilaian', 'icon' => 'fa-clipboard-check', 'route' => 'admin.evaluations.index', 'active' => ['admin.evaluations.*']],
                ];
            @endphp
            <nav aria-label="Navigasi utama" class="flex-1 p-3 overflow-y-auto overflow-x-hidden">
                <div class="px-2 pb-1.5" x-show="!sidebarCollapsed">
                    <span class="text-xs font-semibold text-surface-600">Core HR</span>
                </div>

                <div class="space-y-1">
                    @foreach ($navItems as $item)
                        @php $isActive = request()->routeIs(...$item['active']); @endphp
                        <a wire:navigate href="{{ route($item['route']) }}"
                            class="flex items-center gap-3 px-3 py-2 rounded-lg transition group relative border-l-4 focus-visible:ring-2 focus-visible:ring-primary-500
                                {{ $isActive
                                    ? 'bg-primary-50 text-primary-800 font-semibold border-primary-600'
                                    : 'text-surface-600 hover:text-surface-900 hover:bg-surface-100 font-medium border-transparent' }}"
                            title="{{ $item['label'] }}" aria-label="{{ $item['label'] }}"
                            @if ($isActive) aria-current="page" @endif>
                            <div class="w-8 h-8 rounded-lg flex items-center justify-center shrink-0 {{ $isActive ? 'bg-primary-100 text-primary-700' : 'text-surface-500 group-hover:text-surface-900' }} transition">
                                <i class="fas {{ $item['icon'] }} text-sm" aria-hidden="true"></i>
                            </div>
                            <span class="flex-1 min-w-0 text-sm truncate" x-show="!sidebarCollapsed" x-transition>{{ $item['label'] }}</span>
                        </a>
                    @endforeach
                </div>
            </nav>
        </aside>

        <!-- Main Layout Container -->
        {{-- Single scroll container: the window itself never scrolls, so the sticky topbar and sidebar stay pinned --}}
        <div id="admin-main" class="flex-1 flex flex-col min-w-0 h-dvh overflow-y-auto">
            <!-- Enterprise Sticky Topbar (Navbar) - Clean Enterprise Light Chrome -->
            <header class="sticky top-0 z-30 shrink-0 bg-white/95 backdrop-blur-md border-b border-surface-200 px-4 lg:px-6 h-16 flex items-center justify-between gap-4 transition-all">
                <!-- Left: Mobile Menu Trigger, Desktop Collapse Toggle & Global Search -->
                <div class="flex items-center gap-3 flex-1 max-w-xl">
                    <!-- Mobile Hamburger -->
                    <button type="button" @click="mobileSidebarOpen = true" 
                        class="lg:hidden p-2 rounded-lg text-surface-600 hover:bg-surface-100 hover:text-surface-900 transition cursor-pointer"
                        aria-label="Buka Menu Navigasi" aria-controls="sidebar">
                        <i class="fas fa-bars text-base" aria-hidden="true"></i>
                    </button>

                    <!-- Desktop Collapse Toggle Button -->
                    <button type="button" @click="toggleCollapse()" 
                        class="hidden lg:inline-flex items-center justify-center w-9 h-9 rounded-lg text-surface-500 hover:text-surface-900 hover:bg-surface-100 transition border border-surface-200 cursor-pointer focus-visible:ring-2 focus-visible:ring-primary-500"
                        title="Ciutkan / Lebarkan Sidebar" aria-label="Ciutkan atau lebarkan menu samping"
                        :aria-expanded="(!sidebarCollapsed).toString()" aria-controls="sidebar">
                        <i class="fas" :class="sidebarCollapsed ? 'fa-indent text-primary-700' : 'fa-outdent'" aria-hidden="true"></i>
                    </button>

                    <!-- Global Quick Search Bar with Ctrl+K shortcut -->
                    <form action="{{ route('admin.employees.index') }}" method="GET" role="search" class="relative flex-1">
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-surface-500">
                                <i class="fas fa-search text-xs" aria-hidden="true"></i>
                            </span>
                            <input type="text" id="globalSearchInput" name="search"
                                placeholder="Cari karyawan, NIK, jabatan, atau unit kerja..." aria-label="Cari karyawan berdasarkan nama, NIK, jabatan, atau unit kerja" autocomplete="off"
                                class="w-full pl-9 pr-14 py-1.5 bg-surface-50 hover:bg-white focus:bg-white border border-surface-200 focus:border-primary-500 focus:ring-2 focus:ring-primary-100 rounded-lg text-xs text-surface-900 placeholder-surface-500 transition outline-none">
                            <div class="absolute inset-y-0 right-0 pr-2 flex items-center pointer-events-none">
                                <kbd class="hidden sm:inline-flex items-center px-1.5 py-0.5 text-[10px] font-semibold text-surface-500 bg-white border border-surface-200 rounded shadow-2xs">Ctrl K</kbd>
                            </div>
                        </div>
                    </form>
                </div>

                <!-- Right: Notification Center & User Profile Dropdown -->
                <div class="flex items-center gap-3 shrink-0">
                    {{-- Pusat Pemberitahuan: butir dihitung dari data (NotificationCenterService), bukan angka tetap --}}
                    <x-admin.notification-center :items="$notificationItems" />

                    <!-- User Menu -->
                    <div class="relative" x-data="{ open: false }" @keydown.escape.window="if (open) { open = false; $refs.trigger.focus() }" @click.outside="open = false">
                        <button type="button" x-ref="trigger" @click="open = !open"
                            class="flex items-center gap-2 p-1.5 pl-2 rounded-lg border border-surface-200 hover:bg-surface-50 transition cursor-pointer focus-visible:ring-2 focus-visible:ring-primary-500"
                            aria-haspopup="menu" :aria-expanded="open.toString()" aria-controls="user-menu"
                            aria-label="Menu pengguna {{ $authUser?->name }}">
                            <div class="w-7 h-7 rounded-full bg-primary-700 text-white flex items-center justify-center text-xs font-bold shrink-0 shadow-2xs" aria-hidden="true">
                                {{ strtoupper(substr($authUser?->name ?? 'A', 0, 2)) }}
                            </div>
                            <span class="hidden sm:inline-block text-sm font-semibold text-surface-800 max-w-[120px] truncate">
                                {{ $authUser?->name }}
                            </span>
                            <i class="fas fa-chevron-down text-[10px] text-surface-500 transition" :class="open ? 'rotate-180' : ''" aria-hidden="true"></i>
                        </button>

                        <div id="user-menu" role="menu" aria-label="Menu pengguna" x-show="open" x-cloak
                            x-transition:enter="transition ease-out duration-150 transform"
                            x-transition:enter-start="opacity-0 scale-95"
                            x-transition:enter-end="opacity-100 scale-100"
                            class="absolute right-0 mt-2 w-60 bg-white rounded-xl shadow-xl border border-surface-200 py-1.5 z-50">
                            <div class="px-4 py-2.5 border-b border-surface-100">
                                <p class="text-sm font-bold text-surface-900 truncate">{{ $authUser?->name }}</p>
                                <p class="text-xs text-surface-600 truncate">{{ $authUser?->email }}</p>
                                <span class="inline-block mt-1 px-2 py-0.5 rounded text-[11px] font-bold bg-primary-100 text-primary-800">{{ $authUser?->roleLabel() }}</span>
                            </div>
                            <div class="pt-1">
                                <button type="button" role="menuitem" @click="open = false; $dispatch('confirm-logout')"
                                    class="flex items-center gap-2.5 w-full text-left px-4 py-2 text-sm font-medium text-surface-700 hover:bg-primary-50 hover:text-surface-900 focus-visible:bg-primary-50 focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-primary-500 transition cursor-pointer">
                                    <i class="fas fa-arrow-right-from-bracket text-surface-500 w-4" aria-hidden="true"></i>
                                    <span>Keluar</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Main Content Area -->
            <main class="flex-1 p-4 lg:p-6">
                <div class="max-w-7xl mx-auto">
                    @yield('content')
                    {{ $slot ?? '' }}
                </div>
            </main>
        </div>
    </div>

    <x-admin.logout-modal />

    <!-- Global Keydown Listener for Ctrl+K Quick Search -->
    <script>
        document.addEventListener('keydown', function(e) {
            if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
                e.preventDefault();
                const searchInput = document.getElementById('globalSearchInput');
                if (searchInput) {
                    searchInput.focus();
                    searchInput.select();
                }
            }
        });
    </script>

    @livewireScripts
    @stack('scripts')
</body>

</html>
