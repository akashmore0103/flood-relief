/**
 * Resilient Map Adapter (Google Maps API + Leaflet/OpenStreetMap Fallback)
 * Flood Risk Prediction & Relief Camp Locator
 */

class MapAdapter {
    constructor(containerId, options = {}) {
        this.containerId = containerId;
        this.options = Object.assign({
            lat: window.APP_CONFIG?.defaultLat || 10.0159,
            lng: window.APP_CONFIG?.defaultLng || 76.3419,
            zoom: window.APP_CONFIG?.defaultZoom || 11
        }, options);

        this.provider = 'leaflet'; // Default robust fallback
        this.map = null;
        this.markers = [];
        this.circleOverlay = null;
        this.directionsRenderer = null;
        this.directionsService = null;

        this.init();
    }

    init() {
        const container = document.getElementById(this.containerId);
        if (!container) {
            console.error(`Map container #${this.containerId} not found.`);
            return;
        }

        // Detect if Google Maps API is loaded and functional
        const hasGoogle = typeof google !== 'undefined' && typeof google.maps !== 'undefined';

        if (hasGoogle && window.APP_CONFIG?.mapProvider !== 'leaflet') {
            this.provider = 'google';
            this.initGoogleMap(container);
        } else {
            this.provider = 'leaflet';
            this.initLeafletMap(container);
        }
    }

    /**
     * Initialize Google Maps
     */
    initGoogleMap(container) {
        try {
            this.map = new google.maps.Map(container, {
                center: { lat: this.options.lat, lng: this.options.lng },
                zoom: this.options.zoom,
                mapTypeControl: true,
                streetViewControl: false,
                fullscreenControl: true
            });

            this.directionsService = new google.maps.DirectionsService();
            this.directionsRenderer = new google.maps.DirectionsRenderer({
                map: this.map,
                suppressMarkers: false
            });
            console.log('Map initialized with Google Maps Provider.');
        } catch (e) {
            console.warn('Google Maps initialization failed, switching to Leaflet:', e);
            this.provider = 'leaflet';
            this.initLeafletMap(container);
        }
    }

