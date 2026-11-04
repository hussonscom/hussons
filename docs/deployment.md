# DreamHost deployment and rollback

These steps are intentionally conservative because DreamHost VPS products differ. Confirm PHP, Composer, cron, database and document-root capabilities in the panel or over SSH before running a release.

## 1. Inventory and backup

Record the current document root, PHP version, database host and scheduled jobs. Before changing production, create a timestamped archive of the complete current web root, including hidden files, uploads and `.htaccess`. If a database exists, create a consistent dump including triggers and routines. Store the archives outside the web root, calculate SHA-256 checksums and keep a second copy outside DreamHost.

The repository version replaced during development is preserved locally at:

`/workspace/archive/hussons/legacy-2026-11-04/`

It contains a full Git bundle, the original site files, the original `index.html` and verified checksums.

## 2. Prepare a release

Build in a new sibling directory rather than overwriting the live directory.

```bash
composer install --no-dev --prefer-dist --optimize-autoloader
npm ci
npm run build
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Copy `.env` from secure server configuration. Never place credentials in the repository or archive. Give `storage/` and `bootstrap/cache/` write permission to the PHP user; do not use mode `777`.

## 3. Database and scheduler

Create a dedicated MySQL database and a user restricted to that database. Run migrations only after the backup has been verified:

```bash
php artisan migrate --force
php artisan db:seed --force
```

Add one cron entry that invokes `php artisan schedule:run` every minute from the release directory. Queue work can run in short, restartable cron jobs if the VPS does not provide a supervised worker.

## 4. Smoke checks before switching

Point a protected preview host or temporary document root at the new release's `public/` directory and verify:

- HTTPS and `/up` health response;
- registration, login, logout and tenant separation;
- product creation and stock receiving;
- standard, serial/IMEI and batch/expiry sales;
- expired-lot blocking and FEFO selection;
- restaurant table, kitchen and payment flow;
- AZ, RU and EN switching;
- internal receipt generation and audit records.

## 5. Switch and rollback

Switch the domain document root or release symlink atomically after all checks pass. Keep the previous release and database backup unchanged. If a critical check fails, restore the previous document root immediately and restore the database only when the failed migration changed data incompatibly.

External fiscalization remains disabled until the selected Azerbaijani provider's current API, test credentials and acceptance process are verified.
