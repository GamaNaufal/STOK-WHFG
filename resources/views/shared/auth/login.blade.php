<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Warehouse FG Yamatogomu</title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background: linear-gradient(135deg, #005461 0%, #0C7779 50%, #249E94 100%);
            background-attachment: fixed;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Roboto', sans-serif;
            position: relative;
            overflow-y: auto;
        }

        /* Background decoration */
        body::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -10%;
            width: 600px;
            height: 600px;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 50%;
        }

        body::after {
            content: '';
            position: absolute;
            bottom: -30%;
            left: -5%;
            width: 500px;
            height: 500px;
            background: rgba(255, 255, 255, 0.03);
            border-radius: 50%;
        }

        .login-wrapper {
            display: grid;
            grid-template-columns: 1fr 1fr;
            width: 100%;
            max-width: 1000px;
            gap: 2rem;
            align-items: center;
            position: relative;
            z-index: 1;
            padding: 2rem;
        }

        .login-info {
            color: white;
            padding: 2rem;
        }

        .login-info h2 {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 1.5rem;
            line-height: 1.2;
        }

        .login-info p {
            font-size: 1rem;
            opacity: 0.95;
            margin-bottom: 2rem;
            line-height: 1.6;
        }

        .feature-list {
            list-style: none;
        }

        .feature-list li {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 1.25rem;
            font-size: 0.95rem;
            opacity: 0.9;
        }

        .feature-list i {
            font-size: 1.25rem;
            color: #249E94;
        }

        .login-container {
            background: white;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            overflow: hidden;
            width: 100%;
        }

        .login-header {
            background: linear-gradient(135deg, #0C7779 0%, #005461 100%);
            color: white;
            padding: 3rem 2.5rem 2.5rem;
            text-align: center;
            position: relative;
        }

        .login-header::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 200px;
            height: 200px;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 50%;
            transform: translate(50%, -50%);
        }

        .icon-box {
            display: inline-flex;
            width: 70px;
            height: 70px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 14px;
            align-items: center;
            justify-content: center;
            margin-bottom: 1.5rem;
            font-size: 2.2rem;
            color: white;
            border: 2px solid rgba(255, 255, 255, 0.3);
            position: relative;
            z-index: 1;
        }

        .login-header h1 {
            font-size: 1.75rem;
            margin-bottom: 0.25rem;
            font-weight: 700;
            position: relative;
            z-index: 1;
        }

        .login-header .subtitle {
            font-size: 0.9rem;
            opacity: 0.85;
            position: relative;
            z-index: 1;
        }

        .login-body {
            padding: 2.5rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: #0C7779;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .form-control {
            border-radius: 8px;
            border: 1.5px solid #e0e0e0;
            padding: 0.75rem 1rem;
            font-size: 0.95rem;
            transition: all 0.2s ease;
            background: #fafafa;
        }

        .form-control:focus {
            border-color: #0C7779;
            background: white;
            box-shadow: 0 0 0 3px rgba(12, 119, 121, 0.1);
        }

        .form-control::placeholder {
            color: #9ca3af;
        }

        .btn-login {
            width: 100%;
            padding: 0.85rem 1.5rem;
            font-size: 1rem;
            font-weight: 700;
            border-radius: 8px;
            border: none;
            background: linear-gradient(135deg, #0C7779 0%, #005461 100%);
            color: white;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 1rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }

        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 28px rgba(12, 119, 121, 0.35);
        }

        .btn-login:active {
            transform: translateY(0);
        }

        .alert-error {
            background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%);
            border: 1.5px solid #fca5a5;
            color: #991b1b;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1.5rem;
            font-size: 0.9rem;
            display: flex;
            align-items: flex-start;
            gap: 0.75rem;
        }

        .alert-error i {
            font-size: 1.1rem;
            flex-shrink: 0;
            margin-top: 0.1rem;
        }

        @media (max-width: 768px) {
            .login-wrapper {
                grid-template-columns: 1fr;
                gap: 1rem;
            }

            .login-info {
                display: none;
            }

            .login-header h1 {
                font-size: 1.5rem;
            }

            .login-body {
                padding: 2rem 1.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="login-wrapper">
        <!-- Left Side - Info -->
        <div class="login-info d-none d-lg-block">
            <h2>Warehouse FG</h2>
            <p>Sistem Manajemen Inventori & Pengiriman PT. Yamatogomu Indonesia</p>
            
            <ul class="feature-list">
                <li>
                    <i class="bi bi-box2-fill"></i>
                    Kelola stok dan inventori dengan efisien
                </li>
                <li>
                    <i class="bi bi-truck"></i>
                    Tracking pengiriman real-time
                </li>
                <li>
                    <i class="bi bi-graph-up"></i>
                    Laporan dan analitik lengkap
                </li>
                <li>
                    <i class="bi bi-shield-check"></i>
                    Kontrol akses berbasis role
                </li>
                <li>
                    <i class="bi bi-clock-history"></i>
                    Audit trail untuk setiap operasi
                </li>
            </ul>
        </div>

        <!-- Right Side - Login Form -->
        <div class="login-container">
            <!-- Header -->
            <div class="login-header">
                <div class="icon-box">
                    <i class="bi bi-box2-fill"></i>
                </div>
                <h1>Login</h1>
                <p class="subtitle">PT. Yamatogomu Indonesia</p>
            </div>

            <!-- Form -->
            <div class="login-body">
                @if ($errors->any())
                    <div class="alert-error">
                        <i class="bi bi-exclamation-circle-fill"></i>
                        <div>
                            <strong>Gagal Login</strong><br>
                            {{ $errors->first('email') }}
                        </div>
                    </div>
                @endif

                <form method="POST" action="{{ route('login') }}">
                    @csrf

                    <!-- Email -->
                    <div class="form-group">
                        <label class="form-label">
                            <i class="bi bi-envelope-fill"></i> Email
                        </label>
                        <input type="email" class="form-control @error('email') is-invalid @enderror" 
                               name="email" value="{{ old('email') }}" 
                               placeholder="Masukkan email Anda" required autofocus>
                    </div>

                    <!-- Password -->
                    <div class="form-group">
                        <label class="form-label">
                            <i class="bi bi-lock-fill"></i> Password
                        </label>
                        <input type="password" class="form-control @error('password') is-invalid @enderror" 
                               name="password" placeholder="Masukkan password" required>
                    </div>

                    <!-- Submit -->
                    <button type="submit" class="btn btn-login">
                        <i class="bi bi-box-arrow-in-right"></i> Login Sekarang
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
