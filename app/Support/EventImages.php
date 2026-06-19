<?php

namespace App\Support;

/**
 * Maps an event to a stable set of locally-served placeholder images.
 *
 * The mapping is deterministic from the event id (no storage writes, no
 * per-row data), so all 1.25M seeded events get a consistent set of images
 * without a backfill. Files live in public/images/events and are served
 * locally — no external/hotlinked URLs.
 */
final class EventImages
{
    /** @var list<string> */
    private const POOL = [
        '/images/events/placeholder-1.svg',
        '/images/events/placeholder-2.svg',
        '/images/events/placeholder-3.svg',
        '/images/events/placeholder-4.svg',
        '/images/events/placeholder-5.svg',
        '/images/events/placeholder-6.svg',
    ];

    /** Images assigned per event (satisfies the "two or more" requirement). */
    private const PER_EVENT = 3;

    /**
     * @return list<string>
     */
    public static function for(string $id): array
    {
        $count = count(self::POOL);
        $start = (int) (crc32($id) % $count);

        $images = [];
        for ($offset = 0; $offset < self::PER_EVENT; $offset++) {
            $images[] = self::POOL[($start + $offset) % $count];
        }

        return $images;
    }
}
