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
