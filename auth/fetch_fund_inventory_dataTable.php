<?php
session_start();
require_once __DIR__ . '/../database/databaseConnection.php';
require_once __DIR__ . '/datatable_helpers.php';

header('Content-Type: application/json; charset=utf-8');

$draw = isset($_POST['draw']) ? (int)($_POST['draw'] ?? 0) : 0;
$fund = strtolower(trim((string)($_POST['fund'] ?? '')));
$funds = array(
    'trust' => array('table' => 'trust_fund', 'history' => 'trust_fund_history'),
    'donation' => array('table' => 'donation', 'history' => 'donation_history'),
);

if (!isset($funds[$fund])) {
    dt_json_empty($draw);
}

$table = $funds[$fund]['table'];
$historyTable = $funds[$fund]['history'];

if (!dt_information_schema_table_exists($conn, $table)) {
    dt_json_empty($draw);
}

$historyJoin = '';
if (dt_information_schema_table_exists($conn, $historyTable)) {
    $historyJoin = "LEFT JOIN (
        SELECT id, MAX(emp_id) AS emp_id, MAX(dept_id) AS dept_id, MAX(par_number) AS par_number, MAX(category) AS history_category
        FROM {$historyTable}
        WHERE status = 1
        GROUP BY id
    ) AS h ON h.id = f.id";
}

$baseFromSql = "FROM {$table} AS f
                {$historyJoin}
                LEFT JOIN employee AS e ON e.emp_id = h.emp_id
                LEFT JOIN department AS d ON d.department_code = h.dept_id";

$whereParts = array('1 = 1');
$whereTypes = '';
$whereParams = array();

$colSearch = isset($_POST['columns']) && is_array($_POST['columns']) ? $_POST['columns'] : array();
$itemFilter = '';
$empFilter = '';
$parIcsFilter = '';

$explicitItem = trim((string)($_POST['asset_class'] ?? ''));
$explicitEmp = trim((string)($_POST['end_user'] ?? ''));
$explicitParIcs = strtoupper(trim((string)($_POST['par_ics'] ?? '')));

if ($explicitItem !== '') {
    $itemFilter = $explicitItem;
} elseif (isset($colSearch[0]['search']['value']) && trim((string)$colSearch[0]['search']['value']) !== '') {
    $itemFilter = dt_extract_exact_value($colSearch[0]['search']['value']);
}

if ($explicitEmp !== '') {
    $empFilter = $explicitEmp;
} elseif (isset($colSearch[5]['search']['value']) && trim((string)$colSearch[5]['search']['value']) !== '') {
    $empFilter = dt_extract_exact_value($colSearch[5]['search']['value']);
}

if ($explicitParIcs === 'PAR' || $explicitParIcs === 'ICS') {
    $parIcsFilter = $explicitParIcs;
}

if ($itemFilter !== '') {
    $whereParts[] = 'f.item = ?';
    $whereTypes .= 's';
    $whereParams[] = $itemFilter;
}
if ($empFilter !== '') {
    $whereParts[] = 'e.emp_name = ?';
    $whereTypes .= 's';
    $whereParams[] = $empFilter;
}
if ($parIcsFilter !== '') {
    $whereParts[] = "REPLACE(UPPER(TRIM(COALESCE(f.category, h.history_category, ''))), '.', '') = ?";
    $whereTypes .= 's';
    $whereParams[] = $parIcsFilter;
}

$searchValue = trim((string)($_POST['search']['value'] ?? ''));
if ($searchValue !== '') {
    $like = '%' . $searchValue . '%';
    $whereParts[] = '(f.item LIKE ? OR f.model LIKE ? OR f.description LIKE ? OR f.serial_number LIKE ? OR f.serial_number_2 LIKE ? OR f.property_number LIKE ? OR e.emp_name LIKE ?)';
    $whereTypes .= 'sssssss';
    for ($i = 0; $i < 7; $i++) {
        $whereParams[] = $like;
    }
}

$whereSql = ' WHERE ' . implode(' AND ', $whereParts);

$totalRow = dt_execute_one($conn, "SELECT COUNT(*) AS cnt FROM {$table} AS f");
$recordsTotal = (int)($totalRow['cnt'] ?? 0);

$filteredRow = dt_execute_one(
    $conn,
    "SELECT COUNT(DISTINCT f.id) AS cnt {$baseFromSql}{$whereSql}",
    $whereTypes,
    $whereParams
);
$recordsFiltered = (int)($filteredRow['cnt'] ?? 0);

$columns = array(
    0 => 'f.item',
    1 => 'f.model',
    2 => 'f.serial_number',
    3 => 'f.serial_number_2',
    4 => 'f.property_number',
    5 => 'e.emp_name',
);
$orderColumn = isset($_POST['order'][0]['column']) ? (int)$_POST['order'][0]['column'] : 0;
$orderDir = strtolower((string)($_POST['order'][0]['dir'] ?? 'asc')) === 'desc' ? 'DESC' : 'ASC';
$orderBy = isset($columns[$orderColumn]) ? $columns[$orderColumn] : 'f.item';

$dataSql = "SELECT
                f.id AS fund_id,
                f.item,
                f.model,
                f.description,
                f.serial_number,
                f.serial_number_2,
                COALESCE(NULLIF(TRIM(f.property_number), ''), NULLIF(TRIM(h.par_number), ''), CONCAT('NPID:', f.id)) AS property_number,
                f.unit,
                f.date_aquired,
                COALESCE(f.category, h.history_category, '') AS category,
                COALESCE(e.emp_name, '') AS emp_name,
                COALESCE(h.dept_id, '') AS current_dept_id,
                COALESCE(d.department_name, '') AS current_dept_name
            {$baseFromSql}
            {$whereSql}
            ORDER BY {$orderBy} {$orderDir}";

$dataTypes = $whereTypes;
$dataParams = $whereParams;
$start = isset($_POST['start']) ? max(0, (int)$_POST['start']) : 0;
$length = isset($_POST['length']) ? (int)($_POST['length'] ?? 10) : 10;
if ($length !== -1) {
    $dataSql .= ' LIMIT ?, ?';
    $dataTypes .= 'ii';
    $dataParams[] = $start;
    $dataParams[] = max(0, $length);
}

list($stmt, $rows) = dt_execute_all($conn, $dataSql, $dataTypes, $dataParams);
dt_close_stmt($stmt);

$data = array();
foreach ($rows as $row) {
    $data[] = array(
        'fund_id' => (int)($row['fund_id'] ?? 0),
        'item' => (string)($row['item'] ?? ''),
        'model' => (string)($row['model'] ?? ''),
        'description' => (string)($row['description'] ?? ''),
        'serial_number' => (string)($row['serial_number'] ?? ''),
        'serial_number_2' => (string)($row['serial_number_2'] ?? ''),
        'par_number' => (string)($row['property_number'] ?? ''),
        'unit' => (string)($row['unit'] ?? ''),
        'date_aquired' => (string)($row['date_aquired'] ?? ''),
        'category' => (string)($row['category'] ?? ''),
        'emp_name' => (string)($row['emp_name'] ?? ''),
        'current_dept_id' => (string)($row['current_dept_id'] ?? ''),
        'current_dept_name' => (string)($row['current_dept_name'] ?? ''),
    );
}

echo json_encode(array(
    'draw' => $draw,
    'recordsTotal' => $recordsTotal,
    'recordsFiltered' => $recordsFiltered,
    'data' => $data,
));
