<?php
/**
 * Citizen Emergency Help Request Form
 * Satisfies PRD Section 6 (Request Help Form) and FR-13
 */

require_once dirname(__DIR__, 2) . '/config/app.php';
require_once dirname(__DIR__, 2) . '/config/maps_config.php';
require_once dirname(__DIR__, 2) . '/core/Database.php';
require_once dirname(__DIR__, 2) . '/core/Auth.php';
require_once dirname(__DIR__, 2) . '/core/CSRF.php';
require_once dirname(__DIR__, 2) . '/core/Helper.php';

$prefillName = '';
$prefillEmail = '';
if (Auth::check()) {
    $u = Auth::user();
    $prefillName = $u['full_name'];
    $prefillEmail = $u['email'];
}

$pageTitle = "Request Emergency Assistance";
require_once dirname(__DIR__) . '/layouts/header.php';
?>

<div class="container py-4">
    <!-- Life-safety warning banner -->
    <div class="alert alert-danger shadow-sm border-danger d-flex align-items-center mb-4 rounded-3" role="alert">
        <i class="bi bi-exclamation-octagon-fill fs-3 text-danger me-3 flex-shrink-0"></i>
        <div>
            <h5 class="alert-heading fw-bold mb-0">Is your life in immediate, critical danger?</h5>
            <div class="small">
                If floodwaters are rapidly rising above safe head height or you are trapped with no exit, 
                immediately dial <a href="tel:112" class="fw-bold text-danger">112</a> or 
                <a href="tel:1077" class="fw-bold text-danger">1077</a> for official emergency rescue mobilization while submitting this form.
            </div>
        </div>
    </div>

    <div class="row g-4 justify-content-center">
        <!-- Help Request Form Card -->
        <div class="col-lg-7">
            <div class="card shadow-sm border-0 rounded-4">
                <div class="card-header bg-dark text-white py-3">
                    <div class="d-flex align-items-center">
                        <i class="bi bi-life-preserver text-warning fs-4 me-2"></i>
                        <div>
                            <h5 class="fw-bold mb-0">Emergency Help &amp; Rescue Request</h5>
                            <small class="text-white-50">Transmitted directly to district disaster response coordinators</small>
                        </div>
                    </div>
                </div>

                <div class="card-body p-4">
                    <form id="helpRequestForm">
                        <?= CSRF::inputField() ?>

                        <h6 class="fw-bold text-primary small text-uppercase mb-3">1. Contact Information</h6>
                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label for="guest_name" class="form-label fw-semibold small">Full Name / Contact Person *</label>
                                <input type="text" class="form-control" id="guest_name" name="guest_name" required 
                                       placeholder="Your name" value="<?= Helper::e($prefillName) ?>">
                            </div>
                            <div class="col-md-6">
                                <label for="guest_phone" class="form-label fw-semibold small">Active 10-Digit Mobile Number *</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light fw-bold text-muted">+91</span>
                                    <input type="tel" class="form-control font-monospace fw-bold" id="guest_phone" name="guest_phone" required 
                                           pattern="^[6-9][0-9]{9}$" maxlength="10" placeholder="9847012345">
                                </div>
                                <div class="form-text small text-muted">Mandatory 10-digit Indian mobile number for rescue team call.</div>
                            </div>
                            <div class="col-12">
                                <label for="guest_email" class="form-label fw-semibold small">Email Address (Optional)</label>
                                <input type="email" class="form-control" id="guest_email" name="guest_email" 
                                       placeholder="name@example.com" value="<?= Helper::e($prefillEmail) ?>">
                            </div>
                        </div>

                        <hr class="my-4">

                        <h6 class="fw-bold text-primary small text-uppercase mb-3">2. Nature of Emergency</h6>
                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label for="request_type" class="form-label fw-semibold small">Assistance Category *</label>
                                <select class="form-select" id="request_type" name="request_type" required>
                                    <option value="Evacuation" selected>Evacuation / Boat Rescue</option>
                                    <option value="Medical Support">Medical Support / First Aid</option>
                                    <option value="Food & Water">Potable Food & Clean Water</option>
                                    <option value="Shelter">Temporary Shelter / Bed Request</option>
                                    <option value="Missing Person">Missing Person Search</option>
                                    <option value="Other">Other Emergency Support</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="urgency" class="form-label fw-semibold small">Urgency Level *</label>
                                <select class="form-select fw-semibold" id="urgency" name="urgency" required>
                                    <option value="Critical" class="text-danger fw-bold" selected>Critical (Trapped / Water inside house)</option>
                                    <option value="High" class="text-warning fw-bold">High (Water at doorway / Elderly stranded)</option>
                                    <option value="Medium">Medium (Provisions running low)</option>
                                    <option value="Low">Low (General assistance)</option>
                                </select>
                            </div>

                            <!-- Water Inundation Severity Level Visual Cards -->
                            <div class="col-12">
                                <label class="form-label fw-semibold small d-block">Water Inundation Severity Level *</label>
                                <div class="row g-2" id="severitySelector">
                                    <div class="col-md-4">
                                        <div class="severity-card text-center p-3 h-100" data-val="Ankle-Level">
                                            <i class="bi bi-water text-info fs-3 d-block mb-1"></i>
                                            <div class="fw-bold small">Ankle-Level</div>
                                            <div class="text-muted" style="font-size: 0.75rem;">0 - 1 ft (Shallow / Walkable)</div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="severity-card active-waist text-center p-3 h-100" data-val="Waist-Level">
                                            <i class="bi bi-tsunami text-warning fs-3 d-block mb-1"></i>
                                            <div class="fw-bold small">Waist-Level</div>
                                            <div class="text-muted" style="font-size: 0.75rem;">3 - 4 ft (Stranded / Currents)</div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="severity-card text-center p-3 h-100" data-val="Roof-Level">
                                            <i class="bi bi-house-exclamation text-danger fs-3 d-block mb-1"></i>
                                            <div class="fw-bold small text-danger">Roof / Overhead</div>
                                            <div class="text-muted" style="font-size: 0.75rem;">&gt; 8 ft (Critical Evacuation)</div>
                                        </div>
                                    </div>
                                </div>
                                <input type="hidden" name="water_severity" id="water_severity" value="Waist-Level">
                            </div>

                            <div class="col-md-4">
                                <label for="people_count" class="form-label fw-semibold small">People Needing Help *</label>
                                <input type="number" class="form-control" id="people_count" name="people_count" min="1" max="100" value="1" required>
                            </div>
                            <div class="col-md-8">
                                <label for="description" class="form-label fw-semibold small">Detailed Situation &amp; Landmarks *</label>
                                <textarea class="form-control" id="description" name="description" rows="2" required 
                                          placeholder="E.g., 2 adults & 1 infant on roof. Water 4 feet high. White building with red gate near temple."></textarea>
                            </div>
                        </div>

                        <hr class="my-4">

                        <h6 class="fw-bold text-primary small text-uppercase mb-3">3. Precise Rescue Location</h6>
                        <div class="mb-3">
                            <label for="address" class="form-label fw-semibold small">Address / Landmark Description *</label>
                            <input type="text" class="form-control" id="address" name="address" required 
                                   placeholder="House name, street, junction, ward, or village">
                        </div>

                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <label class="form-label fw-semibold small mb-0">GPS Coordinates (Auto-detected or Pinpoint on Map) *</label>
                            <button type="button" id="btnDetectGps" class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-crosshair me-1"></i> Detect My GPS
                            </button>
                        </div>

                        <div class="row g-2 mb-3">
                            <div class="col-6">
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text">Lat</span>
                                    <input type="number" step="any" class="form-control font-monospace" id="latitude" name="latitude" required readonly>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text">Lng</span>
                                    <input type="number" step="any" class="form-control font-monospace" id="longitude" name="longitude" required readonly>
                                </div>
                            </div>
                        </div>

                        <!-- Mini Interactive Location Picker Map -->
                        <div class="mb-3">
                            <div id="pickerMap" style="height: 250px; border-radius: 8px;"></div>
                            <small class="text-muted">Click or drag the marker to pinpoint your exact rescue location on the map.</small>
                        </div>

                        <div class="d-grid mt-4">
                            <button type="submit" id="btnSubmitRequest" class="btn btn-danger btn-lg fw-bold shadow-sm">
                                <i class="bi bi-send-fill me-2"></i> Submit Emergency Assistance Ticket
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Guidance & What Happens Next Column -->
        <div class="col-lg-5">
            <!-- Click-to-Call Emergency Speed Dial -->
            <div class="card shadow-sm border-0 rounded-4 glass-card mb-4 overflow-hidden">
                <div class="card-header bg-danger text-white py-3">
                    <h6 class="fw-bold mb-0"><i class="bi bi-telephone-outbound-fill me-2"></i>Instant Emergency Speed Dial</h6>
                    <small class="text-white-50">Toll-free direct connects during active flooding</small>
                </div>
                <div class="card-body p-3">
                    <div class="d-grid gap-2">
                        <a href="tel:112" class="btn btn-danger btn-speed-dial shadow-sm">
                            <div class="text-start">
                                <div class="fw-bold"><i class="bi bi-telephone-fill me-2"></i> National Emergency</div>
                                <small class="text-white-50">Police / Fire / Disaster Response</small>
                            </div>
                            <span class="badge bg-white text-danger font-monospace fs-6 px-3 py-2">112</span>
                        </a>
                        <a href="tel:1077" class="btn btn-dark btn-speed-dial shadow-sm">
                            <div class="text-start">
                                <div class="fw-bold"><i class="bi bi-shield-shaded me-2 text-warning"></i> Disaster Control Room</div>
                                <small class="text-white-50">District Collectorate Helpline</small>
                            </div>
                            <span class="badge bg-warning text-dark font-monospace fs-6 px-3 py-2">1077</span>
                        </a>
                        <a href="tel:108" class="btn btn-outline-danger btn-speed-dial shadow-sm">
                            <div class="text-start">
                                <div class="fw-bold"><i class="bi bi-hospital-fill me-2"></i> Ambulance &amp; Medical</div>
                                <small class="text-muted">Emergency Medical Services</small>
                            </div>
                            <span class="badge bg-danger text-white font-monospace fs-6 px-3 py-2">108</span>
                        </a>
                    </div>
                </div>
            </div>

            <div class="card shadow-sm border-0 rounded-4 mb-4">
                <div class="card-header bg-white py-3 border-0">
                    <h6 class="fw-bold text-dark mb-0"><i class="bi bi-info-circle-fill text-primary me-2"></i>What Happens Next?</h6>
                </div>
                <div class="card-body pt-0">
                    <ol class="list-group list-group-numbered list-group-flush small">
                        <li class="list-group-item px-0 py-2 border-0">
                            <strong>Instant Reference Code:</strong> You will receive a unique ticket tracking code (e.g., <code>REQ-2026-XXXX</code>).
                        </li>
                        <li class="list-group-item px-0 py-2 border-0">
                            <strong>Coordinator Triage:</strong> The district disaster coordinator immediately reviews urgency and assigns field rescue or relief teams.
                        </li>
                        <li class="list-group-item px-0 py-2 border-0">
                            <strong>Direct Contact:</strong> The rescue team or camp coordinator may call your mobile phone to verify conditions.
                        </li>
                        <li class="list-group-item px-0 py-2 border-0">
                            <strong>Real-time Tracking:</strong> You can track ticket progress at any time using your reference code.
                        </li>
                    </ol>
                </div>
            </div>

            <div class="card shadow-sm border-0 rounded-4 bg-light">
                <div class="card-body p-4">
                    <h6 class="fw-bold text-dark mb-2"><i class="bi bi-shield-fill-check text-success me-2"></i>Safety Rules While Awaiting Help</h6>
                    <ul class="small text-muted ps-3 mb-0">
                        <li class="mb-2">Move to highest reachable floor or rooftop terrace.</li>
                        <li class="mb-2">Wave a brightly colored cloth or use phone flashlight to signal rescue boats/helicopters.</li>
                        <li class="mb-2">Never touch downed electric poles, dangling wires, or submerged wall sockets.</li>
                        <li>Keep drinking water bottles safely sealed and avoid drinking murky floodwater.</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Success Modal Displaying Reference Code -->
