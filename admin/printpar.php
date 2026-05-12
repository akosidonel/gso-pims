<?php
ini_set('display_errors', '1');
error_reporting(E_ALL);
require_once __DIR__ . '/../database/databaseConnection.php';
require_once __DIR__ . '/../tcpdf/tcpdf.php';

$refnumber = isset($_GET['refnumber']) ? trim((string)$_GET['refnumber']) : '';
if ($refnumber === '') {
  die('Missing reference number.');
}

// Optional: filter to specific property numbers only (re-print selected items)
$parsRaw = isset($_GET['pars']) ? trim((string)$_GET['pars']) : '';
$pars    = $parsRaw !== '' ? array_values(array_filter(array_map('trim', explode(',', $parsRaw)))) : [];
$parsInNew = count($pars) ? ' AND np.property_number IN (' . implode(',', array_fill(0, count($pars), '?')) . ')' : '';
$parsInGf  = count($pars) ? ' AND p.par_number IN ('      . implode(',', array_fill(0, count($pars), '?')) . ')' : '';
$parsInSef = count($pars) ? ' AND p.property_number IN ('  . implode(',', array_fill(0, count($pars), '?')) . ')' : '';

// Fetch PAR items by reference_number from property history (GF + SEF)
$items = [];
$meta = [
  'supplier' => '',
  'po' => '',
  'pr' => '',
  'obr' => '',
  'code' => '',
  'user' => '',
  'dept' => '',
  'par_ics_number' => '',
];

function gso_printpar_table_has_column(mysqli $conn, string $tableName, string $columnName): bool {
  $tableName = trim($tableName);
  $columnName = trim($columnName);
  if ($tableName === '' || $columnName === '') { return false; }
  $stmt = $conn->prepare('SHOW COLUMNS FROM `' . str_replace('`', '``', $tableName) . '` LIKE ?');
  if (!$stmt) { return false; }
  $stmt->bind_param('s', $columnName);
  $stmt->execute();
  $res = $stmt->get_result();
  $exists = ($res && $res->num_rows > 0);
  $stmt->close();
  return $exists;
}

$newPurchaseUnitSelect = gso_printpar_table_has_column($conn, 'new_purchase', 'unit')
  ? 'np.unit'
  : "'' AS unit";

function gso_repair_blank_new_purchase_history_links(mysqli $conn, string $referenceNumber, string $category): void {
  $historyRows = [];
  $sqlHistory = "
    SELECT id, created_at
    FROM new_purchase_history
    WHERE reference_number = ?
      AND UPPER(category) = ?
      AND status = 1
      AND (par_number IS NULL OR par_number = '')
    ORDER BY id ASC
  ";
  if ($stmt = $conn->prepare($sqlHistory)) {
    $categoryUpper = strtoupper($category);
    $stmt->bind_param('ss', $referenceNumber, $categoryUpper);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) { $historyRows[] = $row; }
    $stmt->close();
  }
  if (count($historyRows) === 0) { return; }

  $createdAtValues = [];
  foreach ($historyRows as $row) {
    $createdAt = trim((string)($row['created_at'] ?? ''));
    if ($createdAt !== '') { $createdAtValues[$createdAt] = true; }
  }
  if (count($createdAtValues) === 0) { return; }

  $createdAtList = array_keys($createdAtValues);
  $createdPlaceholders = implode(',', array_fill(0, count($createdAtList), '?'));
  $sqlPurchase = "
    SELECT np.id
    FROM new_purchase AS np
    WHERE UPPER(np.category) = ?
      AND (np.property_number IS NULL OR np.property_number = '')
      AND np.created_at IN ($createdPlaceholders)
      AND NOT EXISTS (
        SELECT 1
        FROM new_purchase_history AS h2
        WHERE h2.par_number = CONCAT('NPID:', np.id)
      )
    ORDER BY np.id ASC
    LIMIT " . count($historyRows);

  $purchaseIds = [];
  if ($stmt = $conn->prepare($sqlPurchase)) {
    $types = 's' . str_repeat('s', count($createdAtList));
    $params = array_merge([strtoupper($category)], $createdAtList);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
      $purchaseId = (int)($row['id'] ?? 0);
      if ($purchaseId > 0) { $purchaseIds[] = $purchaseId; }
    }
    $stmt->close();
  }

  $pairCount = min(count($historyRows), count($purchaseIds));
  if ($pairCount <= 0) { return; }
  if ($stmtUpdate = $conn->prepare('UPDATE new_purchase_history SET par_number = ? WHERE id = ? AND (par_number IS NULL OR par_number = \'\')')) {
    for ($i = 0; $i < $pairCount; $i++) {
      $historyId = (int)($historyRows[$i]['id'] ?? 0);
      $historyLink = 'NPID:' . $purchaseIds[$i];
      if ($historyId <= 0) { continue; }
      $stmtUpdate->bind_param('si', $historyLink, $historyId);
      $stmtUpdate->execute();
    }
    $stmtUpdate->close();
  }
}

