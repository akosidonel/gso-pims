<?php
ini_set("display_errors", "1");
error_reporting(E_ALL);
require '../database/databaseConnection.php';
require_once('../tcpdf/tcpdf.php');
require_once('../include/summarize_print_rows.php');

function prs_splitTextToFitCellHeight(TCPDF $pdf, string $text, float $cellWidth, float $maxHeight): array {
    $normalized = trim(preg_replace('/\s+/', ' ', $text));
    if ($normalized === '') {
        return [''];
    }

    $words = preg_split('/\s+/', $normalized);
    $chunks = [];
    $current = '';

    foreach ($words as $word) {
        if ($word === '') {
            continue;
        }

        $candidate = ($current === '') ? $word : ($current . ' ' . $word);
        $height = $pdf->getStringHeight($cellWidth, $candidate);

        if ($height <= $maxHeight) {
            $current = $candidate;
            continue;
        }

        if ($current !== '') {
            $chunks[] = $current;
            $current = $word;
            continue;
        }

        // Single word longer than the cell height constraints; force it as its own chunk.
        $chunks[] = $candidate;
        $current = '';
    }

    if ($current !== '') {
        $chunks[] = $current;
    }

    return $chunks;
}

function prs_renderHeader(TCPDF $pdf, string $controlNumber, string $purposeType = ''): void {
    // Insert centered logo above the title
    $logoPath = '../admin/logo.jpg';
    if (file_exists($logoPath)) {
        $logoWidth = 20;
        $logoHeight = 20;
        $pageWidth = $pdf->getPageWidth();
        $margins = $pdf->getMargins();
        $usableWidth = $pageWidth - $margins['left'] - $margins['right'];
        $x = $margins['left'] + ($usableWidth - $logoWidth) / 2;
        $y = max(10, $margins['top'] - 8);
        $pdf->Image($logoPath, $x, $y, $logoWidth, $logoHeight, '', '', '', false, 300, '', false, false, 0);
        $pdf->SetY($y + $logoHeight + 2);
    }

    // Title
    $pdf->SetFont('helvetica', 'B', 13);
    $pdf->Cell(0, 8, 'PROPERTY RETURN SLIP', 0, 1, 'C');
    $pdf->Ln(4);

    // LGU Name (left) + Control Number (right)
    $pdf->SetFont('helvetica', '', 10);
    $lguText = 'Name of Local Government Unit: CITY OF PARAÑAQUE';
    $controlText = 'Control No.: ' . $controlNumber;
    $pageWidth = $pdf->getPageWidth();
    $margins = $pdf->getMargins();
    $usableWidth = $pageWidth - $margins['left'] - $margins['right'];
    $halfWidth = $usableWidth / 2;
    $pdf->Cell($halfWidth, 6, $lguText, 0, 0, 'L');
    $controlHtml = '<div style="text-align:right">Control No.: <b>' . htmlspecialchars($controlNumber, ENT_QUOTES, 'UTF-8') . '</b></div>';
    $pdf->writeHTMLCell($halfWidth, 6, '', '', $controlHtml, 0, 1, false, true, 'R', false);
    $pdf->Ln(2);

    // Purpose with checkboxes
    $pdf->SetFont('helvetica', '', 10);
    $purposeNorm = strtoupper(trim((string)$purposeType));
    $isDisposal = ($purposeNorm === 'DISPOSAL');
    $isReturnToStock = ($purposeNorm === 'RETURNED TO STOCK');
    $box = function(bool $checked): string {
        return $checked ? '[X]' : '[  ]';
    };
    $purpose = 'Purpose: ' . $box($isDisposal) . ' Disposal    ' . $box($isReturnToStock) . ' Returned to Stock    ' . $box(!$isDisposal && !$isReturnToStock) . ' Other __________________';
    $pdf->Cell(0, 6, $purpose, 0, 1, 'L');
    $pdf->Ln(4);
}

function prs_hasSerial(array $row): bool {
    $sn1 = strtoupper(trim((string)($row['serial_number'] ?? '')));
    $sn2 = strtoupper(trim((string)($row['serial_number_2'] ?? '')));
    return ($sn1 !== '' && $sn1 !== 'N/A') || ($sn2 !== '' && $sn2 !== 'N/A');
}

function prs_normalizeQty($value): int {
    $qty = (int)$value;
    return $qty > 0 ? $qty : 1;
}

