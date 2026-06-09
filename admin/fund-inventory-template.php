<?php
include_once('../config/session.php');
include('../config/check_session.php');
require_once('../auth/auth.php');

if (!isset($_SESSION['alogin'])) {
  header('Location:../index.php');
  exit();
}

$fundKey = isset($fundKey) ? (string)$fundKey : '';
$fundTitle = isset($fundTitle) ? (string)$fundTitle : 'Fund Inventory';
$fundSubtitle = isset($fundSubtitle) ? (string)$fundSubtitle : 'Property Inventory';

if (!in_array($fundKey, ['trust', 'donation'], true)) {
  header('Location:dashboard.php');
  exit();
}

$accountCodeOptions = gso_fetch_account_codes($conn);
$departmentOptions = gso_fetch_departments($conn);

$itemUnits = array('PC', 'UNIT', 'LOT', 'GAL', 'L', 'BOX', 'PACK', 'ROLL', 'METER', 'SET');
$assetClasses = array(
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
);
?>

<?php include('../include/header.php') ?>
<?php include('../include/navbar.php') ?>
<?php include('../include/sidebar.php') ?>

<style>
  #addItemNewPurchaseTable td,
  #addItemNewPurchaseTable th {
    padding: 0.75rem !important;
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

<div class="preloader flex-column justify-content-center align-items-center">
  <img src="../assets/dist/img/spin.gif" alt="AdminLogo" height="90" width="90">
</div>

<div id="destroy"></div>

<div class="content-wrapper" id="fundInventoryPage" data-fund-key="<?php echo htmlspecialchars($fundKey, ENT_QUOTES); ?>">
  <section class="content-header">
    <div class="container-fluid">
      <div class="row mb-2">
        <div class="col-sm-6">
          <h1><?php echo htmlspecialchars($fundTitle); ?></h1>
        </div>
        <div class="col-sm-6">
          <ol class="breadcrumb float-sm-right">
            <li class="breadcrumb-item"><a href="dashboard.php">Home</a></li>
            <li class="breadcrumb-item active"><?php echo htmlspecialchars($fundTitle); ?></li>
          </ol>
        </div>
      </div>
    </div>
  </section>

  <section class="content">
    <div class="card">
      <div class="card-header">
        <h3 class="card-title" id="reportTitle"><i class="fas fa-clipboard"></i>&nbsp; <b><?php echo htmlspecialchars($fundSubtitle); ?></b></h3>
      </div>
      <div class="card-body">
        <input type="hidden" id="np_source_context" value="fund_inventory">
        <input type="hidden" id="np_fund_key" value="<?php echo htmlspecialchars($fundKey, ENT_QUOTES); ?>">
        <div class="table-responsive px-3 pb-1">
        <table id="addItemNewPurchaseTable" class="table table-bordered table-hover w-100 mb-0" style="width:100%">
          <thead>
            <tr class="bg-dark text-light bg-gradient bg-opacity-150">
              <th class="text-center" style="width:4%;">
                <input type="checkbox" id="add_item_np_select_all" aria-label="Select all rows">
              </th>
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
              $summaryRows = gso_fund_inventory_summary_rows($conn, $fundKey);
              if ($summaryRows && $summaryRows->num_rows > 0):
                while ($row = $summaryRows->fetch_assoc()):
                  $poRaw = trim((string)($row['purchase_order'] ?? ''));
                  $po = htmlspecialchars($poRaw, ENT_QUOTES, 'UTF-8');
                  $rowId = (int)($row['row_id'] ?? 0);
                  $itemIdsCsv = trim((string)($row['item_ids_csv'] ?? ''));
            ?>
              <tr class="add-item-np-row">
                <td class="text-center">
                  <input
                    type="checkbox"
                    class="add-item-np-checkbox"
                    value="<?php echo $rowId; ?>"
                    data-item-ids="<?php echo htmlspecialchars($itemIdsCsv, ENT_QUOTES, 'UTF-8'); ?>"
                    aria-label="Select row <?php echo $rowId; ?>">
                </td>
                <td class="font-weight-bold"><?php echo $showText($row['purchase_order'] ?? ''); ?></td>
                <td><?php echo $showText($row['purchase_request'] ?? ''); ?></td>
                <td><?php echo $showText($row['obr_number'] ?? ''); ?></td>
                <td><?php echo !empty($row['supplier']) ? htmlspecialchars((string)$row['supplier']) : "<span class='text-muted'>-</span>"; ?></td>
                <td><?php echo !empty($row['department_name']) ? htmlspecialchars((string)$row['department_name']) : "<span class='text-muted'>-</span>"; ?></td>
                <td><?php echo '₱ ' . number_format((float)($row['total_amount'] ?? 0), 2, '.', ','); ?></td>
                <td class="text-center">
                  <button
                    type="button"
                    class="btn btn-xs btn-outline-primary np-edit-btn"
                    data-row-id="<?php echo $rowId; ?>"
                    data-po="<?php echo $po; ?>"
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
    </div>
  </section>
</div>

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
        <input type="hidden" name="source_context" value="fund_inventory">
        <input type="hidden" name="fund_inventory_key" value="<?php echo htmlspecialchars($fundKey, ENT_QUOTES); ?>">
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
                      <input type="text" class="form-control text-uppercase" id="edit_np_pr" name="purchase_request" placeholder="Enter purchase request no.">
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
                      <input type="text" class="form-control text-uppercase" id="edit_np_po" name="purchase_order" placeholder="Enter purchase order">
                    </div>
                    <div class="form-group col-md-4 mb-0">
                      <label>O.B.R</label>
                      <input type="text" class="form-control text-uppercase" id="edit_np_obr" name="obr_number" placeholder="Enter obr">
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
                      <label class="col-form-label">Department</label>
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
                      <label>End User <span class="text-muted">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                        <input type="checkbox" class="form-check-input" id="editNpMultipleEndUserCheckBox">
                        <label class="form-check-label" for="editNpMultipleEndUserCheckBox">add multiple enduser</label>
                      </span></label>
                      <select id="edit_np_emp_single" class="form-control" autocomplete="off" style="display:none;">
                        <option value="">-SELECT-</option>
                      </select>
                      <div id="editNpEndUserRows" style="display:none; max-height:260px; overflow-y:auto;"></div>
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
                      <input type="number" class="form-control" id="edit_np_set_count" name="set_count" value="0" min="1" max="10" step="1">
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

<?php include('../include/footer.php') ?>
<?php include('../include/script.php') ?>
