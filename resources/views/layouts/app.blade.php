<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'CargoTrace') | CargoTrace</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-50 text-slate-800 antialiased">
    <header class="border-b border-slate-200 bg-white">
        <div class="mx-auto flex max-w-7xl items-center justify-between px-4 py-3 sm:px-6">
            <a href="{{ route('shipments.index') }}" class="text-lg font-semibold tracking-tight text-slate-900">
                Cargo<span class="text-sky-600">Trace</span>
            </a>

            <nav class="flex items-center gap-4 text-sm">
                <a href="{{ route('shipments.index') }}"
                   class="font-medium hover:text-sky-700 {{ request()->routeIs('shipments.*') ? 'text-sky-700' : 'text-slate-700' }}">Shipments</a>
                <a href="{{ route('tracking.index') }}"
                   class="font-medium hover:text-sky-700 {{ request()->routeIs('tracking.*') ? 'text-sky-700' : 'text-slate-700' }}">Live map</a>

                <span class="hidden items-center text-slate-500 sm:inline-flex">
                    {{ auth()->user()->name }}
                    <span class="ml-2 rounded bg-slate-100 px-1.5 py-0.5 text-xs font-medium uppercase text-slate-600">
                        {{ auth()->user()->role->value }}
                    </span>
                </span>

                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="rounded-md border border-slate-300 px-3 py-1.5 text-slate-700 hover:bg-slate-50">
                        Sign out
                    </button>
                </form>
            </nav>
        </div>
    </header>

    <main class="mx-auto max-w-7xl px-4 py-6 sm:px-6">
        @if (session('status'))
            <div class="mb-4 rounded-md border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800" role="status">
                {{ session('status') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="mb-4 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" role="alert">
                <ul class="list-disc space-y-1 ps-5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @yield('content')
    </main>

    <footer class="mx-auto max-w-7xl px-4 pb-8 text-xs text-slate-400 sm:px-6">
        CargoTrace &middot; Web Programming 2 final project
    </footer>
</body>
</html>
