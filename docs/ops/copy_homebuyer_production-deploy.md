# Production Deploy Walkthrough — Fresh Linux Server

End-to-end guide for deploying **bb-homebuyer-lite** to a fresh Ubuntu server with Nginx, PostgreSQL, and Cloudflare-fronted SSL.

> First successfully executed on: Ubuntu 26.04 LTS (`resolute`), 2026-05-19.
> Adjust commands for the Ubuntu release you're on — gotchas noted inline.

---

## Target Architecture

```
[ Browser ]  ←HTTPS→  [ Cloudflare edge ]  ←HTTPS (Origin Cert)→  [ Nginx :443 ]
                                                                       │
                                                                       ▼
                                                                  [ PHP-FPM ]
                                                                       │
                                                                       ▼
                                                                  [ Laravel app ]
                                                                  /var/www/bb-homebuyer-lite
                                                                       │
                                            ┌──────────────────────────┼──────────────────────────┐
                                            ▼                          ▼                          ▼
                                       [ PostgreSQL ]            [ queue worker ]           [ scheduler ]
                                      (local, port 5432)       (bb-queue systemd)        (linuxuser crontab)
```

**Stack installed**
| Layer | Version | Notes |
|---|---|---|
| OS | Ubuntu 26.04 LTS | |
| PHP | 8.5 (Ubuntu default) | `php8.5-fpm`, socket `/var/run/php/php8.5-fpm.sock` |
| Composer | 2.x | from getcomposer.org installer |
| Node | 22 LTS (Ubuntu default) | for Vite build only — not runtime |
| PostgreSQL | 18 (Ubuntu default) | local, scram-sha-256 auth |
| Nginx | 1.28+ | |
| SSL | Cloudflare Origin Certificate (15-year) | CF SSL mode: **Full (strict)** |

---

## Prerequisites

Before touching the server:

- [ ] SSH access to a fresh Ubuntu LTS instance (sudo user)
- [ ] Domain or subdomain DNS record pointing to the server IP
- [ ] Cloudflare account access for the zone (or someone who can generate an Origin Cert for you)
- [ ] Strong PostgreSQL password chosen and saved
- [ ] Mail provider credentials ready (SMTP, Mailtrap, SES, etc.)

---

## Step 1 — Initial Server Setup

```bash
cloud-init status --wait                 # let provisioning finish
sudo apt update && sudo apt upgrade -y   # accept defaults on any prompts
```

**Firewall** — open SSH, HTTP, HTTPS only:

```bash
sudo ufw allow OpenSSH
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp
sudo ufw --force enable
sudo ufw status
```

**Validation**: `ufw status` shows `Status: active` with the three rules.

---

## Step 2 — Install PHP

```bash
sudo apt install -y \
  php8.5-fpm php8.5-cli php8.5-pgsql php8.5-mbstring \
  php8.5-xml php8.5-curl php8.5-zip php8.5-bcmath \
  php8.5-intl php8.5-gd
```

**Gotcha**: on Ubuntu 26.04 (`resolute`), the `ppa:ondrej/php` PPA is unsupported (returns 404). Use Ubuntu's native PHP — 26.04 ships PHP 8.5 which satisfies Laravel 11's `^8.2`. On older Ubuntu (22.04 / 24.04) the PPA works for PHP 8.3.

**Validation**:
```bash
php -v                                       # >= 8.2
sudo systemctl is-active php8.5-fpm          # active
```

---

## Step 3 — Install Composer

```bash
cd ~
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
composer -V
```

**Validation**: `composer -V` prints `Composer version 2.x.x`.

---

## Step 4 — Install Node + npm

```bash
sudo apt install -y nodejs npm
node -v
npm -v
```

**Note**: Ubuntu's native `nodejs` package satisfies Vite 6 + the project's `^20` target. Skip NodeSource unless you specifically need a different Node major.

