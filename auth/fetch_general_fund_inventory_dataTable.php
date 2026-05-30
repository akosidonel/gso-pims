<?php
session_start();
require_once __DIR__ . '/../database/databaseConnection.php';
require_once __DIR__ . '/datatable_helpers.php';

header('Content-Type: application/json; charset=utf-8');

$draw = isset($_POST['draw']) ? (int)$_POST['draw'] : 0;
$deptCode = isset($_POST['dept']) ? (int)$_POST['dept'] : 0;
$deptPk = 0;

if ($deptCode > 0) {
    $deptRow = dt_execute_one(
        $conn,
        'SELECT dept_id FROM department WHERE department_code = ? LIMIT 1',
        'i',
        array($deptCode)
    );
    $deptPk = (int)($deptRow['dept_id'] ?? 0);
}

$whereParts = array('g.status = ?');
$whereTypes = 'i';
$whereParams = array(1);

if ($deptCode > 0) {
    if ($deptPk > 0 && $deptPk !== $deptCode) {
        $whereParts[] = '(e.department_code = ? OR g.dept_id = ? OR g.dept_id = ?)';
        $whereTypes .= 'iii';
        $whereParams[] = $deptCode;
        $whereParams[] = $deptCode;
        $whereParams[] = $deptPk;
    } else {
        $whereParts[] = '(e.department_code = ? OR g.dept_id = ?)';
        $whereTypes .= 'ii';
        $whereParams[] = $deptCode;
        $whereParams[] = $deptCode;
    }
}

$colSearch = isset($_POST['columns']) && is_array($_POST['columns']) ? $_POST['columns'] : array();
$itemFilter = '';
$empFilter = '';
$parIcsFilter = '';

$explicitItem = trim((string)($_POST['asset_class'] ?? ''));
$explicitEmp = trim((string)($_POST['end_user'] ?? ''));
$explicitParIcs = strtoupper(trim((string)($_POST['par_ics'] ?? '')));

if ($explicitItem !== '') {
    $itemFilter = $explicitItem;
} elseif (isset($colSearch[2]['search']['value']) && trim((string)$colSearch[2]['search']['value']) !== '') {
    $itemFilter = dt_extract_exact_value($colSearch[2]['search']['value']);
}

if ($explicitEmp !== '') {
    $empFilter = $explicitEmp;
} elseif (isset($colSearch[7]['search']['value']) && trim((string)$colSearch[7]['search']['value']) !== '') {
    $empFilter = dt_extract_exact_value($colSearch[7]['search']['value']);
}

if ($explicitParIcs === 'PAR' || $explicitParIcs === 'ICS') {
    $parIcsFilter = $explicitParIcs;
}

if ($itemFilter !== '') {
    $whereParts[] = 'p.item = ?';
    $whereTypes .= 's';
    $whereParams[] = $itemFilter;
}
if ($empFilter !== '') {
    $whereParts[] = 'e.emp_name = ?';
    $whereTypes .= 's';
    $whereParams[] = $empFilter;
}
if ($parIcsFilter !== '') {
    $whereParts[] = 'UPPER(TRIM(p.category)) = ?';
    $whereTypes .= 's';
    $whereParams[] = $parIcsFilter;
}

$searchValue = trim((string)($_POST['search']['value'] ?? ''));
if ($searchValue !== '') {
    $like = '%' . $searchValue . '%';
    $whereParts[] = '(p.item LIKE ? OR p.model LIKE ? OR p.description LIKE ? OR p.serial_number LIKE ? OR p.serial_number_2 LIKE ? OR p.par_number LIKE ? OR e.emp_name LIKE ?)';
    $whereTypes .= 'sssssss';
    for ($i = 0; $i < 7; $i++) {
        $whereParams[] = $like;
    }
}

$whereSql = ' WHERE ' . implode(' AND ', $whereParts);
$unitSelect = dt_information_schema_column_exists($conn, 'par_gen_fund', 'unit') ? 'p.unit' : "'' AS unit";

$baseFromSql = "FROM general_fund_property_history AS g
                STRAIGHT_JOIN par_gen_fund AS p ON g.par_number = p.par_number
                STRAIGHT_JOIN employee AS e ON g.emp_id = e.emp_id";

$countSql = "SELECT COUNT(*) AS cnt {$baseFromSql}{$whereSql}";
$countRow = dt_execute_one($conn, $countSql, $whereTypes, $whereParams);
$recordsFiltered = (int)($countRow['cnt'] ?? 0);

$totalParams = array(1);
$totalTypes = 'i';
if ($deptCode > 0) {
    if ($deptPk > 0 && $deptPk !== $deptCode) {
        $totalSql = "SELECT COUNT(*) AS cnt
                     FROM general_fund_property_history AS g
                     STRAIGHT_JOIN employee AS e ON g.emp_id = e.emp_id
                     WHERE g.status = ? AND (e.department_code = ? OR g.dept_id = ? OR g.dept_id = ?)";
        $totalTypes .= 'iii';
        $totalParams[] = $deptCode;
        $totalParams[] = $deptCode;
        $totalParams[] = $deptPk;
    } else {
        $totalSql = "SELECT COUNT(*) AS cnt
                     FROM general_fund_property_history AS g
                     STRAIGHT_JOIN employee AS e ON g.emp_id = e.emp_id
                     WHERE g.status = ? AND (e.department_code = ? OR g.dept_id = ?)";
        $totalTypes .= 'ii';
        $totalParams[] = $deptCode;
        $totalParams[] = $deptCode;
    }
} else {
    $totalSql = "SELECT COUNT(*) AS cnt
                 FROM general_fund_property_history AS g
                 STRAIGHT_JOIN employee AS e ON g.emp_id = e.emp_id
                 WHERE g.status = ?";
}
$totalRow = dt_execute_one($conn, $totalSql, $totalTypes, $totalParams);
$recordsTotal = (int)($totalRow['cnt'] ?? 0);

$columns = array(
    2 => 'p.item',
    3 => 'p.model',
    4 => 'p.serial_number',
    5 => 'p.serial_number_2',
    6 => 'p.par_number',
    7 => 'e.emp_name',
);
$orderColumn = isset($_POST['order'][0]['column']) ? (int)$_POST['order'][0]['column'] : 2;
$orderDir = strtolower((string)($_POST['order'][0]['dir'] ?? 'asc')) === 'desc' ? 'DESC' : 'ASC';
$orderBy = isset($columns[$orderColumn]) ? $columns[$orderColumn] : 'p.item';

$dataSql = "SELECT
                p.item,
                p.model,
                p.description,
                p.serial_number,
                p.serial_number_2,
                p.par_number,
                {$unitSelect},
                p.date_aquired,
                p.category,
                e.emp_name
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
        'item' => (string)($row['item'] ?? ''),
        'model' => (string)($row['model'] ?? ''),
        'description' => (string)($row['description'] ?? ''),
        'serial_number' => (string)($row['serial_number'] ?? ''),
        'serial_number_2' => (string)($row['serial_number_2'] ?? ''),
        'par_number' => (string)($row['par_number'] ?? ''),
        'unit' => (string)($row['unit'] ?? ''),
        'date_aquired' => (string)($row['date_aquired'] ?? ''),
        'category' => (string)($row['category'] ?? ''),
        'emp_name' => (string)($row['emp_name'] ?? ''),
    );
}

echo json_encode(array(
    'draw' => $draw,
    'recordsTotal' => $recordsTotal,
    'recordsFiltered' => $recordsFiltered,
    'data' => $data,
));
