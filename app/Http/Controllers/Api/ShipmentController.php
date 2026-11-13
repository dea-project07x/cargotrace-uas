<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreShipmentRequest;
use App\Http\Requests\UpdateShipmentRequest;
use App\Http\Resources\ShipmentResource;
use App\Models\Shipment;
use App\Services\AuditService;
use App\Services\ShipmentService;
use App\Support\ShipmentSearch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class ShipmentController extends Controller
{
    public function __construct(
        private ShipmentService $shipments,
        private AuditService $audit,
    ) {
    }

    public function index(Request $request, ShipmentSearch $search)
    {
        Gate::authorize('viewAny', Shipment::class);

        $filters = $request->validate(ShipmentSearch::rules());

        $query = $search->apply(
            Shipment::query()
                ->visibleTo($request->user())
                ->with(['customer', 'carrier', 'origin', 'destination']),
            $filters
        );

        return ShipmentResource::collection(
            $query->paginate($filters['per_page'] ?? 15)->withQueryString()
        );
    }

    public function store(StoreShipmentRequest $request): JsonResponse
    {
        $shipment = $this->shipments->create($request->validated(), $request->user());

        return ShipmentResource::make($shipment)->response()->setStatusCode(201);
    }

    public function show(Shipment $shipment): ShipmentResource
    {
        Gate::authorize('view', $shipment);

        $shipment->load(['customer', 'carrier', 'origin', 'destination', 'items', 'trackingEvents']);

        return ShipmentResource::make($shipment);
    }

    public function update(UpdateShipmentRequest $request, Shipment $shipment): ShipmentResource
    {
        $shipment = $this->shipments->update($shipment, $request->validated(), $request->user());

        return ShipmentResource::make($shipment);
    }

    public function destroy(Request $request, Shipment $shipment): Response
    {
        Gate::authorize('delete', $shipment);

        $this->audit->log($request->user(), 'shipment.deleted', $shipment, [
            'shipment_number' => $shipment->shipment_number,
            'status' => $shipment->status->value,
        ]);

        $shipment->delete();

        return response()->noContent();
    }
}
