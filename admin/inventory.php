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

  <?php
                  $pass = substr(str_shuffle("0123456789"), 0, 10);
                  $year = date("Y");
                  $code = $year."-".$pass;
            ?>

   <!-- Preloader -->
<div class="preloader flex-column justify-content-center align-items-center">
    <img src="../assets/dist/img/spin.gif" alt="AdminLogo" height="90" width="90">
</div>

<div id="destroy"></div>

  <div class="content-wrapper"><!-- Content Wrapper. Contains page content -->
   
    <section class="content-header"> <!-- Content Header (Page header) -->
      <div class="container-fluid">
        <div class="row mb-2">
          <div class="col-sm-6">
            <h1>Inventory</h1>
          </div>
          <div class="col-sm-6">
            <ol class="breadcrumb float-sm-right">
              <li class="breadcrumb-item"><a href="dashboard.php">Home</a></li>
              <li class="breadcrumb-item"><a href="dashboard-inventory.php">Return to Stock Inventory</a></li>
              <li class="breadcrumb-item active">Inventory</li>
            </ol>
          </div>
        </div>
      </div><!-- /.container-fluid -->
    </section>

   
    <section class="content"> <!-- Main content -->

     
      <div class="card"> <!-- Default box -->
        <div class="card-header">
        <h3 class="card-title"><i class="fas fa-dolly-flatbed"></i>&nbsp; List of returned items</h3>
      </div>

  <!-- Transfer of property to new enduser section-->
  <div class="modal fade" id="transInModal" data-backdrop="static" tabindex="-1" role="dialog">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="exampleModalLabel">Transfer of Property</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
  <form method="POST" id="parTransferFromStock"  enctype="multipart/form-data">
  <h6 class="text-success">Property Information</h6>
        <div class="form-row">
        <div class="form-group col-md-6">
            <label class="col-form-label">P.A.R No.</label>
            <input type="text" class="form-control" id="par_num" name="par_num" readonly>
            <input type="hidden" class="form-control" id="category" name="category" readonly>
          </div>
          <div class="form-group col-md-6">
            <label for="message-text" class="col-form-label">Item</label>
          <input type="text" class="form-control" id="citem" name="citem" readonly>
          </div>
        </div>
          <div class="form-row">   
          <div class="form-group col-md-6">
            <label class="col-form-label">Previous user</label>
            <input type="hidden" id="empid" name="empid">
            <input type="text" class="form-control" id="cuser" name="cuser" readonly>
          </div>
          <div class="form-group col-md-6">
            <label for="message-text" class="col-form-label">Department</label>
            <input type="hidden" name="cdeptid" id="cdeptid">
            <input type="text" class="form-control text-uppercase" id="cdept" name="cdept" readonly>
          </div>
          </div>
          <hr>
          <h6  class="text-success">Transfer to</h6>
          
          <input type="hidden" value="<?php echo $code ?>" name="refnum" id="refnum">
          <div class="form-row">
            <div class="form-group col-md-6">
              <label class="col-form-label">Department</label>
              <input type="text" id="deptSearch" class="form-control mb-2" placeholder="Type to search department" list="deptDatalist" autocomplete="off">
              <datalist id="deptDatalist"></datalist>
              <select name="dept" id="dept" class="form-control" required style="display:none;">
                <option value="">-SELECT-</option>
                <?php 
                  $sql = "SELECT * FROM department ORDER BY department_name ASC";
                  $query = mysqli_query($conn,$sql);
                  if(mysqli_num_rows($query) > 0){
                    foreach($query as $result){?>
                      <option value="<?php echo htmlentities($result['department_code']);?>"><?php echo htmlentities($result['department_name']);?></option>
                <?php }} ?>
              </select>
            </div>
            <div class="form-group col-md-6">
              <label class="col-form-label">New user</label>
              <select name="parEmp" id="parEmp" class="form-control" autocomplete="off" required>
                <option value="">-SELECT-</option>
              </select>
            </div>
          </div>
          <div id="add_new_employee_transfer" style="display:none;">
            <div class="form-row">
              <input type="hidden" class="form-control text-uppercase" name="emp_id" id="emp_id" value="" readonly>
              <div class="form-group col-md-6">
                <label class="col-form-label">Add New Employee</label>
                <input type="text" class="form-control text-uppercase" id="new_emp" name="new_emp" placeholder="Enter New Employee Name">
                <small id="transfer-name-validation-msg" class="form-text ml-1" style="display:none;"></small>
              </div>
              <div class="form-group col-md-6">
                <label class="col-form-label">Position</label>
                <input type="text" class="form-control text-uppercase" id="position" name="position" placeholder="Enter Employee Position">
              </div>
            </div>
          </div>
          <div class="form-row" id="reasonConditionRow" style="display:none;">
            <div class="form-group col-md-6">
              <label class="col-form-label">Reason for Transfer</label>
              <select name="reason" id="reason" class="form-control">
                <option value="">-SELECT-</option>
                <option value="REDISTRIBUTION OF UNUTILIZED EQUIPMENT">REDISTRIBUTION OF UNUTILIZED EQUIPMENT</option>
                <option value="REQUEST FROM RECEIVING OFFICE">REQUEST FROM RECEIVING OFFICE</option>
                <option value="SUPPORT FOR SPECIAL PROJECTS">SUPPORT FOR SPECIAL PROJECTS</option>
                <option value="OTHERS">OTHERS</option>
              </select>
            </div>
            <div class="form-group col-md-6">
              <label class="col-form-label">Condition of the Equipment</label>
              <select name="condition" id="condition" class="form-control">
                <option value="">-SELECT-</option>
                <option value="SERVICEABLE">SERVICEABLE</option>
                <option value="UNSERVICEABLE">UNSERVICEABLE</option>
              </select>
            </div>
          </div>
      </div>
      <div class="modal-footer">
        <button type="submit" class="btn btn-success"><i class="fas fa-recycle"></i> Print and Transfer</button>
        </form>
      </div>
    </div>
  </div>
