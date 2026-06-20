<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAttendeeRequest;
use App\Mail\AttendanceConfirmationMail;
use App\Models\Event;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Mail;

class AttendeeController extends Controller
{
    public function store(StoreAttendeeRequest $request, Event $event): RedirectResponse
    {
        // Only published events accept registrations (the UI also disables the
        // button); this is the server-side guard behind it.
        abort_unless($event->status === 'published', 403, 'Registration is closed for this event.');

        $validated = $request->validated();

        // Idempotent per (event, email): re-registering the same email is a no-op.
        $attendee = $event->attendees()->firstOrCreate(
            ['email' => $validated['email']],
            ['name' => $validated['name']],
        );

        if ($attendee->wasRecentlyCreated) {
            $attendee->update(['confirmed_at' => now()]);
            Mail::to($attendee->email)->queue(new AttendanceConfirmationMail($attendee));
        }

        return back();
    }
}
