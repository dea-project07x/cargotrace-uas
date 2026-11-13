@extends('layouts.app')

@section('title', 'Live tracking')

@section('content')
    @php
        $inputClass = 'block w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500';
    @endphp

    <div class="mb-4 flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-2xl font-semibold text-slate-900">Live tracking</h1>
            <p class="text-sm text-slate-500">Latest known position of every shipment. Select one to see its route and replay its journey.</p>
        </div>
        <div class="flex items-center gap-3 text-xs text-slate-500">
            <span id="track-updated"></span>
            <button id="track-refresh" type="button" class="rounded-md border border-slate-300 bg-white px-3 py-1.5 text-sm text-slate-700 hover:bg-slate-50">Refresh</button>
        </div>
    </div>

    <p id="track-error" class="mb-4 hidden rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" role="alert"></p>

    <div id="tracking-app" data-url="{{ route('tracking.data') }}" class="grid gap-4 lg:grid-cols-[22rem_minmax(0,1fr)]">
        {{-- Shipment list --}}
        <aside class="flex max-h-[34rem] flex-col rounded-lg border border-slate-200 bg-white lg:max-h-[42rem]">
            <div class="space-y-2 border-b border-slate-200 p-3">
                <label for="track-search" class="sr-only">Search shipments</label>
                <input id="track-search" type="search" placeholder="Search number, city, customer" class="{{ $inputClass }}">

                <label for="track-status" class="sr-only">Filter</label>
                <select id="track-status" class="{{ $inputClass }}">
                    <option value="">All shipments</option>
                    <option value="ACTIVE">Active (not delivered)</option>
                    <option value="DELAYED">Delayed only</option>
                    <option value="CREATED">Created</option>
                    <option value="BOOKED">Booked</option>
                    <option value="PICKED_UP">Picked up</option>
                    <option value="IN_TRANSIT">In transit</option>
                    <option value="DELIVERED">Delivered</option>
                    <option value="CANCELLED">Cancelled</option>
                </select>

                <p id="track-count" class="text-xs text-slate-500">Loading...</p>
            </div>

            <ul id="track-list" class="divide-y divide-slate-100 overflow-y-auto"></ul>
        </aside>

        {{-- Map + detail --}}
        <div class="min-w-0 space-y-4">
            <div id="tracking-map" role="region" aria-label="Map of all shipments"
                 class="relative z-0 h-[28rem] w-full rounded-lg border border-slate-200 lg:h-[34rem]"></div>

            <div class="flex flex-wrap gap-x-5 gap-y-1 text-xs text-slate-500">
                <span class="inline-flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full bg-slate-500"></span>Created</span>
                <span class="inline-flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full bg-blue-500"></span>Booked</span>
                <span class="inline-flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full bg-indigo-500"></span>Picked up</span>
                <span class="inline-flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full bg-sky-600"></span>In transit</span>
                <span class="inline-flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full bg-green-600"></span>Delivered</span>
                <span class="inline-flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full bg-yellow-500"></span>Delayed (low)</span>
                <span class="inline-flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full bg-orange-500"></span>Delayed (medium)</span>
                <span class="inline-flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full bg-red-600"></span>Delayed (high)</span>
            </div>

            <section id="track-detail" class="hidden rounded-lg border border-slate-200 bg-white p-4">
                <div class="mb-3 flex flex-wrap items-center gap-3">
                    <a id="d-number" href="#" class="text-lg font-semibold text-sky-700 hover:underline"></a>
                    <span id="d-badges" class="flex items-center gap-2"></span>
                    <a id="d-link" href="#" class="ms-auto text-sm text-sky-700 hover:underline">Open full details &rarr;</a>
                </div>

                <p id="d-delay" class="mb-3 hidden rounded-md border border-orange-200 bg-orange-50 px-3 py-2 text-sm text-orange-900"></p>

                <dl class="grid gap-x-6 gap-y-3 text-sm sm:grid-cols-2 lg:grid-cols-3">
                    <div><dt class="text-slate-500">Route</dt><dd id="d-route" class="font-medium"></dd></div>
                    <div><dt class="text-slate-500">Carrier</dt><dd id="d-carrier" class="font-medium"></dd></div>
                    <div id="d-customer-row"><dt class="text-slate-500">Customer</dt><dd id="d-customer" class="font-medium"></dd></div>
                    <div><dt class="text-slate-500">Cargo</dt><dd id="d-cargo" class="font-medium"></dd></div>
                    <div><dt class="text-slate-500">ETA</dt><dd id="d-eta" class="font-medium"></dd></div>
                    <div><dt class="text-slate-500">Last position</dt><dd id="d-last" class="font-medium"></dd></div>
                </dl>

                <div id="replay" class="mt-4 hidden border-t border-slate-200 pt-4">
                    <div class="flex items-center gap-3">
                        <button id="replay-play" type="button" class="w-20 rounded-md bg-sky-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-sky-700">Play</button>
                        <label for="replay-slider" class="sr-only">Journey replay</label>
                        <input id="replay-slider" type="range" min="0" max="0" value="0" class="w-full accent-sky-600">
                    </div>
                    <p id="replay-info" class="mt-2 text-sm text-slate-600"></p>
                </div>
            </section>
        </div>
    </div>

    <noscript><p class="mt-4 text-sm text-slate-500">Enable JavaScript to use the live map.</p></noscript>

    @vite(['resources/js/tracking-map.js'])
@endsection
