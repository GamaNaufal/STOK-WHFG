# Setup Local Development

## Prasyarat

- PHP 8.2
- Composer
- Node.js 18+
- MySQL

## Langkah Setup

1. Clone repository dari GitHub.
2. Install dependency backend:
    - `composer install`
3. Install dependency frontend:
    - `npm install`
4. Copy environment:
    - `cp .env.example .env` (Windows: copy manual)
5. Set konfigurasi database di `.env`:
    - DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME, DB_PASSWORD
6. Generate app key:
    - `php artisan key:generate`
7. Jalankan migrasi:
    - `php artisan migrate`
8. Jika perlu data awal:
    - `php artisan db:seed`
9. Build assets:
    - `npm run dev` (development) atau `npm run build` (production build)
10. Permission:

- Pastikan `storage` dan `bootstrap/cache` writable.

## Catatan

- Login via `/login`.
- Role menentukan menu yang tampil.
