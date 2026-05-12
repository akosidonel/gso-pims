<?php
session_start();
include __DIR__ . '/../database/databaseConnection.php';

header('Content-Type: application/json; charset=utf-8');

// Lightweight server-side cache (reduces dashboard login delay)
$cacheKey = 'dashboard_metrics_cache_v7';
$cacheTtlSeconds = 60;

if (isset($_SESSION[$cacheKey]) && is_array($_SESSION[$cacheKey])) {
    $cached = $_SESSION[$cacheKey];
    $cachedAt = isset($cached['cached_at']) ? (int)$cached['cached_at'] : 0;
    if ($cachedAt > 0 && (time() - $cachedAt) <= $cacheTtlSeconds) {
        echo json_encode($cached['data']);
        exit;
    }
}

function dashboard_table_exists(mysqli $conn, $table) {
    static $cache = [];
    if (isset($cache[$table])) {
        return $cache[$table];
    }

    $safeTable = mysqli_real_escape_string($conn, $table);
    $res = mysqli_query($conn, "SHOW TABLES LIKE '{$safeTable}'");
    return $cache[$table] = ($res && mysqli_num_rows($res) > 0);
}

function dashboard_sum(mysqli $conn, $table, $column, $where = '') {
    if (!dashboard_table_exists($conn, $table)) {
        return 0.0;
    }

    $sql = "SELECT COALESCE(SUM({$column}), 0) AS total FROM {$table} {$where}";
    $res = mysqli_query($conn, $sql);
    $row = $res ? mysqli_fetch_assoc($res) : null;
    return (float)($row['total'] ?? 0);
}

function dashboard_count(mysqli $conn, $table) {
    if (!dashboard_table_exists($conn, $table)) {
        return 0;
    }

    $res = mysqli_query($conn, "SELECT COUNT(*) AS total FROM {$table}");
    $row = $res ? mysqli_fetch_assoc($res) : null;
    return (int)($row['total'] ?? 0);
}

$gftotal = dashboard_sum($conn, 'par_gen_fund', 'unit_value');
$seftotal = dashboard_sum($conn, 'property_sef', 'unit_value');
$landTotal = dashboard_sum($conn, 'land_properties', 'total_amount');
$newPurchaseTotal = dashboard_sum($conn, 'new_purchase', 'unit_value');
$infraGfTotal = dashboard_sum($conn, 'general_fund_infrastructure', 'amount', "WHERE record_status = 'ACTIVE'");
$infraSefTotal = dashboard_sum($conn, 'sef_infrastructure', 'amount', "WHERE record_status = 'ACTIVE'");
$adminCount = dashboard_count($conn, 'administrator');

// Equipment counts (General Fund + SEF)
$wantedItems = array(
    'FURNITURE AND FIXTURES',
    'DESKTOP COMPUTER',
    'LAPTOP',
    'AIRCONDITIONER',
    'MOTOR VEHICLE',
    'PRINTER',
    'SERVER',
    'OTHER MACHINERY AND EQUIPMENT',
);

$itemCounts = array(
    'furniture_count' => 0,
    'desktop_count' => 0,
    'laptop_count' => 0,
    'aircon_count' => 0,
    'vehicle_count' => 0,
    'printer_count' => 0,
    'server_count' => 0,
    'machinery_count' => 0,
);

$inList = "'" . implode("','", array_map(function ($v) use ($conn) {
    return mysqli_real_escape_string($conn, $v);
}, $wantedItems)) . "'";

$sql = "SELECT item, SUM(cnt) AS cnt
        FROM (
            SELECT p.item, COUNT(*) AS cnt
            FROM par_gen_fund AS p
            STRAIGHT_JOIN general_fund_property_history AS g
                ON g.par_number = p.par_number AND g.status = 1
            WHERE p.item IN ($inList)
            GROUP BY p.item

            UNION ALL

            SELECT s.item, COUNT(*) AS cnt
            FROM property_sef AS s
            STRAIGHT_JOIN sef_property_history AS sh
                ON sh.property_number = s.property_number AND sh.status = 1
            WHERE s.item IN ($inList)
            GROUP BY s.item
        ) AS equipment_counts
        GROUP BY item";
$res = mysqli_query($conn, $sql);
if ($res) {
    while ($row = mysqli_fetch_assoc($res)) {
        $item = strtoupper(trim((string)($row['item'] ?? '')));
        $cnt = (int)($row['cnt'] ?? 0);
        if ($item === 'FURNITURE AND FIXTURES') $itemCounts['furniture_count'] = $cnt;
        elseif ($item === 'DESKTOP COMPUTER') $itemCounts['desktop_count'] = $cnt;
        elseif ($item === 'LAPTOP') $itemCounts['laptop_count'] = $cnt;
        elseif ($item === 'AIRCONDITIONER') $itemCounts['aircon_count'] = $cnt;
        elseif ($item === 'MOTOR VEHICLE') $itemCounts['vehicle_count'] = $cnt;
        elseif ($item === 'PRINTER') $itemCounts['printer_count'] = $cnt;
        elseif ($item === 'SERVER') $itemCounts['server_count'] = $cnt;
        elseif ($item === 'OTHER MACHINERY AND EQUIPMENT') $itemCounts['machinery_count'] = $cnt;
    }
}

$data = array_merge(array(
    'gftotal' => $gftotal,
    'seftotal' => $seftotal,
    'land_total' => $landTotal,
    'new_purchase_total' => $newPurchaseTotal,
    'infrastructure_gf_total' => $infraGfTotal,
    'infrastructure_sef_total' => $infraSefTotal,
    'admin_count' => $adminCount,
), $itemCounts);

$_SESSION[$cacheKey] = array(
    'cached_at' => time(),
    'data' => $data,
);

echo json_encode($data);
