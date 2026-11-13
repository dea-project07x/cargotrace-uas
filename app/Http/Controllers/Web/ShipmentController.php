<?php

namespace App\Http\Controllers\Web;

use App\Enums\ShipmentStatus;
use App\Http\Controllers\Controller;
use App\Models\Shipment;
use App\Support\ShipmentSearch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ShipmentController extends Controller
{
    public function index(Request $request, ShipmentSearch $search): View
    {
        Gate::authorize('viewAny', Shipment::class);

        $filters = $request->validate(ShipmentSearch::rules());

        $shipments = $search->apply(
            Shipment::query()
                ->visibleTo($request->user())
                ->with(['customer', 'carrier', 'origin', 'destination']),
            $filters
        )->paginate($filters['per_page'] ?? 15)->withQueryString();

        return view('shipments.index', [
            'shipments' => $shipments,
            'filters' => $filters,
            'statuses' => ShipmentStatus::cases(),
        ]);
    }

    public function show(Shipment $shipment): View
    {
        Gate::authorize('view', $shipment);

        $shipment->load([
            'customer', 'carrier', 'origin', 'destination', 'items',
            'trackingEvents.location', 'exceptions',
        ]);

        return view('shipments.show', [
            'shipment' => $shipment,
            'mapData' => $this->mapData($shipment),
        ]);
    }

    /**
     * Data for the Leaflet map: origin, destination, recorded positions (events that
     * carry coordinates, in chronological order) and the current location.
     *
     * @return array<string, mixed>
     */
    private function mapData(Shipment $shipment): array
    {
        $points = $shipment->trackingEvents
            ->filter(fn ($e) => $e->latitude !== null && $e->longitude !== null)
            ->values()
            ->map(fn ($e) => [
                'lat' => $e->latitude,
                'lng' => $e->longitude,
                'title' => ucwords(strtolower(str_replace('_', ' ', $e->event_type))),
                'description' => $e->description,
                'location' => $e->location?->name,
                'time' => $e->occurred_at->format('d M Y, H:i'),
                'exception' => $e->is_exception,
            ])
            ->all();

        $current = $shipment->currentLocationEvent();

        return [
            'origin' => [
                'name' => $shipment->origin->name.', '.$shipment->origin->city,
                'lat' => $shipment->origin->latitude,
                'lng' => $shipment->origin->longitude,
            ],
            'destination' => [
                'name' => $shipment->destination->name.', '.$shipment->destination->city,
                'lat' => $shipment->destination->latitude,
                'lng' => $shipment->destination->longitude,
            ],
            'points' => $points,
            'current' => $current ? [
                'lat' => $current->latitude,
                'lng' => $current->longitude,
                'time' => $current->occurred_at->format('d M Y, H:i'),
            ] : null,
            'delivered' => $shipment->status === ShipmentStatus::Delivered,
        ];
    }
}
