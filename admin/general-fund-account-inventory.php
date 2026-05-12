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
          $aid = intval($_GET['acct']);
          $cat = strtoupper(trim((string)($_GET['cat'] ?? '')));
          if (!in_array($cat, ['PAR', 'ICS'], true)) { $cat = ''; }
          $sql = "SELECT * FROM account_code  
          WHERE id = '$aid' LIMIT 1 ";
          $query = mysqli_query($conn, $sql);
          if(mysqli_num_rows($query)>0){
            foreach($query as $result){?>
                <h3 class="card-title"><i class="fas fa-clipboard"></i>&nbsp; <?=$result['account_name']?>&nbsp;<?=$result['account_code']?>
                  <?php if($cat !== ''){ ?><span class="badge badge-info ml-2"><?=$cat?></span><?php } ?>
                </h3>
            <?php }}?>  
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
                 $aid = intval($_GET['acct']);
                   $catWhere = '';
                   if ($cat !== '') {
                     $catSafe = mysqli_real_escape_string($conn, $cat);
                     $catWhere = " AND UPPER(TRIM(par_gen_fund.category)) = '{$catSafe}' ";
                   }
                  $query = "SELECT
                      par_gen_fund.item,
                      par_gen_fund.model,
                      par_gen_fund.serial_number,
                      par_gen_fund.serial_number_2,
                      par_gen_fund.jev_number,
                      par_gen_fund.unit_value,
                      par_gen_fund.date_aquired,
                      par_gen_fund.par_number,
                      COALESCE(e.emp_name, '') AS end_user
                    FROM account_code
                    INNER JOIN par_gen_fund ON account_code.account_code = par_gen_fund.account_code
                    LEFT JOIN general_fund_property_history AS g
                      ON g.par_number = par_gen_fund.par_number AND g.status = 1
                    LEFT JOIN employee AS e ON e.emp_id = g.emp_id
                    WHERE account_code.id = '$aid' {$catWhere}
                    ORDER BY par_gen_fund.date_aquired DESC";

                  $results = mysqli_query($conn, $query);
                  if ($results && mysqli_num_rows($results) > 0) {
                    while ($row = mysqli_fetch_assoc($results)) {
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
                      $propNo = (string)($row['par_number'] ?? '');
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
                    <?php }
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

</body>

</html>

<?php }?>