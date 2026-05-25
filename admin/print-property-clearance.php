<?php
ini_set("display_errors", "1");
error_reporting(E_ALL);
date_default_timezone_set('Asia/Manila');
require_once __DIR__ . '/../database/databaseConnection.php';
require_once __DIR__ . '/../auth/auth.php';
require_once('../tcpdf/tcpdf.php');

// update once printed section (accept both control_id and control_id_number)
$controlParam = $_GET['control_id'] ?? null;
if ($controlParam !== null) {
    $isPrinted = 1;
    $isRead = 1;
    $today = date("Y-m-d H:i:s");
    $control_number = (string)$controlParam; // treat as string to support non-numeric control numbers
    $stmtUpdate = $conn->prepare("UPDATE clearance_history SET is_read = ?, status = ?, release_date = ? WHERE control_number = ?");
    $stmtUpdate->bind_param("iiss", $isRead, $isPrinted, $today, $control_number);
    $stmtUpdate->execute();
    $stmtUpdate->close();
}

// --- TCPDF Custom Class ---
class PDF extends TCPDF  
{
    public $or = '';
    public $date = '';
    public $showDisclaimer = false;
    public $verifiedByName = 'ARLENE M. POLANCOS';
    public $verifiedByTitle = 'Supply and Property Division';
    public $signatoryName = 'ATTY. ARVIN Q. TAPIA';
    public $signatoryTitle = 'OIC-General Services Office';

    public function Header() {
        $this->SetFont('dejavusans','',11);
        $this->setJPEGQuality(75);

        $assetDir = __DIR__ . '/';
        $leftLogo = $assetDir . 'logo.jpg';
        $rightLogo = $assetDir . 'bp.png';
        $headerTextX = 50;
        $headerTextWidth = 110;

        if (file_exists($leftLogo)) {
            $this->Image($leftLogo, 24, 8, 22, 22, 'JPG', '', '', true, 300, '', false, false, 0, false, false, false);
        }

        if (file_exists($rightLogo)) {
            $this->Image($rightLogo, 163, 9, 26, 20, 'PNG', '', '', true, 300, '', false, false, 0, false, false, false);
        }

        $this->SetY(11);
        $this->SetX($headerTextX);
        $this->SetFont('times', 'B', 23);
        $this->Cell($headerTextWidth, 8, 'GENERAL SERVICES OFFICE', 0, 1, 'C');

        $this->SetX($headerTextX);
        $this->SetFont('times', '', 9);
        $this->Cell($headerTextWidth, 5, 'TANGGAPAN NG OPISYAL NG SERBISYOPANG KALAHATAN NG LUNGSOD', 0, 1, 'C');

        $this->Ln(15);
        $this->SetFont('helvetica', 'B', 14);
        $this->Cell(0, 7, 'PROPERTY CLEARANCE CERTIFICATE', 0, 1, 'C');

        $this->SetFont('helvetica', '', 10);
        $this->Cell(0, 5, '(No Property Accountability)', 0, 1, 'C');
    }

