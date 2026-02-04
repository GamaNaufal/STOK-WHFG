# 📘 Panduan Tech Stack - STOK WHFG

> **Dokumentasi ini dibuat untuk:** Developer, DevOps, atau IT yang akan melakukan deployment aplikasi warehouse management system STOK WHFG.

---

## 🎯 Apa Itu STOK WHFG?

**STOK WHFG** adalah sistem manajemen gudang berbasis web yang membantu mengelola:

- Stock/inventori barang
- Lokasi penyimpanan (pallet & box)
- Proses input dan withdrawal barang
- Laporan operasional dan audit trail

---

## 🏗️ Teknologi Apa Saja Yang Dipakai?

Aplikasi ini dibangun menggunakan:

| Komponen       | Teknologi               | Fungsi                                       |
| -------------- | ----------------------- | -------------------------------------------- |
| **Backend**    | Laravel 12              | Framework PHP untuk logika bisnis & database |
| **Frontend**   | Blade + Tailwind CSS v4 | Template engine & styling tampilan           |
| **Database**   | MySQL atau SQLite       | Penyimpanan data aplikasi                    |
| **Build Tool** | Vite                    | Compile & bundling file CSS/JS               |

**Analogi Sederhana:**

- **Laravel** = Mesin dan otak aplikasi
- **Blade + Tailwind** = Desain tampilan yang dilihat user
- **MySQL** = Lemari arsip untuk simpan semua data
- **Vite** = Alat packaging untuk siapkan file ke production

---

## 📋 Persiapan Sebelum Deploy

### ✅ Checklist Software Yang Harus Diinstall

Sebelum deploy, pastikan server sudah punya software ini:

#### 1️⃣ **PHP 8.2 atau Lebih Baru**

**Apa itu?** Bahasa pemrograman yang dipakai Laravel.

**Cara cek versi:**

```bash
php -v
```

**Extensions PHP yang dibutuhkan:**
| Extension | Kenapa Perlu? |
|-----------|---------------|
| `pdo_mysql` | Koneksi ke database MySQL |
| `mbstring` | Handle text dengan karakter special (UTF-8) |
| `xml` | Baca/tulis file XML |
| `zip` | Compress/extract file |
| `gd` | Proses gambar (untuk PDF) |
| `intl` | Format tanggal/mata uang internasional |
| `bcmath` | Kalkulasi angka presisi tinggi |
| `curl` | Request ke API eksternal |

---

#### 2️⃣ **Composer (Package Manager PHP)**

**Apa itu?** Seperti "npm" tapi untuk PHP. Dipakai install library/package PHP.

**Download:** https://getcomposer.org/download/

**Cara cek versi:**

```bash
composer --version
```

---

#### 3️⃣ **Database: MySQL 8.0+ atau MariaDB 10.3+**

**Apa itu?** Tempat nyimpen semua data aplikasi (user, stock, transaksi, dll).

**Alternatif:** SQLite (lebih simple, cocok untuk development/testing).

**Persiapan:**

- Buat database baru (contoh: `stok_whfg_db`)
- Buat user database dengan password kuat
- Catat: host, port, username, password (nanti dipakai di `.env`)

---

#### 4️⃣ **Node.js & NPM**

**Apa itu?** Runtime JavaScript untuk build frontend assets (CSS, JS).

**Versi minimal:** Node.js 18.x

**Download:** https://nodejs.org/

**Cara cek versi:**

```bash
node -v
npm -v
```

---

#### 5️⃣ **Web Server**

**Apa itu?** Software yang "melayani" aplikasi ke internet/network.

**Pilihan:**

**A. Nginx (Recommended)**

- Lebih cepat & ringan
- Cocok untuk production
- Install: `sudo apt install nginx`

**B. Apache**

- Lebih familiar
- Butuh aktifkan `mod_rewrite`
- Install: `sudo apt install apache2`

**C. Caddy**

