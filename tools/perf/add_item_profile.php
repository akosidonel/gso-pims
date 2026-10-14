<?php
// Read-only: php tools/perf/add_item_profile.php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
define('GSO_DASHBOARD_METRICS_REQUEST', true);
require_once __DIR__ . '/../../auth/auth.php';

if (in_array('--add-indexes', $argv, true)) {
    foreach (['new_purchase_history', 'general_fund_property_history', 'sef_property_history',
        'trust_fund_history', 'donation_history', 'items_user_history'] as $table) {
        $result = $conn->query("SHOW INDEX FROM `$table`");
        $hasReferenceIndex = false;
        while ($index = $result->fetch_assoc()) {
            if ((int)$index['Seq_in_index'] === 1 && $index['Column_name'] === 'reference_number') {
                $hasReferenceIndex = true;
            }
        }
        if (!$hasReferenceIndex) {
            $conn->query("ALTER TABLE `$table` ADD INDEX idx_print_reference (reference_number)");
            echo "Added reference index: $table\n";
        }
    }
}

$sample = $conn->query("SELECT np.category, np.date_aquired, np.account_code, d.department_code, np.fund
    FROM new_purchase np JOIN new_purchase_history h ON h.par_number = np.property_number
    JOIN department d ON d.dept_id = h.dept_id
    WHERE np.category IN ('PAR','ICS') AND np.property_number <> '' AND np.account_code <> '' LIMIT 1")->fetch_assoc();
if (!$sample) { throw new RuntimeException('No numbered purchase available for profiling.'); }
$cache = [];
$reserved = [];
$start = microtime(true);
for ($i = 0; $i < 20; $i++) {
    $result = gso_generate_one_property_number($conn, $sample['category'], $sample['date_aquired'],
        $sample['account_code'], $sample['department_code'], $sample['fund'], $reserved, 10, $cache);
    if (empty($result['ok'])) { throw new RuntimeException($result['error']); }
    $reserved = array_merge($reserved, $result['numbers']);
}
if (count(array_unique($reserved)) !== 200) { throw new RuntimeException('Preview reused a property number.'); }
printf("Number preview, 20 sets / 200 units: %.1f ms\n", (microtime(true) - $start) * 1000);

foreach (['PAR', 'ICS'] as $category) {
    $stmt = $conn->prepare("SELECT reference_number FROM new_purchase_history WHERE category = ? AND status = 1 AND par_number <> '' GROUP BY reference_number ORDER BY COUNT(*) DESC LIMIT 1");
    $stmt->bind_param('s', $category);
    $stmt->execute();
    $ref = $stmt->get_result()->fetch_assoc()['reference_number'] ?? '';
    $stmt->close();
    if ($ref === '') { continue; }
    $start = microtime(true);
    $stmt = $conn->prepare("SELECT np.id FROM (
        SELECT DISTINCT emp_id, dept_id, par_number, reference_number, status, category, created_at
        FROM new_purchase_history
        WHERE reference_number = ? AND status = 1
    ) h JOIN new_purchase np ON np.id = IF(UPPER(COALESCE(h.par_number,'')) LIKE 'NPID:%',
        CAST(SUBSTRING(h.par_number,6) AS UNSIGNED),
        (SELECT np2.id FROM new_purchase np2 WHERE np2.property_number = h.par_number
         ORDER BY (COALESCE(np2.unit_value,0)>0) DESC, np2.id ASC LIMIT 1))
    WHERE REPLACE(UPPER(h.category),'.','') = ?");
    $stmt->bind_param('ss', $ref, $category);
    $stmt->execute();
    $result = $stmt->get_result();
    printf("%s print item lookup: %.1f ms (%d rows)\n", $category, (microtime(true)-$start)*1000, $result->num_rows);
    $optimizedIds = array_column($result->fetch_all(MYSQLI_ASSOC), 'id');
    $stmt->close();
    if (in_array('--compare-print', $argv, true)) {
        $start = microtime(true);
        $stmt = $conn->prepare("SELECT np.id FROM (
            SELECT DISTINCT emp_id, dept_id, par_number, reference_number, status, category, created_at
            FROM new_purchase_history
        ) h JOIN new_purchase np ON np.id = (
            SELECT np2.id FROM new_purchase np2 WHERE
            (UPPER(COALESCE(h.par_number,'')) LIKE 'NPID:%' AND np2.id=CAST(SUBSTRING(h.par_number,6) AS UNSIGNED))
            OR (UPPER(COALESCE(h.par_number,'')) NOT LIKE 'NPID:%' AND np2.property_number=h.par_number)
            ORDER BY (COALESCE(np2.unit_value,0)>0) DESC, np2.id ASC LIMIT 1
        ) WHERE h.reference_number=? AND REPLACE(UPPER(h.category),'.','')=? AND h.status=1");
        $stmt->bind_param('ss', $ref, $category);
        $stmt->execute();
        $legacyIds = array_column($stmt->get_result()->fetch_all(MYSQLI_ASSOC), 'id');
        printf("%s original print lookup: %.1f ms\n", $category, (microtime(true)-$start)*1000);
        sort($legacyIds);
        sort($optimizedIds);
        if ($legacyIds !== $optimizedIds) { throw new RuntimeException('Print rows changed.'); }
        echo "Print row equivalence passed.\n";
        $stmt->close();
    }
}
