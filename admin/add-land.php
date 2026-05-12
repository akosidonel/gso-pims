<?php
include_once('../config/session.php');
include('../config/check_session.php');

if (!isset($_SESSION['alogin'])) {
    header('Location:../index.php');
    exit();
}

function land_h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$landFunds = ['GENERAL FUND', 'SEF', 'TRUST FUND'];
$landClassifications = ['SOCIALIZED HOUSING', 'PUBLIC SCHOOL', 'PUBLIC HOSPITAL', 'OPEN SPACE', 'PUBLIC MARKET', 'ROAD LOT', 'EASEMENT', 'RIGHT OF WAY', 'DAYCARE CENTER', 'BARANGAY HALL', 'HEALTH CENTER', 'OTHER GOVERNMENT USE'];
$landStatuses = ['ON PROCESS', 'INCOMPLETE DOCUMENTS', 'PENDING TRANSFER', 'TRANSFERRED'];
$landBarangays = ['MERVILLE', 'SMDP', 'SUN VALLEY', 'DON BOSCO', 'MARCELO', 'BF HOMES', 'SAN ANTONIO', 'MOONWALK', 'SAN ISIDRO', 'SAN DIONISIO', 'LA HUERTA', 'DON GALO', 'STO. NINO', 'VITALEZ', 'TAMBO', 'BACLARAN'];
?>
<?php include('../include/header.php')?>
<?php include('../include/navbar.php')?>
<?php include('../include/sidebar.php')?>

<style>
  .add-item-section + .add-item-section {
    margin-top: 1rem;
  }

  #addLandModal .modal-content > form#addLandForm,
  #editLandModal .modal-content > form#editLandForm {
    display: flex;
    flex-direction: column;
    min-height: 0;
    max-height: inherit;
    overflow: hidden;
  }

  #addLandModal form#addLandForm .modal-body,
  #editLandModal form#editLandForm .modal-body {
    flex: 1 1 auto;
    min-height: 0;
    overflow-y: auto;
    overflow-x: hidden;
    -webkit-overflow-scrolling: touch;
  }

  #addLandModal form#addLandForm .modal-footer,
  #editLandModal form#editLandForm .modal-footer {
    flex-shrink: 0;
  }

  .land-label-line {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: .75rem;
  }

  .land-label-line .form-check {
    margin-bottom: .5rem;
  }
</style>

<div id="destroy"></div>

<div class="content-wrapper">
  <section class="content-header">
    <div class="container-fluid">
      <div class="row mb-2">
        <div class="col-sm-6"><h1>Add Land Property</h1></div>
        <div class="col-sm-6">
          <ol class="breadcrumb float-sm-right">
            <li class="breadcrumb-item"><a href="dashboard.php">Home</a></li>
            <li class="breadcrumb-item active">Land</li>
          </ol>
        </div>
      </div>
    </div>
  </section>

  <section class="content">
    <div class="card">
      <div class="card-header">
        <h3 class="card-title"><i class="fas fa-map-marked-alt"></i>&nbsp; Land Property Registry</h3>
        <div class="card-tools">
          <button type="button" class="btn btn-block bg-gradient-success btn-sm" data-toggle="modal" data-target="#addLandModal">
            <i class="fa-solid fa-keyboard"></i>&nbsp; Add Land
          </button>
        </div>
      </div>
      <div class="card-body">
        <div id="landTableFilterControls" class="d-none">
          <div class="form-group mb-0">
            <label for="landTableClassificationFilter">Classification</label>
            <select class="form-control form-control-sm" id="landTableClassificationFilter">
              <option value="">ALL</option>
              <?php foreach ($landClassifications as $classification): ?><option value="<?php echo land_h($classification); ?>"><?php echo land_h($classification); ?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="form-group mb-0">
            <label for="landTableBarangayFilter">Barangay</label>
            <select class="form-control form-control-sm" id="landTableBarangayFilter">
              <option value="">ALL</option>
              <?php foreach ($landBarangays as $barangay): ?><option value="<?php echo land_h($barangay); ?>"><?php echo land_h($barangay); ?></option><?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="table-responsive">
          <table id="landPropertyTable" data-dt-custom="1" class="table table-bordered table-hover w-100">
            <thead>
              <tr class="bg-dark text-light bg-gradient bg-opacity-150">
                <th>ACTION</th>
                <th>FUND</th>
                <th>CLASSIFICATION</th>
                <th>OWNER</th>
                <th>TCT NO.</th>
                <th>PROJECT NAME</th>
                <th>AREA</th>
                <th>BARANGAY</th>
                <th>ACQUISITION COST</th>
                <th>CAPITAL GAIN TAX</th>
                <th>DOCUMENTARY STAMP</th>
                <th>OTHER INCIDENTAL TRANSFER FEES</th>
                <th>TOTAL AMOUNT</th>
                <th>STATUS</th>
              </tr>
            </thead>
            <tbody></tbody>
          </table>
        </div>
      </div>
    </div>
  </section>
