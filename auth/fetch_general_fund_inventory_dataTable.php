<?php
session_start();
include '../database/databaseConnection.php';

// DataTables server-side processing for General Fund inventory

header('Content-Type: application/json; charset=utf-8');

$deptCode = isset($_POST['dept']) ? (int)$_POST['dept'] : 0;
$status = 1;

// Active inventory is keyed by the employee's department_code.
// Some legacy history rows stored department.dept_id in g.dept_id, so we still allow that
// as a secondary match, but only after anchoring the result set to the canonical employee department.
$deptPk = 0;
if ($deptCode > 0) {
    if ($st = mysqli_prepare($conn, 'SELECT dept_id FROM department WHERE department_code = ? LIMIT 1')) {
        mysqli_stmt_bind_param($st, 'i', $deptCode);
        mysqli_stmt_execute($st);
        $rs = mysqli_stmt_get_result($st);
        if ($rs && ($r = mysqli_fetch_assoc($rs))) {
            $deptPk = isset($r['dept_id']) ? (int)$r['dept_id'] : 0;
        }
        mysqli_stmt_close($st);
    }
}

$where = "WHERE g.status = $status";
if ($deptCode > 0) {
    $where .= " AND e.department_code = $deptCode";
    if ($deptPk > 0 && $deptPk !== $deptCode) {
        $where .= " AND g.dept_id IN ($deptCode, $deptPk)";
    } else {
        $where .= " AND g.dept_id = $deptCode";
    }
}

// Total records for the scoped department.
$countSql = "SELECT COUNT(*) AS cnt
             FROM general_fund_property_history AS g
             STRAIGHT_JOIN employee e ON g.emp_id = e.emp_id
             $where";
$countRes = mysqli_query($conn, $countSql);
$totalData = 0;
if ($countRes && ($row = mysqli_fetch_assoc($countRes))) {
    $totalData = (int)$row['cnt'];
}

// Main query
// IMPORTANT:
// We must start from `general_fund_property_history` so the dept/status filter is applied early.
// Without this, MySQL may choose a bad plan (e.g., starting from `employee` or `par_gen_fund`),
// which makes DataTables requests feel "super slow" on large datasets.
$tableSql = "SELECT
                p.item,
                p.model,
                p.description,
                p.serial_number,
                p.serial_number_2,
                p.par_number,
                p.date_aquired,
                p.category,
                e.emp_name
            FROM general_fund_property_history AS g
            STRAIGHT_JOIN par_gen_fund p ON g.par_number = p.par_number
            STRAIGHT_JOIN employee e ON g.emp_id = e.emp_id
            $where";

// Column-specific filters (from explicit params or DataTables column search)
function dt_extract_exact_value($rawValue) {
    $rawValue = trim((string)$rawValue);
    if (preg_match('/^\^(.*)\$$/s', $rawValue, $m)) {
        return $m[1];
    }
    return $rawValue;
}

$colSearch = isset($_POST['columns']) && is_array($_POST['columns']) ? $_POST['columns'] : array();
$itemFilter = '';
$empFilter = '';
$parIcsFilter = '';

$explicitItem = isset($_POST['asset_class']) ? trim((string)$_POST['asset_class']) : '';
$explicitEmp  = isset($_POST['end_user']) ? trim((string)$_POST['end_user']) : '';
$explicitParIcs = isset($_POST['par_ics']) ? strtoupper(trim((string)$_POST['par_ics'])) : '';

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
    $safeItem = mysqli_real_escape_string($conn, $itemFilter);
    $tableSql .= " AND p.item = '$safeItem'";
}
if ($empFilter !== '') {
    $safeEmp = mysqli_real_escape_string($conn, $empFilter);
    $tableSql .= " AND e.emp_name = '$safeEmp'";
}
if ($parIcsFilter !== '') {
    $safeParIcs = mysqli_real_escape_string($conn, $parIcsFilter);
    $tableSql .= " AND UPPER(TRIM(p.category)) = '$safeParIcs'";
}

// Global search
$searchValue = isset($_POST['search']['value']) ? trim($_POST['search']['value']) : '';
if ($searchValue !== '') {
    $safe = mysqli_real_escape_string($conn, $searchValue);
    $tableSql .= " AND (
        p.item LIKE '%$safe%' OR
        p.model LIKE '%$safe%' OR
        p.description LIKE '%$safe%' OR
        p.serial_number LIKE '%$safe%' OR
        p.serial_number_2 LIKE '%$safe%' OR
        p.par_number LIKE '%$safe%' OR
        e.emp_name LIKE '%$safe%'
    )";
}

