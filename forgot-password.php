<?php
require_once __DIR__ . '/config/session_bootstrap.php';
gso_start_secure_session();
require_once __DIR__ . '/auth/auth.php';
require_once __DIR__ . '/config/mail.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

//Load Composer's autoloader
require 'vendor/autoload.php';

function send_password_reset($getEmail,$token){
  $mailSettings = gso_mail_settings();
  $smtpHost = trim((string)($mailSettings['host'] ?? 'smtp.gmail.com'));
  $smtpPort = (int)($mailSettings['port'] ?? 465);
  $smtpUser = trim((string)($mailSettings['username'] ?? ''));
  $smtpPass = trim((string)($mailSettings['password'] ?? ''));
  $fromEmail = trim((string)($mailSettings['from_email'] ?? $smtpUser));
  $fromName = trim((string)($mailSettings['from_name'] ?? 'General Services Office'));
  $replyTo = trim((string)($mailSettings['reply_to'] ?? 'no-reply@localhost'));
  $smtpEncryption = strtolower(trim((string)($mailSettings['encryption'] ?? 'smtps')));

  if ($smtpUser === '' || $smtpPass === '' || $fromEmail === '') {
    return false;
  }

  $mail = new PHPMailer(true);

  try {
    $mail->isSMTP();
    $mail->Host = $smtpHost;
    $mail->SMTPAuth = true;
    $mail->Username = $smtpUser;
    $mail->Password = $smtpPass;
    $mail->SMTPSecure = ($smtpEncryption === 'tls')
      ? PHPMailer::ENCRYPTION_STARTTLS
      : PHPMailer::ENCRYPTION_SMTPS;
    $mail->SMTPKeepAlive = true;
    $mail->Port = $smtpPort;

    $mail->setFrom($fromEmail, $fromName);
    $mail->addAddress($getEmail);
    $mail->addReplyTo($replyTo, 'No reply');

    $url = gso_build_app_url('reset-password.php', ['token' => $token]);
    $mail->isHTML(true);
    $mail->Subject = 'Your Password reset link';
    $mail->Body = "Hello,<br>
    We've received your request to reset your P.T.M.S admin password.<br><br>
    Reset your password <a href='$url' class='btn btn-success'>here</a>";
    $mail->AltBody = 'Open this link to reset your password: ' . $url;
    $mail->send();
    return true;
  } catch (Exception $e) {
    error_log('Password reset email failed: ' . $e->getMessage());
    return false;
  }
}

$resetFormToken = gso_issue_form_token('forgot_password');

if(isset($_POST['resetBtn'])){
  if (!gso_validate_form_token('forgot_password', (string)($_POST['reset_form_token'] ?? ''), 1800, true)) {
    $_SESSION['error'] = "The reset request expired. Please try again.";
    header("Location:forgot-password.php");
    exit(0);
  }

  $email = trim((string)($_POST['email'] ?? ''));
  $token = bin2hex(random_bytes(32));
  $adminRow = gso_fetch_administrator_by_email($conn, $email);
  $expiresAt = date('Y-m-d H:i:s', time() + 3600);
  $genericSuccessMessage = "If the email is registered, a reset link will be sent shortly.";

  if($adminRow){
    $getEmail = (string)$adminRow['email'];

    if(gso_store_administrator_reset_token($conn, $getEmail, $token, $expiresAt) && send_password_reset($getEmail,$token)){
      $_SESSION['success'] = $genericSuccessMessage;
      header("Location:forgot-password.php");
      exit(0);
    }else{
      $_SESSION['error'] = "Unable to process the reset request right now.";
      header("Location:forgot-password.php");
      exit(0);
    }

  }else{
    $_SESSION['success'] = $genericSuccessMessage;
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
              <input type="hidden" name="reset_form_token" value="<?= htmlspecialchars($resetFormToken, ENT_QUOTES, 'UTF-8') ?>">
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
