<?php
/**
 * SEF inventory query profiler
 *
 * Usage (PowerShell):
 *   C:\xampp\php\php.exe tools\perf\sef_inventory_profile.php --dept=1 --start=0 --length=10
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
    $explainSql = 'EXPLAIN ' . $sql;
    $res = mysqli_query($conn, $explainSql);
    if (!$res) {
        echo "\n[EXPLAIN $label] FAILED: " . mysqli_error($conn) . "\n";
        return;
    }

    echo "\n[EXPLAIN $label]\n";
    while ($row = mysqli_fetch_assoc($res)) {
        // Keep output compact
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
    $sql = "SHOW INDEX FROM `{$safeTable}`";
    $res = mysqli_query($conn, $sql);
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
            $seen[$keyName] = [
                'nonUnique' => $nonUnique,
                'card' => $card,
                'cols' => [],
            ];
        }
        $seen[$keyName]['cols'][$seq] = $col;
    }
    mysqli_free_result($res);

    foreach ($seen as $name => $info) {
        ksort($info['cols']);
        $cols = implode(', ', $info['cols']);
        printf("- %s (%s) [%s] cols: %s\n", $name, ($info['nonUnique'] ? 'NONUNIQUE' : 'UNIQUE'), $info['card'], $cols);
    }

    return $seen;
}

$deptId = (int)cli_arg('dept', 0);
$start = (int)cli_arg('start', 0);
$length = (int)cli_arg('length', 10);
$status = 1;

if ($deptId <= 0) {
    // Pick the busiest dept as default
    $sql = "SELECT sch_id, COUNT(*) AS cnt FROM sef_property_history WHERE status={$status} GROUP BY sch_id ORDER BY cnt DESC LIMIT 1";
    $res = mysqli_query($conn, $sql);
    if ($res && ($row = mysqli_fetch_assoc($res))) {
        $deptId = (int)$row['sch_id'];
    }
    if ($res instanceof mysqli_result) {
        mysqli_free_result($res);
    }
}

if ($deptId <= 0) {
    fwrite(STDERR, "Could not detect a dept id. Pass --dept=...\n");
    exit(1);
}

echo "Profiling SEF inventory for dept={$deptId}, status={$status}, start={$start}, length={$length}\n";

$idxSh = show_indexes($conn, 'sef_property_history');
$idxS  = show_indexes($conn, 'property_sef');
show_indexes($conn, 'employee');

// Quick performance hints (non-blocking): recommend common indexes if missing.
// This keeps the profiler useful across deployments without forcing schema changes.
function has_index_with_cols(array $idxInfo, array $needleCols): bool {
    $needle = array_map('strtolower', $needleCols);
    foreach ($idxInfo as $info) {
        if (!isset($info['cols']) || !is_array($info['cols'])) continue;
        $cols = array_map('strtolower', array_values($info['cols']));
        if ($cols === $needle) return true;
    }
    return false;
}

echo "\n[PERF HINTS]\n";
if (!has_index_with_cols($idxSh, ['sch_id', 'status', 'property_number']) && !has_index_with_cols($idxSh, ['sch_id', 'status', 'property_number', 'emp_id'])) {
    echo "- Missing composite index on sef_property_history(sch_id, status, property_number[, emp_id]).\n";
    echo "  Suggested: ALTER TABLE sef_property_history ADD INDEX idx_sef_history_sch_status_prop_emp (sch_id, status, property_number, emp_id);\n";
}
if (!has_index_with_cols($idxS, ['item', 'property_number']) && !has_index_with_cols($idxS, ['item'])) {
    echo "- Consider indexing property_sef(item[, property_number]) to speed ORDER BY item.\n";
    echo "  Suggested: ALTER TABLE property_sef ADD INDEX idx_property_sef_item (item);\n";
}

$where = "WHERE sh.sch_id = {$deptId} AND sh.status = {$status}";

$countTotalSql = "SELECT COUNT(*) AS cnt FROM sef_property_history AS sh {$where}";

// Mirror the production endpoint: start from sef_property_history so sch_id/status can use indexes.
// The live page defaults to property_number ordering unless the user changes sort.
$mainSql = "SELECT s.item, s.model, s.description, s.serial_number, s.serial_number_2, sh.property_number AS par_number, s.category, e.emp_name
            FROM sef_property_history AS sh
            INNER JOIN property_sef AS s ON s.property_number = sh.property_number
            INNER JOIN employee AS e ON e.emp_id = sh.emp_id
            {$where}
            ORDER BY sh.property_number ASC
            LIMIT {$start},{$length}";

// Mirror the filters endpoint: no FORCE INDEX (index names vary by deployment).
$distinctItemsSql = "SELECT DISTINCT s.item AS v
                      FROM sef_property_history AS sh
                      INNER JOIN property_sef AS s ON s.property_number = sh.property_number
                      {$where}
                      ORDER BY s.item ASC";

$distinctUsersSql = "SELECT DISTINCT e.emp_name AS v
                     FROM sef_property_history AS sh
                     INNER JOIN employee e ON sh.emp_id = e.emp_id
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
