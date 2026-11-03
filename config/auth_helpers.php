<?php
function gso_admin_landing_page($role) {
    if (!is_string($role)) {
        return null;
    }

    switch ($role) {
        case 'SYSTEM-ADMIN':
        case 'GF/SEF-ADMIN':
        case 'DISPOSAL-ADMIN':
            return 'admin/dashboard.php';
        case 'MV-ADMIN':
            return 'admin/motor-vehicle-dashboard.php';
        case 'CLEARANCE-ADMIN':
            return 'services/clearance.php';
        default:
            return null;
    }
}

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
