# Production Deploy Walkthrough — Second App on an Existing Server

End-to-end guide for deploying **bb-internal-clone** to the **same Ubuntu server already running bb-homebuyer-lite**, sharing Nginx, PHP-FPM, and PostgreSQL but with a fully isolated database, app directory, queue worker, and SSL cert.

> Pairs with `copy_homebuyer_production-deploy.md` (the fresh-server install). Use this doc when the stack is already installed and you are adding a second tenant.

---

## Target Architecture

```
[ Browser ]  ←HTTPS→  [ Cloudflare edge ]  ←HTTPS (Origin Cert)→  [ Nginx :443 ]
                                                                       │
                                              ┌────────────────────────┼────────────────────────┐
                                              ▼                        ▼                        ▼
                                       bb-lite vhost          bb-internal vhost           (other vhosts)
                                              │                        │
                                              ▼                        ▼
                                       [ PHP-FPM 8.5 socket — shared ]
                                              │                        │
                                              ▼                        ▼
                                       /var/www/                /var/www/
                                       bb-homebuyer-lite        bb-internal
                                              │                        │
                                              ▼                        ▼
                                       DB: bb_homebuyer_lite    DB: bb_internal
                                       Queue: bb-queue          Queue: bb-internal-queue
                                       Cron: HomeBuyer schedule Cron: Internal schedule
```

**Shared with HomeBuyer (no changes needed)**
- PHP 8.5 + FPM (`/var/run/php/php8.5-fpm.sock`)
- Composer, Node 22, npm
- PostgreSQL 18 instance (different DB inside it)
- Nginx 1.28+ (different server block)
- `ufw` firewall rules

**Isolated per-app**
| Resource | HomeBuyer | bb-internal |
|---|---|---|
| App dir | `/var/www/bb-homebuyer-lite` | `/var/www/bb-internal` |
| Repo | `bb-homebuyer-lite-clone` | `bb-internal-clone` |
| Public hostname | `bb-lite.buffalobuiltusa.com` | `internal.buffalobuiltusa.com` |
| Postgres DB | `bb_homebuyer_lite` | `bb_internal` |
| Postgres user | `bb_user` | `bb_internal_user` |
| Nginx site | `/etc/nginx/sites-available/bb-homebuyer-lite` | `/etc/nginx/sites-available/bb-internal` |
| SSL cert | `/etc/nginx/ssl/bb-lite.buffalobuiltusa.com.*` | `/etc/nginx/ssl/internal.buffalobuiltusa.com.*` |
| Queue systemd unit | `bb-queue.service` | `bb-internal-queue.service` |
| Queue log | `/var/log/bb-queue.log` | `/var/log/bb-internal-queue.log` |
| Scheduler cron line | existing | append (do **not** overwrite) |

> Same naming pattern means future apps follow this table: bump the slug only.

---

## Prerequisites