</div>

<div class="modal fade" id="addLandModal" data-backdrop="static" tabindex="-1" role="dialog" aria-labelledby="addLandModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable" role="document">
    <div class="modal-content gso-modal">
      <div class="modal-header border-0 p-0">
        <div class="gso-hero w-100 gso-hero-modalhead">
          <div class="card-body py-3">
            <div class="d-flex align-items-start justify-content-between flex-wrap">
              <div class="mb-2 mb-md-0">
                <div class="gso-title gso-title-sm" id="addLandModalLabel">Land Property Details</div>
              </div>
              <button type="button" class="close text-white" style="opacity:.8;" data-dismiss="modal"><span>&times;</span></button>
            </div>
          </div>
        </div>
      </div>

      <form id="addLandForm">
        <div class="modal-body">
          <div class="row add-item-section">
            <div class="col-lg-6 d-flex mb-3">
              <div class="card gso-card w-100 h-100 mb-0">
                <div class="card-header border-0"><h3 class="card-title mb-0"><i class="fas fa-bookmark"></i>&nbsp; Reference</h3></div>
                <div class="card-body">
                  <div class="form-row">
                    <div class="form-group col-md-4">
                      <label for="land_fund_cluster">Fund Cluster</label>
                      <select class="form-control" id="land_fund_cluster" name="fund_cluster" required>
                        <option value="">-SELECT-</option>
                        <?php foreach ($landFunds as $fund): ?><option value="<?php echo land_h($fund); ?>"><?php echo land_h($fund); ?></option><?php endforeach; ?>
                      </select>
                    </div>
                    <div class="form-group col-md-8">
                      <label for="land_classification">Classification</label>
                      <select class="form-control" id="land_classification" name="classification" required>
                        <option value="">-SELECT-</option>
                        <?php foreach ($landClassifications as $classification): ?><option value="<?php echo land_h($classification); ?>"><?php echo land_h($classification); ?></option><?php endforeach; ?>
                      </select>
                    </div>
                  </div>
                  <div class="form-row">
                    <div class="form-group col-md-6">
                      <label for="land_declared_owner">Declared / Registered Owner</label>
                      <input type="text" class="form-control text-uppercase" id="land_declared_owner" name="declared_owner" required>
                    </div>
                    <div class="form-group col-md-6">
                      <label for="land_tct_no">TCT No.</label>
                      <input type="text" class="form-control text-uppercase" id="land_tct_no" name="tct_no" required>
                    </div>
                  </div>
                  <div class="form-row">
                    <div class="form-group col-md-6">
                      <label for="land_area_sqm">Area Sq. Meter</label>
                      <input type="number" class="form-control" id="land_area_sqm" name="area_sqm" min="0" step="0.01" value="0.00" required>
                    </div>
                    <div class="form-group col-md-6">
                      <div class="land-label-line">
                        <label for="land_date_acquired">Date Acquired</label>
                        <div class="form-check">
                          <input class="form-check-input land-none-toggle" type="checkbox" id="land_date_acquired_none" data-target="#land_date_acquired" data-hidden="#land_date_acquired_na">
                          <label class="form-check-label" for="land_date_acquired_none">None</label>
                        </div>
                      </div>
                      <input type="date" class="form-control" id="land_date_acquired" name="date_acquired" required>
                      <input type="hidden" id="land_date_acquired_na" name="date_acquired" value="N/A" disabled>
                    </div>
                  </div>
                  <div class="form-group mb-0">
                    <label for="land_project_name">Project Name</label>
                    <input type="text" class="form-control text-uppercase" id="land_project_name" name="project_name">
                  </div>
                </div>
              </div>
            </div>

            <div class="col-lg-6 d-flex mb-3">
              <div class="card gso-card w-100 h-100 mb-0">
                <div class="card-header border-0"><h3 class="card-title mb-0"><i class="fas fa-map-marker-alt"></i>&nbsp; Location and Status</h3></div>
                <div class="card-body">
                  <div class="form-row">
                    <div class="form-group col-md-8">
                      <div class="land-label-line">
                        <label for="land_address">Address</label>
                        <div class="form-check">
                          <input class="form-check-input land-none-toggle" type="checkbox" id="land_address_none" data-target="#land_address">
                          <label class="form-check-label" for="land_address_none">None</label>
                        </div>
                      </div>
                      <input type="text" class="form-control text-uppercase" id="land_address" name="address" required>
                    </div>
                    <div class="form-group col-md-4">
                      <div class="land-label-line">
                        <label for="land_barangay">Barangay</label>
                        <div class="form-check">
                          <input class="form-check-input land-none-toggle" type="checkbox" id="land_barangay_none" data-target="#land_barangay">
                          <label class="form-check-label" for="land_barangay_none">None</label>
                        </div>
                      </div>
                      <select class="form-control" id="land_barangay" name="barangay" required>
                        <option value="">-SELECT-</option>
                        <option value="N/A">N/A</option>
                        <?php foreach ($landBarangays as $barangay): ?><option value="<?php echo land_h($barangay); ?>"><?php echo land_h($barangay); ?></option><?php endforeach; ?>
                      </select>
                    </div>
                  </div>
                  <div class="form-row">
                    <div class="form-group col-md-6">
                      <label for="land_transfer_status">Transfer Status</label>
                      <select class="form-control" id="land_transfer_status" name="transfer_status" required>
                        <option value="">-SELECT-</option>
                        <option value="NO">NO</option>
                        <option value="TRANSFERRED">TRANSFERRED</option>
                      </select>
                    </div>
                    <div class="form-group col-md-6">
                      <label for="land_current_status">Progress Status</label>
                      <select class="form-control" id="land_current_status" name="current_status" required>
                        <option value="">-SELECT-</option>
                        <?php foreach ($landStatuses as $status): ?><option value="<?php echo land_h($status); ?>"><?php echo land_h($status); ?></option><?php endforeach; ?>
                      </select>
                    </div>
                  </div>
                  <div class="form-group mb-0">
                    <label for="land_remarks">Remarks</label>
                    <textarea class="form-control text-uppercase" id="land_remarks" name="remarks" rows="5"></textarea>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div class="row add-item-section">
            <div class="col-lg-6 d-flex mb-3">
              <div class="card gso-card w-100 h-100 mb-0">
                <div class="card-header border-0"><h3 class="card-title mb-0"><i class="fas fa-coins"></i>&nbsp; Acquisition Cost</h3></div>
                <div class="card-body">
                  <div class="form-row">
                    <div class="form-group col-md-6"><label for="land_acquisition_cost">Acquisition Cost</label><input type="text" class="form-control land-money land-money-format" id="land_acquisition_cost" name="acquisition_cost" inputmode="decimal" value="0.00" required></div>
                    <div class="form-group col-md-6"><label for="land_documentary_stamp_tax">Documentary Stamp Tax</label><input type="text" class="form-control land-money land-money-format" id="land_documentary_stamp_tax" name="documentary_stamp_tax" inputmode="decimal" value="0.00"></div>
                  </div>
                  <div class="form-row">
                    <div class="form-group col-md-6"><label for="land_capital_gains_tax">Capital Gains Tax</label><input type="text" class="form-control land-money land-money-format" id="land_capital_gains_tax" name="capital_gains_tax" inputmode="decimal" value="0.00"></div>
                    <div class="form-group col-md-6"><label for="land_other_fees">Other Incidental Transfer Fees</label><input type="text" class="form-control land-money land-money-format" id="land_other_fees" name="other_incidental_transfer_fees" inputmode="decimal" value="0.00"></div>
                  </div>
                  <div class="form-group mb-0"><label for="land_total_amount">Total Amount</label><input type="text" class="form-control" id="land_total_amount" name="total_amount" value="0.00" readonly></div>
                </div>
              </div>
            </div>

            <div class="col-lg-6 d-flex mb-3">
              <div class="card gso-card w-100 h-100 mb-0">
                <div class="card-header border-0"><h3 class="card-title mb-0"><i class="fas fa-file-signature"></i>&nbsp; Documents</h3></div>
                <div class="card-body">
                  <div class="form-row">
                    <div class="form-group col-md-4"><label for="land_has_original_tct">Original TCT?</label><select class="form-control" id="land_has_original_tct" name="has_original_tct" required><option value="">-SELECT-</option><option value="NO">NO</option><option value="ORIGINAL">ORIGINAL</option><option value="CERTIFIED TRUE COPY">CERTIFIED TRUE COPY</option><option value="PHOTOCOPY">PHOTOCOPY</option></select></div>
                    <div class="form-group col-md-4"><label for="land_has_doas">DOAS?</label><select class="form-control" id="land_has_doas" name="has_doas" required><option value="">-SELECT-</option><option value="NO">NO</option><option value="YES">YES</option><option value="N/A">N/A</option><option value="PHOTOCOPY">PHOTOCOPY</option><option value="CERTIFIED COPY">CERTIFIED COPY</option></select></div>
                    <div class="form-group col-md-4"><label for="land_has_dod">DOD?</label><select class="form-control" id="land_has_dod" name="has_dod" required><option value="">-SELECT-</option><option value="NO">NO</option><option value="YES">YES</option><option value="N/A">N/A</option><option value="PHOTOCOPY">PHOTOCOPY</option><option value="CERTIFIED COPY">CERTIFIED COPY</option></select></div>
                  </div>
                  <div class="form-group">
                    <label for="land_tax_declaration_no">Tax Declaration No.</label>
                    <input type="text" class="form-control text-uppercase" id="land_tax_declaration_no" name="tax_declaration_no">
                  </div>
                  <div class="form-group mb-0">
                    <label for="land_other_supporting_documents">Other Supporting Documents</label>
                    <textarea class="form-control text-uppercase" id="land_other_supporting_documents" name="other_supporting_documents" rows="4"></textarea>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="modal-footer border-0 pt-0">
          <button type="submit" class="btn btn-success" id="addLandSubmitBtn"><i class="fas fa-save"></i>&nbsp;<span class="btn-text">Save</span></button>
          <button type="button" class="btn btn-outline-success" data-dismiss="modal">Close</button>
        </div>
      </form>
    </div>
  </div>
