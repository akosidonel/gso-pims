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
            <h1>SEF Account Code</h1>
          </div>
          <div class="col-sm-6">
            <ol class="breadcrumb float-sm-right">
              <li class="breadcrumb-item"><a href="#">Home</a></li>
              <li class="breadcrumb-item active">Account Code</li>
            </ol>
          </div>
        </div>
      </div><!-- /.container-fluid -->
    </section>

   
    <section class="content"> <!-- Main content -->
      <div class="card"> <!-- Default box -->
        <div class="card-header">
          <h3 class="card-title"><i class="fas fa-clipboard"></i>&nbsp; List of account code</h3>
          <div class="card-tools">
            <button type="button" class="btn btn-block bg-gradient-success btn-sm" data-toggle="modal" data-target="#addAccntModal">
              <i class="fas fa-book"></i>&nbsp; Add Account Code
            </button> 

            <!-- add account code modal -->
            <div class="modal fade" id="addAccntModal">
              <div class="modal-dialog modal-lg">
                <div class="modal-content">
                  <div class="modal-header">
                    <h5 class="modal-title">Add Account Code</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                      <span aria-hidden="true">&times;</span>
                    </button>
                  </div>
                  <form class="form-horizontal" id="acct_form" method="POST" enctype="multipart/form-data">
                    <div class="modal-body">
                      <div class="alert alert-warning d-none"></div>
                      <div class="card-body">
                        <div class="form-group">
                          <label>Account Title</label>
                          <input type="text" class="form-control text-uppercase" name="acctname" id="acctname" placeholder="Account title">  
                        </div>
                        <div class="form-group ">
                          <label>Account Code</label>
                          <input type="text" class="form-control text-uppercase" name="acctcode" id="acctcode" placeholder="Account code">   
                        </div>   
                      </div>
                    </div>
                    <div class="modal-footer">
                      <button type="submit" class="btn btn-success"><i class="fa-solid fa-floppy-disk"></i>&nbsp;Save</button>
                    </div>
                  </form>
                </div>
              </div>
            </div>

            <!-- edit account code modal -->
            <div class="modal fade" id="editAccntModal">
              <div class="modal-dialog modal-lg">
                <div class="modal-content">
                  <div class="modal-header">
                    <h5 class="modal-title">Edit Account Code Information</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                      <span aria-hidden="true">&times;</span>
                    </button>
                  </div>
                  <form class="form-horizontal" id="acct_update" method="POST" enctype="multipart/form-data">
                    <div class="modal-body">
                      <div class="alert alert-warning d-none"></div>
                      <div class="card-body">
                        <div class="form-group row">
                          <input type="hidden" name="AccntId" id="AccntId">
                          <label class="col-sm-2 col-form-label">Account title</label>
                          <div class="col-sm-10">
                            <input type="text" class="form-control text-uppercase" name="eacctname" id="eacctname" placeholder="Account title">
                          </div>
                        </div>
                        <div class="form-group row">
                          <label class="col-sm-2 col-form-label">Code</label>
                          <div class="col-sm-10">
                            <input type="text" class="form-control text-uppercase" name="eacctcode" id="eacctcode" placeholder="Account code">
                          </div>
                        </div>   
                      </div>
                    </div>
                    <div class="modal-footer">
                      <button type="submit" class="btn btn-success">Update</button>
                    </div>
                  </form>
                </div>
              </div>
            </div>

          </div>
        </div>

        <div class="card-body">
          <div id="sefAccountCodePage" data-default-category="PAR"></div>

          <div class="d-flex align-items-center justify-content-between mb-3" style="gap:12px;">
            <ul class="nav nav-tabs" id="sefAcctCategoryTabs" role="tablist">
              <li class="nav-item">
                <a class="nav-link active" href="#" role="tab" data-category="PAR">PAR</a>
              </li>
              <li class="nav-item">
                <a class="nav-link" href="#" role="tab" data-category="ICS">ICS</a>
              </li>
            </ul>
            <div class="text-muted">
              <span id="reportTitle" class="d-none">SEF Account Code</span>
              <small>TOTAL AMOUNT:&nbsp;<b id="sefAcctCategoryTotal">₱ 0.00</b></small>
            </div>
          </div>

          <table id="dataTable" data-dt-custom="sefAccountCodes" class="table table-bordered table-hover">
            <thead>
              <tr class="bg-dark text-light bg-gradient bg-opacity-150">
                <th class="col-sm-1">ACCOUNT CODE</th>
                <th class="col-sm-3">ACCOUNT TITLES</th>
                <th class="col-sm-2">TOTAL AMOUNT</th>
                <th class="col-sm-1">ACTION</th>
              </tr>
            </thead>
            <tbody></tbody>
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
