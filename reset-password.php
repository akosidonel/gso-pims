<?php
require_once __DIR__ . '/config/session_bootstrap.php';
gso_start_secure_session();
require_once __DIR__ . '/auth/auth.php';

$resetToken = trim((string)($_GET['token'] ?? $_POST['token'] ?? ''));
$resetFormToken = gso_issue_form_token('reset_password');
$hasValidResetToken = ($resetToken !== '' && gso_fetch_administrator_by_reset_token($conn, $resetToken));

if(isset($_POST['changeBtn'])){
  if (!gso_validate_form_token('reset_password', (string)($_POST['reset_form_token'] ?? ''), 1800, true)) {
    $_SESSION['error'] = "The reset form expired. Please try again.";
    header("Location:reset-password.php" . ($resetToken !== '' ? '?token=' . urlencode($resetToken) : ''));
    exit(0);
  }

  $newPassword = trim((string)($_POST['newpassword'] ?? ''));
  $confirmPassword = trim((string)($_POST['confirmpassword'] ?? ''));
  $token = trim((string)($_POST['token'] ?? ''));

  if(!empty($token)){
    if(!empty($newPassword) && !empty($confirmPassword)){
        $tokenRow = gso_fetch_administrator_by_reset_token($conn, $token);
        if($tokenRow){
          if($newPassword == $confirmPassword){
              if(strlen($newPassword) < 8){
                $_SESSION['error'] = "Password must be at least 8 characters long.";
                header("Location:reset-password.php?token=$token");
                exit(0);
              }

              $password = password_hash($newPassword,PASSWORD_DEFAULT);
              if(gso_update_password_by_reset_token($conn, $password, $token)){
                unset($_SESSION['alogin'], $_SESSION['role'], $_SESSION['admin_name'], $_SESSION['start']);
                session_regenerate_id(true);
                $_SESSION['success'] = "Password reset successfuly";
                header("Location:reset-password.php");
                exit(0);
              }else{
                $_SESSION['error'] = "Something went wrong";
                header("Location:reset-password.php?token= $token");
                exit(0);
              }
          }else{
            $_SESSION['error'] = "password and confirm password does not match";
            header("Location:reset-password.php?token=$token");
            exit(0);
          }
        }else{
          $_SESSION['error'] = "Token expired";
          header("Location:reset-password.php?token=$token");
          exit(0);
        } 
    }else{
      $_SESSION['error'] = "All fields are required";
      header("Location:reset-password.php?token=$token");
      exit(0);
    }
  }else{
    $_SESSION['error'] = "No token available";
    header("Location:reset-password.php?token=$token");
    exit(0);
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

    <title>GSO | RESET PASSWORD</title>
  </head>
  <body>
  
  <div class="d-lg-flex half">
    <div class="bg order-1 order-md-2" ></div> 
    <div class="contents order-2 order-md-1">

      <div class="container">
        <div class="row align-items-center justify-content-center">
          <div class="col-md-7">
            <h3 class="mb-2">Password</h2>
            <p>Use at least 8 characters. Don’t use a password from another site, or something too obvious like your pet’s name.</p>
            <form method="post">
              <input type="hidden" value="<?= htmlspecialchars($resetToken, ENT_QUOTES, 'UTF-8') ?>" name="token" id="token">
              <input type="hidden" value="<?= htmlspecialchars($resetFormToken, ENT_QUOTES, 'UTF-8') ?>" name="reset_form_token">
              <div class="form-group first">
                <input type="password" class="form-control"  placeholder="New password" name="newpassword" id="newpassword" autofocus required>
              </div>
              <div class="form-group last mb-4">
                <input type="password" class="form-control" placeholder="Confirm new password" name="confirmpassword" id="confirmpassword" autofocus required>
              </div>
              <?php
            if(!isset($_SESSION['success']) && $hasValidResetToken){?>
            <input type="submit" value="Change password" name="changeBtn" class="btn btn-success btn btn-block">
            <?php } elseif (!isset($_SESSION['success'])) { ?>
              <a href="forgot-password.php" class="btn btn-outline-secondary btn btn-block mt-3">request a new reset link</a>
            <?php } else{?>
              <a href="index.php" class="btn btn-primary btn btn-block mt-3 text-white">back to login page</a>
            <?php }?>
            </form>
          </div>
        </div>
      </div>
    </div> 
  </div>

  <script src="assets/plugins/sweetalert2/sweetalert2.min.js"></script><!--sweetalert-->
  <?php if (isset($_SESSION['success'])) {?>
	  <script>
    Swal.fire({
    position: 'center',
    icon: 'success',
    title: '<?=$_SESSION['success'];?>',
    showConfirmButton: false,
    timer: 2500
    });
    </script>
    <?php } unset($_SESSION['success']);?>

    <?php if (isset($_SESSION['error'])) {?>
	  <script>
    Swal.fire({
    position: 'center',
    icon: 'error',
    title: '<?=$_SESSION['error'];?>',
    showConfirmButton: false,
    timer: 2300
    });
    </script>
    <?php } unset($_SESSION['error']);?>
 
  </body>
   
</html>
