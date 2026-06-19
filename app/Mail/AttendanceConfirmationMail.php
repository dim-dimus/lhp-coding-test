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

class AttendanceConfirmationMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Attendee $attendee) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "You're registered for ".$this->attendee->event->name);
    }

    public function content(): Content
    {
        $event = $this->attendee->event;
        $location = ReverseGeocoder::label($event->latitude, $event->longitude);

        return new Content(
            markdown: 'mail.attendance-confirmation',
            with: [
                'attendeeName' => $this->attendee->name,
                'eventName' => $event->name,
                'startsAt' => $event->created_time !== null
                    ? Carbon::createFromTimestamp($event->created_time, 'UTC')->format('l, j F Y \a\t H:i').' UTC'
                    : null,
                'location' => $location['display'] ?? null,
                'url' => route('events.show', $event),
            ],
        );
    }
}
