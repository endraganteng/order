# Deployment Guide — Order App

Dokumentasi lengkap untuk men-deploy aplikasi Laravel Order ke VPS Debian
dengan Docker + Traefik (HTTPS otomatis) + MySQL shared + GitHub Actions
CI/CD.

> **Status code-side**: ✅ Siap deploy. File Docker, CI/CD, dan template
> environment sudah ada di repo.
>
> **Status server-side**: ⏳ Belum dikerjakan. Hermes (atau Anda) perlu
> setup VPS sesuai panduan di bawah.

---

## Arsitektur

```
Internet  ──→ Traefik (port 80/443, auto-HTTPS via Let's Encrypt)
                │
                ├─→ order.<domain> ─→ order-nginx ─→ order-app (php-fpm)
                ├─→ shop.<domain>  ─→ shop-nginx  ─→ shop-app  (kalau ada)
                └─→ ...
                
Shared services (1 instance, dipakai semua app):
   • MySQL container         → akses internal via network "db"
   • Hermes Agent            → biarkan jalan paralel, tidak diutak-atik

Per-app stack (untuk app order):
   • order-app           php-fpm 8.3, image dibangun dari Dockerfile
   • order-app-init      sekali-jalan: migrate + storage:link + cache
   • order-nginx         web server, expose ke Traefik
   • order-scheduler     `php artisan schedule:work` (8 cron)
```

---

## Prasyarat di VPS

```bash
# Sudah ter-install (asumsi state Anda sekarang):
docker --version       # Docker Engine 20.10+
docker compose version # Compose plugin v2

# Yang perlu di-install kalau belum ada:
sudo apt-get install -y git curl
```

---

## One-Time Setup

### 1. Buat Docker network bersama

Network `web` dan `db` dipakai oleh banyak app. Bikin sekali, dipakai
semua project.

```bash
docker network create web
docker network create db
```

### 2. Setup Traefik (reverse proxy + HTTPS otomatis)

```bash
mkdir -p /opt/traefik && cd /opt/traefik

cat > docker-compose.yml <<'YAML'
services:
  traefik:
    image: traefik:v3.2
    container_name: traefik
    restart: unless-stopped
    ports:
      - "80:80"
      - "443:443"
    command:
      - "--api.dashboard=false"
      - "--providers.docker=true"
      - "--providers.docker.network=web"
      - "--providers.docker.exposedbydefault=false"
      - "--entrypoints.web.address=:80"
      - "--entrypoints.websecure.address=:443"
      - "--certificatesresolvers.letsencrypt.acme.email=YOUR_EMAIL@example.com"
      - "--certificatesresolvers.letsencrypt.acme.storage=/letsencrypt/acme.json"
      - "--certificatesresolvers.letsencrypt.acme.tlschallenge=true"
      - "--log.level=INFO"
      - "--accesslog=true"
    volumes:
      - /var/run/docker.sock:/var/run/docker.sock:ro
      - traefik-acme:/letsencrypt
    networks:
      - web

volumes:
  traefik-acme:

networks:
  web:
    external: true
YAML

# GANTI YOUR_EMAIL@example.com dengan email Anda (untuk Let's Encrypt)
nano docker-compose.yml

docker compose up -d
docker compose logs -f
```

### 3. Setup MySQL shared

```bash
mkdir -p /opt/mysql && cd /opt/mysql

# Generate root password kuat & simpan di tempat aman
openssl rand -base64 32 > .root_password

cat > docker-compose.yml <<YAML
services:
  mysql:
    image: mysql:8.4
    container_name: mysql
    restart: unless-stopped
    environment:
      MYSQL_ROOT_PASSWORD: $(cat .root_password)
      MYSQL_INITDB_SKIP_TZINFO: "1"
    volumes:
      - mysql-data:/var/lib/mysql
    networks:
      - db
    command:
      - --character-set-server=utf8mb4
      - --collation-server=utf8mb4_unicode_ci
      - --max_allowed_packet=64M
    healthcheck:
      test: ["CMD", "mysqladmin", "ping", "-h", "localhost"]
      interval: 30s
      timeout: 5s
      retries: 5

volumes:
  mysql-data:

networks:
  db:
    external: true
YAML

docker compose up -d

# Buat database & user untuk app order
ROOT_PW=$(cat .root_password)
docker exec -i mysql mysql -uroot -p"$ROOT_PW" <<SQL
CREATE DATABASE order_app CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'order_app'@'%' IDENTIFIED BY 'GANTI_PASSWORD_INI';
GRANT ALL PRIVILEGES ON order_app.* TO 'order_app'@'%';
FLUSH PRIVILEGES;
SQL

# Catat password user app — akan dipakai di .env
```

