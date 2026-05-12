<?php

// TCPDF requires a clean output buffer (no whitespace/HTML/JS) before sending the PDF.
// Some shared includes (e.g., session inactivity scripts) echo content, so we avoid them here.
ob_start();

require_once __DIR__ . '/../tcpdf/tcpdf.php';

include_once('../config/session.php');

// Pull SQL/data-fetching logic from auth.php (library-only mode: no request handlers).
if (!defined('GSO_AUTH_LIB_ONLY')) {
    define('GSO_AUTH_LIB_ONLY', true);
}
require_once __DIR__ . '/../auth/auth.php';

if (!isset($_SESSION['alogin'])) {
    while (ob_get_level() > 0) { @ob_end_clean(); }
    header('Location:../index.php');
    exit();
}

// Optional DB connection (kept for compatibility with your existing usage)
$dbPath = __DIR__ . '/../database/databaseConnection.php';
if (file_exists($dbPath)) {
    require_once $dbPath;
}

function array_get($arr, $key, $default = '')
{
    if (!is_array($arr) || !array_key_exists($key, $arr)) {
        return $default;
    }
    return $arr[$key];
}

function get_str_param($key, $default = '')
{
    if (!isset($_GET[$key])) {
        return $default;
    }
    $value = trim((string)$_GET[$key]);
    return $value === '' ? $default : $value;
}

function scale_widths($widthsMm, $targetTotalMm)
{
    $sum = 0.0;
    foreach ((array)$widthsMm as $w) {
        $sum += (float)$w;
    }
    if ($sum <= 0.0) {
        return $widthsMm;
    }
    $scale = $targetTotalMm / $sum;
    return array_map(function ($w) use ($scale) {
        return round(((float)$w) * $scale, 2);
    }, (array)$widthsMm);
}

function concat_role($position, $roleLabel)
{
    $p = trim((string)$position);
    $r = trim((string)$roleLabel);
    if ($p === '') {
        return $r;
    }
    if ($r === '') {
        return $p;
    }
    return $p . ' / ' . $r;
}

function normalize_fund_label($fund)
{
    $f = strtoupper(trim((string)$fund));
    if ($f === '') {
        return '';
    }
    // Treat common variants as the same fund.
    if (strpos($f, 'SEF') !== false || strpos($f, 'SPECIAL EDUCATION') !== false) {
        return 'SEF';
    }
    if (strpos($f, 'GF') !== false || strpos($f, 'GENERAL') !== false) {
        return 'GENERAL FUND';
    }
    return $f;
}

function infer_doc_type($category, $propertyNo)
{
    $cat = strtoupper(trim((string)$category));
    if ($cat === 'ICS') {
        return 'ICS';
    }
    // Fallback: infer from printed property number.
    $s = strtoupper(trim((string)$propertyNo));
    if ($s === '') {
        return 'PAR';
    }
    // Common formats: "ICS-...", "ICS ...", or embedded "ICS" token.
    if (strpos($s, 'ICS') === 0 || preg_match('/\bICS\b/', $s)) {
        return 'ICS';
    }
    return 'PAR';
}

final class IirupAppendix74Pdf extends TCPDF
{
    /** @var float[] */
    private $colW = array();

    private function textBox(
        $x,
        $y,
        $w,
        $h,
        $text,
        $align = 'L',
        $style = '',
        $fontSize = 9,
        $border = 0,
        $valign = 'M',
        $fit = true
    ) {
        $this->SetFont('helvetica', $style, $fontSize);
        // NOTE: $fit=true lets TCPDF reduce font size to fit the cell height.
        // For signature blocks we sometimes disable it to keep consistent font sizing.
        $this->MultiCell($w, $h, $text, $border, $align, false, 0, $x, $y, true, 0, false, true, $h, $valign, (bool)$fit);
    }

