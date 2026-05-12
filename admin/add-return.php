<?php 
include_once('../config/session.php');
include('../config/check_session.php');

include_once('../include/departments.php');

if(!isset($_SESSION['alogin'])){
  header('Location:../index.php');
  exit();
}else {

$departments = [];
if (function_exists('gso_get_departments') && isset($conn) && $conn instanceof mysqli) {
  $departments = gso_get_departments($conn);
}

$next_emp_id = null; // server allocates emp_id at save time (concurrency-safe)

$accountCodes = [];
if (isset($conn) && $conn instanceof mysqli) {
  $resAcct = mysqli_query($conn, "SELECT account_code, account_name FROM account_code ORDER BY account_code ASC");
  if ($resAcct) {
    while ($rowAcct = mysqli_fetch_assoc($resAcct)) {
      if (!$rowAcct) { continue; }
      $accountCodes[] = $rowAcct;
    }
  }
}

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
            <h1>Add Return</h1>
          </div>
          <div class="col-sm-6">
            <ol class="breadcrumb float-sm-right">
              <li class="breadcrumb-item"><a href="dashboard.php">Home</a></li>
              <li class="breadcrumb-item active">Add Return</li>
            </ol>
          </div>
        </div>
      </div><!-- /.container-fluid -->
    </section>

   
    <section class="content"> <!-- Main content -->



        <!-- Add Return Item (moved from unserviceable.php) -->
        <div class="modal fade" id="addReturnItemModal" tabindex="-1" role="dialog" aria-labelledby="addReturnItemLabel" aria-hidden="true">
          <div class="modal-dialog modal-xl modal-dialog-scrollable" role="document">
            <div class="modal-content gso-modal">
              <div class="modal-header border-0 p-0">
                <div class="gso-hero w-100" style="border-radius:0; box-shadow:none;">
                  <div class="card-body py-3">
                    <div class="d-flex align-items-start justify-content-between flex-wrap">
                      <div class="mb-2 mb-md-0">
                        <div class="gso-kicker">Return</div>
                        <div class="gso-title" id="addReturnItemLabel" style="font-size:22px;">Add Return Item</div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              <div class="modal-body">
                <form id="addReturnItemForm" autocomplete="off">
                  <div class="row">
                    <div class="col-lg-6 mb-3">
                      <div class="card gso-card h-100">
                        <div class="card-header border-0">
                          <div class="d-flex justify-content-between align-items-center">
                            <h3 class="card-title mb-0"><i class="fas fa-box"></i>&nbsp; Item Details</h3>
                            <div class="custom-control custom-checkbox" id="ri_unrepeated_wrap" style="display:none;">
                              <input type="checkbox" class="custom-control-input" id="ri_unrepeated" name="unrepeated" value="1">
                              <label class="custom-control-label" for="ri_unrepeated">unrepeated</label>
                            </div>
                          </div>
                        </div>
                        <div class="card-body">
                          <div class="form-row">
                            <div class="form-group col-md-6">
                              <label>Qty</label>
                              <input type="number" class="form-control" name="qty" id="ri_qty" min="1" value="1" placeholder="Enter qty">
                              <small class="text-warning" id="ri_qty_warning" style="display:none;"></small>
                            </div>
                            <div class="form-group col-md-6">
                              <label>Fund</label>
                              <select class="form-control" name="fund" id="ri_fund" required>
                                <option value="">Select Fund</option>
                                <option value="GENERAL FUND">GENERAL FUND</option>
                                <option value="SPECIAL EDUCATION FUND">SPECIAL EDUCATION FUND</option>
                              </select>
                            </div>
                          </div>

                          <div class="form-row">
                            <div class="form-group col-md-6">
                              <label>Category</label>
                              <select name="category" id="ri_category" class="form-control" required>
                                <option value="">-SELECT-</option>
                                <option value="PAR">PAR</option>
                                <option value="ICS">ICS</option>
                              </select>
                            </div>
                            <div class="form-group col-md-6">
                              <label>Account Code</label>
                              <select class="form-control" name="account_code" id="ri_account_code" required>
                                <option value="">Select account code</option>
                                <?php foreach ($accountCodes as $acct):
                                  $code = isset($acct['account_code']) ? trim((string)$acct['account_code']) : '';
                                  if ($code === '') { continue; }
                                ?>
                                  <option value="<?php echo htmlentities($code); ?>"><?php echo htmlentities($code); ?></option>
                                <?php endforeach; ?>
                              </select>
                            </div>
                          </div>

                          <div class="form-row">
                            <div class="form-group col-md-6">
                              <label>Asset Class</label>
                              <select name="item" id="ri_item" class="form-control" required>
                                <option value="">-SELECT-</option>
                                <option value="DESKTOP COMPUTER">DESKTOP COMPUTER</option>
                                <option value="LAPTOP">LAPTOP</option>
                                <option value="I.T EQUIPMENT">I.T EQUIPMENT</option>
                                <option value="PRINTER">PRINTER</option>
                                <option value="COPIER">COPIER</option>
                                <option value="SERVER">SERVER</option>
                                <option value="OFFICE EQUIPMENT">OFFICE EQUIPMENT</option>
                                <option value="FURNITURE & FIXTURES">FURNITURE & FIXTURES</option>
                                <option value="SPORTS EQUIPMENT">SPORTS EQUIPMENT</option>
                                <option value="AIRCONDITIONER">AIRCONDITIONER</option>
                                <option value="OTHER MACHINERY AND EQUIPMENT">OTHER MACHINERY AND EQUIPMENT</option>
                                <option value="COMMUNICATION EQUIPMENT">COMMUNICATION EQUIPMENT</option>
                                <option value="MEDICAL EQUIPMENT">MEDICAL EQUIPMENT</option>
                                <option value="OTHER SUPPLY">OTHER SUPPLY</option>
                                <option value="MILLITARY AND POLICE EQUIPMENT">MILLITARY AND POLICE EQUIPMENT</option>
                                <option value="BOOKS">BOOKS</option>
                                <option value="CONSTRUCTION AND HEAVY EQUIPMENT">CONSTRUCTION AND HEAVY EQUIPMENT</option>
                                <option value="MOTOR VEHICLE">MOTOR VEHICLE</option>
                                <option value="DISASTER RESPONSE AND RESCUE EQUIPMENT">DISASTER RESPONSE AND RESCUE EQUIPMENT</option>
                                <option value="COMPUTER SOFTWARE">COMPUTER SOFTWARE</option>
                                <option value="OTHER MAINTENANCE AND OPERATING EXPENSES">OTHER MAINTENANCE AND OPERATING EXPENSES</option>
                                <option value="SUBSCRIPTION EXPENSES">SUBSCRIPTION EXPENSES</option>
                              </select>
                            </div>
                            <div class="form-group col-md-6">
                              <label class="d-flex align-items-center justify-content-between">
                                <span>Model</span>
                                <span class="custom-control custom-checkbox d-inline-flex align-items-center" style="gap:.35rem;">
                                  <input type="checkbox" class="custom-control-input" id="ri_no_model" name="no_brand_model" value="1">
                                  <label class="custom-control-label" for="ri_no_model">no brand/model</label>
                                </span>
                              </label>
                              <input type="text" class="form-control text-uppercase" name="model" id="ri_model" placeholder="Enter brand/model" required>
                            </div>
                          </div>

                          <div class="form-group">
                            <label class="d-flex align-items-center justify-content-between">
                              <span>Description</span>
                              <span class="d-inline-flex align-items-center" style="gap:1rem;">
                                <span class="custom-control custom-checkbox d-inline-flex align-items-center" style="gap:.35rem;">
                                  <input type="checkbox" class="custom-control-input" id="ri_add_serial" name="add_serial_number" value="1">
                                  <label class="custom-control-label" for="ri_add_serial">Add serial number</label>
                                </span>
                              </span>
                            </label>
                            <textarea class="form-control text-uppercase" name="description" id="ri_description" rows="3" placeholder="Enter description" required></textarea>
                          </div>

                          <div class="form-row gso-collapse" id="ri_serial_fields_row" aria-hidden="true">
                            <div class="form-group col-12 mb-0">
                              <div id="ri_serialRows"></div>
                            </div>
                          </div>

                          <div class="form-row" id="ri_property_number_single_row">
                            <div class="form-group col-12 mb-0">
                              <label>Property Number</label>
                              <textarea class="form-control text-uppercase" name="property_number" id="ri_property_number" rows="3" required readonly></textarea>
                              <small class="text-warning d-block" id="ri_property_number_warning" style="display:none;"></small>
                            </div>
                          </div>
                        </div>
                      </div>
                    </div>

                    <div class="col-lg-6 mb-3">
                      <div class="card gso-card mb-3">
                        <div class="card-header border-0">
                          <div class="d-flex justify-content-between align-items-center">
                            <h3 class="card-title mb-0"><i class="fas fa-receipt"></i>&nbsp; Acquisition Details</h3>
                          </div>
                        </div>
                        <div class="card-body">
                          <div class="form-row">
                            <div class="form-group col-md-6">
                              <label>Unit Value</label>
                              <input type="number" step="0.01" class="form-control" name="unit_value" id="ri_unit_value" min="0" value="0" placeholder="0.00">
                            </div>
                            <div class="form-group col-md-6">
                              <label>Supplier</label>
                              <input type="text" class="form-control text-uppercase" name="supplier" id="ri_supplier" placeholder="Enter supplier">
                            </div>
                          </div>

                          <div class="form-row">
                            <div class="form-group col-md-6">
                              <label>PAR/ICS Number</label>
                              <input type="text" class="form-control text-uppercase" name="par_ics_number" id="ri_par_ics_number" placeholder="Enter PAR/ICS number">
                            </div>
                            <div class="form-group col-md-6">
                              <label>Purchase Order</label>
                              <input type="text" class="form-control text-uppercase" name="purchase_order" id="ri_purchase_order" placeholder="Enter purchase order">
                            </div>
                          </div>

                          <div class="form-row">
                            <div class="form-group col-md-6">
                              <label>Purchase Request</label>
                              <input type="text" class="form-control text-uppercase" name="purchase_request" id="ri_purchase_request" placeholder="Enter purchase request">
                            </div>
                            <div class="form-group col-md-3">
                              <label>OBR Number</label>
                              <input type="text" class="form-control text-uppercase" name="obr_number" id="ri_obr_number" placeholder="Enter OBR">
                            </div>
                            <div class="form-group col-md-3">
                              <label>JEV Number</label>
                              <input type="text" class="form-control text-uppercase" name="jev_number" id="ri_jev_number" placeholder="Enter JEV">
                            </div>
                          </div>

                          <div class="form-row">
                            <div class="form-group col-md-6">
                              <label>Year Acquired</label>
                              <select class="form-control" name="date_aquired" id="ri_date_aquired" required>
                                <option value="">Select Year</option>
                                <option value="FS">Found at Station</option>
                              </select>
                            </div>
                            <div class="form-group col-md-6">
                              <label>Return Type</label>
                              <select class="form-control" name="return_type" id="ri_return_type" required>
                                <option value="">-SELECT-</option>
                                <option value="RETURN TO STOCK">SERVICEABLE (RETURN TO STOCK)</option>
                                <option value="UNSERVICEABLE">UNSERVICEABLE</option>
                              </select>
                            </div>
                          </div>
                        </div>
                      </div>

                      <div class="card gso-card mb-0">
                        <div class="card-header border-0">
                          <div class="d-flex justify-content-between align-items-center">
                            <h3 class="card-title mb-0"><i class="fas fa-user"></i>&nbsp; End User Details</h3>
                          </div>
                        </div>
                        <div class="card-body">
                          <div class="form-row">
                            <div class="form-group col-md-6">
                              <label>Department</label>
                              <select class="form-control" name="end_user_department" id="ri_end_user_dept" required>
                                <option value="">-SELECT-</option>
                                <?php foreach ($departments as $d): ?>
                                  <?php
                                    $deptCode = htmlspecialchars((string)($d['department_code'] ?? ''), ENT_QUOTES);
                                    $deptName = htmlspecialchars((string)($d['department_name'] ?? ''), ENT_QUOTES);
                                    if ($deptCode === '') { continue; }
                                  ?>
                                  <option value="<?php echo $deptCode; ?>"><?php echo $deptName; ?></option>
                                <?php endforeach; ?>
                              </select>
                            </div>
                            <div class="form-group col-md-6">
                              <label>Employee Name</label>
                              <select class="form-control" name="end_user_employee" id="ri_end_user_emp" required disabled>
                                <option value="">SELECT A DEPARTMENT FIRST</option>
                              </select>
                            </div>
                          </div>

                          <div id="ri_add_new_employee" style="display:none;">
                            <div class="form-row">
                              <input type="hidden" class="form-control text-uppercase" name="usi_emp_id" id="ri_emp_id" value="" readonly>
                              <div class="form-group col-md-6">
                                <label class="col-form-label">Add New Employee</label>
                                <input type="text" class="form-control text-uppercase" id="ri_new_emp" name="usi_new_emp" placeholder="Enter New Employee Name">
                                <small id="ri-name-validation-msg" class="form-text ml-1" style="display:none;"></small>
                              </div>
                              <div class="form-group col-md-6">
                                <label class="col-form-label">Position</label>
                                <input type="text" class="form-control text-uppercase" id="ri_position" name="usi_position" placeholder="Enter Employee Position">
                              </div>
                            </div>
                            <small class="text-muted d-block">New employee will be created when this form is saved.</small>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>

                  <div class="card gso-card mb-0">
                    <div class="card-header border-0">
                      <div class="d-flex justify-content-between align-items-center">
                        <h3 class="card-title mb-0"><i class="fas fa-comment-dots"></i>&nbsp; Remarks</h3>
                      </div>
                    </div>
                    <div class="card-body">
                      <div class="form-group">
                        <label>Remarks</label>
                        <textarea class="form-control text-uppercase" name="remarks" id="ri_remarks" rows="2" placeholder="Enter remarks"></textarea>
                      </div>
                    </div>
                  </div>
                </form>
              </div>

              <div class="modal-footer border-0 pt-0">
                <button type="submit" class="btn btn-success" id="btnRiSaveReturn" form="addReturnItemForm">
                  <i class="fas fa-save"></i>&nbsp; Save
                </button>
                <button type="button" class="btn btn-outline-success" data-dismiss="modal">Close</button>
              </div>
            </div>
          </div>
        </div>
        <!-- /Add Return Item -->

      <div class="card">
        <div class="card-header">
          <h3 class="card-title"><i class="fas fa-history"></i>&nbsp; Recently Added Return Items</h3>
          <div class="card-tools">
            <button type="button" class="btn btn-sm btn-success" id="btnAddReturnItem">
              <i class="fas fa-plus"></i>&nbsp; Add Return Item
            </button>
          </div>
        </div>
        <div class="card-body">
          <table id="recentReturnItemsTable" class="table table-bordered table-hover" style="width:100%">
            <thead>
              <tr class="bg-dark text-light">
                <th>DATE RETURNED</th>
                <th>TYPE</th>
                <th>FUND</th>
                <th>CATEGORY</th>
                <th>ITEM</th>
                <th>MODEL</th>
                <th>SERIAL NO. 1</th>
                <th>SERIAL NO. 2</th>
                <th>PAR / PROPERTY NO.</th>
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

<style>
  /* Unserviceable - serial number collapse UI (moved from unserviceable.php) */
  .gso-collapse {
    overflow: hidden;
    height: 0;
    opacity: 0;
    transform: translateY(-6px);
    transition: height 520ms cubic-bezier(0.22, 1, 0.36, 1), opacity 220ms ease, transform 220ms ease;
    will-change: height, opacity, transform;
    pointer-events: none;
  }

  .gso-collapse.is-open {
    opacity: 1;
    transform: translateY(0);
    pointer-events: auto;
  }

  /* Scroll container for many serial rows */
  #ri_serialRows {
    max-height: 260px;
    overflow-y: auto;
    overflow-x: hidden;
    -webkit-overflow-scrolling: touch;
  }

  @media (prefers-reduced-motion: reduce) {
    .gso-collapse {
      transition: none;
    }
  }