**Gotcha**: Ubuntu splits `nodejs` and `npm` into separate packages — install both.

---

## Step 5 — Install PostgreSQL

```bash
sudo apt install -y postgresql postgresql-contrib
psql --version
sudo systemctl is-active postgresql
```

**Gotcha**: the PGDG repo (`apt.postgresql.org`) requires a matching Ubuntu codename. On `resolute` (26.04), `noble-pgdg` packages fail with `libicu74` / `libxml2` dependency mismatches. Stick with Ubuntu's default Postgres (PG 18 on 26.04, PG 16 on 24.04) unless you have a hard version requirement.

**Validation**: `psql --version` prints `psql (PostgreSQL) 18.x` (or 16/17 on older Ubuntu).

---

## Step 6 — Install Nginx, Certbot, Git, ACL

```bash
sudo apt install -y nginx certbot python3-certbot-nginx git unzip acl
nginx -v
```

> `certbot` is included for fallback — not used when fronted by Cloudflare Origin Cert, but useful if you ever go DNS-only.

---

## Step 7 — Create Postgres Database + User

```bash
sudo -u postgres psql <<EOF
CREATE USER bb_user WITH PASSWORD 'CHANGE_ME_STRONG_PASSWORD';
CREATE DATABASE bb_homebuyer_lite OWNER bb_user ENCODING 'UTF8';
GRANT ALL PRIVILEGES ON DATABASE bb_homebuyer_lite TO bb_user;
\c bb_homebuyer_lite
GRANT ALL ON SCHEMA public TO bb_user;
EOF
```

**Gotcha**: PostgreSQL 15+ revoked default `CREATE` privilege on the `public` schema. Without the `GRANT ALL ON SCHEMA public`, Laravel migrations fail with `permission denied for schema public`.

**Validation** — connect from the shell as `bb_user`:

```bash
PGPASSWORD='CHANGE_ME_STRONG_PASSWORD' \
  psql -h 127.0.0.1 -U bb_user -d bb_homebuyer_lite -c '\dt'
```

Expected: `Did not find any relations.`

---

## Step 8 — Clone Repo + Install Dependencies

```bash
sudo mkdir -p /var/www
sudo chown $(whoami):$(whoami) /var/www
cd /var/www
git clone https://github.com/reancirl/bb-homebuyer-lite-clone.git bb-homebuyer-lite
cd bb-homebuyer-lite

composer install --no-dev --optimize-autoloader --no-interaction
npm ci
npm run build
```

**Validation**:
```bash
ls public/build/manifest.json bootstrap/ssr/ssr.js   # both should exist
```

**Memory budget**: `npm run build` peaks ~1 GB RAM. On <1 GB instances either add swap or build locally and rsync `public/build` + `bootstrap/ssr`.

---

## Step 9 — Configure `.env`

```bash
cd /var/www/bb-homebuyer-lite
cp .env.example .env
```

Overwrite with production values (replace `CHANGE_ME` placeholders):

```bash
sudo tee .env > /dev/null <<'EOF'
APP_NAME="BB Homebuyer Lite"
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_TIMEZONE=America/New_York
APP_URL=https://bb-lite.buffalobuiltusa.com

APP_LOCALE=en
APP_FALLBACK_LOCALE=en
APP_FAKER_LOCALE=en_US

APP_MAINTENANCE_DRIVER=file
PHP_CLI_SERVER_WORKERS=4
BCRYPT_ROUNDS=12

LOG_CHANNEL=stack
LOG_STACK=single
LOG_DEPRECATIONS_CHANNEL=null
LOG_LEVEL=warning

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=bb_homebuyer_lite
DB_USERNAME=bb_user
DB_PASSWORD=CHANGE_ME_STRONG_PASSWORD

SESSION_DRIVER=database
SESSION_LIFETIME=120
SESSION_ENCRYPT=false
SESSION_PATH=/
SESSION_DOMAIN=null

BROADCAST_CONNECTION=log
FILESYSTEM_DISK=local
QUEUE_CONNECTION=database

CACHE_STORE=database
CACHE_PREFIX=

MAIL_MAILER=smtp
MAIL_SCHEME=null
MAIL_HOST=sandbox.smtp.mailtrap.io
MAIL_PORT=2525
MAIL_USERNAME=CHANGE_ME
MAIL_PASSWORD=CHANGE_ME
MAIL_FROM_ADDRESS="no-reply@bb-lite.buffalobuiltusa.com"
MAIL_FROM_NAME="${APP_NAME}"

VITE_APP_NAME="${APP_NAME}"
EOF
```

