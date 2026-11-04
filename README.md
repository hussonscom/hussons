# Hussons One

Hussons One is a multilingual operations platform for retail, electronics, pharmacy, restaurant and cafe businesses. One installation can host multiple companies while keeping every company's users, branches, stock and transactions isolated.

## Product modules

- **Retail & Electronics:** product variants, IMEI and serial-number tracking, warranty periods, purchasing, stock, POS, payments and returns.
- **Pharmacy:** active ingredients, dosage and dosage form, manufacturers, batches, expiry dates, FEFO stock selection, prescription flags and expiry alerts.
- **Restaurant & Cafe:** tables, orders, kitchen states and payment flow.
- **Universal Retail:** categories, barcodes, stock adjustments, customers, suppliers and reporting.
- **Platform:** companies, branches, warehouses, roles, audit log, AZ/RU/EN interface and internal receipts.

## Requirements

- PHP 8.2 or newer with `pdo_mysql` or `pdo_sqlite`, `mbstring`, `xml`, `curl`, `zip` and `intl`
- Composer 2
- MySQL 8 / MariaDB 10.6 for production, SQLite for local checks
- Node.js 22 and npm for asset builds

## Local setup

```bash
cp .env.example .env
composer install
php artisan key:generate
touch database/database.sqlite
php artisan migrate --seed
npm ci
npm run build
php artisan serve
```

The first owner is provisioned only when `SEED_ADMIN_EMAIL` and `SEED_ADMIN_PASSWORD` are present. There is no built-in password. You can also create the first company through the onboarding screen.

## Validation

```bash
composer validate --strict --no-check-publish
vendor/bin/pint --test
php artisan test
npm run build
```

CI runs the feature suite on SQLite and MySQL 8.4 across supported PHP versions.

## Production notes

Set the web document root to `public/`. Keep `.env`, storage, source code and backups outside the public directory. Use HTTPS, `APP_DEBUG=false`, `SESSION_SECURE_COOKIE=true`, a dedicated least-privilege database user and a cron entry that runs `php artisan schedule:run` every minute.

`FISCAL_DRIVER=internal` produces an internal receipt and makes no claim of government fiscalization. A verified provider adapter must be configured before external fiscal receipts are enabled.

Deployment and rollback instructions are in [docs/deployment.md](docs/deployment.md).
