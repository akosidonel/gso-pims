<?php
/**
 * General Fund inventory query profiler
 *
 * Usage (PowerShell):
 *   C:\xampp\php\php.exe tools\perf\general_fund_inventory_profile.php --dept=1 --start=0 --length=10
 */

require_once __DIR__ . '/../../database/databaseConnection.php';

function cli_arg(string $name, $default = null) {
    global $argv;
    $prefix = '--' . $name . '=';
    foreach ($argv as $arg) {
        if (strpos($arg, $prefix) === 0) {
            return substr($arg, strlen($prefix));
        }
    }
    return $default;
}

function time_query(mysqli $conn, string $label, string $sql) {
    $start = microtime(true);
    $res = mysqli_query($conn, $sql);
    $elapsedMs = (microtime(true) - $start) * 1000.0;

    $rows = null;
    if ($res instanceof mysqli_result) {
        $rows = mysqli_num_rows($res);
        mysqli_free_result($res);
    }

    printf("\n[%s] %.2f ms%s\n", $label, $elapsedMs, ($rows !== null ? " (rows: $rows)" : ''));
    echo $sql . "\n";
}

function explain_query(mysqli $conn, string $label, string $sql) {
    $res = mysqli_query($conn, 'EXPLAIN ' . $sql);
    if (!$res) {
        echo "\n[EXPLAIN $label] FAILED: " . mysqli_error($conn) . "\n";
        return;
    }

    echo "\n[EXPLAIN $label]\n";
    while ($row = mysqli_fetch_assoc($res)) {
        printf(
            "- table=%s type=%s key=%s rows=%s extra=%s\n",
            $row['table'] ?? '',
            $row['type'] ?? '',
            $row['key'] ?? '',
            $row['rows'] ?? '',
            $row['Extra'] ?? ''
        );
    }
    mysqli_free_result($res);
}

function show_indexes(mysqli $conn, string $table) {
    $safeTable = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
    $res = mysqli_query($conn, "SHOW INDEX FROM `{$safeTable}`");
    if (!$res) {
        echo "\n[INDEXES {$safeTable}] FAILED: " . mysqli_error($conn) . "\n";
        return;
    }

    echo "\n[INDEXES {$safeTable}]\n";
    $seen = [];
    while ($row = mysqli_fetch_assoc($res)) {
        $keyName = $row['Key_name'] ?? '';
        $seq = (int)($row['Seq_in_index'] ?? 0);
        $col = $row['Column_name'] ?? '';
        $nonUnique = (int)($row['Non_unique'] ?? 1);
        $card = $row['Cardinality'] ?? '';

        if (!isset($seen[$keyName])) {
            $seen[$keyName] = ['nonUnique' => $nonUnique, 'card' => $card, 'cols' => []];
        }
        $seen[$keyName]['cols'][$seq] = $col;
    }
    mysqli_free_result($res);

    foreach ($seen as $name => $info) {
        ksort($info['cols']);
        $cols = implode(', ', $info['cols']);
        printf("- %s (%s) [%s] cols: %s\n", $name, ($info['nonUnique'] ? 'NONUNIQUE' : 'UNIQUE'), $info['card'], $cols);
    }
}

$deptId = (int)cli_arg('dept', 0);
$start = (int)cli_arg('start', 0);
$length = (int)cli_arg('length', 10);
$status = 1;

if ($deptId <= 0) {
    $sql = "SELECT dept_id, COUNT(*) AS cnt FROM general_fund_property_history WHERE status={$status} GROUP BY dept_id ORDER BY cnt DESC LIMIT 1";
    $res = mysqli_query($conn, $sql);
    if ($res && ($row = mysqli_fetch_assoc($res))) {
        $deptId = (int)$row['dept_id'];
    }
    if ($res instanceof mysqli_result) {
        mysqli_free_result($res);
    }
}

if ($deptId <= 0) {
    fwrite(STDERR, "Could not detect a dept id. Pass --dept=...\n");
    exit(1);
}

echo "Profiling General Fund inventory for dept={$deptId}, status={$status}, start={$start}, length={$length}\n";

show_indexes($conn, 'general_fund_property_history');
show_indexes($conn, 'par_gen_fund');
show_indexes($conn, 'employee');

$where = "WHERE g.dept_id = {$deptId} AND g.status = {$status}";

$countTotalSql = "SELECT COUNT(*) AS cnt FROM general_fund_property_history AS g {$where}";
$mainSql = "SELECT p.item, p.model, p.description, p.serial_number, p.serial_number_2, p.par_number, p.category, e.emp_name
            FROM par_gen_fund p
            STRAIGHT_JOIN general_fund_property_history AS g ON g.par_number = p.par_number
            STRAIGHT_JOIN employee e ON g.emp_id = e.emp_id
            {$where}
            ORDER BY p.item ASC
            LIMIT {$start},{$length}";

$distinctItemsSql = "SELECT DISTINCT p.item AS v
                     FROM general_fund_property_history AS g
                     INNER JOIN par_gen_fund p ON g.par_number = p.par_number
                     {$where}
                     ORDER BY p.item ASC";

$distinctUsersSql = "SELECT DISTINCT e.emp_name AS v
                     FROM general_fund_property_history AS g
                     INNER JOIN employee e ON g.emp_id = e.emp_id
                     {$where}
                     ORDER BY e.emp_name ASC";

explain_query($conn, 'countTotal', $countTotalSql);
explain_query($conn, 'main', $mainSql);
explain_query($conn, 'distinctItems', $distinctItemsSql);
explain_query($conn, 'distinctUsers', $distinctUsersSql);

time_query($conn, 'countTotal', $countTotalSql);
time_query($conn, 'main', $mainSql);
time_query($conn, 'distinctItems', $distinctItemsSql);
time_query($conn, 'distinctUsers', $distinctUsersSql);
