<?php
define('GSO_AUTH_LIB_ONLY', true);
require_once __DIR__ . '/../auth/auth.php';
require_once __DIR__ . '/../tcpdf/tcpdf.php';
require_once __DIR__ . '/../include/summarize_print_rows.php';

define('PTR_PAGE_WIDTH', 216);
define('PTR_PAGE_HEIGHT', 356);
define('PTR_MARGIN', 10);
define('PTR_BOTTOM_Y', PTR_PAGE_HEIGHT - PTR_MARGIN);
define('PTR_FOOTER_HEIGHT', 80);
define('PTR_ROW_MIN_HEIGHT', 14);
define('PTR_ROW_MAX_HEIGHT', 140);
define('PTR_COL', [12, 25, 40, 50, 35, 34]);

function ptr_request_references(): array {
    $refsCsv = isset($_GET['refs']) ? trim((string)$_GET['refs']) : '';
    if ($refsCsv !== '') {
        return array_values(array_filter(array_map('trim', explode(',', $refsCsv))));
    }

    $batchRef = isset($_GET['reference_number']) ? trim((string)$_GET['reference_number']) : '';
    return $batchRef === '' ? [] : [$batchRef];
}

function groupAndSummarizePtrRows(array $rows): array {
    $buckets = [];
    $order = [];

    foreach ($rows as $row) {
        $key = sha1(strtoupper(trim($row['emp_name'] ?? '')) . '|' . strtoupper(trim($row['department_code'] ?? '')));
        if (!isset($buckets[$key])) {
            $buckets[$key] = ['meta' => $row, 'items' => []];
            $order[] = $key;
        }
        $buckets[$key]['items'][] = $row;
    }

    foreach ($order as $key) {
        $individual = [];
        $grouped = [];

        foreach ($buckets[$key]['items'] as $row) {
            $sn1 = trim((string)($row['serial_number'] ?? ''));
            $sn2 = trim((string)($row['serial_number_2'] ?? ''));
            $hasSerial = ($sn1 !== '' && strtoupper($sn1) !== 'N/A')
                || ($sn2 !== '' && strtoupper($sn2) !== 'N/A');

            $row['qty'] = 1;
            $row['total_value'] = (float)($row['unit_value'] ?? 0);
            $row['par_numbers'] = [$row['p_par_number'] ?? ''];

            if ($hasSerial) {
                $individual[] = $row;
                continue;
            }

            $groupKey = strtoupper(trim($row['model'] ?? '')) . '|' . strtoupper(trim($row['description'] ?? ''));
            if (!isset($grouped[$groupKey])) {
                $grouped[$groupKey] = $row;
                continue;
            }

            $grouped[$groupKey]['qty']++;
            $grouped[$groupKey]['total_value'] += (float)($row['unit_value'] ?? 0);
            $grouped[$groupKey]['par_numbers'][] = $row['p_par_number'] ?? '';
        }

        $buckets[$key]['items'] = array_merge($individual, array_values($grouped));
    }

    return array_map(function ($key) use ($buckets) {
        return $buckets[$key];
    }, $order);
}