function prs_groupReturnRows(array $rows): array {
    $out = [];
    $groups = [];

    foreach ($rows as $row) {
        $row['qty'] = prs_normalizeQty($row['qty'] ?? 1);
        $row['par_numbers'] = [trim((string)($row['par_number'] ?? ''))];

        if (prs_hasSerial($row)) {
            $out[] = $row;
            continue;
        }

        $keyParts = [
            strtoupper(trim((string)($row['emp_id'] ?? ''))),
            strtoupper(trim((string)($row['dept_id'] ?? ''))),
            strtoupper(trim((string)($row['item'] ?? ''))),
            strtoupper(trim((string)($row['model'] ?? ''))),
            strtoupper(trim((string)($row['description'] ?? ''))),
            number_format((float)($row['unit_value'] ?? 0), 2, '.', ''),
        ];
        $key = implode('|', $keyParts);

        if (!isset($groups[$key])) {
            $groups[$key] = $row;
            continue;
        }

        $groups[$key]['qty'] += $row['qty'];
        $groups[$key]['par_numbers'][] = trim((string)($row['par_number'] ?? ''));
    }

    return array_merge($out, array_values($groups));
}

function prs_formatUnitValue(array $row): string {
    $qty = prs_normalizeQty($row['qty'] ?? 1);
    $unitValue = (float)($row['unit_value'] ?? 0);
    $amount = number_format($unitValue, 2);

    if ($qty <= 1) {
        return $amount;
    }

    return $amount . "\nTotal: " . number_format($unitValue * $qty, 2);
}

    if(isset($_GET['reference_number'])){
        $ref_num = mysqli_real_escape_string($conn, $_GET['reference_number']);

        $hasQtyCol = false;
        $qCols = mysqli_query($conn, "SHOW COLUMNS FROM unserviceable_items_history LIKE 'qty'");
        if ($qCols && mysqli_num_rows($qCols) > 0) {
            $hasQtyCol = true;
        }

        $qtySelect = $hasQtyCol
            ? "COALESCE((SELECT h.qty FROM unserviceable_items_history AS h WHERE h.reference_number = r.reference_number AND h.par_number = r.par_number ORDER BY h.created_at DESC LIMIT 1), 1) AS qty,"
            : "1 AS qty,";

        // Support both Serviceable (in return_to_stock) and Unserviceable (moved to unserviceable_items)
        $sql = "
            SELECT 
                {$qtySelect}
                r.emp_id,
                r.dept_id,
                r.par_number,
                r.reference_number,
            rs.par_number AS rs_par_number,
            us.par_number AS us_par_number,
                COALESCE(rs.model, us.model) AS model,
                COALESCE(rs.item, us.item) AS item,
                COALESCE(rs.description, us.description) AS description,
                COALESCE(rs.serial_number, us.serial_number) AS serial_number,
                COALESCE(rs.serial_number_2, us.serial_number_2) AS serial_number_2,
                COALESCE(rs.unit_value, us.unit_value) AS unit_value,
                e.emp_id,
                e.emp_name,
                d.department_name,
                d.department_code
            FROM return_history AS r
            JOIN employee AS e ON r.emp_id = e.emp_id
            JOIN department AS d ON r.dept_id = d.department_code
            LEFT JOIN return_to_stock AS rs ON rs.par_number = r.par_number
            LEFT JOIN unserviceable_items AS us ON us.par_number = r.par_number
            WHERE r.reference_number = '$ref_num'
        ";
        $result = mysqli_query($conn, $sql);
    }

// Create new PDF document
$pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
$pdf->setPrintHeader(false);
$pdf->SetCreator(PDF_CREATOR);
$pdf->SetAuthor('GSO');
$pdf->SetTitle('Property Return Slip');

$pdf->SetMargins(10, 18, 10);
$pdf->SetAutoPageBreak(TRUE, 18);

$controlNumber = isset($ref_num) ? (string)$ref_num : '';

// Table
// ...existing code...

// Table (only vertical borders, top and bottom horizontal borders)
$pdf->SetFont('helvetica', 'B', 9);
$tableHeader = array(
    "QTY",
    "UNIT",
    "DESCRIPTION",
    "PROPERTY\nNUMBER",
    "PAR/ICS\nNO.",
    "UNIT\nVALUE"
);
$widths = array(13, 13, 63, 40, 25, 35);
$tableRows = 1;
$rowHeight = 110;
$headerHeight = 9;

// Collect rows up-front so we can paginate when DESCRIPTION is long
$rows = [];
$emp_name = '';
$department_name = '';
$purposeType = '';

