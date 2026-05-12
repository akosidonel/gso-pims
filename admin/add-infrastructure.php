<?php
include_once('../config/session.php');
include('../config/check_session.php');

if (!isset($_SESSION['alogin'])) {
    header('Location:../index.php');
    exit();
}

$infraCatalog = [
    'OTHER STRUCTURE' => '1-07-04-990',
    'MARKET' => '1-07-04-040',
    'HOSPITALS & HEALTH CENTERS' => '1-07-04-030',
    'SCHOOL BUILDING' => '1-07-04-020',
    'OTHER BUILDINGS' => '1-07-04-010',
    'OTHER INFRASTRUCTURE ASSETS' => '1-07-03-990',
    'PARKS, PLAZAS, MONUMENTS' => '1-07-03-090',
    'POWER SUPPLY SYSTEM' => '1-07-04-990',
    'SEWER SYSTEMS' => '1-07-03-990',
    'FLOOD CONTROL SYSTEM' => '1-07-03-020',
    'ROAD NETWORKS' => '1-07-03-010',
    'OTHER LAND IMPROVEMENTS' => '1-07-02-990',
];
?>
<?php include('../include/header.php')?>
<?php include('../include/navbar.php')?>
<?php include('../include/sidebar.php')?>

<div id="destroy"></div>

<div class="content-wrapper">
  <section class="content-header">
    <div class="container-fluid">
      <div class="row mb-2">
        <div class="col-sm-6">
          <h1>Add Infrastructure</h1>
        </div>
        <div class="col-sm-6">
          <ol class="breadcrumb float-sm-right">
            <li class="breadcrumb-item"><a href="dashboard.php">Home</a></li>
            <li class="breadcrumb-item active">Infrastructure</li>
          </ol>
        </div>
      </div>
    </div>
  </section>

  <section class="content">
    <div class="card">
      <div class="card-header">
        <h3 class="card-title" id="reportTitle"><i class="fas fa-city"></i>&nbsp; Infrastructure Records</h3>
        <div class="card-tools">
          <button type="button" class="btn btn-success btn-sm" data-toggle="modal" data-target="#addInfrastructureModal">
            <i class="fas fa-plus"></i>&nbsp; Add Infrastructure
          </button>
        </div>
      </div>
      <div class="card-body">
        <div class="table-responsive">
          <table id="infrastructureTable" data-dt-custom="1" class="table table-bordered table-hover w-100">
            <thead>
              <tr class="bg-dark text-light bg-gradient bg-opacity-150">
                <th>ACCOUNT CODE</th>
                <th>DESCRIPTION</th>
              </tr>
            </thead>
            <tbody></tbody>
          </table>
        </div>
      </div>
    </div>
  </section>
</div>

