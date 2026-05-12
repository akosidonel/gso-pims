<?php
session_start();
include '../database/databaseConnection.php';

// DataTables server-side processing for SEF inventory

$deptCode = isset($_POST['dept']) ? (int)$_POST['dept'] : 0;
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
$status = 1;

// Base WHERE clause (only active records for given school/department)
$where = "WHERE sh.status = $status";
if ($deptCode > 0 && $deptPk > 0) {
    // Use IN() instead of OR to keep index usage more predictable.
    $where .= " AND sh.sch_id IN ($deptCode, $deptPk)";
} elseif ($deptCode > 0) {
    $where .= " AND sh.sch_id = $deptCode";
}

// Total records (without search)
// Note: do NOT join large tables here; we only need the history row count.
$countSql = "SELECT COUNT(*) AS cnt
             FROM sef_property_history AS sh
             $where";
$countRes = mysqli_query($conn, $countSql);
$totalData = 0;
if ($countRes && ($row = mysqli_fetch_assoc($countRes))) {
    $totalData = (int)$row['cnt'];
}

// Main select
// IMPORTANT: start from sef_property_history so the sch_id/status filter can use indexes.
// Starting from property_sef forces a full scan and is the main cause of "hanging" requests on large datasets.
$tableSql = "SELECT
                    s.item,
                    s.model,
                    s.description,
                    s.serial_number,
                    s.serial_number_2,
                    sh.property_number AS par_number,
                    s.date_aquired,
                    s.category,
                    e.emp_name
                FROM sef_property_history AS sh
                INNER JOIN property_sef AS s ON s.property_number = sh.property_number
                INNER JOIN employee AS e ON e.emp_id = sh.emp_id
                $where";

// Column-specific filters (DataTables column().search())
// Note: with serverSide=true, these MUST be applied here or the client-side dropdown filters will not work.
function dt_extract_exact_value($rawValue) {
    $rawValue = trim((string)$rawValue);
    // Our JS uses regex anchors: ^value$
    if (preg_match('/^\^(.*)\$$/s', $rawValue, $m)) {
        return $m[1];
    }
    return $rawValue;
}

function dt_strlen($value) {
    $value = (string)$value;
    return function_exists('mb_strlen') ? mb_strlen($value) : strlen($value);
}

function dt_is_code_like_query($plain) {
    $plain = trim((string)$plain);
    if ($plain === '') return false;
    // "Code-like" means likely property/serial numbers: no spaces and mostly [A-Z0-9-./].
    if (strpos($plain, ' ') !== false) return false;
    if (preg_match('/[0-9\-\.\/]/', $plain) !== 1) return false;
    return (preg_match('/^[A-Za-z0-9\-\.\/]+$/', $plain) === 1);
}

function dt_analyze_search_value($searchValue) {
    $plain = preg_replace('/\s+/', ' ', trim((string)$searchValue));
    $tokens = $plain === '' ? array() : preg_split('/\s+/', $plain);

    return array(
        'plain' => $plain,
        'token_count' => count($tokens),
        'has_digits' => (preg_match('/\d/', $plain) === 1),
        'is_mostly_numeric' => ($plain !== '' && preg_match('/^[0-9\-\.\/\s]+$/', $plain) === 1),
        'is_code_like' => dt_is_code_like_query($plain),
    );
}

function dt_make_count_cache_key($deptCode, $deptPk, $status, $itemFilter, $empFilter, $parIcsFilter, $searchValue) {
    return 'sef_dt_cnt_' . md5(json_encode(array(
        'dept' => (int)$deptCode,
        'deptPk' => (int)$deptPk,
        'status' => (int)$status,
        'item' => (string)$itemFilter,
        'emp' => (string)$empFilter,
        'parics' => (string)$parIcsFilter,
        'q' => (string)$searchValue,
    )));
}

function dt_can_use_property_sef_fulltext($conn) {
    static $requestCache = null;

    if ($requestCache !== null) {
        return $requestCache;
    }

    $sessionKey = 'sef_dt_property_sef_fulltext_v1';
    if (isset($_SESSION[$sessionKey])) {
        $requestCache = (bool)$_SESSION[$sessionKey];
        return $requestCache;
    }

    $requestCache = dt_has_fulltext_index_for_columns($conn, 'property_sef', array('item', 'model', 'description'));
    $_SESSION[$sessionKey] = $requestCache;

    return $requestCache;
}

