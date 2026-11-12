<?php

namespace App\Http\Controllers\Api;

use App\Enums\ShipmentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreShipmentRequest;
use App\Http\Requests\UpdateShipmentRequest;
use App\Http\Resources\ShipmentResource;
use App\Models\Shipment;
use App\Services\AuditService;
use App\Services\ShipmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class ShipmentController extends Controller
{
    private const SORTABLE = ['shipment_number', 'status', 'expected_departure_at', 'expected_arrival_at', 'created_at'];

    public function __construct(
        private ShipmentService $shipments,
        private AuditService $audit,
    ) {
    }

    public function index(Request $request)
    {
        Gate::authorize('viewAny', Shipment::class);

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::enum(ShipmentStatus::class)],
            'carrier_id' => ['nullable', 'integer'],
            'origin_id' => ['nullable', 'integer'],
            'destination_id' => ['nullable', 'integer'],
            'from' => ['nullable', 'date'],            // filters expected_departure_at >= from
            'to' => ['nullable', 'date', 'after_or_equal:from'], // filters expected_departure_at <= to
            'delayed' => ['nullable', 'boolean'],
            'sort' => ['nullable', Rule::in(self::SORTABLE)],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = Shipment::query()
            ->visibleTo($request->user())
            ->with(['customer', 'carrier', 'origin', 'destination'])
            ->when($filters['search'] ?? null, function ($q, $search) {
                $escaped = addcslashes($search, '%_\\');
                $q->where('shipment_number', 'like', "%{$escaped}%");
            })
            ->when($filters['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->when($filters['carrier_id'] ?? null, fn ($q, $v) => $q->where('carrier_id', $v))
            ->when($filters['origin_id'] ?? null, fn ($q, $v) => $q->where('origin_id', $v))
            ->when($filters['destination_id'] ?? null, fn ($q, $v) => $q->where('destination_id', $v))
            ->when($filters['from'] ?? null, fn ($q, $v) => $q->where('expected_departure_at', '>=', $v))
            ->when($filters['to'] ?? null, fn ($q, $v) => $q->where('expected_departure_at', '<=', $v))
            ->when(array_key_exists('delayed', $filters) && $filters['delayed'] !== null,
                fn ($q) => $q->where('is_delayed', $request->boolean('delayed')))
            ->orderBy($filters['sort'] ?? 'created_at', $filters['direction'] ?? 'desc')
            ->orderByDesc('id');

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
