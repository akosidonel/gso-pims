<?php
ini_set("display_errors", "1");
error_reporting(E_ALL);
require '../database/databaseConnection.php';
require_once('../tcpdf/tcpdf.php');

  
  //property return slip information
  $products_info=[];

  if(isset($_GET['reference_number'])){
    $ref_num = $_GET['reference_number'];
    $sql = "SELECT  r.emp_id,r.dept_id,r.par_number,r.reference_number,p.par_number,p.model,p.item,p.serial_number,p.serial_number_2,e.emp_id,e.emp_name,d.department_name,d.department_code
    FROM return_history AS r
    JOIN return_to_stock AS p ON r.par_number = p.par_number  
    JOIN employee AS e ON r.emp_id = e.emp_id
    JOIN department AS d ON r.dept_id = d.department_code
    WHERE r.reference_number = '$ref_num'";
    $query = mysqli_query($conn, $sql);
    if(mysqli_num_rows($query) >0 ){
      while($value = mysqli_fetch_assoc($query)){
        $products_info[]=[
          "item"=> $value["item"],
          "model"=> $value["model"],
          "parnumber"=> $value["par_number"],
          "enduser"=> $value["emp_name"],
          "serial_number"=> $value["serial_number"],
          "serial_number_2"=> $value["serial_number_2"],
        ];
      }
    }
  }
  
  class PDF extends TCPDF
  {
    function Header(){
      $this->Ln(36);
      $this->SetFont('dejavusans','',11);
      $this->setJPEGQuality(75);
      $this->Image('logo.jpg', 93, 5, 27, 27, 'JPG', '', '', true, 300, '', false, false, 0, false, false, false);
      $this->SetX(75);
      $this->SetFont('times','B',14);
      $this->Cell(130,5,"PROPERTY RETURN SLIP",0,1);
     
    }
    
    function body($products_info){
      
      $this->SetY(45);
      $this->SetX(10);
      $this->SetFont('dejavusans','',9);
      $this->Cell(50,10,"Name of Local Government Unit: ",0,1);
      $this->SetFont('dejavusans','',9);
      $this->Cell(50,7,"Purpose: ",0,1);
      
      
      //Display Table headings
      $this->SetY(65);
      $this->SetX(10);
      $this->SetFont('dejavusans','',7);
      $html ='
      <table cellpadding="5" cellspacing="0" border="1" align="center">
      <tr>
        <th  width="27" align="center">Qty</th>
        <th  width="30" align="center">Unit</th>
        <th  width="210">Description</th>
        <th  width="90">Property No.</th>
        <th  width="125">End user</th>
        <th  width="60">Unit Value</th>  
      </tr>
      ';
     
     // Display table product rows
      foreach($products_info as $row => $value){
        
        $html .='
        <tr>
        <td>1</td>
        <td>UNIT</td>
        <td align="left">'.$value["item"].' - '.$value["model"].' - SNID1: '.$value["serial_number"].' , SNID2: '.$value["serial_number_2"].'</td>
        <td>'.$value["parnumber"].'</td>
        <td>'.$value["enduser"].'</td>
        <td>0</td> 
        </tr>
        '; 
      }
 
      //Display table empty rows
      for($i=0; $i<15-count($products_info); $i++)
      {
        $html .='  
        <tr>
        <td></td>
        <td></td>
        <td></td>
        <td></td>
        <td></td>
        <td></td> 
        </tr>
        ';
      }
      
      $html.='</table>';

      $this->writeHTML($html,true,false,false,false,'');
      
    }
    function Footer(){

      $this->SetY(-98);
      $this->SetX(88);
      $this->SetFont('dejavusans','B',10);
      $this->Cell(100,5,"CERTIFICATION",0,1);

      
      $this->SetY(-90);
      $this->SetX(10);
      $this->SetFont('dejavusans','',8);
      $this->Cell(100,5,"I HERBY CERTIFY that i have this ________________________",0,1);
      $this->SetX(10);
      $this->Cell(30,5,"day of ______________ ",0,0);
      $this->Cell(10,5,"  (YEAR) RETURN BY: ",0,0);


      $this->SetY(-90);
      $this->SetX(117);
      $this->SetFont('dejavusans','',8);
      $this->Cell(100,5,"I HERBY CERTIFY that i have this ________________________",0,1);
      $this->SetX(117);
      $this->Cell(30,5,"day of ______________  ",0,0);
      $this->Cell(10,5," RECEIVED BY: ",0,0);



      $this->SetY(-70);
      $this->SetX(40);
      $this->SetFont('dejavusans','',9);
      $this->Cell(10,5,$this->enduser,0,0);
      $this->SetY(-70);
      $this->SetX(140);
      $this->SetFont('dejavusans','',9);
      $this->Cell(10,5,"JEROME A. ALIMPONGAT",0,0);

      $this->SetY(-69);
      $this->SetX(25);
      $this->SetFont('dejavusans','',9);
      $this->Cell(102,5,"________________________________________",0,0);
      $this->Cell(10,5,"________________________________________",0,0);

      
      $this->SetY(-65);
      $this->SetX(35);
      $this->SetFont('dejavusans','',9);
      $this->Cell(100,5,"Printed Name and Signature",0,0);
      $this->SetY(-65);
      $this->SetX(140);
      $this->SetFont('dejavusans','',9);
      $this->Cell(10,5,"Administrative Aide III",0,0);

      // Center department name above 'Department/Office', but shift a bit to the left
      $this->SetY(-50);
      $this->SetFont('dejavusans','',9);
      $deptWidth = $this->GetStringWidth($this->dept) + 4;
      $pageWidth = $this->getPageWidth() - $this->lMargin - $this->rMargin;
      $deptX = ($pageWidth - $deptWidth) / 2 + $this->lMargin - 48; // shift 10mm left
      $this->SetX($deptX);
      $this->Cell($deptWidth, 5, $this->dept, 0, 0, 'C');
      $this->SetX(140);
      $this->Cell(10,5,"ATTY. ARVIN Q. TAPIA",0,0);

      // Underline centered department name
      $this->SetY(-49);
      $this->SetX($deptX);
      $this->Cell($deptWidth,5,"________________________________________",0,0,'C');
      $this->SetX(127);
      $this->Cell(10,5,"________________________________________",0,0);

      // Department/Office label centered under department name
      $this->SetY(-45);
      $this->SetX($deptX);
      $this->Cell($deptWidth,5,"Department/Office",0,0,'C');
      $this->SetX(137);
      $this->Cell(10,5,"OIC-General Services Office",0,0);
    }
    
  }


  if(isset($_GET['reference_number'])){
    $ref_num = $_GET['reference_number'];
    $sql = "SELECT  r.emp_id,r.dept_id,r.par_number,r.reference_number,p.par_number,p.model,p.item,e.emp_id,e.emp_name,d.department_name,d.department_code
    FROM return_history AS r
    JOIN return_to_stock AS p ON r.par_number = p.par_number  
    JOIN employee AS e ON r.emp_id = e.emp_id
    JOIN department AS d ON r.dept_id = d.department_code
    WHERE r.reference_number = '$ref_num'";
    $result = mysqli_query($conn, $sql);
    if(mysqli_fetch_assoc($result)){
     foreach ($result as $key){
        $enduser = $key["emp_name"];
        $dept = $key["department_name"];
     }
    }
  }


  //Create A4 Page with Portrait 
  $pdf= new PDF("P","mm","A4");
  $pdf->AddPage();
  $pdf->body($products_info);
  $pdf->enduser = $enduser;
  $pdf->dept = $dept;
  $pdf->Output();
?>