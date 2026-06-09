<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Stok Warehouse FG Yamato')</title>
    <link rel="icon" type="image/png" href="{{ asset('logo.png') }}">
    <link rel="shortcut icon" href="{{ asset('favicon.ico') }}">
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    
    @include('shared.layouts.partials.layout-shell-styles')
    
    @yield('styles')
</head>
<body>
    <div class="container-fluid">
        @include('shared.layouts.partials.top-navbar')

        <div class="row">
            @include('shared.layouts.partials.sidebar')

            <!-- Main Content -->
            <main class="col-md-10 main-content">
                @if (session('success'))
                    <script>
                        document.addEventListener('DOMContentLoaded', function() {
                            if (typeof window.showToast === 'function') {
                                window.showToast("{{ session('success') }}", 'success');
                            }
                        });
                    </script>
                @endif

                @if (session('error'))
                    <script>
                        document.addEventListener('DOMContentLoaded', function() {
                            if (typeof window.showToast === 'function') {
                                window.showToast("{{ session('error') }}", 'danger');
                            }
                        });
                    </script>
                @endif

                @yield('content')
            </main>
        </div>
    </div>

    <div id="toast-container" class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 1300;"></div>

    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="{{ asset('js/alert-helper.js') }}"></script>
    @include('shared.layouts.partials.layout-shell-scripts')
    
    @yield('scripts')
</body>
</html>
