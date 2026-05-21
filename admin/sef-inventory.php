<?php 
include_once('../config/session.php');
include('../config/check_session.php');
include('../include/generate_ref_number.php');

if(!isset($_SESSION['alogin'])){
  header('Location:../index.php');
  exit();
}else {
 
$refNumber = generateReferenceNumber($conn,'sef_property_history', 'reference_number');

 // Fetch account codes for the account code select dropdown
 $accountCodes = [];
 $acRes = mysqli_query($conn, "SELECT account_code FROM account_code ORDER BY account_code ASC");
 if ($acRes) { while ($r = mysqli_fetch_assoc($acRes)) { $accountCodes[] = $r['account_code']; } }

 // Resolve current admin role for UI permissions
 $currentRole = '';
 try {
   $adminId = isset($_SESSION['alogin']) ? intval($_SESSION['alogin']) : 0;
   if ($adminId > 0) {
     $resRole = mysqli_query($conn, "SELECT role FROM administrator WHERE admin_id='".mysqli_real_escape_string($conn, $adminId)."' LIMIT 1");
     if ($resRole && mysqli_num_rows($resRole) === 1) {
       $rowRole = mysqli_fetch_assoc($resRole);
       $currentRole = strtoupper(trim($rowRole['role'] ?? ''));
     }
   }
 } catch (Throwable $e) { /* ignore */ }
  
?>


  <?php include('../include/header.php')?>

  <?php include('../include/navbar.php')?>

  <?php include('../include/sidebar.php')?>

   <!-- Preloader -->
<div class="preloader flex-column justify-content-center align-items-center">
    <img src="../assets/dist/img/spin.gif" alt="AdminLogo" height="90" width="90">
</div>

<div id="destroy"></div>

  <div class="content-wrapper">
   
    <section class="content-header">
      <div class="container-fluid">
        <div class="row mb-2">
          <div class="col-sm-6">
            <h1>Property Inventory List</h1>
          </div>
          <div class="col-sm-6">
            <ol class="breadcrumb float-sm-right">
              <li class="breadcrumb-item"><a href="dashboard.php">Home</a></li>
              <li class="breadcrumb-item"><a href="sef-institution.php">Institutions</a></li>
              <li class="breadcrumb-item active">SEF Inventory</li>
            </ol>
          </div>
        </div>
      </div>
    </section>

   
    <section class="content">

      
      <div class="card">
        <div class="card-header">
          <?php
          $did = intval($_GET['dept']);
          $sql = "SELECT * FROM department 
          WHERE department_code = '$did' LIMIT 1 ";
          $query = mysqli_query($conn, $sql);
          if(mysqli_num_rows($query)>0){
            foreach($query as $result){?>
                <h3 class="card-title" id="reportTitle" value="<?php echo $result['department_name']?>"><i class="fas fa-clipboard"></i>&nbsp; <b><?=$result['department_name']?></b></h3>
            <?php }}?>  
        </div>


  <div class="modal fade" id="editInModal" data-backdrop="static" tabindex="-1" role="dialog" aria-labelledby="editInModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable" role="document">
      <div class="modal-content gso-modal">
        <div class="modal-header border-0 p-0">
          <div class="gso-hero w-100 gso-hero-modalhead">
            <div class="card-body py-3">
              <div class="d-flex align-items-start justify-content-between flex-wrap">
                <div class="mb-2 mb-md-0">
                  <div class="gso-kicker">Inventory</div>
                  <div class="gso-title gso-title-sm" id="editInModalLabel">Property Information</div>
                </div>
                <div class="mb-2 mb-md-0 d-flex align-items-center">
                  <span class="badge badge-info" id="fundIndicator" title="Fund Cluster">&nbsp;</span>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="modal-body">
          <form method="POST" id="parGenFundUpdate" enctype="multipart/form-data">
            <input type="hidden" id="par" name="par">
            <input type="hidden" id="par_new" name="par_new">
            <input type="hidden" id="sef_current_emp_id" value="">
            <input type="hidden" id="sef_current_dept_id" value="">

            <div class="row">
              <div class="col-lg-6 mb-3">
                <div class="card gso-card h-100">
                  <div class="card-header border-0">
                    <div class="d-flex justify-content-between align-items-center">
                      <h3 class="card-title mb-0"><i class="fas fa-file-alt"></i>&nbsp; Item Information</h3>
                    </div>
                  </div>
                  <div class="card-body">
                    <div class="form-row">
                      <div class="form-group col-md-6">
                        <label>Property Number</label>
                        <input type="text" class="form-control" id="par_display_top" readonly>
                      </div>
                      <div class="form-group col-md-6">
                        <label>Year Acquired</label>
                        <input type="text" class="form-control" id="edate" name="edate" readonly>
                      </div>
                    </div>

                    <div class="form-row">
                      <div class="form-group col-md-6">
                        <label>Article</label>
                        <input type="text" class="form-control" id="paritem" name="paritem">
                      </div>
                      <div class="form-group col-md-6">
                        <label>Brand/Model</label>
                        <input type="text" class="form-control" id="brand" name="brand">
                      </div>
                    </div>

                    <div class="form-group">
                      <label>Description</label>
                      <textarea class="form-control" id="description" name="description" rows="7"></textarea>
                    </div>
                    <div class="form-group mb-0">
                      <label>Remarks</label>
                      <textarea class="form-control" id="remarks" name="remarks" rows="2"></textarea>
                    </div>
                  </div>
                </div>
              </div>

              <div class="col-lg-6 mb-3">
                <div class="card gso-card h-100">
                  <div class="card-header border-0">
                    <div class="d-flex justify-content-between align-items-center">
                      <h3 class="card-title mb-0"><i class="fas fa-clipboard-check"></i>&nbsp; Reference and Accountability</h3>
                    </div>
                  </div>
                  <div class="card-body">
                    <div class="form-row">
                      <div class="form-group col-md-6">
                        <label>Fund</label>
                        <input type="text" class="form-control" id="fund" name="fund" readonly>
                      </div>
                      <div class="form-group col-md-6">
                        <label>PAR/ICS No.</label>
                        <input type="text" class="form-control" id="par_ics_no" name="par_ics_no">
                      </div>
                    </div>

                    <div class="form-row">
                      <div class="form-group col-md-6">
                        <label>Primary Serial Number</label>
                        <input type="text" class="form-control" id="serial" name="serial">
                      </div>
                      <div class="form-group col-md-6">
                        <label>Secondary Serial Number</label>
                        <input type="text" class="form-control" id="serial2" name="serial2">
                      </div>
                    </div>

                    <div class="form-row">
                      <div class="form-group col-md-6">
                        <label>Unit Value</label>
                        <input type="text" class="form-control" id="uvalue" name="uvalue">
                      </div>
                      <div class="form-group col-md-6">
                        <label>Account Code</label>
                        <select class="form-control" id="acode" name="acode">
                          <option value="">— Select —</option>
                          <?php foreach ($accountCodes as $ac): ?>
                            <option value="<?= htmlspecialchars($ac) ?>"><?= htmlspecialchars($ac) ?></option>
                          <?php endforeach; ?>
                        </select>
                      </div>
                    </div>

                    <div class="form-row">
                      <div class="form-group col-md-6">
                        <label>P.O</label>
                        <input type="text" class="form-control" id="po" name="po">
                      </div>
                      <div class="form-group col-md-6">
                        <label>P.R</label>
                        <input type="text" class="form-control" id="pr" name="pr">
                      </div>
                    </div>

                    <div class="form-row">
                      <div class="form-group col-md-6">
                        <label>O.B.R</label>
                        <input type="text" class="form-control" id="obr" name="obr">
                      </div>
                      <div class="form-group col-md-6">
                        <label>Jev No.</label>
                        <input type="text" class="form-control text-uppercase" id="jev" name="jev">
                      </div>
                    </div>

                    <div class="form-row">
                      <div class="form-group col-md-12">
                        <label>Supplier</label>
                        <input type="text" class="form-control" id="supplier" name="supplier">
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <div class="row">
              <div class="col-lg-6 mb-3">
                <div class="card gso-card mb-0">
                  <div class="card-header border-0">
                    <div class="d-flex justify-content-between align-items-center">
                      <h3 class="card-title mb-0"><i class="fas fa-history"></i>&nbsp; Property History</h3>
                    </div>
                  </div>
                  <div class="card-body table-responsive p-0">
                    <table class="table table-striped table-valign-middle mb-0 gso-table">
                      <thead>
                        <tr>
                          <th>No.</th>
                          <th>Previous user</th>
                          <th>Department</th>
                          <th>Date Transferred</th>
                        </tr>
                      </thead>
                      <tbody class="ParGenHistory"></tbody>
                    </table>
                  </div>
                </div>
              </div>

              <div class="col-lg-6 mb-3">
                <div class="card gso-card mb-0">
                  <div class="card-header border-0">
                    <div class="d-flex justify-content-between align-items-center">
                      <h3 class="card-title mb-0"><i class="fa-solid fa-boxes-packing"></i>&nbsp; Bundle with</h3>
                      <button type="button" class="btn btn-sm btn-success" id="btnAddBundleLink" title="Add bundled item">
                        <i class="fas fa-plus"></i>&nbsp; Add
                      </button>
                    </div>
                  </div>
                  <div class="card-body table-responsive p-0">
                    <table class="table table-striped table-valign-middle mb-0 gso-table">
                      <thead>
                        <tr>
                          <th>Item</th>
                          <th>Serial No.</th>
                          <th>Property No.</th>
                        </tr>
                      </thead>
                      <tbody id="bundleWithTbody">
                        <tr class="text-center" id="bundleWithEmptyRow">
                          <td colspan="3">No data</td>
                        </tr>
                      </tbody>
                    </table>
                  </div>
                </div>
              </div>
            </div>

        </div><!--end of modal body-->
        <div class="modal-footer border-0 pt-0">
          <button type="submit" class="btn btn-success"><i class="fa-solid fa-pen-to-square"></i>&nbsp;Update</button>
          <button type="button" class="btn btn-outline-success" data-dismiss="modal">Close</button>
          </form>
        </div>
              </div>
            </div>
          </div>

<!-- item condition choice (Serviceable / Unserviceable) -->
<div class="modal fade" id="itemConditionModal" data-backdrop="static" tabindex="-1" role="dialog" aria-labelledby="itemConditionLabel" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="itemConditionLabel">Return to stock?</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <p class="mb-0 text-muted">Choose the condition of the equipment to proceed.</p>
        <input type="hidden" id="cond_parnum">
        <input type="hidden" id="cond_empid">
        <input type="hidden" id="cond_deptid">
        <input type="hidden" id="cond_cat">
        <input type="hidden" id="cond_refnumber" value="<?php echo $refNumber ?>">
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-danger" id="chooseUnserviceable">Unserviceable</button>
        <span class="mx-2 text-muted font-weight-bold" aria-hidden="true">or</span>
        <button type="button" class="btn btn-success" id="chooseServiceable">Serviceable</button>
      </div>
    </div>
  </div>
 </div>
<!-- end condition choice modal -->

<!-- Multiple Transfer Section -->
<div class="modal fade" id="bulkTransferModal" data-backdrop="static" tabindex="-1" role="dialog" aria-labelledby="bulkTransferLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="bulkTransferLabel">Transfer of Property</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <h6 class="text-success mb-3">Current Users / Items</h6>
        <div class="table-responsive border rounded" style="max-height:340px; overflow:auto;">
          <table class="table table-sm table-striped table-bordered mb-0" id="bulkTransferTable">
            <thead class="thead-dark">
              <tr>
                <th style="width:50px;">#</th>
                <th style="min-width:140px;">Property No.</th>
                <th style="min-width:180px;">Item</th>
                <th style="min-width:160px;">Serial Number</th>
                <th style="min-width:160px;">Current User</th>
                <th style="min-width:160px;">Department</th>
              </tr>
            </thead>
            <tbody id="bulkTransferTableBody">
              <tr><td colspan="6" class="text-center text-muted">No selection.</td></tr>
            </tbody>
          </table>
        </div>
        <input type="hidden" id="bulkTransferIds" value="">
        <hr>
        <h6 class="text-success mb-2">Transfer To</h6>
        <form id="bulkParTransfer" method="POST">
          <input type="hidden" name="bulkTransferPar" value="1">
          <input type="hidden" name="par_ics_number" id="par_ics_number">
          <input type="hidden" name="refnum_bulk" id="refnum_bulk" value="<?php echo $refNumber; ?>">
          <input type="hidden" name="selected_par_numbers" id="selected_par_numbers">
          <input type="hidden" name="category_bulk" id="category_bulk">
          <input type="hidden" id="current_dept_code" value="<?php echo htmlspecialchars($did); ?>">
          <div class="form-row">
            <div class="form-group col-md-6">
              <label class="col-form-label">Department</label>
              <input type="text" id="bulkDeptSearch" class="form-control mb-2" placeholder="Type to search department" list="bulkDeptDatalist" autocomplete="off">
              <datalist id="bulkDeptDatalist"></datalist>
              <select name="dept_bulk" id="dept_bulk" class="form-control" required style="display:none;">
                <option value="">-SELECT-</option>
                <?php 
                  $deptQueryBulk = mysqli_query($conn,"SELECT * FROM department ORDER BY department_name ASC");
                  if($deptQueryBulk && mysqli_num_rows($deptQueryBulk)>0){
                    while($drow = mysqli_fetch_assoc($deptQueryBulk)){
                      echo '<option value="'.htmlentities($drow['department_code']).'">'.htmlentities($drow['department_name']).'</option>';
                    }
                  }
                ?>
              </select>
            </div>
            <div class="form-group col-md-6">
              <label class="col-form-label">New user</label>
              <select name="parEmp_bulk" id="parEmp_bulk" class="form-control" required>
                <option value="">-SELECT-</option>
              </select>
            </div>
          </div>
          <div class="form-row">
            <div class="form-group col-md-6" id="bulkReasonWrapper" style="display:none;">
              <label class="col-form-label">Reason for Transfer</label>
              <select name="reason_bulk" id="reason_bulk" class="form-control">
                <option value="">-SELECT-</option>
                <option value="REDISTRIBUTION OF UNUTILIZED EQUIPMENT">REDISTRIBUTION OF UNUTILIZED EQUIPMENT</option>
                <option value="REQUEST FROM RECEIVING OFFICE">REQUEST FROM RECEIVING OFFICE</option>
                <option value="SUPPORT FOR SPECIAL PROJECTS">SUPPORT FOR SPECIAL PROJECTS</option>
                <option value="OTHERS">OTHERS</option>
              </select>
            </div>
            <div class="form-group col-md-6" id="bulkConditionWrapper" style="display:none;">
              <label class="col-form-label">Condition of the Equipment</label>
              <select name="condition_bulk" id="condition_bulk" class="form-control">
                <option value="">-SELECT-</option>
                <option value="SERVICEABLE">SERVICEABLE</option>
                <option value="UNSERVICEABLE">UNSERVICEABLE</option>
              </select>
            </div>
          </div>
          <div id="bulk_add_new_employee_transfer" style="display:none;">
            <div class="form-row">
              <input type="hidden" class="form-control text-uppercase" name="emp_id_bulk" id="emp_id_bulk" value="" readonly>
              <div class="form-group col-md-6">
                <label class="col-form-label">Add New Employee</label>
                <input type="text" class="form-control text-uppercase" id="new_emp_bulk" name="new_emp_bulk" placeholder="Enter New Employee Name">
                <small id="bulk-transfer-name-validation-msg" class="form-text ml-1" style="display:none;"></small>
              </div>
              <div class="form-group col-md-6">
                <label class="col-form-label">Position</label>
                <input type="text" class="form-control text-uppercase" id="position_bulk" name="position_bulk" placeholder="Enter Employee Position">
              </div>
            </div>
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
        <button type="submit" form="bulkParTransfer" class="btn btn-success" disabled id="proceedBulkTransfer">Print and Transfer</button>
      </div>
    </div>
  </div>
</div>
<!-- End Bulk Transfer Modal -->

<!--request to print par/ics documents-->
<div class="modal fade" id="requestToPrintModal" data-backdrop="static" tabindex="-1" role="dialog">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="exampleModalLabel">Request to Print PAR/ICS Documents</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
      <form method="POST" id="reprintForm"  enctype="multipart/form-data">
      <h6 class="text-dark">Do you want to re-print the PAR/ICS documents for this item?</h6>
      <input type="hidden" class="form-control" id="parnum" name="parnum">
      <input type="hidden" class="form-control" id="emp_id" name="emp_id">
      <input type="hidden" class="form-control" id="cdept_id" name="cdept_id">
      <input type="hidden" value="<?php echo $refNumber ?>" name="refnumber" id="refnumber">
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
        <button type="submit" class="btn btn-success">Yes</button>
        </form>
      </div>
    </div>
  </div>
</div>
<!--end of return to stock-->

<!-- Bundle search / add (SEF) -->
<div class="modal fade" id="bundleSearchModal" data-backdrop="static" tabindex="-1" role="dialog" aria-labelledby="bundleSearchLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable" role="document">
    <div class="modal-content gso-modal">
      <div class="modal-header border-0 p-0">
        <div class="gso-hero w-100 gso-hero-modalhead">
          <div class="card-body py-3">
            <div class="d-flex align-items-start justify-content-between flex-wrap">
              <div class="mb-2 mb-md-0">
                <div class="gso-title gso-title-sm" id="bundleSearchLabel">Add Bundle Item</div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="modal-body">
        <div class="card gso-card mb-0">
          <div class="card-header border-0">
            <div class="d-flex justify-content-between align-items-center">
              <h3 class="card-title mb-0"><i class="fas fa-search"></i>&nbsp; Search by Property Number</h3>
            </div>
          </div>
          <div class="card-body">
            <div class="form-row">
              <div class="form-group col-12 mb-2">
                <label>Property Number</label>
                <input type="text" class="form-control text-uppercase" id="bundleSearchPar" placeholder="Enter property number">
              </div>
            </div>

            <div id="bundleSearchResult">
              <hr>
              <div class="table-responsive">
                <table id="bundleSearchResultTable" class="table table-bordered table-striped table-hover table-sm mb-2">
                  <thead>
                    <tr>
                      <th>Item</th>
                      <th>Brand/Model</th>
                      <th>Primary Serial Number</th>
                      <th>Secondary Serial Number</th>
                      <th>Property Number</th>
                    </tr>
                  </thead>
                  <tbody id="bundleSearchResultTbody">
                    <tr id="bundleSearchEmptyRow">
                      <td class="text-muted text-center" colspan="5">No result. Search a property number.</td>
                    </tr>
                  </tbody>
                </table>
              </div>
              <button type="button" class="btn btn-success" id="bundleAddBtn" disabled><i class="fas fa-plus"></i>&nbsp; Add to Bundle</button>
            </div>

            <div class="text-warning" id="bundleSearchMsg" style="display:none;"></div>
          </div>
        </div>
      </div>

      <div class="modal-footer border-0 pt-0">
        <button type="button" class="btn btn-outline-success" data-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>
<!-- /Bundle search / add -->
  <!-- property table section -->
    <div class="card-body">
    <table id="InventoryTable" class="table table-bordered table-hover">
    <thead>
      <tr class="bg-dark text-light bg-gradient bg-opacity-150">
        <th class="text-center align-middle no-print" style="width:30px;">
          <input type="checkbox" id="selectAllInventory" aria-label="Select all rows">
        </th>
          <th class="w-10 no-print">ACTION</th>
                <th class="w-15">ASSET CLASS</th>
                <th class="w-25">PARTICULARS</th>
                <th class="w-10">SNID NO.1</th>
                <th class="w-10">SNID NO.2</th>
                <th class="w-10">PROPERTY NUMBER</th>
                <th class="w-10">END USER</th>
                <th class="d-none">MODEL</th>
                <th class="d-none">DESCRIPTION</th>
                <th class="d-none">YEAR ACQUIRED</th>
            </tr>
        </thead>
        <tbody>
          <tr class="text-center">
            <td colspan="11">Loading data...</td>
          </tr>
        </tbody> 
                </table>

        </div>
        <!-- /.card-body -->
      </div>
      <!-- /.card -->

    </section>
    
  </div>
  
  <?php include('../include/footer.php') ?>

</div>

<?php include('../include/script.php')?>

<script>
  // Simple HTML escaper for DataTables renderers
  function escHtml(text) {
    if (text === null || text === undefined) return '';
    return String(text)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }

  var currentRoleJS = "<?php echo htmlspecialchars($currentRole, ENT_QUOTES); ?>";
  var currentDeptId = <?php echo isset($did) ? (int)$did : 0; ?>;

  var selectedAssetClass = '';
  var selectedEndUser = '';
  var selectedParIcs = '';
  var selectedInventoryRows = {};

  function getSelectedInventoryRows() {
    return Object.keys(selectedInventoryRows).map(function (par) {
      return selectedInventoryRows[par];
    });
  }

  function syncInventorySelectionUI() {
    var $rows = $('#InventoryTable tbody input.row-select');
    var selectedVisibleCount = 0;

    $rows.each(function () {
      var par = String(this.value || '').trim();
      var isSelected = !!selectedInventoryRows[par];
      this.checked = isSelected;
      if (isSelected) selectedVisibleCount++;
    });

    var selectAll = document.getElementById('selectAllInventory');
    if (selectAll) {
      if (!$rows.length || selectedVisibleCount === 0) {
        selectAll.checked = false;
        selectAll.indeterminate = false;
      } else if (selectedVisibleCount === $rows.length) {
        selectAll.checked = true;
        selectAll.indeterminate = false;
      } else {
        selectAll.checked = false;
        selectAll.indeterminate = true;
      }
    }

    $('#bulkTransferBtn').prop('disabled', getSelectedInventoryRows().length === 0);
  }

  var table = $("#InventoryTable").DataTable({
      responsive: true,
      lengthChange: true,
      pageLength: 25,
      lengthMenu: [[10, 25, 50, 100, 500], [10, 25, 50, 100, 500]],
      autoWidth: false,
      stateSave: false,
      // Balance responsiveness and server load for large datasets.
      searchDelay: 700,
      dom: "<'row'<'col-sm-12 col-md-8'Bl><'col-sm-12 col-md-4'f>>" +
           "<'row'<'col-sm-12'tr>>" +
           "<'row'<'col-sm-12 col-md-5'i><'col-sm-12 col-md-7'p>>",
      processing: true,
      serverSide: true,
      deferRender: true,
      ajax: {
        url: '../auth/fetch_sef_inventory_dataTable.php',
        type: 'POST',
        timeout: 30000,
        data: function (d) {
          d.dept = currentDeptId;
          d.asset_class = selectedAssetClass;
          d.end_user = selectedEndUser;
          d.par_ics = selectedParIcs;
        },
        error: function (xhr, textStatus, errorThrown) {
          try { table.processing(false); } catch (_) {}
          console.error('SEF inventory DataTables AJAX failed:', textStatus, errorThrown);
        }
      },
      columns: [
        {
          data: 'par_number',
          orderable: false,
          searchable: false,
          className: 'text-center align-middle select-checkbox-column',
          render: function (data, type, row) {
            var item = escHtml(row.item);
            var user = escHtml(row.emp_name);
            var cat  = escHtml(row.category || '');
            var par  = escHtml(data || '');
            var sn1 = escHtml(row.serial_number || '');
            var sn2 = escHtml(row.serial_number_2 || '');
            return '<input type="checkbox" class="row-select"' +
                   ' value="' + par + '"' +
                   ' data-item="' + item + '"' +
                   ' data-user="' + user + '"' +
                   ' data-cat="' + cat + '"' +
                   ' data-serial="' + sn1 + '"' +
                   ' data-serial2="' + sn2 + '"' +
                   ' aria-label="Select property ' + par + '">';
          }
        },
        {
          data: 'par_number',
          orderable: false,
          searchable: false,
          className: 'text-center',
          render: function (data, type, row) {
            var par = escHtml(data || '');
            var btns = '' +
              '<div class="btn-group">' +
                '<button type="button" class="btn btn-secondary btn-sm editPropertyDetails" data-target="#editInModal" data-value="' + par + '">' +
                  '<i class="fas fa-eye" data-toggle="popover" data-content="View details" data-trigger="hover"></i>' +
                '</button>' +
                '<button type="button" class="btn btn-success btn-sm dropdown-toggle" data-toggle="dropdown" data-offset="-52">' +
                  '<i class="fas fa-bars" data-toggle="popover" data-content="Actions" data-trigger="hover"></i>' +
                '</button>' +
                '<div class="dropdown-menu" role="menu">' +
                  '<a href="#" class="dropdown-item reprintTransferOfProperty" data-toggle="modal" data-target="#requestToPrintModal" data-value="' + par + '">' +
                    '<i class="fa fa-print" aria-hidden="true"></i>&nbsp; Re-print' +
                  '</a>';
            if (['DISPOSAL-ADMIN', 'SYSTEM-ADMIN'].indexOf(currentRoleJS) !== -1) {
              btns += '<a href="#" class="dropdown-item returnItem" data-value="' + par + '">' +
                        '<i class="fas fa-box-open"></i>&nbsp;Return to stock' +
                      '</a>';
            }
            btns += '</div></div>';
            return btns;
          }
        },
        { data: 'item', render: function (data) { return escHtml(data); } },
        { data: null, render: function (data, type, row) {
            return escHtml((row.model || '') + ' - ' + (row.description || ''));
          }
        },
        { data: 'serial_number', render: function (data) {
            if (!data) return "<span class='text-dark'>NULL</span>";
            return escHtml(data);
          }
        },
        { data: 'serial_number_2', render: function (data) {
            if (!data) return "<span class='text-dark'>NULL</span>";
            return escHtml(data);
          }
        },
        { data: 'par_number', render: function (data) { return escHtml(data); } },
        { data: 'emp_name', render: function (data) { return escHtml(data); } },
        { data: 'model', visible: false, render: function (data) { return escHtml(data); } },
        { data: 'description', visible: false, render: function (data) { return escHtml(data); } },
        { data: 'date_aquired', visible: false, render: function (data) { return escHtml(data); } }
      ],
      columnDefs: [
        { targets: 0, orderable: false, searchable: false },
        { targets: 1, orderable: false },
        { targets: [8, 9, 10], visible: false, searchable: false }
      ],
      // Default to Property No. for faster server-side ordering on large datasets.
      // Users can still click 'Asset Class' to sort by item when needed.
      order: [[6, 'asc']],
      buttons: [
        { extend: "excel", orientation:"landscape", pageSize:"LEGAL", title: function() { 
            return $('#reportTitle').text() || "Default Title";
          }, exportOptions: {
            columns: [2, 8, 9, 4, 5, 6, 10, 7],
            format: {
              header: function (data, columnIdx) {
                var headers = {
                  2: 'ITEM',
                  8: 'MODEL',
                  9: 'DESCRIPTION',
                  4: 'SERIAL NUMBER PRIMARY',
                  5: 'SERIAL NUMBER SECONDARY',
                  6: 'PROPERTY NUMBER',
                  10: 'YEAR ACQUIRED',
                  7: 'END USER'
                };
                return headers[columnIdx] || data;
              },
              body: function (data) {
                return $('<div>').html(data).text();
              }
            }
          },
          action: function (e, dt, button, config) {
            var doDefaultExport = function(){
              if (!($.fn && $.fn.dataTable && $.fn.dataTable.ext && $.fn.dataTable.ext.buttons)) return;
              var buttonsExt = $.fn.dataTable.ext.buttons;
              var extName = (config && config.extend) ? config.extend : 'excel';
              var act = (buttonsExt[extName] && buttonsExt[extName].action) ? buttonsExt[extName].action : null;
              if (!act && extName === 'excel' && buttonsExt.excelHtml5 && buttonsExt.excelHtml5.action) act = buttonsExt.excelHtml5.action;
              if (act) return act.call(this, e, dt, button, config);
            }.bind(this);

            var endUserSelected = (selectedEndUser || '').trim() !== '';
            if (!endUserSelected) {
              return doDefaultExport();
            }

            var previousLength = dt.page.len();
            if (previousLength === -1) {
              return doDefaultExport();
            }

            dt.one('draw', function(){
              doDefaultExport();
              setTimeout(function(){
                try { dt.page.len(previousLength).draw(false); } catch(_) {}
              }, 400);
            });

            dt.page.len(-1).draw();
          }
        },
        { text: 'Transfer', className: 'btn btn-secondary', attr: { id: 'bulkTransferBtn', disabled: true },
          action: function(){
            var selected = getSelectedInventoryRows();
            var deptName = ($('#reportTitle').text() || '').trim();
            selected = selected.map(function (row) {
              return {
                par: row.par,
                item: row.item,
                user: row.user,
                cat: row.cat,
                serial: row.serial || '',
                serial2: row.serial2 || '',
                dept: deptName
              };
            });
            var $tbody = $('#bulkTransferTableBody');
            $tbody.empty();
            if(!selected.length){
              $tbody.append('<tr><td colspan="6" class="text-center text-muted">No selection.</td></tr>');
            } else {
              selected.forEach(function(r, idx){
                var safePar = $('<div>').text(r.par).html();
                var safeItem = $('<div>').text(r.item).html();
                var safeSerial = $('<div>').text(r.serial).html();
                var safeSerial2 = $('<div>').text(r.serial2).html();
                var serialDisplay = safeSerial || safeSerial2 ? (safeSerial + (safeSerial2 ? ' / ' + safeSerial2 : '')) : 'N/A';
                var safeUser = $('<div>').text(r.user).html();
                var safeDept = $('<div>').text(r.dept).html();
                $tbody.append('<tr>'+
                  '<td>'+(idx+1)+'</td>'+
                  '<td>'+safePar+'</td>'+
                  '<td>'+safeItem+'</td>'+
                  '<td>'+serialDisplay+'</td>'+
                  '<td>'+safeUser+'</td>'+
                  '<td>'+safeDept+'</td>'+
                '</tr>');
              });
            }
            $('#bulkTransferIds').val(JSON.stringify(selected.map(function(r){return r.par;})));
            $('#bulkTransferModal').modal('show');
          }
        },
        { extend: "print", orientation:"landscape", pageSize:"LEGAL", title: function() { 
            return $('#reportTitle').text() || "Default Title";
          }, exportOptions: { columns: ':visible:not(.no-print)' },
          action: function (e, dt, button, config) {
            var doDefaultPrint = function(){
              if ($.fn && $.fn.dataTable && $.fn.dataTable.ext && $.fn.dataTable.ext.buttons && $.fn.dataTable.ext.buttons.print && $.fn.dataTable.ext.buttons.print.action) {
                return $.fn.dataTable.ext.buttons.print.action.call(this, e, dt, button, config);
              }
            }.bind(this);

            var endUserSelected = (selectedEndUser || '').trim() !== '';
            if (!endUserSelected) {
              return doDefaultPrint();
            }

            var previousLength = dt.page.len();
            if (previousLength === -1) {
              return doDefaultPrint();
            }

            dt.one('draw', function(){
              doDefaultPrint();
              setTimeout(function(){
                try { dt.page.len(previousLength).draw(false); } catch(_) {}
              }, 400);
            });

            dt.page.len(-1).draw();
          }
        },
        { extend: "pdfHtml5", orientation:"landscape", pageSize:"LEGAL", title:"INVENTORY REPORT", exportOptions: { columns: ':not(:first-child)' },
          action: function (e, dt, button, config) {
            var doDefaultExport = function(){
              if (!($.fn && $.fn.dataTable && $.fn.dataTable.ext && $.fn.dataTable.ext.buttons && $.fn.dataTable.ext.buttons.pdfHtml5 && $.fn.dataTable.ext.buttons.pdfHtml5.action)) return;
              return $.fn.dataTable.ext.buttons.pdfHtml5.action.call(this, e, dt, button, config);
            }.bind(this);

            var endUserSelected = (selectedEndUser || '').trim() !== '';
            if (!endUserSelected) {
              return doDefaultExport();
            }

            var previousLength = dt.page.len();
            if (previousLength === -1) {
              return doDefaultExport();
            }

            dt.one('draw', function(){
              doDefaultExport();
              setTimeout(function(){
                try { dt.page.len(previousLength).draw(false); } catch(_) {}
              }, 400);
            });

            dt.page.len(-1).draw();
          }
        }
      ]
  });

  $(document).on('change', '#selectAllInventory', function(){
    var checked = this.checked;
    $('#InventoryTable tbody input.row-select').each(function(){
      var par = String(this.value || '').trim();
      if (!par) return;

      if (checked) {
        selectedInventoryRows[par] = {
          par: par,
          item: $(this).data('item') || '',
          user: $(this).data('user') || '',
          cat: $(this).data('cat') || '',
          serial: $(this).data('serial') || '',
          serial2: $(this).data('serial2') || ''
        };
      } else {
        delete selectedInventoryRows[par];
      }
    });
    syncInventorySelectionUI();
  });

  $(document).on('change.selectSync', '#InventoryTable tbody input.row-select', function(){
    var par = String(this.value || '').trim();
    if (!par) return;

    if (this.checked) {
      selectedInventoryRows[par] = {
        par: par,
        item: $(this).data('item') || '',
        user: $(this).data('user') || '',
        cat: $(this).data('cat') || '',
        serial: $(this).data('serial') || '',
        serial2: $(this).data('serial2') || ''
      };
    } else {
      delete selectedInventoryRows[par];
    }

    syncInventorySelectionUI();
  });

  table.on('draw', function(){
    syncInventorySelectionUI();
  });
  table.buttons().container().appendTo('#InventoryTable_wrapper .col-md-6:eq(0)');

  // Fast-search mode: submit server search on Enter and when clearing input.
  // This avoids expensive per-keystroke requests on large inventories.
  setTimeout(function () {
    var $searchInput = $('#InventoryTable_filter input[type="search"]');
    if (!$searchInput.length) return;

    $searchInput.off('.DT');

    $searchInput.on('keydown.fastSearch', function (e) {
      if (e.key === 'Enter') {
        e.preventDefault();
        var value = (this.value || '').trim();
        if (value.length === 1) {
          if (window.toastr) {
            toastr.info('Type at least 2 characters to search faster.');
          }
          return;
        }
        if (table.search() !== value) {
          table.search(value).draw();
        }
        return;
      }

      if (e.key === 'Backspace' || e.key === 'Delete') {
        var el = this;
        setTimeout(function () {
          if (!el.value && table.search() !== '') {
            table.search('').draw();
          }
        }, 0);
      }
    });
  }, 0);

var assetClassSelect = $('<select id="assetClassSelect" class="form-control form-control-sm ml-3" style="min-width:160px; max-width:290px; width:auto; display:inline-block;"><option value="">ALL ASSET CLASS</option></select>');
var endUserSelect = $('<select id="endUserSelect" class="form-control form-control-sm ml-3" style="min-width:160px; max-width:290px; width:auto; display:inline-block;"><option value="">ALL END USER</option></select>');
var parIcsSelect = $('<select id="parIcsSelect" class="form-control form-control-sm ml-3" style="min-width:120px; max-width:160px; width:auto; display:inline-block;"><option value="">ALL PAR/ICS</option><option value="PAR">PAR</option><option value="ICS">ICS</option></select>');

function dtExactRegex(value) {
  if (!value) return '';
  var escaped = $.fn.dataTable.util.escapeRegex(String(value));
  return '^' + escaped + '$';
}

function populateFiltersAllRecords() {
  $.ajax({
    url: '../auth/fetch_sef_inventory_filters.php',
    type: 'POST',
    dataType: 'json',
    data: { dept: currentDeptId },
    success: function (resp) {
      assetClassSelect.find('option:not(:first)').remove();
      endUserSelect.find('option:not(:first)').remove();

      var asset = (resp && Array.isArray(resp.asset_classes)) ? resp.asset_classes : [];
      var users = (resp && Array.isArray(resp.end_users)) ? resp.end_users : [];

      asset.forEach(function (v) {
        var safe = $('<div>').text(v).html();
        assetClassSelect.append('<option value="' + safe + '">' + safe + '</option>');
      });
      users.forEach(function (v) {
        var safe = $('<div>').text(v).html();
        endUserSelect.append('<option value="' + safe + '">' + safe + '</option>');
      });

      assetClassSelect.val(selectedAssetClass);
      endUserSelect.val(selectedEndUser);
    },
    error: function (xhr, textStatus, errorThrown) {
      console.error('SEF inventory filter AJAX failed:', textStatus, errorThrown);
    }
  });
}

assetClassSelect.on('change', function() {
  selectedAssetClass = $(this).val() || '';
  // Apply exact-match filter on the Asset Class column (index 2)
  table.column(2).search(dtExactRegex(selectedAssetClass), true, false);
  table.page('first').draw('page');
});
endUserSelect.on('change', function() {
  selectedEndUser = $(this).val() || '';
  // Apply exact-match filter on the End User column (index 7)
  table.column(7).search(dtExactRegex(selectedEndUser), true, false);
  table.page('first').draw('page');
});

parIcsSelect.on('change', function() {
  selectedParIcs = ($(this).val() || '').toUpperCase();
  table.page('first').draw('page');
});

// Load filter lists after the first successful table load to avoid
// overlapping heavy queries (table data + DISTINCT filters) on page open.
table.one('xhr.dt', function(){
  populateFiltersAllRecords();
});
setTimeout(function() {
  var left = $('#InventoryTable_wrapper .col-md-6:eq(0)');
  if (!left.length) {
    left = $('#InventoryTable_wrapper .col-md-8:eq(0)');
  }
  left.css({
    minWidth: '0',
    width: '100%',
    overflowX: 'auto',
    whiteSpace: 'nowrap',
    paddingRight: 0
  });
  var dtButtons = left.find('.dt-buttons');
  var dtLength = left.find('.dataTables_length');
  if (!left.find('.dt-toolbar-flex').length) {
    var flexDiv = $('<div class="dt-toolbar-flex"></div>');
    flexDiv.append(dtButtons);
    flexDiv.append(dtLength);
    flexDiv.append(parIcsSelect);
    flexDiv.append(assetClassSelect);
    flexDiv.append(endUserSelect);
    left.children().not('.dt-toolbar-flex').remove();
    left.append(flexDiv);
  } else {
    var flexDiv = left.find('.dt-toolbar-flex');
    flexDiv.empty();
    flexDiv.append(dtButtons);
    flexDiv.append(dtLength);
    flexDiv.append(parIcsSelect);
    flexDiv.append(assetClassSelect);
    flexDiv.append(endUserSelect);
  }
  flexDiv.css({
    display: 'flex',
    alignItems: 'center',
    gap: '16px',
    flexWrap: 'nowrap',
    width: '100%',
    overflowX: 'auto',
    whiteSpace: 'nowrap',
    minHeight: '42px'
  });
  dtButtons.css({
    display: 'flex',
    flexDirection: 'row',
    gap: '8px',
    flexWrap: 'nowrap',
    marginBottom: 0,
    minWidth: 'max-content',
    width: 'auto',
    whiteSpace: 'nowrap',
    alignItems: 'center',
    justifyContent: 'flex-start'
  });
  dtLength.css({
    marginBottom: 0,
    minWidth: 'max-content',
    whiteSpace: 'nowrap'
  });
  dtLength.find('label').css({
    marginBottom: 0,
    whiteSpace: 'nowrap'
  });
  dtLength.find('select').addClass('form-control form-control-sm').css({
    display: 'inline-block',
    width: 'auto',
    minWidth: '84px',
    margin: '0 6px'
  });
  var wrapper = $('#InventoryTable_wrapper .row:eq(0)');
  wrapper.removeAttr('style');
  var right = $('#InventoryTable_wrapper .col-md-6:eq(1)');
  if (!right.length) {
    right = $('#InventoryTable_wrapper .col-md-4:eq(0)');
  }
  right.removeClass('d-flex align-items-center justify-content-end flex-grow-1').removeAttr('style');
  right.find('input[type="search"]').removeAttr('style');
}, 0);

// ================= Bulk Transfer Logic ================= //
$(function(){
  var $bulkModal = $('#bulkTransferModal');
  var $bulkDeptSelect = $('#dept_bulk');
  var $bulkDeptSearch = $('#bulkDeptSearch');
  var $bulkDeptDatalist = $('#bulkDeptDatalist');
  var $bulkEmp = $('#parEmp_bulk');
  var $bulkReason = $('#reason_bulk');
  var $bulkCondition = $('#condition_bulk');
  var $bulkReasonWrap = $('#bulkReasonWrapper');
  var $bulkConditionWrap = $('#bulkConditionWrapper');
  var $bulkAddEmpSection = $('#bulk_add_new_employee_transfer');
  var $bulkNewEmp = $('#new_emp_bulk');
  var $bulkPosition = $('#position_bulk');
  var $bulkNameMsg = $('#bulk-transfer-name-validation-msg');
  var $bulkSubmitBtn = $('#proceedBulkTransfer');
  var $selectedHidden = $('#selected_par_numbers');

  function populateBulkDeptDatalist(){
    $bulkDeptDatalist.empty();
    $bulkDeptSelect.find('option').each(function(){
      var v = $(this).attr('value');
      var t = $(this).text().trim();
      if(!v) return;
      $('<option>').attr('value', t).appendTo($bulkDeptDatalist);
    });
  }
  populateBulkDeptDatalist();

  function findDeptCodeByName(name){
    var target = (name||'').trim().toLowerCase();
    if(!target) return null;
    var code=null; $bulkDeptSelect.find('option').each(function(){
      var v=$(this).attr('value'); if(!v) return; var txt=$(this).text().trim().toLowerCase();
      if(txt===target){ code=v; return false; }
    }); return code;
  }

  var wasSetFromSelectBulk=false, clearedForRetypeBulk=false;
  $bulkDeptSelect.on('change', function(){
    var code=$bulkDeptSelect.val();
    var name=$bulkDeptSelect.find('option:selected').text().trim();
    $bulkDeptSearch.val(code?name:'');
    wasSetFromSelectBulk=!!code; clearedForRetypeBulk=false;
    $bulkEmp.html('<option value="">-SELECT-</option>').prop('disabled',!code);
    if(code){ loadBulkEmployees(code); }
    hideAddEmployeeBulk();
    evaluateReasonCondition();
    evaluateSubmitState();
  });

  $bulkDeptSearch.on('input', function(){
    if(!clearedForRetypeBulk && wasSetFromSelectBulk && $bulkDeptSearch.val()){
      $bulkDeptSearch.val('');
      clearedForRetypeBulk=true; wasSetFromSelectBulk=false;
    }
    if(!$bulkDeptSearch.val().trim() && $bulkDeptSelect.val()){
      $bulkDeptSelect.val('').trigger('change');
    }
  }).on('change', function(){
    var code=findDeptCodeByName($bulkDeptSearch.val());
    if(code && $bulkDeptSelect.val()!==code){
      $bulkDeptSelect.val(code).trigger('change');
    }
  }).on('keydown', function(e){
    if(clearedForRetypeBulk || !wasSetFromSelectBulk) return;
    var k=e.key; if(['Shift','Control','Alt','Meta','Tab'].indexOf(k)!==-1) return;
    $bulkDeptSearch.val(''); clearedForRetypeBulk=true; wasSetFromSelectBulk=false;
    if($bulkDeptSelect.val()) $bulkDeptSelect.val('').trigger('change');
  });

  function loadBulkEmployees(deptCode){
    $.ajax({
      url: '../auth/auth.php',
      type: 'POST',
      data: { departmentid: deptCode },
      success: function(res){
        $bulkEmp.html(res);
      },
      error: function(){
        $bulkEmp.html('<option value="">ERROR LOADING</option>');
      }
    });
  }

  function showAddEmployeeBulk(){
    $bulkAddEmpSection.stop(true,true).slideDown(180);
    $bulkNewEmp.prop('required', true);
    $bulkPosition.prop('required', true);
  }
  function hideAddEmployeeBulk(){
    $bulkAddEmpSection.stop(true,true).slideUp(180);
    $bulkNewEmp.prop('required', false).val('');
    $bulkPosition.prop('required', false).val('');
    $bulkNameMsg.hide().text('');
  }
  $bulkEmp.on('change', function(){
    if(($bulkEmp.val()||'').toLowerCase()==='add_new_emp'){ showAddEmployeeBulk(); }
    else { hideAddEmployeeBulk(); }
    evaluateSubmitState();
  });

  var bulkNameDebounce;
  $bulkNewEmp.on('input', function(){
    clearTimeout(bulkNameDebounce);
    var name=($bulkNewEmp.val()||'').trim();
    if(!name){ $bulkNameMsg.hide().text(''); evaluateSubmitState(); return; }
    $bulkNameMsg.show().text('Validating...').css('color','red');
    bulkNameDebounce=setTimeout(function(){
      $.ajax({
        url: '../auth/auth.php',
        type: 'POST',
        data: { validate_employee_name: 1, emp_name: name },
        dataType: 'json',
        success: function(res){
          if(res && res.exists){
            $bulkNameMsg.text('Employee name already exists!').css('color','red');
          } else {
            $bulkNameMsg.text('Employee name is available.').css('color','green');
          }
          evaluateSubmitState();
        },
        error: function(){
          $bulkNameMsg.text('Validation error.').css('color','red');
          evaluateSubmitState();
        }
      });
    }, 600);
  });

  function evaluateReasonCondition(){
    var newDept = $bulkDeptSelect.val();
    var currentDept = $('#current_dept_code').val() || '';
    var differentDept = currentDept && newDept && String(currentDept) !== String(newDept);
    if(differentDept){
      $bulkReasonWrap.slideDown(180); $bulkConditionWrap.slideDown(180);
      $bulkReason.prop('required', true); $bulkCondition.prop('required', true);
    } else {
      $bulkReasonWrap.slideUp(180); $bulkConditionWrap.slideUp(180);
      $bulkReason.prop('required', false).val('');
      $bulkCondition.prop('required', false).val('');
    }
  }

  function invalidNameDuplicate(){
    var msg = ($bulkNameMsg.text()||'').toLowerCase();
    return msg.indexOf('already exists')!==-1 && ($bulkEmp.val()||'').toLowerCase()==='add_new_emp';
  }

  function evaluateSubmitState(){
    var selectedCount = JSON.parse($selectedHidden.val()||'[]').length;
    var deptOk = !!$bulkDeptSelect.val();
    var empVal = ($bulkEmp.val()||'').trim();
    var empOk = !!empVal && (empVal.toLowerCase() !== 'add_new_emp' || ($bulkNewEmp.val().trim() && $bulkPosition.val().trim() && !invalidNameDuplicate()));
    var reasonOk = !$bulkReason.prop('required') || !!$bulkReason.val();
    var conditionOk = !$bulkCondition.prop('required') || !!$bulkCondition.val();
    $bulkSubmitBtn.prop('disabled', !(selectedCount && deptOk && empOk && reasonOk && conditionOk));
  }
  $bulkReason.on('change', evaluateSubmitState);
  $bulkCondition.on('change', evaluateSubmitState);
  $bulkNewEmp.on('input', evaluateSubmitState);
  $bulkPosition.on('input', evaluateSubmitState);

  $bulkModal.on('show.bs.modal', function(){
    var selectedRows = getSelectedInventoryRows();
    var pars=[]; var catSet={};
    selectedRows.forEach(function(r){
      pars.push(r.par);
      var cat = (r.cat || '').toString().trim();
      if(cat){ catSet[cat]=true; }
    });
    $selectedHidden.val(JSON.stringify(pars));
    var cats = Object.keys(catSet);
    $('#category_bulk').val(cats.length===1 ? cats[0] : '');
    evaluateSubmitState();
  });
  $bulkModal.on('hidden.bs.modal', function(){
    $('#bulkParTransfer')[0].reset();
    $bulkDeptSelect.val('');
    $bulkDeptSearch.val('');
    $bulkEmp.html('<option value="">-SELECT-</option>').prop('disabled', true);
    hideAddEmployeeBulk();
    evaluateReasonCondition();
    evaluateSubmitState();
  });

  $('#bulkParTransfer').on('submit', function(e){
    e.preventDefault();
    if($bulkSubmitBtn.prop('disabled')) return;

    // Capture fallback reference only; server now returns the effective reference for this transaction.
    var fallbackRefnum = ($('#refnum_bulk').val() || '').trim();
    var hasPAR = false, hasICS = false;
    var selectedParNumbers = [];
    getSelectedInventoryRows().forEach(function(row){
      var p = String(row.par || '').trim();
      if (p) selectedParNumbers.push(p);
      var cat = ((row.cat || '') + '').trim().toUpperCase();
      if (cat === 'ICS' || cat === 'I.C.S') hasICS = true; else hasPAR = true;
    });
    var selectedParCsv = selectedParNumbers.join(',');

    var docKinds = [];
    if (hasPAR || hasICS) {
      if (hasPAR) docKinds.push('pt');
      if (hasICS) docKinds.push('ics');
    }

    var currentDeptName = ($('#reportTitle').text() || '').trim();
    var newDeptName = ($('#bulkDeptSearch').val() || '').trim();
    var deptChanged = !!newDeptName && !!currentDeptName && newDeptName.toLowerCase() !== currentDeptName.toLowerCase();
    if (deptChanged) {
      docKinds.push('ptr');
    }

    var openedTabs = docKinds.map(function(){
      try { return window.open('about:blank', '_blank'); } catch(_) { return null; }
    });

    var formData = new FormData(this);
    $.ajax({
      url: '../auth/auth.php',
      type: 'POST',
      data: formData,
      processData: false,
      contentType: false,
      dataType: 'json',
      success: function(res){
        if (!(res && res.status == 200)) {
          openedTabs.forEach(function(w){ try { if (w && !w.closed) w.close(); } catch(_){} });
          return toastr.error(res && res.message ? res.message : 'Bulk transfer failed');
        }
        var effectiveRef = (res && res.data && res.data.reference_number)
          ? String(res.data.reference_number).trim()
          : fallbackRefnum;

        var urls = docKinds.map(function(kind){
          if (kind === 'ics') {
            return 'inventory_custodian_slip.php?reference_number=' + encodeURIComponent(effectiveRef);
          }
          if (kind === 'ptr') {
            return 'property_transfer_report.php?reference_number=' + encodeURIComponent(effectiveRef);
          }
          var ptUrl = 'printpt.php?reference_number=' + encodeURIComponent(effectiveRef);
          if (selectedParCsv) {
            ptUrl += '&pars=' + encodeURIComponent(selectedParCsv);
          }
          return ptUrl;
        });

        if (window.Swal && Swal.fire) Swal.fire({ icon: 'success', title: 'Transfer completed', timer: 1500, showConfirmButton: false });
        setTimeout(function(){
          $('#bulkTransferModal').modal('hide');

          urls.forEach(function(u, idx){
            var w = openedTabs[idx];
            try {
              if (w && !w.closed) {
                w.location.href = u;
              } else {
                w = window.open(u, '_blank');
              }
              if (w) {
                try { w.focus(); } catch(_) {}
                setTimeout(function(){ try { w.print(); } catch(_) {} }, 900);
              }
            } catch(_) {
              // Intentionally do nothing: keep current page in place
            }
          });

          // Refresh the DataTable in-place (keep current page/search/sort)
          setTimeout(function(){
            try {
              if (window.table && table.ajax && typeof table.ajax.reload === 'function') {
                // Clear bulk selection UI before reload
                selectedInventoryRows = {};
                $('#selectAllInventory').prop('checked', false).prop('indeterminate', false);
                $('#bulkTransferBtn').prop('disabled', true);
                table.ajax.reload(null, false);
                // Refresh filter dropdown options in case transfer changed distinct values
                try { populateFiltersAllRecords(); } catch(_) {}
              } else {
                location.reload();
              }
            } catch(_) {
              location.reload();
            }
          }, 300);
        }, 1900);
      },
      error: function(){
        openedTabs.forEach(function(w){ try { if (w && !w.closed) w.close(); } catch(_){} });
        toastr.error('Server error performing bulk transfer');
      }
    });
  });
});

// ================= Re-print Logic (per item) ================= //
$(function(){
  $(document).on('click', '.reprintTransferOfProperty', function(){
    var par = $(this).data('value') || '';
    $('#reprintForm #parnum').val(par);
  });

  $('#reprintForm').on('submit', function(e){
    e.preventDefault();
    var par = ($('#reprintForm #parnum').val() || '').trim();
    if (!par) { if (window.toastr) toastr.error('Missing property number.'); return; }
    $.ajax({
      url: '../auth/auth.php',
      type: 'GET',
      data: { reprintInfo: par },
      dataType: 'json',
      success: function(res){
        if (!(res && res.status == 200 && res.data && res.data.reference_number)) {
          var msg = (res && res.message) ? res.message : 'No printable record found for this item.';
          if (window.toastr) toastr.warning(msg); else alert(msg);
          return;
        }
        var cat = (res.data.category || '').toUpperCase();
        var ref = res.data.reference_number;
        var page = (cat.indexOf('ICS') !== -1) ? 'inventory_custodian_slip.php' : 'printpt.php';
        var url = page + '?reference_number=' + encodeURIComponent(ref) + '&par=' + encodeURIComponent(par);
        try {
          var w = window.open(url, '_blank');
          if (w) {
            try { w.focus(); } catch(_) {}
            if (typeof w.print === 'function') { setTimeout(function(){ try { w.print(); } catch(_){} }, 600); }
          }
        } catch (_) { /* keep current page in place */ }
        $('#requestToPrintModal').modal('hide');
      },
      error: function(){ if (window.toastr) toastr.error('Server error: failed to fetch re-print info.'); }
    });
  });
});

// Show fund cluster badge as soon as modal starts showing; style per fund
$('#editInModal').on('show.bs.modal', function(){
  updateFundBadge($('#fund').val(), '#fundIndicator');
});

</script>

<?php }?>