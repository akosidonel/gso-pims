<?php 
include_once('../config/session.php');
include('../config/check_session.php');
include_once('../config/auth_helpers.php');

check_admin_role_dynamic_redirect();

if (!isset($_SESSION['alogin'])) {
    header('Location:../index.php');
    exit();
} else {
    include('../include/header.php'); // Header
    include('../include/navbar.php'); // Navbar
    include('../include/sidebar.php'); // Sidebar

?>


<div id="destroy"></div>

<div class="content-wrapper gso-dashboard">
        <!-- Main content -->
        <section class="content">
            <div class="container-fluid">

            <?php
                    $aid = $_SESSION['alogin'];
                    $admin = null;
                    $stmtAdmin = $conn->prepare("SELECT first_name, last_name, last_session, ip FROM administrator WHERE admin_id = ? LIMIT 1");
                    $stmtAdmin->bind_param("s", $aid);
                    $stmtAdmin->execute();
                    $adminResult = $stmtAdmin->get_result();
                    if ($adminResult && $adminResult->num_rows > 0) {
                            $admin = $adminResult->fetch_assoc();
                    }
            ?>

            <div class="card gso-hero mb-4">
                <div class="card-body">
                    <div class="d-flex align-items-start align-items-md-center justify-content-between flex-wrap">
                        <div class="mb-3 mb-md-0">
                            <div class="gso-kicker">Administrator</div>
                            <div class="gso-title">Dashboard</div>
                        </div>

                        <div class="text-md-right">
                            <div class="gso-pill">
                                <i class="fa-solid fa-user-shield"></i>
                                <span>
                                    <?php if ($admin): ?>
                                        Hi, <?= htmlspecialchars(trim(($admin['first_name'] ?? '') . ' ' . ($admin['last_name'] ?? ''))); ?>
                                    <?php else: ?>
                                        Hi, Admin
                                    <?php endif; ?>
                                </span>
                            </div>
                            <div class="gso-meta">
                                <?php if ($admin && !empty($admin['last_session'])): ?>
                                    Last session: <?= date('F j, Y, g:i a', strtotime($admin['last_session'])); ?><br>
                                    IP: <?= htmlspecialchars((string)($admin['ip'] ?? '')); ?>
                                <?php else: ?>
                                    Welcome back.
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                </div>
            </div>

            <div class="d-flex align-items-end justify-content-between flex-wrap">
                <div>
                    <h5 class="gso-section-title">General Information</h5>
                    <div class="gso-section-subtitle">Recent activity and high-level totals</div>
                </div>
            </div>

            <div class="row">
                <div class="col-lg-6 mb-3">
                <div class="card gso-card">
                            <div class="card-header border-0">
                                <div class="d-flex justify-content-between align-items-center">
                                    <h3 class="card-title mb-0">Approved Clearance</h3>
                                    <a class="btn btn-sm btn-outline-success" href="../services/clearance-statistic.php">
                                        View reports <i class="fas fa-arrow-right ml-1"></i>
                                    </a>
                                </div>
                            </div>
                            <div class="card-body table-responsive p-0">  
                            <table class="table table-striped table-valign-middle gso-table mb-0">
                                    <thead>
                                    <tr>
                                        <th>Employee's name</th>
                                        <th>Date applied</th>
                                        <th>Purpose</th>
                                        <th>Status</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                    $stmt = $conn->prepare("
                                        SELECT e.emp_name, h.created_at, h.status, h.release_date AS date, c.clearance_name 
                                        FROM clearance_history AS h 
                                        JOIN employee AS e ON h.emp_id = e.emp_id 
                                        JOIN clearance_type AS c ON h.ctype_id = c.clearance_code 
                                        WHERE h.status = ? 
                                        ORDER BY h.created_at DESC 
                                        LIMIT 4
                                ");
                                $status = 1;
                                $stmt->bind_param("i", $status);
                                $stmt->execute();
                                $results = $stmt->get_result();

                                if ($results->num_rows > 0):
                                        foreach ($results as $result): ?>
                                                <tr>
                                                        <td><?= htmlspecialchars($result['emp_name']); ?></td>
                                                        <td><?= date('F j, Y, g:i a', strtotime($result['created_at'])); ?></td>
                                                        <td><?= htmlspecialchars($result['clearance_name']); ?></td>
                                                        <td>
                                                                <?= date('F j, Y, g:i a', strtotime($result['date'])); ?>
                                                                <small class="badge badge-success ml-1">
                                                                        <i class="fa-solid fa-thumbs-up"></i> Printed
                                                                </small>
                                                        </td>
                                                </tr>
                                        <?php endforeach;
                                else: ?>
                                        <tr class="text-center">
                                                <td colspan="4" class="py-4 text-muted">No approved clearances found.</td>
                                        </tr>
                                <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        </div><!--card -->
            
            <?php
        // Dashboard metrics are lazy-loaded via AJAX to speed up initial page render.
        // Reusable function for rendering info-boxes
        function renderInfoBox($link, $icon, $title, $metricKey, $description, $color) {
          $safeKey = htmlspecialchars($metricKey, ENT_QUOTES);
                    $safeTitle = htmlspecialchars($title);
                    $safeDesc = htmlspecialchars($description);
                    $safeLink = htmlspecialchars($link);

                    if (!empty($link)) {
                        return "
                            <div class='col-sm-6 mb-3'>
                                <a href='{$safeLink}' class='gso-stat-link' aria-label='More information about {$safeTitle}'>
                                    <div class='gso-stat-card'>
                                        <div class='gso-stat-icon'><i class='fa-solid {$icon}'></i></div>
                                        <div>
                                            <div class='gso-stat-title'>{$safeTitle}</div>
                                            <div class='gso-stat-value' data-metric='{$safeKey}'>...</div>
                                            <div class='gso-stat-note'>{$safeDesc}</div>
                                        </div>
                                        <div class='gso-stat-chevron'><i class='fas fa-chevron-right'></i></div>
                                    </div>
                                </a>
                            </div>";
                    }

                    return "
                        <div class='col-sm-6 mb-3'>
                            <div class='gso-stat-card is-disabled' role='group' aria-label='{$safeTitle} (coming soon)'>
                                <div class='gso-stat-icon'><i class='fa-solid {$icon}'></i></div>
                                <div>
                                    <div class='gso-stat-title'>{$safeTitle}</div>
                                    <div class='gso-stat-value' data-metric='{$safeKey}'>...</div>
                                    <div class='gso-stat-note'>{$safeDesc} • Coming soon</div>
                                </div>
                            </div>
                        </div>";
        }
        // Reusable function to render small-box components
        function renderSmallBox($icon, $title, $metricKey, $item, $color) {
          $safeKey = htmlspecialchars($metricKey, ENT_QUOTES);
                    $safeTitle = htmlspecialchars($title);
                    $safeItem = urlencode($item);
                    return "
                        <div class='col-lg-3 col-sm-6 mb-3'>
                            <a href='property-inventory.php?item={$safeItem}' class='gso-stat-link' aria-label='More information about {$safeTitle}'>
                                <div class='gso-stat-card'>
                                    <div class='gso-stat-icon'><i class='fa-solid {$icon}'></i></div>
                                    <div>
                                        <div class='gso-stat-title'>{$safeTitle}</div>
                                        <div class='gso-stat-value'><span data-metric='{$safeKey}'>0</span></div>
                                        <div class='gso-stat-note'>Units</div>
                                    </div>
                                    <div class='gso-stat-chevron'><i class='fas fa-chevron-right'></i></div>
                                </div>
                            </a>
                        </div>";
        }
?>
                            <div class="col-lg-6 mb-3">
                            <div class="row">
              <?php 
                //Render info-boxes
                echo renderInfoBox('general-fund-department.php', 'fa-cart-flatbed', 'Property Inventory', 'gftotal_currency', 'General Fund', 'secondary');
                echo renderInfoBox('add-infrastructure.php', 'fa-city', 'Infrastructure', 'infrastructure_gf_currency', 'General Fund', 'secondary');
                echo renderInfoBox('sef-institution.php', 'fa-boxes', 'Property Inventory', 'seftotal_currency', 'Special Education Fund', 'secondary');
                echo renderInfoBox('add-infrastructure.php', 'fa-university', 'Infrastructure', 'infrastructure_sef_currency', 'Special Education Fund', 'secondary'); 
                echo renderInfoBox('trust-fund-inventory.php', 'fa-hand-holding-dollar', 'Trust Fund', 'trust_fund_total_currency', 'Total Amount', 'secondary');
                echo renderInfoBox('donation-inventory.php', 'fa-gift', 'Donations', 'donation_total_currency', 'Total Amount', 'secondary');
                echo renderInfoBox('add-item.php', 'fa-file-invoice-dollar', 'New Purchase', 'new_purchase_total_currency', 'Total Amount', 'secondary');
                //Render Administrator and Land info-box for SYSTEM-ADMIN only
                if (hasRole(['SYSTEM-ADMIN'])):
                    echo renderInfoBox('admin-panel.php', 'fa-users', 'Administrator', 'admin_count', 'General Fund', 'secondary');
                    echo renderInfoBox('add-land.php', 'fa-map-marked-alt', 'Land', 'land_total_currency', 'Total Amount', 'secondary');
                endif;
              ?>
              </div>
          </div>
        </div> <!--row -->

        <!-- Equipment Section -->
        <h5 class="gso-section-title">Equipment</h5>
        <div class="gso-section-subtitle">Quick view of commonly tracked items</div>
        <div class="row">
        <?php
                // Render small-box components
            echo renderSmallBox('fa-computer', 'Desktop Computer', 'desktop_count', 'DESKTOP COMPUTER', 'info');
            echo renderSmallBox('fa-laptop', 'Laptop', 'laptop_count', 'LAPTOP', 'maroon');
            echo renderSmallBox('fa-fan', 'Airconditioner', 'aircon_count', 'AIRCONDITIONER', 'warning');
            echo renderSmallBox('fa-truck-front', 'Motor Vehicle', 'vehicle_count', 'MOTOR VEHICLE', 'danger');
            echo renderSmallBox('fa-print', 'Printer', 'printer_count', 'PRINTER', 'primary');
            echo renderSmallBox('fa-server', 'Server', 'server_count', 'SERVER', 'orange');
            echo renderSmallBox('fa-gears', 'Other Machinery and Equipment', 'machinery_count', 'OTHER MACHINERY AND EQUIPMENT', 'lightblue');
            echo renderSmallBox('fa-chair', 'Furniture N Fixtures', 'furniture_count', 'FURNITURE AND FIXTURES', 'purple'); ?>
        </div>
              <!-- /.row -->
              </div>
    </section>
</div>

<?php 
    include('../include/footer.php'); // Footer
    include('../include/script.php'); // Script
}
?>

<script>
    (function(){
        var prefersReducedMotion = false;
        try {
            prefersReducedMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        } catch (e) {}

        function parseNumberFromText(text){
            if (text == null) return null;
            var s = String(text);
            // keep digits, minus, decimal
            var cleaned = s.replace(/[^0-9.\-]/g, '');
            if (!cleaned || cleaned === '-' || cleaned === '.' || cleaned === '-.') return null;
            var n = Number(cleaned);
            return Number.isFinite(n) ? n : null;
        }

        function easeOutSpring(t){
            // Softer, more "liquid" spring-ish ease. Final value is snapped at the end.
            // t in [0,1]
            return 1 - (Math.exp(-4.5 * t) * Math.cos(6 * t));
        }

        function animateValue($el, toValue, opts){
            opts = opts || {};
            var duration = Number(opts.duration || 1800);
            var formatter = typeof opts.formatter === 'function' ? opts.formatter : function(v){ return String(v); };
            var decimals = Number.isFinite(opts.decimals) ? opts.decimals : 0;

            if (!$el || !$el.length) return;
            if (!Number.isFinite(toValue)) {
                $el.text(opts.fallbackText != null ? String(opts.fallbackText) : 'N/A');
                return;
            }

            if (prefersReducedMotion) {
                $el.text(formatter(decimals > 0 ? Number(toValue.toFixed(decimals)) : Math.round(toValue)));
                return;
            }

            // Prevent overlapping animations per element
            var existingAnim = $el.data('gsoAnim');
            if (existingAnim && existingAnim.cancel) existingAnim.cancel();

            var fromValue = parseNumberFromText($el.text());
            if (!Number.isFinite(fromValue)) fromValue = 0;
            var direction = (toValue >= fromValue) ? 1 : -1;

            var rafId = 0;
            var cancelled = false;
            var start = 0;

            function cancel(){
                cancelled = true;
                if (rafId) cancelAnimationFrame(rafId);
                $el.removeClass('gso-counting');
            }

            $el.data('gsoAnim', { cancel: cancel });

            // trigger a subtle "fluid" pulse while counting
            $el.removeClass('gso-counting');
            // force reflow so the animation can replay
            void $el[0].offsetWidth;
            $el.addClass('gso-counting');

            function step(ts){
                if (cancelled) return;
                if (!start) start = ts;
                var t = Math.min(1, (ts - start) / duration);
                var eased = easeOutSpring(t);
                if (eased < 0) eased = 0;
                if (eased > 1) eased = 1;
                var current = fromValue + (toValue - fromValue) * eased;

                var shown;
                if (decimals > 0) {
                    shown = Number(current.toFixed(decimals));
                } else {
                    // keep it monotonic (no bouncing digits)
                    shown = direction >= 0 ? Math.floor(current) : Math.ceil(current);
                }
                $el.text(formatter(shown));

                if (t < 1) {
                    rafId = requestAnimationFrame(step);
                } else {
                    // ensure final value is exact
                    var finalShown = decimals > 0 ? Number(toValue.toFixed(decimals)) : Math.round(toValue);
                    $el.text(formatter(finalShown));
                    $el.removeData('gsoAnim');
                    $el.removeClass('gso-counting');
                }
            }

            rafId = requestAnimationFrame(step);
        }

        function setMetric(key, value, opts){
            var $targets = $('[data-metric="' + key + '"]');
            if (!$targets.length) return;

            // If value is a string like 'N/A', just set text
            if (typeof value === 'string' && parseNumberFromText(value) == null) {
                $targets.text(value);
                return;
            }

            var num = (typeof value === 'number') ? value : parseNumberFromText(value);
            $targets.each(function(){
                animateValue($(this), Number(num), opts);
            });
        }

        function formatCurrency(num){
            try {
                return new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP', maximumFractionDigits: 2 }).format(Number(num || 0));
            } catch (e) {
                var n = Number(num || 0);
                return '₱ ' + n.toFixed(2);
            }
        }

        $.ajax({
            url: '../auth/fetch_dashboard_metrics.php',
            type: 'GET',
            dataType: 'json',
            success: function (resp) {
                if (!resp) return;
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
    })();
</script>