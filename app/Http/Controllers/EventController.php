<?php

namespace App\Http\Controllers;

use App\Http\Resources\EventResource;
use App\Models\Event;
use App\Support\ReverseGeocoder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
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

    public function visualTwo(): Response
    {
        return Inertia::render('Events/VisualTwo', [
            'statuses' => self::STATUSES,
            'cities' => ReverseGeocoder::cities(),
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        $invalid = $this->validateFilters($request, [
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'start' => ['nullable', 'integer'],
            'end' => ['nullable', 'integer'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        if ($invalid !== null) {
            return $invalid;
        }

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

    /**
     * Per-day event counts for a calendar month. Bucketed by the viewer's local
     * day (via the `offset` seconds) so cells line up with the displayed times;
     * the `created_time` range keeps the query on its index.
     */
    public function calendar(Request $request): JsonResponse
    {
        $invalid = $this->validateFilters($request, [
            'start' => ['required', 'integer'],
            'end' => ['required', 'integer'],
            // Bound the bucketing offset to the real timezone range (±14h).
            'offset' => ['nullable', 'integer', 'between:-50400,50400'],
        ]);

        if ($invalid !== null) {
            return $invalid;
        }

        $offset = $request->integer('offset');

        $query = Event::query();
        $this->applyFilters($query, $request);

        $counts = $query
            ->when($request->integer('start'), fn (Builder $q, $start) => $q->where('created_time', '>=', $start))
            ->when($request->integer('end'), fn (Builder $q, $end) => $q->where('created_time', '<', $end))
            ->selectRaw("date(created_time + ?, 'unixepoch') as day, count(*) as total", [$offset])
            ->groupBy('day')
            ->pluck('total', 'day')
            ->map(fn ($total) => (int) $total);

        return response()->json(['counts' => $counts]);
    }

    public function show(Event $event): Response
    {
        $event->load('user');

        return Inertia::render('Events/Show', [
            'event' => (new EventResource($event))->resolve(),
            'attendeesCount' => $event->attendees()->count(),
            'recentAttendees' => $event->attendees()->latest()->take(8)->pluck('name')->all(),
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
     * Validation rules for the status + location inputs, so an unknown city or
     * status returns a 422 rather than silently widening (city) or emptying
     * (status) the results.
     *
     * @return array<string, mixed>
     */
    private function filterRules(): array
    {
        return [
            'status' => ['nullable', Rule::in(self::STATUSES)],
            'city' => ['nullable', Rule::in(array_column(ReverseGeocoder::cities(), 'value'))],
        ];
    }

    /**
     * Validate the JSON filter endpoints, returning a 422 JSON response on
     * failure. Done explicitly because the app only auto-renders validation as
     * JSON for `api/*` routes, and these live under `events/`.
     *
     * @param  array<string, mixed>  $extra
     */
    private function validateFilters(Request $request, array $extra): ?JsonResponse
    {
        $validator = Validator::make($request->all(), $this->filterRules() + $extra);

        if ($validator->fails()) {
            return response()->json(['message' => 'Invalid request.', 'errors' => $validator->errors()], 422);
        }

        return null;
    }

    /**
     * Status + location filters shared by the listing and the calendar. Both
     * target indexed columns (status, latitude/longitude).
     *
     * @param  Builder<Event>  $query
     */
    private function applyFilters(Builder $query, Request $request): void
    {
        $query
            ->when($request->input('status'), fn (Builder $q, $status) => $q->where('status', $status))
            ->when($request->input('city'), function (Builder $q, $city) {
                $bbox = ReverseGeocoder::bbox($city);

                if ($bbox !== null) {
                    $q->whereBetween('latitude', [$bbox['min_lat'], $bbox['max_lat']])
                        ->whereBetween('longitude', [$bbox['min_lng'], $bbox['max_lng']]);
                }
            });
    }

    /**
     * Listing query. Every filter and the sort key target an indexed column
     * (status, created_time, latitude/longitude) so this stays fast against
     * the full dataset; the JSON payload is only decoded later, per visible row.
     *
     * `from`/`to` are user-facing date filters; `start`/`end` are precise unix
     * timestamps used by the calendar's day drill-in.
     *
     * @return Builder<Event>
     */
    private function listingQuery(Request $request): Builder
    {
        $query = Event::query()->with('user');
        $this->applyFilters($query, $request);

        return $query
            ->whereNotNull('created_time')
            ->when($request->date('from', null, 'UTC'), fn (Builder $q, $from) => $q->where('created_time', '>=', $from->startOfDay()->timestamp))
            ->when($request->date('to', null, 'UTC'), fn (Builder $q, $to) => $q->where('created_time', '<=', $to->endOfDay()->timestamp))
            ->when($request->integer('start'), fn (Builder $q, $start) => $q->where('created_time', '>=', $start))
            ->when($request->integer('end'), fn (Builder $q, $end) => $q->where('created_time', '<', $end))
            ->orderBy('created_time');
    }
}
