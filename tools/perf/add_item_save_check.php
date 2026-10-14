<?php
// CLI integration check. Creates and removes its own isolated fixture database.
// php tools/perf/add_item_save_check.php [new|existing|donation|trust|skip|failure] [units] [pdf-dir] [baseline-auth]
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
ini_set('session.save_path', sys_get_temp_dir());
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['REQUEST_METHOD'] = 'POST';
require_once __DIR__ . '/../../config/session_bootstrap.php';
gso_start_secure_session();
require_once __DIR__ . '/../../database/databaseConnection.php';
$sourceDb = $conn->query('SELECT DATABASE() AS name')->fetch_assoc()['name'];
$testDb = 'gso_save_check_' . bin2hex(random_bytes(5));
$conn->query("CREATE DATABASE `$testDb`");
register_shutdown_function(function () use ($conn, $testDb) {
    $conn->query("DROP DATABASE IF EXISTS `$testDb`");
});
$tables = ['new_purchase', 'new_purchase_history', 'new_bundle_purchase', 'par_gen_fund', 'property_sef',
    'general_fund_property_history', 'sef_property_history', 'trust_fund', 'trust_fund_history',
    'donation', 'donation_history', 'department', 'employee', 'bundle_gen_fund', 'bundle_sef', 'items_user_history'];
foreach ($tables as $table) {
    $conn->query("CREATE TABLE `$testDb`.`$table` LIKE `$sourceDb`.`$table`");
}
// Copy one valid accountability pair; all item data below is synthetic.
$pair = $conn->query('SELECT e.emp_id, e.department_code FROM employee e JOIN department d ON d.department_code=e.department_code LIMIT 1')->fetch_assoc();
if (!$pair) { throw new RuntimeException('No employee/department fixture available.'); }
$empId = (int)$pair['emp_id'];
$deptCode = $conn->real_escape_string($pair['department_code']);
$conn->query("INSERT INTO `$testDb`.employee SELECT * FROM `$sourceDb`.employee WHERE emp_id=$empId");
$conn->query("INSERT INTO `$testDb`.department SELECT * FROM `$sourceDb`.department WHERE department_code='$deptCode'");
$conn->select_db($testDb);

