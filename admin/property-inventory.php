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
            <h1>Property Inventory</h1>
          </div>
          <div class="col-sm-6">
            <ol class="breadcrumb float-sm-right">
              <li class="breadcrumb-item"><a href="dashboard.php">Home</a></li>
              <li class="breadcrumb-item active">Property Inventory</li>
            </ol>
          </div>
        </div>
      </div><!-- /.container-fluid -->
    </section>
    <section class="content"> <!-- Main content -->
      <div class="card"> <!-- Default box -->
        <div class="card-body">
        <table id="dataTable" class="table table-bordered table-hover">
                  <thead>
                  <tr class="bg-dark text-light bg-gradient bg-opacity-150">
                    <th class="col-sm-1">FUND</th>
                    <th class="col-sm-2">BRAND/MODEL</th>    
                    <th class="col-sm-2">SNID NO.1</th>
                    <th class="col-sm-2">SNID NO.2</th>
                    <th class="col-sm-2">PROPERTY NUMBER</th>
                    <th class="col-sm-2">DEPARTMENT</th>
                    <th class="col-sm-2">END USER</th>
                  </tr>
                  </thead>
                  <tbody>
                    <?php
                    $item = isset($_GET['item']) ? (string)$_GET['item'] : '';
                    $sql = "SELECT
                              fund,
                              model,
                              serial_number,
                              serial_number_2,
                              property_number,
                              department_name,
                              end_user
                            FROM (
                            SELECT
                              'General Fund' AS fund,
                              p.model,
                              p.serial_number,
                              p.serial_number_2,
                              p.par_number AS property_number,
                              COALESCE(ed.department_name, hd.department_name, hdp.department_name, '') AS department_name,
                              COALESCE(e.emp_name, '') AS end_user
                            FROM general_fund_property_history AS g
                            STRAIGHT_JOIN par_gen_fund AS p ON g.par_number = p.par_number
                            STRAIGHT_JOIN employee AS e ON e.emp_id = g.emp_id
                            LEFT JOIN department AS ed ON ed.department_code = e.department_code
                            LEFT JOIN department AS hd ON hd.department_code = g.dept_id
                            LEFT JOIN department AS hdp ON hdp.dept_id = g.dept_id
                            WHERE g.status = 1 AND p.item = ?
                            
                            UNION ALL
                            
                            SELECT
                              'SEF' AS fund,
                              s.model,
                              s.serial_number,
                              s.serial_number_2,
                              s.property_number,
                              COALESCE(ed.department_name, sd.department_name, sdp.department_name, '') AS department_name,
                              COALESCE(e.emp_name, '') AS end_user
                            FROM sef_property_history AS sh
                            STRAIGHT_JOIN property_sef AS s ON sh.property_number = s.property_number
                            STRAIGHT_JOIN employee AS e ON e.emp_id = sh.emp_id
                            LEFT JOIN department AS ed ON ed.department_code = e.department_code
                            LEFT JOIN department AS sd ON sd.department_code = sh.sch_id
                            LEFT JOIN department AS sdp ON sdp.dept_id = sh.sch_id
                            WHERE sh.status = 1 AND s.item = ?
                            ) AS combined_inventory
                            ORDER BY department_name ASC, end_user ASC, property_number ASC";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param('ss', $item, $item);
                    $stmt->execute();
                    $query = $stmt->get_result();
                    foreach($query as $result){?>
                          <tr>
                            <td><?=htmlspecialchars((string)($result['fund'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');?></td>
                            <td><?=htmlspecialchars((string)($result['model'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');?></td>
                            <td><?= !empty($result['serial_number']) ? htmlspecialchars($result['serial_number']) : "<span class='text-dark'>NULL</span>"; ?></td>
                            <td><?= !empty($result['serial_number_2']) ? htmlspecialchars($result['serial_number_2']) : "<span class='text-dark'>NULL</span>"; ?></td>
                            <td><?=htmlspecialchars((string)($result['property_number'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');?></td>
                            <td><?=htmlspecialchars((string)($result['department_name'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');?></td>
                            <td><?=htmlspecialchars((string)($result['end_user'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');?></td>
                          </tr>
                    <?php }?> 
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