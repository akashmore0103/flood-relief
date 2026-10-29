<?php
/**
 * Role-Sensitive Navigation Bar with Multi-Language Switcher
 * Flood Risk Prediction & Relief Camp Locator
 */

require_once dirname(__DIR__, 2) . '/core/I18n.php';

$isAuth = Auth::check();
$role = Auth::role();
$userName = $isAuth ? Auth::user()['full_name'] : '';
$currentLang = I18n::getLang();
$langLabels = ['en' => 'English', 'mr' => 'मराठी', 'hi' => 'हिंदी'];
?>
<style>
/* ========================================================
   RESPONSIVE, ZERO-OVERFLOW DARK NAVBAR SYSTEM
   ======================================================== */
.app-main-navbar {
    padding-top: 8px !important;
    padding-bottom: 8px !important;
    padding-left: 0 !important;
    padding-right: 0 !important;
    min-height: 56px;
    background-color: #0f172a !important; /* Deep dark slate */
    width: 100%;
    max-width: 100vw;
    box-sizing: border-box;
}

.app-main-navbar .navbar-brand {
    white-space: nowrap;
    flex-shrink: 0;
    margin-right: 0.75rem !important;
}

.app-main-navbar .brand-title {
    font-size: 1rem;
    font-weight: 700;
    letter-spacing: -0.02em;
}

.app-main-navbar .brand-badge {
    font-size: 0.58rem;
    letter-spacing: 0.4px;
    font-weight: 600;
    padding: 2px 6px;
    vertical-align: middle;
}

/* Primary Navigation Links (Single-Line, Compact & Sleek) */
.app-main-navbar .navbar-nav.main-nav-links {
    display: flex;
    align-items: center;
    flex-wrap: nowrap;
    gap: 0.25rem;
    margin-bottom: 0;
}

@media (min-width: 1200px) {
    .app-main-navbar .navbar-nav.main-nav-links {
        gap: 0.35rem; /* ~6px gap on standard laptop screens */
    }
}

@media (min-width: 1440px) {
    .app-main-navbar .navbar-nav.main-nav-links {
        gap: 0.65rem; /* ~10px gap on large monitors */
    }
}

.app-main-navbar .main-nav-links .nav-link {
    display: inline-flex;
    align-items: center;
    white-space: nowrap;
    padding: 0.35rem 0.45rem !important;
    font-size: 0.8125rem; /* 13px sleek typography */
    font-weight: 500;
    color: #94a3b8;
    border-radius: 6px;
    line-height: 1.2;
    transition: color 0.15s ease-in-out, background-color 0.15s ease-in-out;
}

.app-main-navbar .main-nav-links .nav-link:hover {
    color: #ffffff;
    background-color: rgba(255, 255, 255, 0.08);
}

.app-main-navbar .main-nav-links .nav-link.active {
    color: #ffffff;
    background-color: rgba(255, 255, 255, 0.12);
}

.app-main-navbar .main-nav-links .nav-link i {
    display: inline-flex;
    align-items: center;
    font-size: 0.875rem; /* 14px compact icon */
    margin-right: 4px;
    line-height: 1;
}

/* Right-Side Utility & Profile Controls (Strictly Non-Shrinkable) */
.app-main-navbar .navbar-nav.utility-nav-controls {
    display: flex;
    align-items: center;
    flex-wrap: nowrap;
    flex-shrink: 0 !important;
    gap: 0.35rem;
    margin-left: auto;
    margin-bottom: 0;
}

.app-main-navbar .nav-pill-control {
    height: 34px !important;
    display: inline-flex;
    align-items: center;
    padding: 0 10px !important;
    border-radius: 50rem;
    background: rgba(255, 255, 255, 0.06);
    border: 1px solid rgba(255, 255, 255, 0.14);
    color: #e2e8f0;
    font-size: 0.78rem !important;
    white-space: nowrap;
    flex-shrink: 0 !important;
    transition: all 0.15s ease;
}

.app-main-navbar .nav-pill-control:hover {
    background: rgba(255, 255, 255, 0.12);
    border-color: rgba(255, 255, 255, 0.28);
    color: #ffffff;
}

