<?php
// Include database connection and TCPDF library
require_once __DIR__ . '/../database/databaseConnection.php';
require_once __DIR__ . '/../tcpdf/tcpdf.php';
require_once __DIR__ . '/../include/generate_par_ics_number.php';
require_once __DIR__ . '/../include/summarize_print_rows.php';

function generateParIcsNumberNewPurchase(mysqli $conn, string $category): string {
    $ym = date('Ym');
    $cat = strtoupper(trim($category));
    $letter = (strpos($cat, 'ICS') !== false) ? 'I' : 'P';
    $prefix = $ym . '-' . $letter;
    $prefEsc = mysqli_real_escape_string($conn, $prefix);
    $max = 0;
    $sql = "SELECT MAX(CAST(SUBSTRING(par_ics_number, LENGTH('$prefEsc') + 1) AS UNSIGNED)) AS max_sfx FROM new_purchase WHERE par_ics_number LIKE CONCAT('$prefEsc','%')";
    $res = mysqli_query($conn, $sql);
    if ($res && mysqli_num_rows($res) === 1) {
        $row = mysqli_fetch_assoc($res);
        if ($row && $row['max_sfx'] !== null) { $max = (int)$row['max_sfx']; }
    }
    return $prefix . sprintf('%04d', ($max + 1));
}

// Validate and sanitize input
$ref_num = isset($_GET['reference_number']) ? trim($_GET['reference_number']) : '';
$refs_csv = isset($_GET['refs']) ? trim($_GET['refs']) : '';
$parsRaw  = isset($_GET['pars']) ? trim($_GET['pars']) : '';
$parFilter = isset($_GET['par']) ? trim($_GET['par']) : '';
// Build pars array: prefer ?pars= (multi), fall back to ?par= (single)
$pars = $parsRaw !== ''
    ? array_values(array_filter(array_map('trim', explode(',', $parsRaw))))
    : ($parFilter !== '' ? [$parFilter] : []);