if (isset($result) && $result && mysqli_num_rows($result) > 0) {
    while ($r = mysqli_fetch_assoc($result)) {
        $rows[] = $r;
    }
    if (!empty($rows[0]['emp_name'])) {
        $emp_name = $rows[0]['emp_name'];
    }
    if (!empty($rows[0]['department_name'])) {
        $department_name = $rows[0]['department_name'];
    }

    // Determine purpose for checkbox rendering.
    // - If item exists in unserviceable_items => Disposal
    // - Else if item exists in return_to_stock => Returned to Stock
    $hasUs = false;
    $hasRs = false;
    foreach ($rows as $rr) {
        if (!empty($rr['us_par_number'])) { $hasUs = true; }
        if (!empty($rr['rs_par_number'])) { $hasRs = true; }
    }
    if ($hasUs) {
        $purposeType = 'DISPOSAL';
    } elseif ($hasRs) {
        $purposeType = 'RETURNED TO STOCK';
    } else {
        $purposeType = '';
    }

    $rows = prs_groupReturnRows($rows);
}

// Build pages; if a DESCRIPTION overflows, continuation always starts on a new page
$pdf->SetFont('helvetica', '', 9);

$pages = [];
$currentPageRows = [];
$currentRowCount = 0;

$pushPage = function () use (&$pages, &$currentPageRows, &$currentRowCount, $tableRows) {
    while (count($currentPageRows) < $tableRows) {
        $currentPageRows[] = ['', '', '', '', '', ''];
    }
    $pages[] = $currentPageRows;
    $currentPageRows = [];
    $currentRowCount = 0;
};

$addRow = function (array $row) use (&$currentPageRows, &$currentRowCount, $tableRows, $pushPage) {
    if ($currentRowCount >= $tableRows) {
        $pushPage();
    }
    $currentPageRows[] = $row;
    $currentRowCount++;
};

foreach ($rows as $r) {
    $parts = [];
    if (!empty($r['description'])) {
        $parts[] = $r['description'];
    }
    if (!empty($r['item']) && (empty($r['description']) || stripos($r['description'], $r['item']) === false)) {
        $parts[] = $r['item'];
    }
    if (!empty($r['model'])) {
        $parts[] = 'Model: ' . $r['model'];
    }
    if (!empty($r['serial_number'])) {
        $parts[] = 'SN1: ' . $r['serial_number'];
    }
    if (!empty($r['serial_number_2'])) {
        $parts[] = 'SN2: ' . $r['serial_number_2'];
    }

    $fullDesc = implode(' | ', $parts);
    $maxDescHeight = max(1.0, (float)$rowHeight - 2.0);
    $descChunks = prs_splitTextToFitCellHeight($pdf, $fullDesc, (float)$widths[2], $maxDescHeight);
    if (count($descChunks) === 0) {
        $descChunks = [''];
    }

    // First chunk: full row with values
    $qtyInt = isset($r['qty']) ? (int)$r['qty'] : 1;
    if ($qtyInt < 1) { $qtyInt = 1; }
    $qtyVal = (string)$qtyInt;
    $unitLabel = ($qtyInt > 1) ? 'UNITS' : 'UNIT';

    $addRow([
        $qtyVal,
        $unitLabel,
        $descChunks[0],
        collapsePropertyNumbers($r['par_numbers'] ?? [$r['par_number'] ?? '']),
        '',
        prs_formatUnitValue($r)
    ]);

    // If more chunks exist, start them on a new page (avoid splitting in the middle of the table)
    if (count($descChunks) > 1) {
        $pushPage();
        for ($i = 1; $i < count($descChunks); $i++) {
            $addRow(['', '', $descChunks[$i], '', '', '']);
            if ($i < count($descChunks) - 1) {
                $pushPage();
            }
        }
    }
}

// If nothing was added, still render a single empty page
if (count($pages) === 0 && $currentRowCount === 0) {
    $currentPageRows[] = ['', '', '', '', '', ''];
    $currentRowCount = 1;
}
if ($currentRowCount > 0) {
    $pushPage();
}

$totalPages = count($pages);

