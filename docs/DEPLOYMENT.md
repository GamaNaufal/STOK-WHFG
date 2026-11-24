# Deployment Guide

Panduan di bawah untuk instalasi baru. Untuk production existing dengan migration
sampai 15 Oktober 2026, gunakan [panduan upgrade](PRODUCTION_UPGRADE_FROM_2026_10_15.md).
Pertahankan database, .env dan APP_KEY existing. Jangan menjalankan key:generate
atau membuat ulang database sebagai bagian upgrade.
Hasil pemeriksaan rilis terbaru dan prasyarat yang belum selesai tercatat di
[kesiapan production 24 November 2026](PRODUCTION_READINESS_2026-11-24.md).

## Target: Ubuntu VPS (Nginx/Apache)

### 1. Server Preparation

- Install: PHP sesuai composer.lock (minimal 8.2), Composer, MySQL, Nginx/Apache.
- Untuk build Vite 7 pada lockfile saat ini: Node.js ^20.19.0 atau >=22.12.0.
- Enable extensions: pdo_mysql, mbstring, zip, intl, gd.

### 2. Deploy Code

- Clone repo dari GitHub ke `/var/www/your-app`.
- `composer install --no-dev --optimize-autoloader`
- `npm ci` lalu `npm run build`

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
