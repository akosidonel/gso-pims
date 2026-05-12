<?php 
include_once('../config/session.php');
include('../config/check_session.php');
include_once('../config/auth_helpers.php');
include('../include/departments.php');

check_admin_role_dynamic_redirect(['SYSTEM-ADMIN', 'GF/SEF-ADMIN', 'DISPOSAL-ADMIN', 'CLEARANCE-ADMIN']);

if(!isset($_SESSION['alogin'])){
  header('Location:../index.php');
  exit();
}else {

  $departments = gso_get_departments($conn);
  usort($departments, static function (array $a, array $b): int {
    return strcasecmp((string) ($a['department_name'] ?? ''), (string) ($b['department_name'] ?? ''));
  });
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
            <h1>Employee</h1> 
          </div>
          <div class="col-sm-6">
            <ol class="breadcrumb float-sm-right">
              <li class="breadcrumb-item"><a href="dashboard.php">Home</a></li>
              <li class="breadcrumb-item">Employee</li>
              <li class="breadcrumb-item active">Manage Employee</li>
            </ol>
          </div>
        </div>
      </div><!-- /.container-fluid -->
    </section>

    <section class="content"> <!-- Main content -->
     
      <div class="card"> <!-- Default box -->
        <div class="card-header">
          <h3 class="card-title"><i class="fas fa-clipboard"></i>&nbsp; List of employees</h3>
          <div class="card-tools">
          <button type="button" class="btn btn-block bg-gradient-success btn-sm"  data-toggle="modal" data-target="#addEmployeeModal"><i class="fa-solid fa-users"></i>&nbsp; Add Employee</button> 
          <!-- add user modal -->
          <div class="modal fade" id="addEmployeeModal">
            <div class="modal-dialog modal-lg">
            <div class="modal-content">
            <div class="modal-header">
              <h5 class="modal-title">Add Employee Information</h5>
              <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                <span aria-hidden="true">&times;</span>
              </button>
            </div>
            <form class="form-horizontal" id="emp_form" method="POST" enctype="multipart/form-data">
            <div class="modal-body">
              <div class="alert alert-warning d-none"></div>
                <div class="card-body">
                  <div class="form-group">
                    <label>Name</label>
                      <input type="text" class="form-control text-uppercase" name="fname" id="fname" placeholder="Full name" required>  
                  </div>
                  <div class="form-group">
                    <label>Agency</label>
                    <input type="text" id="departmentSearch" class="form-control" list="departmentDatalist" placeholder="Type to search department" autocomplete="off" autofocus>
                    <datalist id="departmentDatalist"></datalist>
                    <select name="department" id="department" class="form-control" autocomplete="off" required style="display:none;">
                      <option value="">-SELECT-</option>
                      <?php foreach ($departments as $dept) { ?>
                        <option value="<?php echo htmlentities($dept['department_code']);?>"><?php echo htmlentities($dept['department_name']);?></option>
                      <?php } ?>
                    </select>
                  </div>
                  <div class="form-group ">
                    <label>Position</label><span class='text-gray'> (type N/A if not applicable)</span>
                      <input type="text" class="form-control text-uppercase" name="position" id="position" placeholder="Position" required>   
                  </div>
                  <div class="form-group ">
                    <label>Property Custodian?</label>
                     <select name="pcustodian" id="pcustodian" class="form-control" required>
                      <option value="">-SELECT-</option>
                      <option value="1">YES</option>
                      <option value="0">NO</option>
                     </select>
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

       <!-- edit employee modal -->
       <div class="modal fade" id="editEmployeeModal">
          <div class="modal-dialog modal-lg">
          <div class="modal-content">
            <div class="modal-header">
              <h5 class="modal-title">Edit Employee Information</h5>
              <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                <span aria-hidden="true">&times;</span>
              </button>
            </div>
            <form class="form-horizontal" id="emp_update" method="POST" enctype="multipart/form-data">
            <div class="modal-body">
              <div class="alert alert-warning d-none"></div>
                <div class="card-body">
                  <div class="form-group row">
                    <input type="hidden" name="empId" id="empId">
                    <label  class="col-sm-4 col-form-label">Name</label>
                    <div class="col-sm-8">
                      <input type="text" class="form-control text-uppercase" name="name" id="name" placeholder="Name">
                    </div>
                  </div>
                  <div class="form-group row">
                    <label class="col-sm-4 col-form-label">Department</label>
                    <div class="col-sm-8">
                    <select name="edepartment" id="edepartment" class="form-control" required>
                      <option value="">-SELECT-</option>
                      <?php foreach ($departments as $dept) { ?>
                        <option value="<?php echo htmlentities($dept['department_code']);?>"><?php echo htmlentities($dept['department_name']);?></option>
                      <?php } ?>
                    </select>
                    </div>
                  </div>
                  <div class="form-group row">
                    <label  class="col-sm-4 col-form-label">Position</label>
                    <div class="col-sm-8">
                      <input type="text" class="form-control text-uppercase" name="eposition" id="eposition" placeholder="Position">
                    </div>
                  </div>  
                  <div class="form-group row">
                    <label  class="col-sm-4 col-form-label">Property Custodian?</label>
                    <div class="col-sm-8">
                    <select name="epcustodian" id="epcustodian" class="form-control">
                      <option value="">-SELECT-</option>
                      <option value="1">YES</option>
                      <option value="0">NO</option>
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
                    <th class="col-sm-2">EMPLOYEE NAME</th>
                    <th class="col-sm-2">POSITION</th>
                    <th class="col-sm-3">DEPARTMENT</th>
                    <th class="text-center col-sm-1">STATUS</th>
                    <th class="text-center col-sm-1">ACTION</th>
                  </tr>
                  </thead>
                  <tbody>
                    <?php
                    $query = "SELECT e.emp_name, e.emp_id as empid,e.department_code,e.position,e.emp_status,
                    d.department_code,d.department_name as office
                    FROM employee AS e JOIN department AS d ON e.department_code = d.department_code";
                    $results = mysqli_query($conn, $query);
                    if(mysqli_num_rows($results)){
                      foreach($results as $result){?>
                      <tr>
                        <td><?=$result['emp_name']?></td>
                        <td><?=$result['position']?></td>
                        <td><?=$result['office']?></td>
                        <td class="text-center"><?php $stats=$result['emp_status'];
                        if($stats==1){?>
                        <b class="text-success">ACTIVE</b>
                        <?php } if($stats==0)  { ?>
                        <b class="badge badge-danger">RETIRED</b>
                        <?php } if($stats==2)  { ?>
                        <b class="badge badge-danger">END OF CONTRACT</b>
                        <?php } ?>
                      </td>
                   
                        <td class="text-center">
                          <button type="submit" value="<?= $result['empid']; ?>" class="editEmployee btn btn-sm btn-success" data-toggle="modal" data-target="#editEmployeeModal"><i class="fas fa-edit" data-toggle="popover" data-content="Edit" data-trigger="hover"></i></button>
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
  <?php include('../include/footer.php')?><!--footer-->
</div><!-- ./wrapper -->
<?php include('../include/script.php')?><!--script-->


<?php }?>

<script>
(function() {
  var $deptSelect = $('#department');
  var $deptInput = $('#departmentSearch');
  var $deptDatalist = $('#departmentDatalist');
  var $modal = $('#addEmployeeModal');
  var $form = $('#emp_form');

  if (!$deptSelect.length || !$deptInput.length) return;

  function populateDatalist() {
    $deptDatalist.empty();
    $deptSelect.find('option').each(function() {
      var name = $(this).text().trim();
      var val = $(this).val();
      if (!val) return; // skip placeholder
      $('<option>').attr('value', name).appendTo($deptDatalist);
    });
  }

  function findCodeByExactName(name) {
    var code = '';
    $deptSelect.find('option').each(function(){
      var optName = $(this).text().trim();
      if (optName.toLowerCase() === String(name||'').trim().toLowerCase()) {
        code = $(this).val();
        return false;
      }
    });
    return code;
  }

  // Sync select -> input when select changes (authoritative)
  $deptSelect.on('change', function() {
    var code = $(this).val();
    var name = '';
    if (code) {
      name = $(this).find('option:selected').text().trim();
    }
    $deptInput.val(name);
  });

  // Input behaviors
  var wasSetFromSelect = false;
  var clearedForRetype = false;

  $deptInput.on('change', function() {
    var name = $(this).val();
    var code = findCodeByExactName(name);
    if (code) {
      $deptSelect.val(code).trigger('change');
      wasSetFromSelect = true;
      clearedForRetype = false;
    } else {
      $deptSelect.val('').trigger('change');
    }
  });

  // Clear on first retype after a selection
  function markClearedIfNeeded(e) {
    if (wasSetFromSelect && !clearedForRetype) {
      // Ignore navigation keys
      var key = e && e.key;
      if (e && (e.ctrlKey || e.metaKey || e.altKey)) return;
      if (key && ['Tab','Shift','ArrowLeft','ArrowRight','ArrowUp','ArrowDown','Home','End'].includes(key)) return;
      $deptInput.val('');
      $deptSelect.val('').trigger('change');
      clearedForRetype = true;
    }
  }

  $deptInput.on('beforeinput', markClearedIfNeeded);
  $deptInput.on('keydown', function(e){
    if (e.key === 'Tab') return;
    markClearedIfNeeded(e);
  });
  $deptInput.on('paste compositionstart', function(e){ markClearedIfNeeded(e); });

  // If input emptied, clear select immediately (resets dependent UI)
  $deptInput.on('input', function(){
    if (!$(this).val()) {
      $deptSelect.val('').trigger('change');
    }
  });

  // Clicking near the right edge (native datalist icon area) should clear to show list
  $deptInput.on('mousedown', function(e){
    var el = this;
    var rect = el.getBoundingClientRect();
    var fromRight = rect.right - e.clientX;
    if (fromRight <= 28) { // approx width of the icon area
      // Defer to let native dropdown open
      setTimeout(function(){
        $deptInput.val('');
        $deptSelect.val('').trigger('change');
      }, 0);
    }
  });

  // Modal lifecycle
  $modal.on('shown.bs.modal', function(){
    populateDatalist();
    var code = $deptSelect.val();
    var name = code ? $deptSelect.find('option:selected').text().trim() : '';
    $deptInput.val(name);
    wasSetFromSelect = !!code;
    clearedForRetype = false;
    setTimeout(function(){ $deptInput.trigger('focus'); }, 100);
  });
  $modal.on('hidden.bs.modal', function(){
    $deptInput.val('');
    $deptSelect.val('');
    wasSetFromSelect = false;
    clearedForRetype = false;
  });

  // Basic validation on submit: require a valid department (exact match)
  $form.on('submit', function(e){
    var name = $deptInput.val().trim();
    var code = findCodeByExactName(name);
    if (!code) {
      e.preventDefault();
      $deptInput.addClass('is-invalid');
      if (!$deptInput.next('.invalid-feedback').length) {
        $('<div class="invalid-feedback">Please select a valid department from the list.</div>').insertAfter($deptInput);
      }
      $deptInput.focus();
      return false;
    } else {
      $deptInput.removeClass('is-invalid');
      $deptInput.next('.invalid-feedback').remove();
      $deptSelect.val(code);
    }
  });

  // Clear validation feedback on change
  $deptInput.on('input change', function(){
    $(this).removeClass('is-invalid');
    $(this).next('.invalid-feedback').remove();
  });

  // Initial datalist population in case modal already open
  populateDatalist();
})();
</script>