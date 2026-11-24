#!/usr/bin/env bash
# Run on the Docker HOST, from the production repository. Read-only diagnostics.
# Usage: bash scripts/production-preflight.sh [application-container]
set -eu

app_container="${1:-fifowhfg_app}"
script_dir="$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)"
repo_dir="${REPO_DIR:-$PWD}"

section() { printf '\n## %s\n' "$1"; }
command -v docker >/dev/null 2>&1 || {
    printf 'STOP: jalankan di HOST Docker, bukan di dalam container.\n' >&2
    exit 1
}
test -f "$script_dir/production-preflight.php" || {
    printf 'STOP: production-preflight.php harus tersedia bersama skrip ini.\n' >&2
    exit 1
}
running="$(docker inspect "$app_container" --format '{{.State.Running}}')" || exit 1
if test "$running" != true; then
    printf 'STOP: container aplikasi harus sedang berjalan untuk pemeriksaan ini.\n' >&2
    exit 1
fi

section 'Waktu dan OS host'
date -u '+%Y-%m-%dT%H:%M:%SZ'
uname -srm
if test -r /etc/os-release; then
    awk -F= '$1 == "PRETTY_NAME" {print $0}' /etc/os-release
fi
section 'Versi Docker/Compose dan ruang filesystem checkout host'
docker version --format 'client={{.Client.Version}} server={{.Server.Version}}'
docker compose version 2>/dev/null || true
df -h "$repo_dir" || true
section 'Checkout host (tidak membuktikan versi source di image)'
if command -v git >/dev/null 2>&1; then
    git -C "$repo_dir" rev-parse HEAD || true
    git -C "$repo_dir" status --short || true
fi
section 'Container dan image (tanpa environment/secret)'
docker ps --format 'table {{.Names}}\t{{.Image}}\t{{.Status}}'
docker inspect "$app_container" --format 'image={{.Config.Image}} image_id={{.Image}} workdir={{.Config.WorkingDir}} user={{.Config.User}} compose_service={{index .Config.Labels "com.docker.compose.service"}}'
section 'Mount aplikasi (cek source, storage dan konfigurasi persistent)'
docker inspect "$app_container" --format '{{range .Mounts}}{{println .Type .Source "->" .Destination "rw=" .RW}}{{end}}'
section 'Nama proses aplikasi (tanpa argumen yang mungkin mengandung secret)'
docker top "$app_container" -eo pid,comm || true
section 'Petunjuk scheduler/worker host, perlu konfirmasi pengelola'
if command -v crontab >/dev/null 2>&1; then
    # Never print crontab contents: command lines can contain credentials.
    crontab -l 2>/dev/null | awk '/artisan.*schedule:(run|work)/ {n++} END {print "User cron schedule entries:", n+0}' || true
fi
if command -v systemctl >/dev/null 2>&1; then
    systemctl list-units --type=service --state=running --no-legend --plain 2>/dev/null |
        awk 'tolower($1) ~ /(supervisor|cron|queue|laravel|horizon)/ {print $1, $3, $4}' || true
fi
section 'PHP aplikasi'
docker exec "$app_container" php -v
docker exec "$app_container" php -m
section 'Tool build di host (boleh tidak terpasang jika build di CI/image)'
if command -v node >/dev/null 2>&1; then node --version; fi
if command -v npm >/dev/null 2>&1; then npm --version; fi
section 'Composer di aplikasi (boleh tidak terpasang jika vendor dari build)'
docker exec -e COMPOSER_DISABLE_NETWORK=1 "$app_container" composer --version --no-ansi 2>/dev/null || true

app_dir="${APP_DIR:-$(docker inspect "$app_container" --format '{{.Config.WorkingDir}}')}"
if test -z "$app_dir"; then
    printf 'STOP: WorkingDir kosong. Ulangi dengan APP_DIR=/path/project-di-container.\n' >&2
    exit 1
fi
section 'Konfigurasi aman, migrasi dan ringkasan database (SELECT saja)'
docker exec -i -w "$app_dir" "$app_container" php < "$script_dir/production-preflight.php"
section 'Informasi yang tetap harus dijawab pengelola'
printf '%s\n' \
    '1. Lokasi Dockerfile/Compose dan cara update image atau bind mount?' \
    '2. Ada instance aplikasi lain, worker/scheduler di container/user/host lain?' \
    '3. Kapan backup terakhir, tersimpan di mana, dan kapan berhasil diuji restore?' \
    '4. Tersedia database terpisah dengan engine/versi/collation production untuk rehearsal?' \
    '5. Siapa PIC deploy/restore dan kapan window maintenance yang diizinkan?' \
    '6. Periksa laporan sebelum dibagikan; jangan sertakan .env, password, token atau dump data.'
