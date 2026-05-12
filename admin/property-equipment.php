<?php 
include_once('../config/session.php');

if(!isset($_SESSION['alogin'])){
    header('Location:../index.php');
    exit();
  }else {

@extract($_REQUEST);
if(!empty($_POST['action_type'])){
	$action_type = $conn->real_escape_string($_POST['action_type']);
	switch($action_type){
		case "get_detail":
		if(!empty($_POST['par_number'])){
			$qr_code = $conn->real_escape_string($_POST['par_number']);
			$sql = $conn->query("SELECT * FROM par_gen_fund WHERE par_number = '$qr_code' ");
			$numRow = $sql->num_rows;
			if($numRow > 0){
				$row = $sql->fetch_array();
				$detail = array('type'=>'Success','par_number'=>$row['par_number'],'article'=>$row['item'],'model'=>$row['model'],'serial'=>$row['serial_number']);
				echo json_encode($detail);
			}else{
				$detail = array('type'=>'Error');
				echo json_encode($detail);
			}
		}
		break;
	}
}
}?>