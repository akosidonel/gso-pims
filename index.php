<?php 
require_once __DIR__ . '/config/session_bootstrap.php';
gso_start_secure_session();
// Log errors to a file instead of suppressing
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/php-error.log');
error_reporting(E_ALL);

require_once __DIR__ . '/auth/auth.php';

$loginLockout = [
  'is_locked' => false,
  'remaining_seconds' => 0,
  'unlock_at' => '',
  'message' => '',
];

if (isset($_SESSION['login_lockout']) && is_array($_SESSION['login_lockout'])) {
  $sessionLockout = gso_login_lockout_state($_SESSION['login_lockout']['unlock_at'] ?? '');
  if (!empty($sessionLockout['is_locked'])) {
    $loginLockout = [
      'is_locked' => true,
      'remaining_seconds' => (int)$sessionLockout['remaining_seconds'],
      'unlock_at' => (string)$sessionLockout['unlock_at'],
      'message' => gso_login_lockout_message($sessionLockout),
    ];
  } else {
    unset($_SESSION['login_lockout']);
  }
}

if(isset($_SESSION['alogin'])){
  $sessionRole = strtoupper(trim((string)($_SESSION['role'] ?? '')));
  $landingPage = $sessionRole === 'MV-ADMIN' ? 'admin/motor-vehicle-dashboard.php' : 'admin/dashboard.php';
  if ($sessionRole === 'CLEARANCE-ADMIN') { $landingPage = 'services/clearance.php'; }
  header('Location:' . $landingPage);
  exit();
}else{
	  if(isset($_POST['signinbtn'])){
	    $empid = trim((string)($_POST['empid'] ?? ''));
	    $password = (string)($_POST['password'] ?? '');
	    $row = gso_fetch_administrator_by_emp_number($conn, $empid);
	    if($row){
        $accountLockout = gso_administrator_login_lockout_state($row);
        if (!empty($accountLockout['is_locked'])) {
          $loginLockout = [
            'is_locked' => true,
            'remaining_seconds' => (int)$accountLockout['remaining_seconds'],
            'unlock_at' => (string)$accountLockout['unlock_at'],
            'message' => gso_login_lockout_message($accountLockout),
          ];
          $_SESSION['login_lockout'] = [
            'unlock_at' => $loginLockout['unlock_at'],
          ];
          $_SESSION['error'] = $loginLockout['message'];
	      } elseif(password_verify($password, $row['password'])){
          gso_reset_administrator_login_failures($conn, $row['admin_id']);
          unset($_SESSION['login_lockout']);
	        session_regenerate_id(true); // Prevent session fixation

        if (!empty($row['password_must_change'])) {
          $resetEmail = trim((string)($row['email'] ?? ''));
          if ($resetEmail === '') {
            $_SESSION['error'] = "Your account requires a password reset, but no email is configured.";
          } else {
            $resetToken = bin2hex(random_bytes(32));
            $expiresAt = date('Y-m-d H:i:s', time() + 3600);
            if (gso_store_administrator_reset_token($conn, $resetEmail, $resetToken, $expiresAt)) {
              $_SESSION['success'] = "Please set a new password to continue.";
              header('Location:reset-password.php?token=' . urlencode($resetToken));
              exit();
            }
            $_SESSION['error'] = "Unable to start the password reset process for this account.";
          }
        } else {

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
	          gso_log_activity($conn, $uid, $uip, $actvty);
	          gso_admin_touch_activity($conn, (string)$uid, $uip);
	        }
	        elseif($row['role'] == 'GF/SEF-ADMIN'){
          $_SESSION['alogin'] = $row['admin_id'];
          $_SESSION['role'] = $row['role'];
	          $uid = $_SESSION['alogin'];
	          $uip = getUserIpAddr();
	          $actvty = "Logged in the system.";
	          header('Location:admin/dashboard.php');
	          gso_log_activity($conn, $uid, $uip, $actvty);
	          gso_admin_touch_activity($conn, (string)$uid, $uip);
	        }
	        elseif($row['role'] == 'CLEARANCE-ADMIN'){
          $_SESSION['alogin'] = $row['admin_id'];
          $_SESSION['role'] = $row['role'];
	          $uid = $_SESSION['alogin'];
	          $uip = getUserIpAddr();
	          $actvty = "Logged in the system.";
	          header('Location:services/clearance.php');
	          gso_log_activity($conn, $uid, $uip, $actvty);
	          gso_admin_touch_activity($conn, (string)$uid, $uip);
	        }
	         elseif($row['role'] == 'DISPOSAL-ADMIN'){
          $_SESSION['alogin'] = $row['admin_id'];
          $_SESSION['role'] = $row['role'];
	          $uid = $_SESSION['alogin'];
	          $uip = getUserIpAddr();
	          $actvty = "Logged in the system.";
	          header('Location:admin/dashboard.php');
	          gso_log_activity($conn, $uid, $uip, $actvty);
	          gso_admin_touch_activity($conn, (string)$uid, $uip);
	        }
	        elseif($row['role'] == 'MV-ADMIN'){
          $_SESSION['alogin'] = $row['admin_id'];
          $_SESSION['role'] = $row['role'];
	          $uid = $_SESSION['alogin'];
	          $uip = getUserIpAddr();
	          $actvty = "Logged in the system.";
	          header('Location:admin/motor-vehicle-dashboard.php');
	          gso_log_activity($conn, $uid, $uip, $actvty);
	          gso_admin_touch_activity($conn, (string)$uid, $uip);
	        }
	        else {
	          $_SESSION['error']="Unauthorized role!";
	        }
        }
	      }else{
          $failedLogin = gso_register_administrator_login_failure($conn, $row['admin_id']);
          if (!empty($failedLogin['is_locked'])) {
            $loginLockout = [
              'is_locked' => true,
              'remaining_seconds' => (int)$failedLogin['remaining_seconds'],
              'unlock_at' => (string)$failedLogin['unlock_at'],
              'message' => gso_login_lockout_message($failedLogin),
            ];
            $_SESSION['login_lockout'] = [
              'unlock_at' => $loginLockout['unlock_at'],
            ];
            $_SESSION['error'] = $loginLockout['message'];
          } else {
	          $_SESSION['error']="Incorrect Password!";
          }
	      }
    }else{
      $_SESSION['error']="Invalid Employee Number!";
    }
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
            <div id="loginLockoutNotice" class="alert alert-warning" style="display:none;"></div>
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
  <script src="assets/plugins/jquery/jquery.min.js"></script><!-- jQuery -->
  <script>
    window.currentUserRole = "";
    window.GSO_LIGHT_PAGE = true;
    window.gsoLoginLockout = <?php echo json_encode($loginLockout, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;
  </script>
  <?php
    $__gsoLoginScriptPath = __DIR__ . '/assets/dist/js/script.js';
    $__gsoLoginScriptVer = @filemtime($__gsoLoginScriptPath);
    if (!$__gsoLoginScriptVer) { $__gsoLoginScriptVer = '20260205'; }
  ?>
  <script src="assets/dist/js/script.js?v=<?php echo urlencode((string)$__gsoLoginScriptVer); ?>"></script><!---Custom App-->
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
