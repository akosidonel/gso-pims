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
            <h1>Property Accountabilty</h1>
          </div>
          <div class="col-sm-6">
            <ol class="breadcrumb float-sm-right">
              <li class="breadcrumb-item"><a href="dashboard.php">Home</a></li>
              <li class="breadcrumb-item"><a href="employee.php">Employee</a></li>
              <li class="breadcrumb-item active">Property Accountability</li>
            </ol>
          </div>
        </div>
      </div><!-- /.container-fluid -->
    </section>

   
    <section class="content"> <!-- Main content -->
     
     
      <div class="card"> <!-- Default box -->
        <div class="card-header">
          <h3 class="card-title"><i class="fas fa-clipboard"></i>&nbsp; List of accountabilty</h3>
        </div>
        <div class="card-body">
      
                  <table id="dataTable" class="table table-bordered table-hover">
                  <thead>
                  <tr class="bg-dark text-light bg-gradient bg-opacity-150">
                    <th class="col-sm-2">ARTICLE</th>
                    <th class="col-sm-1">BRAND/MODEL</th>
                    <th class="col-sm-2">SNID NO.1</th>
                    <th class="col-sm-2">SNID NO.2</th>
                    <th class="col-sm-2">P.A.R NO.</th>
                  </tr>
                  </thead>
                  <tbody>
                  <?php
                    $par = intval($_GET['empid']);
                    $sql = "SELECT gh.emp_id,gh.par_number,gh.status,p.item,p.model,p.serial_number,p.serial_number_2,p.par_number
                    FROM general_fund_property_history AS gh JOIN par_gen_fund AS p ON gh.par_number = p.par_number WHERE gh.emp_id = '$par' AND gh.status = '1' ";
                    $result =mysqli_query($conn,$sql) ;
                    if(mysqli_num_rows($result)){
                      foreach($result as $row){?>
                      <tr>
                        <td><?=$row['item']?></td>
                        <td><?=$row['model']?></td>
                        <td ><?php $sn1=$row['serial_number'];
                      if($sn1==""){?>
                        <span class="text-dark">NULL</span> 
                      <?php } else { ?> 
                      <?php
                        echo $row['serial_number'];
                      }?></td>
                      <td ><?php $sn2 = $row['serial_number_2'];
                      if($sn2==""){?>
                          <span class="text-dark">NULL</span> 
                      <?php } else {?>
                      <?php
                        echo $row['serial_number_2'];
                      }?></td>
                        <td><?=$row['par_number']?></td>
                      </tr>
                    <?php  }}?>
                  </tbody>
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

<script>
   $("#example2").DataTable({// dataTable function
    "responsive": true, "lengthChange": false, "autoWidth": false
  });
</script>

<?php }?>