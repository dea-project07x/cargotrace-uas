<?php

namespace App\Services;

use App\Enums\ExceptionStatus;
use App\Enums\ExceptionType;
use App\Enums\Severity;
use App\Exceptions\InvalidStatusTransitionException;
use App\Models\Shipment;
use App\Models\ShipmentException;
use App\Models\TrackingEvent;
use App\Models\User;
use App\Notifications\ShipmentAlert;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

class ExceptionService
{
    public function __construct(private AuditService $audit)
    {
    }

    /** @param array<string, mixed> $data type, severity, description, optional location_id */
    public function open(Shipment $shipment, array $data, User $actor): ShipmentException
    {
        return DB::transaction(function () use ($shipment, $data, $actor) {
            $detectedAt = now();

            $exception = new ShipmentException([
                'shipment_id' => $shipment->id,
                'type' => $data['type'],
                'severity' => $data['severity'],
                'description' => $data['description'],
                'detected_at' => $detectedAt,
                'reported_by' => $actor->id,
            ]);
            $exception->status = ExceptionStatus::Open;
            $exception->save();

            // The timeline distinguishes exception events from normal ones.
            $type = ExceptionType::from($data['type']);
            TrackingEvent::create([
                'shipment_id' => $shipment->id,
                'event_type' => 'EXCEPTION',
                'status' => null,
                'is_exception' => true,
                'location_id' => $data['location_id'] ?? null,
                'description' => "[{$type->value}] ".$data['description'],
                'occurred_at' => $detectedAt,
                'created_by' => $actor->id,
            ]);

            $this->audit->log($actor, 'exception.opened', $exception, null, [
                'shipment_id' => $shipment->id,
                'type' => $type->value,
                'severity' => Severity::from($data['severity'])->value,
            ]);

            Notification::send(
                $shipment->notifiableUsers(),
                new ShipmentAlert(
                    $shipment,
                    'exception',
                    "Shipment {$shipment->shipment_number}: {$type->value} ({$data['severity']}) reported."
                )
            );

            return $exception;
        });
    }

    public function changeStatus(
        ShipmentException $exception,
        ExceptionStatus $to,
        ?string $notes,
        User $actor
    ): ShipmentException {
        return DB::transaction(function () use ($exception, $to, $notes, $actor) {
            $from = $exception->status;

            if (! $from->canTransitionTo($to)) {
                throw new InvalidStatusTransitionException(
                    $from->value,
                    $to->value,
                    'exception status',
                    array_map(fn (ExceptionStatus $s) => $s->value, $from->allowedTransitions())
                );
            }

            $exception->status = $to;

            if ($notes !== null) {
                $exception->resolution_notes = $notes;
            }

            if ($to === ExceptionStatus::Resolved) {
                $exception->resolved_at = now();
            }

            $exception->save();

            $shipment = $exception->shipment;

            if ($to === ExceptionStatus::Resolved) {
                TrackingEvent::create([
                    'shipment_id' => $shipment->id,
                    'event_type' => 'EXCEPTION_RESOLVED',
                    'status' => null,
                    'is_exception' => false,
                    'description' => "[{$exception->type->value}] resolved".($notes ? ": {$notes}" : ''),
                    'occurred_at' => now(),
                    'created_by' => $actor->id,
                ]);

                Notification::send(
                    $shipment->notifiableUsers(),
                    new ShipmentAlert(
                        $shipment,
                        'exception_resolved',
                        "Shipment {$shipment->shipment_number}: {$exception->type->value} resolved."
                    )
                );
            }

            $this->audit->log(
                $actor,
                'exception.status_changed',
                $exception,
                ['status' => $from->value],
                ['status' => $to->value]
            );

            return $exception;
        });
    }
}
