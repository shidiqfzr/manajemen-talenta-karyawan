<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ config('app.name', 'HRIS Talenta Hub') }} - PTPN IV</title>

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
    </style>

    @stack('styles')
</head>

<body class="bg-surface-50 text-surface-900 min-h-screen antialiased selection:bg-primary-500 selection:text-white"
    x-data="{
        sidebarCollapsed: localStorage.getItem('sidebar_collapsed') === 'true',
        mobileSidebarOpen: false,
        toggleCollapse() {
            this.sidebarCollapsed = !this.sidebarCollapsed;
            localStorage.setItem('sidebar_collapsed', this.sidebarCollapsed);
        }
    }">

    <div class="flex min-h-screen">
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
                'lg:w-64': !sidebarCollapsed
            }"
            class="fixed top-0 left-0 z-50 h-screen bg-white border-r border-surface-200 transition-all duration-300 ease-in-out flex flex-col shrink-0 shadow-xl lg:shadow-none lg:static">

            <!-- Sidebar Header / Branding -->
            <div class="h-16 flex items-center justify-between px-4 border-b border-surface-200 shrink-0">
                <a wire:navigate href="{{ route('admin.employees.index') }}" class="flex items-center gap-3 overflow-hidden">
                    <img src="{{ asset('images/logo-ptpn4.png') }}" alt="PTPN IV Logo" class="h-8 w-auto object-contain shrink-0">
                    <div class="flex flex-col min-w-0" x-show="!sidebarCollapsed" x-transition>
                        <span class="text-base font-bold text-surface-900 tracking-tight leading-tight truncate">Talenta Hub</span>
                        <span class="text-[10px] font-semibold text-primary-700 tracking-wider uppercase leading-tight truncate">PTPN IV Regional V</span>
                    </div>
                </a>

                <!-- Mobile Close Button -->
                <button type="button" @click="mobileSidebarOpen = false"
                    class="lg:hidden p-1.5 rounded-lg text-surface-400 hover:text-surface-700 hover:bg-surface-100 transition">
                    <i class="fas fa-times text-sm"></i>
                </button>
            </div>

            <!-- Sidebar Navigation Menu (Structured by DESIGN.md Section 3) -->
            <nav class="flex-1 p-3 space-y-4 overflow-y-auto overflow-x-hidden">
                <!-- Group 1: CORE HR -->
                <div>
                    <div class="px-2 pb-1.5" x-show="!sidebarCollapsed">
                        <span class="text-[10px] font-extrabold tracking-wider uppercase text-surface-400">Core HR</span>
                    </div>
                    <div class="my-1.5 border-t border-surface-200" x-show="sidebarCollapsed"></div>

                    <a wire:navigate href="{{ route('admin.employees.index') }}"
                        class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition group relative
                            {{ request()->routeIs('admin.employees.*')
                                ? 'bg-primary-50 text-primary-800 font-semibold border-l-4 border-primary-600'
                                : 'text-surface-600 hover:text-surface-900 hover:bg-surface-100 font-medium' }}"
                        :class="sidebarCollapsed ? 'justify-center px-2' : ''"
                        title="Data Karyawan">
                        <div class="w-8 h-8 rounded-lg flex items-center justify-center shrink-0 {{ request()->routeIs('admin.employees.*') ? 'bg-primary-100 text-primary-700' : 'text-surface-500 group-hover:text-primary-700 group-hover:bg-primary-50' }} transition">
                            <i class="fas fa-users text-sm"></i>
                        </div>
                        <div class="flex-1 min-w-0" x-show="!sidebarCollapsed" x-transition>
                            <p class="text-sm leading-tight truncate">Karyawan</p>
                            <p class="text-[11px] text-surface-400 font-normal leading-tight truncate">Data Induk &amp; Formasi</p>
                        </div>
                    </a>
                </div>

                <!-- Group 2: TALENT & MOBILITY -->
                <div>
                    <div class="px-2 pb-1.5" x-show="!sidebarCollapsed">
                        <span class="text-[10px] font-extrabold tracking-wider uppercase text-surface-400">Talent &amp; Mobility</span>
                    </div>
                    <div class="my-1.5 border-t border-surface-200" x-show="sidebarCollapsed"></div>

                    <a wire:navigate href="{{ route('admin.mutasi.index') }}"
                        class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition group relative
                            {{ request()->routeIs('admin.mutasi.*') || request()->routeIs('admin.vacancies.*')
                                ? 'bg-primary-50 text-primary-800 font-semibold border-l-4 border-primary-600'
                                : 'text-surface-600 hover:text-surface-900 hover:bg-surface-100 font-medium' }}"
                        :class="sidebarCollapsed ? 'justify-center px-2' : ''"
                        title="Mutasi & Penempatan">
                        <div class="w-8 h-8 rounded-lg flex items-center justify-center shrink-0 {{ request()->routeIs('admin.mutasi.*') || request()->routeIs('admin.vacancies.*') ? 'bg-primary-100 text-primary-700' : 'text-surface-500 group-hover:text-primary-700 group-hover:bg-primary-50' }} transition">
                            <i class="fas fa-arrows-split-up-and-left text-sm"></i>
                        </div>
                        <div class="flex-1 min-w-0" x-show="!sidebarCollapsed" x-transition>
                            <p class="text-sm leading-tight truncate">Mutasi &amp; Penempatan</p>
                            <p class="text-[11px] text-surface-400 font-normal leading-tight truncate">Monitoring &amp; MPP</p>
                        </div>
                    </a>
                </div>

                <!-- Group 3: DEVELOPMENT & PERFORMANCE -->
                <div>
                    <div class="px-2 pb-1.5" x-show="!sidebarCollapsed">
                        <span class="text-[10px] font-extrabold tracking-wider uppercase text-surface-400">Development &amp; Performance</span>
                    </div>
                    <div class="my-1.5 border-t border-surface-200" x-show="sidebarCollapsed"></div>

                    <div class="space-y-1">
                        <!-- Pelatihan -->
                        <a wire:navigate href="{{ route('admin.trainings.index') }}"
                            class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition group relative
                                {{ request()->routeIs('admin.trainings.*')
                                    ? 'bg-primary-50 text-primary-800 font-semibold border-l-4 border-primary-600'
                                    : 'text-surface-600 hover:text-surface-900 hover:bg-surface-100 font-medium' }}"
                            :class="sidebarCollapsed ? 'justify-center px-2' : ''"
                            title="Pelatihan">
                            <div class="w-8 h-8 rounded-lg flex items-center justify-center shrink-0 {{ request()->routeIs('admin.trainings.*') ? 'bg-primary-100 text-primary-700' : 'text-surface-500 group-hover:text-primary-700 group-hover:bg-primary-50' }} transition">
                                <i class="fas fa-chalkboard-user text-sm"></i>
                            </div>
                            <div class="flex-1 min-w-0" x-show="!sidebarCollapsed" x-transition>
                                <p class="text-sm leading-tight truncate">Pelatihan</p>
                                <p class="text-[11px] text-surface-400 font-normal leading-tight truncate">L&amp;D &amp; Sertifikasi</p>
                            </div>
                        </a>

                        <!-- Penilaian -->
                        <a wire:navigate href="{{ route('admin.evaluations.index') }}"
                            class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition group relative
                                {{ request()->routeIs('admin.evaluations.*')
                                    ? 'bg-primary-50 text-primary-800 font-semibold border-l-4 border-primary-600'
                                    : 'text-surface-600 hover:text-surface-900 hover:bg-surface-100 font-medium' }}"
                            :class="sidebarCollapsed ? 'justify-center px-2' : ''"
                            title="Penilaian">
                            <div class="w-8 h-8 rounded-lg flex items-center justify-center shrink-0 {{ request()->routeIs('admin.evaluations.*') ? 'bg-primary-100 text-primary-700' : 'text-surface-500 group-hover:text-primary-700 group-hover:bg-primary-50' }} transition">
                                <i class="fas fa-clipboard-check text-sm"></i>
                            </div>
                            <div class="flex-1 min-w-0" x-show="!sidebarCollapsed" x-transition>
                                <p class="text-sm leading-tight truncate">Penilaian</p>
                                <p class="text-[11px] text-surface-400 font-normal leading-tight truncate">Kinerja &amp; Evaluasi</p>
                            </div>
                        </a>
                    </div>
                </div>

                <!-- Group 4: MASTER DATA & REFERENSI -->
                <div>
                    <div class="px-2 pb-1.5" x-show="!sidebarCollapsed">
                        <span class="text-[10px] font-extrabold tracking-wider uppercase text-surface-400">Master Data</span>
                    </div>
                    <div class="my-1.5 border-t border-surface-200" x-show="sidebarCollapsed"></div>

                    <div class="space-y-1">
                        <!-- Unit Kerja -->
                        <a wire:navigate href="{{ route('admin.master.units.index') }}"
                            class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition group relative
                                {{ request()->routeIs('admin.master.units.*')
                                    ? 'bg-primary-50 text-primary-800 font-semibold border-l-4 border-primary-600'
                                    : 'text-surface-600 hover:text-surface-900 hover:bg-surface-100 font-medium' }}"
                            :class="sidebarCollapsed ? 'justify-center px-2' : ''"
                            title="Master Unit Kerja">
                            <div class="w-8 h-8 rounded-lg flex items-center justify-center shrink-0 {{ request()->routeIs('admin.master.units.*') ? 'bg-primary-100 text-primary-700' : 'text-surface-500 group-hover:text-primary-700 group-hover:bg-primary-50' }} transition">
                                <i class="fas fa-building-flag text-sm"></i>
                            </div>
                            <div class="flex-1 min-w-0" x-show="!sidebarCollapsed" x-transition>
                                <p class="text-sm leading-tight truncate">Unit Kerja</p>
                                <p class="text-[11px] text-surface-400 font-normal leading-tight truncate">43 Unit &amp; Wilayah</p>
                            </div>
                        </a>

                        <!-- Jabatan & Formasi -->
                        <a wire:navigate href="{{ route('admin.master.positions.index') }}"
                            class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition group relative
                                {{ request()->routeIs('admin.master.positions.*')
                                    ? 'bg-primary-50 text-primary-800 font-semibold border-l-4 border-primary-600'
                                    : 'text-surface-600 hover:text-surface-900 hover:bg-surface-100 font-medium' }}"
                            :class="sidebarCollapsed ? 'justify-center px-2' : ''"
                            title="Master Jabatan">
                            <div class="w-8 h-8 rounded-lg flex items-center justify-center shrink-0 {{ request()->routeIs('admin.master.positions.*') ? 'bg-primary-100 text-primary-700' : 'text-surface-500 group-hover:text-primary-700 group-hover:bg-primary-50' }} transition">
                                <i class="fas fa-id-card-clip text-sm"></i>
                            </div>
                            <div class="flex-1 min-w-0" x-show="!sidebarCollapsed" x-transition>
                                <p class="text-sm leading-tight truncate">Jabatan &amp; Posisi</p>
                                <p class="text-[11px] text-surface-400 font-normal leading-tight truncate">Bidang &amp; RM Level</p>
                            </div>
                        </a>
                    </div>
                </div>
            </nav>

            <!-- Bottom User Profile Card (Sidebar Footer) -->
            <div class="p-3 border-t border-surface-200 bg-surface-50/60 shrink-0">
                <!-- Expanded view -->
                <div x-show="!sidebarCollapsed" class="flex items-center gap-3 p-2 rounded-lg bg-white border border-surface-200 shadow-2xs">
                    <div class="relative w-8 h-8 rounded-full bg-primary-700 text-white flex items-center justify-center font-bold text-xs shrink-0 shadow-2xs">
                        {{ strtoupper(substr(auth()->user()->name ?? 'A', 0, 2)) }}
                        <span class="absolute bottom-0 right-0 w-2 h-2 rounded-full bg-emerald-500 ring-2 ring-white"></span>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-xs font-bold text-surface-900 truncate leading-tight">{{ auth()->user()->name ?? 'Administrator' }}</p>
                        <p class="text-[10px] text-surface-500 font-medium truncate leading-tight">HR Administrator</p>
                    </div>
                    <form method="POST" action="{{ route('logout') }}" id="sidebarLogoutForm">
                        @csrf
                        <button type="submit" 
                            onclick="return confirm('Apakah Anda yakin ingin keluar dari sistem?')"
                            class="p-1.5 rounded-md text-surface-400 hover:text-danger hover:bg-red-50 transition cursor-pointer" 
                            title="Keluar">
                            <i class="fas fa-arrow-right-from-bracket text-xs"></i>
                        </button>
                    </form>
                </div>

                <!-- Collapsed view -->
                <div x-show="sidebarCollapsed" class="flex flex-col items-center gap-2 py-1">
                    <div class="relative w-8 h-8 rounded-full bg-primary-700 text-white flex items-center justify-center font-bold text-xs shrink-0 shadow-2xs" 
                        title="{{ auth()->user()->name ?? 'Administrator' }}">
                        {{ strtoupper(substr(auth()->user()->name ?? 'A', 0, 2)) }}
                        <span class="absolute bottom-0 right-0 w-2 h-2 rounded-full bg-emerald-500 ring-2 ring-white"></span>
                    </div>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" 
                            onclick="return confirm('Apakah Anda yakin ingin keluar dari sistem?')"
                            class="p-2 rounded-md text-surface-400 hover:text-danger hover:bg-red-50 transition cursor-pointer"
                            title="Logout">
                            <i class="fas fa-power-off text-xs"></i>
                        </button>
                    </form>
                </div>
            </div>
        </aside>

        <!-- Main Layout Container -->
        <div class="flex-1 flex flex-col min-w-0 h-screen overflow-y-auto">
            <!-- Enterprise Sticky Topbar (Navbar) -->
            <header class="sticky top-0 z-30 bg-white/95 backdrop-blur-md border-b border-surface-200 px-4 lg:px-6 py-2.5 flex items-center justify-between gap-4 transition-all">
                <!-- Left: Mobile Menu Trigger, Desktop Collapse Toggle & Global Search -->
                <div class="flex items-center gap-3 flex-1 max-w-xl">
                    <!-- Mobile Hamburger -->
                    <button type="button" @click="mobileSidebarOpen = true" 
                        class="lg:hidden p-2 rounded-lg text-surface-600 hover:bg-surface-100 hover:text-surface-900 transition cursor-pointer"
                        aria-label="Buka Menu Navigasi">
                        <i class="fas fa-bars text-base"></i>
                    </button>

                    <!-- Desktop Collapse Toggle Button -->
                    <button type="button" @click="toggleCollapse()" 
                        class="hidden lg:inline-flex items-center justify-center w-9 h-9 rounded-lg text-surface-500 hover:text-surface-900 hover:bg-surface-100 transition border border-surface-200 cursor-pointer"
                        title="Ciutkan / Lebarkan Sidebar">
                        <i class="fas" :class="sidebarCollapsed ? 'fa-indent text-primary-700' : 'fa-outdent'"></i>
                    </button>

                    <!-- Global Quick Search Bar with Ctrl+K shortcut -->
                    <form action="{{ route('admin.employees.index') }}" method="GET" class="relative flex-1">
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-surface-400">
                                <i class="fas fa-search text-xs"></i>
                            </span>
                            <input type="text" id="globalSearchInput" name="search"
                                placeholder="Cari cepat data karyawan, NIK, formasi jabatan..."
                                class="w-full pl-9 pr-14 py-1.5 bg-surface-50 hover:bg-white focus:bg-white border border-surface-200 focus:border-primary-500 focus:ring-2 focus:ring-primary-100 rounded-lg text-xs text-surface-900 placeholder-surface-400 transition outline-none">
                            <div class="absolute inset-y-0 right-0 pr-2 flex items-center pointer-events-none">
                                <kbd class="hidden sm:inline-flex items-center px-1.5 py-0.5 text-[10px] font-semibold text-surface-400 bg-white border border-surface-200 rounded shadow-2xs">Ctrl K</kbd>
                            </div>
                        </div>
                    </form>
                </div>

                <!-- Right: Organization Badge, Notification Center & User Profile Dropdown -->
                <div class="flex items-center gap-3 shrink-0">
                    <!-- Organization Context Badge -->
                    <div class="hidden md:inline-flex items-center gap-2 px-3 py-1.5 rounded-full bg-surface-100 border border-surface-200 text-xs font-medium text-surface-700">
                        <span class="w-2 h-2 rounded-full bg-emerald-600"></span>
                        <span class="font-semibold text-surface-900">PTPN IV</span>
                        <span class="text-surface-300">|</span>
                        <span class="text-surface-500">Regional V</span>
                    </div>

                    <!-- Notification Center Bell -->
                    <div class="relative" x-data="{ notificationOpen: false }">
                        <button type="button" @click="notificationOpen = !notificationOpen"
                            class="relative p-2 rounded-lg text-surface-500 hover:text-surface-900 hover:bg-surface-100 transition border border-surface-200 cursor-pointer"
                            title="Pemberitahuan Sistem">
                            <i class="fas fa-bell text-sm"></i>
                            <span class="absolute -top-1 -right-1 w-4 h-4 rounded-full bg-danger text-white text-[10px] font-bold flex items-center justify-center animate-pulse">2</span>
                        </button>

                        <!-- Notification Popover -->
                        <div x-show="notificationOpen" @click.away="notificationOpen = false" x-cloak
                            x-transition:enter="transition ease-out duration-150 transform"
                            x-transition:enter-start="opacity-0 scale-95"
                            x-transition:enter-end="opacity-100 scale-100"
                            x-transition:leave="transition ease-in duration-100 transform"
                            x-transition:leave-start="opacity-100 scale-100"
                            x-transition:leave-end="opacity-0 scale-95"
                            class="absolute right-0 mt-2 w-80 sm:w-96 bg-white rounded-xl shadow-xl border border-surface-200 py-2 z-50">
                            <div class="px-4 py-2 border-b border-surface-100 flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <span class="text-xs font-bold text-surface-900">Pemberitahuan SDM</span>
                                    <span class="px-1.5 py-0.5 text-[10px] font-bold bg-primary-100 text-primary-800 rounded-full">2 Baru</span>
                                </div>
                                <span class="text-[11px] text-primary-700 hover:underline cursor-pointer font-medium">Tandai Dibaca</span>
                            </div>
                            <div class="divide-y divide-surface-100 max-h-72 overflow-y-auto">
                                <a wire:navigate href="{{ route('admin.mutasi.index', ['tab' => 'mpp']) }}" class="flex items-start gap-3 p-3 hover:bg-surface-50 transition">
                                    <div class="w-8 h-8 rounded-lg bg-earth-100 text-earth-700 flex items-center justify-center shrink-0 mt-0.5">
                                        <i class="fas fa-chart-pie text-xs"></i>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <p class="text-xs font-semibold text-surface-900">Validasi Formasi MPP Tersedia</p>
                                        <p class="text-[11px] text-surface-500 line-clamp-2 mt-0.5">Formasi baku kebun memiliki selisih kebutuhan tenaga kerja (-14 posisi). Mohon verifikasi.</p>
                                        <span class="text-[10px] text-surface-400 mt-1 block">10 menit yang lalu</span>
                                    </div>
                                </a>
                                <a wire:navigate href="{{ route('admin.mutasi.index', ['tab' => 'audit']) }}" class="flex items-start gap-3 p-3 hover:bg-surface-50 transition">
                                    <div class="w-8 h-8 rounded-lg bg-primary-100 text-primary-700 flex items-center justify-center shrink-0 mt-0.5">
                                        <i class="fas fa-clock-rotate-left text-xs"></i>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <p class="text-xs font-semibold text-surface-900">Sinkronisasi Riwayat Mutasi</p>
                                        <p class="text-[11px] text-surface-500 line-clamp-2 mt-0.5">Audit log mutasi penempatan karyawan diperbarui secara otomatis.</p>
                                        <span class="text-[10px] text-surface-400 mt-1 block">1 jam yang lalu</span>
                                    </div>
                                </a>
                            </div>
                            <div class="px-4 py-2 border-t border-surface-100 bg-surface-50/50 text-center">
                                <a wire:navigate href="{{ route('admin.mutasi.index', ['tab' => 'audit']) }}" class="text-xs font-semibold text-primary-700 hover:text-primary-800">
                                    Lihat Semua Riwayat &amp; Audit Log
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- User Profile Dropdown -->
                    <div class="relative" x-data="{ profileOpen: false }">
                        <button type="button" @click="profileOpen = !profileOpen"
                            class="flex items-center gap-2 p-1.5 pl-2 rounded-lg border border-surface-200 hover:bg-surface-50 transition cursor-pointer">
                            <div class="w-7 h-7 rounded-full bg-primary-700 text-white flex items-center justify-center text-xs font-bold shrink-0">
                                {{ strtoupper(substr(auth()->user()->name ?? 'A', 0, 2)) }}
                            </div>
                            <span class="hidden sm:inline-block text-xs font-bold text-surface-800 max-w-[120px] truncate">
                                {{ auth()->user()->name ?? 'Administrator' }}
                            </span>
                            <i class="fas fa-chevron-down text-[10px] text-surface-400 transition" :class="profileOpen ? 'rotate-180' : ''"></i>
                        </button>

                        <!-- Dropdown Menu -->
                        <div x-show="profileOpen" @click.away="profileOpen = false" x-cloak
                            x-transition:enter="transition ease-out duration-150 transform"
                            x-transition:enter-start="opacity-0 scale-95"
                            x-transition:enter-end="opacity-100 scale-100"
                            x-transition:leave="transition ease-in duration-100 transform"
                            x-transition:leave-start="opacity-100 scale-100"
                            x-transition:leave-end="opacity-0 scale-95"
                            class="absolute right-0 mt-2 w-56 bg-white rounded-xl shadow-xl border border-surface-200 py-1.5 z-50">
                            <div class="px-4 py-2.5 border-b border-surface-100">
                                <p class="text-xs font-bold text-surface-900 truncate">{{ auth()->user()->name ?? 'Administrator' }}</p>
                                <p class="text-[11px] text-surface-500 truncate">{{ auth()->user()->email ?? 'admin@ptpn4.co.id' }}</p>
                                <span class="inline-block mt-1 px-2 py-0.5 rounded text-[10px] font-bold bg-primary-100 text-primary-800">
                                    HR Administrator
                                </span>
                            </div>
                            <div class="py-1">
                                <a wire:navigate href="{{ route('admin.employees.index') }}" class="flex items-center gap-2.5 px-4 py-2 text-xs font-medium text-surface-700 hover:bg-surface-50 hover:text-surface-900">
                                    <i class="fas fa-users text-surface-400 w-4"></i>
                                    <span>Direktori Karyawan</span>
                                </a>
                                <a wire:navigate href="{{ route('admin.mutasi.index', ['tab' => 'mpp']) }}" class="flex items-center gap-2.5 px-4 py-2 text-xs font-medium text-surface-700 hover:bg-surface-50 hover:text-surface-900">
                                    <i class="fas fa-chart-line text-surface-400 w-4"></i>
                                    <span>Perencanaan Formasi (MPP)</span>
                                </a>
                            </div>
                            <div class="border-t border-surface-100 pt-1">
                                <form method="POST" action="{{ route('logout') }}" id="headerLogoutForm">
                                    @csrf
                                    <button type="submit" 
                                        onclick="return confirm('Apakah Anda yakin ingin keluar dari sistem?')"
                                        class="flex items-center gap-2.5 w-full text-left px-4 py-2 text-xs font-medium text-danger hover:bg-red-50 transition cursor-pointer">
                                        <i class="fas fa-arrow-right-from-bracket w-4"></i>
                                        <span>Keluar (Logout)</span>
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Main Content Area -->
            <main class="flex-1 p-4 lg:p-6">
                <div class="max-w-7xl mx-auto">
                    @yield('content')
                </div>
            </main>
        </div>
    </div>

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
