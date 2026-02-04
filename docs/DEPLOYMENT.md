# Deployment Guide

## Target: Ubuntu VPS (Nginx/Apache)

### 1. Server Preparation

- Install: PHP 8.2, Composer, MySQL, Node.js 18+, Nginx/Apache.
- Enable extensions: pdo_mysql, mbstring, zip, intl, gd.

### 2. Deploy Code

- Clone repo dari GitHub ke `/var/www/your-app`.
- `composer install --no-dev --optimize-autoloader`
- `npm install` lalu `npm run build`

### 3. Environment

- Copy `.env.example` → `.env`.
- Set `APP_ENV=production`, `APP_DEBUG=false`.
- Set database production.
- `php artisan key:generate`

### 4. Database

- `php artisan migrate --force`

### 5. Permission

- `storage` dan `bootstrap/cache` writable.

### 6. Queue & Scheduler

- Queue worker (opsional): `php artisan queue:work --tries=3`
- Scheduler: Cron `* * * * * php /var/www/your-app/artisan schedule:run`

### 7. SSL

- Rekomendasi: Let’s Encrypt (certbot).
- Force HTTPS pada web server.

## Target: Shared Hosting

1. Upload aplikasi (public ke web root).
2. Pastikan `index.php` menunjuk ke `bootstrap`.
3. Install dependency via SSH atau upload vendor build.
4. Set `.env` di luar public.
5. Build assets sebelum upload.
6. Jalankan migrate via SSH.

## Hardening

- `APP_DEBUG=false`
- Disable directory listing
- Pastikan `.env` tidak dapat diakses publik
- Gunakan SSL dan secure cookies

## Performance Caching (Production)

### 1) Static asset caching (Vite build)

- Build assets with Vite (`npm run build`).
- Pastikan server mengirim cache header panjang untuk `/build/*`.
- Untuk Apache, aturan di [public/.htaccess](../public/.htaccess) sudah menambahkan:
  `Cache-Control: public, max-age=31536000, immutable`

### 2) Blade view cache (no logic change)

- Jalankan saat deploy:
    - `php artisan view:cache`
    - (opsional) `php artisan config:cache`
    - (opsional) `php artisan route:cache`

### 3) PHP OPcache

- Aktifkan di php.ini (contoh minimal):
    - `opcache.enable=1`
    - `opcache.enable_cli=0`
    - `opcache.memory_consumption=128`
    - `opcache.max_accelerated_files=20000`
    - `opcache.validate_timestamps=1`
    - `opcache.revalidate_freq=60`

### 4) Application cache (Redis, short TTL)

- Gunakan Redis untuk cache store di production:
    - `CACHE_STORE=redis`
    - `READONLY_CACHE_ENABLED=true`
    - `READONLY_CACHE_TTL_SECONDS=180`
- Cache hanya untuk data read-only (laporan & tampilan stok).

### 5) Response cache (read-heavy GET only)

- Aktifkan hanya untuk endpoint read-heavy:
    - `RESPONSE_CACHE_ENABLED=true`
    - `RESPONSE_CACHE_TTL_SECONDS=120`
- Pastikan cache key berbasis filter/parameter dan user.

### 6) Validation

- Pastikan data tidak stale > 2–5 menit.
- Pastikan tidak ada caching pada POST/PUT/DELETE.
