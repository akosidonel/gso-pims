<?php 
include_once('../config/session.php');
include('../config/check_session.php');

if(!isset($_SESSION['alogin'])){
  header('Location:../index.php');
  exit();
}

$role = isset($_SESSION['role']) ? strtoupper(trim((string)$_SESSION['role'])) : '';
if(!in_array($role, ['CLEARANCE-ADMIN', 'SYSTEM-ADMIN'], true)){
  header('Location:../404.php');
  exit();
}

$yearNow = (int)date('Y');
$minYear = $yearNow - 5;
$maxYear = $yearNow;
?>

<?php include('../include/header.php')?><!--Header-->
<?php include('../include/navbar.php')?><!-- Navbar -->
<?php include('../include/sidebar.php')?><!--Sidebar-->

<div class="content-wrapper">
  <section class="content-header">
    <div class="container-fluid">
      <div class="row mb-2">
        <div class="col-sm-6">
          <h1>Property Clearance Statistics</h1>
        </div>
        <div class="col-sm-6">
          <ol class="breadcrumb float-sm-right">
            <li class="breadcrumb-item"><a href="#">Home</a></li>
            <li class="breadcrumb-item active">Property Clearance Statistics</li>
          </ol>
        </div>
      </div>
    </div>
  </section>

  <section class="content" id="clearanceStatsPage" data-default-year="<?php echo (int)$yearNow; ?>">
    <div class="row">
      <div class="col-lg-3 col-6">
        <div class="small-box bg-info">
          <div class="inner">
            <h3 id="csKpiTotal">0</h3>
            <p>Total Released</p>
          </div>
          <div class="icon"><i class="fas fa-check-circle"></i></div>
        </div>
      </div>
      <div class="col-lg-3 col-6">
        <div class="small-box bg-success">
          <div class="inner">
            <h3 id="csKpiThisMonth">0</h3>
            <p>Released This Month</p>
          </div>
          <div class="icon"><i class="fas fa-calendar-alt"></i></div>
        </div>
      </div>
      <div class="col-lg-3 col-6">
        <div class="small-box bg-warning">
          <div class="inner">
            <h3 id="csKpiLastMonth">0</h3>
            <p>Released Last Month</p>
          </div>
          <div class="icon"><i class="fas fa-calendar-minus"></i></div>
        </div>
      </div>

      <div class="col-lg-3 col-6">
        <div class="small-box bg-danger">
          <div class="inner">
            <h3 id="csKpiPending">0</h3>
            <p>Pending / For Release</p>
          </div>
          <div class="icon"><i class="fas fa-hourglass-half"></i></div>
        </div>
      </div>
    </div>

    <div class="card">
      <div class="card-header">
        <div class="d-flex align-items-center justify-content-between flex-wrap">
          <h3 class="card-title"><i class="fas fa-chart-bar"></i>&nbsp; Clearance Release Analytics</h3>

          <div class="form-row align-items-end">
            <div class="col-auto">
              <label for="csYear" class="mb-0">Year</label>
              <select id="csYear" class="form-control form-control-sm">
                <?php for($y=$maxYear; $y>=$minYear; $y--): ?>
                  <option value="<?php echo (int)$y; ?>" <?php echo $y===$yearNow?'selected':''; ?>><?php echo (int)$y; ?></option>
                <?php endfor; ?>
              </select>
            </div>
            <div class="col-auto">
              <label for="csFrom" class="mb-0">From</label>
              <input type="text" class="form-control form-control-sm" id="csFrom" placeholder="YYYY-MM-DD" autocomplete="off">
            </div>
            <div class="col-auto">
              <label for="csTo" class="mb-0">To</label>
              <input type="text" class="form-control form-control-sm" id="csTo" placeholder="YYYY-MM-DD" autocomplete="off">
            </div>
            <div class="col-auto">
              <button type="button" class="btn btn-sm btn-secondary" id="csClearRange">Clear Range</button>
            </div>
            <div class="col-auto">
              <div class="btn-group btn-group-toggle" data-toggle="buttons" id="csViewToggle">
                <label class="btn btn-sm btn-outline-primary active">
                  <input type="radio" name="csView" value="stacked" autocomplete="off" checked> Stacked
                </label>
                <label class="btn btn-sm btn-outline-primary">
                  <input type="radio" name="csView" value="total" autocomplete="off"> Monthly Total
                </label>
              </div>
            </div>
            <div class="col-auto">
              <button type="button" class="btn btn-sm btn-primary" id="csExportSummary"><i class="fas fa-download"></i> Export CSV</button>
            </div>
          </div>
        </div>
      </div>

      <div class="card-body">
        <div class="row">
          <div class="col-lg-8">
            <div class="position-relative" style="min-height: 360px;">
              <canvas id="csMonthlyByTypeChart" height="140"></canvas>
            </div>
            <div class="text-muted mt-2" id="csHint" style="font-size: 13px;">
              Tip: Click a bar segment to drill down.
            </div>
          </div>
          <div class="col-lg-4">
            <div class="position-relative" style="min-height: 360px;">
              <canvas id="csReleasedPerTypeChart" height="200"></canvas>
            </div>
            <div class="mt-2" id="csTypeTotals"></div>
            <div class="mt-2 text-muted" id="csSummary" style="font-size: 13px;"></div>
          </div>
        </div>
      </div>
    </div>

    <!-- Drilldown modal -->
    <div class="modal fade" id="csDetailsModal" tabindex="-1" role="dialog" aria-hidden="true">
      <div class="modal-dialog modal-xl" role="document">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="csDetailsTitle">Released Clearances</h5>
            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
              <span aria-hidden="true">&times;</span>
            </button>
          </div>
          <div class="modal-body">
            <table id="csDetailsTable" class="table table-bordered table-striped" style="width:100%">
              <thead>
                <tr>
                  <th>Control No.</th>
                  <th>Employee</th>
                  <th>Clearance Category</th>
                  <th>Release Date</th>
                </tr>
              </thead>
            </table>
          </div>
        </div>
      </div>
    </div>
  </section>
</div>

<?php include('../include/footer.php')?><!-- Footer -->
<?php include('../include/script.php')?><!-- Script -->

<script>
  // Small page hook; main logic lives in assets/dist/js/script.js
  if (window.GSO && window.GSO.ClearanceStats && typeof window.GSO.ClearanceStats.init === 'function') {
    window.GSO.ClearanceStats.init();
  }
</script>
