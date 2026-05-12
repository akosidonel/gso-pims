<?php
function check_admin_role_dynamic_redirect(array $allowedRoles = ['SYSTEM-ADMIN', 'GF/SEF-ADMIN', 'DISPOSAL-ADMIN']) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $role = $_SESSION['role'] ?? null;
    if ($role === null || !in_array($role, $allowedRoles, true)) {
        header('Location: ../index.php');
        exit();
    }
}
?>