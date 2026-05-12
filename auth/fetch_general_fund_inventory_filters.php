<?php
session_start();
include '../database/databaseConnection.php';

// Returns distinct filter values for General Fund inventory DataTable (serverSide=true)

header('Content-Type: application/json; charset=utf-8');

$deptId = isset($_POST['dept']) ? (int)$_POST['dept'] : 0;
$status = 1;

if ($deptId <= 0) {
    echo json_encode(array('asset_classes' => array(), 'end_users' => array()));
    exit;
}

// Cache filter lists in-session for a short time.
$cacheKey = 'gf_inventory_filters_' . $deptId;
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

// Active inventory is keyed by the employee's department_code.
// Some legacy history rows stored department.dept_id in g.dept_id, so we still allow that
// as a secondary match, but only after anchoring the result set to the canonical employee department.
$deptPk = 0;
if ($deptId > 0) {
    if ($st = mysqli_prepare($conn, 'SELECT dept_id FROM department WHERE department_code = ? LIMIT 1')) {
        mysqli_stmt_bind_param($st, 'i', $deptId);
        mysqli_stmt_execute($st);
        $rs = mysqli_stmt_get_result($st);
        if ($rs && ($r = mysqli_fetch_assoc($rs))) {
            $deptPk = isset($r['dept_id']) ? (int)$r['dept_id'] : 0;
        }
        mysqli_stmt_close($st);
    }
}

$where = "WHERE g.status = $status";
if ($deptId > 0) {
    $where .= " AND e.department_code = $deptId";
    if ($deptPk > 0 && $deptPk !== $deptId) {
        $where .= " AND g.dept_id IN ($deptId, $deptPk)";
    } else {
        $where .= " AND g.dept_id = $deptId";
    }
}

$assetClasses = array();
$endUsers = array();

// Asset classes/items
$sqlItems = "SELECT DISTINCT p.item AS v
             FROM general_fund_property_history AS g
             STRAIGHT_JOIN employee e ON g.emp_id = e.emp_id
             STRAIGHT_JOIN par_gen_fund p ON g.par_number = p.par_number
             $where
             ORDER BY p.item ASC";
$resItems = mysqli_query($conn, $sqlItems);
if ($resItems) {
    while ($r = mysqli_fetch_assoc($resItems)) {
        $v = isset($r['v']) ? trim((string)$r['v']) : '';
        if ($v !== '') {
            $assetClasses[] = $v;
        }
    }
}

// End users
$sqlUsers = "SELECT DISTINCT e.emp_name AS v
             FROM general_fund_property_history AS g
             STRAIGHT_JOIN employee e ON g.emp_id = e.emp_id
             $where
             ORDER BY e.emp_name ASC";
$resUsers = mysqli_query($conn, $sqlUsers);
if ($resUsers) {
    while ($r = mysqli_fetch_assoc($resUsers)) {
        $v = isset($r['v']) ? trim((string)$r['v']) : '';
        if ($v !== '') {
            $endUsers[] = $v;
        }
    }
}

echo json_encode(array(
    'asset_classes' => $assetClasses,
    'end_users' => $endUsers,
));

$_SESSION[$cacheKey] = array(
    'cached_at' => time(),
    'asset_classes' => $assetClasses,
    'end_users' => $endUsers,
);