<div class="modal fade" id="addInfrastructureModal" data-backdrop="static" tabindex="-1" role="dialog" aria-labelledby="addInfrastructureModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable" role="document">
    <div class="modal-content gso-modal">
      <div class="modal-header border-0 p-0">
        <div class="gso-hero w-100 gso-hero-modalhead">
          <div class="card-body py-3">
            <div class="d-flex align-items-start justify-content-between flex-wrap">
              <div class="mb-2 mb-md-0">
                <div class="gso-title gso-title-sm" id="addInfrastructureModalLabel">Infrastructure Details</div>
              </div>
              <div class="mb-2 mb-md-0 d-flex align-items-center">
                <span class="gso-pill">
                  <i class="fas fa-layer-group"></i>
                  <span id="infraFundIndicator">GENERAL FUND</span>
                </span>
              </div>
            </div>
          </div>
        </div>
      </div>

      <form id="addInfrastructureForm">
        <div class="modal-body">
          <div class="row">
            <div class="col-md-6 mb-3">
              <div class="card gso-card h-100 mb-0">
                <div class="card-header border-0">
                  <h3 class="card-title mb-0"><i class="fas fa-file-alt"></i>&nbsp; Main Information</h3>
                </div>
                <div class="card-body">
                  <div class="form-row">
                    <div class="form-group col-md-6">
                      <label for="infra_fund_cluster">Fund</label>
                      <select class="form-control" id="infra_fund_cluster" name="fund_cluster" required>
                        <option value="GENERAL FUND">GENERAL FUND</option>
                        <option value="SPECIAL EDUCATION FUND">SPECIAL EDUCATION FUND</option>
                      </select>
                    </div>
                    <div class="form-group col-md-6">
                      <label for="infra_classification">Classification</label>
                      <select class="form-control" id="infra_classification" name="classification" required>
                        <option value="">-SELECT-</option>
                        <?php foreach ($infraCatalog as $classification => $accountCode): ?>
                          <option value="<?php echo htmlspecialchars($classification, ENT_QUOTES); ?>" data-account-code="<?php echo htmlspecialchars($accountCode, ENT_QUOTES); ?>">
                            <?php echo htmlspecialchars($classification, ENT_QUOTES); ?>
                          </option>
                        <?php endforeach; ?>
                      </select>
                    </div>
                  </div>

                  <div class="form-row">
                    <div class="form-group col-md-6">
                      <label for="infra_account_code">Account Code</label>
                      <input type="text" class="form-control" id="infra_account_code" name="account_code" readonly required>
                    </div>
                    <div class="form-group col-md-6">
                      <label for="infra_condition_status">Condition</label>
                      <select class="form-control" id="infra_condition_status" name="condition_status" required>
                        <option value="SERVICEABLE">SERVICEABLE</option>
                        <option value="UNSERVICEABLE">UNSERVICEABLE</option>
                      </select>
                    </div>
                  </div>

                  <div class="form-row">
                    <div class="form-group col-md-12">
                      <label for="infra_property_number">Property Number</label>
                      <input type="text" class="form-control" id="infra_property_number" name="property_number" value="STANDBY" readonly>
                    </div>
                  </div>

                  <div class="form-group mb-0">
                    <label for="infra_description">Description</label>
                    <textarea class="form-control" id="infra_description" name="description" rows="8" required></textarea>
                  </div>
                </div>
              </div>
            </div>

            <div class="col-md-6 mb-3">
              <div class="card gso-card h-100 mb-0">
                <div class="card-header border-0">
                  <h3 class="card-title mb-0"><i class="fas fa-map-marker-alt"></i>&nbsp; Location and Status</h3>
                </div>
                <div class="card-body">
                  <div class="form-group">
                    <label for="infra_location_name">Location</label>
                    <input type="text" class="form-control" id="infra_location_name" name="location_name" required>
                  </div>

                  <div class="form-row">
                    <div class="form-group col-md-6">
                      <label for="infra_barangay">Barangay</label>
                      <input type="text" class="form-control" id="infra_barangay" name="barangay">
                    </div>
                    <div class="form-group col-md-6">
                      <label for="infra_date_acquired">Date Acquired</label>
                      <input type="date" class="form-control" id="infra_date_acquired" name="date_acquired">
                    </div>
                  </div>

                  <div class="form-row">
                    <div class="form-group col-md-12">
                      <label for="infra_amount">Amount</label>
                      <input type="number" class="form-control" id="infra_amount" name="amount" min="0" step="0.01" value="0.00" required>
                    </div>
                  </div>

                  <input type="hidden" id="infra_year_acquired" name="year_acquired" value="<?php echo date('Y'); ?>">

                  <div class="form-group mb-0">
                    <label for="infra_remarks">Remarks</label>
                    <textarea class="form-control" id="infra_remarks" name="remarks" rows="4"></textarea>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="modal-footer border-0 pt-0">
          <button type="submit" class="btn btn-success" id="addInfrastructureSubmitBtn">
            <i class="fas fa-save"></i>&nbsp;<span class="btn-text">Save</span>
          </button>
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