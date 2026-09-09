<?php
// php tools/perf/dashboard_profile.php [--add-indexes]
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
define('GSO_DASHBOARD_METRICS_REQUEST', true);
require_once __DIR__ . '/../../auth/auth.php';

if (in_array('--add-indexes', $argv, true)) {
    $added = gso_ensure_dashboard_total_indexes($conn);
    echo $added ? 'Added amount indexes: ' . implode(', ', $added) . PHP_EOL : "Amount indexes already present.\n";
}

foreach (['General Information' => 'gso_fetch_dashboard_general_metrics', 'Equipment counts' => 'gso_fetch_dashboard_equipment_metrics'] as $label => $fetch) {
    $start = microtime(true);
    $data = $fetch($conn);
    printf("%s: %.1f ms (%d metrics)\n", $label, (microtime(true) - $start) * 1000, count($data));
}