$parsIn = count($pars) ? implode(',', array_fill(0, count($pars), '?')) : '';
$rows = [];
if ($refs_csv !== '') {
    $refs = array_filter(array_map('trim', explode(',', $refs_csv)));
    if (count($refs) > 0) {
        $placeholders = implode(',', array_fill(0, count($refs), '?'));
        // NEW purchases
        $sqlNew = "SELECT
            np.id AS new_purchase_id,
                np.property_number AS par_number,
                h.reference_number,
                '' AS previous_user,
                '' AS previous_dept,
                h.emp_id AS new_user,
                d.department_code AS new_dept,
                d.department_code,
                d.department_name,
                np.par_ics_number AS par_ics_number,
                np.model AS model,
                np.description AS description,
                np.unit AS unit,
                np.serial_number AS serial_number,
                np.serial_number_2 AS serial_number_2,
                np.account_code AS account_code,
                np.date_aquired AS date_aquired,
                np.unit_value AS unit_value,
                np.supplier AS supplier,
                np.purchase_order AS purchase_order,
                np.purchase_request AS purchase_request,
                np.obr_number AS obr_number,
                e.emp_name AS user,
                e.emp_id,
                UPPER(h.category) AS category,
                'NEW' AS source
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
            LEFT JOIN department AS d ON d.dept_id = h.dept_id
            LEFT JOIN employee AS e ON e.emp_id = h.emp_id
            WHERE h.reference_number IN ($placeholders)
              AND REPLACE(UPPER(h.category), '.', '') = 'ICS'
              AND h.status = 1";
        if (count($pars) > 0) { $sqlNew .= ' AND UPPER(np.property_number) IN (' . $parsIn . ')'; }
        $stmtNew = $conn->prepare($sqlNew);
        if ($stmtNew) {
            $types = str_repeat('s', count($refs)) . str_repeat('s', count($pars));
            $params = array_merge($refs, $pars);
            $stmtNew->bind_param($types, ...$params);
            $stmtNew->execute();
            $resNew = $stmtNew->get_result();
            while ($r = $resNew->fetch_assoc()) { $rows[] = $r; }
            $stmtNew->close();
        }

        // Legacy (existing inventory)
        $sqlOld = "SELECT 
                i.par_number, i.reference_number, i.previous_user, i.previous_dept, i.new_user, i.new_dept,
                d.department_code, d.department_name,
                COALESCE(pg.par_ics_number, ps.par_ics_number) AS par_ics_number,
                COALESCE(pg.model, ps.model) AS model,
                COALESCE(pg.description, ps.description) AS description,
                COALESCE(pg.unit, ps.unit) AS unit,
                COALESCE(pg.serial_number, ps.serial_number) AS serial_number,
                COALESCE(pg.serial_number_2, ps.serial_number_2) AS serial_number_2,
                COALESCE(pg.account_code, ps.account_code) AS account_code,
                COALESCE(pg.date_aquired, ps.date_aquired) AS date_aquired,
                COALESCE(pg.unit_value, ps.unit_value) AS unit_value,
                COALESCE(pg.supplier, ps.supplier) AS supplier,
                COALESCE(pg.purchase_order, ps.purchase_order) AS purchase_order,
                COALESCE(pg.purchase_request, ps.purchase_request) AS purchase_request,
                COALESCE(pg.obr_number, ps.obr_number) AS obr_number,
                e.emp_name as user, e.emp_id,
                UPPER(COALESCE(pg.category, ps.category)) AS category,
                'OLD' AS source
            FROM items_user_history AS i
            LEFT JOIN par_gen_fund AS pg ON i.par_number = pg.par_number
            LEFT JOIN property_sef AS ps ON i.par_number = ps.property_number
            LEFT JOIN department AS d ON i.new_dept = d.department_code
            LEFT JOIN employee as e ON i.new_user = e.emp_id
            WHERE i.reference_number IN ($placeholders) AND REPLACE(UPPER(COALESCE(pg.category, ps.category)), '.', '') = 'ICS'";
        if (count($pars) > 0) { $sqlOld .= ' AND i.par_number IN (' . $parsIn . ')'; }
        $stmtOld = $conn->prepare($sqlOld);
        if ($stmtOld) {
            $types = str_repeat('s', count($refs)) . str_repeat('s', count($pars));
            $params = array_merge($refs, $pars);
            $stmtOld->bind_param($types, ...$params);
            $stmtOld->execute();
            $resOld = $stmtOld->get_result();
            while ($r = $resOld->fetch_assoc()) { $rows[] = $r; }
            $stmtOld->close();
        }
    }
} elseif ($ref_num !== '') {
    // Use prepared statement for security
    // NEW purchases
    $sqlNew = "SELECT
            np.id AS new_purchase_id,
            np.property_number AS par_number,
            h.reference_number,
            '' AS previous_user,
            '' AS previous_dept,
            h.emp_id AS new_user,
            d.department_code AS new_dept,
            d.department_code, d.department_name,
            np.par_ics_number AS par_ics_number,
            np.model AS model,
            np.description AS description,
            np.unit AS unit,
            np.serial_number AS serial_number,
            np.serial_number_2 AS serial_number_2,
            np.account_code AS account_code,
            np.date_aquired AS date_aquired,
            np.unit_value AS unit_value,
            np.supplier AS supplier,
            np.purchase_order AS purchase_order,
            np.purchase_request AS purchase_request,
            np.obr_number AS obr_number,
            e.emp_name AS user, e.emp_id,
            UPPER(h.category) AS category,
            'NEW' AS source
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
        LEFT JOIN department AS d ON d.dept_id = h.dept_id
        LEFT JOIN employee as e ON e.emp_id = h.emp_id
        WHERE h.reference_number = ?
          AND REPLACE(UPPER(h.category), '.', '') = 'ICS'
          AND h.status = 1";
    if (count($pars) > 0) { $sqlNew .= ' AND UPPER(np.property_number) IN (' . $parsIn . ')'; }
    $stmtNew = $conn->prepare($sqlNew);
    if ($stmtNew) {
        $stmtNew->bind_param('s' . str_repeat('s', count($pars)), $ref_num, ...$pars);
        $stmtNew->execute();
        $resNew = $stmtNew->get_result();
        while ($r = $resNew->fetch_assoc()) { $rows[] = $r; }
        $stmtNew->close();
    }

    // Legacy (existing inventory)
    $sqlOld = "SELECT 
            i.par_number, i.reference_number, i.previous_user, i.previous_dept, i.new_user, i.new_dept,
            d.department_code, d.department_name,
            COALESCE(pg.par_ics_number, ps.par_ics_number) AS par_ics_number,
            COALESCE(pg.model, ps.model) AS model,
            COALESCE(pg.description, ps.description) AS description,
            COALESCE(pg.unit, ps.unit) AS unit,
            COALESCE(pg.serial_number, ps.serial_number) AS serial_number,
            COALESCE(pg.serial_number_2, ps.serial_number_2) AS serial_number_2,
            COALESCE(pg.account_code, ps.account_code) AS account_code,
            COALESCE(pg.date_aquired, ps.date_aquired) AS date_aquired,
            COALESCE(pg.unit_value, ps.unit_value) AS unit_value,
            COALESCE(pg.supplier, ps.supplier) AS supplier,
            COALESCE(pg.purchase_order, ps.purchase_order) AS purchase_order,
            COALESCE(pg.purchase_request, ps.purchase_request) AS purchase_request,
            COALESCE(pg.obr_number, ps.obr_number) AS obr_number,
            e.emp_name as user, e.emp_id,
            UPPER(COALESCE(pg.category, ps.category)) AS category,
            'OLD' AS source
        FROM items_user_history AS i
        LEFT JOIN par_gen_fund AS pg ON i.par_number = pg.par_number
        LEFT JOIN property_sef AS ps ON i.par_number = ps.property_number
        LEFT JOIN department AS d ON i.new_dept = d.department_code
        LEFT JOIN employee as e ON i.new_user = e.emp_id
    WHERE i.reference_number = ? AND REPLACE(UPPER(COALESCE(pg.category, ps.category)), '.', '') = 'ICS'";
    if (count($pars) > 0) { $sqlOld .= ' AND i.par_number IN (' . $parsIn . ')'; }
    $stmtOld = $conn->prepare($sqlOld);
    if ($stmtOld) {
        $stmtOld->bind_param('s' . str_repeat('s', count($pars)), $ref_num, ...$pars);
        $stmtOld->execute();
        $resOld = $stmtOld->get_result();
        while ($r = $resOld->fetch_assoc()) { $rows[] = $r; }
        $stmtOld->close();
    }
}

