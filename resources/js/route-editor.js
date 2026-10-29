import L from 'leaflet';
import 'leaflet/dist/leaflet.css';

const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

document.addEventListener('DOMContentLoaded', () => {
    initializeRouteEditor();
    initializeRouteViewer();
    initializeRouteList();
});

function normalizePoints(points = []) {
    return points
        .map((point) => ({
            latitude: Number(point.latitude),
            longitude: Number(point.longitude),
            elevation_meters: point.elevation_meters ?? null,
            track_segment_index: Number(point.track_segment_index ?? 0),
        }))
        .filter((point) => Number.isFinite(point.latitude) && Number.isFinite(point.longitude));
}

function markerIcon(index, total) {
    const kind = index === 0 ? 'start' : index === total - 1 ? 'finish' : 'control';
    const label = kind === 'start' ? 'S' : kind === 'finish' ? 'F' : String(index + 1);

    return L.divIcon({
        className: 'route-marker-shell',
        html: `<span class="route-marker route-marker-${kind}">${label}</span>`,
        iconSize: [30, 30],
        iconAnchor: [15, 15],
    });
}

function createMap(element, config = {}) {
    const center = config.initialCenter ?? [-2.5, 118];
    const zoom = config.initialZoom ?? 5;
    const map = L.map(element, { scrollWheelZoom: true }).setView(center, zoom);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
    }).addTo(map);

    return map;
}

function distanceBetween(pointA, pointB) {
    const earthRadiusMeters = 6_371_000;
    const radians = (degrees) => (degrees * Math.PI) / 180;
    const latitudeDelta = radians(pointB.latitude - pointA.latitude);
    const longitudeDelta = radians(pointB.longitude - pointA.longitude);
    const a = Math.sin(latitudeDelta / 2) ** 2
        + Math.cos(radians(pointA.latitude))
        * Math.cos(radians(pointB.latitude))
        * Math.sin(longitudeDelta / 2) ** 2;

    return 2 * earthRadiusMeters * Math.asin(Math.sqrt(Math.min(1, a)));
}

function distanceOf(points) {
    return points.slice(1).reduce((total, point, index) => total + distanceBetween(points[index], point), 0);
}

