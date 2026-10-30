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

            // Update action buttons
            const btnCamps = document.getElementById('btnViewCamps');
            const btnHelp = document.getElementById('btnRequestHelp');
            if (btnCamps) btnCamps.href = `${window.APP_CONFIG.baseUrl}/views/citizen/camps.php?zone_id=${zoneId}`;
            if (btnHelp) btnHelp.href = `${window.APP_CONFIG.baseUrl}/views/citizen/request_help.php?zone_id=${zoneId}`;
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

        const response = await fetch(`${window.APP_CONFIG.baseUrl}/api/zone_risk.php?zone_id=${zoneId}`);
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
    document.getElementById('zoneNameHeader').textContent = zone.name;
    document.getElementById('zoneDistrictSubtitle').textContent = `${zone.district}, ${zone.state} (Center: ${parseFloat(zone.latitude).toFixed(4)}° N, ${parseFloat(zone.longitude).toFixed(4)}° E)`;

    // 2. Freshness Badge
    const freshnessBadge = document.getElementById('dataFreshnessBadge');
    if (freshnessBadge) {
        if (obs.is_stale) {
            freshnessBadge.className = 'badge bg-warning text-dark';
            freshnessBadge.innerHTML = '<i class="bi bi-clock-history me-1"></i> Stale Data (>24h)';
        } else {
            freshnessBadge.className = 'badge bg-success';
            freshnessBadge.innerHTML = '<i class="bi bi-check-circle-fill me-1"></i> Live Hydrology';
        }
    }

    // 3. Composite Score & Category
    const scoreVal = parseFloat(risk.score).toFixed(1);
    const scoreNumberEl = document.getElementById('scoreNumber');
    scoreNumberEl.textContent = scoreVal;

    const categoryBadge = document.getElementById('categoryBadge');
    categoryBadge.textContent = risk.category.toUpperCase();

    // Map Category Colors
    const badgeClasses = {
        'Critical': 'badge-risk-critical',
        'High': 'badge-risk-high',
        'Moderate': 'badge-risk-moderate',
        'Low': 'badge-risk-low'
    };
    categoryBadge.className = `badge fs-5 px-4 py-2 ${badgeClasses[risk.category] || 'bg-secondary'}`;

    // Update Progress Bar
    const progressBar = document.getElementById('riskProgressBar');
    progressBar.style.width = `${scoreVal}%`;
    const barClasses = {
        'Critical': 'bg-danger',
        'High': 'bg-warning',
        'Moderate': 'bg-info',
        'Low': 'bg-success'
    };
    progressBar.className = `progress-bar progress-bar-striped progress-bar-animated ${barClasses[risk.category] || 'bg-secondary'}`;

    // 4. Observation Meta
    document.getElementById('obsSource').textContent = obs.source;
    document.getElementById('obsTimestamp').textContent = obs.observed_at_human;
    document.getElementById('obsFreshness').textContent = obs.time_ago;

    // 5. Hydrological Indicators
    document.getElementById('rainfallVal').textContent = parseFloat(obs.rainfall_mm).toFixed(1);
    document.getElementById('rainfallNormVal').textContent = risk.rainfall_normalized;
    document.getElementById('rainfallWeight').textContent = (risk.weights.rainfall * 100) + '%';
    document.getElementById('rainfallNormBar').style.width = `${Math.min(100, risk.rainfall_normalized)}%`;

    document.getElementById('riverVal').textContent = parseFloat(obs.river_level_m).toFixed(2);
    document.getElementById('riverNormVal').textContent = risk.river_normalized;
    document.getElementById('riverWeight').textContent = (risk.weights.river * 100) + '%';
    document.getElementById('riverNormBar').style.width = `${Math.min(100, risk.river_normalized)}%`;

    // 6. Active Alert Ticker
    const alertBox = document.getElementById('zoneAlertContainer');
    if (alertData && alertBox) {
        alertBox.classList.remove('d-none');
        document.getElementById('alertTitle').textContent = alertData.title;
        document.getElementById('alertMessage').textContent = alertData.message;
        document.getElementById('alertSeverity').textContent = alertData.severity;
    } else if (alertBox) {
        alertBox.classList.add('d-none');
    }

    // 7. Safety Guidance Directives
    document.getElementById('guidanceSummary').textContent = risk.guidance.summary;
    const actionsList = document.getElementById('guidanceActions');
    actionsList.innerHTML = '';
    risk.guidance.actions.forEach(action => {
        const li = document.createElement('li');
        li.className = 'list-group-item d-flex align-items-center py-2 px-0 border-0';
        li.innerHTML = `<i class="bi bi-check2-circle text-${risk.guidance.color} fs-5 me-2"></i> <span>${escapeHtml(action)}</span>`;
        actionsList.appendChild(li);
    });

    document.getElementById('guidanceCoordinator').textContent = risk.guidance.coordinator_action;

    // 8. Historical Table
    const historyBody = document.getElementById('historyTableBody');
    historyBody.innerHTML = '';
    if (!history || history.length === 0) {
        historyBody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-3">No previous observation records logged for this zone.</td></tr>';
    } else {
        history.forEach(item => {
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td class="font-monospace small">${escapeHtml(item.observed_at)}</td>
                <td>${parseFloat(item.rainfall_value).toFixed(1)} mm</td>
                <td>${parseFloat(item.river_level_value).toFixed(2)} m</td>
                <td class="fw-bold">${parseFloat(item.score).toFixed(1)}</td>
                <td><span class="badge ${badgeClasses[item.category] || 'bg-secondary'}">${escapeHtml(item.category)}</span></td>
                <td class="small text-muted">${escapeHtml(item.source)}</td>
            `;
            historyBody.appendChild(tr);
        });
    }
}

function escapeHtml(str) {
    if (!str) return '';
    return str.replace(/[&<>'"]/g, 
        tag => ({
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            "'": '&#39;',
            '"': '&quot;'
        }[tag] || tag)
    );
}