<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Ultra Planner')</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    <div class="app-shell">
        <header class="topbar">
            <a class="brand" href="{{ route('routes.index') }}" aria-label="Ultra Planner, halaman rute">
                <span class="brand-mark">U</span>
                <span class="brand-copy">
                    <strong>ULTRA PLANNER</strong>
                    <small>Rancang jalurmu sendiri</small>
                </span>
            </a>

            <nav class="top-nav" aria-label="Navigasi utama">
                <a href="{{ route('routes.index') }}">Rute saya</a>
                <a href="{{ route('routes.create') }}">Buat rute</a>
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