    public function Footer() {
        $leftX = 18;
        $columnWidth = 82;
        $rightX = 110;

        $this->SetY(-128);
        $this->SetFont('helvetica', '', 11);
        $this->SetX($leftX);
        $this->Cell($columnWidth, 6, 'Verified by:', 0, 0, 'L');
        $this->SetX($rightX);
        $this->Cell($columnWidth, 6, 'Approved by:', 0, 1, 'L');

        $lineY = $this->GetY() + 16;
        $this->Line($leftX, $lineY, $leftX + $columnWidth, $lineY);
        $this->Line($rightX, $lineY, $rightX + $columnWidth, $lineY);

        $this->SetY($lineY + 6);
        $this->SetX($leftX);
        $this->SetFont('helvetica', 'B', 13);
        $this->Cell($columnWidth, 6, $this->verifiedByName, 0, 0, 'C');
        $this->SetX($rightX);
        $this->Cell($columnWidth, 6, $this->signatoryName, 0, 1, 'C');

        $this->SetX($leftX);
        $this->SetFont('helvetica', '', 11);
        $this->Cell($columnWidth, 6, $this->verifiedByTitle, 0, 0, 'C');
        $this->SetX($rightX);
        $this->Cell($columnWidth, 6, $this->signatoryTitle, 0, 1, 'C');

        $this->SetY(-68);
        $this->SetX(23);
        $this->SetFont('dejavusans','',12);
        $this->Cell(95,5,"O.R. No.: ".$this->or,0,1);
        $this->SetX(23);      
        $this->Cell(95,5,"Date : ".$this->date,0,1);
        $this->SetX(23);      
        $this->Cell(95,5,"Amount : Php 100.00",0,1);
        $this->SetX(23);      
        $this->Cell(95,5,"DRY SEAL",0,1);

        if ($this->showDisclaimer) {
            $this->SetY(-34);
            $this->SetX(23);
            $this->SetFont('helvetica', '', 7.5);
            $this->setCellPaddings(3, 2, 3, 2);
            $disclaimer = '<div><b>DISCLAIMER:</b> This clearance is issued based on the current records of the General Services Office (GSO). Any city-owned equipment, property, or assets found to be in the possession of the applicant which were not reflected or accounted for at the time of verification shall render this clearance null and void. The issuance of this document does not absolve the applicant from accountability or liability for any unrecorded properties discovered hereafter.</div>';
            $this->writeHTMLCell(164, 0, '', '', $disclaimer, 1, 1, false, true, 'J', true);
            $this->setCellPaddings(0, 0, 0, 0);
        }

        $megaLogo = __DIR__ . '/mega.png';
        if (file_exists($megaLogo)) {
            $this->Image($megaLogo, 88, 281, 34, 12, 'PNG', '', '', true, 300, '', false, false, 0, false, false, false);
        }
    }
}

if (!function_exists('pdf_uppercase')) {
    function pdf_uppercase($value) {
        $text = trim((string)$value);
        if ($text === '') {
            return '';
        }

        return function_exists('mb_strtoupper')
            ? mb_strtoupper($text, 'UTF-8')
            : strtoupper($text);
    }
}