    private function cellBox(
        $x,
        $y,
        $w,
        $h,
        $text,
        $align = 'C',
        $style = '',
        $fontSize = 8,
        $valign = 'M'
    ) {
        $this->Rect($x, $y, $w, $h);
        $this->textBox($x + 0.6, $y + 0.4, $w - 1.2, $h - 0.8, $text, $align, $style, $fontSize, 0, $valign);
    }

    private function yLine($x1, $y, $x2)
    {
        $this->Line($x1, $y, $x2, $y);
    }

    private function xLine($x, $y1, $y2)
    {
        $this->Line($x, $y1, $x, $y2);
    }

    private function logoPath()
    {
        $path = __DIR__ . '/logo.jpg';
        return file_exists($path) ? $path : null;
    }

    /**
     * @param array{entity_name:string,fund_cluster:string,as_of:string,name_officer:string,designation:string,station:string} $meta
     * @param array<int,array{date_acquired?:string,particulars?:string,property_no?:string,qty?:string,unit_cost?:string,total_cost?:string,remarks?:string}> $items
     */
    public function render($meta, $items)
    {
        $allItems = (array)$items;
        $chunks = array_chunk($allItems, 9);
        if (count($chunks) === 0) {
            $chunks = [[]];
        }
        $pageCount = count($chunks);

        for ($pageIndex = 0; $pageIndex < $pageCount; $pageIndex++) {
            $items = $chunks[$pageIndex];
            $isLastPage = ($pageIndex === ($pageCount - 1));

            $this->AddPage();

        $this->SetLineWidth(0.2);

        $x0 = (float)$this->lMargin;
        $y0 = (float)$this->tMargin;
        $usableW = (float)($this->getPageWidth() - $this->lMargin - $this->rMargin);
        $usableH = (float)($this->getPageHeight() - $this->tMargin - $this->bMargin);

        // --- Top header
        $this->textBox($x0, $y0 - 1, $usableW, 6, 'Appendix 74', 'R', '', 8);

        $logo = $this->logoPath();
        if ($logo) {
            $logoW = 18;
            $logoX = $x0 + ($usableW / 2) - ($logoW / 2);
            $this->Image($logo, $logoX, $y0 + 1, $logoW, $logoW, '', '', 'T', false, 300, '', false, false, 0, false, false, false);
        }

        $this->textBox(
            $x0,
            $y0 + 20,
            $usableW,
            6,
            "INVENTORY AND INSPECTION REPORT OF UNSERVICEABLE PROPERTY",
            'C',
            'B',
            11
        );
        // "As of" label normal, year/value bold (centered as a combined string)
        $asOfVal = (string)array_get($meta, 'as_of', date('Y'));
        $asLabel = 'As of ';
        $this->SetFont('helvetica', '', 9);
        $wLabel = $this->GetStringWidth($asLabel);
        $this->SetFont('helvetica', 'B', 9);
        $wVal = $this->GetStringWidth($asOfVal);
        $xStart = $x0 + (($usableW - ($wLabel + $wVal)) / 2);
        $this->textBox($xStart, $y0 + 26, $wLabel + 1, 5, $asLabel, 'L', '', 9);
        $this->textBox($xStart + $wLabel, $y0 + 26, $wVal + 1, 5, $asOfVal, 'L', 'B', 9);

        $this->SetFont('helvetica', '', 9);
        $entityW = $usableW * 0.62;
        $entityLabelW = 28;
        $this->textBox($x0, $y0 + 34, $entityLabelW, 5, 'Entity Name :', 'L', '', 9);
        $this->textBox($x0 + $entityLabelW, $y0 + 34, $entityW - $entityLabelW, 5, array_get($meta, 'entity_name', ''), 'L', 'B', 9);

        $fundX = $x0 + ($usableW * 0.62);
        $fundW = $usableW * 0.38;
        $fundY = $y0 + 34;
        $this->textBox($fundX, $fundY, 28, 5, 'Fund Cluster:', 'L', '', 9);
        $this->yLine($fundX + 23, $fundY + 4.2, $fundX + $fundW);
        if (trim((string)array_get($meta, 'fund_cluster', '')) !== '') {
            $this->textBox($fundX + 28, $fundY - 0.2, $fundW - 28, 5, array_get($meta, 'fund_cluster', ''), 'C', 'B', 9);
        }

        // Accountable Officer / Designation / Station
        $rowY = $y0 + 42;
        $colGap = 10;
        $segW = ($usableW - (2 * $colGap)) / 3;
        $lineY = $rowY + 4;
        $this->yLine($x0, $lineY, $x0 + $segW);
        $this->yLine($x0 + $segW + $colGap, $lineY, $x0 + (2 * $segW) + $colGap);
        $this->yLine($x0 + (2 * $segW) + (2 * $colGap), $lineY, $x0 + (3 * $segW) + (2 * $colGap));
        $this->textBox($x0, $rowY - 1, $segW, 5, array_get($meta, 'name_officer', ''), 'C', 'B', 9);
        $this->textBox($x0 + $segW + $colGap, $rowY - 1, $segW, 5, array_get($meta, 'designation', ''), 'C', 'B', 9);
        $this->textBox($x0 + (2 * $segW) + (2 * $colGap), $rowY - 1, $segW, 5, array_get($meta, 'station', ''), 'C', 'B', 9);
        $this->textBox($x0, $rowY + 5, $segW, 4, '(Name of Accountable Officer)', 'C', 'I', 8);
        $this->textBox($x0 + $segW + $colGap, $rowY + 5, $segW, 4, '(Designation)', 'C', 'I', 8);
        $this->textBox($x0 + (2 * $segW) + (2 * $colGap), $rowY + 5, $segW, 4, '(Station)', 'C', 'I', 8);

        // --- Table geometry
        $tableTop = $y0 + 54;
        $header1H = 8;
        $header2H = 6;
        $header3H = 6;
        $indexH = 6;

        $bodyRowH = 7;
        $bodyRows = 9;
        $bodyH = $bodyRowH * $bodyRows;

        // Column widths (mm) tuned to match Appendix 74 proportions, then scaled to fit page.
        $baseWidths = [
            18, // (1) Date Acquired
            60, // (2) Particulars/Articles
            18, // (3) Property No.
            10, // (4) Qty
            18, // (5) Unit Cost
            18, // (6) Total Cost
            22, // (7) Accumulated Depreciation
            22, // (8) Accumulated Impairment Losses
            18, // (9) Carrying Amount
            18, // (10) Remarks
            12, // (11) Sale
            12, // (12) Transfer
            12, // (13) Destruction
            16, // (14) Others (Specify)
            12, // (15) Total
            18, // (16) Appraised Value
            14, // (17) OR No.
            16, // (18) Amount
        ];
        $this->colW = scale_widths($baseWidths, $usableW);

        $x = $x0;
        $y = $tableTop;

        $headerH = $header1H + $header2H + $header3H;

        // Rowspan cells (columns 1-10)
        $headersLeft = [
            "Date\nAcquired",
            "Particulars/ Articles",
            "Property\nNo.",
            "Qty",
            "Unit Cost",
            "Total Cost",
            "Accumulated\nDepreciation",
            "Accumulated\nImpairment\nLosses",
            "Carrying\nAmount",
            "Remarks",
        ];
        for ($i = 0; $i < 10; $i++) {
            $this->cellBox($x, $y, $this->colW[$i], $headerH, $headersLeft[$i], 'C', 'B', 7);
            $x += $this->colW[$i];
        }

        // Group headers (cols 11-18)
        $groupX = $x;
        $groupW = 0.0;
        for ($i = 10; $i < 18; $i++) {
            $groupW += $this->colW[$i];
        }
        $this->cellBox($groupX, $y, $groupW, $header1H, 'INSPECTION and DISPOSAL', 'C', 'B', 8);

        // Second header row inside group
        $y2 = $y + $header1H;
        $disposalW = 0.0;
        for ($i = 10; $i <= 14; $i++) {
            $disposalW += $this->colW[$i];
        }
        $this->cellBox($groupX, $y2, $disposalW, $header2H, 'DISPOSAL', 'C', 'B', 7);

        $appraisedX = $groupX + $disposalW;
        $this->cellBox($appraisedX, $y2, $this->colW[15], $header2H + $header3H, "Appraised\nValue", 'C', 'B', 7);

        $recordX = $appraisedX + $this->colW[15];
        $recordW = $this->colW[16] + $this->colW[17];
        $this->cellBox($recordX, $y2, $recordW, $header2H, 'RECORD OF SALES', 'C', 'B', 7);

        // Third header row inside group
        $y3 = $y2 + $header2H;
        $subHeaders = [
            'Sale',
            'Transfer',
            'Destruction',
            "Others\n(Specify)",
            'Total',
        ];
        $sx = $groupX;
        for ($i = 10; $i <= 14; $i++) {
            $this->cellBox($sx, $y3, $this->colW[$i], $header3H, $subHeaders[$i - 10], 'C', 'B', 7);
            $sx += $this->colW[$i];
        }
        $this->cellBox($recordX, $y3, $this->colW[16], $header3H, "OR No.", 'C', 'B', 7);
        $this->cellBox($recordX + $this->colW[16], $y3, $this->colW[17], $header3H, 'Amount', 'C', 'B', 7);

        // Index row (1)-(18)
        $indexY = $y + $headerH;
        $ix = $x0;
        for ($i = 0; $i < 18; $i++) {
            $this->cellBox($ix, $indexY, $this->colW[$i], $indexH, '(' . ($i + 1) . ')', 'C', 'I', 7);
            $ix += $this->colW[$i];
        }

        // Body grid (aligned to header)
        $bodyY = $indexY + $indexH;
        $tableW = array_sum($this->colW);
        $this->Rect($x0, $bodyY, $tableW, $bodyH);

        // Vertical lines
        $vx = $x0;
        for ($i = 0; $i < 18; $i++) {
            $vx += $this->colW[$i];
            if ($i < 17) {
                $this->xLine($vx, $bodyY, $bodyY + $bodyH);
            }
        }
        // Horizontal lines
        for ($r = 1; $r < $bodyRows; $r++) {
            $this->yLine($x0, $bodyY + ($r * $bodyRowH), $x0 + $tableW);
        }

        // Fill items (only the commonly-populated columns; others remain blank like the form)
        $maxItems = min(count($items), $bodyRows);
        for ($r = 0; $r < $maxItems; $r++) {
            $row = $items[$r];
            $rowTop = $bodyY + ($r * $bodyRowH);

            $unitCostRaw = array_get($row, 'unit_cost', '');
            $unitCostText = '';
            if ($unitCostRaw !== '' && is_numeric($unitCostRaw)) {
                $unitCostText = number_format((float)$unitCostRaw, 2);
            } else {
                $unitCostText = (string)$unitCostRaw;
            }

            $totalCostRaw = array_get($row, 'total_cost', '');
            $totalCostText = '';
            if ($totalCostRaw !== '' && is_numeric($totalCostRaw)) {
                $totalCostText = number_format((float)$totalCostRaw, 2);
            } else {
                $totalCostText = (string)$totalCostRaw;
            }

            $vals = [
                array_get($row, 'date_acquired', ''),
                array_get($row, 'particulars', ''),
                array_get($row, 'property_no', ''),
                array_get($row, 'qty', ''),
                $unitCostText,
                $totalCostText,
                '', '', '',
                array_get($row, 'remarks', ''),
            ];

            $cx = $x0;
            for ($c = 0; $c < 10; $c++) {
                $align = $c === 1 ? 'L' : 'C';
                $this->textBox($cx + 0.6, $rowTop + 0.6, $this->colW[$c] - 1.2, $bodyRowH - 1.2, (string)$vals[$c], $align, '', 8, 0, 'M');
                $cx += $this->colW[$c];
            }

            // Disposal columns: put Qty under Sale, and mirror it in Total.
            $qtyText = trim((string)array_get($row, 'qty', ''));
            if ($qtyText !== '') {
                // (11) Sale (index 10)
                $saleX = $cx;
                $this->textBox($saleX + 0.6, $rowTop + 0.6, $this->colW[10] - 1.2, $bodyRowH - 1.2, $qtyText, 'C', '', 8, 0, 'M');
            }

            // Column (16) Appraised Value (index 15)
            $appraiseX = $cx;
            for ($c = 10; $c <= 14; $c++) {
                $appraiseX += $this->colW[$c];
            }
            $appraiseRaw = array_get($row, 'appraise_value', '');
            $appraiseText = '';
            if ($appraiseRaw !== '' && is_numeric($appraiseRaw)) {
                $appraiseText = number_format((float)$appraiseRaw, 2);
            } else {
                $appraiseText = (string)$appraiseRaw;
            }
            $this->textBox($appraiseX + 0.6, $rowTop + 0.6, $this->colW[15] - 1.2, $bodyRowH - 1.2, $appraiseText, 'C', '', 8, 0, 'M');
        }

        // --- Signature / certification blocks (render on every page)
            // Use the table's bottom border as the footer's top border to avoid a double/overlapping line.
            $sigTop = $bodyY + $bodyH;
            $sigH = $usableH - ($sigTop - $y0);
            if ($sigH < 38) {
                // If the page is too tight (unexpected margins), still render but keep it readable.
                $sigH = 38;
            }

        // Align footer width to the actual table width (guards against rounding drift).
        $footerW = $tableW;

        // Footer split (left: request/approval, right: certifications). Tweaked to better match the printed form.
        $footerLeftRatio = 0.45;
        $leftW = round($footerW * $footerLeftRatio, 2);
        $rightW = $footerW - $leftW;
        $leftX = $x0;
        $rightX = $x0 + $leftW;

        // Footer box without top border (top is the table's bottom border)
        $footerRightX = $x0 + $footerW;
        $this->xLine($x0, $sigTop, $sigTop + $sigH);
        $this->xLine($footerRightX, $sigTop, $sigTop + $sigH);
        $this->yLine($x0, $sigTop + $sigH, $footerRightX);
        $this->xLine($rightX, $sigTop, $sigTop + $sigH);

        // Left block text
        $this->textBox(
            $leftX + 2,
            $sigTop + 3,
            $leftW - 4,
            16,
            'I HEREBY request inspection and disposition, pursuant to Section 79 of PD 1445, of the property enumerated above.',
            'L',
            '',
            8,
            0,
            'T'
        );

        $reqY = $sigTop + 22;
        $this->textBox($leftX + 2, $reqY, ($leftW / 2) - 4, 4, 'Requested by:', 'L', '', 8);
        $this->textBox($leftX + ($leftW / 2) + 2, $reqY, ($leftW / 2) - 4, 4, 'Approved by:', 'L', '', 8);

        // Nudge the signature lines/titles upward to match the printed form.
        $leftSigBottomOffset = 22;
        $sigLineY = $sigTop + $sigH - $leftSigBottomOffset;
        $leftMid = $leftX + ($leftW / 2);
        $this->yLine($leftX + 12, $sigLineY, $leftMid - 12);
        $this->yLine($leftMid + 12, $sigLineY, $leftX + $leftW - 12);
        $reqName = trim((string)array_get($meta, 'requested_by_name', ''));
        $reqPos = trim((string)array_get($meta, 'requested_by_position', ''));
        $apprName = trim((string)array_get($meta, 'approved_by_name', ''));
        $apprPos = trim((string)array_get($meta, 'approved_by_position', ''));
        // Name should be on top of the underline; position should be below the underline.
        $this->textBox($leftX + 2, $sigLineY - 4.6, ($leftW / 2) - 4, 4, $reqName, 'C', 'B', 8, 0, 'T');
        $this->textBox($leftX + 2, $sigLineY + 0.6, ($leftW / 2) - 4, 6, $reqPos, 'C', '', 8, 0, 'T');

        $this->textBox($leftX + ($leftW / 2) + 2, $sigLineY - 4.6, ($leftW / 2) - 4, 4, $apprName, 'C', 'B', 8, 0, 'T');
        $this->textBox($leftX + ($leftW / 2) + 2, $sigLineY + 0.6, ($leftW / 2) - 4, 6, $apprPos, 'C', '', 8, 0, 'T');

        // Right block text
        // The official Appendix 74 form splits the right-side footer into two columns:
        // - Left: inspection certification + disposal committee / inspection officers
        // - Right: witness certification + witness signature
        $rightColW = $rightW / 2;
        $inspectX = $rightX;
        $witnessX = $rightX + $rightColW;
        // No vertical divider between these two right-side sections (matches the form).

        $this->textBox(
            $inspectX + 2,
            $sigTop + 3,
            $rightColW - 4,
            18,
            'I CERTIFY that I have inspected each and every article enumerated in this report, and that the disposition made thereof was, in my judgement, the best for the public interest.',
            'L',
            '',
            8,
            0,
            'T'
        );

        // Chairperson: name above underline, position below underline.
        $chairTopY = $sigTop + 22;
        $chairLineY = $chairTopY + 5;
        $this->yLine($inspectX + 10, $chairLineY, $witnessX - 10);
        $chairName = trim((string)array_get($meta, 'chairperson_name', ''));
        $chairPos = trim((string)array_get($meta, 'chairperson_position', 'Chairperson, Disposal Committee'));
        $this->textBox($inspectX + 2, $chairLineY - 4.6, $rightColW - 4, 4.5, $chairName, 'C', 'B', 8, 0, 'T');
        $this->textBox($inspectX + 2, $chairLineY + 0.8, $rightColW - 4, 6.5, $chairPos, 'C', '', 8, 0, 'T');

        $inspectors = (array)array_get($meta, 'inspectors', []);
        $inspectors = array_values(array_filter($inspectors, function ($o) {
            if (!is_array($o)) {
                return false;
            }
            $nm = trim((string)array_get($o, 'name', ''));
            $pos = trim((string)array_get($o, 'position', ''));
            return !($nm === '' && $pos === '');
        }));
        $inspectors = array_slice($inspectors, 0, 5);

        // Requirement: if there is a 3rd/4th inspection officer, do not add new rows below.
        // Keep only the first two on the left inspection column, then place the 3rd and 4th
        // into the two existing blank signature slots in the opposite (witness) column.
        $leftInspectors = array_slice($inspectors, 0, 2);
        $extraInspectors = array_slice($inspectors, 2, 2); // 3rd/4th only

        $leftCount = count($leftInspectors);
        if ($leftCount <= 1) {
            $officerStartY = $sigTop + 42;
            $officerRowGap = 12.0;
        } else {
            $officerStartY = $sigTop + 40;
            $officerRowGap = 12.0;
        }

        $insLineYs = [];
        for ($i = 0; $i < $leftCount; $i++) {
            $rowY = $officerStartY + ($i * $officerRowGap);
            $nm = trim((string)array_get($leftInspectors[$i], 'name', ''));
            $pos = trim((string)array_get($leftInspectors[$i], 'position', 'Inspection Officer'));
            $pos = concat_role($pos, 'Inspection Officer');

            $lineY = $rowY + 2.6;
            $insLineYs[] = $lineY;
            $this->yLine($inspectX + 10, $lineY, $witnessX - 10);
            $this->textBox($inspectX + 2, $lineY - 3.8, $rightColW - 4, 4.2, $nm, 'C', 'B', 8, 0, 'T', false);
            $this->textBox($inspectX + 2, $lineY + 0.6, $rightColW - 4, 4.2, $pos, 'C', '', 8, 0, 'T', false);
        }

        // Move the witness signature line/caption slightly upward to match the printed form.
        $witSigBottomOffset = 40;
        $witSigLineY = $sigTop + $sigH - $witSigBottomOffset;
        $witTextY = $sigTop + 3;
        $witTextH = 18;

        $this->textBox(
            $witnessX + 2,
            $witTextY,
            $rightColW - 4,
            $witTextH,
            'I CERTIFY that I have witnessed the disposition of the articles enumerated on this report this ________ day of ______________',
            'L',
            '',
            8,
            0,
            'T'
        );

        $this->yLine($witnessX + 10, $witSigLineY, $rightX + $rightW - 10);
        $witName = trim((string)array_get($meta, 'witness_name', ''));
        $witPos = trim((string)array_get($meta, 'witness_position', ''));
        $witPos = concat_role($witPos, 'Witness');
        // Name above underline, position below underline.
        $this->textBox($witnessX + 2, $witSigLineY - 4.6, $rightColW - 4, 4, $witName, 'C', 'B', 8, 0, 'T');
        $this->textBox($witnessX + 2, $witSigLineY + 0.6, $rightColW - 4, 4, $witPos, 'C', '', 8, 0, 'T');

        // Two fixed blank signature slots in the opposite column.
        // If there are 3rd/4th inspectors, place them here; otherwise keep as blank "Inspection Officer" slots.
        // Requirement: the 4th inspector slot must align with the 2nd inspector slot.
        if (isset($insLineYs) && is_array($insLineYs) && count($insLineYs) >= 2) {
            $roleLineYs = [$insLineYs[0], $insLineYs[1]];
        } else {
            $witnessRolesBottomOffset = 23;
            $bottomY = $sigTop + $sigH - $witnessRolesBottomOffset;
            $roleSlotGap = 9.0;
            $roleDown = 7.5;
            $roleLineYs = [($bottomY - $roleSlotGap + $roleDown), ($bottomY + $roleDown)];
        }

        for ($i = 0; $i < 2; $i++) {
            $lineY = $roleLineYs[$i];
            $this->yLine($witnessX + 6, $lineY, $rightX + $rightW - 6);

            if (isset($extraInspectors[$i]) && is_array($extraInspectors[$i])) {
                $nm = trim((string)array_get($extraInspectors[$i], 'name', ''));
                $pos = trim((string)array_get($extraInspectors[$i], 'position', 'Inspection Officer'));
                $pos = concat_role($pos, 'Inspection Officer');
                $this->textBox($witnessX + 2, $lineY - 3.8, $rightColW - 4, 4.2, $nm, 'C', 'B', 8, 0, 'T', false);
                $this->textBox($witnessX + 2, $lineY + 0.6, $rightColW - 4, 4.2, $pos, 'C', '', 8, 0, 'T', false);
            } else {
                $this->textBox($witnessX + 2, $lineY + 1, $rightColW - 4, 6, 'Inspection Officer', 'C', '', 8, 0, 'T');
            }
        }
    }
    }
}

