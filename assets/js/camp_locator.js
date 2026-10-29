/**
 * Frontend Controller for Relief Camp Locator & Maps
 * Pan-India Evacuation Shelters & Geospatial Proximity
 * Flood Risk Prediction & Relief Camp Locator
 */

let mapAdapter = null;
let isUserGpsActive = false;
let currentUserPos = null;
let campsData = [];

document.addEventListener('DOMContentLoaded', () => {
    // 1. Initialize Map via Adapter (Centered on India by default)
    const defaultCenter = {
        lat: window.APP_CONFIG?.defaultLat || 21.7679,
        lng: window.APP_CONFIG?.defaultLng || 78.8718
    };

    mapAdapter = new MapAdapter('campMap', {
        lat: defaultCenter.lat,
        lng: defaultCenter.lng,
        zoom: 5
    });

    // Add native on-map control button for Recenter / Detect Location
    addRecenterControl();

    // 2. Fetch all operational relief camps on load (Pan-India view)
    fetchCamps();

    // 3. Filter Controls Listeners
    document.getElementById('btnApplyFilter')?.addEventListener('click', () => {
        fetchCamps();
    });

    document.getElementById('filterZone')?.addEventListener('change', (e) => {
        const zoneId = e.target.value;
        const radiusSelect = document.getElementById('filterRadius');
        // When user chooses "All Monitoring Zones", default back to Pan-India
        if (!zoneId && radiusSelect) {
            radiusSelect.value = 'all';
        }
        fetchCamps();
    });

    document.getElementById('filterRadius')?.addEventListener('change', () => {
        fetchCamps();
    });

    document.getElementById('filterStatus')?.addEventListener('change', () => {
        fetchCamps();
    });

    // Pan-India Reset Button
    document.getElementById('btnResetView')?.addEventListener('click', () => {
        isUserGpsActive = false;
        currentUserPos = null;
        const zoneSelect = document.getElementById('filterZone');
        const radiusSelect = document.getElementById('filterRadius');
        const statusSelect = document.getElementById('filterStatus');

        if (zoneSelect) zoneSelect.value = '';
        if (radiusSelect) radiusSelect.value = 'all';
        if (statusSelect) statusSelect.value = 'Operational';

        if (mapAdapter) mapAdapter.clearCircle();
        fetchCamps();
    });

    // Locate Me Button (Explicit user-triggered GPS)
    document.getElementById('btnLocateMe')?.addEventListener('click', () => {
        triggerLocateUser();
    });
});

/**
 * Query backend camps endpoint with current filters
 */
async function fetchCamps() {
    const listContainer = document.getElementById('campsListContainer');
    const radius = document.getElementById('filterRadius')?.value || 'all';
    const status = document.getElementById('filterStatus')?.value || 'Operational';
    const zoneId = document.getElementById('filterZone')?.value || '';
    const urlParams = new URLSearchParams(window.location.search);
    const damId = urlParams.get('dam_id');

    let url = `${window.APP_CONFIG.baseUrl}/api/camps_nearby.php?status=${encodeURIComponent(status)}&radius=${encodeURIComponent(radius)}`;

    if (isUserGpsActive && currentUserPos) {
        url += `&lat=${currentUserPos.lat}&lng=${currentUserPos.lng}`;
    }

    if (zoneId) {
        url += `&zone_id=${encodeURIComponent(zoneId)}`;
    }

    if (damId) {
        url += `&dam_id=${encodeURIComponent(damId)}`;
    }

    try {
        if (listContainer) {
            listContainer.innerHTML = `
                <div class="text-center py-5 text-muted">
                    <div class="spinner-border spinner-border-sm text-primary mb-2" role="status"></div>
                    <div>Loading operational shelters...</div>
                </div>`;
        }

        const res = await fetch(url);
        const json = await res.json();

        if (!json.success || !json.data) {
            throw new Error(json.message || 'Error fetching relief shelters.');
        }

        campsData = json.data.camps || [];
        renderCamps(campsData, radius);

    } catch (e) {
        console.error('Camp Query Error:', e);
        if (listContainer) {
            listContainer.innerHTML = `<div class="alert alert-danger m-2 small">Failed to load shelters: ${escapeHtml(e.message)}</div>`;
        }
    }
}