<div class="modal fade" id="successModal" tabindex="-1" aria-labelledby="successModalLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-header bg-success text-white py-3">
                <h5 class="modal-title fw-bold" id="successModalLabel">
                    <i class="bi bi-check-circle-fill me-2"></i> Emergency Request Registered
                </h5>
            </div>
            <div class="modal-body p-4 text-center">
                <p class="text-muted mb-2">Your assistance request has been transmitted to disaster response coordinators.</p>
                <div class="small text-uppercase fw-bold text-muted mb-1">Your Tracking Reference Code:</div>
                <div class="p-3 bg-light rounded-3 border mb-3">
                    <div class="display-6 fw-bold text-primary font-monospace" id="modalRefCode">REQ-XXXX</div>
                    <button class="btn btn-sm btn-outline-secondary mt-2" onclick="copyRefCode()">
                        <i class="bi bi-clipboard me-1"></i> Copy Code
                    </button>
                </div>
                <p class="small text-danger fw-semibold mb-0">
                    <i class="bi bi-exclamation-triangle-fill me-1"></i> Please write down or screenshot this code to track response updates.
                </p>
            </div>
            <div class="modal-footer bg-light justify-content-between">
                <a href="<?= BASE_URL ?>/views/citizen/index.php" class="btn btn-outline-secondary btn-sm">Return to Dashboard</a>
                <a id="modalTrackBtn" href="#" class="btn btn-primary btn-sm fw-semibold">
                    <i class="bi bi-search me-1"></i> Track Ticket Status
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Map Adapter -->
<script src="<?= BASE_URL ?>/assets/js/map_adapter.js"></script>