gso_repair_blank_new_purchase_history_links($conn, $refnumber, 'PAR');

// NEW purchases (preferred): new_purchase_history + new_purchase
$sqlNew = "
  SELECT
    $newPurchaseUnitSelect,
    np.unit_value,
    np.item,
    np.model,
    np.serial_number,
    np.serial_number_2,
    np.description,
    np.par_ics_number,
    np.property_number AS property_number,
    np.date_aquired,
    np.supplier,
    np.purchase_order,
    np.purchase_request,
    np.obr_number,
    np.account_code,
    e.emp_name AS emp_name,
    d.department_name AS department_name
  FROM (
    SELECT DISTINCT emp_id, dept_id, par_number, reference_number, status, category, created_at
    FROM new_purchase_history
  ) AS h
  JOIN new_purchase AS np ON np.id = (
    SELECT np2.id
    FROM new_purchase AS np2
    WHERE (
      (UPPER(COALESCE(h.par_number, '')) LIKE 'NPID:%' AND np2.id = CAST(SUBSTRING(h.par_number, 6) AS UNSIGNED))
      OR (UPPER(COALESCE(h.par_number, '')) NOT LIKE 'NPID:%' AND np2.property_number = h.par_number)
    )
    ORDER BY (COALESCE(np2.unit_value, 0) > 0) DESC, np2.id ASC
    LIMIT 1
  )
  LEFT JOIN employee AS e ON e.emp_id = h.emp_id
  JOIN department AS d ON d.dept_id = h.dept_id
  WHERE h.reference_number = ?
    AND UPPER(h.category) = 'PAR'
    AND h.status = 1
" . $parsInNew . "
  ORDER BY np.property_number ASC
";

if ($stmt = $conn->prepare($sqlNew)) {
  $stmt->bind_param('s' . str_repeat('s', count($pars)), $refnumber, ...$pars);
  $stmt->execute();
  $res = $stmt->get_result();
  while ($row = $res->fetch_assoc()) {
    $items[] = $row;
    if ($meta['user'] === '') {
      $meta['supplier'] = (string)($row['supplier'] ?? '');
      $meta['po'] = (string)($row['purchase_order'] ?? '');
      $meta['pr'] = (string)($row['purchase_request'] ?? '');
      $meta['obr'] = (string)($row['obr_number'] ?? '');
      $meta['code'] = (string)($row['account_code'] ?? '');
      $meta['user'] = (string)($row['emp_name'] ?? '');
      $meta['dept'] = (string)($row['department_name'] ?? '');
      $meta['par_ics_number'] = (string)($row['par_ics_number'] ?? '');
    }
    if ($meta['par_ics_number'] === '' && (string)($row['par_ics_number'] ?? '') !== '') {
      $meta['par_ics_number'] = (string)$row['par_ics_number'];
    }
  }
  $stmt->close();
}

// General Fund
$sqlGf = "
  SELECT
    p.unit,
    p.unit_value,
    p.item,
    p.model,
    p.serial_number,
    p.serial_number_2,
    p.description,
    p.par_ics_number,
    p.par_number AS property_number,
    p.date_aquired,
    p.supplier,
    p.purchase_order,
    p.purchase_request,
    p.obr_number,
    p.account_code,
    e.emp_name AS emp_name,
    d.department_name AS department_name
  FROM general_fund_property_history AS h
  JOIN par_gen_fund AS p ON p.par_number = h.par_number
  JOIN employee AS e ON e.emp_id = h.emp_id
  JOIN department AS d ON d.dept_id = h.dept_id
  WHERE h.reference_number = ?
    AND UPPER(h.category) = 'PAR'
    AND h.status = 1
" . $parsInGf . "
  ORDER BY p.pargf_id ASC
";

