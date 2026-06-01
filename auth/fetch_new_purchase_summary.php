<?php
require_once __DIR__ . '/../config/session_bootstrap.php';
gso_start_secure_session();
require_once __DIR__ . '/../database/databaseConnection.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

if (empty($_SESSION['alogin'])) {
    http_response_code(401);
    echo json_encode(array('data' => array(), 'message' => 'Not logged in.'));
    exit;
}

$sql = "
    SELECT
        MIN(np.id) AS row_id,
        NULLIF(TRIM(np.purchase_order), '') AS purchase_order,
        MAX(np.fund) AS fund,
        MAX(np.purchase_request) AS purchase_request,
        MAX(np.obr_number) AS obr_number,
        MAX(np.supplier) AS supplier,
        MAX(np.par_ics_number) AS par_ics_number,
        MAX(COALESCE(d_by_id.department_code, d_by_code.department_code, hp.dept_id, hn.dept_id)) AS department_code,
        MAX(COALESCE(d_by_id.department_name, d_by_code.department_name)) AS department_name,
        SUM(COALESCE(np.unit_value, 0)) AS total_amount,
        MAX(np.created_at) AS created_at
    FROM new_purchase AS np
    LEFT JOIN (
        SELECT par_number, MAX(dept_id) AS dept_id
        FROM new_purchase_history
        WHERE status = 1
        GROUP BY par_number
    ) AS hp ON hp.par_number = np.property_number
    LEFT JOIN (
        SELECT par_number, MAX(dept_id) AS dept_id
        FROM new_purchase_history
        WHERE status = 1
        GROUP BY par_number
    ) AS hn ON hn.par_number = CONCAT('NPID:', np.id)
    LEFT JOIN department AS d_by_code ON d_by_code.department_code = COALESCE(hp.dept_id, hn.dept_id)
    LEFT JOIN department AS d_by_id ON d_by_id.dept_id = COALESCE(hp.dept_id, hn.dept_id)
    GROUP BY
        CASE
            WHEN TRIM(COALESCE(np.purchase_order, '')) = '' THEN CONCAT('ID:', np.id)
            ELSE CONCAT('PO:', UPPER(TRIM(np.purchase_order)))
        END
    ORDER BY MAX(np.created_at) DESC
";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    http_response_code(500);
    echo json_encode(array('data' => array(), 'message' => 'Unable to prepare summary query.'));
    exit;
}

if (!$stmt->execute()) {
    $stmt->close();
    http_response_code(500);
    echo json_encode(array('data' => array(), 'message' => 'Unable to load summary rows.'));
    exit;
}

$result = $stmt->get_result();
$rows = array();
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $rows[] = array(
            'row_id' => (int)($row['row_id'] ?? 0),
            'purchase_order' => (string)($row['purchase_order'] ?? ''),
            'fund' => (string)($row['fund'] ?? ''),
            'purchase_request' => (string)($row['purchase_request'] ?? ''),
            'obr_number' => (string)($row['obr_number'] ?? ''),
            'supplier' => (string)($row['supplier'] ?? ''),
            'par_ics_number' => (string)($row['par_ics_number'] ?? ''),
            'department_code' => (string)($row['department_code'] ?? ''),
            'department_name' => (string)($row['department_name'] ?? ''),
            'total_amount' => (float)($row['total_amount'] ?? 0),
            'created_at' => (string)($row['created_at'] ?? ''),
        );
    }
}

$stmt->close();

echo json_encode(array('data' => $rows));