- [ ] SSH access to the existing server as the same sudo user that owns `/var/www/bb-homebuyer-lite`
- [ ] DNS `A` record for `internal.buffalobuiltusa.com` → server IP, proxied through Cloudflare
- [ ] Cloudflare access to issue an Origin Cert for `internal.buffalobuiltusa.com`
- [ ] Strong Postgres password chosen for `bb_internal_user`
- [ ] Mail provider credentials ready (or reuse HomeBuyer's Mailtrap creds — safe, separate sender domain)

---

## Step 1 — Verify the Existing Stack

Should all already be installed. If any check fails, run the matching step from `copy_homebuyer_production-deploy.md`.

```bash
php -v                                    # >= 8.2 (expect 8.5 on Ubuntu 26.04)
composer -V                               # 2.x
node -v && npm -v                         # node >= 20
psql --version                            # 18.x
nginx -v                                  # 1.28+
sudo systemctl is-active nginx php8.5-fpm postgresql cron
sudo ufw status                           # active, with OpenSSH/80/443
```

**Validation**: every check returns a version and every service is `active`.

---

## Step 2 — Create the Postgres Database + User

Run from any directory:

```bash
sudo -u postgres psql <<EOF
CREATE USER bb_internal_user WITH PASSWORD 'CHANGE_ME_STRONG_PASSWORD';
CREATE DATABASE bb_internal OWNER bb_internal_user ENCODING 'UTF8';
GRANT ALL PRIVILEGES ON DATABASE bb_internal TO bb_internal_user;
\c bb_internal
GRANT ALL ON SCHEMA public TO bb_internal_user;
EOF
```

**Why two grants**: PG 15+ revoked `CREATE` on the `public` schema by default. Without the second grant, Laravel migrations fail with `permission denied for schema public`.

**Validation**:

```bash
PGPASSWORD='CHANGE_ME_STRONG_PASSWORD' \
  psql -h 127.0.0.1 -U bb_internal_user -d bb_internal -c '\dt'
# expected: Did not find any relations.
```

---

## Step 3 — Clone Repo + Install Dependencies

```bash
cd /var/www
git clone https://github.com/reancirl/bb-internal-clone.git bb-internal
cd bb-internal

composer install --no-dev --optimize-autoloader --no-interaction
npm ci
npm run build
```

**Validation**:

```bash
ls public/build/manifest.json     # exists
```

**Memory note**: Vite peaks ~1 GB during `npm run build`. If the box is tight (HomeBuyer + system already using RAM), either add swap or build locally and `rsync public/build/` to the server.

```bash
# add swap (one-time, only if OOM-killed)
sudo fallocate -l 2G /swapfile && sudo chmod 600 /swapfile
sudo mkswap /swapfile && sudo swapon /swapfile
echo '/swapfile none swap sw 0 0' | sudo tee -a /etc/fstab
```

---

## Step 4 — Configure `.env`

```bash
cd /var/www/bb-internal
cp .env.example .env
```

Overwrite with production values (replace `CHANGE_ME_*`):

```bash
sudo tee .env > /dev/null <<'EOF'
APP_NAME="BuffaloBuilt Internal"
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_TIMEZONE=America/New_York
APP_URL=https://internal.buffalobuiltusa.com

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
DB_DATABASE=bb_internal
DB_USERNAME=bb_internal_user
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
MAIL_FROM_ADDRESS="no-reply@internal.buffalobuiltusa.com"
MAIL_FROM_NAME="${APP_NAME}"

VITE_APP_NAME="${APP_NAME}"
EOF
```

> Critical: `.env.example` ships with `DB_CONNECTION=sqlite`. Production **must** be `pgsql` — the block above already does this.

Generate the key, migrate, cache:

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

## Step 5 — File Permissions

Same pattern as HomeBuyer — deploy user owns the files, `www-data` gets group + ACL access on writable dirs.

```bash
cd /var/www/bb-internal
sudo chown -R $(whoami):www-data .
sudo chmod -R u=rwX,g=rX,o= .
sudo chmod -R ug+rwx storage bootstrap/cache
sudo setfacl -R  -m u:www-data:rwx storage bootstrap/cache
sudo setfacl -dR -m u:www-data:rwx storage bootstrap/cache
sudo chmod 640 .env
```

**Validation**:

```bash
sudo -u www-data touch storage/logs/permcheck && \
  sudo -u www-data rm storage/logs/permcheck && \
  echo OK
```

---

## Step 6 — Nginx Server Block (HTTP-only, pre-cert)

A separate server block — does **not** touch the existing HomeBuyer site.

```bash
sudo tee /etc/nginx/sites-available/bb-internal > /dev/null <<'EOF'
server {
    listen 80;
    listen [::]:80;
    server_name internal.buffalobuiltusa.com;

    root /var/www/bb-internal/public;
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

sudo ln -sf /etc/nginx/sites-available/bb-internal /etc/nginx/sites-enabled/
sudo nginx -t && sudo systemctl reload nginx
```

> Do **not** remove the HomeBuyer symlink. Both sites should be enabled at the same time.

**Validation** — bypass Cloudflare and hit the origin directly:

```bash
curl -I http://127.0.0.1 -H "Host: internal.buffalobuiltusa.com"
# HTTP/1.1 200 OK
```

---

## Step 7 — Queue Worker (systemd)

**Unique unit name** so this doesn't collide with HomeBuyer's `bb-queue.service`.

```bash
sudo tee /etc/systemd/system/bb-internal-queue.service > /dev/null <<EOF
[Unit]
Description=BB Internal Queue Worker
After=network.target postgresql.service

[Service]
User=$(whoami)
Group=www-data
Restart=always
RestartSec=5
WorkingDirectory=/var/www/bb-internal
ExecStart=/usr/bin/php artisan queue:work --sleep=3 --tries=3 --max-time=3600
StandardOutput=append:/var/log/bb-internal-queue.log
StandardError=append:/var/log/bb-internal-queue.log

[Install]
WantedBy=multi-user.target
EOF

sudo touch /var/log/bb-internal-queue.log
sudo chown $(whoami):www-data /var/log/bb-internal-queue.log
sudo systemctl daemon-reload
sudo systemctl enable --now bb-internal-queue
sudo systemctl status bb-internal-queue --no-pager
```

**Validation**: `active (running)` and a `Main PID`. Confirm both queues are up:

```bash
sudo systemctl is-active bb-queue bb-internal-queue
```

---

## Step 8 — Scheduler Cron (append, don't overwrite)

The deploy user already has a crontab line for HomeBuyer. **Appending** preserves it:

```bash
( sudo crontab -u $(whoami) -l 2>/dev/null; \
  echo "* * * * * cd /var/www/bb-internal && /usr/bin/php artisan schedule:run >> /dev/null 2>&1" \
) | sudo crontab -u $(whoami) -
```

**Validation** — both lines should be present:

```bash
sudo crontab -u $(whoami) -l
# expect two lines: one /var/www/bb-homebuyer-lite, one /var/www/bb-internal
```

> If you ever need to wipe and rewrite the crontab, dump it first (`crontab -l > ~/cron.bak`) so HomeBuyer's line isn't lost.

---

## Step 9 — Cloudflare Origin Certificate + HTTPS

### A. Generate the cert (Cloudflare dashboard)

1. Zone `buffalobuiltusa.com` → **SSL/TLS → Origin Server → Create Certificate**
2. Key type: **RSA (2048)**, hostname: `internal.buffalobuiltusa.com`, validity: 15 years
3. Copy **both** the certificate and the private key (key shown only once — save it somewhere safe right away)

### B. Install on the server

```bash
sudo mkdir -p /etc/nginx/ssl && sudo chmod 700 /etc/nginx/ssl

# Paste certificate body here
sudo nano /etc/nginx/ssl/internal.buffalobuiltusa.com.pem

# Paste private key here
sudo nano /etc/nginx/ssl/internal.buffalobuiltusa.com.key

# Lock down
sudo chmod 600 /etc/nginx/ssl/internal.buffalobuiltusa.com.key
sudo chown root:root /etc/nginx/ssl/internal.buffalobuiltusa.com.*
```

### C. Replace the Nginx config with the HTTPS version

```bash
sudo tee /etc/nginx/sites-available/bb-internal > /dev/null <<'EOF'
server {
    listen 80;
    listen [::]:80;
    server_name internal.buffalobuiltusa.com;
    return 301 https://$host$request_uri;
}

server {
    listen 443 ssl;
    listen [::]:443 ssl;
    http2 on;
    server_name internal.buffalobuiltusa.com;

    ssl_certificate     /etc/nginx/ssl/internal.buffalobuiltusa.com.pem;
    ssl_certificate_key /etc/nginx/ssl/internal.buffalobuiltusa.com.key;
    ssl_protocols       TLSv1.2 TLSv1.3;
    ssl_ciphers         HIGH:!aNULL:!MD5;

    root /var/www/bb-internal/public;
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

### D. Cloudflare SSL mode

Already on **Full (strict)** from the HomeBuyer rollout. Confirm it; the zone-wide setting covers both subdomains.

### E. Validate

```bash
curl -I https://internal.buffalobuiltusa.com   # HTTP/2 200 (or 302)
```

Open in browser — valid HTTPS lock, no cert errors.

---

## Step 10 — Final Smoke Test

```bash
sudo systemctl is-active nginx php8.5-fpm postgresql bb-queue bb-internal-queue cron
curl -sI https://internal.buffalobuiltusa.com | head -1
curl -sI https://bb-lite.buffalobuiltusa.com  | head -1
tail -n 20 /var/log/bb-internal-queue.log
tail -n 20 /var/www/bb-internal/storage/logs/laravel.log
```

All services `active`; both subdomains return 200; no Laravel errors in either app.

---

## Operations: Future Deploys

Save as `~/deploy-internal.sh` on the server (separate from HomeBuyer's `~/deploy.sh`):

```bash
cat > ~/deploy-internal.sh <<'EOF'
#!/usr/bin/env bash
set -e
cd /var/www/bb-internal
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
sudo systemctl restart php8.5-fpm bb-internal-queue
php artisan up
echo "bb-internal deploy done."
EOF
chmod +x ~/deploy-internal.sh
```

For each release: `~/deploy-internal.sh`.

> `php8.5-fpm` is shared with HomeBuyer. Restarting it briefly interrupts the HomeBuyer app too (sub-second). If you need true zero-impact restarts later, swap to FPM `reload` (`sudo systemctl reload php8.5-fpm`), which keeps existing workers alive while spawning new ones.

---

## Multi-Tenant Risks (read once before first deploy)

| Risk | Why it matters | Mitigation |
|---|---|---|
| Restarting `php8.5-fpm` for one app blips the other | Shared FPM master | Use `systemctl reload php8.5-fpm` instead of `restart` once stable |
| Crontab rewrite drops HomeBuyer's scheduler line | `crontab -u user -` replaces the whole file | Always pipe `crontab -l` first, then append — never write a fresh crontab |
| `npm run build` OOM-kills HomeBuyer's PHP workers | Both compete for RAM on small instances | Add swap (Step 3) or build locally and rsync `public/build/` |
| Nginx config typo takes down both sites | `nginx -t` validates **all** loaded sites; bad config in one breaks reloads | Always run `sudo nginx -t` before `reload`; never `restart` blind |
| Postgres role mix-up | Both apps share one PG instance | Strictly: `bb_user` for HomeBuyer, `bb_internal_user` for this app — never reuse |
| `.env` written world-readable | `tee` defaults to 644 | Step 4 forces `chmod 640 .env` — verify with `ls -l .env` |
| SSL cert dir is shared | `/etc/nginx/ssl/` holds both certs | Use the **full hostname** in filenames so they never collide |

---

## Troubleshooting Cheatsheet

| Symptom | Likely cause | Fix |
|---|---|---|
| `nginx: [emerg] duplicate listen options` | Both sites declared `default_server` | Drop `default_server` from the new vhost |
| HomeBuyer goes down after editing the bb-internal vhost | Nginx reload failed and rolled back, or the FPM socket path is wrong | `sudo nginx -t`; verify `/var/run/php/php8.5-fpm.sock` exists |
| `internal.buffalobuiltusa.com` resolves to HomeBuyer's homepage | `server_name` typo or missing symlink in `sites-enabled/` | `ls -l /etc/nginx/sites-enabled/`; ensure `bb-internal` is linked |
| `permission denied for schema public` on migrate | Step 2's second `GRANT` was skipped | Re-run the `GRANT ALL ON SCHEMA public` line in Step 2 |
| 521 from Cloudflare | Origin not listening on 443 yet (still in Step 6 state) | Finish Step 9 or temporarily set CF SSL mode to **Flexible** |
| 525 SSL Handshake Failed | Cert hostname mismatch | Re-issue Origin Cert with exact hostname `internal.buffalobuiltusa.com` |
| `bb-internal-queue` won't start | Wrong `User=` in unit file, or `WorkingDirectory` typo | `sudo systemctl status bb-internal-queue` + `journalctl -u bb-internal-queue -n 50` |
| App can't write to `storage/logs` | ACLs reset (often after `git pull` of new dirs) | Re-run Step 5's `setfacl` commands |
| Stale config after `.env` edit | Cached | `php artisan config:cache` (prod requires the cache; recache after every change) |
| `Connection refused` to Postgres | `DB_HOST=localhost` instead of `127.0.0.1` | Use `127.0.0.1` — PG's default `pg_hba.conf` treats `localhost` differently in some setups |

---

## Hardening Checklist (post-deploy)

- [ ] Rotate the temporary DB password if it was shared in chat/screenshots
- [ ] Add `bb_internal` to the existing `pg_dump` backup cron (use `--dbname=bb_internal`)
- [ ] Add `/var/log/bb-internal-queue.log` to logrotate (mirror HomeBuyer's logrotate config)
- [ ] Add UptimeRobot / Better Stack monitor on `https://internal.buffalobuiltusa.com`
- [ ] Configure Cloudflare WAF / rate limits for the new subdomain
- [ ] Confirm `unattended-upgrades` is still enabled (set up during HomeBuyer rollout)
- [ ] Re-test HomeBuyer end-to-end — make sure adding the second tenant didn't regress it

---

## File / Service Reference

| Thing | Path |
|---|---|
| App root | `/var/www/bb-internal` |
| Nginx site config | `/etc/nginx/sites-available/bb-internal` |
| SSL cert + key | `/etc/nginx/ssl/internal.buffalobuiltusa.com.{pem,key}` |
| PHP-FPM socket (shared) | `/var/run/php/php8.5-fpm.sock` |
| Queue worker unit | `/etc/systemd/system/bb-internal-queue.service` |
| Queue worker log | `/var/log/bb-internal-queue.log` |
| Laravel app log | `/var/www/bb-internal/storage/logs/laravel.log` |
| Nginx access log (shared) | `/var/log/nginx/access.log` |
| Nginx error log (shared) | `/var/log/nginx/error.log` |
| Postgres data dir (shared) | `/var/lib/postgresql/18/main/` |
| Postgres config (shared) | `/etc/postgresql/18/main/postgresql.conf` |

| Service | Manage with |
|---|---|
| Nginx (shared) | `sudo systemctl reload nginx` |
| PHP-FPM (shared) | `sudo systemctl reload php8.5-fpm` |
| PostgreSQL (shared) | `sudo systemctl restart postgresql` |
| Internal queue worker | `sudo systemctl restart bb-internal-queue` |
| Internal scheduler | runs via the deploy user's crontab |