if (count($items) === 0 && ($stmt = $conn->prepare($sqlGf))) {
  $stmt->bind_param('s' . str_repeat('s', count($pars)), $refnumber, ...$pars);
  $stmt->execute();
  $res = $stmt->get_result();
  while ($row = $res->fetch_assoc()) {
    $items[] = $row;
    if ($meta['user'] === '') {
      $meta['supplier'] = (string)($row['supplier'] ?? '');
      $meta['po'] = (string)($row['purchase_order'] ?? '');
      $meta['pr'] = (string)($row['purchase_request'] ?? '');
      $meta['obr'] = (string)($row['obr_number'] ?? '');
      $meta['code'] = (string)($row['account_code'] ?? '');
      $meta['user'] = (string)($row['emp_name'] ?? '');
      $meta['dept'] = (string)($row['department_name'] ?? '');
      $meta['par_ics_number'] = (string)($row['par_ics_number'] ?? '');
    }
    if ($meta['par_ics_number'] === '' && (string)($row['par_ics_number'] ?? '') !== '') {
      $meta['par_ics_number'] = (string)$row['par_ics_number'];
    }
  }
  $stmt->close();
}

// SEF (optional; in case PAR items are stored under SEF)
$sqlSef = "
  SELECT
    p.unit,
    p.unit_value,
    p.item,
    p.model,
    p.serial_number,
    p.serial_number_2,
    p.description,
    p.par_ics_number,
    p.property_number AS property_number,
    p.date_aquired,
    p.supplier,
    p.purchase_order,
    p.purchase_request,
    p.obr_number,
    p.account_code,
    e.emp_name AS emp_name,
    d.department_name AS department_name
  FROM sef_property_history AS h
  JOIN property_sef AS p ON p.property_number = h.property_number
  JOIN employee AS e ON e.emp_id = h.emp_id
  JOIN department AS d ON d.dept_id = h.sch_id
  WHERE h.reference_number = ?
    AND UPPER(h.category) = 'PAR'
    AND h.status = 1
" . $parsInSef . "
  ORDER BY p.sef_id ASC
";

if (count($items) === 0 && ($stmt = $conn->prepare($sqlSef))) {
  $stmt->bind_param('s' . str_repeat('s', count($pars)), $refnumber, ...$pars);
  $stmt->execute();
  $res = $stmt->get_result();
  while ($row = $res->fetch_assoc()) {
    $items[] = $row;
    if ($meta['user'] === '') {
      $meta['supplier'] = (string)($row['supplier'] ?? '');
      $meta['po'] = (string)($row['purchase_order'] ?? '');
      $meta['pr'] = (string)($row['purchase_request'] ?? '');
      $meta['obr'] = (string)($row['obr_number'] ?? '');
      $meta['code'] = (string)($row['account_code'] ?? '');
      $meta['user'] = (string)($row['emp_name'] ?? '');
      $meta['dept'] = (string)($row['department_name'] ?? '');
      $meta['par_ics_number'] = (string)($row['par_ics_number'] ?? '');
    }
    if ($meta['par_ics_number'] === '' && (string)($row['par_ics_number'] ?? '') !== '') {
      $meta['par_ics_number'] = (string)$row['par_ics_number'];
    }
  }
  $stmt->close();
}

if (count($items) === 0) {
  die('No PAR records found for this reference number.');
}

$accountCodes = [];
$accountCodeSeen = [];
foreach ($items as $row) {
  $accountCode = trim((string)($row['account_code'] ?? ''));
  if ($accountCode === '') { continue; }
  $accountCodeKey = strtoupper($accountCode);
  if (isset($accountCodeSeen[$accountCodeKey])) { continue; }
  $accountCodeSeen[$accountCodeKey] = true;
  $accountCodes[] = $accountCode;
}
if (count($accountCodes) > 0) {
  $meta['code'] = implode('/', $accountCodes);
}

function gso_bundle_table_columns(mysqli $conn, string $table): array {
  static $cache = [];

  $tableKey = strtolower(trim($table));
  if ($tableKey === '') { return []; }
  if (isset($cache[$tableKey])) { return $cache[$tableKey]; }

  $safeTable = preg_replace('/[^A-Za-z0-9_]/', '', $tableKey);
  if ($safeTable === '') {
    $cache[$tableKey] = [];
    return $cache[$tableKey];
  }

  $cache[$tableKey] = [];
  if ($res = mysqli_query($conn, "SHOW COLUMNS FROM `{$safeTable}`")) {
    while ($row = mysqli_fetch_assoc($res)) {
      $field = strtolower(trim((string)($row['Field'] ?? '')));
      if ($field !== '') {
        $cache[$tableKey][$field] = true;
      }
    }
    mysqli_free_result($res);
  }

  return $cache[$tableKey];
}

function gso_bundle_union_field(array $columns, string $column): string {
  return isset($columns[$column]) ? $column : ('NULL AS ' . $column);
}