- Modern & auto SSL
- Config lebih simple
- Download: https://caddyserver.com/

---

## 📦 Package & Library Yang Dipakai

### Backend (PHP - via Composer)

#### **Package Utama (Wajib Ada)**

| Package                     | Versi | Fungsi                             |
| --------------------------- | ----- | ---------------------------------- |
| **laravel/framework**       | ^12.0 | Core/inti framework Laravel        |
| **laravel/tinker**          | ^2.10 | Console interaktif untuk debugging |
| **barryvdh/laravel-dompdf** | ^3.1  | Generate file PDF (laporan)        |
| **maatwebsite/excel**       | ^3.1  | Import/Export file Excel           |

**Install semua package:**

```bash
composer install --no-dev --optimize-autoloader
```

**Penjelasan Parameter:**

- `--no-dev` = Jangan install package development (testing, debugging)
- `--optimize-autoloader` = Optimasi loading class (lebih cepat)

---

#### **Package Development (Opsional - Hanya Untuk Developer)**

| Package             | Fungsi                            |
| ------------------- | --------------------------------- |
| **phpunit/phpunit** | Running automated tests           |
| **laravel/pail**    | Monitor log real-time             |
| **laravel/pint**    | Format kode otomatis              |
| **fakerphp/faker**  | Generate data dummy untuk testing |

---

### Frontend (JavaScript - via NPM)

#### **Package Yang Dipakai**

| Package                 | Versi  | Fungsi                             |
| ----------------------- | ------ | ---------------------------------- |
| **tailwindcss**         | ^4.0.0 | CSS framework untuk styling        |
| **vite**                | ^7.0.7 | Build tool modern (compile CSS/JS) |
| **laravel-vite-plugin** | ^2.0.0 | Integrasi Vite dengan Laravel      |
| **@tailwindcss/vite**   | ^4.0.0 | Plugin Tailwind untuk Vite         |

**Install & Build:**

```bash
npm install        # Install semua package
npm run build      # Compile untuk production
```

**Catatan:** File hasil build akan ada di folder `public/build/`

---

## ⚙️ Konfigurasi File `.env`

File `.env` adalah tempat simpan **setting rahasia** aplikasi (password database, API key, dll).

### 📝 Template `.env` Untuk Production

```env
# ========================================
# INFORMASI APLIKASI
# ========================================
APP_NAME="STOK WHFG"
APP_ENV=production                 # Mode: production (live) atau local (dev)
APP_KEY=base64:xxxxx               # Generate dengan: php artisan key:generate
APP_DEBUG=false                    # PENTING: false untuk production!
APP_URL=https://your-domain.com    # URL website kamu

# ========================================
# KONEKSI DATABASE (MySQL)
# ========================================
DB_CONNECTION=mysql
DB_HOST=127.0.0.1                  # IP server database (127.0.0.1 = localhost)
DB_PORT=3306                       # Port MySQL default
DB_DATABASE=stok_whfg_db           # Nama database yang sudah dibuat
DB_USERNAME=your_db_user           # Username database
DB_PASSWORD=your_secure_password   # Password database (HARUS KUAT!)

# ========================================
# SESSION & CACHE
# ========================================
SESSION_DRIVER=database            # Simpan session di database
SESSION_LIFETIME=120               # Session expire setelah 120 menit (2 jam)
CACHE_STORE=database               # Simpan cache di database

# ========================================
# QUEUE (Antrian Proses)
# ========================================
QUEUE_CONNECTION=database          # Gunakan database untuk queue

# ========================================
# EMAIL (Untuk Kirim Notifikasi)
# ========================================
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com           # SMTP server (contoh: Gmail)
MAIL_PORT=587                      # Port SMTP
MAIL_USERNAME=your-email@gmail.com
MAIL_PASSWORD=your-app-password    # Password atau App Password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@yourdomain.com
MAIL_FROM_NAME="${APP_NAME}"

# ========================================
# LOGGING
# ========================================
LOG_CHANNEL=stack
LOG_LEVEL=warning                  # Level: debug, info, warning, error
```