</div>

<div class="modal fade" id="editLandModal" data-backdrop="static" tabindex="-1" role="dialog" aria-labelledby="editLandModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable" role="document">
    <div class="modal-content gso-modal">
      <div class="modal-header border-0 p-0">
        <div class="gso-hero w-100 gso-hero-modalhead">
          <div class="card-body py-3">
            <div class="d-flex align-items-start justify-content-between flex-wrap">
              <div class="mb-2 mb-md-0"><div class="gso-title gso-title-sm" id="editLandModalLabel">Edit Land Property</div></div>
              <button type="button" class="close text-white" style="opacity:.8;" data-dismiss="modal"><span>&times;</span></button>
            </div>
          </div>
        </div>
      </div>

      <form id="editLandForm">
        <input type="hidden" id="edit_land_id" name="land_id">
        <div class="modal-body">
          <div class="row add-item-section">
            <div class="col-lg-6 d-flex mb-3">
              <div class="card gso-card w-100 h-100 mb-0">
                <div class="card-header border-0"><h3 class="card-title mb-0"><i class="fas fa-bookmark"></i>&nbsp; Reference</h3></div>
                <div class="card-body">
                  <div class="form-row">
                    <div class="form-group col-md-4"><label>Fund Cluster</label><select class="form-control" id="edit_land_fund_cluster" name="fund_cluster" required><option value="">-SELECT-</option><?php foreach ($landFunds as $fund): ?><option value="<?php echo land_h($fund); ?>"><?php echo land_h($fund); ?></option><?php endforeach; ?></select></div>
                    <div class="form-group col-md-8"><label>Classification</label><select class="form-control" id="edit_land_classification" name="classification" required><option value="">-SELECT-</option><?php foreach ($landClassifications as $classification): ?><option value="<?php echo land_h($classification); ?>"><?php echo land_h($classification); ?></option><?php endforeach; ?></select></div>
                  </div>
                  <div class="form-row">
                    <div class="form-group col-md-6"><label>Declared / Registered Owner</label><input type="text" class="form-control text-uppercase" id="edit_land_declared_owner" name="declared_owner" required></div>
                    <div class="form-group col-md-6"><label>TCT No.</label><input type="text" class="form-control text-uppercase" id="edit_land_tct_no" name="tct_no" required></div>
                  </div>
                  <div class="form-row">
                    <div class="form-group col-md-6"><label>Area Sq. Meter</label><input type="number" class="form-control" id="edit_land_area_sqm" name="area_sqm" min="0" step="0.01" value="0.00" required></div>
                    <div class="form-group col-md-6"><div class="land-label-line"><label for="edit_land_date_acquired">Date Acquired</label><div class="form-check"><input class="form-check-input land-none-toggle" type="checkbox" id="edit_land_date_acquired_none" data-target="#edit_land_date_acquired" data-hidden="#edit_land_date_acquired_na"><label class="form-check-label" for="edit_land_date_acquired_none">None</label></div></div><input type="date" class="form-control" id="edit_land_date_acquired" name="date_acquired" required><input type="hidden" id="edit_land_date_acquired_na" name="date_acquired" value="N/A" disabled></div>
                  </div>
                  <div class="form-group mb-0"><label>Project Name</label><input type="text" class="form-control text-uppercase" id="edit_land_project_name" name="project_name"></div>
                </div>
              </div>
            </div>
            <div class="col-lg-6 d-flex mb-3">
              <div class="card gso-card w-100 h-100 mb-0">
                <div class="card-header border-0"><h3 class="card-title mb-0"><i class="fas fa-map-marker-alt"></i>&nbsp; Location and Status</h3></div>
                <div class="card-body">
                  <div class="form-row">
                    <div class="form-group col-md-8"><div class="land-label-line"><label for="edit_land_address">Address</label><div class="form-check"><input class="form-check-input land-none-toggle" type="checkbox" id="edit_land_address_none" data-target="#edit_land_address"><label class="form-check-label" for="edit_land_address_none">None</label></div></div><input type="text" class="form-control text-uppercase" id="edit_land_address" name="address" required></div>
                    <div class="form-group col-md-4"><div class="land-label-line"><label for="edit_land_barangay">Barangay</label><div class="form-check"><input class="form-check-input land-none-toggle" type="checkbox" id="edit_land_barangay_none" data-target="#edit_land_barangay"><label class="form-check-label" for="edit_land_barangay_none">None</label></div></div><select class="form-control" id="edit_land_barangay" name="barangay" required><option value="">-SELECT-</option><option value="N/A">N/A</option><?php foreach ($landBarangays as $barangay): ?><option value="<?php echo land_h($barangay); ?>"><?php echo land_h($barangay); ?></option><?php endforeach; ?></select></div>
                  </div>
                  <div class="form-row">
                    <div class="form-group col-md-6"><label>Transfer Status</label><select class="form-control" id="edit_land_transfer_status" name="transfer_status" required><option value="">-SELECT-</option><option value="NO">NO</option><option value="TRANSFERRED">TRANSFERRED</option></select></div>
                    <div class="form-group col-md-6"><label>Progress Status</label><select class="form-control" id="edit_land_current_status" name="current_status" required><option value="">-SELECT-</option><?php foreach ($landStatuses as $status): ?><option value="<?php echo land_h($status); ?>"><?php echo land_h($status); ?></option><?php endforeach; ?></select></div>
                  </div>
                  <div class="form-group mb-0"><label>Remarks</label><textarea class="form-control text-uppercase" id="edit_land_remarks" name="remarks" rows="5"></textarea></div>
                </div>
              </div>
            </div>
          </div>

          <div class="row add-item-section">
            <div class="col-lg-6 d-flex mb-3">
              <div class="card gso-card w-100 h-100 mb-0">
                <div class="card-header border-0"><h3 class="card-title mb-0"><i class="fas fa-coins"></i>&nbsp; Acquisition Cost</h3></div>
                <div class="card-body">
                  <div class="form-row">
                    <div class="form-group col-md-6"><label>Acquisition Cost</label><input type="text" class="form-control land-money land-money-format" id="edit_land_acquisition_cost" name="acquisition_cost" inputmode="decimal" value="0.00" required></div>
                    <div class="form-group col-md-6"><label>Documentary Stamp Tax</label><input type="text" class="form-control land-money land-money-format" id="edit_land_documentary_stamp_tax" name="documentary_stamp_tax" inputmode="decimal" value="0.00"></div>
                  </div>
                  <div class="form-row">
                    <div class="form-group col-md-6"><label>Capital Gains Tax</label><input type="text" class="form-control land-money land-money-format" id="edit_land_capital_gains_tax" name="capital_gains_tax" inputmode="decimal" value="0.00"></div>
                    <div class="form-group col-md-6"><label>Other Incidental Transfer Fees</label><input type="text" class="form-control land-money land-money-format" id="edit_land_other_fees" name="other_incidental_transfer_fees" inputmode="decimal" value="0.00"></div>
                  </div>
                  <div class="form-group mb-0"><label>Total Amount</label><input type="text" class="form-control" id="edit_land_total_amount" name="total_amount" value="0.00" readonly></div>
                </div>
              </div>
            </div>
            <div class="col-lg-6 d-flex mb-3">
              <div class="card gso-card w-100 h-100 mb-0">
                <div class="card-header border-0"><h3 class="card-title mb-0"><i class="fas fa-file-signature"></i>&nbsp; Documents</h3></div>
                <div class="card-body">
                  <div class="form-row">
                    <div class="form-group col-md-4"><label>Original TCT?</label><select class="form-control" id="edit_land_has_original_tct" name="has_original_tct" required><option value="">-SELECT-</option><option value="NO">NO</option><option value="ORIGINAL">ORIGINAL</option><option value="CERTIFIED TRUE COPY">CERTIFIED TRUE COPY</option><option value="PHOTOCOPY">PHOTOCOPY</option></select></div>
                    <div class="form-group col-md-4"><label>DOAS?</label><select class="form-control" id="edit_land_has_doas" name="has_doas" required><option value="">-SELECT-</option><option value="NO">NO</option><option value="YES">YES</option><option value="N/A">N/A</option><option value="PHOTOCOPY">PHOTOCOPY</option><option value="CERTIFIED COPY">CERTIFIED COPY</option></select></div>
                    <div class="form-group col-md-4"><label>DOD?</label><select class="form-control" id="edit_land_has_dod" name="has_dod" required><option value="">-SELECT-</option><option value="NO">NO</option><option value="YES">YES</option><option value="N/A">N/A</option><option value="PHOTOCOPY">PHOTOCOPY</option><option value="CERTIFIED COPY">CERTIFIED COPY</option></select></div>
                  </div>
                  <div class="form-group"><label>Tax Declaration No.</label><input type="text" class="form-control text-uppercase" id="edit_land_tax_declaration_no" name="tax_declaration_no"></div>
                  <div class="form-group mb-0"><label>Other Supporting Documents</label><textarea class="form-control text-uppercase" id="edit_land_other_supporting_documents" name="other_supporting_documents" rows="4"></textarea></div>
                </div>
              </div>
            </div>
          </div>
        </div>
        <div class="modal-footer border-0 pt-0">
          <button type="submit" class="btn btn-success" id="editLandSubmitBtn"><i class="fas fa-save"></i>&nbsp;<span class="btn-text">Update</span></button>
          <button type="button" class="btn btn-outline-success" data-dismiss="modal">Close</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php include('../include/footer.php')?>
</div>
<?php include('../include/script.php')?>

</body>
</html>
