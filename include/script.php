<script src="../assets/plugins/jquery/jquery.min.js"></script><!-- jQuery -->
<script src="../assets/plugins/bootstrap/js/bootstrap.bundle.min.js"></script><!-- Bootstrap 4 -->
<script src="../assets/plugins/select2/js/select2.full.min.js"></script><!-- Select2 -->
<script src="../assets/dist/js/adminlte.min.js"></script><!-- Custom App -->
<script>
	window.currentUserRole = "<?php echo isset($_SESSION['role']) ? htmlspecialchars(strtoupper(trim((string)$_SESSION['role'])), ENT_QUOTES) : ''; ?>";
</script>
<?php
	$__gsoScriptPath = __DIR__ . '/../assets/dist/js/script.js';
	$__gsoScriptVer = @filemtime($__gsoScriptPath);
	if (!$__gsoScriptVer) { $__gsoScriptVer = '20260205'; }
?>
<script src="../assets/dist/js/script.js?v=<?php echo urlencode((string)$__gsoScriptVer); ?>"></script><!---Custom App-->
<script src="../assets/plugins/datatables/jquery.dataTables.min.js"></script><!-- DataTables  & Plugins -->
<script src="../assets/plugins/datatables-bs4/js/dataTables.bootstrap4.min.js"></script><!-- DataTables  & Plugins -->
<script src="../assets/plugins/datatables-responsive/js/dataTables.responsive.min.js"></script><!-- DataTables  & Plugins -->
<script src="../assets/plugins/datatables-responsive/js/responsive.bootstrap4.min.js"></script><!-- DataTables  & Plugins -->
<script src="../assets/plugins/datatables-buttons/js/dataTables.buttons.min.js"></script>
<script src="../assets/plugins/datatables-buttons/js/buttons.bootstrap4.min.js"></script>
<script src="../assets/plugins/datatables-fnReloadAjax/fnReloadAjax.js"></script><!-- Reload-Plugins -->
<script src="../assets/plugins/jszip/jszip.min.js"></script>
<script src="../assets/plugins/pdfmake/pdfmake.min.js"></script>
<script src="../assets/plugins/pdfmake/vfs_fonts.js"></script>
<script src="../assets/plugins/datatables-buttons/js/buttons.html5.min.js"></script>
<script src="../assets/plugins/datatables-buttons/js/buttons.print.min.js"></script>
<script src="../assets/plugins/datatables-buttons/js/buttons.colVis.min.js"></script>
<script src="../assets/plugins/jquery-validation/jquery.validate.min.js"></script><!-- jquery-validation -->
<script src="../assets/plugins/jquery-validation/additional-methods.min.js"></script><!-- jquery-validation -->
<script src="../assets/dist/js/form-validation.js"></script><!--form input validation-->
<script src="../assets/plugins/sweetalert2/sweetalert2.min.js"></script><!--sweetalert-->
<script src="../assets/plugins/datepicker/bootstrap-datepicker.min.js"></script><!--datepicker-->
<script src="../assets/plugins/chart.js/Chart.min.js"></script>