### 🔑 Langkah Setup `.env`

1. **Copy file example:**

    ```bash
    cp .env.example .env
    ```

2. **Edit file `.env` dengan text editor:**

    ```bash
    nano .env
    # atau
    vim .env
    ```

3. **Generate APP_KEY:**

    ```bash
    php artisan key:generate
    ```

    ✅ Command ini akan otomatis isi `APP_KEY` di `.env`

4. **Isi data database** (DB_HOST, DB_DATABASE, DB_USERNAME, DB_PASSWORD)

5. **Test koneksi:**
    ```bash
    php artisan migrate --pretend
    ```
    Jika tidak ada error, berarti koneksi database berhasil!

---

## 🚀 Panduan Deploy Step-by-Step

### 🖥️ **Opsi A: Deploy ke VPS (Ubuntu/Debian)**

#### **Langkah 1: Persiapan Server**

**Update sistem & install PHP:**

```bash
# Update package list
sudo apt update && sudo apt upgrade -y

# Install software properties
sudo apt install -y software-properties-common

# Tambah repository PHP
sudo add-apt-repository ppa:ondrej/php
sudo apt update

# Install PHP 8.2 + extensions
sudo apt install -y php8.2 php8.2-fpm php8.2-cli \
    php8.2-mysql php8.2-mbstring php8.2-xml \
    php8.2-zip php8.2-intl php8.2-gd \
    php8.2-bcmath php8.2-curl

# Cek instalasi
php -v
```

---

#### **Langkah 2: Install Composer**

```bash
# Download & install Composer
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
sudo chmod +x /usr/local/bin/composer

# Cek instalasi
composer --version
```

---

#### **Langkah 3: Install & Setup MySQL**

```bash
# Install MySQL Server
sudo apt install -y mysql-server

# Secure installation (set root password, dll)
sudo mysql_secure_installation

# Login ke MySQL
sudo mysql -u root -p

# Buat database & user
CREATE DATABASE stok_whfg_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'stok_user'@'localhost' IDENTIFIED BY 'password_kuat_123';
GRANT ALL PRIVILEGES ON stok_whfg_db.* TO 'stok_user'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

---

#### **Langkah 4: Install Node.js & NPM**

```bash
# Install Node.js 18.x
curl -fsSL https://deb.nodesource.com/setup_18.x | sudo -E bash -
sudo apt install -y nodejs

# Cek instalasi
node -v
npm -v
```

---

#### **Langkah 5: Install & Setup Nginx**

```bash
# Install Nginx
sudo apt install -y nginx

# Start & enable Nginx
sudo systemctl start nginx
sudo systemctl enable nginx

# Cek status
sudo systemctl status nginx
```

**Buat config file untuk aplikasi:**

```bash
sudo nano /etc/nginx/sites-available/stok-whfg
```

**Isi config file:**

```nginx
server {
    listen 80;
    server_name your-domain.com www.your-domain.com;
    root /var/www/stok-whfg/public;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    index index.php index.html;

    charset utf-8;

    # Handle semua request
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    # Disable log untuk favicon & robots
    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    # Handle 404
    error_page 404 /index.php;

    # PHP processing
    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_hide_header X-Powered-By;
    }

    # Deny access ke hidden files
    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

**Aktifkan config:**

```bash
# Buat symlink
sudo ln -s /etc/nginx/sites-available/stok-whfg /etc/nginx/sites-enabled/

# Test config
sudo nginx -t

# Reload Nginx
sudo systemctl reload nginx
```

---

#### **Langkah 6: Deploy Aplikasi**

**A. Clone/Upload kode aplikasi:**

```bash
# Buat folder
sudo mkdir -p /var/www/stok-whfg

# Clone dari Git (jika pakai Git)
cd /var/www
sudo git clone https://github.com/your-repo/stok-whfg.git

# Atau upload manual via FTP/SCP
```