### 4. Clone repo app

```bash
sudo mkdir -p /var/www
sudo chown $USER:$USER /var/www

cd /var/www
git clone https://github.com/endraganteng/order.git
cd order
```

### 5. Setup `.env` production

```bash
cp .env.docker.example .env

# Edit semua field yang kosong:
#   APP_KEY            → docker run --rm -v "$PWD:/app" -w /app php:8.3-cli php artisan key:generate --show
#   APP_DOMAIN         → domain Anda (misal order.toko-anda.com)
#   DB_PASSWORD        → password order_app dari step 3
#   FIREBASE_*         → credentials Firebase Anda
#   GEMINI_API_KEY     → kalau pakai AI chat
#   SUPABASE_*         → kalau pakai vector DB
#   FONNTE_TOKEN       → kalau pakai WhatsApp notif
#   DANA_*             → kalau pakai DANA payment
#   ADMIN_PASSWORD     → password admin panel
nano .env
```

### 6. Upload Firebase credentials JSON

File `firebase-credentials.json` adalah **secret**, JANGAN commit ke git.
Upload manual ke VPS:

```bash
mkdir -p /var/www/order/secrets
# Lalu transfer file dari laptop ke /var/www/order/secrets/firebase-credentials.json
# Pakai scp / sftp:
#   scp firebase-credentials.json user@vps:/var/www/order/secrets/
chmod 600 /var/www/order/secrets/firebase-credentials.json
```

### 7. DNS

Di registrar domain Anda (Cloudflare, Namecheap, dll):

```
A   order.toko-anda.com     → IP_VPS
```

Tunggu DNS propagation (biasanya 1-30 menit).

### 8. First deploy

```bash
cd /var/www/order
docker compose build
docker compose up -d

# Cek logs
docker compose logs -f app-init   # harus exit 0
docker compose logs -f app
docker compose logs -f nginx

# Cek Traefik route udah aktif & cert sudah issued (1-2 menit)
docker logs traefik | grep -i order
```

Kalau sukses: buka `https://order.toko-anda.com` di browser.

---

## Setup CI/CD (GitHub Actions auto-deploy)

### 1. Generate SSH key untuk deploy

Di VPS:

```bash
# Buat user khusus untuk deploy (jangan pakai root)
sudo adduser --disabled-password --gecos "" deploy
sudo usermod -aG docker deploy

# Generate key di laptop / lokal Anda:
ssh-keygen -t ed25519 -f ~/.ssh/order_deploy -C "github-actions-deploy"

# Copy public key ke VPS:
ssh-copy-id -i ~/.ssh/order_deploy.pub deploy@VPS_IP

# Test:
ssh -i ~/.ssh/order_deploy deploy@VPS_IP "docker ps"
```

Lalu set ownership /var/www/order ke `deploy`:

```bash
sudo chown -R deploy:deploy /var/www/order
```

### 2. Daftarkan secrets di GitHub

Buka: `https://github.com/endraganteng/order/settings/secrets/actions`

Klik "New repository secret" untuk masing-masing:

| Nama | Isi |
|---|---|
| `SSH_HOST` | IP atau hostname VPS |
| `SSH_USER` | `deploy` |
| `SSH_PORT` | `22` (atau port custom) |
| `SSH_PRIVATE_KEY` | Isi `~/.ssh/order_deploy` (private key, bukan `.pub`) |

Opsional, set sebagai **Variables** (bukan Secrets):

| Nama | Isi |
|---|---|
| `DEPLOY_PATH` | `/var/www/order` |

### 3. Test pipeline

```bash
# Di lokal Anda
git commit --allow-empty -m "test ci/cd"
git push origin main
```

Cek tab Actions di GitHub: workflow `Deploy to VPS` harus jalan & sukses.

---

## Operasional

### Lihat logs

```bash
cd /var/www/order
docker compose logs -f app       # PHP app
docker compose logs -f nginx     # Web server
docker compose logs -f scheduler # Cron
docker compose logs --tail=200   # Semua, 200 baris terakhir
```

### Restart manual

```bash
docker compose restart app
docker compose down && docker compose up -d
```

### Buka shell di container app (misal untuk run artisan)

```bash
docker compose exec app sh
# Di dalam container:
php artisan tinker
php artisan bonus:rack-recheck-backfill --dry-run
```

### Backup database

```bash
ROOT_PW=$(cat /opt/mysql/.root_password)
docker exec mysql mysqldump -uroot -p"$ROOT_PW" order_app \
    | gzip > /backup/order_app_$(date +%Y%m%d).sql.gz
```

