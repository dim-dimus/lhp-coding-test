# Event Visuals

A coding-test app for browsing a large, fully-seeded set of global events. Built on
**Laravel 13 + Inertia 2 + Vue 3 + TypeScript + Tailwind v4** (SQLite).

See [`CODING_TEST.md`](CODING_TEST.md) for the brief and [`IMPLEMENTATION_PLAN.md`](IMPLEMENTATION_PLAN.md)
for the approach and decisions.

## Requirements

- PHP **8.3+** with the usual Laravel extensions
- Composer 2
- Node **20+** and npm

## Local setup

```bash
composer install
cp .env.example .env
php artisan key:generate

# SQLite database
touch database/database.sqlite
php artisan migrate

# Seed a workable dataset. The default is 1,250,000 events (~2.5 GB) — for local
# work pass a smaller SEED_ROWS:
SEED_ROWS=5000 php artisan db:seed

npm install
```

> Shortcut: `composer setup` runs install + env + key + migrate + npm install + build
> in one go (it does **not** seed — run the `db:seed` line above afterwards).

## Start

```bash
composer dev
```

This runs everything concurrently — PHP server, queue worker, log tail (Pail) and the
Vite dev server. Then open:

- **http://localhost:8000/events-visual-1** — Visual One: card grid (defaults to upcoming events)
- http://localhost:8000/events-visual-2 — Visual Two: calendar *(in progress)*
- http://localhost:8000/events — basic list view

The event browsing pages are public (no login required). A test account is seeded if you
need one:

```
email:    test@example.com
password: password
```

## Useful commands

```bash
php artisan test          # run the test suite (Pest)
composer dev              # full dev environment (server + queue + vite + logs)
npm run build             # build front-end assets for production
SEED_ROWS=50000 php artisan migrate:fresh --seed   # rebuild + reseed a larger set
```

## Notes

- **Timezones** — event times are stored in UTC and rendered in the **viewer's local
  timezone** (events are global).
- **Images** are served locally from `public/images/events` (no external URLs).
- **Locations** are derived offline from each event's coordinates — no geocoding API.
- Email is sent via the `log` mailer by default, so confirmations/reminders land in
  `storage/logs/laravel.log`.
