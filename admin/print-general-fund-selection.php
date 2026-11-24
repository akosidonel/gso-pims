<?php
require_once __DIR__ . '/../config/session_bootstrap.php';
gso_start_secure_session();

if (!isset($_SESSION['alogin'])) {
    header('Location: ../index.php');
    exit;
}

function inventorySelectionPrintError(string $message): void {
    http_response_code(422);
    header('Content-Type: text/plain; charset=UTF-8');
    exit($message);
}

$selection = $_POST['property_numbers'] ?? '';
$propertyNumbers = is_string($selection) ? json_decode($selection) : null;
if (!is_array($propertyNumbers) || !$propertyNumbers) {
    inventorySelectionPrintError('Select at least one inventory item to print.');
}
foreach ($propertyNumbers as &$propertyNumber) {
    if (!is_string($propertyNumber) || trim($propertyNumber) === '') {
        inventorySelectionPrintError('The selection contains an invalid property number.');
    }
    $propertyNumber = trim($propertyNumber);
}
unset($propertyNumber);
$propertyNumbers = array_unique($propertyNumbers);

require_once __DIR__ . '/../auth/auth.php';
require_once __DIR__ . '/../include/general_fund_inventory_print.php';

$rows = gso_fetch_inventory_print_rows($conn, $propertyNumbers);
$documents = ['PAR' => [], 'ICS' => []];
foreach ($propertyNumbers as $propertyNumber) {
    $row = $rows[$propertyNumber] ?? null;
    if (!$row || !isset($documents[$row['category']])) {
        inventorySelectionPrintError('No printable current assignment for property ' . $propertyNumber . '. Refresh the inventory and check your selection.');
    }
    $previousDepartment = gso_resolve_history_department($conn, $row['previous_dept'] ?? '');
    $row['previous_dept'] = $previousDepartment['department_name'] ?? ($row['previous_dept'] ?? '');
    $documents[$row['category']][] = $row;
}

if (!$documents['ICS'] || !$documents['PAR']) {
    $inventorySelectionRows = $documents['PAR'] ?: $documents['ICS'];
    require ($documents['PAR'] ? __DIR__ . '/printpt.php' : __DIR__ . '/inventory_custodian_slip.php');
    exit;
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Print PAR/ICS</title>
  <link rel="stylesheet" href="../assets/dist/css/adminlte.min.css">
</head>
<body>
  <main class="container py-4">
    <h1 class="h3">Print selected inventory items</h1>
    <p>Your selection includes both PAR and ICS items. Open each document to print it.</p>
    <?php foreach ($documents as $category => $documentRows): ?>
      <form method="post" target="_blank" class="d-inline-block mr-2 mb-2">
        <input type="hidden" name="property_numbers" value="<?= htmlspecialchars(json_encode(array_column($documentRows, 'par_number')), ENT_QUOTES, 'UTF-8') ?>">
        <button type="submit" class="btn btn-secondary">Open <?= $category ?> (<?= count($documentRows) ?>)</button>
      </form>
    <?php endforeach; ?>
  </main>
</body>
</html>
