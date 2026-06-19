<x-mail::message>
# You're on the list, {{ $attendeeName }}!

Thanks for registering for **{{ $eventName }}**. We've saved your spot.

@if ($startsAt)
- **When:** {{ $startsAt }}
@endif
@if ($location)
- **Where:** {{ $location }}
@endif

<x-mail::button :url="$url">
View event
</x-mail::button>

See you there!<br>
{{ config('app.name') }}
</x-mail::message>
