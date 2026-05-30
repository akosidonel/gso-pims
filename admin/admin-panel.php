<?php 
include_once('../config/session.php');
include('../config/check_session.php');
require_once('../auth/auth.php');

if(!isset($_SESSION['alogin'])){
  header('Location:../index.php');
  exit();
} elseif (!gso_user_has_role(['SYSTEM-ADMIN'])) {
  header('Location:../index.php');
  exit();
}else {
  $adminPanelToken = gso_issue_form_token('admin_panel');
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
            <h1>Admin Panel</h1>
          </div>
          <div class="col-sm-6">
            <ol class="breadcrumb float-sm-right">
              <li class="breadcrumb-item"><a href="#">Home</a></li>
              <li class="breadcrumb-item active">Admin Panel</li>
            </ol>
          </div>
        </div>
      </div><!-- /.container-fluid -->
    </section>

    <section class="content"> <!-- Main content -->
     
      <div class="card"> <!-- Default box -->
        <div class="card-header">
          <h3 class="card-title"><i class="fas fa-clipboard"></i>&nbsp; List of Administrator</h3>

          <div class="card-tools">
          <button type="button" class="btn btn-block bg-gradient-success btn-sm"  data-toggle="modal" data-target="#addAdministrator"> <i class="fas fa-user-plus"></i>&nbsp; Add Administrator</button> 
          <!-- add user modal -->
          <div class="modal fade" id="addAdministrator">
            <div class="modal-dialog modal-lg">
            <div class="modal-content">
            <div class="modal-header">
              <h5 class="modal-title"><i class="fa-solid fa-user-plus"></i>&nbsp; Add Administrator Information</h5>
              <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                <span aria-hidden="true">&times;</span>
              </button>
            </div>
	            <form class="form-horizontal" id="admin_form" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="admin_form_token" id="admin_form_token" value="<?= htmlspecialchars($adminPanelToken, ENT_QUOTES, 'UTF-8') ?>">
	            <div class="modal-body">

              <div class="alert alert-warning d-none"></div>

                <div class="card-body">
                  <div class="form-group row">
                    <label class="col-sm-3 col-form-label">First name</label>
                    <div class="validation col-sm-9">
                      <input type="text" class="form-control text-uppercase" name="fname" id="fname" placeholder="First name">
                    </div>
                  </div>
                  <div class="form-group row">
                    <label  class="col-sm-3 col-form-label">Last name</label>
                    <div class="validation col-sm-9">
                      <input type="text" class="form-control text-uppercase" name="lname" id="lname" placeholder="Last name">
                    </div>
                  </div> 
                  <div class="form-group row">
                    <label  class="col-sm-3 col-form-label">Email</label>
                    <div class="validation col-sm-9">
                      <input type="email" class="form-control" name="email" id="email" placeholder="EMAIL">
                    </div>
                  </div> 
                  <div class="form-group row">
                    <label  class="col-sm-3 col-form-label">Phone No.:</label>
                    <div class="validation col-sm-9">
                      <input type="text" class="form-control text-uppercase" name="contact" id="contact" placeholder="Phone number" maxlength="11">
                    </div>
                  </div>
                  <div class="form-group row">
                    <label class="col-sm-3 col-form-label">Employee ID No. :</label>
                    <div class="validation col-sm-9">
                      <input type="text" class="form-control text-uppercase" name="emp_number" id="emp_number" placeholder="Employee Number." maxlength="8">
                    </div>
                  </div>
                  <div class="form-group row">
                    <label class="col-sm-3 col-form-label">User role</label>
                    <div class="validation col-sm-9">
                        <select class="form-control text-uppercase" name="role" id="role">
                          <option value="">-SELECT-</option>
                          <option value="DISPOSAL-ADMIN">DISPOSAL-ADMIN</option>
                          <option value="GF/SEF-ADMIN">GF/SEF-ADMIN</option>
                          <option value="MV-ADMIN">MV-ADMIN</option>
                          <option value="SYSTEM-ADMIN">SYSTEM-ADMIN</option>
                          <option value="USER">USER</option>
                        </select>
                    </div>
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
       <div class="modal fade" id="updateAdministrator">
          <div class="modal-dialog modal-lg">
          <div class="modal-content">
            <div class="modal-header">
              <h5 class="modal-title">Update Admin Information</h5>
              <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                <span aria-hidden="true">&times;</span>
              </button>
            </div>
	            <form class="form-horizontal" id="admin_update" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="admin_form_token" value="<?= htmlspecialchars($adminPanelToken, ENT_QUOTES, 'UTF-8') ?>">
	            <div class="modal-body">
                <div class="card-body">
                <input type="hidden" name="id" id="id" value="">
                  <div class="form-group row">
                    <label class="col-sm-3 col-form-label">First name</label>
                    <div class="col-sm-9">
                      <input type="text" class="form-control" name="efname" id="efname" placeholder="First name">
                    </div>
                  </div>
                  <div class="form-group row">
                    <label class="col-sm-3 col-form-label">Last name</label>
                    <div class="col-sm-9">
                      <input type="text" class="form-control" name="elname" id="elname" placeholder="Last name">
                    </div>
                  </div> 
                  <div class="form-group row">
                    <label  class="col-sm-3 col-form-label">Email</label>
                    <div class="col-sm-9">
                      <input type="email" class="form-control" name="eemail" id="eemail" placeholder="Email">
                    </div>
                  </div> 
                  <div class="form-group row">
                    <label  class="col-sm-3 col-form-label">Phone No.</label>
                    <div class="col-sm-9">
                      <input type="" class="form-control" name="econtact" id="econtact" placeholder="Phone number">
                    </div>
                  </div>
                  <div class="form-group row">
                    <label class="col-sm-3 col-form-label">Employee Number</label>
                    <div class="col-sm-9">
                      <input type="text" class="form-control" name="empnumber" id="empnumber" placeholder="EMPLOYEE NUMBER">
                    </div>
                  </div>
                  <div class="form-group row">
                    <label class="col-sm-3 col-form-label">User role</label>
                    <div class="col-sm-9">
                        <select class="form-control" name="erole" id="erole">
                          <option value="">-SELECT-</option>
                          <option value="DISPOSAL-ADMIN">DISPOSAL-ADMIN</option>
                          <option value="GF/SEF-ADMIN">GF/SEF-ADMIN</option>
                          <option value="MV-ADMIN">MV-ADMIN</option>
                          <option value="SYSTEM-ADMIN">SYSTEM-ADMIN</option>
                          <option value="USER">USER</option>
                        </select>
                    </div>
                  </div>

                </div>
                <!-- /.card-body -->
          
                </div>
                  <div class="modal-footer">
                      <button type="submit" class="btn btn-success"><i class="fa-solid fa-floppy-disk"></i>&nbsp; Update</button>
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
                    <th class="col-sm-2">ADMIN</th>
                    <th class="col-sm-2">EMP NO.</th>
                    <th class="col-sm-2">CONTACT</th>
                    <th class="col-sm-2">ROLE</th>
                    <th class="col-sm-2">STATUS</th>
                    <th class="col-sm-1 text-center">ACTION</th>
                  </tr>
                </thead>
              <tbody>
	                <?php  
	                $timeoutSeconds = 90;
	                $results = gso_fetch_admin_panel_rows($conn, $timeoutSeconds);
	                if(!empty($results)){
	                  foreach($results as $row){ ?>
                        <tr>
                          <td><?=$row['first_name']." ".$row['last_name']?></td>
                          <td><?=$row['emp_number']?></td>
                          <td><?=$row['contact_number']?></td>
                          <td><?=$row['role']?></td>
                          <td class="presenceStatus" data-admin-id="<?= htmlspecialchars((string)$row['admin_id'], ENT_QUOTES) ?>"><?php
                            $isOnline = ((int)($row['is_online'] ?? 0) === 1);
                            if($isOnline){
                              echo '<span class="badge badge-success">ONLINE</span>';
                            } else {
                              echo '<span class="badge badge-dark">OFFLINE</span>';
                            }
                          ?></td>
                          <td>
                            <button type="button" value="<?= $row['admin_id'] ?>" class="editAdmin btn btn-sm btn-success" data-toggle="modal" data-target="#"><i class="fas fa-edit" data-toggle="popover" data-content="Edit" data-trigger="hover"></i></button>
                            <button type="button" value="<?= $row['admin_id'] ?>" class="delAdmin btn btn-sm btn-danger"><i class="fas fa-trash" data-toggle="popover" data-content="Trash" data-trigger="hover"></i></button>
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

</body>
</html>

<?php }?>
