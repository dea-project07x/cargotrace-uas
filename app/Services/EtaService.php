<?php

namespace App\Services;

use App\Enums\ShipmentStatus;
use App\Models\Shipment;
use App\Models\User;
use App\Notifications\ShipmentAlert;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Notification;

class EtaService
{
    public function __construct(
        private DelayCalculator $calculator,
        private AuditService $audit,
    ) {
    }

    /** Evaluate delay without saving anything. */
    public function evaluate(Shipment $shipment): DelayResult
    {
        if ($shipment->status === ShipmentStatus::Cancelled) {
            return new DelayResult(false, 0, null, 'Shipment cancelled; delay not applicable.');
        }

        $expected = $shipment->expected_arrival_at;

        // Delivered shipments are judged on the actual delivery time, others on the current ETA.
        $reference = $shipment->status === ShipmentStatus::Delivered
            ? ($shipment->delivered_at ?? now())
            : ($shipment->estimated_arrival_at ?? $expected);

        return $this->calculator->calculate($expected, $reference);
    }

    /**
     * Optionally set a new ETA, re-evaluate the delay, persist the result,
     * audit the change and notify when a delay appears or gets worse.
     */
    public function apply(Shipment $shipment, ?CarbonInterface $newEstimate, ?User $actor): DelayResult
    {
        if ($newEstimate !== null) {
            $shipment->estimated_arrival_at = $newEstimate;
        }

        $wasDelayed = (bool) $shipment->is_delayed;
        $oldSeverity = $shipment->delay_severity;

        $result = $this->evaluate($shipment);

        $shipment->is_delayed = $result->isDelayed;
        $shipment->delay_minutes = $result->delayMinutes;
        $shipment->delay_severity = $result->severity;
        $shipment->delay_reason = $result->reason;
        $shipment->save();

        $changed = $wasDelayed !== $result->isDelayed || $oldSeverity !== $result->severity;

        if ($changed) {
            $this->audit->log(
                $actor,
                'shipment.delay_evaluated',
                $shipment,
                ['is_delayed' => $wasDelayed, 'severity' => $oldSeverity?->value],
                ['is_delayed' => $result->isDelayed, 'severity' => $result->severity?->value, 'reason' => $result->reason]
            );
        }

        if ($result->isDelayed && $changed) {
            Notification::send(
                $shipment->notifiableUsers(),
                new ShipmentAlert($shipment, 'delay', "Shipment {$shipment->shipment_number}: {$result->reason}")
            );
        }

        return $result;
    }
}
