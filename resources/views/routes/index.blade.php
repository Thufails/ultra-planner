@extends('layouts.app')

@section('title', 'Rute Saya · Ultra Planner')

@section('content')
    <section class="page-heading">
        <div>
            <p class="eyebrow">WORKSPACE / ROUTES</p>
            <h1>Rute saya</h1>
            <p class="page-subtitle">Simpan jalur latihan dan siapkan rute untuk perangkatmu.</p>
        </div>
        <a class="button button-primary" href="{{ route('routes.create') }}">Buat rute</a>
    </section>

    @if ($routes->isEmpty())
        <section class="empty-state">
            <div class="empty-icon" aria-hidden="true"><svg viewBox="0 0 32 32" fill="none"><path d="M7 25c0-5 3-8 8-8s8-3 8-10"/><circle cx="7" cy="25" r="3"/><circle cx="23" cy="7" r="3"/></svg></div>
            <p class="eyebrow">MULAI DARI PETA</p>
            <h2>Belum ada rute tersimpan</h2>
            <p>Klik peta untuk menambahkan titik kontrol, lalu simpan rute pertamamu.</p>
            <a class="button button-primary" href="{{ route('routes.create') }}">Rancang rute pertama</a>
        </section>
    @else
        <section class="route-list" aria-label="Daftar rute">
            @foreach ($routes as $route)
                <article class="route-card" data-route-card>
                    <a class="route-card-main" href="{{ route('routes.show', $route) }}">
                        <span class="route-card-icon" aria-hidden="true"><svg viewBox="0 0 32 32" fill="none"><path d="M7 25c0-5 3-8 8-8s8-3 8-10"/><circle cx="7" cy="25" r="3"/><circle cx="23" cy="7" r="3"/></svg></span>
                        <span class="route-card-copy">
                            <strong>{{ $route->name }}</strong>
                            <small>Diperbarui {{ $route->updated_at->format('d M Y, H:i') }}</small>
                        </span>
                    </a>

                    <div class="route-card-stats">
                        <span class="stat-value">
                            {{ $route->distance_meters ? number_format($route->distance_meters / 1000, 1) . ' km' : '—' }}
                        </span>
                        <span class="stat-label">jarak</span>
                    </div>
                    <div class="route-card-stats route-points-stat">
                        <span class="stat-value">{{ number_format($route->track_points_count) }}</span>
                        <span class="stat-label">titik lintasan</span>
                    </div>

                    <div class="route-card-actions">
                        <a class="button button-secondary button-small" href="{{ route('routes.edit', $route) }}">Edit</a>
                        <button class="button button-quiet button-small" type="button" data-delete-url="{{ route('routes.destroy', $route) }}">Hapus</button>
                    </div>
                </article>
            @endforeach
        </section>

        <div class="pagination-wrap">{{ $routes->links() }}</div>
    @endif

    <p class="toast-message" data-toast role="status" aria-live="polite"></p>
@endsection
