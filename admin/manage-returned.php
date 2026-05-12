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
            <h1>Return of Equipment</h1>
          </div>
          <div class="col-sm-6">
            <ol class="breadcrumb float-sm-right">
              <li class="breadcrumb-item"><a href="dashboard.php">Home</a></li>
              <li class="breadcrumb-item active">Return of equipment</li>
            </ol>
          </div>
        </div>
      </div><!-- /.container-fluid -->
    </section>

   
    <section class="content"> <!-- Main content -->

    <div class="card"> <!-- Default box -->
        <div class="card-header">
        <h3 class="card-title"><i class="fas fa-exchange-alt"></i>&nbsp; List of returned equipment</h3>
        </div>
        <div class="card-body">

        <div>
            <form action="">
                <table class="table table-striped" id="app">
                  <thead class="thead-dark">
                    <tr>
                      <th class="col-sm-2">P.A.R</th>
                      <th class="col-sm-2">BRAND/MODEL</th>
                      <th class="col-sm-2">SERIAL NO.1</th>
                      <th class="col-sm-2">SERIAL NO.2</th>
                      <th class="col-sm-2">END USER</th>
                      <th class="col-sm-2">CONDITION</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php
                      $did = intval($_GET['dept']);
                      $query = "SELECT p.item,p.model,p.serial_number,p.serial_number_2,p.par_number,e.emp_name
                      FROM items_user_history AS i JOIN return_to_stock AS p ON i.par_number = p.par_number WHERE g.dept_id = '$did' AND g.status = '1'"; ?>
                    <tr id="1">
                      <td><input value="2021-05-030-0001-2" type="text" id="par_number_1" name="par_number[]" class="form-control" readonly></td>
                      <td><input value="DELL INSPIRON" type="text" id="model_1" name="model[]" class="form-control" readonly></td>
                      <td><input value="G10VSH3-34890466971" type="text" id="serial_1" name="serial[]" class="form-control" readonly></td>
                      <td><input value="CN-04WX6Y-FCCOO-1AL-D94X-AO1" type="text" id="serial_2" name="serial2[]" class="form-control" readonly></td>
                      <td><input value="MARILOU TANAEL" type="text" id="enduser" name="enduser" class="form-control" readonly></td>
                      <td>
                        <select name="" id="" class="form-control" required>
                            <option value="">-SELECT-</option>
                            <option value="0">UNSERVICEABLE</option>
                            <option value="1">SERVICEABLE</option>
                        </select>
                      </td>
                    </tr>
                  </tbody>
                </table>
                <div>
                <div class="form-row">
                <div class="form-group col-md-6">
                <label for="">Department</label>
                <select name="deptid" id="deptid" class="form-control" autocomplete="off">
                      <option value="">-SELECT-</option>
                      <?php 
                        $sql = "SELECT * FROM department";
                        $query = mysqli_query($conn,$sql);
                        if(mysqli_num_rows($query) > 0){
                        foreach($query as $result){?> 
                      <option value="<?php echo htmlentities($result['department_code']);?>"><?php echo htmlentities($result['department_name']);?></option>
                      <?php }} ?>
                    </select>
                </div>
                <div class="form-group col-md-6">
                <label for="">Property Custodian</label>
                <select name="custodian" id="custodian" class="form-control" autocomplete="off">
                    <option value="">-SELECT-</option>
                </select>
                </div>
                </div>
              </div>
            <button class="btn btn-success mt-2"><i class="fa-solid fas fa-box-open"></i>&nbsp; Submit and Return to stock</button>
          </form>
          </div>


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