function dt_build_fulltext_clause($conn, $plain) {
    $plain = preg_replace('/\s+/', ' ', trim((string)$plain));
    if ($plain === '') {
        return '';
    }

    $tokens = preg_split('/\s+/', $plain);
    $booleanParts = array();
    foreach ($tokens as $t) {
        $t = preg_replace('/[^\pL\pN_]+/u', '', $t);
        if ($t === '') continue;
        if (dt_strlen($t) < 3) continue;

        $tSafe = mysqli_real_escape_string($conn, $t);
        $booleanParts[] = '+' . $tSafe . '*';
    }

    if (empty($booleanParts)) {
        return '';
    }

    $q = implode(' ', $booleanParts);
    return "MATCH(s.item, s.model, s.description) AGAINST ('$q' IN BOOLEAN MODE)";
}

function dt_build_fast_search_clauses($conn, $analysis, $useFullText, $fullTextClause) {
    $plain = $analysis['plain'];
    $safePrefix = mysqli_real_escape_string($conn, $plain);
    $searchParts = array();

    if ($useFullText && $fullTextClause !== '') {
        $searchParts[] = $fullTextClause;
    }

    if ($analysis['is_code_like'] || $analysis['is_mostly_numeric']) {
        // Code-like terms: keep search to index-friendly prefix checks.
        $searchParts[] = "s.property_number LIKE '" . $safePrefix . "%'";
        $searchParts[] = "s.serial_number LIKE '" . $safePrefix . "%'";
        $searchParts[] = "s.serial_number_2 LIKE '" . $safePrefix . "%'";
        if (!$analysis['is_mostly_numeric']) {
            $searchParts[] = "e.emp_name LIKE '" . $safePrefix . "%'";
        }
        return $searchParts;
    }

    // Descriptive terms: keep fallbacks index-friendly so FULLTEXT remains effective.
    $searchParts[] = "e.emp_name LIKE '" . $safePrefix . "%'";
    $searchParts[] = "s.item LIKE '" . $safePrefix . "%'";
    $searchParts[] = "s.model LIKE '" . $safePrefix . "%'";

    if (!$useFullText && dt_strlen($plain) >= 4) {
        $searchParts[] = "s.description LIKE '" . $safePrefix . "%'";
    }

    // Mixed text with digits often targets model codes, so allow serial/property prefix fallback only.
    if ($analysis['has_digits']) {
        $searchParts[] = "s.property_number LIKE '" . $safePrefix . "%'";
        $searchParts[] = "s.serial_number LIKE '" . $safePrefix . "%'";
        $searchParts[] = "s.serial_number_2 LIKE '" . $safePrefix . "%'";
    }

    return $searchParts;
}

function dt_should_use_broad_search_fallback($analysis) {
    return (
        !empty($analysis['plain']) &&
        !$analysis['is_code_like'] &&
        !$analysis['is_mostly_numeric'] &&
        dt_strlen($analysis['plain']) >= 3
    );
}

function dt_build_broad_search_clauses($conn, $analysis) {
    $plain = trim((string)$analysis['plain']);
    if ($plain === '') {
        return array();
    }

    $safeLike = mysqli_real_escape_string($conn, $plain);
    $searchParts = array(
        "e.emp_name LIKE '%$safeLike%'",
        "s.item LIKE '%$safeLike%'",
        "s.model LIKE '%$safeLike%'",
        "s.description LIKE '%$safeLike%'",
    );

    if (!empty($analysis['has_digits'])) {
        $searchParts[] = "s.property_number LIKE '%$safeLike%'";
        $searchParts[] = "s.serial_number LIKE '%$safeLike%'";
        $searchParts[] = "s.serial_number_2 LIKE '%$safeLike%'";
    }

    return $searchParts;
}

// MySQL requires a FULLTEXT index that matches the MATCH() column list.
// If the index doesn't exist, using MATCH...AGAINST will cause the query to fail,
// which looks like "No matching records found" to the UI.
function dt_has_fulltext_index_for_columns($conn, $table, array $columns) {
    static $cache = array();

    $table = (string)$table;
    if (!preg_match('/^[A-Za-z0-9_]+$/', $table)) {
        return false;
    }
    $colsKey = implode(',', $columns);
    $cacheKey = $table . '|' . $colsKey;
    if (array_key_exists($cacheKey, $cache)) {
        return $cache[$cacheKey];
    }

    $want = array();
    foreach ($columns as $c) {
        $want[] = strtolower(trim((string)$c));
    }
    sort($want);

    $idxRes = mysqli_query($conn, "SHOW INDEX FROM `" . $table . "` WHERE Index_type = 'FULLTEXT'");
    if (!$idxRes) {
        $cache[$cacheKey] = false;
        return false;
    }

    $indexCols = array();
    while ($r = mysqli_fetch_assoc($idxRes)) {
        $keyName = (string)($r['Key_name'] ?? '');
        $colName = strtolower(trim((string)($r['Column_name'] ?? '')));
        if ($keyName === '' || $colName === '') continue;
        if (!isset($indexCols[$keyName])) $indexCols[$keyName] = array();
        $indexCols[$keyName][] = $colName;
    }

    foreach ($indexCols as $cols) {
        $cols = array_values(array_unique($cols));
        sort($cols);
        if (count($cols) === count($want) && $cols === $want) {
            $cache[$cacheKey] = true;
            return true;
        }
    }

    $cache[$cacheKey] = false;
    return false;
}

