export interface EventLocation {
    city: string;
    country: string;
    display: string;
}

export interface EventPrice {
    amount: number;
    currency: string;
}

export interface EventUser {
    id: number;
    name: string;
}

export interface EventRow {
    id: string;
    type: string;
    status: string;
    name: string;
    description: string | null;
    venue: string | null;
    created_time: number | null;
    // ISO 8601 UTC timestamp; format for display in the viewer's timezone.
    starts_at: string | null;
    latitude: number | null;
    longitude: number | null;
    location: EventLocation | null;
    images: string[];
    price: EventPrice | null;
    user?: EventUser;
}

export interface City {
    value: string;
    label: string;
    country: string;
}

export interface EventFilters {
    status: string | null;
    from: string | null;
    to: string | null;
    city: string | null;
}

export interface EventFeedStats {
    ms: number;
    bytes: number;
}

export interface EventFeedResponse {
    data: EventRow[];
    current_page: number;
    last_page: number;
    total: number;
    stats: EventFeedStats;
}
