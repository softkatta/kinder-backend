# Kindergarten Backend (Laravel API)

Laravel API skeleton for the kindergarten project. Application code has been removed; only setup and configuration remain.

## Setup

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan serve
```

## Health Check

- `GET /up` — Laravel health route
- `GET /api/v1/health` — API health route

## Key Packages

- `laravel/sanctum` — API authentication (ready to use)
- `barryvdh/laravel-dompdf` — PDF generation
- `simplesoftwareio/simple-qrcode` — QR codes
- Local disk storage (`FILESYSTEM_DISK=local`) — `storage/app/public` + `php artisan storage:link`
