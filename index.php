<?php
/**
 * Application Entry Point
 * Routes visitor to the Citizen Risk Dashboard
 */

require_once __DIR__ . '/config/app.php';
header("Location: " . BASE_URL . "/views/citizen/index.php");
exit;