$mode = $argv[1] ?? 'new';
$units = max(1, min(10000, (int)($argv[2] ?? 205)));
$isExisting = in_array($mode, ['existing', 'existing_sef'], true);
$fund = $mode === 'donation' ? 'DONATION' : ($mode === 'trust' ? 'TRUST FUND' : (strpos($mode, 'sef') !== false ? 'SPECIAL EDUCATION FUND' : 'GENERAL FUND'));
$token = bin2hex(random_bytes(16));
$_SESSION['alogin'] = 'fixture';
$_SESSION['form_tokens']['add_item'][$token] = time();
$_POST = [
    'save_item' => 1, 'submission_token' => $token, 'condition' => $isExisting ? 'EXISTING' : 'NEW',
    'fund' => $fund, 'year' => '2026', 'dept' => $pair['department_code'], 'parEmp' => (string)$empId,
    'quantity' => 2, 'po' => '2026-1234',
    'category' => [1 => 'PAR', 2 => 'ICS'], 'unit' => [1 => 'UNIT', 2 => 'PC'],
    'asset' => [1 => 'OFFICE EQUIPMENT', 2 => 'OFFICE EQUIPMENT'],
    'brand' => [1 => 'TEST MODEL A', 2 => 'TEST MODEL B'],
    'description' => [1 => 'SYNTHETIC PERFORMANCE FIXTURE', 2 => 'SYNTHETIC ICS FIXTURE'],
    'unitvalue' => [1 => '50000.25', 2 => '1234.50'], 'account_code' => [1 => '1-07-05-020', 2 => '1-07-05-020'],
    'item_quantity' => [1 => $units, 2 => 3],
    'serial' => [1 => [1 => 'PRIMARY-1', $units => 'PRIMARY-LAST'], 2 => [1 => 'ICS-PRIMARY']],
    'serial2' => [1 => [1 => 'SECONDARY-1'], 2 => [3 => 'ICS-LAST']],
];
$_POST['serial'][1] = array_fill(1, $units, '');
$_POST['serial'][1][1] = 'PRIMARY-1';
$_POST['serial'][1][$units] = 'PRIMARY-LAST';
$_POST['serial'][2] = [1 => 'ICS-PRIMARY', 2 => '', 3 => ''];
$_POST['serial2'][1] = array_fill(1, $units, '');
$_POST['serial2'][1][1] = 'SECONDARY-1';
$_POST['serial2'][2] = [1 => '', 2 => '', 3 => 'ICS-LAST'];
if ($mode === 'skip' || $mode === 'trust') {
    $_POST['item_skip_property_number'] = [1 => 1, 2 => 1];
}
if ($mode === 'failure') { $_POST['description'][2] = ''; }
$start = microtime(true);
ob_start();
require $argv[4] ?? __DIR__ . '/../../auth/auth.php';
$response = json_decode(ob_get_clean(), true);
$elapsed = (microtime(true) - $start) * 1000;
if (!is_array($response)) { throw new RuntimeException('Invalid save response.'); }
$mainTable = $fund === 'DONATION' ? 'donation' : ($isExisting ? ($mode === 'existing_sef' ? 'property_sef' : 'par_gen_fund') : 'new_purchase');
$historyTable = $fund === 'DONATION' ? 'donation_history' : ($isExisting ? ($mode === 'existing_sef' ? 'sef_property_history' : 'general_fund_property_history') : 'new_purchase_history');
$itemCount = (int)$conn->query("SELECT COUNT(*) n FROM $mainTable")->fetch_assoc()['n'];
$historyCount = (int)$conn->query("SELECT COUNT(*) n FROM $historyTable")->fetch_assoc()['n'];
$expectFailure = $mode === 'failure';
if ($expectFailure) {
    if ($response['status'] === 200 || $itemCount !== 0 || $historyCount !== 0) {
        throw new RuntimeException('Failed save did not roll back all rows.');
    }
    if (empty($argv[4])) {
        // The save restores its token at shutdown, after rollback and before session persistence.
        register_shutdown_function(function () use ($token) {
            if (!isset($_SESSION['form_tokens']['add_item'][$token])) {
                throw new RuntimeException('Failed save did not restore its retry token.');
            }
        });
    }
} else {
    if ($response['status'] !== 200 || $itemCount !== $units + 3 || $historyCount !== $itemCount) {
        throw new RuntimeException('Save counts incorrect: ' . json_encode($response));
    }
    $propertyColumn = $isExisting && $mode !== 'existing_sef' ? 'par_number' : 'property_number';
    $duplicates = $conn->query("SELECT $propertyColumn FROM $mainTable WHERE $propertyColumn IS NOT NULL GROUP BY $propertyColumn HAVING COUNT(*)>1");
    if ($duplicates->num_rows) { throw new RuntimeException('Duplicate property numbers.'); }
    $row = $conn->query("SELECT serial_number, serial_number_2, unit_value FROM $mainTable WHERE model='TEST MODEL A' ORDER BY " . ($isExisting ? ($mode === 'existing_sef' ? 'sef_id' : 'pargf_id') : 'id') . ($fund === 'DONATION' ? ' DESC' : ' ASC') . ' LIMIT 1')->fetch_assoc();
    // The existing GF schema uses FLOAT and rounds this value to 50000.2.
    $amountTolerance = $isExisting ? 0.051 : 0.0001;
    if ($row['serial_number'] !== 'PRIMARY-1' || $row['serial_number_2'] !== 'SECONDARY-1' || abs((float)$row['unit_value'] - 50000.25) > $amountTolerance) {
        throw new RuntimeException('Serials or unit value changed: ' . json_encode($row));
    }
    $join = $isExisting ? ($mode === 'existing_sef' ? 'p.property_number=h.property_number' : 'p.par_number=h.par_number') : ($fund === 'DONATION' ? 'p.id=h.id' : "(p.property_number=h.par_number OR CONCAT('NPID:',p.id)=h.par_number)");
    $linked = (int)$conn->query("SELECT COUNT(*) n FROM $mainTable p JOIN $historyTable h ON $join")->fetch_assoc()['n'];
    if ($linked !== $itemCount) { throw new RuntimeException('History links incorrect.'); }
    if (count($response['data']['par_refs']) !== 1 || count($response['data']['ics_refs']) !== 1) {
        throw new RuntimeException('Print accountability groups changed.');
    }
    // Occupied numbers and reserved numbers must be excluded even when a lookup is cached.
    $lookupCache = [];
    $first = gso_allocate_property_number_block($conn, 'GENERAL FUND', '2099-05-9999-04', 3, [], 20000, $lookupCache);
    $second = gso_allocate_property_number_block($conn, 'GENERAL FUND', '2099-05-9999-04', 3, $first['numbers'], 20000, $lookupCache);
    if (empty($first['ok']) || empty($second['ok']) || array_intersect($first['numbers'], $second['numbers'])
        || $first['numbers'][1] !== '2099-05-10000-04') {
        throw new RuntimeException('Cached allocation or sequence rollover changed.');
    }
    if (empty($argv[4]) && (empty($response['submission_token']) || isset($_SESSION['form_tokens']['add_item'][$token]))) {
        throw new RuntimeException('Successful save did not rotate its single-use token.');
    }
}
printf("%s: %.1f ms; %d items / %d histories; assertions passed\n", $mode, $elapsed, $itemCount, $historyCount);

// Optional PDF regression output. Data remains in the fixture database on this connection.
if (!$expectFailure && !empty($argv[3])) {
    $pdfDir = rtrim($argv[3], '/');
    if (!is_dir($pdfDir)) { mkdir($pdfDir, 0700, true); }
    foreach (['par' => 'printpar.php', 'ics' => 'inventory_custodian_slip.php'] as $category => $file) {
        $_GET = ['refs' => implode(',', $response['data'][$category . '_refs'])];
        $start = microtime(true);
        ob_start();
        $previousDirectory = getcwd();
        chdir(__DIR__ . '/../../admin');
        // Ignore legacy library deprecations when comparing the original PDF code.
        set_error_handler(function ($severity) { return $severity === E_DEPRECATED || $severity === E_USER_DEPRECATED; });
        require !empty($argv[4]) ? dirname($argv[4]) . '/gso-baseline-' . $file : __DIR__ . '/../../admin/' . $file;
        restore_error_handler();
        chdir($previousDirectory);
        $pdf = ob_get_clean();
        if (strncmp($pdf, '%PDF-', 5) !== 0) { throw new RuntimeException('Invalid PDF output.'); }
        file_put_contents("$pdfDir/$mode-$category.pdf", $pdf);
        printf("%s PDF: %.1f ms (%d bytes)\n", $category, (microtime(true)-$start)*1000, strlen($pdf));
    }
}
