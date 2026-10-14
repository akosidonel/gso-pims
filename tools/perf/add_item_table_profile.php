<?php
// Read-only table benchmark: php tools/perf/add_item_table_profile.php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
ini_set('session.save_path', sys_get_temp_dir());
require_once __DIR__ . '/../../config/session_bootstrap.php';
gso_start_secure_session();
$_SESSION['alogin'] = 'table-profile';
if (in_array('--recent', $argv, true)) {
    require_once __DIR__ . '/../../database/databaseConnection.php';
    $source = file_get_contents(__DIR__ . '/../../admin/add-item.php');
    if (!preg_match('/\$recentItemsSql\s*=\s*(".*?");/s', $source, $match)) { throw new RuntimeException('Recent-item query was not found.'); }
    eval('$recentSql = ' . $match[1] . ';');
    $recentStart = microtime(true);
    $recent = $conn->query($recentSql);
    fprintf(STDERR, "Existing table: %.1f ms; %d rows\n", (microtime(true)-$recentStart)*1000, $recent->num_rows);
}
$search = isset($argv[1]) && substr($argv[1], 0, 2) !== '--' ? $argv[1] : '';
$_POST = ['draw' => 1, 'start' => 0, 'length' => 10, 'search' => ['value' => $search]];
$start = microtime(true);
ob_start();
register_shutdown_function(function () use ($start) {
    $json = ob_get_clean();
    $response = json_decode($json, true);
    if (!is_array($response) || isset($response['error'])) {
        fwrite(STDERR, 'Invalid table response: ' . $json . PHP_EOL);
        return;
    }
    printf("Table load: %.1f ms; %d rows; %d bytes; total=%s; filtered=%s\n",
        (microtime(true)-$start)*1000, count($response['data'] ?? []), strlen($json),
        $response['recordsTotal'] ?? 'unpaged', $response['recordsFiltered'] ?? 'unpaged');
});
require __DIR__ . '/../../auth/fetch_new_purchase_summary.php';
