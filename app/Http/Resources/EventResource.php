<?php

namespace App\Http\Resources;

use App\Models\Event;
use App\Support\EventImages;
use App\Support\ReverseGeocoder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

/**
 * Display-ready shape for an event. Decodes the JSON payload for this single
 * row only and derives a readable location + local images on the fly — never
 * scanning the payload across the table.
 *
 * @mixin Event
 */
class EventResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $pricing = $this->payload['pricing'] ?? [];
        $description = $this->payload['description'] ?? null;

        return [
            'id' => $this->id,
            'type' => $this->type,
            'status' => $this->status,
            'name' => $this->name,
            'description' => is_string($description) ? $description : null,
            'venue' => $this->payload['venue']['name'] ?? null,
            'created_time' => $this->created_time,
            'starts_at' => $this->created_time !== null
                ? Carbon::createFromTimestamp($this->created_time, 'UTC')->toIso8601String()
                : null,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'location' => ReverseGeocoder::label($this->latitude, $this->longitude),
            'images' => EventImages::for($this->id),
            'price' => isset($pricing['min_price']) ? [
                'amount' => (float) $pricing['min_price'],
                'currency' => $pricing['currency'] ?? 'USD',
            ] : null,
            'user' => $this->whenLoaded('user', fn () => [
                'id' => $this->user->id,
                'name' => $this->user->name,
            ]),
        ];
    }
}
