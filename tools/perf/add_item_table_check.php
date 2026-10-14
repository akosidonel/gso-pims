<?php
// Read-only checks against the local database. No items are inserted or changed.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../../database/databaseConnection.php';
require_once __DIR__ . '/../../auth/new_purchase_summary.php';
function check_table(bool $condition, string $message): void {
    if (!$condition) { throw new RuntimeException($message); }
}
$first = gso_np_summary_response($conn, ['draw' => 7, 'length' => 10]);
$second = gso_np_summary_response($conn, ['draw' => 8, 'start' => 10, 'length' => 10]);
check_table($first['draw'] === 7 && $second['draw'] === 8, 'Draw counters were not echoed.');
check_table(count($first['data']) <= 10 && count($second['data']) <= 10, 'Page size was ignored.');
check_table(!array_intersect(array_column($first['data'], 'row_id'), array_column($second['data'], 'row_id')), 'Pages overlap.');
$all = gso_np_summary_response($conn, ['select_all' => 1]);
check_table(count($all['data']) === $first['recordsTotal'], 'Select All omitted groups.');
$end = gso_np_summary_response($conn, ['start' => $first['recordsTotal'] + 10]);
check_table($end['data'] === [] && $end['recordsTotal'] === $first['recordsTotal'], 'Empty page counts changed.');
foreach (['purchase_order', 'supplier', 'department_name'] as $field) {
    foreach ($first['data'] as $row) {
        $value = trim($row[$field]);
        if ($value === '') { continue; }
        $filtered = gso_np_summary_response($conn, ['length' => 100, 'search' => ['value' => $value]]);
        $selected = gso_np_summary_response($conn, ['select_all' => 1, 'search' => ['value' => $value]]);
        check_table($filtered['recordsFiltered'] > 0 && $filtered['recordsFiltered'] <= $filtered['recordsTotal'], 'Search count is invalid.');
        check_table(count($selected['data']) === $filtered['recordsFiltered'], 'Filtered Select All differs from the table.');
        check_table(in_array($row['row_id'], array_column($selected['data'], 'row_id'), true), 'Search omitted its own displayed value.');
        break;
    }
}
$literal = gso_np_summary_response($conn, ['search' => ['value' => '%_!no-such-purchase']]);
check_table($literal['recordsFiltered'] === 0 && !$literal['data'], 'Search wildcards were not treated literally.');
// Every displayed total must equal the underlying item sum, independent of history joins.
foreach ($first['data'] as $row) {
    $key = $row['purchase_order'] === '' ? 'ID:' . $row['row_id'] : 'PO:' . strtoupper($row['purchase_order']);
    $sum = gso_np_summary_query($conn, 'SELECT SUM(COALESCE(unit_value,0)) total FROM new_purchase np WHERE ' . gso_np_summary_group_key() . ' = ?', [$key])[0]['total'];
    check_table(abs((float)$sum - $row['total_amount']) < 0.005, 'Summary total was multiplied by history joins.');
}
// Optional comparison to a captured original endpoint, retained locally in /tmp.
if (!empty($argv[1])) {
    $source = file_get_contents($argv[1]);
    check_table((bool)preg_match('/\$sql\s*=\s*(".*?");/s', $source, $match), 'Original query was not found.');
    eval('$originalSql = ' . $match[1] . ';');
    $original = gso_np_summary_query($conn, $originalSql);
    $current = [];
    for ($start = 0; $start < $first['recordsTotal']; $start += 100) {
        $current = array_merge($current, gso_np_summary_response($conn, ['start' => $start, 'length' => 100])['data']);
    }
    $byId = array_column($current, null, 'row_id');
    check_table(count($original) === count($byId), 'Purchase groups changed.');
    $differences = [];
    foreach ($original as $row) {
        $new = $byId[$row['row_id']] ?? null;
        check_table($new !== null, 'A purchase group disappeared.');
        foreach ($row as $field => $value) {
            $same = $field === 'total_amount' ? abs((float)$value - $new[$field]) < 0.005 : (string)$value === (string)$new[$field];
            if (!$same) { $differences[$field] = ($differences[$field] ?? 0) + 1; }
        }
    }
    echo 'Original/current field differences: ' . json_encode($differences) . PHP_EOL;
}
echo "Paging, counts, search, totals, and filtered selection checks passed.\n";
