<?php
include_once('../config/session.php');

// Inactivity timeout: 10 minutes (600 seconds)
$timeout = 600;

if (isset($_SESSION['start']) && (time() - $_SESSION['start'] > $timeout)) {
    // Optionally update status for administrator if logged in
    if (isset($_SESSION['alogin'])) {
        mysqli_query($conn, "UPDATE administrator SET status ='0' WHERE admin_id='" . $_SESSION['alogin'] . "'");
    }
    // Destroy all session data
    session_unset();
    session_destroy();

    // Redirect the top-level window to login page without reloading the current page first
    echo "<script>window.top.location.href='" . SITE_URL . "index.php';</script>";
    exit();
}
?>