<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreExceptionRequest;
use App\Http\Resources\ExceptionResource;
use App\Models\Shipment;
use App\Services\ExceptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class ShipmentExceptionController extends Controller
{
    public function __construct(private ExceptionService $exceptions)
    {
    }

    public function index(Shipment $shipment)
    {
        Gate::authorize('view', $shipment);

        return ExceptionResource::collection(
            $shipment->exceptions()->orderByDesc('detected_at')->orderByDesc('id')->get()
        );
    }

    public function store(StoreExceptionRequest $request, Shipment $shipment): JsonResponse
    {
        $exception = $this->exceptions->open($shipment, $request->validated(), $request->user());

        return ExceptionResource::make($exception)->response()->setStatusCode(201);
    }
}
