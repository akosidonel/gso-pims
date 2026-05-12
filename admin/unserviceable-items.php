<?php 
include_once('../config/session.php');
include('../config/check_session.php');

if(!isset($_SESSION['alogin'])){
  header('Location:../index.php');
  exit();
}

$accountCode = isset($_GET['code']) ? trim((string)$_GET['code']) : '';
if ($accountCode === '') {
  header('Location:unserviceable.php');
  exit();
}

$accountCodeSafe = htmlspecialchars($accountCode, ENT_QUOTES);
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
            <h1>Unserviceable Items</h1>
          </div>
          <div class="col-sm-6">
            <ol class="breadcrumb float-sm-right">
              <li class="breadcrumb-item"><a href="dashboard.php">Home</a></li>
              <li class="breadcrumb-item"><a href="unserviceable.php">Unserviceable</a></li>
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
          <div id="unserviceableItemsPage" data-account-code="<?php echo $accountCodeSafe; ?>"></div>

          <table id="unserviceableItemsTable" class="table table-bordered table-hover" style="width:100%">
            <thead>
              <tr class="bg-dark text-light bg-gradient bg-opacity-150">
                <th class="col-sm-1 text-center" style="width:30px;">
                  <input type="checkbox" id="selectAllUnserviceableItems" aria-label="Select all rows">
                </th>
                <th class="col-sm-1 text-center">ACTION</th>
                <th class="col-sm-3">PARTICULAR</th>
                <th class="col-sm-2">SNID NO.1</th>
                <th class="col-sm-2">SNID NO.2</th>
                <th class="col-sm-2">PROPERTY NUMBER</th>
                <th class="col-sm-3">DEPARTMENT</th>
                <th class="col-sm-2">DATE RETURNED</th>
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
