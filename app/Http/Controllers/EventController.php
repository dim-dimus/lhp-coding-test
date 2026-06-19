<?php

namespace App\Http\Controllers;

use App\Http\Resources\EventResource;
use App\Models\Event;
use App\Support\ReverseGeocoder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EventController extends Controller
{
    private const STATUSES = ['draft', 'published', 'cancelled', 'sold_out'];

    public function index(Request $request): Response
    {
        return Inertia::render('Events/Index', [
            'filters' => $this->filters($request),
            'statuses' => self::STATUSES,
            'cities' => ReverseGeocoder::cities(),
        ]);
    }

    public function visualOne(): Response
    {
        return Inertia::render('Events/VisualOne', [
            // Default the grid to upcoming events (soonest first).
            'filters' => [
                'status' => null,
                'from' => now()->toDateString(),
                'to' => null,
                'city' => null,
            ],
            'statuses' => self::STATUSES,
            'cities' => ReverseGeocoder::cities(),
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        $start = microtime(true);

        $paginator = $this->listingQuery($request)->paginate(50)->withQueryString();

        $data = EventResource::collection($paginator->getCollection())->resolve($request);

        return response()->json([
            'data' => $data,
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'total' => $paginator->total(),
            'stats' => [
                'ms' => (int) round((microtime(true) - $start) * 1000),
                'bytes' => strlen((string) json_encode($data)),
            ],
        ]);
    }

    public function show(Event $event): Response
    {
        $event->load('user');

        return Inertia::render('Events/Show', [
            'event' => $event,
        ]);
    }

    /**
     * @return array{status: string|null, from: string|null, to: string|null, city: string|null}
     */
    private function filters(Request $request): array
    {
        return [
            'status' => $request->input('status'),
            'from' => $request->input('from', '2023-01-01'),
            'to' => $request->input('to'),
            'city' => $request->input('city'),
        ];
    }

    /**
     * Listing query. Every filter and the sort key target an indexed column
     * (status, created_time, latitude/longitude) so this stays fast against
     * the full dataset; the JSON payload is only decoded later, per visible row.
     *
     * @return Builder<Event>
     */
    private function listingQuery(Request $request): Builder
    {
        return Event::query()
            ->with('user')
            ->when($request->input('status'), fn (Builder $q, $status) => $q->where('status', $status))
            ->when($request->date('from', null, 'UTC'), fn (Builder $q, $from) => $q->where('created_time', '>=', $from->startOfDay()->timestamp))
            ->when($request->date('to', null, 'UTC'), fn (Builder $q, $to) => $q->where('created_time', '<=', $to->endOfDay()->timestamp))
            ->when($request->input('city'), function (Builder $q, $city) {
                $bbox = ReverseGeocoder::bbox($city);
                if ($bbox !== null) {
                    $q->whereBetween('latitude', [$bbox['min_lat'], $bbox['max_lat']])
                        ->whereBetween('longitude', [$bbox['min_lng'], $bbox['max_lng']]);
                }
            })
            ->orderBy('created_time');
    }
}