function ptr_render_page_header(TCPDF $pdf, array $meta): void {
    $pdf->AddPage();
    $pdf->SetY(PTR_MARGIN);
    $pdf->SetDrawColor(255, 255, 255);
    $pdf->SetFont('dejavusans', 'B', 13);

    $logoPath = __DIR__ . '/logo.jpg';
    if (file_exists($logoPath)) {
        $logoWidth = 20;
        $logoHeight = 20;
        $centerX = PTR_MARGIN + ((PTR_PAGE_WIDTH - (PTR_MARGIN * 2)) - $logoWidth) / 2;
        $pdf->Image($logoPath, $centerX, PTR_MARGIN, $logoWidth, $logoHeight, '', '', '', false, 300, '', false, false, 0);
        $pdf->SetY(PTR_MARGIN + $logoHeight + 2);
    }

    $pdf->Cell(0, 7, 'PROPERTY TRANSFER REPORT', 0, 2, 'C');
    $pdf->SetDrawColor(0, 0, 0);
    $pdf->Ln(2);

    $pdf->SetFont('dejavusans', '', 10);
    $pdf->Cell(120, 8, 'Entity Name : ' . ($meta['department_name'] ?? ''), 1, 0, 'L');
    $pdf->Cell(0, 8, 'Fund Cluster : GENERAL FUND', 1, 1, 'L');

    $pdf->SetFont('dejavusans', '', 9);
    $pdf->Cell(155, 8, 'From Accountable Officer/Agency : ' . ($meta['previous_user'] ?? '') . '/' . ($meta['previous_dept'] ?? ''), 1, 0, 'L');
    $pdf->Cell(0, 8, 'PTR No. : ' . ($meta['ptr_number'] ?? ''), 1, 1, 'L');
    $pdf->Cell(155, 8, 'To Accountable Officer/Agency : ' . ($meta['emp_name'] ?? ''), 1, 0, 'L');
    $pdf->Cell(0, 8, 'Date : ' . date('m.d.y'), 1, 1, 'L');

    $pdf->Cell(0, 8, 'Transfer Type: (check only one)', 'LTR', 1, 'L');
    $pdf->Cell(60, 8, '', 'L', 0);
    $pdf->Cell(50, 8, '[  ] Donation', 0, 0);
    $pdf->Cell(50, 8, '[  ] Relocate', 0, 0);
    $pdf->Cell(0, 8, '', 'R', 1);
    $pdf->Cell(60, 8, '', 'L', 0);
    $pdf->Cell(50, 8, '[  ] Reassignment', 0, 0);
    $pdf->Cell(50, 8, '[  ] Others (Specify)', 0, 0);
    $pdf->Cell(0, 8, '', 'R', 1);

    [$cQty, $cDate, $cProp, $cDesc, $cAmt, $cCond] = PTR_COL;
    $pdf->SetFont('dejavusans', 'B', 9);
    $pdf->Cell($cQty, 10, 'Qty', 1, 0, 'C');
    $pdf->Cell($cDate, 10, "Date Acq'd", 1, 0, 'C');
    $pdf->Cell($cProp, 10, 'Property No.', 1, 0, 'C');
    $pdf->Cell($cDesc, 10, 'Description', 1, 0, 'C');
    $pdf->Cell($cAmt, 10, 'Amount', 1, 0, 'C');
    $pdf->MultiCell($cCond, 10, "Condition\nof PPE", 1, 'C', false, 1);
    $pdf->SetFont('dejavusans', '', 9);
}

function ptr_cell_height(TCPDF $pdf, float $width, string $text): float {
    return $pdf->getStringHeight($width, "\n" . $text, false, true, '', 0);
}

function ptr_shorten_text(TCPDF $pdf, string $text, string $suffix, float $width, float $maxHeight): string {
    $text = trim($text);
    $suffix = trim($suffix);
    $withSuffix = function (string $value) use ($suffix): string {
        return $suffix === '' ? $value : trim($value) . "\n" . $suffix;
    };

    if (ptr_cell_height($pdf, $width, $withSuffix($text)) <= $maxHeight) {
        return $withSuffix($text);
    }

    $words = preg_split('/\s+/', $text);
    while (count($words) > 1) {
        array_pop($words);
        $candidate = $withSuffix(implode(' ', $words) . '...');
        if (ptr_cell_height($pdf, $width, $candidate) <= $maxHeight) {
            return $candidate;
        }
    }

    return $suffix === '' ? $text : $suffix;
}

