<?php

use App\Models\Attendee;
use App\Models\Event;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

it('registers an attendee for an event', function () {
    $event = Event::factory()->published()->create();

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
    $event = Event::factory()->published()->create();

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

it('normalises email case so a differently-cased duplicate is not created', function () {
    $event = Event::factory()->published()->create();

    $this->post(route('attendees.store', $event), ['name' => 'Ada', 'email' => 'Ada@Example.com']);
    $this->post(route('attendees.store', $event), ['name' => 'Ada', 'email' => 'ada@example.com']);

    expect(Attendee::where('event_id', $event->id)->count())->toBe(1);
    $this->assertDatabaseHas('attendees', ['event_id' => $event->id, 'email' => 'ada@example.com']);
});

it('rate-limits attendee registration', function () {
    $event = Event::factory()->published()->create();

    foreach (range(1, 6) as $i) {
        $this->post(route('attendees.store', $event), ['name' => "Person {$i}", 'email' => "person{$i}@example.com"]);
    }

    $this->post(route('attendees.store', $event), ['name' => 'Person 7', 'email' => 'person7@example.com'])
        ->assertStatus(429);
});

it('blocks registration unless the event is published', function (string $status) {
    Mail::fake();
    $event = Event::factory()->create(['status' => $status]);

    $this->post(route('attendees.store', $event), ['name' => 'Ada', 'email' => 'ada@example.com'])
        ->assertForbidden();

    expect(Attendee::where('event_id', $event->id)->count())->toBe(0);
    Mail::assertNothingQueued();
})->with(['draft', 'cancelled', 'sold_out']);
