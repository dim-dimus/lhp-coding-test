<?php

namespace App\Mail;

use App\Models\Attendee;
use App\Support\ReverseGeocoder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;

class EventReminderMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * @param  string  $window  Human-readable lead time, e.g. "3 days" or "24 hours".
     */
    public function __construct(public Attendee $attendee, public string $window) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->attendee->event->name.' is in '.$this->window);
    }

    public function content(): Content
    {
        $event = $this->attendee->event;
        $location = ReverseGeocoder::label($event->latitude, $event->longitude);

        return new Content(
            markdown: 'mail.event-reminder',
            with: [
                'attendeeName' => $this->attendee->name,
                'eventName' => $event->name,
                'window' => $this->window,
                'startsAt' => $event->created_time !== null
                    ? Carbon::createFromTimestamp($event->created_time, 'UTC')->format('l, j F Y \a\t H:i').' UTC'
                    : null,
                'location' => $location['display'] ?? null,
                'url' => route('events.show', $event),
            ],
        );
    }
}
