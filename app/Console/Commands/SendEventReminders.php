<?php

namespace App\Console\Commands;

use App\Mail\EventReminderMail;
use App\Models\Attendee;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Mail;

class SendEventReminders extends Command
{
    /** Hours before each window we still send, to tolerate irregular runs. */
    private const TOLERANCE_HOURS = 12;

    protected $signature = 'events:send-reminders';

    protected $description = 'Email attendees a reminder 3 days and 24 hours before their event.';

    public function handle(): int
    {
        $sent = $this->remind('reminded_72h_at', 72, '3 days');
        $sent += $this->remind('reminded_24h_at', 24, '24 hours');

        $this->info("Queued {$sent} reminder(s).");

        return self::SUCCESS;
    }

    /**
     * Email everyone whose event starts in a narrow band ending `$leadHours`
     * ahead (so the "3 days" / "24 hours" label stays honest), who hasn't been
     * reminded for this window yet, then flag them so re-runs are no-ops.
     */
    private function remind(string $flag, int $leadHours, string $window): int
    {
        $upper = now()->addHours($leadHours)->getTimestamp();
        $lower = now()->addHours($leadHours - self::TOLERANCE_HOURS)->getTimestamp();

        $attendees = Attendee::query()
            ->whereNull($flag)
            ->whereHas('event', fn (Builder $query) => $query->whereBetween('created_time', [$lower + 1, $upper]))
            ->with('event')
            ->get();

        foreach ($attendees as $attendee) {
            Mail::to($attendee->email)->queue(new EventReminderMail($attendee, $window));
            $attendee->update([$flag => now()]);
        }

        return $attendees->count();
    }
}
