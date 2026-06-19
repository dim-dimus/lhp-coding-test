<?php

use App\Models\Attendee;
use App\Models\Event;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('registers an attendee for an event', function () {
    $event = Event::factory()->create();

    $this->from(route('events.show', $event))
        ->post(route('attendees.store', $event), ['name' => 'Ada Lovelace', 'email' => 'ada@example.com'])
        ->assertRedirect(route('events.show', $event));

    $this->assertDatabaseHas('attendees', [
        'event_id' => $event->id,
        'email' => 'ada@example.com',
        'name' => 'Ada Lovelace',
    ]);
});

it('does not create a duplicate attendee for the same email', function () {
    $event = Event::factory()->create();

    $this->post(route('attendees.store', $event), ['name' => 'Ada', 'email' => 'ada@example.com']);
    $this->post(route('attendees.store', $event), ['name' => 'Ada Lovelace', 'email' => 'ada@example.com']);

    expect(Attendee::where('event_id', $event->id)->count())->toBe(1);
});

it('validates attendee registration input', function () {
    $event = Event::factory()->create();

    $this->from(route('events.show', $event))
        ->post(route('attendees.store', $event), ['name' => '', 'email' => 'nope'])
        ->assertRedirect(route('events.show', $event))
        ->assertSessionHasErrors(['name', 'email']);
});
