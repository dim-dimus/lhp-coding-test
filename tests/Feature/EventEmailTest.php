<?php

use App\Mail\AttendanceConfirmationMail;
use App\Mail\EventReminderMail;
use App\Models\Attendee;
use App\Models\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

it('queues a confirmation email when a new attendee registers', function () {
    Mail::fake();
    $event = Event::factory()->published()->create();

    $this->post(route('attendees.store', $event), ['name' => 'Ada', 'email' => 'ada@example.com']);

    Mail::assertQueued(AttendanceConfirmationMail::class, fn ($mail) => $mail->hasTo('ada@example.com'));
    expect(Attendee::firstWhere('email', 'ada@example.com')->confirmed_at)->not->toBeNull();
});

it('does not re-send the confirmation on duplicate registration', function () {
    Mail::fake();
    $event = Event::factory()->published()->create();

    $this->post(route('attendees.store', $event), ['name' => 'Ada', 'email' => 'ada@example.com']);
    $this->post(route('attendees.store', $event), ['name' => 'Ada', 'email' => 'ada@example.com']);

    Mail::assertQueuedCount(1);
});

it('does not re-send the confirmation for a differently-cased duplicate', function () {
    Mail::fake();
    $event = Event::factory()->published()->create();

    $this->post(route('attendees.store', $event), ['name' => 'Ada', 'email' => 'Ada@Example.com']);
    $this->post(route('attendees.store', $event), ['name' => 'Ada', 'email' => 'ada@example.com']);

    Mail::assertQueuedCount(1);
});

it('survives queue serialization when rendering the confirmation email', function () {
    $event = Event::factory()->published()->create(['payload' => ['name' => 'Global Tech Summit']]);
    $attendee = Attendee::factory()->for($event)->create(['name' => 'Ada Lovelace']);

    // Round-trip through the queue (SerializesModels) before rendering, the way
    // a worker would, so a broken serialize/lazy-load surfaces here.
    $mailable = unserialize(serialize(new AttendanceConfirmationMail($attendee)));

    $mailable->assertSeeInHtml('Global Tech Summit');
    $mailable->assertSeeInHtml('Ada Lovelace');
});

it('renders the confirmation email with the event details', function () {
    $event = Event::factory()->create(['payload' => ['name' => 'Global Tech Summit']]);
    $attendee = Attendee::factory()->for($event)->create(['name' => 'Ada Lovelace']);

    $mailable = new AttendanceConfirmationMail($attendee);

    $mailable->assertSeeInHtml('Global Tech Summit');
    $mailable->assertSeeInHtml('Ada Lovelace');
});

it('sends a 3-day reminder once and is idempotent on re-run', function () {
    Mail::fake();
    $event = Event::factory()->published()->create(['created_time' => now()->addDays(3)->subHour()->timestamp]);
    $attendee = Attendee::factory()->for($event)->create();

    $this->artisan('events:send-reminders')->assertSuccessful();

    Mail::assertQueued(EventReminderMail::class, fn ($mail) => $mail->window === '3 days' && $mail->hasTo($attendee->email));
    expect($attendee->fresh()->reminded_72h_at)->not->toBeNull();

    $this->artisan('events:send-reminders');
    Mail::assertQueuedCount(1);
});

it('sends the 3-day reminder at the exact 72-hour boundary', function () {
    Mail::fake();
    $event = Event::factory()->published()->create(['created_time' => now()->addHours(72)->timestamp]);
    Attendee::factory()->for($event)->create();

    $this->artisan('events:send-reminders');

    Mail::assertQueued(EventReminderMail::class, fn ($mail) => $mail->window === '3 days');
});

it('sends a 24-hour reminder for imminent events', function () {
    Mail::fake();
    $event = Event::factory()->published()->create(['created_time' => now()->addHours(18)->timestamp]);
    $attendee = Attendee::factory()->for($event)->create();

    $this->artisan('events:send-reminders');

    Mail::assertQueued(EventReminderMail::class, fn ($mail) => $mail->window === '24 hours');
    expect($attendee->fresh()->reminded_24h_at)->not->toBeNull();
});

it('sends the 24-hour reminder at the exact 24-hour boundary', function () {
    Mail::fake();
    $event = Event::factory()->published()->create(['created_time' => now()->addHours(24)->timestamp]);
    Attendee::factory()->for($event)->create();

    $this->artisan('events:send-reminders');

    Mail::assertQueued(EventReminderMail::class, fn ($mail) => $mail->window === '24 hours');
});

it('does not remind for events outside the windows', function () {
    Mail::fake();
    $event = Event::factory()->published()->create(['created_time' => now()->addDays(10)->timestamp]);
    Attendee::factory()->for($event)->create();

    $this->artisan('events:send-reminders');

    Mail::assertNothingQueued();
});

it('does not send the 3-day reminder once the event is only about a day away', function () {
    Mail::fake();
    // ~25h away: past the 3-day band, so it must NOT get the "3 days" email.
    $event = Event::factory()->published()->create(['created_time' => now()->addHours(25)->timestamp]);
    Attendee::factory()->for($event)->create();

    $this->artisan('events:send-reminders');

    Mail::assertNotQueued(EventReminderMail::class);
});

it('does not remind attendees of a non-published event', function () {
    Mail::fake();
    // Within the 3-day window, but cancelled — no reminder should go out.
    $event = Event::factory()->create([
        'status' => 'cancelled',
        'created_time' => now()->addDays(3)->subHour()->timestamp,
    ]);
    Attendee::factory()->for($event)->create();

    $this->artisan('events:send-reminders');

    Mail::assertNothingQueued();
});

it('schedules the reminder command to run', function () {
    $commands = collect(app(Schedule::class)->events())
        ->map(fn ($event) => (string) $event->command);

    expect($commands->contains(fn ($command) => str_contains($command, 'events:send-reminders')))->toBeTrue();
});