// Ensure each row has a par_ics_number; if missing, generate and persist
if (count($rows) > 0) {
    foreach ($rows as $idx => $r) {
        if (!isset($r['par_ics_number']) || trim($r['par_ics_number']) === '') {
            $source = isset($r['source']) ? strtoupper(trim((string)$r['source'])) : 'OLD';
            $newParIcs = ($source === 'NEW') ? generateParIcsNumberNewPurchase($conn, 'ICS') : generateParIcsNumber($conn, 'ICS');
            if ($source === 'NEW') {
                $newPurchaseId = (int)($r['new_purchase_id'] ?? 0);
                if ($newPurchaseId > 0 && ($stmtU = $conn->prepare("UPDATE new_purchase SET par_ics_number=? WHERE id=?"))) {
                    $stmtU->bind_param('si', $newParIcs, $newPurchaseId);
                    $stmtU->execute();
                    $stmtU->close();
                }
            } else {
                if ($stmtU = $conn->prepare("UPDATE par_gen_fund SET par_ics_number=? WHERE par_number=?")) {
                    $stmtU->bind_param('ss', $newParIcs, $r['par_number']);
                    $stmtU->execute();
                    $stmtU->close();
                }
                if ($stmtU2 = $conn->prepare("UPDATE property_sef SET par_ics_number=? WHERE property_number=?")) {
                    $stmtU2->bind_param('ss', $newParIcs, $r['par_number']);
                    $stmtU2->execute();
                    $stmtU2->close();
                }
            }
            $rows[$idx]['par_ics_number'] = $newParIcs;
        }
    }
}

// Set to A4 size (210mm x 297mm)
$pdf = new TCPDF('P', 'mm', 'A4');
$pdf->setPrintHeader(false);
$pdf->SetMargins(10, 10, 10);

// When "add multiple enduser" was used, a single reference number can contain
// items assigned to different employees. Group print output by end user (and department)
// so each end user's slips are printed together.
if (is_array($rows) && count($rows) > 1) {
    $users = [];
    foreach ($rows as $r) {
        $u = strtoupper(trim((string)($r['user'] ?? '')));
        if ($u !== '') { $users[$u] = true; }
    }
    if (count($users) > 1) {
        usort($rows, function ($a, $b) {
            $ua = strtoupper(trim((string)($a['user'] ?? '')));
            $ub = strtoupper(trim((string)($b['user'] ?? '')));
            if ($ua !== $ub) { return strcmp($ua, $ub); }

            $da = strtoupper(trim((string)($a['department_name'] ?? '')));
            $db = strtoupper(trim((string)($b['department_name'] ?? '')));
            if ($da !== $db) { return strcmp($da, $db); }

            $pa = strtoupper(trim((string)($a['par_number'] ?? '')));
            $pb = strtoupper(trim((string)($b['par_number'] ?? '')));
            return strcmp($pa, $pb);
        });
    }
}

function gso_ics_distinct_values(array $rows, string $field): array {
    $values = [];
    $seen = [];
    foreach ($rows as $row) {
        $value = trim((string)($row[$field] ?? ''));
        if ($value === '') { continue; }
        $key = strtoupper($value);
        if (isset($seen[$key])) { continue; }
        $seen[$key] = true;
        $values[] = $value;
    }
    return $values;
}

function gso_ics_join_distinct(array $rows, string $field): string {
    return implode('/', gso_ics_distinct_values($rows, $field));
}

function gso_ics_collapse_numbers(array $numbers): string {
    $numbers = array_values(array_filter(array_map(function ($value) {
        return trim((string)$value);
    }, $numbers), function ($value) {
        return $value !== '';
    }));
    if (count($numbers) === 0) { return ''; }

    $seen = [];
    $deduped = [];
    foreach ($numbers as $number) {
        $key = strtoupper($number);
        if (isset($seen[$key])) { continue; }
        $seen[$key] = true;
        $deduped[] = $number;
    }

    $groups = [];
    $unparsed = [];
    foreach ($deduped as $number) {
        if (!preg_match('/^(.+?)(\d+)$/', $number, $matches)) {
            $unparsed[] = $number;
            continue;
        }
        $prefix = $matches[1];
        $suffix = $matches[2];
        $width = strlen($suffix);
        $groupKey = strtoupper($prefix) . '|' . $width;
        if (!isset($groups[$groupKey])) {
            $groups[$groupKey] = ['prefix' => $prefix, 'width' => $width, 'numbers' => []];
        }
        $groups[$groupKey]['numbers'][(int)$suffix] = true;
    }

    $parts = $unparsed;
    foreach ($groups as $group) {
        $serials = array_keys($group['numbers']);
        sort($serials, SORT_NUMERIC);
        if (count($serials) === 0) { continue; }
        $format = function (int $serial) use ($group): string {
            return $group['prefix'] . str_pad((string)$serial, (int)$group['width'], '0', STR_PAD_LEFT);
        };
        $start = $serials[0];
        $prev = $serials[0];
        for ($i = 1; $i < count($serials); $i++) {
            $current = $serials[$i];
            if ($current === $prev + 1) {
                $prev = $current;
                continue;
            }
            $parts[] = ($start === $prev) ? $format($start) : $format($start) . ' to ' . $format($prev);
            $start = $current;
            $prev = $current;
        }
        $parts[] = ($start === $prev) ? $format($start) : $format($start) . ' to ' . $format($prev);
    }

    return implode('/', $parts);
}