</div>
<!--validate for unserviceable items -->
<div class="modal fade" id="stockItemsModal" data-backdrop="static" tabindex="-1" role="dialog">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="exampleModalLabel">Inventory</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
      <form method="POST" id="unserviceableItems"  enctype="multipart/form-data">
      <h6 class="text-dark">Are you sure this item is unserviceable?</h6>
      <input type="hidden" class="form-control" id="parnum" name="parnum">
      <input type="hidden" class="form-control" id="cat" name="cat">
      <input type="hidden" class="form-control" id="deptid" name="deptid">
      <input type="hidden" value="<?php echo $code ?>" name="refnumber" id="refnumber">
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">No</button>
        <button type="submit" class="btn btn-success">Yes</button>
        </form>
      </div>
    </div>
  </div>
</div>

<!--validate for unserviceable items-->


        <div class="card-body">
        <table id="dataTable" class="table table-bordered table-hover">
                  <thead>
                  <tr class="bg-dark text-light bg-gradient bg-opacity-150">
                    <th class="col-sm-1">ACTION</th>    
                    <th class="col-sm-2">ITEM</th>    
                    <th class="col-sm-2">BRAND/MODEL</th>
                    <th class="col-sm-2">SNID NO.1</th>
                    <th class="col-sm-2">SNID NO.2</th>
                    <th class="col-sm-2">PROPERTY NUMBER</th>
                    <th class="col-sm-2">DEPARTMENT</th>
                  </tr>
                  </thead>
                  <tbody>
               
                 <?php 
                  $inventoryBaseSql = "SELECT rts.par_number, rts.item, rts.model, rts.serial_number, rts.serial_number_2,
                                              COALESCE(d.department_name, '') AS department_name
                                       FROM return_to_stock AS rts
                                       LEFT JOIN department AS d ON d.department_code = (
                                         SELECT rh.dept_id
                                         FROM return_history AS rh
                                         WHERE rh.par_number = rts.par_number AND rh.status = '2'
                                         ORDER BY rh.created_at DESC
                                         LIMIT 1
                                       )";

                  if (isset($_GET['item'])) {
                      $item_filter = $_GET['item'];
                      $stmt = $conn->prepare($inventoryBaseSql . " WHERE rts.item = ?");
                      $stmt->bind_param("s", $item_filter);
                      $stmt->execute();
                      $results = $stmt->get_result();
                  } else {
                      $query = $inventoryBaseSql;
                      $results = mysqli_query($conn, $query);
                  }

                 if(mysqli_num_rows($results) > 0){
                    foreach($results as $row){?>
                    <tr>
                    <td class="text-center">
                     
                     <div class="btn-group">
                         <button type="button" class="btn btn-success btn-sm dropdown-toggle" data-toggle="dropdown" data-offset="-52"><i class="fas fa-bars" data-toggle="popover" data-content="Actions" data-trigger="hover"></i></button>
                   <div class="dropdown-menu" role="menu">
                     <a href="#" class="dropdown-item transferFromStock" data-toggle="modal" data-target="#transInModal" data-value="<?=$row['par_number']; ?>" ><i class="fa fa-exchange" aria-hidden="true"></i>&nbsp; Transfer to</a>
                     <a href="#" class="dropdown-item stockItems" data-toggle="modal" data-target="#stockItemsModal" data-value="<?=$row['par_number']; ?>"><i class="fas fa-exclamation-triangle"></i>&nbsp; Unserviceable</a>
                   </div>
                 </div>
                     </td>
                      <td><?=$row['item']?></td>
                      <td><?=$row['model']?></td> 
                      <td ><?php $sn1=$row['serial_number'];
                      if($sn1==""){?>
                        <span class="text-dark">NULL</span> 
                      <?php } else { ?> 
                      <?php
                        echo $row['serial_number'];
                      }?></td>
                      <td ><?php $sn2 = $row['serial_number_2'];
                      if($sn2==""){?>
                          <span class="text-dark">NULL</span> 
                      <?php } else {?>
                      <?php
                        echo $row['serial_number_2'];
                      }?></td>
                      <td><?=$row['par_number']?></td>
                      <td><?=htmlentities($row['department_name'] ?: 'N/A')?></td>
                    </tr>

               <?php  } } ?>

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

