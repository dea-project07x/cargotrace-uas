<?php

namespace App\Http\Controllers\Api;

use App\Enums\ExceptionStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateExceptionRequest;
use App\Http\Resources\ExceptionResource;
use App\Models\ShipmentException;
use App\Services\ExceptionService;

class ExceptionController extends Controller
{
    public function __construct(private ExceptionService $exceptions)
    {
    }

    public function update(UpdateExceptionRequest $request, ShipmentException $exception): ExceptionResource
    {
        $exception = $this->exceptions->changeStatus(
            $exception,
            ExceptionStatus::from($request->validated('status')),
            $request->validated('resolution_notes'),
            $request->user()
        );

        return ExceptionResource::make($exception);
    }
}
