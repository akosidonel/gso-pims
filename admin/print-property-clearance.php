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
    public $signatoryName = 'ATTY. ARVIN Q. TAPIA';
    public $signatoryTitle = 'OIC-General Services Office';

    public function Header() {
        $this->Ln(33);
        $this->SetFont('dejavusans','',11);
        $this->setJPEGQuality(75);

        $logoSize = 26;
        $logoY = 5;
        $logoGap = 4;
        $pageWidth = $this->getPageWidth();

        $logos = [];
        if (file_exists('mega.png')) { $logos[] = ['file' => 'mega.png', 'type' => 'PNG']; }
        if (file_exists('logo.jpg')) { $logos[] = ['file' => 'logo.jpg', 'type' => 'JPG']; }
        if (file_exists('bp.png'))   { $logos[] = ['file' => 'bp.png',   'type' => 'PNG']; }

        $count = count($logos);
        if ($count > 0) {
            $groupWidth = ($logoSize * $count) + ($logoGap * ($count - 1));
            $x = ($pageWidth - $groupWidth) / 2;
            foreach ($logos as $logo) {
                $this->Image($logo['file'], $x, $logoY, $logoSize, $logoSize, $logo['type'], '', '', true, 300, '', false, false, 0, false, false, false);
                $x += $logoSize + $logoGap;
            }
        }
        $this->SetX(72);
        $this->SetFont('times','B',18);
        $this->Cell(130,5,"Republic of the Philippines",0,1);
        $this->SetX(90);
        $this->SetFont('times','B',14);
        $this->Cell(130,5,"City of Parañaque",0,1);
        $this->SetX(97);
        $this->SetFont('times','',12);
        $this->Cell(130,5,"Metro Manila",0,1);
        $this->Ln(6);
        $this->SetX(23);
        $this->SetFont('times','',13);
        $this->Cell(10,5,"TANGGAPAN NG OPISYAL NG SERBISYONG PANGKALAHATAN NG LUNGSOD",0,1);
        $this->SetX(80);
        $this->SetFont('times','B',12);
        $this->Cell(10,5,"(GENERAL SERVICES OFFICE)",0,1);
    }

    public function Footer() {
        $this->SetY(-110);
        $this->SetFont('dejavusans','B',13);
        $this->Cell(0,5,$this->signatoryName,0,1,'C');
        $this->SetFont('dejavusans','',11);
        $this->Cell(0,5,$this->signatoryTitle,0,1,'C');
        $this->SetY(-50);
        $this->SetX(23);
        $this->SetFont('dejavusans','',12);
        $this->Cell(95,5,"O.R. No.: ".$this->or,0,1);
        $this->SetX(23);      
        $this->Cell(95,5,"Date : ".$this->date,0,1);
        $this->SetX(23);      
        $this->Cell(95,5,"Amount : Php 100.00",0,1);
        $this->SetX(23);      
        $this->Cell(95,5,"DRY SEAL",0,1);
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
        $employee      = htmlspecialchars($employeeRaw);
        $position      = htmlspecialchars($positionRaw);
        $dept          = htmlspecialchars($deptRaw);
        $address       = htmlspecialchars($row['address']);
        $city          = htmlspecialchars($row['city']);
        $clearanceType = htmlspecialchars($row['clearance_name']);
        $created       = htmlspecialchars($row['created_at']);
        $dateCreated   = date('F j, Y', strtotime($created));
        $date          = date('jS \of F Y');

        $appointmentPhrase = $appointmentPhraseOverride
            ?? (should_use_elected_as($row['emp_name'] ?? '', $row['position'] ?? '') ? 'elected as' : 'appointed as');

        $pdf = new PDF('P','mm', 'A4', true, 'UTF-8', false);
        $pdf->SetMargins(20, 20, 20);
        $pdf->AddPage();

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
            $pdf->Image('gso.png', 0, 70, 210, 190, '', '', 'C', true, 72);
        }
        $pdf->SetAlpha(1);

        $pdf->Ln(65);
        $pdf->SetFont('dejavusans','',13);

        $html = <<<EOD
        <h4 style="text-align:right; margin-top: 20px; margin-bottom: 20px;">Control No. : $controlNum </h4>
        <h2 style="text-align:center; margin-top: 20px; margin-bottom: 20px;">PROPERTY CLEARANCE</h2>
        <h4 style="margin-left: 150px; margin-bottom: 15px;">TO WHOM IT MAY CONCERN:</h4>
        <p style="text-align:justify; text-indent:50px; margin-left: 50px; margin-right: 50px; margin-bottom: 15px;">
            This is to Certify that Mr./Ms. <b>$employee</b>, a resident of <b>$address</b>, <b>$city</b>, $appointmentPhrase <b>$position</b>
            of <b>$dept</b>, has no property accountability in the office as of this date.
        </p>
        <p style="text-align:justify; text-indent:50px; margin-left: 50px; margin-right: 50px; margin-bottom: 15px;">
            Issued this <b>$date</b> upon the request of the above to be used for <b>$clearanceType</b> and for whatever legal purpose and intent this may serve.
        </p>
EOD;
        $pdf->writeHTML($html, true, false, false, false, '');

        // QR code style
        $style = array(
            'border' => 2,
            'vpadding' => 'auto',
            'hpadding' => 'auto',
            'fgcolor' => array(0, 0, 0),
            'bgcolor' => false,
            'module_width' => 1.5,
            'module_height' => 1.5
        );
        $pdf->write2DBarcode($controlNum, 'QRCODE,L', 150, 228, 60, 60, $style, 'N');
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