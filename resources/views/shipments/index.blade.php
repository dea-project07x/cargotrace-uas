@extends('layouts.app')

@section('title', 'Shipments')

@section('content')
    @php
        $isCustomer = auth()->user()->isCustomer();
        $fmt = fn ($date) => $date ? $date->format('d M Y, H:i') : '-';
        $inputClass = 'block w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500';
        $labelClass = 'mb-1 block text-xs font-medium uppercase tracking-wide text-slate-500';
    @endphp

    <div class="mb-6">
        <h1 class="text-2xl font-semibold text-slate-900">Shipments</h1>
        <p class="text-sm text-slate-500">
            {{ $shipments->total() }} {{ \Illuminate\Support\Str::plural('shipment', $shipments->total()) }}
            @if ($isCustomer) belonging to your account @endif
        </p>
    </div>

    <form method="GET" action="{{ route('shipments.index') }}"
          class="mb-6 grid gap-3 rounded-lg border border-slate-200 bg-white p-4 sm:grid-cols-2 lg:grid-cols-6">
        <div class="lg:col-span-2">
            <label for="search" class="{{ $labelClass }}">Search</label>
            <input id="search" type="text" name="search" value="{{ $filters['search'] ?? '' }}"
                   placeholder="Shipment number" class="{{ $inputClass }}">
        </div>

        <div>
            <label for="status" class="{{ $labelClass }}">Status</label>
            <select id="status" name="status" class="{{ $inputClass }}">
                <option value="">All</option>
                @foreach ($statuses as $status)
                    <option value="{{ $status->value }}" @selected(($filters['status'] ?? '') === $status->value)>
                        {{ ucwords(strtolower(str_replace('_', ' ', $status->value))) }}
                    </option>
                @endforeach
            </select>
        </div>

        <div>
            <label for="delayed" class="{{ $labelClass }}">Delay</label>
            <select id="delayed" name="delayed" class="{{ $inputClass }}">
                <option value="">Any</option>
                <option value="1" @selected((string) ($filters['delayed'] ?? '') === '1')>Delayed only</option>
                <option value="0" @selected((string) ($filters['delayed'] ?? '') === '0')>On time only</option>
            </select>
        </div>

        <div>
            <label for="sort" class="{{ $labelClass }}">Sort by</label>
            <select id="sort" name="sort" class="{{ $inputClass }}">
                @foreach (['created_at' => 'Created', 'shipment_number' => 'Shipment number', 'expected_departure_at' => 'Departure', 'expected_arrival_at' => 'Arrival', 'status' => 'Status'] as $value => $label)
                    <option value="{{ $value }}" @selected(($filters['sort'] ?? 'created_at') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label for="direction" class="{{ $labelClass }}">Order</label>
            <select id="direction" name="direction" class="{{ $inputClass }}">
                <option value="desc" @selected(($filters['direction'] ?? 'desc') === 'desc')>Newest / Z-A</option>
                <option value="asc" @selected(($filters['direction'] ?? 'desc') === 'asc')>Oldest / A-Z</option>
            </select>
        </div>

        <div class="flex items-end gap-2 sm:col-span-2 lg:col-span-6">
            <button type="submit" class="rounded-md bg-sky-600 px-4 py-2 text-sm font-medium text-white hover:bg-sky-700">Apply filters</button>
            <a href="{{ route('shipments.index') }}" class="rounded-md border border-slate-300 px-4 py-2 text-sm text-slate-700 hover:bg-slate-50">Reset</a>
        </div>
    </form>

    <div class="overflow-x-auto rounded-lg border border-slate-200 bg-white">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-left text-xs font-medium uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-4 py-3">Shipment</th>
                    @unless ($isCustomer)
                        <th class="px-4 py-3">Customer</th>
                    @endunless
                    <th class="px-4 py-3">Route</th>
                    <th class="px-4 py-3">Carrier</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3">ETA</th>
                    <th class="px-4 py-3">Delay</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($shipments as $shipment)
                    <tr class="hover:bg-slate-50">
                        <td class="whitespace-nowrap px-4 py-3">
                            <a href="{{ route('shipments.show', $shipment) }}" class="font-medium text-sky-700 hover:underline">
                                {{ $shipment->shipment_number }}
                            </a>
                            <div class="text-xs text-slate-400">{{ $shipment->cargo_type }}</div>
                        </td>
                        @unless ($isCustomer)
                            <td class="px-4 py-3">{{ $shipment->customer->name }}</td>
                        @endunless
                        <td class="px-4 py-3">
                            {{ $shipment->origin->city }}
                            <span class="text-slate-400">&rarr;</span>
                            {{ $shipment->destination->city }}
                        </td>
                        <td class="px-4 py-3">{{ $shipment->carrier->name }}</td>
                        <td class="px-4 py-3"><x-status-badge :status="$shipment->status" /></td>
                        <td class="whitespace-nowrap px-4 py-3">
                            {{ $fmt($shipment->estimated_arrival_at) }}
                            <div class="text-xs text-slate-400">Expected {{ $fmt($shipment->expected_arrival_at) }}</div>
                        </td>
                        <td class="px-4 py-3"><x-delay-badge :shipment="$shipment" /></td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ $isCustomer ? 6 : 7 }}" class="px-4 py-10 text-center text-slate-500">
                            No shipments match your filters.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $shipments->links() }}
    </div>
@endsection
