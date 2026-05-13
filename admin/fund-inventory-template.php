<?php
include_once('../config/session.php');
include('../config/check_session.php');

if (!isset($_SESSION['alogin'])) {
  header('Location:../index.php');
  exit();
}

$fundKey = isset($fundKey) ? (string)$fundKey : '';
$fundTitle = isset($fundTitle) ? (string)$fundTitle : 'Fund Inventory';
$fundSubtitle = isset($fundSubtitle) ? (string)$fundSubtitle : 'Property Inventory';

if (!in_array($fundKey, ['trust', 'donation'], true)) {
  header('Location:dashboard.php');
  exit();
}
?>

<?php include('../include/header.php') ?>
<?php include('../include/navbar.php') ?>
<?php include('../include/sidebar.php') ?>

<div class="preloader flex-column justify-content-center align-items-center">
  <img src="../assets/dist/img/spin.gif" alt="AdminLogo" height="90" width="90">
</div>

<div id="destroy"></div>

<div class="content-wrapper">
  <section class="content-header">
    <div class="container-fluid">
      <div class="row mb-2">
        <div class="col-sm-6">
          <h1><?php echo htmlspecialchars($fundTitle); ?></h1>
        </div>
        <div class="col-sm-6">
          <ol class="breadcrumb float-sm-right">
            <li class="breadcrumb-item"><a href="dashboard.php">Home</a></li>
            <li class="breadcrumb-item active"><?php echo htmlspecialchars($fundTitle); ?></li>
          </ol>
        </div>
      </div>
    </div>
  </section>

  <section class="content">
    <div class="card">
      <div class="card-header">
        <h3 class="card-title" id="reportTitle"><i class="fas fa-clipboard"></i>&nbsp; <b><?php echo htmlspecialchars($fundSubtitle); ?></b></h3>
      </div>
      <div class="card-body">
        <table id="FundInventoryTable" class="table table-bordered table-hover">
          <thead>
            <tr class="bg-dark text-light bg-gradient bg-opacity-150">
              <th class="w-15">ASSET CLASS</th>
              <th class="w-25">PARTICULARS</th>
              <th class="w-10">SNID NO.1</th>
              <th class="w-10">SNID NO.2</th>
              <th class="w-10">PROPERTY NUMBER</th>
              <th class="w-10">END USER</th>
              <th class="d-none">MODEL</th>
              <th class="d-none">DESCRIPTION</th>
              <th class="d-none">YEAR ACQUIRED</th>
              <th class="d-none">UNIT</th>
            </tr>
          </thead>
          <tbody></tbody>
        </table>
      </div>
    </div>
  </section>
</div>

<?php include('../include/footer.php') ?>
<?php include('../include/script.php') ?>

