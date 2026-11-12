<?php

namespace App\Models;

use App\Enums\Severity;
use App\Enums\ShipmentStatus;
use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class Shipment extends Model
{
    use HasFactory;

    // status, ETA, delay fields, shipment_number and created_by are set ONLY by services.
    protected $fillable = [
        'customer_id', 'carrier_id', 'origin_id', 'destination_id',
        'cargo_type', 'weight_kg', 'volume_cbm',
        'expected_departure_at', 'expected_arrival_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => ShipmentStatus::class,
            'delay_severity' => Severity::class,
            'weight_kg' => 'decimal:2',
            'volume_cbm' => 'decimal:2',
            'expected_departure_at' => 'datetime',
            'expected_arrival_at' => 'datetime',
            'estimated_arrival_at' => 'datetime',
            'delivered_at' => 'datetime',
            'is_delayed' => 'boolean',
            'delay_minutes' => 'integer',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function carrier(): BelongsTo
    {
        return $this->belongsTo(Carrier::class);
    }

    public function origin(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'origin_id');
    }

    public function destination(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'destination_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(ShipmentItem::class);
    }

    public function trackingEvents(): HasMany
    {
        return $this->hasMany(TrackingEvent::class)->orderBy('occurred_at')->orderBy('id');
    }

    public function exceptions(): HasMany
    {
        return $this->hasMany(ShipmentException::class);
    }

    /** Customers only see their own shipments; staff see all. */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $user->isCustomer() ? $query->where('customer_id', $user->id) : $query;
    }

    /** Current location = latest event (by time) that carries valid coordinates. */
    public function currentLocationEvent(): ?TrackingEvent
    {
        $events = $this->relationLoaded('trackingEvents')
            ? $this->trackingEvents
            : $this->trackingEvents()->get();

        return $events
            ->filter(fn (TrackingEvent $e) => $e->latitude !== null && $e->longitude !== null)
            ->sortBy([['occurred_at', 'desc'], ['id', 'desc']])
            ->first();
    }

    /** Customer + all staff receive alerts about this shipment. */
    public function notifiableUsers(): Collection
    {
        return User::query()
            ->where('id', $this->customer_id)
            ->orWhereIn('role', [UserRole::Admin->value, UserRole::Operator->value])
            ->get();
    }
}
