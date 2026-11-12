<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\LocationResource;
use App\Models\Location;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class LocationController extends Controller
{
    public function index()
    {
        Gate::authorize('view-reference-data');

        return LocationResource::collection(Location::orderBy('name')->paginate(50));
    }

    public function store(Request $request, AuditService $audit): JsonResponse
    {
        Gate::authorize('manage-reference-data');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:10', 'unique:locations,code'],
            'city' => ['required', 'string', 'max:255'],
            'country_code' => ['required', 'string', 'size:2'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
        ]);

        $location = Location::create($data);
        $audit->log($request->user(), 'location.created', $location, null, ['code' => $location->code]);

        return LocationResource::make($location)->response()->setStatusCode(201);
    }
}
