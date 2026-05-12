<?php 
include_once('../config/session.php');
include('../config/check_session.php');

if(!isset($_SESSION['alogin'])){
  header('Location:../index.php');
  exit();
}

$disposalRef = isset($_GET['ref']) ? trim((string)$_GET['ref']) : '';
if ($disposalRef === '') {
  header('Location:disposal.php');
  exit();
}

$disposalRefSafe = htmlspecialchars($disposalRef, ENT_QUOTES);
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
            <h1>Disposal</h1>
          </div>
          <div class="col-sm-6">
            <ol class="breadcrumb float-sm-right">
              <li class="breadcrumb-item"><a href="dashboard.php">Home</a></li>
              <li class="breadcrumb-item"><a href="disposal.php">Disposal</a></li>
              <li class="breadcrumb-item active">Activity</li>
            </ol>
          </div>
        </div>
      </div><!-- /.container-fluid -->
    </section>

    <section class="content"> <!-- Main content -->
      <div class="card"> <!-- Default box -->
        <div class="card-header">
          <h3 class="card-title"><i class="fas fa-dolly-flatbed"></i>&nbsp; Disposal Activity: <b><?php echo $disposalRefSafe; ?></b></h3>
        </div>

        <div class="card-body">
          <div id="disposalAccountCodesPage" data-disposal-ref="<?php echo $disposalRefSafe; ?>"></div>
          <table id="disposalAccountCodesTable" class="table table-bordered table-hover" style="width:100%">
            <thead>
              <tr class="bg-dark text-light bg-gradient bg-opacity-150">
                <th class="col-sm-2">ACCOUNT CODE</th>
                <th class="col-sm-4">ACCOUNT NAME</th>
                <th class="col-sm-2">ITEMS</th>
                <th class="col-sm-2">TOTAL APPRAISAL VALUE</th>
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
