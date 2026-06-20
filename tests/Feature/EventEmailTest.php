<?php

use App\Mail\AttendanceConfirmationMail;
use App\Mail\EventReminderMail;
use App\Models\Attendee;
use App\Models\Event;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

it('queues a confirmation email when a new attendee registers', function () {
    Mail::fake();
    $event = Event::factory()->create();

    $this->post(route('attendees.store', $event), ['name' => 'Ada', 'email' => 'ada@example.com']);

    Mail::assertQueued(AttendanceConfirmationMail::class, fn ($mail) => $mail->hasTo('ada@example.com'));
    expect(Attendee::firstWhere('email', 'ada@example.com')->confirmed_at)->not->toBeNull();
});

it('does not re-send the confirmation on duplicate registration', function () {
    Mail::fake();
    $event = Event::factory()->create();

    $this->post(route('attendees.store', $event), ['name' => 'Ada', 'email' => 'ada@example.com']);
    $this->post(route('attendees.store', $event), ['name' => 'Ada', 'email' => 'ada@example.com']);

    Mail::assertQueuedCount(1);
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
    $event = Event::factory()->create(['created_time' => now()->addDays(3)->subHour()->timestamp]);
    $attendee = Attendee::factory()->for($event)->create();

    $this->artisan('events:send-reminders')->assertSuccessful();

    Mail::assertQueued(EventReminderMail::class, fn ($mail) => $mail->window === '3 days' && $mail->hasTo($attendee->email));
    expect($attendee->fresh()->reminded_72h_at)->not->toBeNull();

    $this->artisan('events:send-reminders');
    Mail::assertQueuedCount(1);
});

it('sends a 24-hour reminder for imminent events', function () {
    Mail::fake();
    $event = Event::factory()->create(['created_time' => now()->addHours(18)->timestamp]);
    $attendee = Attendee::factory()->for($event)->create();

    $this->artisan('events:send-reminders');

    Mail::assertQueued(EventReminderMail::class, fn ($mail) => $mail->window === '24 hours');
    expect($attendee->fresh()->reminded_24h_at)->not->toBeNull();
});

it('does not remind for events outside the windows', function () {
    Mail::fake();
    $event = Event::factory()->create(['created_time' => now()->addDays(10)->timestamp]);
    Attendee::factory()->for($event)->create();

    $this->artisan('events:send-reminders');

    Mail::assertNothingQueued();
});

it('does not send the 3-day reminder once the event is only about a day away', function () {
    Mail::fake();
    // ~25h away: past the 3-day band, so it must NOT get the "3 days" email.
    $event = Event::factory()->create(['created_time' => now()->addHours(25)->timestamp]);
    Attendee::factory()->for($event)->create();

    $this->artisan('events:send-reminders');

    Mail::assertNotQueued(EventReminderMail::class);
});
