<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}?v=1">
    <title>@yield('title', 'Ultra Planner')</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    <div class="app-shell">
        <header class="topbar">
            <a class="brand" href="{{ route('dashboard') }}" aria-label="Ultra Planner, beranda">
                <span class="brand-mark" aria-hidden="true">
                    <svg viewBox="0 0 32 32" fill="none">
                        <path d="M8 25c0-5 3-8 8-8s8-3 8-10" stroke="currentColor" stroke-width="2.8" stroke-linecap="round" stroke-dasharray="1 0" />
                        <circle cx="8" cy="25" r="3" fill="currentColor" />
                        <circle cx="24" cy="7" r="3" fill="currentColor" />
                    </svg>
                </span>
                <span class="brand-copy">
                    <strong>ULTRA PLANNER</strong>
                    <small>Rancang jalurmu sendiri</small>
                </span>
            </a>

            <nav class="top-nav" aria-label="Navigasi utama">
                <a class="{{ request()->routeIs('dashboard') ? 'is-active' : '' }}" href="{{ route('dashboard') }}">Beranda</a>
                <a class="{{ request()->routeIs('routes.index') ? 'is-active' : '' }}" href="{{ route('routes.index') }}">Rute saya</a>
                <a class="{{ request()->routeIs('routes.create', 'routes.edit') ? 'is-active' : '' }}" href="{{ route('routes.create') }}">Buat rute</a>
            </nav>

            <span class="topbar-badge"><span></span> ROUTE PLANNER</span>
        </header>

        <main class="page-content">
            @yield('content')
        </main>
    </div>

    @stack('scripts')
</body>
</html>
