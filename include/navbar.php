<?php
$adminName = isset($_SESSION['admin_name']) ? trim((string)$_SESSION['admin_name']) : '';
$roleLabel = isset($_SESSION['role']) ? strtoupper(trim((string)$_SESSION['role'])) : '';

// Fallback for older sessions where admin_name wasn't set at login
if ($adminName === '' && isset($_SESSION['alogin']) && isset($conn)) {
  $adminId = mysqli_real_escape_string($conn, (string)$_SESSION['alogin']);
  $q = mysqli_query($conn, "SELECT first_name, last_name FROM administrator WHERE admin_id='$adminId' LIMIT 1");
  if ($q && mysqli_num_rows($q) === 1) {
    $row = mysqli_fetch_assoc($q);
    $adminName = trim(((string)($row['first_name'] ?? '')) . ' ' . ((string)($row['last_name'] ?? '')));
    if ($adminName !== '') {
      $_SESSION['admin_name'] = $adminName;
    }
  }
}

$adminNameDisplay = $adminName !== '' ? $adminName : 'Administrator';
?>

<nav class="main-header navbar navbar-expand navbar-white navbar-light">
    <!-- Left navbar links -->
    <ul class="navbar-nav">
      <li class="nav-item">
        <a class="nav-link" data-widget="pushmenu" href="#" role="button"><i class="fas fa-bars"></i></a>
      </li>
    </ul>

    <!-- Right navbar links -->
    <ul class="navbar-nav ml-auto">
      <!-- Realtime notifications (driven by assets/dist/js/script.js) -->
      <li class="nav-item dropdown" id="rtNotifNavItem" style="display:none;">
        <a class="nav-link" data-toggle="dropdown" href="#" aria-haspopup="true" aria-expanded="false" id="rtNotifBellLink" title="Notifications">
          <i class="far fa-bell fa-lg" style="font-size: 1.35rem; line-height: 1;"></i>
          <span class="badge badge-danger navbar-badge" id="rtNotifBadge" style="display:none; top: 4px; right: 2px;">0</span>
        </a>
        <div class="dropdown-menu dropdown-menu-right" style="min-width: 320px; max-width: 420px;">
          <span class="dropdown-item dropdown-header" id="rtNotifHeader">Notifications</span>
          <div class="dropdown-divider"></div>
          <div id="rtNotifList" style="max-height: 320px; overflow:auto;"></div>
          <div class="dropdown-divider"></div>
              <a href="../services/clearance.php" class="dropdown-item dropdown-footer" id="rtNotifMarkAll">Open Property Clearance</a>
        </div>
      </li>

      <li class="nav-item dropdown">
        <a class="nav-link" data-toggle="dropdown" href="#" aria-haspopup="true" aria-expanded="false">
          <i class="far fa-user"></i>
          <span class="d-none d-sm-inline ml-1"><?php echo htmlspecialchars($adminNameDisplay); ?></span>
        </a>
        <div class="dropdown-menu dropdown-menu-right">
          <span class="dropdown-item dropdown-header"><?php echo htmlspecialchars($roleLabel !== '' ? $roleLabel : 'ADMIN'); ?></span>
          <div class="dropdown-divider"></div>
          <a href="../logout.php" class="dropdown-item text-danger">
            <i class="fas fa-sign-out-alt mr-2"></i> Logout
          </a>
        </div>
      </li>
    </ul>
  </nav>