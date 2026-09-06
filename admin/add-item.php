<?php 
include_once('../config/session.php');
include('../config/check_session.php');
require_once('../auth/auth.php');

if(!isset($_SESSION['alogin'])){
  header('Location:../index.php');
  exit();
}else {
  // Normalize serial arrays to simple comma-separated strings for backend
  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // If quantity is 1, collapse to string; otherwise keep arrays for per-row saving
    $qty = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 1;
    if ($qty <= 1 && (isset($_POST['serial']) || isset($_POST['serial2']))) {
      $serials = [];
      $serials2 = [];
      if (isset($_POST['serial']) && is_array($_POST['serial'])) {
        foreach ($_POST['serial'] as $s) { $serials[] = strtoupper(trim($s)); }
      } elseif (isset($_POST['serial'])) {
        $serials[] = strtoupper(trim($_POST['serial']));
      }
      if (isset($_POST['serial2']) && is_array($_POST['serial2'])) {
        foreach ($_POST['serial2'] as $s2) { $serials2[] = strtoupper(trim($s2)); }
      } elseif (isset($_POST['serial2'])) {
        $serials2[] = strtoupper(trim($_POST['serial2']));
      }
      $_POST['serial'] = implode(', ', array_filter($serials, function($v){ return $v !== ''; }));
      $_POST['serial2'] = implode(', ', array_filter($serials2, function($v){ return $v !== ''; }));
    }
  }
  // Generate a one-time submission token to prevent duplicate inserts on double-click / slow network
  $add_item_token = gso_issue_form_token('add_item');
  $np_transfer_token = gso_issue_form_token('np_transfer');
	  // emp_id is allocated by the server at save time (concurrency-safe)
	  $next_emp_id = null;
	  $accountCodeOptions = gso_fetch_account_codes($conn);
	  $departmentOptions = gso_fetch_departments($conn);
	?>
  <?php include('../include/header.php')?><!--Header-->
  
  <?php include('../include/navbar.php')?><!-- Navbar -->

  <?php include('../include/sidebar.php')?><!--Sidebar-->

  <!-- Preloader -->
  <div class="preloader flex-column justify-content-center align-items-center">
    <img src="../assets/dist/img/spin.gif" alt="AdminLogo" height="90" width="90">
  </div>

  <style>
    #addItemTable td,
    #addItemTable th,
    #addItemNewPurchaseTable td,
    #addItemNewPurchaseTable th {
      padding: 0.75rem !important;
    }

    .add-item-section + .add-item-section {
      margin-top: 1rem;
    }

    .item-set-card + .item-set-card {
      margin-top: 1rem;
    }

    /* Show the newest set first while preserving set numbers and form field order. */
    #itemSetRows {
      display: flex;
      flex-direction: column-reverse;
      gap: 1rem;
    }

    #itemSetRows > .item-set-card {
      margin-top: 0;
    }

    #addItemModal .modal-content > form#addItem {
      display: flex;
      flex-direction: column;
      min-height: 0;
      max-height: inherit;
      overflow: hidden;
    }

    #addItemModal form#addItem .modal-body {
      flex: 1 1 auto;
      min-height: 0;
      overflow-y: auto;
      overflow-x: hidden;
      -webkit-overflow-scrolling: touch;
    }

    #addItemModal form#addItem .modal-footer {
      flex-shrink: 0;
    }

    #editNpDetailModal .modal-content > form#editNpDetailForm {
      display: flex;
      flex-direction: column;
      min-height: 0;
      max-height: inherit;
      overflow: hidden;
    }

    #editNpDetailModal form#editNpDetailForm .modal-body {
      flex: 1 1 auto;
      min-height: 0;
      overflow-y: auto;
      overflow-x: hidden;
      -webkit-overflow-scrolling: touch;
    }

    #editNpDetailModal form#editNpDetailForm .modal-footer {
      flex-shrink: 0;
    }

    #editNpDetailModal .item-set-card + .item-set-card {
      margin-top: 1rem;
    }

    .gso-serial-table-scroll {
      max-height: 17.5rem;
      overflow-y: auto;
      -webkit-overflow-scrolling: touch;
    }

    .gso-serial-table-scroll thead th {
      position: sticky;
      top: 0;
      z-index: 1;
    }
  </style>

  <div id="destroy"></div>

  <div class="content-wrapper"><!-- Content Wrapper. Contains page content -->
    <section class="content-header"> <!-- Content Header (Page header) -->
      <div class="container-fluid">
        <div class="row mb-2">
          <div class="col-sm-6"> 
            <h1>Add Item or Equipment</h1> 
          </div>
          <div class="col-sm-6">
            <ol class="breadcrumb float-sm-right">
              <li class="breadcrumb-item"><a href="dashboard.php">Home</a></li>
              <li class="breadcrumb-item active">Add Item</li>
            </ol>
          </div>
        </div>
      </div><!-- /.container-fluid -->
    </section>

    <section class="content"> <!-- Main content -->
     
      <div class="card"> <!-- Default box -->
        <div class="card-header">
          <h3 class="card-title"><i class="fas fa-clipboard"></i>&nbsp; List of items added recently</h3>
          <div class="card-tools">
          <button type="button" class="btn btn-block bg-gradient-success btn-sm"  data-toggle="modal" data-target="#addItemModal"><i class="fa-solid fa-keyboard"></i>&nbsp; Add Item</button> 
          </div><!-- /.card-tools -->
        </div><!-- /.card-header -->
        <div class="card-body">
      <!-- add property manualy -->
    <div class="modal fade" id="addItemModal" data-backdrop="static" tabindex="-1" role="dialog" aria-labelledby="addItemModalLabel" aria-hidden="true" style="display:none;">
    <div class="modal-dialog modal-xl modal-dialog-scrollable" role="document">
    <div class="modal-content gso-modal">
      <div class="modal-header border-0 p-0">
        <div class="gso-hero w-100 gso-hero-modalhead">
          <div class="card-body py-3">
            <div class="d-flex align-items-start justify-content-between flex-wrap">
              <div class="mb-2 mb-md-0">
                <div class="gso-title gso-title-sm" id="addItemModalLabel">Add Item or Equipment</div>
              </div>
            </div>
          </div>
        </div>
      </div>

    <form method="POST" id="addItem" enctype="multipart/form-data">
      <input type="hidden" name="submission_token" id="add_item_submission_token" value="<?php echo htmlspecialchars($add_item_token, ENT_QUOTES, 'UTF-8'); ?>">
      <div class="modal-body">
        <div class="row add-item-section"><!--row-->
          <div class="col-lg-6 d-flex"><!--col-->
            <div class="card gso-card w-100 h-100 mb-0">
              <div class="card-header border-0">
                <div class="d-flex justify-content-between align-items-center">
                  <h3 class="card-title mb-0"><i class="fas fa-bookmark"></i>&nbsp; Reference</h3>
                </div>
              </div>
              <div class="card-body">
                <div class="form-row">
                  <div class="form-group col-md-6">
                    <label>Condition</label>
                    <select name="condition" id="condition" class="form-control" required>
                      <option value="">-SELECT-</option>
                      <option value="NEW">NEW</option>
                      <option value="EXISTING">EXISTING</option>
                    </select>
                  </div>
                  <div class="form-group col-md-6">
                    <label>Year Acquired</label>
                    <select class="form-control" id="year" name="year" required disabled>
                      <option value="">-SELECT-</option>
                      <?php
                        $currentYear = (int)date('Y');
                        $startYear = 2001;
                        for ($y = $currentYear; $y >= $startYear; $y--) {
                          echo '<option value="' . $y . '">' . $y . '</option>';
                        }
                      ?>
                      <option value="RFS">Found at Station</option>
                    </select>
                  </div>
                </div>
                <div class="form-row">
                  <div class="form-group col-md-6">
                    <label>Fund</label>
                    <select name="fund" id="fund" class="form-control" required>
                      <option value="">-SELECT-</option>
                      <option value="GENERAL FUND">GENERAL FUND</option>
                      <option value="SPECIAL EDUCATION FUND">SPECIAL EDUCATION FUND</option>
                      <option value="TRUST FUND">TRUST FUND</option>
                      <option value="DONATION">DONATION</option>
                    </select>
                  </div>
                  <div class="form-group col-md-6">
                    <label>P.R</label>
                    <input type="text" class="form-control" id="pr" name="pr" placeholder="Enter purchase request no." inputmode="text" pattern="^[0-9/-]+$" title="Numbers, slash, and hyphen only.">
                  </div>
                </div>
                <div class="form-row">
                  <div class="form-group col-md-12">
                    <label>Supplier</label>
                    <input type="text" class="form-control text-uppercase" id="supplier" name="supplier" placeholder="Enter supplier">
                  </div>
                </div>
                <div class="form-row mb-0">
                  <div class="form-group col-md-4 mb-0">
                    <label>P.O</label>
                    <input type="text" class="form-control" id="po" name="po" placeholder="Enter purchase order" inputmode="text" pattern="^[0-9/-]+$" title="Numbers, slash, and hyphen only.">
                  </div>
                  <div class="form-group col-md-4 mb-0">
                    <label>O.B.R</label>
                    <input type="text" class="form-control" id="obr" name="obr" placeholder="Enter obr" inputmode="text" pattern="^[0-9/-]+$" title="Numbers, slash, and hyphen only.">
                  </div>
                  <div class="form-group col-md-4 mb-0">
                    <label>J.E.V No.</label>
                    <input type="text" class="form-control" id="jev" name="jev" placeholder="Enter jev no.">
                  </div>
                </div>
              </div>
            </div>
          </div><!--col-->
          <div class="col-lg-6 d-flex"><!--col-->
            <div class="card gso-card w-100 h-100 mb-0">
              <div class="card-header border-0">
                <div class="d-flex justify-content-between align-items-center">
                  <h3 class="card-title mb-0"><i class="fas fa-clipboard-check"></i>&nbsp; Accountability</h3>
                </div>
              </div>
              <div class="card-body">
                <div class="form-row">
                  <div class="form-group col-md-12">
                    <div class="d-flex align-items-center mb-2">
                      <label class="col-form-label mb-0 mr-4">Department</label>
                      <div class="form-check form-check-inline mb-0 text-muted">
                        <input type="checkbox" class="form-check-input" id="multipleEndUserCheckBox" name="multipleEndUserCheckBox">
                        <label class="form-check-label" for="multipleEndUserCheckBox">assign department/end user per set</label>
                      </div>
                    </div>
                    <input type="text" id="deptSearch" class="form-control" list="deptDatalist" placeholder="Type to search department" autocomplete="off" disabled>
                    <datalist id="deptDatalist"></datalist>
                    <select name="dept" id="dept" class="form-control" required style="display:none;">
                      <option value="">-SELECT-</option>
                      <?php foreach ($departmentOptions as $departmentOption): ?>
                        <?php
                          $departmentCode = trim((string)($departmentOption['department_code'] ?? ''));
                          $departmentName = trim((string)($departmentOption['department_name'] ?? ''));
                          if ($departmentCode === '') {
                            continue;
                          }
                        ?>
                        <option value="<?php echo htmlentities($departmentCode); ?>"><?php echo htmlentities($departmentName); ?></option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                </div>
                <div class="form-row">
                  <div class="form-group col-md-12 mb-0">
                    <label>End User <span class="text-muted">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                      <input type="checkbox" class="form-check-input" id="endUserNoneCheckBox" name="endUserNoneCheckBox" value="1" disabled>
                      <label class="form-check-label" for="endUserNoneCheckBox">none</label>
                    </span></label>
                    <select name="parEmp" id="parEmp" class="form-control" autocomplete="off" required>
                      <option value="">-SELECT-</option>
                    </select>
                    <small class="form-text text-muted">These are the defaults. Enable per-set assignment to activate Department and End User fields inside each Set card.</small>
                  </div>
                </div>
                <div id="add_new_employee" style="display:none;">
                  <div class="form-row mt-3">
                    <input type="hidden" class="form-control text-uppercase" name="emp_id" id="emp_id" value="" readonly>
                    <div class="form-group col-md-6 mb-0">
                      <label class="col-form-label">Add New Employee</label>
                      <input type="text" class="form-control text-uppercase" id="new_emp" name="new_emp" placeholder="Enter New Employee Name" required>
                      <small id="additem-name-validation-msg" class="form-text ml-1" style="display:none;"></small>
                    </div>
                    <div class="form-group col-md-6 mb-0">
                      <label for="message-text" class="col-form-label">Position</label>
                      <input type="text" class="form-control text-uppercase" id="position" name="position" placeholder="Enter Employee Position" required>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div><!--col-->
        </div><!--end of row-->

        <div class="row add-item-section">
          <div class="col-12">
            <div class="card gso-card mb-0">
              <div class="card-header border-0">
                <div class="d-flex justify-content-between align-items-center flex-wrap" style="gap:0.75rem;">
                  <h3 class="card-title mb-0"><i class="fas fa-file-alt"></i>&nbsp; Item Information</h3>
                  <div class="form-group mb-0">
                    <label class="mb-1" for="quantity">Sets</label>
                    <input type="number" class="form-control" id="quantity" name="quantity" value="1" min="1" max="100" step="1" required>
                  </div>
                </div>
              </div>
              <div class="card-body">
                <?php
                  $itemUnits = ['PC', 'UNIT', 'PAIR', 'LOT', 'GAL', 'L', 'BOX', 'PACK', 'ROLL', 'METER', 'SET', 'BOOK', 'COPY', 'TANK'];
                  $assetClasses = [
                    'DESKTOP COMPUTER',
                    'LAPTOP',
                    'I.T EQUIPMENT',
                    'PRINTER',
                    'COPIER',
                    'SERVER',
                    'OFFICE EQUIPMENT',
                    'FURNITURE & FIXTURES',
                    'SPORTS EQUIPMENT',
                    'AIRCONDITIONER',
                    'OTHER MACHINERY AND EQUIPMENT',
                    'COMMUNICATION EQUIPMENT',
                    'MEDICAL EQUIPMENT',
                    'TECHNICAL & SCIENTIFIC EQUIPMENT',
                    'OTHER SUPPLY',
                    'MILLITARY AND POLICE EQUIPMENT',
                    'BOOKS',
                    'CONSTRUCTION AND HEAVY EQUIPMENT',
                    'MOTOR VEHICLE',
                    'DISASTER RESPONSE AND RESCUE EQUIPMENT',
                    'COMPUTER SOFTWARE',
                    'OTHER MAINTENANCE AND OPERATING EXPENSES',
                    'PRINTING AND PUBLICATION EXPENSES',
                    'SUBSCRIPTION EXPENSES'
                  ];
                ?>
                <div class="d-none" id="itemSetTemplates">
                  <select id="itemUnitOptionsTemplate">
                    <option value="">-SELECT-</option>
                    <?php foreach ($itemUnits as $itemUnit): ?>
                      <option value="<?php echo htmlentities($itemUnit); ?>"><?php echo htmlentities($itemUnit); ?></option>
                    <?php endforeach; ?>
                  </select>
                  <select id="itemAccountCodeOptionsTemplate">
                    <option value="">-SELECT-</option>
                    <?php foreach ($accountCodeOptions as $accountCodeOption): ?>
                      <?php
                        $accountCodeValue = trim((string)($accountCodeOption['account_code'] ?? ''));
                        $accountCodeName = trim((string)($accountCodeOption['account_name'] ?? ''));
                        if ($accountCodeValue === '') {
                          continue;
                        }
                        $accountCodeLabel = $accountCodeValue;
                        if ($accountCodeName !== '') {
                          $accountCodeLabel .= ' - ' . $accountCodeName;
                        }
                      ?>
                      <option value="<?php echo htmlentities($accountCodeValue); ?>"><?php echo htmlentities($accountCodeLabel); ?></option>
                    <?php endforeach; ?>
                  </select>
                  <select id="itemAssetOptionsTemplate">
                    <option value="">-SELECT-</option>
                    <?php foreach ($assetClasses as $assetClass): ?>
                      <option value="<?php echo htmlentities($assetClass); ?>"><?php echo htmlentities($assetClass); ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <input type="hidden" id="par_number_value" name="par_number" value="">
                <textarea class="d-none" id="par_number" name="par_number_preview" rows="1" readonly></textarea>
                <div id="itemSetRows"></div>
              </div>
            </div>
          </div>
        </div>

        <div class="row add-item-section">
          <div class="col-12">
            <div class="card gso-card mb-0">
              <div class="card-header border-0">
                <div class="d-flex justify-content-between align-items-center">
                  <h3 class="card-title mb-0"><i class="fas fa-layer-group"></i>&nbsp; Bundle Equipment</h3>
                  <button type="button" class="btn btn-sm btn-success" id="btnAddBundleRow" title="Add bundle equipment">
                    <i class="fas fa-plus"></i>
                  </button>
                </div>
              </div>
              <div class="card-body pt-2" id="bundleCardBody" style="display:none;">
                <div id="bundleRows"></div>
                <small id="bundleHelp" class="form-text text-danger" style="display:none;"></small>
              </div>
            </div>
          </div>
        </div>
      </div><!--end of modal body-->
      <div class="modal-footer border-0 pt-0">
        <button type="submit" class="btn btn-success" id="addItemSubmitBtn"><i class="fa-solid fa-pen-to-square"></i>&nbsp;<span class="btn-text">Save</span></button>
        <button type="button" class="btn btn-outline-success" data-dismiss="modal">Close</button>
      </div>
    </form>
              </div>
        </div>
      </div>    
    </div>

      <ul class="nav nav-tabs" id="addItemRecentTabs" role="tablist">
        <li class="nav-item" role="presentation">
          <a class="nav-link active" id="addItemNewPurchaseTab" data-toggle="tab" href="#addItemNewPurchasePane" role="tab" aria-controls="addItemNewPurchasePane" aria-selected="true">New Purchase</a>
        </li>
        <li class="nav-item" role="presentation">
          <a class="nav-link" id="addItemExistingTab" data-toggle="tab" href="#addItemExistingPane" role="tab" aria-controls="addItemExistingPane" aria-selected="false">Existing</a>
        </li>
      </ul>

      <div class="tab-content pt-3" id="addItemRecentTabContent">
        <div class="tab-pane fade show active" id="addItemNewPurchasePane" role="tabpanel" aria-labelledby="addItemNewPurchaseTab">
          <!-- New Purchase: one row per P.O. No. -->
          <input type="hidden" id="np_source_context" value="new_purchase">
          <input type="hidden" id="np_transfer_token" value="<?= htmlspecialchars($np_transfer_token, ENT_QUOTES, 'UTF-8') ?>">
          <div class="table-responsive px-3 pb-1">
            <table id="addItemNewPurchaseTable" class="table table-bordered table-hover w-100 mb-0" style="width:100%">
              <thead>
                <tr class="bg-dark text-light bg-gradient bg-opacity-150">
                  <th class="text-center" style="width:4%"><input type="checkbox" id="add_item_np_select_all" aria-label="Select all new purchase rows"></th>
                  <th>P.O NO.</th>
                  <th>P.R. NO.</th>
                  <th>O.B.R. NO.</th>
                  <th>SUPPLIER</th>
                  <th>DEPARTMENT</th>
                  <th>TOTAL AMOUNT</th>
                  <th style="width:4%">ACTION</th>
                </tr>
              </thead>
              <tbody>
                <?php
                  $showText = function ($value) {
                    $text = trim((string)($value ?? ''));
                    return $text !== '' ? htmlspecialchars($text, ENT_QUOTES, 'UTF-8') : '<span class="text-dark">NULL</span>';
                  };
                  $resultsNp = false;

                  if ($resultsNp && $resultsNp->num_rows > 0):
                    while ($row = $resultsNp->fetch_assoc()):
                      $poRaw = trim((string)($row['purchase_order'] ?? ''));
                      $po = htmlspecialchars($poRaw, ENT_QUOTES, 'UTF-8');
                      $rowId = (int)($row['row_id'] ?? 0);
                ?>
                  <tr class="add-item-np-row">
                    <td class="text-center">
                      <input type="checkbox" class="add-item-np-checkbox"
                        value="<?= $po ?>"
                        aria-label="Select P.O. <?= $poRaw !== '' ? $po : 'NULL' ?>">
                    </td>
                    <td class="font-weight-bold"><?= $showText($row['purchase_order'] ?? '') ?></td>
                    <td><?= $showText($row['purchase_request'] ?? '') ?></td>
                    <td><?= $showText($row['obr_number'] ?? '') ?></td>
                    <td><?= !empty($row['supplier'])         ? htmlspecialchars((string)$row['supplier'])         : "<span class='text-muted'>â</span>" ?></td>
                    <td><?= !empty($row['department_name'])  ? htmlspecialchars((string)$row['department_name'])  : "<span class='text-muted'>â</span>" ?></td>
                    <td><?= '₱ ' . number_format((float)($row['total_amount'] ?? 0), 2, '.', ',') ?></td>
                    <td class="text-center">
                      <button type="button" class="btn btn-xs btn-outline-primary np-edit-btn"
                        data-row-id="<?= $rowId ?>"
                        data-po="<?= $po ?>"
                        data-fund="<?= htmlspecialchars((string)($row['fund'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                        data-dept="<?= htmlspecialchars((string)($row['department_code'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                        data-pr="<?= htmlspecialchars((string)($row['purchase_request'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                        data-obr="<?= htmlspecialchars((string)($row['obr_number'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                        data-supplier="<?= htmlspecialchars((string)($row['supplier'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                        data-paricsno="<?= htmlspecialchars((string)($row['par_ics_number'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                        title="Edit">
                        <i class="fas fa-edit"></i>
                      </button>
                    </td>
                  </tr>
                <?php
                    endwhile;
                  endif;
                ?>
              </tbody>
            </table>
          </div>
        </div>
        <div class="tab-pane fade" id="addItemExistingPane" role="tabpanel" aria-labelledby="addItemExistingTab">
          <!-- Existing: recent add item table -->
          <div class="table-responsive px-3 pb-3">
            <table id="addItemTable" class="table table-bordered table-hover w-100 mb-0" style="width:100%">
              <thead>
                    <tr class="bg-dark text-light bg-gradient bg-opacity-150">
                  <th>ASSET CLASS</th>
                  <th>PARTICULARS</th>
                  <th>SNID NO.1</th>
                  <th>SNID NO.2</th>
                  <th>PROPERTY NO.</th>
                  <th>DEPARTMENT</th>
                  <th>END USER</th>
                    </tr>
                    </thead>
                    <tbody>
                    
                    <?php
                        // Performance note:
                        // Limit the base tables first, then join to history/department/employee.
                        // This avoids scanning huge history tables just to show the latest 10 records.
                        $recentItemsSql = "
                          SELECT
                            src.entry_id,
                            src.asset_class,
                            src.model,
                            src.description,
                            src.serial_number,
                            src.serial_number_2,
                            src.property_number,
                            d.department_name,
                            e.emp_name
                          FROM (
                            SELECT
                              gf.pargf_id AS entry_id,
                              gf.item AS asset_class,
                              gf.model,
                              gf.description,
                              gf.serial_number,
                              gf.serial_number_2,
                              gf.par_number AS property_number,
                              h.dept_id AS dept_code,
                              h.emp_id AS emp_id
                            FROM (
                              SELECT pargf_id, item, model, description, serial_number, serial_number_2, par_number
                              FROM par_gen_fund
                              ORDER BY pargf_id DESC
                              LIMIT 5
                            ) AS gf
                            LEFT JOIN general_fund_property_history AS h
                              ON h.par_number = gf.par_number AND h.status = 1

                            UNION ALL

                            SELECT
                              sef.sef_id AS entry_id,
                              sef.item AS asset_class,
                              sef.model,
                              sef.description,
                              sef.serial_number,
                              sef.serial_number_2,
                              sef.property_number AS property_number,
                              sh.sch_id AS dept_code,
                              sh.emp_id AS emp_id
                            FROM (
                              SELECT sef_id, item, model, description, serial_number, serial_number_2, property_number
                              FROM property_sef
                              ORDER BY sef_id DESC
                              LIMIT 5
                            ) AS sef
                            LEFT JOIN sef_property_history AS sh
                              ON sh.property_number = sef.property_number AND sh.status = 1
                          ) AS src
                          LEFT JOIN department AS d ON d.department_code = src.dept_code
                          LEFT JOIN employee AS e ON e.emp_id = src.emp_id
                          ORDER BY src.entry_id DESC
                        ";

                        $stmt = $conn->prepare($recentItemsSql);
                        $stmt->execute();
                        $results = $stmt->get_result();
                      if ($results && $results->num_rows > 0): ?>
                      <?php while ($row = $results->fetch_assoc()):
                        $assetClass = (string)($row['asset_class'] ?? '');
                        $model = trim((string)($row['model'] ?? ''));
                        $description = trim((string)($row['description'] ?? ''));
                        $particulars = ($model !== '' && $description !== '')
                          ? ($model . ' - ' . $description)
                          : (($model !== '') ? $model : $description);
                      ?>
                        <tr>
                          <td><?= htmlspecialchars($assetClass); ?></td>
                          <td><?= htmlspecialchars($particulars); ?></td>
                          <td><?= !empty($row['serial_number']) ? htmlspecialchars((string)$row['serial_number']) : "<span class='text-dark'>NULL</span>"; ?></td>
                          <td><?= !empty($row['serial_number_2']) ? htmlspecialchars((string)$row['serial_number_2']) : "<span class='text-dark'>NULL</span>"; ?></td>
                          <td><?= htmlspecialchars((string)($row['property_number'] ?? '')); ?></td>
                          <td><?= htmlspecialchars((string)($row['department_name'] ?? '')); ?></td>
                          <td><?= htmlspecialchars((string)($row['emp_name'] ?? '')); ?></td>
                        </tr>
                        <?php endwhile; ?>
                      <?php else: ?>
                        <!-- Leave tbody empty when no rows; DataTables will render its emptyTable message. -->
                      <?php endif; ?>
                    </tbody>
                  </table>
          </div>
        </div>
      </div>
        </div>
       <!-- /.card-body -->
      </div>
      <!-- /.card -->
    </section><!-- /.content -->
  </div><!-- /.content-wrapper -->

  <!-- Edit New Purchase Detail Modal -->
  <div class="modal fade" id="editNpDetailModal" data-backdrop="static" tabindex="-1" role="dialog" aria-labelledby="editNpDetailModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable" role="document">
      <div class="modal-content gso-modal">
        <div class="modal-header border-0 p-0">
          <div class="gso-hero w-100 gso-hero-modalhead">
            <div class="card-body py-3">
              <div class="d-flex align-items-start justify-content-between flex-wrap">
                <div class="mb-2 mb-md-0">
                  <div class="gso-title gso-title-sm" id="editNpDetailModalLabel">Edit Item or Equipment</div>
                </div>
                <button type="button" class="close text-white" style="opacity:.8;" data-dismiss="modal"><span>&times;</span></button>
              </div>
            </div>
          </div>
        </div>
        <form id="editNpDetailForm" accept-charset="UTF-8">
          <input type="hidden" name="update_new_purchase_group" value="1">
          <input type="hidden" id="edit_np_group_po" name="po">
          <input type="hidden" id="edit_np_group_row_id" name="row_id">
          <div class="modal-body">
            <div class="row add-item-section">
              <div class="col-lg-6 d-flex">
                <div class="card gso-card w-100 h-100 mb-0">
                  <div class="card-header border-0">
                    <div class="d-flex justify-content-between align-items-center">
                      <h3 class="card-title mb-0"><i class="fas fa-bookmark"></i>&nbsp; Reference</h3>
                    </div>
                  </div>
                  <div class="card-body">
                    <div class="form-row">
                      <div class="form-group col-md-6">
                        <label>Condition</label>
                        <select class="form-control" id="edit_np_condition" disabled>
                          <option value="NEW" selected>NEW</option>
                        </select>
                      </div>
                      <div class="form-group col-md-6">
                        <label>Year Acquired</label>
                        <select class="form-control" id="edit_np_year" name="year" required>
                          <option value="">-SELECT-</option>
                          <?php
                            $currentYear = (int)date('Y');
                            $startYear = 2001;
                            for ($y = $currentYear; $y >= $startYear; $y--) {
                              echo '<option value="' . $y . '">' . $y . '</option>';
                            }
                          ?>
                          <option value="RFS">Found at Station</option>
                        </select>
                      </div>
                    </div>
                    <div class="form-row">
                      <div class="form-group col-md-6">
                        <label>Fund</label>
                        <select class="form-control" id="edit_np_fund" name="fund" required>
                          <option value="">-SELECT-</option>
                          <option value="GENERAL FUND">GENERAL FUND</option>
                          <option value="SPECIAL EDUCATION FUND">SPECIAL EDUCATION FUND</option>
                          <option value="TRUST FUND">TRUST FUND</option>
                          <option value="DONATION">DONATION</option>
                        </select>
                      </div>
                      <div class="form-group col-md-6">
                        <label>P.R</label>
                        <input type="text" class="form-control text-uppercase" id="edit_np_pr" name="purchase_request" placeholder="Enter purchase request no." inputmode="text" pattern="^[0-9/-]+$" title="Numbers, slash, and hyphen only.">
                      </div>
                    </div>
                    <div class="form-row">
                      <div class="form-group col-md-12">
                        <label>Supplier</label>
                        <input type="text" class="form-control text-uppercase" id="edit_np_supplier" name="supplier" placeholder="Enter supplier">
                      </div>
                    </div>
                    <div class="form-row mb-0">
                      <div class="form-group col-md-4 mb-0">
                        <label>P.O</label>
                        <input type="text" class="form-control text-uppercase" id="edit_np_po" name="purchase_order" placeholder="Enter purchase order" inputmode="text" pattern="^[0-9/-]+$" title="Numbers, slash, and hyphen only.">
                      </div>
                      <div class="form-group col-md-4 mb-0">
                        <label>O.B.R</label>
                        <input type="text" class="form-control text-uppercase" id="edit_np_obr" name="obr_number" placeholder="Enter obr" inputmode="text" pattern="^[0-9/-]+$" title="Numbers, slash, and hyphen only.">
                      </div>
                      <div class="form-group col-md-4 mb-0">
                        <label>J.E.V No.</label>
                        <input type="text" class="form-control text-uppercase" id="edit_np_jev" name="jev_number" placeholder="Enter jev no.">
                      </div>
                    </div>
                  </div>
                </div>
              </div>
              <div class="col-lg-6 d-flex mt-3 mt-lg-0">
                <div class="card gso-card w-100 h-100 mb-0">
                  <div class="card-header border-0">
                    <div class="d-flex justify-content-between align-items-center">
                      <h3 class="card-title mb-0"><i class="fas fa-clipboard-check"></i>&nbsp; Accountability</h3>
                    </div>
                  </div>
                  <div class="card-body">
                    <div class="form-row">
                      <div class="form-group col-md-12">
                        <label class="col-form-label">Department <span class="text-muted">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                          <input type="checkbox" class="form-check-input" id="editNpMultipleEndUserCheckBox">
                          <label class="form-check-label" for="editNpMultipleEndUserCheckBox">assign department/end user per set</label>
                        </span></label>
                        <input type="text" id="editNpDeptSearch" class="form-control" list="editNpDeptDatalist" placeholder="Type to search department" autocomplete="off">
                        <datalist id="editNpDeptDatalist"></datalist>
                        <select name="dept_id" id="edit_np_dept" class="form-control" required style="display:none;">
                          <option value="">-SELECT-</option>
                          <?php foreach ($departmentOptions as $departmentOption): ?>
                            <?php
                              $departmentCode = trim((string)($departmentOption['department_code'] ?? ''));
                              $departmentName = trim((string)($departmentOption['department_name'] ?? ''));
                              if ($departmentCode === '') {
                                continue;
                              }
                            ?>
                            <option value="<?php echo htmlentities($departmentCode); ?>"><?php echo htmlentities($departmentName); ?></option>
                          <?php endforeach; ?>
                        </select>
                      </div>
                    </div>
                    <div class="form-row mb-0">
                      <div class="form-group col-md-12 mb-0">
                        <label>End User</label>
                        <select id="edit_np_emp_single" class="form-control" autocomplete="off" style="display:none;">
                          <option value="">-SELECT-</option>
                        </select>
                      </div>
                    </div>
                    <div id="edit_np_add_new_employee" style="display:none;">
                      <div class="form-row mt-3">
                        <div class="form-group col-md-6 mb-0">
                          <label class="col-form-label">Add New Employee</label>
                          <input type="text" class="form-control text-uppercase" id="edit_np_new_emp" placeholder="Enter New Employee Name" disabled>
                        </div>
                        <div class="form-group col-md-6 mb-0">
                          <label class="col-form-label">Position</label>
                          <input type="text" class="form-control text-uppercase" id="edit_np_position" placeholder="Enter Employee Position" disabled>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <div class="row add-item-section">
              <div class="col-12">
                <div class="card gso-card mb-0">
                  <div class="card-header border-0">
                    <div class="d-flex justify-content-between align-items-center flex-wrap" style="gap:0.75rem;">
                      <h3 class="card-title mb-0"><i class="fas fa-file-alt"></i>&nbsp; Item Information</h3>
                      <div class="form-group mb-0">
                        <label class="mb-1" for="edit_np_set_count">Sets</label>
                        <input type="number" class="form-control" id="edit_np_set_count" name="set_count" value="0" min="1" max="100" step="1">
                      </div>
                    </div>
                  </div>
                  <div class="card-body">
                    <div class="d-none" id="editNpTemplates">
                      <select id="editNpItemUnitOptionsTemplate">
                        <option value="">-SELECT-</option>
                        <?php foreach ($itemUnits as $itemUnit): ?>
                          <option value="<?php echo htmlentities($itemUnit); ?>"><?php echo htmlentities($itemUnit); ?></option>
                        <?php endforeach; ?>
                      </select>
                      <select id="editNpItemAccountCodeOptionsTemplate">
                        <option value="">-SELECT-</option>
                        <?php foreach ($accountCodeOptions as $accountCodeOption): ?>
                          <?php
                            $accountCodeValue = trim((string)($accountCodeOption['account_code'] ?? ''));
                            $accountCodeName = trim((string)($accountCodeOption['account_name'] ?? ''));
                            if ($accountCodeValue === '') {
                              continue;
                            }
                            $accountCodeLabel = $accountCodeValue;
                            if ($accountCodeName !== '') {
                              $accountCodeLabel .= ' - ' . $accountCodeName;
                            }
                          ?>
                          <option value="<?php echo htmlentities($accountCodeValue); ?>"><?php echo htmlentities($accountCodeLabel); ?></option>
                        <?php endforeach; ?>
                      </select>
                      <select id="editNpItemAssetOptionsTemplate">
                        <option value="">-SELECT-</option>
                        <?php foreach ($assetClasses as $assetClass): ?>
                          <option value="<?php echo htmlentities($assetClass); ?>"><?php echo htmlentities($assetClass); ?></option>
                        <?php endforeach; ?>
                      </select>
                    </div>
                    <div id="editNpItemRows">
                      <div class="text-center text-muted py-4">Select a purchase to view details.</div>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <div class="row add-item-section">
              <div class="col-12">
                <div class="card gso-card mb-0">
                  <div class="card-header border-0">
                    <div class="d-flex justify-content-between align-items-center">
                      <h3 class="card-title mb-0"><i class="fas fa-layer-group"></i>&nbsp; Bundle Equipment</h3>
                      <button type="button" class="btn btn-sm btn-success" id="editNpAddBundleRow" title="Add bundle equipment">
                        <i class="fas fa-plus"></i>
                      </button>
                    </div>
                  </div>
                  <div class="card-body pt-2">
                    <div id="editNpBundleRows">
                      <div class="text-center text-muted py-4">No bundle equipment found.</div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <div class="modal-footer border-0 pt-0">
            <button type="submit" class="btn btn-success" id="editNpDetailSaveBtn"><i class="fas fa-save"></i>&nbsp; Update</button>
            <button type="button" class="btn btn-outline-primary" id="editNpDetailPrintBtn"><i class="fas fa-print"></i>&nbsp; Print</button>
            <button type="button" class="btn btn-outline-success" data-dismiss="modal">Close</button>
          </div>
        </form>
      </div>
    </div>
  </div>
  <!-- /Edit New Purchase Detail Modal -->
  <?php include('../include/footer.php')?><!--footer-->
</div><!-- ./wrapper -->
<?php include('../include/script.php')?><!--script-->

<?php }?>
