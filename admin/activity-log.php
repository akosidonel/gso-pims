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
            <h1>Activity Log</h1>
          </div>
          <div class="col-sm-6">
            <ol class="breadcrumb float-sm-right">
              <li class="breadcrumb-item"><a href="#">Home</a></li>
              <li class="breadcrumb-item active">Activity Log</li>
            </ol>
          </div>
        </div>
      </div><!-- /.container-fluid -->
    </section>
    <section class="content"> <!-- Main content -->
      <div class="card"> <!-- Default box -->
        <div class="card-header">
          <h3 class="card-title"><i class="fas fa-clipboard"></i>&nbsp; List of activity</h3>
        </div>
        <div class="card-body">
            <table id="activityLogTable" class="table table-bordered table-hover" cellspacing="0" width="100%">
                  <thead>
                    <tr class="bg-dark text-light bg-gradient bg-opacity-150">
                      <th class="col-sm-2">DATE & TIME</th>
                      <th class="col-sm-2">USER</th>
                      <th class="col-sm-2">ROLE</th>
                      <th class="col-sm-2">IP ADDRESS</th>
                      <th class="col-sm-4">ACTION MADE</th>
                    </tr>
                  </thead>
                  <tbody>
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

<script type="text/javascript">
$(document).ready(function(){
  var table = $('#activityLogTable').DataTable({
    "fnCreateRow":function(nRow, aData, iDataIndex){
      $(nRow).attr('id', aData[0]);
    },
    'serverSide': 'true',
    'paging': 'true',
    'order': [],
    'stateSave': 'true',
    'responsive': 'true',
    'dom': 'Bfrtip',
    'buttons': [{ extend: 'excel', title: 'PTMS-USERS AUDIT LOG'}, { extend: 'print', title: 'PTMS-USERS AUDIT LOG'}],
    'ajax':{
      'url':'../auth/fetch_activitylog_dataTable.php',
      'type': 'post',
    },
    "aoColumnDefs": [{
            "bSortable": false,
            "aTargets": [4]
      },
    ]
  });
  setInterval(() => {
    table.ajax.reload(null, false);
  },1000);
});
</script>

<?php }?>