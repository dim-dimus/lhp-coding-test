<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAttendeeRequest;
use App\Models\Event;
use Illuminate\Http\RedirectResponse;

class AttendeeController extends Controller
{
    public function store(StoreAttendeeRequest $request, Event $event): RedirectResponse
    {
        $validated = $request->validated();

        // Idempotent per (event, email): re-registering the same email is a no-op.
        $event->attendees()->firstOrCreate(
            ['email' => $validated['email']],
            ['name' => $validated['name']],
        );

        // Phase 7 will queue a confirmation email here for newly-created attendees.

        return back();
    }
}
