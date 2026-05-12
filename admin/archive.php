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
            <h1>Archive Folder</h1>
          </div>
          <div class="col-sm-6">
            <ol class="breadcrumb float-sm-right">
              <li class="breadcrumb-item"><a href="#">Home</a></li>
              <li class="breadcrumb-item active">Archive folder</li>
            </ol>
          </div>
        </div>
      </div><!-- /.container-fluid -->
    </section>

   
    <section class="content"> <!-- Main content -->

    <div class="card"> <!-- Default box -->
        <div class="card-header">
          <h3 class="card-title"><i class="fas fa-clipboard"></i>&nbsp; List of archive items</h3>
        </div>
        <div class="card-body">
        <table id="example1" class="table table-bordered table-hover">
                  <thead>
                  <tr class="bg-dark text-light bg-gradient bg-opacity-150">
                  <th>ID</th>
                    <th>ITEM</th>    
                    <th>DESCRIPTION</th>
                    <th>P.A.R NUMBER</th>
                    <th>UNIT VALUE</th>
                    <th>DATE</th>
                    <th>QTY</th>        
                    <th>ACCOUNT CODE</th>
                    <th>SUPPLIER</th>
                    <th>ACTION</th>
                  </tr>
                  </thead>
                  <tbody>
    <?php
    $query = "SELECT * FROM archive";
    $results = mysqli_query($conn, $query);
    $cnt = 1;
    if(mysqli_num_rows($results)){
      foreach($results as $row ){?>

  <tr>
    <td><?php echo htmlentities($cnt); ?></td>
    <td><?=$row['item']?></td>
    <td><?=$row['description']?></td>
    <td><?=$row['par_number']?></td>
    <td><?=$row['unit_value']?></td>
    <td><?=$row['date_aquired']?></td>
    <td><?=$row['quantity']?></td>
    <td><?=$row['account_code']?></td>
    <td><?=$row['supplier']?></td>
    <td>
        <button type="submit" value="<?= $result['']; ?>" class=" btn btn-sm btn-success" data-toggle="modal" data-target="#editDeptModal"><i class="fas fa-undo" data-toggle="popover" data-content="Undo" data-trigger="hover"></i></button>
        <button type="submit" value="<?= $result['']; ?>" class=" btn btn-sm btn-danger"><i class="fas fa-trash" data-toggle="popover" data-content="Trash" data-trigger="hover"></i></button>
    </td>
  </tr>

      <?php $cnt++; }}?>

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