// Bundle lookup:
// - NEW purchases: new_bundle_purchase
// - Transferred records: bundle_gen_fund / bundle_sef
// We render bundle details under the serial number block.
function gso_fetch_bundles_for_parent(mysqli $conn, string $bundleWith): array {
  $bundleWith = strtoupper(trim($bundleWith));
  if ($bundleWith === '') { return []; }

  $gfColumns = gso_bundle_table_columns($conn, 'bundle_gen_fund');
  $sefColumns = gso_bundle_table_columns($conn, 'bundle_sef');

  $sql = "
    SELECT
      nb.property_number AS bundle_property_number,
      COALESCE(nb.item, np_bundle.item, gf.item, sf.item, '') AS item,
      COALESCE(nb.model, np_bundle.model, gf.model, sf.model, '') AS model,
      COALESCE(nb.description, np_bundle.description, gf.description, sf.description, '') AS description,
      COALESCE(nb.serial_number, np_bundle.serial_number, gf.serial_number, sf.serial_number, '') AS serial_number,
      COALESCE(nb.serial_number_2, np_bundle.serial_number_2, gf.serial_number_2, sf.serial_number_2, '') AS serial_number_2
    FROM (
      SELECT property_number, bundle_with, item, model, description, serial_number, serial_number_2 FROM new_bundle_purchase
      UNION ALL
      SELECT property_number, bundle_with, " . gso_bundle_union_field($gfColumns, 'item') . ", " . gso_bundle_union_field($gfColumns, 'model') . ", " . gso_bundle_union_field($gfColumns, 'description') . ", " . gso_bundle_union_field($gfColumns, 'serial_number') . ", " . gso_bundle_union_field($gfColumns, 'serial_number_2') . " FROM bundle_gen_fund
      UNION ALL
      SELECT property_number, bundle_with, " . gso_bundle_union_field($sefColumns, 'item') . ", " . gso_bundle_union_field($sefColumns, 'model') . ", " . gso_bundle_union_field($sefColumns, 'description') . ", " . gso_bundle_union_field($sefColumns, 'serial_number') . ", " . gso_bundle_union_field($sefColumns, 'serial_number_2') . " FROM bundle_sef
    ) nb
    LEFT JOIN new_purchase np_bundle ON np_bundle.id = (
      SELECT np2.id
      FROM new_purchase np2
      WHERE np2.property_number = nb.property_number
        AND COALESCE(np2.unit_value, 0) = 0
      ORDER BY np2.id ASC
      LIMIT 1
    )
    LEFT JOIN par_gen_fund gf ON gf.par_number = nb.property_number
    LEFT JOIN property_sef sf ON sf.property_number = nb.property_number
    WHERE UPPER(nb.bundle_with) = UPPER(?)
    ORDER BY nb.property_number ASC
  ";

  $out = [];
  if ($stmt = $conn->prepare($sql)) {
    $stmt->bind_param('s', $bundleWith);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
      $bp = strtoupper(trim((string)($row['bundle_property_number'] ?? '')));
      if ($bp === '') { continue; }
      $rowKey = sha1($bp . '|' . strtoupper(trim((string)($row['item'] ?? ''))) . '|' . strtoupper(trim((string)($row['model'] ?? ''))) . '|' . strtoupper(trim((string)($row['description'] ?? ''))) . '|' . strtoupper(trim((string)($row['serial_number'] ?? ''))) . '|' . strtoupper(trim((string)($row['serial_number_2'] ?? ''))));
      $out[$rowKey] = $row;
    }
    $stmt->close();
  }
  return array_values($out);
}

