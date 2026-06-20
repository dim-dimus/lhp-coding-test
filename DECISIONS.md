# Decisions & notes

A short tour of the choices behind this build. The full phase-by-phase plan is in
[`IMPLEMENTATION_PLAN.md`](IMPLEMENTATION_PLAN.md).

## Overall approach — work with the dataset as-is

The seed is large by design (1.25M events, ~2.5 GB). Rather than backfilling the JSON
`payload` into columns, the app **filters and sorts only on indexed columns** and derives
everything else **on the fly for the rows actually on screen**. No batch migration, no
payload mutation, full-scale performance from the first request.

- **SQLite** kept (the project default). Added WAL + `busy_timeout` for web/queue write
  concurrency, and indexes on `created_time` and `(latitude, longitude)`.
- The listing never decodes `payload` in bulk; `EventResource` decodes it for the ≤50 rows
  in a page only.

## The two visuals

Deliberately different so they don't read as the same page twice:

- **Visual One — card grid:** image-forward cards, infinite scroll, full filter bar.
  Defaults to **upcoming events, soonest first**.
- **Visual Two — calendar:** a locally-built month grid. It queries **per-day counts**
  (`GROUP BY day` over the indexed `created_time` range) instead of rows, so it scales;
  a day-cell heatmap shows volume, and clicking a day drills into its events.

## Time zones

Times are stored in UTC and rendered in the **viewer's local timezone** (events are
global — show them where the viewer is). The calendar buckets days in the viewer's local
zone too, via an offset passed to the counts query, so cells line up with the displayed
times. Emails are sent server-side with no viewer context, so they show the time in **UTC**.

## Locations & images (served locally)

- **Addresses:** events only carry lat/lng. An **offline reverse geocoder** maps each
  coordinate to its nearest of ~80 known city anchors (the seed jitters around these), so
  "New York, USA" needs **no external API** and is computed only for visible rows.
- **Location filter:** picking a city translates to a lat/lng **bounding box** on the
  indexed columns.
- **Images:** deterministic per event id → a local SVG placeholder pool in
  `public/images/events` (2+ per event). No external/hotlinked URLs, no per-row migration.

## Attendees & emails

- Registration is **idempotent** per (event, email) via `firstOrCreate` + a unique
  constraint.
- A **confirmation email** is queued only on a genuinely new registration.
- **Reminders** run from one hourly command (`events:send-reminders`) covering the
  **3-day** and **24-hour** windows. The `reminded_72h_at` / `reminded_24h_at` flags make
  re-runs no-ops; disjoint windows avoid a stale "3 days away" notice to a last-minute
  registrant.
- Dev mail uses the `log` mailer, so emails land in `storage/logs/laravel.log`.

## Animations

Kept light: card fade-rise on load, a calendar heatmap + fade on day/month change, and a
detail-page fade. All gated behind `prefers-reduced-motion`.

## Tooling — `composer ci:check`

The repo as delivered failed `ci:check` for reasons unrelated to the feature work:
pre-existing **PHPStan / Pint** issues in `EventSeeder` / `EventFactory`, **ESLint** errors in
the starter sidebar / listing, and — most broadly — a **missing Prettier config**, so Prettier
disagreed with the committed formatting across the whole `resources/` tree.

These are split into two changes so each stays reviewable on its own:

1. **Feature / gap work** (on `main`) — fixes the PHPStan / Pint / ESLint issues alongside the
   added test coverage and the published-only registration gating.
2. **`chore/prettier-format-frontend` branch** — adds `.prettierrc.json` (the starter-kit
   settings the codebase was already written to: single quotes, 4-space indent, `printWidth`
   150, trailing commas, plus `prettier-plugin-tailwindcss`) and runs `prettier --write
   resources/`. That's a mechanical, behaviour-free reformat of ~190 mostly starter-kit files,
   kept on its own branch so it doesn't bury the feature diff.

With both in place, `composer ci:check` is fully green (Pint, PHPStan, ESLint, Prettier,
vue-tsc, and the Pest suite).
