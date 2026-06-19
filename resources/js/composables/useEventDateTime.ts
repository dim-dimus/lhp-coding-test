const relativeFormatter = new Intl.RelativeTimeFormat(undefined, { numeric: 'auto' });

const RELATIVE_UNITS: [Intl.RelativeTimeFormatUnit, number][] = [
    ['year', 31536000],
    ['month', 2592000],
    ['week', 604800],
    ['day', 86400],
    ['hour', 3600],
    ['minute', 60],
    ['second', 1],
];

export interface UseEventDateTimeReturn {
    // e.g. "Fri, 19 Jun 2026, 8:00 PM GMT+1" — local time with timezone label.
    formatDateTime: (iso: string | null) => string;
    formatDate: (iso: string | null) => string;
    formatTime: (iso: string | null) => string;
    // e.g. "in 3 days", "2 hours ago".
    relative: (iso: string | null) => string;
}

function formatDateTime(iso: string | null): string {
    if (!iso) {
        return '';
    }

    return new Intl.DateTimeFormat(undefined, {
        weekday: 'short',
        day: 'numeric',
        month: 'short',
        year: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
        timeZoneName: 'short',
    }).format(new Date(iso));
}

function formatDate(iso: string | null): string {
    if (!iso) {
        return '';
    }

    return new Intl.DateTimeFormat(undefined, {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    }).format(new Date(iso));
}

function formatTime(iso: string | null): string {
    if (!iso) {
        return '';
    }

    return new Intl.DateTimeFormat(undefined, {
        hour: 'numeric',
        minute: '2-digit',
        timeZoneName: 'short',
    }).format(new Date(iso));
}

function relative(iso: string | null): string {
    if (!iso) {
        return '';
    }

    const diffSeconds = Math.round((new Date(iso).getTime() - Date.now()) / 1000);

    for (const [unit, seconds] of RELATIVE_UNITS) {
        if (Math.abs(diffSeconds) >= seconds || unit === 'second') {
            return relativeFormatter.format(Math.round(diffSeconds / seconds), unit);
        }
    }

    return '';
}

/**
 * Formatters that render an event's UTC `starts_at` in the viewer's local
 * timezone (events are global; we display them where the viewer is).
 */
export function useEventDateTime(): UseEventDateTimeReturn {
    return { formatDateTime, formatDate, formatTime, relative };
}
