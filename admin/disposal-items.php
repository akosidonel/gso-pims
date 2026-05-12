<?php 
include_once('../config/session.php');
include('../config/check_session.php');

include_once('../include/departments.php');

if(!isset($_SESSION['alogin'])){
  header('Location:../index.php');
  exit();
}

$accountCode = isset($_GET['code']) ? trim((string)$_GET['code']) : '';
$disposalRef = isset($_GET['ref']) ? trim((string)$_GET['ref']) : '';
if ($disposalRef === '') {
  header('Location:disposal.php');
  exit();
}
if ($accountCode === '') {
  header('Location:disposal-account-code.php?ref=' . urlencode($disposalRef));
  exit();
}

$accountCodeSafe = htmlspecialchars($accountCode, ENT_QUOTES);
$disposalRefSafe = htmlspecialchars($disposalRef, ENT_QUOTES);

$departments = [];
if (function_exists('gso_get_departments') && isset($conn) && $conn instanceof mysqli) {
  $departments = gso_get_departments($conn);
}

$next_emp_id = 1;
if (isset($conn) && $conn instanceof mysqli) {
  $resEmp = mysqli_query($conn, "SELECT MAX(emp_id) AS max_id FROM employee");
  $rowEmp = $resEmp ? mysqli_fetch_assoc($resEmp) : null;
  $next_emp_id = ($rowEmp && isset($rowEmp['max_id']) && $rowEmp['max_id'] !== null) ? ((int)$rowEmp['max_id'] + 1) : 1;
}
?>
  <?php include('../include/header.php')?><!--Header-->
  <?php include('../include/navbar.php')?><!-- Navbar -->
  <?php include('../include/sidebar.php')?><!--Sidebar-->

   <!-- Preloader -->
<div class="preloader flex-column justify-content-center align-items-center">
    <img src="../assets/dist/img/spin.gif" alt="AdminLogo" height="90" width="90">
</div>

<div id="destroy"></div>

  <div class="content-wrapper"><!-- Content Wrapper. Contains page content -->
    <section class="content-header"> <!-- Content Header (Page header) -->
      <div class="container-fluid">
        <div class="row mb-2">
          <div class="col-sm-6">
            <h1>Disposal Items</h1>
          </div>
          <div class="col-sm-6">
            <ol class="breadcrumb float-sm-right">
              <li class="breadcrumb-item"><a href="dashboard.php">Home</a></li>
              <li class="breadcrumb-item"><a href="disposal-account-code.php?ref=<?php echo urlencode($disposalRef); ?>">Disposal</a></li>
              <li class="breadcrumb-item active">Account Code</li>
            </ol>
          </div>
        </div>
      </div><!-- /.container-fluid -->
    </section>

    <section class="content"> <!-- Main content -->
      <div class="card"> <!-- Default box -->
        <div class="card-header">
          <h3 class="card-title"><i class="fas fa-dolly-flatbed"></i>&nbsp; Account Code: <b><?php echo $accountCodeSafe; ?></b></h3>
        </div>

        <div class="card-body">
          <div id="disposalItemsPage" data-account-code="<?php echo $accountCodeSafe; ?>" data-disposal-ref="<?php echo $disposalRefSafe; ?>"></div>

          <table id="disposalItemsTable" class="table table-bordered table-hover" style="width:100%">
            <thead>
              <tr class="bg-dark text-light bg-gradient bg-opacity-150">
                <th class="col-sm-1">FUND</th>
                <th class="col-sm-1">CAT.</th>
                <th class="col-sm-1">QTY</th>
                <th class="col-sm-3">PARTICULAR</th>
                <th class="col-sm-2">PROPERTY NUMBER</th>
                <th class="col-sm-2">UNIT COST</th>
                <th class="col-sm-1">APPRAISAL VALUE (FMV)</th>
                <th class="col-sm-1">TOTAL APPRAISAL VALUE</th>
              </tr>
            </thead>
            <tbody></tbody>
          </table>
        </div>
      </div>
    </section><!-- /.content -->
  </div><!-- /.content-wrapper -->

  <?php include('../include/footer.php') ?><!--footer-->
</div><!-- ./wrapper -->

<?php include('../include/script.php')?><!--script-->
</body>
</html>
