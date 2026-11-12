<?php

namespace App\Notifications;

use App\Models\Shipment;
use Illuminate\Notifications\Notification;

/** In-app (database) notification about a shipment event: delay, exception, resolution. */
class ShipmentAlert extends Notification
{
    public function __construct(
        public Shipment $shipment,
        public string $type,
        public string $message,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'shipment_id' => $this->shipment->id,
            'shipment_number' => $this->shipment->shipment_number,
            'type' => $this->type,
            'message' => $this->message,
        ];
    }
}
