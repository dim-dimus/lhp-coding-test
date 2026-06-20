<?php

use App\Models\Attendee;
use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

it('renders the events listing shell without authentication', function () {
    $this->get(route('events.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Events/Index')
            ->has('statuses', 4)
            ->where('filters.from', '2023-01-01')
        );
});

it('returns a json page of events with load stats for lazy loading', function () {
    $user = User::factory()->create(['name' => 'Ada Lovelace']);
    Event::factory()->for($user)->create([
        'type' => 'concert',
        'status' => 'published',
        'created_time' => 1_700_000_000,
        'latitude' => 40.7128,
        'longitude' => -74.0060,
    ]);

    $this->getJson(route('events.data'))
        ->assertOk()
        ->assertJsonStructure([
            'data',
            'current_page',
            'last_page',
            'total',
            'stats' => ['ms', 'bytes'],
        ])
        ->assertJsonPath('total', 1)
        ->assertJsonPath('data.0.type', 'concert')
        ->assertJsonPath('data.0.created_time', 1_700_000_000)
        ->assertJsonPath('data.0.latitude', 40.7128)
        ->assertJsonPath('data.0.user.name', 'Ada Lovelace');
});

it('filters the data endpoint by status', function () {
    $user = User::factory()->create();
    Event::factory()->for($user)->create(['status' => 'published']);
    Event::factory()->for($user)->create(['status' => 'cancelled']);

    $this->getJson(route('events.data', ['status' => 'cancelled']))
        ->assertOk()
        ->assertJsonPath('total', 1)
        ->assertJsonPath('data.0.status', 'cancelled');
});

it('shows an event detail page with attendee count', function () {
    $user = User::factory()->create();
    $event = Event::factory()->for($user)->create([
        'payload' => ['name' => 'Global Tech Summit', 'location' => ['lat' => 1.5, 'lng' => 2.5]],
    ]);
    Attendee::factory()->count(3)->for($event)->create();

    $this->get(route('events.show', $event))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Events/Show')
            ->where('event.id', $event->id)
            ->where('event.name', 'Global Tech Summit')
            ->where('attendeesCount', 3)
            ->has('recentAttendees', 3)
        );
});

it('renders the two visualization pages and the dashboard without authentication', function () {
    $this->get(route('events.visual1'))->assertOk();
    $this->get(route('events.visual2'))->assertOk();
    $this->get(route('dashboard'))->assertOk();
});

