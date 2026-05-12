<?php 
// Start session with secure parameters
session_set_cookie_params([
  'httponly' => true,
  'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
  'samesite' => 'Strict',
]);
session_start();
// Log errors to a file instead of suppressing
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/php-error.log');
error_reporting(E_ALL);

include('database/databaseConnection.php');
include('include/getuser_ipaddress.php');

function set_admin_online($conn, $adminId){
  $adminIdSafe = mysqli_real_escape_string($conn, (string)$adminId);
  // Prefer last_activity if present; fall back to legacy status-only.
  $ok = mysqli_query($conn, "UPDATE administrator SET status = '1', last_activity = NOW() WHERE admin_id='{$adminIdSafe}'");
  if(!$ok){
    mysqli_query($conn, "UPDATE administrator SET status = '1' WHERE admin_id='{$adminIdSafe}'");
  }
}

if(isset($_SESSION['alogin'])){
  header('Location:admin/dashboard.php');
  exit();
}else{
  if(isset($_POST['signinbtn'])){
    $empid = trim($_POST['empid']);
    $password = $_POST['password'];
    // Use prepared statement
    $stmt = $conn->prepare("SELECT * FROM administrator WHERE emp_number = ? LIMIT 1");
    $stmt->bind_param('s', $empid);
    $stmt->execute();
    $results = $stmt->get_result();
    if($results && $results->num_rows == 1){
      $row = $results->fetch_assoc();
      if(password_verify($password, $row['password'])){
        session_regenerate_id(true); // Prevent session fixation

        // Store display name for navbar usage
        $_SESSION['admin_name'] = trim(((string)($row['first_name'] ?? '')) . ' ' . ((string)($row['last_name'] ?? '')));
        if($row['role'] == 'SYSTEM-ADMIN'){
          $_SESSION['alogin'] = $row['admin_id'];
          $_SESSION['role'] = $row['role'];
          $_SESSION['start'] = time();
          $uid = $_SESSION['alogin'];
          $uip = getUserIpAddr();
          $actvty = "Logged in the system.";
          header('Location:admin/dashboard.php');
          mysqli_query($conn, "INSERT INTO activity_log(admin_id,ip_address,activity) VALUES('$uid','$uip','$actvty')");
          set_admin_online($conn, $uid);
        }
        elseif($row['role'] == 'GF/SEF-ADMIN'){
          $_SESSION['alogin'] = $row['admin_id'];
          $_SESSION['role'] = $row['role'];
          $uid = $_SESSION['alogin'];
          $uip = getUserIpAddr();
          $actvty = "Logged in the system.";
          header('Location:admin/dashboard.php');
          mysqli_query($conn, "INSERT INTO activity_log(admin_id,ip_address,activity) VALUES('$uid','$uip','$actvty')");
          set_admin_online($conn, $uid);
        }
        elseif($row['role'] == 'CLEARANCE-ADMIN'){
          $_SESSION['alogin'] = $row['admin_id'];
          $_SESSION['role'] = $row['role'];
          $uid = $_SESSION['alogin'];
          $uip = getUserIpAddr();
          $actvty = "Logged in the system.";
          header('Location:services/clearance.php');
          mysqli_query($conn, "INSERT INTO activity_log(admin_id,ip_address,activity) VALUES('$uid','$uip','$actvty')");
          set_admin_online($conn, $uid);
        }
         elseif($row['role'] == 'DISPOSAL-ADMIN'){
          $_SESSION['alogin'] = $row['admin_id'];
          $_SESSION['role'] = $row['role'];
          $uid = $_SESSION['alogin'];
          $uip = getUserIpAddr();
          $actvty = "Logged in the system.";
          header('Location:admin/dashboard.php');
          mysqli_query($conn, "INSERT INTO activity_log(admin_id,ip_address,activity) VALUES('$uid','$uip','$actvty')");
          set_admin_online($conn, $uid);
        }
      }else{
        $_SESSION['error']="Incorrect Password!";
      }
    }else{
      $_SESSION['error']="Invalid Employee Number!";
    }
    $stmt->close();
  } 
}
?>
<!doctype html>
<html lang="en">
  <head>
    <!-- Required meta tags -->
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <link href="https://fonts.googleapis.com/css?family=Roboto:300,400&display=swap" rel="stylesheet">
    <!-- Style -->
    <link rel="stylesheet" href="assets/plugins/sweetalert2-theme-bootstrap-4/bootstrap-4.min.css">
    <link rel="stylesheet" href="assets/plugins/fontawesome-free/css/all.min.css">  <!-- Font Awesome -->
    <link rel="stylesheet" href="assets/dist/css/adminlte.min.css">  <!-- Theme style -->
    <link rel="stylesheet" href="assets/dist/css/auth.css">

    <title>PIMS | LOGIN</title>
  </head>
  <body>
  <div class="d-lg-flex half">
    <div class="bg order-1 order-md-2" style="background-image: url('cover.png'); background-position:center center; height: 100%;background-repeat: no-repeat;background-size: cover;"></div> 
    <div class="contents order-2 order-md-1">

      <div class="container">
        <div class="row align-items-center justify-content-center">
          <div class="row">
          <div class="col-md-12 mb-4">
            <h2 class="mb-4"><strong class="text-success">Property Inventory Management System</strong></h2>
            <form method="post">
              <div class="form-group first">
                <input type="text" class="form-control" placeholder="Your employee number here..." name="empid" id="empid" autofocus required>
              </div>
              <div class="form-group last mb-3">
                <input type="password" class="form-control" placeholder="Your password here..." id="password" name="password" required>
              </div>
              <div class="d-flex mb-4 align-items-center">
                <span><a href="forgot-password.php" class="forgot-pass">Forgot Password</a></span> 
              </div>
              <input type="submit" value="Log In" name="signinbtn" class="btn btn-success float-right">
            </form>
          </div>
            <div class="col-md-12 mt-5 text-black-50"> <?php include('include/version.php')?><!--version control--></div>
          </div>
        </div>
      </div>
    </div> 
  </div>

  <script src="assets/plugins/sweetalert2/sweetalert2.min.js"></script><!--sweetalert-->
  <?php if (isset($_SESSION['error'])) {?>
	  <script>
    Swal.fire({
    position: 'center',
    icon: 'error',
    title: '<?=$_SESSION['error'];?>',
    showConfirmButton: false,
    timer: 2200
    });
    </script>
    <?php } unset($_SESSION['error']);?>

  </body>
   
</html>