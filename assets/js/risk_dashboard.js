/**
 * Client-side Controller for Public Flood Risk Dashboard
 * Flood Risk Prediction & Relief Camp Locator
 */

document.addEventListener('DOMContentLoaded', () => {
    const zoneSelector = document.getElementById('zoneSelector');
    if (!zoneSelector) return;

    // Load initial zone
    const initialZoneId = zoneSelector.value;
    if (initialZoneId) {
        fetchZoneRisk(initialZoneId);
    }

    zoneSelector.addEventListener('change', (e) => {
        const zoneId = e.target.value;
        if (zoneId) {
            fetchZoneRisk(zoneId);
            // Update URL param without full page reload
            const url = new URL(window.location);
            url.searchParams.set('zone_id', zoneId);
            window.history.replaceState({}, '', url);

            // Get safe base URL with fallback to relative directory
            const appBase = (window.APP_CONFIG && window.APP_CONFIG.baseUrl) ? window.APP_CONFIG.baseUrl.replace(/\/$/, '') : '../..';

            // Update action buttons
            const btnCamps = document.getElementById('btnViewCamps');
            const btnHelp = document.getElementById('btnRequestHelp');
            if (btnCamps) btnCamps.href = `${appBase}/views/citizen/camps.php?zone_id=${zoneId}`;
            if (btnHelp) btnHelp.href = `${appBase}/views/citizen/request_help.php?zone_id=${zoneId}`;
        }
    });
});

/**
 * Fetch and render risk indicators for selected zone
 */
async function fetchZoneRisk(zoneId) {
    const loadingSpinner = document.getElementById('riskLoadingSpinner');
    const displayContainer = document.getElementById('riskDisplayContainer');

    try {
        if (loadingSpinner) loadingSpinner.classList.remove('d-none');
        if (displayContainer) displayContainer.style.opacity = '0.5';

        // Base URL fallback logic: If APP_CONFIG is missing, use relative root path
        const appBase = (window.APP_CONFIG && window.APP_CONFIG.baseUrl) ? window.APP_CONFIG.baseUrl.replace(/\/$/, '') : '../..';
        const response = await fetch(`${appBase}/api/zone_risk.php?zone_id=${zoneId}`);
        
        // Check for non-200 HTTP response
        if (!response.ok) {
            throw new Error(`Server returned HTTP ${response.status}`);
        }

        const result = await response.json();

        if (!result.success || !result.data) {
            throw new Error(result.message || 'Failed to calculate risk indicators.');
        }

        renderZoneRisk(result.data);

    } catch (err) {
        console.error('Risk Telemetry Error:', err);
        alert('Notice: Unable to fetch live zone risk telemetry. ' + err.message);
    } finally {
        if (loadingSpinner) loadingSpinner.classList.add('d-none');
        if (displayContainer) displayContainer.style.opacity = '1.0';
    }
}

/**
 * Render telemetry and calculation payload to UI
 */
