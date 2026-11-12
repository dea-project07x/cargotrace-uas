<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tracking events are an append-only history: services never update or delete them.
 */
class TrackingEvent extends Model
{
    protected $fillable = [
        'shipment_id', 'event_type', 'status', 'is_exception', 'location_id',
        'latitude', 'longitude', 'description', 'occurred_at', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'is_exception' => 'boolean',
            'latitude' => 'float',
            'longitude' => 'float',
            'occurred_at' => 'datetime',
        ];
    }

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