it('renders the grid page with filter metadata', function () {
    $this->get(route('events.visual1'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Events/VisualOne')
            ->has('statuses', 4)
            ->has('cities')
            ->has('filters.from')
        );
});

it('filters the data endpoint by date range', function () {
    $user = User::factory()->create();
    Event::factory()->for($user)->create(['created_time' => Carbon::parse('2024-06-01')->timestamp]);
    Event::factory()->for($user)->create(['created_time' => Carbon::parse('2024-01-01')->timestamp]);

    $this->getJson(route('events.data', ['from' => '2024-03-01', 'to' => '2024-12-31']))
        ->assertOk()
        ->assertJsonPath('total', 1)
        ->assertJsonPath('data.0.created_time', Carbon::parse('2024-06-01')->timestamp);
});

it('filters the data endpoint by city bounding box', function () {
    $user = User::factory()->create();
    Event::factory()->for($user)->create(['latitude' => 40.72, 'longitude' => -74.01]);  // near New York
    Event::factory()->for($user)->create(['latitude' => 51.50, 'longitude' => -0.12]);    // near London

    $this->getJson(route('events.data', ['city' => 'New York, USA']))
        ->assertOk()
        ->assertJsonPath('total', 1)
        ->assertJsonPath('data.0.location.display', 'New York, USA');
});

it('serves a readable location and local images in the listing', function () {
    $user = User::factory()->create();
    Event::factory()->for($user)->create([
        'latitude' => 40.72,
        'longitude' => -74.01,
        'payload' => ['name' => 'Sunset Jazz Night', 'description' => 'A night of jazz.'],
    ]);

    $response = $this->getJson(route('events.data'))
        ->assertOk()
        ->assertJsonPath('data.0.name', 'Sunset Jazz Night')
        ->assertJsonPath('data.0.location.display', 'New York, USA')
        ->assertJsonCount(3, 'data.0.images');

    expect($response->json('data.0.images'))->each->toStartWith('/images/events/');
});

it('renders the calendar page with filter metadata', function () {
    $this->get(route('events.visual2'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Events/VisualTwo')
            ->has('statuses', 4)
            ->has('cities')
        );
});

it('returns per-day event counts for the calendar', function () {
    $user = User::factory()->create();
    Event::factory()->for($user)->create(['created_time' => Carbon::parse('2026-06-10 12:00', 'UTC')->timestamp]);
    Event::factory()->for($user)->create(['created_time' => Carbon::parse('2026-06-10 18:00', 'UTC')->timestamp]);
    Event::factory()->for($user)->create(['created_time' => Carbon::parse('2026-06-12 09:00', 'UTC')->timestamp]);

    $this->getJson(route('events.calendar', [
        'start' => Carbon::parse('2026-06-01', 'UTC')->timestamp,
        'end' => Carbon::parse('2026-07-01', 'UTC')->timestamp,
        'offset' => 0,
    ]))
        ->assertOk()
        ->assertJsonPath('counts.2026-06-10', 2)
        ->assertJsonPath('counts.2026-06-12', 1);
});

it('buckets calendar counts by the viewer local day using the offset', function () {
    $user = User::factory()->create();
    // 23:00 UTC on the 10th is 01:00 on the 11th at UTC+2.
    Event::factory()->for($user)->create(['created_time' => Carbon::parse('2026-06-10 23:00', 'UTC')->timestamp]);

    $range = [
        'start' => Carbon::parse('2026-06-01', 'UTC')->timestamp,
        'end' => Carbon::parse('2026-07-01', 'UTC')->timestamp,
    ];

    $this->getJson(route('events.calendar', $range + ['offset' => 0]))
        ->assertJsonPath('counts.2026-06-10', 1);

    $this->getJson(route('events.calendar', $range + ['offset' => 7200]))
        ->assertJsonPath('counts.2026-06-11', 1);
});

it('drills into a single day via start/end timestamps', function () {
    $user = User::factory()->create();
    Event::factory()->for($user)->create(['created_time' => Carbon::parse('2026-06-10 12:00', 'UTC')->timestamp]);
    Event::factory()->for($user)->create(['created_time' => Carbon::parse('2026-06-11 12:00', 'UTC')->timestamp]);

    $this->getJson(route('events.data', [
        'start' => Carbon::parse('2026-06-10 00:00', 'UTC')->timestamp,
        'end' => Carbon::parse('2026-06-11 00:00', 'UTC')->timestamp,
    ]))
        ->assertOk()
        ->assertJsonPath('total', 1)
        ->assertJsonPath('data.0.created_time', Carbon::parse('2026-06-10 12:00', 'UTC')->timestamp);
});

it('rejects an invalid status filter instead of returning empty', function () {
    $this->getJson(route('events.data', ['status' => 'bogus']))
        ->assertStatus(422);
});

it('rejects an unknown city filter instead of returning all events', function () {
    $this->getJson(route('events.data', ['city' => 'Atlantis, Nowhere']))
        ->assertStatus(422);
});

it('rejects an out-of-range calendar offset', function () {
    $this->getJson(route('events.calendar', ['start' => 1, 'end' => 2, 'offset' => 999999]))
        ->assertStatus(422);
});

it('excludes events without a start time from the listing', function () {
    $user = User::factory()->create();
    Event::factory()->for($user)->create(['created_time' => null]);
    Event::factory()->for($user)->create(['created_time' => Carbon::parse('2025-01-01', 'UTC')->timestamp]);

    $this->getJson(route('events.data'))
        ->assertOk()
        ->assertJsonPath('total', 1);
});