<script>
// Replicated transfer modal logic from general-fund-inventory (adapted for stock transfer)
$(function(){
  function setInvalid($el,msg){ if(!$el.length) return; $el.addClass('is-invalid'); var $fb=$el.next('.invalid-feedback'); if(!$fb.length){ $fb=$('<div class="invalid-feedback"></div>'); $el.after($fb);} $fb.text(msg).show(); }
  function clearInvalid($el){ if(!$el.length) return; $el.removeClass('is-invalid'); var $fb=$el.next('.invalid-feedback'); if($fb.length){ $fb.text('').hide(); }}
  function exactDeptCodeByName(name){ var target=(name||'').trim().toLowerCase(); if(!target) return null; var code=null; $('#dept option').each(function(){ var v=$(this).attr('value'); if(!v) return; var n=$(this).text().trim().toLowerCase(); if(n===target){ code=v; return false; }}); return code; }

  // Department autocomplete
  (function initDeptAutocomplete(){
    var $select=$('#dept'); var $input=$('#deptSearch'); var $list=$('#deptDatalist'); if(!$select.length||!$input.length||!$list.length) return;
    function refresh(){ $list.empty(); $select.find('option').each(function(){ var v=$(this).attr('value'); var n=$(this).text().trim(); if(!v) return; $('<option>').attr('value',n).appendTo($list); }); }
    refresh(); var wasSet=false, cleared=false; var prevVal='';
    $select.on('change',function(){ var code=$select.val(); var name=$select.find('option:selected').text().trim(); $input.val(code?name:''); wasSet=!!code; cleared=false; });
    $input.on('input',function(){ if(!cleared && wasSet && $input.val()){ $input.val(''); cleared=true; wasSet=false; } if(!$input.val().trim() && $select.val()){ $select.val('').trigger('change'); }});
    $input.on('keydown',function(e){ if(cleared||!wasSet) return; var k=e.key; if(['Shift','Control','Alt','Meta','Tab'].includes(k)) return; $input.val(''); cleared=true; wasSet=false; if($select.val()) $select.val('').trigger('change'); });
    $input.on('change',function(){ var code=exactDeptCodeByName($input.val()); if(code && $select.val()!==code){ $select.val(code).trigger('change'); }});
    $('#transInModal').on('shown.bs.modal',function(){ var name=$select.find('option:selected').text().trim(); $input.val($select.val()?name:''); cleared=false; wasSet=!!$select.val(); setTimeout(function(){ $input.trigger('focus'); },0); });
    $('#transInModal').on('hidden.bs.modal',function(){ $input.val(''); cleared=false; wasSet=false; });
  })();

  function reveal($el){ if(!$el.length) return; $el.stop(true,true).css('opacity',0).slideDown(280).animate({opacity:1},{duration:220,queue:false}); }
  function conceal($el){ if(!$el.length) return; $el.stop(true,true).animate({opacity:0},{duration:180,queue:false}).slideUp(280); }

  function toggleReasonCondition(){
    var cdept=$('#cdeptid').val()||''; var dept=$('#dept').val()||''; var $row=$('#reasonConditionRow'); var $reason=$('#reason'); var $cond=$('#condition');
    var diff=cdept && dept && String(cdept)!==String(dept);
    if(diff){ reveal($row); $reason.prop('required',true); $cond.prop('required',true); } else { conceal($row); $reason.val('').prop('required',false); $cond.val('').prop('required',false); }
  }
  $('#dept').on('change',function(){ toggleReasonCondition(); resetEmployeeSelection(); });
  function resetEmployeeSelection(){ var $emp=$('#parEmp'); var hasDept=!!($('#dept').val()||'').trim(); $emp.val(''); if(!hasDept){ $emp.prop('disabled',true).html('<option value="">SELECT A DEPARTMENT FIRST</option>'); } else { $emp.prop('disabled',false).html('<option value="">-SELECT-</option>'); } hideAddNewEmp(); }

  function hideAddNewEmp(){ var $section=$('#add_new_employee_transfer'); $('#new_emp').prop('required',false).val(''); $('#position').prop('required',false).val(''); $('#transfer-name-validation-msg').hide().text(''); if($section.is(':visible')) $section.slideUp(200); }
  function showAddNewEmp(){ var $section=$('#add_new_employee_transfer'); $section.stop(true,true).slideDown(200); $('#new_emp').prop('required',true); $('#position').prop('required',true); }
  $('#parEmp').on('change',function(){ if(($('#parEmp').val()||'').toLowerCase()==='add_new_emp'){ showAddNewEmp(); } else { hideAddNewEmp(); }});

  // Debounced employee name validation
  var validateTimer; $(document).on('input','#new_emp',function(){ var $msg=$('#transfer-name-validation-msg'); var name=($(this).val()||'').trim(); var $btn=$('#parTransferFromStock button[type=submit]'); clearTimeout(validateTimer); if(!name){ $msg.hide().text(''); $btn.prop('disabled',false); return; } $msg.show().text('Validating...').css('color','red'); $btn.prop('disabled',true); validateTimer=setTimeout(function(){ $.ajax({ url:'../auth/auth.php', type:'POST', data:{ validate_employee_name:1, emp_name:name }, dataType:'json', success:function(res){ if(res && res.exists){ $msg.text('Employee name already exists!').css('color','red'); $btn.prop('disabled',true); } else { $msg.text('Employee name is available.').css('color','green'); $btn.prop('disabled',false); } }, error:function(){ $msg.text('Validation error.').css('color','red'); $btn.prop('disabled',true); } }); },600); });

  // Form validation and submission
  $('#parTransferFromStock').on('submit',function(e){ 
    e.preventDefault(); 
    e.stopPropagation(); // Prevent global handler in script.js

    var valid=true; 
    ['#deptSearch','#dept','#parEmp','#new_emp','#position','#reason','#condition'].forEach(function(sel){ clearInvalid($(sel)); }); 
    var deptCode=($('#dept').val()||'').trim(); 
    var typed=($('#deptSearch').val()||'').trim(); 
    
    if(!deptCode){ 
      if(typed){ 
        var code=exactDeptCodeByName(typed); 
        if(code){ 
          $('#dept').val(code).trigger('change'); 
          deptCode=code; 
        } else { 
          setInvalid($('#deptSearch'),'Please select a department from the list.'); 
          valid=false; 
        } 
      } else { 
        setInvalid($('#deptSearch'),'Department is required.'); 
        valid=false; 
      } 
    }

    var emp=($('#parEmp').val()||'').trim(); 
    if(!emp){ 
      setInvalid($('#parEmp'),'Please select a new user.'); 
      valid=false; 
    } else if(emp.toLowerCase()==='add_new_emp'){ 
      var newEmp=($('#new_emp').val()||'').trim(); 
      var pos=($('#position').val()||'').trim(); 
      if(!newEmp){ 
        setInvalid($('#new_emp'),'Enter employee name.'); 
        valid=false; 
      } 
      if(!pos){ 
        setInvalid($('#position'),'Enter employee position.'); 
        valid=false; 
      } 
      var msg=($('#transfer-name-validation-msg').text()||'').toLowerCase(); 
      if(msg.indexOf('already exists')!==-1){ 
        setInvalid($('#new_emp'),'Employee name already exists.'); 
        valid=false; 
      } 
    }

    // Use IDs for comparison for better reliability
    var cdeptId = ($('#cdeptid').val()||'').trim();
    var newDeptId = ($('#dept').val()||'').trim();

    if(cdeptId && newDeptId && cdeptId !== newDeptId){ 
      var reason=($('#reason').val()||'').trim(); 
      var cond=($('#condition').val()||'').trim(); 
      if(!reason){ 
        setInvalid($('#reason'),'Select a reason.'); 
        valid=false; 
      } 
      if(!cond){ 
        setInvalid($('#condition'),'Select equipment condition.'); 
        valid=false; 
      } 
    }

    if(!valid){ 
      return; 
    }

    // Proceed with AJAX submission
    var refnum = document.getElementById("refnum").value;
    var category = document.getElementById("category").value;

    // Build printable URLs now (user-gesture context) and pre-open tabs so popup blockers don't force same-tab redirects.
    var urls = [];
    if(category == "PAR"){
      urls.push('printpt.php?reference_number='+encodeURIComponent(refnum));
    } else if(category == "ICS"){
      urls.push('inventory_custodian_slip.php?reference_number='+encodeURIComponent(refnum));
    }
    if(cdeptId && newDeptId && cdeptId !== newDeptId){
      urls.push('property_transfer_report.php?reference_number='+encodeURIComponent(refnum));
    }

    var openedTabs = urls.map(function(){
      try { return window.open('about:blank', '_blank'); } catch(_) { return null; }
    });

    var fd = new FormData(this);
    fd.append("parTransferFromStock", true);

    $.ajax({
      type: "POST",
      url: "../auth/auth.php",
      data: fd,
      processData: false,
      contentType: false,
      success: function(response){
        var res = jQuery.parseJSON(response);
        if(res.status == 200){
          Swal.fire({
            position: 'center',
            icon: 'success',
            title: 'Property Transferred Successfully',
            showConfirmButton: false,
            timer: 1500
          });
          setTimeout(function(){
            $('#transInModal').modal('hide');

            // Navigate pre-opened tabs to their print URLs (no same-tab redirects)
            urls.forEach(function(u, idx){
              var w = openedTabs[idx];
              try {
                if (w && !w.closed) {
                  w.location.href = u;
                } else {
                  w = window.open(u, '_blank');
                }
                if (w) {
                  try { w.focus(); } catch(_) {}
                  setTimeout(function(){ try { w.print(); } catch(_) {} }, 900);
                }
              } catch(_) {
                // Intentionally do nothing: keep current page in place
              }
            });

            location.reload();
          }, 1900);
        } else {
          // Close any tabs we pre-opened if transfer fails
          openedTabs.forEach(function(w){ try { if (w && !w.closed) w.close(); } catch(_){} });
          if(res.message) alert(res.message);
        }
      },
      error: function() {
        openedTabs.forEach(function(w){ try { if (w && !w.closed) w.close(); } catch(_){} });
        alert('Error transferring property');
      }
    });
  });
  $(document).on('input change','#deptSearch, #dept, #parEmp, #new_emp, #position, #reason, #condition',function(){ clearInvalid($(this)); });
  toggleReasonCondition();
});
</script>

<?php }?>