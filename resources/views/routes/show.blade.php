@extends('layouts.app')

@section('title', $route->name . ' · Ultra Planner')

@section('content')
    @php
        $controlPoints = $route->routeControlPoints->map(fn ($point) => [
            'latitude' => (float) $point->latitude,
            'longitude' => (float) $point->longitude,
        ])->values();
        $trackPoints = $route->routePoints->map(fn ($point) => [
            'latitude' => (float) $point->latitude,
            'longitude' => (float) $point->longitude,
            'elevation_meters' => $point->elevation_meters === null ? null : (float) $point->elevation_meters,
            'distance_from_start_meters' => $point->distance_from_start_meters === null ? null : (int) $point->distance_from_start_meters,
        ])->values();
    @endphp

    <section class="editor-heading">
        <div>
            <a class="back-link" href="{{ route('routes.index') }}">Kembali ke rute saya</a>
            <p class="eyebrow">ROUTE DETAILS</p>
            <h1>{{ $route->name }}</h1>
            <p class="page-subtitle">Diperbarui {{ $route->updated_at->format('d M Y, H:i') }}</p>
        </div>
        <a class="button button-primary" href="{{ route('routes.edit', $route) }}">Edit rute</a>
    </section>

    <section class="detail-stats">
        <article><span>Jarak</span><strong>{{ $route->distance_meters ? number_format($route->distance_meters / 1000, 1) . ' km' : 'Belum dihitung' }}</strong></article>
        <article><span>Perkiraan tanjakan</span><strong>{{ $route->elevation_gain_meters !== null ? '+' . number_format($route->elevation_gain_meters) . ' m' : '—' }}</strong></article>
        <article><span>Perkiraan turunan</span><strong>{{ $route->elevation_loss_meters !== null ? '−' . number_format($route->elevation_loss_meters) . ' m' : '—' }}</strong></article>
        <article><span>Titik kontrol</span><strong>{{ number_format($route->routeControlPoints->count()) }}</strong></article>
        <article><span>Titik lintasan</span><strong>{{ number_format($route->track_points_count) }}</strong></article>
    </section>

    <section class="elevation-panel detail-elevation-panel" aria-labelledby="elevation-heading">
        <div class="elevation-panel-heading">
            <h2 id="elevation-heading">Profil elevasi</h2>
            <p data-elevation-status role="status" aria-live="polite">
                {{ $route->elevation_source ? 'Estimasi dari ' . $route->elevation_source . ' · Data terrain 30 m' : 'Rute ini belum memiliki data elevasi.' }}
            </p>
        </div>
        <div class="elevation-chart" data-elevation-chart role="img" aria-label="Grafik profil elevasi rute">
            <p>Profil elevasi belum tersedia untuk rute ini.</p>
        </div>
        <p class="elevation-disclaimer">Estimasi medan dari data terrain SRTM 30 m, bukan pengukuran GPS atau altimeter barometrik.</p>
    </section>

    <section class="detail-map-card">
        <div id="route-viewer-map" class="detail-map" aria-label="Peta rute"></div>
        <p class="map-note">
            @if ($route->routing_mode === 'foot')
                Rute mengikuti jalur pejalan kaki OpenStreetMap melalui FOSSGIS. Permintaan koordinat dicatat pada log layanan.
            @else
                Rute menghubungkan titik kontrol secara langsung.
            @endif
            <a href="https://www.openstreetmap.org/fixthemap" target="_blank" rel="noreferrer">Perbaiki data peta</a> · © OpenStreetMap contributors.
        </p>
    </section>
@endsection

@push('scripts')
    <script>
        window.routeViewerConfig = @js([
            'initialCenter' => [-2.5, 118],
            'initialZoom' => 5,
            'controlPoints' => $controlPoints,
            'routePoints' => $trackPoints,
            'elevationSource' => $route->elevation_source,
        ]);
    </script>
@endpush