/**
 * Render camps onto the map and sidebar list, then auto-fit map bounds
 */
function renderCamps(camps, radius) {
    const listContainer = document.getElementById('campsListContainer');
    const badge = document.getElementById('campCountBadge');
    if (badge) {
        badge.textContent = `${camps.length} found`;
    }

    if (!mapAdapter) return;

    // Clear existing markers and any active radius circle
    mapAdapter.clearMarkers();
    mapAdapter.clearCircle();

    // If user GPS is active, add user pin and optional radius circle
    if (isUserGpsActive && currentUserPos) {
        mapAdapter.addMarker(currentUserPos.lat, currentUserPos.lng, {
            title: 'Your Detected Location',
            color: 'blue',
            popup: '<strong>Your Location</strong><br><small>Detected via browser GPS</small>'
        });

        if (radius !== 'all' && !isNaN(parseFloat(radius)) && parseFloat(radius) > 0) {
            mapAdapter.drawCircle(currentUserPos.lat, currentUserPos.lng, parseFloat(radius));
        }
    }

    if (!camps || camps.length === 0) {
        if (listContainer) {
            listContainer.innerHTML = `
                <div class="text-center py-5 px-3 text-muted">
                    <i class="bi bi-info-circle fs-3 text-secondary d-block mb-2"></i>
                    <h6>No shelters found</h6>
                    <p class="small mb-0">Try setting Search Radius to "All India" or choosing "All Camps" in the filters above.</p>
                </div>`;
        }
        return;
    }

    if (listContainer) {
        listContainer.innerHTML = '';
    }

    camps.forEach((camp) => {
        const campLat = parseFloat(camp.latitude);
        const campLng = parseFloat(camp.longitude);
        const distanceText = (camp.distance_km !== undefined && camp.distance_km !== null) 
            ? `${camp.distance_km} km away` 
            : '';
        const availableBeds = camp.available_beds !== undefined 
            ? parseInt(camp.available_beds) 
            : Math.max(0, parseInt(camp.total_capacity) - parseInt(camp.current_occupancy));
        const campName = camp.camp_name || camp.name || 'Relief Camp';
        const stateName = camp.zone_state || camp.state || '';
        const zoneName = camp.zone_name || camp.district || '';

        // Create Popup HTML
        const popupHtml = `
            <div style="min-width: 220px;">
                <div class="d-flex align-items-center justify-content-between mb-1">
                    <span class="badge bg-secondary-subtle text-dark border small">${escapeHtml(stateName || zoneName)}</span>
                    <strong class="text-${camp.status_badge || 'success'} small">${escapeHtml(camp.status || 'Operational')}</strong>
                </div>
                <h6 class="fw-bold mb-1 text-dark">${escapeHtml(campName)}</h6>
                <p class="small text-muted mb-2">${escapeHtml(camp.address || '')}</p>
                <div class="d-flex justify-content-between small mb-1">
                    <span>Available Capacity:</span> <strong>${availableBeds} / ${camp.total_capacity} beds</strong>
                </div>
                ${distanceText ? `<div class="d-flex justify-content-between small mb-2 text-muted"><span>Distance:</span> <strong>${distanceText}</strong></div>` : ''}
                <div class="d-grid gap-1 mt-2">
                    <a href="tel:${escapeHtml(camp.contact_phone || '')}" class="btn btn-sm btn-outline-success">
                        <i class="bi bi-telephone-fill me-1"></i> Call Officer (${escapeHtml(camp.contact_person || 'Disaster Cell')})
                    </a>
                    <button class="btn btn-sm btn-primary" onclick="getDirectionsTo(${campLat}, ${campLng})">
                        <i class="bi bi-cursor-fill me-1"></i> Get Directions
                    </button>
                </div>
            </div>`;

        // Add Marker to Map
        mapAdapter.addMarker(campLat, campLng, {
            title: campName,
            color: camp.marker_color || 'green',
            popup: popupHtml
        });

        // Add Card to Sidebar
        if (listContainer) {
            const card = document.createElement('div');
            card.className = `card camp-card p-3 mb-2 rounded-3 status-${(camp.status || '').toLowerCase()}`;
            card.innerHTML = `
                <div class="d-flex justify-content-between align-items-start mb-1">
                    <div>
                        <span class="badge bg-light text-dark border me-1 small" style="font-size: 0.72rem;">${escapeHtml(stateName || zoneName)}</span>
                        <h6 class="fw-bold mb-0 text-dark d-inline-block" style="font-size: 0.95rem;">${escapeHtml(campName)}</h6>
                    </div>
                    <span class="badge bg-${camp.status_badge || 'success'} small">${escapeHtml(camp.status || 'Operational')}</span>
                </div>
                <p class="small text-muted mb-2 text-truncate" title="${escapeHtml(camp.address || '')}">
                    <i class="bi bi-geo-alt me-1"></i>${escapeHtml(camp.address || '')}
                </p>
                <div class="d-flex justify-content-between small text-muted mb-1">
                    <span>${distanceText ? `<i class="bi bi-speedometer2 me-1"></i>${distanceText}` : `<i class="bi bi-building me-1"></i>${escapeHtml(zoneName)}`}</span>
                    <span class="fw-semibold text-dark">${availableBeds} beds vacant</span>
                </div>
                <div class="progress mb-2" style="height: 5px;">
                    <div class="progress-bar bg-${camp.status_badge || 'success'}" role="progressbar" style="width: ${camp.occupancy_percentage || 0}%;"></div>
                </div>
                <div class="d-flex gap-1 mt-1">
                    <button class="btn btn-light btn-sm flex-fill text-primary" onclick="panToCamp(${campLat}, ${campLng})">
                        <i class="bi bi-eye me-1"></i> View on Map
                    </button>
                    <button class="btn btn-primary btn-sm flex-fill" onclick="getDirectionsTo(${campLat}, ${campLng})">
                        <i class="bi bi-cursor-fill me-1"></i> Route
                    </button>
                </div>`;

            listContainer.appendChild(card);
        }
    });

    // Auto-fit map bounds to encompass all returned camp markers across India
    mapAdapter.fitToMarkers();

    // If dam_id was specified in query string, auto-highlight and focus on that dam's designated shelter
    const urlParams = new URLSearchParams(window.location.search);
    const damId = urlParams.get('dam_id');
    if (damId && camps.length > 0) {
        const targetCampIndex = camps.findIndex(c => String(c.dam_id) === String(damId));
        if (targetCampIndex !== -1 && mapAdapter.markers[targetCampIndex]) {
            const targetCamp = camps[targetCampIndex];
            const marker = mapAdapter.markers[targetCampIndex];
            mapAdapter.setCenter(parseFloat(targetCamp.latitude), parseFloat(targetCamp.longitude), 14);
            if (marker.openPopup) {
                marker.openPopup();
            } else if (mapAdapter.provider === 'google' && typeof google !== 'undefined') {
                google.maps.event.trigger(marker, 'click');
            }
        }
    }
}

