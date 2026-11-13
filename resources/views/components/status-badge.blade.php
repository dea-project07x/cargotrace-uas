@props(['status'])
@php
    $value = $status instanceof \BackedEnum ? $status->value : (string) $status;
    $classes = match ($value) {
        'CREATED' => 'bg-slate-100 text-slate-700 ring-slate-500/20',
        'BOOKED' => 'bg-blue-50 text-blue-700 ring-blue-600/20',
        'PICKED_UP' => 'bg-indigo-50 text-indigo-700 ring-indigo-600/20',
        'IN_TRANSIT' => 'bg-sky-50 text-sky-700 ring-sky-600/20',
        'DELIVERED' => 'bg-green-50 text-green-700 ring-green-600/20',
        'CANCELLED' => 'bg-gray-100 text-gray-600 ring-gray-500/20',
        'OPEN' => 'bg-red-50 text-red-700 ring-red-600/20',
        'INVESTIGATING' => 'bg-amber-50 text-amber-800 ring-amber-600/20',
        'RESOLVED' => 'bg-green-50 text-green-700 ring-green-600/20',
        default => 'bg-gray-100 text-gray-600 ring-gray-500/20',
    };
@endphp
<span {{ $attributes->merge(['class' => 'inline-flex items-center whitespace-nowrap rounded-full px-2 py-0.5 text-xs font-medium ring-1 ring-inset '.$classes]) }}>{{ str_replace('_', ' ', $value) }}</span>