**B. Install dependencies:**

```bash
cd /var/www/stok-whfg

# Install PHP packages
composer install --no-dev --optimize-autoloader

# Install NPM packages
npm install

# Build frontend assets
npm run build
```

**C. Setup environment & database:**

```bash
# Copy & edit .env
cp .env.example .env
nano .env   # Edit sesuai setting server

# Generate APP_KEY
php artisan key:generate

# Run migrations
php artisan migrate --force

# (Opsional) Seed data awal
php artisan db:seed
```

**D. Set permissions:**

```bash
# Set owner ke web server user
sudo chown -R www-data:www-data /var/www/stok-whfg

# Set folder permissions
sudo chmod -R 755 /var/www/stok-whfg
sudo chmod -R 775 /var/www/stok-whfg/storage
sudo chmod -R 775 /var/www/stok-whfg/bootstrap/cache
```

**E. Cache config (optimasi):**

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

---

#### **Langkah 7: Setup SSL (HTTPS)**

```bash
# Install Certbot
sudo apt install -y certbot python3-certbot-nginx

# Generate SSL certificate
sudo certbot --nginx -d your-domain.com -d www.your-domain.com

# Auto-renewal sudah aktif, cek dengan:
sudo certbot renew --dry-run
```

✅ **Selesai!** Akses aplikasi di `https://your-domain.com`

---

### 🪟 **Opsi B: Deploy ke Windows Server (IIS)**

#### **Persyaratan:**

- Windows Server 2016+ atau Windows 10/11 Pro
- IIS 10+
- PHP 8.2 (install via Web Platform Installer atau manual)
- MySQL/MariaDB for Windows
- URL Rewrite Module untuk IIS

#### **Langkah Singkat:**

1. **Install IIS via Server Manager**
2. **Install PHP** via Web Platform Installer
3. **Install MySQL** for Windows
4. **Enable PHP extensions** di `php.ini`
5. **Configure IIS** FastCGI untuk PHP
6. **Install URL Rewrite Module**
7. **Setup aplikasi**:
    - Copy files ke `C:\inetpub\wwwroot\stok-whfg`
    - Point IIS site ke folder `public`
    - Import web.config untuk URL rewrite
8. **Set folder permissions** (IIS_IUSRS harus bisa write ke `storage` & `bootstrap/cache`)
9. **Run** composer install, npm build, migrations

📖 **Referensi:** https://laravel.com/docs/12.x/deployment#server-requirements

---

## 💾 Spesifikasi Server

### **Minimal (Testing/Staging)**

| Resource  | Spec       |
| --------- | ---------- |
| CPU       | 1 Core     |
| RAM       | 1 GB       |
| Storage   | 10 GB SSD  |
| Bandwidth | 1 TB/bulan |

### **Recommended (Production)**

| Resource  | Spec             |
| --------- | ---------------- |
| CPU       | 2-4 Cores        |
| RAM       | 4-8 GB           |
| Storage   | 20-50 GB SSD     |
| Bandwidth | Unlimited        |
| OS        | Ubuntu 22.04 LTS |

**Estimasi Penggunaan:**

- ~50-100 users bersamaan: 2 Core, 4GB RAM
- ~200+ users bersamaan: 4 Core, 8GB RAM

---

## ⚡ Fitur Opsional (Meningkatkan Performa)

### **1. Redis (Caching & Queue)**

**Kenapa?** Cache & queue pakai Redis 10-100x lebih cepat dari database.

```bash
# Install Redis
```

### 2. Supervisor (untuk Queue Worker)

```bash
sudo apt install -y supervisor
```

Config: `/etc/supervisor/conf.d/stok-whfg.conf`

```ini
[program:stok-whfg-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/stok-whfg/artisan queue:work --tries=3
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/www/stok-whfg/storage/logs/worker.log
stopwaitsecs=3600
```

### 3. SSL Certificate (Let's Encrypt)