/**
 * Smooth pan to selected camp on the map
 */
function panToCamp(lat, lng) {
    if (!mapAdapter) return;
    mapAdapter.setCenter(lat, lng, 15);
}

/**
 * Route directions from current position (or default) to camp
 */
function getDirectionsTo(destLat, destLng) {
    if (!mapAdapter) return;
    const originLat = (currentUserPos && isUserGpsActive) ? currentUserPos.lat : 21.7679;
    const originLng = (currentUserPos && isUserGpsActive) ? currentUserPos.lng : 78.8718;
    mapAdapter.renderDirections(originLat, originLng, destLat, destLng);
}

function escapeHtml(str) {
    if (!str) return '';
    return String(str).replace(/[&<>'"]/g, 
        tag => ({
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            "'": '&#39;',
            '"': '&quot;'
        }[tag] || tag)
    );
}

/**
 * Request user's GPS position with non-blocking feedback and resilient fallback
 */
function triggerLocateUser() {
    const btn = document.getElementById('btnLocateMe');
    const origHtml = btn ? btn.innerHTML : '';
    if (btn) {
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Locating...';
        btn.disabled = true;
    }

    const fallbackPos = {
        lat: 19.8762,
        lng: 75.3433,
        name: 'Maharashtra Central (Godavari Region)'
    };

    if (!navigator.geolocation) {
        if (btn) {
            btn.innerHTML = origHtml;
            btn.disabled = false;
        }
        showGeoToast(
            'GPS Not Supported',
            'Your browser does not support geolocation. Defaulted to Maharashtra Central.',
            'warning'
        );
        applyLocation(fallbackPos.lat, fallbackPos.lng);
        return;
    }

    navigator.geolocation.getCurrentPosition(
        (pos) => {
            if (btn) {
                btn.innerHTML = origHtml;
                btn.disabled = false;
            }
            showGeoToast(
                'Location Detected',
                'Displaying operational relief shelters near your current GPS coordinates.',
                'success'
            );
            applyLocation(pos.coords.latitude, pos.coords.longitude);
        },
        (err) => {
            console.warn('Geolocation failed or timed out:', err.message);
            if (btn) {
                btn.innerHTML = origHtml;
                btn.disabled = false;
            }
            // Gentle non-blocking notification - NO window.alert()
            showGeoToast(
                'Location Fallback Active',
                'Using default regional location (Maharashtra / India Center).',
                'info'
            );
            applyLocation(fallbackPos.lat, fallbackPos.lng);
        },
        {
            enableHighAccuracy: false,
            timeout: 10000,
            maximumAge: 60000
        }
    );
}

/**
 * Apply target coordinates to state and refresh shelters & map
 */
function applyLocation(lat, lng) {
    isUserGpsActive = true;
    currentUserPos = { lat, lng };
    fetchCamps();
    if (mapAdapter) {
        mapAdapter.setCenter(lat, lng, 10);
    }
}

/**
 * Non-blocking Bootstrap toast notification
 */
function showGeoToast(title, message, type = 'info') {
    const toastEl = document.getElementById('geoNotificationToast');
    if (!toastEl) {
        console.log(`[Toast] ${title}: ${message}`);
        return;
    }

    const iconMap = {
        success: 'bi-check-circle-fill text-success',
        info: 'bi-info-circle-fill text-primary',
        warning: 'bi-exclamation-triangle-fill text-warning',
        danger: 'bi-x-circle-fill text-danger'
    };

    const iconEl = document.getElementById('geoToastIcon');
    const titleEl = document.getElementById('geoToastTitle');
    const msgEl = document.getElementById('geoToastMessage');

    if (iconEl) iconEl.className = `bi ${iconMap[type] || iconMap.info} fs-5`;
    if (titleEl) titleEl.textContent = title;
    if (msgEl) msgEl.textContent = message;

    if (typeof bootstrap !== 'undefined' && bootstrap.Toast) {
        const toast = bootstrap.Toast.getOrCreateInstance(toastEl, { delay: 5000 });
        toast.show();
    } else {
        toastEl.classList.add('show');
        setTimeout(() => toastEl.classList.remove('show'), 5000);
    }
}

/**
 * Adds a native Leaflet on-map button to re-center / detect location
 */
function addRecenterControl() {
    if (!mapAdapter || !mapAdapter.map || mapAdapter.provider !== 'leaflet' || typeof L === 'undefined') {
        return;
    }

    const LocateControl = L.Control.extend({
        options: { position: 'topright' },
        onAdd: function() {
            const container = L.DomUtil.create('div', 'leaflet-bar leaflet-control');
            const btn = L.DomUtil.create('a', 'leaflet-locate-btn shadow-sm', container);
            btn.href = '#';
            btn.title = 'Recenter / Detect My Location';
            btn.role = 'button';
            btn.setAttribute('aria-label', 'Recenter / Detect My Location');
            btn.innerHTML = '<i class="bi bi-crosshair text-danger fs-6"></i>';
            btn.style.cssText = 'display:flex;align-items:center;justify-content:center;width:34px;height:34px;background:#fff;text-decoration:none;cursor:pointer;';

            L.DomEvent.disableClickPropagation(container);
            L.DomEvent.disableScrollPropagation(container);

            btn.addEventListener('click', function(e) {
                e.preventDefault();
                triggerLocateUser();
            });
            return container;
        }
    });

    mapAdapter.map.addControl(new LocateControl());
}

