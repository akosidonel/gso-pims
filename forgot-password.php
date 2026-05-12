<?php
session_start();
include('database/databaseConnection.php');

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

//Load Composer's autoloader
require 'vendor/autoload.php';

function send_password_reset($getEmail,$token){
  $mail = new PHPMailer(true);
  $mail->isSMTP();                                            // Send using SMTP
  $mail->Host       = 'smtp.gmail.com';                    // Set the SMTP server to send through
  $mail->SMTPAuth   = true;                                   // Enable SMTP authentication
  $mail->Username   = 'generalservicesoffice.pque@gmail.com';                     // SMTP username
  $mail->Password   = 'sizjeaenppcxnxtp';                               // SMTP password
  $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;         // Enable TLS encryption; `PHPMailer::ENCRYPTION_SMTPS` encouraged
  $mail->SMTPKeepAlive = true; 
  $mail->Port       = 465;                                    // TCP port to connect to, use 465 for `PHPMailer::ENCRYPTION_SMTPS` above

  //Recipients
  $mail->setFrom('generalservicesoffice.pque@gmail.com', 'General Services Office');
  $mail->addAddress($getEmail);     // Add a recipient
  $mail->addReplyTo('no-reply@gmail.com', 'No reply');
  
  // Content
  $url = "http://localhost/gso-master/reset-password.php?token=$token";
  $mail->isHTML(true);                                  // Set email format to HTML
  $mail->Subject = 'Your Password reset link';
  $mail->Body    = "Hello,<br>
  We've received your request to reset your P.T.M.S admin password.<br><br>
  Reset your password <a href='$url' class='btn btn-success'>here</a>";
  $mail->AltBody = 'This is the body in plain text for non-HTML mail clients';
  $mail->send();
}

if(isset($_POST['resetBtn'])){

  $email = mysqli_real_escape_string($conn, $_POST['email']);
  $token = md5(rand());

  $checkEmail = "SELECT email FROM administrator WHERE email= '$email' ";
  $query = mysqli_query($conn,$checkEmail);

  if(mysqli_num_rows($query) > 0){
    $row = mysqli_fetch_array($query);
    $getEmail = $row['email'];

    $updateToken = "UPDATE administrator SET token ='$token' WHERE email ='$getEmail' LIMIT 1";
    $query2 = mysqli_query($conn,$updateToken);

    if($query2){
      send_password_reset($getEmail,$token);
      $_SESSION['success'] = "Link has been Send to your Email";
      header("Location:forgot-password.php");
      exit(0);
    }else{
      $_SESSION['error'] = "Something went wrong. #1";
      header("Location:forgot-password.php");
      exit(0);
    }

  }else{
    $_SESSION['error'] = "Invalid Email";
    header("Location:forgot-password.php");
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

    <title>GSO | FORGOT PASSWORD</title>
  </head>
  <body>
  

  <div class="d-lg-flex half">
    <div class="bg order-1 order-md-2" ></div> 
    <div class="contents order-2 order-md-1">

      <div class="container">
        <div class="row align-items-center justify-content-center">
          <div class="col-md-7">
            <h3 class="mb-2">Reset your password</h2>
            <p>Please enter your email address. You will received a link to create a new password via email. or Return to <a href="index.php" class="text-success" ><b>Login page</b></a> </p>
            <form method="post">
              <div class="form-group last mb-4">
                <input type="email" class="form-control" placeholder="Your Email Here..." name="email" id="email" autofocus required>
              </div>
              <input type="submit" value="Send link" name="resetBtn" class="btn btn-success btn btn-block">
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