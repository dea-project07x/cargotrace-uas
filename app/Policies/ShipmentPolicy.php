<?php

namespace App\Policies;

use App\Enums\ShipmentStatus;
use App\Models\Shipment;
use App\Models\User;

class ShipmentPolicy
{
    public function viewAny(User $user): bool
    {
        return true; // customers are restricted by Shipment::scopeVisibleTo()
    }

    public function view(User $user, Shipment $shipment): bool
    {
        return $user->isStaff() || $shipment->customer_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->isStaff();
    }

    public function update(User $user, Shipment $shipment): bool
    {
        return $user->isAdmin() || ($user->isOperator() && ! $shipment->status->isTerminal());
    }

    public function delete(User $user, Shipment $shipment): bool
    {
        return $user->isAdmin()
            && in_array($shipment->status, [ShipmentStatus::Created, ShipmentStatus::Cancelled], true);
    }

    public function addTracking(User $user, Shipment $shipment): bool
    {
        return $user->isStaff();
    }

    public function manageExceptions(User $user, Shipment $shipment): bool
    {
        return $user->isStaff();
    }
}