$colSearch = isset($_POST['columns']) && is_array($_POST['columns']) ? $_POST['columns'] : array();
$itemFilter = '';
$empFilter  = '';
$parIcsFilter = '';

// Prefer explicit filters sent by the page (more reliable than DataTables column search under serverSide)
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
    $tableSql .= " AND s.item = '$safeItem'";
}
if ($empFilter !== '') {
    $safeEmp = mysqli_real_escape_string($conn, $empFilter);
    $tableSql .= " AND e.emp_name = '$safeEmp'";
}
if ($parIcsFilter !== '') {
    $safeParIcs = mysqli_real_escape_string($conn, $parIcsFilter);
    $tableSql .= " AND REPLACE(UPPER(TRIM(s.category)), '.', '') = '$safeParIcs'";
}

$tableSqlBase = $tableSql;

// Global search
$searchValue = isset($_POST['search']['value']) ? trim($_POST['search']['value']) : '';
$searchAnalysis = null;
$searchPartsUsed = array();
$searchUsesEmployeeName = false;
if ($searchValue !== '') {
    $searchAnalysis = dt_analyze_search_value($searchValue);
    $useFullText = dt_can_use_property_sef_fulltext($conn);
    if (dt_strlen($searchAnalysis['plain']) < 3 || $searchAnalysis['is_mostly_numeric']) {
        $useFullText = false;
    }

    $fullTextClause = $useFullText ? dt_build_fulltext_clause($conn, $searchAnalysis['plain']) : '';
    if ($fullTextClause === '') {
        $useFullText = false;
    }

    $searchPartsUsed = dt_build_fast_search_clauses($conn, $searchAnalysis, $useFullText, $fullTextClause);
    $searchUsesEmployeeName = (!$searchAnalysis['is_code_like'] || !$searchAnalysis['is_mostly_numeric']);
    if ($searchAnalysis['is_mostly_numeric']) {
        $searchUsesEmployeeName = false;
    }

    $tableSql .= " AND (" . implode(' OR ', $searchPartsUsed) . ")";
}

// Order mapping: use the same column indexes as the DataTable
$columns = array(
    0 => null,                 // checkbox (not sortable)
    1 => null,                 // action buttons (not sortable)
    2 => 's.item',             // asset class / item
    3 => 's.model',            // particulars (model-description)
    4 => 's.serial_number',
    5 => 's.serial_number_2',
    6 => 'sh.property_number',
    7 => 'e.emp_name',
);

if (isset($_POST['order'][0]['column'])) {
    $colIdx = (int)$_POST['order'][0]['column'];
    $dir    = ($_POST['order'][0]['dir'] === 'desc') ? 'DESC' : 'ASC';
    if (isset($columns[$colIdx]) && $columns[$colIdx] !== null) {
        $orderSql = ' ORDER BY ' . $columns[$colIdx] . ' ' . $dir;
    } else {
        $orderSql = ' ORDER BY s.item ASC';
    }
} else {
    // Match the UI default sort (Property No.).
    $orderSql = ' ORDER BY sh.property_number ASC';
}

// Paging
$start  = isset($_POST['start']) ? (int)$_POST['start'] : 0;
$length = isset($_POST['length']) ? (int)$_POST['length'] : 10;
if ($length !== -1) {
    $limitSql = ' LIMIT ' . $start . ',' . $length;
} else {
    $limitSql = '';
}

$tableSql .= $orderSql;
$tableSql .= $limitSql;

$runQuery = mysqli_query($conn, $tableSql);
$data = array();

if ($runQuery) {
    while ($row = mysqli_fetch_assoc($runQuery)) {
        $data[] = array(
            'item'            => $row['item'],
            'model'           => $row['model'],
            'description'     => $row['description'],
            'serial_number'   => $row['serial_number'],
            'serial_number_2' => $row['serial_number_2'],
            'par_number'      => $row['par_number'],
            'date_aquired'    => $row['date_aquired'],
            'category'        => $row['category'],
            'emp_name'        => $row['emp_name'],
        );
    }
}

