<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <title>Login - Manajemen Talenta</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .animate-float {
            animation: float 6s ease-in-out infinite;
        }

        .animate-float-delay {
            animation: float 8s ease-in-out infinite 2s;
        }

        .animate-float-delay-2 {
            animation: float 7s ease-in-out infinite 4s;
        }

        @keyframes float {

            0%,
            100% {
                transform: translateY(0px);
            }

            50% {
                transform: translateY(-10px);
            }
        }

        .glass-effect {
            backdrop-filter: blur(10px);
            background: rgba(255, 255, 255, 0.1);
        }

        .input-focus:focus {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(34, 197, 94, 0.15);
        }

        .fade-in {
            animation: fadeIn 0.8s ease-out;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
    </style>
</head>

<body class="bg-gradient-to-br from-surface-50 via-white to-surface-100 min-h-screen">
    <div class="flex min-h-screen">
        <!-- Left Column - Form -->
        <div class="w-full lg:w-2/5 flex items-center justify-center px-4 sm:px-6 lg:px-8 py-8">
            <div class="w-full max-w-sm space-y-8 fade-in">
                <!-- Header Section -->
                <div class="text-center">
                    <h2 class="text-3xl font-bold text-surface-900 mb-2 tracking-tight">Admin Login</h2>
                    <p class="text-surface-600 text-sm leading-relaxed">Masuk ke sistem manajemen talenta karyawan</p>
                </div>

                <!-- Error Alert -->
                @php
                    $loginError =
                        session('error') ??
                        ($errors->first('username') ??
                            ($errors->first('password') ?? ($errors->first('login') ?? null)));
                @endphp

                @if ($loginError)
                    <div role="alert" class="mb-6 p-4 bg-danger-50 border border-danger-200 text-danger-700 rounded-xl shadow-sm">
                        <div class="flex items-center">
                            <i class="fas fa-exclamation-circle text-danger mr-3" aria-hidden="true"></i>
                            <span class="text-sm font-medium">{{ $loginError }}</span>
                        </div>
                    </div>
                @endif

                <!-- Login Form -->
                <form method="POST" action="{{ route('login.submit') }}" class="space-y-6" id="loginForm">
                    @csrf
                    <div class="space-y-5">
                        <!-- Username Field -->
                        <div class="space-y-2">
                            <label for="username" class="block text-sm font-semibold text-surface-700">
                                <i class="fas fa-user text-surface-400 mr-2" aria-hidden="true"></i>Username
                            </label>
                            <div class="relative">
                                <input type="text" id="username" name="username"
                                    class="input-focus w-full px-4 py-3.5 border border-surface-200 placeholder-surface-400 text-surface-900 rounded-xl focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all duration-300 hover:border-surface-300"
                                    placeholder="Masukkan username admin" required autofocus autocomplete="username">
                                <div class="absolute inset-y-0 right-0 pr-4 flex items-center pointer-events-none">
                                    <i class="fas fa-user-shield text-surface-400 text-sm" aria-hidden="true"></i>
                                </div>
                            </div>
                        </div>

                        <!-- Password Field -->
                        <div class="space-y-2">
                            <label for="password" class="block text-sm font-semibold text-surface-700">
                                <i class="fas fa-lock text-surface-400 mr-2" aria-hidden="true"></i>Password
                            </label>
                            <div class="relative">
                                <input type="password" id="password" name="password" autocomplete="current-password"
                                    class="input-focus w-full px-4 py-3.5 pr-12 border border-surface-200 placeholder-surface-400 text-surface-900 rounded-xl focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all duration-300 hover:border-surface-300"
                                    placeholder="Masukkan password admin" required>
                                <button type="button" id="togglePassword"
                                    class="absolute inset-y-0 right-0 pr-4 flex items-center text-surface-500 hover:text-primary-600 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 transition-colors duration-200"
                                    aria-label="Toggle password visibility">
                                    <i id="eye" class="fas fa-eye text-lg" aria-hidden="true"></i>
                                    <i id="eye-off" class="fas fa-eye-slash text-lg hidden" aria-hidden="true"></i>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Submit Button -->
                    <div class="pt-2">
                        <button type="submit" id="submitBtn"
                            class="btn-hover w-full flex justify-center items-center gap-2 py-4 px-4 border border-transparent text-white font-semibold rounded-xl bg-gradient-to-r from-primary-600 to-primary-700 hover:from-primary-700 hover:to-primary-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 transition-all duration-300 active:scale-95 shadow-lg">
                            <i id="btnSpinner" class="fas fa-spinner fa-spin ml-2 hidden" aria-hidden="true"></i>
                            <span id="btnText">Masuk</span>
                        </button>
                    </div>
                </form>

                <!-- Footer -->
                <div class="text-center pt-4">
                    <p class=text-xs text-surface-500">
                        © 2025 PTPN IV Regional V. All rights reserved.
                    </p>
                </div>
            </div>
        </div>

        <!-- Right Column - Image/Branding -->
        <div
            class="hidden lg:flex lg:flex-1 lg:flex-col lg:justify-center lg:items-center bg-gradient-to-br from-primary-600 via-primary-700 to-primary-800 relative overflow-hidden">
            <!-- Background Image -->
            <div class="absolute inset-0 bg-center bg-cover opacity-30"
                style="background-image: url('{{ asset('images/login-background.jpg') }}');">
            </div>

            <!-- Animated Decorative Elements -->
            <div class="absolute top-10 left-10 w-20 h-20 bg-white bg-opacity-10 rounded-full animate-float"></div>
            <div class="absolute bottom-20 right-20 w-32 h-32 bg-white bg-opacity-5 rounded-full animate-float-delay">
            </div>
            <div class="absolute top-1/3 right-20 w-16 h-16 bg-white bg-opacity-10 rounded-full animate-float-delay-2">
            </div>

            <!-- Additional floating elements -->
            <div class="absolute top-1/4 left-1/4 w-12 h-12 bg-white bg-opacity-5 rounded-full animate-float"></div>
            <div class="absolute bottom-1/3 left-16 w-24 h-24 bg-white bg-opacity-5 rounded-full animate-float-delay-2">
            </div>

            <!-- Content -->
            <div class="relative z-10 text-center text-white px-2 max-w-lg">
                <!-- Logo badge -->
                <div
                    class="mx-auto mb-8 h-24 w-24 rounded-full bg-white flex items-center justify-center shadow-2xl ring-4 ring-white/20">
                    <img src="{{ asset('images/logo-ptpn4.png') }}" alt="Logo PTPN IV" class="h-16 w-16 object-contain"
                        loading="eager" decoding="async" />
                </div>

                <h3 class="text-4xl font-bold mb-4 tracking-tight">Manajemen Talenta Karyawan</h3>
                <p class="text-primary-100 text-lg leading-relaxed mb-8 opacity-90">
                    Portal administrasi untuk mengelola karyawan, talent, dan sumber daya manusia dengan efisien dan
                    modern.
                </p>

                <!-- Feature badges -->
                <div class="flex flex-wrap items-center justify-center gap-4 text-primary-200">
                    <div class="glass-effect px-4 py-2 rounded-full flex items-center">
                        <i class="fas fa-shield-alt mr-2 text-primary-300" aria-hidden="true"></i>
                        <span class="text-sm font-medium">Secure</span>
                    </div>
                    <div class="glass-effect px-4 py-2 rounded-full flex items-center">
                        <i class="fas fa-bolt mr-2 text-primary-300" aria-hidden="true"></i>
                        <span class="text-sm font-medium">Efficient</span>
                    </div>
                    <div class="glass-effect px-4 py-2 rounded-full flex items-center">
                        <i class="fas fa-mobile-alt mr-2 text-primary-300" aria-hidden="true"></i>
                        <span class="text-sm font-medium">Modern</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- JavaScript -->
    <script>
        (function() {
            const pwd = document.getElementById('password');
            const toggle = document.getElementById('togglePassword');
            const eye = document.getElementById('eye');
            const eyeOff = document.getElementById('eye-off');
            const form = document.getElementById('loginForm');
            const submitBtn = document.getElementById('submitBtn');
            const btnText = document.getElementById('btnText');
            const btnSpinner = document.getElementById('btnSpinner');

            // Password toggle functionality
            toggle.addEventListener('click', function() {
                const isPassword = pwd.type === 'password';
                pwd.type = isPassword ? 'text' : 'password';

                if (isPassword) {
                    eye.classList.add('hidden');
                    eyeOff.classList.remove('hidden');
                    toggle.classList.add('text-primary-600');
                    toggle.classList.remove('text-surface-500');
                } else {
                    eye.classList.remove('hidden');
                    eyeOff.classList.add('hidden');
                    toggle.classList.remove('text-primary-600');
                    toggle.classList.add('text-surface-500');
                }
            });

            // Input validation and styling
            const inputs = form.querySelectorAll('input[type="text"], input[type="password"]');
            inputs.forEach(input => {
                input.addEventListener('input', function() {
                    if (this.value.trim() !== '') {
                        this.classList.add('border-primary-300');
                        this.classList.remove('border-surface-200');
                    } else {
                        this.classList.remove('border-primary-300');
                        this.classList.add('border-surface-200');
                    }
                });

                // Enhanced focus effects
                input.addEventListener('focus', function() {
                    this.parentElement.style.transform = 'scale(1.02)';
                });

                input.addEventListener('blur', function() {
                    this.parentElement.style.transform = 'scale(1)';
                });
            });

            // Form submission with loading state
            form.addEventListener('submit', function(e) {
                // Show loading state
                btnText.textContent = 'Memproses...';
                btnSpinner.classList.remove('hidden');
                setTimeout(function() {
                    submitBtn.disabled = true;
                    submitBtn.classList.add('opacity-75', 'cursor-not-allowed');
                }, 10);
            });

            // Auto-focus username on page load
            document.getElementById('username').focus();
        })();
    </script>
</body>

</html>
