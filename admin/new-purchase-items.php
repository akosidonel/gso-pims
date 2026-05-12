<?php
include_once('../config/session.php');
include('../config/check_session.php');

if (!isset($_SESSION['alogin'])) {
    header('Location:../index.php');
    exit();
}

$po = trim((string)($_GET['po'] ?? ''));
if ($po === '') {
    header('Location: add-item.php');
    exit();
}

// Fetch items for this P.O.
$sql = "
    SELECT
      np.id,
        np.item, np.model, np.description,
        np.serial_number, np.serial_number_2, np.property_number,
        np.fund, np.category, np.unit_value, np.date_aquired,
        np.account_code, np.supplier, np.par_ics_number,
        np.purchase_request, np.obr_number, np.jev_number, np.remarks,
        COALESCE(d_by_id.department_name, d_by_code.department_name) AS department_name,
        COALESCE(d_by_id.department_code, d_by_code.department_code) AS department_code,
        h.dept_id, e.emp_name,
        h.category AS doc_type, h.reference_number
    FROM new_purchase AS np
    LEFT JOIN new_purchase_history AS h
      ON (h.par_number = np.property_number OR h.par_number = CONCAT('NPID:', np.id)) AND h.status = 1
    LEFT JOIN department AS d_by_id ON d_by_id.dept_id = h.dept_id
    LEFT JOIN department AS d_by_code ON d_by_code.department_code = h.dept_id
    LEFT JOIN employee   AS e ON e.emp_id  = h.emp_id
    WHERE np.purchase_order = ?
      AND (
        np.property_number IS NULL
        OR np.property_number = ''
        OR np.id = (
          SELECT MIN(np2.id)
          FROM new_purchase AS np2
          WHERE np2.property_number = np.property_number
        )
      )
    ORDER BY np.id ASC
";
$stmt = $conn->prepare($sql);
$stmt->bind_param('s', $po);
$stmt->execute();
$items = $stmt->get_result();

$accountCodeOptions = [];
$accountCodeSql = "SELECT account_code, account_name FROM account_code ORDER BY account_code ASC";
$accountCodeResult = mysqli_query($conn, $accountCodeSql);
if ($accountCodeResult) {
  while ($accountCodeRow = mysqli_fetch_assoc($accountCodeResult)) {
    $accountCodeOptions[] = [
      'account_code' => (string)($accountCodeRow['account_code'] ?? ''),
      'account_name' => (string)($accountCodeRow['account_name'] ?? ''),
    ];
  }
}
?>
<?php include('../include/header.php') ?>
<?php include('../include/navbar.php') ?>
<?php include('../include/sidebar.php') ?>

<style>
  #npEditModal .add-item-section + .add-item-section {
    margin-top: 1rem;
  }

  #npEditModal .gso-card {
    margin-bottom: 0.25rem;
  }
</style>

<div id="destroy"></div>