if ($hasSearch = ($searchValue !== '')) {
    if (empty($data) && $searchAnalysis !== null && dt_should_use_broad_search_fallback($searchAnalysis)) {
        $broadSearchParts = dt_build_broad_search_clauses($conn, $searchAnalysis);
        if (!empty($broadSearchParts)) {
            $searchPartsUsed = $broadSearchParts;
            $searchUsesEmployeeName = true;

            $fallbackSql = $tableSqlBase . " AND (" . implode(' OR ', $searchPartsUsed) . ")" . $orderSql . $limitSql;
            $fallbackQuery = mysqli_query($conn, $fallbackSql);
            if ($fallbackQuery) {
                $data = array();
                while ($row = mysqli_fetch_assoc($fallbackQuery)) {
                    $data[] = array(
                        'item'            => $row['item'],
                        'model'           => $row['model'],
                        'description'     => $row['description'],
                        'serial_number'   => $row['serial_number'],
                        'serial_number_2' => $row['serial_number_2'],
                        'par_number'      => $row['par_number'],
                        'date_aquired'    => $row['date_aquired'],
                        'category'        => $row['category'],
                        'emp_name'        => $row['emp_name'],
                    );
                }
            }
        }
    }
}

// If there was search, compute filtered count; otherwise same as total
$needsFiltersCount = ($itemFilter !== '' || $empFilter !== '' || $parIcsFilter !== '');
$hasSearch = ($searchValue !== '');

if (!$hasSearch && !$needsFiltersCount) {
    $totalFiltered = $totalData;
} else {
    // Build filtered count with only the joins we actually need.
    $countFilteredSql = "SELECT COUNT(*) AS cnt FROM sef_property_history AS sh";
    if ($itemFilter !== '' || $parIcsFilter !== '' || $hasSearch) {
        $countFilteredSql .= " INNER JOIN property_sef s ON sh.property_number = s.property_number";
    }
    if ($empFilter !== '' || ($hasSearch && $searchUsesEmployeeName)) {
        $countFilteredSql .= " INNER JOIN employee e ON sh.emp_id = e.emp_id";
    }
    $countFilteredSql .= " $where";

    if ($itemFilter !== '') {
        $safeItem2 = mysqli_real_escape_string($conn, $itemFilter);
        $countFilteredSql .= " AND s.item = '$safeItem2'";
    }
    if ($empFilter !== '') {
        $safeEmp2 = mysqli_real_escape_string($conn, $empFilter);
        $countFilteredSql .= " AND e.emp_name = '$safeEmp2'";
    }
    if ($parIcsFilter !== '') {
        $safeParIcs2 = mysqli_real_escape_string($conn, $parIcsFilter);
        $countFilteredSql .= " AND REPLACE(UPPER(TRIM(s.category)), '.', '') = '$safeParIcs2'";
    }

    if ($hasSearch && !empty($searchPartsUsed)) {
        $countFilteredSql .= " AND (" . implode(' OR ', $searchPartsUsed) . ")";
    }

    $countCacheTtlSeconds = 20;
    $countCacheKey = dt_make_count_cache_key($deptCode, $deptPk, $status, $itemFilter, $empFilter, $parIcsFilter, $searchValue);

    $totalFiltered = 0;
    $countCached = false;
    if (isset($_SESSION[$countCacheKey]) && is_array($_SESSION[$countCacheKey])) {
        $cachedAt = isset($_SESSION[$countCacheKey]['ts']) ? (int)$_SESSION[$countCacheKey]['ts'] : 0;
        if ($cachedAt > 0 && (time() - $cachedAt) <= $countCacheTtlSeconds) {
            $totalFiltered = (int)($_SESSION[$countCacheKey]['value'] ?? 0);
            $countCached = true;
        }
    }

        if (!$countCached) {
            $countFilteredRes = mysqli_query($conn, $countFilteredSql);
            if ($countFilteredRes && ($row2 = mysqli_fetch_assoc($countFilteredRes))) {
                $totalFiltered = (int)$row2['cnt'];
            }
            $_SESSION[$countCacheKey] = array(
                'ts' => time(),
                'value' => $totalFiltered,
            );
        }
}

$output = array(
    'draw'            => isset($_POST['draw']) ? (int)$_POST['draw'] : 0,
    'recordsTotal'    => $totalData,
    'recordsFiltered' => $totalFiltered,
    'data'            => $data,
);

header('Content-Type: application/json; charset=utf-8');
echo json_encode($output);
