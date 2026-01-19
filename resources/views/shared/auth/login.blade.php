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
        body {
            background: linear-gradient(135deg, #005461 0%, #0C7779 50%, #249E94 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Instrument Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
        }

        .login-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            overflow: hidden;
            width: 100%;
            max-width: 420px;
        }

        .login-header {
            background: linear-gradient(135deg, #0C7779 0%, #005461 100%);
            color: white;
            padding: 40px 20px;
            text-align: center;
        }

        .login-header h1 {
            font-size: 2rem;
            margin-bottom: 10px;
            font-weight: 600;
        }

        .login-header .subtitle {
            font-size: 0.95rem;
            opacity: 0.9;
        }

        .login-body {
            padding: 40px 30px;
        }

        .login-body .form-label {
            font-weight: 500;
            margin-bottom: 8px;
            color: #0C7779;
        }

        .login-body .form-control {
            border-radius: 6px;
            border: 1px solid #e3e3e0;
            padding: 10px 15px;
            font-size: 0.95rem;
            margin-bottom: 20px;
        }

        .login-body .form-control:focus {
            border-color: #0C7779;
            box-shadow: 0 0 0 3px rgba(12, 119, 121, 0.1);
        }

        .btn-login {
            width: 100%;
            padding: 12px;
            font-size: 1rem;
            font-weight: 600;
            border-radius: 6px;
            border: none;
            background: linear-gradient(135deg, #0C7779 0%, #005461 100%);
            color: white;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 10px;
        }

        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(12, 119, 121, 0.3);
        }

        .alert-error {
            background-color: #fff5f5;
            border: 1px solid #feb2b2;
            color: #c53030;
            border-radius: 6px;
            padding: 12px 15px;
            margin-bottom: 20px;
            font-size: 0.9rem;
        }

        .test-credentials {
            background-color: #e0f5f3;
            border-left: 4px solid #0C7779;
            padding: 15px;
            margin-top: 30px;
            border-radius: 6px;
        }

        .test-credentials h6 {
            color: #0C7779;
            font-weight: 600;
            margin-bottom: 12px;
            font-size: 0.9rem;
        }

        .test-credentials .cred-item {
            margin-bottom: 8px;
            font-size: 0.85rem;
            color: #555;
        }

        .test-credentials .cred-item strong {
            color: #005461;
        }

        .icon-box {
            display: inline-flex;
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, #249E94 0%, #3BC1A8 100%);
            border-radius: 12px;
            align-items: center;
            justify-content: center;
            margin-bottom: 20px;
            font-size: 2rem;
            color: white;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <!-- Header -->
        <div class="login-header">
            <div class="icon-box mx-auto">
                <i class="bi bi-box2"></i>
            </div>
            <h1><i class="bi bi-box2"></i> Warehouse FG</h1>
            <p class="subtitle">PT. Yamatogomu - Login</p>
        </div>

        <!-- Form -->
        <div class="login-body">
            @if ($errors->any())
                <div class="alert-error">
                    <i class="bi bi-exclamation-circle"></i>
                    {{ $errors->first('email') }}
                </div>
            @endif

            <form method="POST" action="{{ route('login') }}">
                @csrf

                <!-- Email -->
                <div class="mb-4">
                    <label class="form-label">
                        <i class="bi bi-envelope"></i> Email
                    </label>
                    <input type="email" class="form-control @error('email') is-invalid @enderror" 
                           name="email" value="{{ old('email') }}" 
                           placeholder="Masukkan email Anda" required autofocus>
                </div>

                <!-- Password -->
                <div class="mb-3">
                    <label class="form-label">
                        <i class="bi bi-lock"></i> Password
                    </label>
                    <input type="password" class="form-control @error('password') is-invalid @enderror" 
                           name="password" placeholder="Masukkan password" required>
                </div>

                <!-- Submit -->
                <button type="submit" class="btn btn-login">
                    <i class="bi bi-box-arrow-in-right"></i> Login
                </button>
            </form>

            <!-- Test Credentials Info -->
            <div class="test-credentials">
                <h6><i class="bi bi-info-circle"></i> Akun Test</h6>
                
                <div class="cred-item">
                    <strong>Admin:</strong><br>
                    Email: admin@yamato.local | Password: password
                </div>
                
                <div class="cred-item">
                    <strong>Packing Dept:</strong><br>
                    Email: budi@packing.local | Password: password
                </div>
                
                <div class="cred-item">
                    <strong>Warehouse Op:</strong><br>
                    Email: andi@warehouse.local | Password: password
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