</style>

<script type="text/plain" data-moved="assets/dist/js/script.js">
  // Add Return Item modal UI + handlers (moved from admin/unserviceable.php)
  if ($('#addReturnItemModal').length) {
    (function initAddReturnItem(){
      function valTrim(selector) {
        return String($(selector).val() || '').trim();
      }

      function validateRequiredFields() {
        var missing = [];

        if (!valTrim('#ri_fund')) missing.push('Fund');
        if (!valTrim('#ri_category')) missing.push('Category');
        if (!valTrim('#ri_account_code')) missing.push('Account Code');
        if (!valTrim('#ri_item')) missing.push('Asset Class');
        if (!valTrim('#ri_model')) missing.push('Model');
        if (!valTrim('#ri_description')) missing.push('Description');
        if (!valTrim('#ri_date_aquired')) missing.push('Date Acquired');
        if (!valTrim('#ri_return_type')) missing.push('Return Type');
        if (!valTrim('#ri_end_user_dept')) missing.push('Department');
        if (!valTrim('#ri_end_user_emp')) missing.push('Employee');

        // Property number is always required (single for qty=1, per-row list for qty>1)
        var qty = normalizeUsiQty();
        if (qty <= 1) {
          if (!valTrim('#ri_property_number')) missing.push('Property Number');
        } else {
          var allOk = true;
          $('#ri_propertyNumbers input').each(function(){
            if (!String($(this).val() || '').trim()) { allOk = false; return false; }
          });
          if (!allOk) missing.push('Property Numbers');
        }

        if (missing.length) {
          if (window.Swal) {
            Swal.fire('Required', 'Please complete: ' + missing.join(', ') + '.', 'warning');
          }
          return false;
        }
        return true;
      }

      function initUsiAccountCodeSelect2(){
        if (!$.fn.select2) { return; }
        var $sel = $('#ri_account_code');
        if (!$sel.length) { return; }
        if ($sel.hasClass('select2-hidden-accessible')) { return; }

        var $parent = $('#addReturnItemModal');
        $sel.select2({
          theme: 'bootstrap4',
          width: '100%',
          dropdownParent: $parent.length ? $parent : $(document.body),
          placeholder: 'Select account code',
          allowClear: true
        });
      }

      // Cache serial inputs across qty changes
      var usiSerialByRow = {};
      function toUsiRowKey(rowIndex){
        var n = parseInt(rowIndex, 10);
        if (!n || n < 1) { return null; }
        return String(n);
      }

      function snapshotUsiSerialInputsIntoCache(){
        $('#ri_serialRows input').each(function(){
          var name = $(this).attr('name') || '';
          var m = name.match(/^(serial2?|serial)\[(\d+)\]$/i);
          if(!m) { return; }
          var field = m[1].toLowerCase();
          var idxKey = toUsiRowKey(m[2]);
          if(!idxKey) { return; }
          if(!usiSerialByRow[idxKey]) { usiSerialByRow[idxKey] = { serial1: '', serial2: '' }; }
          if(field === 'serial2') { usiSerialByRow[idxKey].serial2 = String($(this).val() || ''); }
          else { usiSerialByRow[idxKey].serial1 = String($(this).val() || ''); }
        });
      }

      function normalizeUsiQty(){
        var $q = $('#ri_qty');
        var qty = parseInt($q.val(), 10);
        if (!qty || qty < 1) { qty = 1; $q.val(1); }
        return qty;
      }

      // Cache property number inputs across qty changes
      var usiPropByRow = {};
      function snapshotUsiPropInputsIntoCache(){
        $('#ri_propertyNumbers input').each(function(){
          var name = $(this).attr('name') || '';
          var m = name.match(/^property_numbers\[(\d+)\]$/);
          if(!m) { return; }
          var idxKey = toUsiRowKey(m[1]);
          if(!idxKey) { return; }
          usiPropByRow[idxKey] = String($(this).val() || '');
        });
      }

      function renderUsiPropertyRows(){
        snapshotUsiPropInputsIntoCache();
        var qty = normalizeUsiQty();
        var $wrap = $('#ri_propertyNumbers');
        if (!$wrap.length) { return; }

        var showIndex = qty > 1;
        var table = '<table class="table table-bordered mb-2">' +
          '<thead class="bg-light">' +
          '<tr>' +
            (showIndex ? '<th style="width:60px">No.</th>' : '') +
            '<th>Property Number</th>' +
          '</tr>' +
          '</thead><tbody>';

        for (var i = 1; i <= qty; i++) {
          var k = toUsiRowKey(i);
          var prev = (k && typeof usiPropByRow[k] !== 'undefined') ? usiPropByRow[k] : '';
          table += '<tr>' +
            (showIndex ? '<td>' + i + '</td>' : '') +
            '<td><input type="text" class="form-control text-uppercase" name="property_numbers[' + i + ']" placeholder="Enter property number" required value="' + $('<div>').text(prev).html() + '"></td>' +
          '</tr>';
        }
        table += '</tbody></table>';
        $wrap.html(table);
      }

      function setPropertyNumbersVisible(visible) {
        var $row = $('#ri_property_numbers_row');
        if (!$row.length) { return; }
        var rowEl = $row[0];
        var CAP = 360;
        $row.off('transitionend.usiPropNums');

        if (visible) {
          renderUsiPropertyRows();
          rowEl.style.height = '0px';
          rowEl.offsetHeight;
          $row.addClass('is-open').attr('aria-hidden', 'false');

          var target = 0;
          try { target = Math.min(CAP, rowEl.scrollHeight || 0); } catch (e) { target = CAP; }
          rowEl.style.height = target + 'px';
          $row.on('transitionend.usiPropNums', function(ev){
            if (ev && ev.originalEvent && ev.originalEvent.propertyName !== 'height') { return; }
            rowEl.style.height = Math.min(CAP, rowEl.scrollHeight || target) + 'px';
          });
        } else {
          var current = 0;
          try { current = rowEl.getBoundingClientRect().height || 0; } catch (e2) { current = 0; }
          rowEl.style.height = current + 'px';
          rowEl.offsetHeight;
          $row.removeClass('is-open').attr('aria-hidden', 'true');
          rowEl.style.height = '0px';

          $row.on('transitionend.usiPropNums', function(ev2){
            if (ev2 && ev2.originalEvent && ev2.originalEvent.propertyName !== 'height') { return; }
            $('#ri_propertyNumbers').empty();
            usiPropByRow = {};
          });
        }
      }

      function syncPropertyNumberMode(){
        var qty = normalizeUsiQty();
        if (qty <= 1) {
          $('#ri_property_number_single_row').show();
          $('#ri_property_number').prop('disabled', false).prop('required', true);
          setPropertyNumbersVisible(false);
        } else {
          $('#ri_property_number_single_row').hide();
          $('#ri_property_number').val('').prop('required', false).prop('disabled', true);
          setPropertyNumbersVisible(true);
        }
      }

      function enforceUsiSerialQtyLimit(){
        var serialOn = $('#ri_add_serial').is(':checked');
        var $warn = $('#ri_qty_warning');
        var qty = normalizeUsiQty();

        if (!serialOn) {
          if ($warn.length) { $warn.hide().text(''); }
          return qty;
        }

        var maxQty = 50;
        if (qty > maxQty) {
          qty = maxQty;
          $('#ri_qty').val(maxQty);
        }

        if ($warn.length) {
          $warn.text('max quantity is ' + maxQty + '.').show();
        }
        return qty;
      }

      function renderUsiSerialRows(){
        snapshotUsiSerialInputsIntoCache();
        var on = $('#ri_add_serial').is(':checked');
        var qty = enforceUsiSerialQtyLimit();
        var $wrap = $('#ri_serialRows');

        if (!on) {
          $wrap.empty();
          return;
        }

        var showIndex = qty > 1;
        var table = '<table class="table table-bordered mb-2">' +
          '<thead class="bg-light">' +
          '<tr>' +
            (showIndex ? '<th style="width:60px">No.</th>' : '') +
            '<th>Primary Serial Number</th>' +
            '<th>Secondary Serial Number</th>' +
          '</tr>' +
          '</thead><tbody>';

        for (var i = 1; i <= qty; i++) {
          var k = toUsiRowKey(i);
          var prev1 = (k && usiSerialByRow[k]) ? usiSerialByRow[k].serial1 : '';
          var prev2 = (k && usiSerialByRow[k]) ? usiSerialByRow[k].serial2 : '';
          table += '<tr>' +
            (showIndex ? '<td>' + i + '</td>' : '') +
            '<td><input type="text" class="form-control text-uppercase" name="serial[' + i + ']" placeholder="Enter serial number" value="' + $('<div>').text(prev1).html() + '"></td>' +
            '<td><input type="text" class="form-control text-uppercase" name="serial2[' + i + ']" placeholder="Enter serial number" value="' + $('<div>').text(prev2).html() + '"></td>' +
          '</tr>';
        }
        table += '</tbody></table>';

        $wrap.html(table);
      }

      function setSerialFieldsVisible(visible) {
        var $row = $('#ri_serial_fields_row');
        if (!$row.length) { return; }

        var rowEl = $row[0];
        var CAP = 360;

        $row.off('transitionend.usiSerial');

        if (visible) {
          renderUsiSerialRows();
          rowEl.style.height = '0px';
          rowEl.offsetHeight;

          $row.addClass('is-open').attr('aria-hidden', 'false');

          var target = 0;
          try { target = Math.min(CAP, rowEl.scrollHeight || 0); } catch (e) { target = CAP; }
          rowEl.style.height = target + 'px';

          $row.on('transitionend.usiSerial', function(ev){
            if (ev && ev.originalEvent && ev.originalEvent.propertyName !== 'height') { return; }
            rowEl.style.height = Math.min(CAP, rowEl.scrollHeight || target) + 'px';
          });
        } else {
          var current = 0;
          try { current = rowEl.getBoundingClientRect().height || 0; } catch (e2) { current = 0; }
          rowEl.style.height = current + 'px';
          rowEl.offsetHeight;

          $row.removeClass('is-open').attr('aria-hidden', 'true');
          rowEl.style.height = '0px';

          $row.on('transitionend.usiSerial', function(ev2){
            if (ev2 && ev2.originalEvent && ev2.originalEvent.propertyName !== 'height') { return; }
            var $inputs = $row.find('input, select, textarea');
            $inputs.prop('disabled', true).val('');
            $('#ri_serialRows').empty();
            usiSerialByRow = {};
          });
        }

        var $inputsNow = $row.find('input, select, textarea');
        $inputsNow.prop('disabled', !visible);
      }

      function syncSerialFieldsFromCheckbox() {
        var checked = $('#ri_add_serial').is(':checked');
        if (checked) { enforceUsiSerialQtyLimit(); }
        setSerialFieldsVisible(checked);
      }


      // Ensure Select2 renders correctly inside modal
      $('#addReturnItemModal').on('shown.bs.modal.usi', function(){
        initUsiAccountCodeSelect2();
      });

      // If Fund changes, clear account code selection (prevents mismatched picks)
      $(document).on('change', '#ri_fund', function(){
        var $sel = $('#ri_account_code');
        if ($sel.length) {
          $sel.val('').trigger('change');
        }
      });

      function updateUsiEndUserEmpState() {
        var hasDept = !!(($('#ri_end_user_dept').val() || '').trim());
        var $emp = $('#ri_end_user_emp');
        $emp.val('');
        if (!hasDept) {
          $emp.prop('disabled', true)
              .html('<option value="">SELECT A DEPARTMENT FIRST</option>');
        } else {
          $emp.prop('disabled', false);
          if ($emp.find('option').length === 0) {
            $emp.html('<option value="">-SELECT-</option>');
          }
        }
      }

      function loadUsiEmployeesForDept(deptCode) {
        var dept = (deptCode || '').trim();
        var $emp = $('#ri_end_user_emp');

        updateUsiEndUserEmpState();
        if (!dept) { return; }

        $emp.prop('disabled', true).html('<option value="">Loading...</option>');
        $.ajax({
          url: '../auth/auth.php',
          type: 'POST',
          data: { departmentid: dept },
          success: function (html) {
            $emp.html(html);
            updateUsiEndUserEmpState();
          },
          error: function () {
            $emp.html('<option value="">Failed to load employees</option>').prop('disabled', true);
          }
        });
      }

      function resetUsiAddNewEmpFields(){
        $('#ri_new_emp').val('');
        $('#ri_position').val('');
        $('#ri-name-validation-msg').hide().text('');
      }

      function toggleUsiAddNewEmpSection(){
        var isAddNew = (($('#ri_end_user_emp').val() || '').toLowerCase() === 'add_new_emp');
        var $section = $('#ri_add_new_employee');
        if (isAddNew) {
          $section.stop(true, true).slideDown(200);
          $('#ri_new_emp').prop('required', true);
          $('#ri_position').prop('required', true);
        } else {
          $section.stop(true, true).slideUp(200);
          $('#ri_new_emp').prop('required', false);
          $('#ri_position').prop('required', false);
          resetUsiAddNewEmpFields();
        }
      }

      (function populateYearSelect() {
        var $year = $('#ri_date_aquired');
        if (!$year.length) { return; }
        if ($year.data('gsoYearsInit')) { return; }
        $year.data('gsoYearsInit', true);

        // Non-year selection: Found at Station (keep it as first choice after placeholder)
        if ($year.find('option[value="FS"]').length === 0) {
          var $fsOpt = $('<option>', { value: 'FS', text: 'Found at Station' });
          var $placeholder = $year.find('option[value=""]').first();
          if ($placeholder.length) {
            $placeholder.after($fsOpt);
          } else {
            $year.prepend($fsOpt);
          }
        }

        var currentYear = new Date().getFullYear();
        for (var y = currentYear; y >= 2000; y--) {
          $year.append($('<option>', { value: String(y), text: String(y) }));
        }
      })();

      $('#btnAddReturnItem')
        .off('click.unserviceableAdd')
        .on('click.unserviceableAdd', function () {
          var $form = $('#addReturnItemForm');
          if ($form.length) {
            $form.trigger('reset');
          }

          usiSerialByRow = {};
          normalizeUsiQty();
          enforceUsiSerialQtyLimit();
          renderUsiSerialRows();
          syncSerialFieldsFromCheckbox();
          syncPropertyNumberMode();
          updateUsiEndUserEmpState();
          toggleUsiAddNewEmpSection();

          $('#addReturnItemModal').modal('show');
        });

      $('#ri_end_user_dept')
        .off('change.unserviceableDept')
        .on('change.unserviceableDept', function(){
          loadUsiEmployeesForDept($(this).val() || '');
        });

      $(document)
        .off('change.unserviceableEmp', '#ri_end_user_emp')
        .on('change.unserviceableEmp', '#ri_end_user_emp', function(){
          toggleUsiAddNewEmpSection();
        });

      var usiNameDebounce;
      $(document)
        .off('input.unserviceableNewEmp', '#ri_new_emp')
        .on('input.unserviceableNewEmp', '#ri_new_emp', function(){
          var name = ($(this).val() || '').trim();
          var $msg = $('#ri-name-validation-msg');
          clearTimeout(usiNameDebounce);

          if(!name){
            $msg.hide().text('');
            return;
          }

          $msg.show().text('Validating...').css('color','red');
          usiNameDebounce = setTimeout(function(){
            $.ajax({
              url: '../auth/auth.php',
              type: 'POST',
              data: { validate_employee_name: 1, emp_name: name },
              dataType: 'json',
              success: function(res){
                if(res && res.exists){
                  $msg.text('Employee name already exists!').css('color','red');
                } else {
                  $msg.text('Employee name is available.').css('color','green');
                }
              },
              error: function(){
                $msg.text('Validation error.').css('color','red');
              }
            });
          }, 600);
        });

      var usiSubmitLock = false;
      $('#addReturnItemForm')
        .off('submit.unserviceable')
        .on('submit.unserviceable', function(e){
          if (usiSubmitLock) { return true; }

          var empVal = (String($('#ri_end_user_emp').val() || '')).trim();
          if (empVal.toLowerCase() !== 'add_new_emp') {
            return true;
          }

          e.preventDefault();

          var dept = ($('#ri_end_user_dept').val() || '').trim();
          var name = ($('#ri_new_emp').val() || '').trim();
          var pos = ($('#ri_position').val() || '').trim();
          if(!dept || !name || !pos){
            if (window.Swal) { Swal.fire('Required', 'Please complete the new employee fields.', 'warning'); }
            return false;
          }

          var msg = ($('#ri-name-validation-msg').text() || '').toLowerCase();
          if (msg.indexOf('already exists') !== -1) {
            if (window.Swal) { Swal.fire('Duplicate', 'Employee name already exists.', 'error'); }
            return false;
          }

          var $form = $(this);
          $form.find('button, input, select, textarea').prop('disabled', true);

          $.ajax({
            url: '../auth/auth.php',
            type: 'POST',
            dataType: 'json',
            data: {
              save_employee_info: 1,
              fname: name,
              department: dept,
              position: pos,
              pcustodian: 0
            },
            success: function(res){
              if(res && res.status === 200){
                var newEmpId = (res && res.data && res.data.emp_id) ? String(res.data.emp_id) : '';
                loadUsiEmployeesForDept(dept);
                setTimeout(function(){
                  if (newEmpId) { $('#ri_end_user_emp').val(newEmpId).trigger('change'); }
                }, 350);
                resetUsiAddNewEmpFields();

                // Continue to item submission (AJAX) after adding employee.
                $form.find('button, input, select, textarea').prop('disabled', false);
                if (typeof window.gsoSubmitManualReturnItem === 'function') {
                  window.gsoSubmitManualReturnItem();
                }
              } else {
                $form.find('button, input, select, textarea').prop('disabled', false);
                if (window.Swal) { Swal.fire('Error', (res && res.message) ? res.message : 'Failed to add employee.', 'error'); }
              }
            },
            error: function(xhr){
              $form.find('button, input, select, textarea').prop('disabled', false);
              if (window.Swal) { Swal.fire('Error', 'Failed to add employee.', 'error'); }
              console.error('Add employee error:', xhr && xhr.responseText);
            }
          });

          return false;
        });

      function setUsiReturnType(typeLabel) {
        $('#ri_return_type').val(String(typeLabel || '').trim());
      }

      function lockUsiForm(locked) {
        var $form = $('#addReturnItemForm');
        if (!$form.length) { return; }
        $form.find('button, input, select, textarea').prop('disabled', !!locked);
        // Keep close button usable
        $('#addReturnItemModal [data-dismiss="modal"]').prop('disabled', false);
      }

      // Exposed so the add-new-employee flow can continue into item submission.
      window.gsoSubmitManualReturnItem = function(){
        var $form = $('#addReturnItemForm');
        if ($form.length) {
          $form.trigger('submit.gsoManualReturn');
        }
      };

      // Main item submission (AJAX)
      $('#addReturnItemForm')
        .off('submit.gsoManualReturn')
        .on('submit.gsoManualReturn', function(e){
          e.preventDefault();

          // Custom guard since we submit via AJAX (native browser validation may not run)
          if (!validateRequiredFields()) {
            return false;
          }

          var returnType = (String($('#ri_return_type').val() || '')).trim();
          if (!returnType) {
            if (window.Swal) { Swal.fire('Required', 'Please select Return Type.', 'warning'); }
            return false;
          }

          // If user chose add_new_emp, block until employee is saved.
          var empVal = (String($('#ri_end_user_emp').val() || '')).trim();
          if (empVal.toLowerCase() === 'add_new_emp') {
            // Trigger the existing submit handler to create employee first.
            $('#addReturnItemForm').trigger('submit.unserviceable');
            return false;
          }

          var fd = new FormData(this);
          fd.append('manual_return_item', '1');

          lockUsiForm(true);
          $.ajax({
            url: '../auth/auth.php',
            type: 'POST',
            data: fd,
            processData: false,
            contentType: false,
            dataType: 'json',
            success: function(res){
              lockUsiForm(false);
              if (res && res.status === 200) {
                if (window.Swal) { Swal.fire('Success', res.message || 'Saved.', 'success'); }
                $('#addReturnItemModal').modal('hide');
                try {
                  if ($.fn.DataTable && $('#recentReturnItemsTable').length) {
                    $('#recentReturnItemsTable').DataTable().ajax.reload(null, false);
                  }
                } catch (dtErr) {}
              } else {
                if (window.Swal) { Swal.fire('Error', (res && res.message) ? res.message : 'Unable to save item.', 'error'); }
              }
            },
            error: function(xhr){
              lockUsiForm(false);
              if (window.Swal) { Swal.fire('Error', 'Unable to save item.', 'error'); }
              console.error('manual_return_item error:', xhr && xhr.responseText);
            }
          });

          return false;
        });

      $('#ri_qty')
        .off('input.unserviceableQty change.unserviceableQty blur.unserviceableQty')
        .on('input.unserviceableQty change.unserviceableQty blur.unserviceableQty', function(){
          normalizeUsiQty();
          syncPropertyNumberMode();
          if ($('#ri_add_serial').is(':checked')) {
            enforceUsiSerialQtyLimit();
            renderUsiSerialRows();
            var $row = $('#ri_serial_fields_row');
            if ($row.length && $row.hasClass('is-open')) {
              try { $row[0].style.height = Math.min(360, $row[0].scrollHeight || 0) + 'px'; } catch (e) {}
            }
          } else {
            var $warn = $('#ri_qty_warning');
            if ($warn.length) { $warn.hide().text(''); }
          }
        });

      $(document)
        .off('input.unserviceablePropNums change.unserviceablePropNums', '#ri_propertyNumbers input')
        .on('input.unserviceablePropNums change.unserviceablePropNums', '#ri_propertyNumbers input', function(){
          snapshotUsiPropInputsIntoCache();
        });

      $(document)
        .off('input.unserviceableSerial change.unserviceableSerial', '#ri_serialRows input')
        .on('input.unserviceableSerial change.unserviceableSerial', '#ri_serialRows input', function(){
          snapshotUsiSerialInputsIntoCache();
        });

      $('#ri_add_serial')
        .off('change.unserviceableSerialToggle')
        .on('change.unserviceableSerialToggle', syncSerialFieldsFromCheckbox);

      syncSerialFieldsFromCheckbox();
      syncPropertyNumberMode();
      updateUsiEndUserEmpState();
      toggleUsiAddNewEmpSection();
    })();
  }

<!-- Legacy inline JS disabled: moved to assets/dist/js/script.js -->
</script>

<script type="text/plain" data-moved="assets/dist/js/script.js">
  $(document).ready(function(){
    if (!$.fn.DataTable) { return; }

    var table = $('#recentReturnItemsTable').DataTable({
      responsive: true,
      processing: true,
      serverSide: false,
      stateSave: true,
      paging: true,
      info: true,
      lengthChange: true,
      pageLength: 10,
      lengthMenu: [10, 25, 50, 100, 500],
      order: [[0, 'desc']],
      ajax: {
        url: '../auth/fetch_recent_return_items_dataTable.php',
        type: 'post'
      },
      columns: [
        { data: 'created_at' },
        { data: 'return_type' },
        { data: 'fund' },
        { data: 'category' },
        { data: 'item' },
        { data: 'model' },
        { data: 'serial_number' },
        { data: 'serial_number_2' },
        { data: 'par_number' }
      ]
    });

    // Keep the list fresh without a full page reload
    setInterval(function(){
      if (table && table.ajax) {
        table.ajax.reload(null, false);
      }
    }, 8000);
  });
<!-- Legacy inline JS disabled: moved to assets/dist/js/script.js -->
</script>
<?php }?>