<div class="content-wrapper">
  <section class="content-header">
    <div class="container-fluid">
      <div class="row mb-2">
        <div class="col-sm-6">
          <h1>New Purchase Items</h1>
        </div>
        <div class="col-sm-6">
          <ol class="breadcrumb float-sm-right">
            <li class="breadcrumb-item"><a href="dashboard.php">Home</a></li>
            <li class="breadcrumb-item"><a href="add-item.php">Add Item</a></li>
            <li class="breadcrumb-item active">New Purchase Items</li>
          </ol>
        </div>
      </div>
    </div>
  </section>

  <section class="content">
    <div class="card">
      <div class="card-header">
        <h3 class="card-title">
          <i class="fas fa-clipboard"></i>&nbsp; Items for P.O.: <strong><?= htmlspecialchars($po) ?></strong>
        </h3>
        <div class="card-tools">
          <a href="add-item.php" class="btn btn-sm btn-outline-secondary">
            <i class="fas fa-arrow-left"></i>&nbsp; Back
          </a>
        </div>
      </div>
      <div class="card-body">
        <div class="table-responsive">
          <table id="npItemsTable" class="table table-bordered table-hover w-100" style="width:100%">
            <thead>
              <tr class="bg-dark text-light bg-gradient bg-opacity-150">
                <th class="text-center"><input type="checkbox" id="np_items_select_all" aria-label="Select all"></th>
                <th>ASSET CLASS</th>
                <th>PARTICULARS</th>
                <th>SNID NO.1</th>
                <th>SNID NO.2</th>
                <th>PROPERTY NO.</th>
                <th>DEPARTMENT</th>
                <th>END USER</th>
                <th class="text-center">ACTION</th>
              </tr>
            </thead>
            <tbody>
              <?php while ($row = $items->fetch_assoc()):
                $model       = trim((string)($row['model']       ?? ''));
                $description = trim((string)($row['description'] ?? ''));
                $particulars = ($model !== '' && $description !== '')
                  ? ($model . ' - ' . $description)
                  : ($model !== '' ? $model : $description);
              ?>
              <?php
                $editData = json_encode([
                  'id'               => (string)($row['id']               ?? ''),
                  'item'             => (string)($row['item']             ?? ''),
                  'model'            => (string)($row['model']            ?? ''),
                  'description'      => (string)($row['description']      ?? ''),
                  'serial_number'    => (string)($row['serial_number']    ?? ''),
                  'serial_number_2'  => (string)($row['serial_number_2']  ?? ''),
                  'property_number'  => (string)($row['property_number']  ?? ''),
                  'fund'             => (string)($row['fund']             ?? ''),
                  'category'         => (string)($row['category']         ?? ''),
                  'unit_value'       => (string)($row['unit_value']       ?? ''),
                  'date_aquired'     => (string)($row['date_aquired']     ?? ''),
                  'account_code'     => (string)($row['account_code']     ?? ''),
                  'dept'             => (string)($row['department_code']   ?? $row['dept_id'] ?? ''),
                  'supplier'         => (string)($row['supplier']         ?? ''),
                  'par_ics_number'   => (string)($row['par_ics_number']   ?? ''),
                  'purchase_request' => (string)($row['purchase_request'] ?? ''),
                  'obr_number'       => (string)($row['obr_number']       ?? ''),
                  'jev_number'       => (string)($row['jev_number']       ?? ''),
                  'remarks'          => (string)($row['remarks']          ?? ''),
                ], JSON_HEX_APOS | JSON_HEX_QUOT);
              ?>
              <tr>
                <td class="text-center">
                  <input type="checkbox" class="np-items-checkbox"
                    value="<?= htmlspecialchars((string)($row['property_number'] ?? '')) ?>"
                    data-doc-type="<?= htmlspecialchars(strtoupper((string)($row['doc_type'] ?? ''))) ?>"
                    data-ref-number="<?= htmlspecialchars((string)($row['reference_number'] ?? '')) ?>"
                    aria-label="Select row">
                </td>
                <td><?= htmlspecialchars((string)($row['item'] ?? '')) ?></td>
                <td><?= htmlspecialchars($particulars) ?></td>
                <td><?= !empty($row['serial_number'])   ? htmlspecialchars((string)$row['serial_number'])   : '<span class="text-muted">&mdash;</span>' ?></td>
                <td><?= !empty($row['serial_number_2']) ? htmlspecialchars((string)$row['serial_number_2']) : '<span class="text-muted">&mdash;</span>' ?></td>
                <td><?= htmlspecialchars((string)($row['property_number'] ?? '')) ?></td>
                <td><?= htmlspecialchars((string)($row['department_name'] ?? '')) ?></td>
                <td><?= htmlspecialchars((string)($row['emp_name']        ?? '')) ?></td>
                <td class="text-center">
                  <button type="button" class="btn btn-sm btn-warning np-edit-btn"
                    data-item='<?= htmlspecialchars($editData, ENT_QUOTES, 'UTF-8') ?>'
                    title="Edit item">
                    <i class="fas fa-edit"></i>
                  </button>
                </td>
              </tr>
              <?php endwhile; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </section>
</div>