// --- Fetch and output property clearance PDF ---
if ($controlParam !== null) {
    $control_id = (string)$controlParam;
    $row = fetch_property_clearance_print_data($conn, $control_id);
    if ($row) {
        // Apply council secretary forcing BEFORE escaping for output
        $employeeRaw = (string)($row['emp_name'] ?? '');
        $positionRaw = (string)($row['position'] ?? '');
        $deptRaw     = (string)($row['department_name'] ?? '');

        $appointmentPhraseOverride = null;
        $executivePos = normalize_city_executive_position($positionRaw);
        if ($executivePos !== null) {
            $positionRaw = $executivePos;
            $deptRaw = 'City of Parañaque';
            $appointmentPhraseOverride = 'elected as';
        }

        if ($executivePos === null) {
            $forcedPos = forced_council_secretary_position_from_position($positionRaw);
            if ($forcedPos !== null) {
                $positionRaw = $forcedPos;
                $appointmentPhraseOverride = 'elected as';
                if ($forcedPos === 'CITY COUNCILOR') {
                    $deptRaw = 'City of Parañaque';
                } else {
                    $deptRaw = 'OFFICE OF THE CITY COUNCIL SECRETARY';
                }
            }
        }

        // Sanitize output
        $controlNum    = htmlspecialchars($row['control_number']);
        $or            = htmlspecialchars($row['or_number']);
        $employee      = htmlspecialchars(pdf_uppercase($employeeRaw));
        $position      = htmlspecialchars(pdf_uppercase($positionRaw));
        $dept          = htmlspecialchars(pdf_uppercase($deptRaw));
        $clearanceTypeText = pdf_uppercase($row['clearance_name']);
        $clearanceType = htmlspecialchars($clearanceTypeText);
        $created       = htmlspecialchars($row['created_at']);
        $dateCreated   = date('F j, Y', strtotime($created));
        $date          = date('jS \of F Y');
        $appointmentPhrase = htmlspecialchars($appointmentPhraseOverride ?: 'holding the position of');

        $noAccountabilityTypes = array(
            'TERMINAL LEAVE',
            'RETIREMENT',
            'RESIGNATION',
            'TRANSFER OF OFFICE FROM LOCAL TO NATIONAL',
            'EXHAUSTION OF LEAVE CREDITS'
        );
        $usesNoAccountabilityWording = in_array($clearanceTypeText, $noAccountabilityTypes, true);

        $pdf = new PDF('P','mm', 'A4', true, 'UTF-8', false);
        $pdf->SetMargins(20, 20, 20);
        $pdf->AddPage();
        $pdf->showDisclaimer = $usesNoAccountabilityWording;

        if (is_gso_officer_in_charge_applicant($row['department_name'] ?? '', $row['position'] ?? '')) {
            $cityAdmin = fetch_city_administrator_signatory($conn);
            if ($cityAdmin && !empty($cityAdmin['name'])) {
                $pdf->signatoryName = (string)$cityAdmin['name'];
                $pdf->signatoryTitle = 'CITY ADMINISTRATOR';
            }
        }

        // Watermark image
        $pdf->SetAlpha(0.10);
        if (file_exists('gso.png')) {
            $pdf->Image('gso.png', 0, 58, 210, 190, '', '', 'C', true, 72);
        }
        $pdf->SetAlpha(1);

        $pdf->SetY(64);
        $pdf->SetFont('dejavusans','',13);

        if ($usesNoAccountabilityWording) {
            $bodyHtml = <<<EOD
        <p style="text-align:justify; text-indent:38px; margin-left: 32px; margin-right: 32px; line-height: 1.55; margin-bottom: 8px;">
            This is to certify that <b>$employee</b>, $appointmentPhrase <b>$position</b> in the <b>$dept</b>,
            has been verified by this Office with <b>NO EXISTING PROPERTY ACCOUNTABILITY</b> under any Property Acknowledgement
            Receipt (PAR) or Inventory Custodian Slip (ICS).
        </p>
        <p style="text-align:justify; text-indent:38px; margin-left: 32px; margin-right: 32px; line-height: 1.55; margin-bottom: 8px;">
            This clearance is issued specifically for <b>$clearanceType</b> purposes and for whatever legal purpose it may serve.
        </p>
EOD;
        } else {
            $bodyHtml = <<<EOD
        <p style="text-align:justify; text-indent:38px; margin-left: 32px; margin-right: 32px; line-height: 1.55; margin-bottom: 8px;">
            This is to certify that <b>$employee</b>, $appointmentPhrase <b>$position</b> in the <b>$dept</b>,
            is authorized by this Office during his/her approved leave.
        </p>
        <p style="text-align:justify; text-indent:38px; margin-left: 32px; margin-right: 32px; line-height: 1.55; margin-bottom: 8px;">
            This certification is issued specifically for <b>$clearanceType</b> purposes. All properties issued to the applicant
            under a Property Acknowledgement Receipt (PAR) or Inventory Custodian Slip (ICS) shall remain under their accountability
            for the duration of the leave and must be accounted for upon resumption of duty.
        </p>
EOD;
        }

        $html = <<<EOD
        <h4 style="text-align:right; margin-top: 0; margin-bottom: 12px;">Control No. : $controlNum </h4>
        <h4 style="margin-left: 32px; margin-top: 0; margin-bottom: 12px;">TO WHOM IT MAY CONCERN:</h4>
        $bodyHtml
        <p style="text-align:left; text-indent:38px; margin-left: 32px; margin-right: 32px; line-height: 1.55; margin-bottom: 8px;">
            Issued this <b>$date</b> at Parañaque City.
        </p>
EOD;
        $pdf->writeHTML($html, true, false, false, false, '');

        $pdf->or = $or;
        $pdf->date = $dateCreated;
        $pdf->Output();
    } else {
        echo "No record found.";
    }
} else {
    echo "No record found.";
}
?>
