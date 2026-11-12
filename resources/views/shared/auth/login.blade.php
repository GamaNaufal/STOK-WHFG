<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#f5f5f7">
    <title>Login - Warehouse FG Yamatogomu</title>
    <link rel="icon" type="image/png" href="{{ asset('logo.png') }}">
    <link rel="shortcut icon" href="{{ asset('favicon.ico') }}">
    <link href="{{ asset('vendor/bootstrap-5.3.0/dist/css/bootstrap.min.css') }}" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('vendor/bootstrap-icons-1.11.0/font/bootstrap-icons.min.css') }}">
    <link rel="stylesheet" href="{{ asset('css/login-ui.css') }}">
    <link rel="stylesheet" href="{{ asset('css/apple-design.css') }}">
</head>
<body class="login-apple">
    <main class="login-page">
        <div class="login-wrapper">
            <section class="login-info" aria-label="Warehouse FG">
                <div class="login-introduction">
                    <span class="login-eyebrow">PT. Yamatogomu Indonesia</span>
                    <h2>Warehouse FG</h2>
                    <p>Sistem Manajemen Inventori &amp; Pengiriman PT. Yamatogomu Indonesia</p>
                </div>
                <div class="login-cinema" data-login-cinema>
                    <div class="login-cinema-screen">
                        <picture class="login-cinema-poster">
                            <source media="(max-width: 1023px)" srcset="{{ asset('media/warehouse-login/warehouse_fg_login_mobile.webp') }}">
                            <img src="{{ asset('media/warehouse-login/warehouse_fg_login_poster.webp') }}"
                                 alt="Area penyimpanan finished goods dengan rak pallet dan forklift"
                                 width="1280" height="720" fetchpriority="low" decoding="async">
                        </picture>
                        <video class="login-cinema-video" data-login-video
                               data-src="{{ asset('media/warehouse-login/warehouse_fg_login_desktop.mp4') }}"
                               autoplay muted loop playsinline preload="none" tabindex="-1" aria-hidden="true"
                               disablepictureinpicture disableremoteplayback></video>
                    </div>
                    <div class="login-cinema-footer">
                        <span>Penerimaan <span aria-hidden="true">·</span> Penyimpanan <span aria-hidden="true">·</span> Pengiriman</span>
                        <button type="button" class="login-motion-toggle" data-login-motion hidden aria-label="Jeda animasi gudang">
                            <i class="bi bi-pause-fill" aria-hidden="true" data-login-motion-icon></i>
                            <span data-login-motion-label>Jeda animasi</span>
                        </button>
                    </div>
                </div>
            </section>

            <section class="login-container" aria-labelledby="login-title">
                <header class="login-header">
                    <div class="icon-box">
                        <img src="{{ asset('logo.png') }}" alt="Logo PT. Yamatogomu Indonesia" class="login-brand-logo" width="44" height="44">
                    </div>
                    <p class="login-mobile-brand">Warehouse FG</p>
                    <h1 id="login-title">Login</h1>
                    <p class="subtitle">PT. Yamatogomu Indonesia</p>
                </header>

                <div class="login-body">
                    @if ($errors->any())
                        <div class="alert-error" role="alert" id="login-error">
                            <i class="bi bi-exclamation-circle-fill" aria-hidden="true"></i>
                            <div>
                                <strong>Gagal Login</strong><br>
                                {{ $errors->first('email') ?: $errors->first() }}
                            </div>
                        </div>
                    @endif

                    <form method="POST" action="{{ route('login') }}">
                        @csrf
                        <div class="form-group">
                            <label for="email" class="form-label">
                                <i class="bi bi-envelope-fill" aria-hidden="true"></i> Email
                            </label>
                            <input id="email" autocomplete="username" type="email" class="form-control @error('email') is-invalid @enderror"
                                   name="email" value="{{ old('email') }}" placeholder="Masukkan email Anda" required autofocus
                                   @error('email') aria-invalid="true" aria-describedby="login-error" @enderror>
                        </div>

                        <div class="form-group">
                            <label for="password" class="form-label">
                                <i class="bi bi-lock-fill" aria-hidden="true"></i> Password
                            </label>
                            <div class="password-wrapper">
                                <input id="password" autocomplete="current-password" type="password" class="form-control password-input @error('password') is-invalid @enderror"
                                       name="password" placeholder="Masukkan password" required
                                       @error('password') aria-invalid="true" aria-describedby="login-error" @enderror>
                                <button type="button" class="password-toggle" id="togglePassword" aria-label="Tampilkan password" aria-controls="password" aria-pressed="false">
                                    <i class="bi bi-eye" id="togglePasswordIcon" aria-hidden="true"></i>
                                </button>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-login">
                            <i class="bi bi-box-arrow-in-right" aria-hidden="true"></i> Login Sekarang
                        </button>
                    </form>
                </div>
            </section>
        </div>
    </main>

    <script type="module" src="{{ asset('js/pages/login-cinematic.js') }}"></script>
    <script>
        const passwordInput = document.getElementById('password');
        const togglePasswordButton = document.getElementById('togglePassword');
        const togglePasswordIcon = document.getElementById('togglePasswordIcon');

        if (passwordInput && togglePasswordButton && togglePasswordIcon) {
            togglePasswordButton.addEventListener('click', function () {
                const cursorStart = passwordInput.selectionStart;
                const cursorEnd = passwordInput.selectionEnd;
                const isPassword = passwordInput.getAttribute('type') === 'password';

                passwordInput.setAttribute('type', isPassword ? 'text' : 'password');
                togglePasswordIcon.classList.toggle('bi-eye', !isPassword);
                togglePasswordIcon.classList.toggle('bi-eye-slash', isPassword);
                togglePasswordButton.setAttribute('aria-label', isPassword ? 'Sembunyikan password' : 'Tampilkan password');
                togglePasswordButton.setAttribute('aria-pressed', String(isPassword));

                passwordInput.focus();
                if (cursorStart !== null && cursorEnd !== null) {
                    passwordInput.setSelectionRange(cursorStart, cursorEnd);
                }
            });
        }
    </script>
</body>
</html>