<!-- ============================================================ -->
<!-- Edit New Purchase Item Modal                                -->
<!-- ============================================================ -->
<div class="modal fade" id="npEditModal" data-backdrop="static" tabindex="-1" role="dialog">
  <div class="modal-dialog modal-xl modal-dialog-scrollable" role="document">
    <div class="modal-content gso-modal">
      <div class="modal-header border-0 p-0">
        <div class="gso-hero w-100 gso-hero-modalhead">
          <div class="card-body py-3">
            <div class="d-flex align-items-start justify-content-between flex-wrap">
              <div class="mb-2 mb-md-0">
                <div class="gso-title gso-title-sm">Edit Item or Equipment</div>
              </div>
              <button type="button" class="close text-white" style="opacity:.8;" data-dismiss="modal"><span>&times;</span></button>
            </div>
          </div>
        </div>
      </div>
      <form id="npEditForm">
        <div class="modal-body">
          <input type="hidden" name="update_new_purchase_item" value="1">
          <input type="hidden" name="new_purchase_id" id="edit_new_purchase_id">
          <input type="hidden" name="property_number" id="edit_property_number">

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
                      <label>Fund</label>
                      <select name="fund" id="edit_fund" class="form-control" required>
                        <option value="">-SELECT-</option>
                        <option value="GENERAL FUND">GENERAL FUND</option>
                        <option value="SEF">SEF</option>
                      </select>
                    </div>
                    <div class="form-group col-md-6">
                      <label>Category</label>
                      <select name="category" id="edit_category" class="form-control">
                        <option value="">-SELECT-</option>
                        <option value="PAR">PAR</option>
                        <option value="ICS">ICS</option>
                      </select>
                    </div>
                  </div>
                  <div class="form-row">
                    <div class="form-group col-md-12">
                      <label>Account Code</label>
                      <select class="form-control" name="account_code" id="edit_account_code">
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
                          <option value="<?= htmlspecialchars($accountCodeValue) ?>"><?= htmlspecialchars($accountCodeLabel) ?></option>
                        <?php endforeach; ?>
                      </select>
                    </div>
                  </div>
                  <div class="form-row">
                    <div class="form-group col-md-6">
                      <label>Property No.</label>
                      <input type="text" class="form-control text-uppercase" name="new_property_number" id="show_property_number" readonly>
                      <small id="edit_property_number_help" class="form-text text-muted">Generated from account code.</small>
                    </div>
                    <div class="form-group col-md-6">
                      <label>Year Acquired</label>
                      <select class="form-control" name="date_aquired" id="edit_date_aquired">
                        <option value="">-SELECT-</option>
                        <?php
                          $currentYear = (int)date('Y');
                          $startYear = 2001;
                          for ($year = $currentYear; $year >= $startYear; $year--) {
                            echo '<option value="' . $year . '">' . $year . '</option>';
                          }
                        ?>
                        <option value="RFS">Found at Station</option>
                      </select>
                    </div>
                  </div>
                  <div class="form-row mb-0">
                    <div class="form-group col-md-12 mb-0">
                      <label>Supplier</label>
                      <input type="text" class="form-control text-uppercase" name="supplier" id="edit_supplier">
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <div class="col-lg-6 d-flex mt-3 mt-lg-0">
              <div class="card gso-card w-100 h-100 mb-0">
                <div class="card-header border-0">
                  <div class="d-flex justify-content-between align-items-center">
                    <h3 class="card-title mb-0"><i class="fas fa-clipboard-check"></i>&nbsp; Document Details</h3>
                  </div>
                </div>
                <div class="card-body">
                  <div class="form-row">
                    <div class="form-group col-md-6">
                      <label>PAR / ICS No.</label>
                      <input type="text" class="form-control text-uppercase" name="par_ics_number" id="edit_par_ics_number">
                    </div>
                    <div class="form-group col-md-6">
                      <label>Purchase Request No.</label>
                      <input type="text" class="form-control text-uppercase" name="purchase_request" id="edit_purchase_request">
                    </div>
                  </div>
                  <div class="form-row">
                    <div class="form-group col-md-6">
                      <label>OBR No.</label>
                      <input type="text" class="form-control text-uppercase" name="obr_number" id="edit_obr_number">
                    </div>
                    <div class="form-group col-md-6">
                      <label>JEV No.</label>
                      <input type="text" class="form-control text-uppercase" name="jev_number" id="edit_jev_number">
                    </div>
                  </div>
                  <div class="form-row mb-0">
                    <div class="form-group col-md-12 mb-0">
                      <label>Remarks</label>
                      <input type="text" class="form-control text-uppercase" name="remarks" id="edit_remarks">
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
                  <div class="d-flex justify-content-between align-items-center">
                    <h3 class="card-title mb-0"><i class="fas fa-file-alt"></i>&nbsp; Item Information</h3>
                  </div>
                </div>
                <div class="card-body">
                  <div class="form-row">
                    <div class="form-group col-md-4">
                      <label>Asset Class</label>
                      <input type="text" class="form-control text-uppercase" name="item" id="edit_item" required>
                    </div>
                    <div class="form-group col-md-4">
                      <label>Brand / Model</label>
                      <input type="text" class="form-control text-uppercase" name="model" id="edit_model">
                    </div>
                    <div class="form-group col-md-4">
                      <label>Unit Value</label>
                      <input type="text" inputmode="decimal" class="form-control text-right" name="unit_value" id="edit_unit_value" placeholder="0.00" autocomplete="off">
                    </div>
                  </div>
                  <div class="form-row">
                    <div class="form-group col-md-6">
                      <label>Primary Serial No.</label>
                      <input type="text" class="form-control text-uppercase" name="serial_number" id="edit_serial_number">
                    </div>
                    <div class="form-group col-md-6">
                      <label>Secondary Serial No.</label>
                      <input type="text" class="form-control text-uppercase" name="serial_number_2" id="edit_serial_number_2">
                    </div>
                  </div>
                  <div class="form-row mb-0">
                    <div class="form-group col-md-12 mb-0">
                      <label>Description</label>
                      <textarea class="form-control text-uppercase" name="description" id="edit_description" rows="4"></textarea>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="modal-footer border-0 pt-0">
          <button type="submit" class="btn btn-success" id="npEditSaveBtn">
            <i class="fas fa-save"></i>&nbsp; Save Changes
          </button>
          <button type="button" class="btn btn-outline-success" data-dismiss="modal">Close</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php include('../include/footer.php') ?>
</div>
<?php include('../include/script.php') ?>
