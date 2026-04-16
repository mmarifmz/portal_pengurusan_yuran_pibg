# First Live Publish Checklist (Laravel 13 + Livewire)

## 1) Server prerequisites
- Ubuntu 22.04+ (or equivalent)
- PHP 8.3 + extensions: `bcmath`, `ctype`, `fileinfo`, `json`, `mbstring`, `openssl`, `pdo`, `pdo_mysql`, `tokenizer`, `xml`, `curl`, `gd`, `zip`
- Composer 2.x
- Node 20+ and npm
- MySQL 8+
- Nginx + PHP-FPM (`php8.3-fpm`)
- Supervisor (for queue worker)

## 2) Domain & SSL
- Point DNS A record to server IP
- Use Let's Encrypt (certbot)
- Set `APP_URL=https://<your-domain>`

## 3) Deploy code
```bash
cd /var/www
sudo mkdir -p pibg
sudo chown -R $USER:$USER pibg
cd pibg
# first time
git clone <your-repo-url> .
# updates
git pull origin main
```

## 4) Install dependencies and build
```bash
cd /var/www/pibg
composer install --no-dev --optimize-autoloader
npm ci
npm run build
```

## 5) Configure environment
```bash
cp .env.example .env
php artisan key:generate
```
Fill `.env` at minimum:
- `APP_ENV=production`
- `APP_DEBUG=false`
- `APP_URL=https://<your-domain>`
- DB credentials (`DB_*`)
- ToyyibPay (`TOYYIBPAY_*`)
- WhatsApp provider (`WHATSAPP_TAC_*`, `WASENDER_*`)
- `TEACHER_WHATSAPP_PHONE`
- `TREASURY_WHATSAPP_PHONE`
- (Optional) tester: `PARENT_TESTER_PHONES`, `PARENT_TESTER_AMOUNT`

## 6) Database and caches
```bash
php artisan migrate --force
php artisan storage:link
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

## 7) Permissions
```bash
sudo chown -R www-data:www-data /var/www/pibg/storage /var/www/pibg/bootstrap/cache
sudo chmod -R 775 /var/www/pibg/storage /var/www/pibg/bootstrap/cache
```

## 8) Queue worker (Supervisor)
Create `/etc/supervisor/conf.d/pibg-worker.conf`:
```ini
[program:pibg-worker]
process_name=%(program_name)s_%(process_num)02d
command=/usr/bin/php /var/www/pibg/artisan queue:work database --sleep=3 --tries=3 --timeout=120
autostart=true
autorestart=true
user=www-data
numprocs=1
redirect_stderr=true
stdout_logfile=/var/www/pibg/storage/logs/worker.log
stopwaitsecs=3600
```
Then:
```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start pibg-worker:*
```

## 9) Nginx site config
Set web root to:
- `/var/www/pibg/public`

Use standard Laravel Nginx config with:
- `try_files $uri $uri/ /index.php?$query_string;`
- pass PHP to `php8.3-fpm.sock`

## 10) Post-deploy smoke test
- Home page loads (`/`)
- Parent search works (`/parent/search`)
- Parent TAC request + verify works
- Checkout creates ToyyibPay bill
- Return URL works (`/parent/payments/summary/return?...`)
- Callback endpoint reachable by gateway (`POST /parent/payments/callback`)
- Receipt web page opens (`/receipts/{uuid}`)
- Teacher share and treasury WhatsApp links open correctly

## 11) Deploy update flow (next releases)
```bash
cd /var/www/pibg
git pull origin main
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan migrate --force
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
sudo supervisorctl restart pibg-worker:*
```

## 12) Rollback plan
- Keep previous release tag
- Re-checkout previous tag/commit
- Re-run `composer install`, `npm run build`, cache commands
- Restart queue worker