$ref = get_str_param('ref', '');
$code = get_str_param('code', '');
if ($ref === '' || $code === '') {
    header('Content-Type: text/plain');
    echo "Missing required parameters. Expected ?ref=YYYYMM-DXXX&code=ACCOUNT_CODE";
    exit;
}

$res = fetch_iirup_appendix74_print_data($conn, $ref, $code);
$meta = (array)($res['meta'] ?? []);
$items = (array)($res['items'] ?? []);

if (count($items) === 0) {
    header('Content-Type: text/plain');
    echo "No IIRUP items found for ref={$ref} and account code={$code}.";
    exit;
}

$pdf = new IirupAppendix74Pdf('L', 'mm', 'LEGAL', true, 'UTF-8', false);
$pdf->SetCreator(PDF_CREATOR);
$pdf->SetAuthor('Parañaque GSO');
$pdf->SetTitle('Inventory and Inspection Report of Unserviceable Property (Appendix 74)');
$pdf->SetSubject('IIRUP Appendix 74');

$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);
$pdf->SetMargins(8, 8, 8);
$pdf->SetAutoPageBreak(false);

// Render in fixed category sequence:
// GENERAL FUND + ACCOUNT CODE (PAR), GENERAL FUND + ACCOUNT CODE (ICS), SEF + ACCOUNT CODE (PAR), SEF + ACCOUNT CODE (ICS)
$groups = [];
foreach ($items as $it) {
    $fundLabel = normalize_fund_label(array_get($it, 'fund', ''));
    if ($fundLabel === '') { $fundLabel = 'GENERAL FUND'; }
    $docType = infer_doc_type(array_get($it, 'category', ''), array_get($it, 'property_no', ''));
    $key = $fundLabel . '|' . $docType;
    if (!isset($groups[$key])) { $groups[$key] = []; }
    $groups[$key][] = $it;
}

