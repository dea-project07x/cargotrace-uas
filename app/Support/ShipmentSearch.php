<?php

namespace App\Support;

use App\Enums\ShipmentStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;

/**
 * Shared search / filter / sort logic so the REST API and the web UI behave identically.
 */
class ShipmentSearch
{
    public const SORTABLE = ['shipment_number', 'status', 'expected_departure_at', 'expected_arrival_at', 'created_at'];

    /** @return array<string, mixed> */
    public static function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::enum(ShipmentStatus::class)],
            'carrier_id' => ['nullable', 'integer'],
            'origin_id' => ['nullable', 'integer'],
            'destination_id' => ['nullable', 'integer'],
            'from' => ['nullable', 'date'],                        // expected_departure_at >= from
            'to' => ['nullable', 'date', 'after_or_equal:from'],   // expected_departure_at <= to
            'delayed' => ['nullable', 'boolean'],
            'sort' => ['nullable', Rule::in(self::SORTABLE)],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    /** @param array<string, mixed> $filters validated input */
    public function apply(Builder $query, array $filters): Builder
    {
        return $query
            ->when($filters['search'] ?? null, function ($q, $search) {
                $escaped = addcslashes($search, '%_\\');
                $q->where('shipment_number', 'like', "%{$escaped}%");
            })
            ->when($filters['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->when($filters['carrier_id'] ?? null, fn ($q, $v) => $q->where('carrier_id', $v))
            ->when($filters['origin_id'] ?? null, fn ($q, $v) => $q->where('origin_id', $v))
            ->when($filters['destination_id'] ?? null, fn ($q, $v) => $q->where('destination_id', $v))
            ->when($filters['from'] ?? null, fn ($q, $v) => $q->where('expected_departure_at', '>=', $v))
            ->when($filters['to'] ?? null, fn ($q, $v) => $q->where('expected_departure_at', '<=', $v))
            ->when(
                array_key_exists('delayed', $filters) && $filters['delayed'] !== null,
                fn ($q) => $q->where('is_delayed', filter_var($filters['delayed'], FILTER_VALIDATE_BOOLEAN))
            )
            ->orderBy($filters['sort'] ?? 'created_at', $filters['direction'] ?? 'desc')
            ->orderByDesc('id');
    }
}
