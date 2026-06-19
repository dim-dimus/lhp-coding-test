# Implementation Plan (Event Visuals)

> **Strategy:** Leave the 1.25M-row dataset as-is. Filter and sort on **indexed columns that already exist**, reverse-geocode and assign images **on the fly for only the rows on screen**, and add a thin relational layer for attendees + mail. No multi-hour backfill, no `payload` mutation, full-scale performance from day one. Database stays **SQLite**.

## Guiding principles

1. **Never scan or decode `payload` across the whole table.** Filter/sort only on real columns (`created_time`, `status`, `latitude`, `longitude`) backed by indexes. Decode `payload` only for the ≤50 rows in a page.
2. **Derive, don't store.** City/country labels and image URLs are computed per-request for visible rows, not backfilled into columns.
3. **Keep it focused.** Two genuinely distinct pages, the required features, clean code, a short decisions note. Quality over quantity.

## Locked decisions

| Topic | Decision |
|---|---|
| Database | SQLite, with `journal_mode=wal` + `busy_timeout=5000` for queue/web write concurrency |
| Date sort/filter | Index `created_time` (it holds the event start time); filter `from`/`to`, sort upcoming |
| Location filter | City picker (from anchors) → bounding box on indexed `(latitude, longitude)` |
| Human-readable address | Offline nearest-anchor reverse geocoder, applied to visible rows only |
| Images | Deterministic UUID→pool mapping, 2–3 local files per event from `public/images/events` |
| Timezone | `created_time` is UTC; render in the **viewer's local timezone** with an explicit TZ label + relative ("in 3 days"). Documented as a deliberate choice. |
| Visual 1 | **Card grid** (filters + infinite scroll + image-forward cards) |
| Visual 2 | **Calendar** (month/week grid; events placed on their dates, per-day counts → click a day to load its events) |
| External deps | **None** — no map tiles, no CDN libraries. Calendar built locally, offline geocoder, local images. Honors "served locally" project-wide. |

---

## Phase 0 — Foundation (config + indexes)

**Goal:** the data layer is fast and write-safe before any feature work.

- `config/database.php` (sqlite block): set `busy_timeout => 5000`, `journal_mode => 'wal'`.
- New migration `add_event_listing_indexes`:
  - index `created_time`
  - composite index `(latitude, longitude)`
- `.env` for local dev: `MAIL_MAILER=log` (or Mailpit), `QUEUE_CONNECTION=database` (or keep `sync` until Phase 7).

**Acceptance:** `php artisan migrate`; `EXPLAIN QUERY PLAN` on a `created_time` range + bbox query uses the new indexes, not a full scan.

---

## Phase 1 — Backend read model

**Goal:** one clean JSON listing endpoint that returns display-ready rows without scanning `payload`.