function gso_ics_unit_label(array $row): string {
    $qty = (int)($row['qty'] ?? 1);
    if ($qty < 1) { $qty = 1; }
    $unit = strtoupper(trim((string)($row['unit'] ?? '')));
    if ($unit === '') { $unit = 'UNIT'; }

    $singularUnits = [
        'PCS' => 'PC',
        'PIECE' => 'PC',
        'PIECES' => 'PC',
        'UNITS' => 'UNIT',
        'LOTS' => 'LOT',
        'SETS' => 'SET',
        'GALS' => 'GAL',
        'LITERS' => 'L',
        'LITRES' => 'L',
        'BOXES' => 'BOX',
        'PACKS' => 'PACK',
        'ROLLS' => 'ROLL',
        'METERS' => 'METER',
        'METRES' => 'METER'
    ];
    if (isset($singularUnits[$unit])) { $unit = $singularUnits[$unit]; }

    if ($qty <= 1) { return $unit; }

    $pluralUnits = [
        'PC' => 'PCS',
        'UNIT' => 'UNITS',
        'LOT' => 'LOTS',
        'SET' => 'SETS',
        'GAL' => 'GALS',
        'L' => 'L',
        'BOX' => 'BOXES',
        'PACK' => 'PACKS',
        'ROLL' => 'ROLLS',
        'METER' => 'METERS'
    ];

    return $pluralUnits[$unit] ?? ($unit . 'S');
}

function gso_ics_is_new_condition(array $row): bool {
    $source = strtoupper(trim((string)($row['source'] ?? '')));
    $condition = strtoupper(trim((string)($row['condition'] ?? $row['unit_condition'] ?? '')));
    return $source === 'NEW' || $condition === 'NEW';
}

function gso_ics_description(array $row): string {
    $qty = (int)($row['qty'] ?? 1);
    if ($qty < 1) { $qty = 1; }
    $model = gso_ics_is_new_condition($row) ? '' : trim((string)($row['model'] ?? ''));
    $base = trim($model . ' ' . trim((string)($row['description'] ?? '')));
    
    // Handle serial numbers - check if we have an array of serial numbers (from grouping)
    $serialsArray = $row['serial_numbers'] ?? [];
    if (!is_array($serialsArray)) {
        $serialsArray = [trim((string)($row['serial_number'] ?? ''))];
    }
    
    // Filter out empty serial numbers
    $serialsArray = array_filter(array_map('trim', $serialsArray), function ($s) {
        return $s !== '';
    });
    
    if (empty($serialsArray)) {
        return $base;
    }
    
    // Format serial numbers
    $serialDisplay = '';
    if (count($serialsArray) === 1) {
        $serialDisplay = "SN: " . reset($serialsArray);
    } else {
        $serialDisplay = "SNs: " . implode(', ', $serialsArray);
    }
    
    return $base . "\n" . $serialDisplay;
}

function gso_ics_row_height($pdf, array $row, array $colWidths): float {
    $pdf->SetFont('dejavusans', 'B', 7.5);
    $descriptionLines = (int)$pdf->getNumLines(gso_ics_description($row), $colWidths[4]);
    $lineCount = max(1, $descriptionLines);
    return max(9.5, min(48.0, ($lineCount * 2.8) + 2.4));
}

function gso_ics_new_batches($pdf, array $rows, array $colWidths, float $availableHeight): array {
    $batches = [];
    $current = [];
    $currentHeight = 0.0;
    foreach ($rows as $row) {
        $rowHeight = gso_ics_row_height($pdf, $row, $colWidths);
        $wouldOverflowHeight = count($current) > 0 && (($currentHeight + $rowHeight) > $availableHeight);
        $wouldOverflowCount = count($current) >= 10;
        if ($wouldOverflowHeight || $wouldOverflowCount) {
            $batches[] = $current;
            $current = [];
            $currentHeight = 0.0;
        }
        $current[] = $row;
        $currentHeight += $rowHeight;
    }
    if (count($current) > 0) { $batches[] = $current; }
    return $batches;
}

function gso_ics_render_office_row($pdf, string $leftOffice, string $rightOffice): void {
    $cellWidth = 95;
    $cellHeight = 12;
    $lineHeight = 4.4;
    $padding = 2.5;
    $minFontSize = 6.5;
    $baseFontSize = 10;
    $officeValues = [$leftOffice, $rightOffice];
    $fontSize = $baseFontSize;

    while ($fontSize > $minFontSize) {
        $pdf->SetFont('dejavusans', 'B', $fontSize);
        $fits = true;
        foreach ($officeValues as $officeValue) {
            $lineCount = (int)$pdf->getNumLines($officeValue, $cellWidth - ($padding * 2));
            if ($lineCount > 2) {
                $fits = false;
                break;
            }
        }
        if ($fits) {
            break;
        }
        $fontSize -= 0.5;
    }

    $startX = $pdf->GetX();
    $startY = $pdf->GetY();
    $pdf->SetFont('dejavusans', 'B', $fontSize);
    $pdf->MultiCell($cellWidth, $cellHeight, $leftOffice, 1, 'C', false, 0, $startX, $startY, true, 0, false, true, $cellHeight, 'M', true);
    $pdf->MultiCell($cellWidth, $cellHeight, $rightOffice, 1, 'C', false, 1, $startX + $cellWidth, $startY, true, 0, false, true, $cellHeight, 'M', true);
    $pdf->SetFont('dejavusans', '', 10);
}

