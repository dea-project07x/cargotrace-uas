@extends('layouts.app')

@section('title', $shipment->shipment_number)

@section('content')
    @php
        $fmt = fn ($date) => $date ? $date->format('d M Y, H:i') : '-';
        $delayText = $shipment->delay_minutes >= 60
            ? intdiv($shipment->delay_minutes, 60).' h '.($shipment->delay_minutes % 60).' min'
            : $shipment->delay_minutes.' min';
        $label = fn (?string $v) => ucwords(strtolower(str_replace('_', ' ', (string) $v)));
    @endphp

    <a href="{{ route('shipments.index') }}" class="text-sm text-sky-700 hover:underline">&larr; Back to shipments</a>

    <div class="mb-6 mt-2 flex flex-wrap items-center gap-3">
        <h1 class="text-2xl font-semibold text-slate-900">{{ $shipment->shipment_number }}</h1>
        <x-status-badge :status="$shipment->status" />
        <x-delay-badge :shipment="$shipment" />
    </div>

    @if ($shipment->is_delayed)
        <div class="mb-6 rounded-md border border-orange-200 bg-orange-50 px-4 py-3 text-sm text-orange-900">
            <strong>Delay of {{ $delayText }}.</strong> {{ $shipment->delay_reason }}
        </div>
    @endif

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            {{-- Map --}}
            <section class="rounded-lg border border-slate-200 bg-white p-5">
                <h2 class="mb-4 text-base font-semibold text-slate-900">Route map</h2>

                <div id="shipment-map"
                     data-map="{{ json_encode($mapData) }}"
                     role="region" aria-label="Shipment route map"
                     class="relative z-0 h-80 w-full rounded-md border border-slate-200 sm:h-96"></div>

                <div class="mt-3 flex flex-wrap gap-x-5 gap-y-1 text-xs text-slate-500">
                    <span class="inline-flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full bg-green-600"></span>Origin</span>
                    <span class="inline-flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full bg-red-600"></span>Destination</span>
                    <span class="inline-flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full bg-sky-600"></span>Current location</span>
                    <span class="inline-flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full bg-sky-400"></span>Tracking point</span>
                    <span class="inline-flex items-center gap-1.5"><span class="inline-block w-5 border-t-2 border-dashed border-slate-400"></span>Remaining route</span>
                </div>
                <p class="mt-2 text-xs text-slate-400">Segments are drawn as straight lines between recorded positions, not the exact sailing route.</p>

                <noscript><p class="mt-2 text-sm text-slate-500">Enable JavaScript to view the map.</p></noscript>

                @vite(['resources/js/shipment-map.js'])
            </section>

            {{-- Overview --}}
            <section class="rounded-lg border border-slate-200 bg-white p-5">
                <h2 class="mb-4 text-base font-semibold text-slate-900">Overview</h2>
                <dl class="grid gap-x-6 gap-y-4 text-sm sm:grid-cols-2">
                    <div><dt class="text-slate-500">Customer</dt><dd class="font-medium">{{ $shipment->customer->name }}</dd></div>
                    <div><dt class="text-slate-500">Carrier</dt><dd class="font-medium">{{ $shipment->carrier->name }}</dd></div>
                    <div>
                        <dt class="text-slate-500">Origin</dt>
                        <dd class="font-medium">{{ $shipment->origin->name }}, {{ $shipment->origin->city }} ({{ $shipment->origin->country_code }})</dd>
                    </div>
                    <div>
                        <dt class="text-slate-500">Destination</dt>
                        <dd class="font-medium">{{ $shipment->destination->name }}, {{ $shipment->destination->city }} ({{ $shipment->destination->country_code }})</dd>
                    </div>
                    <div><dt class="text-slate-500">Cargo type</dt><dd class="font-medium">{{ $shipment->cargo_type }}</dd></div>
                    <div>
                        <dt class="text-slate-500">Weight / volume</dt>
                        <dd class="font-medium">
                            {{ number_format((float) $shipment->weight_kg, 2) }} kg
                            @if ($shipment->volume_cbm !== null) / {{ number_format((float) $shipment->volume_cbm, 2) }} cbm @endif
                        </dd>
                    </div>
                    <div><dt class="text-slate-500">Expected departure</dt><dd class="font-medium">{{ $fmt($shipment->expected_departure_at) }}</dd></div>
                    <div><dt class="text-slate-500">Expected arrival</dt><dd class="font-medium">{{ $fmt($shipment->expected_arrival_at) }}</dd></div>
                    <div><dt class="text-slate-500">Current ETA</dt><dd class="font-medium">{{ $fmt($shipment->estimated_arrival_at) }}</dd></div>
                    <div><dt class="text-slate-500">Delivered at</dt><dd class="font-medium">{{ $fmt($shipment->delivered_at) }}</dd></div>
                </dl>
            </section>

            {{-- Items --}}
            <section class="rounded-lg border border-slate-200 bg-white p-5">
                <h2 class="mb-4 text-base font-semibold text-slate-900">Cargo items</h2>
                @if ($shipment->items->isEmpty())
                    <p class="text-sm text-slate-500">No items were listed for this shipment.</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-200 text-sm">
                            <thead class="text-left text-xs font-medium uppercase tracking-wide text-slate-500">
                                <tr><th class="py-2 pe-4">Description</th><th class="py-2 pe-4">Quantity</th><th class="py-2">Unit weight</th></tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @foreach ($shipment->items as $item)
                                    <tr>
                                        <td class="py-2 pe-4">{{ $item->description }}</td>
                                        <td class="py-2 pe-4">{{ $item->quantity }}</td>
                                        <td class="py-2">{{ $item->unit_weight_kg !== null ? number_format((float) $item->unit_weight_kg, 2).' kg' : '-' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>

            {{-- Exceptions --}}
            <section class="rounded-lg border border-slate-200 bg-white p-5">
                <h2 class="mb-4 text-base font-semibold text-slate-900">Exceptions</h2>
                @forelse ($shipment->exceptions->sortByDesc('detected_at') as $exception)
                    <div class="mb-3 rounded-md border border-slate-200 p-3 last:mb-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="font-medium">{{ $label($exception->type->value) }}</span>
                            <x-status-badge :status="$exception->status" />
                            <span class="text-xs text-slate-500">Severity: {{ $exception->severity->value }}</span>
                        </div>
                        <p class="mt-1 text-sm text-slate-600">{{ $exception->description }}</p>
                        <p class="mt-1 text-xs text-slate-400">
                            Detected {{ $fmt($exception->detected_at) }}
                            @if ($exception->resolved_at) &middot; Resolved {{ $fmt($exception->resolved_at) }} @endif
                        </p>
                        @if ($exception->resolution_notes)
                            <p class="mt-1 text-xs text-slate-500">Resolution: {{ $exception->resolution_notes }}</p>
                        @endif
                    </div>
                @empty
                    <p class="text-sm text-slate-500">No exceptions reported.</p>
                @endforelse
            </section>
        </div>

        {{-- Timeline --}}
        <aside>
            <section class="rounded-lg border border-slate-200 bg-white p-5">
                <h2 class="mb-4 text-base font-semibold text-slate-900">Tracking timeline</h2>

                <ol class="relative border-s border-slate-200 ps-6">
                    @foreach ($shipment->trackingEvents as $event)
                        <li class="mb-6 last:mb-0">
                            <span class="absolute -start-1.5 mt-1.5 h-3 w-3 rounded-full {{ $event->is_exception ? 'bg-red-500' : 'bg-sky-500' }}"></span>
                            <div class="text-sm font-medium {{ $event->is_exception ? 'text-red-700' : 'text-slate-900' }}">
                                {{ $label($event->event_type) }}
                            </div>
                            @if ($event->description)
                                <p class="text-sm text-slate-600">{{ $event->description }}</p>
                            @endif
                            <p class="text-xs text-slate-400">
                                {{ $fmt($event->occurred_at) }}
                                @if ($event->location) &middot; {{ $event->location->name }} @endif
                            </p>
                        </li>
                    @endforeach
                </ol>
            </section>
        </aside>
    </div>
@endsection