Then generate the key (fills in `APP_KEY=`) and run migrations:

```bash
php artisan key:generate
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
```

**Validation**:
```bash
php artisan about | head -20
# Environment: production, Debug Mode: OFF, Database: pgsql
```

---

## Step 10 — File Permissions

Nginx/PHP-FPM run as `www-data`. The app files belong to your deploy user. Use ACLs so both can work without world-writable directories.

```bash
cd /var/www/bb-homebuyer-lite
sudo chown -R $(whoami):www-data .
sudo chmod -R u=rwX,g=rX,o= .
sudo chmod -R ug+rwx storage bootstrap/cache
sudo setfacl -R  -m u:www-data:rwx storage bootstrap/cache
sudo setfacl -dR -m u:www-data:rwx storage bootstrap/cache
sudo chmod 640 .env
```

**Why `X` (capital)**: applies execute bit only to directories or files that already have it — single-pass, fast. Lowercase `x` would make every file executable, which is wrong.

**Validation**:
```bash
sudo -u www-data touch storage/logs/permcheck && \
  sudo -u www-data rm storage/logs/permcheck && \
  echo OK
```

---

## Step 11 — Nginx Server Block (HTTP-only, pre-cert)

```bash
sudo tee /etc/nginx/sites-available/bb-homebuyer-lite > /dev/null <<'EOF'
server {
    listen 80;
    listen [::]:80;
    server_name bb-lite.buffalobuiltusa.com;

    root /var/www/bb-homebuyer-lite/public;
    index index.php;

    charset utf-8;
    client_max_body_size 25M;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    location / { try_files $uri $uri/ /index.php?$query_string; }
    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }
    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.5-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_read_timeout 120;
    }

    location ~ /\.(?!well-known).* { deny all; }
}
EOF

sudo ln -sf /etc/nginx/sites-available/bb-homebuyer-lite /etc/nginx/sites-enabled/
sudo rm -f /etc/nginx/sites-enabled/default
sudo nginx -t && sudo systemctl reload nginx
```

**Validation** — hit the app via the origin (bypassing Cloudflare):

```bash
curl -I http://127.0.0.1 -H "Host: bb-lite.buffalobuiltusa.com"
# HTTP/1.1 200 OK   ← expected
```

---

## Step 12 — Queue Worker (systemd)

```bash
sudo tee /etc/systemd/system/bb-queue.service > /dev/null <<EOF
[Unit]
Description=BB Homebuyer Queue Worker
After=network.target postgresql.service

[Service]
User=$(whoami)
Group=www-data
Restart=always
RestartSec=5
WorkingDirectory=/var/www/bb-homebuyer-lite
ExecStart=/usr/bin/php artisan queue:work --sleep=3 --tries=3 --max-time=3600
StandardOutput=append:/var/log/bb-queue.log
StandardError=append:/var/log/bb-queue.log

[Install]
WantedBy=multi-user.target
EOF

sudo touch /var/log/bb-queue.log
sudo chown $(whoami):www-data /var/log/bb-queue.log
sudo systemctl daemon-reload
sudo systemctl enable --now bb-queue
sudo systemctl status bb-queue --no-pager
```

**Validation**: `active (running)` and a `Main PID`.

---

## Step 13 — Scheduler Cron