function render_ics_new_items_page($pdf, array $rows, float $bodyHeight = 95.0) {
    if (count($rows) === 0) { return; }
    $pdf->AddPage();
    $pdf->SetFont('dejavusans', 'I', 10);
    $pdf->SetY(10);
    $pdf->Cell(0, 5, 'Appendix 52', 0, 1, 'R');
    $logoPath = '../admin/logo.jpg';
    if (file_exists($logoPath)) {
        $logoWidth = 24; $logoHeight = 24; $pageWidth = 210; $margin = 10;
        $usableWidth = $pageWidth - 2 * $margin;
        $centerX = $margin + ($usableWidth - $logoWidth) / 2;
        $currentY = $pdf->GetY();
        $logoY = max(10, $currentY - 2);
        $pdf->Image($logoPath, $centerX, $logoY, $logoWidth, $logoHeight, '', '', '', false, 300, '', false, false, 0);
        $pdf->SetY($logoY + $logoHeight + 4);
    } else {
        $pdf->Ln(20);
    }

    $pdf->SetFont('dejavusans', 'B', 14);
    $pdf->Ln(-4);
    $pdf->Cell(0, 8, 'INVENTORY CUSTODIAN SLIP', 0, 1, 'C');
    $pdf->Ln(2);

    $icsNo = gso_ics_collapse_numbers(gso_ics_distinct_values($rows, 'par_ics_number'));
    $pdf->SetFont('dejavusans', '', 11);
    $pdf->Cell(35, 7, 'LGU:', 0, 0, 'L');
    $pdf->SetFont('dejavusans', 'B', 11);
    $pdf->Cell(60, 7, 'Parañaque City', 0, 0, 'L');
    $pdf->SetFont('dejavusans', '', 11);
    $pdf->Cell(20, 7, '', 0, 0);
    $pdf->Cell(20, 7, 'ICS No :', 0, 0, 'R');
    $pdf->SetFont('dejavusans', 'B', 9);
    $pdf->Cell(0, 7, $icsNo, 'B', 1, 'L');
    $pdf->SetFont('dejavusans', '', 11);
    $pdf->Cell(35, 7, 'Fund Cluster :', 0, 0, 'L');
    $pdf->SetFont('dejavusans', 'B', 11);
    $pdf->Cell(40, 7, 'General Fund', 0, 1, 'L');
    $pdf->Ln(2);

    $pdf->SetFont('dejavusans', '', 10);
    $colWidths = [11, 11, 22, 22, 78, 26, 20];
    $startX = $pdf->GetX();
    $startY = $pdf->GetY();
    $pdf->SetXY($startX, $startY);
    $pdf->Cell($colWidths[0], 14, 'Qty', 1, 0, 'C');
    $pdf->Cell($colWidths[1], 14, 'Unit', 1, 0, 'C');
    $pdf->Cell($colWidths[2] + $colWidths[3], 7, 'Amount', 1, 0, 'C');
    $pdf->Cell($colWidths[4], 14, 'Description', 1, 0, 'C');
    $pdf->MultiCell($colWidths[5], 14, "Inventory\nItem No.", 1, 'C', false, 0, '', '', true, 0, false, true, 14, 'M');
    $pdf->MultiCell($colWidths[6], 14, "Estimated\nUseful Life", 1, 'C', false, 1, '', '', true, 0, false, true, 14, 'M');
    $pdf->SetXY($startX + $colWidths[0] + $colWidths[1], $startY + 7);
    $pdf->Cell($colWidths[2], 7, 'Unit Cost', 1, 0, 'C');
    $pdf->Cell($colWidths[3], 7, 'Total Cost', 1, 0, 'C');

    $rowHeights = [];
    $usedBodyHeight = 0.0;
    foreach ($rows as $row) {
        $rowHeight = gso_ics_row_height($pdf, $row, $colWidths);
        $rowHeights[] = $rowHeight;
        $usedBodyHeight += $rowHeight;
    }

    $pdf->SetXY($startX, $startY + 14);
    $rowIndex = 0;
    foreach ($rows as $row) {
        $qty = (int)($row['qty'] ?? 1);
        if ($qty < 1) { $qty = 1; }
        $unitValue = (float)($row['unit_value'] ?? 0);
        $totalValue = (float)($row['total_value'] ?? ($unitValue * $qty));
        $rowHeight = $rowHeights[$rowIndex] ?? gso_ics_row_height($pdf, $row, $colWidths);
        $xRow = $pdf->GetX();
        $yRow = $pdf->GetY();
        $pdf->SetFont('dejavusans', 'B', 7.5);
        $pdf->MultiCell($colWidths[0], $rowHeight, (string)$qty, 'LR', 'C', false, 0, $xRow, $yRow, true, 0, false, true, $rowHeight, 'T');
        $pdf->SetFont('dejavusans', 'B', 6);
        $pdf->MultiCell($colWidths[1], $rowHeight, gso_ics_unit_label($row), 'LR', 'C', false, 0, '', '', true, 0, false, true, $rowHeight, 'T');
        $pdf->SetFont('dejavusans', 'B', 7.5);
        $pdf->MultiCell($colWidths[2], $rowHeight, '₱ ' . number_format($unitValue, 2), 'LR', 'R', false, 0, '', '', true, 0, false, true, $rowHeight, 'T');
        $pdf->MultiCell($colWidths[3], $rowHeight, '₱ ' . number_format($totalValue, 2), 'LR', 'R', false, 0, '', '', true, 0, false, true, $rowHeight, 'T');
        $pdf->MultiCell($colWidths[4], $rowHeight, gso_ics_description($row), 'LR', 'L', false, 0, '', '', true, 0, false, true, $rowHeight, 'T');
        $pdf->MultiCell($colWidths[5], $rowHeight, '', 'LR', 'C', false, 0, '', '', true, 0, false, true, $rowHeight, 'T', true);
        $pdf->MultiCell($colWidths[6], $rowHeight, '', 'LR', 'C', false, 1, '', '', true, 0, false, true, $rowHeight, 'T');
        $rowIndex++;
    }

    $remainingBodyHeight = max(0.0, $bodyHeight - $usedBodyHeight);
    if ($remainingBodyHeight > 0.01) {
        foreach ($colWidths as $columnIndex => $columnWidth) {
            $isLastColumn = ($columnIndex === (count($colWidths) - 1));
            $pdf->Cell($columnWidth, $remainingBodyHeight, '', 'LR', $isLastColumn ? 1 : 0, 'L');
        }
    }

    $pdf->SetFont('dejavusans', '', 10);
    $pdf->Cell(0, 8, '', 'TLR', 1, 'L');
    $footerDetails = [
        ['Supplier: ', gso_ics_join_distinct($rows, 'supplier')],
        ['Purchase Request: ', gso_ics_join_distinct($rows, 'purchase_request')],
        ['Purchase Order: ', gso_ics_join_distinct($rows, 'purchase_order')],
        ['OBR: ', gso_ics_join_distinct($rows, 'obr_number')],
        ['Account Code: ', gso_ics_join_distinct($rows, 'account_code')]
    ];
    foreach ($footerDetails as $detail) {
        $pdf->SetFont('dejavusans', '', 10);
        $pdf->Cell(40, 6, $detail[0], 'L', 0, 'L');
        $pdf->SetFont('dejavusans', 'B', 10);
        $pdf->Cell(0, 6, $detail[1], 'R', 1, 'L');
    }
    $pdf->SetFont('dejavusans', '', 10);
    $pdf->Cell(0, 1, '', 'LR', 1, 'L');

    $firstRow = $rows[0];
    $pdf->SetFont('dejavusans', '', 10);
    $pdf->Cell(95, 8, 'Received from:', 'LRT', 0, 'L');
    $pdf->Cell(95, 8, 'Received by:', 'LRT', 1, 'L');
    $pdf->SetFont('dejavusans', 'B', 10);
    $pdf->Cell(95, 8, 'ATTY. ARVIN Q. TAPIA', 'LRB', 0, 'C');
    $pdf->Cell(95, 8, (string)($firstRow['user'] ?? ''), 'LRB', 1, 'C');
    $pdf->SetFont('dejavusans', '', 9);
    $pdf->Cell(95, 6, 'Signature Over Printed Name', 'LR', 0, 'C');
    $pdf->Cell(95, 6, 'Signature Over Printed Name', 'LR', 1, 'C');
    gso_ics_render_office_row(
        $pdf,
        'OIC-GENERAL SERVICES OFFICE',
        (string)($firstRow['department_name'] ?? '')
    );
    $pdf->SetFont('dejavusans', '', 10);
    $pdf->Cell(95, 6, 'Position/Office', 'LRT', 0, 'C');
    $pdf->Cell(95, 6, 'Position/Office', 'LRT', 1, 'C');
    $pdf->Cell(95, 8, '', 'LRB', 0, 'L');
    $pdf->Cell(95, 8, '', 'LRB', 1, 'L');
    $pdf->Cell(95, 8, 'Date', 1, 0, 'C');
    $pdf->Cell(95, 8, 'Date', 1, 1, 'C');

    $pdf->Ln(2);
    $pdf->SetFont('dejavusans', 'I', 9);
    $pdf->Cell(0, 3, 'For Use of Supply and/or Property Division/Unit', 0, 1, 'L');
}

