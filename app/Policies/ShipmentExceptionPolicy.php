<?php

namespace App\Policies;

use App\Models\ShipmentException;
use App\Models\User;

class ShipmentExceptionPolicy
{
    public function view(User $user, ShipmentException $exception): bool
    {
        return $user->isStaff() || $exception->shipment->customer_id === $user->id;
    }

    public function update(User $user, ShipmentException $exception): bool
    {
        return $user->isStaff();
    }
}
