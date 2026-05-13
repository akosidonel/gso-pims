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
    echo json_encode(array('draw' => isset($_POST['draw']) ? (int)$_POST['draw'] : 0, 'recordsTotal' => 0, 'recordsFiltered' => 0, 'data' => array()));
    exit;
}

$table = $funds[$fund]['table'];
$historyTable = $funds[$fund]['history'];

if (!function_exists('fund_dt_table_exists')) {
    function fund_dt_table_exists(mysqli $conn, $table) {
        $safeTable = mysqli_real_escape_string($conn, $table);
        $res = mysqli_query($conn, "SHOW TABLES LIKE '{$safeTable}'");
        return $res && mysqli_num_rows($res) > 0;
    }
}

if (!function_exists('fund_dt_extract_exact_value')) {
    function fund_dt_extract_exact_value($rawValue) {
        $rawValue = trim((string)$rawValue);
        if (preg_match('/^\^(.*)\$$/s', $rawValue, $m)) {
            return $m[1];
        }
        return $rawValue;
    }
}

if (!fund_dt_table_exists($conn, $table)) {
    echo json_encode(array('draw' => isset($_POST['draw']) ? (int)$_POST['draw'] : 0, 'recordsTotal' => 0, 'recordsFiltered' => 0, 'data' => array()));
    exit;
}

$historyJoin = '';
if (fund_dt_table_exists($conn, $historyTable)) {
    $historyJoin = "LEFT JOIN (
        SELECT id, MAX(emp_id) AS emp_id, MAX(dept_id) AS dept_id, MAX(par_number) AS par_number, MAX(category) AS history_category
        FROM {$historyTable}
        WHERE status = 1
        GROUP BY id
    ) h ON h.id = f.id";
}

$fromSql = "FROM {$table} f
            {$historyJoin}
            LEFT JOIN employee e ON e.emp_id = h.emp_id";
$whereParts = array('1 = 1');

$colSearch = isset($_POST['columns']) && is_array($_POST['columns']) ? $_POST['columns'] : array();
$itemFilter = '';
$empFilter = '';
$parIcsFilter = '';

$explicitItem = isset($_POST['asset_class']) ? trim((string)$_POST['asset_class']) : '';
$explicitEmp = isset($_POST['end_user']) ? trim((string)$_POST['end_user']) : '';
$explicitParIcs = isset($_POST['par_ics']) ? strtoupper(trim((string)$_POST['par_ics'])) : '';

if ($explicitItem !== '') {
    $itemFilter = $explicitItem;
} elseif (isset($colSearch[0]['search']['value']) && trim((string)$colSearch[0]['search']['value']) !== '') {
    $itemFilter = fund_dt_extract_exact_value($colSearch[0]['search']['value']);
}

if ($explicitEmp !== '') {
    $empFilter = $explicitEmp;
} elseif (isset($colSearch[5]['search']['value']) && trim((string)$colSearch[5]['search']['value']) !== '') {
    $empFilter = fund_dt_extract_exact_value($colSearch[5]['search']['value']);
}

if ($explicitParIcs === 'PAR' || $explicitParIcs === 'ICS') {
    $parIcsFilter = $explicitParIcs;
}

if ($itemFilter !== '') {
    $safeItem = mysqli_real_escape_string($conn, $itemFilter);
    $whereParts[] = "f.item = '{$safeItem}'";
}
if ($empFilter !== '') {
    $safeEmp = mysqli_real_escape_string($conn, $empFilter);
    $whereParts[] = "e.emp_name = '{$safeEmp}'";
}
if ($parIcsFilter !== '') {
    $safeParIcs = mysqli_real_escape_string($conn, $parIcsFilter);
    $whereParts[] = "REPLACE(UPPER(TRIM(COALESCE(f.category, h.history_category, ''))), '.', '') = '{$safeParIcs}'";
}

$searchValue = isset($_POST['search']['value']) ? trim((string)$_POST['search']['value']) : '';
if ($searchValue !== '') {
    $safe = mysqli_real_escape_string($conn, $searchValue);
    $whereParts[] = "(
        f.item LIKE '%{$safe}%' OR
        f.model LIKE '%{$safe}%' OR
        f.description LIKE '%{$safe}%' OR
        f.serial_number LIKE '%{$safe}%' OR
        f.serial_number_2 LIKE '%{$safe}%' OR
        f.property_number LIKE '%{$safe}%' OR
        e.emp_name LIKE '%{$safe}%'
    )";
}

$whereSql = 'WHERE ' . implode(' AND ', $whereParts);

$countTotalSql = "SELECT COUNT(*) AS cnt FROM {$table} f";
$countTotalRes = mysqli_query($conn, $countTotalSql);
$totalData = 0;
if ($countTotalRes && ($row = mysqli_fetch_assoc($countTotalRes))) {
    $totalData = (int)$row['cnt'];
}

$countFilteredSql = "SELECT COUNT(DISTINCT f.id) AS cnt {$fromSql} {$whereSql}";
$countFilteredRes = mysqli_query($conn, $countFilteredSql);
$totalFiltered = 0;
if ($countFilteredRes && ($row = mysqli_fetch_assoc($countFilteredRes))) {
    $totalFiltered = (int)$row['cnt'];
}

$columns = array(
    0 => 'f.item',
    1 => 'f.model',
    2 => 'f.serial_number',
    3 => 'f.serial_number_2',
    4 => 'f.property_number',
    5 => 'e.emp_name',
);

$orderSql = ' ORDER BY f.item ASC';
if (isset($_POST['order'][0]['column'])) {
    $colIdx = (int)$_POST['order'][0]['column'];
    $dir = (isset($_POST['order'][0]['dir']) && $_POST['order'][0]['dir'] === 'desc') ? 'DESC' : 'ASC';
    if (isset($columns[$colIdx])) {
        $orderSql = ' ORDER BY ' . $columns[$colIdx] . ' ' . $dir;
    }
}

$start = isset($_POST['start']) ? max(0, (int)$_POST['start']) : 0;
$length = isset($_POST['length']) ? (int)$_POST['length'] : 10;
$limitSql = $length !== -1 ? ' LIMIT ' . $start . ',' . max(0, $length) : '';

$dataSql = "SELECT
                f.item,
                f.model,
                f.description,
                f.serial_number,
                f.serial_number_2,
                f.property_number,
                f.unit,
                f.date_aquired,
                COALESCE(f.category, h.history_category, '') AS category,
                COALESCE(e.emp_name, '') AS emp_name
            {$fromSql}
            {$whereSql}
            {$orderSql}
            {$limitSql}";

$runQuery = mysqli_query($conn, $dataSql);
$data = array();
if ($runQuery) {
    while ($row = mysqli_fetch_assoc($runQuery)) {
        $data[] = array(
            'item' => $row['item'],
            'model' => $row['model'],
            'description' => $row['description'],
            'serial_number' => $row['serial_number'],
            'serial_number_2' => $row['serial_number_2'],
            'par_number' => $row['property_number'],
            'unit' => $row['unit'],
            'date_aquired' => $row['date_aquired'],
            'category' => $row['category'],
            'emp_name' => $row['emp_name'],
        );
    }
}

echo json_encode(array(
    'draw' => isset($_POST['draw']) ? (int)$_POST['draw'] : 0,
    'recordsTotal' => $totalData,
    'recordsFiltered' => $totalFiltered,
    'data' => $data,
));