```bash
( sudo crontab -u $(whoami) -l 2>/dev/null; \
  echo "* * * * * cd /var/www/bb-homebuyer-lite && /usr/bin/php artisan schedule:run >> /dev/null 2>&1" \
) | sudo crontab -u $(whoami) -
```

**Validation**:
```bash
sudo crontab -u $(whoami) -l
```

---

## Step 14 — Cloudflare Origin Certificate + HTTPS

### A. Generate the cert (Cloudflare dashboard)

1. Zone `buffalobuiltusa.com` → **SSL/TLS → Origin Server → Create Certificate**
2. Key type: **RSA (2048)**, hostname: `bb-lite.buffalobuiltusa.com`, validity: 15 years
3. Copy **both** the certificate and the private key (key shown only once)

### B. Install on the server

```bash
sudo mkdir -p /etc/nginx/ssl && sudo chmod 700 /etc/nginx/ssl

# Paste cert
sudo nano /etc/nginx/ssl/bb-lite.buffalobuiltusa.com.pem

# Paste private key
sudo nano /etc/nginx/ssl/bb-lite.buffalobuiltusa.com.key

# Lock down
sudo chmod 600 /etc/nginx/ssl/bb-lite.buffalobuiltusa.com.key
sudo chown root:root /etc/nginx/ssl/bb-lite.buffalobuiltusa.com.*
```

### C. Replace Nginx config with HTTPS version

```bash
sudo tee /etc/nginx/sites-available/bb-homebuyer-lite > /dev/null <<'EOF'
server {
    listen 80;
    listen [::]:80;
    server_name bb-lite.buffalobuiltusa.com;
    return 301 https://$host$request_uri;
}

server {
    listen 443 ssl;
    listen [::]:443 ssl;
    http2 on;
    server_name bb-lite.buffalobuiltusa.com;

    ssl_certificate     /etc/nginx/ssl/bb-lite.buffalobuiltusa.com.pem;
    ssl_certificate_key /etc/nginx/ssl/bb-lite.buffalobuiltusa.com.key;
    ssl_protocols       TLSv1.2 TLSv1.3;
    ssl_ciphers         HIGH:!aNULL:!MD5;

    root /var/www/bb-homebuyer-lite/public;
    index index.php;
    charset utf-8;
    client_max_body_size 25M;

    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header Strict-Transport-Security "max-age=15552000" always;

    location / { try_files $uri $uri/ /index.php?$query_string; }
    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }
    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.5-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        fastcgi_param HTTPS on;
        include fastcgi_params;
        fastcgi_read_timeout 120;
    }

    location ~ /\.(?!well-known).* { deny all; }
}
EOF

sudo nginx -t && sudo systemctl reload nginx
```

### D. Switch Cloudflare SSL mode

Cloudflare dashboard → **SSL/TLS → Overview → Full (strict)**.

### E. Validate

```bash
curl -I https://bb-lite.buffalobuiltusa.com   # HTTP/2 200 (or 302)
```

Open in browser — should show valid HTTPS lock.

---

## Step 15 — Final Smoke Test

```bash
sudo systemctl is-active nginx php8.5-fpm postgresql bb-queue cron
curl -sI https://bb-lite.buffalobuiltusa.com | head -1
tail -n 20 /var/log/bb-queue.log
tail -n 20 /var/www/bb-homebuyer-lite/storage/logs/laravel.log
```

All services `active`, app returns 200, no Laravel errors.

---

## Operations: Future Deploys

Save this script as `~/deploy.sh` on the server:

```bash
cat > ~/deploy.sh <<'EOF'
#!/usr/bin/env bash
set -e
cd /var/www/bb-homebuyer-lite
php artisan down --render="errors::503" || true
git pull origin main
composer install --no-dev --optimize-autoloader --no-interaction
npm ci
npm run build
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
sudo systemctl restart php8.5-fpm bb-queue
php artisan up
echo "Deploy done."
EOF
chmod +x ~/deploy.sh
```