function initializeRouteEditor() {
    const editor = document.querySelector('[data-route-editor]');
    const config = window.routeEditorConfig;

    if (!editor || !config) return;

    const mapElement = document.getElementById('route-map');
    const nameInput = document.getElementById('route-name');
    const locateButton = editor.querySelector('[data-locate]');
    const undoButton = editor.querySelector('[data-undo]');
    const clearButton = editor.querySelector('[data-clear]');
    const saveButton = editor.querySelector('[data-save]');
    const message = editor.querySelector('[data-form-message]');
    const countElement = editor.querySelector('[data-point-count]');
    const distanceElement = editor.querySelector('[data-distance]');

    const map = createMap(mapElement, config);
    const markers = L.layerGroup().addTo(map);
    let controlPoints = normalizePoints(config.controlPoints);
    let trackPoints = normalizePoints(config.routePoints);
    let trackWasEdited = false;
    let path = null;
    let userLocationMarker = null;
    let userAccuracyCircle = null;
    let locationWatchActive = false;
    let locationWatchTimer = null;
    let bestLocationAccuracy = Number.POSITIVE_INFINITY;
    let centeredDuringLocationSearch = false;

    function visibleTrack() {
        if (trackWasEdited || trackPoints.length === 0) return controlPoints;

        return trackPoints;
    }

    function updateSummary() {
        const previewPoints = visibleTrack();
        const distance = distanceOf(previewPoints);

        countElement.textContent = String(controlPoints.length);
        distanceElement.textContent = `${(distance / 1000).toFixed(1)} km`;
        undoButton.disabled = controlPoints.length === 0;
        clearButton.disabled = controlPoints.length === 0;
    }

    function render() {
        markers.clearLayers();

        if (path) {
            map.removeLayer(path);
            path = null;
        }

        const previewPoints = visibleTrack();

        if (previewPoints.length >= 2) {
            path = L.polyline(
                previewPoints.map((point) => [point.latitude, point.longitude]),
                { color: '#e16b47', weight: 5, opacity: 0.92, lineCap: 'round', lineJoin: 'round' },
            ).addTo(map);
        }

        controlPoints.forEach((point, index) => {
            const marker = L.marker([point.latitude, point.longitude], {
                icon: markerIcon(index, controlPoints.length),
                draggable: true,
                autoPan: true,
            }).addTo(markers);

            marker.on('dragend', (event) => {
                const position = event.target.getLatLng();
                controlPoints[index] = {
                    ...controlPoints[index],
                    latitude: position.lat,
                    longitude: position.lng,
                };
                trackWasEdited = true;
                render();
            });
        });

        updateSummary();
    }

    function stopLocationSearch(statusMessage, isError = false) {
        if (!locationWatchActive) return;

        locationWatchActive = false;
        if (locationWatchTimer) window.clearTimeout(locationWatchTimer);
        locationWatchTimer = null;
        map.stopLocate();
        locateButton.disabled = false;
        locateButton.textContent = '◎ Lokasi saya';
        showMessage(statusMessage, isError);
    }

    map.on('locationfound', (event) => {
        if (!locationWatchActive || !Number.isFinite(event.accuracy)) return;

        if (!centeredDuringLocationSearch) {
            map.setView(event.latlng, Math.max(map.getZoom(), 15), { animate: true });
            centeredDuringLocationSearch = true;
        }

        if (event.accuracy < bestLocationAccuracy) {
            bestLocationAccuracy = event.accuracy;

            if (userLocationMarker) map.removeLayer(userLocationMarker);
            if (userAccuracyCircle) map.removeLayer(userAccuracyCircle);

            userAccuracyCircle = L.circle(event.latlng, {
                radius: event.accuracy,
                color: '#3478d4',
                weight: 1,
                fillColor: '#3478d4',
                fillOpacity: 0.1,
                interactive: false,
            }).addTo(map);

            userLocationMarker = L.marker(event.latlng, {
                interactive: false,
                icon: L.divIcon({
                    className: 'user-location-marker-shell',
                    html: '<span class="user-location-marker"></span>',
                    iconSize: [22, 22],
                    iconAnchor: [11, 11],
                }),
            }).addTo(map);

            map.panTo(event.latlng, { animate: true });
        }

        if (bestLocationAccuracy <= 50) {
            stopLocationSearch(`Posisi ditemukan (akurasi sekitar ±${Math.round(bestLocationAccuracy)} m). Tidak disimpan sebagai titik rute.`);
        } else {
            showMessage(`Posisi terbaik sementara: akurasi sekitar ±${Math.round(bestLocationAccuracy)} m. Sedang mencari fix yang lebih baik…`);
        }
    });

    map.on('locationerror', (event) => {
        const errorMessages = {
            1: 'Izin lokasi ditolak. Izinkan akses lokasi di browser, lalu coba lagi.',
            2: 'Posisi belum tersedia. Pastikan layanan lokasi perangkat aktif.',
            3: 'Pencarian lokasi terlalu lama. Coba lagi.',
        };

        stopLocationSearch(errorMessages[event.code] ?? 'Lokasi tidak dapat ditemukan. Coba lagi.', true);
    });

    locateButton.addEventListener('click', () => {
        bestLocationAccuracy = Number.POSITIVE_INFINITY;
        centeredDuringLocationSearch = false;
        locationWatchActive = true;
        locateButton.disabled = true;
        locateButton.textContent = 'Mencari lokasi…';
        showMessage('Meminta posisi perangkat. Mencari hasil terbaik hingga 12 detik…');

        map.locate({
            setView: false,
            watch: true,
            enableHighAccuracy: true,
            timeout: 15000,
            maximumAge: 0,
        });

        locationWatchTimer = window.setTimeout(() => {
            if (Number.isFinite(bestLocationAccuracy)) {
                const accuracy = Math.round(bestLocationAccuracy);
                const warning = accuracy > 250
                    ? ' Sumber lokasi perangkat masih cukup kasar; geser peta secara manual untuk mengoreksi.'
                    : '';
                stopLocationSearch(`Posisi terbaik ditemukan (akurasi sekitar ±${accuracy} m).${warning} Posisi ini tidak disimpan sebagai titik rute.`);
            } else {
                stopLocationSearch('Belum mendapat posisi dari perangkat. Coba lagi atau cek izin dan layanan lokasi.', true);
            }
        }, 12000);
    });

    map.on('click', (event) => {
        controlPoints.push({
            latitude: event.latlng.lat,
            longitude: event.latlng.lng,
            elevation_meters: null,
            track_segment_index: 0,
        });
        trackWasEdited = true;

        if (controlPoints.length === 1) {
            map.setView(event.latlng, Math.max(map.getZoom(), 13));
        }

        render();
    });

    undoButton.addEventListener('click', () => {
        controlPoints.pop();
        trackWasEdited = true;
        render();
        message.textContent = 'Titik kontrol terakhir dihapus. Perubahan tersimpan setelah kamu menekan Simpan.';
        message.classList.remove('is-error');
    });

    clearButton.addEventListener('click', () => {
        controlPoints = [];
        trackWasEdited = true;
        render();
        message.textContent = 'Semua titik kontrol dihapus dari rancangan ini.';
        message.classList.remove('is-error');
    });

    saveButton.addEventListener('click', async () => {
        message.textContent = '';
        message.classList.remove('is-error');

        if (!nameInput.value.trim()) {
            nameInput.focus();
            showMessage('Isi nama rute terlebih dahulu.', true);
            return;
        }

        if (controlPoints.length < 2) {
            showMessage('Tambahkan minimal dua titik kontrol pada peta.', true);
            return;
        }

        const submittedTrack = trackWasEdited || trackPoints.length === 0
            ? controlPoints
            : trackPoints;
        const payload = {
            name: nameInput.value.trim(),
            control_points: controlPoints.map(({ latitude, longitude }) => ({ latitude, longitude })),
            route_points: submittedTrack.map(({ latitude, longitude, elevation_meters, track_segment_index }) => ({
                latitude,
                longitude,
                elevation_meters,
                track_segment_index,
            })),
        };

        saveButton.disabled = true;
        saveButton.textContent = 'Menyimpan…';

        try {
            const isEditing = config.mode === 'edit';
            const response = await fetch(isEditing ? config.updateUrl : config.storeUrl, {
                method: isEditing ? 'PATCH' : 'POST',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
                body: JSON.stringify(payload),
            });
            const result = await response.json().catch(() => ({}));

            if (!response.ok) {
                const validationMessage = Object.values(result.errors ?? {}).flat()[0];
                throw new Error(validationMessage ?? result.message ?? 'Rute gagal disimpan. Coba lagi.');
            }

            const routeId = result.id ?? config.updateUrl.split('/').pop();
            window.location.assign(config.showUrlTemplate.replace('__ROUTE_ID__', routeId));
        } catch (error) {
            showMessage(error.message || 'Terjadi kendala saat menyimpan rute.', true);
        } finally {
            saveButton.disabled = false;
            saveButton.textContent = config.mode === 'edit' ? 'Simpan perubahan' : 'Simpan rute';
        }
    });

    function showMessage(text, isError = false) {
        message.textContent = text;
        message.classList.toggle('is-error', isError);
    }

    const bounds = L.latLngBounds(
        [...trackPoints, ...controlPoints].map((point) => [point.latitude, point.longitude]),
    );
    if (bounds.isValid()) map.fitBounds(bounds.pad(0.18), { maxZoom: 15 });

    render();
    window.setTimeout(() => map.invalidateSize(), 0);
}