```bash
sudo apt install -y certbot python3-certbot-nginx
sudo certbot --nginx -d your-domain.com
```

---

## 🧪 Testing Stack

### Unit & Feature Testing

- **PHPUnit** 11.5.3
- **Mockery** untuk mocking
- **Faker** untuk dummy data

### Run Tests

```bash
php artisan test
```

---

## 📚 Documentation & Tools

### Development Tools

- **Laravel Pail** - Real-time log monitoring
- **Laravel Tinker** - Interactive REPL
- **Laravel Pint** - Code formatter (Laravel style)

### Monitoring (Optional)

- **Laravel Telescope** - Debugging assistant
- **Sentry** - Error tracking
- **New Relic** - Performance monitoring

---

## 🔒 Checklist Keamanan

Sebelum go-live, pastikan:

| No  | Item                                                          | Status |
| --- | ------------------------------------------------------------- | ------ |
| ✅  | `APP_DEBUG=false` di production                               | ⬜     |
| ✅  | `APP_KEY` sudah di-generate                                   | ⬜     |
| ✅  | Database password kuat (min 16 karakter, campur angka/simbol) | ⬜     |
| ✅  | SSL/HTTPS aktif (Let's Encrypt)                               | ⬜     |
| ✅  | Firewall aktif (UFW), hanya buka port 80, 443, 22             | ⬜     |
| ✅  | File permissions benar (755 folder, 644 file)                 | ⬜     |
| ✅  | `storage` & `bootstrap/cache` writable oleh web server        | ⬜     |
| ✅  | Backup database otomatis (daily/weekly)                       | ⬜     |
| ✅  | Rate limiting aktif (anti brute force)                        | ⬜     |
| ✅  | Update PHP & dependencies rutin                               | ⬜     |

---

## 🧪 Testing & Monitoring

### **Running Tests (Development)**

```bash
# Run semua tests
php artisan test

# Run specific test
php artisan test --filter=StockInputTest
```

### **Monitoring Logs**

```bash
# Real-time log monitoring
php artisan pail

# Atau manual
tail -f storage/logs/laravel.log
```

### **Tools Monitoring (Opsional)**

- **Laravel Telescope** - Debug assistant (development only!)
- **Sentry** - Error tracking & monitoring
- **New Relic** - Performance monitoring
- **Uptime Robot** - Uptime monitoring (free tier available)

---

## 📞 Troubleshooting & FAQ

### ❓ **"500 Internal Server Error" setelah deploy**

**Jawab:**

```bash
# Cek log error
tail storage/logs/laravel.log

# Biasanya karena:
# 1. Permission salah
sudo chmod -R 775 storage bootstrap/cache
sudo chown -R www-data:www-data storage bootstrap/cache

# 2. .env tidak ada atau APP_KEY kosong
cp .env.example .env
php artisan key:generate

# 3. Cache config lama
php artisan config:clear
php artisan cache:clear
```

---

### ❓ **"SQLSTATE[HY000] [2002] Connection refused"**

**Jawab:**

- Cek database running: `sudo systemctl status mysql`
- Cek credentials di `.env` (DB_HOST, DB_USERNAME, DB_PASSWORD)
- Test koneksi: `mysql -u username -p -h localhost`

---

### ❓ **Assets (CSS/JS) tidak load**

**Jawab:**

```bash
# Re-build assets
npm run build

# Cek folder public/build ada
ls -la public/build

# Clear cache browser (Ctrl+Shift+R)
```

---

### ❓ **Queue jobs tidak jalan**

**Jawab:**

```bash
# Manual run queue (testing)
php artisan queue:work

# Cek ada job pending
php artisan queue:monitor

# Pastikan Supervisor running (jika pakai)
sudo supervisorctl status
```

---

## 📚 Referensi & Link Penting

### **Dokumentasi Official**

| Tool            | Link                          |
| --------------- | ----------------------------- |
| Laravel 12      | https://laravel.com/docs/12.x |
| Tailwind CSS v4 | https://tailwindcss.com/docs  |
| Vite            | https://vitejs.dev/guide/     |
| MySQL           | https://dev.mysql.com/doc/    |

### **Package Documentation**

- Laravel Excel: https://docs.laravel-excel.com/
- DomPDF: https://github.com/barryvdh/laravel-dompdf

### **Tutorial & Community**

- Laravel News: https://laravel-news.com/
- Laracasts: https://laracasts.com/ (Video tutorials)
- StackOverflow: Tag `laravel`

---

## 📋 Command Cheatsheet

```bash
# ====== COMPOSER ======
composer install --no-dev --optimize-autoloader  # Install production
composer update                                   # Update packages

# ====== ARTISAN ======
php artisan migrate                 # Run migrations
php artisan migrate:fresh --seed    # Reset DB + seed data
php artisan db:seed                 # Seed data only
php artisan key:generate            # Generate APP_KEY
php artisan config:cache            # Cache config (production)
php artisan route:cache             # Cache routes (production)
php artisan view:cache              # Cache views (production)
php artisan cache:clear             # Clear cache
php artisan config:clear            # Clear config cache
php artisan queue:work              # Run queue worker
php artisan schedule:run            # Run scheduled tasks
php artisan storage:link            # Link storage folder

# ====== NPM ======
npm install          # Install packages
npm run build        # Build for production
npm run dev          # Dev server (hot reload)

# ====== PERMISSIONS ======
sudo chown -R www-data:www-data storage bootstrap/cache
sudo chmod -R 755 /var/www/stok-whfg
sudo chmod -R 775 storage bootstrap/cache

# ====== NGINX ======
sudo nginx -t                    # Test config
sudo systemctl reload nginx      # Reload config
sudo systemctl restart nginx     # Restart Nginx
sudo systemctl status nginx      # Check status

# ====== MYSQL ======
mysql -u root -p                           # Login as root
SHOW DATABASES;                            # List databases
USE stok_whfg_db;                          # Select database
SHOW TABLES;                               # List tables
mysqldump -u user -p db_name > backup.sql  # Backup database
```

---

## ✅ Post-Deployment Checklist

Setelah deploy, test fitur-fitur ini:

- [ ] Login/Logout berhasil
- [ ] Dashboard data tampil
- [ ] Input stock berfungsi
- [ ] Withdrawal stock berfungsi
- [ ] Export Excel/PDF berhasil
- [ ] Search & filter bekerja
- [ ] Pagination bekerja
- [ ] Email notifikasi terkirim (jika ada)
- [ ] Mobile responsive
- [ ] HTTPS aktif (gembok hijau)
- [ ] Speed test (< 3 detik)

---

**🎉 Selamat! Aplikasi STOK WHFG siap digunakan!**

---

## 📞 Support & Resources

### Official Documentation

- Laravel 12: https://laravel.com/docs/12.x
- Tailwind CSS v4: https://tailwindcss.com/docs
- Vite: https://vitejs.dev/

### Package Documentation

- Laravel Excel: https://docs.laravel-excel.com/
- DomPDF: https://github.com/barryvdh/laravel-dompdf

---

## 🎯 Quick Start Commands

```bash
# Install dependencies
composer install --no-dev --optimize-autoloader
npm install

# Build assets
npm run build

# Setup environment
cp .env.example .env
php artisan key:generate

# Run migrations
php artisan migrate --force

# Set permissions
chmod -R 755 storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache

# Clear & cache config
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

---

**Last Updated**: February 2026  
**Version**: 2.0 (Simplified & User-Friendly)  
**Maintained by**: Development Team

**Need Help?**

- 📧 Email: support@yourdomain.com
- 📖 Docs: [SYSTEM_OVERVIEW.md](SYSTEM_OVERVIEW.md)
- 🚀 Deployment: [DEPLOYMENT.md](DEPLOYMENT.md)
