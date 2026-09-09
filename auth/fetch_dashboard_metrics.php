<?php
require_once __DIR__ . '/../config/session_bootstrap.php';
gso_start_secure_session();
header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['alogin'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized.']);
    exit;
}

$section = (string)($_GET['section'] ?? 'all');
if (!in_array($section, ['general', 'equipment', 'all'], true)) {
    http_response_code(422);
    echo json_encode(['error' => 'Invalid dashboard section.']);
    exit;
}

// Share the existing 60-second cache window across dashboard visits.
$cacheFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR
    . 'gso_dashboard_' . sha1(__DIR__ . '|v9|' . $section) . '.json';
$cached = is_file($cacheFile) ? json_decode((string)@file_get_contents($cacheFile), true) : null;
$age = time() - (int)($cached['cached_at'] ?? 0);
if (is_array($cached) && $age >= 0 && $age <= 60 && isset($cached['data'])) {
    session_write_close();
    echo json_encode($cached['data']);
    exit;
}

// auth.php releases the session before connecting to the database.
define('GSO_DASHBOARD_METRICS_REQUEST', true);
require_once __DIR__ . '/auth.php';

try {
    $data = [];
    if ($section !== 'equipment') {
        $data = gso_fetch_dashboard_general_metrics($conn);
    }
    if ($section !== 'general') {
        $data = array_merge($data, gso_fetch_dashboard_equipment_metrics($conn));
    }
    @file_put_contents($cacheFile, json_encode(['cached_at' => time(), 'data' => $data]), LOCK_EX);
    echo json_encode($data);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Unable to load dashboard metrics. Please refresh to retry.']);
}
