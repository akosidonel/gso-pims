<?php 
include_once('../config/session.php');
include('../config/check_session.php');

if(!isset($_SESSION['alogin'])){
  header('Location:../index.php');
  exit();
}else {

?>
  <?php include('../include/header.php')?><!--Header-->

  <?php include('../include/navbar.php')?><!-- Navbar -->

  <?php include('../include/sidebar.php')?><!--Sidebar-->

  <div id="destroy"></div>

  <div class="content-wrapper"><!-- Content Wrapper. Contains page content -->
   
    <section class="content-header"> <!-- Content Header (Page header) -->
      <div class="container-fluid">
        <div class="row mb-2">
          <div class="col-sm-6">
            <h1>Special Education Fund</h1>
          </div>
          <div class="col-sm-6">
            <ol class="breadcrumb float-sm-right">
              <li class="breadcrumb-item"><a href="dashboard.php">Home</a></li>
              <li class="breadcrumb-item active">Special Education Fund</li>
            </ol>
          </div>
        </div>
      </div><!-- /.container-fluid -->
    </section>

    <section class="content"> <!-- Main content -->
      <div class="card"> <!-- Default box -->
        <div class="card-header">
          <h3 class="card-title"><i class="fas fa-clipboard"></i>&nbsp; List of institutions</h3>
        </div>
        <div class="card-body">
        <?php
          $institutions = [];
          $instQuery = "SELECT department_code, department_name FROM department WHERE agencies = 'INSTITUTION' ORDER BY department_name ASC";
          $instRes = mysqli_query($conn, $instQuery);
          if ($instRes && mysqli_num_rows($instRes)) {
            while ($r = mysqli_fetch_assoc($instRes)) {
              $institutions[] = $r;
            }
          }
        ?>
        <table id="example1" class="table table-bordered table-hover">
                  <thead>
                  <tr class="bg-dark  text-light bg-gradient bg-opacity-150">
                    <th class="col-sm-4">DEPARTMENT NAME</th>
                    <th class="col-sm-2">CODE</th>
                  </tr>
                  </thead>
                  <tbody>
               <?php
              if(!empty($institutions)){
                foreach($institutions as $row){?>
              <tr>
                <td><a href="sef-inventory.php?dept=<?=$row['department_code']?>"><?=$row['department_name']?></a></td>
                <td><?=$row['department_code']?></td>
              </tr>
            
           <?php }               
               } ?>
                 
                  </tbody>
                </table>
        </div>
        <!-- /.card-body -->
      </div>
      <!-- /.card -->

    </section><!-- /.content -->
    
  </div><!-- /.content-wrapper -->

  <!-- Export/Print Modal (SEF Institution) -->
  <div class="modal fade" id="sefInventoryExportModal" tabindex="-1" role="dialog" aria-labelledby="sefInventoryExportModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
      <div class="modal-content gso-modal">
        <div class="modal-header border-0 p-0">
          <div class="gso-hero w-100" style="border-radius:0; box-shadow:none;">
            <div class="card-body py-3">
              <div class="d-flex align-items-start justify-content-between flex-wrap">
                <div class="mb-2 mb-md-0">
                  <div class="gso-kicker">Special Education Fund</div>
                  <div class="gso-title" id="sefInventoryExportModalLabel" style="font-size:22px;">Inventory Report</div>
                  <div class="gso-meta">Choose category and institution to export or print.</div>
                </div>

                <button type="button" class="close text-white ml-3" data-dismiss="modal" aria-label="Close">
                  <span aria-hidden="true">&times;</span>
                </button>
              </div>
            </div>
          </div>
        </div>

        <div class="modal-body">
          <div class="form-row">
            <div class="form-group col-md-6">
              <label for="sefExportCategory">Category</label>
              <select id="sefExportCategory" class="form-control">
                <option value="ALL">All (PAR &amp; ICS)</option>
                <option value="PAR">PAR</option>
                <option value="ICS">ICS</option>
              </select>
              <small class="text-muted">Select what type of property records to include.</small>
            </div>

            <div class="form-group col-md-6 mb-0">
              <label for="sefExportDept">Institution</label>
              <select id="sefExportDept" class="form-control">
                <option value="">All Institutions</option>
                <?php foreach ($institutions as $d): ?>
                  <option value="<?php echo htmlspecialchars((string)$d['department_code'], ENT_QUOTES); ?>">
                    <?php echo htmlspecialchars((string)$d['department_name'], ENT_QUOTES); ?>
                  </option>
                <?php endforeach; ?>
              </select>
              <small class="text-muted">Leave as “All Institutions” to include everything.</small>
            </div>
          </div>
        </div>

        <div class="modal-footer d-flex justify-content-end">
          <div>
            <button type="button" class="btn btn-success" id="sefPrintConfirm">
              <i class="fas fa-print mr-1"></i> Print
            </button>
            <button type="button" class="btn btn-primary" id="sefExportConfirm">
              <i class="fas fa-file-excel mr-1"></i> Export
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
  
  <?php include('../include/footer.php') ?><!--footer-->

</div><!-- ./wrapper -->

<?php include('../include/script.php')?><!--script-->

<?php }?>