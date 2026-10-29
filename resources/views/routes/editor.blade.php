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
            </div>
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
            <div class="route-summary">
                <div><strong data-point-count>0</strong><span>titik kontrol</span></div>
                <div><strong data-distance>0.0 km</strong><span>perkiraan jarak garis</span></div>
            </div>
            <p class="map-note">Garis saat ini menghubungkan titik secara langsung. Mengikuti jalan dan ekspor GPX akan disambungkan pada tahap berikutnya.</p>
            <div class="save-area">
                <p class="form-message" data-form-message role="status" aria-live="polite"></p>
                <button class="button button-primary" type="button" data-save>
                    {{ $isEditing ? 'Simpan perubahan' : 'Simpan rute' }}
                </button>
            </div>
        </div>
    </section>
@endsection

@push('scripts')
    <script>
        window.routeEditorConfig = @js([
            'mode' => $isEditing ? 'edit' : 'create',
            'storeUrl' => route('routes.store'),
            'updateUrl' => $route ? route('routes.update', $route) : null,
            'showUrlTemplate' => url('/routes/__ROUTE_ID__'),
            'initialCenter' => [-2.5, 118],
            'initialZoom' => 5,
            'controlPoints' => $controlPoints,
            'routePoints' => $trackPoints,
        ]);
    </script>
@endpush
