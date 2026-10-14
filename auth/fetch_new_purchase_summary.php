<?php
require_once __DIR__ . '/../config/session_bootstrap.php';
gso_start_secure_session();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

$draw = max(0, (int)($_POST['draw'] ?? 0));
if (empty($_SESSION['alogin'])) {
    http_response_code(401);
    echo json_encode(['draw' => $draw, 'recordsTotal' => 0, 'recordsFiltered' => 0, 'data' => [], 'error' => 'Not logged in.']);
    exit;
}
// Release the session so this read-only query cannot queue form previews and edits.
session_write_close();
require_once __DIR__ . '/../database/databaseConnection.php';
require_once __DIR__ . '/new_purchase_summary.php';
try {
    echo json_encode(gso_np_summary_response($conn, $_POST));
} catch (Throwable $error) {
    error_log('Purchase summary: ' . $error->getMessage());
    http_response_code(500);
    echo json_encode(['draw' => $draw, 'recordsTotal' => 0, 'recordsFiltered' => 0, 'data' => [], 'error' => 'Unable to load purchase summary.']);
}
