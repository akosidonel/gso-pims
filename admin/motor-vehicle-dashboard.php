<?php
include_once('../config/session.php');
include('../config/check_session.php');
include_once('../config/auth_helpers.php');

check_admin_role_dynamic_redirect(['SYSTEM-ADMIN', 'MV-ADMIN']);

if (!isset($_SESSION['alogin'])) {
    header('Location:../index.php');
    exit();
}

include('../include/header.php');
include('../include/navbar.php');
include('../include/sidebar.php');
?>

<div id="destroy"></div>

<div class="content-wrapper gso-dashboard" id="motorVehicleDashboard">
    <section class="content">
        <div class="container-fluid">
            <div class="card gso-hero mb-4">
                <div class="card-body">
                    <div class="d-flex align-items-start align-items-md-center justify-content-between flex-wrap">
                        <div class="mb-3 mb-md-0">
                            <div class="gso-title">Motor Vehicle Dashboard</div>
                        </div>

                        <div class="text-md-right">
                            <div class="gso-pill">
                                <i class="fa-solid fa-truck-front"></i>
                                <span>Account codes 1-07-06-010 and 1-07-05-080</span>
                            </div>
                            <div class="gso-meta">General Fund, SEF, Trust Fund, and Donation records</div>
                        </div>
                    </div>
                </div>
            </div>

            <h5 class="gso-section-title">Vehicle Summary</h5>

            <div class="row">
                <div class="col-lg-3 col-sm-6 mb-3">
                    <button type="button" class="gso-stat-link gso-stat-button gso-mv-scope is-active" data-scope="all" aria-pressed="true">
                        <div class="gso-stat-card">
                            <div class="gso-stat-icon"><i class="fa-solid fa-car-side"></i></div>
                            <div>
                                <div class="gso-stat-title">Total Number of Vehicles</div>
                                <div class="gso-stat-value" data-mv-metric="total_vehicles">...</div>
                                <div class="gso-stat-note">Covered assets</div>
                            </div>
                            <div class="gso-stat-chevron"><i class="fas fa-chevron-right"></i></div>
                        </div>
                    </button>
                </div>

                <div class="col-lg-3 col-sm-6 mb-3">
                    <button type="button" class="gso-stat-link gso-stat-button gso-mv-scope" data-scope="registered" aria-pressed="false">
                        <div class="gso-stat-card">
                            <div class="gso-stat-icon"><i class="fa-solid fa-clipboard-check"></i></div>
                            <div>
                                <div class="gso-stat-title">Total Registered Vehicles</div>
                                <div class="gso-stat-value" data-mv-metric="registered_vehicles">...</div>
                                <div class="gso-stat-note">In vehicle registry</div>
                            </div>
                            <div class="gso-stat-chevron"><i class="fas fa-chevron-right"></i></div>
                        </div>
                    </button>
                </div>

                <div class="col-lg-3 col-sm-6 mb-3">
                    <button type="button" class="gso-stat-link gso-stat-button gso-mv-scope" data-scope="due_current_month" aria-pressed="false">
                        <div class="gso-stat-card">
                            <div class="gso-stat-icon"><i class="fa-solid fa-file-circle-exclamation"></i></div>
                            <div>
                                <div class="gso-stat-title">For Registration This Month</div>
                                <div class="gso-stat-value" data-mv-metric="for_registration">...</div>
                                <div class="gso-stat-note">Plate renewal schedule</div>
                            </div>
                            <div class="gso-stat-chevron"><i class="fas fa-chevron-right"></i></div>
                        </div>
                    </button>
                </div>

                <div class="col-lg-3 col-sm-6 mb-3">
                    <a href="motor-vehicle-statistics.php" class="gso-stat-link" aria-label="Open motor vehicle statistics">
                        <div class="gso-stat-card">
                            <div class="gso-stat-icon"><i class="fa-solid fa-chart-simple"></i></div>
                            <div>
                                <div class="gso-stat-title">Statistics</div>
                                <div class="gso-stat-value"><i class="fas fa-chart-bar"></i></div>
                                <div class="gso-stat-note">Vehicle counts</div>
                            </div>
                            <div class="gso-stat-chevron"><i class="fas fa-chevron-right"></i></div>
                        </div>
                    </a>
                </div>
            </div>

            <div class="card gso-card mb-4">
                <div class="card-header border-0">
                    <div class="d-flex justify-content-between align-items-center flex-wrap">
                        <div>
                            <h3 class="card-title mb-0">
                                <i class="fa-solid fa-truck-front mr-1"></i> <span id="motorVehicleTableTitle">All Motor Vehicles</span>
                            </h3>
                            <div class="small text-muted mt-1" id="motorVehicleTableSubtitle"></div>
                        </div>
                    </div>
                </div>

                <div class="card-body">
                    <table id="motorVehicleDashboardTable" class="table table-bordered table-hover gso-table w-100">
                        <thead>
                            <tr>
                                <th>Brand/Model</th>
                                <th>Year Acquired</th>
                                <th>Chassis Number</th>
                                <th>Engine Number</th>
                                <th>Plate Number</th>
                                <th>Department</th>
                                <th>End User</th>
                                <th>Renewal Schedule</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>
    </section>
