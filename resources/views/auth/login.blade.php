<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <title>Masuk - Manajemen Talenta</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css'])
    <style>
        /* Keep browser autofill from painting a blue field, without removing the border or the focus ring */
        input:-webkit-autofill,
        input:-webkit-autofill:hover,
        input:-webkit-autofill:focus {
            -webkit-text-fill-color: var(--color-surface-900);
            box-shadow: 0 0 0 1000px #fff inset;
        }

        input:-webkit-autofill:focus {
            box-shadow: 0 0 0 1000px #fff inset, 0 0 0 2px rgb(from var(--color-primary-500) r g b / 0.2);
        }
    </style>
</head>

<body class="bg-primary-900 min-h-screen">
    <!-- Page backdrop: darkened plantation photo from lg up, flat green on small screens to save field bandwidth -->
    {{-- Zoomed onto the palm canopy (top of the photo) so the workers never peek out from behind the card. --}}
    <div aria-hidden="true" class="hidden lg:block fixed inset-0"
        style="background-image: url('{{ asset('images/login-background.jpg') }}'); background-size: max(260vw, 400vh) auto; background-position: center top;">
        <div class="absolute inset-0 bg-primary-900/85"></div>
    </div>

    <div class="relative min-h-screen flex items-center justify-center p-4 sm:p-6 lg:p-4 short:py-2">
        <div class="relative w-full max-w-md lg:max-w-6xl">
            <div class="bg-white rounded-2xl flex lg:p-3 lg:min-h-[min(640px,calc(100vh-2rem))]">
                <!-- Form column: first in the DOM (mobile and tab order), shown on the right from lg up -->
                <main class="w-full lg:w-[46%] lg:order-2 flex flex-col px-6 sm:px-8 lg:px-12 py-8 short:py-3">
                    <div class="w-full max-w-sm mx-auto flex-1 flex flex-col">
                        <div class="flex-1 flex flex-col justify-center">
                            @php
                                $loginError =
                                    session('error') ??
                                    ($errors->first('username') ??
                                        ($errors->first('password') ?? ($errors->first('login') ?? null)));
                            @endphp

                            <img src="{{ asset('images/logo-PTPN4.png') }}" alt="Logo PTPN IV" width="320" height="320"
                                class="mx-auto mb-4 short:mb-1 h-[clamp(3rem,10vh,4rem)] w-[clamp(3rem,10vh,4rem)] object-contain" />

                            <!-- Header Section -->
                            <h1 class="text-3xl short:text-2xl font-bold text-surface-900 mb-2 tracking-tight text-center">Selamat Datang</h1>
                            <p class="text-surface-600 text-sm leading-relaxed {{ $loginError ? 'mb-4' : 'mb-8' }} short:mb-4 text-center">Sistem Informasi Manajemen Talenta &amp; Formasi Karyawan</p>

                            <!-- Error Alert -->
                            @if ($loginError)
                                <div id="loginError" role="alert" class="mb-4 p-3 bg-danger-50 border border-danger-200 text-danger-700 rounded-xl">
                                    <div class="flex items-center">
                                        <i class="fas fa-exclamation-circle text-danger mr-3" aria-hidden="true"></i>
                                        <span class="text-sm font-medium">{{ $loginError }}</span>
                                    </div>
                                </div>
                            @endif

                            <!-- Login Form -->
                            <form method="POST" action="{{ route('login.submit') }}" class="space-y-6 short:space-y-4" id="loginForm">
                                @csrf
                                <div class="space-y-5 short:space-y-3">
                                    <!-- Username Field (accepts username or email, see LoginController) -->
                                    <div class="space-y-1.5">
                                        <label for="username" class="block text-sm font-semibold text-surface-700">Username atau Email</label>
                                        <input type="text" id="username" name="username" value="{{ old('username') }}"
                                            class="block w-full h-11 px-3.5 rounded-lg border border-surface-300 bg-white text-base sm:text-sm text-surface-900 placeholder-surface-400 hover:border-surface-400 focus-visible:outline-none focus-visible:border-primary-600 focus-visible:ring-2 focus-visible:ring-primary-500/20 aria-invalid:border-danger aria-invalid:ring-2 aria-invalid:ring-danger/20 transition-colors"
                                            placeholder="Masukkan username atau email" required @if (!$loginError) autofocus @endif autocomplete="username"
                                            @if ($loginError) aria-invalid="true" aria-describedby="loginError" @endif>
                                    </div>

                                    <!-- Password Field -->
                                    <div class="space-y-1.5">
                                        <label for="password" class="block text-sm font-semibold text-surface-700">Password</label>
                                        <div class="relative">
                                            <input type="password" id="password" name="password" autocomplete="current-password"
                                                class="block w-full h-11 pl-3.5 pr-12 rounded-lg border border-surface-300 bg-white text-base sm:text-sm text-surface-900 placeholder-surface-400 hover:border-surface-400 focus-visible:outline-none focus-visible:border-primary-600 focus-visible:ring-2 focus-visible:ring-primary-500/20 transition-colors"
                                                placeholder="Masukkan password" required @if ($loginError) autofocus @endif
                                                aria-describedby="capsLockHint">
                                            <button type="button" id="togglePassword"
                                                class="absolute inset-y-0 right-0 w-12 flex items-center justify-center rounded-lg text-surface-500 hover:text-primary-600 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 transition-colors"
                                                aria-label="Tampilkan password" aria-pressed="false">
                                                <span id="eye"><i class="fas fa-eye text-lg" aria-hidden="true"></i></span>
                                                <span id="eye-off" class="hidden"><i class="fas fa-eye-slash text-lg" aria-hidden="true"></i></span>
                                            </button>
                                        </div>
                                        <p id="capsLockHint" class="hidden text-sm text-warning-700" role="status">
                                            <i class="fas fa-exclamation-triangle mr-1" aria-hidden="true"></i>Caps Lock sedang aktif.
                                        </p>
                                    </div>
                                </div>

                                <!-- Submit Button -->
                                <button type="submit" id="submitBtn"
                                    class="w-full h-11 inline-flex justify-center items-center gap-2 rounded-xl bg-primary-600 hover:bg-primary-700 active:bg-primary-800 text-white font-semibold shadow-sm transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 focus-visible:ring-offset-2 disabled:opacity-60 disabled:cursor-not-allowed">
                                    <span id="btnSpinner" class="hidden"><i class="fas fa-spinner fa-spin" aria-hidden="true"></i></span>
                                    <span id="btnText">Masuk</span>
                                </button>
                            </form>
                        </div>

                        <!-- Footer -->
                        <p class="mt-8 short:mt-3 text-xs text-surface-500 text-center">
                            &copy; {{ date('Y') }} PTPN IV Regional V. Hak cipta dilindungi.
                        </p>
                    </div>
                </main>

                <!-- Photo panel: inset inside the card from lg up -->
                <aside class="hidden lg:flex lg:flex-1 lg:order-1 flex-col justify-end relative overflow-hidden rounded-xl bg-primary-900 p-8 short:p-6">
                    <!-- Flat green overlay (no gradient) so the white text keeps its contrast -->
                    <div class="absolute inset-0 bg-cover"
                        style="background-image: url('{{ asset('images/login-background.jpg') }}'); background-position: 35% center;">
                    </div>
                    <div data-overlay class="absolute inset-0 bg-primary-900/70"></div>

                    <div class="relative z-10 text-white max-w-md">
                        <h2 class="text-4xl short:text-3xl font-bold leading-tight tracking-tight text-balance mb-3">Pengelolaan Talenta yang Terpadu dan Akuntabel</h2>
                        <p class="text-base leading-relaxed">
                            Satu platform untuk mengelola data karyawan, formasi jabatan, mutasi, pelatihan, penilaian kinerja, dan pemetaan talenta di lingkungan PTPN IV Regional V.
                        </p>
                    </div>
                </aside>
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

                eye.classList.toggle('hidden', isPassword);
                eyeOff.classList.toggle('hidden', !isPassword);
                toggle.classList.toggle('text-primary-600', isPassword);
                toggle.classList.toggle('text-surface-500', !isPassword);
                toggle.setAttribute('aria-pressed', isPassword ? 'true' : 'false');
                toggle.setAttribute('aria-label', isPassword ? 'Sembunyikan password' : 'Tampilkan password');
            });

            // Caps Lock warning while typing the password
            const capsHint = document.getElementById('capsLockHint');
            const syncCaps = function(e) {
                capsHint.classList.toggle('hidden', !e.getModifierState('CapsLock'));
            };
            pwd.addEventListener('keydown', syncCaps);
            pwd.addEventListener('keyup', syncCaps);
            pwd.addEventListener('blur', function() {
                capsHint.classList.add('hidden');
            });

            // Form submission with loading state
            form.addEventListener('submit', function() {
                btnText.textContent = 'Memproses...';
                btnSpinner.classList.remove('hidden');
                setTimeout(function() {
                    submitBtn.disabled = true;
                }, 10);
            });

            // Restore the button when the page returns from the back/forward cache
            window.addEventListener('pageshow', function(e) {
                if (e.persisted) {
                    btnText.textContent = 'Masuk';
                    btnSpinner.classList.add('hidden');
                    submitBtn.disabled = false;
                }
            });
        })();
    </script>
</body>

</html>
