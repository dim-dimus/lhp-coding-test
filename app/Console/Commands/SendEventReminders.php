<?php

namespace App\Console\Commands;

use App\Mail\EventReminderMail;
use App\Models\Attendee;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Mail;

class SendEventReminders extends Command
{
    protected $signature = 'events:send-reminders';

    protected $description = 'Email attendees a reminder 3 days and 24 hours before their event.';

    public function handle(): int
    {
        $sent = $this->remind('reminded_72h_at', now()->addDay()->getTimestamp(), now()->addDays(3)->getTimestamp(), '3 days');
        $sent += $this->remind('reminded_24h_at', now()->getTimestamp(), now()->addDay()->getTimestamp(), '24 hours');

        $this->info("Queued {$sent} reminder(s).");

        return self::SUCCESS;
    }

    /**
     * Email everyone whose event starts in (`$after`, `$until`] and who hasn't
     * been reminded for this window yet, then flag them so re-runs are no-ops.
     */
    private function remind(string $flag, int $after, int $until, string $window): int
    {
        $attendees = Attendee::query()
            ->whereNull($flag)
            ->whereHas('event', fn (Builder $query) => $query->whereBetween('created_time', [$after + 1, $until]))
            ->with('event')
            ->get();

        foreach ($attendees as $attendee) {
            Mail::to($attendee->email)->queue(new EventReminderMail($attendee, $window));
            $attendee->update([$flag => now()]);
        }

        return $attendees->count();
    }
}
