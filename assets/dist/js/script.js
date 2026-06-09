
// Global helper: update fund badge text and classes
window.updateFundBadge = function(fundVal, badgeSelector){
  var $badge = (badgeSelector instanceof jQuery) ? badgeSelector : $(badgeSelector || '#fundIndicator');
  var text = (String(fundVal || '').trim()).toUpperCase();
  if (!text) { $badge.hide(); return; }
  $badge.removeClass('badge-info badge-primary badge-secondary');
  if (text === 'SEF' || text === 'SPECIAL EDUCATION FUND') {
    $badge.addClass('badge badge-secondary').text('SPECIAL EDUCATION FUND').show();
  } else {
    $badge.addClass('badge badge-primary').text('GENERAL FUND').show();
  }
};

window.gsoGetFormToken = window.gsoGetFormToken || function(tokenKey){
  var tokens = window.gsoFormTokens || {};
  return String(tokens[tokenKey] || '').trim();
};

window.gsoAppendFormToken = window.gsoAppendFormToken || function(formData, fieldName, tokenKey){
  if (!formData || typeof formData.append !== 'function') { return formData; }
  var token = window.gsoGetFormToken(tokenKey);
  if (token) {
    formData.append(String(fieldName || 'form_token'), token);
  }
  return formData;
};

window.GSO = window.GSO || {};
window.GSO.LoginLockout = window.GSO.LoginLockout || (function(){
  var timerId = null;

  function formatRemaining(seconds){
    var totalSeconds = Math.max(0, Number(seconds || 0) || 0);
    var minutes = Math.floor(totalSeconds / 60);
    var secs = totalSeconds % 60;
    if (minutes > 0) {
      return minutes + 'm ' + String(secs).padStart(2, '0') + 's';
    }
    return secs + 's';
  }

  function setFrozen(isFrozen){
    $('#empid, #password, input[name="signinbtn"]').prop('disabled', !!isFrozen);
  }

  function render(lockout){
    var $notice = $('#loginLockoutNotice');
    if (!$notice.length) { return; }

    var remainingSeconds = Math.max(0, Number(lockout.remaining_seconds || 0) || 0);
    if (!lockout.is_locked || remainingSeconds <= 0) {
      setFrozen(false);
      $notice.hide().text('');
      return;
    }

    setFrozen(true);
    $notice.text('Login temporarily locked. Try again in ' + formatRemaining(remainingSeconds) + '.').show();
  }

  function start(){
    var lockout = window.gsoLoginLockout || {};
    if (!lockout || !lockout.is_locked) { return; }

    render(lockout);
    if (timerId) {
      clearInterval(timerId);
      timerId = null;
    }

    timerId = setInterval(function(){
      lockout.remaining_seconds = Math.max(0, Number(lockout.remaining_seconds || 0) - 1);
      render(lockout);
      if (lockout.remaining_seconds <= 0) {
        clearInterval(timerId);
        timerId = null;
      }
    }, 1000);
  }

  return { start: start };
})();

// Realtime notifications (reusable)
// - Renders a bell dropdown in the shared navbar
// - Uses polling + localStorage to detect "new" items
window.GSO = window.GSO || {};
window.GSO.Notifications = window.GSO.Notifications || (function(){
  var timers = {};

  function role(){ return String(window.currentUserRole || '').trim().toUpperCase(); }
  function hasBell(){ return $('#rtNotifNavItem').length > 0; }

  function setBadge(n){
    var $b = $('#rtNotifBadge');
    var $link = $('#rtNotifBellLink');
    var count = Math.max(0, Number(n || 0) || 0);
    if (!$b.length) { return; }
    if (count <= 0) {
      $b.hide().text('0');
      if ($link.length) { $link.removeClass('gso-has-unread'); }
    } else {
      $b.text(String(count)).show();
      if ($link.length) { $link.addClass('gso-has-unread'); }
    }
  }

  function setVisible(isVisible){
    if (!hasBell()) { return; }
    $('#rtNotifNavItem').toggle(!!isVisible);
  }

  function renderList(items){
    var $list = $('#rtNotifList');
    if (!$list.length) { return; }
    $list.empty();

    if (!items || !items.length) {
      $list.append('<span class="dropdown-item text-muted">No notifications</span>');
      return;
    }

    items.slice(0, 10).forEach(function(it){
      var ctrlRaw = String(it.control_number || '').trim();
      var ctrlUrl = encodeURIComponent(ctrlRaw);
      var emp = $('<div>').text(it.emp_name || '').html();
      var ctrl = $('<div>').text(ctrlRaw).html();
      var type = $('<div>').text(it.clearance_name || '').html();
      var when = $('<div>').text(it.created_at_display || '').html();
      $list.append(
        '<a class="dropdown-item rt-notif-open" href="../services/clearance.php?pc=' + ctrlUrl + '" data-control="' + ctrl + '">' +
          '<div class="d-flex justify-content-between" style="gap:12px;">' +
            '<div class="text-truncate">' +
              '<div class="font-weight-bold text-truncate">' + emp + '</div>' +
              '<div class="small font-weight-bold">Clearance ready to print</div>' +
              '<div class="small text-muted text-truncate">' + type + ' • CTRL ' + ctrl + '</div>' +
            '</div>' +
            '<div class="small text-muted" style="white-space:nowrap;">' + when + '</div>' +
          '</div>' +
        '</a>'
      );
    });
  }

  // Source: Property Clearance READY notifications
  function startPcReadyPolling(){
    if (!hasBell()) { return; }
    var r = role();
    if (r !== 'CLEARANCE-ADMIN') { setVisible(false); return; }

    setVisible(true);
    $('#rtNotifHeader').text('Clearance Notifications');

    // Task-style notifications: do not mark as read.
    // The badge/animation should persist until the clearance is printed/released
    // (i.e., removed from the READY feed by the backend).

    function poll(){
      if (document.hidden) { return; }
      $.ajax({
        url: '../auth/auth.php',
        type: 'GET',
        cache: false,
        dataType: 'json',
        data: { fetch_pc_ready_notifications: 1, _ts: Date.now() },
        success: function(resp){
          if (!resp || resp.status !== 200 || !resp.data) { return; }
          var items = Array.isArray(resp.data.items) ? resp.data.items : [];

          // Badge equals pending READY-to-print items.
          setBadge(items.length);
          renderList(items);
        }
      });
    }

    var timerName = 'pcReadyTimer';
    if (timers[timerName]) {
      try { clearInterval(timers[timerName]); } catch (e) {}
      timers[timerName] = null;
    }
    poll();
    timers[timerName] = setInterval(poll, 7000);
    document.addEventListener('visibilitychange', function(){
      if (!document.hidden) { poll(); }
    });
  }

  return {
    startPcReadyPolling: startPcReadyPolling
  };
})();

// Presence (online/offline) + session guard (reusable)
// - Sends a throttled heartbeat to the backend so the admin panel can show accurate status
// - Keeps the existing inactivity logout behavior by polling session_destroy.php
window.GSO = window.GSO || {};

window.GSO.Presence = window.GSO.Presence || (function(){
  var pingBusy = false;
  var lastPingMs = 0;
  var PING_COOLDOWN_MS = 2500;
  var HEARTBEAT_INTERVAL_MS = 25000;
  var heartbeatTimer = null;

  function canPing(){
    var now = Date.now();
    return !pingBusy && (now - lastPingMs) >= PING_COOLDOWN_MS;
  }

  function ping(){
    if (!canPing()) { return; }
    pingBusy = true;
    lastPingMs = Date.now();
    $.ajax({
      url: '../auth/auth.php',
      type: 'GET',
      cache: false,
      dataType: 'json',
      data: { presence_heartbeat: 1, _ts: Date.now() },
      complete: function(){ pingBusy = false; }
    });
  }

  function start(){
    if (heartbeatTimer) { return; }
    ping();
    heartbeatTimer = setInterval(function(){
      ping();
    }, HEARTBEAT_INTERVAL_MS);

    document.addEventListener('visibilitychange', function(){
      if (!document.hidden) { ping(); }
    });
  }

  return { start: start, ping: ping };
})();

window.GSO.SessionGuard = window.GSO.SessionGuard || (function(){
  var inited = false;

  function init(){
    if (inited) { return; }
    inited = true;

    // Heartbeat on common user interactions.
    ['click','keydown','mousemove','scroll','input','focus'].forEach(function(evt){
      window.addEventListener(evt, function(){
        if (window.GSO && window.GSO.Presence) { window.GSO.Presence.ping(); }
      }, { passive: true });
    });

    if (window.GSO && window.GSO.Presence) { window.GSO.Presence.start(); }

    // Ask server if session should be destroyed (server enforces timeout)
    setInterval(function(){
      $.ajax({
        url: '../config/session_destroy.php',
        type: 'GET',
        cache: false,
        data: { _ts: Date.now() },
        success: function(html){
          var $target = $('#destroy');
          if ($target.length) { $target.html(html); }
        }
      });
    }, 5000);
  }

  return { init: init };
})();

// Admin panel: poll presence status and update ONLINE/OFFLINE badges.
window.GSO.AdminPresencePanel = window.GSO.AdminPresencePanel || (function(){
  var timer = null;

  function renderBadge(isOnline){
    return isOnline
      ? '<span class="badge badge-success">ONLINE</span>'
      : '<span class="badge badge-dark">OFFLINE</span>';
  }

  function apply(items){
    if (!items || !items.length) { return; }
    var byId = {};
    items.forEach(function(it){ byId[String(it.admin_id)] = Number(it.is_online) === 1; });
    $('.presenceStatus[data-admin-id]').each(function(){
      var id = String($(this).data('adminId'));
      if (Object.prototype.hasOwnProperty.call(byId, id)) {
        $(this).html(renderBadge(byId[id]));
      }
    });
  }

  function poll(){
    $.ajax({
      url: '../auth/auth.php',
      type: 'GET',
      cache: false,
      dataType: 'json',
      data: { fetch_presence_status: 1, _ts: Date.now() },
      success: function(resp){
        if (!resp || resp.status !== 200 || !resp.data) { return; }
        apply(Array.isArray(resp.data.items) ? resp.data.items : []);
      }
    });
  }

  function start(){
    if (timer) { return; }
    if (!$('.presenceStatus[data-admin-id]').length) { return; }
    poll();
    timer = setInterval(function(){
      if (!document.hidden) { poll(); }
    }, 10000);
  }

  return { start: start };
})();

window.GSO.DashboardMetrics = window.GSO.DashboardMetrics || (function(){
  function isDashboardPage(){
    return $('.gso-dashboard').length > 0;
  }

  function parseNumberFromText(text){
    if (text == null) { return null; }
    var cleaned = String(text).replace(/[^0-9.\-]/g, '');
    if (!cleaned || cleaned === '-' || cleaned === '.' || cleaned === '-.') { return null; }
    var value = Number(cleaned);
    return Number.isFinite(value) ? value : null;
  }

  function prefersReducedMotion(){
    try {
      return !!(window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches);
    } catch (e) {
      return false;
    }
  }

  function easeOutSpring(t){
    return 1 - (Math.exp(-4.5 * t) * Math.cos(6 * t));
  }

  function animateValue($element, targetValue, options){
    var opts = options || {};
    var duration = Number(opts.duration || 1800);
    var formatter = typeof opts.formatter === 'function' ? opts.formatter : function(value){ return String(value); };
    var decimals = Number.isFinite(opts.decimals) ? opts.decimals : 0;

    if (!$element || !$element.length) { return; }
    if (!Number.isFinite(targetValue)) {
      $element.text(opts.fallbackText != null ? String(opts.fallbackText) : 'N/A');
      return;
    }

    if (prefersReducedMotion()) {
      $element.text(formatter(decimals > 0 ? Number(targetValue.toFixed(decimals)) : Math.round(targetValue)));
      return;
    }

    var currentAnimation = $element.data('gsoAnim');
    if (currentAnimation && currentAnimation.cancel) {
      currentAnimation.cancel();
    }

    var fromValue = parseNumberFromText($element.text());
    if (!Number.isFinite(fromValue)) {
      fromValue = 0;
    }
    var direction = targetValue >= fromValue ? 1 : -1;
    var animationFrameId = 0;
    var isCancelled = false;
    var startTime = 0;

    function cancel(){
      isCancelled = true;
      if (animationFrameId) {
        cancelAnimationFrame(animationFrameId);
      }
      $element.removeClass('gso-counting');
    }

    $element.data('gsoAnim', { cancel: cancel });
    $element.removeClass('gso-counting');
    void $element[0].offsetWidth;
    $element.addClass('gso-counting');

    function step(timestamp){
      if (isCancelled) { return; }
      if (!startTime) {
        startTime = timestamp;
      }

      var progress = Math.min(1, (timestamp - startTime) / duration);
      var easedProgress = Math.min(1, Math.max(0, easeOutSpring(progress)));
      var currentValue = fromValue + (targetValue - fromValue) * easedProgress;
      var shownValue = decimals > 0
        ? Number(currentValue.toFixed(decimals))
        : (direction >= 0 ? Math.floor(currentValue) : Math.ceil(currentValue));

      $element.text(formatter(shownValue));

      if (progress < 1) {
        animationFrameId = requestAnimationFrame(step);
        return;
      }

      var finalValue = decimals > 0 ? Number(targetValue.toFixed(decimals)) : Math.round(targetValue);
      $element.text(formatter(finalValue));
      $element.removeData('gsoAnim');
      $element.removeClass('gso-counting');
    }

    animationFrameId = requestAnimationFrame(step);
  }

  function setMetric(metricKey, value, options){
    var $targets = $('[data-metric="' + metricKey + '"]');
    if (!$targets.length) { return; }

    if (typeof value === 'string' && parseNumberFromText(value) == null) {
      $targets.text(value);
      return;
    }

    var numericValue = typeof value === 'number' ? value : parseNumberFromText(value);
    $targets.each(function(){
      animateValue($(this), Number(numericValue), options);
    });
  }

  function formatCurrency(value){
    try {
      return new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency: 'PHP',
        maximumFractionDigits: 2
      }).format(Number(value || 0));
    } catch (e) {
      return '₱ ' + Number(value || 0).toFixed(2);
    }
  }

  function init(){
    if (!isDashboardPage()) { return; }

    $.ajax({
      url: '../auth/fetch_dashboard_metrics.php',
      type: 'GET',
      dataType: 'json',
      success: function(resp){
        if (!resp) { return; }

        setMetric('gftotal_currency', resp.gftotal ?? 0, { decimals: 2, formatter: formatCurrency, duration: 2200 });
        setMetric('seftotal_currency', resp.seftotal ?? 0, { decimals: 2, formatter: formatCurrency, duration: 2200 });
        setMetric('trust_fund_total_currency', resp.trust_fund_total ?? 0, { decimals: 2, formatter: formatCurrency, duration: 2200 });
        setMetric('donation_total_currency', resp.donation_total ?? 0, { decimals: 2, formatter: formatCurrency, duration: 2200 });
        setMetric('new_purchase_total_currency', resp.new_purchase_total ?? 0, { decimals: 2, formatter: formatCurrency, duration: 2200 });

        setMetric('admin_count', resp.admin_count ?? 0, { duration: 1800 });
        setMetric('desktop_count', resp.desktop_count ?? 0, { duration: 1800 });
        setMetric('laptop_count', resp.laptop_count ?? 0, { duration: 1800 });
        setMetric('aircon_count', resp.aircon_count ?? 0, { duration: 1800 });
        setMetric('vehicle_count', resp.vehicle_count ?? 0, { duration: 1800 });
        setMetric('printer_count', resp.printer_count ?? 0, { duration: 1800 });
        setMetric('server_count', resp.server_count ?? 0, { duration: 1800 });
        setMetric('machinery_count', resp.machinery_count ?? 0, { duration: 1800 });
        setMetric('furniture_count', resp.furniture_count ?? 0, { duration: 1800 });

        setMetric('infrastructure_gf_currency', resp.infrastructure_gf_total ?? 0, { decimals: 2, formatter: formatCurrency, duration: 2200 });
        setMetric('infrastructure_sef_currency', resp.infrastructure_sef_total ?? 0, { decimals: 2, formatter: formatCurrency, duration: 2200 });
        setMetric('land_total_currency', resp.land_total ?? 0, { decimals: 2, formatter: formatCurrency, duration: 2200 });
      }
    });
  }

  return { init: init };
})();

$(function(){
  if (window.GSO && window.GSO.DashboardMetrics) { window.GSO.DashboardMetrics.init(); }
});

window.GSO.DashboardClearanceAnalytics = window.GSO.DashboardClearanceAnalytics || (function(){
  var charts = { monthly: null };

  function hasWidget(){
    return $('#dashboardClearanceMonthlyChart').length > 0;
  }

  function destroyChart(chart){
    try {
      if (chart && typeof chart.destroy === 'function') {
        chart.destroy();
      }
    } catch (e) {}
  }

  function palette(index){
    var colors = [
      '#007bff', '#28a745', '#ffc107', '#dc3545', '#17a2b8', '#fd7e14',
      '#6f42c1', '#20c997', '#6610f2', '#6c757d', '#e83e8c', '#343a40'
    ];
    return colors[index % colors.length];
  }

  function setSummary(text){
    $('#dashboardClearanceSummary').text(String(text || ''));
  }

  function render(payload){
    if (!payload) { return; }

    var months = Array.isArray(payload.months) ? payload.months : ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
    var types = Array.isArray(payload.types) ? payload.types : [];
    var series = payload.series || {};
    var totalReleased = Number(payload.total_released || 0) || 0;
    var year = Number(payload.year || new Date().getFullYear()) || new Date().getFullYear();

    if (!types.length || totalReleased <= 0) {
      setSummary('No released clearances found for ' + year + '.');
    } else {
      setSummary('Monthly released clearances for ' + year + '.');
    }

    if (!window.Chart) { return; }

    var monthlyCanvas = $('#dashboardClearanceMonthlyChart').get(0);
    if (monthlyCanvas && monthlyCanvas.getContext) {
      destroyChart(charts.monthly);
      charts.monthly = new Chart(monthlyCanvas.getContext('2d'), {
        type: 'bar',
        data: {
          labels: months,
          datasets: types.map(function(typeName, index){
            return {
              label: typeName,
              data: Array.isArray(series[typeName]) ? series[typeName] : new Array(12).fill(0),
              backgroundColor: palette(index),
              borderColor: palette(index),
              borderWidth: 1
            };
          })
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          scales: {
            xAxes: [{ stacked: true }],
            yAxes: [{ stacked: true, ticks: { beginAtZero: true, precision: 0 } }]
          },
          legend: { display: true, position: 'bottom' },
          tooltips: { mode: 'index', intersect: false }
        }
      });
    }

  }

  function init(){
    if (!hasWidget()) { return; }

    $.ajax({
      url: '../auth/auth.php',
      type: 'GET',
      cache: false,
      dataType: 'json',
      data: {
        fetch_clearance_statistics: 1,
        year: new Date().getFullYear(),
        _ts: Date.now()
      },
      success: function(resp){
        if (typeof resp === 'string') {
          try { resp = JSON.parse(resp); } catch (e) { resp = null; }
        }
        if (!resp || resp.status !== 200 || !resp.data) {
          setSummary('Unable to load clearance analytics.');
          return;
        }
        render(resp.data);
      },
      error: function(){
        setSummary('Server error while loading clearance analytics.');
      }
    });
  }

  return { init: init };
})();

$(function(){
  if (window.GSO && window.GSO.DashboardClearanceAnalytics) { window.GSO.DashboardClearanceAnalytics.init(); }
});

// Select2 + datalist helpers (reusable)
window.GSO.UI = window.GSO.UI || {};

window.GSO.UI.initDeptDatalistAutocomplete = function(opts){
  opts = opts || {};
  var $select = $(opts.select || '#dept');
  var $input = $(opts.input || '#deptSearch');
  var $list = $(opts.list || '#deptDatalist');
  var $modal = opts.modal ? $(opts.modal) : $();

  if(!$select.length || !$input.length || !$list.length){ return; }
  if($input.data('gsoDeptAutocompleteInit')){ return; }
  $input.data('gsoDeptAutocompleteInit', true);

  function exactDeptCodeByName(name){
    var target = (name || '').trim().toLowerCase();
    if(!target){ return null; }
    var code = null;
    $select.find('option').each(function(){
      var v = $(this).attr('value');
      if(!v){ return; }
      var n = $(this).text().trim().toLowerCase();
      if(n === target){ code = v; return false; }
    });
    return code;
  }

  function populate(){
    $list.empty();
    $select.find('option').each(function(){
      var v = $(this).attr('value');
      var n = $(this).text().trim();
      if(!v){ return; }
      $('<option>').attr('value', n).appendTo($list);
    });
  }

  populate();

  var wasSetFromSelect = false;
  var clearedForRetype = false;

  $select.on('change.gsoDeptAutocomplete', function(){
    var code = $select.val();
    var name = $select.find('option:selected').text().trim();
    $input.val(code ? name : '');
    wasSetFromSelect = !!code;
    clearedForRetype = false;
  });

  $input.on('input.gsoDeptAutocomplete', function(){
    if(!clearedForRetype && wasSetFromSelect && $input.val()){
      $input.val('');
      clearedForRetype = true;
      wasSetFromSelect = false;
    }
    if(!$input.val().trim() && $select.val()){
      $select.val('').trigger('change');
    }
  });

  $input.on('keydown.gsoDeptAutocomplete', function(e){
    if(clearedForRetype || !wasSetFromSelect){ return; }
    var k = e.key;
    if(['Shift','Control','Alt','Meta','Tab'].includes(k)){ return; }
    $input.val('');
    clearedForRetype = true;
    wasSetFromSelect = false;
    if($select.val()){
      $select.val('').trigger('change');
    }
  });

  $input.on('change.gsoDeptAutocomplete', function(){
    var code = exactDeptCodeByName($input.val());
    if(code && $select.val() !== code){
      $select.val(code).trigger('change');
    }
  });

  function syncOnOpen(){
    var code = $select.val();
    var name = $select.find('option:selected').text().trim();
    $input.val(code ? name : '');
    clearedForRetype = false;
    wasSetFromSelect = !!code;
  }

  if($modal.length){
    $modal.on('shown.bs.modal.gsoDeptAutocomplete', function(){
      syncOnOpen();
      setTimeout(function(){ try { $input.trigger('focus'); } catch(e) {} }, 0);
    });
    $modal.on('hidden.bs.modal.gsoDeptAutocomplete', function(){
      $input.val('');
      clearedForRetype = false;
      wasSetFromSelect = false;
    });
  } else {
    syncOnOpen();
  }
};

window.GSO.UI.initPcEmployeeSelect2 = function(){
  if(!$.fn.select2){ return; }
  if(!$('#pc_form').length){ return; }
  var $sel = $('#employee');
  if(!$sel.length){ return; }
  if($sel.hasClass('select2-hidden-accessible')){ return; }

  var $parent = $('#addClearanceModal');
  $sel.select2({
    theme: 'bootstrap4',
    width: '100%',
    dropdownParent: $parent.length ? $parent : $(document.body),
    placeholder: '-SELECT-',
    allowClear: true
  });
};

// Global deep-link + click support for Property Clearance notifications.
// Works even if the clearance DataTable was already initialized.
(function setupPcNotifDeepLinkSupport(){
  function getPcParam(){
    try {
      var params = new URLSearchParams(window.location.search || '');
      return (params.get('pc') || '').trim();
    } catch (e) {
      return '';
    }
  }

  function clearPcParam(){
    try {
      var params2 = new URLSearchParams(window.location.search || '');
      if (!params2.has('pc')) { return; }
      params2.delete('pc');
      var newUrl = window.location.pathname + (params2.toString() ? ('?' + params2.toString()) : '') + (window.location.hash || '');
      if (window.history && window.history.replaceState) {
        window.history.replaceState({}, document.title, newUrl);
      }
    } catch (e) {}
  }

  // If user is already on clearance.php, open the modal without navigating.
  $(document).on('click', 'a.rt-notif-open', function(e){
    var control = String($(this).attr('data-control') || '').trim();
    if (!control) { return; }
    if (typeof window.openPcEditModal === 'function') {
      e.preventDefault();
      window.openPcEditModal(control);
      // Close the dropdown reliably (Bootstrap 4)
      try {
        var $dd = $(this).closest('.dropdown');
        $dd.removeClass('show');
        $dd.find('.dropdown-menu').removeClass('show');
        $dd.find('[data-toggle="dropdown"]').attr('aria-expanded', 'false');
      } catch (err) {}
    }
  });

  // On page load with ?pc=, wait for openPcEditModal to exist then open once.
  $(function(){
    var ctrl = getPcParam();
    if (!ctrl) { return; }

    var attempts = 0;
    var maxAttempts = 20; // ~5 seconds
    var timer = setInterval(function(){
      attempts++;
      if (typeof window.openPcEditModal === 'function') {
        try { window.openPcEditModal(ctrl); } catch (e) {}
        clearPcParam();
        clearInterval(timer);
        return;
      }
      if (attempts >= maxAttempts) {
        clearInterval(timer);
      }
    }, 250);
  });
})();

// Clearance statistics (services/clearance-statistic.php)
// Renders:
// - Stacked bar: released clearances per month, grouped by clearance type
// - Doughnut: share by clearance type for the selected year
window.GSO.ClearanceStats = window.GSO.ClearanceStats || (function(){
  var charts = { monthly: null, byType: null };
  var lastPayload = null;
  var state = {
    view: 'stacked',
    year: null,
    from: '',
    to: ''
  };
  var detailsTable = null;

  function hasPage(){ return $('#clearanceStatsPage').length > 0; }
  function role(){ return String(window.currentUserRole || '').trim().toUpperCase(); }
  function canView(){ return ['SYSTEM-ADMIN', 'CLEARANCE-ADMIN'].indexOf(role()) !== -1; }

  function palette(i){
    var colors = [
      '#007bff','#28a745','#ffc107','#dc3545','#6f42c1','#17a2b8',
      '#fd7e14','#20c997','#6610f2','#6c757d','#e83e8c','#343a40'
    ];
    return colors[i % colors.length];
  }

  function destroyChart(ch){
    try { if (ch && typeof ch.destroy === 'function') { ch.destroy(); } } catch(e) {}
  }

  function setSummary(text){
    var $s = $('#csSummary');
    if ($s.length) { $s.text(String(text || '')); }
  }

  function setKpis(kpis){
    kpis = kpis || {};
    $('#csKpiTotal').text(String(Number(kpis.total || 0) || 0));
    $('#csKpiThisMonth').text(String(Number(kpis.this_month || 0) || 0));
    $('#csKpiLastMonth').text(String(Number(kpis.last_month || 0) || 0));
    $('#csKpiPending').text(String(Number(kpis.pending || 0) || 0));
  }

  function getFilters(){
    var $year = $('#csYear');
    var year = Number($year.val() || '') || Number($('#clearanceStatsPage').attr('data-default-year')) || new Date().getFullYear();
    var from = String($('#csFrom').val() || '').trim();
    var to = String($('#csTo').val() || '').trim();
    var okDate = function(s){ return /^\d{4}-\d{2}-\d{2}$/.test(s); };
    if (!(okDate(from) && okDate(to))) {
      from = '';
      to = '';
    }
    state.year = year;
    state.from = from;
    state.to = to;
    return { year: year, from: from, to: to };
  }

  function monthLabel(idx){
    var months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
    return months[idx] || '';
  }

  function renderTotalsTable(types, totalsByType, totalReleased){
    var $wrap = $('#csTypeTotals');
    if (!$wrap.length) { return; }
    $wrap.empty();

    if (!types || !types.length) {
      $wrap.append($('<div class="text-muted" style="font-size: 13px;"></div>').text('No category totals to display.'));
      return;
    }

    // Sort by total desc for readability
    var rows = types.map(function(t){
      return { type: t, total: Number(totalsByType[t] || 0) || 0 };
    }).sort(function(a,b){ return b.total - a.total; });

    var $table = $('<table class="table table-sm table-bordered mb-0"></table>');
    var $thead = $('<thead class="thead-light"></thead>');
    $thead.append('<tr><th>Clearance Category</th><th class="text-right" style="width: 110px;">Total</th></tr>');
    $table.append($thead);

    var $tbody = $('<tbody></tbody>');
    rows.forEach(function(r){
      var $tr = $('<tr></tr>');
      $tr.append($('<td></td>').text(r.type));
      $tr.append($('<td class="text-right"></td>').text(String(r.total)));
      $tbody.append($tr);
    });
    $table.append($tbody);

    var $tfoot = $('<tfoot></tfoot>');
    var $ttr = $('<tr></tr>');
    $ttr.append('<th class="text-right">Grand Total</th>');
    $ttr.append($('<th class="text-right"></th>').text(String(Number(totalReleased || 0) || 0)));
    $tfoot.append($ttr);
    $table.append($tfoot);

    $wrap.append($table);
  }

  function render(payload){
    if (!payload) { return; }
    lastPayload = payload;
    var months = payload.months || ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
    var types = Array.isArray(payload.types) ? payload.types : [];
    var series = payload.series || {};
    var totalsByType = payload.totals_by_type || {};
    var totalReleased = Number(payload.total_released || 0) || 0;

    setKpis(payload.kpis || {});

    if (!types.length || totalReleased <= 0) {
      setSummary('No released clearances found for ' + (payload.year || '') + '.');
    }

    // Monthly stacked bar
    var $bar = $('#csMonthlyByTypeChart');
    if ($bar.length && window.Chart) {
      destroyChart(charts.monthly);
      var barCanvas = $bar.get(0);
      if (!barCanvas || !barCanvas.getContext) { return; }
      var barCtx = barCanvas.getContext('2d');

      var datasets;
      if (state.view === 'total') {
        var totals = new Array(12).fill(0);
        types.forEach(function(t){
          var arr = Array.isArray(series[t]) ? series[t] : new Array(12).fill(0);
          for (var i=0;i<12;i++){ totals[i] += Number(arr[i] || 0) || 0; }
        });
        datasets = [{
          label: 'Released',
          data: totals,
          backgroundColor: '#007bff',
          borderColor: '#007bff',
          borderWidth: 1
        }];
      } else {
        datasets = types.map(function(t, idx){
          var data = Array.isArray(series[t]) ? series[t] : new Array(12).fill(0);
          return {
            label: t,
            data: data,
            backgroundColor: palette(idx),
            borderColor: palette(idx),
            borderWidth: 1
          };
        });
      }

      charts.monthly = new Chart(barCtx, {
        type: 'bar',
        data: {
          labels: months,
          datasets: datasets
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          scales: {
            xAxes: [{ stacked: state.view !== 'total' }],
            yAxes: [{ stacked: state.view !== 'total', ticks: { beginAtZero: true, precision: 0 } }]
          },
          legend: { display: true, position: 'bottom' },
          tooltips: { mode: 'index', intersect: false }
        }
      });

      // Drilldown click
      try {
        barCanvas.onclick = function(evt){
          if (!charts.monthly) { return; }
          var active = charts.monthly.getElementsAtEventForMode(evt, 'nearest', { intersect: true }, true);
          if (!active || !active.length) { return; }
          var p = active[0];
          var monthIdx = p._index;
          var datasetIdx = p._datasetIndex;
          var typeName = (state.view === 'total') ? '' : (charts.monthly.data.datasets[datasetIdx] ? charts.monthly.data.datasets[datasetIdx].label : '');
          openDetails({
            month: monthIdx + 1,
            type: typeName,
            title: 'Released - ' + monthLabel(monthIdx) + (typeName ? (' (' + typeName + ')') : '')
          });
        };
      } catch(e) {}
    }

    // Released per clearance type (category)
    var $byType = $('#csReleasedPerTypeChart');
    if ($byType.length && window.Chart) {
      destroyChart(charts.byType);
      var byTypeCanvas = $byType.get(0);
      if (!byTypeCanvas || !byTypeCanvas.getContext) { return; }
      var byTypeCtx = byTypeCanvas.getContext('2d');

      var labels = types.slice();
      var data = labels.map(function(t){ return Number(totalsByType[t] || 0) || 0; });
      var colors = labels.map(function(_, idx){ return palette(idx); });

      charts.byType = new Chart(byTypeCtx, {
        type: 'horizontalBar',
        data: {
          labels: labels,
          datasets: [{
            label: 'Released',
            data: data,
            backgroundColor: colors,
            borderColor: colors,
            borderWidth: 1
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          legend: { display: false },
          scales: {
            xAxes: [{ ticks: { beginAtZero: true, precision: 0 } }],
            yAxes: [{ ticks: { autoSkip: false } }]
          },
          tooltips: { mode: 'nearest', intersect: true }
        }
      });

      // Drilldown click: by category
      try {
        byTypeCanvas.onclick = function(evt){
          if (!charts.byType) { return; }
          var active = charts.byType.getElementsAtEventForMode(evt, 'nearest', { intersect: true }, true);
          if (!active || !active.length) { return; }
          var p = active[0];
          var idx = p._index;
          var t = charts.byType.data.labels[idx] || '';
          if (!t) { return; }
          openDetails({
            month: 0,
            type: t,
            title: 'Released - ' + t
          });
        };
      } catch(e) {}
    }

    renderTotalsTable(types, totalsByType, totalReleased);

    if (totalReleased > 0) {
      setSummary('');
    }
  }

  function fetchAndRender(year){
    var f = getFilters();
    var y = Number(f.year) || new Date().getFullYear();
    setSummary('Loading…');
    return $.ajax({
      url: '../auth/auth.php',
      type: 'GET',
      cache: false,
      data: {
        fetch_clearance_statistics: 1,
        year: y,
        from: f.from || undefined,
        to: f.to || undefined,
        _ts: Date.now()
      },
      success: function(resp){
        if (typeof resp === 'string') {
          try { resp = JSON.parse(resp); } catch (e) { resp = null; }
        }
        if (!resp || resp.status !== 200 || !resp.data) {
          setSummary('Unable to load statistics.');
          return;
        }
        render(resp.data);
      },
      error: function(){
        setSummary('Server error while loading statistics.');
      }
    });
  }

  function buildExportUrl(kind, opts){
    opts = opts || {};
    var f = getFilters();
    var q = {
      year: f.year,
      from: f.from || '',
      to: f.to || '',
      month: opts.month || 0,
      type: opts.type || ''
    };
    var params = [];
    Object.keys(q).forEach(function(k){
      if (q[k] === '' || q[k] === null || typeof q[k] === 'undefined') { return; }
      params.push(encodeURIComponent(k) + '=' + encodeURIComponent(String(q[k])));
    });
    if (kind === 'summary') {
      params.unshift('export_clearance_statistics_csv=1');
    } else {
      params.unshift('export_clearance_released_details_csv=1');
    }
    return '../auth/auth.php?' + params.join('&');
  }

  function openDetails(opts){
    opts = opts || {};
    var month = Number(opts.month || 0) || 0;
    var type = String(opts.type || '').trim();
    var title = String(opts.title || 'Released Clearances');
    $('#csDetailsTitle').text(title);

    // Build ajax URL for the table
    var f = getFilters();
    var ajaxData = {
      fetch_clearance_released_details: 1,
      year: f.year,
      _ts: Date.now()
    };
    if (f.from && f.to) { ajaxData.from = f.from; ajaxData.to = f.to; }
    if (month >= 1 && month <= 12) { ajaxData.month = month; }
    if (type) { ajaxData.type = type; }

    $('#csDetailsModal').modal('show');

    // Init once
    if (!detailsTable) {
      detailsTable = $('#csDetailsTable').DataTable({
        responsive: true,
        lengthChange: false,
        autoWidth: false,
        processing: true,
        dom: "<'row'<'col-sm-12 col-md-6'B><'col-sm-12 col-md-6'f>>" +
             "<'row'<'col-sm-12'tr>>" +
             "<'row'<'col-sm-12 col-md-5'i><'col-sm-12 col-md-7'p>>",
        buttons: [
          { extend: 'excelHtml5', title: 'Released Clearances' },
          { extend: 'csvHtml5', title: 'Released Clearances' },
          { extend: 'print', title: 'Released Clearances' },
          {
            text: 'Download CSV',
            className: 'btn btn-sm btn-primary',
            action: function(){
              window.location.href = buildExportUrl('details', { month: month, type: type });
            }
          }
        ],
        ajax: {
          url: '../auth/auth.php',
          type: 'GET',
          cache: false,
          data: function(d){
            Object.keys(ajaxData).forEach(function(k){ d[k] = ajaxData[k]; });
            return d;
          },
          dataSrc: function(resp){
            if (typeof resp === 'string') {
              try { resp = JSON.parse(resp); } catch (e) { resp = null; }
            }
            if (!resp || resp.status !== 200 || !resp.data || !Array.isArray(resp.data.items)) { return []; }
            return resp.data.items;
          }
        },
        columns: [
          { data: 'control_number' },
          { data: 'emp_name' },
          { data: 'clearance_name' },
          { data: 'release_date_display' }
        ]
      });
    } else {
      // Update ajax parameters and reload
      detailsTable.ajax.params = function(){ return ajaxData; };
      detailsTable.ajax.reload();
    }
  }

  function init(){
    if (!hasPage()) { return; }
    if (!canView()) { return; }

    // Date pickers
    if ($.fn.datepicker) {
      $('#csFrom, #csTo').datepicker({
        autoclose: true,
        format: 'yyyy-mm-dd',
        todayHighlight: true
      });
    }

    var $year = $('#csYear');
    var defaultYear = Number($('#clearanceStatsPage').attr('data-default-year')) || new Date().getFullYear();
    if ($year.length && !$year.val()) { $year.val(String(defaultYear)); }

    state.view = String($('input[name="csView"]:checked').val() || 'stacked');
    fetchAndRender($year.val() || defaultYear);

    $year.off('change.cs').on('change.cs', function(){ fetchAndRender($(this).val()); });
    $('#csFrom, #csTo').off('change.cs').on('change.cs', function(){ fetchAndRender($year.val() || defaultYear); });
    $('#csClearRange').off('click.cs').on('click.cs', function(){
      $('#csFrom').val('');
      $('#csTo').val('');
      fetchAndRender($year.val() || defaultYear);
    });

    $('#csViewToggle input[name="csView"]').off('change.cs').on('change.cs', function(){
      state.view = String($(this).val() || 'stacked');
      if (lastPayload) { render(lastPayload); }
    });

    $('#csExportSummary').off('click.cs').on('click.cs', function(){
      window.location.href = buildExportUrl('summary');
    });
  }

  // Don't auto-init here; the page will call init().
  return { init: init };
})();

$(function(){ 
  if (window.GSO_LIGHT_PAGE) { return; }

  function initTooltips(scope) {
    if (!$.fn || typeof $.fn.tooltip !== 'function') { return; }
    var $scope = scope ? $(scope) : $(document);
    // Avoid duplicated tooltip instances on redraw
    $scope.find('[data-toggle="tooltip"]').tooltip('dispose').tooltip({
      container: 'body',
      boundary: 'window'
    });
  }


  if ($('#example1').length && $.fn.dataTable && !$.fn.dataTable.isDataTable('#example1')) {
    var path = String((window.location && window.location.pathname) || '');
    var isGeneralFundDepartmentPage = /general-fund-department\.php$/i.test(path);
    var isSefInstitutionPage = /sef-institution\.php$/i.test(path);

    var dtOpts = {
      responsive: true,
      lengthChange: false
    };

    if (isGeneralFundDepartmentPage || isSefInstitutionPage) {
      dtOpts.autoWidth = false;
      dtOpts.dom = "<'row'<'col-sm-12 col-md-6'B><'col-sm-12 col-md-6'f>>" +
                  "<'row'<'col-sm-12'tr>>" +
                  "<'row'<'col-sm-12 col-md-5'i><'col-sm-12 col-md-7'p>>";
      dtOpts.buttons = [
        {
          text: 'Export Inventory',
          className: 'btn btn-primary',
          action: function(){
            if (isGeneralFundDepartmentPage) {
              var $m = $('#gfInventoryExportModal');
              if ($m.length) {
                $m.modal('show');
                return;
              }
              window.location.href = '../auth/auth.php?export_gf_inventory=1&category=PAR';
              return;
            }

            if (isSefInstitutionPage) {
              var $m2 = $('#sefInventoryExportModal');
              if ($m2.length) {
                $m2.modal('show');
                return;
              }
              window.location.href = '../auth/auth.php?export_sef_inventory=1&category=PAR';
            }
          }
        }
      ];
    }

    $("#example1").DataTable(dtOpts);
  }

  initTooltips();

  // General Fund Department export modal handler
  (function bindGfExportModal(){
    var path = String((window.location && window.location.pathname) || '');
    if (!/general-fund-department\.php$/i.test(path)) { return; }
    var $btn = $('#gfExportConfirm');
    if (!$btn.length) { return; }

    var $printBtn = $('#gfPrintConfirm');

    $btn.off('click.gsoGfExport').on('click.gsoGfExport', function(){
      var category = String($('#gfExportCategory').val() || '').trim().toUpperCase();
      if (category !== 'PAR' && category !== 'ICS' && category !== 'ALL') { category = 'PAR'; }

      var dept = String($('#gfExportDept').val() || '').trim();
      var url = '../auth/auth.php?export_gf_inventory=1&category=' + encodeURIComponent(category);
      if (dept) {
        url += '&dept=' + encodeURIComponent(dept);
      }

      try { $('#gfInventoryExportModal').modal('hide'); } catch (_) {}
      window.location.href = url;
    });

    if ($printBtn.length) {
      $printBtn.off('click.gsoGfPrint').on('click.gsoGfPrint', function(){
        var category = String($('#gfExportCategory').val() || '').trim().toUpperCase();
        if (category !== 'PAR' && category !== 'ICS' && category !== 'ALL') { category = 'PAR'; }

        var dept = String($('#gfExportDept').val() || '').trim();
        var url = '../auth/auth.php?print_gf_inventory=1&category=' + encodeURIComponent(category);
        if (dept) {
          url += '&dept=' + encodeURIComponent(dept);
        }

        try { $('#gfInventoryExportModal').modal('hide'); } catch (_) {}
        try {
          window.open(url, '_blank');
        } catch (e) {
          window.location.href = url;
        }
      });
    }
  })();

  // SEF Institution export/print modal handler
  (function bindSefExportModal(){
    var path = String((window.location && window.location.pathname) || '');
    if (!/sef-institution\.php$/i.test(path)) { return; }

    var $btn = $('#sefExportConfirm');
    if (!$btn.length) { return; }

    var $printBtn = $('#sefPrintConfirm');

    $btn.off('click.gsoSefExport').on('click.gsoSefExport', function(){
      var category = String($('#sefExportCategory').val() || '').trim().toUpperCase();
      if (category !== 'PAR' && category !== 'ICS' && category !== 'ALL') { category = 'PAR'; }

      var dept = String($('#sefExportDept').val() || '').trim();
      var url = '../auth/auth.php?export_sef_inventory=1&category=' + encodeURIComponent(category);
      if (dept) {
        url += '&dept=' + encodeURIComponent(dept);
      }

      try { $('#sefInventoryExportModal').modal('hide'); } catch (_) {}
      window.location.href = url;
    });

    if ($printBtn.length) {
      $printBtn.off('click.gsoSefPrint').on('click.gsoSefPrint', function(){
        var category = String($('#sefExportCategory').val() || '').trim().toUpperCase();
        if (category !== 'PAR' && category !== 'ICS' && category !== 'ALL') { category = 'PAR'; }

        var dept = String($('#sefExportDept').val() || '').trim();
        var url = '../auth/auth.php?print_sef_inventory=1&category=' + encodeURIComponent(category);
        if (dept) {
          url += '&dept=' + encodeURIComponent(dept);
        }

        try { $('#sefInventoryExportModal').modal('hide'); } catch (_) {}
        try {
          window.open(url, '_blank');
        } catch (e) {
          window.location.href = url;
        }
      });
    }
  })();

  // Navbar bell notifications for CLEARANCE-ADMIN when items become READY
  // Initialize globally so the bell shows on any page (e.g., Clearance Statistics).
  if (window.GSO && window.GSO.Notifications && typeof window.GSO.Notifications.startPcReadyPolling === 'function') {
    window.GSO.Notifications.startPcReadyPolling();
  }

  // Unserviceable (Disposal) - Summary by Account Code
  if ($('#unserviceableAccountCodesTable').length && !$.fn.dataTable.isDataTable('#unserviceableAccountCodesTable')) {
    function esc(text){ return $('<div>').text(text === null || text === undefined ? '' : String(text)).html(); }
    function formatMoney(n){
      var num = Number(n || 0) || 0;
      try {
        return '₱ ' + num.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
      } catch (e) {
        return '₱ ' + num.toFixed(2);
      }
    }

    $('#unserviceableAccountCodesTable').DataTable({
      responsive: true,
      lengthChange: false,
      autoWidth: false,
      processing: true,
      serverSide: true,
      searchDelay: 500,
      ajax: {
        url: '../auth/auth.php',
        type: 'POST',
        data: function(d){
          d.unserviceable_account_codes_dt = 1;
          return d;
        }
      },
      columns: [
        {
          data: 'account_code',
          render: function(data){
            var code = String(data || '').trim();
            var href = 'unserviceable-items.php?code=' + encodeURIComponent(code);
            return '<a href="' + href + '">' + esc(code) + '</a>';
          }
        },
        { data: 'account_name', render: function(d){ return esc(d || ''); } },
        { data: 'item_count', className: 'text-center', render: function(d){ return esc(d || 0); } },
        { data: 'total_value', className: 'text-right', render: function(d){ return formatMoney(d); } }
      ],
      order: [[0, 'asc']]
    });
  }

  // Unserviceable (Disposal) - Items filtered by Account Code
  if ($('#unserviceableItemsTable').length && !$.fn.dataTable.isDataTable('#unserviceableItemsTable')) {
    function esc2(text){ return $('<div>').text(text === null || text === undefined ? '' : String(text)).html(); }
    function escAttr(text){
      return String(text === null || text === undefined ? '' : text)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
    }
    function checkboxCell(row){
      var par = String((row && row.par_number) || '').trim();
      if (!par) { return ''; }
      var attrPar = escAttr(par);
      return '<input type="checkbox" class="us-row-select" value="' + attrPar + '" aria-label="Select property ' + attrPar + '">';
    }
    function undoCell(row){
      var par = String((row && row.par_number) || '').trim();
      if (!par) { return ''; }
      return '<button type="button" class="btn btn-sm btn-outline-secondary undoUnserviceableItem" data-par="' + escAttr(par) + '" title="Undo to inventory"><i class="fas fa-undo"></i></button>';
    }

    var code = '';
    try {
      code = String($('#unserviceableItemsPage').attr('data-account-code') || '').trim();
    } catch (e) { code = ''; }

    function selectedPars(){
      var pars = [];
      $('#unserviceableItemsTable tbody input.us-row-select:checked').each(function(){
        var v = String(this.value || '').trim();
        if (v) { pars.push(v); }
      });
      return pars;
    }

    function setForDisposalEnabled(){
      var enabled = selectedPars().length > 0;
      try {
        $('.buttons-forDisposal').prop('disabled', !enabled);
      } catch (e) {}
    }

    var usItemsTable = $('#unserviceableItemsTable').DataTable({
      responsive: true,
      lengthChange: false,
      autoWidth: false,
      processing: true,
      serverSide: true,
      searchDelay: 500,
      dom: "<'row'<'col-sm-12 col-md-6'B><'col-sm-12 col-md-6'f>>" +
           "<'row'<'col-sm-12'tr>>" +
           "<'row'<'col-sm-12 col-md-5'i><'col-sm-12 col-md-7'p>>",
      buttons: [
        {
          extend: 'excelHtml5',
          text: 'Excel',
          title: 'Unserviceable Items - ' + (code || ''),
          exportOptions: { columns: [2, 3, 4, 5, 6, 7] }
        },
        {
          extend: 'print',
          text: 'Print',
          title: 'Unserviceable Items - ' + (code || ''),
          exportOptions: { columns: [2, 3, 4, 5, 6, 7] }
        },
        {
          text: 'For Disposal',
          className: 'btn btn-danger buttons-forDisposal',
          action: function(){
            var pars = selectedPars();
            if (!pars.length) {
              Swal.fire({ icon: 'info', title: 'No items selected' });
              return;
            }

            Swal.fire({
              title: 'Mark selected items?',
              text: 'This will save the selected items to IIRUP and mark them as FOR DISPOSAL.',
              icon: 'warning',
              showCancelButton: true,
              confirmButtonText: 'Yes, continue'
            }).then(function(r){
              if (!r.isConfirmed) { return; }
              var fd = new FormData();
              fd.append('unserviceable_mark_for_disposal', 1);
              fd.append('par_numbers', JSON.stringify(pars));
              window.gsoAppendFormToken(fd, 'disposal_form_token', 'disposalActions');
              $.ajax({
                type: 'POST',
                url: '../auth/auth.php',
                data: fd,
                processData: false,
                contentType: false,
                success: function(resp){
                  var res = resp;
                  if (typeof resp === 'string') {
                    try { res = JSON.parse(resp); } catch (e) { res = null; }
                  }
                  if (res && res.status === 200) {
                    Swal.fire({ position:'center', icon:'success', title: res.message || 'Marked for disposal', showConfirmButton:false, timer:1400 });
                    usItemsTable.ajax.reload(null, false);
                    $('#selectAllUnserviceableItems').prop('checked', false).prop('indeterminate', false);
                  } else {
                    Swal.fire({ icon:'error', title:'Failed', text: (res && res.message) ? res.message : 'Unable to update selected items.' });
                  }
                  setForDisposalEnabled();
                },
                error: function(xhr){
                  var msg = 'Server error';
                  try {
                    if (xhr && xhr.responseText) {
                      msg = String(xhr.responseText).trim() || msg;
                    }
                  } catch (e) {}
                  Swal.fire({ icon:'error', title:'Server error', text: msg });
                  setForDisposalEnabled();
                }
              });
            });
          }
        }
      ],
      ajax: {
        url: '../auth/auth.php',
        type: 'POST',
        data: function(d){
          d.unserviceable_items_by_account_code_dt = 1;
          d.code = code;
          return d;
        }
      },
      columns: [
        { data: 'par_number', orderable: false, searchable: false, className: 'text-center align-middle', render: function(d, type, row){ return checkboxCell(row); } },
        { data: null, orderable: false, searchable: false, className: 'text-center align-middle', render: function(d, type, row){ return undoCell(row); } },
        { data: 'particular', render: function(d){ return esc2(d || ''); } },
        { data: 'serial_number', render: function(d){ return d ? esc2(d) : "<span class='text-dark'>NULL</span>"; } },
        { data: 'serial_number_2', render: function(d){ return d ? esc2(d) : "<span class='text-dark'>NULL</span>"; } },
        { data: 'par_number', render: function(d){ return esc2(d || ''); } },
        { data: 'department_name', render: function(d){ return esc2(d || ''); } },
        { data: 'last_update', render: function(d){ return esc2(d || ''); } }
      ],
      columnDefs: [
        { targets: 0, responsivePriority: 1 },
        { targets: 1, responsivePriority: 2 },
        { targets: 2, responsivePriority: 3 }
      ],
      order: [[7, 'desc']]
    });

    function syncSelectAll(){
      var $all = $('#unserviceableItemsTable tbody input.us-row-select');
      var $checked = $all.filter(':checked');
      var el = document.getElementById('selectAllUnserviceableItems');
      if (!el) { return; }
      if ($checked.length === 0) {
        el.checked = false;
        el.indeterminate = false;
      } else if ($checked.length === $all.length) {
        el.checked = true;
        el.indeterminate = false;
      } else {
        el.checked = false;
        el.indeterminate = true;
      }
    }

    $(document).off('change.usSelectAll').on('change.usSelectAll', '#selectAllUnserviceableItems', function(){
      var checked = this.checked;
      $('#unserviceableItemsTable tbody input.us-row-select').prop('checked', checked);
      syncSelectAll();
      setForDisposalEnabled();
    });

    $(document).off('change.usRowSelect').on('change.usRowSelect', '#unserviceableItemsTable tbody input.us-row-select', function(){
      syncSelectAll();
      setForDisposalEnabled();
    });

    $(document).off('click.undoUnserviceableItem').on('click.undoUnserviceableItem', '.undoUnserviceableItem', function(){
      var par = String($(this).data('par') || '').trim();
      if (!par) { return; }

      Swal.fire({
        title: 'Undo this item?',
        text: 'This will move the item back to its inventory table.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Yes, undo'
      }).then(function(r){
        if (!r.isConfirmed) { return; }
        var fd = new FormData();
        fd.append('unserviceable_undo_item', 1);
        fd.append('par_number', par);
        $.ajax({
          type: 'POST',
          url: '../auth/auth.php',
          data: fd,
          processData: false,
          contentType: false,
          success: function(resp){
            var res = resp;
            if (typeof resp === 'string') {
              try { res = JSON.parse(resp); } catch (e) { res = null; }
            }
            if (res && res.status === 200) {
              Swal.fire({ position:'center', icon:'success', title: res.message || 'Item restored', showConfirmButton:false, timer:1400 });
              usItemsTable.ajax.reload(null, false);
              $('#selectAllUnserviceableItems').prop('checked', false).prop('indeterminate', false);
            } else {
              Swal.fire({ icon:'error', title:'Failed', text:(res && res.message) || 'Unable to undo item.' });
            }
            setForDisposalEnabled();
          },
          error: function(){
            Swal.fire({ icon:'error', title:'Server error', text:'Unable to undo item.' });
            setForDisposalEnabled();
          }
        });
      });
    });

    usItemsTable.on('draw', function(){
      syncSelectAll();
      setForDisposalEnabled();
    });

    // Initialize state
    setForDisposalEnabled();
  }

  // Disposal - Summary by Account Code
  if ($('#disposalAccountCodesTable').length && !$.fn.dataTable.isDataTable('#disposalAccountCodesTable')) {
    function escD(text){ return $('<div>').text(text === null || text === undefined ? '' : String(text)).html(); }
    function formatMoneyD(n){
      var num = Number(n || 0) || 0;
      try { return '₱ ' + num.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }
      catch (e) { return '₱ ' + num.toFixed(2); }
    }

    var dispRef = '';
    try { dispRef = String($('#disposalAccountCodesPage').attr('data-disposal-ref') || '').trim(); } catch (e) { dispRef = ''; }

    $('#disposalAccountCodesTable').DataTable({
      responsive: true,
      lengthChange: false,
      autoWidth: false,
      processing: true,
      serverSide: true,
      searchDelay: 500,
      ajax: {
        url: '../auth/auth.php',
        type: 'POST',
        data: function(d){ d.disposal_account_codes_dt = 1; d.disposal_reference = dispRef; return d; }
      },
      columns: [
        {
          data: 'account_code',
          render: function(data){
            var code = String(data || '').trim();
            var href = 'disposal-items.php?code=' + encodeURIComponent(code) + '&ref=' + encodeURIComponent(dispRef || '');
            return '<a href="' + href + '">' + escD(code) + '</a>';
          }
        },
        { data: 'account_name', render: function(d){ return escD(d || ''); } },
        { data: 'item_count', className: 'text-center', render: function(d){ return escD(d || 0); } },
        { data: 'total_appraise_value', className: 'text-right', render: function(d){ return formatMoneyD(d); } }
      ],
      order: [[0, 'asc']]
    });
  }

  // Disposal - Activities list
  if ($('#disposalActivitiesTable').length && !$.fn.dataTable.isDataTable('#disposalActivitiesTable')) {
    function escDA(text){ return $('<div>').text(text === null || text === undefined ? '' : String(text)).html(); }

    // ---- Disposal Info Modal (IIRUP details)
    var DISP_INFO_MAX_OFFICERS = 5;

    function formatMoneyDA(n){
      var num = Number(n || 0) || 0;
      try { return '₱ ' + num.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }
      catch (e) { return '₱ ' + num.toFixed(2); }
    }

    function setDispInfoSummaryLoading(ref){
      $('#dispInfoSumRef').text(ref || '—');
      $('#dispInfoSumStatus').text('...');
      $('#dispInfoSumCreated').text('...');
      $('#dispInfoSumItems').text('...');
      $('#dispInfoSumQty').text('...');
      $('#dispInfoSumTotal').text('...');
    }

    function fillDispInfoSummary(d){
      var ref = d && d.disposal_reference ? String(d.disposal_reference) : '';
      var st = (d && d.status != null) ? Number(d.status) : 0;
      var statusLabel = (st === 0) ? 'ONGOING APPRAISAL' : 'DONE';
      $('#dispInfoSumRef').text(ref || '—');
      $('#dispInfoSumStatus').text(statusLabel);
      $('#dispInfoSumCreated').text(d && d.created_at ? ('Created: ' + String(d.created_at)) : '—');
      $('#dispInfoSumItems').text((d && d.items_count != null) ? String(d.items_count) : '0');
      $('#dispInfoSumQty').text((d && d.qty_total != null) ? String(d.qty_total) : '0');
      $('#dispInfoSumTotal').text(formatMoneyDA(d && d.total_appraise_value != null ? d.total_appraise_value : 0));
    }

    function loadDispInfoSummary(ref){
      setDispInfoSummaryLoading(ref);
      return $.ajax({
        url: '../auth/auth.php',
        type: 'POST',
        data: { disposal_get_info: 1, disposal_reference: ref },
        cache: false
      }).done(function(resp){
        var res = resp;
        if (typeof resp === 'string') { try { res = JSON.parse(resp); } catch (e) { res = null; } }
        if (res && res.status === 200 && res.data) {
          fillDispInfoSummary(res.data);
        } else {
          $('#dispInfoSumStatus').text('—');
          $('#dispInfoSumCreated').text('—');
          $('#dispInfoSumItems').text('—');
          $('#dispInfoSumQty').text('—');
          $('#dispInfoSumTotal').text('—');
        }
      }).fail(function(){
        $('#dispInfoSumStatus').text('—');
        $('#dispInfoSumCreated').text('—');
        $('#dispInfoSumItems').text('—');
        $('#dispInfoSumQty').text('—');
        $('#dispInfoSumTotal').text('—');
      });
    }

    function setDispInfoFieldReadonly(readonly){
      var ro = !!readonly;
      $('#disposalInfoForm input').not('#dispInfoRef,#dispInfoExists').prop('readonly', ro);
      $('#dispInfoAddOfficer').prop('disabled', ro);
      $('#dispInfoOfficers').find('input').prop('readonly', ro);
      $('#dispInfoOfficers').find('.dispInfoRemoveOfficer').prop('disabled', ro);
    }

    function setDispInfoMode(mode){
      // mode: 'new' | 'view' | 'edit'
      var isView = (mode === 'view');
      var isNew = (mode === 'new');
      $('#dispInfoAlert').toggleClass('d-none', !isNew);
      $('#dispInfoEditBtn').toggleClass('d-none', isNew || !isView);
      $('#dispInfoSaveBtn').toggleClass('d-none', isView);
      setDispInfoFieldReadonly(isView);
    }

    function validateIirupInfoPayload(p){
      var missing = [];
      function req(val, label){
        if (val == null || String(val).trim() === '') { missing.push(label); }
      }

      req(p.accountable_officer, 'Name of Accountable Officer');
      req(p.designation, 'Designation');
      req(p.station, 'Station');
      req(p.local_chief_executive, 'Approved by');
      req(p.disposal_chairperson, 'Disposal Committee Chairperson');
      req(p.witness_name, 'Witness');
      req(p.witness_position, 'Witness Position');

      if (!Array.isArray(p.inspectors) || p.inspectors.length < 1) {
        missing.push('At least one Inspector (Name + Position)');
      }
      if (Array.isArray(p.inspectors) && p.inspectors.length > DISP_INFO_MAX_OFFICERS) {
        missing.push('Inspectors (max of 5)');
      }
      if (Array.isArray(p.inspectors)) {
        for (var i = 0; i < p.inspectors.length; i++) {
          var o = p.inspectors[i] || {};
          var n = (o.name != null) ? String(o.name).trim() : '';
          var pos = (o.position != null) ? String(o.position).trim() : '';
          if (!n) { missing.push('Inspector #' + (i + 1) + ' Name'); }
          if (!pos) { missing.push('Inspector #' + (i + 1) + ' Position'); }
        }
      }
      return missing;
    }

    function fillDispInfoFromIirupData(d){
      d = d && typeof d === 'object' ? d : {};
      $('#dispInfoAccountableOfficer').val(d.accountable_officer || '');
      $('#dispInfoDesignation').val(d.designation || '');
      $('#dispInfoStation').val(d.station || '');

      // UI label says "Approved by" but DB requirement is local_chief_executive
      $('#dispInfoApprovedBy').val(d.local_chief_executive || '');
      $('#dispInfoCommitteeChair').val(d.disposal_chairperson || '');

      $('#dispInfoWitness').val(d.witness_name || '');
      $('#dispInfoWitnessPosition').val(d.witness_position || '');
      setOfficers(d.inspectors || []);
    }

    function prefillDispInfoFromLegacyDisposalDetails(d){
      d = d && typeof d === 'object' ? d : {};
      $('#dispInfoAccountableOfficer').val(d.accountable_officer_name || '');
      $('#dispInfoDesignation').val(d.designation || '');
      $('#dispInfoStation').val(d.station || '');
      $('#dispInfoApprovedBy').val(d.approved_by || '');
      $('#dispInfoCommitteeChair').val(d.committee_chairperson || '');
      $('#dispInfoWitness').val(d.witness || '');
      $('#dispInfoWitnessPosition').val(d.witness_position || '');
      setOfficers(d.inspection_officers || []);
    }

    function officerRowHtml(officer){
      var o = officer && typeof officer === 'object' ? officer : {};
      var name = (o.name != null) ? String(o.name) : '';
      var position = (o.position != null) ? String(o.position) : '';
      return (
        '<div class="dispInfoOfficerRow mb-2">' +
          '<div class="input-group">' +
            '<div class="input-group-prepend">' +
              '<span class="input-group-text" title="Officer"><i class="fas fa-user"></i></span>' +
            '</div>' +
            '<input type="text" class="form-control dispInfoOfficerName" placeholder="Officer name" value="' + escDA(name) + '">' +
            '<div class="input-group-prepend">' +
              '<span class="input-group-text" title="Position"><i class="fas fa-briefcase"></i></span>' +
            '</div>' +
            '<input type="text" class="form-control dispInfoOfficerPosition" placeholder="Position" value="' + escDA(position) + '">' +
            '<div class="input-group-append">' +
              '<button class="btn btn-outline-danger dispInfoRemoveOfficer" type="button" title="Remove">' +
                '<i class="fas fa-times"></i>' +
              '</button>' +
            '</div>' +
          '</div>' +
        '</div>'
      );
    }

    function getOfficers(){
      var arr = [];
      $('#dispInfoOfficers .dispInfoOfficerRow').each(function(){
        var name = String($(this).find('.dispInfoOfficerName').val() || '').trim();
        var position = String($(this).find('.dispInfoOfficerPosition').val() || '').trim();

        // Ignore completely empty rows.
        if (!name && !position) { return; }

        arr.push({ name: name, position: position });
      });
      return arr;
    }

    function setOfficers(officers){
      var list = Array.isArray(officers) ? officers : [];
      $('#dispInfoOfficers').empty();
      if (!list.length) {
        $('#dispInfoOfficers').append(officerRowHtml({ name: '', position: '' }));
        return;
      }
      for (var i = 0; i < list.length && i < DISP_INFO_MAX_OFFICERS; i++) {
        // Backward-compat: old payload can be array of strings
        if (typeof list[i] === 'string') {
          $('#dispInfoOfficers').append(officerRowHtml({ name: list[i], position: '' }));
        } else {
          $('#dispInfoOfficers').append(officerRowHtml(list[i]));
        }
      }
    }

    function resetDispInfoForm(){
      $('#dispInfoExists').val('0');
      $('#dispInfoRef').val('');
      $('#dispInfoAccountableOfficer').val('');
      $('#dispInfoDesignation').val('');
      $('#dispInfoStation').val('');
      $('#dispInfoApprovedBy').val('');
      $('#dispInfoCommitteeChair').val('');
      $('#dispInfoWitness').val('');
      $('#dispInfoWitnessPosition').val('');
      setOfficers([{ name: '', position: '' }]);
      $('#disposalInfoForm .is-invalid').removeClass('is-invalid');
    }

    function openDisposalInfoModal(ref){
      resetDispInfoForm();
      $('#dispInfoRef').val(ref);
      loadDispInfoSummary(ref);
      setDispInfoMode('view');
      $('#disposalInfoModal').modal('show');

      // Load from iirup_* tables (new source of truth)
      return $.ajax({
        url: '../auth/auth.php',
        type: 'POST',
        data: { iirup_info_get: 1, disposal_reference: ref, disposal_form_token: window.gsoGetFormToken('disposalActions') },
        cache: false
      }).done(function(resp){
        var res = resp;
        if (typeof resp === 'string') { try { res = JSON.parse(resp); } catch (e) { res = null; } }
        if (!res || res.status !== 200) {
          Swal.fire({ icon:'error', title:'Failed', text:(res && res.message) ? res.message : 'Unable to load IIRUP info.' });
          return;
        }

        var d = res.data || {};
        var exists = !!d.exists;
        $('#dispInfoExists').val(exists ? '1' : '0');

        fillDispInfoFromIirupData(d);

        if (!exists) {
          // Backward-compat: prefill from legacy disposal_details if present
          $.ajax({
            url: '../auth/auth.php',
            type: 'POST',
            data: { disposal_details_get: 1, disposal_reference: ref, disposal_form_token: window.gsoGetFormToken('disposalActions') },
            cache: false
          }).done(function(r2){
            var rr = r2;
            if (typeof r2 === 'string') { try { rr = JSON.parse(r2); } catch (e) { rr = null; } }
            if (rr && rr.status === 200 && rr.data) {
              prefillDispInfoFromLegacyDisposalDetails(rr.data);
            }
            setDispInfoMode('new');
          }).fail(function(){
            setDispInfoMode('new');
          });
        } else {
          setDispInfoMode('view');
        }
      }).fail(function(){
        Swal.fire({ icon:'error', title:'Server error', text:'Unable to load IIRUP info.' });
      });
    }
    // Modal interactions
    $(document).off('click.dispInfoEdit').on('click.dispInfoEdit', '#dispInfoEditBtn', function(){
      setDispInfoMode('edit');
    });


    function setCreateDisposalDisabled(disabled, tooltipText){
      var $btn = $('#createDisposalBtn');
      var $wrap = $('#createDisposalBtnWrap');
      if (!$btn.length) { return; }

      $btn.prop('disabled', !!disabled);

      // Tooltips don't fire on disabled buttons, so attach to wrapper.
      if ($wrap.length) {
        $wrap.attr('title', disabled ? (tooltipText || '') : '');
        if (disabled) {
          $wrap.attr('data-toggle', 'tooltip');
          try {
            $wrap.tooltip('dispose').tooltip({ container: 'body', boundary: 'window' });
          } catch (e) {}
        } else {
          try { $wrap.tooltip('dispose'); } catch (e) {}
        }
      }
    }

    function refreshCreateDisposalState(){
      var $btn = $('#createDisposalBtn');
      if (!$btn.length) { return; }

      return $.ajax({
        url: '../auth/auth.php',
        type: 'POST',
        data: { disposal_get_active: 1, disposal_form_token: window.gsoGetFormToken('disposalActions') },
        cache: false
      }).done(function(resp){
        var res = resp;
        if (typeof resp === 'string') {
          try { res = JSON.parse(resp); } catch (e) { res = null; }
        }
        var hasActive = !!(res && res.status === 200 && res.data && Number(res.data.status) === 0);
        setCreateDisposalDisabled(hasActive, 'finish the ongoing disposal first');
      }).fail(function(){
        // If backend fails, keep button enabled (avoid blocking user).
        setCreateDisposalDisabled(false, '');
      });
    }

    function statusBadge(st){
      var v = parseInt(st, 10);
      if (!isFinite(v)) { v = 0; }
      if (v === 0) {
        return '<span class="badge badge-warning">ONGOING APPRAISAL</span>';
      }
      return '<span class="badge badge-success">DONE</span>';
    }

    function actionDropdown(row){
      var ref = row && row.disposal_reference ? String(row.disposal_reference) : '';
      var st = row && row.status != null ? parseInt(row.status, 10) : 0;
      if (!isFinite(st)) { st = 0; }
      var closeDisabled = (st !== 0);
      var closeCls = closeDisabled ? ' disabled' : '';
      var closeAria = closeDisabled ? ' aria-disabled="true" tabindex="-1"' : '';
      return (
        '<div class="btn-group">' +
          '<button class="btn btn-sm btn-secondary dropdown-toggle" type="button" data-toggle="dropdown" data-boundary="window" aria-haspopup="true" aria-expanded="false">' +
            '<i class="fas fa-ellipsis-v"></i>' +
          '</button>' +
          '<div class="dropdown-menu dropdown-menu-right">' +
            '<a href="#" class="dropdown-item btnUploadDisposalDocs" data-ref="' + escDA(ref) + '">' +
              '<i class="fas fa-upload mr-2"></i>Upload Documents' +
            '</a>' +
            '<a href="#" class="dropdown-item btnDisposalInfo" data-ref="' + escDA(ref) + '">' +
              '<i class="fas fa-info-circle mr-2"></i>Disposal Info' +
            '</a>' +
            '<a href="#" class="dropdown-item btnCloseDisposal text-success' + closeCls + '" data-ref="' + escDA(ref) + '"' + closeAria + '>' +
              '<i class="fas fa-check mr-2"></i>Completed' +
            '</a>' +
          '</div>' +
        '</div>'
      );
    }

    var activitiesTable = $('#disposalActivitiesTable').DataTable({
      responsive: true,
      lengthChange: false,
      autoWidth: false,
      processing: true,
      serverSide: true,
      searchDelay: 500,
      ajax: {
        url: '../auth/auth.php',
        type: 'POST',
        data: function(d){ d.disposal_activities_dt = 1; return d; }
      },
      columns: [
        { data: 'created_at', render: function(d){ return escDA(d || ''); } },
        {
          data: 'disposal_reference',
          render: function(d){
            var ref = String(d || '').trim();
            var href = 'disposal-account-code.php?ref=' + encodeURIComponent(ref);
            return '<a href="' + href + '">' + escDA(ref) + '</a>';
          }
        },
        { data: 'status', render: function(d){ return statusBadge(d); } },
        { data: null, orderable: false, searchable: false, render: function(d, t, row){ return actionDropdown(row); } }
      ],
      order: [[0, 'desc']]
    });

    // Initial state: disable Create button if an activity is ongoing
    refreshCreateDisposalState();

    $(document).off('click.createDisposal').on('click.createDisposal', '#createDisposalBtn', function(){
      Swal.fire({
        title: 'Create a disposal Activities?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Create'
      }).then(function(r){
        if (!r.isConfirmed) { return; }
        var fd = new FormData();
        fd.append('disposal_create', 1);
        window.gsoAppendFormToken(fd, 'disposal_form_token', 'disposalActions');
        $.ajax({
          type: 'POST',
          url: '../auth/auth.php',
          data: fd,
          processData: false,
          contentType: false,
          success: function(resp){
            var res = resp;
            if (typeof resp === 'string') { try { res = JSON.parse(resp); } catch (e) { res = null; } }
            if (res && res.status === 200) {
              Swal.fire({ icon:'success', title: res.message || 'Created', timer: 1200, showConfirmButton: false });
              activitiesTable.ajax.reload(null, false);
              refreshCreateDisposalState();
            } else {
              Swal.fire({ icon:'error', title:'Failed', text: (res && res.message) ? res.message : 'Unable to create disposal activity.' });
            }
          },
          error: function(){
            Swal.fire({ icon:'error', title:'Server error', text:'Unable to create disposal activity.' });
          }
        });
      });
    });

    $(document).off('click.closeDisposal').on('click.closeDisposal', '.btnCloseDisposal', function(e){
      if (e && e.preventDefault) { e.preventDefault(); }
      if ($(this).hasClass('disabled') || $(this).attr('aria-disabled') === 'true') { return; }
      var ref = String($(this).attr('data-ref') || '').trim();
      if (!ref) { return; }
      Swal.fire({
        title: 'Mark this disposal activity as completed?',
        text: ref,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Completed'
      }).then(function(r){
        if (!r.isConfirmed) { return; }
        var fd = new FormData();
        fd.append('disposal_close', 1);
        fd.append('disposal_reference', ref);
        window.gsoAppendFormToken(fd, 'disposal_form_token', 'disposalActions');
        $.ajax({
          type: 'POST',
          url: '../auth/auth.php',
          data: fd,
          processData: false,
          contentType: false,
          success: function(resp){
            var res = resp;
            if (typeof resp === 'string') { try { res = JSON.parse(resp); } catch (e) { res = null; } }
            if (res && res.status === 200) {
              Swal.fire({ icon:'success', title: res.message || 'Closed', timer: 1200, showConfirmButton: false });
              activitiesTable.ajax.reload(null, false);
              refreshCreateDisposalState();
            } else {
              Swal.fire({ icon:'error', title:'Failed', text: (res && res.message) ? res.message : 'Unable to close disposal activity.' });
            }
          },
          error: function(){
            Swal.fire({ icon:'error', title:'Server error', text:'Unable to close disposal activity.' });
          }
        });
      });
    });

    $(document).off('click.disposalInfo').on('click.disposalInfo', '.btnDisposalInfo', function(e){
      if (e && e.preventDefault) { e.preventDefault(); }
      var ref = String($(this).attr('data-ref') || '').trim();
      if (!ref) { return; }
      openDisposalInfoModal(ref);
    });

    $(document).off('click.dispInfoAddOfficer').on('click.dispInfoAddOfficer', '#dispInfoAddOfficer', function(){
      var count = $('#dispInfoOfficers .dispInfoOfficerRow').length;
      if (count >= DISP_INFO_MAX_OFFICERS) {
        Swal.fire({ icon:'info', title:'Limit reached', text:'You can add up to 5 inspection officers only.' });
        return;
      }
      $('#dispInfoOfficers').append(officerRowHtml({ name: '', position: '' }));
    });

    $(document).off('click.dispInfoRemoveOfficer').on('click.dispInfoRemoveOfficer', '.dispInfoRemoveOfficer', function(){
      var $rows = $('#dispInfoOfficers .dispInfoOfficerRow');
      if ($rows.length <= 1) {
        $(this).closest('.dispInfoOfficerRow').find('input').val('');
        return;
      }
      $(this).closest('.dispInfoOfficerRow').remove();
    });

    $(document).off('click.dispInfoSave').on('click.dispInfoSave', '#dispInfoSaveBtn', function(){
      var ref = String($('#dispInfoRef').val() || '').trim();
      if (!ref) { return; }

      var payload = {
        disposal_reference: ref,
        accountable_officer: String($('#dispInfoAccountableOfficer').val() || '').trim(),
        designation: String($('#dispInfoDesignation').val() || '').trim(),
        station: String($('#dispInfoStation').val() || '').trim(),
        disposal_chairperson: String($('#dispInfoCommitteeChair').val() || '').trim(),
        local_chief_executive: String($('#dispInfoApprovedBy').val() || '').trim(),
        witness_name: String($('#dispInfoWitness').val() || '').trim(),
        witness_position: String($('#dispInfoWitnessPosition').val() || '').trim(),
        inspectors: getOfficers()
      };

      var missing = validateIirupInfoPayload(payload);
      if (missing.length) {
        Swal.fire({ icon:'warning', title:'Please complete required fields', html: '<div class="text-left">' + missing.map(function(x){ return '• ' + escDA(x); }).join('<br>') + '</div>' });
        return;
      }

      var fd = new FormData();
      fd.append('iirup_info_save', 1);
      fd.append('disposal_reference', payload.disposal_reference);
      fd.append('accountable_officer', payload.accountable_officer);
      fd.append('designation', payload.designation);
      fd.append('station', payload.station);
      fd.append('disposal_chairperson', payload.disposal_chairperson);
      fd.append('local_chief_executive', payload.local_chief_executive);
      fd.append('witness_name', payload.witness_name);
      fd.append('witness_position', payload.witness_position);
      fd.append('inspectors_json', JSON.stringify(payload.inspectors));
      window.gsoAppendFormToken(fd, 'disposal_form_token', 'disposalActions');

      $.ajax({
        type: 'POST',
        url: '../auth/auth.php',
        data: fd,
        processData: false,
        contentType: false,
        success: function(resp){
          var res = resp;
          if (typeof resp === 'string') { try { res = JSON.parse(resp); } catch (e) { res = null; } }
          if (res && res.status === 200) {
            $('#dispInfoExists').val('1');
            Swal.fire({ icon:'success', title: res.message || 'Saved', timer: 1200, showConfirmButton: false });
            setDispInfoMode('view');
          } else {
            Swal.fire({ icon:'error', title:'Failed', text:(res && res.message) ? res.message : 'Unable to save IIRUP information.' });
          }
        },
        error: function(){
          Swal.fire({ icon:'error', title:'Server error', text:'Unable to save IIRUP information.' });
        }
      });
    });

    // Disposal Activities - Required documents (modal UI)
    var dispDocsRequiredIds = [
      '#dispDocsAppraisalReport',
      '#dispDocsFormalRequest',
      '#dispDocsTaasReport',
      '#dispDocsInvitationToBid',
      '#dispDocsResolution',
      '#dispDocsNoticeOfAward',
      '#dispDocsDeedOfSale',
      '#dispDocsTransmittalAccounting',
      '#dispDocsTransmittalCOA'
    ];

    // Optional additional uploads (do not block Submit)
    var dispDocsOptionalIds = [
      '#dispDocsResolutionOptional1',
      '#dispDocsResolutionOptional2',
      '#dispDocsInvitationOptional1',
      '#dispDocsInvitationOptional2'
    ];

    var dispDocsAllIds = dispDocsRequiredIds.concat(dispDocsOptionalIds);
    var dispDocsUploaded = {};
    for (var di = 0; di < dispDocsAllIds.length; di++) {
      dispDocsUploaded[dispDocsAllIds[di]] = false;
    }

    function dispDocsSetAlert(type, message){
      var $a = $('#dispDocsAlert');
      if (!$a.length) { return; }
      var cls = 'alert-info';
      if (type === 'success') { cls = 'alert-success'; }
      else if (type === 'danger' || type === 'error') { cls = 'alert-danger'; }
      else if (type === 'warning') { cls = 'alert-warning'; }
      $a.removeClass('d-none alert-success alert-danger alert-warning alert-info').addClass(cls).text(String(message || ''));
    }

    function dispDocsClearAlert(){
      var $a = $('#dispDocsAlert');
      if (!$a.length) { return; }
      $a.addClass('d-none').removeClass('alert-success alert-danger alert-warning alert-info').text('');
    }

    function dispDocsSetFileName($input){
      if (!$input || !$input.length) { return; }
      var targetSel = String($input.attr('data-name-target') || '').trim();
      if (!targetSel) { return; }
      var $t = $(targetSel);
      if (!$t.length) { return; }
      var el = $input.get(0);
      var nm = (el && el.files && el.files.length) ? String(el.files[0].name || '') : '';
      $t.text(nm || 'Choose file');
    }

    function dispDocsAllRequiredPresent(){
      for (var i = 0; i < dispDocsRequiredIds.length; i++) {
        var el = $(dispDocsRequiredIds[i]).get(0);
        if (!el || !el.files || !el.files.length) { return false; }
        var f = el.files[0];
        var nm = String((f && f.name) ? f.name : '').toLowerCase();
        var isPdfByName = nm.endsWith('.pdf');
        var isPdfByType = (f && f.type) ? (String(f.type).toLowerCase() === 'application/pdf') : false;
        if (!isPdfByName && !isPdfByType) { return false; }
      }
      return true;
    }

    // "All uploaded" refers to required documents only.
    function dispDocsAllUploaded(){
      for (var i = 0; i < dispDocsRequiredIds.length; i++) {
        var idSel = dispDocsRequiredIds[i];
        if (!dispDocsUploaded[idSel]) { return false; }
      }
      return true;
    }

    function dispDocsSetStatus(inputSel, uploaded){
      var map = {
        '#dispDocsFormalRequest': '#dispDocsStatusFormalRequest',
        '#dispDocsAppraisalReport': '#dispDocsStatusAppraisalReport',
        '#dispDocsTaasReport': '#dispDocsStatusTaasReport',
        '#dispDocsResolution': '#dispDocsStatusResolution',
        '#dispDocsResolutionOptional1': '#dispDocsStatusResolutionOptional1',
        '#dispDocsResolutionOptional2': '#dispDocsStatusResolutionOptional2',
        '#dispDocsInvitationToBid': '#dispDocsStatusInvitationToBid',
        '#dispDocsInvitationOptional1': '#dispDocsStatusInvitationOptional1',
        '#dispDocsInvitationOptional2': '#dispDocsStatusInvitationOptional2',
        '#dispDocsDeedOfSale': '#dispDocsStatusDeedOfSale',
        '#dispDocsNoticeOfAward': '#dispDocsStatusNoticeOfAward',
        '#dispDocsTransmittalAccounting': '#dispDocsStatusTransmittalAccounting',
        '#dispDocsTransmittalCOA': '#dispDocsStatusTransmittalCOA'
      };
      var tgt = map[inputSel];
      if (!tgt) { return; }
      var $b = $(tgt);
      if (!$b.length) { return; }
      if (uploaded) {
        $b.removeClass('badge-secondary badge-warning').addClass('badge-success').text('Uploaded');
      } else {
        $b.removeClass('badge-success badge-warning').addClass('badge-secondary').text('Not uploaded');
      }
    }

    function dispDocsSyncUploadButton(){
      var ids = dispDocsRequiredIds;
      var selected = 0;
      for (var i = 0; i < ids.length; i++) {
        var el = $(ids[i]).get(0);
        if (el && el.files && el.files.length) { selected++; }
      }
      // Footer button is Submit: enabled only when all required docs are already uploaded.
      var allUploaded = dispDocsAllUploaded();
      $('#dispDocsUploadBtn').prop('disabled', !allUploaded);

      var $badge = $('#dispDocsCountBadge');
      if ($badge.length) {
        var uploadedCount = 0;
        for (var k = 0; k < dispDocsRequiredIds.length; k++) {
          if (dispDocsUploaded[dispDocsRequiredIds[k]]) { uploadedCount++; }
        }
        $badge.text(String(uploadedCount) + '/' + String(ids.length) + ' uploaded');
        $badge.removeClass('badge-secondary badge-success').addClass(allUploaded ? 'badge-success' : 'badge-secondary');
      }
    }

    function dispDocsResetModal(newRef){
      $('#dispDocsRef').val(newRef || '');
      $('#dispDocsRefText').text(newRef || '—');

      function dispDocsSetResolutionOptionalVisible(visible) {
        var $wrap = $('#dispDocsResolutionOptionalWrap');
        var $btn = $('#dispDocsShowMoreResolutionBtn');
        if (!$wrap.length || !$btn.length) { return; }

        var show = !!visible;
        if ($.fn && $.fn.collapse) {
          try { $wrap.collapse(show ? 'show' : 'hide'); } catch (e) {}
        } else {
          // Fallback (no animation)
          $wrap.toggleClass('d-none', !show);
        }

        $btn.attr('aria-expanded', show ? 'true' : 'false');
        $btn.attr('title', show ? 'Hide additional resolution files' : 'Add additional resolution files');

        var $icon = $btn.find('i.fas');
        if ($icon.length) {
          $icon.toggleClass('fa-plus', !show);
          $icon.toggleClass('fa-minus', show);
        }
      }

      // Hide optional resolution inputs by default
      dispDocsSetResolutionOptionalVisible(false);

      function dispDocsSetInvitationOptionalVisible(visible) {
        var $wrap = $('#dispDocsInvitationOptionalWrap');
        var $btn = $('#dispDocsShowMoreInvitationBtn');
        if (!$wrap.length || !$btn.length) { return; }

        var show = !!visible;
        if ($.fn && $.fn.collapse) {
          try { $wrap.collapse(show ? 'show' : 'hide'); } catch (e2) {}
        } else {
          $wrap.toggleClass('d-none', !show);
        }

        $btn.attr('aria-expanded', show ? 'true' : 'false');
        $btn.attr('title', show ? 'Hide additional invitation to bid files' : 'Add additional invitation to bid files');

        var $icon = $btn.find('i.fas');
        if ($icon.length) {
          $icon.toggleClass('fa-plus', !show);
          $icon.toggleClass('fa-minus', show);
        }
      }

      // Hide optional invitation inputs by default
      dispDocsSetInvitationOptionalVisible(false);

      var $inputs = $('#disposalDocsForm .dispDocsInput');
      $inputs.val('');
      $inputs.each(function(){ dispDocsSetFileName($(this)); });

      // Reset upload state + statuses
      var keys = Object.keys(dispDocsUploaded);
      for (var i = 0; i < keys.length; i++) {
        dispDocsUploaded[keys[i]] = false;
        dispDocsSetStatus(keys[i], false);
      }
      $('.dispDocsUploadOne').prop('disabled', true);

      dispDocsClearAlert();
      dispDocsSyncUploadButton();
    }

    // Keep button state in sync with collapse animation
    $(document)
      .off('shown.bs.collapse.dispDocsResolutionOptional hidden.bs.collapse.dispDocsResolutionOptional')
      .on('shown.bs.collapse.dispDocsResolutionOptional', '#dispDocsResolutionOptionalWrap', function(){
        var $btn = $('#dispDocsShowMoreResolutionBtn');
        if (!$btn.length) { return; }
        $btn.attr('aria-expanded', 'true');
        $btn.attr('title', 'Hide additional resolution files');
        var $icon = $btn.find('i.fas');
        if ($icon.length) { $icon.removeClass('fa-plus').addClass('fa-minus'); }
      })
      .on('hidden.bs.collapse.dispDocsResolutionOptional', '#dispDocsResolutionOptionalWrap', function(){
        var $btn = $('#dispDocsShowMoreResolutionBtn');
        if (!$btn.length) { return; }
        $btn.attr('aria-expanded', 'false');
        $btn.attr('title', 'Add additional resolution files');
        var $icon = $btn.find('i.fas');
        if ($icon.length) { $icon.removeClass('fa-minus').addClass('fa-plus'); }
      });

    $(document)
      .off('shown.bs.collapse.dispDocsInvitationOptional hidden.bs.collapse.dispDocsInvitationOptional')
      .on('shown.bs.collapse.dispDocsInvitationOptional', '#dispDocsInvitationOptionalWrap', function(){
        var $btn = $('#dispDocsShowMoreInvitationBtn');
        if (!$btn.length) { return; }
        $btn.attr('aria-expanded', 'true');
        $btn.attr('title', 'Hide additional invitation to bid files');
        var $icon = $btn.find('i.fas');
        if ($icon.length) { $icon.removeClass('fa-plus').addClass('fa-minus'); }
      })
      .on('hidden.bs.collapse.dispDocsInvitationOptional', '#dispDocsInvitationOptionalWrap', function(){
        var $btn = $('#dispDocsShowMoreInvitationBtn');
        if (!$btn.length) { return; }
        $btn.attr('aria-expanded', 'false');
        $btn.attr('title', 'Add additional invitation to bid files');
        var $icon = $btn.find('i.fas');
        if ($icon.length) { $icon.removeClass('fa-minus').addClass('fa-plus'); }
      });

    // Toggle optional Resolution (Committee) inputs
    $(document).off('click.dispDocsShowMoreResolution').on('click.dispDocsShowMoreResolution', '#dispDocsShowMoreResolutionBtn', function(e){
      if (e && e.preventDefault) { e.preventDefault(); }
      var $wrap = $('#dispDocsResolutionOptionalWrap');
      var $btn = $(this);
      if (!$wrap.length || !$btn.length) { return; }

      var isShown = $wrap.hasClass('show');
      var willShow = !isShown;

      if ($.fn && $.fn.collapse) {
        try { $wrap.collapse('toggle'); } catch (ex) {}
      } else {
        // Fallback (no animation)
        $wrap.toggleClass('d-none', isShown);
      }

      // Optimistic UI update; final state is enforced by shown/hidden events
      $btn.attr('aria-expanded', willShow ? 'true' : 'false');
      $btn.attr('title', willShow ? 'Hide additional resolution files' : 'Add additional resolution files');
      var $icon = $btn.find('i.fas');
      if ($icon.length) {
        $icon.toggleClass('fa-plus', !willShow);
        $icon.toggleClass('fa-minus', willShow);
      }
    });

    // Toggle optional Invitation to Bid inputs
    $(document).off('click.dispDocsShowMoreInvitation').on('click.dispDocsShowMoreInvitation', '#dispDocsShowMoreInvitationBtn', function(e){
      if (e && e.preventDefault) { e.preventDefault(); }
      var $wrap = $('#dispDocsInvitationOptionalWrap');
      var $btn = $(this);
      if (!$wrap.length || !$btn.length) { return; }

      var isShown = $wrap.hasClass('show');
      var willShow = !isShown;

      if ($.fn && $.fn.collapse) {
        try { $wrap.collapse('toggle'); } catch (ex2) {}
      } else {
        $wrap.toggleClass('d-none', isShown);
      }

      $btn.attr('aria-expanded', willShow ? 'true' : 'false');
      $btn.attr('title', willShow ? 'Hide additional invitation to bid files' : 'Add additional invitation to bid files');
      var $icon = $btn.find('i.fas');
      if ($icon.length) {
        $icon.toggleClass('fa-plus', !willShow);
        $icon.toggleClass('fa-minus', willShow);
      }
    });

    $(document).off('click.uploadDisposalDocs').on('click.uploadDisposalDocs', '.btnUploadDisposalDocs', function(e){
      if (e && e.preventDefault) { e.preventDefault(); }
      var ref = String($(this).attr('data-ref') || '').trim();
      if (!ref) { return; }
      // Show modal
      if (!$('#disposalDocsModal').length) {
        // Fallback: do nothing if UI is not present on the page.
        return;
      }
      dispDocsResetModal(ref);
      $('#disposalDocsModal').modal('show');
    });

    // Disposal Activities - Required documents modal behaviors
    $(document).off('change.dispDocsInput').on('change.dispDocsInput', '#disposalDocsForm .dispDocsInput', function(){
      dispDocsClearAlert();
      var $inp = $(this);
      var el = $inp.get(0);
      var f = (el && el.files && el.files.length) ? el.files[0] : null;
      if (f) {
        var nm = String(f.name || '').toLowerCase();
        var isPdfByName = nm.endsWith('.pdf');
        var isPdfByType = f.type ? (String(f.type).toLowerCase() === 'application/pdf') : false;
        if (!isPdfByName && !isPdfByType) {
          // Reset invalid selection
          $inp.val('');
          dispDocsSetFileName($inp);
          dispDocsSetAlert('warning', 'PDF files only. Please select a .pdf document.');
          dispDocsSyncUploadButton();
          return;
        }
      }
      dispDocsSetFileName($inp);
      // Enable the per-tile upload button when a file is selected
      var idSel = '#' + String($inp.attr('id') || '');
      if (idSel && dispDocsUploaded.hasOwnProperty(idSel)) {
        var $btn = $('.dispDocsUploadOne[data-input="' + idSel + '"]');
        if ($btn.length) {
          $btn.prop('disabled', !(f && f.name));
        }
        // Selecting a new file means it is not yet uploaded
        dispDocsUploaded[idSel] = false;
        dispDocsSetStatus(idSel, false);
      }
      dispDocsSyncUploadButton();
    });

    // Per-document upload button
    $(document).off('click.dispDocsUploadOne').on('click.dispDocsUploadOne', '.dispDocsUploadOne', function(){
      dispDocsClearAlert();
      var ref = String($('#dispDocsRef').val() || '').trim();
      if (!ref) {
        dispDocsSetAlert('danger', 'Missing disposal reference.');
        return;
      }

      var inputSel = String($(this).attr('data-input') || '').trim();
      if (!inputSel) { return; }
      var el = $(inputSel).get(0);
      if (!el || !el.files || !el.files.length) {
        dispDocsSetAlert('warning', 'Please choose a PDF file first.');
        return;
      }

      var f = el.files[0];
      var nm = String((f && f.name) ? f.name : '').toLowerCase();
      var isPdfByName = nm.endsWith('.pdf');
      var isPdfByType = (f && f.type) ? (String(f.type).toLowerCase() === 'application/pdf') : false;
      if (!isPdfByName && !isPdfByType) {
        dispDocsSetAlert('warning', 'PDF files only. Please select a .pdf document.');
        return;
      }

      var $btn = $(this);
      $btn.prop('disabled', true);
      dispDocsSetAlert('info', 'Uploading selected document...');

      var fd = new FormData();
      fd.append('disposal_upload_documents', 1);
      fd.append('disposal_reference', ref);
      fd.append('docs[]', f);
      window.gsoAppendFormToken(fd, 'disposal_form_token', 'disposalActions');

      $.ajax({
        type: 'POST',
        url: '../auth/auth.php',
        data: fd,
        processData: false,
        contentType: false,
        success: function(resp){
          var res = resp;
          if (typeof resp === 'string') { try { res = JSON.parse(resp); } catch (e) { res = null; } }
          if (res && res.status === 200) {
            var errList = (res.data && res.data.errors && res.data.errors.length) ? res.data.errors.join('\n') : '';
            if (errList) {
              dispDocsSetAlert('warning', (res.message || 'Uploaded with warnings') + ' ' + errList);
              $btn.prop('disabled', false);
              dispDocsSetStatus(inputSel, false);
            } else {
              dispDocsUploaded[inputSel] = true;
              dispDocsSetStatus(inputSel, true);
              dispDocsSetAlert('success', 'Uploaded.');
            }
          } else {
            dispDocsSetAlert('danger', (res && res.message) ? res.message : 'Upload failed.');
            $btn.prop('disabled', false);
          }
          dispDocsSyncUploadButton();
        },
        error: function(){
          dispDocsSetAlert('danger', 'Server error. Upload failed.');
          $btn.prop('disabled', false);
          dispDocsSyncUploadButton();
        }
      });
    });

    // Footer button = Submit (requires all 7 uploaded)
    $(document).off('click.dispDocsSubmit').on('click.dispDocsSubmit', '#dispDocsUploadBtn', function(){
      dispDocsClearAlert();
      if (!dispDocsAllUploaded()) {
        dispDocsSetAlert('warning', 'Please upload all ' + String(dispDocsRequiredIds.length) + ' required documents before submitting.');
        dispDocsSyncUploadButton();
        return;
      }
      dispDocsSetAlert('success', 'Submitted.');
      setTimeout(function(){ $('#disposalDocsModal').modal('hide'); }, 700);
    });
  }

  // Disposal - Items filtered by Account Code
  if ($('#disposalItemsTable').length && !$.fn.dataTable.isDataTable('#disposalItemsTable')) {
    function escDI(text){ return $('<div>').text(text === null || text === undefined ? '' : String(text)).html(); }
    function formatMoneyDI(n){
      var num = Number(n || 0) || 0;
      try { return '₱ ' + num.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }
      catch (e) { return '₱ ' + num.toFixed(2); }
    }

    function formatNumberDI(n){
      var num = Number(n || 0);
      if (!isFinite(num)) { num = 0; }
      try { return num.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }
      catch (e) { return num.toFixed(2); }
    }

    function parseNumberDI(v){
      var raw = String(v == null ? '' : v).trim();
      if (!raw) { return 0; }
      // Remove thousand separators and anything non-numeric except dot and minus.
      raw = raw.replace(/,/g, '').replace(/[^0-9.\-]/g, '');
      var num = Number(raw);
      if (!isFinite(num) || num < 0) { return 0; }
      return num;
    }

    // Live formatting helper: insert commas while preserving caret position.
    function liveFormatNumberInputDI(inputEl){
      if (!inputEl) { return; }
      var $el = $(inputEl);
      if ($el.data('gsoFmtLock')) { return; }

      var raw = String(inputEl.value == null ? '' : inputEl.value);
      var selStart = typeof inputEl.selectionStart === 'number' ? inputEl.selectionStart : raw.length;
      var beforeCaretRaw = raw.slice(0, selStart);

      // Token count = number of non-comma characters to the left (digits/dot/minus only).
      var beforeTokens = beforeCaretRaw.replace(/,/g, '').replace(/[^0-9.\-]/g, '');
      var tokenCount = beforeTokens.length;

      // Clean input (keep one leading minus, keep first dot; cap decimals to 2).
      var cleaned = raw.replace(/,/g, '').replace(/[^0-9.\-]/g, '');
      if (cleaned === '' || cleaned === '-' || cleaned === '.' || cleaned === '-.') {
        return; // let user type, format on blur
      }

      var sign = '';
      if (cleaned[0] === '-') {
        sign = '-';
        cleaned = cleaned.slice(1);
      }

      // Split on first dot only.
      var dotIdx = cleaned.indexOf('.');
      var intPart = dotIdx === -1 ? cleaned : cleaned.slice(0, dotIdx);
      var decPart = dotIdx === -1 ? '' : cleaned.slice(dotIdx + 1);

      intPart = intPart.replace(/\D/g, '');
      decPart = decPart.replace(/\D/g, '').slice(0, 2);

      // If user started with dot (e.g., ".5"), normalize to "0.5".
      var hadDot = dotIdx !== -1;
      if (intPart === '' && hadDot) {
        intPart = '0';
      }

      var intFmt = intPart.replace(/\B(?=(\d{3})+(?!\d))/g, ',');
      var formatted = sign + (intFmt || '0');
      if (hadDot) {
        formatted += '.' + decPart;
      }

      if (formatted === raw) {
        return;
      }

      // Restore caret by tokenCount into formatted (count non-comma chars).
      var targetCount = tokenCount;
      var nonCommaLen = formatted.replace(/,/g, '').length;
      if (targetCount > nonCommaLen) { targetCount = nonCommaLen; }
      var pos = 0;
      var seen = 0;
      while (pos < formatted.length && seen < targetCount) {
        if (formatted[pos] !== ',') {
          seen++;
        }
        pos++;
      }

      $el.data('gsoFmtLock', true);
      inputEl.value = formatted;
      try { inputEl.setSelectionRange(pos, pos); } catch (e) {}
      $el.data('gsoFmtLock', false);
    }

    function renderAppraiseInput(val, row){
      var id = row && row.iirup_id ? String(row.iirup_id) : '';
          var num = Number(val || 0);
          if (!isFinite(num)) { num = 0; }
          return '<input type="text" inputmode="decimal" class="form-control form-control-sm iirup-appraise-input" ' +
            'data-iirup-id="' + escDI(id) + '" value="' + escDI(formatNumberDI(num)) + '" style="max-width:160px; text-align:right;" />';
    }

    function saveAppraiseValue(iirupId, value){
      var fd = new FormData();
      fd.append('iirup_update_appraise_value', 1);
      fd.append('iirup_id', iirupId);
      fd.append('appraise_value', value);
      return $.ajax({
        type: 'POST',
        url: '../auth/auth.php',
        data: fd,
        processData: false,
        contentType: false
      });
    }
    var dCode = '';
    var dispRef2 = '';
    try { dCode = String($('#disposalItemsPage').attr('data-account-code') || '').trim(); } catch (e) { dCode = ''; }
    try { dispRef2 = String($('#disposalItemsPage').attr('data-disposal-ref') || '').trim(); } catch (e) { dispRef2 = ''; }

    var disposalItemsTable = $('#disposalItemsTable').DataTable({
      responsive: true,
      lengthChange: false,
      autoWidth: false,
      processing: true,
      serverSide: true,
      searchDelay: 500,
      dom: "<'row mb-2'<'col-md-8'B><'col-md-4 text-right'f>>rtip",
      buttons: [
        {
          extend: 'excel',
          title: 'Disposal Items - ' + (dCode || ''),
          exportOptions: { columns: [0,1,2,3,4,5,6,7] }
        },
        {
          extend: 'print',
          title: 'Disposal Items - ' + (dCode || ''),
          exportOptions: { columns: [0,1,2,3,4,5,6,7] }
        },
        {
          text: 'IIRUP',
          action: function(){
            if (!dispRef2 || !dCode) {
              try { Swal.fire({ icon:'warning', title:'Missing reference', text:'Unable to print IIRUP: missing disposal reference or account code.' }); } catch (e) {}
              return;
            }
            var url = 'print-iirup.php?ref=' + encodeURIComponent(dispRef2) + '&code=' + encodeURIComponent(dCode);
            window.open(url, '_blank');
          }
        }
      ],
      ajax: {
        url: '../auth/auth.php',
        type: 'POST',
        data: function(d){ d.disposal_items_by_account_code_dt = 1; d.code = dCode; d.disposal_reference = dispRef2; return d; }
      },
      columns: [
        { data: 'fund', className: 'text-center', render: function(d){ return escDI(d || ''); } },
        { data: 'category', className: 'text-center', render: function(d){ return escDI(d || ''); } },
        { data: 'qty', className: 'text-center', render: function(d){
            var n = parseInt(d, 10);
            if (!isFinite(n) || n <= 0) { n = 1; }
            return String(n);
          }
        },
        { data: 'particular', render: function(d){ return escDI(d || ''); } },
        { data: 'par_number', render: function(d){ return escDI(d || ''); } },
        { data: 'unit_cost', className: 'text-right', render: function(d){ return formatMoneyDI(d); } },
        { data: 'appraise_value', className: 'text-right', orderable: false, render: function(d, t, row){ return renderAppraiseInput(d, row); } },
        { data: 'total_appraise_value', className: 'text-right', orderable: false, render: function(d, t, row){
            var id = row && row.iirup_id ? String(row.iirup_id) : '';
            var qty = parseInt(row && row.qty ? row.qty : 1, 10);
            if (!isFinite(qty) || qty <= 0) { qty = 1; }
            var av = Number(row && row.appraise_value ? row.appraise_value : 0);
            if (!isFinite(av)) { av = 0; }
            var total = qty * av;
            return '<span class="iirup-total-appraise" data-iirup-id="' + escDI(id) + '">' + escDI(formatMoneyDI(total)) + '</span>';
          }
        }
      ],
      columnDefs: [
        // Make UNIT COST a bit narrower
        { targets: 5, width: '160px' }
      ],
      order: [[3, 'asc']]
    });

    function updateTotalAppraiseFor(iirupId, qty, appraiseValue){
      var id = String(iirupId || '').trim();
      if (!id) { return; }
      var q = parseInt(qty, 10);
      if (!isFinite(q) || q <= 0) { q = 1; }
      var av = Number(appraiseValue);
      if (!isFinite(av)) { av = 0; }
      var total = q * av;
      // Update both normal + responsive child rendering (if any) by id.
      try {
        $('#disposalItemsTable').find('.iirup-total-appraise[data-iirup-id="' + escDI(id) + '"]').text(formatMoneyDI(total));
      } catch (e) {}
    }

    function updateTotalFromInput(inputEl){
      var $inp = $(inputEl);
      var id = String($inp.data('iirup-id') || '').trim();
      if (!id) { return; }

      // Resolve row data even when input is inside responsive child row.
      var $tr = $inp.closest('tr');
      if ($tr.hasClass('child')) {
        $tr = $tr.prev();
      }
      var rowData = {};
      try { rowData = disposalItemsTable.row($tr).data() || {}; } catch (e) { rowData = {}; }
      var qty = rowData && rowData.qty ? rowData.qty : 1;
      var av = parseNumberDI($inp.val());
      updateTotalAppraiseFor(id, qty, av);
    }

    // Auto-save appraisal value (debounced per row, formats with separators on blur/change)
    var appraiseTimers = {};
    function queueAppraiseSave($inp){
      var id = String($inp.data('iirup-id') || '').trim();
      if (!id) { return; }
      if (appraiseTimers[id]) {
        try { clearTimeout(appraiseTimers[id]); } catch (e) {}
      }
      appraiseTimers[id] = setTimeout(function(){
        var vNum = parseNumberDI($inp.val());
        saveAppraiseValue(id, vNum.toFixed(2))
          .done(function(resp){
            var res = resp;
            if (typeof resp === 'string') {
              try { res = JSON.parse(resp); } catch (e) { res = null; }
            }
            if (!res || res.status !== 200) {
              try {
                Swal.fire({ icon:'error', title:'Failed', text: (res && res.message) ? res.message : 'Unable to save appraisal value.' });
              } catch (e) {}
            }
          })
          .fail(function(xhr){
            var msg = 'Server error';
            try { if (xhr && xhr.responseText) { msg = String(xhr.responseText).trim() || msg; } } catch (e) {}
            try { Swal.fire({ icon:'error', title:'Server error', text: msg }); } catch (e) {}
          });
      }, 450);
    }

    $('#disposalItemsTable').on('change', 'input.iirup-appraise-input', function(){
      var $inp = $(this);
      updateTotalFromInput(this);
      queueAppraiseSave($inp);
    });

    // Live format while typing (no save here; save on blur/change).
    $('#disposalItemsTable').on('input', 'input.iirup-appraise-input', function(){
      liveFormatNumberInputDI(this);
      updateTotalFromInput(this);
    });

    // Pressing Enter should exit the input (blur) and not trigger table actions.
    $('#disposalItemsTable').on('keydown', 'input.iirup-appraise-input', function(e){
      var key = e && (e.key || e.code);
      if (key === 'Enter') {
        try { e.preventDefault(); } catch (err) {}
        try { e.stopPropagation(); } catch (err2) {}
        try { this.blur(); } catch (err3) {}
        return false;
      }
    });

    $('#disposalItemsTable').on('blur', 'input.iirup-appraise-input', function(){
      var $inp = $(this);
      var vNum = parseNumberDI($inp.val());
      $inp.val(formatNumberDI(vNum));
      updateTotalFromInput(this);
      queueAppraiseSave($inp);
    });
  }

  // Property Clearance list (services/clearance.php)
  if ($('#clearanceTable').length && !$.fn.dataTable.isDataTable('#clearanceTable')) {
    var currentRole = String(window.currentUserRole || '').trim().toUpperCase();
    var canSeePcActions = ['SYSTEM-ADMIN', 'CLEARANCE-ADMIN'].indexOf(currentRole) !== -1;

    var clearanceTable = $('#clearanceTable').DataTable({
      responsive: true,
      lengthChange: false,
      autoWidth: false,
      processing: true,
      dom: "<'row mb-2'<'col-md-8 pcFiltersWrap'><'col-md-4 text-right'f>>rtip",
      ajax: {
        url: '../auth/auth.php',
        type: 'GET',
        cache: false,
        data: function (d) {
          // Backend handler expects $_GET['fetch_property_clearance_list']
          // Add a timestamp to prevent cached responses during polling.
          d.fetch_property_clearance_list = 1;
          d._ts = Date.now();
          return d;
        },
        dataSrc: function (json) {
          return (json && json.data) ? json.data : [];
        }
      },
      columns: [
        { data: 'employee_cell' },
        { data: 'clearance_name' },
        {
          data: 'created_at',
          render: function (data, type, row) {
            if (type === 'display' || type === 'filter') {
              return row.created_at_display || '';
            }
            return data || '';
          }
        },
        { data: 'control_number' },
        { data: 'status_badge' },
        { data: 'action_html' }
      ],
      order: [[2, 'desc']],
      columnDefs: [
        { targets: [0, 1, 3, 4, 5], orderable: false },
        { targets: [5], searchable: false },
        { targets: [5], visible: canSeePcActions }
      ],
      initComplete: function(){
        try {
          var $wrap = $(clearanceTable.table().container());
          var $target = $wrap.find('.pcFiltersWrap');
          var $bar = $('#pcFiltersBar');
          if ($target.length && $bar.length) {
            $bar.prependTo($target).addClass('mb-0');
          }
        } catch (e) {}
      }
    });

    // Dropdown filters (Category + Status)
    function escapeRegex(s){
      return String(s || '').replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    }

    function applyPcFilters(){
      var cat = String($('#pcFilterCategory').val() || '').trim();
      var st = String($('#pcFilterStatus').val() || '').trim();

      // Column 1: REQUEST FOR (clearance_name)
      if (cat) {
        clearanceTable.column(1).search('^' + escapeRegex(cat) + '$', true, false);
      } else {
        clearanceTable.column(1).search('');
      }

      // Column 4: STATUS (status_badge contains the label text)
      if (st) {
        clearanceTable.column(4).search('\\b' + escapeRegex(st) + '\\b', true, false);
      } else {
        clearanceTable.column(4).search('');
      }

      clearanceTable.draw();
    }

    $('#pcFilterCategory, #pcFilterStatus').off('change.pcFilters').on('change.pcFilters', applyPcFilters);
    $('#pcClearFilters').off('click.pcFilters').on('click.pcFilters', function(){
      $('#pcFilterCategory').val('');
      $('#pcFilterStatus').val('');
      applyPcFilters();
    });

    // Tooltips for dynamically-rendered action buttons
    clearanceTable.on('draw.dt', function(){
      initTooltips($('#clearanceTable'));
    });

    // Global helper for reliable refreshes (used by polling + action handlers)
    window.reloadPcClearanceTable = function () {
      if (!$('#clearanceTable').length) { return; }
      if (!$.fn.dataTable || !$.fn.dataTable.isDataTable('#clearanceTable')) { return; }
      try {
        $('#clearanceTable').DataTable().ajax.reload(null, false);
      } catch (e) {}
    };

    // Lightweight "realtime" updates via periodic AJAX refresh
    // Keeps the current page/filters by using ajax.reload(null, false)
    (function setupPcRealtimeRefresh(){
      var AUTO_REFRESH_MS = 8000; // adjust as needed
      var timerKey = 'pcClearanceRealtimeTimer';

      // Avoid duplicate timers (script.js can be loaded on many pages)
      if (window[timerKey]) {
        try { clearInterval(window[timerKey]); } catch (e) {}
        window[timerKey] = null;
      }

      function safeReload() {
        // Pause when tab is not visible
        if (document.hidden) { return; }
        if (typeof window.reloadPcClearanceTable === 'function') {
          window.reloadPcClearanceTable();
          return;
        }
        // Fallback (should rarely happen)
        if (typeof clearanceTable !== 'undefined' && clearanceTable && clearanceTable.ajax && clearanceTable.ajax.reload) {
          clearanceTable.ajax.reload(null, false);
        }
      }

      window[timerKey] = setInterval(safeReload, AUTO_REFRESH_MS);
      // When user returns to the tab, refresh immediately
      document.addEventListener('visibilitychange', function(){
        if (!document.hidden) { safeReload(); }
      });
    })();

    function setPcEditStatusBadge(label) {
      var $badge = $('#pcEditStatusBadge');
      if (!$badge.length) { return; }
      var text = String(label || '').toUpperCase();
      if (!text) { $badge.hide(); return; }
      $badge.removeClass('badge-primary badge-success badge-warning badge-danger');
      if (text === 'READY') { $badge.addClass('badge badge-primary'); }
      else if (text === 'RELEASED') { $badge.addClass('badge badge-success'); }
      else if (text === 'CANCELED') { $badge.addClass('badge badge-danger'); }
      else { $badge.addClass('badge badge-warning'); }
      $badge.text(text).show();
    }

    function openPcEditModal(controlNumber) {
      if (!controlNumber) { return; }
      $.ajax({
        type: 'GET',
        url: '../auth/auth.php',
        data: {
          fetch_pc_details: 1,
          control_number: controlNumber
        },
        success: function (response) {
          var res;
          try { res = jQuery.parseJSON(response); } catch (err) { res = null; }
          if (!res || res.status !== 200 || !res.data || !res.data.record) {
            var msg = (res && res.message) ? res.message : 'Unable to load details.';
            if (typeof Swal !== 'undefined') { Swal.fire({ icon: 'error', title: msg }); }
            return;
          }

          var record = res.data.record;
          var flags = res.data.flags || {};

          $('#pcEditCid').val(record.control_number || '');
          $('#pcEditEmpId').val(record.emp_id || '');
          $('#pcEditEmpName').val(record.emp_name || '');
          $('#pcEditPosition').val(record.position || '');
          $('#pcEditDept').val(record.dept_id || '');
          $('#pcEditCtype').val(record.ctype_id || '');
          $('#pcEditAddress').val(record.address || '');
          $('#pcEditCity').val(record.city || '');
          $('#pcEditOrNumber').val(record.or_number || '');

          setPcEditStatusBadge(flags.status_label);

          // Re-print section inside edit modal
          var $reprintSection = $('#pcEditReprintSection');
          var $reprintReason = $('#pcEditReprintReason');
          var $reprintBtn = $('#pcEditReprintBtn');
          $reprintSection.hide();
          $reprintReason.val('');
          $reprintBtn.hide().prop('disabled', false);
          if (flags.can_reprint) {
            $reprintSection.show();
            $reprintBtn.show();
            // Keep the control number on the button for action
            $reprintBtn.data('control', record.control_number || '');
          } else {
            $reprintBtn.data('control', '');
          }

          var $approve = $('#pcEditApproveBtn');
          var $cancel = $('#pcEditCancelBtn');
          var $note = $('#pcEditAcctNote');

          $note.hide().text('');
          var isReleased = (Number(record.release_status) === 1);
          var isCanceled = (Number(record.release_status) === 2);
          var canApprove = !!flags.can_approve;

          if (isCanceled) {
            $approve.hide();
            $cancel.hide();
          } else if (isReleased) {
            // Once RELEASED, cancellation is not allowed from the UI
            $approve.hide();
            $cancel.hide();
          } else {
            $cancel.show().data('value', record.control_number || '');
            if (canApprove && !isReleased) {
              $approve.show().data('value', record.control_number || '');
            } else {
              $approve.hide();
            }
          }

          var ctName = String(record.clearance_name || '').trim().toUpperCase();
          var ignoreAcct = (typeof flags.ignore_accountability !== 'undefined')
            ? !!flags.ignore_accountability
            : (ctName === 'TRAVEL ABROAD' || ctName === 'MATERNITY LEAVE' || ctName === 'VACATION LEAVE' || ctName === 'VACTION LEAVE');

          if (flags.has_accountability && !ignoreAcct) {
            $note.text('This employee still has active accountabilities. Approval/printing is blocked until cleared.');
            $note.show();
          }

          $('#pcEditModal').modal('show');
          initTooltips($('#pcEditModal'));
        }
      });
    }

    // Expose for notification deep-links / global click handler
    window.openPcEditModal = openPcEditModal;

    // (Notification click handler is registered globally above)

    // Deep-link support: open a specific clearance from navbar notifications
    (function openFromQueryParam(){
      try {
        var params = new URLSearchParams(window.location.search || '');
        var ctrl = (params.get('pc') || '').trim();
        if (!ctrl) { return; }

        // Open the modal
        openPcEditModal(ctrl);

        // Remove param so refresh/back doesn't keep reopening
        params.delete('pc');
        var newUrl = window.location.pathname + (params.toString() ? ('?' + params.toString()) : '') + (window.location.hash || '');
        if (window.history && window.history.replaceState) {
          window.history.replaceState({}, document.title, newUrl);
        }
      } catch (e) {}
    })();

    // Re-print from inside the Edit modal
    $(document).on('click', '#pcEditReprintBtn', function () {
      var controlNumber = ($(this).data('control') || '').trim();
      var reason = ($('#pcEditReprintReason').val() || '').trim();
      if (!controlNumber) {
        if (typeof Swal !== 'undefined') {
          Swal.fire({ icon: 'warning', title: 'No control number found.' });
        }
        return;
      }
      if (!reason) {
        if (typeof Swal !== 'undefined') {
          Swal.fire({ icon: 'warning', title: 'Please select a reason.' });
        }
        return;
      }

      var $btn = $(this);

      function doReprint() {
        $btn.prop('disabled', true);
        $.ajax({
          type: 'POST',
          url: '../auth/auth.php',
          data: {
            reprint_property_clearance: true,
            control_number: controlNumber,
            reason: reason
          },
          success: function (response) {
            var res;
            try { res = jQuery.parseJSON(response); } catch (err) { res = null; }

            if (res && res.status === 200) {
              // Hide section after successful one-time reprint
              $('#pcEditReprintSection').hide();
              $('#pcEditReprintReason').val('');
              $('#pcEditReprintBtn').hide();

              var nw = window.open('../admin/print-property-clearance.php?control_id=' + encodeURIComponent(controlNumber), '_Blank');
              if (nw) { nw.print(); }

              if (typeof window.reloadPcClearanceTable === 'function') {
                window.reloadPcClearanceTable();
              } else if (typeof clearanceTable !== 'undefined' && clearanceTable && clearanceTable.ajax && clearanceTable.ajax.reload) {
                clearanceTable.ajax.reload(null, false);
              }
              if ($('#pcEditModal').length) {
                $('#pcEditModal').modal('hide');
              }
              if (typeof Swal !== 'undefined') {
                Swal.fire({ position: 'center', icon: 'success', title: 'Re-printing...', showConfirmButton: false, timer: 1200 });
              }
            } else {
              var msg = (res && res.message) ? res.message : 'Unable to re-print.';
              if (typeof Swal !== 'undefined') {
                Swal.fire({ icon: 'error', title: msg });
              }
              $btn.prop('disabled', false);
            }
          },
          error: function () {
            if (typeof Swal !== 'undefined') {
              Swal.fire({ icon: 'error', title: 'Request failed. Please try again.' });
            }
            $btn.prop('disabled', false);
          }
        });
      }

      // Confirmation (Re-print)
      if (typeof Swal !== 'undefined' && Swal.fire) {
        Swal.fire({
          icon: 'warning',
          title: 'Re-print this clearance?',
          html: 'Reason: <strong>' + $('<div/>').text(reason).html() + '</strong>',
          showCancelButton: true,
          confirmButtonText: 'Yes, re-print',
          cancelButtonText: 'No, cancel'
        }).then(function (result) {
          if (result && result.isConfirmed) { doReprint(); }
        });
      } else {
        if (confirm('Re-print this clearance?')) {
          doReprint();
        }
      }
    });

    $(document).on('click', '.btnEditPc', function () {
      var control = $(this).data('control') || '';
      openPcEditModal(control);
    });

    // Deep-link support: services/clearance.php?control_id=XXXX
    try {
      var urlParams = new URLSearchParams(window.location.search);
      var preopen = urlParams.get('control_id');
      if (preopen) { openPcEditModal(preopen); }
    } catch (e) {}

  }
  
  // Add Item page: small DataTable for "recently added" list
  if ($('#addItemTable').length && $.fn.dataTable && !$.fn.dataTable.isDataTable('#addItemTable')) {
    $('#addItemTable').DataTable({
      responsive: true,
      lengthChange: false,
      ordering: false,
      language: {
        emptyTable: 'No data found.',
        zeroRecords: 'No matching records found.'
      }
    });
  }

  // ============================================================
  // New Purchase: summary table (one row per P.O. No.)
  // ============================================================
  function gsoNpSourceContext() {
    return String($('#np_source_context').val() || 'new_purchase').trim().toLowerCase();
  }

  function gsoNpFundKey() {
    return String($('#np_fund_key').val() || '').trim().toLowerCase();
  }

  if ($('#addItemNewPurchaseTable').length && $.fn.dataTable && !$.fn.dataTable.isDataTable('#addItemNewPurchaseTable')) {
    function gsoNpEsc(text) {
      return $('<div>').text(text === null || text === undefined ? '' : String(text)).html();
    }

    function gsoNpTextOrNull(text) {
      var value = String(text || '').trim();
      return value ? gsoNpEsc(value) : '<span class="text-dark">NULL</span>';
    }

    function gsoNpTextOrDash(text) {
      var value = String(text || '').trim();
      return value ? gsoNpEsc(value) : '<span class="text-muted">-</span>';
    }

    function gsoNpMoney(amount) {
      var value = Number(amount || 0) || 0;
      try {
        return '&#8369; ' + value.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
      } catch (e) {
        return '&#8369; ' + value.toFixed(2);
      }
    }

    var addItemNpTable = $('#addItemNewPurchaseTable').DataTable({
      responsive: true,
      lengthChange: false,
      ordering: false,
      autoWidth: false,
      deferRender: true,
      processing: true,
      searchDelay: 350,
      ajax: {
        url: '../auth/fetch_new_purchase_summary.php',
        type: 'POST',
        cache: false,
        dataSrc: function (resp) {
          return resp && $.isArray(resp.data) ? resp.data : [];
        }
      },
      dom: "<'row mb-2'<'col-sm-12 col-md-6'B><'col-sm-12 col-md-6'f>>" +
           "<'row'<'col-sm-12'tr>>" +
           "<'row'<'col-sm-12 col-md-5'i><'col-sm-12 col-md-7'p>>",
      language: {
        processing: 'Processing..',
        emptyTable: 'No data found.',
        zeroRecords: 'No matching records found.'
      },
      columns: [
        {
          data: null,
          className: 'text-center',
          orderable: false,
          searchable: false,
          render: function (data, type, row) {
            var poRaw = String((row && row.purchase_order) || '').trim();
            var checkboxValue = gsoNpEsc(poRaw);
            var label = poRaw ? checkboxValue : 'NULL';
            return '<input type="checkbox" class="add-item-np-checkbox" value="' + checkboxValue + '" aria-label="Select P.O. ' + label + '">';
          }
        },
        {
          data: 'purchase_order',
          render: function (data) {
            return '<span class="font-weight-bold">' + gsoNpTextOrNull(data) + '</span>';
          }
        },
        {
          data: 'purchase_request',
          render: function (data) {
            return gsoNpTextOrNull(data);
          }
        },
        {
          data: 'obr_number',
          render: function (data) {
            return gsoNpTextOrNull(data);
          }
        },
        {
          data: 'supplier',
          render: function (data) {
            return gsoNpTextOrDash(data);
          }
        },
        {
          data: 'department_name',
          render: function (data) {
            return gsoNpTextOrDash(data);
          }
        },
        {
          data: 'total_amount',
          render: function (data) {
            return gsoNpMoney(data);
          }
        },
        {
          data: null,
          className: 'text-center',
          orderable: false,
          searchable: false,
          render: function (data, type, row) {
            var rowId = parseInt((row && row.row_id) || 0, 10) || 0;
            return '' +
              '<button type="button" class="btn btn-xs btn-outline-primary np-edit-btn"' +
                ' data-row-id="' + rowId + '"' +
                ' data-po="' + gsoNpEsc((row && row.purchase_order) || '') + '"' +
                ' data-fund="' + gsoNpEsc((row && row.fund) || '') + '"' +
                ' data-dept="' + gsoNpEsc((row && row.department_code) || '') + '"' +
                ' data-pr="' + gsoNpEsc((row && row.purchase_request) || '') + '"' +
                ' data-obr="' + gsoNpEsc((row && row.obr_number) || '') + '"' +
                ' data-supplier="' + gsoNpEsc((row && row.supplier) || '') + '"' +
                ' data-paricsno="' + gsoNpEsc((row && row.par_ics_number) || '') + '"' +
                ' title="Edit">' +
                '<i class="fas fa-edit"></i>' +
              '</button>';
          }
        }
      ],
      buttons: [
        {
          text: 'Transfer to Records',
          className: 'btn btn-success btn-sm',
          action: function (e, dt, node) {
            try { e.preventDefault(); } catch (ex) {}

            var sourceContext = gsoNpSourceContext();
            if (sourceContext === 'fund_inventory') {
              var selectedFundIds = [];
              $(addItemNpTable.rows({ search: 'applied' }).nodes()).find('input.add-item-np-checkbox:checked').each(function () {
                var idsCsv = String($(this).data('itemIds') || '').trim();
                if (!idsCsv) { return; }
                idsCsv.split(',').forEach(function (idValue) {
                  var id = parseInt(String(idValue || '').trim(), 10) || 0;
                  if (id > 0 && $.inArray(id, selectedFundIds) === -1) {
                    selectedFundIds.push(id);
                  }
                });
              });

              if (!selectedFundIds.length) {
                Swal && Swal.fire({ icon: 'warning', title: 'Please select at least one P.O. row.' });
                return;
              }

              function doFundTransfer() {
                var $btn = $(node);
                $btn.prop('disabled', true).addClass('disabled');
                $.ajax({
                  url: '../auth/auth.php',
                  type: 'POST',
                  cache: false,
                  dataType: 'json',
                  data: {
                    bulkTransferFundInventory: 1,
                    fund_bulk: gsoNpFundKey(),
                    selected_fund_ids: JSON.stringify(selectedFundIds)
                  },
                  success: function (resp) {
                    if (!resp || Number(resp.status) !== 200) {
                      var message = (resp && resp.message) ? resp.message : 'Transfer failed.';
                      Swal ? Swal.fire({ icon: 'error', title: message }) : alert(message);
                      return;
                    }
                    if (Swal) {
                      Swal.fire({ icon: 'success', title: resp.message || 'Transferred successfully.' }).then(function () { window.location.reload(); });
                    } else {
                      alert(resp.message || 'Transferred successfully.');
                      window.location.reload();
                    }
                  },
                  error: function () {
                    Swal ? Swal.fire({ icon: 'error', title: 'Request failed. Please try again.' }) : alert('Request failed.');
                  },
                  complete: function () {
                    $btn.prop('disabled', false).removeClass('disabled');
                  }
                });
              }

              if (Swal) {
                Swal.fire({
                  icon: 'question',
                  title: 'Transfer selected P.O. rows to records?',
                  text: 'This will move their items into records.',
                  showCancelButton: true,
                  confirmButtonText: 'Yes, transfer',
                  cancelButtonText: 'Cancel'
                }).then(function (r) { if (r && r.isConfirmed) { doFundTransfer(); } });
              } else if (confirm('Transfer selected P.O. rows to records?')) {
                doFundTransfer();
              }
              return;
            }

            var token = String($('#np_transfer_token').val() || '').trim();
            if (!token) {
              Swal && Swal.fire({ icon: 'error', title: 'Missing transfer token. Please refresh the page.' });
              return;
            }

            var purchaseOrders = [];
            $(addItemNpTable.rows({ search: 'applied' }).nodes()).find('input.add-item-np-checkbox:checked').each(function () {
              var value = String($(this).val() || '').trim();
              if (value) { purchaseOrders.push(value); }
            });
            purchaseOrders = purchaseOrders.filter(function (value, index, array) { return array.indexOf(value) === index; });

            if (!purchaseOrders.length) {
              Swal && Swal.fire({ icon: 'warning', title: 'Please select at least one P.O. row.' });
              return;
            }

            function doTransfer() {
              var $btn = $(node);
              $btn.prop('disabled', true).addClass('disabled');
              $.ajax({
                url: '../auth/auth.php',
                type: 'POST',
                cache: false,
                dataType: 'json',
                data: {
                  transfer_new_purchase_to_records: 1,
                  token: token,
                  transfer_all: 0,
                  'purchase_orders[]': purchaseOrders
                },
                success: function (resp) {
                  if (!resp || Number(resp.status) !== 200) {
                    var msg = (resp && resp.message) ? resp.message : 'Transfer failed.';
                    Swal ? Swal.fire({ icon: 'error', title: msg }) : alert(msg);
                    return;
                  }
                  var okMsg = resp.message || 'Transferred successfully.';
                  try {
                    if (resp.data) {
                      var total = Number(resp.data.general_fund || 0) + Number(resp.data.sef || 0) + Number(resp.data.trust_fund || 0) + Number(resp.data.donation || 0);
                      if (total > 0) { okMsg += ' (' + total + ' item' + (total > 1 ? 's' : '') + ')'; }
                    }
                  } catch (mErr) {}
                  if (Swal) {
                    Swal.fire({ icon: 'success', title: okMsg }).then(function () { window.location.reload(); });
                  } else {
                    alert(okMsg);
                    window.location.reload();
                  }
                },
                error: function () {
                  Swal ? Swal.fire({ icon: 'error', title: 'Request failed. Please try again.' }) : alert('Request failed.');
                },
                complete: function () {
                  $btn.prop('disabled', false).removeClass('disabled');
                }
              });
            }

            if (Swal) {
              Swal.fire({
                icon: 'question',
                title: 'Transfer selected P.O. rows to records?',
                text: 'This will move their items out of New Purchase.',
                showCancelButton: true,
                confirmButtonText: 'Yes, transfer',
                cancelButtonText: 'Cancel'
              }).then(function (r) { if (r && r.isConfirmed) { doTransfer(); } });
            } else {
              if (confirm('Transfer selected P.O. rows to records?')) { doTransfer(); }
            }
          }
        }
      ],
      columnDefs: [
        { targets: 0, orderable: false, width: '4%' },
        { targets: -1, orderable: false, width: '4%' }
      ]
    });

    function syncAddItemNpSelectAll() {
      var $checks = $(addItemNpTable.rows({ search: 'applied' }).nodes()).find('input.add-item-np-checkbox');
      var checkedCount = $checks.filter(':checked').length;
      $('#add_item_np_select_all')
        .prop('checked', $checks.length > 0 && checkedCount === $checks.length)
        .prop('indeterminate', checkedCount > 0 && checkedCount < $checks.length);
    }

    $(document)
      .off('change.addItemNpSelectAll', '#add_item_np_select_all')
      .on('change.addItemNpSelectAll', '#add_item_np_select_all', function () {
        $(addItemNpTable.rows({ search: 'applied' }).nodes()).find('input.add-item-np-checkbox').prop('checked', $(this).is(':checked'));
        syncAddItemNpSelectAll();
      });

    $(document)
      .off('change.addItemNpRowCheckbox', '#addItemNewPurchaseTable input.add-item-np-checkbox')
      .on('change.addItemNpRowCheckbox', '#addItemNewPurchaseTable input.add-item-np-checkbox', syncAddItemNpSelectAll);

    addItemNpTable.on('draw', syncAddItemNpSelectAll);
    syncAddItemNpSelectAll();
  }

  // ============================================================
  // Add Item page: edit new purchase detail modal
  // ============================================================
  (function () {
    if (!$('#editNpDetailModal').length || (!$('#addItemNewPurchaseTable').length && !$('#FundInventoryTable').length)) { return; }

    var npDetailState = {
      group: null,
      items: [],
      bundles: [],
      employeeOptionsHtml: '<option value="">-SELECT-</option><option value="add_new_emp"> + ADD NEW EMPLOYEE </option>',
      useMultipleEndUsers: false
    };
    var npDetailPropertyRequestId = 0;
    var npDetailTempIndex = 0;
    var npDetailBundleParIcsCache = {};
    var npDetailGroupCache = {};
    var npDetailDepartmentOptionsCache = {};
    var npDetailEmployeeOptionsCache = {};
    var npDetailOpenRequest = null;

    function npDetailEsc(value) {
      return $('<div>').text(value === null || value === undefined ? '' : String(value)).html();
    }

    function npDetailNormalizeFund(value) {
      var fund = String(value || '').trim().toUpperCase();
      if (fund === 'SEF') { return 'SPECIAL EDUCATION FUND'; }
      return fund;
    }

    function npDetailPropertyOptionalFund() {
      var fund = npDetailNormalizeFund($('#edit_np_fund').val());
      return fund === 'TRUST FUND' || fund === 'DONATION';
    }

    function npDetailSourceContext() {
      return gsoNpSourceContext();
    }

    function npDetailFundInventoryKey() {
      return gsoNpFundKey();
    }

    function npDetailGroupCacheKey(po, rowId, sourceContext, fundInventoryKey) {
      return [
        String(sourceContext || '').trim().toLowerCase(),
        String(fundInventoryKey || '').trim().toLowerCase(),
        String(po || '').trim().toUpperCase(),
        parseInt(rowId, 10) || 0
      ].join('|');
    }

    function npDetailGetYear(value) {
      var text = String(value || '').trim();
      var match = text.match(/^\d{4}/);
      return match ? match[0] : text;
    }

    function npDetailFormatMoney(value, fixed) {
      var raw = String(value || '').replace(/,/g, '').replace(/[^0-9.]/g, '');
      var parts = raw.split('.');
      if (parts.length > 2) {
        raw = parts.shift() + '.' + parts.join('');
        parts = raw.split('.');
      }
      var whole = (parts[0] || '').replace(/^0+(?=\d)/, '').replace(/\B(?=(\d{3})+(?!\d))/g, ',');
      var decimals = parts.length > 1 ? parts[1].slice(0, 2) : '';
      if (!whole && decimals) { whole = '0'; }
      if (fixed) {
        decimals = (decimals + '00').slice(0, 2);
        return (whole || '0') + '.' + decimals;
      }
      return parts.length > 1 ? whole + '.' + decimals : whole;
    }

    function npDetailNormalizeSetCount(value) {
      var count = parseInt(value, 10) || 1;
      if (count < 1) { count = 1; }
      if (count > 100) { count = 100; }
      return count;
    }

    function npDetailNormalizeItemQuantity(value) {
      var count = parseInt(value, 10) || 1;
      if (count < 1) { count = 1; }
      if (count > 5000) { count = 5000; }
      return count;
    }

    function npDetailNextPropertyNumber(value) {
      var parts = String(value || '').trim().split('-');
      if (parts.length < 4) { return String(value || '').trim(); }
      var index = parts.length - 2;
      var seq = parts[index];
      var pad = seq.length;
      parts[index] = String((parseInt(seq, 10) || 0) + 1).padStart(pad, '0');
      return parts.join('-');
    }

    function npDetailPropertyCopies(firstValue, qty) {
      var copies = [];
      var current = String(firstValue || '').trim();
      var count = npDetailNormalizeItemQuantity(qty);
      for (var i = 0; i < count; i++) {
        if (current) {
          copies.push(current);
          current = npDetailNextPropertyNumber(current);
        }
      }
      return copies;
    }

    function npDetailBuildSerialRows(item) {
      var quantity = npDetailNormalizeItemQuantity(item.item_quantity || 1);
      var serials1 = $.isArray(item.serial_numbers) ? item.serial_numbers : [String(item.serial_number || '')];
      var serials2 = $.isArray(item.serial_numbers_2) ? item.serial_numbers_2 : [String(item.serial_number_2 || '')];
      var html = ''
        + '<div class="table-responsive gso-serial-table-scroll">'
        + '  <table class="table table-sm table-bordered mb-0">'
        + '    <thead class="bg-light">'
        + '      <tr>'
        + '        <th style="width:80px;">Item</th>'
        + '        <th>Primary Serial Number</th>'
        + '        <th>Secondary Serial Number</th>'
        + '      </tr>'
        + '    </thead>'
        + '    <tbody>';

      for (var i = 1; i <= quantity; i++) {
        html += ''
          + '<tr data-copy-index="' + i + '">'
          + '  <td class="align-middle text-center">' + i + '</td>'
          + '  <td><input type="text" class="form-control text-uppercase edit-np-serial-primary" name="serial_number[' + npDetailEsc(item.key) + '][' + i + ']" value="' + npDetailEsc(serials1[i - 1] || '') + '" placeholder="Enter primary serial number"></td>'
          + '  <td><input type="text" class="form-control text-uppercase edit-np-serial-secondary" name="serial_number_2[' + npDetailEsc(item.key) + '][' + i + ']" value="' + npDetailEsc(serials2[i - 1] || '') + '" placeholder="Enter secondary serial number"></td>'
          + '</tr>';
      }

      html += '    </tbody></table></div>';
      return html;
    }

    function npDetailFindParentItemByPropertyNumber(propertyNumber) {
      var target = String(propertyNumber || '').trim().toUpperCase();
      if (!target) { return null; }
      for (var i = 0; i < npDetailState.items.length; i++) {
        var item = npDetailState.items[i];
        var propertyNumbers = $.isArray(item.property_numbers) ? item.property_numbers : [];
        for (var j = 0; j < propertyNumbers.length; j++) {
          if (String(propertyNumbers[j] || '').trim().toUpperCase() === target) {
            return item;
          }
        }
        if (String(item.property_number || '').trim().toUpperCase() === target) {
          return item;
        }
      }
      return null;
    }

    function npDetailBundleSetCount() {
      return npDetailState.items.length || 0;
    }

    function npDetailBundleSetOptionsHtml(selectedValue) {
      var selected = String(selectedValue || '').trim();
      var html = '<option value="">-SELECT-</option>';
      $.each(npDetailState.items, function (_, item) {
        var setValue = String(item.set_no || '');
        var isSelected = setValue === selected ? ' selected' : '';
        html += '<option value="' + npDetailEsc(setValue) + '"' + isSelected + '>Set ' + npDetailEsc(setValue) + '</option>';
      });
      return html;
    }

    function npDetailFindParentItemBySetIndex(setIndex) {
      var target = parseInt(setIndex, 10) || 0;
      if (target < 1) { return null; }
      for (var i = 0; i < npDetailState.items.length; i++) {
        if ((parseInt(npDetailState.items[i].set_no, 10) || 0) === target) {
          return npDetailState.items[i];
        }
      }
      return null;
    }

    function npDetailBundlePropertyNumbers(bundle) {
      var parentItem = npDetailFindParentItemBySetIndex(bundle.set_index);
      if (!parentItem) { return []; }
      var propertyNumbers = $.isArray(parentItem.property_numbers) ? parentItem.property_numbers : [];
      if (propertyNumbers.length) {
        return propertyNumbers;
      }
      return npDetailPropertyCopies(parentItem.property_number || '', parentItem.item_quantity || 1);
    }

    function npDetailBundlePropertyPreviewText(bundle) {
      return npDetailBundlePropertyNumbers(bundle).join(', ');
    }

    function npDetailNormalizeBundle(bundle) {
      var data = $.extend({
        key: 'bundle_' + (++npDetailTempIndex),
        id: 0,
        set_index: '',
        category: '',
        unit: '',
        item: '',
        model: '',
        description: '',
        par_ics_number: '',
        add_serial: false,
        no_brand: false,
        serial_numbers: [],
        serial_numbers_2: []
      }, bundle || {});
      var parentItem = npDetailFindParentItemByPropertyNumber(data.bundle_with);
      if (!data.set_index && parentItem) {
        data.set_index = String(parentItem.set_no || '');
      }
      if (!data.set_index && npDetailBundleSetCount() === 1) {
        data.set_index = '1';
      }
      data.category = String(data.category || '').trim().toUpperCase();
      data.unit = String(data.unit || '').trim().toUpperCase();
      data.item = String(data.item || '').trim().toUpperCase();
      data.model = String(data.model || '');
      data.description = String(data.description || '');
      data.par_ics_number = String(data.par_ics_number || '').trim().toUpperCase();
      data.no_brand = String(data.model || '').trim().toUpperCase() === 'NO BRAND/MODEL' || !!data.no_brand;
      data.serial_numbers = $.isArray(data.serial_numbers) ? data.serial_numbers : [];
      data.serial_numbers_2 = $.isArray(data.serial_numbers_2) ? data.serial_numbers_2 : [];
      data.add_serial = !!data.add_serial;
      if (!data.add_serial) {
        $.each(data.serial_numbers, function (_, value) {
          if (String(value || '').trim() !== '') { data.add_serial = true; return false; }
        });
      }
      if (!data.add_serial) {
        $.each(data.serial_numbers_2, function (_, value) {
          if (String(value || '').trim() !== '') { data.add_serial = true; return false; }
        });
      }
      return data;
    }

    function npDetailSnapshotBundleRows() {
      var bundles = [];
      $('#editNpBundleRows .edit-np-bundle-card').each(function () {
        var $card = $(this);
        var serials1 = [];
        var serials2 = [];
        $card.find('.edit-np-bundle-serial-primary').each(function () {
          serials1.push(String($(this).val() || ''));
        });
        $card.find('.edit-np-bundle-serial-secondary').each(function () {
          serials2.push(String($(this).val() || ''));
        });
        bundles.push(npDetailNormalizeBundle({
          key: String($card.data('bundleKey') || ''),
          id: parseInt($card.data('bundleId'), 10) || 0,
          set_index: String($card.find('.edit-np-bundle-set').val() || '').trim(),
          category: String($card.find('.edit-np-bundle-category').val() || '').trim(),
          unit: String($card.find('.edit-np-bundle-unit').val() || '').trim(),
          item: String($card.find('.edit-np-bundle-asset').val() || '').trim(),
          model: String($card.find('.edit-np-bundle-brand').val() || ''),
          description: String($card.find('.edit-np-bundle-description').val() || ''),
          par_ics_number: String($card.find('.edit-np-bundle-par-ics-value').val() || '').trim(),
          add_serial: $card.find('.edit-np-bundle-add-serial').is(':checked'),
          no_brand: $card.find('.edit-np-bundle-no-brand').is(':checked'),
          serial_numbers: serials1,
          serial_numbers_2: serials2
        }));
      });
      npDetailState.bundles = bundles;
    }

    function npDetailBuildBundleSerialRows(bundle) {
      var normalized = npDetailNormalizeBundle(bundle);
      var parentItem = npDetailFindParentItemBySetIndex(normalized.set_index);
      var quantity = parentItem ? npDetailNormalizeItemQuantity(parentItem.item_quantity || 1) : 0;
      var propertyNumbers = npDetailBundlePropertyNumbers(normalized);
      var html = ''
        + '<div class="table-responsive gso-serial-table-scroll">'
        + '  <table class="table table-sm table-bordered mb-0">'
        + '    <thead class="bg-light">'
        + '      <tr>'
        + '        <th style="width:80px;">Unit</th>'
        + '        <th>Primary Serial Number</th>'
        + '        <th>Secondary Serial Number</th>'
        + '      </tr>'
        + '    </thead>'
        + '    <tbody>';

      if (!parentItem || quantity < 1) {
        html += '<tr><td colspan="3" class="text-center text-muted">Select a bundle set to load the per-unit bundle rows.</td></tr>';
      }

      for (var i = 1; i <= quantity; i++) {
        html += ''
          + '<tr>'
          + '  <td class="align-middle text-center">' + i + '</td>'
          + '  <td><input type="text" class="form-control text-uppercase edit-np-bundle-serial-primary" value="' + npDetailEsc(normalized.serial_numbers[i - 1] || '') + '" placeholder="Enter primary serial number"></td>'
          + '  <td><input type="text" class="form-control text-uppercase edit-np-bundle-serial-secondary" value="' + npDetailEsc(normalized.serial_numbers_2[i - 1] || '') + '" placeholder="Enter secondary serial number"><input type="hidden" class="edit-np-bundle-property-number" value="' + npDetailEsc(propertyNumbers[i - 1] || '') + '"></td>'
          + '</tr>';
      }

      html += ''
        + '    </tbody>'
        + '  </table>'
        + '</div>';
      return html;
    }

    function npDetailApplyBundleNoBrandState($card) {
      var $brand = $card.find('.edit-np-bundle-brand');
      var $toggle = $card.find('.edit-np-bundle-no-brand');
      if (!$brand.length || !$toggle.length) { return; }
      if ($toggle.is(':checked')) {
        if ($brand.data('prevUserValueCaptured') !== true) {
          $brand.data('prevUserValue', String($brand.val() || ''));
          $brand.data('prevUserValueCaptured', true);
        }
        $brand.val('NO BRAND/MODEL').prop('readonly', true);
        return;
      }
      if (String($brand.val() || '').trim().toUpperCase() === 'NO BRAND/MODEL') {
        $brand.val(String($brand.data('prevUserValue') || ''));
      }
      $brand.prop('readonly', false);
      $brand.data('prevUserValueCaptured', false);
    }

    function npDetailApplyBundleSerialVisibility($card) {
      $card.find('.edit-np-bundle-serial-row').toggle($card.find('.edit-np-bundle-add-serial').is(':checked'));
    }

    function npDetailSetBundleParIcsPreview($card, value) {
      var code = String(value || '').trim().toUpperCase();
      $card.find('.edit-np-bundle-par-ics-value').val(code);
      $card.find('.edit-np-bundle-par-ics-preview').val(code);
      var bundleKey = String($card.data('bundleKey') || '');
      $.each(npDetailState.bundles || [], function (_, bundle) {
        if (String(bundle.key || '') === bundleKey) {
          bundle.par_ics_number = code;
          return false;
        }
      });
    }

    function npDetailRefreshBundlePreviewFields($card) {
      if (!$card || !$card.length) { return; }
      var normalized = npDetailNormalizeBundle({
        key: String($card.data('bundleKey') || ''),
        id: parseInt($card.data('bundleId'), 10) || 0,
        set_index: String($card.find('.edit-np-bundle-set').val() || '').trim(),
        category: String($card.find('.edit-np-bundle-category').val() || '').trim(),
        unit: String($card.find('.edit-np-bundle-unit').val() || '').trim(),
        item: String($card.find('.edit-np-bundle-asset').val() || '').trim(),
        model: String($card.find('.edit-np-bundle-brand').val() || ''),
        description: String($card.find('.edit-np-bundle-description').val() || ''),
        par_ics_number: String($card.find('.edit-np-bundle-par-ics-value').val() || '').trim()
      });
      $card.find('.edit-np-bundle-property-preview').val(npDetailBundlePropertyPreviewText(normalized));
    }

    function npDetailRefreshBundleParIcsNumbers() {
      var codeByCategory = {};
      $.each(npDetailState.items, function (_, item) {
        var category = String(item.category || '').trim().toUpperCase();
        var code = String(item.par_ics_number || '').trim().toUpperCase();
        if (category && code && !codeByCategory[category]) {
          codeByCategory[category] = code;
        }
      });
      $.each(npDetailState.bundles || [], function (_, bundle) {
        var category = String(bundle.category || '').trim().toUpperCase();
        var code = String(bundle.par_ics_number || '').trim().toUpperCase();
        if (category && code && !codeByCategory[category]) {
          codeByCategory[category] = code;
        }
      });

      var pendingCategories = [];
      $('#editNpBundleRows .edit-np-bundle-card').each(function () {
        var $card = $(this);
        var category = String($card.find('.edit-np-bundle-category').val() || '').trim().toUpperCase();
        if (!category) {
          npDetailSetBundleParIcsPreview($card, '');
          return;
        }
        if (codeByCategory[category]) {
          npDetailSetBundleParIcsPreview($card, codeByCategory[category]);
          return;
        }
        if (npDetailBundleParIcsCache[category]) {
          codeByCategory[category] = npDetailBundleParIcsCache[category];
          npDetailSetBundleParIcsPreview($card, codeByCategory[category]);
          return;
        }
        if ($.inArray(category, pendingCategories) === -1) {
          pendingCategories.push(category);
        }
      });

      $.each(pendingCategories, function (_, category) {
        $.ajax({
          url: '../auth/auth.php',
          type: 'POST',
          cache: false,
          dataType: 'json',
          data: {
            generate_par_ics_code: 1,
            category: category,
            condition: 'NEW'
          },
          success: function (resp) {
            var code = (resp && Number(resp.status) === 200 && resp.code) ? String(resp.code).trim().toUpperCase() : '';
            if (!code) { return; }
            npDetailBundleParIcsCache[category] = code;
            $('#editNpBundleRows .edit-np-bundle-card').each(function () {
              var $card = $(this);
              if (String($card.find('.edit-np-bundle-category').val() || '').trim().toUpperCase() === category) {
                npDetailSetBundleParIcsPreview($card, code);
              }
            });
          }
        });
      });
    }

    function npDetailBuildBundleCard(bundle, index) {
      var normalized = npDetailNormalizeBundle(bundle);
      return ''
        + '<div class="border rounded p-3 mb-2 edit-np-bundle-card" data-bundle-key="' + npDetailEsc(normalized.key) + '" data-bundle-id="' + npDetailEsc(normalized.id) + '">'
        + '  <div class="d-flex justify-content-between align-items-center mb-3">'
        + '    <strong>Bundle Equipment ' + (index + 1) + '</strong>'
        + '    <button type="button" class="btn btn-sm btn-outline-danger edit-np-remove-bundle" data-bundle-key="' + npDetailEsc(normalized.key) + '"><i class="fas fa-trash"></i></button>'
        + '  </div>'
        + '  <div class="form-row">'
        + '    <div class="form-group col-md-2">'
        + '      <label>Bundle Set</label>'
        + '      <select class="form-control edit-np-bundle-set">' + npDetailBundleSetOptionsHtml(normalized.set_index) + '</select>'
        + '    </div>'
        + '    <div class="form-group col-md-2">'
        + '      <label>Category</label>'
        + '      <select class="form-control edit-np-bundle-category">'
        + '        <option value="">-SELECT-</option>'
        + '        <option value="PAR"' + (normalized.category === 'PAR' ? ' selected' : '') + '>PAR</option>'
        + '        <option value="ICS"' + (normalized.category === 'ICS' ? ' selected' : '') + '>ICS</option>'
        + '      </select>'
        + '    </div>'
        + '    <div class="form-group col-md-2">'
        + '      <label>Unit</label>'
        + '      <select class="form-control edit-np-bundle-unit">' + npDetailSelectedOptions('#editNpItemUnitOptionsTemplate', normalized.unit) + '</select>'
        + '    </div>'
        + '    <div class="form-group col-md-3">'
        + '      <label>Asset Class</label>'
        + '      <select class="form-control edit-np-bundle-asset">' + npDetailSelectedOptions('#editNpItemAssetOptionsTemplate', normalized.item) + '</select>'
        + '    </div>'
        + '    <div class="form-group col-md-3">'
        + '      <div class="d-flex align-items-center justify-content-between mb-2">'
        + '        <label class="mb-0">Brand/Model</label>'
        + '        <div class="form-check form-check-inline mb-0 text-muted">'
        + '          <input type="checkbox" class="form-check-input edit-np-bundle-no-brand"' + (normalized.no_brand ? ' checked' : '') + '>'
        + '          <label class="form-check-label">none</label>'
        + '        </div>'
        + '      </div>'
        + '      <input type="text" class="form-control text-uppercase edit-np-bundle-brand" value="' + npDetailEsc(normalized.model) + '" placeholder="Enter brand/model">'
        + '    </div>'
        + '  </div>'
        + '  <div class="form-row">'
        + '    <div class="form-group col-12">'
        + '      <div class="d-flex align-items-center justify-content-between mb-2">'
        + '        <label class="mb-0">Description</label>'
        + '        <div class="form-check form-check-inline mb-0 text-muted">'
        + '          <input type="checkbox" class="form-check-input edit-np-bundle-add-serial"' + (normalized.add_serial ? ' checked' : '') + '>'
        + '          <label class="form-check-label">add serial number</label>'
        + '        </div>'
        + '      </div>'
        + '      <textarea class="form-control text-uppercase edit-np-bundle-description" rows="4" placeholder="Enter description">' + npDetailEsc(normalized.description) + '</textarea>'
        + '    </div>'
        + '  </div>'
        + '  <div class="form-row edit-np-bundle-serial-row"' + (normalized.add_serial ? '' : ' style="display:none;"') + '>'
        + '    <div class="form-group col-12">'
        + '      <div class="edit-np-bundle-serial-table-wrap">' + npDetailBuildBundleSerialRows(normalized) + '</div>'
        + '    </div>'
        + '  </div>'
        + '  <div class="form-row">'
        + '    <div class="form-group col-md-6">'
        + '      <label>PAR/ICS No.</label>'
        + '      <input type="hidden" class="edit-np-bundle-par-ics-value" value="' + npDetailEsc(normalized.par_ics_number || '') + '">'
        + '      <textarea class="form-control edit-np-bundle-par-ics-preview" rows="3" readonly>' + npDetailEsc(normalized.par_ics_number || '') + '</textarea>'
        + '    </div>'
        + '    <div class="form-group col-md-6">'
        + '      <label>Property No.</label>'
        + '      <textarea class="form-control edit-np-bundle-property-preview" rows="3" readonly>' + npDetailEsc(npDetailBundlePropertyPreviewText(normalized)) + '</textarea>'
        + '    </div>'
        + '  </div>'
        + '</div>';
    }

    function npDetailRenderBundleRows() {
      var $wrap = $('#editNpBundleRows');
      if (!$wrap.length) { return; }
      npDetailState.bundles = $.map(npDetailState.bundles || [], function (bundle) {
        return npDetailNormalizeBundle(bundle);
      });
      if (!npDetailState.bundles.length) {
        $wrap.html('<div class="text-center text-muted py-4">No bundle equipment found.</div>');
        return;
      }

      var html = '';
      $.each(npDetailState.bundles, function (index, bundle) {
        html += npDetailBuildBundleCard(bundle, index);
      });

      $wrap.html(html);
      $wrap.find('.edit-np-bundle-card').each(function () {
        npDetailApplyBundleNoBrandState($(this));
        npDetailApplyBundleSerialVisibility($(this));
        npDetailRefreshBundlePreviewFields($(this));
      });
      npDetailRefreshBundleParIcsNumbers();
    }

    function npDetailHasSerialValues(item) {
      var serials1 = $.isArray(item.serial_numbers) ? item.serial_numbers : [String(item.serial_number || '')];
      var serials2 = $.isArray(item.serial_numbers_2) ? item.serial_numbers_2 : [String(item.serial_number_2 || '')];
      var hasPrimary = $.grep(serials1, function (value) {
        return String(value || '').trim() !== '';
      }).length > 0;
      var hasSecondary = $.grep(serials2, function (value) {
        return String(value || '').trim() !== '';
      }).length > 0;
      return hasPrimary || hasSecondary;
    }

    function npDetailApplySerialVisibilityState($card, animate) {
      var $serialRow = $card.find('.edit-np-serial-row');
      var $toggle = $card.find('.edit-np-add-serial');
      var showSerial = $toggle.is(':checked');
      if (!$serialRow.length || !$toggle.length) { return; }

      $serialRow.stop(true, true);
      if (showSerial) {
        if (animate && !$serialRow.is(':visible')) {
          $serialRow
            .css({ display: 'flex', overflow: 'hidden', opacity: 0 })
            .hide()
            .slideDown({
              duration: 280,
              queue: false,
              complete: function () {
                $serialRow.css({ display: 'flex', overflow: '', opacity: '' });
              }
            });
          $serialRow.animate({ opacity: 1 }, { duration: 280, queue: false });
        } else {
          $serialRow.css({ display: 'flex', overflow: '', opacity: '' });
        }
      } else if (animate && $serialRow.is(':visible')) {
        $serialRow.animate({ opacity: 0 }, { duration: 240, queue: false });
        $serialRow.slideUp({
          duration: 240,
          queue: false,
          complete: function () {
            $serialRow.css({ overflow: '', opacity: '' });
          }
        });
      } else {
        $serialRow.hide().css({ overflow: '', opacity: '' });
      }

      $serialRow.find('.edit-np-serial-primary, .edit-np-serial-secondary').prop('disabled', !showSerial);
    }

    function npDetailSyncTotalAmount($input) {
      var $card = $input.closest('.item-set-card');
      var $total = $card.find('.edit-np-total-amount');
      if (!$total.length) { return; }
      var quantity = npDetailNormalizeItemQuantity($card.find('.edit-np-item-quantity').val());
      var unitValue = (parseFloat(String($input.val() || '').replace(/,/g, '')) || 0) * quantity;
      $total.val(npDetailFormatMoney(unitValue, true));
    }

    function npDetailApplyNoBrandState($card) {
      var $brand = $card.find('.edit-np-brand');
      var $toggle = $card.find('.edit-np-no-brand');
      if (!$brand.length || !$toggle.length) { return; }

      if ($toggle.is(':checked')) {
        if ($brand.data('prevUserValueCaptured') !== true) {
          $brand.data('prevUserValue', String($brand.val() || ''));
          $brand.data('prevUserValueCaptured', true);
        }
        $brand.val('NO BRAND/MODEL').prop('readonly', true).prop('required', false);
        return;
      }

      if (String($brand.val() || '').trim().toUpperCase() === 'NO BRAND/MODEL') {
        $brand.val(String($brand.data('prevUserValue') || ''));
      }
      $brand.prop('readonly', false).prop('required', true);
      $brand.data('prevUserValueCaptured', false);
    }

    function npDetailApplyNoAmountState($card) {
      var $input = $card.find('.edit-np-unit-value');
      var $toggle = $card.find('.edit-np-no-amount');
      if (!$input.length || !$toggle.length) { return; }

      if ($toggle.is(':checked')) {
        if (!String($input.val() || '').trim()) {
          $input.val('0.00');
        }
        $input.prop('readonly', true).prop('required', false);
      } else {
        $input.prop('readonly', false).prop('required', true);
      }

      npDetailSyncTotalAmount($input);
    }

    function npDetailApplyNoAccountPropertyState($card) {
      var $toggle = $card.find('.edit-np-no-account-property');
      var $account = $card.find('.edit-np-account-code');
      if (!$toggle.length || !$account.length) { return; }

      if ($toggle.is(':checked')) {
        $account.val('').prop('disabled', true).prop('required', false);
      } else {
        $account.prop('disabled', false).prop('required', !npDetailPropertyOptionalFund());
      }
    }

    function npDetailSetParIcs(itemId, value) {
      var $card = $('#editNpItemRows .item-set-card[data-item-id="' + itemId + '"]');
      var code = String(value || '').trim();
      var item = npDetailFindItem(itemId);
      if (item) {
        item.par_ics_number = code;
      }
      $card.find('.edit-np-par-ics-value').val(code);
      $card.find('.edit-np-par-ics-preview').val(code);
    }

    function npDetailRefreshParIcsNumbers() {
      if (!npDetailState.items.length) { return; }

      var codeByCategory = {};
      $.each(npDetailState.items, function (_, item) {
        var category = String(item.category || '').trim().toUpperCase();
        var code = String(item.par_ics_number || '').trim().toUpperCase();
        if (category && code && !codeByCategory[category]) {
          codeByCategory[category] = code;
        }
      });

      var pendingCategories = [];
      $('#editNpItemRows .item-set-card').each(function () {
        var $card = $(this);
        var itemId = String($card.data('itemId') || '');
        var category = String($card.find('.edit-np-item-category').val() || '').trim().toUpperCase();
        var item = npDetailFindItem(itemId);
        if (item) {
          item.category = category;
        }

        if (!category) {
          npDetailSetParIcs(itemId, '');
        } else if (codeByCategory[category]) {
          npDetailSetParIcs(itemId, codeByCategory[category]);
        } else if ($.inArray(category, pendingCategories) === -1) {
          pendingCategories.push(category);
        }
      });

      $.each(pendingCategories, function (_, category) {
        $.ajax({
          url: '../auth/auth.php',
          type: 'POST',
          cache: false,
          dataType: 'json',
          data: {
            generate_par_ics_code: 1,
            category: category,
            condition: 'NEW'
          },
          success: function (resp) {
            var code = (resp && Number(resp.status) === 200 && resp.code) ? String(resp.code).trim().toUpperCase() : '';
            if (!code) { return; }
            codeByCategory[category] = code;
            $('#editNpItemRows .item-set-card').each(function () {
              var $card = $(this);
              var itemId = String($card.data('itemId') || '');
              if (String($card.find('.edit-np-item-category').val() || '').trim().toUpperCase() === category) {
                npDetailSetParIcs(itemId, code);
              }
            });
          }
        });
      });
    }

    function npDetailSelectedOptions(templateSelector, selectedValue) {
      var $wrap = $('<select>' + String($(templateSelector).html() || '<option value="">-SELECT-</option>') + '</select>');
      var selectedText = String(selectedValue || '').trim();
      if (selectedText) {
        var hasOption = $wrap.find('option').filter(function () {
          return String(this.value || '').trim() === selectedText;
        }).length > 0;
        if (!hasOption) {
          $('<option>', {
            value: selectedText,
            text: selectedText
          }).attr('data-current-value', '1').insertAfter($wrap.find('option:first'));
        }
      }
      $wrap.find('option').prop('selected', false).removeAttr('selected');
      $wrap.find('option').filter(function () {
        return String(this.value || '').trim() === selectedText;
      }).prop('selected', true).attr('selected', 'selected');
      return $wrap.html();
    }

    function npDetailEmployeeOptionsHtml(selectedValue, selectedLabel) {
      var selectedText = String(selectedValue || '').trim();
      var labelText = String(selectedLabel || selectedText).trim();
      var $wrap = $('<select>' + String(npDetailState.employeeOptionsHtml || '<option value="">-SELECT-</option>') + '</select>');

      if (selectedText) {
        var hasOption = $wrap.find('option').filter(function () {
          return String(this.value || '').trim() === selectedText;
        }).length > 0;
        if (!hasOption) {
          $('<option>', {
            value: selectedText,
            text: labelText || selectedText
          }).attr('data-current-value', '1').insertAfter($wrap.find('option:first'));
        }
      }

      $wrap.find('option').prop('selected', false).removeAttr('selected');
      $wrap.find('option').filter(function () {
        return String(this.value || '').trim() === selectedText;
      }).prop('selected', true).attr('selected', 'selected');
      return $wrap.html();
    }

    function npDetailGetPrimaryEmployee() {
      if (!npDetailState.items.length) {
        return { value: '', label: '' };
      }

      var firstItem = npDetailState.items[0];
      return {
        value: String(firstItem.emp_id || '').trim(),
        label: String(firstItem.emp_name || firstItem.emp_id || '').trim()
      };
    }

    function npDetailFindItem(itemKey) {
      itemKey = String(itemKey || '').trim();
      for (var i = 0; i < npDetailState.items.length; i++) {
        if (String(npDetailState.items[i].key || '') === itemKey) {
          return npDetailState.items[i];
        }
      }
      return null;
    }

    function npDetailCurrentDeptCode() {
      return String($('#edit_np_dept').val() || '').trim();
    }

    function npDetailPopulateDeptDatalist() {
      var $deptSelect = $('#edit_np_dept');
      var $list = $('#editNpDeptDatalist');
      $list.empty();
      $deptSelect.find('option').each(function () {
        var value = String(this.value || '').trim();
        var text = String($(this).text() || '').trim();
        if (!value || !text) { return; }
        $('<option>').attr('value', text).appendTo($list);
      });
    }

    function npDetailLoadDepartmentsForFund(fund, selectedDept, done) {
      fund = String(fund || '').trim();
      selectedDept = String(selectedDept || '').trim();
      var $deptSelect = $('#edit_np_dept');
      var $deptInput = $('#editNpDeptSearch');
      var cacheKey = fund.toUpperCase();

      $deptSelect.html('<option value="">-SELECT-</option>').val('');
      $deptInput.val('');
      npDetailPopulateDeptDatalist();

      if (!fund) {
        if (typeof done === 'function') { done(false); }
        return;
      }

      if (npDetailDepartmentOptionsCache[cacheKey]) {
        $deptSelect.html(npDetailDepartmentOptionsCache[cacheKey]);
        if (selectedDept) {
          $deptSelect.val(selectedDept);
        }
        npDetailPopulateDeptDatalist();
        npDetailSyncDeptInput();
        if (typeof done === 'function') { done(true); }
        return;
      }

      $.ajax({
        url: '../auth/auth.php',
        type: 'POST',
        data: { fund_for_departments: fund },
        success: function (html) {
          var optionsHtml = html || '<option value="">-SELECT-</option>';
          npDetailDepartmentOptionsCache[cacheKey] = optionsHtml;
          $deptSelect.html(optionsHtml);
          if (selectedDept) {
            $deptSelect.find('option').each(function () {
              if (String(this.value || '').trim() === selectedDept) {
                $deptSelect.val(selectedDept);
                return false;
              }
            });
          }
          npDetailPopulateDeptDatalist();
          npDetailSyncDeptInput();
          if (typeof done === 'function') { done(true); }
        },
        error: function () {
          $deptSelect.html('<option value="">-SELECT-</option>');
          npDetailPopulateDeptDatalist();
          if (typeof done === 'function') { done(false); }
        }
      });
    }

    function npDetailSyncDeptInput() {
      var $deptSelect = $('#edit_np_dept');
      var selectedText = $deptSelect.val() ? $deptSelect.find('option:selected').text().trim() : '';
      $('#editNpDeptSearch').val(selectedText);
    }

    function npDetailFindDeptCodeByName(name) {
      var target = String(name || '').trim().toLowerCase();
      var match = '';
      $('#edit_np_dept option').each(function () {
        if ($(this).text().trim().toLowerCase() === target) {
          match = String(this.value || '').trim();
          return false;
        }
      });
      return match;
    }

    function npDetailResetEmployeeSelection() {
      $.each(npDetailState.items, function (_, item) {
        item.emp_id = '';
        item.emp_name = '';
      });
      $('#edit_np_emp_single').val('');
      $('#edit_np_new_emp').val('').prop('disabled', true);
      $('#edit_np_position').val('').prop('disabled', true);
      $('#edit_np_add_new_employee').hide();
    }

    function npDetailResetDepartmentAndEmployeeSelection() {
      $('#edit_np_dept').val('');
      $('#editNpDeptSearch').val('');
      $('#editNpMultipleEndUserCheckBox').prop('checked', false);
      npDetailState.useMultipleEndUsers = false;
      npDetailState.employeeOptionsHtml = '<option value="">SELECT A DEPARTMENT FIRST</option>';
      npDetailResetEmployeeSelection();
      npDetailRenderEndUserRows();
    }

    function npDetailRenderEndUserRows() {
      var $single = $('#edit_np_emp_single');
      var $multiWrap = $('#editNpEndUserRows');
      var $multiToggle = $('#editNpMultipleEndUserCheckBox');
      var $newEmpSection = $('#edit_np_add_new_employee');
      var useMultiple = npDetailState.items.length > 1 && npDetailState.useMultipleEndUsers;

      if (!npDetailState.items.length) {
        $single.hide().removeAttr('name').prop('disabled', true);
        $multiWrap.hide().empty();
        $multiToggle.prop('checked', false).prop('disabled', true);
        $newEmpSection.hide();
        return;
      }

      if (!useMultiple) {
        var singleItem = npDetailState.items[0];
        var singleItemKey = String(singleItem.key || '');
        var primaryEmployee = npDetailGetPrimaryEmployee();
        var singleValue = primaryEmployee.value;
        var singleLabel = primaryEmployee.label;

        $multiToggle.prop('checked', false).prop('disabled', npDetailState.items.length <= 1);
        $multiWrap.hide().empty();
        $single
          .html(npDetailEmployeeOptionsHtml(singleValue, singleLabel))
          .attr('name', npDetailState.items.length === 1 ? ('emp_id[' + singleItemKey + ']') : '')
          .attr('data-item-id', npDetailState.items.length === 1 ? singleItemKey : '')
          .prop('disabled', false)
          .show();

        $('#edit_np_new_emp')
          .attr('name', npDetailState.items.length === 1 ? ('emp_new_name[' + singleItemKey + ']') : '')
          .prop('disabled', String($single.val() || '').toLowerCase() !== 'add_new_emp')
          .val('');
        $('#edit_np_position')
          .attr('name', npDetailState.items.length === 1 ? ('emp_new_position[' + singleItemKey + ']') : '')
          .prop('disabled', String($single.val() || '').toLowerCase() !== 'add_new_emp')
          .val('');

        if (String($single.val() || '').toLowerCase() === 'add_new_emp') {
          $newEmpSection.show();
        } else {
          $newEmpSection.hide();
        }
        return;
      }

      $multiToggle.prop('checked', true).prop('disabled', false);
      $single.hide().removeAttr('name').prop('disabled', true);
      $newEmpSection.hide();

      var html = '<table class="table table-sm table-bordered mb-0">'
        + '<thead class="bg-light">'
        + '<tr>'
        + '<th style="width:90px;">Set</th>'
        + '<th>End User</th>'
        + '<th>New Employee</th>'
        + '<th>Position</th>'
        + '</tr>'
        + '</thead><tbody>';

      $.each(npDetailState.items, function (_, item) {
        var itemKey = String(item.key || '');
        var empValue = String(item.emp_id || '').trim();
        html += ''
          + '<tr data-item-id="' + npDetailEsc(itemKey) + '">'
          + '  <td class="align-middle font-weight-bold">Set ' + npDetailEsc(item.set_no) + '</td>'
          + '  <td>'
          + '    <select class="form-control edit-np-emp-select" name="emp_id[' + npDetailEsc(itemKey) + ']" data-item-id="' + npDetailEsc(itemKey) + '">'
          +        npDetailEmployeeOptionsHtml(empValue, item.emp_name || empValue)
          + '    </select>'
          + '  </td>'
          + '  <td>'
          + '    <input type="text" class="form-control text-uppercase edit-np-emp-new-name" name="emp_new_name[' + npDetailEsc(itemKey) + ']" data-item-id="' + npDetailEsc(itemKey) + '" placeholder="Enter new employee name" disabled>'
          + '  </td>'
          + '  <td>'
          + '    <input type="text" class="form-control text-uppercase edit-np-emp-new-position" name="emp_new_position[' + npDetailEsc(itemKey) + ']" data-item-id="' + npDetailEsc(itemKey) + '" placeholder="Enter position" disabled>'
          + '  </td>'
          + '</tr>';
      });

      html += '</tbody></table>';
      $multiWrap.html(html).show();
      $multiWrap.find('select.edit-np-emp-select').each(function () {
        npDetailToggleNewEmployeeRow($(this));
      });
    }

    function npDetailRenderItemRows(options) {
      var opts = options || {};
      var $wrap = $('#editNpItemRows');
      if (!npDetailState.items.length) {
        $wrap.html('<div class="text-center text-muted py-4">No items found.</div>');
        $('#edit_np_set_count').val(0);
        return;
      }

      var html = '';
      $.each(npDetailState.items, function (_, item) {
        var itemId = parseInt(item.id, 10) || 0;
        var itemKey = String(item.key || '');
        var hasPersistedIds = $.grep($.isArray(item.existing_item_ids) ? item.existing_item_ids : [itemId], function (value) {
          return (parseInt(value, 10) || 0) > 0;
        }).length > 0;
        html += ''
          + '<div class="border rounded p-3 item-set-card" data-item-id="' + npDetailEsc(itemKey) + '">'
          + '  <input type="hidden" name="set_keys[]" value="' + npDetailEsc(itemKey) + '">'
          + '  <input type="hidden" name="existing_item_id[' + npDetailEsc(itemKey) + ']" value="' + itemId + '">'
          + '  <input type="hidden" name="existing_item_ids[' + npDetailEsc(itemKey) + ']" value="' + npDetailEsc(($.isArray(item.existing_item_ids) ? item.existing_item_ids : [itemId]).join(',')) + '">'
          + '  <div class="d-flex justify-content-between align-items-center mb-3">'
          + '    <strong>Set ' + npDetailEsc(item.set_no) + '</strong>'
          + '    <button type="button" class="btn btn-sm btn-outline-danger edit-np-remove-set" data-item-id="' + npDetailEsc(itemKey) + '"' + (npDetailState.items.length === 1 ? ' style="visibility:hidden;"' : '') + '><i class="fas fa-trash"></i></button>'
          + '  </div>'
          + '  <div class="form-row">'
          + '    <div class="form-group col-md-2">'
          + '      <label>Quantity</label>'
          + '      <input type="number" class="form-control edit-np-item-quantity" name="item_quantity[' + npDetailEsc(itemKey) + ']" data-item-id="' + npDetailEsc(itemKey) + '" value="' + npDetailEsc(item.item_quantity || 1) + '" min="1" step="1">'
          + '    </div>'
          + '    <div class="form-group col-md-2">'
          + '      <label>Category</label>'
          + '      <input type="hidden" class="edit-np-item-category-value" name="category[' + npDetailEsc(itemKey) + ']" value="' + npDetailEsc(String(item.category || '').trim().toUpperCase()) + '">'
          + '      <select class="form-control edit-np-item-category" data-item-id="' + npDetailEsc(itemKey) + '" required' + (hasPersistedIds ? ' disabled' : '') + '>'
          + '        <option value="">-SELECT-</option>'
          + '        <option value="PAR"' + (String(item.category || '').trim().toUpperCase() === 'PAR' ? ' selected' : '') + '>PAR</option>'
          + '        <option value="ICS"' + (String(item.category || '').trim().toUpperCase() === 'ICS' ? ' selected' : '') + '>ICS</option>'
          + '      </select>'
          + '    </div>'
          + '    <div class="form-group col-md-2">'
          + '      <label>Unit</label>'
          + '      <select class="form-control" name="unit[' + npDetailEsc(itemKey) + ']" required>'
          +          npDetailSelectedOptions('#editNpItemUnitOptionsTemplate', item.unit)
          + '      </select>'
          + '    </div>'
          + '    <div class="form-group col-md-3">'
          + '      <label>Asset Class</label>'
          + '      <select class="form-control" name="item[' + npDetailEsc(itemKey) + ']" required>'
          +          npDetailSelectedOptions('#editNpItemAssetOptionsTemplate', item.item)
          + '      </select>'
          + '    </div>'
          + '    <div class="form-group col-md-3">'
          + '      <div class="d-flex align-items-center justify-content-between mb-2">'
          + '        <label class="mb-0">Brand/Model</label>'
          + '        <div class="form-check form-check-inline mb-0 text-muted">'
          + '          <input type="checkbox" class="form-check-input edit-np-no-brand" name="item_no_brand[' + npDetailEsc(itemKey) + ']" value="1"' + (item.no_brand ? ' checked' : '') + '>'
          + '          <label class="form-check-label">none</label>'
          + '        </div>'
          + '      </div>'
          + '      <input type="text" class="form-control text-uppercase edit-np-brand" name="model[' + npDetailEsc(itemKey) + ']" value="' + npDetailEsc(item.model) + '" placeholder="Enter Brand and model" required>'
          + '    </div>'
          + '  </div>'
          + '  <div class="form-row">'
          + '    <div class="form-group col-md-12">'
          + '      <div class="d-flex align-items-center justify-content-between mb-2">'
          + '        <label class="mb-0">Description</label>'
          + '        <div class="form-check form-check-inline mb-0 text-muted">'
          + '          <input type="checkbox" class="form-check-input edit-np-add-serial" name="item_add_serial[' + npDetailEsc(itemKey) + ']" value="1"' + (item.add_serial ? ' checked' : '') + '>'
          + '          <label class="form-check-label">add serial number</label>'
          + '        </div>'
          + '      </div>'
          + '      <textarea class="form-control text-uppercase" name="description[' + npDetailEsc(itemKey) + ']" rows="4" placeholder="Enter description">' + npDetailEsc(item.description) + '</textarea>'
          + '    </div>'
          + '  </div>'
          + '  <div class="form-row edit-np-serial-row"' + (item.add_serial ? '' : ' style="display:none;"') + '>'
          + '    <div class="form-group col-12">'
          + '      <div class="edit-np-serial-table-wrap">' + npDetailBuildSerialRows(item) + '</div>'
          + '    </div>'
          + '  </div>'
          + '  <div class="form-row">'
          + '    <div class="form-group col-md-3">'
          + '      <label>Unit Value <span class="text-muted">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;'
          + '        <input type="checkbox" class="form-check-input edit-np-no-amount" name="item_no_amount[' + npDetailEsc(itemKey) + ']" value="1"' + (item.no_amount ? ' checked' : '') + '>'
          + '        <label class="form-check-label">no amount value</label>'
          + '      </span></label>'
          + '      <input type="text" class="form-control text-right edit-np-unit-value" name="unit_value[' + npDetailEsc(itemKey) + ']" value="' + npDetailEsc(npDetailFormatMoney(item.unit_value, true)) + '" placeholder="0.00" autocomplete="off" required>'
          + '      <div class="text-danger edit-np-unitvalue-error" style="display:none;font-size:13px;"></div>'
          + '    </div>'
          + '    <div class="form-group col-md-3">'
          + '      <label>Total Amount</label>'
          + '      <input type="text" class="form-control edit-np-total-amount" value="' + npDetailEsc(npDetailFormatMoney((Number(item.unit_value || 0) * Number(item.item_quantity || 1)), true)) + '" readonly>'
          + '    </div>'
          + '    <div class="form-group col-md-6">'
          + '      <div class="d-flex align-items-center justify-content-between mb-2">'
          + '        <label class="mb-0">Account Code</label>'
          + '        <div class="form-check form-check-inline mb-0 text-muted">'
          + '          <input type="checkbox" class="form-check-input edit-np-no-account-property" name="item_no_account_property[' + npDetailEsc(itemKey) + ']" value="1"' + (item.no_account_property ? ' checked' : '') + '>'
          + '          <label class="form-check-label">none</label>'
          + '        </div>'
          + '      </div>'
          + '      <select class="form-control edit-np-account-code" name="account_code[' + npDetailEsc(itemKey) + ']" data-item-id="' + npDetailEsc(itemKey) + '">'
          +          npDetailSelectedOptions('#editNpItemAccountCodeOptionsTemplate', item.account_code)
          + '      </select>'
          + '    </div>'
          + '  </div>'
          + '  <div class="form-row mb-0">'
          + '    <div class="form-group col-md-6">'
          + '      <label>PAR/ICS No.</label>'
          + '      <input type="hidden" name="par_ics_number_preview_value[' + npDetailEsc(itemKey) + ']" class="edit-np-par-ics-value" value="' + npDetailEsc(item.par_ics_number || '') + '">'
          + '      <textarea class="form-control edit-np-par-ics-preview" rows="3" readonly>' + npDetailEsc(item.par_ics_number || '') + '</textarea>'
          + '    </div>'
          + '    <div class="form-group col-md-6">'
          + '      <label>Property Number</label>'
          + '      <input type="hidden" name="property_number[' + npDetailEsc(itemKey) + ']" class="edit-np-property-value" value="' + npDetailEsc(item.property_number) + '">'
          + '      <textarea class="form-control edit-np-property-preview" rows="3" readonly>' + npDetailEsc(item.property_number_preview || item.property_number) + '</textarea>'
          + '    </div>'
          + '  </div>'
          + '  <div class="form-row mb-0">'
          + '    <div class="form-group col-md-12 mb-0">'
          + '      <label>Remarks</label>'
          + '      <textarea class="form-control text-uppercase" name="remarks[' + npDetailEsc(itemKey) + ']" rows="3" placeholder="Enter remarks">' + npDetailEsc(item.remarks) + '</textarea>'
          + '    </div>'
          + '  </div>'
          + '</div>';
      });

      $wrap.html(html);
      $wrap.find('.item-set-card').each(function () {
        npDetailApplySerialVisibilityState($(this), false);
        npDetailApplyNoBrandState($(this));
        npDetailApplyNoAmountState($(this));
        npDetailApplyNoAccountPropertyState($(this));
      });
      $('#edit_np_set_count').val(npDetailState.items.length);
      if (!opts.skipParIcsRefresh) {
        npDetailRefreshParIcsNumbers();
      }
    }

    function npDetailSetProperty(itemId, value, message, type) {
      var $card = $('#editNpItemRows .item-set-card[data-item-id="' + itemId + '"]');
      var $hidden = $card.find('.edit-np-property-value');
      var $preview = $card.find('.edit-np-property-preview');
      var quantity = npDetailNormalizeItemQuantity($card.find('.edit-np-item-quantity').val());
      var firstValue = String(value || '').trim();
      var previewValue = npDetailPropertyCopies(firstValue, quantity).join(', ');
      var item = npDetailFindItem(itemId);

      if (item) {
        item.property_number = firstValue;
        item.property_number_preview = previewValue;
      }

      $hidden.val(firstValue);
      $preview.val(previewValue);
      npDetailSnapshotBundleRows();
      npDetailRenderBundleRows();
    }

    function npDetailIsUsablePropertyNumber(value) {
      var text = String(value || '').trim().toUpperCase();
      return text !== '' && text !== 'GENERATING...';
    }

    function npDetailCollectReservedPropertyNumbers(exceptItemId) {
      var seen = {};
      var reserved = [];
      var exceptKey = String(exceptItemId || '').trim();

      $('#editNpItemRows .item-set-card').each(function () {
        var $card = $(this);
        var itemId = String($card.data('itemId') || '').trim();
        if (exceptKey && itemId === exceptKey) {
          return;
        }
        if ($card.find('.edit-np-no-account-property').is(':checked')) {
          return;
        }

        var firstValue = String($card.find('.edit-np-property-value').val() || '').trim();
        if (!npDetailIsUsablePropertyNumber(firstValue)) {
          return;
        }

        var quantity = npDetailNormalizeItemQuantity($card.find('.edit-np-item-quantity').val());
        $.each(npDetailPropertyCopies(firstValue, quantity), function (_, propertyNumber) {
          var candidate = String(propertyNumber || '').trim().toUpperCase();
          if (!candidate || seen[candidate]) {
            return;
          }
          seen[candidate] = true;
          reserved.push(candidate);
        });
      });

      return reserved;
    }

    function npDetailRequestPropertyNumber(itemId, item, $card, extraExclude, requestId, onDone) {
      var fund = npDetailNormalizeFund($('#edit_np_fund').val());
      var category = String($card.find('.edit-np-item-category').val() || '').trim().toUpperCase();
      var year = npDetailGetYear($('#edit_np_year').val());
      var dept = npDetailCurrentDeptCode();
      var accountCode = String($card.find('.edit-np-account-code').val() || '').trim();
      var originalYear = npDetailGetYear(item.original_year || item.year || '');
      var originalFund = npDetailNormalizeFund(item.original_fund || item.fund || '');
      var originalCategory = String(item.original_category || item.category || '').trim().toUpperCase();
      var originalDept = String(item.original_dept || item.dept || '').trim();
      var originalAccount = String(item.original_account_code || item.account_code || '').trim();
      var unchanged = fund === originalFund
        && category === originalCategory
        && year === originalYear
        && dept === originalDept
        && accountCode === originalAccount;
      var savedPropertyNumber = String(item.original_property_number || item.property_number || '').trim();
      var excludedNumbers = npDetailCollectReservedPropertyNumbers(itemId);

      $.each($.isArray(extraExclude) ? extraExclude : [], function (_, value) {
        var candidate = String(value || '').trim().toUpperCase();
        if (candidate && $.inArray(candidate, excludedNumbers) === -1) {
          excludedNumbers.push(candidate);
        }
      });

      if (npDetailPropertyOptionalFund()) {
        npDetailSetProperty(itemId, '', 'Property number is not generated for ' + fund + '.', 'info');
        if (typeof onDone === 'function') { onDone(''); }
        return;
      }

      if ($card.find('.edit-np-no-account-property').is(':checked')) {
        npDetailSetProperty(itemId, '', 'No property number.', 'info');
        if (typeof onDone === 'function') { onDone(''); }
        return;
      }

      if (unchanged && savedPropertyNumber) {
        npDetailSetProperty(itemId, savedPropertyNumber, 'Generated from account code.');
        if (typeof onDone === 'function') { onDone(savedPropertyNumber); }
        return;
      }

      if (!fund || !category || !year || !dept || !accountCode) {
        npDetailSetProperty(itemId, savedPropertyNumber, 'Select fund, category, year, department, and account code to generate a property number.', 'error');
        if (typeof onDone === 'function') { onDone(savedPropertyNumber); }
        return;
      }

      npDetailSetProperty(itemId, 'Generating...', 'Checking available property number...', 'info');
      $.ajax({
        url: '../auth/auth.php',
        type: 'POST',
        cache: false,
        dataType: 'json',
        data: {
          generate_new_purchase_edit_property_number: 1,
          new_purchase_id: itemId,
          property_number: item.original_property_number || item.property_number || '',
          category: category,
          year: year,
          account_code: accountCode,
          dept: dept,
          fund: fund,
          item_quantity: npDetailNormalizeItemQuantity($card.find('.edit-np-item-quantity').val()),
          exclude_property_numbers: excludedNumbers
        },
        success: function (resp) {
          if (requestId !== npDetailPropertyRequestId) { return; }
          if (resp && Number(resp.status) === 200 && resp.data && resp.data.property_number) {
            npDetailSetProperty(itemId, resp.data.property_number, 'Available property number generated.', 'success');
            if (typeof onDone === 'function') { onDone(resp.data.property_number); }
            return;
          }
          npDetailSetProperty(itemId, savedPropertyNumber, (resp && resp.message) ? resp.message : 'Unable to generate an available property number.', 'error');
          if (typeof onDone === 'function') { onDone(savedPropertyNumber); }
        },
        error: function () {
          if (requestId !== npDetailPropertyRequestId) { return; }
          npDetailSetProperty(itemId, savedPropertyNumber, 'Unable to check property number availability.', 'error');
          if (typeof onDone === 'function') { onDone(savedPropertyNumber); }
        }
      });
    }

    function npDetailRefreshProperty(itemId, extraExclude, onDone, requestIdOverride) {
      var item = npDetailFindItem(itemId);
      var $card = $('#editNpItemRows .item-set-card[data-item-id="' + itemId + '"]');
      if (!item || !$card.length) {
        if (typeof onDone === 'function') { onDone(''); }
        return;
      }

      var requestId = requestIdOverride || (++npDetailPropertyRequestId);
      npDetailRequestPropertyNumber(itemId, item, $card, extraExclude, requestId, onDone);
    }

    function npDetailRefreshAllProperties() {
      var cards = $('#editNpItemRows .item-set-card').toArray();
      var reserved = [];
      var batchRequestId = ++npDetailPropertyRequestId;

      function reserveCopies(firstValue, $card) {
        var value = String(firstValue || '').trim();
        if (!npDetailIsUsablePropertyNumber(value)) { return; }
        var quantity = npDetailNormalizeItemQuantity($card.find('.edit-np-item-quantity').val());
        $.each(npDetailPropertyCopies(value, quantity), function (_, propertyNumber) {
          var candidate = String(propertyNumber || '').trim().toUpperCase();
          if (candidate && $.inArray(candidate, reserved) === -1) {
            reserved.push(candidate);
          }
        });
      }

      function processNext(index) {
        if (batchRequestId !== npDetailPropertyRequestId) { return; }
        if (index >= cards.length) { return; }

        var $card = $(cards[index]);
        var itemId = String($card.data('itemId') || '').trim();
        npDetailRefreshProperty(itemId, reserved, function (firstValue) {
          if (batchRequestId !== npDetailPropertyRequestId) { return; }
          reserveCopies(firstValue, $card);
          processNext(index + 1);
        }, batchRequestId);
      }

      processNext(0);
    }

    function npDetailValidateUnitValues() {
      var year = npDetailGetYear($('#edit_np_year').val());
      var isValid = true;

      $('#editNpItemRows .item-set-card').each(function () {
        var $card = $(this);
        var $error = $card.find('.edit-np-unitvalue-error');
        var category = String($card.find('.edit-np-item-category').val() || '').trim().toUpperCase();
        var unitValue = parseFloat(String($card.find('.edit-np-unit-value').val() || '0').replace(/,/g, '')) || 0;
        var error = '';

        if ($card.find('.edit-np-no-amount').is(':checked')) {
          $error.hide().text('');
          return;
        }
        if (year === 'RFS' && unitValue !== 0) {
          error = 'Unit Value must be 0.00 for Found at Station.';
        } else if (category === 'ICS' && unitValue >= 50001) {
          error = 'Unit Value for ICS must be less than 50,000.00.';
        } else if (category === 'PAR' && unitValue < 50001) {
          error = 'Unit Value for PAR must be 50,000.00 or above.';
        }

        if (error) {
          $error.text(error).show();
          isValid = false;
        } else {
          $error.hide().text('');
        }
      });

      return isValid;
    }

    function npDetailToggleNewEmployeeRow($select) {
      var itemId = String($select.data('itemId') || '');
      var isAddNew = String($select.val() || '').toLowerCase() === 'add_new_emp';
      var $name = $('.edit-np-emp-new-name[data-item-id="' + itemId + '"]');
      var $position = $('.edit-np-emp-new-position[data-item-id="' + itemId + '"]');
      $name.prop('disabled', !isAddNew).prop('required', isAddNew);
      $position.prop('disabled', !isAddNew).prop('required', isAddNew);
      if (!isAddNew) {
        $name.val('');
        $position.val('');
      }
    }

    function npDetailLoadEmployees(deptCode, done) {
      deptCode = String(deptCode || '').trim();
      if (!deptCode) {
        npDetailState.employeeOptionsHtml = '<option value="">SELECT A DEPARTMENT FIRST</option>';
        npDetailRenderEndUserRows();
        if (typeof done === 'function') { done(); }
        return;
      }

      if (npDetailEmployeeOptionsCache[deptCode]) {
        npDetailState.employeeOptionsHtml = npDetailEmployeeOptionsCache[deptCode];
        npDetailRenderEndUserRows();
        if (typeof done === 'function') { done(); }
        return;
      }

      $.ajax({
        url: '../auth/auth.php',
        type: 'POST',
        data: { departmentid: deptCode },
        success: function (html) {
          npDetailState.employeeOptionsHtml = String(html || '<option value="">-SELECT-</option>');
          npDetailEmployeeOptionsCache[deptCode] = npDetailState.employeeOptionsHtml;
          npDetailRenderEndUserRows();
        },
        error: function () {
          npDetailState.employeeOptionsHtml = '<option value="">-SELECT-</option><option value="add_new_emp"> + ADD NEW EMPLOYEE </option>';
          npDetailRenderEndUserRows();
        },
        complete: function () {
          if (typeof done === 'function') { done(); }
        }
      });
    }

    function npDetailShouldRefreshDerivedValues() {
      var needsRefresh = false;
      $.each(npDetailState.items, function (_, item) {
        if (needsRefresh) { return false; }
        if (!String(item.par_ics_number || '').trim()) {
          needsRefresh = true;
          return false;
        }
        if (npDetailPropertyOptionalFund() || item.no_account_property) {
          return;
        }
        if (!String(item.property_number || '').trim()) {
          needsRefresh = true;
          return false;
        }
      });
      return needsRefresh;
    }

    function npDetailFillModal(data, options) {
      var opts = options || {};
      var group = data && data.group ? data.group : {};
      var items = data && data.items ? data.items : [];
      var bundles = data && data.bundles ? data.bundles : [];

      npDetailState.group = group;
      npDetailState.items = $.map(items, function (item) {
        var originalYear = npDetailGetYear(group.year || '');
        var originalFund = npDetailNormalizeFund(group.fund || '');
        var originalCategory = String(item.category || group.category || '').trim().toUpperCase();
        var originalDept = String(group.department_code || '').trim();
        var key = String(item.id || ('tmp_' + (++npDetailTempIndex)));
        var itemQuantity = npDetailNormalizeItemQuantity(item.item_quantity || 1);
        var propertyNumber = String(item.property_number || '');
        var serialNumbers = $.isArray(item.serial_numbers) ? item.serial_numbers : [String(item.serial_number || '')];
        var serialNumbers2 = $.isArray(item.serial_numbers_2) ? item.serial_numbers_2 : [String(item.serial_number_2 || '')];
        var propertyNumbers = $.isArray(item.property_numbers) ? item.property_numbers : [];
        var existingItemIds = $.isArray(item.existing_item_ids) ? item.existing_item_ids : [parseInt(item.id, 10) || 0];
        return $.extend({}, item, {
          key: key,
          item_quantity: itemQuantity,
          serial_numbers: serialNumbers,
          serial_numbers_2: serialNumbers2,
          add_serial: false,
          no_brand: String(item.model || '').trim().toUpperCase() === 'NO BRAND/MODEL',
          no_amount: Number(item.unit_value || 0) === 0,
          no_account_property: String(item.account_code || '').trim() === '',
          property_number: propertyNumber,
          property_numbers: propertyNumbers,
          existing_item_ids: existingItemIds,
          property_number_preview: String(item.property_number_preview || propertyNumbers.join(', ') || npDetailPropertyCopies(propertyNumber, itemQuantity).join(', ')),
          par_ics_number: String(item.par_ics_number || ''),
          original_property_number: String(item.property_number || ''),
          original_account_code: String(item.account_code || ''),
          original_year: originalYear,
          original_fund: originalFund,
          original_category: originalCategory,
          original_dept: originalDept,
          year: originalYear,
          fund: originalFund,
          category: originalCategory,
          dept: originalDept
        });
      }).map(function (item) {
        item.add_serial = npDetailHasSerialValues(item);
        return item;
      });
      npDetailState.bundles = $.map(bundles, function (bundle) {
        return npDetailNormalizeBundle($.extend({}, bundle, {
          key: String(bundle.id || ('bundle_' + (++npDetailTempIndex))),
          serial_numbers: $.isArray(bundle.serial_numbers) ? bundle.serial_numbers : [String(bundle.serial_number || '')],
          serial_numbers_2: $.isArray(bundle.serial_numbers_2) ? bundle.serial_numbers_2 : [String(bundle.serial_number_2 || '')]
        }));
      });

      $('#edit_np_group_po').val(group.po || '');
      $('#edit_np_group_row_id').val(group.row_id || '');
      $('#edit_np_year').val(npDetailGetYear(group.year || ''));
      $('#edit_np_fund').val(npDetailNormalizeFund(group.fund || ''));
      $('#edit_np_pr').val(group.purchase_request || '');
      $('#edit_np_supplier').val(group.supplier || '');
      $('#edit_np_po').val(group.po || '');
      $('#edit_np_obr').val(group.obr_number || '');
      $('#edit_np_jev').val(group.jev_number || '');
      $('#edit_np_dept').val(group.department_code || '');
      $('#editNpDetailPrintBtn').toggle(npDetailSourceContext() !== 'fund_inventory');
      $('#edit_np_fund').prop('disabled', npDetailSourceContext() === 'fund_inventory');
      $('#edit_np_set_count').prop('readonly', npDetailSourceContext() === 'fund_inventory');
      npDetailRenderItemRows({ skipParIcsRefresh: true });
      npDetailRenderBundleRows();
      if (npDetailSourceContext() === 'fund_inventory') {
        $('#editNpItemRows .edit-np-remove-set').hide();
        $('#editNpItemRows .edit-np-item-quantity').prop('readonly', true);
      }
      npDetailLoadDepartmentsForFund(group.fund || '', group.department_code || '', function () {
        npDetailLoadEmployees(String($('#edit_np_dept').val() || '').trim(), function () {
          if (!opts.skipDerivedRefresh || npDetailShouldRefreshDerivedValues()) {
            npDetailRefreshParIcsNumbers();
            npDetailRefreshAllProperties();
          }
        });
      });
    }

    function npDetailResetSetNumbers() {
      $.each(npDetailState.items, function (index, item) {
        item.set_no = index + 1;
      });
      $('#edit_np_set_count').val(npDetailState.items.length);
    }

    function npDetailCreateBlankItem() {
      var key = 'tmp_' + (++npDetailTempIndex);
      return {
        key: key,
        id: 0,
        set_no: npDetailState.items.length + 1,
        unit: '',
        item: '',
        model: '',
        description: '',
        serial_number: '',
        serial_number_2: '',
        serial_numbers: [''],
        serial_numbers_2: [''],
        add_serial: false,
        property_number: '',
        property_number_preview: '',
        unit_value: '0.00',
        account_code: '',
        remarks: '',
        emp_id: '',
        emp_name: '',
        item_quantity: 1,
        original_property_number: '',
        original_account_code: '',
        original_year: npDetailGetYear($('#edit_np_year').val()),
        original_fund: npDetailNormalizeFund($('#edit_np_fund').val()),
        original_category: '',
        original_dept: String($('#edit_np_dept').val() || '').trim(),
        year: npDetailGetYear($('#edit_np_year').val()),
        fund: npDetailNormalizeFund($('#edit_np_fund').val()),
        category: '',
        dept: String($('#edit_np_dept').val() || '').trim()
      };
    }

    function npDetailCurrentPrintCategories() {
      var categories = {};
      $.each(npDetailState.items || [], function (_, item) {
        var category = String(item.category || '').trim().toUpperCase();
        if (category === 'PAR' || category === 'ICS') {
          categories[category] = true;
        }
      });
      $('#editNpItemRows .edit-np-item-category').each(function () {
        var category = String($(this).val() || '').trim().toUpperCase();
        if (category === 'PAR' || category === 'ICS') {
          categories[category] = true;
        }
      });
      return categories;
    }

    function npDetailPrintCurrentGroup() {
      var group = npDetailState.group || {};
      var referenceNumber = String(group.reference_number || '').trim();
      var referenceNumbers = group.reference_numbers || {};
      var categories = npDetailCurrentPrintCategories();
      var parRefs = $.isArray(referenceNumbers.PAR) ? referenceNumbers.PAR : [];
      var icsRefs = $.isArray(referenceNumbers.ICS) ? referenceNumbers.ICS : [];

      if (!referenceNumber && !parRefs.length && !icsRefs.length) {
        Swal ? Swal.fire({ icon: 'warning', title: 'No reference number found for printing.' }) : alert('No reference number found for printing.');
        return;
      }

      var opened = 0;
      if (categories.PAR) {
        if (parRefs.length) {
          $.each(parRefs, function (_, ref) {
            window.open('printpar.php?refnumber=' + encodeURIComponent(String(ref || '').trim()), '_blank');
            opened++;
          });
        } else if (referenceNumber) {
          window.open('printpar.php?refnumber=' + encodeURIComponent(referenceNumber), '_blank');
          opened++;
        }
      }
      if (categories.ICS) {
        if (icsRefs.length) {
          window.open('inventory_custodian_slip.php?refs=' + $.map(icsRefs, function (ref) {
            return encodeURIComponent(String(ref || '').trim());
          }).join(','), '_blank');
          opened++;
        }
      }

      if (!opened) {
        Swal ? Swal.fire({ icon: 'warning', title: 'No saved PAR or ICS reference found for the selected document type.' }) : alert('No saved PAR or ICS reference found for the selected document type.');
      }
    }

    function npDetailConfirmAction(options, onConfirm) {
      var config = options || {};
      if (Swal) {
        Swal.fire({
          icon: config.icon || 'question',
          title: config.title || 'Continue?',
          text: config.text || '',
          showCancelButton: true,
          confirmButtonText: config.confirmButtonText || 'Yes',
          cancelButtonText: config.cancelButtonText || 'Cancel',
          confirmButtonColor: config.confirmButtonColor || undefined,
          cancelButtonColor: config.cancelButtonColor || undefined
        }).then(function (result) {
          if (result && result.isConfirmed && typeof onConfirm === 'function') {
            onConfirm();
          }
        });
        return;
      }
      if (window.confirm(config.text || config.title || 'Continue?') && typeof onConfirm === 'function') {
        onConfirm();
      }
    }

    function npDetailAdjustSetCount(nextCount) {
      npDetailSnapshotBundleRows();
      nextCount = npDetailNormalizeSetCount(nextCount);
      while (npDetailState.items.length < nextCount) {
        npDetailState.items.push(npDetailCreateBlankItem());
      }
      while (npDetailState.items.length > nextCount) {
        npDetailState.items.pop();
      }
      npDetailResetSetNumbers();
      npDetailRenderItemRows();
      npDetailRenderEndUserRows();
      npDetailRenderBundleRows();
      npDetailRefreshAllProperties();
    }

    function npDetailReloadTable() {
      if (!($.fn.dataTable && $.fn.dataTable.isDataTable('#addItemNewPurchaseTable'))) { return; }

      if (npDetailSourceContext() === 'fund_inventory') {
        window.location.reload();
        return;
      }

      var table = $('#addItemNewPurchaseTable').DataTable();
      var settings = table.settings()[0] || {};

      if (settings.ajax) {
        table.ajax.reload(null, false);
        return;
      }

      table.rows().invalidate().draw(false);
      if (table.columns && table.columns.adjust) {
        table.columns.adjust();
      }
      if (table.responsive && table.responsive.recalc) {
        table.responsive.recalc();
      }
    }

    function npDetailReloadGroup(onDone) {
      $.ajax({
        url: '../auth/auth.php',
        type: 'POST',
        cache: false,
        dataType: 'json',
        data: {
          fetch_new_purchase_group: 1,
          po: String($('#edit_np_po').val() || $('#edit_np_group_po').val() || '').trim(),
          row_id: parseInt($('#edit_np_group_row_id').val(), 10) || 0,
          source_context: npDetailSourceContext(),
          fund_inventory_key: npDetailFundInventoryKey()
        },
        success: function (resp) {
          if (resp && Number(resp.status) === 200 && resp.data) {
            npDetailFillModal(resp.data);
            if (typeof onDone === 'function') { onDone(true, resp); }
            return;
          }
          if (typeof onDone === 'function') { onDone(false, resp); }
        },
        error: function () {
          if (typeof onDone === 'function') { onDone(false, null); }
        }
      });
    }

    $(document)
      .off('click.addItemNpDetailBtn', '#addItemNewPurchaseTable .np-edit-btn, #FundInventoryTable .np-edit-btn')
      .on('click.addItemNpDetailBtn', '#addItemNewPurchaseTable .np-edit-btn, #FundInventoryTable .np-edit-btn', function () {
        var $btn = $(this);
        var po = String($btn.data('po') || '').trim();
        var rowId = parseInt($btn.data('rowId'), 10) || 0;
        var sourceContext = npDetailSourceContext();
        var fundInventoryKey = npDetailFundInventoryKey();
        var cacheKey = npDetailGroupCacheKey(po, rowId, sourceContext, fundInventoryKey);
        var cachedGroup = npDetailGroupCache[cacheKey] || null;

        if (cachedGroup) {
          npDetailFillModal(cachedGroup, { skipDerivedRefresh: true });
          $('#editNpDetailModal').modal('show');
        } else {
          $('#editNpItemRows').html('<div class="text-center text-muted py-4">Loading details...</div>');
          $('#editNpEndUserRows').html('<tr><td colspan="4" class="text-center text-muted">Loading details...</td></tr>');
          $('#editNpDetailModal').modal('show');
        }

        if (npDetailOpenRequest && npDetailOpenRequest.readyState && npDetailOpenRequest.readyState !== 4) {
          npDetailOpenRequest.abort();
        }

        npDetailOpenRequest = $.ajax({
          url: '../auth/auth.php',
          type: 'POST',
          cache: false,
          dataType: 'json',
          data: {
            fetch_new_purchase_group: 1,
            po: po,
            row_id: rowId,
            source_context: sourceContext,
            fund_inventory_key: fundInventoryKey
          },
          success: function (resp) {
            if (!resp || Number(resp.status) !== 200 || !resp.data) {
              var message = (resp && resp.message) ? resp.message : 'Unable to load purchase details.';
              $('#editNpDetailModal').modal('hide');
              Swal ? Swal.fire({ icon: 'error', title: message }) : alert(message);
              return;
            }
            npDetailGroupCache[cacheKey] = resp.data;
            npDetailFillModal(resp.data, { skipDerivedRefresh: true });
            $('#editNpDetailModal').modal('show');
          },
          error: function (xhr, status) {
            if (status === 'abort') { return; }
            $('#editNpDetailModal').modal('hide');
            Swal ? Swal.fire({ icon: 'error', title: 'Request failed. Please try again.' }) : alert('Request failed.');
          },
          complete: function () {
            npDetailOpenRequest = null;
          }
        });
      });

    $(document)
      .off('input.editNpDeptSearch change.editNpDeptSearch', '#editNpDeptSearch')
      .on('input.editNpDeptSearch change.editNpDeptSearch', '#editNpDeptSearch', function () {
        var $deptSelect = $('#edit_np_dept');
        var currentCode = String($deptSelect.val() || '').trim();
        var code = npDetailFindDeptCodeByName($(this).val());
        if (code) {
          if (code !== currentCode) {
            $deptSelect.val(code).trigger('change');
          }
        } else if (!$(this).val()) {
          if (currentCode) {
            $deptSelect.val('').trigger('change');
          }
        }
      });

    $(document)
      .off('change.editNpDeptSelect', '#edit_np_dept')
      .on('change.editNpDeptSelect', '#edit_np_dept', function () {
        npDetailSyncDeptInput();
        npDetailResetEmployeeSelection();
        npDetailLoadEmployees(String($(this).val() || '').trim(), function () {
          npDetailRefreshAllProperties();
        });
      });

    $(document)
      .off('change.editNpMultipleEndUsers', '#editNpMultipleEndUserCheckBox')
      .on('change.editNpMultipleEndUsers', '#editNpMultipleEndUserCheckBox', function () {
        npDetailState.useMultipleEndUsers = $(this).is(':checked') && npDetailState.items.length > 1;
        npDetailRenderEndUserRows();
      });

    $(document)
      .off('change.editNpEmployeeSelect', '#editNpEndUserRows .edit-np-emp-select, #edit_np_emp_single')
      .on('change.editNpEmployeeSelect', '#editNpEndUserRows .edit-np-emp-select, #edit_np_emp_single', function () {
        var $field = $(this);
        var selectedValue = String($field.val() || '').trim();
        var selectedLabel = $field.find('option:selected').text().trim();
        npDetailToggleNewEmployeeRow($field);

        if ($field.is('#edit_np_emp_single')) {
          if (npDetailState.items.length === 1) {
            npDetailState.items[0].emp_id = selectedValue;
            npDetailState.items[0].emp_name = selectedLabel;
          } else if (!npDetailState.useMultipleEndUsers && selectedValue !== '__keep_existing__') {
            $.each(npDetailState.items, function (_, item) {
              item.emp_id = selectedValue;
              item.emp_name = selectedLabel;
            });
          }
        } else {
          var itemId = String($field.data('itemId') || '');
          var item = npDetailFindItem(itemId);
          if (item) {
            item.emp_id = selectedValue;
            item.emp_name = selectedLabel;
          }
        }

        if ($field.is('#edit_np_emp_single')) {
          var isAddNew = String($field.val() || '').toLowerCase() === 'add_new_emp';
          $('#edit_np_new_emp').prop('disabled', !isAddNew);
          $('#edit_np_position').prop('disabled', !isAddNew);
          if (isAddNew) {
            $('#edit_np_add_new_employee').show();
          } else {
            $('#edit_np_add_new_employee').hide();
            $('#edit_np_new_emp').val('');
            $('#edit_np_position').val('');
          }
        }
      });

    $(document)
      .off('input.editNpSetCount change.editNpSetCount blur.editNpSetCount', '#edit_np_set_count')
      .on('input.editNpSetCount change.editNpSetCount blur.editNpSetCount', '#edit_np_set_count', function () {
        var count = npDetailNormalizeSetCount($(this).val());
        $(this).val(count);
        npDetailAdjustSetCount(count);
      });

    $(document)
      .off('change.editNpBundleSerialToggle', '#editNpBundleRows .edit-np-bundle-add-serial')
      .on('change.editNpBundleSerialToggle', '#editNpBundleRows .edit-np-bundle-add-serial', function () {
        npDetailApplyBundleSerialVisibility($(this).closest('.edit-np-bundle-card'));
      });

    $(document)
      .off('click.editNpAddBundle', '#editNpAddBundleRow')
      .on('click.editNpAddBundle', '#editNpAddBundleRow', function () {
        npDetailSnapshotBundleRows();
        npDetailState.bundles.push(npDetailNormalizeBundle({}));
        npDetailRenderBundleRows();
      });

    $(document)
      .off('click.editNpRemoveBundle', '#editNpBundleRows .edit-np-remove-bundle')
      .on('click.editNpRemoveBundle', '#editNpBundleRows .edit-np-remove-bundle', function () {
        var bundleKey = String($(this).data('bundleKey') || '');
        npDetailSnapshotBundleRows();
        npDetailState.bundles = $.grep(npDetailState.bundles, function (bundle) {
          return String(bundle.key || '') !== bundleKey;
        });
        npDetailRenderBundleRows();
      });

    $(document)
      .off('change.editNpBundleSet', '#editNpBundleRows .edit-np-bundle-set')
      .on('change.editNpBundleSet', '#editNpBundleRows .edit-np-bundle-set', function () {
        npDetailSnapshotBundleRows();
        npDetailRenderBundleRows();
      });

    $(document)
      .off('change.editNpBundleCategory', '#editNpBundleRows .edit-np-bundle-category')
      .on('change.editNpBundleCategory', '#editNpBundleRows .edit-np-bundle-category', function () {
        var $card = $(this).closest('.edit-np-bundle-card');
        npDetailRefreshBundleParIcsNumbers();
        npDetailRefreshBundlePreviewFields($card);
      });

    $(document)
      .off('change.editNpBundleNoBrand', '#editNpBundleRows .edit-np-bundle-no-brand')
      .on('change.editNpBundleNoBrand', '#editNpBundleRows .edit-np-bundle-no-brand', function () {
        npDetailApplyBundleNoBrandState($(this).closest('.edit-np-bundle-card'));
      });

    $(document)
      .off('click.editNpRemoveSet', '#editNpItemRows .edit-np-remove-set')
      .on('click.editNpRemoveSet', '#editNpItemRows .edit-np-remove-set', function () {
        var $button = $(this);
        var itemKey = String($(this).data('itemId') || '');
        var item = npDetailFindItem(itemKey);
        var setLabel = item ? ('Set ' + item.set_no) : 'this set';
        var existingItemIds = item && $.isArray(item.existing_item_ids) ? item.existing_item_ids : [];
        var deleteItemIds = $.map(existingItemIds, function (value) {
          var itemId = parseInt(value, 10) || 0;
          return itemId > 0 ? itemId : null;
        });

        npDetailConfirmAction({
          icon: 'warning',
          title: 'Delete ' + setLabel + '?',
          text: 'This will permanently delete all data under ' + setLabel + ' from the database.',
          confirmButtonText: 'Yes, delete set',
          cancelButtonText: 'Cancel',
          confirmButtonColor: '#dc3545'
        }, function () {
          if (!deleteItemIds.length) {
            npDetailSnapshotBundleRows();
            npDetailState.items = $.grep(npDetailState.items, function (currentItem) {
              return String(currentItem.key || '') !== itemKey;
            });
            npDetailResetSetNumbers();
            npDetailRenderItemRows();
            npDetailRenderEndUserRows();
            npDetailRenderBundleRows();
            npDetailRefreshAllProperties();
            return;
          }

          $button.prop('disabled', true);
          $.ajax({
            url: '../auth/auth.php',
            type: 'POST',
            cache: false,
            dataType: 'json',
            data: {
              delete_new_purchase_group_set: 1,
              po: String($('#edit_np_group_po').val() || '').trim(),
              row_id: parseInt($('#edit_np_group_row_id').val(), 10) || 0,
              item_ids: deleteItemIds,
              item_ids_raw: deleteItemIds.join(',')
            },
            success: function (resp) {
              if (!resp || Number(resp.status) !== 200) {
                var message = (resp && resp.message) ? resp.message : 'Unable to delete the selected set.';
                Swal ? Swal.fire({ icon: 'error', title: message }) : alert(message);
                return;
              }

              npDetailReloadGroup(function (hasGroup) {
                npDetailReloadTable();
                if (hasGroup) {
                  Swal ? Swal.fire({ icon: 'success', title: resp.message || 'Set deleted permanently.' }) : alert(resp.message || 'Set deleted permanently.');
                  return;
                }

                $('#editNpDetailModal').modal('hide');
                Swal ? Swal.fire({ icon: 'success', title: resp.message || 'Set deleted permanently.' }) : alert(resp.message || 'Set deleted permanently.');
              });
            },
            error: function () {
              Swal ? Swal.fire({ icon: 'error', title: 'Request failed. Please try again.' }) : alert('Request failed.');
            },
            complete: function () {
              $button.prop('disabled', false);
            }
          });
        });
      });

    $(document)
      .off('input.editNpItemQuantity change.editNpItemQuantity blur.editNpItemQuantity', '#editNpItemRows .edit-np-item-quantity')
      .on('input.editNpItemQuantity change.editNpItemQuantity blur.editNpItemQuantity', '#editNpItemRows .edit-np-item-quantity', function () {
        var $field = $(this);
        var itemKey = String($field.data('itemId') || '');
        var item = npDetailFindItem(itemKey);
        var quantity = npDetailNormalizeItemQuantity($field.val());
        var $card = $field.closest('.item-set-card');
        var serials1 = [];
        var serials2 = [];

        $field.val(quantity);
        $card.find('.edit-np-serial-primary').each(function () { serials1.push(String($(this).val() || '')); });
        $card.find('.edit-np-serial-secondary').each(function () { serials2.push(String($(this).val() || '')); });

        if (item) {
          item.item_quantity = quantity;
          item.serial_numbers = serials1;
          item.serial_numbers_2 = serials2;
        }

        $card.find('.edit-np-serial-table-wrap').html(npDetailBuildSerialRows(item || {
          key: itemKey,
          item_quantity: quantity,
          serial_numbers: serials1,
          serial_numbers_2: serials2
        }));
        npDetailApplySerialVisibilityState($card, false);
        npDetailSyncTotalAmount($card.find('.edit-np-unit-value'));
        npDetailSnapshotBundleRows();
        npDetailRenderBundleRows();
        npDetailRefreshParIcsNumbers();
        npDetailRefreshProperty(itemKey);
      });

    $(document)
      .off('change.editNpSerialToggle', '#editNpItemRows .edit-np-add-serial')
      .on('change.editNpSerialToggle', '#editNpItemRows .edit-np-add-serial', function () {
        var $card = $(this).closest('.item-set-card');
        var itemKey = String($card.data('itemId') || '');
        var item = npDetailFindItem(itemKey);
        if (item) {
          item.add_serial = $(this).is(':checked');
        }
        npDetailApplySerialVisibilityState($card, true);
      });

    $(document)
      .off('change.editNpNoBrand', '#editNpItemRows .edit-np-no-brand')
      .on('change.editNpNoBrand', '#editNpItemRows .edit-np-no-brand', function () {
        npDetailApplyNoBrandState($(this).closest('.item-set-card'));
      });

    $(document)
      .off('change.editNpNoAmount', '#editNpItemRows .edit-np-no-amount')
      .on('change.editNpNoAmount', '#editNpItemRows .edit-np-no-amount', function () {
        npDetailApplyNoAmountState($(this).closest('.item-set-card'));
      });

    $(document)
      .off('change.editNpCategory', '#editNpItemRows .edit-np-item-category')
      .on('change.editNpCategory', '#editNpItemRows .edit-np-item-category', function () {
        var $card = $(this).closest('.item-set-card');
        var categoryValue = String($(this).val() || '').trim().toUpperCase();
        $card.find('.edit-np-item-category-value').val(categoryValue);
        var item = npDetailFindItem(String($card.data('itemId') || ''));
        if (item) {
          item.category = categoryValue;
        }
        npDetailRefreshParIcsNumbers();
        npDetailRefreshProperty($card.data('itemId'));
      });

    $(document)
      .off('change.editNpNoAccountProperty', '#editNpItemRows .edit-np-no-account-property')
      .on('change.editNpNoAccountProperty', '#editNpItemRows .edit-np-no-account-property', function () {
        var $card = $(this).closest('.item-set-card');
        npDetailApplyNoAccountPropertyState($card);
        npDetailRefreshProperty($card.data('itemId'));
      });

    $(document)
      .off('input.editNpUnitValue', '#editNpItemRows .edit-np-unit-value')
      .on('input.editNpUnitValue', '#editNpItemRows .edit-np-unit-value', function () {
        var cursorAtEnd = this.selectionStart === this.value.length;
        this.value = npDetailFormatMoney(this.value);
        npDetailSyncTotalAmount($(this));
        if (cursorAtEnd && this.setSelectionRange) {
          this.setSelectionRange(this.value.length, this.value.length);
        }
      })
      .off('blur.editNpUnitValue', '#editNpItemRows .edit-np-unit-value')
      .on('blur.editNpUnitValue', '#editNpItemRows .edit-np-unit-value', function () {
        this.value = npDetailFormatMoney(this.value, true);
        npDetailSyncTotalAmount($(this));
      });

    $(document)
      .off('change.editNpPropertyDeps', '#edit_np_fund, #edit_np_year')
      .on('change.editNpPropertyDeps', '#edit_np_fund, #edit_np_year', function () {
        if ($(this).is('#edit_np_fund')) {
          npDetailResetDepartmentAndEmployeeSelection();
          npDetailLoadDepartmentsForFund($(this).val(), '', function () {
            npDetailRefreshAllProperties();
          });
        }
        $('#editNpItemRows .item-set-card').each(function () {
          npDetailApplyNoAccountPropertyState($(this));
        });
        npDetailRefreshParIcsNumbers();
        npDetailRefreshAllProperties();
      });

    $(document)
      .off('change.editNpPropertyAccount', '#editNpItemRows .edit-np-account-code')
      .on('change.editNpPropertyAccount', '#editNpItemRows .edit-np-account-code', function () {
        npDetailRefreshProperty($(this).data('itemId'));
      });

    $(document)
      .off('click.editNpPrint', '#editNpDetailPrintBtn')
      .on('click.editNpPrint', '#editNpDetailPrintBtn', function () {
        npDetailConfirmAction({
          title: 'Print this document?',
          text: 'This will open the PAR/ICS print page for the current item group.',
          confirmButtonText: 'Yes, print'
        }, npDetailPrintCurrentGroup);
      });

    $(document)
      .off('submit.editNpDetailForm', '#editNpDetailForm')
      .on('submit.editNpDetailForm', '#editNpDetailForm', function (e) {
        e.preventDefault();

        if (!npDetailValidateUnitValues()) {
          Swal ? Swal.fire({ icon: 'warning', title: 'Validation error', text: 'Please review the unit value rules for each set.' }) : alert('Please review the unit value rules for each set.');
          return;
        }

        var deptCode = npDetailFindDeptCodeByName($('#editNpDeptSearch').val()) || npDetailCurrentDeptCode();
        if (!deptCode) {
          Swal ? Swal.fire({ icon: 'warning', title: 'Validation error', text: 'Please select a valid department from the list.' }) : alert('Please select a valid department from the list.');
          return;
        }
        $('#edit_np_dept').val(deptCode);

        var useMultipleEndUsers = npDetailState.items.length > 1 && npDetailState.useMultipleEndUsers;
        var singleEmployeeValue = String($('#edit_np_emp_single').val() || '').trim();

        if (!useMultipleEndUsers && npDetailState.items.length > 1) {
          if (singleEmployeeValue === 'add_new_emp') {
            var sharedNewEmployeeName = String($('#edit_np_new_emp').val() || '').trim();
            var sharedNewEmployeePosition = String($('#edit_np_position').val() || '').trim();
            if (!sharedNewEmployeeName || !sharedNewEmployeePosition) {
              Swal ? Swal.fire({ icon: 'warning', title: 'Validation error', text: 'New employee name and position are required.' }) : alert('New employee name and position are required.');
              return;
            }
          }
        }

        var missingEmployeeRows = [];
        if (useMultipleEndUsers) {
          $('#editNpEndUserRows .edit-np-emp-select').each(function () {
            var itemId = $(this).data('itemId');
            if (String($(this).val() || '').toLowerCase() === 'add_new_emp') {
              var name = $('.edit-np-emp-new-name[data-item-id="' + itemId + '"]').val();
              var position = $('.edit-np-emp-new-position[data-item-id="' + itemId + '"]').val();
              if (!String(name || '').trim() || !String(position || '').trim()) {
                missingEmployeeRows.push(($('#editNpItemRows .item-set-card[data-item-id="' + itemId + '"] strong').text() || ('Set ' + itemId)));
              }
            }
          });
        }
        if (missingEmployeeRows.length) {
          Swal ? Swal.fire({ icon: 'warning', title: 'Validation error', text: 'New employee name and position are required for: ' + missingEmployeeRows.join(', ') + '.' }) : alert('New employee name and position are required.');
          return;
        }

        npDetailConfirmAction({
          title: 'Update this item?',
          text: 'Please confirm that the edited item/equipment details are correct.',
          confirmButtonText: 'Yes, update'
        }, function () {
          var $btn = $('#editNpDetailSaveBtn').prop('disabled', true);
          var formData = new FormData(e.currentTarget);
          formData.set('dept_id', deptCode);
          formData.set('fund', $('#edit_np_fund').val() || '');
          formData.set('source_context', npDetailSourceContext());
          formData.set('fund_inventory_key', npDetailFundInventoryKey());
          formData.delete('bundle_parent_index[]');
          formData.delete('bundle_category[]');
          formData.delete('bundle_unit[]');
          formData.delete('bundle_asset_class[]');
          formData.delete('bundle_brand_model[]');
          formData.delete('bundle_description[]');
          formData.delete('bundle_serial1[]');
          formData.delete('bundle_serial2[]');
          formData.delete('bundle_property_number[]');

          if (!useMultipleEndUsers && npDetailState.items.length > 1) {
            $.each(npDetailState.items, function (_, item) {
              var itemKey = String(item.key || '');
              var employeeValue = singleEmployeeValue || String(item.emp_id || '').trim();

              formData.set('emp_id[' + itemKey + ']', employeeValue);

              if (singleEmployeeValue === 'add_new_emp') {
                formData.set('emp_new_name[' + itemKey + ']', String($('#edit_np_new_emp').val() || '').trim());
                formData.set('emp_new_position[' + itemKey + ']', String($('#edit_np_position').val() || '').trim());
              } else {
                formData.delete('emp_new_name[' + itemKey + ']');
                formData.delete('emp_new_position[' + itemKey + ']');
              }
            });
          }

          npDetailSnapshotBundleRows();
          var bundleMissing = [];
          var isPropertyNumberOptionalFund = npDetailPropertyOptionalFund();
          $.each(npDetailState.bundles || [], function (bundleIndex, bundle) {
            var normalized = npDetailNormalizeBundle(bundle);
            var propertyNumbers = npDetailBundlePropertyNumbers(normalized);
            var quantity = propertyNumbers.length;
            var groupLabel = 'Bundle ' + (bundleIndex + 1);
            var hasAny = normalized.set_index || normalized.category || normalized.unit || normalized.item || normalized.model || normalized.description || normalized.serial_numbers.length || normalized.serial_numbers_2.length;
            if (!hasAny) {
              return;
            }
            if (!normalized.set_index || !normalized.category || !normalized.unit || !normalized.item || quantity < 1) {
              bundleMissing.push(groupLabel);
              return;
            }

            for (var copyIndex = 0; copyIndex < quantity; copyIndex++) {
              var propertyNumber = String(propertyNumbers[copyIndex] || '').trim();
              if (!isPropertyNumberOptionalFund && !propertyNumber) {
                bundleMissing.push(groupLabel);
                return false;
              }
              var serial1 = normalized.add_serial ? String(normalized.serial_numbers[copyIndex] || '').trim() : '';
              var serial2 = normalized.add_serial ? String(normalized.serial_numbers_2[copyIndex] || '').trim() : '';
              formData.append('bundle_parent_index[]', normalized.set_index);
              formData.append('bundle_category[]', normalized.category);
              formData.append('bundle_unit[]', normalized.unit);
              formData.append('bundle_asset_class[]', normalized.item);
              formData.append('bundle_brand_model[]', normalized.model);
              formData.append('bundle_description[]', normalized.description);
              formData.append('bundle_serial1[]', serial1);
              formData.append('bundle_serial2[]', serial2);
              formData.append('bundle_property_number[]', propertyNumber);
            }
          });

          if (bundleMissing.length) {
            bundleMissing = $.grep(bundleMissing, function (value, index) {
              return $.inArray(value, bundleMissing) === index;
            });
            $btn.prop('disabled', false);
            Swal ? Swal.fire({ icon: 'warning', title: 'Validation error', text: 'Bundle equipment requires Bundle Set, Category, Unit, Asset Class, and valid property numbers for: ' + bundleMissing.join(', ') + '.' }) : alert('Bundle equipment is incomplete.');
            return;
          }

          $.ajax({
            url: '../auth/auth.php',
            type: 'POST',
            cache: false,
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json',
            success: function (resp) {
              if (!resp || Number(resp.status) !== 200) {
                var message = (resp && resp.message) ? resp.message : 'Update failed.';
                Swal ? Swal.fire({ icon: 'error', title: message }) : alert(message);
                return;
              }
              npDetailReloadGroup(function () {
                if (Swal) {
                  Swal.fire({ icon: 'success', title: resp.message || 'Updated successfully.' });
                } else {
                  alert(resp.message || 'Updated successfully.');
                }
                npDetailReloadTable();
              });
            },
            error: function () {
              Swal ? Swal.fire({ icon: 'error', title: 'Request failed. Please try again.' }) : alert('Request failed.');
            },
            complete: function () {
              $btn.prop('disabled', false);
            }
          });
        });
      });

    $('#editNpDetailModal')
      .off('hidden.bs.modal.editNpDetail')
      .on('hidden.bs.modal.editNpDetail', function () {
        npDetailState.group = null;
        npDetailState.items = [];
        npDetailState.bundles = [];
        npDetailState.employeeOptionsHtml = '<option value="">-SELECT-</option><option value="add_new_emp"> + ADD NEW EMPLOYEE </option>';
        npDetailState.useMultipleEndUsers = false;
        npDetailBundleParIcsCache = {};
        $('#editNpDetailForm')[0].reset();
        $('#editNpItemRows').html('<div class="text-center text-muted py-4">Select a purchase to view details.</div>');
        $('#editNpBundleRows').html('<div class="text-center text-muted py-4">No bundle equipment found.</div>');
        $('#editNpEndUserRows').html('<tr><td colspan="4" class="text-center text-muted">Select a purchase to view details.</td></tr>');
        $('#edit_np_set_count').val(0);
        $('#edit_np_fund').prop('disabled', false);
        $('#edit_np_set_count').prop('readonly', false);
        $('#editNpDetailPrintBtn').show();
        npDetailPopulateDeptDatalist();
      });

    npDetailPopulateDeptDatalist();
  })();

  // ============================================================
  // New Purchase Items page: datatable with Excel/Print/Re-Print
  // ============================================================
  if ($('#npItemsTable').length && $.fn.dataTable && !$.fn.dataTable.isDataTable('#npItemsTable')) {
    var npItemsTable = $('#npItemsTable').DataTable({
      responsive: true,
      lengthChange: false,
      ordering: false,
      autoWidth: false,
      language: {
        emptyTable: 'No items found for this P.O.',
        zeroRecords: 'No matching records found.'
      },
      buttons: [
        {
          extend: 'excel',
          orientation: 'landscape',
          pageSize: 'LEGAL',
          title: 'NEW PURCHASE ITEMS',
          exportOptions: { columns: ':not(:first-child):not(:last-child)' }
        },
        {
          extend: 'print',
          orientation: 'landscape',
          pageSize: 'LEGAL',
          title: 'NEW PURCHASE ITEMS',
          exportOptions: { columns: ':not(:first-child):not(:last-child)' }
        },
        {
          text: 'Re-Print',
          className: 'btn btn-info btn-sm',
          action: function () {
            var $checked = $('#npItemsTable tbody input.np-items-checkbox:checked');
            if (!$checked.length) {
              Swal && Swal.fire({ icon: 'warning', title: 'No items selected.', text: 'Please check at least one item to re-print.' });
              return;
            }

            // Group selected property numbers by ref + docType
            // parRefs = { ref: [pn1, pn2, ...] }, icsRefs = { ref: [pn1, ...] }
            var parRefs = {}, icsRefs = {};
            $checked.each(function () {
              var docType = String($(this).data('doc-type') || '').toUpperCase().trim();
              var refNum  = String($(this).data('ref-number') || '').trim();
              var propNum = String($(this).val() || '').trim();
              if (!refNum || !propNum) return;
              if (docType === 'PAR') {
                if (!parRefs[refNum]) parRefs[refNum] = [];
                parRefs[refNum].push(propNum);
              } else if (docType === 'ICS') {
                if (!icsRefs[refNum]) icsRefs[refNum] = [];
                icsRefs[refNum].push(propNum);
              }
            });

            var parList = Object.keys(parRefs);
            var icsList = Object.keys(icsRefs);

            if (!parList.length && !icsList.length) {
              Swal && Swal.fire({ icon: 'warning', title: 'No printable documents found.', text: 'Selected items have no PAR or ICS assignment yet.' });
              return;
            }

            // PAR: one window per reference number, filtered to only selected property numbers
            parList.forEach(function (ref) {
              var url = 'printpar.php?refnumber=' + encodeURIComponent(ref)
                      + '&pars=' + parRefs[ref].map(encodeURIComponent).join(',');
              window.open(url, '_blank');
            });

            // ICS: one window for all selected ICS items, filtered to only selected property numbers
            if (icsList.length) {
              var allIcsPars = icsList.reduce(function (acc, ref) { return acc.concat(icsRefs[ref]); }, []);
              window.open(
                'inventory_custodian_slip.php?refs=' + icsList.map(encodeURIComponent).join(',')
                + '&pars=' + allIcsPars.map(encodeURIComponent).join(','),
                '_blank'
              );
            }
          }
        }
      ],
      columnDefs: [
        { targets: 0, orderable: false, width: '4%' },
        { targets: 1, width: '13%' },
        { targets: 2, width: '20%' },
        { targets: 3, width: '10%' },
        { targets: 4, width: '10%' },
        { targets: 5, width: '12%' },
        { targets: 6, width: '13%' },
        { targets: 7, width: '12%' },
        { targets: 8, orderable: false, width: '6%' }
      ]
    });

    try { npItemsTable.buttons().container().appendTo('#npItemsTable_wrapper .col-md-6:eq(0)'); } catch (e) {}

    // Re-Print button is index 2; disable until something is checked
    function npSyncReprintBtn() {
      var hasChecked = $('#npItemsTable tbody input.np-items-checkbox:checked').length > 0;
      npItemsTable.button(2).enable(hasChecked);
    }
    npSyncReprintBtn();

    // Select-all checkbox
    $(document)
      .off('change.npItemsSelectAll', '#np_items_select_all')
      .on('change.npItemsSelectAll', '#np_items_select_all', function () {
        $('#npItemsTable tbody input.np-items-checkbox').prop('checked', $(this).is(':checked'));
        npSyncReprintBtn();
      });

    $(document)
      .off('change.npItemsRowCheckbox', '#npItemsTable input.np-items-checkbox')
      .on('change.npItemsRowCheckbox', '#npItemsTable input.np-items-checkbox', function () {
        var $all = $('#npItemsTable tbody input.np-items-checkbox');
        var chk  = $all.filter(':checked').length;
        $('#np_items_select_all')
          .prop('checked', chk === $all.length)
          .prop('indeterminate', chk > 0 && chk < $all.length);
        npSyncReprintBtn();
      });
  }

  // ============================================================
  // New Purchase Items: Edit item modal (new-purchase-items.php only)
  // ============================================================
  if ($('#npItemsTable').length) {
    var npEditOriginal = {};
    var npEditPropertyRequestId = 0;
    var npEditPropertyGenerating = false;
    var npEditPropertyValid = true;

    function npNormalizeFund(value) {
      var fund = String(value || '').trim().toUpperCase();
      var fundMap = { 'GF': 'GENERAL FUND', 'SF': 'SEF', 'SPECIAL EDUCATION FUND': 'SEF' };

      return fundMap[fund] || fund;
    }

    function npSetPropertyHelp(message, type) {
      var $help = $('#edit_property_number_help');
      var classes = 'form-text text-muted text-danger text-success text-info';
      var className = 'form-text text-muted';

      if (type === 'error') {
        className = 'form-text text-danger';
      } else if (type === 'success') {
        className = 'form-text text-success';
      } else if (type === 'info') {
        className = 'form-text text-info';
      }

      $help.removeClass(classes).addClass(className).text(message || 'Generated from account code.');
    }

    function npGetDeptFromPropertyNumber(value) {
      var parts = String(value || '').trim().split('-');

      return parts.length ? parts[parts.length - 1] : '';
    }

    function npSetSelectValue($select, value, label, marker) {
      var selectedValue = String(value || '').trim();
      var optionMarker = marker || 'data-current-value';

      $select.find('option[' + optionMarker + '="1"]').remove();

      var hasOption = $select.find('option').filter(function () {
        return this.value === selectedValue;
      }).length > 0;

      if (selectedValue && !hasOption) {
        $('<option>', {
          value: selectedValue,
          text: label || selectedValue
        }).attr(optionMarker, '1').insertAfter($select.find('option:first'));
      }

      $select.val(selectedValue);
    }

    function npCleanUnitValue(value) {
      var raw = String(value || '').replace(/,/g, '').replace(/[^0-9.]/g, '');
      var parts = raw.split('.');

      if (parts.length > 2) {
        raw = parts.shift() + '.' + parts.join('');
      }

      return raw;
    }

    function npFormatUnitValue(value, fixedDecimals) {
      var clean = npCleanUnitValue(value);
      if (!clean) {
        return '';
      }

      var parts = clean.split('.');
      var whole = parts[0] || '';
      var decimals = parts.length > 1 ? parts[1].slice(0, 2) : '';

      whole = whole.replace(/^0+(?=\d)/, '').replace(/\B(?=(\d{3})+(?!\d))/g, ',');

      if (!whole && decimals) {
        whole = '0';
      }

      if (fixedDecimals) {
        decimals = (decimals + '00').slice(0, 2);
        return (whole || '0') + '.' + decimals;
      }

      return parts.length > 1 ? whole + '.' + decimals : whole;
    }

    function npSetUnitValue(value) {
      $('#edit_unit_value').val(npFormatUnitValue(value, true));
    }

    function npGetYearAcquired(value) {
      var acquired = String(value || '').trim();
      var yearMatch = acquired.match(/^\d{4}/);

      return yearMatch ? yearMatch[0] : acquired;
    }

    function npSetYearAcquired(value) {
      var yearAcquired = npGetYearAcquired(value);
      var $yearSelect = $('#edit_date_aquired');

      npSetSelectValue($yearSelect, yearAcquired, yearAcquired, 'data-current-year');
    }

    function npSetAccountCode(value) {
      npSetSelectValue($('#edit_account_code'), value, value, 'data-current-account-code');
    }

    function npSetCategory(value) {
      var category = String(value || '').trim().toUpperCase();

      npSetSelectValue($('#edit_category'), category, category, 'data-current-category');
    }

    function npShouldRegeneratePropertyNumber() {
      return String($('#edit_account_code').val() || '').trim() !== String(npEditOriginal.accountCode || '').trim()
        || npGetYearAcquired($('#edit_date_aquired').val()) !== String(npEditOriginal.year || '').trim()
        || String($('#edit_category').val() || '').trim().toUpperCase() !== String(npEditOriginal.category || '').trim().toUpperCase()
        || npNormalizeFund($('#edit_fund').val()) !== npNormalizeFund(npEditOriginal.fund);
    }

    function npRegenerateEditPropertyNumber() {
      var requestId = ++npEditPropertyRequestId;
      var oldPropertyNumber = String(npEditOriginal.propertyNumber || '').trim();

      if (!npShouldRegeneratePropertyNumber()) {
        $('#show_property_number').val(oldPropertyNumber);
        npSetPropertyHelp('Generated from account code.');
        npEditPropertyValid = true;
        return;
      }

      var accountCode = String($('#edit_account_code').val() || '').trim();
      var category = String($('#edit_category').val() || '').trim().toUpperCase();
      var year = npGetYearAcquired($('#edit_date_aquired').val());
      var fund = npNormalizeFund($('#edit_fund').val());
      var dept = String(npEditOriginal.dept || '').trim() || npGetDeptFromPropertyNumber(oldPropertyNumber);

      if (fund === 'TRUST FUND' || fund === 'DONATION') {
        $('#show_property_number').val('');
        npSetPropertyHelp('Property number is not generated for ' + fund + '.', 'info');
        npEditPropertyGenerating = false;
        npEditPropertyValid = true;
        $('#npEditSaveBtn').prop('disabled', false);
        return;
      }

      if (!accountCode || !category || !year || !fund || !dept) {
        $('#show_property_number').val(oldPropertyNumber);
        npSetPropertyHelp('Select account code, category, fund, and year to generate a property number.', 'error');
        npEditPropertyValid = false;
        return;
      }

      npEditPropertyGenerating = true;
      npEditPropertyValid = false;
      $('#npEditSaveBtn').prop('disabled', true);
      $('#show_property_number').val('Generating...');
      npSetPropertyHelp('Checking available property number...', 'info');

      $.ajax({
        url: '../auth/auth.php',
        type: 'POST',
        cache: false,
        dataType: 'json',
        data: {
          generate_new_purchase_edit_property_number: 1,
          new_purchase_id: $('#edit_new_purchase_id').val(),
          property_number: oldPropertyNumber,
          category: category,
          year: year,
          account_code: accountCode,
          dept: dept,
          fund: fund
        },
        success: function (resp) {
          if (requestId !== npEditPropertyRequestId) {
            return;
          }

          if (resp && Number(resp.status) === 200 && resp.data && resp.data.property_number) {
            $('#show_property_number').val(resp.data.property_number);
            npSetPropertyHelp('Available property number generated.', 'success');
            npEditPropertyValid = true;
            return;
          }

          $('#show_property_number').val(oldPropertyNumber);
          npSetPropertyHelp((resp && resp.message) ? resp.message : 'Unable to generate an available property number.', 'error');
          npEditPropertyValid = false;
        },
        error: function () {
          if (requestId !== npEditPropertyRequestId) {
            return;
          }

          $('#show_property_number').val(oldPropertyNumber);
          npSetPropertyHelp('Unable to check property number availability.', 'error');
          npEditPropertyValid = false;
        },
        complete: function () {
          if (requestId !== npEditPropertyRequestId) {
            return;
          }

          npEditPropertyGenerating = false;
          $('#npEditSaveBtn').prop('disabled', false);
        }
      });
    }

    $(document)
      .off('input.npEditUnitValue', '#edit_unit_value')
      .on('input.npEditUnitValue', '#edit_unit_value', function () {
        var cursorAtEnd = this.selectionStart === this.value.length;
        this.value = npFormatUnitValue(this.value);

        if (cursorAtEnd) {
          this.setSelectionRange(this.value.length, this.value.length);
        }
      })
      .off('blur.npEditUnitValue', '#edit_unit_value')
      .on('blur.npEditUnitValue', '#edit_unit_value', function () {
        this.value = npFormatUnitValue(this.value, true);
      });

    $(document)
      .off('click.npEditBtn', '.np-edit-btn')
      .on('click.npEditBtn', '.np-edit-btn', function () {
        if ($(this).closest('#addItemNewPurchaseTable').length) {
          return;
        }
        if (!$(this).attr('data-item')) {
          return;
        }
        var d = {};
        try { d = JSON.parse($(this).attr('data-item') || '{}'); } catch (e) {}

        var fundVal = npNormalizeFund(d.fund || '');

        npEditOriginal = {
          propertyNumber: String(d.property_number || '').trim(),
          accountCode: String(d.account_code || '').trim(),
          year: npGetYearAcquired(d.date_aquired),
          category: String(d.category || '').trim().toUpperCase(),
          fund: fundVal,
          dept: String(d.dept || '').trim() || npGetDeptFromPropertyNumber(d.property_number)
        };

        $('#edit_new_purchase_id').val(d.id || '');
        $('#edit_property_number').val(d.property_number  || '');
        $('#show_property_number').val(d.property_number  || '');
        $('#edit_item').val(d.item             || '');
        $('#edit_model').val(d.model            || '');
        $('#edit_description').val(d.description      || '');
        $('#edit_serial_number').val(d.serial_number    || '');
        $('#edit_serial_number_2').val(d.serial_number_2  || '');
        npSetUnitValue(d.unit_value       || '');
        npSetYearAcquired(d.date_aquired);
        npSetAccountCode(d.account_code     || '');
        $('#edit_supplier').val(d.supplier         || '');
        $('#edit_par_ics_number').val(d.par_ics_number   || '');
        $('#edit_purchase_request').val(d.purchase_request || '');
        $('#edit_obr_number').val(d.obr_number       || '');
        $('#edit_jev_number').val(d.jev_number       || '');
        $('#edit_remarks').val(d.remarks          || '');
        npSetCategory(d.category         || '');

        $('#edit_fund').val(fundVal);
        npSetPropertyHelp('Generated from account code.');
        npEditPropertyValid = true;

        $('#npEditModal').modal('show');
      });

    $(document)
      .off('change.npEditPropertyInputs', '#edit_account_code, #edit_date_aquired, #edit_category, #edit_fund')
      .on('change.npEditPropertyInputs', '#edit_account_code, #edit_date_aquired, #edit_category, #edit_fund', npRegenerateEditPropertyNumber);

    $('#npEditForm').off('submit.npEdit').on('submit.npEdit', function (e) {
      e.preventDefault();

      if (npEditPropertyGenerating) {
        Swal ? Swal.fire({ icon: 'info', title: 'Please wait for property number validation.' }) : alert('Please wait for property number validation.');
        return;
      }

      if (npShouldRegeneratePropertyNumber() && !npEditPropertyValid) {
        Swal ? Swal.fire({ icon: 'warning', title: 'Please generate an available property number before saving.' }) : alert('Please generate an available property number before saving.');
        return;
      }

      var $btn = $('#npEditSaveBtn').prop('disabled', true);
      var formData = $(this).serializeArray();

      $.each(formData, function (_, field) {
        if (field.name === 'unit_value') {
          field.value = npCleanUnitValue(field.value);
        }
      });

      $.ajax({
        url: '../auth/auth.php',
        type: 'POST',
        cache: false,
        dataType: 'json',
        data: $.param(formData),
        success: function (resp) {
          if (!resp || Number(resp.status) !== 200) {
            var msg = (resp && resp.message) ? resp.message : 'Update failed.';
            Swal ? Swal.fire({ icon: 'error', title: msg }) : alert(msg);
            return;
          }
          $('#npEditModal').modal('hide');
          Swal ? Swal.fire({ icon: 'success', title: resp.message || 'Updated.' }).then(function () { location.reload(); })
               : (alert(resp.message || 'Updated.'), location.reload());
        },
        error: function () {
          Swal ? Swal.fire({ icon: 'error', title: 'Request failed. Please try again.' }) : alert('Request failed.');
        },
        complete: function () { $btn.prop('disabled', false); }
      });
    });
  }

  // DataTables inside tabs need a recalculation when the tab becomes visible
  $(document)
    .off('shown.bs.tab.addItemRecentTables')
    .on('shown.bs.tab.addItemRecentTables', 'a[data-toggle="tab"][href="#addItemExistingPane"], a[data-toggle="tab"][href="#addItemNewPurchasePane"]', function () {
      try {
        if ($.fn.dataTable && $.fn.dataTable.isDataTable('#addItemTable')) {
          $('#addItemTable').DataTable().columns.adjust().responsive.recalc();
        }
        if ($.fn.dataTable && $.fn.dataTable.isDataTable('#addItemNewPurchaseTable')) {
          $('#addItemNewPurchaseTable').DataTable().columns.adjust().responsive.recalc();
        }
      } catch (e) {}
    });

  // Infrastructure register
  if ($('#infrastructureTable').length && $.fn.dataTable && !$.fn.dataTable.isDataTable('#infrastructureTable')) {
    function infraEsc(text){
      return $('<div>').text(text === null || text === undefined ? '' : String(text)).html();
    }

    var infrastructureTable = $('#infrastructureTable').DataTable({
      responsive: true,
      lengthChange: false,
      autoWidth: false,
      processing: true,
      ajax: {
        url: '../auth/auth.php',
        type: 'POST',
        dataType: 'json',
        data: function(d){
          d.fetch_infrastructure_dt = 1;
          return d;
        },
        dataSrc: function(resp){
          return resp && Array.isArray(resp.data) ? resp.data : [];
        }
      },
      columns: [
        { data: 'account_code', render: function(d){ return infraEsc(d || ''); } },
        { data: 'description', render: function(d){ return infraEsc(d || ''); } }
      ],
      order: [[0, 'asc']]
    });

    function syncInfrastructureAccountCode(){
      var code = $('#infra_classification option:selected').data('accountCode') || '';
      $('#infra_account_code').val(String(code || '').trim());
    }

    function syncInfrastructureFundBadge(){
      var fund = String($('#infra_fund_cluster').val() || 'GENERAL FUND').trim();
      $('#infraFundIndicator').text(fund.toUpperCase());
    }

    function syncInfrastructureYear(){
      var dateVal = String($('#infra_date_acquired').val() || '').trim();
      if (!dateVal || dateVal.length < 4) { return; }
      $('#infra_year_acquired').val(dateVal.slice(0, 4));
    }

    $(document)
      .off('change.infrastructureClassification', '#infra_classification')
      .on('change.infrastructureClassification', '#infra_classification', syncInfrastructureAccountCode)
      .off('change.infrastructureFund', '#infra_fund_cluster')
      .on('change.infrastructureFund', '#infra_fund_cluster', syncInfrastructureFundBadge)
      .off('change.infrastructureDate', '#infra_date_acquired')
      .on('change.infrastructureDate', '#infra_date_acquired', syncInfrastructureYear)
      .off('submit.addInfrastructure', '#addInfrastructureForm')
      .on('submit.addInfrastructure', '#addInfrastructureForm', function(e){
        e.preventDefault();

        var $form = $(this);
        var $btn = $('#addInfrastructureSubmitBtn');
        var fd = new FormData(this);
        fd.append('save_infrastructure', 1);

        $btn.prop('disabled', true);
        $btn.find('.btn-text').text('Saving...');

        $.ajax({
          type: 'POST',
          url: '../auth/auth.php',
          data: fd,
          processData: false,
          contentType: false,
          dataType: 'json',
          success: function(res){
            if (res && res.status === 200) {
              Swal.fire({ position: 'center', icon: 'success', title: res.message || 'Saved successfully.', showConfirmButton: false, timer: 1600 }).then(function(){
                $('#addInfrastructureModal').modal('hide');
                $form[0].reset();
                $('#infra_fund_cluster').val('GENERAL FUND');
                $('#infra_condition_status').val('SERVICEABLE');
                $('#infra_year_acquired').val(String(new Date().getFullYear()));
                syncInfrastructureAccountCode();
                syncInfrastructureFundBadge();
                infrastructureTable.ajax.reload(null, false);
              });
              return;
            }

            Swal.fire({ icon: 'error', title: 'Unable to save', text: (res && res.message) ? res.message : 'Please review the form and try again.' });
          },
          error: function(xhr){
            var msg = 'Server error.';
            try {
              if (xhr && xhr.responseJSON && xhr.responseJSON.message) {
                msg = xhr.responseJSON.message;
              }
            } catch (e2) {}
            Swal.fire({ icon: 'error', title: 'Unable to save', text: msg });
          },
          complete: function(){
            $btn.prop('disabled', false);
            $btn.find('.btn-text').text('Save');
          }
        });
      });

    $('#addInfrastructureModal')
      .off('shown.bs.modal.infrastructure')
      .on('shown.bs.modal.infrastructure', function(){
        syncInfrastructureAccountCode();
        syncInfrastructureFundBadge();
        setTimeout(function(){ $('#infra_fund_cluster').trigger('focus'); }, 0);
      })
      .off('hidden.bs.modal.infrastructure')
      .on('hidden.bs.modal.infrastructure', function(){
        var form = document.getElementById('addInfrastructureForm');
        if (form) { form.reset(); }
        $('#infra_fund_cluster').val('GENERAL FUND');
        $('#infra_condition_status').val('SERVICEABLE');
        $('#infra_year_acquired').val(String(new Date().getFullYear()));
        syncInfrastructureAccountCode();
        syncInfrastructureFundBadge();
      });

    syncInfrastructureAccountCode();
    syncInfrastructureFundBadge();
  }

  // Land property register
  if ($('#landPropertyTable').length && $.fn.dataTable && !$.fn.dataTable.isDataTable('#landPropertyTable')) {
    function landEsc(text){
      return $('<div>').text(text === null || text === undefined ? '' : String(text)).html();
    }

    function landMoney(value){
      var amount = Number(value || 0);
      return '₱ ' + amount.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    var landPropertyTable = $('#landPropertyTable').DataTable({
      responsive: true,
      lengthChange: true,
      pageLength: 25,
      lengthMenu: [[10, 25, 50, 100, 500], [10, 25, 50, 100, 500]],
      autoWidth: false,
      processing: true,
       dom: "<'row align-items-end mb-3'<'col-sm-12 col-lg-2'l><'col-sm-12 col-lg-3 land-classification-filter-slot'><'col-sm-12 col-lg-3 land-barangay-filter-slot'><'col-sm-12 col-lg-4'f>>" +
         "<'row'<'col-sm-12'tr>>" +
         "<'row mt-2'<'col-sm-12 col-md-5'i><'col-sm-12 col-md-7'p>>",
      ajax: {
        url: '../auth/auth.php',
        type: 'POST',
        dataType: 'json',
        data: function(d){
          d.fetch_land_property_dt = 1;
          return d;
        },
        dataSrc: function(resp){
          return resp && Array.isArray(resp.data) ? resp.data : [];
        }
      },
      columns: [
        { data: null, orderable: false, searchable: false, className: 'text-center', render: function(){ return '<button type="button" class="btn btn-xs btn-success land-edit-btn" title="Edit"><i class="fas fa-edit"></i></button>'; } },
        { data: 'fund_cluster', render: function(d){ return landEsc(d || ''); } },
        { data: 'classification', render: function(d){ return landEsc(d || ''); } },
        { data: 'declared_owner', render: function(d){ return landEsc(d || ''); } },
        { data: 'tct_no', render: function(d){ return landEsc(d || ''); } },
        { data: 'project_name', render: function(d){ return landEsc(d || ''); } },
        { data: 'area_sqm', className: 'text-right', render: function(d){ return Number(d || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); } },
        { data: 'barangay', render: function(d){ return landEsc(d || ''); } },
        { data: 'acquisition_cost', className: 'text-right', render: landMoney },
        { data: 'capital_gains_tax', className: 'text-right', render: landMoney },
        { data: 'documentary_stamp_tax', className: 'text-right', render: landMoney },
        { data: 'other_incidental_transfer_fees', className: 'text-right', render: landMoney },
        { data: 'total_amount', className: 'text-right', render: landMoney },
        { data: null, render: function(row){ return landEsc((row.current_status || '') + (row.transfer_status === 'TRANSFERRED' ? ' / TRANSFERRED' : '')); } }
      ],
      order: [[1, 'asc']]
    });

    $('#landTableClassificationFilter').closest('.form-group').appendTo('#landPropertyTable_wrapper .land-classification-filter-slot');
    $('#landTableBarangayFilter').closest('.form-group').appendTo('#landPropertyTable_wrapper .land-barangay-filter-slot');
    $('#landTableFilterControls').remove();

    function applyLandTableFilters(){
      var classification = String($('#landTableClassificationFilter').val() || '');
      var barangay = String($('#landTableBarangayFilter').val() || '');
      var escapeRegex = $.fn.dataTable.util.escapeRegex;

      landPropertyTable
        .column(2).search(classification ? '^' + escapeRegex(classification) + '$' : '', true, false)
        .column(7).search(barangay ? '^' + escapeRegex(barangay) + '$' : '', true, false)
        .draw();
    }

    function parseLandMoney(value){
      return Number(String(value || '0').replace(/,/g, '')) || 0;
    }

    function formatLandMoney(value){
      return parseLandMoney(value).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function formatLandMoneyLive(input){
      var value = String(input.value || '').replace(/,/g, '').replace(/[^0-9.]/g, '');
      var cursor = input.selectionStart || value.length;
      var digitsBeforeCursor = String(input.value || '').slice(0, cursor).replace(/[^0-9]/g, '').length;
      var parts = value.split('.');
      var whole = (parts[0] || '').replace(/^0+(?=\d)/, '');
      var decimals = parts.length > 1 ? '.' + parts.slice(1).join('').replace(/\D/g, '').slice(0, 2) : '';
      var formatted = (whole || '0').replace(/\B(?=(\d{3})+(?!\d))/g, ',') + decimals;
      var nextCursor = formatted.length;
      var seenDigits = 0;

      input.value = formatted;
      for (var i = 0; i < formatted.length; i++) {
        if (/\d/.test(formatted.charAt(i))) { seenDigits++; }
        if (seenDigits >= digitsBeforeCursor) { nextCursor = i + 1; break; }
      }
      try { input.setSelectionRange(nextCursor, nextCursor); } catch (e) {}
    }

    function syncLandTotal(scope){
      var $scope = scope ? $(scope) : $('#addLandForm');
      var total = 0;
      $scope.find('.land-money').each(function(){ total += parseLandMoney($(this).val()); });
      $scope.find('[name="total_amount"]').val(formatLandMoney(total));
    }

    function isLandDocSelected(value){
      value = String(value || '').toUpperCase();
      return value !== '' && value !== 'NO' && value !== 'N/A';
    }

    function syncLandDoasDod(scope, changed){
      var $scope = scope ? $(scope) : $('#addLandForm');
      var $doas = $scope.find('[name="has_doas"]');
      var $dod = $scope.find('[name="has_dod"]');
      var doasSelected = isLandDocSelected($doas.val());
      var dodSelected = isLandDocSelected($dod.val());

      $doas.prop('disabled', false);
      $dod.prop('disabled', false);

      if (changed === 'doas' && doasSelected) {
        $dod.val('N/A').prop('disabled', true);
        return;
      }
      if (changed === 'dod' && dodSelected) {
        $doas.val('N/A').prop('disabled', true);
        return;
      }
      if (doasSelected) {
        $dod.val('N/A').prop('disabled', true);
        return;
      }
      if (dodSelected) {
        $doas.val('N/A').prop('disabled', true);
      }
    }

    function includeLandDocValues(fd, form){
      var $form = $(form);
      fd.set('has_doas', String($form.find('[name="has_doas"]').val() || ''));
      fd.set('has_dod', String($form.find('[name="has_dod"]').val() || ''));
    }

    function syncLandNoneField(checkbox){
      var $checkbox = $(checkbox);
      var $field = $($checkbox.data('target'));
      var $hidden = $($checkbox.data('hidden'));
      var checked = $checkbox.is(':checked');

      if (!$field.length) { return; }

      if (checked) {
        $field.val($field.is('input[type="date"]') ? '' : 'N/A').prop('disabled', !!$hidden.length).prop('readonly', !$hidden.length);
        if ($hidden.length) { $hidden.prop('disabled', false).val('N/A'); }
        return;
      }

      $field.prop('disabled', false).prop('readonly', false);
      if (String($field.val() || '').toUpperCase() === 'N/A') { $field.val(''); }
      if ($hidden.length) { $hidden.prop('disabled', true); }
    }

    function syncLandNoneFields(scope){
      $(scope || document).find('.land-none-toggle').each(function(){ syncLandNoneField(this); });
    }

    function setLandNoneState(selector, isNone){
      $(selector).prop('checked', !!isNone).each(function(){ syncLandNoneField(this); });
    }

    function landRow($btn){
      var $tr = $btn.closest('tr');
      if ($tr.hasClass('child')) { $tr = $tr.prev(); }
      return landPropertyTable.row($tr).data() || null;
    }

    function setLandEdit(row){
      $('#edit_land_id').val(row.land_id || '');
      $('#edit_land_fund_cluster').val(row.fund_cluster || '');
      $('#edit_land_classification').val(row.classification || '');
      $('#edit_land_declared_owner').val(row.declared_owner || '');
      $('#edit_land_tct_no').val(row.tct_no || '');
      $('#edit_land_area_sqm').val(Number(row.area_sqm || 0).toFixed(2));
      $('#edit_land_date_acquired').val(row.date_acquired || '');
      $('#edit_land_project_name').val(row.project_name || '');
      $('#edit_land_address').val(row.address || '');
      $('#edit_land_barangay').val(row.barangay || '');
      $('#edit_land_transfer_status').val(row.transfer_status || '');
      $('#edit_land_current_status').val(row.current_status || '');
      $('#edit_land_remarks').val(row.remarks || '');
      $('#edit_land_acquisition_cost').val(formatLandMoney(row.acquisition_cost || 0));
      $('#edit_land_documentary_stamp_tax').val(formatLandMoney(row.documentary_stamp_tax || 0));
      $('#edit_land_capital_gains_tax').val(formatLandMoney(row.capital_gains_tax || 0));
      $('#edit_land_other_fees').val(formatLandMoney(row.other_incidental_transfer_fees || 0));
      $('#edit_land_has_original_tct').val(row.has_original_tct || '');
      $('#edit_land_tax_declaration_no').val(row.tax_declaration_no || '');
      $('#edit_land_has_doas').val(row.has_doas || '');
      $('#edit_land_has_dod').val(row.has_dod || '');
      $('#edit_land_other_supporting_documents').val(row.other_supporting_documents || '');
      setLandNoneState('#edit_land_date_acquired_none', String(row.date_acquired || '').toUpperCase() === 'N/A');
      setLandNoneState('#edit_land_address_none', String(row.address || '').toUpperCase() === 'N/A');
      setLandNoneState('#edit_land_barangay_none', String(row.barangay || '').toUpperCase() === 'N/A');
      syncLandTotal('#editLandForm');
      syncLandDoasDod('#editLandForm');
    }

    $(document)
      .off('change.landTableFilters', '#landTableClassificationFilter, #landTableBarangayFilter')
      .on('change.landTableFilters', '#landTableClassificationFilter, #landTableBarangayFilter', applyLandTableFilters)
      .off('input.landMoney change.landMoney', '.land-money')
      .on('input.landMoney change.landMoney', '.land-money', function(){
        syncLandTotal($(this).closest('form'));
      })
      .off('input.landMoneyFormatLive', '.land-money-format')
      .on('input.landMoneyFormatLive', '.land-money-format', function(){
        formatLandMoneyLive(this);
        syncLandTotal($(this).closest('form'));
      })
      .off('click.editLand', '.land-edit-btn')
      .on('click.editLand', '.land-edit-btn', function(){
        var row = landRow($(this));
        if (!row) { return; }
        setLandEdit(row);
        $('#editLandModal').modal('show');
      })
      .off('change.landDoasDod', '#land_has_doas, #land_has_dod, #edit_land_has_doas, #edit_land_has_dod')
      .on('change.landDoasDod', '#land_has_doas, #land_has_dod, #edit_land_has_doas, #edit_land_has_dod', function(){
        syncLandDoasDod($(this).closest('form'), $(this).attr('name') === 'has_doas' ? 'doas' : 'dod');
      })
      .off('change.landNoneToggle', '.land-none-toggle')
      .on('change.landNoneToggle', '.land-none-toggle', function(){
        syncLandNoneField(this);
      })
      .off('focus.landMoneyFormat', '.land-money-format')
      .on('focus.landMoneyFormat', '.land-money-format', function(){
        $(this).val(String($(this).val() || '').replace(/,/g, ''));
      })
      .off('blur.landMoneyFormat', '.land-money-format')
      .on('blur.landMoneyFormat', '.land-money-format', function(){
        $(this).val(formatLandMoney($(this).val()));
        syncLandTotal($(this).closest('form'));
      })
      .off('submit.addLand', '#addLandForm')
      .on('submit.addLand', '#addLandForm', function(e){
        e.preventDefault();

        var $form = $(this);
        syncLandTotal($form);
        syncLandNoneFields($form);
        var $btn = $('#addLandSubmitBtn');
        var fd = new FormData(this);
        includeLandDocValues(fd, this);
        fd.append('save_land_property', 1);

        $btn.prop('disabled', true);
        $btn.find('.btn-text').text('Saving...');

        $.ajax({
          type: 'POST',
          url: '../auth/auth.php',
          data: fd,
          processData: false,
          contentType: false,
          dataType: 'json',
          success: function(res){
            if (res && res.status === 200) {
              Swal.fire({ position: 'center', icon: 'success', title: res.message || 'Saved successfully.', showConfirmButton: false, timer: 1600 }).then(function(){
                $('#addLandModal').modal('hide');
                $form[0].reset();
                $('#land_fund_cluster').val('');
                $('#land_transfer_status').val('');
                $('#land_current_status').val('');
                syncLandTotal($form);
                landPropertyTable.ajax.reload(null, false);
              });
              return;
            }
            Swal.fire({ icon: 'error', title: 'Unable to save', text: (res && res.message) ? res.message : 'Please review the form and try again.' });
          },
          error: function(xhr){
            var msg = 'Server error.';
            try { if (xhr && xhr.responseJSON && xhr.responseJSON.message) { msg = xhr.responseJSON.message; } } catch (e2) {}
            Swal.fire({ icon: 'error', title: 'Unable to save', text: msg });
          },
          complete: function(){
            $btn.prop('disabled', false);
            $btn.find('.btn-text').text('Save');
          }
        });
      })
      .off('submit.editLand', '#editLandForm')
      .on('submit.editLand', '#editLandForm', function(e){
        e.preventDefault();

        var $form = $(this);
        syncLandTotal($form);
        syncLandNoneFields($form);
        var $btn = $('#editLandSubmitBtn');
        var fd = new FormData(this);
        includeLandDocValues(fd, this);
        fd.append('update_land_property', 1);

        $btn.prop('disabled', true);
        $btn.find('.btn-text').text('Updating...');

        $.ajax({
          type: 'POST',
          url: '../auth/auth.php',
          data: fd,
          processData: false,
          contentType: false,
          dataType: 'json',
          success: function(res){
            if (res && res.status === 200) {
              Swal.fire({ position: 'center', icon: 'success', title: res.message || 'Updated successfully.', showConfirmButton: false, timer: 1600 }).then(function(){
                $('#editLandModal').modal('hide');
                landPropertyTable.ajax.reload(null, false);
              });
              return;
            }
            Swal.fire({ icon: 'error', title: 'Unable to update', text: (res && res.message) ? res.message : 'Please review the form and try again.' });
          },
          error: function(xhr){
            var msg = 'Server error.';
            try { if (xhr && xhr.responseJSON && xhr.responseJSON.message) { msg = xhr.responseJSON.message; } } catch (e2) {}
            Swal.fire({ icon: 'error', title: 'Unable to update', text: msg });
          },
          complete: function(){
            $btn.prop('disabled', false);
            $btn.find('.btn-text').text('Update');
          }
        });
      });

    $('#addLandModal')
      .off('shown.bs.modal.land')
      .on('shown.bs.modal.land', function(){
        syncLandTotal();
        setTimeout(function(){ $('#land_fund_cluster').trigger('focus'); }, 0);
      })
      .off('hidden.bs.modal.land')
      .on('hidden.bs.modal.land', function(){
        var form = document.getElementById('addLandForm');
        if (form) { form.reset(); }
        $('#land_fund_cluster').val('');
        $('#land_transfer_status').val('');
        $('#land_current_status').val('');
        $('#land_has_doas, #land_has_dod').prop('disabled', false);
        $('#land_date_acquired_none, #land_address_none, #land_barangay_none').prop('checked', false);
        syncLandNoneFields('#addLandForm');
        syncLandTotal('#addLandForm');
        syncLandDoasDod('#addLandForm');
      });

    $('#editLandModal')
      .off('hidden.bs.modal.landEdit')
      .on('hidden.bs.modal.landEdit', function(){
        var form = document.getElementById('editLandForm');
        if (form) { form.reset(); }
        $('#edit_land_has_doas, #edit_land_has_dod').prop('disabled', false);
        $('#edit_land_date_acquired_none, #edit_land_address_none, #edit_land_barangay_none').prop('checked', false);
        syncLandNoneFields('#editLandForm');
        syncLandTotal('#editLandForm');
        syncLandDoasDod('#editLandForm');
      });

    syncLandNoneFields('#addLandForm');
    syncLandTotal('#addLandForm');
    syncLandDoasDod('#addLandForm');
  }

  // Default DataTable initializer (skip when a page provides its own custom init)
  if (
    $('#dataTable').length &&
    $.fn.dataTable &&
    !$.fn.dataTable.isDataTable('#dataTable') &&
    !$('#dataTable').data('dtCustom')
  ) {
    $("#dataTable").DataTable({
      "responsive": true,
      "lengthChange": false,
      "autoWidth": false,
      "stateSave": true,
      "buttons": [
        {
          extend: "excel",
          orientation: "landscape",
          pageSize: "LEGAL",
          title: function () {
            return $('#reportTitle').text() || "Default Title";
          },
          exportOptions: {
            columns: ':not(:first-child)'
          }
        },
        {
          extend: "print",
          orientation: "landscape",
          pageSize: "LEGAL",
          title: function () {
            return $('#reportTitle').text() || "Default Title";
          },
          exportOptions: {
            columns: ':not(:first-child)'
          }
        },
        { extend: "pdfHtml5", orientation: "landscape", pageSize: "LEGAL", title: "INVENTORY REPORT" }
      ]
    }).buttons().container().appendTo('#dataTable_wrapper .col-md-6:eq(0)');
  }
  
  $('#clearanceModal').on('hidden.bs.modal', function () {
    $(this).find('form').trigger('reset');
  });

  $('.btn-link[aria-expanded="true"]').closest('.accordion-item').addClass('active');
  $('.collapse').on('show.bs.collapse', function () {
    $(this).closest('.accordion-item').addClass('active');
  });

  $('.collapse').on('hidden.bs.collapse', function () {
    $(this).closest('.accordion-item').removeClass('active');
  });

  $('#date').datepicker({
    startDate:'tomorrow',
    autoclose:true,
    format:'M-dd-yyyy',
    todayHighlight:true,
    orientation:'auto',
  }).on('changeDate',function(selected){
    var minDate = new Date(selected.date.valueOf());
    $('#datepick').datepicker('setStartDate',minDate);
  });

  $('#datepick').datepicker({
    startDate:'tomorrow',
    autoclose:true,
    format:'M-dd-yyyy',
    todayHighlight:true,
    orientation:'auto',
  }).on('changeDate',function(selected){
    var maxDate = new Date(selected.date.valueOf());
    $('#date').datepicker('setEndDate',maxDate);
  });
  
  $('.editBtn').popover(); // hover-popover

  $(document).on('change','#dept',function(){//dynamic dependent dropdown, select employee per department
    var departmentid = $(this).val();
    // Reset position when switching departments
    $('#position').val('');
    // Defensive UX: reset employee dropdown while loading
    if(!departmentid){
      $("#employee").html('<option value="">-SELECT-</option><option value="add_new_emp">+ ADD NEW EMPLOYEE</option>');
      return;
    }
    $("#employee").html('<option value="">Loading...</option>');
    $.ajax({
      type: "POST",
      url: "../auth/auth.php",
      data:{'departmentid':departmentid},
      success:function(data){
        $("#employee").html(data);
        $("#parEmp").html(data);
        $("#employee_id").html(data);

        // Keep Select2 (if enabled) in sync after options refresh
        try {
          if ($('#employee').hasClass('select2-hidden-accessible')) {
            $('#employee').val('').trigger('change');
          } else if (window.GSO && window.GSO.UI && typeof window.GSO.UI.initPcEmployeeSelect2 === 'function') {
            window.GSO.UI.initPcEmployeeSelect2();
          }
        } catch(e) {}
      },
      error:function(){
        $("#employee").html('<option value="">-SELECT-</option><option value="add_new_emp">+ ADD NEW EMPLOYEE</option>');
        if (typeof Swal !== 'undefined') {
          Swal.fire({ icon:'error', title:'Unable to load employees', text:'Please try selecting the department again.' });
        }
      }
    })
  });

  $(document).on('change','#deptid',function(){//dynamic dependent dropdown, select custodian per department
    var deptid = $(this).val();
    $.ajax({
      type: "POST",
      url: "../auth/auth.php",
      data:{'deptid':deptid},
      success:function(data){
        $("#custodian").html(data);
      }
    })
  });

  // Property Clearance details (modal or legacy page)
  $(document).on('submit', '#pcEditForm, #pc_details_form', function (e) {
    e.preventDefault();
    var formEl = this;

    function doSavePcDetails() {
      var fd = new FormData(formEl);
      fd.append('save_pc_details', true);

      $.ajax({
        type: 'POST',
        url: '../auth/auth.php',
        data: fd,
        processData: false,
        contentType: false,
        success: function (response) {
          var res;
          try { res = jQuery.parseJSON(response); } catch (err) { res = null; }
          if (res && res.status === 200) {
            if (typeof Swal !== 'undefined') {
              Swal.fire({ position: 'center', icon: 'success', title: res.message || 'Saved.', showConfirmButton: false, timer: 1200 });
            }
            // Reflect updates immediately in the list
            if (typeof window.reloadPcClearanceTable === 'function') {
              window.reloadPcClearanceTable();
            } else if ($('#clearanceTable').length && $.fn.dataTable && $.fn.dataTable.isDataTable('#clearanceTable')) {
              try { $('#clearanceTable').DataTable().ajax.reload(null, false); } catch (e) {}
            }
          } else {
            var msg = (res && res.message) ? res.message : 'Unable to save.';
            if (typeof Swal !== 'undefined') {
              Swal.fire({ icon: 'error', title: msg });
            }
          }
        },
        error: function () {
          if (typeof Swal !== 'undefined') {
            Swal.fire({ icon: 'error', title: 'Request failed. Please try again.' });
          }
        }
      });
    }

    var isInModal = $(formEl).closest('.modal').length > 0 || $(formEl).is('#pcEditForm');
    if (!isInModal) {
      doSavePcDetails();
      return;
    }

    // Confirmation (Update) for modal safety/validation
    if (typeof Swal !== 'undefined' && Swal.fire) {
      Swal.fire({
        icon: 'question',
        title: 'Update details?',
        text: 'Please confirm you want to save these changes.',
        showCancelButton: true,
        confirmButtonText: 'Yes, update',
        cancelButtonText: 'No, go back'
      }).then(function (result) {
        if (result && result.isConfirmed) { doSavePcDetails(); }
      });
    } else {
      if (confirm('Are you sure you want to update these details?')) {
        doSavePcDetails();
      }
    }
  });

  $(document).on('click', '.approvePcBtn', function (e) {
    e.preventDefault();
    var $form = $(this).closest('.modal').find('form');
    if (!$form.length) { $form = $('#pcEditForm'); }
    if (!$form.length) { $form = $('#pc_details_form'); }
    if (!$form.length) { return; }
    var clearanceId = ($form.find('input[name="cid"]').val() || $('#cid').val() || '').trim();
    var fd = new FormData($form[0]);
    fd.append('update_pc', true);

    function doApproveAndPrint() {
      $.ajax({
        type: 'POST',
        url: '../auth/auth.php',
        data: fd,
        processData: false,
        contentType: false,
        success: function (response) {
          var res;
          try { res = jQuery.parseJSON(response); } catch (err) { res = null; }
          if (res && res.status === 200) {
            var shouldPrint = (typeof res.should_print === 'undefined') ? true : !!res.should_print;
            var swalIcon = shouldPrint ? 'success' : 'info';
            var swalTitle = res.message || (shouldPrint ? 'Approved.' : 'Updated, but printing is blocked.');
            if (typeof Swal !== 'undefined') {
              Swal.fire({ position: 'center', icon: swalIcon, title: swalTitle, showConfirmButton: false, timer: 1500 }).then(function () {
                if (shouldPrint && clearanceId) {
                  var nw = window.open('../admin/print-property-clearance.php?control_id=' + encodeURIComponent(clearanceId), '_Blank');
                  if (nw) { nw.print(); }
                }
                if ($('#pcEditModal').length) {
                  $('#pcEditModal').modal('hide');
                }
                if (typeof window.reloadPcClearanceTable === 'function') {
                  window.reloadPcClearanceTable();
                } else if (typeof clearanceTable !== 'undefined' && clearanceTable && clearanceTable.ajax && clearanceTable.ajax.reload) {
                  clearanceTable.ajax.reload(null, false);
                } else {
                  location.reload();
                }
              });
            } else {
              if (shouldPrint && clearanceId) {
                var nw2 = window.open('../admin/print-property-clearance.php?control_id=' + encodeURIComponent(clearanceId), '_Blank');
                if (nw2) { nw2.print(); }
              }
              if ($('#pcEditModal').length) {
                $('#pcEditModal').modal('hide');
              }
              if (typeof window.reloadPcClearanceTable === 'function') {
                window.reloadPcClearanceTable();
              } else if (typeof clearanceTable !== 'undefined' && clearanceTable && clearanceTable.ajax && clearanceTable.ajax.reload) {
                clearanceTable.ajax.reload(null, false);
              } else {
                location.reload();
              }
            }
          } else {
            var msg = (res && res.message) ? res.message : 'Unable to approve.';
            if (typeof Swal !== 'undefined') {
              Swal.fire({ icon: 'error', title: msg });
            }
          }
        }
      });
    }

    // Confirmation (Approve & Print)
    if (typeof Swal !== 'undefined' && Swal.fire) {
      Swal.fire({
        icon: 'warning',
        title: 'Approve & print this clearance?',
        text: 'This will mark it as approved and open the print dialog.',
        showCancelButton: true,
        confirmButtonText: 'Yes, approve & print',
        cancelButtonText: 'No, cancel'
      }).then(function (result) {
        if (result && result.isConfirmed) { doApproveAndPrint(); }
      });
    } else {
      if (confirm('Approve and print this clearance?')) {
        doApproveAndPrint();
      }
    }
  });

  $(document).on('click', '.cancelBtnClearance', function (e) {
    e.preventDefault();
    var cid = $(this).data('value');

    function doCancelClearance() {
      $.ajax({
        type: 'POST',
        url: '../auth/auth.php',
        data: {
          propertyClearanceCancelBtn_id: true,
          propertyClearanceCancelBtn: cid
        },
        success: function (response) {
          var res;
          try { res = jQuery.parseJSON(response); } catch (err) { res = null; }
          if (res && res.status === 200) {
            if (typeof Swal !== 'undefined') {
              Swal.fire({ position: 'center', icon: 'success', title: 'Cancelled Successfully', showConfirmButton: false, timer: 1200 }).then(function () {
                if ($('#pcEditModal').length) {
                  $('#pcEditModal').modal('hide');
                }
                if (typeof window.reloadPcClearanceTable === 'function') {
                  window.reloadPcClearanceTable();
                } else if (typeof clearanceTable !== 'undefined' && clearanceTable && clearanceTable.ajax && clearanceTable.ajax.reload) {
                  clearanceTable.ajax.reload(null, false);
                } else {
                  location.reload();
                }
              });
            } else {
              if ($('#pcEditModal').length) {
                $('#pcEditModal').modal('hide');
              }
              if (typeof window.reloadPcClearanceTable === 'function') {
                window.reloadPcClearanceTable();
              } else if (typeof clearanceTable !== 'undefined' && clearanceTable && clearanceTable.ajax && clearanceTable.ajax.reload) {
                clearanceTable.ajax.reload(null, false);
              } else {
                location.reload();
              }
            }
          } else {
            var msg = (res && res.message) ? res.message : 'Unable to cancel.';
            if (typeof Swal !== 'undefined') {
              Swal.fire({ icon: 'error', title: msg });
            }
          }
        }
      });
    }

    // Confirmation (Cancel)
    if (typeof Swal !== 'undefined' && Swal.fire) {
      Swal.fire({
        icon: 'warning',
        title: 'Cancel this clearance?',
        text: 'This action cannot be undone.',
        showCancelButton: true,
        confirmButtonText: 'Yes, cancel it',
        cancelButtonText: 'No, keep it'
      }).then(function (result) {
        if (result && result.isConfirmed) { doCancelClearance(); }
      });
    } else {
      if (confirm('Are you sure you want to cancel this clearance?')) {
        doCancelClearance();
      }
    }
  });
  
  //adminstrator
	  $(document).on('submit','#admin_form',function (e){// to save adminstrator information
	    e.preventDefault();
	    var fd = new FormData(this);
	    fd.append("save_admin_info", true);
  
    $.ajax({
      type: "POST",
      url: "../auth/auth.php",
      data: fd,
      processData: false,
      contentType: false,
      success:function(response){
        
        var res = jQuery.parseJSON(response);
  
        if(res.status == 200 ){
          Swal.fire({
            position: 'center',
            icon: 'success',
            title: 'Save Successfully',
            showConfirmButton: false,
            timer: 1700
          }).then(()=>{
            $('#addAdministrator').modal('hide');
            $('#admin_form')[0].reset();
            location.reload();
        });
       }
      }
    });
  });

  $(document).on('click','.editAdmin', function (){// to fetch administrator information
    var adminid = $(this).val();
   $.ajax({
     type:'GET',
     url: '../auth/auth.php?adminid='+adminid,
     success:function(response){
  
        var res = jQuery.parseJSON(response);
  
        if(res.status == 422){
          alert(res.message);
        }else if(res.status == 200 ){
          
          $('#id').val(res.data.admin_id);
          $('#efname').val(res.data.first_name);
          $('#elname').val(res.data.last_name);
          $('#econtact').val(res.data.contact_number);
          $('#eemail').val(res.data.email);
          $('#empnumber').val(res.data.emp_number);
          $('#erole').val(res.data.role);
          $('#updateAdministrator').modal('show');
        }
     }
   })
  });
  $(document).on('submit','#admin_update',function(e){ // to update administrator information
    e.preventDefault();
    var fd = new FormData(this);
    fd.append("update_admin_info",true);

    $.ajax({
      type: "POST",
      url: "../auth/auth.php",
      data: fd,
      processData: false,
      contentType: false,
      success:function(response){
        var res = jQuery.parseJSON(response);

        if(res.status == 200){
            Swal.fire({
                position: 'center',
                icon: 'success',
                title: 'Admin Updated Successfully',
                showConfirmButton: false,
                timer: 2000
          }).then(()=>{
            $('#updateAdministrato').modal('hide');
            $('#admin_update')[0].reset();
            location.reload();
        });  
      }
      }
    });
 });
	 $(document).on('click','.delAdmin', function(e){// to delete administrator information
	  e.preventDefault();
	  if(confirm("Are you sure? ")){
	  var deladmin = $(this).val();
	  var adminFormToken = String($('#admin_form_token').val() || '').trim();

	  $.ajax({
	    type: "POST",
	    url: "../auth/auth.php",
	    data:{
	      'delete_admin':true,
	      'deladmin':deladmin,
	      'admin_form_token': adminFormToken
	    },
    success:function(response){
      var res = jQuery.parseJSON(response);
      if(res.status == 500){
        alert(res.message);
      }else{
        alert(res.message);
        location.reload();
      }
    }
  });
 }
 });

 $(document).on('submit','#acct_update',function(e){ // to update account code
    e.preventDefault();
    var fd = new FormData(this);
    fd.append("update_acct",true);

    $.ajax({
      type: "POST",
      url: "../auth/auth.php",
      data: fd,
      processData: false,
      contentType: false,
      success:function(response){
        var res = jQuery.parseJSON(response);

        if(res.status == 200){
            Swal.fire({
                position: 'center',
                icon: 'success',
                title: 'Account Code Updated Successfully',
                showConfirmButton: false,
                timer: 2000
          }).then(()=>{
            $('#editAccntModal').modal('hide');
            $('#acct_update')[0].reset();
            if (window.GSO && window.GSO.GfAccountCodes && typeof window.GSO.GfAccountCodes.reload === 'function') {
              window.GSO.GfAccountCodes.reload();
            } else {
              location.reload();
            }
        });  
      }
      }
    });
 });

  // add account code
  $(document).on('submit', '#acct_form', function(e){
    e.preventDefault();
    var $form = $(this);
    if (typeof $form.valid === 'function' && !$form.valid()) { return; }

    var fd = new FormData(this);
    fd.append('save_acct', true);

    $.ajax({
      type: 'POST',
      url: '../auth/auth.php',
      data: fd,
      processData: false,
      contentType: false,
      success: function(response){
        var res;
        try { res = jQuery.parseJSON(response); } catch (e) { res = null; }
        if (res && res.status == 200) {
          Swal.fire({ position:'center', icon:'success', title: res.message || 'Added successfully!', showConfirmButton:false, timer:1500 })
            .then(function(){
              $('#addAccntModal').modal('hide');
              $('#acct_form')[0].reset();
              if (window.GSO && window.GSO.GfAccountCodes && typeof window.GSO.GfAccountCodes.reload === 'function') {
                window.GSO.GfAccountCodes.reload();
              } else {
                location.reload();
              }
            });
        } else {
          var msg = (res && res.message) ? res.message : 'Unable to save account code.';
          if (window.Swal) { Swal.fire({ icon:'error', title: msg }); }
        }
      },
      error: function(){
        if (window.Swal) { Swal.fire({ icon:'error', title:'Request failed. Please try again.' }); }
      }
    });
  });

  // fetch account code info -> edit modal
  $(document).on('click', '.editacct', function(e){
    e.preventDefault();
    var id = $(this).val();
    if (!id) { return; }

    $.ajax({
      type: 'GET',
      url: '../auth/auth.php',
      data: { accntcode: id },
      dataType: 'json',
      success: function(res){
        if (!res || res.status !== 200 || !res.data) {
          if (window.Swal) { Swal.fire({ icon:'error', title:(res && res.message) ? res.message : 'Unable to load account code.' }); }
          return;
        }
        $('#AccntId').val(res.data.id || '');
        $('#eacctname').val(res.data.account_name || '');
        $('#eacctcode').val(res.data.account_code || '');
        $('#editAccntModal').modal('show');
      },
      error: function(){
        if (window.Swal) { Swal.fire({ icon:'error', title:'Unable to load account code.' }); }
      }
    });
  });
 $(document).on('click','.delacct', function(e){ //to delate account code
    e.preventDefault();

    if(confirm("Are you sure? ")){
      var delacct = $(this).val();
      $.ajax({
        type: "POST",
        url: "../auth/auth.php",
        data:{
          'delete_acct':true,
          'delacct':delacct
        },
        success:function(response){
          var res = jQuery.parseJSON(response);
          if (res.status == 200){
            
            Swal.fire({
                position: 'center',
                icon: 'success',
                title: 'Account Code Deleted!',
                showConfirmButton: false,
                timer: 2000
          }).then(()=>{
            if (window.GSO && window.GSO.GfAccountCodes && typeof window.GSO.GfAccountCodes.reload === 'function') {
              window.GSO.GfAccountCodes.reload();
            } else {
              location.reload();
            }
              }); 
            }
          }
      });
    }
 });
//employee
$(document).on('submit','#emp_form',function(e){// to save employee information
  e.preventDefault();
  var fd = new FormData(this);
  fd.append("save_employee_info",true);
  $.ajax({
    type: "POST",
    url:  "../auth/auth.php",
    data: fd,
    processData: false,
    contentType: false,
    success:function(response){
      var res = jQuery.parseJSON(response);
      if(res.status==200){
        Swal.fire({
          position: 'center',
          icon: 'success',
          title: 'Save Successfully',
          showConfirmButton: false,
          timer: 1700
        }).then(()=>{
          $('#addEmployeeModal').modal('hide');
          $('#emp_form')[0].reset();
          location.reload();
      });  
      }
    }
  })
});

$(document).on('click','.editEmployee', function(){// to fetch employee information
  var empId = $(this).val();
  $.ajax({
    type:'GET',
    url:'../auth/auth.php?empid='+ empId,
    success:function(response){

      var res = jQuery.parseJSON(response);
      
     if(res.status == 200){
        $('#empId').val(res.data.emp_id);
        $('#name').val(res.data.name);
          $('#edepartment').val(res.data.department_code || '');
        $('#eposition').val(res.data.position);
        $('#epcustodian').val(res.data.property_custodian);
        $('#editEmployeeModal').modal('show');
      }
    }
  });
});

$(document).on('submit','#emp_update',function(e){//to update employee information
  e.preventDefault();
  var fd = new FormData(this);
  fd.append("update_employee_info", true);

  $.ajax({
    type: "POST",
    url: "../auth/auth.php",
    data: fd,
    processData: false,
    contentType: false,
    timeout: 20000,
    success: function(response){
      var res = jQuery.parseJSON(response);

      if(res.status==200){
        Swal.fire({
          position: 'center',
          icon: 'success',
          title: 'Employee Updated Successfully',
          showConfirmButton: false,
          timer: 2000
        }).then(()=>{
          $('#editEmployeeModal').modal('hide');
          $('#emp_update')[0].reset();
          location.reload();
        });     
      }
    }
  })
});

$(document).on('click','.deleteEmployee', function(e){//to delete employee data
  e.preventDefault();
  if(confirm("Are you sure? ")){
  var delemployee = $(this).val();
  
  $.ajax({
    type: "POST",
    url: "../auth/auth.php",
    data:{
      'delete_emp':true,
      'delemployee':delemployee
    },
    success:function(response){
      var res = jQuery.parseJSON(response);
      if(res.status==200){
        alert(res.message);
        location.reload();
      }
    }
  });
}
});
//employee

//department
$(document).on('submit','#dept_form',function(e){//to save department information
  e.preventDefault();
  var fd = new FormData(this);
  fd.append("save_dept",true);

  $.ajax({
    type: "POST",
    url: "../auth/auth.php",
    data: fd,
    processData: false,
    contentType: false,
    success:function(response){
      var res = jQuery.parseJSON(response);
        if(res.status == 200 ){
          Swal.fire({
            position: 'center',
            icon: 'success',
            title: 'Save Successfully',
            showConfirmButton: false,
            timer: 1700
          }).then(()=>{
            $('#addDeptModal').modal('hide');
            $('#dept_form')[0].reset();
            location.reload();
        });  
      }
    }
  });
});
$(document).on('click','.editdept', function(){// to fetch department information
  var deptid = $(this).val();
  $.ajax({
    type:'GET',
    url:'../auth/auth.php?deptid='+ deptid,
    success:function(response){

      var res = jQuery.parseJSON(response);
      
     if(res.status == 200){
        $('#DeptId').val(res.data.dept_id);
        // Populate agency type
        var agencyVal = (res.data.agencies || '').trim();
        if(agencyVal){
          // Try direct set first
          $('#eagency_type').val(agencyVal);
          // If not selected (value not in list), append then select
          if($('#eagency_type').val() !== agencyVal){
            $('#eagency_type').append('<option value="'+agencyVal+'">'+agencyVal+'</option>')
                               .val(agencyVal);
          }
        }
        $('#edeptname').val(res.data.department_name);
        $('#edeptcode').val(res.data.department_code);
        $('#editDeptModal').modal('show');
      }
    }
  });
});
$(document).on('submit','#dept_update',function(e){// to update department information
  e.preventDefault();
  var fd = new FormData(this);
  fd.append("update_dept", true);

    $.ajax({
      type: "POST",
      url: "../auth/auth.php",
      data: fd,
      processData: false,
      contentType: false,
      success: function(response){
        var res = jQuery.parseJSON(response);

        if(res.status == 200 ){
          Swal.fire({
            position: 'center',
            icon: 'success',
            title: 'Department Updated Successfully',
            showConfirmButton: false,
            timer: 2000
          }).then(()=>{
            $('#editDeptModal').modal('hide');
            $('#dept_update')[0].reset();
            location.reload();
          });     
          }
        }
    });
});
$(document).on('click','.deldept', function(e){// to delete department data
  e.preventDefault();
  if(confirm("Are you sure? ")){
  var deldept = $(this).val();

  $.ajax({
    type: "POST",
    url: "../auth/auth.php",
    data:{
      'delete_dept':true,
      'deldept':deldept
    },
    success:function(response){
      var res = jQuery.parseJSON(response);
      if(res.status == 500){
        alert(res.message);
      }else{
        alert(res.message);
        location.reload();
      }
    }
  });
}
});
//department

// ------------------------------
// Property clearance: preview + confirm before submit
// ------------------------------
function pcIsNewEmployee(employeeVal){
  return String(employeeVal || '').toLowerCase() === 'add_new_emp';
}

function pcEntrySubmitBtn(){
  return $('#clearanceSubmitButton').closest('button');
}

function pcSyncNewEmployeeSection(employeeVal, sectionSelector, nameInputSelector, msgSelector, btnSelectorOrFn){
  var isNew = pcIsNewEmployee(employeeVal);
  var $section = $(sectionSelector);
  var $name = $(nameInputSelector);
  var $msg = $(msgSelector);
  var $btn = (typeof btnSelectorOrFn === 'function') ? btnSelectorOrFn() : $(btnSelectorOrFn);

  if(isNew){
    $section.slideDown();
    $name.prop('required', true);
  } else {
    $section.slideUp();
    $name.val('').prop('required', false);
    $msg.hide().text('');
    if($btn.length){ $btn.prop('disabled', false); }
  }
}

function pcSelectedEmployeePosition(){
  var $opt = $('#employee option:selected');
  if(!$opt.length){ return ''; }
  var pos = ($opt.data('position') || '').toString().trim();
  return pos;
}

function pcFetchAndFillEmployeePosition(empId){
  var id = (empId || '').toString().trim();
  if(!id){ return; }

  $.ajax({
    url: '../auth/auth.php',
    type: 'GET',
    data: { empid: id },
    dataType: 'json',
    success: function(res){
      if(!res || res.status !== 200 || !res.data){ return; }

      // Avoid overwriting user edits or stale responses
      var currentSelected = ($('#employee').val() || '').toString().trim();
      if(currentSelected !== id){ return; }

      var currentPos = ($('#position').val() || '').toString().trim();
      if(currentPos){ return; }

      var fetchedPos = (res.data.position || '').toString().trim();
      if(fetchedPos){ $('#position').val(fetchedPos); }
    }
  });
}

function pcSyncPositionField(employeeVal){
  // New employee: always require manual entry
  if(pcIsNewEmployee(employeeVal)){
    $('#position').val('');
    return;
  }

  // Nothing selected
  if(!employeeVal){
    $('#position').val('');
    return;
  }

  // Existing employee: fill from DB if present, else leave blank for manual entry
  var pos = pcSelectedEmployeePosition();
  $('#position').val(pos || '');

  // Fallback: if option had no data-position, fetch employee record
  if(!pos){ pcFetchAndFillEmployeePosition(employeeVal); }
}

function pcSetPreviewText(selector, value){
  var txt = (value === null || value === undefined || value === '') ? '-' : String(value);
  $(selector).text(txt);
}

function pcOpenPreviewModal(){
  var deptVal = $('#dept').val() || '';
  var deptText = ($('#dept option:selected').text() || '').trim();

  var employeeVal = $('#employee').val() || '';
  var employeeText = ($('#employee option:selected').text() || '').trim();

  var newEmployeeName = ($('#new_employee_name').val() || '').toString().trim();
  var position = ($('#position').val() || '').toString().trim();

  var ctypeVal = $('#ctype').val() || '';
  var ctypeText = ($('#ctype option:selected').text() || '').trim();

  var ornumber = ($('#ornumber').val() || '').toString().trim();
  var address = ($('#address').val() || '').toString().trim();
  var cityVal = $('#city').val() || '';
  var cityText = ($('#city option:selected').text() || '').trim();

  // hidden values for submission
  $('#pv_ctrlno').val($('#ctrlno').val() || '');
  $('#pv_dept').val(deptVal);
  $('#pv_employee').val(employeeVal);
  $('#pv_emp_id').val('');
  $('#pv_new_employee_name').val(newEmployeeName);
  $('#pv_position').val(position);
  $('#pv_ctype').val(ctypeVal);
  $('#pv_ornumber').val(ornumber);
  $('#pv_address').val(address);
  $('#pv_city').val(cityVal);

  // readable preview
  pcSetPreviewText('#pv_txt_dept', deptText);
  if(pcIsNewEmployee(employeeVal)){
    pcSetPreviewText('#pv_txt_employee', newEmployeeName ? (newEmployeeName.toUpperCase()) : employeeText);
  } else {
    pcSetPreviewText('#pv_txt_employee', employeeText);
  }
  pcSetPreviewText('#pv_txt_position', position ? position.toUpperCase() : position);
  pcSetPreviewText('#pv_txt_ctype', ctypeText);
  pcSetPreviewText('#pv_txt_ornumber', ornumber);
  pcSetPreviewText('#pv_txt_address', address ? address.toUpperCase() : address);
  pcSetPreviewText('#pv_txt_city', cityText);

  $('#addClearanceModal').modal('hide');
  $('#pcPreviewModal').modal('show');
}

function pcBindNewEmployeeNameValidation(inputSelector, msgSelector, btnSelectorOrFn){
  var debounceTimer;
  $(document).off('input.pcName', inputSelector).on('input.pcName', inputSelector, function(){
    clearTimeout(debounceTimer);
    var name = ($(this).val() || '').toString().trim();
    var $msg = $(msgSelector);
    var $btn = (typeof btnSelectorOrFn === 'function') ? btnSelectorOrFn() : $(btnSelectorOrFn);

    if(!name){
      $msg.hide().text('');
      if($btn.length){ $btn.prop('disabled', false); }
      return;
    }

    $msg.show().text('Validating...').css('color','red');
    if($btn.length){ $btn.prop('disabled', true); }

    debounceTimer = setTimeout(function(){
      $.ajax({
        url: '../auth/auth.php',
        type: 'POST',
        data: { validate_employee_name: 1, emp_name: name },
        dataType: 'json',
        success: function(res){
          if(res && res.exists){
            $msg.text('Employee name already exists!').css('color','red');
            if($btn.length){ $btn.prop('disabled', true); }
          } else {
            $msg.text('Employee name is available.').css('color','green');
            if($btn.length){ $btn.prop('disabled', false); }
          }
        },
        error: function(){
          $msg.text('Validation error.').css('color','red');
          if($btn.length){ $btn.prop('disabled', true); }
        }
      });
    }, 700);
  });
}

$(document).off('change.pcEmployee', '#employee').on('change.pcEmployee', '#employee', function(){
  var employeeVal = $(this).val();
  pcSyncNewEmployeeSection(employeeVal, '#add_new_employee', '#new_employee_name', '#name-validation-msg', pcEntrySubmitBtn);
  pcSyncPositionField(employeeVal);
});
pcBindNewEmployeeNameValidation('#new_employee_name', '#name-validation-msg', pcEntrySubmitBtn);

// Property Clearance: department type-to-search + employee select2
$(function(){
  if(!$('#pc_form').length){ return; }

  // Init department datalist autocomplete (type to search) for clearance modal
  if(window.GSO && window.GSO.UI && typeof window.GSO.UI.initDeptDatalistAutocomplete === 'function'){
    window.GSO.UI.initDeptDatalistAutocomplete({
      select: '#dept',
      input: '#deptSearch',
      list: '#deptDatalist',
      modal: '#addClearanceModal'
    });
  }

  // Init Select2 for employee dropdown when the modal opens
  $('#addClearanceModal').off('shown.bs.modal.pcSelect2').on('shown.bs.modal.pcSelect2', function(){
    if(window.GSO && window.GSO.UI && typeof window.GSO.UI.initPcEmployeeSelect2 === 'function'){
      window.GSO.UI.initPcEmployeeSelect2();
    }
  });
});

$(document).off('submit.pcForm', '#pc_form').on('submit.pcForm', '#pc_form', function(e){
  e.preventDefault();
  var $form = $(this);
  if (typeof $form.valid === 'function' && !$form.valid()) { return; }
  pcOpenPreviewModal();
});

$(document).off('click.pcPreviewBack', '#pcPreviewBackBtn').on('click.pcPreviewBack', '#pcPreviewBackBtn', function(){
  $('#pcPreviewModal').modal('hide');
  $('#addClearanceModal').modal('show');
});

$(document).off('click.pcPreviewConfirm', '#pcPreviewConfirmBtn').on('click.pcPreviewConfirm', '#pcPreviewConfirmBtn', function(){
  var $btn = $(this);
  if ($btn.data('submitting')) { return; }

  var formEl = document.getElementById('pc_preview_form');
  if (formEl && !formEl.checkValidity()) { formEl.reportValidity(); return; }

  Swal.fire({
    icon: 'question',
    title: 'Submit the application now?',
    showCancelButton: true,
    confirmButtonText: 'YES',
    cancelButtonText: 'NO'
  }).then(function(result){
    if(!result.isConfirmed){
      // NO -> keep preview modal open
      return;
    }

    $btn.data('submitting', true);
    var originalHtml = $btn.html();
    $btn.prop('disabled', true).html('<i class="fas fa-circle-notch fa-spin"></i>&nbsp;Submitting...');

    var fd = new FormData(formEl);
    fd.append('save_pc', true);

    $.ajax({
      type: 'POST',
      url: '../auth/auth.php',
      data: fd,
      processData: false,
      contentType: false,
      success: function(response){
        var res;
        try { res = jQuery.parseJSON(response || '{}'); }
        catch(e){
          Swal.fire({ icon:'error', title:'Unexpected response', text:'The server returned an invalid response. Please try again.' });
          return;
        }

        if(res.status == 200){
          Swal.fire({ position:'center', icon:'success', title:'Form submitted successfully.', showConfirmButton:false, timer:1500 });
          setTimeout(function(){
            $('#pcPreviewModal').modal('hide');
            $('#addClearanceModal').modal('hide');
            var entryForm = document.getElementById('pc_form');
            if(entryForm){ entryForm.reset(); }

            if(res.should_print){
              var url = '../admin/print-property-clearance.php?control_id=' + encodeURIComponent(res.control_number);
              var win = window.open(url, '_blank');
              if (win) { win.print(); }
              else { alert('Please allow popups for this site.'); }
            }
            location.reload();
          }, 1700);
        } else if (res.status == 409) {
          Swal.fire({ icon:'warning', title:'Application already in process', text: res.message || 'This employee already has an ongoing RESIGNATION/RETIREMENT clearance.' });
        } else if (res.status == 422) {
          Swal.fire({ icon:'warning', title:'Validation error', text: res.message || 'Please check your inputs.' });
        } else if (res.status == 500) {
          Swal.fire({ icon:'error', title:'Server error', text: res.message || 'Unexpected error occurred.' });
        } else {
          Swal.fire({ icon:'info', title:'Notice', text: res.message || 'Unexpected response.' });
        }
      },
      error: function(){
        Swal.fire({ icon:'error', title:'Network error', text:'Please check your connection and try again.' });
      },
      complete: function(){
        $btn.data('submitting', false);
        $btn.prop('disabled', false).html(originalHtml);
      }
    });
  });
});
// ------------------------------
// End property clearance preview
// ------------------------------

// ==========================================================
// Add Item: Bundle equipment property numbers
// - Bundle rows are assigned to a parent set
// - Each bundle row mirrors the selected parent set's property number
// - Bundle set selection drives both property number and multi-end-user mapping
// ==========================================================
window.GSO = window.GSO || {};
window.GSO.AddItemBundle = window.GSO.AddItemBundle || (function(){
  var inited = false;
  var refreshTimer = null;
  var bundleParIcsCache = {};

  function hasUI(){
    return $('#addItemModal').length && $('#bundleRows').length && $('#btnAddBundleRow').length;
  }

  function esc(text){ return $('<div>').text(text === null || text === undefined ? '' : String(text)).html(); }

  function setBundleHelp(msg){
    var $h = $('#bundleHelp');
    if(!$h.length){ return; }
    if(!msg){ $h.hide().text(''); return; }
    $h.show().text(String(msg));
  }

  function getParentQty(){
    var q = parseInt($('#quantity').val(), 10);
    if (!q || q < 1) { q = 1; }
    return q;
  }

  function getParentPropertyNumbersPreview(){
    var out = [];
    $('#itemSetRows .js-item-property-number').each(function(){
      var value = String($(this).val() || '').trim();
      out.push(value);
    });
    return out.filter(function(v){ return v !== ''; });
  }

  function nextPropertyNumber(num){
    var parts = String(num || '').split('-');
    if (parts.length < 4) { return String(num || ''); }
    var idx = parts.length - 2;
    var seq = String(parts[idx] || '');
    if (!seq || !/^\d+$/.test(seq)) { return String(num || ''); }
    var pad = seq.length;
    parts[idx] = String((parseInt(seq, 10) || 0) + 1).padStart(pad, '0');
    return parts.join('-');
  }

  function splitPreviewValues(raw){
    return String(raw || '')
      .split(/[\n\r,]+/)
      .map(function(value){ return String(value || '').trim(); })
      .filter(function(value){ return value !== ''; });
  }

  function getParentSetQuantity(setIndex){
    var index = parseInt(setIndex, 10) || 0;
    if (index < 1) { return 0; }
    var qty = parseInt($('#itemSetRows .item-set-card[data-set-index="' + index + '"] .js-item-quantity').val(), 10);
    return qty && qty > 0 ? qty : 1;
  }

  function getParentSetPropertyNumbers(setIndex){
    var index = parseInt(setIndex, 10) || 0;
    if (index < 1) { return []; }
    var preview = $('#itemSetRows .item-set-card[data-set-index="' + index + '"] .js-item-property-number').val();
    return splitPreviewValues(preview);
  }

  function buildBundleSetOptionsHtml(selected){
    var qty = getParentQty();
    var sel = String(selected || '').trim();
    var html = '<option value="">-SELECT-</option>';
    for (var i = 1; i <= qty; i++) {
      var s = (sel === String(i)) ? ' selected' : '';
      html += '<option value="' + i + '"' + s + '>Set ' + i + ' (Unit ' + i + ')</option>';
    }
    return html;
  }

  function syncBundleSetSelectors(){
    var qty = getParentQty();
    bundleRowEls().each(function(){
      var $sel = $(this).find('select[name="bundle_parent_index[]"]');
      if (!$sel.length) { return; }
      var current = String($sel.val() || '').trim();
      $sel.html(buildBundleSetOptionsHtml(current));
      if (!current || (parseInt(current, 10) > qty)) {
        $sel.val('');
      }
      if (qty === 1 && !$sel.val()) {
        $sel.val('1');
      }
    });
  }

  function syncBundlePropertyNumbers(){
    bundleRowEls().each(function(){
      var $row = $(this);
      var setVal = String($row.find('select[name="bundle_parent_index[]"]').val() || '').trim();
      var numbers = getParentSetPropertyNumbers(setVal);
      var previewText = numbers.join(', ');
      $row.find('.js-bundle-property-preview').val(previewText);
      $row.find('.bundle-copy-row').each(function(copyIndex){
        var propNumber = numbers[copyIndex] || '';
        $(this).find('.js-bundle-property-number').val(propNumber);
      });
    });
  }

  function getItemParIcsCodeByCategory(category){
    var target = String(category || '').trim().toUpperCase();
    if (!target) { return ''; }
    var code = '';
    $('#itemSetRows .item-set-card').each(function(){
      var itemCategory = String($(this).find('.js-item-category').val() || '').trim().toUpperCase();
      if (itemCategory !== target) { return; }
      code = String($(this).find('.js-item-par-ics-value').val() || '').trim().toUpperCase();
      if (code) { return false; }
    });
    return code;
  }

  function setBundleParIcsPreview($row, value){
    var code = String(value || '').trim().toUpperCase();
    $row.find('.js-bundle-par-ics-preview').val(code);
  }

  function refreshBundleParIcsNumbers(){
    bundleRowEls().each(function(){
      var $row = $(this);
      var category = String($row.find('.js-bundle-category').val() || '').trim().toUpperCase();
      if (!category) {
        setBundleParIcsPreview($row, '');
        return;
      }
      var existingCode = getItemParIcsCodeByCategory(category) || bundleParIcsCache[category] || '';
      if (existingCode) {
        setBundleParIcsPreview($row, existingCode);
        return;
      }
      $.ajax({
        url: '../auth/auth.php',
        type: 'POST',
        cache: false,
        dataType: 'json',
        data: {
          generate_par_ics_code: 1,
          category: category,
          condition: String($('#condition').val() || '').trim().toUpperCase()
        },
        success: function(resp){
          var code = (resp && Number(resp.status) === 200 && resp.code) ? String(resp.code).trim().toUpperCase() : '';
          if (!code) { return; }
          bundleParIcsCache[category] = code;
          bundleRowEls().each(function(){
            var $bundleRow = $(this);
            if (String($bundleRow.find('.js-bundle-category').val() || '').trim().toUpperCase() === category) {
              setBundleParIcsPreview($bundleRow, code);
            }
          });
        }
      });
    });
  }

  function bundleRowEls(){
    return $('#bundleRows .bundle-row');
  }

  function syncBundleCardVisibility(animate){
    var hasRows = bundleRowEls().length > 0;
    var $body = $('#bundleCardBody');
    var $rows = $('#bundleRows');
    var doAnim = (animate !== false);
    var speed = doAnim ? 320 : 0;
    var easing = 'swing';

    if (hasRows) {
      $rows.show();
      if ($body.is(':visible')) { return; }
      $body.stop(true, true).slideDown(speed, easing);
      return;
    }
    if (!$body.is(':visible')) {
      $rows.hide().empty();
      return;
    }
    $body.stop(true, true).slideUp(speed, easing, function(){
      $rows.hide();
    });
  }

  function getBundleAssetOptionsHtml(){
    var html = String($('#itemAssetOptionsTemplate').html() || '');
    if (!html || !html.trim()) { html = '<option value="">-SELECT-</option>'; }
    return html;
  }

  function getBundleUnitOptionsHtml(){
    return String($('#itemUnitOptionsTemplate').html() || '<option value="">-SELECT-</option>');
  }

  function getBundleCategoryOptionsHtml(){
    return ''
      + '<option value="">-SELECT-</option>'
      + '<option value="PAR">PAR</option>'
      + '<option value="ICS">ICS</option>';
  }

  function getParentSetCategory(setIndex){
    var index = parseInt(setIndex, 10) || 0;
    if (index < 1) { return ''; }
    return String($('#itemSetRows .item-set-card[data-set-index="' + index + '"] .js-item-category').val() || '').trim().toUpperCase();
  }

  function getBundleGroupState($group){
    var serials = {};
    $group.find('.bundle-copy-row').each(function(){
      var rowIndex = parseInt($(this).attr('data-copy-index'), 10) || 0;
      if (rowIndex < 1) { return; }
      serials[rowIndex] = {
        serial1: String($(this).find('.js-bundle-serial1').val() || ''),
        serial2: String($(this).find('.js-bundle-serial2').val() || '')
      };
    });
    return {
      setIndex: String($group.find('.js-bundle-parent-index').val() || '').trim(),
      category: String($group.find('.js-bundle-category').val() || '').trim().toUpperCase(),
      unit: String($group.find('.js-bundle-unit').val() || '').trim().toUpperCase(),
      asset: String($group.find('.js-bundle-asset').val() || '').trim().toUpperCase(),
      model: String($group.find('.js-bundle-brand-model').val() || ''),
      noBrand: $group.find('.bundle-no-brand-model').is(':checked'),
      addSerial: $group.find('.js-bundle-add-serial').is(':checked'),
      description: String($group.find('.js-bundle-description').val() || ''),
      serials: serials
    };
  }

  function getBundleSerialValue(serials, rowIndex, fieldName){
    if (serials && serials[rowIndex] && serials[rowIndex][fieldName] !== undefined) {
      return String(serials[rowIndex][fieldName] || '');
    }
    return '';
  }

  function buildBundleSerialRowsHtml(state){
    var parentIndex = state && state.setIndex ? state.setIndex : '';
    if (!parentIndex) {
      return '<div class="text-muted py-2">Select a bundle set to load the per-unit bundle rows.</div>';
    }

    var qty = getParentSetQuantity(parentIndex);
    var numbers = getParentSetPropertyNumbers(parentIndex);
    var html = ''
      + '<div class="table-responsive gso-serial-table-scroll">'
      + '<table class="table table-sm table-bordered mb-0">'
      + '<thead class="bg-light">'
      + '<tr>'
      + '<th style="width:80px;">Unit</th>'
      + '<th>Primary Serial Number</th>'
      + '<th>Secondary Serial Number</th>'
      + '</tr>'
      + '</thead><tbody>';

    for (var copyIndex = 1; copyIndex <= qty; copyIndex++) {
      html += '<tr class="bundle-copy-row" data-copy-index="' + copyIndex + '">'
        + '<td class="align-middle text-center">' + copyIndex + '</td>'
        + '<td><input type="text" class="form-control text-uppercase js-bundle-serial1" placeholder="Enter primary serial number" value="' + esc(getBundleSerialValue(state.serials, copyIndex, 'serial1')) + '"></td>'
        + '<td><input type="text" class="form-control text-uppercase js-bundle-serial2" placeholder="Enter secondary serial number" value="' + esc(getBundleSerialValue(state.serials, copyIndex, 'serial2')) + '"><input type="hidden" class="js-bundle-property-number" value="' + esc(numbers[copyIndex - 1] || '') + '"></td>'
        + '</tr>';
    }

    html += '</tbody></table></div>';
    return html;
  }

  function applyBundleSerialVisibility($group){
    if (!$group || !$group.length) { return; }
    var showSerial = $group.find('.js-bundle-add-serial').is(':checked');
    $group.find('.js-bundle-serial-row').toggle(showSerial);
  }

  function syncBundleGroup($group){
    if (!$group || !$group.length) { return; }
    var state = getBundleGroupState($group);
    var currentSet = state.setIndex;
    var $setSelect = $group.find('.js-bundle-parent-index');

    if ($setSelect.length) {
      $setSelect.html(buildBundleSetOptionsHtml(currentSet));
      if (!currentSet || parseInt(currentSet, 10) > getParentQty()) {
        currentSet = '';
        if (getParentQty() === 1) {
          currentSet = '1';
        }
        $setSelect.val(currentSet);
      } else {
        $setSelect.val(currentSet);
      }
    }

    state.setIndex = currentSet;
    if (!$group.find('.js-bundle-category').val()) {
      var parentCategory = getParentSetCategory(currentSet || 1);
      if (parentCategory && $group.find('.js-bundle-category option[value="' + parentCategory + '"]').length) {
        $group.find('.js-bundle-category').val(parentCategory);
      }
      state.category = String($group.find('.js-bundle-category').val() || '').trim().toUpperCase();
    }

    $group.find('.js-bundle-serial-table-wrap').html(buildBundleSerialRowsHtml(state));
    applyBundleSerialVisibility($group);
  }

  function addBundleRow(){
    var assetOpts = getBundleAssetOptionsHtml();
    var unitOpts = getBundleUnitOptionsHtml();
    var catOpts = getBundleCategoryOptionsHtml();
    var idx = Date.now().toString(36) + Math.random().toString(36).slice(2, 7);

    var row = ''
      + '<div class="border rounded p-3 mb-2 bundle-row" data-bundle-row="' + esc(idx) + '">'
      + '  <div class="d-flex justify-content-between align-items-center mb-3">'
      + '    <strong>Bundle Equipment</strong>'
      + '    <button type="button" class="btn btn-sm btn-outline-danger btnRemoveBundleRow" title="Remove">'
      + '      <i class="fas fa-trash"></i>'
      + '    </button>'
      + '  </div>'
      + '  <div class="form-row">'
      + '    <div class="form-group col-md-2">'
      + '      <label>Bundle Set</label>'
      + '      <select class="form-control js-bundle-parent-index" name="bundle_parent_index[]">' + buildBundleSetOptionsHtml('') + '</select>'
      + '    </div>'
      + '    <div class="form-group col-md-2">'
      + '      <label>Category</label>'
      + '      <select class="form-control js-bundle-category" name="bundle_category[]">' + catOpts + '</select>'
      + '    </div>'
      + '    <div class="form-group col-md-2">'
      + '      <label>Unit</label>'
      + '      <select class="form-control js-bundle-unit" name="bundle_unit[]">' + unitOpts + '</select>'
      + '    </div>'
      + '    <div class="form-group col-md-3">'
      + '      <label>Asset Class</label>'
      + '      <select class="form-control js-bundle-asset" name="bundle_asset_class[]">' + assetOpts + '</select>'
      + '    </div>'
      + '    <div class="form-group col-md-3">'
      + '      <label>Brand/Model'
      + '        <span class="text-muted" style="margin-left:10px;">'
      + '          <input type="checkbox" class="form-check-input bundle-no-brand-model" style="position:static; margin-left:0; margin-right:6px;">'
      + '          <span class="form-check-label">no brand/model</span>'
      + '        </span>'
      + '      </label>'
      + '      <input type="text" class="form-control text-uppercase js-bundle-brand-model" name="bundle_brand_model[]" placeholder="Enter brand/model">'
      + '    </div>'
      + '  </div>'
      + '  <div class="form-row">'
      + '    <div class="form-group col-12">'
      + '      <div class="d-flex align-items-center justify-content-between mb-2">'
      + '        <label class="mb-0">Description</label>'
      + '        <div class="form-check form-check-inline mb-0 text-muted">'
      + '          <input type="checkbox" class="form-check-input js-bundle-add-serial" style="position:static; margin-left:0; margin-right:6px;">'
      + '          <label class="form-check-label">add serial number</label>'
      + '        </div>'
      + '      </div>'
      + '      <textarea class="form-control text-uppercase js-bundle-description" name="bundle_description[]" rows="4" placeholder="Enter description"></textarea>'
      + '    </div>'
      + '  </div>'
      + '  <div class="form-row js-bundle-serial-row" style="display:none;">'
      + '    <div class="form-group col-12">'
      + '      <div class="js-bundle-serial-table-wrap"></div>'
      + '    </div>'
      + '  </div>'
      + '  <div class="form-row">'
      + '    <div class="form-group col-md-6">'
      + '      <label>PAR/ICS No.</label>'
      + '      <textarea class="form-control js-bundle-par-ics-preview" rows="3" readonly></textarea>'
      + '    </div>'
      + '    <div class="form-group col-md-6">'
      + '      <label>Property No.</label>'
      + '      <textarea class="form-control js-bundle-property-preview" rows="3" readonly></textarea>'
      + '    </div>'
      + '  </div>'
      + '</div>';

    $('#bundleRows').append(row).show();
    var $last = $('#bundleRows .bundle-row').last();
    var defaultSet = getParentQty() === 1 ? '1' : '';
    if (defaultSet) {
      $last.find('.js-bundle-parent-index').val(defaultSet);
    }
    syncBundleGroup($last);
    syncBundleCardVisibility(true);
    try { $('#deptSearch').trigger('change'); } catch(e) {}
    scheduleRefresh();
  }

  function scheduleRefresh(){
    clearTimeout(refreshTimer);
    refreshTimer = setTimeout(function(){
      refreshBundlePropertyNumbers();
    }, 250);
  }

  function refreshBundlePropertyNumbers(){
    if (!hasUI()) { return; }
    if (!bundleRowEls().length) { return; }

    bundleRowEls().each(function(){
      syncBundleGroup($(this));
    });

    var fund = String($('#fund').val() || '').trim().toUpperCase();
    if (fund === 'TRUST FUND' || fund === 'DONATION') {
      $('.js-bundle-property-number').val('');
      $('.js-bundle-property-preview').val('');
      setBundleHelp('');
      refreshBundleParIcsNumbers();
      return;
    }

    if (!getParentPropertyNumbersPreview().length) {
      $('.js-bundle-property-number').val('');
      $('.js-bundle-property-preview').val('');
      setBundleHelp('');
      refreshBundleParIcsNumbers();
      return;
    }
    setBundleHelp('');
    syncBundlePropertyNumbers();
    refreshBundleParIcsNumbers();
  }

  function init(){
    if (inited) { return; }
    if (!hasUI()) { return; }
    inited = true;

    $(document).off('click.addBundleRow', '#btnAddBundleRow').on('click.addBundleRow', '#btnAddBundleRow', function(){
      addBundleRow();
    });

    $(document).off('click.removeBundleRow', '.btnRemoveBundleRow').on('click.removeBundleRow', '.btnRemoveBundleRow', function(){
      $(this).closest('.bundle-row').remove();
      syncBundleCardVisibility(true);
      scheduleRefresh();
    });

    $(document).off('change.bundleCategory', '.js-bundle-category').on('change.bundleCategory', '.js-bundle-category', function(){
      scheduleRefresh();
    });

    $(document).off('change.bundleAddSerial', '.js-bundle-add-serial').on('change.bundleAddSerial', '.js-bundle-add-serial', function(){
      applyBundleSerialVisibility($(this).closest('.bundle-row'));
    });

    $(document).off('change.bundleSet', '.js-bundle-parent-index').on('change.bundleSet', '.js-bundle-parent-index', function(){
      var $row = $(this).closest('.bundle-row');
      var $category = $row.find('.js-bundle-category');
      if ($category.length && !$category.val()) {
        var parentCategory = getParentSetCategory($(this).val());
        if (parentCategory && $category.find('option[value="' + parentCategory + '"]').length) {
          $category.val(parentCategory);
        }
      }
      syncBundleGroup($row);
      scheduleRefresh();
    });

    $(document).off('input.bundleQty change.bundleQty', '#quantity').on('input.bundleQty change.bundleQty', '#quantity', function(){
      bundleRowEls().each(function(){
        syncBundleGroup($(this));
      });
      scheduleRefresh();
    });

    // Bundle Brand/Model: "no brand/model" checkbox behavior
    $(document).off('change.bundleNoBrandModel', '#bundleRows .bundle-no-brand-model').on('change.bundleNoBrandModel', '#bundleRows .bundle-no-brand-model', function(){
      var $cb = $(this);
      var $row = $cb.closest('.bundle-row');
      var $model = $row.find('.js-bundle-brand-model');
      if(!$model.length){ return; }

      var checked = $cb.is(':checked');
      var DEFAULT_VAL = 'NO BRAND/MODEL';
      if(checked){
        $model.val(DEFAULT_VAL).prop('readonly', true);
      } else {
        if(String($model.val() || '').trim().toUpperCase() === DEFAULT_VAL){
          $model.val('');
        }
        $model.prop('readonly', false);
      }
    });

    // Parent fields that influence numbering
    $(document).off('change.bundleParent', '#fund, #year, .js-item-account-code, #dept').on('change.bundleParent', '#fund, #year, .js-item-account-code, #dept', function(){
      scheduleRefresh();
    });

    // Department is a type-to-search input; refresh when it changes too.
    $(document).off('change.bundleDeptSearch', '#deptSearch').on('change.bundleDeptSearch', '#deptSearch', function(){
      scheduleRefresh();
    });

    // Parent category: if bundle rows have no category selected yet, default them from the chosen parent set.
    $(document).off('change.bundleParentCat', '.js-item-category').on('change.bundleParentCat', '.js-item-category', function(){
      bundleRowEls().each(function(){
        var $row = $(this);
        var $sel = $row.find('.js-bundle-category');
        var cur = String($sel.val() || '').trim();
        if (cur) { return; }
        var parentCategory = getParentSetCategory($row.find('.js-bundle-parent-index').val());
        if (parentCategory && $sel.find('option[value="' + parentCategory + '"]').length) {
          $sel.val(parentCategory);
        }
      });
      scheduleRefresh();
    });

    $('#addItemModal').off('shown.bs.modal.addItemBundle').on('shown.bs.modal.addItemBundle', function(){
      syncBundleCardVisibility(false);
      bundleParIcsCache = {};
      bundleRowEls().each(function(){
        syncBundleGroup($(this));
      });
      scheduleRefresh();
    });
  }

  // Best-effort auto-init
  $(function(){ init(); });

  return {
    init: init,
    refreshBundlePropertyNumbers: refreshBundlePropertyNumbers,
    syncBundleCardVisibility: syncBundleCardVisibility
  };
})();

// ==========================================================
// Add Item: Year Acquired
// ==========================================================
window.GSO = window.GSO || {};
window.GSO.AddItemMeta = window.GSO.AddItemMeta || (function(){
  var inited = false;

  function hasUI(){
    return $('#addItemModal').length && $('#addItem').length;
  }

  function fillYearOptions(){
    var $year = $('#year');
    if (!$year.length) { return; }

    // If already populated, leave it (avoid clobbering user's selection)
    if ($year.find('option').length > 1) { return; }

    var start = 2001;
    var current = new Date().getFullYear();
    var options = "<option value='' >-SELECT-</option>";
    for (var y = current; y >= start; y--) {
      options += "<option value='" + y + "'>" + y + "</option>";
    }
    options += "<option value='RFS'>Found at Station</option>";
    $year.html(options);
  }

  function syncYearLockWithCondition(){
    var $year = $('#year');
    if (!$year.length) { return; }

    var cond = String($('#condition').val() || '').trim().toUpperCase();
    var currentYear = String(new Date().getFullYear());

    // Ensure options exist (server usually renders them, but keep this as a fallback)
    if ($year.find('option').length === 0) {
      fillYearOptions();
    }

    if (!cond) {
      $year.val('');
      $year.prop('disabled', true);
      $year.trigger('change');
    } else if (cond === 'NEW') {
      $year.val(currentYear);
      $year.prop('disabled', true);
      $year.trigger('change');
    } else {
      $year.prop('disabled', false);
    }
  }

  function onConditionChange(){
    if (!hasUI()) { return; }
    syncYearLockWithCondition();
  }

  function init(){
    if (inited) { return; }
    if (!hasUI()) { return; }
    inited = true;

    // Ensure Year dropdown is always populated when modal opens
    $('#addItemModal')
      .off('shown.bs.modal.addItemMeta')
      .on('shown.bs.modal.addItemMeta', function(){
        fillYearOptions();
        syncYearLockWithCondition();
        // Enforce NEW-condition default behavior on open (browser may restore previous selection).
        onConditionChange();
      });

    $(document)
      .off('change.addItemMetaCond', '#condition')
      .on('change.addItemMetaCond', '#condition', onConditionChange);

  }

  $(function(){ init(); });
  return { init: init, fillYearOptions: fillYearOptions, syncYearLockWithCondition: syncYearLockWithCondition };
})();

// ==========================================================
// Add Item page UI (admin/add-item.php)
// - Migrated from inline JS to keep templates clean
// - Guarded so it only runs on the Add Item modal/form
// ==========================================================
window.GSO = window.GSO || {};
window.GSO.AddItemPage = window.GSO.AddItemPage || (function(){
  var inited = false;
  var MAX_ITEM_SETS = 100;

  var empOptionsAll = '';
  var itemSetByRow = {};
  var endUserByRow = {};

  var reqFundDepts = null;
  var debFundDepts = null;
  var debPropNum = null;
  var debParIcsNum = null;

  function hasUI(){
    return $('#addItemModal').length && $('#addItem').length;
  }

  function toRowKey(rowIndex){
    var n = parseInt(rowIndex, 10);
    if (!n || n < 1) { return null; }
    return String(n);
  }

  function esc(text){
    return $('<div>').text(text === null || text === undefined ? '' : String(text)).html();
  }

  function normalizeSetCount(count){
    var normalized = parseInt(count, 10) || 1;
    if (normalized < 1) { normalized = 1; }
    if (normalized > MAX_ITEM_SETS) { normalized = MAX_ITEM_SETS; }
    return normalized;
  }

  function getSetCount(){
    var count = normalizeSetCount($('#quantity').val());
    if (String($('#quantity').val() || '') !== String(count)) {
      $('#quantity').val(count);
    }
    return count;
  }

  function getSetLabel(setIndex){
    return 'Set ' + setIndex;
  }

  function defaultItemSetState(){
    return {
      itemQuantity: 1,
      category: '',
      unit: '',
      asset: '',
      accountCode: '',
      noAccountProperty: false,
      brand: '',
      noBrand: false,
      addSerial: false,
      description: '',
      serials: {},
      serial1: '',
      serial2: '',
      unitvalue: '0.00',
      noAmount: false,
      remarks: ''
    };
  }

  function getItemSetState(setIndex){
    var idxKey = toRowKey(setIndex);
    if (!idxKey) { return defaultItemSetState(); }
    if (!itemSetByRow[idxKey]) {
      itemSetByRow[idxKey] = defaultItemSetState();
    }
    return $.extend({}, defaultItemSetState(), itemSetByRow[idxKey]);
  }

  function setPrimaryId(fieldName, setIndex){
    if (setIndex !== 1) { return ''; }
    var idMap = {
      category: 'item_category',
      unit: 'unit',
      asset: 'asset',
      brand: 'brand',
      noBrand: 'noBrandModelCheckBox',
      addSerial: 'addSerialCheckBox',
      description: 'description',
      unitvalue: 'unitvalue',
      noAmount: 'noAmountValueCheckBox',
      total: 'total_amount',
      accountCode: 'account_code',
      noAccountProperty: 'noAccountPropertyCheckBox',
      remarks: 'remarks'
    };
    return idMap[fieldName] ? (' id="' + idMap[fieldName] + '"') : '';
  }

  function getItemUnitOptionsHtml(){
    return String($('#itemUnitOptionsTemplate').html() || '<option value="">-SELECT-</option>');
  }

  function getItemCategoryOptionsHtml(){
    return ''
      + '<option value="">-SELECT-</option>'
      + '<option value="PAR">PAR</option>'
      + '<option value="ICS">ICS</option>';
  }

  function getItemAccountCodeOptionsHtml(){
    return String($('#itemAccountCodeOptionsTemplate').html() || '<option value="">-SELECT-</option>');
  }

  function usesManualAccountCodeInput(){
    var fund = String($('#fund').val() || '').trim().toUpperCase();
    return fund === 'TRUST FUND' || fund === 'DONATION';
  }

  function propertyNumberOptionalFund(){
    var fund = String($('#fund').val() || '').trim().toUpperCase();
    return fund === 'TRUST FUND' || fund === 'DONATION';
  }

  function sanitizeManualAccountCode(value){
    return String(value || '').replace(/[^0-9-]/g, '').slice(0, 18);
  }

  function buildAccountCodeControlHtml(setIndex, state){
    var primaryId = setPrimaryId('accountCode', setIndex);
    if (usesManualAccountCodeInput()) {
      return '<input type="text" class="form-control js-item-account-code" name="account_code[' + setIndex + ']" inputmode="numeric" maxlength="18" pattern="^[0-9-]{1,18}$" title="Use numbers and hyphen only, maximum 18 characters" placeholder="Enter account code" value="' + esc(sanitizeManualAccountCode(state.accountCode)) + '" required' + primaryId + '>';
    }
    return '<select class="form-control js-item-account-code" name="account_code[' + setIndex + ']" required' + primaryId + '>' + withSelectedOption(getItemAccountCodeOptionsHtml(), state.accountCode) + '</select>';
  }

  function getItemAssetOptionsHtml(){
    return String($('#itemAssetOptionsTemplate').html() || '<option value="">-SELECT-</option>');
  }

  function withSelectedOption(optionsHtml, selectedValue){
    var $wrap = $('<select>' + optionsHtml + '</select>');
    $wrap.val(String(selectedValue || ''));
    return $wrap.html();
  }

  function parseMoneyValue(raw){
    var s = String(raw || '').replace(/,/g, '').trim();
    var n = parseFloat(s);
    return isNaN(n) ? 0 : n;
  }

  function formatMoneyValue(n){
    var num = Number(n || 0);
    if (!isFinite(num)) { num = 0; }
    return num.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  }

  function applyParIcsPreview(list){
    $('#itemSetRows .item-set-card').each(function(index){
      var value = String(list[index] || '').trim().toUpperCase();
      $(this).find('.js-item-par-ics').val(value);
      $(this).find('.js-item-par-ics-value').val(value);
    });
  }

  function snapshotItemSetInputsIntoCache(){
    $('#itemSetRows .item-set-card').each(function(){
      var $row = $(this);
      var idxKey = toRowKey($row.data('setIndex'));
      if (!idxKey) { return; }
      var serials = {};
      $row.find('.js-item-serial-copy-row').each(function(){
        var copyKey = toRowKey($(this).data('copyIndex'));
        if (!copyKey) { return; }
        serials[copyKey] = {
          serial1: String($(this).find('.js-item-serial1').val() || ''),
          serial2: String($(this).find('.js-item-serial2').val() || '')
        };
      });
      itemSetByRow[idxKey] = {
        itemQuantity: Math.max(1, parseInt($row.find('.js-item-quantity').val(), 10) || 1),
        category: String($row.find('.js-item-category').val() || ''),
        unit: String($row.find('.js-item-unit').val() || ''),
        asset: String($row.find('.js-item-asset').val() || ''),
        accountCode: String($row.find('.js-item-account-code').val() || ''),
        noAccountProperty: $row.find('.js-item-no-account-property').is(':checked'),
        brand: String($row.find('.js-item-brand').val() || ''),
        noBrand: $row.find('.js-item-no-brand').is(':checked'),
        addSerial: $row.find('.js-item-add-serial').is(':checked'),
        description: String($row.find('.js-item-description').val() || ''),
        serials: serials,
        serial1: String((serials['1'] && serials['1'].serial1) || $row.find('.js-item-serial1').first().val() || ''),
        serial2: String((serials['1'] && serials['1'].serial2) || $row.find('.js-item-serial2').first().val() || ''),
        unitvalue: String($row.find('.js-item-unitvalue').val() || '0.00'),
        noAmount: $row.find('.js-item-no-amount').is(':checked'),
        remarks: String($row.find('.js-item-remarks').val() || '')
      };
    });
  }

  function snapshotEndUserInputsIntoCache(){
    $('#endUserRows select.parEmpRow').each(function(){
      var idxKey = toRowKey($(this).data('row'));
      if(!idxKey) return;
      if(!endUserByRow[idxKey]) endUserByRow[idxKey] = { emp: '', newName: '', newPos: '' };
      endUserByRow[idxKey].emp = String($(this).val() || '');
    });
    $('#endUserRows input.parEmpNewName').each(function(){
      var idxKey = toRowKey($(this).data('row'));
      if(!idxKey) return;
      if(!endUserByRow[idxKey]) endUserByRow[idxKey] = { emp: '', newName: '', newPos: '' };
      endUserByRow[idxKey].newName = String($(this).val() || '');
    });
    $('#endUserRows input.parEmpNewPos').each(function(){
      var idxKey = toRowKey($(this).data('row'));
      if(!idxKey) return;
      if(!endUserByRow[idxKey]) endUserByRow[idxKey] = { emp: '', newName: '', newPos: '' };
      endUserByRow[idxKey].newPos = String($(this).val() || '');
    });
  }

  function reindexCache(cacheObj, removedIndex, totalCount){
    var nextCache = {};
    var cursor = 1;
    for (var i = 1; i <= totalCount; i++) {
      if (i === removedIndex) { continue; }
      var srcKey = toRowKey(i);
      var dstKey = toRowKey(cursor);
      if (srcKey && dstKey && cacheObj[srcKey]) {
        nextCache[dstKey] = $.extend({}, cacheObj[srcKey]);
      }
      cursor++;
    }
    return nextCache;
  }

  function getSerialValueForCopy(state, copyIndex, fieldName){
    var copyKey = toRowKey(copyIndex);
    var serials = (state && state.serials) ? state.serials : {};
    if (copyKey && serials[copyKey] && serials[copyKey][fieldName] !== undefined) {
      return String(serials[copyKey][fieldName] || '');
    }
    if (copyIndex === 1 && state && state[fieldName] !== undefined) {
      return String(state[fieldName] || '');
    }
    return '';
  }

  function buildSerialRowsHtml(setIndex, state){
    var itemQty = Math.max(1, parseInt(state.itemQuantity, 10) || 1);
    var html = ''
      + '<div class="table-responsive gso-serial-table-scroll">'
      + '<table class="table table-sm table-bordered mb-0">'
      + '<thead class="bg-light">'
      + '<tr>'
      + '<th style="width:80px;">Item</th>'
      + '<th>Primary Serial Number</th>'
      + '<th>Secondary Serial Number</th>'
      + '</tr>'
      + '</thead><tbody>';
    for (var copyIndex = 1; copyIndex <= itemQty; copyIndex++) {
      html += '<tr class="js-item-serial-copy-row" data-copy-index="' + copyIndex + '">'
        + '<td class="align-middle text-center">' + copyIndex + '</td>'
        + '<td><input type="text" class="form-control text-uppercase js-item-serial1" name="serial[' + setIndex + '][' + copyIndex + ']" placeholder="Enter primary serial number" value="' + esc(getSerialValueForCopy(state, copyIndex, 'serial1')) + '"></td>'
        + '<td><input type="text" class="form-control text-uppercase js-item-serial2" name="serial2[' + setIndex + '][' + copyIndex + ']" placeholder="Enter secondary serial number" value="' + esc(getSerialValueForCopy(state, copyIndex, 'serial2')) + '"></td>'
        + '</tr>';
    }
    html += '</tbody></table></div>';
    return html;
  }

  function refreshSerialRowsForItemSet($row){
    if (!$row || !$row.length) { return; }
    snapshotItemSetInputsIntoCache();
    var setIndex = parseInt($row.data('setIndex'), 10) || 1;
    var state = getItemSetState(setIndex);
    $row.find('.js-item-serial-table-wrap').html(buildSerialRowsHtml(setIndex, state));
    applySerialVisibilityState($row);
  }

  function buildItemSetCardHtml(setIndex){
    var state = getItemSetState(setIndex);
    var removeHidden = (getSetCount() === 1) ? ' style="visibility:hidden;"' : '';
    return ''
      + '<div class="border rounded p-3 item-set-card" data-set-index="' + setIndex + '">'
      + '  <div class="d-flex justify-content-between align-items-center mb-3">'
      + '    <strong>' + getSetLabel(setIndex) + '</strong>'
      + '    <button type="button" class="btn btn-sm btn-outline-danger btnRemoveItemSet" data-set-index="' + setIndex + '" title="Remove set"' + removeHidden + '>'
      + '      <i class="fas fa-trash"></i>'
      + '    </button>'
      + '  </div>'
      + '  <div class="form-row">'
      + '    <div class="form-group col-md-2">'
      + '      <label>Quantity</label>'
      + '      <input type="number" class="form-control js-item-quantity" name="item_quantity[' + setIndex + ']" value="' + esc(state.itemQuantity || 1) + '" min="1" step="1">'
      + '    </div>'
      + '    <div class="form-group col-md-2">'
      + '      <label>Category</label>'
      + '      <select class="form-control js-item-category" name="category[' + setIndex + ']" required' + setPrimaryId('category', setIndex) + '>' + withSelectedOption(getItemCategoryOptionsHtml(), state.category) + '</select>'
      + '    </div>'
      + '    <div class="form-group col-md-2">'
      + '      <label>Unit</label>'
      + '      <select class="form-control js-item-unit" name="unit[' + setIndex + ']" required' + setPrimaryId('unit', setIndex) + '>' + withSelectedOption(getItemUnitOptionsHtml(), state.unit) + '</select>'
      + '    </div>'
      + '    <div class="form-group col-md-3">'
      + '      <label>Asset Class</label>'
      + '      <select class="form-control js-item-asset" name="asset[' + setIndex + ']" required' + setPrimaryId('asset', setIndex) + '>' + withSelectedOption(getItemAssetOptionsHtml(), state.asset) + '</select>'
      + '    </div>'
      + '    <div class="form-group col-md-3">'
      + '      <div class="d-flex align-items-center justify-content-between mb-2">'
      + '        <label class="mb-0">Brand/Model</label>'
      + '        <div class="form-check form-check-inline mb-0 text-muted">'
      + '          <input type="checkbox" class="form-check-input js-item-no-brand" name="item_no_brand[' + setIndex + ']" value="1"' + (state.noBrand ? ' checked' : '') + setPrimaryId('noBrand', setIndex) + '>'
      + '          <label class="form-check-label">none</label>'
      + '        </div>'
      + '      </div>'
      + '      <input type="text" class="form-control text-uppercase js-item-brand" name="brand[' + setIndex + ']" placeholder="Enter Brand and model" value="' + esc(state.brand) + '" required' + setPrimaryId('brand', setIndex) + '>'
      + '    </div>'
      + '  </div>'
      + '  <div class="form-row">'
      + '    <div class="form-group col-md-12">'
      + '      <div class="d-flex align-items-center justify-content-between mb-2">'
      + '        <label class="mb-0">Description</label>'
      + '        <div class="form-check form-check-inline mb-0 text-muted">'
      + '          <input type="checkbox" class="form-check-input js-item-add-serial" name="item_add_serial[' + setIndex + ']" value="1"' + (state.addSerial ? ' checked' : '') + setPrimaryId('addSerial', setIndex) + '>'
      + '          <label class="form-check-label">add serial number</label>'
      + '        </div>'
      + '      </div>'
      + '      <textarea class="form-control text-uppercase js-item-description" name="description[' + setIndex + ']" rows="4" placeholder="Enter description" required' + setPrimaryId('description', setIndex) + '>' + esc(state.description) + '</textarea>'
      + '    </div>'
      + '  </div>'
      + '  <div class="form-row js-item-serial-row"' + (state.addSerial ? '' : ' style="display:none;"') + '>'
      + '    <div class="form-group col-12">'
      + '      <div class="js-item-serial-table-wrap">' + buildSerialRowsHtml(setIndex, state) + '</div>'
      + '    </div>'
      + '  </div>'
      + '  <div class="form-row">'
      + '    <div class="form-group col-md-3">'
      + '      <label>Unit Value <span class="text-muted">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;'
      + '        <input type="checkbox" class="form-check-input js-item-no-amount" name="item_no_amount[' + setIndex + ']" value="1"' + (state.noAmount ? ' checked' : '') + setPrimaryId('noAmount', setIndex) + '>'
      + '        <label class="form-check-label">no amount value</label>'
      + '      </span></label>'
      + '      <input type="text" class="form-control js-item-unitvalue" name="unitvalue[' + setIndex + ']" value="' + esc(state.unitvalue || '0.00') + '" required pattern="^\\d{1,3}(,\\d{3})*(\\.\\d{2})?$" title="Enter a valid amount (e.g. 1,234.56)"' + setPrimaryId('unitvalue', setIndex) + '>'
      + '      <div class="text-danger item-unitvalue-error" style="display:none;font-size:13px;"></div>'
      + '    </div>'
      + '    <div class="form-group col-md-3">'
      + '      <label>Total Amount</label>'
      + '      <input type="text" class="form-control js-item-total" name="total_amount_preview[' + setIndex + ']" value="0.00" readonly' + setPrimaryId('total', setIndex) + '>'
      + '    </div>'
      + '    <div class="form-group col-md-6">'
      + '      <div class="d-flex align-items-center justify-content-between mb-2">'
      + '        <label class="mb-0">Account Code</label>'
      + '        <div class="form-check form-check-inline mb-0 text-muted js-item-no-account-property-wrap">'
      + '          <input type="checkbox" class="form-check-input js-item-no-account-property" name="item_no_account_property[' + setIndex + ']" value="1"' + (state.noAccountProperty ? ' checked' : '') + setPrimaryId('noAccountProperty', setIndex) + '>'
      + '          <label class="form-check-label">none</label>'
      + '        </div>'
      + '      </div>'
      + '      ' + buildAccountCodeControlHtml(setIndex, state)
      + '    </div>'
      + '  </div>'
      + '  <div class="form-row mb-0">'
      + '    <div class="form-group col-md-6 mb-0">'
      + '      <label>PAR/ICS No.</label>'
      + '      <input type="hidden" class="js-item-par-ics-value" name="par_ics_number_preview_value[' + setIndex + ']" value="">'
      + '      <textarea class="form-control js-item-par-ics" name="par_ics_number_preview[' + setIndex + ']" rows="3" readonly></textarea>'
      + '    </div>'
      + '    <div class="form-group col-md-6 mb-0">'
      + '      <label>Property Number</label>'
      + '      <input type="hidden" class="js-item-property-number-value" name="property_numbers[' + setIndex + ']" value="">'
      + '      <textarea class="form-control js-item-property-number" name="property_number_preview[' + setIndex + ']" rows="3" readonly></textarea>'
      + '    </div>'
      + '  </div>'
      + '  <div class="form-row mb-0">'
      + '    <div class="form-group col-md-12 mb-0">'
      + '      <label>Remarks</label>'
      + '      <textarea class="form-control text-uppercase js-item-remarks" name="remarks[' + setIndex + ']" rows="3" placeholder="Enter remarks"' + setPrimaryId('remarks', setIndex) + '>' + esc(state.remarks) + '</textarea>'
      + '    </div>'
      + '  </div>'
      + '</div>';
  }

  function applyNoBrandModelState($row){
    var $brand = $row.find('.js-item-brand');
    var $cb = $row.find('.js-item-no-brand');
    if(!$brand.length || !$cb.length) return;

    if($cb.is(':checked')){
      if ($brand.data('prevUserValueCaptured') !== true) {
        $brand.data('prevUserValue', String($brand.val() || ''));
        $brand.data('prevUserValueCaptured', true);
      }
      $brand.val('NO BRAND/MODEL').prop('readonly', true).prop('required', false);
    } else {
      var prev = String($brand.data('prevUserValue') || '');
      if (String($brand.val() || '').trim().toUpperCase() === 'NO BRAND/MODEL') {
        $brand.val(prev);
      }
      $brand.prop('readonly', false).prop('required', true);
      $brand.data('prevUserValueCaptured', false);
    }
  }

  function isNewCondition(){
    return String($('#condition').val() || '').toUpperCase() === 'NEW';
  }

  function applyNoAccountPropertyState($row){
    var $cb = $row.find('.js-item-no-account-property');
    var $wrap = $row.find('.js-item-no-account-property-wrap');
    var $account = $row.find('.js-item-account-code');
    var $propertyPreview = $row.find('.js-item-property-number');
    var $propertyValue = $row.find('.js-item-property-number-value');
    if(!$cb.length || !$account.length) return;

    var allowOptional = isNewCondition();
    var skipPropertyNumber = propertyNumberOptionalFund();
    $wrap.toggle(allowOptional);
    $cb.prop('disabled', !allowOptional);
    if(!allowOptional && $cb.is(':checked')){
      $cb.prop('checked', false);
    }

    if(allowOptional && $cb.is(':checked')){
      $account.val('').prop('disabled', true).prop('required', false);
      $propertyPreview.val('').attr('placeholder', 'No property number');
      $propertyValue.val('');
    } else {
      $account.prop('disabled', false).prop('required', true);
      if (skipPropertyNumber) {
        $propertyPreview.val('').attr('placeholder', 'No property number');
        $propertyValue.val('');
      } else {
        $propertyPreview.attr('placeholder', '');
      }
    }
  }

  function applyNoAccountPropertyStateToAll(){
    $('#itemSetRows .item-set-card').each(function(){
      applyNoAccountPropertyState($(this));
    });
  }

  function applySerialVisibilityState($row, animate){
    var $serialRow = $row.find('.js-item-serial-row');
    var $toggle = $row.find('.js-item-add-serial');
    var showSerial = $toggle.is(':checked');
    if(!$serialRow.length || !$toggle.length) { return; }

    $serialRow.stop(true, true);
    if (showSerial) {
      if (animate && !$serialRow.is(':visible')) {
        $serialRow
          .css({ display: 'flex', overflow: 'hidden', opacity: 0 })
          .hide()
          .slideDown({ duration: 280, queue: false, complete: function(){
            $serialRow.css({ display: 'flex', overflow: '', opacity: '' });
          }});
        $serialRow.animate({ opacity: 1 }, { duration: 280, queue: false });
      } else {
        $serialRow.css({ display: 'flex', overflow: '', opacity: '' });
      }
    } else if (animate && $serialRow.is(':visible')) {
      $serialRow.animate({ opacity: 0 }, { duration: 240, queue: false });
      $serialRow.slideUp({ duration: 240, queue: false, complete: function(){
        $serialRow.css({ overflow: '', opacity: '' });
      }});
    } else {
      $serialRow.hide().css({ overflow: '', opacity: '' });
    }

    $serialRow.find('.js-item-serial1, .js-item-serial2').prop('disabled', !showSerial);
  }

  function applyNoAmountValueState($row){
    var $input = $row.find('.js-item-unitvalue');
    var $cb = $row.find('.js-item-no-amount');
    if(!$input.length || !$cb.length) return;
    if($cb.is(':checked')){
      if(!String($input.val() || '').trim()){
        $input.val('0.00');
      }
      $input.prop('readonly', true).prop('required', false);
      $row.find('.item-unitvalue-error').hide().text('');
    } else {
      $input.prop('readonly', false).prop('required', true);
    }
  }

  function updateItemSetTotal($row){
    var qty = parseInt($row.find('.js-item-quantity').val(), 10);
    if(!qty || qty < 1){ qty = 1; }
    var unit = parseMoneyValue($row.find('.js-item-unitvalue').val());
    $row.find('.js-item-total').val(formatMoneyValue(unit * qty));
  }

  function nextPropertyNumber(num){
    var parts = String(num || '').split('-');
    if(parts.length < 4) return num;
    var idx = parts.length - 2;
    var seq = parts[idx];
    var pad = seq.length;
    parts[idx] = String((parseInt(seq, 10) || 0) + 1).padStart(pad, '0');
    return parts.join('-');
  }

  function propertyNumberCopies(first, qty){
    var copies = [];
    var current = String(first || '').trim();
    var count = Math.max(1, parseInt(qty, 10) || 1);
    for (var step = 0; step < count; step++) {
      if (current) { copies.push(current); }
      current = nextPropertyNumber(current);
    }
    return copies;
  }

  function applyPropertyNumberPreview(list){
    var previewList = Array.isArray(list) ? list : [];
    var allNumbers = [];
    previewList.forEach(function(rowNumbers){
      var numbers = Array.isArray(rowNumbers) ? rowNumbers : (rowNumbers ? [rowNumbers] : []);
      allNumbers = allNumbers.concat(numbers);
    });
    $('#par_number_value').val(allNumbers.length ? allNumbers[0] : '');
    $('#par_number').val(allNumbers.join(', '));
    $('#itemSetRows .item-set-card').each(function(index){
      var rowNumbers = previewList[index] || [];
      var numbers = Array.isArray(rowNumbers) ? rowNumbers : (rowNumbers ? [rowNumbers] : []);
      $(this).find('.js-item-property-number').attr('rows', 3).val(numbers.join(', '));
      $(this).find('.js-item-property-number-value').val(numbers.length ? numbers[0] : '');
    });
    if (window.GSO && window.GSO.AddItemBundle && typeof window.GSO.AddItemBundle.refreshBundlePropertyNumbers === 'function') {
      window.GSO.AddItemBundle.refreshBundlePropertyNumbers();
    }
  }

  function renderItemSetRows(options){
    var opts = options || {};
    if (!opts.skipSnapshot) {
      snapshotItemSetInputsIntoCache();
    }
    var count = getSetCount();
    var $rows = $('#itemSetRows');
    var currentCount = $rows.find('.item-set-card').length;

    if (!opts.forceRebuild && currentCount > 0 && count > currentCount) {
      var htmlToAppend = '';
      for (var nextIndex = currentCount + 1; nextIndex <= count; nextIndex++) {
        htmlToAppend += buildItemSetCardHtml(nextIndex);
      }
      $rows.append(htmlToAppend);
      $rows.find('.item-set-card').slice(currentCount).each(function(){
        var $row = $(this);
        applyNoBrandModelState($row);
        applyNoAccountPropertyState($row);
        applySerialVisibilityState($row);
        applyNoAmountValueState($row);
        updateItemSetTotal($row);
      });
      $rows.find('.btnRemoveItemSet').css('visibility', count === 1 ? 'hidden' : 'visible');
      return;
    }

    if (!opts.forceRebuild && currentCount === count) {
      $rows.find('.btnRemoveItemSet').css('visibility', count === 1 ? 'hidden' : 'visible');
      return;
    }

    var html = '';
    for (var i = 1; i <= count; i++) {
      html += buildItemSetCardHtml(i);
    }
    $rows.html(html);
    $rows.find('.item-set-card').each(function(){
      var $row = $(this);
      applyNoBrandModelState($row);
      applyNoAccountPropertyState($row);
      applySerialVisibilityState($row);
      applyNoAmountValueState($row);
      updateItemSetTotal($row);
    });
  }

  function resetDepartmentAndEmployeeFields(){
    $('#deptSearch').val('').prop('disabled', true);
    $('#dept').val('');
    $('#dept option:not(:first)').remove();
    $('#deptDatalist').empty();
    $('#multipleEndUserCheckBox, #endUserNoneCheckBox').prop('checked', false);
    endUserByRow = {};
    $('#endUserRows').hide().empty();
    $('#parEmp').html('<option value="">-SELECT-</option>').prop('disabled', true);
    empOptionsAll = '';
    $('#add_new_employee').hide();
    $('#new_emp, #position').prop('required', false).val('');
    $('#additem-name-validation-msg').hide().text('');
    applyPropertyNumberPreview([]);
  }

  function loadEmployeesForDept(deptCode, cb){
    if(!deptCode){
      $('#parEmp').html('<option value="">SELECT A DEPARTMENT FIRST</option>');
      empOptionsAll = '';
      if(typeof cb==='function') cb();
      return;
    }
    $.ajax({
      url: '../auth/auth.php',
      method: 'POST',
      data: { departmentid: deptCode },
      success: function(html){
        $('#parEmp').html(html);
        empOptionsAll = String(html||'');
        if ($('#multipleEndUserCheckBox').is(':checked')) {
          renderEndUserRows();
        }
        if(typeof cb==='function') cb();
      },
      error: function(){ if(typeof cb==='function') cb(); }
    });
  }

  function populateDeptFromOptions(optionsHtml){
    var $select = $('#dept');
    $select.html(optionsHtml);
    var $datalist = $('#deptDatalist');
    $datalist.empty();
    $select.find('option').each(function(){
      var val = $(this).val();
      if(!val) return;
      $('<option>').attr('value', $(this).text().trim()).appendTo($datalist);
    });
  }

  function enforcePerRowQtyLimit(){
    var q = getSetCount();
    $('#quantity-warning')
      .text('Maximum ' + MAX_ITEM_SETS + ' item sets per submission.')
      .toggle(q >= MAX_ITEM_SETS);
  }

  function getEmployeeOptionsHtml() {
    var html = (empOptionsAll && empOptionsAll.trim()) ? empOptionsAll : String($('#parEmp').html() || '');
    if (!html || !html.trim()) html = '<option value="">-SELECT-</option>';
    return html;
  }

  function isAddNewEmpValue(v){
    return (String(v || '').toLowerCase() === 'add_new_emp');
  }

  function canUseNoEndUser(){
    return String($('#condition').val() || '').trim().toUpperCase() === 'NEW';
  }

  function isNoEndUserSelected(){
    return canUseNoEndUser() && $('#endUserNoneCheckBox').is(':checked');
  }

  function hasPurchaseOrderValue(){
    return String($('#po').val() || '').trim() !== '';
  }

  function isValidNewPurchaseOrderValue(){
    return /^\d{5,8}$/.test(String($('#po').val() || '').trim());
  }

  function isDonationFund(){
    return String($('#fund').val() || '').trim().toUpperCase() === 'DONATION';
  }

  function isPurchaseOrderRequired(){
    return String($('#condition').val() || '').trim().toUpperCase() === 'NEW' && !isDonationFund();
  }

  function syncPurchaseOrderRequirement(){
    var required = isPurchaseOrderRequired();
    var $po = $('#po');
    $po.prop('required', required);
    if (required) {
      $po.attr('inputmode', 'numeric');
      $po.attr('maxlength', '8');
      $po.attr('pattern', '^\\d{5,8}$');
      $po.attr('title', 'Enter 5 to 8 digits.');
    } else {
      $po.removeAttr('inputmode');
      $po.removeAttr('maxlength');
      $po.removeAttr('pattern');
      $po.removeAttr('title');
    }
  }

  function syncAddItemSubmitButton(){
    var $btn = $('#addItemSubmitBtn');
    if (!$btn.length) { return; }
    if ($btn.data('submitting')) { return; }
    syncPurchaseOrderRequirement();
    $btn.prop('disabled', isPurchaseOrderRequired() && !isValidNewPurchaseOrderValue());
  }

  function hideAddNewEmployeeSection(){
    var $section = $('#add_new_employee');
    if ($section.length) { $section.stop(true, true).slideUp(0); }
    $('#new_emp,#position').prop('required', false).val('');
    $('#additem-name-validation-msg').hide().text('');
    syncAddItemSubmitButton();
  }

  function syncNoEndUserState(){
    var allowed = canUseNoEndUser();
    var $none = $('#endUserNoneCheckBox');
    if (!$none.length) { return; }
    $none.prop('disabled', !allowed);
    if (!allowed) { $none.prop('checked', false); }
    if (isNoEndUserSelected()) {
      $('#multipleEndUserCheckBox').prop('checked', false);
      $('#parEmp').val('').prop('required', false).prop('disabled', true).show();
      $('#endUserRows').hide().empty();
      hideAddNewEmployeeSection();
    }
  }

  function syncMultiEndUserRowState($row){
    var $sel = $row.find('select.parEmpRow');
    var $nm  = $row.find('input.parEmpNewName');
    var $ps  = $row.find('input.parEmpNewPos');
    var on = isAddNewEmpValue($sel.val());
    $nm.prop('disabled', !on).prop('required', on);
    $ps.prop('disabled', !on).prop('required', on);
    if(!on){ $nm.val(''); $ps.val(''); }
  }

  function renderEndUserRows() {
    snapshotEndUserInputsIntoCache();
    syncNoEndUserState();
    if (isNoEndUserSelected()) { return; }
    var isMulti = $('#multipleEndUserCheckBox').is(':checked');
    var setCount = getSetCount();

    var $single = $('#parEmp');
    var $singleWrap = $single.closest('.form-group');
    var $addNewSingle = $('#add_new_employee');
    var $wrap = $('#endUserRows');

    if (!isMulti) {
      $wrap.hide().empty();
      $single.prop('disabled', false).prop('required', true).show();
      $singleWrap.removeClass('mb-0');
      return;
    }

    $single.prop('required', false).prop('disabled', true).hide().val('');
    if ($addNewSingle.is(':visible')) {
      $addNewSingle.hide();
      $('#new_emp,#position').prop('required', false).val('');
      $('#additem-name-validation-msg').hide().text('');
    }

    var opts = getEmployeeOptionsHtml();
    var showIndex = setCount > 1;
    var html = '<table class="table table-bordered mb-2">'
      + '<thead class="bg-light" style="position:sticky; top:0; z-index:1">'
      + '<tr>'
      + (showIndex ? '<th style="width:90px">Set</th>' : '')
      + '<th style="min-width:220px">End User</th>'
      + '<th style="min-width:220px">New Employee</th>'
      + '<th style="min-width:200px">Position</th>'
      + '</tr>'
      + '</thead><tbody>';

    for (var i = 1; i <= setCount; i++) {
      html += '<tr>'
        + (showIndex ? '<td>' + getSetLabel(i) + '</td>' : '')
        + '<td><select name="parEmp_multi[' + i + ']" class="form-control parEmpRow" data-row="' + i + '" required>' + opts + '</select></td>'
        + '<td><input type="text" class="form-control text-uppercase parEmpNewName" name="parEmp_multi_new_name[' + i + ']" data-row="' + i + '" placeholder="Enter new employee name" disabled></td>'
        + '<td><input type="text" class="form-control text-uppercase parEmpNewPos" name="parEmp_multi_new_position[' + i + ']" data-row="' + i + '" placeholder="Enter position" disabled></td>'
        + '</tr>';
    }
    html += '</tbody></table>';

    $wrap.html(html).show();

    for (var j = 1; j <= setCount; j++) {
      var k = toRowKey(j);
      if (!k || !endUserByRow[k]) continue;
      $wrap.find('select.parEmpRow[data-row="' + j + '"]').val(endUserByRow[k].emp || '');
      $wrap.find('input.parEmpNewName[data-row="' + j + '"]').val(endUserByRow[k].newName || '');
      $wrap.find('input.parEmpNewPos[data-row="' + j + '"]').val(endUserByRow[k].newPos || '');
    }

    $wrap.find('tbody tr').each(function(){ syncMultiEndUserRowState($(this)); });
  }

  function validateUnitValue() {
    var year = $('#year').val();
    var isValid = true;

    $('#itemSetRows .item-set-card').each(function(){
      var $row = $(this);
      var $error = $row.find('.item-unitvalue-error');
      var unitvalue = parseMoneyValue($row.find('.js-item-unitvalue').val());
      var category = String($row.find('.js-item-category').val() || '').trim().toUpperCase();
      var error = '';

      if($row.find('.js-item-no-amount').is(':checked')) {
        $error.hide().text('');
        updateItemSetTotal($row);
        return;
      }

      if (year === 'RFS' || year === 'FS') {
        if (unitvalue !== 0) {
          error = 'Unit Value must be 0.00 for Found at Station.';
        }
      } else if (category === 'ICS') {
        if (unitvalue >= 50001) {
          error = 'Unit Value for ICS must be less than 50,000.00.';
        }
      } else if (category === 'PAR') {
        if (unitvalue < 50001) {
          error = 'Unit Value for PAR must be 50,000.00 or above.';
        }
      }

      if (error) {
        $error.text(error).show();
        isValid = false;
      } else {
        $error.hide().text('');
      }
    });

    return isValid;
  }

  function validateManualAccountCodes(){
    if (!usesManualAccountCodeInput()) { return true; }
    var invalidSet = '';
    $('#itemSetRows .item-set-card').each(function(){
      var $row = $(this);
      if (isNewCondition() && $row.find('.js-item-no-account-property').is(':checked')) { return; }
      var value = String($row.find('.js-item-account-code').val() || '').trim();
      if (!/^[0-9-]{1,18}$/.test(value)) {
        invalidSet = getSetLabel($row.data('setIndex'));
        return false;
      }
    });
    if (!invalidSet) { return true; }
    Swal.fire({ icon:'warning', title:'Validation error', text:'Account Code must contain numbers and hyphen only, up to 18 characters (' + invalidSet + ').' });
    return false;
  }

  function updateTotalAmount(){
    $('#itemSetRows .item-set-card').each(function(){
      updateItemSetTotal($(this));
    });
  }

  function updateYearOptions() {
    var $year = $('#year');
    var start = 2001;
    var current = new Date().getFullYear();
    var options = "<option value=''>-SELECT-</option>";
    for (var y = current; y >= start; y--) {
      options += "<option value='" + y + "'>" + y + "</option>";
    }
    options += "<option value='RFS'>Found at Station</option>";
    $year.html(options);
  }

  function syncYearAcquiredWithCondition() {
    var condition = String($('#condition').val() || '').toUpperCase();
    var $year = $('#year');
    var currentYear = String(new Date().getFullYear());
    if (!$year.length) { return; }

    if ($year.find('option').length === 0) {
      updateYearOptions();
    }

    if (!condition) {
      $year.val('');
      $year.prop('disabled', true);
      $year.trigger('change');
    } else if (condition === 'NEW') {
      $year.val(currentYear);
      $year.prop('disabled', true);
      $year.trigger('change');
    } else {
      $year.prop('disabled', false);
    }
  }

  function updatePropertyNumber() {
    var year = $('#year').val();
    var dept = $('#dept').val();
    var fund = $('#fund').val();
    var rows = $('#itemSetRows .item-set-card').toArray();

    if (propertyNumberOptionalFund()) {
      applyPropertyNumberPreview([]);
      return;
    }

    if (!(year && dept && fund) || !rows.length) {
      applyPropertyNumberPreview([]);
      return;
    }

    var list = [];
    var exclude = [];
    var reqId = Date.now() + Math.random();
    updatePropertyNumber.lastReqId = reqId;

    function rememberCopies(first, qty){
      exclude = exclude.concat(propertyNumberCopies(first, qty));
    }

    function generateRow(index){
      if (reqId !== updatePropertyNumber.lastReqId) { return; }
      if (index >= rows.length) {
        applyPropertyNumberPreview(list);
        return;
      }

      var $row = $(rows[index]);
      if (isNewCondition() && $row.find('.js-item-no-account-property').is(':checked')) {
        list[index] = [];
        generateRow(index + 1);
        return;
      }
      var category = String($row.find('.js-item-category').val() || '').trim().toUpperCase();
      if (!category) {
        list[index] = [];
        generateRow(index + 1);
        return;
      }
      var accountCode = String($row.find('.js-item-account-code').val() || '').trim();
      if (!accountCode) {
        list[index] = '';
        generateRow(index + 1);
        return;
      }

      $.ajax({
        url: '../auth/auth.php',
        method: 'POST',
        data: {
          generate_property_number: 1,
          category: category,
          year: year,
          account_code: accountCode,
          dept: dept,
          fund: fund,
          exclude: exclude
        },
        dataType: 'json',
        success: function(res) {
          if (reqId !== updatePropertyNumber.lastReqId) { return; }
          var first = (res && res.success) ? String(res.pr_number || '').trim() : '';
          if (first) {
            var itemQty = Math.max(1, parseInt($row.find('.js-item-quantity').val(), 10) || 1);
            list[index] = propertyNumberCopies(first, itemQty);
            rememberCopies(first, itemQty);
          } else {
            list[index] = [];
          }
          generateRow(index + 1);
        },
        error: function(){
          if (reqId !== updatePropertyNumber.lastReqId) { return; }
          list[index] = '';
          generateRow(index + 1);
        }
      });
    }

    generateRow(0);
  }

  function updateParIcsNumbers() {
    var rows = $('#itemSetRows .item-set-card').toArray();
    if (!rows.length) {
      applyParIcsPreview([]);
      return;
    }

    var reqId = Date.now() + Math.random();
    updateParIcsNumbers.lastReqId = reqId;
    var list = [];
    var codeByCategory = {};

    function generateRow(index){
      if (reqId !== updateParIcsNumbers.lastReqId) { return; }
      if (index >= rows.length) {
        applyParIcsPreview(list);
        return;
      }

      var $row = $(rows[index]);
      var category = String($row.find('.js-item-category').val() || '').trim().toUpperCase();
      if (!category) {
        list[index] = '';
        generateRow(index + 1);
        return;
      }

      if (codeByCategory[category]) {
        list[index] = codeByCategory[category];
        generateRow(index + 1);
        return;
      }

      $.ajax({
        url: '../auth/auth.php',
        method: 'POST',
        dataType: 'json',
        data: { generate_par_ics_code: 1, category: category, condition: String($('#condition').val() || '').trim().toUpperCase() },
        success: function(res){
          if (reqId !== updateParIcsNumbers.lastReqId) { return; }
          var code = (res && res.status === 200 && res.code) ? String(res.code).trim().toUpperCase() : '';
          if (code) {
            codeByCategory[category] = code;
            list[index] = code;
          } else {
            list[index] = '';
          }
          generateRow(index + 1);
        },
        error: function(){
          if (reqId !== updateParIcsNumbers.lastReqId) { return; }
          list[index] = '';
          generateRow(index + 1);
        }
      });
    }

    generateRow(0);
  }

  function scheduleUpdatePropertyNumber(){
    clearTimeout(debPropNum);
    debPropNum = setTimeout(function(){ updatePropertyNumber(); }, 300);
  }

  function scheduleUpdateParIcsNumber(){
    clearTimeout(debParIcsNum);
    debParIcsNum = setTimeout(function(){ updateParIcsNumbers(); }, 300);
  }

  function findDeptCodeByExactName(name) {
    var $deptSelect = $('#dept');
    var target = String(name || '').trim().toLowerCase();
    if(!$deptSelect.length || !target) return '';
    var code = '';
    $deptSelect.find('option').each(function(){
      if ($(this).text().trim().toLowerCase() === target) {
        code = $(this).val();
        return false;
      }
    });
    return code;
  }

  function validateDeptExactOnSubmit(){
    var $deptInput = $('#deptSearch');
    var $deptSelect = $('#dept');
    if(!$deptInput.length || !$deptSelect.length){ return true; }
    var code = findDeptCodeByExactName($deptInput.val().trim());
    if (!code) {
      $deptInput.addClass('is-invalid');
      if (!$deptInput.next('.invalid-feedback').length) {
        $('<div class="invalid-feedback">Please select a valid department from the list.</div>').insertAfter($deptInput);
      }
      try { $deptInput.trigger('focus'); } catch(e) {}
      return false;
    }
    $deptInput.removeClass('is-invalid');
    $deptInput.next('.invalid-feedback').remove();
    if($deptSelect.val() !== code){
      $deptSelect.val(code).trigger('change');
    }
    return true;
  }

  function resetAddNewEmpFieldsAddItem() {
    $('#new_emp').val('');
    $('#position').val('');
    $('#additem-name-validation-msg').hide().text('');
    syncAddItemSubmitButton();
  }

  function toggleAddNewEmpSectionAddItem() {
    if (isNoEndUserSelected()) {
      hideAddNewEmployeeSection();
      return;
    }

    if ($('#multipleEndUserCheckBox').is(':checked')) {
      var $sec = $('#add_new_employee');
      if ($sec.length) { $sec.stop(true, true).slideUp(0); }
      $('#new_emp').prop('required', false);
      $('#position').prop('required', false);
      resetAddNewEmpFieldsAddItem();
      return;
    }

    var isAddNew = (String($('#parEmp').val() || '').toLowerCase() === 'add_new_emp');
    var $section = $('#add_new_employee');
    if (!$section.length) { return; }

    if (isAddNew) {
      $section.stop(true, true).slideDown(200);
      $('#new_emp').prop('required', true);
      $('#position').prop('required', true);
    } else {
      $section.stop(true, true).slideUp(200);
      $('#new_emp').prop('required', false);
      $('#position').prop('required', false);
      resetAddNewEmpFieldsAddItem();
    }
  }

  function updateParEmpStateAddItem() {
    if ($('#multipleEndUserCheckBox').is(':checked')) { return; }

    var hasDept = !!String($('#dept').val() || '').trim();
    var $emp = $('#parEmp');
    if (!$emp.length) { return; }

    if (isNoEndUserSelected()) {
      $emp.val('').prop('disabled', true).prop('required', false).show();
      hideAddNewEmployeeSection();
      return;
    }

    if (!hasDept) {
      $emp.val('').prop('disabled', true).html('<option value="">SELECT A DEPARTMENT FIRST</option>');
      var $section = $('#add_new_employee');
      if ($section.length && $section.is(':visible')) {
        $section.stop(true, true).slideUp(200);
      }
      $('#new_emp').prop('required', false).val('');
      $('#position').prop('required', false).val('');
      $('#additem-name-validation-msg').hide().text('');
      syncAddItemSubmitButton();
      return;
    }

    $emp.prop('disabled', false);
    if ($emp.find('option').length === 0) {
      $emp.html('<option value="">-SELECT-</option>');
    }
  }

  function init(){
    if(inited) return;
    if(!hasUI()) return;
    inited = true;

    if ($('#quantity').length && $('#quantity').next('#quantity-warning').length === 0) {
      $('#quantity').after('<div class="text-warning" id="quantity-warning" style="display:none;font-size:13px; margin-top:4px;"></div>');
    }

    $(document)
      .off('click.gsoAddItemPageRemoveSet', '.btnRemoveItemSet')
      .on('click.gsoAddItemPageRemoveSet', '.btnRemoveItemSet', function(){
        var currentCount = getSetCount();
        if (currentCount <= 1) { return; }
        snapshotItemSetInputsIntoCache();
        snapshotEndUserInputsIntoCache();
        var setIndex = parseInt($(this).attr('data-set-index'), 10) || currentCount;
        itemSetByRow = reindexCache(itemSetByRow, setIndex, currentCount);
        endUserByRow = reindexCache(endUserByRow, setIndex, currentCount);
        $('#quantity').val(currentCount - 1);
        renderItemSetRows({ skipSnapshot: true, forceRebuild: true });
        renderEndUserRows();
        scheduleUpdateParIcsNumber();
        scheduleUpdatePropertyNumber();
      });

    $(document)
      .off('change.gsoAddItemPageNoBrand', '.js-item-no-brand')
      .on('change.gsoAddItemPageNoBrand', '.js-item-no-brand', function(){
        applyNoBrandModelState($(this).closest('.item-set-card'));
      });

    $(document)
      .off('change.gsoAddItemPageNoAccountProperty', '.js-item-no-account-property')
      .on('change.gsoAddItemPageNoAccountProperty', '.js-item-no-account-property', function(){
        applyNoAccountPropertyState($(this).closest('.item-set-card'));
        scheduleUpdatePropertyNumber();
      });

    $(document)
      .off('change.gsoAddItemPageSerialToggle', '.js-item-add-serial')
      .on('change.gsoAddItemPageSerialToggle', '.js-item-add-serial', function(){
        applySerialVisibilityState($(this).closest('.item-set-card'), true);
      });

    $(document)
      .off('input.gsoAddItemPageBrand', '.js-item-brand')
      .on('input.gsoAddItemPageBrand', '.js-item-brand', function(){
        var $row = $(this).closest('.item-set-card');
        if(!$row.find('.js-item-no-brand').is(':checked')){
          $(this).data('prevUserValue', String($(this).val() || ''));
        }
      });

    $('#addItemModal')
      .off('hidden.bs.modal.gsoAddItemPageBrand')
      .on('hidden.bs.modal.gsoAddItemPageBrand', function(){
        itemSetByRow = {};
        endUserByRow = {};
        $('#quantity').val(1);
        renderItemSetRows({ skipSnapshot: true, forceRebuild: true });
        applyPropertyNumberPreview([]);
      })
      .off('shown.bs.modal.gsoAddItemPageBrand')
      .on('shown.bs.modal.gsoAddItemPageBrand', function(){
        renderItemSetRows();
      });

    $(document)
      .off('change.gsoAddItemPageFund', '#fund')
      .on('change.gsoAddItemPageFund', '#fund', function(){
        var fund = String($(this).val() || '').trim();
        snapshotItemSetInputsIntoCache();
        renderItemSetRows({ skipSnapshot: true, forceRebuild: true });
        scheduleUpdateParIcsNumber();
        resetDepartmentAndEmployeeFields();
        syncPurchaseOrderRequirement();
        syncAddItemSubmitButton();
        if(!fund){ return; }

        if (reqFundDepts && reqFundDepts.readyState !== 4) {
          try { reqFundDepts.abort(); } catch(e){}
        }
        clearTimeout(debFundDepts);
        debFundDepts = setTimeout(function(){
          reqFundDepts = $.ajax({
            url: '../auth/auth.php',
            method: 'POST',
            data: { fund_for_departments: fund },
            success: function(html){
              populateDeptFromOptions(html);
              $('#deptSearch').prop('disabled', false).focus();
            },
            error: function(){ console.error('Failed to load departments for fund ' + fund); }
          });
        }, 350);
      });

    $(document)
      .off('input.gsoAddItemPageQty blur.gsoAddItemPageQty', '#quantity')
      .on('input.gsoAddItemPageQty blur.gsoAddItemPageQty', '#quantity', function(){
        $('#quantity').val(normalizeSetCount($(this).val()));
        enforcePerRowQtyLimit();
        renderItemSetRows();
        renderEndUserRows();
        updateTotalAmount();
        scheduleUpdateParIcsNumber();
      });

    $(document)
      .off('input.gsoAddItemPageItemQty change.gsoAddItemPageItemQty blur.gsoAddItemPageItemQty', '.js-item-quantity')
      .on('input.gsoAddItemPageItemQty change.gsoAddItemPageItemQty blur.gsoAddItemPageItemQty', '.js-item-quantity', function(){
        var qty = parseInt($(this).val(), 10) || 1;
        if (qty < 1) { qty = 1; }
        $(this).val(qty);
        var $row = $(this).closest('.item-set-card');
        updateItemSetTotal($row);
        refreshSerialRowsForItemSet($row);
        scheduleUpdateParIcsNumber();
        scheduleUpdatePropertyNumber();
      });

    $(document)
      .off('change.gsoAddItemPageMulti', '#multipleEndUserCheckBox')
      .on('change.gsoAddItemPageMulti', '#multipleEndUserCheckBox', function(){
        if ($(this).is(':checked')) { $('#endUserNoneCheckBox').prop('checked', false); }
        renderEndUserRows();
      });
    $(document)
      .off('change.gsoAddItemPageNoEndUser', '#endUserNoneCheckBox')
      .on('change.gsoAddItemPageNoEndUser', '#endUserNoneCheckBox', function(){
        if ($(this).is(':checked')) { $('#multipleEndUserCheckBox').prop('checked', false); }
        syncNoEndUserState();
        renderEndUserRows();
        updateParEmpStateAddItem();
      });
    $(document)
      .off('input.gsoAddItemPageMulti change.gsoAddItemPageMulti', '#quantity')
      .on('input.gsoAddItemPageMulti change.gsoAddItemPageMulti', '#quantity', renderEndUserRows);
    $(document)
      .off('change.gsoAddItemPageMultiRow', '#endUserRows select.parEmpRow')
      .on('change.gsoAddItemPageMultiRow', '#endUserRows select.parEmpRow', function(){
        snapshotEndUserInputsIntoCache();
        syncMultiEndUserRowState($(this).closest('tr'));
      });

    var debParEmp;
    $(document)
      .off('change.gsoAddItemPageParEmp', '#parEmp')
      .on('change.gsoAddItemPageParEmp', '#parEmp', function(){
        clearTimeout(debParEmp);
        debParEmp = setTimeout(function(){ toggleAddNewEmpSectionAddItem(); }, 200);
      });
    $(document)
      .off('change.gsoAddItemPageDeptEmp', '#dept')
      .on('change.gsoAddItemPageDeptEmp', '#dept', function(){
        updateParEmpStateAddItem();
        toggleAddNewEmpSectionAddItem();
      });

    var addItemNameDebounce;
    $(document)
      .off('input.gsoAddItemPageNewEmp', '#new_emp')
      .on('input.gsoAddItemPageNewEmp', '#new_emp', function(){
        var name = String($(this).val() || '').trim();
        var $msg = $('#additem-name-validation-msg');
        var $btn = $('#addItemSubmitBtn');
        clearTimeout(addItemNameDebounce);

        if(!name){
          $msg.hide().text('');
          $btn.prop('disabled', false);
          return;
        }

        $msg.show().text('Validating...').css('color','red');
        $btn.prop('disabled', true);
        addItemNameDebounce = setTimeout(function(){
          $.ajax({
            url: '../auth/auth.php',
            type: 'POST',
            data: { validate_employee_name: 1, emp_name: name },
            dataType: 'json',
            success: function(res){
              if(res && res.exists){
                $msg.text('Employee name already exists!').css('color','red');
                $btn.prop('disabled', true);
              } else {
                $msg.text('Employee name is available.').css('color','green');
                $btn.prop('disabled', false);
              }
            },
            error: function(){
              $msg.text('Validation error.').css('color','red');
              $btn.prop('disabled', true);
            }
          });
        }, 600);
      });

    $(document)
      .off('change.gsoAddItemPageParEmpReset', '#parEmp')
      .on('change.gsoAddItemPageParEmpReset', '#parEmp', function(){
        if(String($(this).val()||'').toLowerCase() !== 'add_new_emp'){
          $('#additem-name-validation-msg').hide().text('');
          syncAddItemSubmitButton();
        }
      });

    $('#addItemModal')
      .off('hidden.bs.modal.gsoAddItemPageEmp')
      .on('hidden.bs.modal.gsoAddItemPageEmp', function(){
        try { $('#parEmp').val(''); } catch(e) {}
        updateParEmpStateAddItem();
        toggleAddNewEmpSectionAddItem();
      })
      .off('shown.bs.modal.gsoAddItemPageEmp')
      .on('shown.bs.modal.gsoAddItemPageEmp', function(){
        updateParEmpStateAddItem();
        toggleAddNewEmpSectionAddItem();
      });

    $(document)
      .off('input.gsoAddItemPageCache change.gsoAddItemPageCache', '#itemSetRows input, #itemSetRows textarea, #itemSetRows select')
      .on('input.gsoAddItemPageCache change.gsoAddItemPageCache', '#itemSetRows input, #itemSetRows textarea, #itemSetRows select', snapshotItemSetInputsIntoCache);
    $(document)
      .off('input.gsoAddItemPageCache2 change.gsoAddItemPageCache2', '#endUserRows input.parEmpNewName, #endUserRows input.parEmpNewPos')
      .on('input.gsoAddItemPageCache2 change.gsoAddItemPageCache2', '#endUserRows input.parEmpNewName, #endUserRows input.parEmpNewPos', snapshotEndUserInputsIntoCache);

    $(document)
      .off('change.gsoAddItemPageNoAmount', '.js-item-no-amount')
      .on('change.gsoAddItemPageNoAmount', '.js-item-no-amount', function(){
        var $row = $(this).closest('.item-set-card');
        applyNoAmountValueState($row);
        updateItemSetTotal($row);
        validateUnitValue();
      });

    $(document)
      .off('input.gsoAddItemPageUnit blur.gsoAddItemPageUnit', '.js-item-unitvalue')
      .on('input.gsoAddItemPageUnit blur.gsoAddItemPageUnit', '.js-item-unitvalue', function(){
        var $row = $(this).closest('.item-set-card');
        updateItemSetTotal($row);
        validateUnitValue();
      });

    $(document)
      .off('input.gsoAddItemPageTotal change.gsoAddItemPageTotal blur.gsoAddItemPageTotal', '.js-item-unitvalue, #quantity')
      .on('input.gsoAddItemPageTotal change.gsoAddItemPageTotal blur.gsoAddItemPageTotal', '.js-item-unitvalue, #quantity', updateTotalAmount);
    $(document)
      .off('change.gsoAddItemPageUnit', '.js-item-category, #year')
      .on('change.gsoAddItemPageUnit', '.js-item-category, #year', validateUnitValue);

    $(document)
      .off('input.gsoAddItemPageUnitFmt', '.js-item-unitvalue')
      .on('input.gsoAddItemPageUnitFmt', '.js-item-unitvalue', function() {
        var val = String($(this).val() || '').replace(/[^\d.]/g, '');
        var parts = val.split('.');
        parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ',');
        $(this).val(parts.length > 1 ? parts[0] + '.' + parts[1].slice(0,2) : parts[0]);
      });
    $(document)
      .off('blur.gsoAddItemPageUnitBlur', '.js-item-unitvalue')
      .on('blur.gsoAddItemPageUnitBlur', '.js-item-unitvalue', function(){
        if ($(this).val().trim() === '') {
          $(this).val('0.00');
        }
        updateItemSetTotal($(this).closest('.item-set-card'));
      });

    $(document)
      .off('change.gsoAddItemPageProp input.gsoAddItemPageProp', '.js-item-category, .js-item-account-code, #dept, #year, #quantity')
      .on('change.gsoAddItemPageProp input.gsoAddItemPageProp', '.js-item-category, .js-item-account-code, #dept, #year, #quantity', scheduleUpdatePropertyNumber);

    $(document)
      .off('change.gsoAddItemPageParIcs input.gsoAddItemPageParIcs', '.js-item-category, #condition, #quantity')
      .on('change.gsoAddItemPageParIcs input.gsoAddItemPageParIcs', '.js-item-category, #condition, #quantity', scheduleUpdateParIcsNumber);

    $(document)
      .off('input.gsoAddItemPageTrustAcct', 'input.js-item-account-code')
      .on('input.gsoAddItemPageTrustAcct', 'input.js-item-account-code', function(){
        if (!usesManualAccountCodeInput()) { return; }
        var clean = sanitizeManualAccountCode($(this).val());
        if ($(this).val() !== clean) { $(this).val(clean); }
      });

    $(document)
      .off('change.gsoAddItemPageCond', '#condition')
      .on('change.gsoAddItemPageCond', '#condition', function(){
        syncYearAcquiredWithCondition();
        syncPurchaseOrderRequirement();
        syncNoEndUserState();
        renderEndUserRows();
        applyNoAccountPropertyStateToAll();
        scheduleUpdatePropertyNumber();
        syncAddItemSubmitButton();
      });

    $('#addItemModal')
      .off('shown.bs.modal.gsoAddItemPageMeta')
      .on('shown.bs.modal.gsoAddItemPageMeta', function(){
        if (window.GSO && window.GSO.AddItemMeta && typeof window.GSO.AddItemMeta.fillYearOptions === 'function') {
          window.GSO.AddItemMeta.fillYearOptions();
        }
        renderItemSetRows();
        enforcePerRowQtyLimit();
        syncYearAcquiredWithCondition();
        syncNoEndUserState();
        applyNoAccountPropertyStateToAll();
        updateParEmpStateAddItem();
        toggleAddNewEmpSectionAddItem();
        validateUnitValue();
        updateParIcsNumbers();
        updatePropertyNumber();
        var dept = String($('#dept').val() || '').trim();
        if(dept){ loadEmployeesForDept(dept); }
        updateTotalAmount();
        syncAddItemSubmitButton();
      });

    $(document)
      .off('input.gsoAddItemPagePoDigits', '#po')
      .on('input.gsoAddItemPagePoDigits', '#po', function(){
        if (!isPurchaseOrderRequired()) { return; }
        var clean = String($(this).val() || '').replace(/\D/g, '').slice(0, 8);
        if ($(this).val() !== clean) {
          $(this).val(clean);
        }
      });

    $(document)
      .off('input.gsoAddItemPagePo change.gsoAddItemPagePo', '#po')
      .on('input.gsoAddItemPagePo change.gsoAddItemPagePo', '#po', syncAddItemSubmitButton);

    (function(){
      var $deptSelect = $('#dept');
      var $deptInput = $('#deptSearch');
      var $deptDatalist = $('#deptDatalist');
      var $modal = $('#addItemModal');

      if (!$deptSelect.length || !$deptInput.length) return;

      function populateDatalist() {
        $deptDatalist.empty();
        $deptSelect.find('option').each(function() {
          var name = $(this).text().trim();
          var val = $(this).val();
          if (!val) return;
          $('<option>').attr('value', name).appendTo($deptDatalist);
        });
      }

      $deptSelect.off('change.gsoAddItemPageDept').on('change.gsoAddItemPageDept', function(){
        var code = $(this).val();
        var name = code ? $(this).find('option:selected').text().trim() : '';
        $deptInput.val(name);
      });

      var wasSetFromSelect = false;
      var clearedForRetype = false;

      $deptInput.off('change.gsoAddItemPageDept').on('change.gsoAddItemPageDept', function(){
        var code = findDeptCodeByExactName($(this).val());
        if (code) {
          $deptSelect.val(code).trigger('change');
          wasSetFromSelect = true;
          clearedForRetype = false;
        } else {
          $deptSelect.val('').trigger('change');
        }
      });

      function markClearedIfNeeded(e){
        if (wasSetFromSelect && !clearedForRetype) {
          var key = e && e.key;
          if (e && (e.ctrlKey || e.metaKey || e.altKey)) return;
          if (key && ['Tab','Shift','ArrowLeft','ArrowRight','ArrowUp','ArrowDown','Home','End'].includes(key)) return;
          $deptInput.val('');
          $deptSelect.val('').trigger('change');
          clearedForRetype = true;
        }
      }

      $deptInput.off('beforeinput.gsoAddItemPageDept').on('beforeinput.gsoAddItemPageDept', markClearedIfNeeded);
      $deptInput.off('keydown.gsoAddItemPageDept').on('keydown.gsoAddItemPageDept', function(e){ if (e.key !== 'Tab') markClearedIfNeeded(e); });
      $deptInput.off('paste.gsoAddItemPageDept compositionstart.gsoAddItemPageDept').on('paste.gsoAddItemPageDept compositionstart.gsoAddItemPageDept', function(e){ markClearedIfNeeded(e); });

      $deptInput.off('input.gsoAddItemPageDept').on('input.gsoAddItemPageDept', function(){
        if (!$(this).val()) {
          $deptSelect.val('').trigger('change');
        }
      });

      $deptInput.off('mousedown.gsoAddItemPageDept').on('mousedown.gsoAddItemPageDept', function(e){
        var rect = this.getBoundingClientRect();
        var fromRight = rect.right - e.clientX;
        if (fromRight <= 28) {
          setTimeout(function(){
            $deptInput.val('');
            $deptSelect.val('').trigger('change');
          }, 0);
        }
      });

      $modal.off('shown.bs.modal.gsoAddItemPageDept').on('shown.bs.modal.gsoAddItemPageDept', function(){
        populateDatalist();
        var code = $deptSelect.val();
        $deptInput.val(code ? $deptSelect.find('option:selected').text().trim() : '');
        wasSetFromSelect = !!code;
        clearedForRetype = false;
        setTimeout(function(){ $deptInput.trigger('focus'); }, 100);
      });
      $modal.off('hidden.bs.modal.gsoAddItemPageDept').on('hidden.bs.modal.gsoAddItemPageDept', function(){
        $deptInput.val('');
        $deptSelect.val('');
        wasSetFromSelect = false;
        clearedForRetype = false;
      });

      $deptInput.off('input.gsoAddItemPageDeptInvalid change.gsoAddItemPageDeptInvalid').on('input.gsoAddItemPageDeptInvalid change.gsoAddItemPageDeptInvalid', function(){
        $(this).removeClass('is-invalid');
        $(this).next('.invalid-feedback').remove();
      });

      populateDatalist();
    })();

  }

  $(document)
    .off('submit.gsoAddItemPagePre', '#addItem')
    .on('submit.gsoAddItemPagePre', '#addItem', function(e){
      if(!hasUI()) { return; }
      init();

      if(!validateDeptExactOnSubmit()){
        e.preventDefault();
        e.stopImmediatePropagation();
        return false;
      }

      syncNoEndUserState();

      var condition = String($('#condition').val() || '').toUpperCase();
      if (condition === 'NEW') {
        var currentYear = String(new Date().getFullYear());
        $('#year').val(currentYear).prop('disabled', false);
        applyNoAccountPropertyStateToAll();
        setTimeout(function(){
          try { $('#year').prop('disabled', true); } catch(_) {}
        }, 0);
      } else {
        applyNoAccountPropertyStateToAll();
        $('#year').prop('disabled', false);
      }

      if(!validateUnitValue()){
        e.preventDefault();
        e.stopImmediatePropagation();
        try {
          $('#itemSetRows .item-unitvalue-error:visible').first().closest('.item-set-card').find('.js-item-unitvalue').trigger('focus');
        } catch(_) {}
        return false;
      }

      if(!validateManualAccountCodes()){
        e.preventDefault();
        e.stopImmediatePropagation();
        return false;
      }
    });

  $(function(){ init(); });

  return {
    init: init,
    validateUnitValue: validateUnitValue,
    updatePropertyNumber: updatePropertyNumber,
    syncYearAcquiredWithCondition: syncYearAcquiredWithCondition,
    syncAddItemSubmitButton: syncAddItemSubmitButton,
    getSetLabel: getSetLabel
  };
})();

//par general fund
$(document).on('submit','#addItem',function(e){//to save p.a.r general fund information
  e.preventDefault();

  var $form = $(this);
  var formCondition = String($('#condition').val() || '').toUpperCase();
  var printPreference = String($form.data('printPreference') || '').toLowerCase();
  var hasPrintableCategory = false;
  var selectedFund = String($('#fund').val() || '').trim().toUpperCase();

  $('#itemSetRows .js-item-category').each(function(){
    var value = String($(this).val() || '').trim().toUpperCase();
    if (value === 'PAR' || value === 'ICS') {
      hasPrintableCategory = true;
      return false;
    }
  });

  var canPrintExisting = formCondition === 'EXISTING' && hasPrintableCategory && selectedFund !== 'TRUST FUND';
  var canPromptPrintChoice = (formCondition === 'NEW' && hasPrintableCategory) || canPrintExisting;

  if (canPromptPrintChoice && printPreference !== 'now' && printPreference !== 'later') {
    if (window.Swal && Swal.fire) {
      if (formCondition === 'EXISTING') {
        Swal.fire({
          icon: 'question',
          title: 'Save this existing item?',
          text: 'Choose whether to print the PAR/ICS right after saving or save only.',
          showCancelButton: true,
          showDenyButton: true,
          confirmButtonText: 'Print and Save',
          denyButtonText: 'Save Only',
          cancelButtonText: 'Back',
          reverseButtons: true
        }).then(function(result){
          if (result && result.isConfirmed) {
            $form.data('printPreference', 'now');
            $form.trigger('submit');
          } else if (result && result.isDenied) {
            $form.data('printPreference', 'later');
            $form.trigger('submit');
          } else {
            $form.removeData('printPreference');
          }
        });
      } else {
        Swal.fire({
          icon: 'question',
          title: 'What do you want to do with this item?',
          text: 'Choose whether to print the PAR/ICS right after saving or print it later.',
          showCancelButton: true,
          showDenyButton: true,
          confirmButtonText: 'Print and Save',
          denyButtonText: 'Save and Print Later',
          cancelButtonText: 'Cancel',
          reverseButtons: true
        }).then(function(result){
          if (result && result.isConfirmed) {
            $form.data('printPreference', 'now');
            $form.trigger('submit');
          } else if (result && result.isDenied) {
            $form.data('printPreference', 'later');
            $form.trigger('submit');
          } else {
            $form.removeData('printPreference');
          }
        });
      }
      return;
    }

    if (window.confirm('Select OK to print and save, or Cancel to go back.')) {
      $form.data('printPreference', 'now');
      $form.trigger('submit');
    } else {
      $form.removeData('printPreference');
    }
    return;
  }

  var shouldPrintAfterSave = (canPromptPrintChoice && printPreference === 'now');
  var $btn = $('#addItemSubmitBtn');
  if($btn.data('submitting')){ return; }
  $btn.data('submitting', true);
  $btn.prop('disabled', true).html('<i class="fas fa-circle-notch fa-spin"></i>&nbsp;Saving...');

  var printWins = { par: null, ics: null };
  if(shouldPrintAfterSave){
    var hasPar = false;
    var hasIcs = false;
    $('#itemSetRows .js-item-category').each(function(){
      var value = String($(this).val() || '').trim().toUpperCase();
      if (value === 'PAR') { hasPar = true; }
      if (value === 'ICS') { hasIcs = true; }
    });
    if (hasPar) {
      try { printWins.par = window.open('', '_blank'); } catch(e) { printWins.par = null; }
    }
    if (hasIcs) {
      try { printWins.ics = window.open('', '_blank'); } catch(e) { printWins.ics = null; }
    }
  }

  var fd = new FormData(this);
  fd.append("save_item",true);
  // ensure token present
  var tokenField = document.getElementById('add_item_submission_token');
  if(tokenField){ fd.append('submission_token', tokenField.value); }

  // Multi end-user: explicitly append dynamic row values to FormData.
  // This guards against cases where the dynamic table is not inside the form DOM
  // (or is re-rendered) at the moment FormData is constructed.
  var isMultiEndUser = $('#multipleEndUserCheckBox').is(':checked');
  var isNoEndUser = formCondition === 'NEW' && $('#endUserNoneCheckBox').is(':checked');
  if(isNoEndUser){
    fd.set('parEmp', '');
    fd.set('endUserNoneCheckBox', '1');
  }
  var getSetLabel = (window.GSO && window.GSO.AddItemPage && typeof window.GSO.AddItemPage.getSetLabel === 'function')
    ? window.GSO.AddItemPage.getSetLabel
    : function(idx){ return 'Set ' + idx; };
  if(isMultiEndUser && !isNoEndUser){
    var missingSets = [];
    var missingNewEmpSets = [];
    $('#endUserRows select.parEmpRow').each(function(){
      var idx = $(this).data('row');
      if(idx === undefined || idx === null || idx === ''){ return; }
      var val = ($(this).val() || '').toString().trim();
      if(!val){ missingSets.push(getSetLabel(idx)); }
      fd.append('parEmp_multi['+idx+']', val);

      // Per-row add new employee + position
      if((val || '').toString().toLowerCase() === 'add_new_emp'){
        var nm = ($('#endUserRows input.parEmpNewName[data-row="'+idx+'"]').val() || '').toString().trim();
        var ps = ($('#endUserRows input.parEmpNewPos[data-row="'+idx+'"]').val() || '').toString().trim();
        if(!nm || !ps){ missingNewEmpSets.push(getSetLabel(idx)); }
        fd.append('parEmp_multi_new_name['+idx+']', nm);
        fd.append('parEmp_multi_new_position['+idx+']', ps);
      }
    });
    if(missingSets.length){
      Swal.fire({ icon:'warning', title:'Validation error', text:'End user is required for set(s): ' + missingSets.join(', ') + '.' });
      // Restore button state and allow user to fix inputs without reload
      $form.removeData('printPreference');
      $btn.data('submitting', false);
      $btn.html('<i class="fa-solid fa-pen-to-square"></i>&nbsp;<span class="btn-text">Save</span>');
      if (window.GSO && window.GSO.AddItemPage && typeof window.GSO.AddItemPage.syncAddItemSubmitButton === 'function') {
        window.GSO.AddItemPage.syncAddItemSubmitButton();
      }
      return;
    }
    if(missingNewEmpSets.length){
      Swal.fire({ icon:'warning', title:'Validation error', text:'New employee name and position are required for set(s): ' + missingNewEmpSets.join(', ') + '.' });
      $form.removeData('printPreference');
      $btn.data('submitting', false);
      $btn.html('<i class="fa-solid fa-pen-to-square"></i>&nbsp;<span class="btn-text">Save</span>');
      if (window.GSO && window.GSO.AddItemPage && typeof window.GSO.AddItemPage.syncAddItemSubmitButton === 'function') {
        window.GSO.AddItemPage.syncAddItemSubmitButton();
      }
      return;
    }
  }

  // Bundle equipment: explicitly append dynamic row values to FormData.
  // This guarantees the backend receives bundle_* arrays even if the bundle UI
  // container is rendered outside the <form> subtree by the browser.
  var bundleRows = $('#bundleRows .bundle-row');
  var isPropertyNumberOptionalFund = selectedFund === 'TRUST FUND' || selectedFund === 'DONATION';
  if(bundleRows.length){
    fd.delete('bundle_parent_index[]');
    fd.delete('bundle_category[]');
    fd.delete('bundle_unit[]');
    fd.delete('bundle_asset_class[]');
    fd.delete('bundle_brand_model[]');
    fd.delete('bundle_description[]');
    fd.delete('bundle_serial1[]');
    fd.delete('bundle_serial2[]');
    fd.delete('bundle_property_number[]');

    var bundleMissing = [];
    bundleRows.each(function(groupIndex){
      var $group = $(this);
      var parentIdx = String($group.find('.js-bundle-parent-index').val() || '').trim();
      var cat = String($group.find('.js-bundle-category').val() || '').trim();
      var unit = String($group.find('.js-bundle-unit').val() || '').trim();
      var asset = String($group.find('.js-bundle-asset').val() || '').trim();
      var model = String($group.find('.js-bundle-brand-model').val() || '').trim();
      var desc = String($group.find('.js-bundle-description').val() || '').trim();
      var groupRows = $group.find('.bundle-copy-row');
      var groupLabel = groupIndex + 1;
      var hasAny = (parentIdx || cat || unit || asset || model || desc || groupRows.length);

      if(!hasAny){
        return;
      }

      if(!parentIdx || !cat || !unit || !asset || !groupRows.length){
        bundleMissing.push('Bundle ' + groupLabel);
        return;
      }

      var rowHasMissing = false;
      groupRows.each(function(){
        var $copy = $(this);
        var s1 = String($copy.find('.js-bundle-serial1').val() || '').trim();
        var s2 = String($copy.find('.js-bundle-serial2').val() || '').trim();
        var par = String($copy.find('.js-bundle-property-number').val() || '').trim();

        if(!isPropertyNumberOptionalFund && !par){
          rowHasMissing = true;
          return false;
        }

        fd.append('bundle_parent_index[]', parentIdx);
        fd.append('bundle_category[]', cat);
        fd.append('bundle_unit[]', unit);
        fd.append('bundle_asset_class[]', asset);
        fd.append('bundle_brand_model[]', model);
        fd.append('bundle_description[]', desc);
        fd.append('bundle_serial1[]', s1);
        fd.append('bundle_serial2[]', s2);
        fd.append('bundle_property_number[]', par);
      });

      if(rowHasMissing){
        bundleMissing.push('Bundle ' + groupLabel);
      }
    });

    if(bundleMissing.length){
      var bundleMessage = isPropertyNumberOptionalFund
        ? 'Bundle equipment requires Bundle Set, Category, Unit, Asset Class, and generated unit rows for: '
        : 'Bundle equipment requires Bundle Set, Category, Unit, Asset Class, and Property Number for: ';
      Swal.fire({ icon:'warning', title:'Validation error', text: bundleMessage + bundleMissing.join(', ') + '.' });
      $form.removeData('printPreference');
      $btn.data('submitting', false);
      $btn.html('<i class="fa-solid fa-pen-to-square"></i>&nbsp;<span class="btn-text">Save</span>');
      if (window.GSO && window.GSO.AddItemPage && typeof window.GSO.AddItemPage.syncAddItemSubmitButton === 'function') {
        window.GSO.AddItemPage.syncAddItemSubmitButton();
      }
      return;
    }
  }
  
  $.ajax({
    type: "POST",
    url: "../auth/auth.php",
    data: fd,
    processData: false,
    contentType: false,
    success: function(response){
      var res;
      try { res = jQuery.parseJSON(response || '{}'); }
      catch(e){
        Swal.fire({ icon:'error', title:'Unexpected response', text:'The server returned an invalid response. Please try again.' });
        setTimeout(function(){ location.reload(); }, 1500);
        return;
      }
      if(res.status == 200){
        // Print only when the backend explicitly requests it (NEW purchases).
        try {
          var data = (res && res.data) ? res.data : null;
          if(shouldPrintAfterSave && data && data.should_print){
            var parRefs = Array.isArray(data.par_refs) ? data.par_refs : [];
            var icsRefs = Array.isArray(data.ics_refs) ? data.ics_refs : [];

            if (!parRefs.length && !icsRefs.length) {
              var fallbackRef = String(data.reference_number || '').trim();
              var fallbackCategory = String(data.category || '').trim().toUpperCase();
              if (fallbackRef && fallbackCategory === 'PAR') { parRefs = [fallbackRef]; }
              if (fallbackRef && fallbackCategory === 'ICS') { icsRefs = [fallbackRef]; }
            }

            if (parRefs.length) {
              var parUrl = 'printpar.php?refnumber=' + encodeURIComponent(parRefs[0]);
              if (printWins.par && !printWins.par.closed) {
                printWins.par.location = parUrl;
              } else {
                window.open(parUrl, '_blank');
              }
            } else if (printWins.par && !printWins.par.closed) {
              printWins.par.close();
            }

            if (icsRefs.length) {
              var icsUrl = 'inventory_custodian_slip.php?refs=' + icsRefs.map(encodeURIComponent).join(',');
              if (printWins.ics && !printWins.ics.closed) {
                printWins.ics.location = icsUrl;
              } else {
                window.open(icsUrl, '_blank');
              }
            } else if (printWins.ics && !printWins.ics.closed) {
              printWins.ics.close();
            }
          } else {
            if(printWins.par && !printWins.par.closed) { printWins.par.close(); }
            if(printWins.ics && !printWins.ics.closed) { printWins.ics.close(); }
          }
        } catch(e) {
          if(printWins.par && !printWins.par.closed){ try { printWins.par.close(); } catch(_){} }
          if(printWins.ics && !printWins.ics.closed){ try { printWins.ics.close(); } catch(_){} }
        }

        Swal.fire({
          position: 'center',
          icon: 'success',
          title: 'Property Added Successfully',
          showConfirmButton: false,
          timer: 1500
        });
        setTimeout(function(){
          $('#addItemModal').modal('hide');
          $('#addItem')[0].reset();
          $('#addItem').removeData('printPreference');
          // Force fresh token by reloading page (simplest approach)
          location.reload();
        }, 1700);
      } else if(res.status == 409){
        // Duplicate / invalid token
        Swal.fire({
          icon: 'warning',
          title: 'Duplicate submission',
          text: res.message || 'This form was already submitted.',
          timer: 2500
        });
        // Re-enable for a fresh attempt only if user keeps modal open (need new token) -> fetch new token via page reload
        setTimeout(function(){ location.reload(); }, 2000);
      } else if(res.status == 500){
        Swal.fire({
          icon: 'error',
          title: 'Save failed',
          text: res.message || 'Server error'
        });
        // Allow retry (token consumed, need new token) -> reload
        setTimeout(function(){ location.reload(); }, 1500);
      } else if(res.status == 422){
        // Validation error: keep modal open so user can correct inputs
        Swal.fire({ icon:'warning', title:'Validation error', text: res.message || 'Please check your inputs.' });
        // Re-enable submit button for correction
        var $btn = $('#addItemSubmitBtn');
        $btn.data('submitting', false);
        $btn.html('<i class="fa-solid fa-pen-to-square"></i>&nbsp;<span class="btn-text">Save</span>');
        if (window.GSO && window.GSO.AddItemPage && typeof window.GSO.AddItemPage.syncAddItemSubmitButton === 'function') {
          window.GSO.AddItemPage.syncAddItemSubmitButton();
        }
      } else {
        // Unexpected
        Swal.fire({ icon:'info', title:'Notice', text: res.message || 'Unexpected response.' });
        setTimeout(function(){ location.reload(); }, 1500);
      }
    },
    error: function(xhr, status){
      Swal.fire({ icon:'error', title:'Network error', text:'Please check your connection. If unsure whether saved, the system prevented duplicate.'});
      setTimeout(function(){ location.reload(); }, 2000);
    },
    complete: function(){
      var $btn = $('#addItemSubmitBtn');
      $('#addItem').removeData('printPreference');
      // Restore button to avoid hanging if no reload happens
      $btn.data('submitting', false);
      $btn.html('<i class="fa-solid fa-pen-to-square"></i>&nbsp;<span class="btn-text">Save</span>');
      if (window.GSO && window.GSO.AddItemPage && typeof window.GSO.AddItemPage.syncAddItemSubmitButton === 'function') {
        window.GSO.AddItemPage.syncAddItemSubmitButton();
      }
    }
  });
 });
 
$(document).on('submit','#parGenFundUpdate',function(e){//to update p.a.r general fund information
  e.preventDefault();
  var pargfid = new FormData(this);
  pargfid.append("update_par",true);

  function reloadInventoryTableKeepState(){
    try {
      if (!(window.$ && $.fn && $.fn.dataTable)) return false;
      if (!$('#InventoryTable').length) return false;
      if (!$.fn.dataTable.isDataTable || !$.fn.dataTable.isDataTable('#InventoryTable')) return false;
      var dt = $('#InventoryTable').DataTable();
      if (dt && dt.ajax && typeof dt.ajax.reload === 'function') {
        dt.ajax.reload(null, false);
        return true;
      }
    } catch (e) {
      // fall through to full reload
    }
    return false;
  }

  $.ajax({
    type: "POST",
    url: "../auth/auth.php",
    data: pargfid,
    processData: false,
    contentType: false,
    dataType: 'json',
    success: function(response){
      var res = (typeof response === 'string') ? jQuery.parseJSON(response) : response;

      if(res.status == 200){
        Swal.fire({
            position: 'center',
            icon: 'success',
            title: 'P.A.R Updated Successfully',
            showConfirmButton: false,
            timer: 2000
          }).then(()=>{
            $('#editInModal').modal('hide');
            $('#parGenFundUpdate')[0].reset();
            // Refresh inventory list without resetting paging/sort/search.
            if (!reloadInventoryTableKeepState()) {
              location.reload();
            }
        });  
      } else {
        Swal.fire({ icon: 'error', title: 'Update failed', text: res.message || 'Unable to update property details.' });
      }
    },
    error: function(xhr, status, error){
      var message = error || 'Unable to update property details.';
      if (xhr && xhr.responseJSON && xhr.responseJSON.message) {
        message = xhr.responseJSON.message;
      } else if (xhr && xhr.responseText) {
        message = xhr.responseText.replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim() || message;
      }
      Swal.fire({ icon: 'error', title: 'Update failed', text: message });
    }
  }); 
});
$(document).on('click','.transferProperty', function(){// to fetch p.a.r and i.c.s general fund information for transfer
  var propertyTransfer = $(this).data("value");
    $.ajax({
      type: 'GET',
      url:'../auth/auth.php?propertyTransfer='+propertyTransfer,
      success:function(response){

        var res = jQuery.parseJSON(response);

        if(res.status==422){
          alert(res.message);
        }else if(res.status == 200){
          $('#par_num').val(res.data.par_number);
          $('#category').val(res.data.category);
          $('#citem').val(res.data.item);
          $('#empid').val(res.data.emp_id);
          $('#cuser').val(res.data.user);
          $('#cdeptid').val(res.data.dept_id);
          $('#cdept').val(res.data.department_name);
          $('#transInModal').modal('show');
        }
      }
    });
});

// Reusable function for opening printable tabs after transfer
function openTransferTabs(cdept, dept, category, refnum) {
  cdept = String(cdept);
  dept = String(dept);
  category = String(category).toUpperCase();
  if (category === 'PAR' || category === 'ICS') {
    if (cdept === dept) {
      // Same department, open one tab
      let url = (category === 'PAR') ? 'printpt.php?reference_number=' + refnum : 'inventory_custodian_slip.php?reference_number=' + refnum;
      let win = window.open(url, '_blank');
      if (win) win.print();
      else alert('Please allow popups for this site.');
    } else {
      // Different departments, open two tabs
      let url1 = 'property_transfer_report.php?reference_number=' + refnum;
      let url2 = (category === 'PAR') ? 'printpt.php?reference_number=' + refnum : 'inventory_custodian_slip.php?reference_number=' + refnum;
      let win1 = window.open(url1, '_blank');
      let win2 = window.open(url2, '_blank');
      if (win1) win1.print();
      if (win2) win2.print();
      if (!win1 || !win2) alert('Please allow popups for this site.');
    }
  }
}

$(document).on('submit','#parTransfer',function(e){//to transfer p.a.r gen fund to another end user section
  e.preventDefault();
  var cdept = $('#cdeptid').val();
  var dept = $('#dept').val();
  var refnum = document.getElementById("refnum").value;
  var category = document.getElementById("category").value;
  var fd = new FormData(this);
  fd.append("transferPar", true);

  $.ajax({
    type: "POST",
    url: "../auth/auth.php",
    data: fd,
    processData: false,
    contentType: false,
    success:  function(response){
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
          openTransferTabs(cdept, dept, category, refnum);
          location.reload();
        },1900)
      }
    }
  });
});

$(document).on('click','.transferFromStock', function(){// to fetch p.a.r and i.c.s general fund information to transfer from stock
  var TransferFromStock = $(this).data("value");
    $.ajax({
      type: 'GET',
      url:'../auth/auth.php?TransferFromStock='+TransferFromStock,
      success:function(response){

        var res = jQuery.parseJSON(response);

        if(res.status==422){
          alert(res.message);
        }else if(res.status == 200){
          $('#par_num').val(res.data.par_number);
          $('#category').val(res.data.category);
          $('#citem').val(res.data.item);
          $('#empid').val(res.data.emp_id);
          $('#cuser').val(res.data.user);
          $('#cdeptid').val(res.data.dept_id);
          $('#cdept').val(res.data.department_name);
          $('#transInModal').modal('show');
        }
      }
    });
});
$(document).on('submit','#parTransferFromStock',function(e){//to transfer p.a.r and i.c.s gen fund to another end user from stock
  e.preventDefault();
  var refnum = document.getElementById("refnum").value;
  var category = document.getElementById("category").value;
  var fd = new FormData(this);
  fd.append("parTransferFromStock", true);

    if(category == "PAR"){
      $.ajax({
        type: "POST",
        url: "../auth/auth.php",
        data: fd,
        processData: false,
        contentType: false,
        success:  function(response){
    
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
                var nw = window.open('printpt.php?reference_number='+refnum,"_blank");
                location.reload();
                nw.print();
              },1900)    
          }
        }
      });
    }else if(category == "ICS"){
      $.ajax({
        type: "POST",
        url: "../auth/auth.php",
        data: fd,
        processData: false,
        contentType: false,
        success:  function(response){
    
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
                var nw = window.open('inventory_custodian_slip.php?reference_number='+refnum,"_blank");
                location.reload();
                nw.print();
              },1900)    
          }
        }
      });
    }
});

$(document).on('click','.retInv', function(e){//to return gen fund property to stock
  e.preventDefault();

  if(confirm("Return item to GSO?")){

    var retInv = $(this).data("value");

    $.ajax({
      type: "POST",
      url: "../auth/auth.php",
      data:{
        'return_inv': true,
        'retInv': retInv
      },
      success:function(response){
        var res = jQuery.parseJSON(response);
              if(res.status == 500){
                alert(res.message);
              }else{
                alert(res.message);

                location.reload();
              }
          }
    });
  }
});

$(document).on('click','.editPropertyDetails', function(){// to fetch p.a.r general fund information
  var getParId = $(this).data("value");
  $.ajax({
    type: 'GET',
    url:'../auth/auth.php?getParId='+ getParId,
    success: function(response){

      var res = jQuery.parseJSON(response);

      if(res.status==422){
        alert(res.message);
      }else if(res.status == 200){
        $('#par').val(res.data.par_number); // hidden input used for update WHERE clause
        $('#par_display_top').val(res.data.par_number);
        $('#par_display').val(res.data.par_number);
        $('#edate').val(res.data.date_aquired);
        $('#paritem').val(res.data.item);
        $('#brand').val(res.data.model);
        $('#description').val(res.data.description);
        $('#remarks').val(res.data.remarks || '');
        $('#fund').val(res.data.fund);
        $('#par_ics_no').val(res.data.par_ics_number);
        $('#serial').val(res.data.serial_number);
        $('#serial2').val(res.data.serial_number_2);
        $('#uvalue').val(res.data.amount);
        $('#acode').val(res.data.account_code).data('original', res.data.account_code || '');
        $('#par_new').val(''); // reset any pending property number rename
        $('#po').val(res.data.purchase_order);
        $('#obr').val(res.data.obr_number);
        $('#pr').val(res.data.purchase_request);
        $('#supplier').val(res.data.supplier);
        $('#jev').val(res.data.jev_number);
        // Current custodian context for bundle-with (if present)
        try {
          $('#gf_current_emp_id').val(res.data.current_emp_id || '');
          $('#gf_current_dept_id').val(res.data.current_dept_id || '');
          $('#sef_current_emp_id').val(res.data.current_emp_id || '');
          $('#sef_current_dept_id').val(res.data.current_dept_id || '');
        } catch (e) {}

        // Keep Bundle-with table in sync with the currently viewed property number
        try { $('#editInModal').trigger('gso:bundleWith:refresh'); } catch (e) {}
        $('#editInModal').modal('show');
      }
    }
  });
});
$(document).on('click','.editPropertyDetails', function (e) {// to view par property history
  e.preventDefault();
  var parnumberid = $(this).data("value");
 
  $.ajax({
    type: "POST",
    url:"../auth/auth.php",
    data: {
      'viewHistoryBtn': true,
      'parnumberid': parnumberid,
    }, 
    success: function (response) {
      $('.ParGenHistory').html(response);
    }
  });   
});

// Account code change: regenerate property number live in the edit modal
$(document).off('change.gfAcodeEdit').on('change.gfAcodeEdit', '#editInModal #acode', function () {
  var newAcode  = $(this).val().trim();
  var origAcode = String($(this).data('original') || '').trim();
  var oldPar    = $('#par').val().trim();
  var fund      = $('#fund').val().trim();

  // Revert display if account code changed back to original
  if (newAcode === origAcode) {
    $('#par_new').val('');
    $('#par_display, #par_display_top').val(oldPar);
    return;
  }

  if (!newAcode || !oldPar) return;

  // Determine category + year + dept from the existing property number segments
  var parts    = oldPar.split('-');
  var first    = parts[0] || '';
  var deptCode = parts[parts.length - 1] || '';
  var category, year;

  if      (/^\d{4}$/.test(first)) { category = 'PAR'; year = first; }
  else if (/^\d{2}$/.test(first)) { category = 'ICS'; year = '20' + first; }
  else { return; } // unrecognised format

  $.ajax({
    url: '../auth/auth.php',
    method: 'POST',
    dataType: 'json',
    data: {
      generate_property_number: 1,
      category: category,
      year: year,
      account_code: newAcode,
      dept: deptCode,
      fund: fund,
      exclude: [oldPar]
    },
    success: function (res) {
      if (res && res.success) {
        $('#par_new').val(res.pr_number);
        $('#par_display, #par_display_top').val(res.pr_number);
      } else {
        Swal && Swal.fire({ icon: 'warning', title: res.error || 'Unable to generate property number.' });
        $('#par_new').val('');
        $('#par_display, #par_display_top').val(oldPar);
      }
    },
    error: function () {
      $('#par_new').val('');
      $('#par_display, #par_display_top').val(oldPar);
    }
  });
});

//par general fund

// ==========================================================
// General Fund: Bundle-with (Add Bundle Item)
// ==========================================================
(function bindGfBundleWith(){
  // Only bind on pages that contain the bundle modal.
  if (!$('#bundleSearchModal').length || !$('#bundleWithTbody').length) { return; }

  var path = String((window.location && window.location.pathname) || '');
  var isSef = /sef-inventory\.php$/i.test(path) || $('#sef_current_emp_id').length > 0;

  function apiLookupParams(par){
    return isSef ? { sef_bundle_lookup: 1, par_number: par } : { gf_bundle_lookup: 1, par_number: par };
  }

  function apiListParams(bundleWith){
    return isSef ? { get_sef_bundle_with: 1, bundle_with: bundleWith } : { get_gf_bundle_with: 1, bundle_with: bundleWith };
  }

  function apiSavePayload(deptId, empId, propertyNumber, bundleWith){
    var base = {
      dept_id: deptId,
      emp_id: empId,
      property_number: propertyNumber,
      bundle_with: bundleWith
    };
    if (isSef) {
      base.save_sef_bundle_link = 1;
    } else {
      base.save_gf_bundle_link = 1;
    }
    return base;
  }

  var state = {
    lastSearch: null,
    lastAutoSearchedPar: '',
    autoTimer: null,
    searching: false
  };

  function esc(text){ return $('<div>').text(text === null || text === undefined ? '' : String(text)).html(); }

  function viewedPar(){
    return String($('#par').val() || '').trim().toUpperCase();
  }

  function viewedDeptId(){
    var v = String($('#gf_current_dept_id').val() || $('#sef_current_dept_id').val() || '').trim();
    var n = parseInt(v, 10);
    return isNaN(n) ? 0 : n;
  }

  function viewedEmpId(){
    var v = String($('#gf_current_emp_id').val() || $('#sef_current_emp_id').val() || '').trim();
    var n = parseInt(v, 10);
    return isNaN(n) ? 0 : n;
  }

  function setMsg(msg){
    if (!msg) { $('#bundleSearchMsg').hide().text(''); return; }
    $('#bundleSearchMsg').show().text(String(msg));
  }

  function resetSearchUI(clearInput){
    state.lastSearch = null;
    state.searching = false;
    $('#bundleAddBtn').prop('disabled', true);
    setMsg('');
    if (clearInput) {
      $('#bundleSearchPar').val('');
      state.lastAutoSearchedPar = '';
    }
    // Reset search result table
    $('#bundleSearchResultTbody').html(
      '<tr id="bundleSearchEmptyRow">' +
        '<td class="text-muted text-center" colspan="5">No result. Search a property number.</td>' +
      '</tr>'
    );
  }

  function renderSearchResult(d){
    if (!d) { resetSearchUI(false); return; }
    var rowHtml = '<tr>' +
      '<td>' + (esc(d.item || '') || '<span class="text-muted">-</span>') + '</td>' +
      '<td>' + (esc(d.model || '') || '<span class="text-muted">-</span>') + '</td>' +
      '<td>' + (esc(d.serial_number || '') || '<span class="text-muted">-</span>') + '</td>' +
      '<td>' + (esc(d.serial_number_2 || '') || '<span class="text-muted">-</span>') + '</td>' +
      '<td class="text-nowrap">' + (esc(d.par_number || '') || '<span class="text-muted">-</span>') + '</td>' +
    '</tr>';
    $('#bundleSearchResultTbody').html(rowHtml);
  }

  function loadBundleWithTable(){
    var par = viewedPar();
    if (!par) {
      $('#bundleWithTbody').find('tr.bundleWithRow').remove();
      $('#bundleWithEmptyRow').show();
      return;
    }

    $.ajax({
      type: 'GET',
      url: '../auth/auth.php',
      data: apiListParams(par),
      success: function(resp){
        var res = resp;
        if (typeof resp === 'string') {
          try { res = JSON.parse(resp); } catch (e) { res = null; }
        }
        if (!(res && res.status === 200 && Array.isArray(res.data))) {
          // If table doesn't exist yet, keep UI stable.
          $('#bundleWithTbody').find('tr.bundleWithRow').remove();
          $('#bundleWithEmptyRow').show();
          return;
        }

        var rows = res.data;
        $('#bundleWithTbody').find('tr.bundleWithRow').remove();
        if (!rows.length) {
          $('#bundleWithEmptyRow').show();
          return;
        }
        $('#bundleWithEmptyRow').hide();
        rows.forEach(function(r){
          var itemRaw = String((r && r.item) ? r.item : '').trim();
          var modelRaw = String((r && r.model) ? r.model : '').trim();
          var itemDisplay = modelRaw ? (itemRaw + ' - ' + modelRaw) : itemRaw;
          var item = esc(itemDisplay);
          var s1 = String((r && r.serial_number) ? r.serial_number : '').trim();
          var s2 = String((r && r.serial_number_2) ? r.serial_number_2 : '').trim();
          var serial = esc([s1, s2].filter(Boolean).join(' / '));
          var pnum = esc((r && r.property_number) ? r.property_number : '');
          $('#bundleWithTbody').append(
            '<tr class="bundleWithRow">' +
              '<td>' + (item || '<span class="text-muted">(unknown)</span>') + '</td>' +
              '<td>' + (serial || '<span class="text-muted">(none)</span>') + '</td>' +
              '<td class="text-nowrap">' + (pnum || '<span class="text-muted">-</span>') + '</td>' +
            '</tr>'
          );
        });
      },
      error: function(){
        // Silent fail; keep table unchanged.
      }
    });
  }

  function performLookup(par, onDone){
    var cleaned = String(par || '').trim().toUpperCase();
    if (!cleaned) {
      setMsg('Please enter a property number.');
      return;
    }
    var viewPar = viewedPar();
    if (viewPar && cleaned === viewPar) {
      setMsg('You are viewing this same property number.');
      return;
    }

    state.searching = true;
    $('#bundleAddBtn').prop('disabled', true);
    setMsg('');

    $.ajax({
      type: 'GET',
      url: '../auth/auth.php',
      data: apiLookupParams(cleaned),
      success: function(resp){
        state.searching = false;
        var res = resp;
        if (typeof resp === 'string') {
          try { res = JSON.parse(resp); } catch (e) { res = null; }
        }

        if (!(res && res.status === 200 && res.data && res.data.par_number)) {
          resetSearchUI(false);
          setMsg((res && res.message) ? res.message : 'No matching property found.');
          if (typeof onDone === 'function') { onDone(false); }
          return;
        }

        state.lastSearch = {
          par_number: String(res.data.par_number || '').trim().toUpperCase(),
          item: res.data.item || '',
          model: res.data.model || '',
          serial_number: res.data.serial_number || '',
          serial_number_2: res.data.serial_number_2 || ''
        };
        renderSearchResult(state.lastSearch);
        $('#bundleAddBtn').prop('disabled', false);
        if (typeof onDone === 'function') { onDone(true); }
      },
      error: function(){
        state.searching = false;
        setMsg('Server error while searching.');
        if (typeof onDone === 'function') { onDone(false); }
      }
    });
  }

  function scheduleAutoSearch(){
    if (state.searching) { return; }
    var v = String($('#bundleSearchPar').val() || '').trim().toUpperCase();
    if (!v) { return; }
    if (v === state.lastAutoSearchedPar) { return; }

    if (state.autoTimer) {
      try { clearTimeout(state.autoTimer); } catch (e) {}
      state.autoTimer = null;
    }

    state.autoTimer = setTimeout(function(){
      var vv = String($('#bundleSearchPar').val() || '').trim().toUpperCase();
      if (!vv) { return; }
      if (vv === state.lastAutoSearchedPar) { return; }
      state.lastAutoSearchedPar = vv;
      performLookup(vv);
    }, 450);
  }

  // Load bundle-with rows whenever the viewed property changes and/or modal opens.
  $('#editInModal')
    .off('shown.gsoBundleWith gsoBundleWithRefresh')
    .on('shown.gsoBundleWith gso:bundleWith:refresh.gsoBundleWithRefresh', function(){
      loadBundleWithTable();
    });

  // Open search modal
  $(document)
    .off('click.gsoOpenBundleSearch', '#btnAddBundleLink')
    .on('click.gsoOpenBundleSearch', '#btnAddBundleLink', function(){
      resetSearchUI(true);
      $('#bundleSearchModal').modal('show');
      setTimeout(function(){ $('#bundleSearchPar').focus(); }, 150);
    });

  // Auto-search once per entered property number
  $(document)
    .off('input.gsoBundleSearch', '#bundleSearchPar')
    .on('input.gsoBundleSearch', '#bundleSearchPar', function(){
      scheduleAutoSearch();
    });

  // Enter triggers immediate search
  $(document)
    .off('keydown.gsoBundleSearchEnter', '#bundleSearchPar')
    .on('keydown.gsoBundleSearchEnter', '#bundleSearchPar', function(e){
      if (e.key === 'Enter') {
        e.preventDefault();
        var v = String($('#bundleSearchPar').val() || '').trim().toUpperCase();
        if (!v) { setMsg('Please enter a property number.'); return; }
        state.lastAutoSearchedPar = v;
        performLookup(v);
      }
    });

  // Save bundle link
  $(document)
    .off('click.gsoBundleAdd', '#bundleAddBtn')
    .on('click.gsoBundleAdd', '#bundleAddBtn', function(){
      if (!state.lastSearch || !state.lastSearch.par_number) {
        setMsg('Search an item first.');
        return;
      }

      var bundleWith = viewedPar();
      var propertyNumber = String(state.lastSearch.par_number || '').trim().toUpperCase();
      var deptId = viewedDeptId();
      var empId = viewedEmpId();

      if (!bundleWith) { setMsg('Missing viewed property number.'); return; }
      if (!deptId || !empId) {
        setMsg('Missing current custodian info (dept/employee).');
        return;
      }

      $('#bundleAddBtn').prop('disabled', true);

      $.ajax({
        type: 'POST',
        url: '../auth/auth.php',
        data: apiSavePayload(deptId, empId, propertyNumber, bundleWith),
        success: function(resp){
          var res = resp;
          if (typeof resp === 'string') {
            try { res = JSON.parse(resp); } catch (e) { res = null; }
          }
          if (res && res.status === 200) {
            if (window.toastr) toastr.success(res.message || 'Bundled successfully');
            $('#bundleSearchModal').modal('hide');
            loadBundleWithTable();
            return;
          }
          $('#bundleAddBtn').prop('disabled', false);
          var msg = (res && res.message) ? res.message : 'Failed to save bundle';
          if (window.toastr) toastr.error(msg);
          else setMsg(msg);
        },
        error: function(){
          $('#bundleAddBtn').prop('disabled', false);
          if (window.toastr) toastr.error('Server error saving bundle');
        }
      });
    });
})();

//clearance type
$(document).on('submit','#ct_form',function(e){//to save clearance type information
  e.preventDefault();
  var fd = new FormData(this);
  fd.append("save_ct",true);

  $.ajax({
    type: "POST",
    url: "../auth/auth.php",
    data: fd,
    processData: false,
    contentType: false,
    success:function(response){
      var res = jQuery.parseJSON(response);

    if(res.status == 200 ){
      Swal.fire({
        position: 'center',
        icon: 'success',
        title: 'Save Successfully',
        showConfirmButton: false,
        timer: 1700
      }).then(()=>{
        $('#addClearanceTypeModal').modal('hide');
        $('#ct_form')[0].reset();
        location.reload();
    });
  }
}
});
});
$(document).on('click','.editct', function(){//to fetch clearance information 
  var ctid = $(this).val();
  $.ajax({
    type:'GET',
    url:'../auth/auth.php?ctid='+ ctid,
    success:function(response){

      var res = jQuery.parseJSON(response);
      
      if(res.status == 200){
        $('#CtId').val(res.data.ctype_id);
        $('#ectname').val(res.data.clearance_name);
        $('#ectcode').val(res.data.clearance_code);
        $('#editCtModal').modal('show');
      }
    }
  });
});
$(document).on('submit','#ct_update',function(e){//to update clearance information
  e.preventDefault();

  var fd = new FormData(this);
  fd.append("update_clearance", true);

  $.ajax({
    type: "POST",
    url: "../auth/auth.php",
    data: fd,
    processData: false,
    contentType: false,
    success: function(response){
      var res = jQuery.parseJSON(response);

     if(res.status == 200 ){
        Swal.fire({
          position: 'center',
          icon: 'success',
          title: 'Updated Successfully',
          showConfirmButton: false,
          timer: 1700
        }).then(()=>{
          $('#editCtModal').modal('hide');
          $('#ct_update')[0].reset();
          location.reload();
      });
      }
    }
  });
});
$(document).on('click','.delct', function(e){//to delete clearance information
  e.preventDefault();

  if(confirm("Are you sure? ")){

  var delct = $(this).val();

  $.ajax({
    type: "POST",
    url: "../auth/auth.php",
    data:{
      'delete_ct':true,
      'delct':delct
    },
    success:function(response){
      var res = jQuery.parseJSON(response);
      if(res.status == 500){
        alert(res.message);
      }else{
        alert(res.message);

        location.reload();
      }
    }
  });
}
});
//clearance type

    $(document).on('click','.retIcsInv', function(e){//to return i.c.s property general fund to stock
      e.preventDefault();
    
      if(confirm("Return item to GSO?")){
    
        var retInv = $(this).data("value");
    
        $.ajax({
          type: "POST",
          url: "../auth/auth.php",
          data:{
            'return_icsInv': true,
            'retIcsInv': retIcsInv
          },
          success:function(response){
            var res = jQuery.parseJSON(response);
                  if(res.status == 500){
                    alert(res.message);
                  }else{
                    alert(res.message);
    
                    location.reload();
                  }
              }
        });
      }
    });
//ics general fund
$(document).on('submit','#update_property_clearance', function(e){//to update employee property clearance details
  e.preventDefault();
  var fd = new FormData(this);
  fd.append("update_pc",true);

  $.ajax({
    type:"POST",
    url:"../auth/auth.php",
    data:fd,
    processData:false,
    contentType:false,
    success:function(response){
      var res = jQuery.parseJSON(response);
      if(res.status==200){
        Swal.fire({
          position: 'center',
          icon: 'success',
          title: 'Form updated successfully!',
          showConfirmButton: false,
          timer: 2000
        }).then(()=>{
          location.reload();
        }); 
      }
    }
  });
});
//fetch general fund property info subject for return to stock -> directly open condition modal
$(document).on('click','.returnItem', function(e){
  e.preventDefault();
  var returnToStock = $(this).data('value');
  $.ajax({
    type: 'GET',
    url: '../auth/auth.php?returnToStock=' + encodeURIComponent(returnToStock),
    success: function(response){
      var res = jQuery.parseJSON(response || '{}');
      if(res.status == 200 && res.data){
        $('#cond_parnum').val(res.data.par_number || '');
        $('#cond_empid').val(res.data.emp_id || '');
        $('#cond_deptid').val(res.data.dept_id || '');
        $('#cond_cat').val(res.data.category || '');
        $('#itemConditionModal').modal('show');
      } else {
        alert(res.message || 'Unable to fetch item details.');
      }
    },
    error: function(){
      alert('Server error fetching item details.');
    }
  });
});
// Chained prompt: after confirming return, ask condition then route accordingly
$(document).on('submit','#returnedToStock',function(e){
  e.preventDefault();
  // If condition modal not present, fallback to original behavior
  if(!$('#itemConditionModal').length){
    var reference = document.getElementById('refnumber').value;
    var fd = new FormData(this);
    fd.append('returned_item', true);
    $.ajax({
      type:'POST', url:'../auth/auth.php', data: fd,
      processData:false, contentType:false,
      success:function(response){
        var res = jQuery.parseJSON(response);
        if(res.status == 200){
          Swal.fire({ position:'center', icon:'success', title:'Property Returned Successfully', showConfirmButton:false, timer:1500 });
          setTimeout(function(){
            $('#returnToStockModal').modal('hide');
            var nw = window.open('property_return_slip.php?reference_number='+encodeURIComponent(reference),'_blank');
            location.reload();
            if(nw && typeof nw.print==='function'){ nw.print(); }
          },1900);
        }
      }
    });
    return;
  }

  // Capture values from the first modal and pass to the condition modal
  var par = $('#returnToStockModal #parnum').val();
  var emp = $('#returnToStockModal #empid').val();
  var dept = $('#returnToStockModal #cdept_id').val();
  var cat = $('#returnToStockModal #cat').val();
  var ref = $('#returnToStockModal #refnumber').val();

  $('#cond_parnum').val(par);
  $('#cond_empid').val(emp);
  $('#cond_deptid').val(dept);
  $('#cond_cat').val(cat);
  $('#cond_refnumber').val(ref);

  $('#returnToStockModal').modal('hide');
  $('#itemConditionModal').modal('show');
});

// Serviceable -> keep in return_to_stock (use existing returned_item endpoint)
$(document).on('click','#chooseServiceable', function(){
  var ref = $('#cond_refnumber').val();
  var fd = new FormData();
  fd.append('returned_item', true);
  fd.append('parnum', $('#cond_parnum').val());
  fd.append('empid', $('#cond_empid').val());
  fd.append('cdept_id', $('#cond_deptid').val());
  fd.append('cat', $('#cond_cat').val());
  fd.append('refnumber', ref);

  $(this).prop('disabled', true);
  $.ajax({
    type: 'POST', url: '../auth/auth.php', data: fd,
    processData: false, contentType: false,
    success: function(response){
      var res = jQuery.parseJSON(response);
      if(res.status == 200){
        Swal.fire({ position:'center', icon:'success', title:'Property Returned Successfully', showConfirmButton:false, timer:1500 });
        setTimeout(function(){
          $('#itemConditionModal').modal('hide');
          var nw = window.open('property_return_slip.php?reference_number='+encodeURIComponent(ref), '_blank');
          location.reload();
          if(nw && typeof nw.print==='function'){ nw.print(); }
        }, 1900);
      } else {
        Swal.fire({ icon:'error', title:'Failed', text: res.message || 'Unable to return to stock.' });
        $('#chooseServiceable').prop('disabled', false);
      }
    },
    error: function(){
      Swal.fire({ icon:'error', title:'Server error' });
      $('#chooseServiceable').prop('disabled', false);
    }
  });
});

// Unserviceable -> move to return_to_stock first, then mark as unserviceable (re-use endpoints)
$(document).on('click','#chooseUnserviceable', function(){
  var $btn = $(this);
  $btn.prop('disabled', true);
  var par = $('#cond_parnum').val();
  var emp = $('#cond_empid').val();
  var dept = $('#cond_deptid').val();
  var cat = $('#cond_cat').val();
  var ref = $('#cond_refnumber').val();

  // Step 1: return to stock
  var fd1 = new FormData();
  fd1.append('returned_item', true);
  fd1.append('parnum', par);
  fd1.append('empid', emp);
  fd1.append('cdept_id', dept);
  fd1.append('cat', cat);
  fd1.append('refnumber', ref);

  $.ajax({
    type:'POST', url:'../auth/auth.php', data: fd1,
    processData:false, contentType:false,
    success:function(resp1){
      var r1 = jQuery.parseJSON(resp1 || '{}');
      if(!(r1 && r1.status==200)){
        Swal.fire({ icon:'error', title:'Failed', text:(r1 && r1.message) || 'Unable to return item.' });
        $btn.prop('disabled', false);
        return;
      }
      // Step 2: mark as unserviceable (expects parnum, deptid, cat, refnumber)
      var fd2 = new FormData();
      fd2.append('unserviceable', true);
      fd2.append('parnum', par);
      fd2.append('deptid', dept); // map from cdept_id
      fd2.append('cat', cat);
      fd2.append('refnumber', ref);

      $.ajax({
        type:'POST', url:'../auth/auth.php', data: fd2,
        processData:false, contentType:false,
        success:function(resp2){
          var r2 = jQuery.parseJSON(resp2 || '{}');
          if(r2 && r2.status==200){
            Swal.fire({ position:'center', icon:'success', title:'Item moved to Unserviceable', showConfirmButton:false, timer:1600 });
            setTimeout(function(){
              $('#itemConditionModal').modal('hide');
              try {
                var nw = window.open('property_return_slip.php?reference_number='+encodeURIComponent(ref), '_blank');
                if(nw && typeof nw.print==='function'){ nw.print(); }
              } catch(e) { /* ignore popup blockers */ }
              location.reload();
            }, 1700);
          } else {
            Swal.fire({ icon:'error', title:'Failed', text:(r2 && r2.message) || 'Unable to mark unserviceable.' });
            $btn.prop('disabled', false);
          }
        },
        error:function(){ Swal.fire({ icon:'error', title:'Server error' }); $btn.prop('disabled', false); }
      });
    },
    error:function(){ Swal.fire({ icon:'error', title:'Server error' }); $btn.prop('disabled', false); }
  });
});
//fetch data of p.a.r and i.c.s general fund property subject for unserviceable
$(document).on('click','.stockItems', function(e){
  var stockItems = $(this).data("value");
  $.ajax({
    type: 'GET',
    url:'../auth/auth.php?stockItems='+stockItems,
    success:function(response){

      var res = jQuery.parseJSON(response);

      if(res.status==422){
        alert(res.message);
      }else if(res.status == 200){
        $('#parnum').val(res.data.par_number);
        $('#cat').val(res.data.category);
        $('#deptid').val(res.data.dept_id);
        $('#unserviceableItemModal').modal('show');
      }
    }
  });
});
$(document).on('submit','#unserviceableItems',function(e){//to declare items as unserviceable
  e.preventDefault();
  var fd = new FormData(this);
  fd.append("unserviceable",true);

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
            title: 'Successfully added to Userviceable',
            showConfirmButton: false,
            timer: 2000
          }).then(()=>{
            $('#unserviceableItems').modal('hide');
            location.reload();
        });  
      }
    }
  }); 
});
});

  // Add Return (admin/add-return.php)
  // Keeps page JS centralized (per project convention).
  (function(){
    function hasAddReturnUI(){
      return $('#addReturnItemModal').length && $('#addReturnItemForm').length;
    }

    function valTrim(sel){
      return String($(sel).val() || '').trim();
    }

    function initRecentReturnItemsTable(){
      if (!$.fn.DataTable) { return; }
      var $t = $('#recentReturnItemsTable');
      if (!$t.length) { return; }
      if ($.fn.DataTable.isDataTable($t)) { return; }

      var table = $t.DataTable({
        responsive: true,
        processing: true,
        serverSide: false,
        stateSave: true,
        paging: true,
        info: true,
        lengthChange: true,
        pageLength: 10,
        lengthMenu: [10, 25, 50, 100, 500],
        order: [[0, 'desc']],
        ajax: {
          url: '../auth/fetch_recent_return_items_dataTable.php',
          type: 'post'
        },
        columns: [
          { data: 'created_at' },
          { data: 'return_type' },
          { data: 'fund' },
          { data: 'category' },
          { data: 'item' },
          { data: 'model' },
          { data: 'serial_number' },
          { data: 'serial_number_2' },
          { data: 'par_number' }
        ]
      });

      setInterval(function(){
        if (table && table.ajax && !document.hidden) {
          table.ajax.reload(null, false);
        }
      }, 8000);
    }

    function initAddReturnModal(){
      if (!hasAddReturnUI()) { return; }
      var $form = $('#addReturnItemForm');
      if (!$form.length) { return; }
      if ($form.data('gsoAddReturnInit')) { return; }
      $form.data('gsoAddReturnInit', true);

      function isUnserviceableReturnType(){
        return String($('#ri_return_type').val() || '').trim().toUpperCase() === 'UNSERVICEABLE';
      }

      function isUnrepeatedMode(){
        return isUnserviceableReturnType() && $('#ri_unrepeated').is(':checked');
      }

      function setUnrepeatedVisible(visible){
        var $wrap = $('#ri_unrepeated_wrap');
        if($wrap.length){
          $wrap.toggle(!!visible);
        } else {
          $('#ri_unrepeated').closest('.custom-control').toggle(!!visible);
        }
      }

      // Ensure Select2 renders in modal
      function initAccountCodeSelect2(){
        if (!$.fn.select2) { return; }
        var $sel = $('#ri_account_code');
        if (!$sel.length) { return; }
        if ($sel.hasClass('select2-hidden-accessible')) { return; }
        var $parent = $('#addReturnItemModal');
        $sel.select2({
          theme: 'bootstrap4',
          width: '100%',
          dropdownParent: $parent.length ? $parent : $(document.body),
          placeholder: 'Select account code',
          allowClear: true
        });
      }

      function setCollapse($el, open){
        if(!$el || !$el.length){ return; }
        if(open){
          $el.addClass('is-open').attr('aria-hidden', 'false');
          try { $el[0].style.height = Math.min(360, $el[0].scrollHeight || 0) + 'px'; } catch(e) {}
        } else {
          $el.removeClass('is-open').attr('aria-hidden', 'true');
          try { $el[0].style.height = '0px'; } catch(e) {}
        }
      }

      // Property number(s) (always required on this page)
      // Keep a single textarea and use comma+space separation when qty > 1.
      function splitPropertyNumbers(raw){
        return String(raw || '')
          .split(/[\n\r,]+/)
          .map(function(s){ return String(s || '').trim().toUpperCase(); })
          .filter(function(s){ return !!s; });
      }

      function clearPropertyNumberWarning(){
        $('#ri_property_number_warning').hide().text('');
      }

      function syncPropertyNumberUI(){
        normalizeQty();
        var $ta = $('#ri_property_number');
        if(!$ta.length){ return; }

        // Always enabled + required.
        $('#ri_property_number_single_row').show();
        $ta.prop('disabled', false).prop('required', true).prop('readonly', true);

        // Optional legacy container (no longer used).
        var $legacyRow = $('#ri_property_numbers_row');
        if($legacyRow.length){ setCollapse($legacyRow, false); }
        var $legacyWrap = $('#ri_propertyNumbers');
        if($legacyWrap.length){ $legacyWrap.empty(); }

        clearPropertyNumberWarning();
      }

      function hasManualPropertyNumbers(){
        var v = String($('#ri_property_number').val() || '').trim();
        var auto = String($('#ri_property_number').attr('data-gso-auto') || '') === '1';
        return v !== '' && !auto;
      }

      function clearAutoFlags(){
        $('#ri_property_number').removeAttr('data-gso-auto');
      }

      function nextPropNumber(num){
        var parts = String(num || '').split('-');
        if(parts.length < 4){ return String(num || ''); }
        var idx = parts.length - 2; // sequence component (always before dept)
        var seq = parts[idx] || '';
        var pad = String(seq).length || 3;
        var inc = String((parseInt(seq, 10) || 0) + 1).padStart(pad, '0');
        parts[idx] = inc;
        return parts.join('-');
      }
      var propGenDeb;
      function scheduleAutoGeneratePropertyNumbers(){
        clearTimeout(propGenDeb);
        propGenDeb = setTimeout(autoGeneratePropertyNumbers, 250);
      }
      function autoGeneratePropertyNumbers(){
        if(hasManualPropertyNumbers()){ return; }
        var unrepeated = isUnrepeatedMode();
        var qty = unrepeated ? 1 : normalizeQty();
        var category = valTrim('#ri_category');
        var year = valTrim('#ri_date_aquired');
        var accountCode = valTrim('#ri_account_code');
        var dept = valTrim('#ri_end_user_dept');
        if(!category || !year || !accountCode || !dept){ return; }

        $.ajax({
          url: '../auth/auth.php',
          type: 'POST',
          dataType: 'json',
          data: {
            generate_return_property_number: 1,
            category: category,
            year: year,
            account_code: accountCode,
            dept: dept
          },
          success: function(res){
            if(!res || res.status !== 200 || !res.data || !res.data.property_number){
              return;
            }
            var first = String(res.data.property_number || '').trim();
            if(!first){ return; }

            // Unrepeated (UNSERVICEABLE only): always generate a single property number.
            // Otherwise, generate a comma-separated list when qty > 1.
            if(unrepeated || qty <= 1){
              $('#ri_property_number').val(first).attr('data-gso-auto', '1');
            } else {
              var list = [];
              var cur = first;
              for(var i=1;i<=qty;i++){
                list.push(cur);
                cur = nextPropNumber(cur);
              }
              $('#ri_property_number').val(list.join(', ')).attr('data-gso-auto', '1');
            }

            scheduleUpdateSaveState();
          }
        });
      }

      // Qty + serial rows
      var serialCache = {};
      function rowKey(idx){
        var n = parseInt(idx, 10);
        if(!n || n < 1){ return null; }
        return String(n);
      }

      function snapshotSerialCache(){
        $('#ri_serialRows input').each(function(){
          var name = $(this).attr('name') || '';
          var m = name.match(/^(serial2?|serial)\[(\d+)\]$/i);
          if(!m){ return; }
          var field = m[1].toLowerCase();
          var k = rowKey(m[2]);
          if(!k){ return; }
          if(!serialCache[k]){ serialCache[k] = { serial1: '', serial2: '' }; }
          if(field === 'serial2'){ serialCache[k].serial2 = String($(this).val() || ''); }
          else { serialCache[k].serial1 = String($(this).val() || ''); }
        });
      }

      function normalizeQty(){
        var $q = $('#ri_qty');
        var qty = parseInt($q.val(), 10);
        if(!qty || qty < 1){ qty = 1; $q.val(1); }
        if(qty > 50){ qty = 50; $q.val(50); }
        return qty;
      }

      function renderSerialRows(){
        if(isUnrepeatedMode()){
          $('#ri_add_serial').prop('checked', false);
          $('#ri_serialRows').empty();
          setCollapse($('#ri_serial_fields_row'), false);
          return;
        }

        snapshotSerialCache();
        var on = $('#ri_add_serial').is(':checked');
        var qty = normalizeQty();
        var $wrap = $('#ri_serialRows');
        if(!on){
          $wrap.empty();
          setCollapse($('#ri_serial_fields_row'), false);
          return;
        }

        var html = '';
        for(var i=1;i<=qty;i++){
          var k = rowKey(i);
          var v1 = (serialCache[k] && serialCache[k].serial1) ? serialCache[k].serial1 : '';
          var v2 = (serialCache[k] && serialCache[k].serial2) ? serialCache[k].serial2 : '';
          html +=
            '<div class="form-row mb-2">' +
              '<div class="form-group col-md-6 mb-0">' +
                '<label class="small text-muted mb-1">Serial Number 1 - ' + i + '</label>' +
                '<input type="text" class="form-control text-uppercase" name="serial[' + i + ']" value="' + $('<div>').text(v1).html() + '" placeholder="Enter serial number">' +
              '</div>' +
              '<div class="form-group col-md-6 mb-0">' +
                '<label class="small text-muted mb-1">Serial Number 2 - ' + i + '</label>' +
                '<input type="text" class="form-control text-uppercase" name="serial2[' + i + ']" value="' + $('<div>').text(v2).html() + '" placeholder="Enter serial number 2">' +
              '</div>' +
            '</div>';
        }
        $wrap.html(html);
        setCollapse($('#ri_serial_fields_row'), true);
      }

      function syncSerialToggle(){
        renderSerialRows();
      }

      function syncNoBrandModel(){
        var $cb = $('#ri_no_model');
        var $model = $('#ri_model');
        if(!$cb.length || !$model.length){ return; }

        var checked = $cb.is(':checked');
        var DEFAULT_VAL = 'NO BRAND/MODEL';
        if(checked){
          $model.val(DEFAULT_VAL).prop('readonly', true);
        } else {
          if(String($model.val() || '').trim().toUpperCase() === DEFAULT_VAL){
            $model.val('');
          }
          $model.prop('readonly', false);
        }

        scheduleUpdateSaveState();
      }

      function validateRequired(){
        var missing = [];
        if(!valTrim('#ri_qty')) missing.push('Qty');
        if(!valTrim('#ri_fund')) missing.push('Fund');
        if(!valTrim('#ri_category')) missing.push('Category');
        if(!valTrim('#ri_account_code')) missing.push('Account Code');
        if(!valTrim('#ri_item')) missing.push('Asset Class');
        if(!valTrim('#ri_model')) missing.push('Model');
        if(!valTrim('#ri_description')) missing.push('Description');
        if(!valTrim('#ri_date_aquired')) missing.push('Date Acquired');
        if(!valTrim('#ri_return_type')) missing.push('Return Type');
        if(!valTrim('#ri_end_user_dept')) missing.push('Department');
        if(!valTrim('#ri_end_user_emp')) missing.push('Employee');
        var q = normalizeQty();
        var unrepeated = isUnrepeatedMode();
        var rawProp = valTrim('#ri_property_number');
        var list = splitPropertyNumbers(rawProp);
        if(unrepeated || q <= 1){
          if(!rawProp) missing.push('Property Number');
        } else {
          if(list.length !== q) missing.push('Property Numbers');
        }
        if(missing.length){
          if(window.Swal){ Swal.fire('Required', 'Please complete: ' + missing.join(', ') + '.', 'warning'); }
          return false;
        }
        return true;
      }

      function setSaveDisabled(disabled){
        var $btn = $('#btnRiSaveReturn');
        if(!$btn.length){
          $btn = $form.find('button[type="submit"], input[type="submit"]').first();
        }
        if($btn && $btn.length){
          $btn.prop('disabled', !!disabled);
        }
      }

      function isFormCompleteSilent(){
        if(!valTrim('#ri_qty')) return false;
        if(!valTrim('#ri_fund')) return false;
        if(!valTrim('#ri_category')) return false;
        if(!valTrim('#ri_account_code')) return false;
        if(!valTrim('#ri_item')) return false;
        if(!valTrim('#ri_model')) return false;
        if(!valTrim('#ri_description')) return false;
        if(!valTrim('#ri_date_aquired')) return false;
        if(!valTrim('#ri_return_type')) return false;
        if(!valTrim('#ri_end_user_dept')) return false;
        if(!valTrim('#ri_end_user_emp')) return false;

        // Additional required fields when adding a new employee.
        if(isAddNewEmployee()){
          if(!valTrim('#ri_new_emp')) return false;
          if(!valTrim('#ri_position')) return false;
          if(!String($('#ri_emp_id').val() || '').trim()) return false;
          var msg = String($('#ri-name-validation-msg').text() || '').toLowerCase();
          if(msg.indexOf('validating') !== -1) return false;
          if(msg.indexOf('already exists') !== -1) return false;
        }

        var q = normalizeQty();
        var unrepeated = isUnrepeatedMode();
        var rawProp = valTrim('#ri_property_number');
        var list = splitPropertyNumbers(rawProp);
        if(unrepeated || q <= 1){
          if(!rawProp) return false;
        } else {
          if(list.length !== q) return false;
        }
        return true;
      }

      function syncUnrepeatedBehavior(){
        var show = isUnserviceableReturnType();
        setUnrepeatedVisible(show);
        if(!show){
          $('#ri_unrepeated').prop('checked', false);
        }

        var unrepeated = isUnrepeatedMode();
        var $serialToggle = $('#ri_add_serial');
        if($serialToggle.length){
          $serialToggle.prop('disabled', unrepeated);
          if(unrepeated){ $serialToggle.prop('checked', false); }
        }
        renderSerialRows();

        // If unrepeated is active, keep a single property number even when qty > 1.
        var $pn = $('#ri_property_number');
        if(unrepeated && $pn.length){
          var first = splitPropertyNumbers($pn.val())[0] || '';
          $pn.val(first);
        }

        scheduleAutoGeneratePropertyNumbers();
        scheduleUpdateSaveState();
      }

      var saveStateDeb;
      function scheduleUpdateSaveState(){
        clearTimeout(saveStateDeb);
        saveStateDeb = setTimeout(function(){
          setSaveDisabled(!isFormCompleteSilent());
        }, 80);
      }

      function lockForm(locked){
        $form.find('button, input, select, textarea').prop('disabled', !!locked);
        $('#addReturnItemModal [data-dismiss="modal"]').prop('disabled', false);

        if(locked){
          setSaveDisabled(true);
        } else {
          scheduleUpdateSaveState();
        }
      }

      function updateEmployeeSelectState(){
        var hasDept = !!valTrim('#ri_end_user_dept');
        var $emp = $('#ri_end_user_emp');
        $emp.val('');
        if(!hasDept){
          $emp.prop('disabled', true).html('<option value="">SELECT A DEPARTMENT FIRST</option>');
        } else {
          $emp.prop('disabled', false);
          if($emp.find('option').length === 0){ $emp.html('<option value="">-SELECT-</option>'); }
        }

        scheduleUpdateSaveState();
      }

      function loadEmployeesForDept(deptCode){
        var dept = String(deptCode || '').trim();
        var $emp = $('#ri_end_user_emp');
        updateEmployeeSelectState();
        if(!dept){ return; }
        // Keep enabled so it never appears "stuck" disabled.
        $emp.prop('disabled', false).html('<option value="">Loading...</option>');
        $.ajax({
          url: '../auth/auth.php',
          type: 'POST',
          data: { departmentid: dept },
          success: function(html){
            $emp.html(html);
            updateEmployeeSelectState();
          },
          error: function(){
            $emp.html('<option value="">Failed to load employees</option>').prop('disabled', false);
          }
        });
      }

      function resetDeptAndEmp(){
        var $dept = $('#ri_end_user_dept');
        if($dept.length){
          $dept.val('');
        }
        updateEmployeeSelectState();
        toggleAddNewEmployeeSection();

        scheduleUpdateSaveState();
      }

      function loadDepartmentsForFund(fundVal){
        var fund = String(fundVal || '').trim();
        var $dept = $('#ri_end_user_dept');
        if(!$dept.length){ return; }

        resetDeptAndEmp();

        if(!fund){
          $dept.prop('disabled', true).html('<option value="">SELECT FUND FIRST</option>');
          return;
        }

        $dept.prop('disabled', true).html('<option value="">Loading...</option>');
        $.ajax({
          url: '../auth/auth.php',
          type: 'POST',
          data: { fund_for_departments: fund },
          success: function(html){
            $dept.html(html).prop('disabled', false);
          },
          error: function(){
            $dept.html('<option value="">Failed to load departments</option>').prop('disabled', false);
          }
        });
      }

      function isAddNewEmployee(){
        return (String($('#ri_end_user_emp').val() || '').trim().toLowerCase() === 'add_new_emp');
      }

      function toggleAddNewEmployeeSection(){
        var show = isAddNewEmployee();
        var $sec = $('#ri_add_new_employee');
        if(show){
          $sec.stop(true,true).slideDown(200);
          $('#ri_new_emp, #ri_position').prop('required', true);
        } else {
          $sec.stop(true,true).slideUp(200);
          $('#ri_new_emp, #ri_position').prop('required', false);
          $('#ri_new_emp, #ri_position').val('');
          $('#ri-name-validation-msg').hide().text('');
        }

        scheduleUpdateSaveState();
      }

      // Populate year select once
      (function initYears(){
        var $year = $('#ri_date_aquired');
        if(!$year.length || $year.data('gsoYearsInit')){ return; }
        $year.data('gsoYearsInit', true);
        var currentYear = new Date().getFullYear();
        for(var y=currentYear; y>=2000; y--){
          $year.append($('<option>', { value: String(y), text: String(y) }));
        }
      })();

      // Events
      $('#addReturnItemModal').on('shown.bs.modal.gsoAddReturn', function(){
        initAccountCodeSelect2();
      });

      $(document).on('change.gsoAddReturn', '#ri_fund', function(){
        var $sel = $('#ri_account_code');
        if($sel.length){ $sel.val('').trigger('change'); }

        loadDepartmentsForFund($(this).val() || '');
      });

      $('#btnAddReturnItem').off('click.gsoAddReturn').on('click.gsoAddReturn', function(){
        $form.trigger('reset');
        serialCache = {};
        clearAutoFlags();
        normalizeQty();
        renderSerialRows();
        syncPropertyNumberUI();
        loadDepartmentsForFund('');
        toggleAddNewEmployeeSection();
        setUnrepeatedVisible(false);
        $('#ri_unrepeated').prop('checked', false);
        // Ensure model checkbox behavior is applied after reset.
        syncNoBrandModel();
        $('#addReturnItemModal').modal('show');

        // Default: disabled until all required fields are filled.
        scheduleUpdateSaveState();
      });

      // Delegated binding (more resilient if the modal DOM is re-rendered)
      $(document).off('change.gsoAddReturn', '#ri_end_user_dept').on('change.gsoAddReturn', '#ri_end_user_dept', function(){
        loadEmployeesForDept($(this).val() || '');
      });
      $(document).off('change.gsoAddReturn', '#ri_end_user_emp').on('change.gsoAddReturn', '#ri_end_user_emp', function(){
        toggleAddNewEmployeeSection();
      });

      $(document).off('change.gsoAddReturn', '#ri_no_model').on('change.gsoAddReturn', '#ri_no_model', function(){
        syncNoBrandModel();
      });

      // Validate employee name
      var nameDebounce;
      $(document).off('input.gsoAddReturn', '#ri_new_emp').on('input.gsoAddReturn', '#ri_new_emp', function(){
        var name = String($(this).val() || '').trim();
        var $msg = $('#ri-name-validation-msg');
        clearTimeout(nameDebounce);
        if(!name){ $msg.hide().text(''); return; }
        $msg.show().text('Validating...').css('color','red');

        scheduleUpdateSaveState();
        nameDebounce = setTimeout(function(){
          $.ajax({
            url: '../auth/auth.php',
            type: 'POST',
            dataType: 'json',
            data: { validate_employee_name: 1, emp_name: name },
            success: function(res){
              if(res && res.exists){ $msg.text('Employee name already exists!').css('color','red'); }
              else { $msg.text('Employee name is available.').css('color','green'); }

              scheduleUpdateSaveState();
            },
            error: function(){ $msg.text('Validation error.').css('color','red'); }
          });
        }, 600);
      });

      function submitManualReturn(){
        $form.trigger('submit.gsoManualReturn');
      }
      window.gsoSubmitManualReturnItem = submitManualReturn;

      // Keep Save button disabled until required fields are complete.
      $form
        .off('input.gsoAddReturnSaveState change.gsoAddReturnSaveState')
        .on('input.gsoAddReturnSaveState change.gsoAddReturnSaveState', 'input, select, textarea', function(){
          scheduleUpdateSaveState();
        });

      // Save new employee (when selected)
      $form.off('submit.gsoAddNewEmp').on('submit.gsoAddNewEmp', function(e){
        if(!isAddNewEmployee()) { return true; }
        e.preventDefault();

        var dept = valTrim('#ri_end_user_dept');
        var name = valTrim('#ri_new_emp');
        var pos = valTrim('#ri_position');
        if(!dept || !name || !pos){
          if(window.Swal){ Swal.fire('Required', 'Please complete the new employee fields.', 'warning'); }
          return false;
        }
        var msg = String($('#ri-name-validation-msg').text() || '').toLowerCase();
        if(msg.indexOf('already exists') !== -1){
          if(window.Swal){ Swal.fire('Duplicate', 'Employee name already exists.', 'error'); }
          return false;
        }

        lockForm(true);
        $.ajax({
          url: '../auth/auth.php',
          type: 'POST',
          dataType: 'json',
          data: {
            save_employee_info: 1,
            fname: name,
            department: dept,
            position: pos,
            pcustodian: 0
          },
          success: function(res){
            lockForm(false);
            if(res && res.status === 200){
              var newEmpId = (res && res.data && res.data.emp_id) ? String(res.data.emp_id) : '';
              loadEmployeesForDept(dept);
              setTimeout(function(){
                if (newEmpId) { $('#ri_end_user_emp').val(newEmpId).trigger('change'); }
                submitManualReturn();
              }, 350);
              $('#ri_new_emp, #ri_position').val('');
              $('#ri-name-validation-msg').hide().text('');
            } else {
              if(window.Swal){ Swal.fire('Error', (res && res.message) ? res.message : 'Failed to add employee.', 'error'); }
            }
          },
          error: function(xhr){
            lockForm(false);
            if(window.Swal){ Swal.fire('Error', 'Failed to add employee.', 'error'); }
            console.error('Add employee error:', xhr && xhr.responseText);
          }
        });
        return false;
      });

      // Main submit (manual_return_item)
      $form.off('submit.gsoManualReturn').on('submit.gsoManualReturn', function(e){
        e.preventDefault();
        if(!validateRequired()){ return false; }
        if(isAddNewEmployee()){
          $form.trigger('submit.gsoAddNewEmp');
          return false;
        }

        var fd = new FormData(this);
        fd.append('manual_return_item', '1');

        lockForm(true);
        $.ajax({
          url: '../auth/auth.php',
          type: 'POST',
          data: fd,
          processData: false,
          contentType: false,
          dataType: 'json',
          success: function(res){
            lockForm(false);
            if(res && res.status === 200){
              var ref = res.data && res.data.reference_number ? String(res.data.reference_number) : '';
              if(window.Swal){ Swal.fire('Success', res.message || 'Saved.', 'success'); }
              $('#addReturnItemModal').modal('hide');
              try {
                if($.fn.DataTable && $('#recentReturnItemsTable').length){
                  $('#recentReturnItemsTable').DataTable().ajax.reload(null, false);
                }
              } catch (e) {}
              if(ref){
                // Print immediately
                window.open('property_return_slip.php?reference_number=' + encodeURIComponent(ref), '_blank');
              }
            } else {
              if(window.Swal){ Swal.fire('Error', (res && res.message) ? res.message : 'Unable to save item.', 'error'); }
            }
          },
          error: function(xhr){
            lockForm(false);
            if(window.Swal){ Swal.fire('Error', 'Unable to save item.', 'error'); }
            console.error('manual_return_item error:', xhr && xhr.responseText);
          }
        });
        return false;
      });

      $('#ri_qty').off('input.gsoAddReturn change.gsoAddReturn blur.gsoAddReturn')
        .on('input.gsoAddReturn change.gsoAddReturn blur.gsoAddReturn', function(){
          normalizeQty();
          if($('#ri_add_serial').is(':checked')){ renderSerialRows(); }
          else { $('#ri_qty_warning').hide().text(''); }
          syncPropertyNumberUI();
          scheduleAutoGeneratePropertyNumbers();
          scheduleUpdateSaveState();
        });

      $(document).off('input.gsoAddReturn change.gsoAddReturn', '#ri_serialRows input')
        .on('input.gsoAddReturn change.gsoAddReturn', '#ri_serialRows input', function(){
          snapshotSerialCache();
        });

      $(document)
        .off('input.gsoAddReturn change.gsoAddReturn', '#ri_property_number')
        .on('input.gsoAddReturn change.gsoAddReturn', '#ri_property_number', function(){
          clearPropertyNumberWarning();
          $(this).removeAttr('data-gso-auto');
          scheduleUpdateSaveState();
        });

      // Normalize separators on blur for readability (no constant rewriting while typing).
      $(document)
        .off('blur.gsoAddReturn', '#ri_property_number')
        .on('blur.gsoAddReturn', '#ri_property_number', function(){
          var qty = normalizeQty();
          if(isUnrepeatedMode()){
            var first = splitPropertyNumbers($(this).val())[0] || '';
            $(this).val(first);
            return;
          }
          var list = splitPropertyNumbers($(this).val());
          if(!list.length){ return; }
          if(qty > 1){ $(this).val(list.join(', ')); }
          else { $(this).val(list[0]); }
        });

      $('#ri_category, #ri_account_code, #ri_date_aquired, #ri_end_user_dept')
        .off('change.gsoAddReturn input.gsoAddReturn')
        .on('change.gsoAddReturn input.gsoAddReturn', function(){
          scheduleAutoGeneratePropertyNumbers();
          scheduleUpdateSaveState();
        });

      $('#ri_add_serial').off('change.gsoAddReturn').on('change.gsoAddReturn', syncSerialToggle);

      $(document)
        .off('change.gsoAddReturn', '#ri_return_type')
        .on('change.gsoAddReturn', '#ri_return_type', function(){
          syncUnrepeatedBehavior();
        });

      $(document)
        .off('change.gsoAddReturn', '#ri_unrepeated')
        .on('change.gsoAddReturn', '#ri_unrepeated', function(){
          syncUnrepeatedBehavior();
        });

      // Default state on load
      syncSerialToggle();
      syncNoBrandModel();
      syncPropertyNumberUI();
      updateEmployeeSelectState();
      toggleAddNewEmployeeSection();
      syncUnrepeatedBehavior();
      scheduleAutoGeneratePropertyNumbers();
      scheduleUpdateSaveState();
    }

    $(function(){
      if(!hasAddReturnUI()){
        return;
      }
      initRecentReturnItemsTable();
      initAddReturnModal();
    });
  })();

  // General Fund - Account Code (PAR / ICS) tabbed DataTable
  window.GSO = window.GSO || {};
  window.GSO.GfAccountCodes = window.GSO.GfAccountCodes || (function(){
    var table = null;
    var currentCategory = 'PAR';

    function normCat(v){
      var s = String(v || '').trim().toUpperCase();
      return (s === 'ICS') ? 'ICS' : 'PAR';
    }

    function esc(v){
      return $('<div>').text(String(v == null ? '' : v)).html();
    }

    function money(v){
      var n = Number(v || 0);
      if (!isFinite(n)) { n = 0; }
      try {
        return '₱ ' + n.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
      } catch (e) {
        return '₱ ' + String(n.toFixed(2));
      }
    }

    function setTotal(v){
      $('#gfAcctCategoryTotal').text(money(v));
    }

    function setReportTitle(){
      var base = 'General Fund Account Code';
      $('#reportTitle').text(base + ' (' + currentCategory + ')');
    }

    function reload(){
      if (table && table.ajax && typeof table.ajax.reload === 'function') {
        table.ajax.reload(null, false);
      }
    }

    function setActiveTab(){
      var $links = $('#gfAcctCategoryTabs [data-category]');
      if (!$links.length) { return; }
      $links.removeClass('active');
      $links.filter('[data-category="' + currentCategory + '"]').addClass('active');
    }

    function bindTabs(){
      var $links = $('#gfAcctCategoryTabs [data-category]');
      if (!$links.length) { return; }
      $links.off('click.gsoGfAcctTabs').on('click.gsoGfAcctTabs', function(e){
        e.preventDefault();
        var cat = normCat($(this).data('category'));
        if (cat === currentCategory) { return; }
        currentCategory = cat;
        setActiveTab();
        setReportTitle();
        reload();
      });
    }

    function initTable(){
      if (!$('#dataTable').length || !$.fn.dataTable) { return; }
      if ($.fn.dataTable.isDataTable('#dataTable')) {
        table = $('#dataTable').DataTable();
        return;
      }

      table = $('#dataTable').DataTable({
        responsive: true,
        lengthChange: false,
        autoWidth: false,
        stateSave: true,
        processing: true,
        serverSide: true,
        ajax: {
          url: '../auth/auth.php',
          type: 'POST',
          data: function(d){
            d.gf_account_codes_dt = 1;
            d.category = currentCategory;
          }
        },
        columns: [
          {
            data: 'account_code',
            render: function(data, type, row){
              var id = row && row.id ? String(row.id) : '';
              var code = esc(data);
              var href = 'general-fund-account-inventory.php?acct=' + encodeURIComponent(id) + '&cat=' + encodeURIComponent(currentCategory);
              return '<a href="' + href + '">' + code + '</a>';
            }
          },
          { data: 'account_name', render: function(d){ return esc(d); } },
          {
            data: 'total_value',
            className: 'text-right',
            render: function(d){ return money(d); }
          },
          {
            data: null,
            orderable: false,
            searchable: false,
            className: 'text-center',
            render: function(_d, _t, row){
              var id = row && row.id ? String(row.id) : '';
              return (
                '<button type="button" value="' + esc(id) + '" class="editacct btn btn-sm btn-success" title="Edit"><i class="fas fa-edit"></i></button> ' +
                '<button type="button" value="' + esc(id) + '" class="delacct btn btn-sm btn-danger" title="Delete"><i class="fas fa-trash"></i></button>'
              );
            }
          }
        ],
        order: [[0, 'asc']],
        dom: "<'row'<'col-sm-12 col-md-6'B><'col-sm-12 col-md-6'f>>" +
             "<'row'<'col-sm-12'tr>>" +
             "<'row'<'col-sm-12 col-md-5'i><'col-sm-12 col-md-7'p>>",
        buttons: [
          {
            extend: 'excel',
            orientation: 'landscape',
            pageSize: 'LEGAL',
            title: function(){ return $('#reportTitle').text() || 'General Fund Account Code'; },
            exportOptions: { columns: [0,1,2] }
          },
          {
            extend: 'print',
            orientation: 'landscape',
            pageSize: 'LEGAL',
            title: function(){ return $('#reportTitle').text() || 'General Fund Account Code'; },
            exportOptions: { columns: [0,1,2] }
          }
        ]
      });

      $('#dataTable').off('xhr.dt.gsoGfAcct').on('xhr.dt.gsoGfAcct', function(_e, _settings, json){
        if (json && typeof json.total_amount !== 'undefined') {
          setTotal(json.total_amount);
        }
      });
    }

    function init(){
      if (!$('#gfAccountCodePage').length) { return; }
      currentCategory = normCat($('#gfAccountCodePage').data('defaultCategory') || 'PAR');
      setActiveTab();
      bindTabs();
      setReportTitle();
      initTable();
    }

    return { init: init, reload: reload };
  })();

  // SEF - Account Code (PAR / ICS) tabbed DataTable
  window.GSO.SefAccountCodes = window.GSO.SefAccountCodes || (function(){
    var table = null;
    var currentCategory = 'PAR';

    function normCat(v){
      var s = String(v || '').trim().toUpperCase();
      return (s === 'ICS') ? 'ICS' : 'PAR';
    }

    function esc(v){
      return $('<div>').text(String(v == null ? '' : v)).html();
    }

    function money(v){
      var n = Number(v || 0);
      if (!isFinite(n)) { n = 0; }
      try {
        return '₱ ' + n.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
      } catch (e) {
        return '₱ ' + String(n.toFixed(2));
      }
    }

    function setTotal(v){
      $('#sefAcctCategoryTotal').text(money(v));
    }

    function setReportTitle(){
      var base = 'SEF Account Code';
      $('#reportTitle').text(base + ' (' + currentCategory + ')');
    }

    function reload(){
      if (table && table.ajax && typeof table.ajax.reload === 'function') {
        table.ajax.reload(null, false);
      }
    }

    function setActiveTab(){
      var $links = $('#sefAcctCategoryTabs [data-category]');
      if (!$links.length) { return; }
      $links.removeClass('active');
      $links.filter('[data-category="' + currentCategory + '"]').addClass('active');
    }

    function bindTabs(){
      var $links = $('#sefAcctCategoryTabs [data-category]');
      if (!$links.length) { return; }
      $links.off('click.gsoSefAcctTabs').on('click.gsoSefAcctTabs', function(e){
        e.preventDefault();
        var cat = normCat($(this).data('category'));
        if (cat === currentCategory) { return; }
        currentCategory = cat;
        setActiveTab();
        setReportTitle();
        reload();
      });
    }

    function initTable(){
      if (!$('#dataTable').length || !$.fn.dataTable) { return; }
      if ($.fn.dataTable.isDataTable('#dataTable')) {
        table = $('#dataTable').DataTable();
        return;
      }

      table = $('#dataTable').DataTable({
        responsive: true,
        lengthChange: false,
        autoWidth: false,
        stateSave: true,
        processing: true,
        serverSide: true,
        ajax: {
          url: '../auth/auth.php',
          type: 'POST',
          data: function(d){
            d.sef_account_codes_dt = 1;
            d.category = currentCategory;
          }
        },
        columns: [
          {
            data: 'account_code',
            render: function(data, type, row){
              var id = row && row.id ? String(row.id) : '';
              var code = esc(data);
              var href = 'sef-account-inventory.php?acct=' + encodeURIComponent(id) + '&cat=' + encodeURIComponent(currentCategory);
              return '<a href="' + href + '">' + code + '</a>';
            }
          },
          { data: 'account_name', render: function(d){ return esc(d); } },
          {
            data: 'total_value',
            className: 'text-right',
            render: function(d){ return money(d); }
          },
          {
            data: null,
            orderable: false,
            searchable: false,
            className: 'text-center',
            render: function(_d, _t, row){
              var id = row && row.id ? String(row.id) : '';
              return (
                '<button type="button" value="' + esc(id) + '" class="editacct btn btn-sm btn-success" title="Edit"><i class="fas fa-edit"></i></button> ' +
                '<button type="button" value="' + esc(id) + '" class="delacct btn btn-sm btn-danger" title="Delete"><i class="fas fa-trash"></i></button>'
              );
            }
          }
        ],
        order: [[0, 'asc']],
        dom: "<'row'<'col-sm-12 col-md-6'B><'col-sm-12 col-md-6'f>>" +
             "<'row'<'col-sm-12'tr>>" +
             "<'row'<'col-sm-12 col-md-5'i><'col-sm-12 col-md-7'p>>",
        buttons: [
          {
            extend: 'excel',
            orientation: 'landscape',
            pageSize: 'LEGAL',
            title: function(){ return $('#reportTitle').text() || 'SEF Account Code'; },
            exportOptions: { columns: [0,1,2] }
          },
          {
            extend: 'print',
            orientation: 'landscape',
            pageSize: 'LEGAL',
            title: function(){ return $('#reportTitle').text() || 'SEF Account Code'; },
            exportOptions: { columns: [0,1,2] }
          }
        ]
      });

      $('#dataTable').off('xhr.dt.gsoSefAcct').on('xhr.dt.gsoSefAcct', function(_e, _settings, json){
        if (json && typeof json.total_amount !== 'undefined') {
          setTotal(json.total_amount);
        }
      });
    }

    function init(){
      if (!$('#sefAccountCodePage').length) { return; }
      currentCategory = normCat($('#sefAccountCodePage').data('defaultCategory') || 'PAR');
      setActiveTab();
      bindTabs();
      setReportTitle();
      initTable();
    }

    return { init: init, reload: reload };
  })();

  $(function(){
    try {
      if (window.GSO && window.GSO.GfAccountCodes) { window.GSO.GfAccountCodes.init(); }
      if (window.GSO && window.GSO.SefAccountCodes) { window.GSO.SefAccountCodes.init(); }
    } catch (e) {}
  });

  window.GSO.MotorVehicleDashboard = window.GSO.MotorVehicleDashboard || (function(){
    var table = null;
    var currentScope = 'all';
    var currentMonthText = '';
    var scopeTitles = {
      all: 'All Motor Vehicles',
      registered: 'Registered Vehicles',
      unregistered: 'Vehicles Needing Details',
      due_current_month: 'For Registration This Month'
    };

    function esc(value) {
      return $('<div>').text(String(value == null ? '' : value)).html();
    }

    function displayValue(value) {
      var text = String(value == null ? '' : value).trim();
      return text !== '' ? esc(text) : '<span class="text-muted">N/A</span>';
    }

    function renderStatus(value, type, row) {
      var text = String(value == null ? '' : value).trim();
      var key = String(row && row.registration_status_key ? row.registration_status_key : '').trim();
      var classes = {
        past_deadline: 'badge-danger',
        due_now: 'badge-warning',
        due_current_month: 'badge-info',
        new_vehicle: 'badge-secondary',
        invalid: 'badge-dark',
        unregistered: 'badge-secondary',
        scheduled: 'badge-success'
      };
      return text !== '' ? '<span class="badge ' + (classes[key] || 'badge-light') + '">' + esc(text) + '</span>' : displayValue(text);
    }

    function cleanAmount(value) {
      var raw = String(value || '').replace(/,/g, '').replace(/[^0-9.]/g, '');
      var parts = raw.split('.');
      if (parts.length > 2) {
        raw = parts.shift() + '.' + parts.join('');
      }
      return raw;
    }

    function formatAmount(value, fixedDecimals) {
      var clean = cleanAmount(value);
      if (!clean) { return ''; }

      var parts = clean.split('.');
      var whole = parts[0] || '';
      var decimals = parts.length > 1 ? parts[1].slice(0, 2) : '';

      whole = whole.replace(/^0+(?=\d)/, '').replace(/\B(?=(\d{3})+(?!\d))/g, ',');
      if (!whole && decimals) { whole = '0'; }

      if (fixedDecimals) {
        decimals = (decimals + '00').slice(0, 2);
        return (whole || '0') + '.' + decimals;
      }

      return parts.length > 1 ? whole + '.' + decimals : whole;
    }

    function notify(icon, title, text) {
      if (window.Swal && Swal.fire) {
        Swal.fire({ icon: icon, title: title, text: text || '' });
        return;
      }
      alert((title || '') + (text ? '\n' + text : ''));
    }

    function setMetric(key, value) {
      var number = Number(value || 0);
      if (!isFinite(number)) { number = 0; }
      $('[data-mv-metric="' + key + '"]').text(number.toLocaleString('en-US'));
    }

    function loadMetrics() {
      $.ajax({
        url: '../auth/auth.php',
        type: 'GET',
        dataType: 'json',
        data: { motor_vehicle_dashboard: 'metrics' },
        success: function(resp) {
          resp = resp || {};
          currentMonthText = String(resp.current_month || '').trim();
          setMetric('total_vehicles', resp.total_vehicles);
          setMetric('registered_vehicles', resp.registered_vehicles);
          setMetric('for_registration', resp.for_registration);
          setMetric('fund_sources', resp.fund_sources);
          updateScopeUi();
        },
        error: function() {
          setMetric('total_vehicles', 0);
          setMetric('registered_vehicles', 0);
          setMetric('for_registration', 0);
          setMetric('fund_sources', 0);
        }
      });
    }

    function scopeSubtitle() {
      if (currentScope === 'registered') {
        return 'Vehicles with saved motor vehicle details';
      }
      if (currentScope === 'unregistered') {
        return 'Covered assets without saved vehicle details';
      }
      if (currentScope === 'due_current_month') {
        return 'Plate-based renewal schedule for ' + (currentMonthText || 'the current month');
      }
      return '';
    }

    function updateScopeUi() {
      $('.gso-mv-scope')
        .removeClass('is-active')
        .attr('aria-pressed', 'false');
      $('.gso-mv-scope[data-scope="' + currentScope + '"]')
        .addClass('is-active')
        .attr('aria-pressed', 'true');
      $('#motorVehicleTableTitle').text(scopeTitles[currentScope] || scopeTitles.all);
      $('#motorVehicleTableSubtitle').text(scopeSubtitle());
    }

    function setScope(scope) {
      scope = String(scope || 'all').trim();
      if (!scopeTitles[scope]) { scope = 'all'; }
      if (currentScope === scope) { return; }
      currentScope = scope;
      updateScopeUi();
      if (table) { table.ajax.reload(null, true); }
    }

    function renderFilterOptions(payload) {
      payload = payload || {};
      var years = Array.isArray(payload.years) ? payload.years : [];
      var departments = Array.isArray(payload.departments) ? payload.departments : [];
      var $actions = $('#motorVehicleDashboardTable_wrapper .gso-mv-dt-actions');
      if (!$actions.length || $('#mvVehicleFilters').length) { return; }

      var $filters = $('<div class="gso-mv-filters" id="mvVehicleFilters"></div>');
      var $yearSelect = $('<select class="form-control form-control-sm" id="mvYearAcquiredFilter" aria-label="Filter by year acquired"></select>');
      var $departmentSelect = $('<select class="form-control form-control-sm" id="mvDepartmentFilter" aria-label="Filter by department"></select>');

      $yearSelect.append($('<option>').val('').text('All Years'));
      years.forEach(function(year) {
        $yearSelect.append($('<option>').val(year).text(year));
      });

      $departmentSelect.append($('<option>').val('').text('All Departments'));
      departments.forEach(function(department) {
        $departmentSelect.append($('<option>').val(department).text(department));
      });

      $filters
        .append($('<label class="gso-mv-filter-label"></label>').append('<span>Year</span>').append($yearSelect))
        .append($('<label class="gso-mv-filter-label gso-mv-filter-dept"></label>').append('<span>Department</span>').append($departmentSelect));

      var $length = $actions.find('.dataTables_length');
      if ($length.length) {
        $filters.insertAfter($length);
      } else {
        $actions.append($filters);
      }

      $yearSelect.add($departmentSelect).on('change.mvDashboardFilters', function() {
        if (table) { table.ajax.reload(); }
      });
    }

    function loadFilterOptions() {
      $.ajax({
        url: '../auth/auth.php',
        type: 'GET',
        dataType: 'json',
        cache: false,
        data: { motor_vehicle_dashboard: 'filters', _ts: Date.now() },
        success: function(resp) {
          if (!resp || resp.status !== 200 || !resp.data) { return; }
          renderFilterOptions(resp.data);
        }
      });
    }

    function initDatePicker() {
      var $input = $('#mv_date_acquired');
      if (!$input.length || !$.fn.datepicker || $input.data('mvDatePickerInit')) { return; }

      $input.data('mvDatePickerInit', true);
      $input.datepicker({
        autoclose: true,
        format: 'yyyy-mm-dd',
        todayHighlight: true,
        orientation: 'bottom auto',
        container: '#motorVehicleModal'
      });

      $('#mv_date_acquired_picker .input-group-text')
        .off('click.mvDashboardDate')
        .on('click.mvDashboardDate', function() {
          $input.datepicker('show');
        });

      $input
        .off('changeDate.mvDashboardDate input.mvDashboardDate change.mvDashboardDate')
        .on('changeDate.mvDashboardDate input.mvDashboardDate change.mvDashboardDate', syncYearModelFromDate);
    }

    function yearFromDateAcquired(value) {
      var match = String(value || '').trim().match(/^(\d{4})/);
      return match ? match[1] : '';
    }

    function syncYearModelFromDate() {
      $('#mv_year_model').val(yearFromDateAcquired($('#mv_date_acquired').val()));
    }

    function setDateAcquired(value) {
      var text = String(value == null ? '' : value).trim();
      var $input = $('#mv_date_acquired');

      if ($.fn.datepicker && $input.data('datepicker')) {
        if (/^\d{4}-\d{2}-\d{2}$/.test(text)) {
          $input.datepicker('update', text);
        } else {
          $input.datepicker('clearDates');
          $input.val(text);
        }
        syncYearModelFromDate();
        return;
      }

      $input.val(text);
      syncYearModelFromDate();
    }

    function initTable() {
      if (!$('#motorVehicleDashboardTable').length || !$.fn.DataTable) { return; }
      if ($.fn.DataTable.isDataTable('#motorVehicleDashboardTable')) {
        table = $('#motorVehicleDashboardTable').DataTable();
        return;
      }

      table = $('#motorVehicleDashboardTable').DataTable({
        responsive: true,
        lengthChange: true,
        lengthMenu: [10, 25, 50, 100],
        pageLength: 25,
        autoWidth: false,
        processing: true,
        serverSide: true,
        ajax: {
          url: '../auth/auth.php',
          type: 'POST',
          data: function(data) {
            data.motor_vehicle_dashboard = 'table';
            data.mv_year_acquired = $('#mvYearAcquiredFilter').val() || '';
            data.mv_department = $('#mvDepartmentFilter').val() || '';
            data.mv_scope = currentScope;
          }
        },
        columns: [
          { data: 'brand_model', render: function(data) { return displayValue(data); } },
          { data: 'year_acquired', render: function(data) { return displayValue(data); } },
          { data: 'chassis_no', render: function(data) { return displayValue(data); } },
          { data: 'engine_no', render: function(data) { return displayValue(data); } },
          { data: 'plate_no', render: function(data) { return displayValue(data); } },
          { data: 'department_name', render: function(data) { return displayValue(data); } },
          { data: 'end_user', render: function(data) { return displayValue(data); } },
          { data: 'registration_schedule', orderable: false, searchable: false, render: function(data) { return displayValue(data); } },
          { data: 'registration_status', orderable: false, searchable: false, render: renderStatus },
          {
            data: null,
            orderable: false,
            searchable: false,
            className: 'text-center',
            render: function(data, type, row) {
              var sourceTable = esc(row && row.source_table ? row.source_table : '');
              var sourceId = Number(row && row.source_id ? row.source_id : 0) || 0;
              return '<button type="button" class="btn btn-xs btn-outline-primary mv-edit-btn" ' +
                'data-source-table="' + sourceTable + '" data-source-id="' + sourceId + '" title="View / Edit">' +
                '<i class="fas fa-edit"></i>' +
                '</button>';
            }
          }
        ],
        order: [[5, 'asc'], [6, 'asc']],
        dom: "<'row gso-mv-dt-toolbar align-items-center mb-2'<'col-sm-12 col-lg-8 gso-mv-dt-actions'B l><'col-sm-12 col-lg-4 gso-mv-dt-search'f>>" +
             "<'row'<'col-sm-12'tr>>" +
             "<'row'<'col-sm-12 col-md-5'i><'col-sm-12 col-md-7'p>>",
        initComplete: function() {
          loadFilterOptions();
        },
        buttons: [
          {
            extend: 'excel',
            orientation: 'landscape',
            pageSize: 'LEGAL',
            title: 'Motor Vehicle Dashboard',
            exportOptions: {
              columns: [0, 1, 2, 3, 4, 5, 6, 7, 8],
              format: {
                body: function(data) { return $('<div>').html(data).text().replace(/\s+/g, ' ').trim(); }
              }
            }
          },
          {
            extend: 'print',
            orientation: 'landscape',
            pageSize: 'LEGAL',
            title: 'Motor Vehicle Dashboard',
            exportOptions: { columns: [0, 1, 2, 3, 4, 5, 6, 7, 8] }
          }
        ]
      });
    }

    function field(id, value) {
      $(id).val(value == null ? '' : String(value));
    }

    function fillForm(data) {
      data = data || {};
      field('#mv_source_table', data.source_table);
      field('#mv_source_id', data.source_id);
      field('#mv_brand_model', data.brand_model);
      field('#mv_description', data.description);
      field('#mv_property_number', data.property_number);
      setDateAcquired(data.date_acquired);
      field('#mv_chassis_no', data.chassis_no);
      field('#mv_engine_no', data.engine_no);
      field('#mv_plate_no', data.plate_no);
      field('#mv_color', data.color);
      field('#mv_file', data.mv_file);
      field('#mv_conduction_sticker', data.conduction_sticker);
      field('#mv_vehicle_usage', data.vehicle_usage);
      field('#mv_capacity', data.capacity);
      if (!$('#mv_year_model').val()) { field('#mv_year_model', data.year_model); }
      field('#mv_cr_number', data.cr_number);
      field('#mv_or_number', data.or_number);
      field('#mv_supplier', data.supplier);
      field('#mv_amount', formatAmount(data.amount, true));
      field('#mv_po', data.po);
      field('#mv_obr', data.obr);
      field('#mv_pr', data.pr);
      field('#mv_jev', data.jev);
      field('#mv_remarks', data.remarks);
      $('#mv_coverage').val(data.coverage || 'None');
    }

    function openVehicleModal(sourceTable, sourceId, button) {
      sourceTable = String(sourceTable || '').trim();
      sourceId = Number(sourceId || 0) || 0;
      if (!sourceTable || sourceId <= 0) {
        notify('warning', 'Missing vehicle reference', 'Please reload the table and try again.');
        return;
      }

      var $btn = button ? $(button) : $();
      var originalHtml = $btn.length ? $btn.html() : '';
      if ($btn.length) {
        $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');
      }

      $.ajax({
        url: '../auth/auth.php',
        type: 'GET',
        dataType: 'json',
        cache: false,
        data: {
          motor_vehicle_dashboard: 'detail',
          source_table: sourceTable,
          source_id: sourceId,
          _ts: Date.now()
        },
        success: function(resp) {
          if (!resp || resp.status !== 200 || !resp.data) {
            notify('error', 'Unable to load vehicle', (resp && resp.message) ? resp.message : 'Please try again.');
            return;
          }
          $('#motorVehicleForm').get(0).reset();
          fillForm(resp.data);
          $('#motorVehicleModal').modal('show');
        },
        error: function() {
          notify('error', 'Server error', 'Unable to load motor vehicle details.');
        },
        complete: function() {
          if ($btn.length) {
            $btn.prop('disabled', false).html(originalHtml);
          }
        }
      });
    }

    function saveVehicle() {
      var form = $('#motorVehicleForm').get(0);
      if (!form) { return; }

      var $btn = $('#motorVehicleSaveBtn');
      var originalHtml = $btn.html();
      syncYearModelFromDate();
      var data = $('#motorVehicleForm').serializeArray();
      data.push({ name: 'motor_vehicle_dashboard', value: 'save' });

      $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>&nbsp;<span class="btn-text">Updating...</span>');

      $.ajax({
        url: '../auth/auth.php',
        type: 'POST',
        dataType: 'json',
        data: data,
        success: function(resp) {
          if (!resp || resp.status !== 200) {
            notify('error', 'Unable to update', (resp && resp.message) ? resp.message : 'Please review the form and try again.');
            return;
          }

          $('#motorVehicleModal').modal('hide');
          loadMetrics();
          if (table) { table.ajax.reload(null, false); }

          if (window.Swal && Swal.fire) {
            Swal.fire({ position: 'center', icon: 'success', title: resp.message || 'Updated successfully.', showConfirmButton: false, timer: 1500 });
          } else {
            alert(resp.message || 'Updated successfully.');
          }
        },
        error: function(xhr) {
          var message = 'Unable to update motor vehicle details.';
          if (xhr && xhr.responseText) {
            try {
              var parsed = JSON.parse(xhr.responseText);
              if (parsed && parsed.message) { message = parsed.message; }
            } catch (e) {}
          }
          notify('error', 'Server error', message);
        },
        complete: function() {
          $btn.prop('disabled', false).html(originalHtml);
        }
      });
    }

    function bindEvents() {
      $(document)
        .off('click.mvDashboard', '.mv-edit-btn')
        .on('click.mvDashboard', '.mv-edit-btn', function() {
          openVehicleModal($(this).attr('data-source-table'), $(this).attr('data-source-id'), this);
        })
        .off('click.mvDashboardScope', '.gso-mv-scope')
        .on('click.mvDashboardScope', '.gso-mv-scope', function(e) {
          e.preventDefault();
          setScope($(this).attr('data-scope'));
        });

      $('#motorVehicleForm')
        .off('submit.mvDashboard')
        .on('submit.mvDashboard', function(e) {
          e.preventDefault();
          saveVehicle();
        });

      $(document)
        .off('input.mvDashboardAmount', '#mv_amount')
        .on('input.mvDashboardAmount', '#mv_amount', function() {
          var cursorAtEnd = this.selectionStart === this.value.length;
          this.value = formatAmount(this.value);
          if (cursorAtEnd) {
            this.setSelectionRange(this.value.length, this.value.length);
          }
        })
        .off('blur.mvDashboardAmount', '#mv_amount')
        .on('blur.mvDashboardAmount', '#mv_amount', function() {
          this.value = formatAmount(this.value, true);
        });
    }

    function init() {
      if (!$('#motorVehicleDashboard').length) { return; }
      initDatePicker();
      bindEvents();
      updateScopeUi();
      loadMetrics();
      initTable();
    }

    return { init: init };
  })();

  $(function(){
    try {
      if (window.GSO && window.GSO.MotorVehicleDashboard) { window.GSO.MotorVehicleDashboard.init(); }
    } catch (e) {}
  });

  window.GSO.MotorVehicleStatistics = window.GSO.MotorVehicleStatistics || (function(){
    var charts = {};
    var statLabels = {
      registered_vehicles: 'Registered',
      for_registration: 'For Registration',
      insured_vehicles: 'Insured',
      serviceable_vehicles: 'Serviceable',
      unserviceable_vehicles: 'Unserviceable',
      new_motor_vehicles: 'New Motor Vehicles',
      needs_details: 'Needs Details'
    };

    function esc(value) {
      return $('<div>').text(String(value == null ? '' : value)).html();
    }

    function numberText(value) {
      var number = Number(value || 0);
      if (!isFinite(number)) { number = 0; }
      return number.toLocaleString('en-US');
    }

    function setStat(key, value) {
      $('[data-mv-stat="' + key + '"]').text(numberText(value));
    }

    function palette(i) {
      var colors = ['#007bff', '#28a745', '#ffc107', '#dc3545', '#6f42c1', '#17a2b8', '#fd7e14', '#6c757d'];
      return colors[i % colors.length];
    }

    function destroyChart(name) {
      try {
        if (charts[name] && typeof charts[name].destroy === 'function') {
          charts[name].destroy();
        }
      } catch (e) {}
      charts[name] = null;
    }

    function statRows(cards) {
      cards = cards || {};
      return Object.keys(statLabels).map(function(key, index) {
        return {
          key: key,
          label: statLabels[key],
          count: Number(cards[key] || 0) || 0,
          color: palette(index)
        };
      });
    }

    function makeChart(name, selector, type, labels, data, colors) {
      if (!window.Chart) { return; }
      var canvas = $(selector).get(0);
      if (!canvas) { return; }
      destroyChart(name);
      charts[name] = new Chart(canvas.getContext('2d'), {
        type: type,
        data: {
          labels: labels || [],
          datasets: [{
            label: 'Vehicles',
            data: data || [],
            backgroundColor: colors || [],
            borderColor: colors || [],
            borderWidth: 1
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          legend: { display: type !== 'horizontalBar', position: 'bottom' },
          scales: type === 'horizontalBar' ? {
            xAxes: [{ ticks: { beginAtZero: true, precision: 0 } }],
            yAxes: [{ ticks: { autoSkip: false } }]
          } : {}
        }
      });
    }

    function renderTotals(rows, total) {
      var $wrap = $('#mvStatTotals');
      if (!$wrap.length) { return; }
      $wrap.empty();

      if (!rows || !rows.length) {
        $wrap.append('<div class="text-muted" style="font-size: 13px;">No statistics to display.</div>');
        return;
      }

      var $table = $('<table class="table table-sm table-bordered mb-0"></table>');
      var $thead = $('<thead class="thead-light"></thead>');
      $thead.append('<tr><th>Statistic</th><th class="text-right" style="width: 90px;">Count</th></tr>');
      $table.append($thead);

      var $tbody = $('<tbody></tbody>');
      rows.forEach(function(row) {
        var $tr = $('<tr></tr>');
        $tr.append($('<td></td>').text(row.label));
        $tr.append($('<td class="text-right"></td>').text(numberText(row.count)));
        $tbody.append($tr);
      });
      $table.append($tbody);

      var $tfoot = $('<tfoot></tfoot>');
      $tfoot.append('<tr><th class="text-right">Total Vehicles</th><th class="text-right">' + numberText(total) + '</th></tr>');
      $table.append($tfoot);
      $wrap.append($table);
    }

    function renderFunds(rows) {
      var $body = $('#mvFundStatsBody');
      if (!$body.length) { return; }
      $body.empty();

      if (!rows || !rows.length) {
        $body.append('<tr><td colspan="4" class="text-center text-muted">No data available</td></tr>');
        return;
      }

      rows.forEach(function(row) {
        $body.append(
          '<tr>' +
            '<td>' + esc(row.fund_name || '') + '</td>' +
            '<td class="text-center">' + numberText(row.serviceable) + '</td>' +
            '<td class="text-center">' + numberText(row.unserviceable) + '</td>' +
            '<td class="text-center font-weight-bold">' + numberText(row.total) + '</td>' +
          '</tr>'
        );
      });
    }

    function renderSummary(cards) {
      var total = Number(cards.total_vehicles || 0) || 0;
      var registered = Number(cards.registered_vehicles || 0) || 0;
      var due = Number(cards.for_registration || 0) || 0;
      var needs = Number(cards.needs_details || 0) || 0;
      var message = total > 0
        ? numberText(total) + ' vehicle assets tracked. ' + numberText(registered) + ' registered, ' + numberText(due) + ' due this month, ' + numberText(needs) + ' need details.'
        : 'No motor vehicle assets found.';
      $('#mvStatsSummary').text(message);
    }

    function render(payload) {
      payload = payload || {};
      var cards = payload.cards || {};
      var rows = statRows(cards);
      var total = Number(cards.total_vehicles || 0) || 0;

      Object.keys(cards).forEach(function(key) {
        setStat(key, cards[key]);
      });

      $('#mvStatsAsOf').text(payload.as_of ? 'As of ' + payload.as_of : '');
      renderFunds(Array.isArray(payload.funds) ? payload.funds : []);
      renderTotals(rows, total);
      renderSummary(cards);

      var chartsData = payload.charts || {};
      var breakdown = chartsData.breakdown || {};
      var labels = Array.isArray(breakdown.labels) ? breakdown.labels : rows.map(function(row) { return row.label; });
      var data = Array.isArray(breakdown.data) ? breakdown.data : rows.map(function(row) { return row.count; });
      var colors = rows.map(function(row) { return row.color; });

      makeChart('registration', '#mvRegistrationChart', 'horizontalBar', labels, data, colors);
      makeChart('condition', '#mvConditionChart', 'doughnut', chartsData.condition && chartsData.condition.labels, chartsData.condition && chartsData.condition.data, ['#28a745', '#dc3545']);
    }

    function load() {
      $('#mvStatsSummary').text('Loading...');
      $.ajax({
        url: '../auth/auth.php',
        type: 'GET',
        dataType: 'json',
        cache: false,
        data: { motor_vehicle_dashboard: 'statistics', _ts: Date.now() },
        success: function(resp) {
          if (!resp || resp.status !== 200 || !resp.data) {
            $('#mvStatsSummary').text('Unable to load statistics.');
            return;
          }
          render(resp.data);
        },
        error: function() {
          $('#mvFundStatsBody').html('<tr><td colspan="4" class="text-center text-danger">Unable to load statistics</td></tr>');
          $('#mvStatsSummary').text('Unable to load statistics.');
        }
      });
    }

    function init() {
      if (!$('#motorVehicleStatistics').length) { return; }
      load();
    }

    return { init: init };
  })();

  $(function(){
    try {
      if (window.GSO && window.GSO.MotorVehicleStatistics) { window.GSO.MotorVehicleStatistics.init(); }
    } catch (e) {}
  });

$(function(){
  var $page = $('#fundInventoryPage');
  var $tableEl = $('#FundInventoryTable');

  if (!$page.length || !$tableEl.length) {
    return;
  }

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

  var fundKey = String($page.data('fund-key') || '').trim().toLowerCase();
  var selectedAssetClass = '';
  var selectedEndUser = '';
  var selectedParIcs = '';
  var selectedFundRows = {};

  function getSelectedFundRows() {
    return Object.keys(selectedFundRows).map(function(key) {
      return selectedFundRows[key];
    });
  }

  function syncFundSelectionUI() {
    var $rows = $('#FundInventoryTable tbody input.row-select');
    var totalRows = 0;
    var checkedRows = 0;

    $rows.each(function() {
      var id = String($(this).data('fund-id') || '').trim();
      if (!id) return;
      totalRows += 1;
      var isChecked = !!selectedFundRows[id];
      $(this).prop('checked', isChecked);
      if (isChecked) checkedRows += 1;
    });

    $('#selectAllFundInventory')
      .prop('checked', totalRows > 0 && checkedRows === totalRows)
      .prop('indeterminate', checkedRows > 0 && checkedRows < totalRows);

    $('#bulkFundTransferBtn').prop('disabled', getSelectedFundRows().length === 0);
  }

  function hasFocusedExportFilter(dt) {
    return (selectedAssetClass || '').trim() !== '' ||
      (selectedEndUser || '').trim() !== '' ||
      (selectedParIcs || '').trim() !== '' ||
      (dt.search() || '').trim() !== '';
  }

  function withAllRows(dt, callback) {
    if (!hasFocusedExportFilter(dt)) {
      callback();
      return;
    }

    var previousLength = dt.page.len();
    if (previousLength === -1) {
      callback();
      return;
    }

    dt.one('draw', function() {
      callback();
      setTimeout(function() {
        try { dt.page.len(previousLength).draw(false); } catch (_) {}
      }, 400);
    });

    dt.page.len(-1).draw();
  }

  var table = $tableEl.DataTable({
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
      {
        data: 'fund_id',
        orderable: false,
        searchable: false,
        className: 'text-center align-middle select-checkbox-column',
        render: function(data, type, row) {
          var fundId = escHtml(data || '');
          var propertyNumber = escHtml(row.par_number || '');
          return '<input type="checkbox" class="row-select"' +
            ' value="' + propertyNumber + '"' +
            ' data-fund-id="' + fundId + '"' +
            ' data-item="' + escHtml(row.item || '') + '"' +
            ' data-user="' + escHtml(row.emp_name || '') + '"' +
            ' data-cat="' + escHtml(row.category || '') + '"' +
            ' data-property-number="' + propertyNumber + '"' +
            ' data-dept-id="' + escHtml(row.current_dept_id || '') + '"' +
            ' data-dept-name="' + escHtml(row.current_dept_name || '') + '"' +
            ' aria-label="Select property ' + propertyNumber + '">';
        }
      },
      {
        data: null,
        orderable: false,
        searchable: false,
        className: 'text-center no-print',
        render: function(data, type, row) {
          var rowId = escHtml(row.row_id || row.fund_id || '');
          var po = escHtml(row.purchase_order || '');
          return ''
            + '<button type="button" class="btn btn-secondary btn-sm np-edit-btn"'
            + ' data-row-id="' + rowId + '"'
            + ' data-po="' + po + '"'
            + ' title="Edit">'
            + '<i class="fas fa-edit"></i>'
            + '</button>';
        }
      },
      { data: 'item', render: function(data) { return escHtml(data); } },
      { data: null, render: function(data, type, row) { return escHtml((row.model || '') + ' - ' + (row.description || '')); } },
      { data: 'serial_number', render: function(data) { return data ? escHtml(data) : "<span class='text-dark'>NULL</span>"; } },
      { data: 'serial_number_2', render: function(data) { return data ? escHtml(data) : "<span class='text-dark'>NULL</span>"; } },
      { data: 'par_number', render: function(data) { return data ? escHtml(data) : "<span class='text-dark'>NULL</span>"; } },
      { data: 'current_dept_name', render: function(data) { return data ? escHtml(data) : "<span class='text-dark'>NULL</span>"; } },
      { data: 'emp_name', render: function(data) { return escHtml(data); } },
      { data: 'model', visible: false, render: function(data) { return escHtml(data); } },
      { data: 'description', visible: false, render: function(data) { return escHtml(data); } },
      { data: 'date_aquired', visible: false, render: function(data) { return escHtml(data); } },
      { data: 'unit', visible: false, render: function(data) { return escHtml(data); } }
    ],
    columnDefs: [
      { targets: 0, orderable: false, searchable: false, className: 'select-checkbox-column' },
      { targets: 1, orderable: false, searchable: false, className: 'no-print' },
      { targets: [9, 10, 11, 12], visible: false, searchable: false }
    ],
    order: [[2, 'asc']],
    buttons: [
      {
        extend: 'excel',
        orientation: 'landscape',
        pageSize: 'LEGAL',
        title: function() { return $('#reportTitle').text() || 'Inventory Report'; },
        exportOptions: {
          columns: [2, 9, 10, 12, 4, 5, 6, 11, 7, 8],
          format: {
            header: function(data, columnIdx) {
              var headers = {
                2: 'ITEM',
                9: 'MODEL',
                10: 'DESCRIPTION',
                12: 'UNIT',
                4: 'SERIAL NUMBER PRIMARY',
                5: 'SERIAL NUMBER SECONDARY',
                6: 'PROPERTY NUMBER',
                11: 'YEAR ACQUIRED',
                7: 'DEPARTMENT',
                8: 'END USER'
              };
              return headers[columnIdx] || data;
            },
            body: function(data) { return $('<div>').html(data).text(); }
          },
          customizeData: summarizeExcelInventoryRows
        },
        action: function(e, dt, button, config) {
          var self = this;
          withAllRows(dt, function() {
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
        exportOptions: { columns: ':visible:not(.select-checkbox-column):not(.no-print)' },
        action: function(e, dt, button, config) {
          var self = this;
          withAllRows(dt, function() {
            if ($.fn && $.fn.dataTable && $.fn.dataTable.ext && $.fn.dataTable.ext.buttons && $.fn.dataTable.ext.buttons.print && $.fn.dataTable.ext.buttons.print.action) {
              $.fn.dataTable.ext.buttons.print.action.call(self, e, dt, button, config);
            }
          });
        }
      },
      {
        text: 'Transfer to Records',
        className: 'btn btn-secondary',
        attr: { id: 'bulkFundTransferBtn', disabled: true },
        action: function() {
          var selectedRows = getSelectedFundRows();
          if (!selectedRows.length) {
            if (window.Swal && Swal.fire) {
              Swal.fire({ icon: 'warning', title: 'Please select at least one item.' });
            } else {
              alert('Please select at least one item.');
            }
            return;
          }

          var missingDeptRows = selectedRows.filter(function(row) {
            return !String(row.current_dept_id || '').trim();
          });
          if (missingDeptRows.length) {
            var preview = missingDeptRows.slice(0, 5).map(function(row) {
              return String(row.par_number || row.fund_id || '').trim();
            }).filter(Boolean).join(', ');
            var message = 'Some selected items do not have a department assigned.';
            if (preview) {
              message += ' Affected: ' + preview;
            }
            if (window.Swal && Swal.fire) {
              Swal.fire({ icon: 'error', title: message });
            } else {
              alert(message);
            }
            return;
          }

          function doTransfer() {
            var $btn = $('#bulkFundTransferBtn');
            $btn.prop('disabled', true).addClass('disabled');

            $.ajax({
              url: '../auth/auth.php',
              type: 'POST',
              dataType: 'json',
              data: {
                bulkTransferFundInventory: 1,
                fund_bulk: fundKey,
                selected_fund_ids: JSON.stringify(selectedRows.map(function(row) { return row.fund_id; }))
              },
              success: function(res) {
                if (!(res && res.status == 200)) {
                  toastr.error(res && res.message ? res.message : 'Transfer failed');
                  return;
                }

                if (window.Swal && Swal.fire) {
                  Swal.fire({ icon: 'success', title: res.message || 'Transferred to records successfully.' });
                }

                selectedFundRows = {};
                $('#selectAllFundInventory').prop('checked', false).prop('indeterminate', false);
                $('#bulkFundTransferBtn').prop('disabled', true);
                table.ajax.reload(null, false);
                populateFiltersAllRecords();
              },
              error: function() {
                toastr.error('Server error performing transfer');
              },
              complete: function() {
                syncFundSelectionUI();
                $btn.removeClass('disabled');
              }
            });
          }

          if (window.Swal && Swal.fire) {
            Swal.fire({
              icon: 'question',
              title: 'Transfer selected items to records?',
              text: 'This will move the selected items into their assigned department records.',
              showCancelButton: true,
              confirmButtonText: 'Yes, transfer',
              cancelButtonText: 'Cancel'
            }).then(function(result) {
              if (result && result.isConfirmed) {
                doTransfer();
              } else {
                syncFundSelectionUI();
              }
            });
          } else if (confirm('Transfer selected items to records?')) {
            doTransfer();
          }
        }
      },
      {
        extend: 'pdfHtml5',
        orientation: 'landscape',
        pageSize: 'LEGAL',
        title: 'INVENTORY REPORT',
        exportOptions: { columns: ':visible:not(.select-checkbox-column):not(.no-print)' },
        action: function(e, dt, button, config) {
          var self = this;
          withAllRows(dt, function() {
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

  assetClassSelect.on('change', function() {
    selectedAssetClass = $(this).val() || '';
    table.ajax.reload(null, true);
  });

  endUserSelect.on('change', function() {
    selectedEndUser = $(this).val() || '';
    table.ajax.reload(null, true);
  });

  parIcsSelect.on('change', function() {
    selectedParIcs = ($(this).val() || '').toUpperCase();
    table.ajax.reload(null, true);
  });

  table.one('draw', populateFiltersAllRecords);
  table.on('draw', syncFundSelectionUI);

  $(document).on('change', '#selectAllFundInventory', function() {
    var checked = this.checked;

    $('#FundInventoryTable tbody input.row-select').each(function() {
      var id = String($(this).data('fund-id') || '').trim();
      if (!id) return;

      if (checked) {
        selectedFundRows[id] = {
          fund_id: id,
          par_number: $(this).data('property-number') || '',
          item: $(this).data('item') || '',
          emp_name: $(this).data('user') || '',
          category: $(this).data('cat') || '',
          current_dept_id: $(this).data('dept-id') || '',
          current_dept_name: $(this).data('dept-name') || ''
        };
      } else {
        delete selectedFundRows[id];
      }
    });

    syncFundSelectionUI();
  });

  $(document).on('change.fundSelectSync', '#FundInventoryTable tbody input.row-select', function() {
    var id = String($(this).data('fund-id') || '').trim();
    if (!id) return;

    if (this.checked) {
      selectedFundRows[id] = {
        fund_id: id,
        par_number: $(this).data('property-number') || '',
        item: $(this).data('item') || '',
        emp_name: $(this).data('user') || '',
        category: $(this).data('cat') || '',
        current_dept_id: $(this).data('dept-id') || '',
        current_dept_name: $(this).data('dept-name') || ''
      };
    } else {
      delete selectedFundRows[id];
    }

    syncFundSelectionUI();
  });

  setTimeout(function() {
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

});

window.GSO.ChangeLogLive = window.GSO.ChangeLogLive || (function(){
  var timer = null;
  var endpoint = '../auth/auth.php';

  function hasPage(){
    return $('.gso-realtime-changelog[data-changelog-live="1"]').length > 0;
  }

  function escapeHtml(value){
    return $('<div>').text(value == null ? '' : String(value)).html();
  }

  function renderVersionCard(version){
    version = version || {};

    $('#gsoChangeLogVersion').html(
      '<div class="gso-live-cardhead">' +
        '<h2 class="gso-live-cardtitle">Current Version</h2>' +
      '</div>' +
      '<div class="gso-live-version">' + escapeHtml(version.full || 'Unavailable') + '</div>'
    );
  }

  function renderComments(items){
    var commits = Array.isArray(items) ? items : [];
    var html = '';

    if (!commits.length) {
      html = '<p class="gso-changelog-empty">No comments yet.</p>';
    } else {
      commits.forEach(function(commit){
        html += '' +
          '<article class="gso-simple-log-row">' +
            '<div class="gso-simple-log-version">' + escapeHtml(commit.patch_version || 'Pending') + '</div>' +
            '<div class="gso-simple-log-comment">' + escapeHtml(commit.subject || commit.raw_subject || 'Updated project files') + '</div>' +
          '</article>';
      });
    }

    $('#gsoChangeLogComments').html(html);
  }

  function renderPayload(data){
    renderVersionCard(data.version || {});
    renderComments(data.recent_comments || []);
  }

  function poll(){
    $.ajax({
      url: endpoint,
      type: 'GET',
      cache: false,
      dataType: 'json',
      data: { fetch_realtime_changelog: 1, _ts: Date.now() },
      success: function(resp){
        if (!(resp && resp.status === 200 && resp.data)) {
          return;
        }
        renderPayload(resp.data);
      }
    });
  }

  function init(){
    if (!hasPage() || timer) { return; }
    poll();
    timer = setInterval(function(){
      if (!document.hidden) {
        poll();
      }
    }, 10000);

    document.addEventListener('visibilitychange', function(){
      if (!document.hidden) {
        poll();
      }
    });
  }

  return { init: init };
})();

window.GSO.LiveVersion = window.GSO.LiveVersion || (function(){
  var timer = null;

  function roots(){
    return $('[data-live-version-root="1"]');
  }

  function hasRoot(){
    return roots().length > 0;
  }

  function endpoint(){
    var $root = roots().first();
    var configured = $root.length ? String($root.data('versionEndpoint') || '').trim() : '';
    if (configured && /^\/[^.].*\/auth\/auth\.php$/i.test(configured)) {
      return configured;
    }

    var parts = String((window.location && window.location.pathname) || '').split('/').filter(Boolean);
    if (!parts.length) {
      return configured;
    }

    return '/' + parts[0] + '/auth/auth.php';
  }

  function apply(payload){
    var data = payload || {};
    roots().each(function(){
      var $root = $(this);
      $root.find('[data-version-name]').text(data.name || 'P.I.M.S');
      $root.find('[data-version-full]').text(data.full || '0.0.0-local');

      var meta = '';
      if (data.source === 'env') {
        meta = '(override)';
      } else if (data.meta_label) {
        meta = '(' + data.meta_label + ')';
      }

      var $meta = $root.find('[data-version-meta]');
      if (!$meta.length) {
        return;
      }

      if (meta) {
        $meta.text(meta).removeClass('d-none');
      } else {
        $meta.text('').addClass('d-none');
      }
    });
  }

  function poll(){
    var url = endpoint();
    if (!url) { return; }

    var query = url + (url.indexOf('?') === -1 ? '?' : '&') + 'fetch_live_version=1&_ts=' + Date.now();
    window.fetch(query, { credentials: 'same-origin' })
      .then(function(resp){ return resp.ok ? resp.json() : null; })
      .then(function(resp){
        if (resp && resp.status === 200 && resp.data) {
          apply(resp.data);
        }
      })
      .catch(function(){});
  }

  function init(){
    if (!hasRoot() || timer) { return; }
    poll();
    timer = setInterval(function(){
      if (!document.hidden) {
        poll();
      }
    }, 5000);

    document.addEventListener('visibilitychange', function(){
      if (!document.hidden) {
        poll();
      }
    });
  }

  return { init: init };
})();

// Global init
$(function(){
  try {
    if (window.GSO && window.GSO.LoginLockout) { window.GSO.LoginLockout.start(); }
    if (window.currentUserRole && window.GSO && window.GSO.SessionGuard) { window.GSO.SessionGuard.init(); }
    if (window.currentUserRole && window.GSO && window.GSO.AdminPresencePanel) { window.GSO.AdminPresencePanel.start(); }
    if (window.GSO && window.GSO.ChangeLogLive) { window.GSO.ChangeLogLive.init(); }
    if (window.GSO && window.GSO.LiveVersion) { window.GSO.LiveVersion.init(); }
  } catch (e) {}
});
