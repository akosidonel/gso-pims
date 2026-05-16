<!DOCTYPE html>
<html lang="en">
<head>
  <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Administrator | GSO</title>

  
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@500&display=swap" rel="stylesheet"><!-- Google Font: Montserrat -->
  <link rel="stylesheet" href="../assets/plugins/fontawesome-free/css/all.min.css">  <!-- Font Awesome -->
  <link rel="stylesheet" href="../assets/dist/css/adminlte.min.css">  <!-- Theme style -->
  <link rel="stylesheet" href="../assets/plugins/datatables-bs4/css/dataTables.bootstrap4.min.css"><!-- DataTables -->
  <link rel="stylesheet" href="../assets/plugins/datatables-responsive/css/responsive.bootstrap4.min.css">
  <link rel="stylesheet" href="../assets/plugins/sweetalert2-theme-bootstrap-4/bootstrap-4.min.css">
  <link rel="stylesheet" href="../assets/plugins/datatables-buttons/css/buttons.bootstrap4.min.css">
  <link rel="stylesheet" href="../assets/plugins/datepicker/bootstrap-datepicker.min.css">
  <link rel="stylesheet" href="../assets/plugins/select2/css/select2.min.css">
  <link rel="stylesheet" href="../assets/plugins/select2-bootstrap4-theme/select2-bootstrap4.min.css">
  <link rel="stylesheet" href="../assets/dist/css/rt-notifications.css?v=20260118">
  <?php $currentPage = basename((string)($_SERVER['SCRIPT_NAME'] ?? '')); ?>
  <?php
    $needsPremiumTheme = in_array($currentPage, ['dashboard.php', 'motor-vehicle-dashboard.php', 'disposal.php', 'disposal-items.php', 'unserviceable.php', 'add-return.php', 'add-item.php', 'add-infrastructure.php', 'add-land.php', 'general-fund-department.php', 'general-fund-inventory.php', 'sef-institution.php', 'sef-inventory.php', 'new-purchase-items.php'], true);
  ?>
  <?php if ($needsPremiumTheme): ?>
    <link rel="stylesheet" href="../assets/dist/css/style.css?v=20260215">
  <?php endif; ?>
  
  <style>
  @media (min-width: 768px) {
      .modal-xl {
        width: 90%;
       max-width:1500px;
      }
    }
</style>

</head>

<body class="hold-transition sidebar-mini sidebar-collapse layout-navbar-fixed layout-fixed">

<div class="wrapper"><!-- Site wrapper -->

