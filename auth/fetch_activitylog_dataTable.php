<?php
session_start();
require_once __DIR__ . '/../database/databaseConnection.php';
require_once __DIR__ . '/datatable_helpers.php';

header('Content-Type: application/json; charset=utf-8');

$draw = isset($_POST['draw']) ? (int)$_POST['draw'] : 0;
$start = isset($_POST['start']) ? max(0, (int)$_POST['start']) : 0;
$length = isset($_POST['length']) ? (int)$_POST['length'] : 10;
$length = ($length === -1) ? -1 : max(0, $length);

$searchValue = trim((string)($_POST['search']['value'] ?? ''));
$orderColumn = isset($_POST['order'][0]['column']) ? (int)$_POST['order'][0]['column'] : 0;
$orderDir = strtolower((string)($_POST['order'][0]['dir'] ?? 'desc')) === 'asc' ? 'ASC' : 'DESC';

$orderMap = array(
    0 => 'activity_log.time',
    1 => 'administrator.first_name',
    2 => 'administrator.role',
    3 => 'activity_log.ip_address',
    4 => 'activity_log.activity',
);
$orderBy = isset($orderMap[$orderColumn]) ? $orderMap[$orderColumn] : 'activity_log.time';

$fromSql = "FROM activity_log
            INNER JOIN administrator ON activity_log.admin_id = administrator.admin_id";

$whereSql = '';
$types = '';
$params = array();
if ($searchValue !== '') {
    $whereSql = " WHERE (
        administrator.first_name LIKE ? OR
        administrator.last_name LIKE ? OR
        activity_log.ip_address LIKE ? OR
        activity_log.activity LIKE ? OR
        activity_log.time LIKE ?
    )";
    $like = '%' . $searchValue . '%';
    $types = 'sssss';
    $params = array($like, $like, $like, $like, $like);
}

$countRow = dt_execute_one(
    $conn,
    "SELECT COUNT(*) AS cnt {$fromSql}",
    '',
    array()
);
$recordsTotal = (int)($countRow['cnt'] ?? 0);

$filteredRow = dt_execute_one(
    $conn,
    "SELECT COUNT(*) AS cnt {$fromSql}{$whereSql}",
    $types,
    $params
);
$recordsFiltered = (int)($filteredRow['cnt'] ?? 0);

$dataSql = "SELECT
                administrator.first_name,
                administrator.last_name,
                administrator.role,
                activity_log.ip_address,
                activity_log.activity,
                activity_log.time
            {$fromSql}
            {$whereSql}
            ORDER BY {$orderBy} {$orderDir}";

$dataTypes = $types;
$dataParams = $params;
if ($length !== -1) {
    $dataSql .= ' LIMIT ?, ?';
    $dataTypes .= 'ii';
    $dataParams[] = $start;
    $dataParams[] = $length;
}

list($stmt, $rows) = dt_execute_all($conn, $dataSql, $dataTypes, $dataParams);
dt_close_stmt($stmt);

$data = array();
foreach ($rows as $row) {
    $activityText = (string)($row['activity'] ?? '');
    if (strpos($activityText, 'PROPERTY CLEARANCE REPRINT|CTRL=') === 0) {
        $ctrl = '';
        $reason = '';
        $ctrlPos = strpos($activityText, '|CTRL=');
        if ($ctrlPos !== false) {
            $ctrl = substr($activityText, $ctrlPos + 6);
            $pipePos = strpos($ctrl, '|');
            if ($pipePos !== false) {
                $ctrl = substr($ctrl, 0, $pipePos);
            }
        }
        $reasonPos = strpos($activityText, '|REASON=');
        if ($reasonPos !== false) {
            $reason = substr($activityText, $reasonPos + 8);
            $pipePos2 = strpos($reason, '|');
            if ($pipePos2 !== false) {
                $reason = substr($reason, 0, $pipePos2);
            }
        }
        $ctrl = trim($ctrl);
        $reason = trim($reason);
        $activityText = 'Re-printed Property Clearance (CTRL: ' . $ctrl . ')' . ($reason !== '' ? ' - Reason: ' . $reason : '');
    }

    $data[] = array(
        date('F j, Y, g:i a', strtotime((string)($row['time'] ?? 'now'))),
        trim((string)($row['first_name'] ?? '') . ' ' . (string)($row['last_name'] ?? '')),
        (string)($row['role'] ?? ''),
        (string)($row['ip_address'] ?? ''),
        $activityText,
    );
}

echo json_encode(array(
    'draw' => $draw,
    'recordsTotal' => $recordsTotal,
    'recordsFiltered' => $recordsFiltered,
    'data' => $data,
));