    /**
     * Initialize Leaflet + OpenStreetMap
     */
    initLeafletMap(container) {
        if (typeof L === 'undefined') {
            container.innerHTML = '<div class="alert alert-warning m-3">Leaflet map library is loading or unavailable.</div>';
            return;
        }

        this.map = L.map(this.containerId).setView([this.options.lat, this.options.lng], this.options.zoom);

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
        }).addTo(this.map);

        console.log('Map initialized with Leaflet/OpenStreetMap Provider.');
    }

    /**
     * Add a marker to the map
     */
    addMarker(lat, lng, opts = {}) {
        lat = parseFloat(lat);
        lng = parseFloat(lng);
        const title = opts.title || '';
        const color = opts.color || 'green'; // green, orange, red, blue
        const popupContent = opts.popup || null;

        if (this.provider === 'google') {
            const iconUrl = this.getGooglePinUrl(color);
            const marker = new google.maps.Marker({
                position: { lat, lng },
                map: this.map,
                title: title,
                icon: iconUrl
            });

            if (popupContent) {
                const infoWindow = new google.maps.InfoWindow({ content: popupContent });
                marker.addListener('click', () => {
                    infoWindow.open(this.map, marker);
                });
            }

            this.markers.push(marker);
            return marker;
        } else {
            // Leaflet Marker with customized SVG or animated radar pin icon
            const isRadar = (opts.pulsing || color === 'red' || opts.radar);
            const pinHtml = isRadar 
                ? `<div class="radar-pin"></div>` 
                : `<div style="background-color: ${this.getColorHex(color)}; width: 24px; height: 24px; border-radius: 50%; border: 3px solid #fff; box-shadow: 0 2px 5px rgba(0,0,0,0.4);"></div>`;

            const customIcon = L.divIcon({
                className: 'custom-map-pin',
                html: pinHtml,
                iconSize: [24, 24],
                iconAnchor: [12, 12]
            });

            const marker = L.marker([lat, lng], { icon: customIcon, title }).addTo(this.map);

            if (popupContent) {
                marker.bindPopup(popupContent);
            }

            this.markers.push(marker);
            return marker;
        }
    }

    /**
     * Clear all markers from map
     */
    clearMarkers() {
        if (this.provider === 'google') {
            this.markers.forEach(m => m.setMap(null));
        } else {
            this.markers.forEach(m => this.map.removeLayer(m));
        }
        this.markers = [];
    }

    /**
     * Draw search radius circle overlay
     */
    drawCircle(lat, lng, radiusKm, color = '#0d6efd') {
        const radiusMeters = radiusKm * 1000;

        if (this.circleOverlay) {
            if (this.provider === 'google') {
                this.circleOverlay.setMap(null);
            } else {
                this.map.removeLayer(this.circleOverlay);
            }
        }

        if (this.provider === 'google') {
            this.circleOverlay = new google.maps.Circle({
                strokeColor: color,
                strokeOpacity: 0.8,
                strokeWeight: 2,
                fillColor: color,
                fillOpacity: 0.15,
                map: this.map,
                center: { lat, lng },
                radius: radiusMeters
            });
        } else {
            this.circleOverlay = L.circle([lat, lng], {
                color: color,
                fillColor: color,
                fillOpacity: 0.15,
                radius: radiusMeters
            }).addTo(this.map);
        }
    }

    /**
     * Clear search radius circle overlay
     */
    clearCircle() {
        if (this.circleOverlay) {
            if (this.provider === 'google') {
                this.circleOverlay.setMap(null);
            } else if (this.map) {
                this.map.removeLayer(this.circleOverlay);
            }
            this.circleOverlay = null;
        }
    }

    /**
     * Set center position and optional zoom
     */
    setCenter(lat, lng, zoom = null) {
        lat = parseFloat(lat);
        lng = parseFloat(lng);
        if (this.provider === 'google' && this.map) {
            this.map.setCenter({ lat, lng });
            if (zoom !== null) this.map.setZoom(zoom);
        } else if (this.map) {
            if (zoom !== null) {
                this.map.setView([lat, lng], zoom);
            } else {
                this.map.panTo([lat, lng]);
            }
        }
    }

    /**
     * Fit map bounds to show all markers
     */
    fitToMarkers() {
        if (!this.markers || this.markers.length === 0) return;

        if (this.markers.length === 1) {
            const m = this.markers[0];
            if (this.provider === 'google') {
                const pos = m.getPosition();
                this.setCenter(pos.lat(), pos.lng(), 13);
            } else {
                const pos = m.getLatLng();
                this.setCenter(pos.lat, pos.lng, 13);
            }
            return;
        }

        if (this.provider === 'google') {
            const bounds = new google.maps.LatLngBounds();
            this.markers.forEach(m => bounds.extend(m.getPosition()));
            this.map.fitBounds(bounds);
        } else {
            const group = new L.featureGroup(this.markers);
            this.map.fitBounds(group.getBounds().pad(0.12), { maxZoom: 14 });
        }
    }

    /**
     * Render Navigation Directions from user to relief shelter
     */
    renderDirections(originLat, originLng, destLat, destLng, panelId = null) {
        if (this.provider === 'google' && this.directionsService) {
            const request = {
                origin: new google.maps.LatLng(originLat, originLng),
                destination: new google.maps.LatLng(destLat, destLng),
                travelMode: google.maps.TravelMode.DRIVING
            };

            this.directionsService.route(request, (result, status) => {
                if (status === google.maps.DirectionsStatus.OK) {
                    this.directionsRenderer.setDirections(result);
                    if (panelId) {
                        const panel = document.getElementById(panelId);
                        if (panel) this.directionsRenderer.setPanel(panel);
                    }
                } else {
                    window.open(`https://www.google.com/maps/dir/?api=1&origin=${originLat},${originLng}&destination=${destLat},${destLng}`, '_blank');
                }
            });
        } else {
            // Leaflet fallback opens Google Maps navigation in new tab
            window.open(`https://www.google.com/maps/dir/?api=1&origin=${originLat},${originLng}&destination=${destLat},${destLng}`, '_blank');
        }
    }

    getColorHex(color) {
        return {
            'green': '#198754',
            'orange': '#fd7e14',
            'red': '#dc3545',
            'blue': '#0d6efd'
        }[color] || '#0d6efd';
    }

    getGooglePinUrl(color) {
        const pinColors = {
            'green': 'http://maps.google.com/mapfiles/ms/icons/green-dot.png',
            'orange': 'http://maps.google.com/mapfiles/ms/icons/orange-dot.png',
            'red': 'http://maps.google.com/mapfiles/ms/icons/red-dot.png',
            'blue': 'http://maps.google.com/mapfiles/ms/icons/blue-dot.png'
        };
        return pinColors[color] || pinColors['blue'];
    }
}
