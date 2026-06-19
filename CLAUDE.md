# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

A Laravel coding test ("Event Visuals" — see `CODING_TEST.md`). Stack: **Laravel 13 + Inertia 2 + Vue 3 + TypeScript + Tailwind v4**, built on the Laravel Vue starter kit (Fortify auth, passkeys, two-factor). The work lives in the **Event domain**; most of the auth/settings scaffolding is starter-kit boilerplate.

## Working rules (read first)

- **Never `git commit`, `git push`, or open a PR without the user's explicit approval.** You may stage or prepare changes when asked, but wait for an explicit go-ahead before creating any commit.
- **Follow the principles in `SKILL.md`** (repo root) for all work here:
  1. **Think before coding** — state assumptions; surface multiple interpretations instead of silently picking; push back when a simpler approach exists; ask when unclear.
  2. **Simplicity first** — minimum code that solves the problem; no speculative features, abstractions for single-use code, or unrequested flexibility. Build a helper only once a real second caller exists.
  3. **Surgical changes** — touch only what the task needs; match existing style; don't refactor what isn't broken; only remove orphans your own change created.
  4. **Goal-driven execution** — define verifiable success criteria (prefer a failing test → make it pass) and state a brief plan with per-step verification.

## Commands

```bash
composer setup            # first-time: install, .env, key, migrate, npm install, build
composer dev              # run everything: php server + queue worker + pail logs + vite (concurrently)

# Tests (Pest)
php artisan test                                   # full suite
php artisan test --filter=EventListingTest         # one file/class by name
php artisan test tests/Feature/EventListingTest.php
./vendor/bin/pest --filter="filters the data endpoint by status"  # one `it(...)` case

# Quality gates
composer lint           # Pint (PHP) auto-fix        | composer lint:check = --test
composer types:check    # PHPStan/Larastan level 7   | npm run types:check = vue-tsc (TS)
npm run lint            # ESLint auto-fix            | npm run format = Prettier
composer ci:check       # eslint + prettier + vue-tsc + phpstan + artisan test (the full bar)

# Data
php artisan migrate:fresh --seed                   # rebuild + seed (default 1.25M events, slow!)
SEED_ROWS=50000 php artisan db:seed                # seed a workable subset
```

Note there are **two separate type checks**: PHPStan for PHP (`composer types:check`) and vue-tsc for TS (`npm run types:check`). `composer ci:check` runs both plus lint/format/tests.

## Architecture

**Inertia bridge.** Controllers return `Inertia::render('Events/Index', [...])`; pages are Vue SFCs in `resources/js/pages/`. Page-to-layout mapping is resolved in `resources/js/app.ts`: `auth/*` → `AuthLayout`, `settings/*` → `AppLayout + SettingsLayout`, everything else → `AppLayout`. Pages are auto-registered by `@inertiajs/vite` (no manual `resolve`). Shared props (`auth.user`, app name, sidebar state) come from `app/Http/Middleware/HandleInertiaRequests.php`. Sidebar nav is hardcoded in `resources/js/components/AppSidebar.vue`.

**The Event model is intentionally thin (`app/Models/Event.php`).** UUID primary key, `$guarded = []`, and a single `payload` JSON column (cast to array) that holds the rich data — `name`, `description`, `venue`, `location {lat,lng}`, `schedule {starts_at, ends_at}`, `pricing`, `tags`. Only `type`, `status`, `created_time`, `latitude`, `longitude` are real columns. **Only `status` is indexed.**

**The listing read path is split in two on purpose** (`app/Http/Controllers/EventController.php`):
- `events.index` renders an Inertia shell with filter metadata only.
- `events.data` is a **separate JSON endpoint** the page polls for infinite scroll (50/page), returning `data`, pagination, and a `stats: {ms, bytes}` block surfaced in the UI.

This split, plus the seeder defaulting to **1.25M rows (~2.5 GB)**, means **performance at scale is a deliberate part of the test** — favor indexed columns over scanning/decoding the JSON `payload`.

**Seeder (`database/seeders/EventSeeder.php`).** Bulk template-driven inserts with SQLite-specific PRAGMA tuning (`withSeedingPragmas`). Coordinates are **jittered ±0.5° around ~80 known city anchors** (`CITY_ANCHORS`) — useful for offline reverse-geocoding without an external API.

**Database is SQLite** (`config/database.php` default); tests run against `:memory:`. A `mysql` connection exists but is dormant — stay on SQLite. The dev script runs a `queue:listen` worker alongside the web server, so concurrent writes (e.g. attendee registration + queued mail) can hit SQLite write locks; enable `journal_mode=wal` + `busy_timeout` (knobs already exposed in `config/database.php`) rather than switching engines.

**Tests** are Pest with `RefreshDatabase`; mail uses the `array` driver and queue is `sync` (`phpunit.xml`), so queued mailables run inline and are assertable. `tests/Feature/EventListingTest.php` is the model for testing the Event endpoints.

## Gotchas

- `created_time` (unsigned int) is the **event start time**, not a record-creation timestamp — the seeder sets `created_time = startsAt`. Date filtering and "upcoming" sorting both key off it.
- The Filter button in `resources/js/pages/Events/Index.vue` calls a misspelled handler (`aplyFilters` vs `applyFilters`) and does nothing — a real bug to fix.
- `routes/console.php` has no scheduled tasks yet; reminder emails will need a scheduler entry there (or in `bootstrap/app.php`).
- `Events/VisualOne.vue` and `VisualTwo.vue` are empty stubs — the two visualizations to build.