// Helper to render a full single-page ICS for one item
function render_ics_page($pdf, $row, $ics_no) {
    // Appendix and logo
    $pdf->AddPage();
    $pdf->SetFont('dejavusans', 'I', 10);
    $pdf->SetY(10);
    $pdf->Cell(0, 5, 'Appendix 52', 0, 1, 'R');
    $logoPath = '../admin/logo.jpg';
    if (file_exists($logoPath)) {
        $logoWidth = 24; $logoHeight = 24; $pageWidth = 210; $margin = 10;
        $usableWidth = $pageWidth - 2 * $margin;
        $centerX = $margin + ($usableWidth - $logoWidth) / 2;
        $currentY = $pdf->GetY();
        $logoY = max(10, $currentY - 2);
        $pdf->Image($logoPath, $centerX, $logoY, $logoWidth, $logoHeight, '', '', '', false, 300, '', false, false, 0);
        $pdf->SetY($logoY + $logoHeight + 4);
    } else {
        $pdf->Ln(20);
    }

    // Title
    $pdf->SetFont('dejavusans', 'B', 14);
    $pdf->Ln(-4);
    $pdf->Cell(0, 8, 'INVENTORY CUSTODIAN SLIP', 0, 1, 'C');
    $pdf->Ln(2);

    // LGU and ICS No
    $pdf->SetFont('dejavusans', '', 11);
    $pdf->Cell(35, 7, 'LGU:', 0, 0, 'L');
    $pdf->SetFont('dejavusans', 'B', 11);
    $pdf->Cell(60, 7, 'Parañaque City', 0, 0, 'L');
    $pdf->SetFont('dejavusans', '', 11);
    $pdf->Cell(20, 7, '', 0, 0);
    $pdf->Cell(20, 7, 'ICS No :', 0, 0, 'R');
    $pdf->SetFont('dejavusans', 'B', 11);
    $pdf->Cell(0, 7, $ics_no, 'B', 1, 'L');
    $pdf->SetFont('dejavusans', '', 11);
    $pdf->Cell(35, 7, 'Fund Cluster :', 0, 0, 'L');
    $pdf->SetFont('dejavusans', 'B', 11);
    $pdf->Cell(40, 7, 'General Fund', 0, 1, 'L');
    $pdf->Ln(2);

    // Table header
    $pdf->SetFont('dejavusans', '', 10);
    $colWidths = [11, 11, 22, 22, 78, 26, 20];
    $startX = $pdf->GetX();
    $startY = $pdf->GetY();
    $pdf->SetXY($startX, $startY);
    $pdf->Cell($colWidths[0], 14, 'Qty', 1, 0, 'C');
    $pdf->Cell($colWidths[1], 14, 'Unit', 1, 0, 'C');
    $pdf->Cell($colWidths[2] + $colWidths[3], 7, 'Amount', 1, 0, 'C');
    $pdf->Cell($colWidths[4], 14, 'Description', 1, 0, 'C');
    $pdf->MultiCell($colWidths[5], 14, "Inventory\nItem No.", 1, 'C', false, 0, '', '', true, 0, false, true, 14, 'M');
    $pdf->MultiCell($colWidths[6], 14, "Estimated\nUseful Life", 1, 'C', false, 1, '', '', true, 0, false, true, 14, 'M');
    // Amount sub-columns
    $pdf->SetXY($startX + $colWidths[0] + $colWidths[1], $startY + 7);
    $pdf->Cell($colWidths[2], 7, 'Unit Cost', 1, 0, 'C');
    $pdf->Cell($colWidths[3], 7, 'Total Cost', 1, 0, 'C');

    // Single item row
    $pdf->SetXY($startX, $startY + 14);
    $qty = $row['qty'] ?? 1;
    $unit = gso_ics_unit_label($row);
    $unitValue = (float)($row['unit_value'] ?? 0);
    $totalValue = $row['total_value'] ?? $unitValue;
    $unit_cost = '₱ ' . number_format($unitValue, 2);
    $total_cost = '₱ ' . number_format($totalValue, 2);
    $parNums           = ($qty > 1) ? ($row['par_numbers'] ?? [$row['par_number']]) : [$row['par_number']];
    $inventory_item_no = collapsePropertyNumbers($parNums);
    $estimated_life = 'NULL';
    $description = wordwrap(gso_ics_description($row), 50, "\n");
    $rowHeight = 95;
    $pdf->SetFont('dejavusans', 'B', 7.5);
    $xRow = $pdf->GetX();
    $yRow = $pdf->GetY();
    $pdf->MultiCell($colWidths[0], $rowHeight, $qty, 'LR', 'C', false, 0, $xRow, $yRow, true, 0, false, true, $rowHeight, 'T');
    $pdf->SetFont('dejavusans', 'B', 6);
    $pdf->MultiCell($colWidths[1], $rowHeight, $unit, 'LR', 'C', false, 0, '', '', true, 0, false, true, $rowHeight, 'T');
    $pdf->SetFont('dejavusans', 'B', 7.5);
    $pdf->MultiCell($colWidths[2], $rowHeight, $unit_cost, 'LR', 'R', false, 0, '', '', true, 0, false, true, $rowHeight, 'T');
    $pdf->MultiCell($colWidths[3], $rowHeight, $total_cost, 'LR', 'R', false, 0, '', '', true, 0, false, true, $rowHeight, 'T');
    $pdf->MultiCell($colWidths[4], $rowHeight, $description, 'LR', 'L', false, 0, '', '', true, 0, false, true, $rowHeight, 'T');
    $pdf->MultiCell($colWidths[5], $rowHeight, $inventory_item_no, 'LR', 'C', false, 0, '', '', true, 0, false, true, $rowHeight, 'T');
    $pdf->MultiCell($colWidths[6], $rowHeight, $estimated_life, 'LR', 'C', false, 1, '', '', true, 0, false, true, $rowHeight, 'T');
    $pdf->SetFont('dejavusans', '', 10);

    // Footer details for this item
    $source = strtoupper(trim((string)($row['source'] ?? '')));
    $cancelMsg = ($source === 'NEW') ? '' : 'This will cancel previous I.C.S to ' . (isset($row['previous_user']) ? $row['previous_user'] : '') . ' of ' . (isset($row['previous_dept']) ? $row['previous_dept'] : '');
    $pdf->Cell(0, 8, $cancelMsg, 'TLR', 1, 'L');
    // Supplier / PR / PO / OBR / Account Code rows
    $footerDetails = [
        ['Supplier: ', (isset($row['supplier']) ? $row['supplier'] : '')],
        ['Purchase Request: ', (isset($row['purchase_request']) ? $row['purchase_request'] : '')],
        ['Purchase Order: ', (isset($row['purchase_order']) ? $row['purchase_order'] : '')],
        ['OBR: ', (isset($row['obr_number']) ? $row['obr_number'] : '')],
        ['Account Code: ', (isset($row['account_code']) ? $row['account_code'] : '')]
    ];
    foreach ($footerDetails as $detail) {
        $pdf->SetFont('dejavusans', '', 10);
        $pdf->Cell(40, 6, $detail[0], 'L', 0, 'L');
        $pdf->SetFont('dejavusans', 'B', 10);
        $pdf->Cell(0, 6, $detail[1], 'R', 1, 'L');
    }
    $pdf->SetFont('dejavusans', '', 10);
    $pdf->Cell(0, 1, '', 'LR', 1, 'L');

    // Signature block
    $pdf->SetFont('dejavusans', '', 10);
    $pdf->Cell(95, 8, 'Received from:', 'LRT', 0, 'L');
    $pdf->Cell(95, 8, 'Received by:', 'LRT', 1, 'L');
    $pdf->SetFont('dejavusans', 'B', 10);
    $pdf->Cell(95, 8, 'ATTY. ARVIN Q. TAPIA', 'LRB', 0, 'C');
    $pdf->Cell(95, 8, (isset($row['user']) ? $row['user'] : ''), 'LRB', 1, 'C');
    $pdf->SetFont('dejavusans', '', 9);
    $pdf->Cell(95, 6, 'Signature Over Printed Name', 'LR', 0, 'C');
    $pdf->Cell(95, 6, 'Signature Over Printed Name', 'LR', 1, 'C');
    gso_ics_render_office_row(
        $pdf,
        'OIC-GENERAL SERVICES OFFICE',
        (string)($row['department_name'] ?? '')
    );
    $pdf->SetFont('dejavusans', '', 10);
    $pdf->Cell(95, 6, 'Position/Office', 'LRT', 0, 'C');
    $pdf->Cell(95, 6, 'Position/Office', 'LRT', 1, 'C');
    $pdf->Cell(95, 8, '', 'LRB', 0, 'L');
    $pdf->Cell(95, 8, '', 'LRB', 1, 'L');
    $pdf->Cell(95, 8, 'Date', 1, 0, 'C');
    $pdf->Cell(95, 8, 'Date', 1, 1, 'C');

    $pdf->Ln(2);
    $pdf->SetFont('dejavusans', 'I', 9);
    $pdf->Cell(0, 3, 'For Use of Supply and/or Property Division/Unit', 0, 1, 'L');
}

