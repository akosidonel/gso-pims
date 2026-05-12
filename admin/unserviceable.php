<?php 
include_once('../config/session.php');
include('../config/check_session.php');

include_once('../include/departments.php');

if(!isset($_SESSION['alogin'])){
  header('Location:../index.php');
  exit();
}else {

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

$accountCodes = [];
if (isset($conn) && $conn instanceof mysqli) {
  $resAcct = mysqli_query($conn, "SELECT account_code, account_name FROM account_code ORDER BY account_code ASC");
  if ($resAcct) {
    while ($rowAcct = mysqli_fetch_assoc($resAcct)) {
      if (!$rowAcct) { continue; }
      $accountCodes[] = $rowAcct;
    }
  }
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
            <h1>Unserviceable Property</h1>
          </div>
          <div class="col-sm-6">
            <ol class="breadcrumb float-sm-right">
              <li class="breadcrumb-item"><a href="dashboard.php">Home</a></li>
              <li class="breadcrumb-item"><a href="general-fund-department.php">Return</a></li>
              <li class="breadcrumb-item active">Unserviceable</li>
            </ol>
          </div>
        </div>
      </div><!-- /.container-fluid -->
    </section>

   
    <section class="content"> <!-- Main content -->

     
      <div class="card"> <!-- Default box -->
        <div class="card-header">
        <h3 class="card-title"><i class="fas fa-dolly-flatbed"></i>&nbsp; Unserviceable items by account code</h3>
      </div>

 
        <div class="card-body">
        <table id="unserviceableAccountCodesTable" class="table table-bordered table-hover" style="width:100%">
                  <thead>
                  <tr class="bg-dark text-light bg-gradient bg-opacity-150">
                    <th class="col-sm-2">ACCOUNT CODE</th>
                    <th class="col-sm-4">ACCOUNT NAME</th>
                    <th class="col-sm-2">ITEMS</th>
                    <th class="col-sm-2">TOTAL VALUE</th>
                  </tr>
                  </thead>
                  <tbody></tbody>
                </table>

        </div>
        <!-- /.card-body -->
      </div>
      <!-- /.card -->

    </section><!-- /.content -->
    
  </div><!-- /.content-wrapper -->
  
  <?php include('../include/footer.php') ?><!--footer-->

</div><!-- ./wrapper -->

<?php include('../include/script.php')?><!--script-->

<?php }?>