.app-main-navbar .user-avatar-circle {
    width: 22px;
    height: 22px;
    border-radius: 50%;
    background: rgba(13, 110, 253, 0.3);
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 6px;
    flex-shrink: 0;
}

.app-main-navbar .user-name-text {
    max-width: 105px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    display: inline-block;
    font-size: 0.78rem;
    font-weight: 600;
    line-height: 1.15;
}

@media (min-width: 1400px) {
    .app-main-navbar .user-name-text {
        max-width: 155px;
    }
}

.app-main-navbar .user-role-text {
    font-size: 0.58rem;
    letter-spacing: 0.3px;
    line-height: 1;
    color: #f59e0b;
}

/* Mobile & Tablet Drawer Stacking (< 1200px) */
@media (max-width: 1199.98px) {
    .app-main-navbar .navbar-collapse {
        background-color: #0b1120;
        border-radius: 12px;
        padding: 12px 14px;
        margin-top: 10px;
        border: 1px solid rgba(255, 255, 255, 0.1);
        max-width: 100%;
        overflow-x: hidden;
    }
    .app-main-navbar .navbar-nav.main-nav-links {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.2rem;
        width: 100%;
        margin-bottom: 10px;
    }
    .app-main-navbar .main-nav-links .nav-link {
        width: 100%;
        padding: 8px 10px !important;
        font-size: 0.88rem;
    }
    .app-main-navbar .navbar-nav.utility-nav-controls {
        flex-direction: row;
        justify-content: flex-start;
        width: 100%;
        padding-top: 10px;
        border-top: 1px solid rgba(255, 255, 255, 0.1);
        gap: 0.5rem;
    }
    .app-main-navbar .user-name-text {
        max-width: 220px;
    }
}
</style>