1. **Reverse geocoder** — `app/Support/ReverseGeocoder.php`
   - Static array of the ~80 `CITY_ANCHORS` paired with `{city, country}` labels (author once; mirrors the seeder's anchors).
   - `label(float $lat, float $lng): array{city,country,display}` → nearest anchor by squared distance.
   - `cities(): array` for the filter dropdown; `bbox(string $city): array{minLat,maxLat,minLng,maxLng}` (anchor ± 0.5°).
2. **Image resolver** — `app/Support/EventImages.php`
   - Deterministic: hash the event UUID → pick 2–3 files from the local pool → return public URLs. Stable per event, no storage writes.
3. **Event model accessors** (`app/Models/Event.php`) — lightweight computed attributes used by the serializer: `name`, `description`, `starts_at` (Carbon from `created_time`), `location` (geocoder label), `images` (resolver). Keep them `Attribute`-based and only invoked on serialized rows.
4. **EventController rewrite** (`app/Http/Controllers/EventController.php`):
   - `data()` accepts `status`, `from`, `to`, `city`, `page`.
   - Query: `select` only needed columns + `payload`; apply `whereBetween('created_time', ...)` for from/to, `where('status', ...)`, and city → bbox `whereBetween` on lat/lng; `orderBy('created_time')` (upcoming first); `paginate(50)`.
   - Map each row to a slim DTO (id, name, description, type, status, starts_at ISO, location label, lat/lng, image URLs). Decode `payload` here, for the page only.
   - Keep the `stats: {ms, bytes}` block — it demonstrates the perf characteristics.
   - `index()` passes filter metadata: statuses, `cities` list, default date range.
   - `show()` returns the same DTO shape + full detail.

**Acceptance:** `events.data` returns geocoded + imaged rows; filtering by `from/to/city/status` works; a page response stays small (~tens of KB) and fast even against the full seed.

---

## Phase 2 — Shared frontend plumbing

**Goal:** reusable pieces so the two pages stay thin and distinct.

- `resources/js/types/events.ts` — `EventRow`, `EventFilters`, `City` types matching the DTO.
- `resources/js/composables/useEventFeed.ts` — encapsulates fetch/paginate/infinite-scroll/filter state against `events.data` (lift the logic out of the current `Index.vue`, fixing the `aplyFilters` typo bug).
- `resources/js/composables/useEventDateTime.ts` — format a UTC timestamp into viewer-local date/time + TZ label + relative string (via `Intl.DateTimeFormat`).
- `resources/js/components/events/EventFilters.vue` — shared filter bar: date range (from/to), city `<select>`, status `<select>`. Emits filter changes.

**Acceptance:** both pages can import the composable + filter bar and drive the same endpoint.

---

## Phase 3 — Visual One: Card grid

**Goal:** image-forward, responsive card grid — the familiar, fast browse view.

- `resources/js/pages/Events/VisualOne.vue`: responsive Tailwind grid of `EventCard.vue` (image carousel/first image, title, location label, local date/time, status badge, price). Infinite scroll via `useEventFeed`. Shared `EventFilters` on top.
- `resources/js/components/events/EventCard.vue` — the card, with a subtle hover lift + image fade-in (Phase 8 polish hooks).
- Wire into nav (already linked in `AppSidebar.vue`).

**Acceptance:** scroll loads pages smoothly; filters re-query; cards show local time + readable location + ≥2 images available per event.

---

## Phase 4 — Visual Two: Calendar

**Goal:** a date-driven view that looks nothing like the grid — and scales without dumping 50k+ events into one month.

- **Scalable data shape.** Add a `group_by=day` mode (or a small `events/calendar` endpoint) that returns **per-day counts** for the visible month via `select date(...) , count(*) ... group by day` over the indexed `created_time` range (+ optional `city`/`status`). Cheap and index-backed even at full scale. Clicking a day fetches that day's events (paginated, the existing DTO).
- `resources/js/pages/Events/VisualTwo.vue`: locally-built month calendar (no external lib). Each day cell shows a count + category dots; selecting a day opens a panel/list of that day's events (title, local time, location label, image). Prev/next month navigation drives the count query. Same `EventFilters` bar (city/status) narrows results.
- `resources/js/composables/useEventCalendar.ts` — month state, day-count fetch, selected-day events.
- Viewer-local timezone applies to day bucketing and display (Phase 2 helper).

**Acceptance:** month renders per-day counts fast against the full seed; navigating months re-queries; clicking a day lists its events; city/status filters apply. No external network calls.

---

## Phase 5 — Filtering (date + location) end to end

**Goal:** satisfy "filter by date and by location" on both pages.

- Confirm `from`/`to` map to `created_time` range; city maps to bbox; status optional. (Most of this lands in Phases 1–2; this phase verifies and adds tests.)
- Empty/edge states: no results, invalid range, "all cities".

**Acceptance:** both visuals filter by date range and by city; queries stay index-backed.

---

## Phase 6 — Attendees & registration

**Goal:** let people register interest; persist an attendee list.

- Migration `create_attendees_table`: `id`, `event_id` (uuid FK → events, cascade), `name`, `email`, `confirmed_at` nullable, `reminded_72h_at` nullable, `reminded_24h_at` nullable, timestamps. **Unique `(event_id, email)`**.
- `app/Models/Attendee.php` + `Event::attendees()` hasMany.
- `app/Http/Requests/StoreAttendeeRequest.php` — validate name/email; reject duplicates gracefully.
- `AttendeeController@store` → route `POST events/{event}/attendees` (`attendees.store`).
- `Events/Show.vue`: registration form + current attendee count.

**Acceptance:** posting registers an attendee (idempotent per email/event); detail page reflects the list/count.

---

## Phase 7 — Emails: confirmation + reminders

**Goal:** confirmation on signup; reminders 3 days and 24 hours before.

1. **Confirmation** — `app/Mail/AttendanceConfirmationMail.php` (queued, `ShouldQueue`); dispatched from `AttendeeController@store`. Set `confirmed_at` (or treat confirmation as informational).
2. **Reminders** — `app/Console/Commands/SendEventReminders.php` (`events:send-reminders`):
   - For the **72h window**: events starting in `[now+72h, now+72h+interval)` with attendees where `reminded_72h_at IS NULL` → send `EventReminderMail(window: '3 days')`, set flag.
   - Same for the **24h window** with `reminded_24h_at`.
   - **Idempotent** via the flag columns; safe to re-run.
3. **Schedule** — in `routes/console.php`: `Schedule::command('events:send-reminders')->hourly()` (or `everyFifteenMinutes`). Document that `php artisan schedule:work` (or cron) must run; `composer dev` already runs a queue worker for the mailables.
4. **Dev mail**: `MAIL_MAILER=log` (or Mailpit) so emails are observable locally.

**Acceptance:** registering queues a confirmation mail; running the command in the window sends each reminder exactly once and flips the flag; re-running sends nothing new.

---

## Phase 8 — Polish, tests, decisions note

- **Animations (tasteful):** card hover/lift, image fade-in on load, list-enter transitions, filter-change crossfade. Use Tailwind + `tw-animate-css`; respect `prefers-reduced-motion`. Don't overdo it.
- **Tests (Pest, extend `tests/Feature/EventListingTest.php` patterns):**
  - data endpoint: filter by `from/to`, by `city` (bbox), by `status`; rows carry location label + image URLs.
  - geocoder: known coordinate → expected nearest city.
  - attendee store: success, duplicate rejected, confirmation mail queued (`Mail::fake`).
  - reminders: `travel()` into each window → mail sent once, flag set, re-run no-op.
- **`DECISIONS.md`** (short note required by the brief): viewer-local timezone handling, geocoding-without-API approach, deterministic local images, location-filter granularity, the **no-external-dependencies** stance (locally-built calendar, no map tiles/CDN libs), the calendar per-day-count aggregation for scale, and the SQLite-at-scale rationale.

**Acceptance:** `composer ci:check` is green (eslint + prettier + vue-tsc + phpstan L7 + tests).

---

## Sequencing & dependencies

```
Phase 0 ─▶ Phase 1 ─▶ Phase 2 ─┬─▶ Phase 3 (grid) ─────┐
                               └─▶ Phase 4 (calendar) ─┼─▶ Phase 5 (filter verify + tests)
Phase 6 (attendees) ─▶ Phase 7 (emails) ────────────┘
                                            All ─▶ Phase 8 (polish + tests + decisions)
```

Phases 0→1→2 are the critical path. Phases 3 and 4 can proceed in parallel once 2 lands. Phase 6→7 is an independent track that can run alongside the visuals. Phase 8 closes out.

## Checklist

- [x] P0: SQLite WAL/busy_timeout + `created_time` and `(lat,lng)` indexes
- [x] P1: ReverseGeocoder, EventImages, Event accessors, EventController DTO + filters
- [ ] P2: events types, useEventFeed, useEventDateTime, EventFilters bar (fix `aplyFilters` bug)
- [ ] P3: VisualOne card grid + EventCard
- [ ] P4: VisualTwo locally-built calendar (per-day counts + day drill-in)
- [ ] P5: date + location filtering verified on both pages
- [ ] P6: attendees table/model/request/controller/route + Show.vue form
- [ ] P7: confirmation mail, reminder command (72h/24h, idempotent), schedule entry
- [ ] P8: animations, Pest tests, DECISIONS.md, green `composer ci:check`