function ptr_format_item(TCPDF $pdf, array $item): array {
    [$cQty, $cDate, $cProp, $cDesc, $cAmt, $cCond] = PTR_COL;

    $qty = (int)($item['qty'] ?? 1);
    $sn1 = trim((string)($item['serial_number'] ?? ''));
    $sn2 = trim((string)($item['serial_number_2'] ?? ''));
    $descBase = trim(($item['model'] ?? '') . ' ' . ($item['description'] ?? ''));

    $snSuffix = '';
    if ($qty === 1 && $sn1 !== '' && strtoupper($sn1) !== 'N/A') {
        $snSuffix = 'SN: ' . $sn1;
        if ($sn2 !== '' && strtoupper($sn2) !== 'N/A') {
            $snSuffix .= ' / ' . $sn2;
        }
    }

    $unitPrice = (float)($item['unit_value'] ?? 0);
    $totalValue = (float)($item['total_value'] ?? $unitPrice);
    $amount = $qty > 1
        ? 'Unit: ₱ ' . number_format($unitPrice, 2) . "\nTotal: ₱ " . number_format($totalValue, 2)
        : '₱ ' . number_format($unitPrice, 2);

    $cells = [
        (string)$qty,
        (string)($item['date_aquired'] ?? ''),
        collapsePropertyNumbers($item['par_numbers'] ?? [$item['p_par_number'] ?? '']),
        ptr_shorten_text($pdf, $descBase, $snSuffix, $cDesc, PTR_ROW_MAX_HEIGHT),
        $amount,
        (string)($item['unit_condition'] ?? ''),
    ];

    $heights = [
        ptr_cell_height($pdf, $cQty, $cells[0]),
        ptr_cell_height($pdf, $cDate, $cells[1]),
        ptr_cell_height($pdf, $cProp, $cells[2]),
        ptr_cell_height($pdf, $cDesc, $cells[3]),
        ptr_cell_height($pdf, $cAmt, $cells[4]),
        ptr_cell_height($pdf, $cCond, $cells[5]),
    ];

    return [
        'cells' => $cells,
        'height' => min(PTR_ROW_MAX_HEIGHT, max(PTR_ROW_MIN_HEIGHT, ceil(max($heights)))),
    ];
}

function ptr_row_fits(TCPDF $pdf, float $rowHeight, bool $needsFooter): bool {
    $bottomY = PTR_BOTTOM_Y - ($needsFooter ? PTR_FOOTER_HEIGHT : 0);
    return ($pdf->GetY() + $rowHeight) <= $bottomY;
}

function ptr_close_table(TCPDF $pdf): void {
    $y = $pdf->GetY();
    if ($y < PTR_BOTTOM_Y) {
        $pdf->Line(PTR_MARGIN, $y, PTR_PAGE_WIDTH - PTR_MARGIN, $y);
    }
}

function ptr_render_item_row(TCPDF $pdf, array $row): void {
    [$cQty, $cDate, $cProp, $cDesc, $cAmt, $cCond] = PTR_COL;
    $x = PTR_MARGIN;
    $y = $pdf->GetY();
    $h = $row['height'];
    $cells = $row['cells'];

    $pdf->MultiCell($cQty, $h, "\n" . $cells[0], 'LR', 'C', false, 0, $x, $y, true, 0, false, true, $h, 'T');
    $pdf->MultiCell($cDate, $h, "\n" . $cells[1], 'LR', 'C', false, 0, $x + $cQty, $y, true, 0, false, true, $h, 'T');
    $pdf->MultiCell($cProp, $h, "\n" . $cells[2], 'LR', 'C', false, 0, $x + $cQty + $cDate, $y, true, 0, false, true, $h, 'T');
    $pdf->MultiCell($cDesc, $h, "\n" . $cells[3], 'LR', 'C', false, 0, $x + $cQty + $cDate + $cProp, $y, true, 0, false, true, $h, 'T');
    $pdf->MultiCell($cAmt, $h, "\n" . $cells[4], 'LR', 'C', false, 0, $x + $cQty + $cDate + $cProp + $cDesc, $y, true, 0, false, true, $h, 'T');
    $pdf->MultiCell($cCond, $h, "\n" . $cells[5], 'LR', 'C', false, 1, $x + $cQty + $cDate + $cProp + $cDesc + $cAmt, $y, true, 0, false, true, $h, 'T');
    $pdf->SetXY(PTR_MARGIN, $y + $h);
}