// Order mapping (matches DataTable columns)
$columns = array(
    0 => null,
    1 => null,
    2 => 'p.item',
    3 => 'p.model',
    4 => 'p.serial_number',
    5 => 'p.serial_number_2',
    6 => 'p.par_number',
    7 => 'e.emp_name',
);

if (isset($_POST['order'][0]['column'])) {
    $colIdx = (int)$_POST['order'][0]['column'];
    $dir = (isset($_POST['order'][0]['dir']) && $_POST['order'][0]['dir'] === 'desc') ? 'DESC' : 'ASC';
    if (isset($columns[$colIdx]) && $columns[$colIdx] !== null) {
        $tableSql .= ' ORDER BY ' . $columns[$colIdx] . ' ' . $dir;
    } else {
        $tableSql .= ' ORDER BY p.item ASC';
    }
} else {
    $tableSql .= ' ORDER BY p.item ASC';
}

// Paging
$start = isset($_POST['start']) ? (int)$_POST['start'] : 0;
$length = isset($_POST['length']) ? (int)$_POST['length'] : 10;
if ($length !== -1) {
    $tableSql .= ' LIMIT ' . $start . ',' . $length;
}

$runQuery = mysqli_query($conn, $tableSql);
$data = array();

if ($runQuery) {
    while ($row = mysqli_fetch_assoc($runQuery)) {
        $data[] = array(
            'item' => $row['item'],
            'model' => $row['model'],
            'description' => $row['description'],
            'serial_number' => $row['serial_number'],
            'serial_number_2' => $row['serial_number_2'],
            'par_number' => $row['par_number'],
            'date_aquired' => $row['date_aquired'],
            'category' => $row['category'],
            'emp_name' => $row['emp_name'],
        );
    }
}

// Filtered count
$needsFiltersCount = ($itemFilter !== '' || $empFilter !== '' || $parIcsFilter !== '');
$hasSearch = ($searchValue !== '');

if (!$hasSearch && !$needsFiltersCount) {
    $totalFiltered = $totalData;
} else {
    $countFilteredSql = 'SELECT COUNT(*) AS cnt FROM general_fund_property_history AS g';

    if ($deptCode > 0 || $empFilter !== '' || $hasSearch) {
        $countFilteredSql .= ' STRAIGHT_JOIN employee e ON g.emp_id = e.emp_id';
    }

    // Only join tables we need for filters/search
    if ($itemFilter !== '' || $parIcsFilter !== '' || $hasSearch) {
        $countFilteredSql .= ' STRAIGHT_JOIN par_gen_fund p ON g.par_number = p.par_number';
    }

    $countFilteredSql .= " $where";

    if ($itemFilter !== '') {
        $safeItem2 = mysqli_real_escape_string($conn, $itemFilter);
        $countFilteredSql .= " AND p.item = '$safeItem2'";
    }
    if ($empFilter !== '') {
        $safeEmp2 = mysqli_real_escape_string($conn, $empFilter);
        $countFilteredSql .= " AND e.emp_name = '$safeEmp2'";
    }
    if ($parIcsFilter !== '') {
        $safeParIcs2 = mysqli_real_escape_string($conn, $parIcsFilter);
        $countFilteredSql .= " AND UPPER(TRIM(p.category)) = '$safeParIcs2'";
    }

    if ($hasSearch) {
        $safe = mysqli_real_escape_string($conn, $searchValue);
        $countFilteredSql .= " AND (
            p.item LIKE '%$safe%' OR
            p.model LIKE '%$safe%' OR
            p.description LIKE '%$safe%' OR
            p.serial_number LIKE '%$safe%' OR
            p.serial_number_2 LIKE '%$safe%' OR
            p.par_number LIKE '%$safe%' OR
            e.emp_name LIKE '%$safe%'
        )";
    }

    $countFilteredRes = mysqli_query($conn, $countFilteredSql);
    $totalFiltered = 0;
    if ($countFilteredRes && ($row2 = mysqli_fetch_assoc($countFilteredRes))) {
        $totalFiltered = (int)$row2['cnt'];
    }
}

$output = array(
    'draw' => isset($_POST['draw']) ? (int)$_POST['draw'] : 0,
    'recordsTotal' => $totalData,
    'recordsFiltered' => $totalFiltered,
    'data' => $data,
);

echo json_encode($output);