For each release: `~/deploy.sh`.

---

## Troubleshooting Cheatsheet

| Symptom | Cause | Fix |
|---|---|---|
| `Err: ondrej/php ... 404` on `apt update` | Old PPA for an Ubuntu release ondrej doesn't ship | `sudo rm /etc/apt/sources.list.d/ondrej-ubuntu-php-*` then `sudo apt update` |
| `Unable to locate package php8.X-fpm` | Wrong PHP version for this Ubuntu | Use `apt-cache search '^php8\.'` to see what's shipped, install that version |
| `Depends: libicu74 ... not installable` on PG install | PGDG repo codename mismatch | Drop PGDG, use Ubuntu's native postgresql package |
| Cloudflare returns **521 Web Server is Down** | CF in Full/Full-strict but origin not listening on 443 | Either install Origin Cert (Step 14) or temporarily set CF to Flexible |
| Cloudflare returns **525 SSL Handshake Failed** | Origin cert doesn't match what CF expects | Re-issue Origin Cert, ensure hostname matches |
| Migration error: `permission denied for schema public` | PG 15+ default behavior | `GRANT ALL ON SCHEMA public TO bb_user` (already in Step 7) |
| `npm run build` killed | OOM on small instance | Add swap (`fallocate -l 2G /swapfile; mkswap; swapon`) or build locally and rsync |
| Queue jobs never run | `bb-queue.service` down | `sudo systemctl status bb-queue` — restart with `sudo systemctl restart bb-queue` |
| App shows stale config after `.env` change | Cached config | `php artisan config:cache` (cache is required in prod; recache after every change) |
| `php artisan ... Permission denied: storage/logs` | ACLs not applied or got reset | Re-run the `setfacl` commands from Step 10 |

---

## Hardening Checklist (post-deploy)

- [ ] Rotate any credentials shared during setup (Mailtrap, DB password if leaked in chat/screenshots)
- [ ] Set up offsite Postgres backups (`pg_dump` cron → S3/B2)
- [ ] Set up log rotation for `/var/log/bb-queue.log` (logrotate config)
- [ ] Add monitoring (UptimeRobot, Better Stack, or similar) on `https://bb-lite.buffalobuiltusa.com`
- [ ] Configure Cloudflare WAF / rate limit rules
- [ ] Disable password SSH auth (key-only): `sudo nano /etc/ssh/sshd_config` → `PasswordAuthentication no`
- [ ] Set up `unattended-upgrades` for security patches: `sudo apt install unattended-upgrades`

---

## File / Service Reference

| Thing | Path |
|---|---|
| App root | `/var/www/bb-homebuyer-lite` |
| Nginx site config | `/etc/nginx/sites-available/bb-homebuyer-lite` |
| SSL cert + key | `/etc/nginx/ssl/bb-lite.buffalobuiltusa.com.{pem,key}` |
| PHP-FPM socket | `/var/run/php/php8.5-fpm.sock` |
| Queue worker unit | `/etc/systemd/system/bb-queue.service` |
| Queue worker log | `/var/log/bb-queue.log` |
| Laravel app log | `/var/www/bb-homebuyer-lite/storage/logs/laravel.log` |
| Nginx access log | `/var/log/nginx/access.log` |
| Nginx error log | `/var/log/nginx/error.log` |
| Postgres data dir | `/var/lib/postgresql/18/main/` |
| Postgres config | `/etc/postgresql/18/main/postgresql.conf` |

| Service | Manage with |
|---|---|
| Nginx | `sudo systemctl {start,stop,reload,restart,status} nginx` |
| PHP-FPM | `sudo systemctl restart php8.5-fpm` |
| PostgreSQL | `sudo systemctl restart postgresql` |
| Queue worker | `sudo systemctl restart bb-queue` |
| Scheduler | `sudo systemctl restart cron` |