for ($pageIndex = 0; $pageIndex < $totalPages; $pageIndex++) {
    $pdf->AddPage();
    prs_renderHeader($pdf, $controlNumber, $purposeType);

    // Calculate table position and width
    $tableX = $pdf->GetX();
    $tableY = $pdf->GetY();
    $tableWidth = array_sum($widths);
    $tableHeight = $headerHeight + ($tableRows * $rowHeight);

    // Draw top border
    $pdf->Line($tableX, $tableY, $tableX + $tableWidth, $tableY);
    // Draw left and right borders
    for ($i = 0, $x = $tableX; $i < count($widths); $i++) {
        $pdf->Line($x, $tableY, $x, $tableY + $tableHeight);
        $x += $widths[$i];
    }
    $pdf->Line($tableX + $tableWidth, $tableY, $tableX + $tableWidth, $tableY + $tableHeight);
    // Draw bottom border
    $pdf->Line($tableX, $tableY + $tableHeight, $tableX + $tableWidth, $tableY + $tableHeight);

    // Print header row
    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->SetXY($tableX, $tableY);
    for ($i = 0; $i < count($tableHeader); $i++) {
        $x = $pdf->GetX();
        $y = $pdf->GetY();
        $pdf->MultiCell($widths[$i], $headerHeight, $tableHeader[$i], 1, 'C', false, 0, $x, $y, true, 0, false, true, $headerHeight, 'M');
    }
    $pdf->Ln();

    // Print fixed number of data rows per page
    $pdf->SetFont('helvetica', '', 9);
    $pageRows = $pages[$pageIndex];

    for ($r = 0; $r < $tableRows; $r++) {
        for ($c = 0; $c < count($widths); $c++) {
            $value = $pageRows[$r][$c];
            $x = $pdf->GetX();
            $y = $pdf->GetY();
            $align = ($c === 2) ? 'L' : 'C';
            $pdf->MultiCell($widths[$c], $rowHeight, $value, 0, $align, false, 0, $x, $y, true, 0, false, true, $rowHeight, 'T');
        }
        $pdf->Ln();
    }

    // Add certification section before signature (print on every page)
    $pdf->Ln(2);
    $pdf->Cell(90, 6, 'I hereby certify that I have returned the above items', 0, 0, 'L');
    $pdf->Cell(0, 6, 'I hereby certify that I have received the above items', 0, 1, 'L');
    $pdf->Cell(90, 6, 'this day of _________________________', 0, 0, 'L');
    $pdf->Cell(0, 6, 'this day of __________________________', 0, 1, 'L');
    $pdf->Cell(90, 6, 'Returned by:', 0, 0, 'L');
    $pdf->Cell(0, 6, 'Received by:', 0, 1, 'L');
    $pdf->Ln(8);

    // Signature section
    if ($emp_name) {
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->Cell(90, 6, $emp_name, 0, 0, 'L');
        $pdf->SetFont('helvetica', '', 9);
        $pdf->Cell(0, 2, '', 0, 1, 'L');
        $pdf->SetY($pdf->GetY() - 2);
    }
    $pdf->Cell(90, 6,'___________________________________', 0, 0, 'L');
    $pdf->Cell(0, 6, '______________________________', 0, 1, 'L');
    $pdf->Cell(90, 6, 'SIGNATURE OVER PRINTED NAME', 0, 0, 'L');
    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->Cell(0, 6, 'CARLOS J. LUMAGBAS', 0, 1, 'L');
    $pdf->SetFont('helvetica', '', 9);
    $pdf->Cell(90, 6, '', 0, 0, 'L');
    $pdf->Cell(0, 6, 'Administrative Officer I', 0, 1, 'L');
    $pdf->Ln(10);

    if ($department_name) {
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->Cell(90, 6, $department_name, 0, 0, 'L');
        $pdf->SetFont('helvetica', '', 9);
        $pdf->Cell(0, 2, '', 0, 1, 'L');
        $pdf->SetY($pdf->GetY() - 2);
    }
    $pdf->Cell(90, 6, '___________________________________', 0, 0, 'L');
    $pdf->Cell(0, 6, '______________________________', 0, 1, 'L');
    $pdf->Cell(90, 6, 'OFFICE/DEPARTMENT', 0, 0, 'L');
    $pdf->Ln(1);
    $pdf->Cell(90, 6, '', 0, 0, 'L');
    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->Cell(0, 6, 'ATTY. ARVIN Q. TAPIA', 0, 1, 'L');
    $pdf->SetFont('helvetica', '', 9);

    $pdf->Cell(90, 6, '', 0, 0, 'L');
    $pdf->Cell(0, 6, 'OIC-General Services Office', 0, 1, 'L');
}

// Footer printing is handled inside the pagination loop (every page)

// Output PDF
$pdf->Output('property_return_slip.pdf', 'I');
