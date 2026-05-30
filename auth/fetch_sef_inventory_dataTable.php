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

$whereParts = array('sh.status = ?');
$whereTypes = 'i';
$whereParams = array(1);

if ($deptCode > 0 && $deptPk > 0) {
    $whereParts[] = '(sh.sch_id = ? OR sh.sch_id = ?)';
    $whereTypes .= 'ii';
    $whereParams[] = $deptCode;
    $whereParams[] = $deptPk;
} elseif ($deptCode > 0) {
    $whereParts[] = 'sh.sch_id = ?';
    $whereTypes .= 'i';
    $whereParams[] = $deptCode;
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
    $whereParts[] = 's.item = ?';
    $whereTypes .= 's';
    $whereParams[] = $itemFilter;
}
if ($empFilter !== '') {
    $whereParts[] = 'e.emp_name = ?';
    $whereTypes .= 's';
    $whereParams[] = $empFilter;
}
if ($parIcsFilter !== '') {
    $whereParts[] = "REPLACE(UPPER(TRIM(s.category)), '.', '') = ?";
    $whereTypes .= 's';
    $whereParams[] = $parIcsFilter;
}

$searchValue = trim((string)($_POST['search']['value'] ?? ''));
if ($searchValue !== '') {
    $like = '%' . $searchValue . '%';
    $whereParts[] = '(s.item LIKE ? OR s.model LIKE ? OR s.description LIKE ? OR s.serial_number LIKE ? OR s.serial_number_2 LIKE ? OR sh.property_number LIKE ? OR e.emp_name LIKE ?)';
    $whereTypes .= 'sssssss';
    for ($i = 0; $i < 7; $i++) {
        $whereParams[] = $like;
    }
}

$whereSql = ' WHERE ' . implode(' AND ', $whereParts);
$baseFromSql = "FROM sef_property_history AS sh
                INNER JOIN property_sef AS s ON s.property_number = sh.property_number
                INNER JOIN employee AS e ON e.emp_id = sh.emp_id";

$countSql = "SELECT COUNT(*) AS cnt {$baseFromSql}{$whereSql}";
$countRow = dt_execute_one($conn, $countSql, $whereTypes, $whereParams);
$recordsFiltered = (int)($countRow['cnt'] ?? 0);

$totalCountSql = "SELECT COUNT(*) AS cnt FROM sef_property_history AS sh WHERE " . implode(' AND ', array_slice($whereParts, 0, $deptCode > 0 ? count($whereParts) >= 2 ? 2 : 1 : 1));
$totalTypes = 'i';
$totalParams = array(1);
if ($deptCode > 0 && $deptPk > 0) {
    $totalTypes .= 'ii';
    $totalParams[] = $deptCode;
    $totalParams[] = $deptPk;
} elseif ($deptCode > 0) {
    $totalTypes .= 'i';
    $totalParams[] = $deptCode;
}
$totalRow = dt_execute_one($conn, $totalCountSql, $totalTypes, $totalParams);
$recordsTotal = (int)($totalRow['cnt'] ?? 0);

$columns = array(
    2 => 's.item',
    3 => 's.model',
    4 => 's.serial_number',
    5 => 's.serial_number_2',
    6 => 'sh.property_number',
    7 => 'e.emp_name',
);
$orderColumn = isset($_POST['order'][0]['column']) ? (int)$_POST['order'][0]['column'] : 6;
$orderDir = strtolower((string)($_POST['order'][0]['dir'] ?? 'asc')) === 'desc' ? 'DESC' : 'ASC';
$orderBy = isset($columns[$orderColumn]) ? $columns[$orderColumn] : 'sh.property_number';

$dataSql = "SELECT
                s.item,
                s.model,
                s.description,
                s.serial_number,
                s.serial_number_2,
                sh.property_number AS par_number,
                s.date_aquired,
                s.category,
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
