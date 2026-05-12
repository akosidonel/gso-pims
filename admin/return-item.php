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
            <h1>Returned Property</h1>
          </div>
          <div class="col-sm-6">
            <ol class="breadcrumb float-sm-right">
              <li class="breadcrumb-item"><a href="#">Home</a></li>
              <li class="breadcrumb-item active">Return to item</li>
            </ol>
          </div>
        </div>
      </div><!-- /.container-fluid -->
    </section>

   
    <section class="content"> <!-- Main content -->

    <div class="card"> <!-- Default box -->
        <div class="card-header">
          <h3 class="card-title"><i class="fas fa-clipboard"></i>&nbsp; List of returned property</h3>
        </div>

        <div class="modal fade" id="reassign" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
          <div class="modal-content">
            <div class="modal-header">
              <h5 class="modal-title">Reassignment  Information</h5>
              <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                <span aria-hidden="true">&times;</span>
              </button>
            </div>
            <div class="modal-body">
              <form action="#" id="ret_form" method="post" enctype="multipart/form-data">
              <input type="hidden" name="rid" id="rid">
              <div class="form-group">
                <label >P.A.R</label>
                <input type="text" class="form-control" name="par" id="par" placeholder="P.A.R" readonly>
              </div> 
              <div class="form-group">
                <label >End User</label>
                <input type="text" class="form-control" id="enduser" name="enduser" placeholder="Username">
              </div> 
              <div class="form-group">
                <label >Department</label>
                <select id="dept" name="dept" class="form-control">
                  <option selected>Choose...</option>
                  <option value="1">G.S.O</option>
                  <option value="2">Accounting</option>
                  <option value="8">B.A.C</option>
                </select>
              </div>
             
            </div> 
            <div class="modal-footer">
              <button type="submit" class="btn btn-secondary">Save changes</button>
              </form>
            </div>
          </div>
        </div>
      </div>





        <div class="card-body">
        <table id="dataTable" class="table table-bordered table-hover">
                  <thead>
                  <tr class="bg-dark text-light bg-gradient bg-opacity-150">
                    <th class="col-sm-1">ARTICLE</th>    
                    <th class="col-sm-2">BRAND/MODEL</th>
                    <th class="col-sm-3">SERIAL NUMBER</th>
                    <th class="col-sm-2">PROPERTY NUMBER</th>
                    <th class="col-sm-2">DATE RETURN</th>
                    <th class="col-sm-1">ACTION</th>
                  </tr>
                  </thead>
                  <tbody>
                    <?php
                    $query = "SELECT * FROM return_to_stock";
                    $results = mysqli_query($conn, $query);

                    if(mysqli_num_rows($results)){
                      foreach($results as $result){?>
                        <tr>
                          <td><?=$result['item']?></td>
                          <td><?=$result['model']?></td>
                          <td><?=$result['serial_number']?></td>
                          <td><?=$result['par_number']?></td>
                          <td><?=date('M-d-Y',strtotime($result['created_at']))?></td>
                          <td>
                          <button type="submit" value="<?= $result['id']; ?>" class="editreturn btn btn-sm btn-success" data-toggle="modal" data-target="#reassign"><i class="fas fa-recycle" data-toggle="popover" data-content="Re-M.R" data-trigger="hover"></i></button>
                          <button type="submit" value="<?= $result['id']; ?>" class="btn btn-sm btn-danger"><i class="fas fa-archive" data-toggle="popover" data-content="Archive" data-trigger="hover"></i></button>
                          </td>
                        </tr>
                    <?php }}?>
                
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

</script>
<script>
  $(document).on('click','.editreturn',function(){
      var retid = $(this).val();
      $.ajax({
        type:'GET',
        url: '../auth/auth.php?retid='+ retid,
        success:function(response){
            var res = jQuery.parseJSON(response);

            if(res.status == 422){
              alert(res.message);
            }else if(res.status == 200){
              $('#rid').val(res.data.id);
              $('#par').val(res.data.par_number);
              $('#reassign').modal('show');
        }
      }
    });
  });
  $(document).on('submit','#ret_form', function(e){
    e.preventDefault();
    var fd  = new FormData(this);
    fd.append("save_retitem",true);

    $.ajax({
      type: "POST",
      url: "../auth/auth.php",
      data: fd,
      processData: false,
      contentType: false,
      success: function(response){

        var res = jQuery.parseJSON(response);

        if(res.status == 500){
            $('#errorMessage').text(res.message);
          }else if(res.status == 200 ){
            $('#reassign').modal('hide');
            $('#ret_form')[0].reset();
            $('#example1').load(location.href + " #example1");
        }
      }
    });
  });
</script>

<?php }?>