</div>

<div class="modal fade" id="motorVehicleModal" data-backdrop="static" tabindex="-1" role="dialog" aria-labelledby="motorVehicleModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable" role="document">
        <div class="modal-content gso-modal">
            <div class="modal-header border-0 p-0">
                <div class="gso-hero w-100 gso-hero-modalhead">
                    <div class="card-body py-3">
                        <div class="d-flex align-items-start justify-content-between flex-wrap">
                            <div class="mb-2 mb-md-0">
                                <div class="gso-title gso-title-sm" id="motorVehicleModalLabel">Motor Vehicle Information</div>
                            </div>
                            <button type="button" class="close text-white" style="opacity:.8;" data-dismiss="modal" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <form id="motorVehicleForm" autocomplete="off">
                <input type="hidden" name="source_table" id="mv_source_table">
                <input type="hidden" name="source_id" id="mv_source_id">
                <input type="hidden" name="year_model" id="mv_year_model">

                <div class="modal-body">
                    <div class="row add-item-section">
                        <div class="col-lg-6 d-flex mb-3 mb-lg-0">
                            <div class="card gso-card w-100 h-100 mb-0">
                                <div class="card-header border-0">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <h3 class="card-title mb-0"><i class="fas fa-bookmark"></i>&nbsp; Asset Details</h3>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <div class="form-row">
                                        <div class="form-group col-md-6">
                                            <label>Brand/Model</label>
                                            <input type="text" class="form-control text-uppercase" name="brand_model" id="mv_brand_model" required>
                                        </div>
                                        <div class="form-group col-md-6">
                                            <label>Property Number</label>
                                            <input type="text" class="form-control text-uppercase" name="property_number" id="mv_property_number" readonly required>
                                        </div>
                                    </div>
                                    <div class="form-row">
                                        <div class="form-group col-md-12 mb-0">
                                            <label>Amount</label>
                                            <input type="text" inputmode="decimal" class="form-control text-right" name="amount" id="mv_amount" placeholder="0.00">
                                        </div>
                                    </div>
                                    <div class="form-row mt-3">
                                        <div class="form-group col-md-12 mb-0">
                                            <label>Description</label>
                                            <textarea class="form-control text-uppercase" name="description" id="mv_description" rows="3" required></textarea>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-6 d-flex">
                            <div class="card gso-card w-100 h-100 mb-0">
                                <div class="card-header border-0">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <h3 class="card-title mb-0"><i class="fa-solid fa-truck-front"></i>&nbsp; Vehicle Details</h3>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <div class="form-row">
                                        <div class="form-group col-md-6">
                                            <label>Chassis Number</label>
                                            <input type="text" class="form-control text-uppercase" name="chassis_no" id="mv_chassis_no" required>
                                        </div>
                                        <div class="form-group col-md-6">
                                            <label>Engine Number</label>
                                            <input type="text" class="form-control text-uppercase" name="engine_no" id="mv_engine_no" required>
                                        </div>
                                    </div>
                                    <div class="form-row">
                                        <div class="form-group col-md-6">
                                            <label>Plate Number</label>
                                            <input type="text" class="form-control text-uppercase" name="plate_no" id="mv_plate_no" required>
                                        </div>
                                        <div class="form-group col-md-6">
                                            <label>Color</label>
                                            <input type="text" class="form-control text-uppercase" name="color" id="mv_color" required>
                                        </div>
                                    </div>
                                    <div class="form-row">
                                        <div class="form-group col-md-6">
                                            <label>MV File</label>
                                            <input type="text" class="form-control text-uppercase" name="mv_file" id="mv_file" required>
                                        </div>
                                        <div class="form-group col-md-6">
                                            <label>Vehicle Usage</label>
                                            <input type="text" class="form-control text-uppercase" name="vehicle_usage" id="mv_vehicle_usage" required>
                                        </div>
                                    </div>
                                    <div class="form-row mb-0">
                                        <div class="form-group col-md-6 mb-md-0">
                                            <label>Capacity</label>
                                            <input type="text" class="form-control text-uppercase" name="capacity" id="mv_capacity" required>
                                        </div>
                                        <div class="form-group col-md-6 mb-0">
                                            <label>Date Acquired</label>
                                            <div class="input-group date" id="mv_date_acquired_picker" data-target-input="nearest">
                                                <input type="text" class="form-control" name="date_acquired" id="mv_date_acquired" placeholder="YYYY-MM-DD" required>
                                                <div class="input-group-append">
                                                    <span class="input-group-text"><i class="fas fa-calendar-alt"></i></span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row add-item-section mt-3">
                        <div class="col-12">
                            <div class="card gso-card mb-0">
                                <div class="card-header border-0">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <h3 class="card-title mb-0"><i class="fas fa-file-alt"></i>&nbsp; Registration and Purchase</h3>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <div class="form-row">
                                        <div class="form-group col-md-4">
                                            <label>Certificate Registration Number (C.R)</label>
                                            <input type="text" class="form-control text-uppercase" name="cr_number" id="mv_cr_number">
                                        </div>
                                        <div class="form-group col-md-4">
                                            <label>Official Receipt Number (O.R)</label>
                                            <input type="text" class="form-control text-uppercase" name="or_number" id="mv_or_number">
                                        </div>
                                        <div class="form-group col-md-4">
                                            <label>Coverage</label>
                                            <select class="form-control" name="coverage" id="mv_coverage" required>
                                                <option value="None">None</option>
                                                <option value="TPL">TPL</option>
                                                <option value="Comprehensive">Comprehensive</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="form-row">
                                        <div class="form-group col-md-12">
                                            <label>Supplier</label>
                                            <input type="text" class="form-control text-uppercase" name="supplier" id="mv_supplier">
                                        </div>
                                    </div>
                                    <div class="form-row mb-0">
                                        <div class="form-group col-md-3 mb-md-0">
                                            <label>P.O</label>
                                            <input type="text" class="form-control text-uppercase" name="po" id="mv_po">
                                        </div>
                                        <div class="form-group col-md-3 mb-md-0">
                                            <label>O.B.R</label>
                                            <input type="text" class="form-control text-uppercase" name="obr" id="mv_obr">
                                        </div>
                                        <div class="form-group col-md-3 mb-md-0">
                                            <label>P.R</label>
                                            <input type="text" class="form-control text-uppercase" name="pr" id="mv_pr">
                                        </div>
                                        <div class="form-group col-md-3 mb-0">
                                            <label>J.E.V No.</label>
                                            <input type="text" class="form-control text-uppercase" name="jev" id="mv_jev">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer border-0 pt-0">
                    <button type="submit" class="btn btn-success" id="motorVehicleSaveBtn">
                        <i class="fas fa-save"></i>&nbsp;<span class="btn-text">Update</span>
                    </button>
                    <button type="button" class="btn btn-outline-success" data-dismiss="modal">Close</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
include('../include/footer.php');
include('../include/script.php');
?>
