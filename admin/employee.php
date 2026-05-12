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
            <h1>Employee</h1> 
          </div>
          <div class="col-sm-6">
            <ol class="breadcrumb float-sm-right">
              <li class="breadcrumb-item"><a href="dashboard.php">Home</a></li>
              <li class="breadcrumb-item">Employee</li>
              <li class="breadcrumb-item active">Search</li>
            </ol>
          </div>
        </div>
      </div><!-- /.container-fluid -->
    </section>
    <section class="content"> <!-- Main content -->
      <div class="card"> <!-- Default box -->
        <div class="card-header">
          <h3 class="card-title"><i class="fas fa-clipboard"></i>&nbsp; List of with active accountability</h3>
    </div>
    <div class="card-body">
        <table id="example1" class="table table-bordered table-hover">
            <thead>
                  <tr class="bg-dark text-light bg-gradient bg-opacity-150">
                    <th class="col-sm-3">EMPLOYEE NAME</th>
                    <th class="col-sm-3">POSITION</th>
                    <th class="col-sm-4">DEPARTMENT</th>
                    <th class="text-center col-sm-1">ACCOUNTABILITY</th>
                  </tr>
                  </thead>
                  <tbody>
                    <?php
                    $query = "SELECT e.emp_name,e.emp_id,e.position,d.department_name as office,
                    SUM(g.status) as accnt, g.emp_id
                    FROM employee AS e
                    JOIN department  AS d ON e.department_code = d.department_code 
                    JOIN general_fund_property_history AS g ON e.emp_id = g.emp_id 
                    GROUP BY e.emp_id";
                    $results = mysqli_query($conn, $query);
                    if(mysqli_num_rows($results)){
                      foreach($results as $result){?>
                      <tr>
                        <td><a href="property-accountability.php?empid=<?=$result['emp_id']?>"><?=$result['emp_name']?></a></td>
                        <td><?=$result['position']?></td>
                        <td><?=$result['office']?></td>
                          <td class="text-center"><?php $unit=$result['accnt'];
                          if($unit==0){?>
                          <span class="badge badge-success">CLEARED</span>
                          <?php }else if($unit==1){?>
                          <span class="badge badge-secondary"><?=$unit." UNIT"?></span>
                          <?php }else {?>
                          <span class="badge badge-danger"><?=$unit." UNITS"?></span>
                          <?php }?></td>
                      </tr>
                      <?php }
                    }?>
                  </tbody>
                </table>
        </div>
        <!-- /.card-body -->
      </div>
      <!-- /.card -->
    </section><!-- /.content -->
  </div><!-- /.content-wrapper -->
  <?php include('../include/footer.php')?><!--footer-->
</div><!-- ./wrapper -->
<?php include('../include/script.php')?><!--script-->

<?php }?>