<script>
(function(){
  function escHtml(text) {
    if (text === null || text === undefined) return '';
    return String(text)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }

  function cleanExportValue(value) {
    return $('<div>').html(value == null ? '' : value).text().trim();
  }

  function hasNoSerial(value) {
    var serial = cleanExportValue(value).toUpperCase();
    return serial === '' || serial === 'NULL' || serial === 'N/A' || serial === 'NA' || serial === 'NONE';
  }

  function displayUnit(value) {
    var unit = cleanExportValue(value);
    return unit !== '' ? unit : 'pcs';
  }

  function summarizeExcelInventoryRows(exportData) {
    var grouped = {};
    var output = [];

    exportData.header.splice(3, 0, 'QTY');

    exportData.body.forEach(function(row) {
      var item = cleanExportValue(row[0]);
      var model = cleanExportValue(row[1]);
      var description = cleanExportValue(row[2]);
      var unit = displayUnit(row[3]);
      var serialPrimary = cleanExportValue(row[4]);
      var serialSecondary = cleanExportValue(row[5]);
      var propertyNumber = cleanExportValue(row[6]);
      var yearAcquired = cleanExportValue(row[7]);
      var endUser = cleanExportValue(row[8]);

      if (!hasNoSerial(serialPrimary) || !hasNoSerial(serialSecondary)) {
        output.push([item, model, description, 1, unit, serialPrimary, serialSecondary, propertyNumber, yearAcquired, endUser]);
        return;
      }

      var key = [item, model, description, unit, yearAcquired, endUser].join('|').toUpperCase();
      if (!grouped[key]) {
        grouped[key] = [item, model, description, 0, unit, '', '', '', yearAcquired, endUser];
        output.push(grouped[key]);
      }

      grouped[key][3] += 1;
      grouped[key][7] = grouped[key][7] ? grouped[key][7] + '\n' + propertyNumber : propertyNumber;
    });

    exportData.body = output;
  }

  var fundKey = <?php echo json_encode($fundKey); ?>;
  var selectedAssetClass = '';
  var selectedEndUser = '';
  var selectedParIcs = '';

  function hasFocusedExportFilter(dt) {
    return (selectedAssetClass || '').trim() !== '' ||
      (selectedEndUser || '').trim() !== '' ||
      (selectedParIcs || '').trim() !== '' ||
      (dt.search() || '').trim() !== '';
  }

  function withAllRows(dt, callback) {
    if (!hasFocusedExportFilter(dt)) {
      return callback();
    }

    var previousLength = dt.page.len();
    if (previousLength === -1) {
      return callback();
    }

    dt.one('draw', function(){
      callback();
      setTimeout(function(){
        try { dt.page.len(previousLength).draw(false); } catch(_) {}
      }, 400);
    });

    dt.page.len(-1).draw();
  }

  var table = $('#FundInventoryTable').DataTable({
    responsive: true,
    lengthChange: true,
    pageLength: 25,
    lengthMenu: [[10, 25, 50, 100, 500, 1500], [10, 25, 50, 100, 500, 1500]],
    autoWidth: false,
    stateSave: false,
    dom: "<'row'<'col-sm-12 col-md-8'Bl><'col-sm-12 col-md-4'f>>" +
         "<'row'<'col-sm-12'tr>>" +
         "<'row'<'col-sm-12 col-md-5'i><'col-sm-12 col-md-7'p>>",
    processing: true,
    serverSide: true,
    deferRender: true,
    ajax: {
      url: '../auth/fetch_fund_inventory_dataTable.php',
      type: 'POST',
      data: function(d) {
        d.fund = fundKey;
        d.asset_class = selectedAssetClass;
        d.end_user = selectedEndUser;
        d.par_ics = selectedParIcs;
      }
    },
    columns: [
      { data: 'item', render: function(data) { return escHtml(data); } },
      { data: null, render: function(data, type, row) { return escHtml((row.model || '') + ' - ' + (row.description || '')); } },
      { data: 'serial_number', render: function(data) { return data ? escHtml(data) : "<span class='text-dark'>NULL</span>"; } },
      { data: 'serial_number_2', render: function(data) { return data ? escHtml(data) : "<span class='text-dark'>NULL</span>"; } },
      { data: 'par_number', render: function(data) { return data ? escHtml(data) : "<span class='text-dark'>NULL</span>"; } },
      { data: 'emp_name', render: function(data) { return escHtml(data); } },
      { data: 'model', visible: false, render: function(data) { return escHtml(data); } },
      { data: 'description', visible: false, render: function(data) { return escHtml(data); } },
      { data: 'date_aquired', visible: false, render: function(data) { return escHtml(data); } },
      { data: 'unit', visible: false, render: function(data) { return escHtml(data); } }
    ],
    columnDefs: [
      { targets: [6, 7, 8, 9], visible: false, searchable: false }
    ],
    order: [[0, 'asc']],
    buttons: [
      {
        extend: 'excel',
        orientation: 'landscape',
        pageSize: 'LEGAL',
        title: function() { return $('#reportTitle').text() || 'Inventory Report'; },
        exportOptions: {
          columns: [0, 6, 7, 9, 2, 3, 4, 8, 5],
          format: {
            header: function(data, columnIdx) {
              var headers = {
                0: 'ITEM',
                6: 'MODEL',
                7: 'DESCRIPTION',
                9: 'UNIT',
                2: 'SERIAL NUMBER PRIMARY',
                3: 'SERIAL NUMBER SECONDARY',
                4: 'PROPERTY NUMBER',
                8: 'YEAR ACQUIRED',
                5: 'END USER'
              };
              return headers[columnIdx] || data;
            },
            body: function(data) { return $('<div>').html(data).text(); }
          },
          customizeData: summarizeExcelInventoryRows
        },
        action: function(e, dt, button, config) {
          var self = this;
          withAllRows(dt, function(){
            var buttonsExt = $.fn && $.fn.dataTable && $.fn.dataTable.ext && $.fn.dataTable.ext.buttons;
            if (!buttonsExt) return;
            var act = (buttonsExt.excel && buttonsExt.excel.action) ? buttonsExt.excel.action : null;
            if (!act && buttonsExt.excelHtml5 && buttonsExt.excelHtml5.action) act = buttonsExt.excelHtml5.action;
            if (act) act.call(self, e, dt, button, config);
          });
        }
      },
      {
        extend: 'print',
        orientation: 'landscape',
        pageSize: 'LEGAL',
        title: function() { return $('#reportTitle').text() || 'Inventory Report'; },
        exportOptions: { columns: ':visible' },
        action: function(e, dt, button, config) {
          var self = this;
          withAllRows(dt, function(){
            if ($.fn && $.fn.dataTable && $.fn.dataTable.ext && $.fn.dataTable.ext.buttons && $.fn.dataTable.ext.buttons.print && $.fn.dataTable.ext.buttons.print.action) {
              $.fn.dataTable.ext.buttons.print.action.call(self, e, dt, button, config);
            }
          });
        }
      },
      {
        extend: 'pdfHtml5',
        orientation: 'landscape',
        pageSize: 'LEGAL',
        title: 'INVENTORY REPORT',
        exportOptions: { columns: ':visible' },
        action: function(e, dt, button, config) {
          var self = this;
          withAllRows(dt, function(){
            if ($.fn && $.fn.dataTable && $.fn.dataTable.ext && $.fn.dataTable.ext.buttons && $.fn.dataTable.ext.buttons.pdfHtml5 && $.fn.dataTable.ext.buttons.pdfHtml5.action) {
              $.fn.dataTable.ext.buttons.pdfHtml5.action.call(self, e, dt, button, config);
            }
          });
        }
      }
    ]
  });

  if (table.buttons && typeof table.buttons === 'function') {
    table.buttons().container().appendTo('#FundInventoryTable_wrapper .col-md-8:eq(0)');
  }

  var assetClassSelect = $('<select id="assetClassSelect" class="form-control form-control-sm ml-3" style="min-width:160px; max-width:290px; width:auto; display:inline-block;"><option value="">ALL ASSET CLASS</option></select>');
  var endUserSelect = $('<select id="endUserSelect" class="form-control form-control-sm ml-3" style="min-width:160px; max-width:290px; width:auto; display:inline-block;"><option value="">ALL END USER</option></select>');
  var parIcsSelect = $('<select id="parIcsSelect" class="form-control form-control-sm ml-3" style="min-width:120px; max-width:160px; width:auto; display:inline-block;"><option value="">ALL PAR/ICS</option><option value="PAR">PAR</option><option value="ICS">ICS</option></select>');

  function populateFiltersAllRecords() {
    assetClassSelect.find('option:not(:first)').remove();
    endUserSelect.find('option:not(:first)').remove();
    assetClassSelect.append('<option value="" disabled>Loading...</option>');
    endUserSelect.append('<option value="" disabled>Loading...</option>');

    $.ajax({
      url: '../auth/fetch_fund_inventory_filters.php',
      type: 'POST',
      dataType: 'json',
      data: { fund: fundKey },
      success: function(resp) {
        assetClassSelect.find('option:not(:first)').remove();
        endUserSelect.find('option:not(:first)').remove();

        ((resp && Array.isArray(resp.asset_classes)) ? resp.asset_classes : []).forEach(function(value) {
          var safe = $('<div>').text(value).html();
          assetClassSelect.append('<option value="' + safe + '">' + safe + '</option>');
        });
        ((resp && Array.isArray(resp.end_users)) ? resp.end_users : []).forEach(function(value) {
          var safe = $('<div>').text(value).html();
          endUserSelect.append('<option value="' + safe + '">' + safe + '</option>');
        });

        assetClassSelect.val(selectedAssetClass);
        endUserSelect.val(selectedEndUser);
      }
    });
  }

  assetClassSelect.on('change', function(){
    selectedAssetClass = $(this).val() || '';
    table.ajax.reload(null, true);
  });
  endUserSelect.on('change', function(){
    selectedEndUser = $(this).val() || '';
    table.ajax.reload(null, true);
  });
  parIcsSelect.on('change', function(){
    selectedParIcs = ($(this).val() || '').toUpperCase();
    table.ajax.reload(null, true);
  });

  table.one('draw', populateFiltersAllRecords);

  setTimeout(function(){
    var left = $('#FundInventoryTable_wrapper .col-md-8:eq(0)');
    var dtButtons = (table.buttons && typeof table.buttons === 'function') ? $(table.buttons().container()) : left.find('.dt-buttons');
    var dtLength = left.find('.dataTables_length');
    var flexDiv = $('<div class="dt-toolbar-flex"></div>');

    flexDiv.append(dtButtons);
    flexDiv.append(dtLength);
    flexDiv.append(parIcsSelect);
    flexDiv.append(assetClassSelect);
    flexDiv.append(endUserSelect);
    left.children().not('.dt-toolbar-flex').remove();
    left.append(flexDiv);

    flexDiv.css({ display: 'flex', alignItems: 'center', gap: '16px', flexWrap: 'nowrap', width: '100%', overflowX: 'auto', whiteSpace: 'nowrap', minHeight: '42px' });
    dtButtons.css({ display: 'flex', flexWrap: 'nowrap', gap: '6px', alignItems: 'center', marginBottom: 0 });
    dtLength.css({ display: 'flex', alignItems: 'center', marginBottom: 0 });
    dtLength.find('label').css({ display: 'flex', alignItems: 'center', gap: '6px', marginBottom: 0, whiteSpace: 'nowrap' });
  }, 0);
})();
</script>