function renderZoneRisk(data) {
    const { zone, current_observation: obs, risk_assessment: risk, active_alert: alertData, recent_history: history } = data;

    // 1. Zone Information
    const zoneNameHeader = document.getElementById('zoneNameHeader');
    if (zoneNameHeader && zone) zoneNameHeader.textContent = zone.name || '';

    const zoneDistrictSubtitle = document.getElementById('zoneDistrictSubtitle');
    if (zoneDistrictSubtitle && zone) {
        zoneDistrictSubtitle.textContent = `${zone.district || ''}, ${zone.state || ''} (Center: ${parseFloat(zone.latitude || 0).toFixed(4)}° N, ${parseFloat(zone.longitude || 0).toFixed(4)}° E)`;
    }

    // 2. Freshness Badge
    const freshnessBadge = document.getElementById('dataFreshnessBadge');
    if (freshnessBadge && obs) {
        if (obs.is_stale) {
            freshnessBadge.className = 'badge bg-warning text-dark';
            freshnessBadge.innerHTML = '<i class="bi bi-clock-history me-1"></i> Stale Data (>24h)';
        } else {
            freshnessBadge.className = 'badge bg-success';
            freshnessBadge.innerHTML = '<i class="bi bi-check-circle-fill me-1"></i> Live Hydrology';
        }
    }

    // 3. Composite Score & Category
    const badgeClasses = {
        'Critical': 'badge-risk-critical',
        'High': 'badge-risk-high',
        'Moderate': 'badge-risk-moderate',
        'Low': 'badge-risk-low'
    };

    if (risk) {
        const scoreVal = parseFloat(risk.score || 0).toFixed(1);
        const scoreNumberEl = document.getElementById('scoreNumber');
        if (scoreNumberEl) scoreNumberEl.textContent = scoreVal;

        const categoryBadge = document.getElementById('categoryBadge');
        if (categoryBadge) {
            categoryBadge.textContent = (risk.category || 'LOW').toUpperCase();
            categoryBadge.className = `badge fs-5 px-4 py-2 ${badgeClasses[risk.category] || 'bg-secondary'}`;
        }

        // Update Progress Bar
        const progressBar = document.getElementById('riskProgressBar');
        if (progressBar) {
            progressBar.style.width = `${scoreVal}%`;
            const barClasses = {
                'Critical': 'bg-danger',
                'High': 'bg-warning',
                'Moderate': 'bg-info',
                'Low': 'bg-success'
            };
            progressBar.className = `progress-bar progress-bar-striped progress-bar-animated ${barClasses[risk.category] || 'bg-secondary'}`;
        }
    }

    // 4. Observation Meta
    if (obs) {
        const obsSource = document.getElementById('obsSource');
        if (obsSource) obsSource.textContent = obs.source || 'Sensors';

        const obsTimestamp = document.getElementById('obsTimestamp');
        if (obsTimestamp) obsTimestamp.textContent = obs.observed_at_human || '';

        const obsFreshness = document.getElementById('obsFreshness');
        if (obsFreshness) obsFreshness.textContent = obs.time_ago || '';
    }

    // 5. Hydrological Indicators
    if (obs && risk) {
        const rainfallVal = document.getElementById('rainfallVal');
        if (rainfallVal) rainfallVal.textContent = parseFloat(obs.rainfall_mm || 0).toFixed(1);

        const rainfallNormVal = document.getElementById('rainfallNormVal');
        if (rainfallNormVal) rainfallNormVal.textContent = risk.rainfall_normalized || 0;

        const rainfallWeight = document.getElementById('rainfallWeight');
        if (rainfallWeight && risk.weights) rainfallWeight.textContent = ((risk.weights.rainfall || 0) * 100) + '%';

        const rainfallNormBar = document.getElementById('rainfallNormBar');
        if (rainfallNormBar) rainfallNormBar.style.width = `${Math.min(100, risk.rainfall_normalized || 0)}%`;

        const riverVal = document.getElementById('riverVal');
        if (riverVal) riverVal.textContent = parseFloat(obs.river_level_m || 0).toFixed(2);

        const riverNormVal = document.getElementById('riverNormVal');
        if (riverNormVal) riverNormVal.textContent = risk.river_normalized || 0;

        const riverWeight = document.getElementById('riverWeight');
        if (riverWeight && risk.weights) riverWeight.textContent = ((risk.weights.river || 0) * 100) + '%';

        const riverNormBar = document.getElementById('riverNormBar');
        if (riverNormBar) riverNormBar.style.width = `${Math.min(100, risk.river_normalized || 0)}%`;
    }

    // 6. Active Alert Ticker
    const alertBox = document.getElementById('zoneAlertContainer');
    if (alertData && alertBox) {
        alertBox.classList.remove('d-none');
        const alertTitle = document.getElementById('alertTitle');
        const alertMessage = document.getElementById('alertMessage');
        const alertSeverity = document.getElementById('alertSeverity');
        if (alertTitle) alertTitle.textContent = alertData.title || '';
        if (alertMessage) alertMessage.textContent = alertData.message || '';
        if (alertSeverity) alertSeverity.textContent = alertData.severity || '';
    } else if (alertBox) {
        alertBox.classList.add('d-none');
    }

    // 7. Safety Guidance Directives
    if (risk && risk.guidance) {
        const guidanceSummary = document.getElementById('guidanceSummary');
        if (guidanceSummary) guidanceSummary.textContent = risk.guidance.summary || '';

        const actionsList = document.getElementById('guidanceActions');
        if (actionsList && Array.isArray(risk.guidance.actions)) {
            actionsList.innerHTML = '';
            risk.guidance.actions.forEach(action => {
                const li = document.createElement('li');
                li.className = 'list-group-item d-flex align-items-center py-2 px-0 border-0';
                li.innerHTML = `<i class="bi bi-check2-circle text-${risk.guidance.color || 'primary'} fs-5 me-2"></i> <span>${escapeHtml(action)}</span>`;
                actionsList.appendChild(li);
            });
        }

        const guidanceCoordinator = document.getElementById('guidanceCoordinator');
        if (guidanceCoordinator) guidanceCoordinator.textContent = risk.guidance.coordinator_action || '';
    }

    // 8. Historical Table
    const historyBody = document.getElementById('historyTableBody');
    if (historyBody) {
        historyBody.innerHTML = '';
        if (!history || history.length === 0) {
            historyBody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-3">No previous observation records logged for this zone.</td></tr>';
        } else {
            history.forEach(item => {
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td class="font-monospace small">${escapeHtml(item.observed_at)}</td>
                    <td>${parseFloat(item.rainfall_value || 0).toFixed(1)} mm</td>
                    <td>${parseFloat(item.river_level_value || 0).toFixed(2)} m</td>
                    <td class="fw-bold">${parseFloat(item.score || 0).toFixed(1)}</td>
                    <td><span class="badge ${badgeClasses[item.category] || 'bg-secondary'}">${escapeHtml(item.category || '')}</span></td>
                    <td class="small text-muted">${escapeHtml(item.source || '')}</td>
                `;
                historyBody.appendChild(tr);
            });
        }
    }
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