<?php
/**
 * Master Header Layout
 * Flood Risk Prediction & Relief Camp Locator
 */

require_once dirname(__DIR__, 2) . '/config/app.php';
require_once dirname(__DIR__, 2) . '/core/Auth.php';
require_once dirname(__DIR__, 2) . '/core/Helper.php';
require_once dirname(__DIR__, 2) . '/core/CSRF.php';
require_once dirname(__DIR__, 2) . '/core/I18n.php';

$pageTitle = $pageTitle ?? APP_NAME;
?>
<!DOCTYPE html>
<html lang="<?= I18n::getLang() ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= Helper::e($pageTitle) ?> | Disaster Decision Support</title>
    
    <!-- Meta & Accessibility -->
    <meta name="description" content="Flood risk prediction, hydrological observation scoring, and emergency relief camp locator.">
    <meta name="theme-color" content="#0d6efd">
    
    <!-- Bootstrap 5.3 CSS & Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    
    <!-- Leaflet CSS (Zero-failure map fallback) -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="" />
    
    <!-- Custom Disaster Management Styling -->
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/custom.css">
</head>
<body>
<?php require_once __DIR__ . '/alert_banner.php'; ?>
<?php require_once __DIR__ . '/navbar.php'; ?>

<!-- Flash Message Container -->
<div class="container mt-3">
    <?php if (Helper::hasFlash()): ?>
        <?php foreach (Helper::getFlash() as $flash): ?>
            <div class="alert alert-<?= Helper::e($flash['type']) ?> alert-dismissible fade show shadow-sm" role="alert">
                <i class="bi bi-info-circle-fill me-2"></i>
                <?= Helper::e($flash['message']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<main class="py-3">
