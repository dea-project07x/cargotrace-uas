<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Shipment;
use App\Models\TrackingEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * "Outer" map: one page showing the latest position of every shipment the user may see,
 * with route trail and replay for the selected shipment.
 */
class TrackingMapController extends Controller
{
    public function index(): View
    {
        Gate::authorize('viewAny', Shipment::class);

        return view('tracking.index');
    }

    /** JSON feed consumed by resources/js/tracking-map.js (session-authenticated). */
    public function data(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Shipment::class);

        $user = $request->user();
        $isCustomer = $user->isCustomer();
        $fmt = fn ($date) => $date?->format('d M Y, H:i');

        $shipments = Shipment::query()
            ->visibleTo($user)
            ->with([
                'customer', 'carrier', 'origin', 'destination',
                // Only events that carry a position are needed for the map.
                'trackingEvents' => fn ($q) => $q
                    ->whereNotNull('latitude')
                    ->whereNotNull('longitude')
                    ->with('location'),
            ])
            ->orderByDesc('id')
            ->get();

        $data = $shipments->map(function (Shipment $s) use ($isCustomer, $fmt) {
            $current = $s->currentLocationEvent();

            return [
                'id' => $s->id,
                'number' => $s->shipment_number,
                'url' => route('shipments.show', $s),
                'status' => $s->status->value,
                'is_delayed' => $s->is_delayed,
                'delay_severity' => $s->delay_severity?->value,
                'delay_reason' => $s->is_delayed ? $s->delay_reason : null,
                'customer' => $isCustomer ? null : $s->customer->name,
                'carrier' => $s->carrier->name,
                'cargo' => $s->cargo_type,
                'eta' => $fmt($s->estimated_arrival_at),
                'expected' => $fmt($s->expected_arrival_at),
                'origin' => [
                    'name' => $s->origin->name,
                    'city' => $s->origin->city,
                    'lat' => $s->origin->latitude,
                    'lng' => $s->origin->longitude,
                ],
                'destination' => [
                    'name' => $s->destination->name,
                    'city' => $s->destination->city,
                    'lat' => $s->destination->latitude,
                    'lng' => $s->destination->longitude,
                ],
                'current' => $current ? [
                    'lat' => $current->latitude,
                    'lng' => $current->longitude,
                    'time' => $fmt($current->occurred_at),
                ] : null,
                'points' => $s->trackingEvents->map(fn (TrackingEvent $e) => [
                    'lat' => $e->latitude,
                    'lng' => $e->longitude,
                    'title' => ucwords(strtolower(str_replace('_', ' ', $e->event_type))),
                    'description' => $e->description,
                    'location' => $e->location?->name,
                    'time' => $fmt($e->occurred_at),
                    'exception' => $e->is_exception,
                ])->values()->all(),
            ];
        })->values();

        return response()->json([
            'data' => $data,
            'generated_at' => now()->toIso8601String(),
        ]);
    }
}
