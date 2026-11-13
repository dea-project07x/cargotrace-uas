@props(['shipment'])
@php
    $severity = $shipment->delay_severity?->value;
    $classes = match ($severity) {
        'HIGH' => 'bg-red-50 text-red-700 ring-red-600/20',
        'MEDIUM' => 'bg-orange-50 text-orange-700 ring-orange-600/20',
        'LOW' => 'bg-yellow-50 text-yellow-800 ring-yellow-600/20',
        default => 'bg-green-50 text-green-700 ring-green-600/20',
    };
@endphp
@if ($shipment->status->value === 'CANCELLED')
    <span class="text-xs text-slate-400">N/A</span>
@elseif ($shipment->is_delayed)
    <span {{ $attributes->merge(['class' => 'inline-flex items-center whitespace-nowrap rounded-full px-2 py-0.5 text-xs font-medium ring-1 ring-inset '.$classes]) }}>Delayed &middot; {{ $severity }}</span>
@else
    <span {{ $attributes->merge(['class' => 'inline-flex items-center whitespace-nowrap rounded-full px-2 py-0.5 text-xs font-medium ring-1 ring-inset '.$classes]) }}>On time</span>
@endif
