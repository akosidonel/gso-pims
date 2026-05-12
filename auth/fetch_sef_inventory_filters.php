<?php
session_start();
include '../database/databaseConnection.php';

// Returns distinct filter values for SEF inventory DataTable (serverSide=true)

header('Content-Type: application/json; charset=utf-8');

$deptId = isset($_POST['dept']) ? (int)$_POST['dept'] : 0;
$status = 1;

if ($deptId <= 0) {
    echo json_encode(array('asset_classes' => array(), 'end_users' => array()));
    exit;
}

// Cache filter lists in-session for a short time (distinct scans can be expensive on large tables).
// NOTE: versioned key to avoid serving stale/empty values from older buggy logic.
$cacheKey = 'sef_inventory_filters_' . $deptId . '_v2';
$cacheTtlSeconds = 300; // 5 minutes
if (isset($_SESSION[$cacheKey]) && is_array($_SESSION[$cacheKey])) {
    $cached = $_SESSION[$cacheKey];
    $cachedAt = isset($cached['cached_at']) ? (int)$cached['cached_at'] : 0;
    if ($cachedAt > 0 && (time() - $cachedAt) <= $cacheTtlSeconds) {
        echo json_encode(array(
            'asset_classes' => isset($cached['asset_classes']) && is_array($cached['asset_classes']) ? $cached['asset_classes'] : array(),
            'end_users' => isset($cached['end_users']) && is_array($cached['end_users']) ? $cached['end_users'] : array(),
        ));
        exit;
    }
}

$where = "WHERE sh.sch_id = $deptId AND sh.status = $status";

$assetClasses = array();
$endUsers = array();
$hadSqlError = false;

// Asset Class / Item
// Start from sef_property_history so sch_id/status can use indexes, then join to property_sef.
$sqlItems = "SELECT DISTINCT s.item AS v
             FROM sef_property_history AS sh
             INNER JOIN property_sef AS s ON s.property_number = sh.property_number
             $where
             ORDER BY s.item ASC";
$resItems = mysqli_query($conn, $sqlItems);
if ($resItems) {
    while ($r = mysqli_fetch_assoc($resItems)) {
        $v = isset($r['v']) ? trim((string)$r['v']) : '';
        if ($v !== '') $assetClasses[] = $v;
    }
} else {
    $hadSqlError = true;
}

// End User
$sqlUsers = "SELECT DISTINCT e.emp_name AS v
             FROM sef_property_history AS sh
             INNER JOIN employee e ON sh.emp_id = e.emp_id
             $where
             ORDER BY e.emp_name ASC";
$resUsers = mysqli_query($conn, $sqlUsers);
if ($resUsers) {
    while ($r = mysqli_fetch_assoc($resUsers)) {
        $v = isset($r['v']) ? trim((string)$r['v']) : '';
        if ($v !== '') $endUsers[] = $v;
    }
} else {
    $hadSqlError = true;
}

echo json_encode(array(
    'asset_classes' => $assetClasses,
    'end_users' => $endUsers,
));

// Only cache when queries succeeded; don't cache empty results caused by SQL errors.
if (!$hadSqlError) {
    $_SESSION[$cacheKey] = array(
        'cached_at' => time(),
        'asset_classes' => $assetClasses,
        'end_users' => $endUsers,
    );
}