function initializeRouteViewer() {
    const mapElement = document.getElementById('route-viewer-map');
    const config = window.routeViewerConfig;

    if (!mapElement || !config) return;

    const map = createMap(mapElement, config);
    const controlPoints = normalizePoints(config.controlPoints);
    const trackPoints = normalizePoints(config.routePoints);
    const visiblePoints = trackPoints.length > 0 ? trackPoints : controlPoints;

    if (visiblePoints.length >= 2) {
        L.polyline(
            visiblePoints.map((point) => [point.latitude, point.longitude]),
            { color: '#e16b47', weight: 5, opacity: 0.92, lineCap: 'round', lineJoin: 'round' },
        ).addTo(map);
    }

    controlPoints.forEach((point, index) => {
        L.marker([point.latitude, point.longitude], {
            icon: markerIcon(index, controlPoints.length),
            interactive: false,
        }).addTo(map);
    });

    const bounds = L.latLngBounds(visiblePoints.map((point) => [point.latitude, point.longitude]));
    if (bounds.isValid()) map.fitBounds(bounds.pad(0.18), { maxZoom: 15 });

    window.setTimeout(() => map.invalidateSize(), 0);
}

function initializeRouteList() {
    document.querySelectorAll('[data-delete-url]').forEach((button) => {
        button.addEventListener('click', async () => {
            if (!window.confirm('Hapus rute ini? Titik-titik yang terkait juga akan dihapus.')) return;

            button.disabled = true;

            try {
                const response = await fetch(button.dataset.deleteUrl, {
                    method: 'DELETE',
                    credentials: 'same-origin',
                    headers: {
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                });

                if (!response.ok) throw new Error('Rute gagal dihapus. Coba lagi.');

                const card = button.closest('[data-route-card]');
                card?.remove();

                if (document.querySelectorAll('[data-route-card]').length === 0) {
                    window.location.reload();
                }
            } catch (error) {
                button.disabled = false;
                showToast(error.message || 'Terjadi kendala saat menghapus rute.');
            }
        });
    });
}

function showToast(message) {
    const toast = document.querySelector('[data-toast]');
    if (!toast) return;

    toast.textContent = message;
    toast.classList.add('is-visible');
    window.setTimeout(() => toast.classList.remove('is-visible'), 4000);
}
