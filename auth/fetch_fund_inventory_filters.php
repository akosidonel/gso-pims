<?php
session_start();
include '../database/databaseConnection.php';

header('Content-Type: application/json; charset=utf-8');

$fund = strtolower(trim((string)($_POST['fund'] ?? '')));
$funds = array(
    'trust' => array('table' => 'trust_fund', 'history' => 'trust_fund_history'),
    'donation' => array('table' => 'donation', 'history' => 'donation_history'),
);

if (!isset($funds[$fund])) {
    echo json_encode(array('asset_classes' => array(), 'end_users' => array()));
    exit;
}

$table = $funds[$fund]['table'];
$historyTable = $funds[$fund]['history'];

if (!function_exists('fund_filter_table_exists')) {
    function fund_filter_table_exists(mysqli $conn, $table) {
        $safeTable = mysqli_real_escape_string($conn, $table);
        $res = mysqli_query($conn, "SHOW TABLES LIKE '{$safeTable}'");
        return $res && mysqli_num_rows($res) > 0;
    }
}

if (!fund_filter_table_exists($conn, $table)) {
    echo json_encode(array('asset_classes' => array(), 'end_users' => array()));
    exit;
}

$cacheKey = 'fund_inventory_filters_' . $fund . '_v1';
$cacheTtlSeconds = 300;
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

$historyJoin = '';
if (fund_filter_table_exists($conn, $historyTable)) {
    $historyJoin = "LEFT JOIN (
        SELECT id, MAX(emp_id) AS emp_id
        FROM {$historyTable}
        WHERE status = 1
        GROUP BY id
    ) h ON h.id = f.id";
}

$assetClasses = array();
$endUsers = array();
$hadSqlError = false;

$sqlItems = "SELECT DISTINCT f.item AS v
             FROM {$table} f
             WHERE f.item IS NOT NULL AND TRIM(f.item) <> ''
             ORDER BY f.item ASC";
$resItems = mysqli_query($conn, $sqlItems);
if ($resItems) {
    while ($row = mysqli_fetch_assoc($resItems)) {
        $assetClasses[] = trim((string)$row['v']);
    }
} else {
    $hadSqlError = true;
}

$sqlUsers = "SELECT DISTINCT e.emp_name AS v
             FROM {$table} f
             {$historyJoin}
             INNER JOIN employee e ON e.emp_id = h.emp_id
             WHERE e.emp_name IS NOT NULL AND TRIM(e.emp_name) <> ''
             ORDER BY e.emp_name ASC";
$resUsers = mysqli_query($conn, $sqlUsers);
if ($resUsers) {
    while ($row = mysqli_fetch_assoc($resUsers)) {
        $endUsers[] = trim((string)$row['v']);
    }
} else {
    $hadSqlError = true;
}

echo json_encode(array(
    'asset_classes' => $assetClasses,
    'end_users' => $endUsers,
));

if (!$hadSqlError) {
    $_SESSION[$cacheKey] = array(
        'cached_at' => time(),
        'asset_classes' => $assetClasses,
        'end_users' => $endUsers,
    );
}
