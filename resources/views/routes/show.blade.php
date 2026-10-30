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
            'track_segment_index' => (int) $point->track_segment_index,
        ])->values();
        $waypoints = $route->waypoints->map(fn ($waypoint) => [
            'id' => $waypoint->id,
            'sequence' => $waypoint->sequence,
            'distance_meters' => $waypoint->distance_meters,
            'latitude' => (float) $waypoint->latitude,
            'longitude' => (float) $waypoint->longitude,
            'name' => $waypoint->name,
            'waypoint_type' => $waypoint->waypoint_type,
            'notes' => $waypoint->notes,
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
        <article><span>Jarak lintasan</span><strong>{{ $route->distance_meters ? number_format($route->distance_meters / 1000, 1) . ' km' : 'Belum dihitung' }}</strong></article>
        <article><span>Perkiraan tanjakan</span><strong>{{ $route->elevation_gain_meters !== null ? '+' . number_format($route->elevation_gain_meters) . ' m' : '—' }}</strong></article>
        <article><span>Perkiraan turunan</span><strong>{{ $route->elevation_loss_meters !== null ? '−' . number_format($route->elevation_loss_meters) . ' m' : '—' }}</strong></article>
        <article><span>Titik kontrol</span><strong>{{ number_format($route->routeControlPoints->count()) }}</strong></article>
        <article><span>Titik lintasan</span><strong>{{ number_format($route->track_points_count) }}</strong></article>
    </section>

    <section class="elevation-panel detail-elevation-panel" aria-labelledby="elevation-heading">
        <div class="elevation-panel-heading">
            <h2 id="elevation-heading">Profil elevasi</h2>
            <div class="elevation-panel-meta">
                <p data-elevation-status role="status" aria-live="polite">
                    {{ $route->elevation_source ? 'Estimasi dari ' . $route->elevation_source . ' · Data terrain 30 m' : 'Rute ini belum memiliki data elevasi.' }}
                </p>
                <p class="elevation-gain-summary">Kenaikan elevasi <strong data-elevation-total-gain>{{ $route->elevation_gain_meters !== null ? number_format($route->elevation_gain_meters) . ' m' : '—' }}</strong></p>
            </div>
        </div>
        <div class="elevation-chart" data-elevation-chart role="img" aria-label="Grafik profil elevasi rute">
            <p>Profil elevasi belum tersedia untuk rute ini.</p>
        </div>
        <p class="elevation-disclaimer">Estimasi medan dari data terrain SRTM 30 m, bukan pengukuran GPS atau altimeter barometrik.</p>
    </section>

    <section class="route-planning-panel detail-planning-panel" aria-labelledby="detail-checkpoint-heading">
        <div class="route-planning-heading">
            <div>
                <h2 id="detail-checkpoint-heading">Checkpoint rute</h2>
                <p>Titik otomatis setiap 5 km dan titik finis.</p>
            </div>
            <strong data-checkpoint-count>Memuat…</strong>
        </div>
        <div class="checkpoint-list" data-checkpoint-list aria-live="polite">
            <p class="planning-empty">Checkpoint dihitung dari lintasan tersimpan.</p>
        </div>
    </section>

    <section class="route-planning-panel detail-planning-panel" aria-labelledby="detail-waypoint-heading">
        <div class="route-planning-heading">
            <div>
                <h2 id="detail-waypoint-heading">Titik penting</h2>
                <p>Lokasi bantuan dan penanda lain pada rute.</p>
            </div>
            <a class="button button-secondary" href="{{ route('routes.edit', $route) }}">Tambah / kelola titik</a>
        </div>
        <div class="waypoint-list" data-waypoint-list aria-live="polite">
            <p class="planning-empty">Belum ada titik penting pada rute ini.</p>
        </div>
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
            'waypoints' => $waypoints,
            'checkpointsUrl' => route('routes.checkpoints.index', $route),
        ]);
    </script>
@endpush
