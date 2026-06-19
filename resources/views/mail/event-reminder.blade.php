<x-mail::message>
# {{ $eventName }} is in {{ $window }}

Hi {{ $attendeeName }}, just a reminder that you're registered for **{{ $eventName }}**.

@if ($startsAt)
- **When:** {{ $startsAt }}
@endif
@if ($location)
- **Where:** {{ $location }}
@endif

<x-mail::button :url="$url">
View event
</x-mail::button>

{{ config('app.name') }}
</x-mail::message>
