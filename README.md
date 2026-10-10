# Organisation (ESNAD Institutions System)

Laravel-based institutions management system with web, desktop (Electron) and mobile (Capacitor: Android/iOS) clients.

## Requirements
- PHP 8.2+ and Composer
- Node.js 18+ and npm
- A database supported by Laravel (MySQL/SQLite)

## Setup
```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
```

## Run
```bash
php artisan serve
npm run dev
```

## Desktop (Electron)
```bash
npm run desktop:dev
npm run desktop:build-win
```

## Mobile (Capacitor)
```bash
npm run build
npm run mobile:sync
npm run mobile:android   # or mobile:ios
```

## Tests
```bash
php artisan test
```

## Structure
- `app/`, `routes/`, `resources/`, `database/` - Laravel application
- `electron/` - desktop wrapper
- `android/`, `ios/` - mobile projects
- `deploy/` - deployment configs (nginx, supervisor)
- `lang/` - Arabic and English translations

More docs (Arabic): `README_AR.md`, `MOBILE_APP_AR.md`, `USER_QUICK_START_AR.md`.
