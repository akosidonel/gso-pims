<?php
session_start();
include '../database/databaseConnection.php';
@extract($_REQUEST);

$output= array();
$table_sql = "SELECT administrator.admin_id, administrator.first_name, administrator.last_name, administrator.role,
activity_log.admin_id, activity_log.ip_address, activity_log.activity, activity_log.time FROM activity_log JOIN administrator ON activity_log.admin_id = administrator.admin_id ";
$table_query = mysqli_query($conn,$table_sql);
$count_all_rows = mysqli_num_rows($table_query);

$columns = array(
    0 => 'time',
	1 => 'user',
	2 => 'role',
	3 => 'ip_address',
	4 => 'activity',
);

if(isset($_POST['search']['value'])){
    $search_value = $_POST['search']['value'];
    $table_sql .=" WHERE first_name like '%".$search_value."%' ";
    $table_sql .=" OR last_name like '%".$search_value."%' ";
    $table_sql .=" OR ip_address like '%".$search_value."%' ";
    $table_sql .=" OR activity like '%".$search_value."%' ";
    $table_sql .=" OR time like '%".$search_value."%' ";
}
if(isset($_POST['order'])){
    $column_name = $_POST['order'][0]['column'];
    $order = $_POST['order'][0]['dir'];
    $table_query .=" ORDER BY ".$columns[$column_name]." ".$order." ";
}else{
    $table_sql .=" ORDER BY time DESC";
}

if($_POST['length'] != -1){
    $start = $_POST['start'];
    $length = $_POST['length'];
    $table_sql .=" LIMIT ".$start.",".$length;
}

$run_query = mysqli_query($conn,$table_sql);
$filtered_rows = mysqli_num_rows($run_query);
$data = array();
while($row = mysqli_fetch_assoc($run_query)){

    $activityText = $row['activity'];
    // Human-friendly formatting for structured log entries
    if (strpos($activityText, 'PROPERTY CLEARANCE REPRINT|CTRL=') === 0) {
        $ctrl = '';
        $reason = '';
        $ctrlPos = strpos($activityText, '|CTRL=');
        if ($ctrlPos !== false) {
            $ctrl = substr($activityText, $ctrlPos + 6);
            $pipePos = strpos($ctrl, '|');
            if ($pipePos !== false) {
                $ctrl = substr($ctrl, 0, $pipePos);
            }
        }
        $reasonPos = strpos($activityText, '|REASON=');
        if ($reasonPos !== false) {
            $reason = substr($activityText, $reasonPos + 8);
            $pipePos2 = strpos($reason, '|');
            if ($pipePos2 !== false) {
                $reason = substr($reason, 0, $pipePos2);
            }
        }
        $ctrl = trim($ctrl);
        $reason = trim($reason);
        $activityText = 'Re-printed Property Clearance (CTRL: ' . $ctrl . ')' . ($reason !== '' ? ' — Reason: ' . $reason : '');
    }

    $subarray = array();
    $subarray[]= date('F j, Y, g:i a',strtotime($row['time']));
    $subarray[]= $row['first_name']." ".$row['last_name'];
    $subarray[]= $row['role']; 
    $subarray[]= $row['ip_address'];
    $subarray[]= $activityText;
    $data[]= $subarray;
}

$output = array(
     'draw'=>intval($_POST['draw']),
     'recordsTotal'=>$filtered_rows,
     'recordsFiltered'=>$count_all_rows,
     'data'=>$data,
);

echo json_encode($output);

?>