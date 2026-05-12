<?php 
include_once('../config/session.php');
include('../config/check_session.php');

if(!isset($_SESSION['alogin'])){
  header('Location:../index.php');
  exit();
}else {
  $controlId = isset($_GET['control_id']) ? mysqli_real_escape_string($conn, $_GET['control_id']) : '';
  if ($controlId !== '') {
    mysqli_query($conn, "UPDATE clearance_history SET is_read = 1 WHERE control_number = '$controlId'");
  }

  $redirectUrl = '../services/clearance.php';
  if ($controlId !== '') {
    $redirectUrl .= '?control_id=' . urlencode($controlId);
  }
  header('Location:' . $redirectUrl);
  exit();
?>
  <?php include('../include/header.php')?><!--Header-->

  <?php include('../include/navbar.php')?><!-- Navbar -->

  <?php include('../include/sidebar.php')?><!--Sidebar-->

  <?php
    // Option lists for editable fields
    $deptOptions = [];
    $deptQ = mysqli_query($conn, "SELECT department_code, department_name FROM department ORDER BY department_name ASC");
    if ($deptQ) {
      while ($d = mysqli_fetch_assoc($deptQ)) { $deptOptions[] = $d; }
    }

    $ctypeOptions = [];
    $ctypeQ = mysqli_query($conn, "SELECT clearance_code, clearance_name FROM clearance_type ORDER BY clearance_name ASC");
    if ($ctypeQ) {
      while ($c = mysqli_fetch_assoc($ctypeQ)) { $ctypeOptions[] = $c; }
    }
  ?>

  <div id="destroy"></div>

  <div class="content-wrapper"><!-- Content Wrapper. Contains page content -->
   
    <section class="content-header"> <!-- Content Header (Page header) -->
      <div class="container-fluid">
        <div class="row mb-2">
          <div class="col-sm-6">
            <h1>Property Clearance</h1>
          </div>
          <div class="col-sm-6">
            <ol class="breadcrumb float-sm-right">
              <li class="breadcrumb-item"><a href="#">Home</a></li>
              <li class="breadcrumb-item">Services</li>
              <li class="breadcrumb-item">Manage Clearance</li>
              <li class="breadcrumb-item active">Clearance Details</li>
            </ol>
          </div>
        </div>
      </div><!-- /.container-fluid -->
    </section>

    <section class="content"> <!-- Main content -->
      <div class="card"> <!-- Default box -->
        <div class="card-header">
          <h3 class="card-title"><i class="fas fa-file-edit"></i>&nbsp;Clearance details</h3>
          <?php
            // Quick status badge on header
            $cid_header = isset($_GET['control_id']) ? mysqli_real_escape_string($conn, $_GET['control_id']) : '';
            $hdrStatus = 0;
            $hdrHasAcct = false;
            if ($cid_header !== '') {
              $hdrQ = mysqli_query($conn, "SELECT
                                        ch.status,
                                        (acct.emp_id IS NOT NULL) AS has_accountability
                                      FROM clearance_history AS ch
                                      LEFT JOIN (
                                        SELECT emp_id FROM general_fund_property_history WHERE status = 1
                                        UNION
                                        SELECT emp_id FROM sef_property_history WHERE status = 1
                                      ) AS acct ON acct.emp_id = ch.emp_id
                                      WHERE ch.control_number='$cid_header'
                                      LIMIT 1");
              if ($hdrQ && mysqli_num_rows($hdrQ) === 1) {
                $hdrRow = mysqli_fetch_assoc($hdrQ);
                $hdrStatus = isset($hdrRow['status']) ? (int)$hdrRow['status'] : 0;
                $hdrHasAcct = !empty($hdrRow['has_accountability']);
              }
            }
            $badgeClass = 'badge-warning';
            $badgeText  = 'Processing';
            if ($hdrStatus === 1) { $badgeClass = 'badge-success'; $badgeText = 'Released'; }
              elseif ($hdrStatus === 2) { $badgeClass = 'badge-danger'; $badgeText = 'Canceled'; }
              elseif ($hdrStatus === 0 && !$hdrHasAcct) { $badgeClass = 'badge-primary'; $badgeText = 'Ready'; }
          ?>
          <span class="badge <?= $badgeClass ?>" style="float:right;"><?= $badgeText ?></span>
        </div>  
      <div class="card-body">
      <div class="row">
        <div class="col-md-7">
        <div class="text-muted mb-3">General Information</div>
          <?php
            $control_id = $controlId;
            $sql="SELECT p.control_number,p.or_number,p.dept_id,p.ctype_id AS ctype_code,p.created_at,p.address,p.city,e.emp_id,e.emp_name,e.position,d.department_code,d.department_name,c.clearance_name
            FROM property_clearance AS p JOIN clearance_type AS c ON p.ctype_id = c.clearance_code JOIN employee AS e ON p.emp_id = e.emp_id JOIN department AS d ON p.dept_id = d.department_code WHERE control_number = '$control_id' ";
            $query = mysqli_query($conn,$sql); 
            if(mysqli_num_rows($query)) {
              foreach($query as $row){?>
                <form method="POST" id="pc_details_form">
                      <div class="form-row">
                            <div class="form-group col-md-12">
                              <label>Name</label>
                              <input type="hidden" name="emp_id" id="emp_id" value="<?=$row['emp_id']?>">
                              <input type="text" class="form-control text-uppercase" name="emp_name" id="emp_name" value="<?=$row['emp_name']?>" required>
                            </div>
                      </div>
                      <div class="form-row">
                            <div class="form-group col-md-6">
                              <label>Position</label>
                              <input type="text" value="<?=$row['position']?>" class="form-control text-uppercase" name="position" id="position" required>
                            </div>
                            <div class="form-group col-md-6">
                              <label>Department</label>
                              <select name="dept_id" id="dept_id" class="form-control" required>
                                <option value="">-SELECT-</option>
                                <?php foreach ($deptOptions as $d):
                                  $code = htmlspecialchars($d['department_code']);
                                  $name = htmlspecialchars($d['department_name']);
                                  $sel = ($row['dept_id'] === $d['department_code']) ? 'selected' : '';
                                  echo "<option value=\"$code\" $sel>$name</option>";
                                endforeach; ?>
                              </select>
                            </div>
                      </div>
                      <div class="form-row">
                            <div class="form-group col-md-6">
                              <label>Street address</label>
                              <input type="text" value="<?=$row['address']?>" name="address" id="address" class="form-control text-uppercase" required>
                            </div>
                            <div class="form-group col-md-6">
                              <label>City</label>
                              <input type="text" value="<?=$row['city']?>" name="city" id="city" class="form-control text-uppercase" required>
                            </div>
                      </div>    
                      <div class="text-muted mb-3 mt-2">Clearance details</div>
                      <div class="form-row">
                            <input type="hidden" value="<?=$row['control_number']?>"  name="cid" id="cid">
                            <div class="form-group col-md-3">
                              <label>Control number</label>
                              <input type="text" class="form-control" value="<?=$row['control_number']?>" readonly>
                            </div>
                            <div class="form-group col-md-3">
                              <label>Date applied</label>
                              <input type="text" value="<?=date('F j, Y, g:i a',strtotime($row['created_at']))?>" class="form-control" readonly>
                            </div>
                            <div class="form-group col-md-3">
                              <label>Applied for</label>
                              <select name="ctype_id" id="ctype_id" class="form-control" required>
                                <option value="">-SELECT-</option>
                                <?php foreach ($ctypeOptions as $c):
                                  $ccode = htmlspecialchars($c['clearance_code']);
                                  $cname = htmlspecialchars($c['clearance_name']);
                                  $sel = ($row['ctype_code'] === $c['clearance_code']) ? 'selected' : '';
                                  echo "<option value=\"$ccode\" $sel>$cname</option>";
                                endforeach; ?>
                              </select>
                            </div>
                            <div class="form-group col-md-3">
                              <label>O.R number</label>
                              <input type="text" class="form-control" name="or_number" id="or_number" value="<?=$row['or_number']?>">
                            </div>
                      </div>
                      <div class="form-row">
                        <div class="form-group col-md-12">
                          <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i>&nbsp; Save Changes</button>
                        </div>
                      </div>
                </form>
         <?php }}?>
        </div>
      </div>
      <!--  -->
      <div class="row mt-3">
        <div class="col-md-7">
                <div class="table-responsive">
                    <table class="table">
                      <?php
                        $control_id = $controlId;
                        $sql = "SELECT 
                        SUM(g.status) AS stat,
                        SUM(CASE WHEN g.status = 1 THEN 1 ELSE 0 END) AS has_status1,
                        p.control_number,
                        p.emp_id,
                        c.status,
                        t.clearance_name
                        FROM property_clearance AS p
                        JOIN clearance_history AS c ON p.control_number = c.control_number
                        LEFT JOIN general_fund_property_history AS g ON p.emp_id = g.emp_id
                        JOIN clearance_type AS t ON p.ctype_id = t.clearance_code
                        WHERE p.control_number = '$control_id'
                        GROUP BY p.control_number, p.emp_id, c.status, t.clearance_name
                        LIMIT 1";
                        $queryinfo = mysqli_query($conn,$sql);
                        $row = mysqli_fetch_array($queryinfo);
                        // Normalize fetched values
                        $activeStat = isset($row['stat']) ? (int)$row['stat'] : 0; // active accountabilities count sum (>=1 means has active)
                        $hasStatus1 = isset($row['has_status1']) ? (int)$row['has_status1'] : 0; // count of status=1 records
                        $releaseStatus = isset($row['status']) ? (int)$row['status'] : 0; // 0=processing,1=released,2=canceled
                        $cname = isset($row['clearance_name']) ? strtoupper(trim($row['clearance_name'])) : '';
                        $isResignOrRetire = in_array($cname, ['RESIGNATION', 'RETIREMENT'], true);

                        // Priority: show based on release status first
                        if ($releaseStatus === 1) {
                          echo '<tr><td><h6>Clearance has been printed and released. <i class="fa-solid fa-check text-success"></i></h6></td></tr>';
                          echo '<tr class="text-center"><td></td></tr>';
                        } elseif ($releaseStatus === 2) {
                          echo '<tr class="text-center"><td><h6 class="text-danger">This clearance has been cancelled. <i class="fa-solid fa-ban text-danger"></i></h6></td></tr>';
                          echo '<tr class="text-center"><td></td></tr>';
                        } else {
                          // Processing
                          $canApprove = false;
                          if ($isResignOrRetire) {
                            // For RESIGNATION/RETIREMENT: block only when any status=1 exists
                            $canApprove = ($hasStatus1 === 0);
                          } else {
                            // Existing behavior for other clearance types
                            $canApprove = ($activeStat === 0);
                          }

                          if ($canApprove) {
                            echo '<tr class="text-left"><td><h6>The above-name employee has no property accountabilities. <i class="fa-solid fa-check text-success"></i></h6></td></tr>';
                            echo '<tr class="text-left"><td>';
                            echo '<button type="button" class="btn btn-success mt-2 mr-3 approvePcBtn" data-value="'.htmlspecialchars($row['control_number']).'">Approved <i class="fa-solid fa-thumbs-up"></i></button>';
                            echo '<button class="btn btn-danger mt-2 cancelBtnClearance" data-value="'.htmlspecialchars($row['control_number']).'" style="margin-right: 5px;">Cancel clearance <i class="fa-solid fa-ban"></i></button>';
                            echo '</td></tr>';
                          } else {
                            echo '<tr class="text-center"><td><h6 class="text-danger">The above-name employee has not yet cleared to all property accountabilities. <i class="fa-solid fa-circle-xmark text-danger"></i></h6></td></tr>';
                            echo '<tr><td></td></tr>';
                          }
                        }
?>
          </table>
            </div>
        </div>
      </div>
      <!-- /.row -->
      </div>
        <!-- /.card-body -->
     </div>
      <!-- /.card -->

    </section><!-- /.content -->
    
  </div><!-- /.content-wrapper -->
  
  <?php include('../include/footer.php') ?><!--footer-->

</div><!-- ./wrapper -->

<?php include('../include/script.php')?><!--script-->
<?php }?>
