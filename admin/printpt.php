<?php
ini_set("display_errors", "1");
error_reporting(E_ALL);
require '../database/databaseConnection.php';
if (!defined('GSO_AUTH_LIB_ONLY')) {
    define('GSO_AUTH_LIB_ONLY', true);
}
require_once('../auth/auth.php');
require_once('../tcpdf/tcpdf.php');
require_once('../include/summarize_print_rows.php');

class PDF extends TCPDF  
{
    public $supplier = '';
    public $po = '';
    public $pr = '';
    public $obr = '';
    public $code = '';
    public $previous_user = '';
    public $previous_dept = '';
    public $new_user = '';
    public $new_dept = '';
    public $fund_cluster = 'GENERAL FUND';
    public $itemPage = 1;
    public $itemTotal = 1;

    public function header(){
        $this->Ln(44);
        $this->SetFont('dejavusans','',8);
        $this->SetX(28);
        $this->Cell(130,5,"CITY GOVERNMENT OF PARAÑAQUE",0,1);
        $this->SetX(28);
        $fund = isset($this->fund_cluster) && $this->fund_cluster ? $this->fund_cluster : 'GENERAL FUND';
        $this->Cell(130,5,$fund,0,1);
    }

    public function footer(){
        $this->SetY(-105);
        $this->SetX(130);
        $this->SetFont('dejavusans','',8);
        $this->Cell(62,5,$this->supplier,0,1);
        $this->SetX(130);
        $this->Cell(62,5,$this->po,0,1);
        $this->SetX(130);
        $this->Cell(62,5,$this->pr,0,1);
        $this->SetX(130);
        $this->Cell(62,5,$this->obr,0,1);
        $this->SetX(130);
        $this->Cell(62,5,$this->code,0,1);
        $this->SetY(-126);
        $this->SetX(130);
        $this->SetFont('dejavusans','',9.5);
        $cancelText = trim($this->previous_user . ((trim($this->previous_dept) !== '') ? "\n" . $this->previous_dept : ''));
        $this->MultiCell(62, 5, $cancelText, 0, 'L', false, 1);
        $this->SetY(-67);
        // Dynamically center $this->new_user below $this->new_dept
        $this->SetX(40);
        $this->SetFont('dejavusans','',9.5);
        $cellWidth = 95;
        $userWidth = $this->GetStringWidth($this->new_user);
        $xUser = 40 + (($cellWidth - $userWidth) / 2);
        // Dynamically center new_user to the left
        $cellWidth = 95;
        $userWidth = $this->GetStringWidth($this->new_user);
        $xUser = 8 + (($cellWidth - $userWidth) / 2); // move right by 8mm
        $this->SetX($xUser);
        $this->Cell($userWidth,5,$this->new_user,0,0,'C');
        $this->SetX(135);
        $this->Cell(40,5,"ATTY. ARVIN Q. TAPIA",0,0,'C');
        // Dynamically center new_dept to the left
        $this->Ln(5);
        $deptWidth = $this->GetStringWidth($this->new_dept);
        $xDept = 8 + (($cellWidth - $deptWidth) / 2); // move right by 8mm
        $this->SetY($this->GetY() + 10); // Lower by 10mm
        $this->SetX($xDept);
        $this->Cell($deptWidth,5,$this->new_dept,0,0,'C');
        $this->SetY($this->GetY()); // Keep same Y for OIC cell
        $this->SetX(135);
        $this->Cell(40,5,"OIC - GENERAL SERVICES OFFICE",0,0,'C');

        // Always print pagination a bit upward from the very bottom center
        $this->SetY(-25); // 25mm from bottom
        $this->SetFont('dejavusans','',9.5);
        // If per-item counters are set, use them; otherwise fall back to document-level counters
        if (property_exists($this, 'itemPage') && property_exists($this, 'itemTotal') && (int)$this->itemTotal > 0) {
            $pageNum = 'Page ' . ((int)$this->itemPage) . ' of ' . ((int)$this->itemTotal);
        } else {
            $pageNum = 'Page '.$this->getAliasNumPage().' of '.$this->getAliasNbPages();
        }
        $this->Cell(0, 10, $pageNum, 0, 0, 'C');
    }
  }

      if (isset($_GET['reference_number']) || isset($_GET['refnumber'])) {
        $refnumber = isset($_GET['reference_number']) ? trim((string)$_GET['reference_number']) : trim((string)$_GET['refnumber']);
        $parFilter = isset($_GET['par']) ? trim($_GET['par']) : '';
                $parsRaw = isset($_GET['pars']) ? trim($_GET['pars']) : '';
                $parsFilter = [];
                if ($parsRaw !== '') {
                        $parts = array_map('trim', explode(',', $parsRaw));
                        $parts = array_filter($parts, function($v) { return $v !== ''; });
                        $parsFilter = array_values(array_unique($parts));
                        if (count($parsFilter) > 200) {
                                $parsFilter = array_slice($parsFilter, 0, 200);
                        }
                }
        $rows = gso_fetch_printpt_rows($conn, $refnumber, $parFilter, $parsFilter);
        if(!empty($rows)){
            // Summarize identical items with no serial numbers into single rows
            $rows = summarizePrintRows($rows);

            $pdf = new PDF ('P','mm','A4',true,'UTF-8',false);

            // Helper: clear all dynamic footer fields for non-final pages
            function clearFooterFields($pdf) {
                $pdf->supplier = '';
                $pdf->po = '';
                $pdf->pr = '';
                $pdf->obr = '';
                $pdf->code = '';
                $pdf->previous_user = '';
                $pdf->previous_dept = '';
            }

            function buildPtRowHtml($chunkHtml, $displayQty, $displayUnit, $displayPar, $displayDate, $displayAmount, $includeNothingFollows) {
                $nothingFollowsRow = $includeNothingFollows
                    ? '<tr align="center"><td colspan="6">**NOTHING FOLLOWS**</td></tr>'
                    : '';

                return <<<EOD
                <table cellpadding="5" cellspacing="0" border="0">
                <tr align="center">
                  <td width="51" align="center">$displayQty</td>
                  <td width="44.5" align="center">$displayUnit</td>
                  <td width="220" align="justified">$chunkHtml</td>
                  <td width="87"> $displayPar</td>
                  <td width="55">$displayDate</td>
                  <td align="center">$displayAmount</td>
                </tr>
                $nothingFollowsRow
                </table>
                EOD;
            }

            function gso_pt_unit_label($unitRaw, $qty) {
                $unit = strtoupper(trim((string)$unitRaw));
                $qty = (int)$qty;

                if ($unit === '') {
                    $unit = 'UNIT';
                }

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
                    'METRES' => 'METER',
                    'BOOKS' => 'BOOK',
                    'COPIES' => 'COPY',
                    'TANKS' => 'TANK'
                ];
                if (isset($singularUnits[$unit])) {
                    $unit = $singularUnits[$unit];
                }

                if ($qty <= 1) {
                    return $unit;
                }

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
                    'METER' => 'METERS',
                    'BOOK' => 'BOOKS',
                    'COPY' => 'COPIES',
                    'TANK' => 'TANKS'
                ];

                return $pluralUnits[$unit] ?? ($unit . 'S');
            }

            function splitTextByRenderedLines($pdf, $text, $widthMm, $maxLinesFirstPage, $maxLinesNextPages) {
                $words = preg_split('/\s+/', trim((string)$text));
                $words = array_values(array_filter($words, function($w) { return $w !== ''; }));

                if (count($words) === 0) {
                    return ['No property description available.'];
                }

                $chunks = [];
                $currentChunk = [];
                $pageIndex = 0;

                foreach ($words as $word) {
                    $lineLimit = ($pageIndex === 0) ? $maxLinesFirstPage : $maxLinesNextPages;
                    $candidate = trim(implode(' ', array_merge($currentChunk, [$word])));
                    $numLines = (int)$pdf->getNumLines($candidate, $widthMm);

                    if ($numLines <= $lineLimit || empty($currentChunk)) {
                        $currentChunk[] = $word;
                    } else {
                        $chunks[] = implode(' ', $currentChunk);
                        $currentChunk = [$word];
                        $pageIndex++;
                    }
                }

                if (!empty($currentChunk)) {
                    $chunks[] = implode(' ', $currentChunk);
                }

                return $chunks;
            }

            function estimateRenderedHtmlLines($pdf, $html, $widthMm) {
                $html = (string)$html;
                if (trim($html) === '') { return 0; }

                $parts = preg_split('/<br\s*\/?>/i', $html);
                $totalLines = 0;
                foreach ($parts as $part) {
                    $text = trim(html_entity_decode(strip_tags((string)$part), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
                    if ($text === '') {
                        $totalLines += 1;
                        continue;
                    }
                    $totalLines += max(1, (int)$pdf->getNumLines($text, $widthMm));
                }

                return $totalLines;
            }

            function gso_pt_bundle_table_columns(mysqli $conn, string $table): array {
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
                if ($resCols = mysqli_query($conn, "SHOW COLUMNS FROM `{$safeTable}`")) {
                    while ($colRow = mysqli_fetch_assoc($resCols)) {
                        $field = strtolower(trim((string)($colRow['Field'] ?? '')));
                        if ($field !== '') {
                            $cache[$tableKey][$field] = true;
                        }
                    }
                    mysqli_free_result($resCols);
                }

                return $cache[$tableKey];
            }

            function gso_pt_bundle_union_field(array $columns, string $column): string {
                return isset($columns[$column]) ? $column : ('NULL AS ' . $column);
            }

            function gso_pt_fetch_bundles_for_parent(mysqli $conn, string $bundleWith): array {
                $bundleWith = strtoupper(trim($bundleWith));
                if ($bundleWith === '') { return []; }

                $gfColumns = gso_pt_bundle_table_columns($conn, 'bundle_gen_fund');
                $sefColumns = gso_pt_bundle_table_columns($conn, 'bundle_sef');

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
                        SELECT property_number, bundle_with, " . gso_pt_bundle_union_field($gfColumns, 'item') . ", " . gso_pt_bundle_union_field($gfColumns, 'model') . ", " . gso_pt_bundle_union_field($gfColumns, 'description') . ", " . gso_pt_bundle_union_field($gfColumns, 'serial_number') . ", " . gso_pt_bundle_union_field($gfColumns, 'serial_number_2') . " FROM bundle_gen_fund
                        UNION ALL
                        SELECT property_number, bundle_with, " . gso_pt_bundle_union_field($sefColumns, 'item') . ", " . gso_pt_bundle_union_field($sefColumns, 'model') . ", " . gso_pt_bundle_union_field($sefColumns, 'description') . ", " . gso_pt_bundle_union_field($sefColumns, 'serial_number') . ", " . gso_pt_bundle_union_field($sefColumns, 'serial_number_2') . " FROM bundle_sef
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
                if ($stmtBundle = $conn->prepare($sql)) {
                    $stmtBundle->bind_param('s', $bundleWith);
                    $stmtBundle->execute();
                    $resBundle = $stmtBundle->get_result();
                    while ($bundleRow = $resBundle->fetch_assoc()) {
                        $bp = strtoupper(trim((string)($bundleRow['bundle_property_number'] ?? '')));
                        if ($bp === '') { continue; }
                        $rowKey = sha1(
                            $bp . '|' .
                            strtoupper(trim((string)($bundleRow['item'] ?? ''))) . '|' .
                            strtoupper(trim((string)($bundleRow['model'] ?? ''))) . '|' .
                            strtoupper(trim((string)($bundleRow['description'] ?? ''))) . '|' .
                            strtoupper(trim((string)($bundleRow['serial_number'] ?? ''))) . '|' .
                            strtoupper(trim((string)($bundleRow['serial_number_2'] ?? '')))
                        );
                        $out[$rowKey] = $bundleRow;
                    }
                    $stmtBundle->close();
                }

                return array_values($out);
            }

            function gso_pt_bundle_block_html(array $bundlesByParentPropertyNumbers): string {
                $groups = [];

                foreach ($bundlesByParentPropertyNumbers as $bundleRows) {
                    if (!is_array($bundleRows) || count($bundleRows) === 0) { continue; }
                    foreach ($bundleRows as $bundleRow) {
                        $item = trim((string)($bundleRow['item'] ?? ''));
                        $model = trim((string)($bundleRow['model'] ?? ''));
                        $desc = trim((string)($bundleRow['description'] ?? ''));
                        $s1 = trim((string)($bundleRow['serial_number'] ?? ''));
                        $s2 = trim((string)($bundleRow['serial_number_2'] ?? ''));

                        $part = trim($item . ' ' . $model);
                        $detail = $part !== '' ? $part : '';
                        if ($desc !== '') {
                            $detail = $detail !== '' ? ($detail . ' - ' . $desc) : $desc;
                        }

                        $dedupeKey = strtoupper(trim($detail));
                        if (!isset($groups[$dedupeKey])) {
                            $groups[$dedupeKey] = ['detail' => $detail, 'serials' => []];
                        }
                        foreach ([$s1, $s2] as $serial) {
                            if ($serial !== '' && !in_array($serial, $groups[$dedupeKey]['serials'], true)) {
                                $groups[$dedupeKey]['serials'][] = $serial;
                            }
                        }
                    }
                }

                $lines = [];
                $hasHeading = false;
                foreach ($groups as $group) {
                    $detailSafe = htmlspecialchars($group['detail'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                    $serialText = implode(', ', $group['serials']);
                    $serialSafe = htmlspecialchars($serialText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

                    $line = '';
                    if ($detailSafe !== '') { $line .= $detailSafe; }
                    if ($serialSafe !== '') { $line .= ' (SN: ' . $serialSafe . ')'; }
                    if (trim(strip_tags($line)) === '') { continue; }
                    if (!$hasHeading) {
                        $lines[] = '<br><br>Bundle Items:';
                        $hasHeading = true;
                    }
                    $lines[] = '<br>' . $line;
                }

                return implode('', $lines);
            }

            $bundleCache = [];
            foreach ($rows as $rowIndex => $bundleRow) {
                $propertyNumbers = [];
                if (isset($bundleRow['par_numbers']) && is_array($bundleRow['par_numbers']) && count($bundleRow['par_numbers']) > 0) {
                    $propertyNumbers = $bundleRow['par_numbers'];
                } elseif (trim((string)($bundleRow['par_number'] ?? '')) !== '') {
                    $propertyNumbers = [$bundleRow['par_number']];
                }

                $bundlesByProperty = [];
                foreach ($propertyNumbers as $propertyNumber) {
                    $pnKey = strtoupper(trim((string)$propertyNumber));
                    if ($pnKey === '') { continue; }
                    if (!array_key_exists($pnKey, $bundleCache)) {
                        $bundleCache[$pnKey] = gso_pt_fetch_bundles_for_parent($conn, $pnKey);
                    }
                    if (!empty($bundleCache[$pnKey])) {
                        $bundlesByProperty[$pnKey] = $bundleCache[$pnKey];
                    }
                }

                if (!empty($bundlesByProperty)) {
                    $rows[$rowIndex]['bundles_by_property'] = $bundlesByProperty;
                }
            }

                      foreach ($rows as $row) {
              $model = $row['model'];
              $description = $row['description'];
              $snid = $row['serial_number'];
              $snid2 = $row['serial_number_2'];
              $par = $row['par_number'];
              $price = $row['unit_value'];
              $date = $row['date_aquired'];
              $qty = $row['qty'] ?? 1;
              $totalValue = $row['total_value'] ?? (float)$price;
              $amount = "₱".number_format($totalValue, 2, ".", ",");
              $parDisplay = ($qty > 1) ? implode("\n", $row['par_numbers'] ?? [$par]) : $par;
              $supplier = $row['supplier'];
              $po = $row['purchase_order'];
              $pr = $row['purchase_request'];
              $obr = $row['obr_number'];
              $code = $row['account_code'];
              $pvuser = $row['previous_user'];
              $pvdept = $row['previous_dept'];
              $newuser = $row['user'];
                  $fundCluster = strtoupper(trim($row['fund'] ?? 'GENERAL FUND'));
              $newdept = $row['department_name'];
              $bundlesByProp = (isset($row['bundles_by_property']) && is_array($row['bundles_by_property'])) ? $row['bundles_by_property'] : [];
              $bundleHtml = !empty($bundlesByProp) ? gso_pt_bundle_block_html($bundlesByProp) : '';

              // Apply static footer fields (signatories) per item
              $pdf->new_user = $newuser;
              $pdf->new_dept = $newdept;

              // Split only description content; serial lines are rendered separately below it.
              $baseDescription = trim($model . ' - ' . $description, " -");
              if ($baseDescription === '') {
                  $baseDescription = 'No property description available.';
              }

              // Skip serial lines for summarized (grouped) items since they have no serials
              if ($qty > 1) {
                  $serialLine1 = '';
                  $serialLine2 = '';
              } else {
                  $serialLine1 = 'SN 1: ' . ((trim((string)$snid) !== '') ? trim((string)$snid) : 'N/A');
                  $serialLine2 = 'SN 2: ' . ((trim((string)$snid2) !== '') ? trim((string)$snid2) : 'N/A');
              }

              // Stable split by rendered lines for description column (avoids TCPDF transaction state issues).
              $pdf->SetFont('dejavusans','',8);
              $descWidthMm = 110.0;
              $lineHeightMm = 3.2;
              $footerTopY = $pdf->getPageHeight() - 125;
              $contentStartY = 116.5;
              $usableHeightMm = max(20, $footerTopY - $contentStartY - 1.0);
              $amountReserveMm = 10.0;
              $firstPageUsableHeightMm = max(16, $usableHeightMm - $amountReserveMm);
              $baseMaxLines = max(8, (int)floor($usableHeightMm / $lineHeightMm));
              $baseFirstPageMaxLines = max(6, (int)floor($firstPageUsableHeightMm / $lineHeightMm));
              $fillRatio = 1.65; // Pack more text before splitting to reduce blank space.
              $maxLinesPerPage = max(14, (int)floor($baseMaxLines * $fillRatio));
              $maxLinesFirstPage = max(10, (int)floor($baseFirstPageMaxLines * $fillRatio));

              // SN lines are only printed for non-grouped items, so reserve space accordingly.
              $serialTailHtml = '';
              if ($qty <= 1) {
                  if ($serialLine1 !== '') { $serialTailHtml .= '<br>' . htmlspecialchars($serialLine1, ENT_QUOTES, 'UTF-8'); }
                  if ($serialLine2 !== '') { $serialTailHtml .= '<br>' . htmlspecialchars($serialLine2, ENT_QUOTES, 'UTF-8'); }
              } elseif ($bundleHtml !== '') {
                  $serialTailHtml .= '<br>SN 1: N/A<br>SN 2: N/A';
              }
              $tailHtml = $serialTailHtml;
              if ($bundleHtml !== '') {
                  $tailHtml .= $bundleHtml;
              }
              $tailLineCount = estimateRenderedHtmlLines($pdf, $tailHtml, $descWidthMm);
              $tailReserveLines = max(0, $tailLineCount - 1);
              $maxLinesFirstPage = max(8, $maxLinesFirstPage - $tailReserveLines);
              $maxLinesPerPage = max(10, $maxLinesPerPage - $tailReserveLines);
              $chunks = splitTextByRenderedLines($pdf, $baseDescription, $descWidthMm, $maxLinesFirstPage, $maxLinesPerPage);

              $lastPageBaseLimit = (count($chunks) === 1) ? $maxLinesFirstPage : $maxLinesPerPage;
              $lastPageLineLimit = max(6, $lastPageBaseLimit);
              if (!empty($chunks)) {
                  $lastChunkIndex = count($chunks) - 1;
                  $lastChunkLines = (int)$pdf->getNumLines($chunks[$lastChunkIndex], $descWidthMm);
                  if ($lastChunkLines > $lastPageLineLimit) {
                      $tailChunks = splitTextByRenderedLines(
                          $pdf,
                          $chunks[$lastChunkIndex],
                          $descWidthMm,
                          $maxLinesPerPage,
                          $lastPageLineLimit
                      );
                      array_pop($chunks);
                      foreach ($tailChunks as $tailChunk) {
                          $chunks[] = $tailChunk;
                      }
                  }
              }

              $last_idx = count($chunks) - 1;

              // footer fields only on the last page for this item section
              foreach ($chunks as $idx => $chunk_text) {
                  // Add a page for this chunk (this closes previous page)
                  $pdf->fund_cluster = ($fundCluster === 'SEF' || $fundCluster === 'SPECIAL EDUCATION FUND') ? 'SPECIAL EDUCATION FUND' : 'GENERAL FUND';
                  $pdf->AddPage();
                  if ($idx === 0) {
                      // Set total pages for this item after starting its first page
                      $pdf->itemTotal = count($chunks);
                  }
                  $pdf->Ln(52.5);
                  $pdf->SetFont('dejavusans','',8);

                  $chunkHtml = nl2br(htmlspecialchars($chunk_text, ENT_QUOTES, 'UTF-8'));
                  if ($idx === $last_idx && ($serialLine1 !== '' || $serialLine2 !== '')) {
                      if ($serialLine1 !== '') {
                          $chunkHtml .= '<br>' . htmlspecialchars($serialLine1, ENT_QUOTES, 'UTF-8');
                      }
                      if ($serialLine2 !== '') {
                          $chunkHtml .= '<br>' . htmlspecialchars($serialLine2, ENT_QUOTES, 'UTF-8');
                      }
                  } elseif ($idx === $last_idx && $bundleHtml !== '') {
                      $chunkHtml .= '<br>SN 1: N/A<br>SN 2: N/A';
                  }
                  if ($idx === $last_idx && $bundleHtml !== '') {
                      $chunkHtml .= $bundleHtml;
                  }

                  // Only display PAR / Date / Amount on the first page of this item
                  $displayPar = ($idx === 0) ? $parDisplay : '';
                  $displayDate = ($idx === 0) ? $date : '';
                  $displayAmount = ($idx === 0) ? $amount : '';
                  $displayQty = ($idx === 0) ? (string)$qty : '';
                  $displayUnit = ($idx === 0) ? gso_pt_unit_label((string)($row['unit'] ?? ''), $qty) : '';

                                    $html = buildPtRowHtml(
                                            $chunkHtml,
                                            $displayQty,
                                            $displayUnit,
                                            $displayPar,
                                            $displayDate,
                                            $displayAmount,
                                            ($idx === $last_idx)
                                    );
                  $pdf->writeHTML($html,true,false,false,false,'');

                  // Configure footer fields for THIS page (used when this page is closed on next AddPage/Output)
                  if ($idx === $last_idx) {
                      $pdf->supplier = "SUPPLIER: $supplier";
                      $pdf->po = "P.O NO. : $po";
                      $pdf->pr = "P.R NO. : $pr";
                      $pdf->obr = "O.B.R NO. : $obr";
                      $pdf->code = "ACCOUNT CODE: $code";
                      $pdf->previous_user = "This will cancel previous P.A.R issued to $pvuser";
                      $pdf->previous_dept = "of $pvdept";
                  } else {
                      clearFooterFields($pdf);
                  }

                  // Set per-item current page AFTER writing content, so footer uses correct values when this page closes later
                  $pdf->itemPage = $idx + 1;
              }
            }

            // Output a single PDF for all items
            $pdf->Output();
        } else {
            exit('No PAR transfer records found for this reference number.');
        }
      } else {
        exit('Missing reference number.');
      }
?>
