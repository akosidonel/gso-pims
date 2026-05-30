<?php 
include_once('../config/session.php');
include('../config/check_session.php');
include_once('../config/auth_helpers.php');
require_once('../auth/auth.php');

check_admin_role_dynamic_redirect();

if (!isset($_SESSION['alogin'])) {
    header('Location:../index.php');
    exit();
} else {
    $adminId = (string)$_SESSION['alogin'];
    $admin = gso_fetch_dashboard_admin_summary($conn, $adminId);
    $approvedClearances = gso_fetch_dashboard_approved_clearances($conn, 4);

    include('../include/header.php'); // Header
    include('../include/navbar.php'); // Navbar
    include('../include/sidebar.php'); // Sidebar

?>


<div id="destroy"></div>

<div class="content-wrapper gso-dashboard">
        <!-- Main content -->
        <section class="content">
            <div class="container-fluid">

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
                                        <?php if (!empty($approvedClearances)): ?>
                                        <?php foreach ($approvedClearances as $result): ?>
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
                                        <?php endforeach; ?>
                                <?php else: ?>
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
        function renderSmallBox($icon, $title, $metricKey, $item, $color, $link = '') {
          $safeKey = htmlspecialchars($metricKey, ENT_QUOTES);
                    $safeTitle = htmlspecialchars($title);
                    $safeItem = urlencode($item);
                    $safeLink = htmlspecialchars($link !== '' ? $link : "property-inventory.php?item={$safeItem}");
                    return "
                        <div class='col-lg-3 col-sm-6 mb-3'>
                            <a href='{$safeLink}' class='gso-stat-link' aria-label='More information about {$safeTitle}'>
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
            echo renderSmallBox('fa-truck-front', 'Motor Vehicle', 'vehicle_count', 'MOTOR VEHICLE', 'danger', 'motor-vehicle-dashboard.php');
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
