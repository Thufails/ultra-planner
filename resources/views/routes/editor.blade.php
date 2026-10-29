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
            'distance_from_start_meters' => $point->distance_from_start_meters === null ? null : (int) $point->distance_from_start_meters,
            'track_segment_index' => (int) $point->track_segment_index,
        ])->values()
        : [];
@endphp

@section('title', ($isEditing ? 'Edit Rute' : 'Buat Rute') . ' · Ultra Planner')

@section('content')
    <section class="editor-heading">
        <div>
            <a class="back-link" href="{{ route('routes.index') }}">Kembali ke rute saya</a>
            <p class="eyebrow">ROUTE BUILDER / {{ $isEditing ? 'EDIT' : 'RUTE BARU' }}</p>
            <h1>{{ $isEditing ? 'Edit rute' : 'Rancang rute' }}</h1>
            <p class="page-subtitle">Klik peta untuk menambahkan titik kontrol. Geser marker untuk mengubah posisinya.</p>
        </div>
        @if ($isEditing)
            <a class="button button-secondary" href="{{ route('routes.show', $route) }}">Lihat rute</a>
        @endif
    </section>

    <section class="editor-card" data-route-editor>
        <div class="editor-sidebar">
        <div class="editor-toolbar">
            <div class="route-name-field">
                <label for="route-name">Nama rute</label>
                <input id="route-name" name="name" type="text" maxlength="150" required
                    placeholder="Contoh: Long run Minggu pagi" value="{{ $route?->name ?? '' }}">
            </div>
            <div class="toolbar-actions">
                <button class="button button-secondary" type="button" data-locate>◎ Lokasi saya</button>
                <button class="button button-secondary" type="button" data-undo disabled>Batalkan</button>
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
                    <span class="location-search-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none"><circle cx="10.5" cy="10.5" r="6.5"/><path d="m15.5 15.5 5 5"/></svg></span>
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
                <span class="metric-icon metric-icon-distance" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none"><path d="M4 17.5c2.5-5 5.5-7.5 8-6s4.5 3 8-5"/><circle cx="4" cy="17.5" r="1.5"/><circle cx="20" cy="6.5" r="1.5"/></svg></span>
                <span class="metric-copy">
                    <strong data-distance>0.0 km</strong>
                    <small data-distance-label>perkiraan jarak garis</small>
                </span>
            </article>
            <article class="metric-card">
                <span class="metric-icon metric-icon-points" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="7.5"/><circle cx="12" cy="12" r="2"/><path d="M12 2v2.5M22 12h-2.5M12 22v-2.5M2 12h2.5"/></svg></span>
                <span class="metric-copy">
                    <strong data-point-count>0</strong>
                    <small>titik kontrol</small>
                </span>
            </article>
            <article class="metric-card">
                <span class="metric-icon metric-icon-gain" aria-hidden="true">+</span>
                <span class="metric-copy">
                    <strong data-elevation-gain>{{ $route?->elevation_gain_meters !== null ? '+' . number_format($route->elevation_gain_meters) . ' m' : '—' }}</strong>
                    <small>perkiraan tanjakan</small>
                </span>
            </article>
            <article class="metric-card">
                <span class="metric-icon metric-icon-loss" aria-hidden="true">−</span>
                <span class="metric-copy">
                    <strong data-elevation-loss>{{ $route?->elevation_loss_meters !== null ? '−' . number_format($route->elevation_loss_meters) . ' m' : '—' }}</strong>
                    <small>perkiraan turunan</small>
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

        <section class="elevation-panel" aria-labelledby="elevation-heading">
            <div class="elevation-panel-heading">
                <h2 id="elevation-heading">Profil elevasi</h2>
                <p data-elevation-status role="status" aria-live="polite">
                    {{ $route?->elevation_source ? 'Estimasi dari ' . $route->elevation_source . ' · Data terrain 30 m' : 'Gambar rute untuk melihat perkiraan tanjakan dan turunan.' }}
                </p>
            </div>
            <div class="elevation-chart" data-elevation-chart role="img" aria-label="Grafik profil elevasi rute">
                <p>Profil elevasi akan tampil setelah rute memiliki sedikitnya dua titik.</p>
            </div>
            <p class="elevation-disclaimer">Estimasi terrain SRTM 30 m melalui OpenTopoData, bukan pengukuran GPS atau altimeter barometrik. Koordinat rute dikirim ke layanan tersebut untuk mengambil sampel elevasi.</p>
        </section>
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
            'elevationUrl' => route('routes.elevation-preview'),
            'showUrlTemplate' => url('/routes/__ROUTE_ID__'),
            'initialCenter' => [-2.5, 118],
            'initialZoom' => 5,
            'controlPoints' => $controlPoints,
            'routePoints' => $trackPoints,
            'routingMode' => $route?->routing_mode ?? 'direct',
            'distanceMeters' => $route?->distance_meters,
            'elevationGainMeters' => $route?->elevation_gain_meters,
            'elevationLossMeters' => $route?->elevation_loss_meters,
            'elevationSource' => $route?->elevation_source,
        ]);
    </script>
@endpush
