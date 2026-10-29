@extends('layouts.app')

@php
    $isEditing = $route !== null;
    $controlPoints = $route
        ? $route->routeControlPoints->map(fn ($point) => [
            'latitude' => (float) $point->latitude,
            'longitude' => (float) $point->longitude,
        ])->values()
        : [];
    $trackPoints = $route
        ? $route->routePoints->map(fn ($point) => [
            'latitude' => (float) $point->latitude,
            'longitude' => (float) $point->longitude,
            'elevation_meters' => $point->elevation_meters === null ? null : (float) $point->elevation_meters,
            'track_segment_index' => (int) $point->track_segment_index,
        ])->values()
        : [];
@endphp

@section('title', ($isEditing ? 'Edit Rute' : 'Buat Rute') . ' · Ultra Planner')

@section('content')
    <section class="editor-heading">
        <div>
            <a class="back-link" href="{{ route('routes.index') }}">← Kembali ke rute saya</a>
            <p class="eyebrow">ROUTE BUILDER / {{ $isEditing ? 'EDIT' : 'RUTE BARU' }}</p>
            <h1>{{ $isEditing ? 'Edit rute' : 'Rancang rute' }}</h1>
            <p class="page-subtitle">Klik peta untuk menambahkan titik kontrol. Geser marker untuk mengubah posisinya.</p>
        </div>
        @if ($isEditing)
            <a class="button button-secondary" href="{{ route('routes.show', $route) }}">Lihat rute</a>
        @endif
    </section>

    <section class="editor-card" data-route-editor>
        <div class="editor-toolbar">
            <div class="route-name-field">
                <label for="route-name">Nama rute</label>
                <input id="route-name" name="name" type="text" maxlength="150" required
                    placeholder="Contoh: Long run Minggu pagi" value="{{ $route?->name ?? '' }}">
            </div>
            <div class="toolbar-actions">
                <button class="button button-secondary" type="button" data-locate>◎ Lokasi saya</button>
                <button class="button button-secondary" type="button" data-undo disabled>↶ Undo</button>
                <button class="button button-quiet" type="button" data-clear disabled>Hapus semua</button>
                <button class="button button-primary" type="button" data-save>
                    {{ $isEditing ? 'Simpan perubahan' : 'Simpan rute' }}
                </button>
            </div>
        </div>

        <div class="location-search-row">
            <form class="location-search" data-location-search role="search">
                <label class="location-search-label" for="location-query">Cari daerah atau tempat</label>
                <div class="location-search-controls">
                    <span class="location-search-icon" aria-hidden="true">⌕</span>
                    <input id="location-query" name="q" type="search" maxlength="120" autocomplete="off"
                        placeholder="Contoh: Kota Batu, Jawa Timur" aria-controls="location-search-results">
                    <button class="button button-primary" type="submit" data-location-search-button>Cari</button>
                    <div class="location-search-results" id="location-search-results" data-location-search-results role="listbox" aria-label="Hasil pencarian lokasi" hidden></div>
                </div>
                <div class="location-search-meta">
                    <p data-location-search-status role="status" aria-live="polite">Pencarian dikirim ke Nominatim; hasilnya tidak ditambahkan sebagai titik rute.</p>
                    <small>Gunakan nama daerah atau tempat umum · Data © OpenStreetMap contributors</small>
                </div>
            </form>
        </div>

        <div class="route-metrics" aria-live="polite">
            <article class="metric-card">
                <span class="metric-icon metric-icon-distance" aria-hidden="true">↗</span>
                <span class="metric-copy">
                    <strong data-distance>0.0 km</strong>
                    <small data-distance-label>perkiraan jarak garis</small>
                </span>
            </article>
            <article class="metric-card">
                <span class="metric-icon metric-icon-points" aria-hidden="true">⌖</span>
                <span class="metric-copy">
                    <strong data-point-count>0</strong>
                    <small>titik kontrol</small>
                </span>
            </article>
        </div>

        <div class="routing-options">
            <label class="routing-toggle">
                <input type="checkbox" data-follow-roads @checked($route?->routing_mode === 'foot')>
                <span class="routing-toggle-track" aria-hidden="true"></span>
                <span class="routing-toggle-copy">
                    <strong>Ikuti jalan</strong>
                    <small>Hitung jalur pejalan kaki di antara titik kontrol</small>
                </span>
            </label>
            <div class="routing-status" data-routing-status role="status" aria-live="polite">
                {{ $route?->routing_mode === 'foot' ? 'Jalur tersimpan mengikuti jalan.' : 'Garis lurus antar titik kontrol.' }}
            </div>
            <div class="route-alternatives" data-route-alternatives hidden aria-label="Pilihan jalur"></div>
        </div>

        <div class="map-frame">
            <div id="route-map" aria-label="Peta untuk menggambar rute"></div>
            <div class="map-legend">
                <span><i class="legend-dot legend-start"></i> Mulai</span>
                <span><i class="legend-dot legend-control"></i> Titik kontrol</span>
                <span><i class="legend-dot legend-finish"></i> Selesai</span>
            </div>
        </div>

        <div class="editor-footer">
            <p class="map-note" data-map-note>Rute dihitung dari titik kontrol. Saat Ikuti jalan aktif, koordinat dikirim dan dicatat pada log layanan routing FOSSGIS. <a href="https://www.openstreetmap.org/fixthemap" target="_blank" rel="noreferrer">Perbaiki data peta</a> · © OpenStreetMap contributors.</p>
            <p class="form-message" data-form-message role="status" aria-live="polite"></p>
        </div>
    </section>
@endsection

@push('scripts')
    <script>
        window.routeEditorConfig = @js([
            'mode' => $isEditing ? 'edit' : 'create',
            'storeUrl' => route('routes.store'),
            'updateUrl' => $route ? route('routes.update', $route) : null,
            'searchUrl' => route('routes.location-search'),
            'previewUrl' => route('routes.preview'),
            'showUrlTemplate' => url('/routes/__ROUTE_ID__'),
            'initialCenter' => [-2.5, 118],
            'initialZoom' => 5,
            'controlPoints' => $controlPoints,
            'routePoints' => $trackPoints,
            'routingMode' => $route?->routing_mode ?? 'direct',
            'distanceMeters' => $route?->distance_meters,
        ]);
    </script>
@endpush
