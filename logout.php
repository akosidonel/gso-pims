<?php
session_start(); 
include('database/databaseConnection.php');
include('include/getuser_ipaddress.php');

$uid = $_SESSION['alogin'];
$uip = getUserIpAddr();
$actvty = "Logged out.";
mysqli_multi_query($conn, "INSERT INTO activity_log(admin_id,ip_address,activity) VALUES('$uid','$uip','$actvty'); UPDATE administrator SET ip='$uip',last_session=now() WHERE admin_id='$uid'; UPDATE administrator SET status ='0' WHERE admin_id='".$_SESSION['alogin']."'; ");

unset($_SESSION['alogin']);
session_destroy(); // destroy session
header("Location:index.php");