function ptr_render_footer(TCPDF $pdf, array $meta): void {
    $pdf->SetFont('dejavusans', '', 9);
    $pdf->Cell(0, 8, 'Reason for Transfer: ' . ($meta['reason'] ?? ''), 'LTR', 1, 'L');
    for ($i = 0; $i < 3; $i++) {
        $pdf->Cell(0, 8, '', 'LR', 1, 'L');
    }

    $pdf->Cell(30, 8, '', 'TL', 0, 'L');
    $pdf->Cell(50, 8, 'Approved by:', 'T', 0, 'L');
    $pdf->Cell(60, 8, 'Released/Issued by:', 'T', 0, 'L');
    $pdf->Cell(50, 8, 'Received by:', 'T', 0, 'L');
    $pdf->Cell(0, 8, '', 'TR', 1, 'L');

    $pdf->Cell(30, 8, 'Signature :', 'L', 0, 'L');
    $pdf->Cell(50, 8, '', 'B', 0, 'L');
    $pdf->Cell(60, 8, '', 'B', 0, 'L');
    $pdf->Cell(50, 8, '', 'B', 0, 'L');
    $pdf->Cell(0, 8, '', 'R', 1, 'L');

    $pdf->Cell(30, 8, 'Printed Name :', 'L', 0, 'L');
    $pdf->Cell(50, 8, 'ATTY. ARVIN Q. TAPIA', 'B', 0, 'L');
    $pdf->Cell(60, 8, ($meta['previous_user'] ?? ''), 'B', 0, 'L');
    $pdf->Cell(50, 8, ($meta['emp_name'] ?? ''), 'B', 0, 'L');
    $pdf->Cell(0, 8, '', 'R', 1, 'L');

    $pdf->Cell(30, 8, 'Designation :', 'L', 0, 'L');
    $pdf->Cell(50, 8, 'OIC-General Services Office', 'B', 0, 'L');
    $pdf->Cell(60, 8, ($meta['previous_dept'] ?? ''), 'B', 0, 'L');
    $pdf->Cell(50, 8, ($meta['department_name'] ?? ''), 'B', 0, 'L');
    $pdf->Cell(0, 8, '', 'R', 1, 'L');

    $pdf->Cell(30, 8, 'Date :', 'L', 0, 'L');
    $pdf->Cell(50, 8, date('F d, Y'), 'B', 0, 'L');
    $pdf->Cell(60, 8, date('F d, Y'), 'B', 0, 'L');
    $pdf->Cell(50, 8, date('F d, Y'), 'B', 0, 'L');
    $pdf->Cell(0, 8, '', 'R', 1, 'L');

    $pdf->Cell(30, 8, '', 'LB', 0, 'L');
    $pdf->Cell(50, 8, '', 'B', 0, 'L');
    $pdf->Cell(60, 8, '', 'B', 0, 'L');
    $pdf->Cell(50, 8, '', 'B', 0, 'L');
    $pdf->Cell(0, 8, '', 'RB', 1, 'L');
}

function render_ptr_report(TCPDF $pdf, array $meta, array $items): void {
    ptr_render_page_header($pdf, $meta);

    foreach ($items as $item) {
        $row = ptr_format_item($pdf, $item);

        if (!ptr_row_fits($pdf, $row['height'], true)) {
            ptr_render_footer($pdf, $meta);
            ptr_render_page_header($pdf, $meta);
        }

        ptr_render_item_row($pdf, $row);
    }

    ptr_render_footer($pdf, $meta);
}

$refs = ptr_request_references();
$rows = gso_fetch_property_transfer_report_rows($conn, $refs);

$pdf = new TCPDF('P', 'mm', [PTR_PAGE_WIDTH, PTR_PAGE_HEIGHT]);
$pdf->setPrintHeader(false);
$pdf->SetMargins(PTR_MARGIN, PTR_MARGIN, PTR_MARGIN);
$pdf->SetAutoPageBreak(false, PTR_MARGIN);

if (count($rows) > 0) {
    foreach (groupAndSummarizePtrRows($rows) as $group) {
        render_ptr_report($pdf, $group['meta'], $group['items']);
    }
} else {
    render_ptr_report($pdf, [
        'department_name' => '',
        'previous_user' => '',
        'previous_dept' => '',
        'emp_name' => '',
        'ptr_number' => '',
        'reason' => '',
    ], [[
        'qty' => 1,
        'date_aquired' => '',
        'par_numbers' => [''],
        'p_par_number' => '',
        'model' => '',
        'description' => '',
        'serial_number' => '',
        'serial_number_2' => '',
        'unit_value' => 0,
        'total_value' => 0,
        'unit_condition' => '',
    ]]);
}

$pdf->Output('property_transfer_report.pdf', 'I');
?>