<nav class="navbar navbar-expand-xl navbar-dark bg-dark sticky-top shadow-sm border-bottom border-secondary border-opacity-25 app-main-navbar">
    <div class="container-fluid px-2 px-md-3 px-xl-4 align-items-center">
        <!-- Fluid Brand Identity -->
        <a class="navbar-brand d-flex align-items-center py-0" href="<?= BASE_URL ?>/views/citizen/index.php">
            <i class="bi bi-water text-info fs-5 me-1 me-sm-2"></i>
            <div class="d-flex align-items-center">
                <span class="text-white brand-title">FloodRelief</span>
                <span class="badge bg-danger-subtle text-danger border border-danger-subtle rounded-pill ms-2 brand-badge d-none d-xxl-inline-block">DECISION SUPPORT</span>
            </div>
        </a>

        <!-- Responsive Hamburger Toggle -->
        <button class="navbar-toggler py-1 px-2 border-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#mainNavbar" aria-controls="mainNavbar" aria-expanded="false" aria-label="Toggle navigation" style="font-size: 0.85rem;">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="mainNavbar">
            <!-- Proportionally Scaled Main Nav Links (No-Wrap, Single Line) -->
            <ul class="navbar-nav me-auto main-nav-links align-items-xl-center">
                <!-- 1. Live Risk Map -->
                <li class="nav-item">
                    <a class="nav-link" href="<?= BASE_URL ?>/views/citizen/index.php">
                        <i class="bi bi-speedometer2 text-primary"></i> <span data-i18n="nav_map">Risk Map</span>
                    </a>
                </li>

                <!-- 2. Dam Telemetry -->
                <li class="nav-item">
                    <a class="nav-link text-info fw-semibold" href="<?= BASE_URL ?>/views/dams_view.php">
                        <i class="bi bi-moisture"></i> <span data-i18n="nav_dams">Dam Telemetry</span>
                    </a>
                </li>

                <!-- 3. Relief Camps -->
                <li class="nav-item">
                    <a class="nav-link" href="<?= BASE_URL ?>/views/citizen/camps.php">
                        <i class="bi bi-geo-alt-fill text-danger"></i> <span data-i18n="nav_camps">Relief Camps</span>
                    </a>
                </li>

                <!-- 4. Missing & Found -->
                <li class="nav-item">
                    <a class="nav-link" href="<?= BASE_URL ?>/views/citizen/missing_persons.php">
                        <i class="bi bi-person-bounding-box text-info"></i> <span data-i18n="nav_missing">Missing &amp; Found</span>
                    </a>
                </li>

                <!-- 5. Emergency SOS -->
                <li class="nav-item">
                    <a class="nav-link text-warning fw-bold" href="<?= BASE_URL ?>/views/citizen/request_help.php">
                        <i class="bi bi-life-preserver"></i> <span data-i18n="nav_sos">Emergency SOS</span>
                    </a>
                </li>

                <?php if (Auth::isAdmin()): ?>
                    <!-- Streamlined Admin Portal Dropdown -->
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle text-warning fw-semibold px-2" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-shield-shaded"></i> Admin Portal
                        </a>
                        <ul class="dropdown-menu dropdown-menu-dark shadow-lg">
                            <li><span class="dropdown-header text-uppercase text-secondary font-monospace" style="font-size: 0.7rem;">Executive Command</span></li>
                            <li><a class="dropdown-item" href="<?= BASE_URL ?>/views/admin/dashboard.php"><i class="bi bi-speedometer text-warning me-2"></i> Overview &amp; Metrics</a></li>
                            <li><a class="dropdown-item text-danger fw-semibold" href="<?= BASE_URL ?>/views/admin/simulate_flood.php"><i class="bi bi-lightning-charge-fill me-2"></i> Simulation Studio (Viva Demo)</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><span class="dropdown-header text-uppercase text-secondary font-monospace" style="font-size: 0.7rem;">Hydrology &amp; Operations</span></li>
                            <li><a class="dropdown-item" href="<?= BASE_URL ?>/views/admin/import_data.php"><i class="bi bi-file-earmark-code text-info me-2"></i> Ingest Feeds (XML/JSON)</a></li>
                            <li><a class="dropdown-item" href="<?= BASE_URL ?>/views/admin/zones.php"><i class="bi bi-map me-2"></i> Zone Management</a></li>
                            <li><a class="dropdown-item" href="<?= BASE_URL ?>/views/admin/risk_rules.php"><i class="bi bi-sliders me-2"></i> Risk Rules Engine</a></li>
                            <li><a class="dropdown-item" href="<?= BASE_URL ?>/views/admin/camps.php"><i class="bi bi-hospital me-2"></i> Camps Master Directory</a></li>
                            <li><a class="dropdown-item" href="<?= BASE_URL ?>/views/admin/alerts.php"><i class="bi bi-broadcast text-danger me-2"></i> Broadcast Public Alerts</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><span class="dropdown-header text-uppercase text-secondary font-monospace" style="font-size: 0.7rem;">Governance &amp; Security</span></li>
                            <li><a class="dropdown-item" href="<?= BASE_URL ?>/views/admin/users.php"><i class="bi bi-people me-2"></i> Staff &amp; User Accounts</a></li>
                            <li><a class="dropdown-item" href="<?= BASE_URL ?>/views/admin/audit_logs.php"><i class="bi bi-clock-history me-2"></i> Security Audit Trail</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><span class="dropdown-header text-uppercase text-secondary font-monospace" style="font-size: 0.7rem;">Cross-Role Portals</span></li>
                            <li><a class="dropdown-item" href="<?= BASE_URL ?>/views/citizen/dashboard.php"><i class="bi bi-person-badge-fill text-success me-2"></i> Citizen Watch Portal</a></li>
                            <li><a class="dropdown-item" href="<?= BASE_URL ?>/views/coordinator/dashboard.php"><i class="bi bi-kanban text-info me-2"></i> Coordinator Dispatch Hub</a></li>
                        </ul>
                    </li>
                <?php else: ?>
                    <!-- Citizen Watch Link -->
                    <li class="nav-item">
                        <a class="nav-link" href="<?= BASE_URL ?>/views/citizen/dashboard.php">
                            <i class="bi bi-person-badge-fill text-success"></i> <span data-i18n="nav_citizen_watch">Citizen Watch</span>
                        </a>
                    </li>

                    <!-- Coordinator Portal Link -->
                    <?php if (Auth::isCoordinator()): ?>
                        <li class="nav-item">
                            <a class="nav-link text-info fw-semibold" href="<?= BASE_URL ?>/views/coordinator/dashboard.php">
                                <i class="bi bi-kanban"></i> Coordinator Hub
                            </a>
                        </li>
                    <?php endif; ?>
                <?php endif; ?>
            </ul>

            <!-- Right-Aligned Controls (Strictly Non-Shrinkable) -->
            <ul class="navbar-nav utility-nav-controls align-items-xl-center">
                <!-- Multi-Language Switcher Pill -->
                <li class="nav-item dropdown flex-shrink-0">
                    <a class="nav-link dropdown-toggle nav-pill-control" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false" title="Change Language">
                        <i class="bi bi-translate text-info me-1"></i>
                        <span id="currentLanguageLabel"><?= $langLabels[$currentLang] ?? 'English' ?></span>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-dark dropdown-menu-end shadow-lg">
                        <li><a class="dropdown-item lang-select-option <?= $currentLang === 'en' ? 'active fw-bold' : '' ?>" href="#" data-lang="en">🇬🇧 English</a></li>
                        <li><a class="dropdown-item lang-select-option <?= $currentLang === 'mr' ? 'active fw-bold' : '' ?>" href="#" data-lang="mr">🇮🇳 मराठी (Marathi)</a></li>
                        <li><a class="dropdown-item lang-select-option <?= $currentLang === 'hi' ? 'active fw-bold' : '' ?>" href="#" data-lang="hi">🇮🇳 हिंदी (Hindi)</a></li>
                    </ul>
                </li>

                <!-- User Profile Pill -->
                <?php if ($isAuth): ?>
                    <li class="nav-item dropdown flex-shrink-0">
                        <a class="nav-link dropdown-toggle nav-pill-control shadow-sm" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <div class="user-avatar-circle">
                                <i class="bi bi-shield-lock-fill text-warning" style="font-size: 0.75rem;"></i>
                            </div>
                            <div class="d-flex flex-column text-start me-1 justify-content-center" style="line-height: 1.1;">
                                <span class="text-white user-name-text"><?= Helper::e($userName) ?></span>
                                <span class="user-role-text font-monospace"><?= strtoupper(Helper::e($role)) ?></span>
                            </div>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-dark dropdown-menu-end shadow-lg">
                            <li><span class="dropdown-item-text text-muted small">Signed in as <strong><?= Helper::e($userName) ?></strong></span></li>
                            <li><span class="dropdown-item-text text-secondary small">Role: <span class="badge bg-warning-subtle text-warning-emphasis"><?= Helper::e($role) ?></span></span></li>
                            <li><hr class="dropdown-divider"></li>
                            <?php if (Auth::isAdmin()): ?>
                                <li><a class="dropdown-item" href="<?= BASE_URL ?>/views/admin/dashboard.php"><i class="bi bi-speedometer me-2"></i> Admin Overview</a></li>
                            <?php endif; ?>
                            <li><a class="dropdown-item" href="<?= BASE_URL ?>/views/citizen/dashboard.php"><i class="bi bi-person-badge me-2"></i> My Flood Watch</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <form action="<?= BASE_URL ?>/api/auth.php?action=logout" method="POST" class="px-2">
                                    <?= CSRF::inputField() ?>
                                    <input type="hidden" name="redirect" value="<?= BASE_URL ?>/views/auth/login.php">
                                    <button type="submit" class="btn btn-outline-danger btn-sm w-100 text-start">
                                        <i class="bi bi-box-arrow-right me-2"></i> <span data-i18n="nav_logout">Sign Out</span>
                                    </button>
                                </form>
                            </li>
                        </ul>
                    </li>
                <?php else: ?>
                    <li class="nav-item flex-shrink-0">
                        <a class="btn btn-outline-light btn-sm px-2 py-1 rounded-pill" style="font-size: 0.78rem;" href="<?= BASE_URL ?>/views/auth/login.php">
                            <i class="bi bi-lock-fill me-1"></i> <span data-i18n="nav_login">Login</span>
                        </a>
                    </li>
                    <li class="nav-item flex-shrink-0">
                        <a class="btn btn-primary btn-sm px-2 py-1 rounded-pill" style="font-size: 0.78rem;" href="<?= BASE_URL ?>/views/auth/register.php">
                            <i class="bi bi-person-plus-fill me-1"></i> <span data-i18n="nav_register">Register</span>
                        </a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>