// Summarize identical items with no serial numbers into single rows
$rows = summarizePrintRows($rows);

// Build one page per item (or per summarized group)
if (count($rows) > 0) {
    $newGroups = [];
    $newGroupOrder = [];
    $legacyRows = [];

    foreach ($rows as $row) {
        $source = strtoupper(trim((string)($row['source'] ?? '')));
        if ($source !== 'NEW') {
            $legacyRows[] = $row;
            continue;
        }

        $groupKey = sha1(
            strtoupper(trim((string)($row['reference_number'] ?? ''))) . '|' .
            strtoupper(trim((string)($row['emp_id'] ?? ($row['user'] ?? '')))) . '|' .
            strtoupper(trim((string)($row['department_name'] ?? '')))
        );
        if (!isset($newGroups[$groupKey])) {
            $newGroups[$groupKey] = [];
            $newGroupOrder[] = $groupKey;
        }
        $newGroups[$groupKey][] = $row;
    }

    $newColWidths = [11, 11, 22, 22, 78, 26, 20];
    $newItemsAvailableHeight = 95.0;
    foreach ($newGroupOrder as $groupKey) {
        $batches = gso_ics_new_batches($pdf, $newGroups[$groupKey], $newColWidths, $newItemsAvailableHeight);
        foreach ($batches as $batch) {
            render_ics_new_items_page($pdf, $batch, $newItemsAvailableHeight);
        }
    }

    foreach ($legacyRows as $row) {
        $ics_no_val = !empty($row['par_ics_number']) ? $row['par_ics_number'] : (!empty($row['reference_number']) ? $row['reference_number'] : $ref_num);
        render_ics_page($pdf, $row, $ics_no_val);
    }
} else {
    // No data: still render an empty page with headers for clarity
    render_ics_page($pdf, [
        'unit_value' => 0,
        'par_number' => '',
        'model' => '',
        'description' => '',
        'serial_number' => '',
        'previous_user' => '',
        'previous_dept' => '',
        'supplier' => '',
        'purchase_request' => '',
        'purchase_order' => '',
        'obr_number' => '',
        'account_code' => '',
        'user' => '',
        'department_name' => ''
    ], $ref_num);
}

$pdf->Output('ics_form.pdf', 'I');
