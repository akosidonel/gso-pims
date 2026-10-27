<?php
// Standalone PDF regression check; does not read or change equipment records.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

error_reporting(E_ALL);
require_once __DIR__ . '/../tcpdf/tcpdf.php';

set_error_handler(function ($severity, $message, $file, $line) {
    if (error_reporting() & $severity) {
        throw new ErrorException($message, 0, $severity, $file, $line);
    }
    return false;
});

// Exercise the actual header, description splitting, and formatting helpers
// without executing the endpoint's database queries or sending its PDF response.
$source = file_get_contents(__DIR__ . '/../admin/property_return_slip.php');
$start = strpos($source, 'function prs_splitTextToFitCellHeight(');
$end = strpos($source, "    if(isset(\$_GET['reference_number']))", $start);
if ($start === false || $end === false) {
    throw new RuntimeException('Could not locate return-slip rendering helpers.');
}
eval(substr($source, $start, $end - $start));

// Header logo paths in the endpoint are relative to the admin directory.
chdir(__DIR__ . '/../admin');
$pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);
$pdf->SetMargins(10, 18, 10);
$pdf->SetAutoPageBreak(true, 18);
$reference = '202610-36119E6BB378';

foreach (['DISPOSAL', 'RETURNED TO STOCK'] as $purpose) {
    $pdf->AddPage();
    prs_renderHeader($pdf, $reference, $purpose);
    $pdf->SetFont('helvetica', '', 9);
    $description = str_repeat('Unserviceable equipment for return. ', 150);
    $chunks = prs_splitTextToFitCellHeight($pdf, $description, 63, 108);
    if (count($chunks) < 2 || implode(' ', $chunks) !== trim($description)) {
        throw new RuntimeException('Long equipment description was not preserved across chunks.');
    }
    $pdf->MultiCell(63, 108, $chunks[0], 1);
}

$pageCount = $pdf->getNumPages();
$bytes = $pdf->Output('return-slip-check.pdf', 'S');
if (substr($bytes, 0, 5) !== '%PDF-' || strpos(substr($bytes, -32), '%%EOF') === false || $pageCount !== 2) {
    throw new RuntimeException('Expected a complete, two-page PDF response.');
}
restore_error_handler();
echo "Return-slip headers, description splitting, and PDF output passed with E_ALL.\n";
