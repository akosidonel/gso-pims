<?php
// Include database connection and TCPDF library (absolute paths)
require_once __DIR__ . '/../database/databaseConnection.php';
require_once __DIR__ . '/../tcpdf/tcpdf.php';
require_once __DIR__ . '/../include/summarize_print_rows.php';

  // Collect inputs: either a single batch reference_number or CSV list via refs
  $batchRef = isset($_GET['reference_number']) ? trim($_GET['reference_number']) : '';
  $refsCsv  = isset($_GET['refs']) ? trim($_GET['refs']) : '';

  // Fetch rows matching the reference(s)
  $rows = [];
  if ($refsCsv !== '') {
    $refs = array_filter(array_map('trim', explode(',', $refsCsv)));
    if (count($refs) > 0) {
      $placeholders = implode(',', array_fill(0, count($refs), '?'));
    $sql = "SELECT  i.par_number,
      i.ptr_number,
          i.reference_number,
          i.new_dept,
          i.reason,
          i.previous_user,
          i.previous_dept,
          i.new_user,
          i.unit_condition,
          COALESCE(pg.par_number, ps.property_number) AS p_par_number,
          COALESCE(pg.model, ps.model) AS model,
          COALESCE(pg.serial_number, ps.serial_number) AS serial_number,
          COALESCE(pg.serial_number_2, ps.serial_number_2) AS serial_number_2,
          COALESCE(pg.unit_value, ps.unit_value) AS unit_value,
          COALESCE(pg.description, ps.description) AS description,
          COALESCE(pg.date_aquired, ps.date_aquired) AS date_aquired,
          e.emp_id,
          e.emp_name,
          d.department_name,
          d.department_code
        FROM items_user_history AS i
        LEFT JOIN par_gen_fund AS pg ON i.par_number = pg.par_number
        LEFT JOIN property_sef AS ps ON i.par_number = ps.property_number
        JOIN employee AS e ON i.new_user = e.emp_id
        JOIN department AS d ON i.new_dept = d.department_code
        WHERE i.reference_number IN ($placeholders)";
      $stmt = $conn->prepare($sql);
      if ($stmt) {
        $types = str_repeat('s', count($refs));
        $stmt->bind_param($types, ...$refs);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($r = $res->fetch_assoc()) { $rows[] = $r; }
        $stmt->close();
      }
    }
  } elseif ($batchRef !== '') {
  $sql = "SELECT  i.par_number,
          i.ptr_number,
            i.reference_number,
            i.new_dept,
            i.reason,
            i.previous_user,
            i.previous_dept,
            i.new_user,
            i.unit_condition,
            COALESCE(pg.par_number, ps.property_number) AS p_par_number,
            COALESCE(pg.model, ps.model) AS model,
            COALESCE(pg.serial_number, ps.serial_number) AS serial_number,
            COALESCE(pg.serial_number_2, ps.serial_number_2) AS serial_number_2,
            COALESCE(pg.unit_value, ps.unit_value) AS unit_value,
            COALESCE(pg.description, ps.description) AS description,
            COALESCE(pg.date_aquired, ps.date_aquired) AS date_aquired,
            e.emp_id,
            e.emp_name,
            d.department_name,
            d.department_code
        FROM items_user_history AS i
        LEFT JOIN par_gen_fund AS pg ON i.par_number = pg.par_number
        LEFT JOIN property_sef AS ps ON i.par_number = ps.property_number
        JOIN employee AS e ON i.new_user = e.emp_id
        JOIN department AS d ON i.new_dept = d.department_code
        WHERE i.reference_number = ?";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
      $stmt->bind_param('s', $batchRef);
      $stmt->execute();
      $res = $stmt->get_result();
      while ($r = $res->fetch_assoc()) { $rows[] = $r; }
      $stmt->close();
    }
  }

  // Set to legal size (216mm x 356mm)
  $pdf = new TCPDF('P', 'mm', [216, 356]);
  $pdf->setPrintHeader(false);
  $pdf->SetMargins(10, 10, 10);

  // Group rows by recipient (emp+dept), then summarize identical items within each group.
  // Items with no serial number and the same model+description are merged into one row with qty.
  function groupAndSummarizePtrRows(array $rows): array {
    $buckets = [];
    $order   = [];
    foreach ($rows as $r) {
      $key = sha1(strtoupper(trim($r['emp_name'] ?? '')) . '|' . strtoupper(trim($r['department_code'] ?? '')));
      if (!isset($buckets[$key])) {
        $buckets[$key] = ['meta' => $r, 'items' => []];
        $order[] = $key;
      }
      $buckets[$key]['items'][] = $r;
    }
    foreach ($order as $key) {
      $individual = [];
      $grouped    = [];
      foreach ($buckets[$key]['items'] as $row) {
        $sn1 = trim((string)($row['serial_number']   ?? ''));
        $sn2 = trim((string)($row['serial_number_2'] ?? ''));
        $hasSerial = ($sn1 !== '' && strtoupper($sn1) !== 'N/A')
                  || ($sn2 !== '' && strtoupper($sn2) !== 'N/A');
        $row['qty']         = 1;
        $row['total_value'] = (float)($row['unit_value'] ?? 0);
        $row['par_numbers'] = [$row['p_par_number'] ?? ''];
        if ($hasSerial) {
          $individual[] = $row;
        } else {
          $gkey = strtoupper(trim($row['model'] ?? '')) . '|' . strtoupper(trim($row['description'] ?? ''));
          if (!isset($grouped[$gkey])) {
            $grouped[$gkey] = $row;
          } else {
            $grouped[$gkey]['qty']++;
            $grouped[$gkey]['total_value'] += (float)($row['unit_value'] ?? 0);
            $grouped[$gkey]['par_numbers'][] = $row['p_par_number'] ?? '';
          }
        }
      }
      $buckets[$key]['items'] = array_merge($individual, array_values($grouped));
    }
    return array_map(function($k) use ($buckets) { return $buckets[$k]; }, $order);
  }

  // Column widths (usable width = 216 - 10 - 10 = 196mm)
  // Qty:12 | Date Acq'd:25 | Property No.:40 | Description:50 | Amount:35 | Condition:34
  define('PTR_COL', [12, 25, 40, 50, 35, 34]);

  function render_ptr_page($pdf, $meta, array $items) {
    $pdf->AddPage();
    $pdf->SetY(10);
    $pdf->SetDrawColor(255, 255, 255);
    $pdf->SetFont('dejavusans', 'B', 13);

    // Logo
    $logoPath = __DIR__ . '/logo.jpg';
    if (file_exists($logoPath)) {
      $logoWidth = 20; $logoHeight = 20; $pageWidth = 216; $margin = 10;
      $centerX = $margin + (($pageWidth - 2 * $margin) - $logoWidth) / 2;
      $pdf->Image($logoPath, $centerX, 10, $logoWidth, $logoHeight, '', '', '', false, 300, '', false, false, 0);
      $pdf->SetY(10 + $logoHeight + 2);
    }

    // Title
    $pdf->Cell(0, 7, 'PROPERTY TRANSFER REPORT', 0, 2, 'C');
    $pdf->SetDrawColor(0, 0, 0);
    $pdf->Ln(2);

    // Entity Name / Fund Cluster
    $pdf->SetFont('dejavusans', '', 10);
    $pdf->Cell(120, 8, 'Entity Name : ' . ($meta['department_name'] ?? ''), 1, 0, 'L');
    $pdf->Cell(0,   8, 'Fund Cluster : GENERAL FUND', 1, 1, 'L');

    // From / To / PTR No. / Date
    $pdf->SetFont('dejavusans', '', 9);
    $pdf->Cell(155, 8, 'From Accountable Officer/Agency : ' . ($meta['previous_user'] ?? '') . '/' . ($meta['previous_dept'] ?? ''), 1, 0, 'L');
    $pdf->Cell(0,   8, 'PTR No. : ' . ($meta['ptr_number'] ?? ''), 1, 1, 'L');
    $pdf->Cell(155, 8, 'To Accountable Officer/Agency : '   . ($meta['emp_name'] ?? ''), 1, 0, 'L');
    $pdf->Cell(0,   8, 'Date : ' . date('m.d.y'), 1, 1, 'L');

    // Transfer Type checkboxes
    $pdf->Cell(0,  8, 'Transfer Type: (check only one)', 'LTR', 1, 'L');
    $pdf->Cell(60, 8, '', 'L', 0); $pdf->Cell(50, 8, '[  ] Donation', 0, 0); $pdf->Cell(50, 8, '[  ] Relocate', 0, 0); $pdf->Cell(0, 8, '', 'R', 1);
    $pdf->Cell(60, 8, '', 'L', 0); $pdf->Cell(50, 8, '[  ] Reassignment', 0, 0); $pdf->Cell(50, 8, '[  ] Others (Specify)', 0, 0); $pdf->Cell(0, 8, '', 'R', 1);

    // Table header
    $pdf->SetFont('dejavusans', 'B', 9);
    [$cQty, $cDate, $cProp, $cDesc, $cAmt, $cCond] = PTR_COL;
    $pdf->Cell($cQty,  10, 'Qty',          1, 0, 'C');
    $pdf->Cell($cDate, 10, "Date Acq'd",   1, 0, 'C');
    $pdf->Cell($cProp, 10, 'Property No.', 1, 0, 'C');
    $pdf->Cell($cDesc, 10, 'Description',  1, 0, 'C');
    $pdf->Cell($cAmt,  10, 'Amount',             1, 0, 'C');
    $pdf->MultiCell($cCond, 10, "Condition\nof PPE", 1, 'C', false, 1);

    // Item rows
    $pdf->SetFont('dejavusans', '', 9);
    foreach ($items as $item) {
      $qty      = (int)($item['qty'] ?? 1);
      $sn1      = trim((string)($item['serial_number']   ?? ''));
      $sn2      = trim((string)($item['serial_number_2'] ?? ''));
      $descBase = trim(($item['model'] ?? '') . ' ' . ($item['description'] ?? ''));

      // Build serial number suffix (only for single items with a real serial)
      $snSuffix = '';
      if ($qty === 1 && $sn1 !== '' && strtoupper($sn1) !== 'N/A') {
        $snSuffix = 'SN: ' . $sn1 . ($sn2 !== '' && strtoupper($sn2) !== 'N/A' ? ' / ' . $sn2 : '');
      }

      if ($snSuffix !== '') {
        // Lines available in a 140mm cell at font 9 (~5mm per line, conservative)
        $maxLines    = (int)floor(140 / 5);
        $snLines     = (int)$pdf->getNumLines($snSuffix, $cDesc);
        $descMaxLines = max(1, $maxLines - $snLines - 1); // -1 for blank separator line

        // Truncate description word by word until it fits within reserved lines
        $words    = preg_split('/\s+/', $descBase);
        $truncated = $descBase;
        while (count($words) > 1 && (int)$pdf->getNumLines($truncated, $cDesc) > $descMaxLines) {
          array_pop($words);
          $truncated = implode(' ', $words) . '...';
        }
        $desc = $truncated . "\n" . $snSuffix;
      } else {
        $desc = $descBase;
      }
      $propText  = collapsePropertyNumbers($item['par_numbers'] ?? [$item['p_par_number'] ?? '']);
      $unitPrice = (float)($item['unit_value'] ?? 0);
      $totalVal  = (float)($item['total_value'] ?? $unitPrice);
      $amount    = $qty > 1
          ? 'Unit: ₱ ' . number_format($unitPrice, 2) . "\nTotal: ₱ " . number_format($totalVal, 2)
          : '₱ ' . number_format($unitPrice, 2);

      $rowH = 140;
      $xRow = $pdf->GetX();
      $yRow = $pdf->GetY();
      $pdf->MultiCell($cQty,  $rowH, "\n" . (string)$qty,                   'LR', 'C', false, 0, $xRow,                              $yRow, true, 0, false, true, $rowH, 'T');
      $pdf->MultiCell($cDate, $rowH, "\n" . ($item['date_aquired'] ?? ''),   'LR', 'C', false, 0, $xRow + $cQty,                      $yRow, true, 0, false, true, $rowH, 'T');
      $pdf->MultiCell($cProp, $rowH, "\n" . $propText,                       'LR', 'C', false, 0, $xRow + $cQty + $cDate,             $yRow, true, 0, false, true, $rowH, 'T');
      $pdf->MultiCell($cDesc, $rowH, "\n" . $desc,                           'LR', 'C', false, 0, $xRow + $cQty + $cDate + $cProp,    $yRow, true, 0, false, true, $rowH, 'T');
      $pdf->MultiCell($cAmt,  $rowH, "\n" . $amount,                         'LR', 'C', false, 0, $xRow + $cQty + $cDate + $cProp + $cDesc, $yRow, true, 0, false, true, $rowH, 'T');
      $pdf->MultiCell($cCond, $rowH, "\n" . ($item['unit_condition'] ?? ''), 'LR', 'C', false, 1, $xRow + $cQty + $cDate + $cProp + $cDesc + $cAmt, $yRow, true, 0, false, true, $rowH, 'T');
    }

    // Reason for Transfer
    $pdf->SetFont('dejavusans', '', 9);
    $pdf->Cell(0, 8, 'Reason for Transfer: ' . ($meta['reason'] ?? ''), 'LTR', 1, 'L');
    for ($i = 0; $i < 3; $i++) { $pdf->Cell(0, 8, '', 'LR', 1, 'L'); }

    // Signature block
    $pdf->SetFont('dejavusans', '', 9);
    $pdf->Cell(30, 8, '',                       'TL',  0, 'L'); $pdf->Cell(50, 8, 'Approved by:',        'T', 0, 'L'); $pdf->Cell(60, 8, 'Released/Issued by:', 'T', 0, 'L'); $pdf->Cell(50, 8, 'Received by:', 'T', 0, 'L'); $pdf->Cell(0, 8, '', 'TR', 1, 'L');
    $pdf->Cell(30, 8, 'Signature :',            'L',   0, 'L'); $pdf->Cell(50, 8, '',                    'B', 0, 'L'); $pdf->Cell(60, 8, '',                    'B', 0, 'L'); $pdf->Cell(50, 8, '',                  'B', 0, 'L'); $pdf->Cell(0, 8, '', 'R',  1, 'L');
    $pdf->Cell(30, 8, 'Printed Name :',         'L',   0, 'L'); $pdf->Cell(50, 8, 'ATTY. ARVIN Q. TAPIA', 'B', 0, 'L'); $pdf->Cell(60, 8, ($meta['previous_user'] ?? ''), 'B', 0, 'L'); $pdf->Cell(50, 8, ($meta['emp_name'] ?? ''), 'B', 0, 'L'); $pdf->Cell(0, 8, '', 'R', 1, 'L');
    $pdf->Cell(30, 8, 'Designation :',          'L',   0, 'L'); $pdf->Cell(50, 8, 'OIC-General Services Office', 'B', 0, 'L'); $pdf->Cell(60, 8, ($meta['previous_dept'] ?? ''), 'B', 0, 'L'); $pdf->Cell(50, 8, ($meta['department_name'] ?? ''), 'B', 0, 'L'); $pdf->Cell(0, 8, '', 'R', 1, 'L');
    $pdf->Cell(30, 8, 'Date :',                 'L',   0, 'L'); $pdf->Cell(50, 8, date('F d, Y'),        'B', 0, 'L'); $pdf->Cell(60, 8, date('F d, Y'),        'B', 0, 'L'); $pdf->Cell(50, 8, date('F d, Y'),      'B', 0, 'L'); $pdf->Cell(0, 8, '', 'R',  1, 'L');
    $pdf->Cell(30, 8, '',                       'LB',  0, 'L'); $pdf->Cell(50, 8, '',                    'B', 0, 'L'); $pdf->Cell(60, 8, '',                    'B', 0, 'L'); $pdf->Cell(50, 8, '',                  'B', 0, 'L'); $pdf->Cell(0, 8, '', 'RB', 1, 'L');
  }

  if (count($rows) > 0) {
    $groups = groupAndSummarizePtrRows($rows);
    foreach ($groups as $group) {
      render_ptr_page($pdf, $group['meta'], $group['items']);
    }
  } else {
    render_ptr_page($pdf, [
      'department_name' => '', 'previous_user' => '', 'previous_dept' => '',
      'emp_name' => '', 'ptr_number' => '', 'reason' => '',
    ], [[
      'qty' => 1, 'date_aquired' => '', 'par_numbers' => [''], 'p_par_number' => '',
      'model' => '', 'description' => '', 'serial_number' => '', 'serial_number_2' => '',
      'unit_value' => 0, 'total_value' => 0, 'unit_condition' => '',
    ]]);
  }

  $pdf->Output('property_transfer_report.pdf', 'I');

  ?>