Sebaiknya jadikan cron:

```bash
# /etc/cron.d/mysql-backup
0 2 * * * root /usr/local/bin/backup-mysql.sh
```

### Restore database

```bash
ROOT_PW=$(cat /opt/mysql/.root_password)
zcat order_app_20260523.sql.gz | docker exec -i mysql mysql -uroot -p"$ROOT_PW" order_app
```

### Rollback ke commit sebelumnya

```bash
cd /var/www/order
git log --oneline -10              # cari SHA commit sebelumnya
git reset --hard <SHA_LAMA>
docker compose build app
docker compose up -d
```

### Cek health

```bash
# Traefik routing OK?
curl -I https://order.toko-anda.com

# App container healthy?
docker ps --filter name=order- --format "table {{.Names}}\t{{.Status}}"

# DB connection OK?
docker compose exec app php artisan migrate:status
```

---

## Troubleshooting

### "503 Service Unavailable" dari Traefik

Container app belum siap atau crash. Cek:
```bash
docker compose logs app
docker compose ps
```

### Cert HTTPS error

Let's Encrypt rate limit 50/week. Kalau Anda banyak retry, tunggu 1 jam.
Atau pakai staging server untuk test:
```yaml
# Tambah di traefik command:
- "--certificatesresolvers.letsencrypt.acme.caserver=https://acme-staging-v02.api.letsencrypt.org/directory"
```

### Migrate gagal di app-init

```bash
docker compose run --rm app php artisan migrate --force --pretend
docker compose run --rm app php artisan migrate --force
```

### Schedule tidak jalan

```bash
docker compose logs scheduler --tail 100
# Pastikan `schedule:work` running, dan `routes/console.php` Anda terload.
```

### Port 80/443 sudah dipakai

Kemungkinan ada nginx/apache di host. Stop dulu:
```bash
sudo systemctl stop apache2 nginx
sudo systemctl disable apache2 nginx
```

---

## Tambah app baru di VPS yang sama

Pattern modular untuk tambah app Laravel kedua:

1. Clone repo app baru ke `/var/www/<nama-app>`
2. Pakai `docker-compose.yml` yang serupa, ganti:
   - `name: order` → `name: <nama-app>`
   - `container_name: order-*` → `<nama-app>-*`
   - Volume names `order_*` → `<nama-app>_*`
   - Traefik labels `order` → `<nama-app>` + domain berbeda
3. Buat database baru di MySQL:
   ```sql
   CREATE DATABASE <nama-app>;
   CREATE USER '<nama-app>'@'%' IDENTIFIED BY '...';
   GRANT ALL ON <nama-app>.* TO '<nama-app>'@'%';
   ```
4. `docker compose up -d`

Traefik akan otomatis routing domain baru, MySQL shared dipakai, no
konflik dengan app order.

---

## Untuk Hermes / setup automation

Skrip yang bisa dipakai Hermes untuk fully automate setup VPS:

```bash
# 1. Network
docker network create web 2>/dev/null || true
docker network create db 2>/dev/null || true

# 2. Traefik (template `/opt/traefik/docker-compose.yml` di atas)
# 3. MySQL (template `/opt/mysql/docker-compose.yml` di atas)
# 4. Clone repo + create deploy user
# 5. Setup GitHub secrets via gh CLI:
gh secret set SSH_HOST --body "$VPS_IP" --repo endraganteng/order
gh secret set SSH_USER --body "deploy" --repo endraganteng/order
gh secret set SSH_PORT --body "22" --repo endraganteng/order
gh secret set SSH_PRIVATE_KEY --body "$(cat order_deploy)" --repo endraganteng/order
```

---

## Migration SQLite → MySQL (kalau ada data lokal yang mau dipindah)

```bash
# 1. Export dari SQLite (di lokal):
sqlite3 database/database.sqlite .dump > sqlite_dump.sql

# 2. Convert SQLite SQL → MySQL SQL (manual / pakai tool):
#    - Ganti tipe data SQLite-specific
#    - Gampangnya: pakai tool seperti `sqlite3-to-mysql` (Python)
pip install sqlite3-to-mysql
sqlite3mysql -f database/database.sqlite \
    -d order_app -u order_app --mysql-password='...' -h VPS_IP

# 3. Verify:
docker compose exec app php artisan migrate:status
```

> **Note**: Karena database utama Anda Firebase, SQLite Anda kemungkinan
> hampir kosong (cuma session/cache table Laravel). Migrasi sederhana:
> tinggal `php artisan migrate --force` di MySQL kosong, tidak perlu
> import data lama.