$categoryOrder = [
    // Requirement: ICS pages must be last.
    'GENERAL FUND|PAR' => 1,
    'SEF|PAR' => 2,
    'GENERAL FUND|ICS' => 3,
    'SEF|ICS' => 4,
];
uksort($groups, function ($a, $b) use ($categoryOrder) {
    $ia = $categoryOrder[$a] ?? 999;
    $ib = $categoryOrder[$b] ?? 999;
    if ($ia === $ib) { return strcmp((string)$a, (string)$b); }
    return $ia < $ib ? -1 : 1;
});

foreach ($groups as $categoryKey => $fundItems) {
    [$fundLabel, $docType] = array_pad(explode('|', (string)$categoryKey, 2), 2, '');
    $meta2 = $meta;
    $meta2['fund_cluster'] = (trim((string)$fundLabel) !== '')
        ? ($fundLabel . ' - ' . $code . ' (' . ($docType !== '' ? $docType : 'PAR') . ')')
        : ($code . ' (' . ($docType !== '' ? $docType : 'PAR') . ')');
    $pdf->render($meta2, $fundItems);
}

// Ensure nothing has been echoed before streaming the PDF.
while (ob_get_level() > 0) { @ob_end_clean(); }

// Add fund/type label(s) to the filename for clarity.
$fundKeyMap = ['GENERAL FUND' => 'GF', 'SEF' => 'SEF'];
$fundSuffixParts = [];
foreach (array_keys($groups) as $categoryKey) {
    [$fundLabel, $docType] = array_pad(explode('|', (string)$categoryKey, 2), 2, '');
    $fundKey = $fundKeyMap[strtoupper(trim((string)$fundLabel))] ?? preg_replace('/[^A-Z0-9]+/', '', strtoupper((string)$fundLabel));
    $docKey = preg_replace('/[^A-Z0-9]+/', '', strtoupper((string)$docType));
    $fundSuffixParts[] = trim($fundKey . ($docKey !== '' ? ('-' . $docKey) : ''));
}
$fundSuffixParts = array_values(array_filter($fundSuffixParts, function ($v) { return trim((string)$v) !== ''; }));
$fundSuffix = count($fundSuffixParts) ? ('-' . implode('-', $fundSuffixParts)) : '';

$safeCode = preg_replace('/[^A-Za-z0-9\-_.]/', '_', $code);
$pdf->Output('IIRUP-Appendix-74-' . $safeCode . $fundSuffix . '.pdf', 'I');