<script>
let pickerMapAdapter = null;
let pickerMarker = null;

document.addEventListener('DOMContentLoaded', () => {
    const defaultLat = window.APP_CONFIG.defaultLat;
    const defaultLng = window.APP_CONFIG.defaultLng;

    document.getElementById('latitude').value = defaultLat.toFixed(6);
    document.getElementById('longitude').value = defaultLng.toFixed(6);

    // Initialize Picker Map
    pickerMapAdapter = new MapAdapter('pickerMap', {
        lat: defaultLat,
        lng: defaultLng,
        zoom: 13
    });

    pickerMarker = pickerMapAdapter.addMarker(defaultLat, defaultLng, {
        title: 'Rescue Point',
        color: 'red',
        popup: 'Rescue Location Pin'
    });

    // Handle map clicks to update pin
    if (pickerMapAdapter.map) {
        if (pickerMapAdapter.provider === 'leaflet') {
            pickerMapAdapter.map.on('click', (e) => {
                updatePinLocation(e.latlng.lat, e.latlng.lng);
            });
        } else if (pickerMapAdapter.provider === 'google') {
            pickerMapAdapter.map.addListener('click', (e) => {
                updatePinLocation(e.latLng.lat(), e.latLng.lng());
            });
        }
    }

    // Auto-detect GPS
    document.getElementById('btnDetectGps')?.addEventListener('click', detectUserGps);
    if (navigator.geolocation) {
        detectUserGps();
    }

    // Water Severity Selector click handler
    document.querySelectorAll('#severitySelector .severity-card').forEach(card => {
        card.addEventListener('click', () => {
            document.querySelectorAll('#severitySelector .severity-card').forEach(c => {
                c.classList.remove('active-ankle', 'active-waist', 'active-roof');
            });
            const val = card.getAttribute('data-val');
            document.getElementById('water_severity').value = val;
            if (val === 'Ankle-Level') card.classList.add('active-ankle');
            else if (val === 'Waist-Level') card.classList.add('active-waist');
            else if (val === 'Roof-Level') card.classList.add('active-roof');
        });
    });

    // Handle Form Submit
    document.getElementById('helpRequestForm')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const form = e.target;
        const btn = document.getElementById('btnSubmitRequest');
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> Transmitting Emergency Request...';

        const formData = new FormData(form);
        const payload = Object.fromEntries(formData.entries());

        try {
            const res = await fetch(`${window.APP_CONFIG.baseUrl}/api/help_requests.php`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify(payload)
            });

            const json = await res.json();
            if (!json.success || !json.data) {
                throw new Error(json.message || 'Submission failed.');
            }

            // Show Success Modal
            document.getElementById('modalRefCode').textContent = json.data.reference_code;
            document.getElementById('modalTrackBtn').href = json.data.tracking_url;

            const modal = new bootstrap.Modal(document.getElementById('successModal'));
            modal.show();

        } catch (err) {
            alert('Emergency Submission Error: ' + err.message);
        } finally {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-send-fill me-2"></i> Submit Emergency Assistance Ticket';
        }
    });
});

function detectUserGps() {
    if (!navigator.geolocation) return;
    navigator.geolocation.getCurrentPosition((pos) => {
        updatePinLocation(pos.coords.latitude, pos.coords.longitude);
        if (pickerMapAdapter) {
            pickerMapAdapter.setCenter(pos.coords.latitude, pos.coords.longitude, 14);
        }
    }, (err) => {
        console.log('GPS detection note:', err.message);
    });
}

function updatePinLocation(lat, lng) {
    document.getElementById('latitude').value = parseFloat(lat).toFixed(6);
    document.getElementById('longitude').value = parseFloat(lng).toFixed(6);

    if (pickerMapAdapter) {
        pickerMapAdapter.clearMarkers();
        pickerMarker = pickerMapAdapter.addMarker(lat, lng, {
            title: 'Rescue Point',
            color: 'red',
            popup: 'Selected Rescue Point'
        });
    }
}

function copyRefCode() {
    const code = document.getElementById('modalRefCode').textContent;
    navigator.clipboard.writeText(code).then(() => {
        alert('Reference Code copied to clipboard: ' + code);
    });
}
</script>

<?php require_once dirname(__DIR__) . '/layouts/footer.php'; ?>
