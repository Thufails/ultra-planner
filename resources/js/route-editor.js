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
            distance_from_start_meters: point.distance_from_start_meters === null || point.distance_from_start_meters === undefined
                ? null
                : Number(point.distance_from_start_meters),
            track_segment_index: Number(point.track_segment_index ?? 0),
        }))
        .filter((point) => Number.isFinite(point.latitude) && Number.isFinite(point.longitude));
}

function renderElevationChart(container, profile) {
    const points = profile
        .filter((point) => point.elevation_meters !== null && point.elevation_meters !== undefined
            && Number.isFinite(Number(point.distance_from_start_meters))
            && Number.isFinite(Number(point.elevation_meters)))
        .map((point) => ({
            distance: Number(point.distance_from_start_meters),
            elevation: Number(point.elevation_meters),
        }));

    const totalGainElement = container.closest('.elevation-panel')?.querySelector('[data-elevation-total-gain]');

    if (points.length < 2) {
        const placeholder = document.createElement('p');
        placeholder.textContent = 'Profil elevasi akan tampil setelah rute memiliki sedikitnya dua titik.';
        container.replaceChildren(placeholder);
        if (totalGainElement) totalGainElement.textContent = '—';
        return;
    }

    const namespace = 'http://www.w3.org/2000/svg';
    const make = (tag, attributes = {}) => {
        const element = document.createElementNS(namespace, tag);
        Object.entries(attributes).forEach(([name, value]) => element.setAttribute(name, String(value)));
        return element;
    };
    const width = 1000;
    const height = 280;
    const left = 64;
    const right = 20;
    const top = 20;
    const bottom = 42;
    const plotWidth = width - left - right;
    const plotHeight = height - top - bottom;
    const totalDistance = Math.max(1, ...points.map((point) => point.distance));
    const minimum = Math.min(...points.map((point) => point.elevation));
    const maximum = Math.max(...points.map((point) => point.elevation));
    const rawStep = Math.max(1, (maximum - minimum) / 4);
    const magnitude = 10 ** Math.floor(Math.log10(rawStep));
    const normalizedStep = rawStep / magnitude;
    const step = (normalizedStep <= 1 ? 1 : normalizedStep <= 2 ? 2 : normalizedStep <= 5 ? 5 : 10) * magnitude;
    let minElevation = Math.floor(minimum / step) * step;
    let maxElevation = Math.ceil(maximum / step) * step;
    if (minElevation === maxElevation) {
        minElevation -= step;
        maxElevation += step;
    }
    const elevationRange = maxElevation - minElevation;
    const x = (distance) => left + (distance / totalDistance) * plotWidth;
    const y = (elevation) => top + ((maxElevation - elevation) / elevationRange) * plotHeight;
    const svg = make('svg', {
        viewBox: `0 0 ${width} ${height}`,
        class: 'elevation-svg',
        preserveAspectRatio: 'none',
        'aria-hidden': 'true',
    });

    for (let index = 0; index <= 4; index += 1) {
        const value = maxElevation - step * index;
        const lineY = y(value);
        const grid = make('line', { x1: left, x2: width - right, y1: lineY, y2: lineY, class: 'elevation-grid-line' });
        const label = make('text', { x: left - 9, y: lineY + 4, 'text-anchor': 'end', class: 'elevation-axis-label' });
        label.textContent = `${Math.round(value)} m`;
        svg.append(grid, label);
    }

    const mappedPoints = points.map((point) => ({ ...point, x: x(point.distance), y: y(point.elevation) }));
    let pathData = `M ${mappedPoints[0].x.toFixed(2)} ${mappedPoints[0].y.toFixed(2)}`;
    for (let index = 0; index < mappedPoints.length - 1; index += 1) {
        const previous = mappedPoints[Math.max(0, index - 1)];
        const current = mappedPoints[index];
        const next = mappedPoints[index + 1];
        const following = mappedPoints[Math.min(mappedPoints.length - 1, index + 2)];
        pathData += ` C ${(current.x + (next.x - previous.x) / 6).toFixed(2)} ${(current.y + (next.y - previous.y) / 6).toFixed(2)}, ${(next.x - (following.x - current.x) / 6).toFixed(2)} ${(next.y - (following.y - current.y) / 6).toFixed(2)}, ${next.x.toFixed(2)} ${next.y.toFixed(2)}`;
    }
    svg.append(make('path', { d: pathData, class: 'elevation-line' }));

    const crosshair = make('line', { x1: left, x2: left, y1: top, y2: top + plotHeight, class: 'elevation-crosshair', visibility: 'hidden' });
    const activePoint = make('circle', { cx: left, cy: top, r: 5, class: 'elevation-active-point', visibility: 'hidden' });
    svg.append(crosshair, activePoint);

    const distanceTickCount = 8;
    for (let index = 0; index <= distanceTickCount; index += 1) {
        const fraction = index / distanceTickCount;
        const distance = totalDistance * fraction;
        const label = make('text', {
            x: x(distance),
            y: height - 10,
            'text-anchor': index === 0 ? 'start' : index === distanceTickCount ? 'end' : 'middle',
            class: 'elevation-axis-label',
        });
        label.textContent = `${(distance / 1000).toFixed(1)} km`;
        svg.append(label);
    }

    const tooltip = document.createElement('div');
    tooltip.className = 'elevation-tooltip';
    tooltip.hidden = true;
    const tooltipDistance = document.createElement('strong');
    const tooltipElevation = document.createElement('span');
    tooltip.append(tooltipDistance, tooltipElevation);
    container.replaceChildren(svg, tooltip);

    let gainAnchor = points[0].elevation;
    let totalGain = 0;
    points.slice(1).forEach((point) => {
        const change = point.elevation - gainAnchor;
        if (change >= 3) {
            totalGain += change;
            gainAnchor = point.elevation;
        } else if (change <= -3) {
            gainAnchor = point.elevation;
        }
    });
    if (totalGainElement) totalGainElement.textContent = `${Math.round(totalGain).toLocaleString('id-ID')} m`;

    const showNearestPoint = (event) => {
        const bounds = svg.getBoundingClientRect();
        if (bounds.width === 0) return;

        const pointerX = left + Math.max(0, Math.min(1, (event.clientX - bounds.left) / bounds.width)) * plotWidth;
        let low = 0;
        let high = mappedPoints.length - 1;
        while (low < high) {
            const middle = Math.floor((low + high) / 2);
            if (mappedPoints[middle].x < pointerX) low = middle + 1;
            else high = middle;
        }
        const nearestIndex = low > 0
            && Math.abs(mappedPoints[low - 1].x - pointerX) < Math.abs(mappedPoints[low].x - pointerX)
            ? low - 1
            : low;
        const point = mappedPoints[nearestIndex];

        crosshair.setAttribute('x1', point.x);
        crosshair.setAttribute('x2', point.x);
        crosshair.setAttribute('visibility', 'visible');
        activePoint.setAttribute('cx', point.x);
        activePoint.setAttribute('cy', point.y);
        activePoint.setAttribute('visibility', 'visible');
        tooltipDistance.textContent = `Jarak: ${(point.distance / 1000).toFixed(2)} km`;
        tooltipElevation.textContent = `Elevasi: ${Math.round(point.elevation)} m`;
        tooltip.hidden = false;
        const tooltipLeft = ((point.x - left) / plotWidth) * bounds.width;
        tooltip.style.left = `${Math.max(72, Math.min(bounds.width - 72, tooltipLeft))}px`;
    };

    svg.addEventListener('pointermove', showNearestPoint);
    svg.addEventListener('pointerdown', showNearestPoint);
    svg.addEventListener('pointerleave', () => {
        crosshair.setAttribute('visibility', 'hidden');
        activePoint.setAttribute('visibility', 'hidden');
        tooltip.hidden = true;
    });
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
    const distanceLabel = editor.querySelector('[data-distance-label]');
    const followRoadsToggle = editor.querySelector('[data-follow-roads]');
    const routingStatus = editor.querySelector('[data-routing-status]');
    const alternativesContainer = editor.querySelector('[data-route-alternatives]');
    const elevationGainElement = editor.querySelector('[data-elevation-gain]');
    const elevationLossElement = editor.querySelector('[data-elevation-loss]');
    const elevationStatus = editor.querySelector('[data-elevation-status]');
    const elevationChart = editor.querySelector('[data-elevation-chart]');

    const map = createMap(mapElement, config);
    initializeLocationSearch(map, config);
    const markers = L.layerGroup().addTo(map);
    let controlPoints = normalizePoints(config.controlPoints);
    let trackPoints = normalizePoints(config.routePoints);
    let routingMode = config.routingMode === 'foot' ? 'foot' : 'direct';
    let routeIsCurrent = routingMode === 'direct' || trackPoints.length >= 2;
    let routedDistanceMeters = Number(config.distanceMeters) || null;
    let routeCandidates = [];
    let routeDebounceTimer = null;
    let routeRequestGeneration = 0;
    let path = null;
    let userLocationMarker = null;
    let userAccuracyCircle = null;
    let locationWatchActive = false;
    let locationWatchTimer = null;
    let bestLocationAccuracy = Number.POSITIVE_INFINITY;
    let centeredDuringLocationSearch = false;
    let elevationProfile = [];
    let elevationRequestGeneration = 0;
    let elevationDebounceTimer = null;
    let elevationCalculationPending = false;

    followRoadsToggle.checked = routingMode === 'foot';

    function visibleTrack() {
        if (routingMode !== 'foot' || !routeIsCurrent || trackPoints.length === 0) return controlPoints;

        return trackPoints;
    }

    function updateSummary() {
        const previewPoints = visibleTrack();
        const distance = routingMode === 'foot' && routeIsCurrent && routedDistanceMeters !== null
            ? routedDistanceMeters
            : distanceOf(previewPoints);

        countElement.textContent = String(controlPoints.length);
        distanceElement.textContent = `${(distance / 1000).toFixed(1)} km`;
        distanceLabel.textContent = routingMode === 'foot' && routeIsCurrent
            ? 'jarak mengikuti jalan'
            : 'perkiraan jarak garis';
        undoButton.disabled = controlPoints.length === 0;
        clearButton.disabled = controlPoints.length === 0;
    }

    function renderAlternatives() {
        alternativesContainer.replaceChildren();
        alternativesContainer.hidden = routeCandidates.length < 2;

        routeCandidates.forEach((candidate, index) => {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'route-alternative';
            button.classList.toggle('is-selected', index === selectedRouteCandidate);
            button.setAttribute('aria-pressed', String(index === selectedRouteCandidate));
            button.textContent = `Jalur ${index + 1} · ${(candidate.distance_meters / 1000).toFixed(1)} km`;
            button.addEventListener('click', () => selectRouteCandidate(index));
            alternativesContainer.append(button);
        });
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
                {
                    color: routingMode === 'foot' && routeIsCurrent ? '#3155e5' : '#3867ff',
                    weight: 5,
                    opacity: 0.95,
                    dashArray: routingMode === 'foot' && !routeIsCurrent ? '8 8' : null,
                    lineCap: 'round',
                    lineJoin: 'round',
                },
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
                if (routingMode === 'foot') scheduleRoadRoute();
                else {
                    render();
                    scheduleElevationPreview(controlPoints);
                }
            });
        });

        updateSummary();
        renderAlternatives();
    }

    function setRoutingStatus(text, isError = false) {
        routingStatus.textContent = text;
        routingStatus.classList.toggle('is-error', isError);
    }

    function setElevationMetrics(gain, loss) {
        elevationGainElement.textContent = gain !== null && gain !== undefined && Number.isFinite(Number(gain))
            ? `+${Math.round(Number(gain))} m`
            : '—';
        elevationLossElement.textContent = loss !== null && loss !== undefined && Number.isFinite(Number(loss))
            ? `−${Math.round(Number(loss))} m`
            : '—';
    }

    function clearElevation(messageText = 'Gambar rute untuk melihat perkiraan tanjakan dan turunan.') {
        elevationProfile = [];
        setElevationMetrics(null, null);
        elevationStatus.textContent = messageText;
        elevationStatus.classList.remove('is-error');
        renderElevationChart(elevationChart, elevationProfile);
    }

    function scheduleElevationPreview(points) {
        if (elevationDebounceTimer) window.clearTimeout(elevationDebounceTimer);
        const requestGeneration = ++elevationRequestGeneration;
        const routePoints = normalizePoints(points);

        if (routePoints.length < 2) {
            elevationCalculationPending = false;
            trackPoints = routePoints;
            clearElevation();
            return;
        }

        trackPoints = routePoints;
        elevationCalculationPending = true;
        elevationProfile = [];
        setElevationMetrics(null, null);
        elevationStatus.textContent = 'Mengambil estimasi elevasi SRTM 30 m…';
        elevationStatus.classList.remove('is-error');
        renderElevationChart(elevationChart, elevationProfile);
        elevationDebounceTimer = window.setTimeout(
            () => loadElevationPreview(routePoints, requestGeneration),
            1400,
        );
    }

    async function loadElevationPreview(routePoints, requestGeneration) {
        elevationDebounceTimer = null;

        try {
            const response = await fetch(config.elevationUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
                body: JSON.stringify({
                    route_points: routePoints.map(({ latitude, longitude, track_segment_index }) => ({
                        latitude,
                        longitude,
                        track_segment_index,
                    })),
                }),
            });
            const payload = await response.json().catch(() => ({}));

            if (requestGeneration !== elevationRequestGeneration) return;
            if (!response.ok) {
                const validationMessage = Object.values(payload.errors ?? {}).flat()[0];
                throw new Error(validationMessage ?? payload.message ?? 'Profil elevasi gagal dihitung.');
            }

            trackPoints = normalizePoints(payload.route_points ?? []);
            elevationProfile = Array.isArray(payload.profile) ? payload.profile : [];
            setElevationMetrics(payload.elevation_gain_meters, payload.elevation_loss_meters);
            elevationStatus.textContent = `Estimasi ${payload.elevation_source ?? 'SRTM 30 m'} · ${elevationProfile.length.toLocaleString('id-ID')} sampel medan`;
            elevationStatus.classList.remove('is-error');
            renderElevationChart(elevationChart, elevationProfile);
            render();
        } catch (error) {
            if (requestGeneration !== elevationRequestGeneration) return;

            elevationProfile = [];
            setElevationMetrics(null, null);
            elevationStatus.textContent = `${error.message} Rute tetap bisa disimpan tanpa estimasi elevasi.`;
            elevationStatus.classList.add('is-error');
            renderElevationChart(elevationChart, elevationProfile);
        } finally {
            if (requestGeneration === elevationRequestGeneration) elevationCalculationPending = false;
        }
    }

    function showStoredElevation() {
        const storedProfile = trackPoints
            .filter((point) => point.elevation_meters !== null && point.elevation_meters !== undefined
                && Number.isFinite(Number(point.elevation_meters))
                && Number.isFinite(Number(point.distance_from_start_meters)))
            .map((point) => ({
                distance_from_start_meters: Number(point.distance_from_start_meters),
                elevation_meters: Number(point.elevation_meters),
            }));

        if (storedProfile.length < 2) return false;

        elevationProfile = storedProfile;
        setElevationMetrics(config.elevationGainMeters, config.elevationLossMeters);
        elevationStatus.textContent = `Estimasi ${config.elevationSource ?? 'SRTM 30 m'} · tersimpan bersama rute`;
        renderElevationChart(elevationChart, elevationProfile);

        return true;
    }

    function cancelElevationPreview() {
        elevationRequestGeneration += 1;
        if (elevationDebounceTimer) window.clearTimeout(elevationDebounceTimer);
        elevationDebounceTimer = null;
        elevationCalculationPending = false;
    }

    function selectRouteCandidate(index) {
        const candidate = routeCandidates[index];
        if (!candidate) return;

        selectedRouteCandidate = index;
        trackPoints = normalizePoints(candidate.route_points);
        routedDistanceMeters = Number(candidate.distance_meters) || null;
        routeIsCurrent = true;
        render();
        scheduleElevationPreview(trackPoints);
        const maxSnapDistance = Math.max(0, ...lastSnapDistances);
        setRoutingStatus(maxSnapDistance > 50
            ? `Jalur siap. Titik terjauh tersambung ke jalan sekitar ${Math.round(maxSnapDistance)} m dari lokasi klik.`
            : `Jalur pejalan kaki siap (${(routedDistanceMeters / 1000).toFixed(1)} km).`);
    }

    let selectedRouteCandidate = 0;
    let lastSnapDistances = [];

    async function calculateRoadRoute(requestGeneration) {
        if (requestGeneration !== routeRequestGeneration || routingMode !== 'foot') return;

        try {
            const response = await fetch(config.previewUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
                body: JSON.stringify({
                    control_points: controlPoints.map(({ latitude, longitude }) => ({ latitude, longitude })),
                }),
            });
            const payload = await response.json().catch(() => ({}));

            if (requestGeneration !== routeRequestGeneration) return;
            if (!response.ok) {
                const validationMessage = Object.values(payload.errors ?? {}).flat()[0];
                throw new Error(validationMessage ?? payload.message ?? 'Jalur pejalan kaki gagal dihitung.');
            }

            routeCandidates = (payload.routes ?? []).filter((candidate) =>
                Array.isArray(candidate.route_points) && candidate.route_points.length >= 2,
            );
            lastSnapDistances = (payload.snap_distances_meters ?? []).map(Number).filter(Number.isFinite);
            selectedRouteCandidate = 0;

            if (routeCandidates.length === 0) throw new Error('Layanan tidak mengembalikan geometri rute yang bisa ditampilkan.');

            selectRouteCandidate(0);
            if (routeCandidates.length > 1) {
                const maxSnapDistance = Math.max(0, ...lastSnapDistances);
                const snapNotice = maxSnapDistance > 50
                    ? ` Titik terjauh tersambung sekitar ${Math.round(maxSnapDistance)} m dari lokasi klik.`
                    : '';
                setRoutingStatus(`${routeCandidates.length} pilihan jalur tersedia. Pilih yang paling sesuai.${snapNotice}`);
            }
        } catch (error) {
            if (requestGeneration !== routeRequestGeneration) return;

            routeIsCurrent = false;
            routedDistanceMeters = null;
            routeCandidates = [];
            render();
            setRoutingStatus(`${error.message} Garis putus-putus hanya pratinjau langsung; rute belum siap disimpan.`, true);
        }
    }

    function scheduleRoadRoute() {
        cancelElevationPreview();
        clearElevation('Menunggu jalur selesai dihitung sebelum mengambil elevasi.');
        if (routeDebounceTimer) window.clearTimeout(routeDebounceTimer);
        const requestGeneration = ++routeRequestGeneration;
        routeIsCurrent = false;
        routedDistanceMeters = null;
        routeCandidates = [];
        alternativesContainer.hidden = true;
        render();

        if (controlPoints.length < 2) {
            setRoutingStatus('Tambahkan minimal dua titik kontrol untuk mengikuti jalan.');
            return;
        }

        setRoutingStatus('Menghitung jalur pejalan kaki…');
        routeDebounceTimer = window.setTimeout(() => calculateRoadRoute(requestGeneration), 1100);
    }

    followRoadsToggle.addEventListener('change', () => {
        routingMode = followRoadsToggle.checked ? 'foot' : 'direct';
        routeRequestGeneration += 1;
        if (routeDebounceTimer) window.clearTimeout(routeDebounceTimer);
        routeDebounceTimer = null;
        routeCandidates = [];
        alternativesContainer.hidden = true;

        if (routingMode === 'foot') {
            scheduleRoadRoute();
        } else {
            routeIsCurrent = true;
            trackPoints = controlPoints.map((point) => ({ ...point }));
            routedDistanceMeters = null;
            render();
            scheduleElevationPreview(trackPoints);
            setRoutingStatus('Garis lurus antar titik kontrol.');
        }
    });

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
        if (controlPoints.length >= 40) {
            setRoutingStatus('Maksimal 40 titik kontrol untuk satu rute.');
            return;
        }

        controlPoints.push({
            latitude: event.latlng.lat,
            longitude: event.latlng.lng,
            elevation_meters: null,
            track_segment_index: 0,
        });
        if (controlPoints.length === 1) {
            map.setView(event.latlng, Math.max(map.getZoom(), 13));
        }

        if (routingMode === 'foot') scheduleRoadRoute();
        else {
            render();
            scheduleElevationPreview(controlPoints);
        }
    });

    undoButton.addEventListener('click', () => {
        controlPoints.pop();
        if (routingMode === 'foot') scheduleRoadRoute();
        else {
            render();
            scheduleElevationPreview(controlPoints);
        }
        message.textContent = 'Titik kontrol terakhir dihapus. Perubahan tersimpan setelah kamu menekan Simpan.';
        message.classList.remove('is-error');
    });

    clearButton.addEventListener('click', () => {
        controlPoints = [];
        trackPoints = [];
        if (routingMode === 'foot') scheduleRoadRoute();
        else {
            render();
            scheduleElevationPreview(controlPoints);
        }
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

        if (elevationCalculationPending) {
            showMessage('Tunggu sebentar sampai estimasi elevasi selesai dimuat.', true);
            return;
        }

        if (routingMode === 'foot' && (!routeIsCurrent || trackPoints.length < 2)) {
            showMessage('Tunggu sampai jalur mengikuti jalan selesai dihitung. Jika layanan belum tersedia, matikan opsi Ikuti jalan untuk menyimpan garis langsung.', true);
            return;
        }

        const submittedTrack = trackPoints.length >= 2 ? trackPoints : controlPoints;
        const payload = {
            name: nameInput.value.trim(),
            routing_mode: routingMode,
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
    if (!showStoredElevation()) {
        const initialElevationPoints = trackPoints.length >= 2 ? trackPoints : controlPoints;
        if (initialElevationPoints.length >= 2) scheduleElevationPreview(initialElevationPoints);
    }
    window.setTimeout(() => map.invalidateSize(), 0);
}

function initializeLocationSearch(map, config) {
    const form = document.querySelector('[data-location-search]');
    if (!form) return;

    const input = form.querySelector('#location-query');
    const searchButton = form.querySelector('[data-location-search-button]');
    const resultsContainer = form.querySelector('[data-location-search-results]');
    const status = form.querySelector('[data-location-search-status]');
    input.addEventListener('input', () => {
        resultsContainer.hidden = true;
        resultsContainer.replaceChildren();
    });

    form.addEventListener('submit', async (event) => {
        event.preventDefault();

        const query = input.value.trim();
        if (query.length < 2) {
            status.textContent = 'Ketik minimal dua karakter untuk mencari.';
            status.classList.add('is-error');
            input.focus();
            return;
        }

        const url = new URL(config.searchUrl, window.location.origin);
        url.searchParams.set('q', query);
        searchButton.disabled = true;
        searchButton.textContent = 'Mencari…';
        status.textContent = 'Sedang mencari lokasi…';
        status.classList.remove('is-error');
        resultsContainer.hidden = true;
        resultsContainer.replaceChildren();

        try {
            const response = await fetch(url, {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            });
            const payload = await response.json().catch(() => ({}));

            if (!response.ok) {
                const validationMessage = Object.values(payload.errors ?? {}).flat()[0];
                throw new Error(validationMessage ?? payload.message ?? 'Pencarian gagal. Coba lagi.');
            }

            const results = payload.results ?? [];
            if (results.length === 0) {
                status.textContent = 'Lokasi tidak ditemukan. Coba nama daerah yang lebih spesifik.';
                return;
            }

            results.forEach((result) => {
                const option = document.createElement('button');
                option.type = 'button';
                option.className = 'location-result';
                option.setAttribute('role', 'option');

                const title = document.createElement('strong');
                title.textContent = result.label.split(',')[0];

                const detail = document.createElement('small');
                detail.textContent = result.label;

                option.append(title, detail);
                option.addEventListener('click', () => {
                    selectSearchResult(map, result);
                    input.value = result.label.split(',')[0];
                    resultsContainer.hidden = true;
                    status.textContent = `Peta dipindahkan ke ${result.label}. Lokasi ini tidak disimpan sebagai titik rute.`;
                    status.classList.remove('is-error');
                });

                resultsContainer.append(option);
            });

            resultsContainer.hidden = false;
            status.textContent = `${results.length} hasil ditemukan. Pilih salah satu untuk memindahkan peta.`;
        } catch (error) {
            status.textContent = error.message || 'Layanan pencarian lokasi sedang tidak tersedia.';
            status.classList.add('is-error');
        } finally {
            searchButton.disabled = false;
            searchButton.textContent = 'Cari';
        }
    });
}

function selectSearchResult(map, result) {
    const latitude = Number(result.latitude);
    const longitude = Number(result.longitude);
    const box = Array.isArray(result.bounding_box) ? result.bounding_box.map(Number) : [];
    const broadPlaceTypes = new Set(['country', 'state', 'province', 'region', 'continent']);

    if (box.length === 4 && box.every(Number.isFinite)) {
        const [south, north, west, east] = box;
        const bounds = L.latLngBounds([south, west], [north, east]);

        if (bounds.isValid()) {
            if (!broadPlaceTypes.has(result.place_type)) {
                map.setView([latitude, longitude], 10, { animate: true });
            } else {
                map.fitBounds(bounds.pad(0.08), { maxZoom: 15, animate: true });
            }

            return;
        }
    }

    map.setView([latitude, longitude], 14, { animate: true });
}

function initializeRouteViewer() {
    const mapElement = document.getElementById('route-viewer-map');
    const config = window.routeViewerConfig;

    if (!mapElement || !config) return;

    const map = createMap(mapElement, config);
    const controlPoints = normalizePoints(config.controlPoints);
    const trackPoints = normalizePoints(config.routePoints);
    const visiblePoints = trackPoints.length > 0 ? trackPoints : controlPoints;
    const elevationChart = document.querySelector('[data-elevation-chart]');
    const elevationStatus = document.querySelector('[data-elevation-status]');
    const profile = trackPoints
        .filter((point) => point.elevation_meters !== null && point.elevation_meters !== undefined
            && Number.isFinite(Number(point.elevation_meters))
            && Number.isFinite(Number(point.distance_from_start_meters)))
        .map((point) => ({
            distance_from_start_meters: Number(point.distance_from_start_meters),
            elevation_meters: Number(point.elevation_meters),
        }));

    if (elevationChart) renderElevationChart(elevationChart, profile);
    if (elevationStatus && profile.length >= 2) {
        elevationStatus.textContent = `Estimasi ${config.elevationSource ?? 'SRTM 30 m'} · tersimpan bersama rute`;
    }

    if (visiblePoints.length >= 2) {
        L.polyline(
            visiblePoints.map((point) => [point.latitude, point.longitude]),
            { color: '#3867ff', weight: 5, opacity: 0.95, lineCap: 'round', lineJoin: 'round' },
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
