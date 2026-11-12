<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CarrierResource;
use App\Models\Carrier;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class CarrierController extends Controller
{
    public function index()
    {
        Gate::authorize('view-reference-data');

        return CarrierResource::collection(Carrier::orderBy('name')->paginate(50));
    }

    public function store(Request $request, AuditService $audit): JsonResponse
    {
        Gate::authorize('manage-reference-data');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:carriers,name'],
            'code' => ['required', 'string', 'max:10', 'unique:carriers,code'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $carrier = Carrier::create($data);
        $audit->log($request->user(), 'carrier.created', $carrier, null, ['name' => $carrier->name]);

        return CarrierResource::make($carrier)->response()->setStatusCode(201);
    }
}
