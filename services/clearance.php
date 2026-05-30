<?php 
include_once('../config/session.php');
include('../config/check_session.php');
require_once('../auth/auth.php');

if(!isset($_SESSION['alogin'])){
  header('Location:../index.php');
  exit();
}else {
?>
  <?php include('../include/header.php')?><!--Header-->
  
  <?php include('../include/navbar.php')?><!-- Navbar -->

  <?php include('../include/sidebar.php')?><!--Sidebar-->
  <?php
        $pass = substr(str_shuffle("0123456789"), 0, 7);
        $date = date("Y");
        $code = $date."-".$pass;
  ?>

  <?php
    // Fetch departments once
    $departments = gso_fetch_departments($conn);
    // Prepare clearance types for the modal
    // NOTE: Do not hard-code a name list here; it causes newly-added clearance types
    // (or names with different casing/spacing) to never appear in the dropdown.
    $propertyClearanceTypes = gso_fetch_clearance_types($conn);
   // City options as a PHP array
  $cities = [
      "ANTIPOLO CITY RIZAL","BACOOR CITY","BULACAN","CALOOCAN CITY","DASMARIÑAS CITY CAVITE","IMUS CAVITE","LAS PIÑAS CITY","MAKATI CITY","MALABON CITY",
      "MANDALUYONG CITY","MANILA CITY","MARIKINA CITY","MUNTINLUPA CITY","NAVOTAS CITY","PARAÑAQUE CITY","PASAY CITY",
      "PASIG CITY","QUEZON CITY","SAN JUAN CITY","TAGUIG CITY","VALENZUELA CITY","TANZA CAVITE","CAVITE CITY","GENERAL TRIAS CAVITE","NAIC CAVITE","INDANG CAVITE",
      "TRECE MARTIRES CAVITE","SAN PEDRO LAGUNA","BIÑAN CITY","CABUYAO CITY","CALAMBA CITY","STA. ROSA CITY","LAGUNA",
  ];
  // Function to render department dropdown
  function renderDepartmentDropdown($id, $name, $departments) {
      echo "<select name=\"$name\" id=\"$id\" class=\"form-control\" autocomplete=\"off\" required>";
      echo "<option value=\"\">-SELECT-</option>";
      foreach ($departments as $dept) {
          echo "<option value=\"".htmlspecialchars($dept['department_code'])."\">".htmlspecialchars($dept['department_name'])."</option>";
      }
      echo "</select>";
  }

    // Function to render department searchable (type-to-search) UI
    // Uses an <input list> for fast typing + a hidden <select> that holds the actual department_code value.
    function renderDepartmentAutocomplete($selectId, $selectName, $departments, $inputId, $datalistId) {
      echo "<input type=\"text\" id=\"".htmlspecialchars($inputId)."\" class=\"form-control mb-2 text-uppercase\" placeholder=\"Type to search department\" list=\"".htmlspecialchars($datalistId)."\" autocomplete=\"off\">";
      echo "<datalist id=\"".htmlspecialchars($datalistId)."\"></datalist>";
      echo "<select name=\"".htmlspecialchars($selectName)."\" id=\"".htmlspecialchars($selectId)."\" class=\"form-control\" autocomplete=\"off\" required style=\"display:none;\">";
      echo "<option value=\"\">-SELECT-</option>";
      foreach ($departments as $dept) {
        echo "<option value=\"".htmlspecialchars($dept['department_code'])."\">".htmlspecialchars($dept['department_name'])."</option>";
      }
      echo "</select>";
    }
  // Function to render city dropdown
  function renderCityDropdown($id, $name, $cities) {
    echo "<select name=\"$name\" id=\"$id\" class=\"form-control\" required>";
    echo "<option value=\"\">-SELECT-</option>";
    foreach ($cities as $city) {
        echo "<option value=\"".htmlspecialchars($city)."\">".htmlspecialchars($city)."</option>";
    }
    echo "</select>";
  }
  // Function to render property clearance dropdown
  function renderPropertyClearanceType($id, $name, $propertyClearanceTypes) {
    echo "<select name=\"$name\" id=\"$id\" class=\"form-control\" required>";
    echo "<option value=\"\">-SELECT-</option>";
    foreach ($propertyClearanceTypes as $properties) {
        echo "<option value=\"".htmlspecialchars($properties['clearance_code'])."\">".htmlspecialchars($properties['clearance_name'])."</option>";
    }
    echo "</select>";
  }
  ?>

  <div id="destroy"></div>

  <div class="content-wrapper"><!-- Content Wrapper. Contains page content -->
   
    <section class="content-header"> <!-- Content Header (Page header) -->
      <div class="container-fluid">
        <div class="row mb-2">
          <div class="col-sm-6"> 
            <h1>Property Clearance</h1> 
          </div>
          <div class="col-sm-6">
            <ol class="breadcrumb float-sm-right">
              <li class="breadcrumb-item"><a href="#">Home</a></li>
              <li class="breadcrumb-item active">Property Clearance</li>
            </ol>
          </div>
        </div>
      </div><!-- /.container-fluid -->
    </section>

    <section class="content"> <!-- Main content -->
     <div class="card"> <!-- Default box -->
        <div class="card-header">
          <h3 class="card-title"><i class="fas fa-clipboard"></i>&nbsp; List of employee applied for property clearance</h3>

          <div class="card-tools">
          <button type="button" class="btn btn-block bg-gradient-success btn-sm"  data-toggle="modal" data-target="#addClearanceModal"><i class="fas fa-file"></i>&nbsp; Apply Clearance</button> 
          <!-- add user modal -->

   <!-- apply clearance section -->
  <div class="modal fade" id="addClearanceModal">
    <div class="modal-dialog modal-lg">
    <div class="modal-content">
    <div class="modal-header">
      <h5 class="modal-title">Add Employee Clearance Information</h5>
      <button type="button" class="close" data-dismiss="modal" aria-label="Close">
        <span aria-hidden="true">&times;</span>
      </button>
    </div>
    <div class="modal-body">
    <form id="pc_form" method="POST" enctype="multipart/form-data">
      <div class="form-row">
      <input type="hidden" value="<?php echo $code ?>" name="ctrlno" id="ctrlno">
      <div class="form-group col-md-6">
          <label>Department</label>
            <?php renderDepartmentAutocomplete('dept', 'dept', $departments, 'deptSearch', 'deptDatalist'); ?>
        </div>
        <div class="form-group col-md-6">     
          <label>Employee Name</label>
          <select name="employee" id="employee" class="form-control" autocomplete="off">
            <option value="">-SELECT-</option>
            <option value="add_new_emp">+ ADD NEW EMPLOYEE</option>
          </select>
        </div>
      </div>
      <!-- add new employee section -->
    <div id="add_new_employee" style="display:none;">
         <div class="form-row">
              <input type="hidden" class="form-control text-uppercase" name="emp_id" id="emp_id" value="" readonly>  
      <div class="form-group col-md-12">
        <label for="inputAddress">Add New Employee Name</label>
        <input type="text" class="form-control text-uppercase" name="new_employee_name" id="new_employee_name" placeholder="Enter New Employee Name" required>
        <span id="name-validation-icon" class="ml-2" style="display:none;"></span>
        <small id="name-validation-msg" class="form-text ml-2" style="display:none;"></small>
      </div>
      </div>
    </div>
       <!-- end new employee section -->
      <div class="form-row">
      <div class="form-group col-md-12">
        <label for="inputAddress">Position</label>
        <input type="text" class="form-control text-uppercase" name="position" id="position" placeholder="Enter Employees Position" required>
      </div>
      </div>
      <div class="form-row">
        <div class="form-group col-md-6">
          <label>Clearance type</label>
          <?php renderPropertyClearanceType('ctype','ctype', $propertyClearanceTypes); ?>
        </div>
        <div class="form-group col-md-6">
          <label>O.R Number</label>
          <input type="text" class="form-control" id="ornumber" name="ornumber" maxlength="9" placeholder="ENTER O.R NUMBER" required>
        </div>
      </div>
      <div class="form-row">
      <div class="form-group col-md-6">
        <label for="inputAddress">Street address</label>
        <input type="text" class="form-control text-uppercase" name="address" id="address" placeholder="Enter Address" required>
      </div>
      <div class="form-group col-md-6">
        <label for="inputAddress">City/Municipality</label>
        <?php renderCityDropdown('city', 'city', $cities); ?>
      </div>
      </div>
      
      </div>
          <div class="modal-footer">
            <button type="submit" class="btn btn-success"><i id="clearanceSubmitButton" class="fa-solid fa-paper-plane"></i>&nbsp; Submit</button>
            </form>
          <!-- form -->
          </div>
          <!-- /.modal-content -->
        </div>
        <!-- /.modal-dialog -->   
        </div>
        <!-- /.modal -->
        </div>
        </div>
        </div>

  <!-- Preview before final submission -->
  <div class="modal fade" id="pcPreviewModal" tabindex="-1" role="dialog" aria-labelledby="pcPreviewModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="pcPreviewModalLabel"><i class="fas fa-clipboard-check"></i>&nbsp; Review Application</h5>
          <button type="button" class="close" data-dismiss="modal" aria-label="Close">
            <span aria-hidden="true">&times;</span>
          </button>
        </div>
        <div class="modal-body">
          <form id="pc_preview_form" autocomplete="off">
            <input type="hidden" name="ctrlno" id="pv_ctrlno">
            <input type="hidden" name="dept" id="pv_dept">
            <input type="hidden" name="employee" id="pv_employee">
            <input type="hidden" name="emp_id" id="pv_emp_id">
            <input type="hidden" name="new_employee_name" id="pv_new_employee_name">
            <input type="hidden" name="position" id="pv_position">
            <input type="hidden" name="ctype" id="pv_ctype">
            <input type="hidden" name="ornumber" id="pv_ornumber">
            <input type="hidden" name="address" id="pv_address">
            <input type="hidden" name="city" id="pv_city">
          </form>

          <div class="callout callout-info mb-3">
            <div class="d-flex justify-content-between align-items-start">
              <div>
                <div class="font-weight-bold">Please review your details before submitting.</div>
                <div class="small text-muted">To edit, click <strong>Edit</strong>. To proceed, click <strong>Submit Application</strong>.</div>
              </div>
              <span class="badge badge-info">PREVIEW</span>
            </div>

            <ul class="list-group list-group-unbordered">
              <li class="list-group-item bg-transparent px-0">
                <div class="row">
                  <div class="col-5 text-muted"><i class="fas fa-building mr-2"></i>Department</div>
                  <div class="col-7 font-weight-bold text-uppercase text-left text-break" id="pv_txt_dept">-</div>
                </div>
              </li>
              <li class="list-group-item bg-transparent px-0">
                <div class="row">
                  <div class="col-5 text-muted"><i class="fas fa-id-badge mr-2"></i>Employee Name</div>
                  <div class="col-7 font-weight-bold text-uppercase text-left text-break" id="pv_txt_employee">-</div>
                </div>
              </li>
              <li class="list-group-item bg-transparent px-0">
                <div class="row">
                  <div class="col-5 text-muted"><i class="fas fa-briefcase mr-2"></i>Position</div>
                  <div class="col-7 font-weight-bold text-uppercase text-left text-break" id="pv_txt_position">-</div>
                </div>
              </li>
              <li class="list-group-item bg-transparent px-0">
                <div class="row">
                  <div class="col-5 text-muted"><i class="fas fa-tag mr-2"></i>Clearance Type</div>
                  <div class="col-7 font-weight-bold text-uppercase text-left text-break" id="pv_txt_ctype">-</div>
                </div>
              </li>
              <li class="list-group-item bg-transparent px-0">
                <div class="row">
                  <div class="col-5 text-muted"><i class="fas fa-receipt mr-2"></i>O.R Number</div>
                  <div class="col-7 font-weight-bold text-left text-break" id="pv_txt_ornumber">-</div>
                </div>
              </li>
              <li class="list-group-item bg-transparent px-0">
                <div class="row">
                  <div class="col-5 text-muted"><i class="fas fa-map-marker-alt mr-2"></i>Street Address</div>
                  <div class="col-7 font-weight-bold text-uppercase text-left text-break" id="pv_txt_address">-</div>
                </div>
              </li>
              <li class="list-group-item bg-transparent px-0">
                <div class="row">
                  <div class="col-5 text-muted"><i class="fas fa-city mr-2"></i>City/Municipality</div>
                  <div class="col-7 font-weight-bold text-uppercase text-left text-break" id="pv_txt_city">-</div>
                </div>
              </li>
            </ul>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" id="pcPreviewBackBtn"><i class="fas fa-pen"></i>&nbsp; Edit</button>
          <button type="button" class="btn btn-success" id="pcPreviewConfirmBtn"><i class="fa-solid fa-paper-plane"></i>&nbsp; Submit Application</button>
        </div>
      </div>
    </div>
  </div>



        <div class="card-body">
        <div id="pcFiltersBar" class="d-flex flex-wrap align-items-end" style="gap: 10px;">
          <div class="form-group mb-2" style="min-width: 220px;">
            <label for="pcFilterCategory" class="mb-1">Category</label>
            <select id="pcFilterCategory" class="form-control form-control-sm">
              <option value="">All Categories</option>
              <?php foreach ($propertyClearanceTypes as $c): ?>
                <option value="<?= htmlspecialchars($c['clearance_name']) ?>"><?= htmlspecialchars($c['clearance_name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group mb-2" style="min-width: 180px;">
            <label for="pcFilterStatus" class="mb-1">Status</label>
            <select id="pcFilterStatus" class="form-control form-control-sm">
              <option value="">All Status</option>
              <option value="READY">READY</option>
              <option value="PROCESSING">PROCESSING</option>
              <option value="RELEASED">RELEASED</option>
              <option value="CANCELED">CANCELED</option>
            </select>
          </div>
          <div class="form-group mb-2">
            <button type="button" class="btn btn-sm btn-secondary" id="pcClearFilters">Clear Filters</button>
          </div>
        </div>
        <table id="clearanceTable" class="table table-bordered table-hover" cellspacing="0" width="100%">
            <thead>
                <tr class="bg-dark text-light bg-gradient bg-opacity-150">
                    <th class="col-sm-2">EMPLOYEE NAME</th>
                    <th class="col-sm-2">REQUEST FOR</th>
                    <th class="col-sm-2">DATE APPLIED</th>
                    <th class="col-sm-2">CONTROL NO.</th>
                    <th class="col-sm-1">STATUS</th>
                  <th class="col-sm-1">ACTION</th>
                </tr>
            </thead>
          <tbody></tbody>
        </table>
        </div>
        <!-- /.card-body -->
      </div>
      <!-- /.card -->

  <!-- Edit / Approve modal (SYSTEM-ADMIN) -->
  <div class="modal fade" id="pcEditModal" tabindex="-1" role="dialog" aria-labelledby="pcEditModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="pcEditModalLabel"><i class="fa-solid fa-file-pen"></i>&nbsp; Edit Property Clearance</h5>
          <span class="badge ml-2" id="pcEditStatusBadge" style="display:none;"></span>
        </div>

        <form id="pcEditForm" autocomplete="off">
          <div class="modal-body">
            <input type="hidden" name="cid" id="pcEditCid">
            <input type="hidden" name="emp_id" id="pcEditEmpId">

            <div class="form-row">
              <div class="form-group col-md-6">
                <label>Employee Name</label>
                <input type="text" class="form-control text-uppercase" name="emp_name" id="pcEditEmpName" required>
              </div>
              <div class="form-group col-md-6">
                <label>Position</label>
                <input type="text" class="form-control text-uppercase" name="position" id="pcEditPosition" required>
              </div>
            </div>

            <div class="form-row">
              <div class="form-group col-md-6">
                <label>Department</label>
                <select class="form-control" name="dept_id" id="pcEditDept" required>
                  <option value="">-SELECT-</option>
                  <?php foreach ($departments as $d): ?>
                    <option value="<?= htmlspecialchars($d['department_code']) ?>"><?= htmlspecialchars($d['department_name']) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="form-group col-md-6">
                <label>Clearance Type</label>
                <select class="form-control" name="ctype_id" id="pcEditCtype" required>
                  <option value="">-SELECT-</option>
                  <?php foreach ($propertyClearanceTypes as $c): ?>
                    <option value="<?= htmlspecialchars($c['clearance_code']) ?>"><?= htmlspecialchars($c['clearance_name']) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>

            <div class="form-row">
              <div class="form-group col-md-6">
                <label>O.R Number</label>
                <input type="text" class="form-control" name="or_number" id="pcEditOrNumber">
              </div>
              <div class="form-group col-md-6">
                <label>City/Municipality</label>
                <select class="form-control" name="city" id="pcEditCity" required>
                  <option value="">-SELECT-</option>
                  <?php foreach ($cities as $cityOpt): ?>
                    <option value="<?= htmlspecialchars($cityOpt) ?>"><?= htmlspecialchars($cityOpt) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>

            <div class="form-row">
              <div class="form-group col-md-12">
                <label>Street Address</label>
                <input type="text" class="form-control text-uppercase" name="address" id="pcEditAddress" required>
              </div>
            </div>

            <div class="alert alert-warning py-2" id="pcEditAcctNote" style="display:none;"></div>

            <div class="border rounded p-3" id="pcEditReprintSection" style="display:none;">
              <div class="d-flex justify-content-between align-items-center mb-2">
                <div class="font-weight-bold"><i class="fa-solid fa-print"></i>&nbsp; Re-print</div>
              </div>

              <div class="form-row">
                <div class="form-group col-md-12 mb-2">
                  <label for="pcEditReprintReason" class="mb-1">Reason</label>
                  <select class="form-control" id="pcEditReprintReason">
                    <option value="">-SELECT-</option>
                    <option value="DATA CORRECTION">Data correction</option>
                    <option value="RE-ISSUANCE">Re-issuance</option>
                    <option value="PRINTER OR SYSTEM ERROR">Printer or system error</option>
                  </select>
                  <small class="form-text text-muted">Re-print is available only for RELEASED clearances and can be used once.</small>
                </div>
              </div>
            </div>
          </div>

          <div class="modal-footer">
            <button type="button" class="btn btn-danger mr-auto cancelBtnClearance" id="pcEditCancelBtn" style="display:none;"><i class="fa-solid fa-ban"></i>&nbsp; Cancel</button>
            <button type="button" class="btn btn-secondary" id="pcEditReprintBtn" style="display:none;"><i class="fa-solid fa-print"></i>&nbsp; Re-print</button>
            <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
            <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i>&nbsp; Update</button>
            <button type="button" class="btn btn-success approvePcBtn" id="pcEditApproveBtn" style="display:none;"><i class="fa-solid fa-thumbs-up"></i>&nbsp; Approve & Print</button>
          </div>
        </form>
      </div>
    </div>
  </div>

    </section><!-- /.content -->
    
  </div><!-- /.content-wrapper -->
  
  <?php include('../include/footer.php')?><!--footer-->

</div><!-- ./wrapper -->

<?php include('../include/script.php')?><!--script-->
<?php }?>
