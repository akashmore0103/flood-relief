<?php
/**
 * Administrator Hydrology Data Ingestion Portal (XML, JSON & CSV)
 * Satisfies PRD Section 5.2 (FR-07, US-08), Section 7, and Section 9
 */

require_once dirname(__DIR__, 2) . '/config/app.php';
require_once dirname(__DIR__, 2) . '/core/Database.php';
require_once dirname(__DIR__, 2) . '/core/Auth.php';
require_once dirname(__DIR__, 2) . '/core/CSRF.php';
require_once dirname(__DIR__, 2) . '/core/Helper.php';

Auth::requireRole(['Administrator']);

$db = Database::getInstance();
$zones = $db->fetchAll("SELECT `id`, `name`, `district` FROM `zones` ORDER BY `id` ASC");

$sampleXmlPath = dirname(__DIR__, 2) . '/database/sample_observations.xml';
$sampleXmlContent = file_exists($sampleXmlPath) ? file_get_contents($sampleXmlPath) : '';

$pageTitle = "Import Hydrological Data (XML / JSON)";
require_once dirname(__DIR__) . '/layouts/header.php';
?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
        <div>
            <h2 class="fw-bold text-dark mb-1">
                <i class="bi bi-file-earmark-code-fill text-info me-2"></i>Hydrological Observation Data Ingestion
            </h2>
            <p class="text-muted small mb-0">Import external weather station and river sensor feeds formatted in standard XML, JSON, or CSV.</p>
        </div>
        <a href="<?= BASE_URL ?>/database/sample_observations.xml" target="_blank" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-download me-1"></i> View Sample XML File
        </a>
    </div>

    <div class="row g-4">
        <!-- Ingestion Form Column -->
        <div class="col-lg-7">
            <div class="card shadow-sm border-0 rounded-4 mb-4">
                <div class="card-header bg-dark text-white py-3">
                    <h5 class="fw-bold mb-0 text-white"><i class="bi bi-cloud-upload-fill me-2 text-info"></i>Upload Observation Feed</h5>
                </div>
                <div class="card-body p-4">
                    <form id="importForm" enctype="multipart/form-data">
                        <?= CSRF::inputField() ?>

                        <div class="mb-3">
                            <label for="formatSelect" class="form-label fw-semibold small">Data Format *</label>
                            <select class="form-select" id="formatSelect" name="format">
                                <option value="xml" selected>XML Hydrology Bulletin (SimpleXML &amp; DOM)</option>
                                <option value="json">JSON Telemetry Feed</option>
                                <option value="csv">CSV Spreadsheet Table</option>
                            </select>
                        </div>

                        <!-- File Upload Option -->
                        <div class="mb-4">
                            <label for="import_file" class="form-label fw-semibold small">Option A: Upload File (.xml, .json, .csv)</label>
                            <input class="form-control" type="file" id="import_file" name="import_file" accept=".xml,.json,.csv">
                        </div>

                        <div class="text-center my-3 text-muted small fw-bold">-- OR --</div>

                        <!-- Raw Text Paste Option -->
                        <div class="mb-4">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <label for="raw_data" class="form-label fw-semibold small mb-0">Option B: Paste Raw XML / JSON Payload</label>
                                <button type="button" class="btn btn-link btn-sm text-decoration-none p-0" onclick="loadSampleXml()">
                                    <i class="bi bi-magic me-1"></i> Paste Sample XML
                                </button>
                            </div>
                            <textarea class="form-control font-monospace small" id="raw_data" name="raw_data" rows="10" 
                                      placeholder="Paste observation XML or JSON code here..."></textarea>
                        </div>

                        <div class="d-grid">
                            <button type="submit" id="btnRunImport" class="btn btn-primary btn-lg fw-bold shadow-sm">
                                <i class="bi bi-check2-circle me-1"></i> Process &amp; Ingest Hydrology Data
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Ingestion Results Display Card -->
            <div id="importResultCard" class="card shadow-sm border-0 rounded-4 d-none">
                <div class="card-header bg-white py-3 border-0">
                    <h6 class="fw-bold text-dark mb-0" id="resultHeader">Import Summary</h6>
                </div>
                <div class="card-body p-4 pt-0" id="resultBody">
                    <!-- Injected by JS -->
                </div>
            </div>
        </div>

        <!-- Reference & Zone ID Guide Column -->
        <div class="col-lg-5">
            <div class="card shadow-sm border-0 rounded-4 mb-4">
                <div class="card-header bg-white py-3 border-0">
                    <h6 class="fw-bold text-dark mb-0"><i class="bi bi-info-circle-fill text-primary me-2"></i>Active Zone ID Reference</h6>
                    <small class="text-muted">Observation tags must match existing database Zone IDs</small>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0">
                        <thead class="table-light small">
                            <tr>
                                <th style="width: 70px;">Zone ID</th>
                                <th>Zone / Basin Name</th>
                                <th>District</th>
                            </tr>
                        </thead>
                        <tbody class="small font-monospace">
                            <?php foreach ($zones as $z): ?>
                                <tr>
                                    <td><strong class="text-primary">#<?= (int)$z['id'] ?></strong></td>
                                    <td><?= Helper::e($z['name']) ?></td>
                                    <td class="text-muted"><?= Helper::e($z['district']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card shadow-sm border-0 rounded-4 bg-light">
                <div class="card-body p-4 small text-muted">
                    <h6 class="fw-bold text-dark mb-2"><i class="bi bi-code-slash text-info me-2"></i>Expected XML Structure</h6>
                    <pre class="bg-white p-3 rounded-3 border mb-0" style="font-size: 0.75rem;"><code>&lt;hydrology_feed&gt;
  &lt;observations&gt;
    &lt;observation&gt;
      &lt;zone_id&gt;1&lt;/zone_id&gt;
      &lt;rainfall_mm&gt;195.5&lt;/rainfall_mm&gt;
      &lt;river_level_m&gt;12.8&lt;/river_level_m&gt;
      &lt;observed_at&gt;2026-09-05 10:00:00&lt;/observed_at&gt;
      &lt;station_id&gt;STN-01&lt;/station_id&gt;
    &lt;/observation&gt;
  &lt;/observations&gt;
&lt;/hydrology_feed&gt;</code></pre>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
const sampleXmlStr = <?= json_encode($sampleXmlContent) ?>;

function loadSampleXml() {
    document.getElementById('raw_data').value = sampleXmlStr;
    document.getElementById('formatSelect').value = 'xml';
}

document.getElementById('importForm')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const btn = document.getElementById('btnRunImport');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> Parsing & Ingesting Observations...';

    const formData = new FormData(e.target);

    try {
        const res = await fetch(`${window.APP_CONFIG.baseUrl}/api/import_risk_data.php`, {
            method: 'POST',
            body: formData
        });

        const json = await res.json();
        const resultCard = document.getElementById('importResultCard');
        const resultBody = document.getElementById('resultBody');
        resultCard.classList.remove('d-none');

        if (json.success && json.data) {
            resultCard.className = 'card shadow-sm border-2 border-success rounded-4';
            resultBody.innerHTML = `
                <div class="alert alert-success d-flex align-items-center mb-3">
                    <i class="bi bi-check-circle-fill fs-3 text-success me-3"></i>
                    <div>
                        <h6 class="alert-heading fw-bold mb-0">${escapeHtml(json.message)}</h6>
                        <small>Total observations processed: ${json.data.total_received} | Successfully stored: ${json.data.imported_count}</small>
                    </div>
                </div>
                ${json.data.errors && json.data.errors.length > 0 ? `
                    <div class="alert alert-warning small mb-0">
                        <strong>Skipped Rows / Notices:</strong>
                        <ul class="mb-0 ps-3 mt-1">
                            ${json.data.errors.map(err => `<li>${escapeHtml(err)}</li>`).join('')}
                        </ul>
                    </div>` : ''}
                <div class="mt-3 text-end">
                    <a href="${window.APP_CONFIG.baseUrl}/views/citizen/index.php" class="btn btn-sm btn-outline-success">
                        <i class="bi bi-speedometer2 me-1"></i> View Updated Dashboard Scores
                    </a>
                </div>
            `;
        } else {
            resultCard.className = 'card shadow-sm border-2 border-danger rounded-4';
            resultBody.innerHTML = `
                <div class="alert alert-danger mb-0">
                    <i class="bi bi-x-circle-fill me-2"></i>
                    <strong>Import Failed:</strong> ${escapeHtml(json.message || 'Unknown parsing error.')}
                </div>
            `;
        }

    } catch (err) {
        alert('Network / Ingestion Error: ' + err.message);
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-check2-circle me-1"></i> Process & Ingest Hydrology Data';
    }
});

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
</script>

<?php require_once dirname(__DIR__) . '/layouts/footer.php'; ?>
