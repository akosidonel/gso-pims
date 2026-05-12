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
            <h1>Clearance Type</h1> 
          </div>
          <div class="col-sm-6">
            <ol class="breadcrumb float-sm-right">
              <li class="breadcrumb-item"><a href="#">Home</a></li>
              <li class="breadcrumb-item">Services</li>
              <li class="breadcrumb-item active">Clearance Type</li>
            </ol>
          </div>
        </div>
      </div><!-- /.container-fluid -->
    </section>

    <section class="content"> <!-- Main content -->
      <div class="card"> <!-- Default box -->
        <div class="card-header">
          <h3 class="card-title"><i class="fas fa-clipboard"></i>&nbsp; List of Clearance Type</h3>
          <div class="card-tools">
          <button type="button" class="btn btn-block bg-gradient-success btn-sm"  data-toggle="modal" data-target="#addClearanceTypeModal"> <i class="fa-solid fa-file"></i>&nbsp; Add Clearance</button> 
          <!-- add user modal -->
          <div class="modal fade" id="addClearanceTypeModal">
            <div class="modal-dialog modal-lg">
            <div class="modal-content">
            <div class="modal-header">
              <h5 class="modal-title">Add Clearance Information</h5>
              <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                <span aria-hidden="true">&times;</span>
              </button>
            </div>
            <form class="form-horizontal" id="ct_form" method="POST" enctype="multipart/form-data">
            <div class="modal-body">

              <div class="alert alert-warning d-none"></div>

                <div class="card-body">
                  <div class="form-group">
                    <label>Clearance Name</label>
                      <input type="text" class="form-control text-uppercase" name="ctname" id="ctname" placeholder="Clearance name">  
                  </div>
                  <div class="form-group ">
                    <label>Clearance Code</label>
                      <input type="text" class="form-control text-uppercase" name="ctcode" id="ctcode" placeholder="Clearance Code">   
                  </div>   
                </div>
                <!-- /.card-body -->
                </div>
                  <div class="modal-footer">
                      <button type="submit" class="btn btn-success"><i class="fa-solid fa-floppy-disk"></i>&nbsp; Save</button>
                </div>
            </form>
          </div>
          <!-- /.modal-content -->
        </div>
        <!-- /.modal-dialog -->
      </div>

       <!-- edit user modal -->
       <div class="modal fade" id="editCtModal">
          <div class="modal-dialog modal-lg">
          <div class="modal-content">
            <div class="modal-header">
              <h5 class="modal-title">Edit Clearance Information</h5>
              <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                <span aria-hidden="true">&times;</span>
              </button>
            </div>
            <form class="form-horizontal" id="ct_update" method="POST" enctype="multipart/form-data">
            <div class="modal-body">

              <div class="alert alert-warning d-none"></div>
                <div class="card-body">
                  <div class="form-group row">
                    <input type="hidden" name="CtId" id="CtId">
                    <label  class="col-sm-4 col-form-label">Clearance Name</label>
                    <div class="col-sm-8">
                      <input type="text" class="form-control text-uppercase" name="ectname" id="ectname" placeholder="Department name">
                    </div>
                  </div>
                  <div class="form-group row">
                    <label  class="col-sm-4 col-form-label">Clearance Code</label>
                    <div class="col-sm-8">
                      <input type="text" class="form-control text-uppercase" name="ectcode" id="ectcode" placeholder="Code">
                    </div>
                  </div>   
                </div>
                <!-- /.card-body -->
                </div>
                  <div class="modal-footer">
                      <button type="submit" class="btn btn-info">Update</button>
                </div>
            </form>
          </div>
          <!-- /.modal-content -->
        </div>
        <!-- /.modal-dialog -->
      </div>
          
        </div>
        </div>

        <div class="card-body">
        <table id="example1" class="table table-bordered table-hover">
                  <thead>
                  <tr class="bg-dark text-light bg-gradient bg-opacity-150">
                    <th class="col-sm-2">CLEARANCE NAME</th>
                    <th class="col-sm-2">CLEARANCE CODE</th>
                    <th class="text-center col-sm-1">ACTION</th>
                  </tr>
                  </thead>
                  <tbody>
                    <?php
                    $query = "SELECT * FROM clearance_type";
                    $results = mysqli_query($conn, $query);
                    
                    if(mysqli_num_rows($results)){
                      foreach($results as $result){?>
                      <tr>
                        <td><?=$result['clearance_name']?></td>
                        <td><?=$result['clearance_code']?></td>
                        <td class="text-center">
                          <button type="submit" value="<?= $result['ctype_id']; ?>" class="editct btn btn-sm btn-success" data-toggle="modal" data-target="#editCtModal"><i class="fas fa-edit" data-toggle="popover" data-content="Edit" data-trigger="hover"></i></button>
                          <button type="submit" value="<?= $result['ctype_id']; ?>" class="delct btn btn-sm btn-danger"><i class="fas fa-trash" data-toggle="popover" data-content="Trash" data-trigger="hover"></i></button>
                        </td>
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
  
  <?php include('../include/footer.php') ?><!--footer-->

</div><!-- ./wrapper -->

<?php include('../include/script.php')?><!--script-->

<?php }?>