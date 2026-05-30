<?php
session_start();
require_once __DIR__ . '/auth/auth.php';

// if(!isset($_GET['token'])){
//   header("Location:404.php");
//   exit(0);
// }

if(isset($_POST['changeBtn'])){
  $newPassword = trim((string)($_POST['newpassword'] ?? ''));
  $confirmPassword = trim((string)($_POST['confirmpassword'] ?? ''));
  $password = password_hash($newPassword,PASSWORD_DEFAULT);
  $token = trim((string)($_POST['token'] ?? ''));

  if(!empty($token)){
    if(!empty($newPassword) && !empty($confirmPassword)){
        $tokenRow = gso_fetch_administrator_by_reset_token($conn, $token);
        if($tokenRow){
          if($newPassword == $confirmPassword){
              if(gso_update_password_by_reset_token($conn, $password, $token)){
                $newToken = bin2hex(random_bytes(32));
                gso_rotate_password_reset_token($conn, $token, $newToken);
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
              <input type="hidden" value="<?php if(isset($_GET['token'])){echo $_GET['token'];} ?>" name="token" id="token">
              <div class="form-group first">
                <input type="password" class="form-control"  placeholder="New password" name="newpassword" id="newpassword" autofocus required>
              </div>
              <div class="form-group last mb-4">
                <input type="password" class="form-control" placeholder="Confirm new password" name="confirmpassword" id="confirmpassword" autofocus required>
              </div>
              <?php
            if(!isset($_SESSION['success'])){?>
            <input type="submit" value="Change password" name="changeBtn" class="btn btn-success btn btn-block">
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
