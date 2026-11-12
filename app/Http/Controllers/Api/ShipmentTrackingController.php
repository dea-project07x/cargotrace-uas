<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTrackingEventRequest;
use App\Http\Resources\TrackingEventResource;
use App\Models\Shipment;
use App\Services\TrackingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class ShipmentTrackingController extends Controller
{
    public function __construct(private TrackingService $tracking)
    {
    }

    /** Chronological timeline + current location (latest event that has coordinates). */
    public function index(Shipment $shipment)
    {
        Gate::authorize('view', $shipment);

        $shipment->load(['trackingEvents.location', 'trackingEvents.creator']);
        $current = $shipment->currentLocationEvent();

        return TrackingEventResource::collection($shipment->trackingEvents)->additional([
            'meta' => [
                'shipment_status' => $shipment->status->value,
                'current_location' => $current ? [
                    'latitude' => $current->latitude,
                    'longitude' => $current->longitude,
                    'occurred_at' => $current->occurred_at->toIso8601String(),
                ] : null,
            ],
        ]);
    }

    public function store(StoreTrackingEventRequest $request, Shipment $shipment): JsonResponse
    {
        $event = $this->tracking->addEvent($shipment, $request->validated(), $request->user());

        return TrackingEventResource::make($event)->response()->setStatusCode(201);
    }
}
