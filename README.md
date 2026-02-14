# Inventory API Laravel

Backend API terpisah untuk aplikasi inventory multi-tenant.

## Stack
- Laravel 12
- Laravel Sanctum (token auth)
- MySQL
- Local public storage untuk upload proof

## Setup
```bash
cp .env.example .env
composer install
php artisan key:generate
php artisan migrate
php artisan db:seed
php artisan storage:link
php artisan serve
```

## Environment utama
- `DB_*`
- `CORS_ALLOWED_ORIGINS`
- `CRON_SECRET`
- `PLATFORM_ADMIN_EMAIL`
- `PLATFORM_ADMIN_PASSWORD`
- `FILESYSTEM_DISK=public`

## Auth
- Login menghasilkan personal access token (`Bearer <token>`)
- Endpoint protected menggunakan `auth:sanctum`

## API Contract
Lihat `openapi.yaml`.
