<?php 
include_once('../config/session.php');
include('../config/check_session.php');

if(!isset($_SESSION['alogin'])){
  header('Location:../index.php');
  exit();
}else {
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
            <h1>Account Inventory</h1>
          </div>
          <div class="col-sm-6">
            <ol class="breadcrumb float-sm-right">
              <li class="breadcrumb-item"><a href="dashboard.php">Home</a></li>
              <li class="breadcrumb-item"><a href="#">Account Code</a></li>
              <li class="breadcrumb-item active">Account Inventory</li>
            </ol>
          </div>
        </div>
      </div><!-- /.container-fluid -->
    </section>

    <section class="content"> <!-- Main content -->
      <div class="card"> <!-- Default box -->
        <div class="card-header">
          <?php
          $aid = intval($_GET['acct'] ?? 0);
          $cat = strtoupper(trim((string)($_GET['cat'] ?? '')));
          if (!in_array($cat, ['PAR', 'ICS'], true)) { $cat = ''; }

          $sql = "SELECT * FROM account_code WHERE id = '$aid' LIMIT 1";
          $query = mysqli_query($conn, $sql);
          if($query && mysqli_num_rows($query)>0){
            while($result = mysqli_fetch_assoc($query)){
              ?>
              <h3 class="card-title"><i class="fas fa-clipboard"></i>&nbsp;
                <?=htmlspecialchars($result['account_name'] ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');?>
                &nbsp;<?=htmlspecialchars($result['account_code'] ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');?>
                <?php if($cat !== ''){ ?><span class="badge badge-info ml-2"><?=$cat?></span><?php } ?>
              </h3>
              <?php
            }
          }
          ?>
        </div>

        <div class="card-body">
          <table id="dataTable" class="table table-bordered table-hover">
            <thead>
              <tr class="bg-dark text-light bg-gradient bg-opacity-150">
                <th class="col-sm-5">DESCRIPTION</th>
                <th class="col-sm-1">JEV NO.</th>
                <th class="col-sm-1">UNIT VALUE</th>
                <th class="col-sm-1">YEAR ACQUIRED</th>
                <th class="col-sm-2">PROPERTY NUMBER</th>
                <th class="col-sm-2">END USER</th>
              </tr>
            </thead>
            <tbody>
              <?php
              $catWhere = '';
              if ($cat !== '') {
                $catSafe = mysqli_real_escape_string($conn, $cat);
                $catWhere = " AND UPPER(TRIM(s.category)) = '{$catSafe}' ";
              }

              $q = "SELECT
                      s.item,
                      s.model,
                      s.serial_number,
                      s.serial_number_2,
                      s.jev_number,
                      s.unit_value,
                      s.date_aquired,
                      s.property_number,
                      COALESCE(e.emp_name, '') AS end_user
                    FROM account_code AS a
                    INNER JOIN property_sef AS s ON a.account_code = s.account_code
                    LEFT JOIN sef_property_history AS sh
                      ON sh.property_number = s.property_number AND sh.status = 1
                    LEFT JOIN employee AS e ON e.emp_id = sh.emp_id
                    WHERE a.id = '$aid' {$catWhere}
                    ORDER BY s.date_aquired DESC";

              $res = mysqli_query($conn, $q);
              if ($res && mysqli_num_rows($res) > 0) {
                while ($row = mysqli_fetch_assoc($res)) {
                  $item = trim((string)($row['item'] ?? ''));
                  $model = trim((string)($row['model'] ?? ''));
                  $sn1 = trim((string)($row['serial_number'] ?? ''));
                  $sn2 = trim((string)($row['serial_number_2'] ?? ''));

                  $descParts = [];
                  if ($item !== '') { $descParts[] = $item; }
                  if ($model !== '') { $descParts[] = $model; }
                  $serials = trim($sn1 . ($sn2 !== '' ? (' / ' . $sn2) : ''));
                  if ($serials !== '') { $descParts[] = $serials; }
                  $desc = implode(' - ', $descParts);

                  $jev = (string)($row['jev_number'] ?? '');
                  $unitValue = (float)($row['unit_value'] ?? 0);
                  $unitValueFmt = '₱ ' . number_format($unitValue, 2);
                  $yearAcquired = (string)($row['date_aquired'] ?? '');
                  $propNo = (string)($row['property_number'] ?? '');
                  $endUser = (string)($row['end_user'] ?? '');
                  ?>
                  <tr>
                    <td><?=htmlspecialchars($desc, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');?></td>
                    <td><?=htmlspecialchars($jev, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');?></td>
                    <td class="text-right"><?=htmlspecialchars($unitValueFmt, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');?></td>
                    <td><?=htmlspecialchars($yearAcquired, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');?></td>
                    <td><?=htmlspecialchars($propNo, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');?></td>
                    <td><?=htmlspecialchars($endUser, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');?></td>
                  </tr>
                  <?php
                }
              }
              ?>
            </tbody>
          </table>
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
