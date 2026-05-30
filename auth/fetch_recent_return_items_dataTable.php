<?php
session_start();
require_once __DIR__ . '/../database/databaseConnection.php';
require_once __DIR__ . '/datatable_helpers.php';

header('Content-Type: application/json; charset=utf-8');

$draw = isset($_POST['draw']) ? (int)$_POST['draw'] : 0;

// Fixed-size feed: always return the latest 50 rows across
// - return_to_stock (serviceable / returned to inventory)
// - unserviceable_items (items marked unserviceable; date from latest history)
$limit = 50;

if (!isset($_SESSION['alogin'])) {
    http_response_code(401);
    dt_json_empty($draw, 'Unauthorized', 401);
}

$latestHistJoin = "
    LEFT JOIN (
        SELECT h1.*
        FROM unserviceable_items_history AS h1
        INNER JOIN (
            SELECT par_number, MAX(created_at) AS max_created_at
            FROM unserviceable_items_history
            GROUP BY par_number
        ) AS h2
            ON h1.par_number = h2.par_number
         AND h1.created_at = h2.max_created_at
    ) AS h
        ON h.par_number = u.par_number
";

$sql = "
    SELECT * FROM (
        SELECT
            'RETURN TO STOCK' AS return_type,
            COALESCE(fund, '') AS fund,
            COALESCE(category, '') AS category,
            COALESCE(item, '') AS item,
            COALESCE(model, '') AS model,
            COALESCE(serial_number, '') AS serial_number,
            COALESCE(serial_number_2, '') AS serial_number_2,
            COALESCE(par_number, '') AS par_number,
            COALESCE(created_at, '') AS event_at
        FROM return_to_stock

        UNION ALL

        SELECT
            'UNSERVICEABLE' AS return_type,
            COALESCE(u.fund, '') AS fund,
            COALESCE(u.category, '') AS category,
            COALESCE(u.item, '') AS item,
            COALESCE(u.model, '') AS model,
            COALESCE(u.serial_number, '') AS serial_number,
            COALESCE(u.serial_number_2, '') AS serial_number_2,
            COALESCE(u.par_number, '') AS par_number,
            COALESCE(h.created_at, '') AS event_at
        FROM unserviceable_items AS u
        {$latestHistJoin}
    ) AS x
    ORDER BY x.event_at DESC
    LIMIT ?
";

$stmt = $conn->prepare($sql);
$data = array();
if ($stmt) {
    $stmt->bind_param('i', $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $createdAtRaw = (string)($row['event_at'] ?? '');
            $createdAt = '';
            if ($createdAtRaw !== '') {
                $ts = strtotime($createdAtRaw);
                $createdAt = $ts ? date('M d, Y h:i A', $ts) : $createdAtRaw;
            }

            $data[] = array(
                'created_at' => $createdAt,
                'return_type' => $row['return_type'] ?? '',
                'fund' => $row['fund'] ?? '',
                'category' => $row['category'] ?? '',
                'item' => $row['item'] ?? '',
                'model' => $row['model'] ?? '',
                'serial_number' => $row['serial_number'] ?? '',
                'serial_number_2' => $row['serial_number_2'] ?? '',
                'par_number' => $row['par_number'] ?? '',
            );
        }
    }
    $stmt->close();
}

$returnedCount = count($data);

echo json_encode(array(
    'draw' => $draw,
    'recordsTotal' => $returnedCount,
    'recordsFiltered' => $returnedCount,
    'data' => $data,
));
