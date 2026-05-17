<?php
include_once('../config/session.php');
include('../config/check_session.php');
include_once('../config/auth_helpers.php');

check_admin_role_dynamic_redirect(['SYSTEM-ADMIN', 'MV-ADMIN']);

if (!isset($_SESSION['alogin'])) {
    header('Location:../index.php');
    exit();
}
?>

<?php include('../include/header.php'); ?><!--Header-->
<?php include('../include/navbar.php'); ?><!-- Navbar -->
<?php include('../include/sidebar.php'); ?><!--Sidebar-->

<div id="destroy"></div>

<div class="content-wrapper gso-dashboard" id="motorVehicleStatistics">
    <section class="content">
        <div class="container-fluid">
            <div class="card gso-hero mb-4">
                <div class="card-body">
                    <div class="d-flex align-items-start align-items-md-center justify-content-between flex-wrap">
                        <div class="mb-3 mb-md-0">
                            <div class="gso-title">Motor Vehicle Statistics</div>
                            <div class="gso-meta">Registration, insurance, condition, and registry status overview</div>
                        </div>

                        <div class="text-md-right">
                            <a href="motor-vehicle-dashboard.php" class="btn btn-light btn-sm">
                                <i class="fas fa-arrow-left"></i>&nbsp;Dashboard
                            </a>
                            <div class="gso-meta" id="mvStatsAsOf">...</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-lg-3 col-6">
                    <div class="small-box bg-info">
                        <div class="inner">
                            <h3 data-mv-stat="total_vehicles">0</h3>
                            <p>Total Vehicles</p>
                        </div>
                        <div class="icon"><i class="fas fa-car-side"></i></div>
                    </div>
                </div>

                <div class="col-lg-3 col-6">
                    <div class="small-box bg-success">
                        <div class="inner">
                            <h3 data-mv-stat="registered_vehicles">0</h3>
                            <p>Registered Vehicles</p>
                        </div>
                        <div class="icon"><i class="fas fa-clipboard-check"></i></div>
                    </div>
                </div>

                <div class="col-lg-3 col-6">
                    <div class="small-box bg-warning">
                        <div class="inner">
                            <h3 data-mv-stat="for_registration">0</h3>
                            <p>For Registration</p>
                        </div>
                        <div class="icon"><i class="fas fa-exclamation-circle"></i></div>
                    </div>
                </div>

                <div class="col-lg-3 col-6">
                    <div class="small-box bg-primary">
                        <div class="inner">
                            <h3 data-mv-stat="insured_vehicles">0</h3>
                            <p>Insured Vehicles</p>
                        </div>
                        <div class="icon"><i class="fas fa-shield-alt"></i></div>
                    </div>
                </div>

                <div class="col-lg-3 col-6">
                    <div class="small-box bg-success">
                        <div class="inner">
                            <h3 data-mv-stat="serviceable_vehicles">0</h3>
                            <p>Serviceable</p>
                        </div>
                        <div class="icon"><i class="fas fa-tools"></i></div>
                    </div>
                </div>

                <div class="col-lg-3 col-6">
                    <div class="small-box bg-danger">
                        <div class="inner">
                            <h3 data-mv-stat="unserviceable_vehicles">0</h3>
                            <p>Unserviceable</p>
                        </div>
                        <div class="icon"><i class="fas fa-exclamation-triangle"></i></div>
                    </div>
                </div>

                <div class="col-lg-3 col-6">
                    <div class="small-box bg-secondary">
                        <div class="inner">
                            <h3 data-mv-stat="new_motor_vehicles">0</h3>
                            <p>New Motor Vehicles</p>
                        </div>
                        <div class="icon"><i class="fas fa-plus-circle"></i></div>
                    </div>
                </div>

                <div class="col-lg-3 col-6">
                    <div class="small-box bg-dark">
                        <div class="inner">
                            <h3 data-mv-stat="needs_details">0</h3>
                            <p>Needs Details</p>
                        </div>
                        <div class="icon"><i class="fas fa-info-circle"></i></div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <div class="d-flex align-items-center justify-content-between flex-wrap">
                        <h3 class="card-title"><i class="fas fa-chart-bar"></i>&nbsp; Motor Vehicle Analytics</h3>
                    </div>
                </div>

                <div class="card-body">
                    <div class="row">
                        <div class="col-lg-8">
                            <div class="position-relative" style="min-height: 360px;">
                                <canvas id="mvRegistrationChart" height="140"></canvas>
                            </div>
                        </div>
                        <div class="col-lg-4">
                            <div class="position-relative" style="min-height: 260px;">
                                <canvas id="mvConditionChart" height="200"></canvas>
                            </div>
                            <div class="mt-2" id="mvStatTotals"></div>
                            <div class="mt-2 text-muted" id="mvStatsSummary" style="font-size: 13px;"></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-layer-group"></i>&nbsp; Vehicle Summary by Fund</h3>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Fund</th>
                                    <th class="text-center">Serviceable</th>
                                    <th class="text-center">Unserviceable</th>
                                    <th class="text-center">Total</th>
                                </tr>
                            </thead>
                            <tbody id="mvFundStatsBody">
                                <tr>
                                    <td colspan="4" class="text-center text-muted">Loading...</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>

<?php include('../include/footer.php'); ?><!-- Footer -->
<?php include('../include/script.php'); ?><!-- Script -->