function gso_bundle_block_html(array $bundlesByParentPropertyNumbers): string {
  // $bundlesByParentPropertyNumbers: [ parentProp => [bundleRow, ...], ... ]
  // Group by description; collect all serial numbers across duplicates, then render once.
  $groups = []; // dedupeKey => ['detail' => string, 'serials' => string[]]

  foreach ($bundlesByParentPropertyNumbers as $parentProp => $bundleRows) {
    if (!is_array($bundleRows) || count($bundleRows) === 0) { continue; }
    foreach ($bundleRows as $b) {
      $item  = trim((string)($b['item']  ?? ''));
      $model = trim((string)($b['model'] ?? ''));
      $desc  = trim((string)($b['description'] ?? ''));
      $s1    = trim((string)($b['serial_number']   ?? ''));
      $s2    = trim((string)($b['serial_number_2'] ?? ''));

      $part   = trim($item . ' ' . $model);
      $detail = $part !== '' ? $part : '';
      if ($desc !== '') {
        $detail = $detail !== '' ? ($detail . ' - ' . $desc) : $desc;
      }

      $dedupeKey = strtoupper(trim($detail));
      if (!isset($groups[$dedupeKey])) {
        $groups[$dedupeKey] = ['detail' => $detail, 'serials' => []];
      }
      foreach ([$s1, $s2] as $sn) {
        if ($sn !== '' && !in_array($sn, $groups[$dedupeKey]['serials'], true)) {
          $groups[$dedupeKey]['serials'][] = $sn;
        }
      }
    }
  }

  $lines = [];
  $hasBundleHeading = false;
  foreach ($groups as $group) {
    $detailSafe = htmlspecialchars($group['detail'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $serialText = implode(', ', $group['serials']);
    $serialSafe = htmlspecialchars($serialText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

      $line = '';
    if ($detailSafe !== '') { $line .= $detailSafe; }
    if ($serialSafe !== '') { $line .= ' (SN: ' . $serialSafe . ')'; }
    if (trim(strip_tags($line)) !== '') {
      if (!$hasBundleHeading) {
        $lines[] = '<br><br>Bundle Items:';
        $hasBundleHeading = true;
      }
      $lines[] = '<br>' . $line;
    }
  }
  return implode('', $lines);
}

// Pre-fetch bundles per property number (avoid duplicate DB calls)
$bundleCache = [];

function gso_print_unit_label($unitRaw, $qty) {
  $unit = strtolower(trim((string)$unitRaw));
  $qty = (int)$qty;

  if ($unit === '') {
    return ($qty > 1) ? 'units' : 'unit';
  }

  if ($qty <= 1) {
    return $unit;
  }

  $alreadyPlural = [
    'pcs', 'pieces', 'units', 'boxes', 'liters', 'pairs', 'sets', 'lots',
    'reams', 'packs', 'bottles', 'cans', 'bags', 'rolls', 'dozens'
  ];
  if (in_array($unit, $alreadyPlural, true)) {
    return $unit;
  }

  $irregular = [
    'pc' => 'pcs',
    'piece' => 'pieces',
    'unit' => 'units',
    'box' => 'boxes',
    'liter' => 'liters',
    'pair' => 'pairs',
    'set' => 'sets',
    'lot' => 'lots',
    'ream' => 'reams',
    'pack' => 'packs',
    'bottle' => 'bottles',
    'can' => 'cans',
    'bag' => 'bags',
    'roll' => 'rolls',
    'dozen' => 'dozens'
  ];
  if (isset($irregular[$unit])) {
    return $irregular[$unit];
  }

  if (preg_match('/(s|x|z|ch|sh)$/', $unit)) {
    return $unit . 'es';
  }

  if (preg_match('/[^aeiou]y$/', $unit)) {
    return substr($unit, 0, -1) . 'ies';
  }

  return $unit . 's';
}

// Combine rows: same description + same end user => single row w/ quantity
$groupsMap = [];
foreach ($items as $r) {
  $emp = strtoupper(trim((string)($r['emp_name'] ?? '')));
  $dept = strtoupper(trim((string)($r['department_name'] ?? '')));
  $item = strtoupper(trim((string)($r['item'] ?? '')));
  $model = strtoupper(trim((string)($r['model'] ?? '')));
  $desc = strtoupper(trim((string)($r['description'] ?? '')));
  $unit = strtoupper(trim((string)($r['unit'] ?? '')));
  $unitValue = (float)($r['unit_value'] ?? 0);
  $date = (string)($r['date_aquired'] ?? '');

  // Include department in the grouping key so the same employee in different departments
  // does not get merged into one print row.
  $key = sha1($emp . '|' . $dept . '|' . $item . '|' . $model . '|' . $desc . '|' . $unit . '|' . $unitValue);
  if (!isset($groupsMap[$key])) {
    $groupsMap[$key] = [
      'qty' => 0,
      'unit' => $unit,
      'unit_value' => $unitValue,
      'item' => (string)($r['item'] ?? ''),
      'model' => (string)($r['model'] ?? ''),
      'description' => (string)($r['description'] ?? ''),
      'date_aquired' => $date,
      'serials' => [],
      'emp_name' => (string)($r['emp_name'] ?? ''),
      'department_name' => (string)($r['department_name'] ?? ''),
    ];
  }
  $groupsMap[$key]['qty']++;
  $pn = trim((string)($r['property_number'] ?? ''));

  // Attach bundle details per parent property number
  if ($pn !== '') {
    $pnKey = strtoupper($pn);
    if (!array_key_exists($pnKey, $bundleCache)) {
      $bundleCache[$pnKey] = gso_fetch_bundles_for_parent($conn, $pnKey);
    }
    if (!isset($groupsMap[$key]['bundles_by_property'])) {
      $groupsMap[$key]['bundles_by_property'] = [];
    }
    if (!empty($bundleCache[$pnKey])) {
      $groupsMap[$key]['bundles_by_property'][$pnKey] = $bundleCache[$pnKey];
    }
  }
  $s1 = trim((string)($r['serial_number'] ?? ''));
  if ($s1 !== '' && !in_array($s1, $groupsMap[$key]['serials'], true)) {
    $groupsMap[$key]['serials'][] = $s1;
  }
  $s2 = trim((string)($r['serial_number_2'] ?? ''));
  if ($s2 !== '' && !in_array($s2, $groupsMap[$key]['serials'], true)) {
    $groupsMap[$key]['serials'][] = $s2;
  }
}

$printRows = array_values($groupsMap);

function gso_split_words($text, $limit) {
  $text = trim((string)$text);
  if ($text === '') { return [$text, '']; }
  $words = preg_split('/\s+/', $text);
  if (!$words || count($words) <= $limit) { return [$text, '']; }
  $first = implode(' ', array_slice($words, 0, $limit));
  $rest = implode(' ', array_slice($words, $limit));
  return [$first, $rest];
}

/**
 * Split $text so the first chunk fits within $maxLines rendered lines at $widthMm wide.
 * Mirrors splitTextByRenderedLines() in printpt.php.
 */
function gso_split_rendered_lines($pdf, $text, $widthMm, $maxLines) {
  $words = preg_split('/\s+/', trim((string)$text));
  $words = array_values(array_filter($words, function($w) { return $w !== ''; }));
  if (count($words) === 0) { return ['', '']; }
  $current = [];
  foreach ($words as $word) {
    $candidate = trim(implode(' ', array_merge($current, [$word])));
    $numLines = (int)$pdf->getNumLines($candidate, $widthMm);
    if ($numLines <= $maxLines || empty($current)) {
      $current[] = $word;
    } else {
      break;
    }
  }
  $first = implode(' ', $current);
  $rest  = implode(' ', array_slice($words, count($current)));
  return [$first, $rest];
}

class PDF extends TCPDF  
{
    public function header(){
        $this->Ln(42);
        $this->SetFont('dejavusans','',10);
        $this->SetX(33);
        $this->Cell(125,5,"CITY GOVERNMENT OF PARAÑAQUE",0,0);
        $this->SetX(168);
        $this->Cell(42,5,(string)($this->parno ?? ''),0,1,'L');
        $this->SetX(33);
        $this->Cell(130,5,"GENERAL FUND",0,1);
    }

    function renderTable($rows, $continuations = [], $grandTotal = null){
      $this->SetAutoPageBreak(true, 82);
      $this->SetMargins(15, 54, 15);
      $this->SetFont('dejavusans','',8);
        $this->SetY(54);
        $this->SetX(15);

        $html = '
          <table cellpadding="5" cellspacing="0" border="0">
          <tr align="center">
            <td width="37" align="center"></td>
            <td width="42" align="center"></td>
            <td width="246" align="justified"></td>
            <td width="47"></td>
            <td width="79"></td>
            <td></td>
          </tr>
        ';

        // Description column: width="246" px → 123 mm (same 0.5 mm/px ratio as printpt.php width="220" → 110 mm)
        $descWidthMm   = 123.0;
        $lineHeightMm  = 3.2;
        $footerTopY    = $this->getPageHeight() - 82;
        $contentStartY = 54;
        $usableHeightMm = max(20, $footerTopY - $contentStartY - 1.0);
        $baseMaxLines   = max(8, (int)floor($usableHeightMm / $lineHeightMm));
        $visibleRowCount = max(1, count($rows));
        $maxLinesPerPage = max(6, (int)floor($baseMaxLines / $visibleRowCount) - 1);

        $idx = 1;
        foreach($rows as $r){
          $desc     = (string)($r['description'] ?? '');
            $baseDesc = trim($desc);
          $noSplit = !empty($r['no_split']);
          if ($noSplit) {
            $descFirst = $baseDesc;
            $descRest  = '';
          } else {
            $this->SetFont('dejavusans', '', 8);
            [$descFirst, $descRest] = gso_split_rendered_lines($this, $baseDesc, $descWidthMm, $maxLinesPerPage);
          }
          $descFirstSafe = htmlspecialchars($descFirst, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

          $qty = (int)($r['qty'] ?? 1);
          if ($qty < 1) { $qty = 1; }

            $year = '';
            $rawDate = (string)($r['date_aquired'] ?? '');
            if ($rawDate !== '') { $year = date('Y', strtotime($rawDate)); }
            $yearSafe = htmlspecialchars((string)$year, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $uv = (float)($r['unit_value'] ?? 0);
            $rowTotal = $uv * $qty;
            $uvSafe = htmlspecialchars('₱ ' . number_format($uv, 2, '.', ','), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $rowTotalSafe = htmlspecialchars('₱ ' . number_format($rowTotal, 2, '.', ','), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

            $cell = $descFirstSafe;
            $serialBlockHtml = '';
            if (isset($r['serial_block_html'])) {
                $serialBlockHtml = (string)$r['serial_block_html'];
            } else {
                // Serial numbers must appear at the bottom of the full description.
                // If description overflows (descRest not empty), serials will be printed on the continuation page.
                $serials = (isset($r['serials']) && is_array($r['serials'])) ? $r['serials'] : [];
                $serialsText = implode(', ', array_map(function($s){ return trim((string)$s); }, $serials));
                $serialsSafe = htmlspecialchars($serialsText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                // Bundle details must be shown after serial number
                $bundlesByProp = (isset($r['bundles_by_property']) && is_array($r['bundles_by_property'])) ? $r['bundles_by_property'] : [];
                $bundleHtml = !empty($bundlesByProp) ? gso_bundle_block_html($bundlesByProp) : '';

                if ($serialsSafe !== '') {
                  $serialBlockHtml = '<br><br>Serial No.: ' . $serialsSafe;
                } elseif ($bundleHtml !== '') {
                  // Keep ordering requirement: show bundle after a (possibly N/A) serial label
                  $serialBlockHtml = '<br><br>Serial No.: N/A';
                } else {
                  $serialBlockHtml = '';
                }
                if ($bundleHtml !== '') {
                  $serialBlockHtml .= $bundleHtml;
                }
            }

            if (!$noSplit && trim($descRest) !== '') {
                $continuations[] = [
                    'qty' => $qty,
                'unit' => (string)($r['unit'] ?? ''),
                    'unit_value' => $uv,
                    'date_aquired' => $rawDate,
                    'description_rest' => $descRest,
                    'serial_block_html' => $serialBlockHtml,
                ];
            } else {
                $cell .= $serialBlockHtml;
            }

            $isContinuationOnly = !empty($r['is_continuation']);
            $qtyCell = $isContinuationOnly ? '' : (string)$qty;
            $unitCell = $isContinuationOnly ? '' : gso_print_unit_label((string)($r['unit'] ?? ''), $qty);
            if ($isContinuationOnly) {
              $yearSafe = '';
              $uvSafe = '';
              $rowTotalSafe = '';
            }

            $html .= '
                <tr>
                    <td>' . htmlspecialchars($qtyCell, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</td>
                    <td>' . htmlspecialchars($unitCell, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</td>
                    <td style="padding-left:5mm">' . $cell . '</td>
                    <td>' . $yearSafe . '</td>
                    <td align="right">' . $uvSafe . '</td>
                    <td align="right">' . $rowTotalSafe . '</td>
                </tr>
            ';

            $idx++;
        }

        if ($grandTotal !== null && count($continuations) === 0) {
          $grandTotalSafe = htmlspecialchars('₱ ' . number_format((float)$grandTotal, 2, '.', ','), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
          $html .= '
            <tr align="center">
              <td width="37"></td>
              <td width="42"></td>
              <td width="246"></td>
              <td width="47"></td>
              <td width="79" align="right" style="border-top:1px solid #000; padding-top:6px;"><strong>TOTAL</strong></td>
              <td align="right" style="border-top:1px solid #000; padding-top:6px;"><strong>' . $grandTotalSafe . '</strong></td>
            </tr>
          ';
        }

        $html .= '</table>';
        $this->writeHTML($html,true,false,false,false,'');

        return $continuations;
    }

    public function Footer(){
        $this->SetY(-102);
      $this->SetFont('dejavusans','',8);
        $this->SetY(-66);
        $this->SetX(35);
        $this->SetFont('dejavusans','',10);
        $this->Cell(95,5,$this->employee,0,0);//end user
        $this->Cell(10,5,"ATTY. ARVIN Q. TAPIA ",0,0);
        $this->Ln(13);
        $this->SetX(35);
        $this->SetFont('dejavusans','',10);
        $this->Cell(95,5,$this->dept,0,0);//dept
        $this->Cell(10,5,"OIC - General Services Office ",0,0);
        // Page number (scoped to this end user's page group)
        $this->SetY(-25);
        $this->SetFont('dejavusans','',9.5);
        $pageNum = 'Page ' . $this->getPageNumGroupAlias() . ' of ' . $this->getPageGroupAlias();
        $this->Cell(0, 10, $pageNum, 0, 0, 'C');
    }

    public function renderProcurementDetails(){
      $this->SetAutoPageBreak(false, 0);
      $this->SetY(-102);
      $this->SetFont('dejavusans','',8);
      $this->SetX(130);
      $this->Cell(62,5,$this->supplier,0,1);
      $this->SetX(130);
      $this->Cell(62,5,$this->po,0,1);//po
      $this->SetX(130);
      $this->Cell(62,5,$this->pr,0,1);//pr
      $this->SetX(130);
      $this->Cell(62,5,$this->obr,0,1);//obr
      $this->SetX(130);
      $this->Cell(62,5,$this->code,0,1);//code
      $this->SetAutoPageBreak(true, 82);
    }
  }


$pdf = new PDF('P','mm','A4',true,'UTF-8',false);

// Header/footer fields (repeat every page)
$pdf->supplier = 'SUPPLIER: ' . $meta['supplier'];
$pdf->po = 'P.O: ' . $meta['po'];
$pdf->pr = 'P.R: ' . $meta['pr'];
$pdf->obr = 'O.B.R: ' . $meta['obr'];
$pdf->code = 'ACCOUNT CODE: ' . $meta['code'];
$pdf->parno = (string)($meta['par_ics_number'] ?? '');

// Print per end user (and department): one page per distinct end user.
// This supports the "add multiple enduser" flow where a single reference number
// contains items assigned to different employees.
$pageBuckets = [];
$pageOrder = [];
foreach ($printRows as $r) {
  $empName = trim((string)($r['emp_name'] ?? ''));
  $deptName = trim((string)($r['department_name'] ?? ''));
  $pageKey = sha1(strtoupper($empName) . '|' . strtoupper($deptName));

  if (!isset($pageBuckets[$pageKey])) {
    $pageBuckets[$pageKey] = [
      'emp_name' => $empName,
      'department_name' => $deptName,
      'rows' => [],
    ];
    $pageOrder[] = $pageKey;
  }
  $pageBuckets[$pageKey]['rows'][] = $r;
}

foreach ($pageOrder as $pk) {
  $bucket = $pageBuckets[$pk];
  $rows = $bucket['rows'];
  $maxItemsPerPage = 3;
  $rowPages = array_chunk($rows, $maxItemsPerPage);

  // startPageGroup() must be called BEFORE AddPage() — it marks the *next* added
  // page as the start of a new group, resetting the per-group page counter.
  $pdf->startPageGroup();

  $grandTotal = 0.0;
  foreach ($rows as $r) {
    $qty = (int)($r['qty'] ?? 1);
    if ($qty < 1) { $qty = 1; }
    $uv = (float)($r['unit_value'] ?? 0);
    $grandTotal += ($qty * $uv);
  }

  for ($pageIndex = 0; $pageIndex < count($rowPages); $pageIndex++) {
    $chunkRows = $rowPages[$pageIndex];
    $pdf->AddPage();
    // IMPORTANT: TCPDF renders the footer of the *previous* page when AddPage() is called.
    // So set per-page fields (employee/dept) *after* AddPage() to avoid bleeding values.
    $pdf->employee = (string)($bucket['emp_name'] ?? '');
    $pdf->dept = (string)($bucket['department_name'] ?? '');

    $isLastPage = ($pageIndex === count($rowPages) - 1);
    $continuations = $pdf->renderTable($chunkRows, [], $isLastPage ? $grandTotal : null);
    if ($isLastPage && (!is_array($continuations) || count($continuations) === 0)) {
      $pdf->renderProcurementDetails();
    }

    // If any item description exceeds 140 words, print the remainder on the next page
    // immediately after this end user's page.
    if (is_array($continuations) && count($continuations) > 0) {
      // Build continuation rows (only description column filled)
      $contRows = [];
      foreach ($continuations as $c) {
          $contRows[] = [
          'qty' => (int)($c['qty'] ?? 1),
          'unit' => (string)($c['unit'] ?? ''),
          'unit_value' => (float)($c['unit_value'] ?? 0),
          'model' => '',
          // continuation-only: print the remainder, then serial numbers at the bottom (as HTML)
          'description' => (string)($c['description_rest'] ?? ''),
          'serial_block_html' => (string)($c['serial_block_html'] ?? ''),
          'date_aquired' => (string)($c['date_aquired'] ?? ''),
          'serials' => [],
          'is_continuation' => true,
          ];
      }
      if ($pageIndex + 1 < count($rowPages)) {
        $rowPages[$pageIndex + 1] = array_merge($contRows, $rowPages[$pageIndex + 1]);
      } else {
        $rowPages[] = $contRows;
      }
    }
  }
}

$pdf->Output();
?>
