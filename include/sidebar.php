<!-- Main Sidebar Container -->
<aside class="main-sidebar sidebar-dark-success elevation-4">
    <?php
    // Get the current page
    $page = htmlspecialchars(substr($_SERVER['SCRIPT_NAME'], strrpos($_SERVER['SCRIPT_NAME'], "/") + 1));

    // Helper function to check roles
    function hasRole($roles) {
        return in_array($_SESSION['role'], $roles);
    }

    // Helper function to check active page
    function isActivePage($pages) {
        global $page;
        return in_array($page, $pages) ? 'active' : '';
    }
    ?>

    <!-- Sidebar -->
    <div class="sidebar">
        <!-- Sidebar user (optional) -->
        <div class="user-panel pb-3 mt-3 mb-2 d-flex">
            <div class="image">
                <img src="../admin/gso.png" class="img-circle elevation-2" alt="User Image">
            </div>
            <div class="info">
                <a href="#" class="d-block text-white"><b>General Service Office</b></a>
            </div>
        </div>

        <!-- Sidebar Menu -->
        <nav class="mt-3">
            <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu" data-accordion="false">
                <?php if (hasRole(['SYSTEM-ADMIN','GF/SEF-ADMIN','DISPOSAL-ADMIN'])): ?>
                    <li class="nav-item">
                        <a href="../admin/dashboard.php" class="nav-link <?= isActivePage(['dashboard.php', 'property-inventory.php', 'general-fund-ics-property.php', 'general-fund-department.php', 'general-fund-ics-department.php', 'return-item.php', 'general-fund-inventory.php', 'general-fund-par-transfer.php','sef-institution.php','sef-inventory.php','trust-fund-inventory.php','donation-inventory.php']) ?>">
                            <i class="nav-icon fa-solid fa-layer-group text-white"></i>
                            <p>Dashboard</p>
                        </a>
                    </li>
                <?php endif; ?>

                <?php if (hasRole(['SYSTEM-ADMIN','GF/SEF-ADMIN','DISPOSAL-ADMIN'])): ?>
                    <li class="nav-item">
                        <a href="../admin/motor-vehicle-dashboard.php" class="nav-link <?= isActivePage(['motor-vehicle-dashboard.php']) ?>">
                            <i class="nav-icon fa-solid fa-truck-front text-white"></i>
                            <p>Motor Vehicle</p>
                        </a>
                    </li>
                <?php endif; ?>

                  <?php if (hasRole(['SYSTEM-ADMIN','GF/SEF-ADMIN','DISPOSAL-ADMIN'])): ?>
                    <li class="nav-item">
                        <a href="#" class="nav-link <?= isActivePage(['add-item.php', 'add-infrastructure.php', 'add-land.php', 'new-purchase-items.php']) ?>">
                            <i class="fa-solid fas fa-truck nav-icon text-white"></i>
                            <p>Procurement Entry<i class="right fas fa-angle-left"></i></p>
                        </a>
                        <ul class="nav nav-treeview">
                            <li class="nav-item">
                                <a href="../admin/add-item.php" class="nav-link <?= isActivePage(['add-item.php', 'new-purchase-items.php']) ?>">
                                    <i class="nav-icon fa-solid fas fa-truck-loading"></i>
                                    <p>Equipment</p>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="../admin/add-infrastructure.php" class="nav-link <?= isActivePage(['add-infrastructure.php']) ?>">
                                    <i class="fa-solid fas fa-city nav-icon"></i>
                                    <p>Infrastructure</p>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="../admin/add-land.php" class="nav-link <?= isActivePage(['add-land.php']) ?>">
                                    <i class="fa-solid fa-map-location-dot nav-icon"></i>
                                    <p>Land</p>
                                </a>
                            </li>
                        </ul>
                    </li>
                <?php endif; ?>

                <?php if (hasRole(['SYSTEM-ADMIN','GF/SEF-ADMIN','DISPOSAL-ADMIN','CLEARANCE-ADMIN'])): ?>
                    <li class="nav-item">
                        <a href="#" class="nav-link <?= isActivePage(['employee.php', 'property-accountability.php', 'manage-employee.php']) ?>">
                            <i class="fa-solid fa-users nav-icon text-white"></i>
                            <p>Employee<i class="right fas fa-angle-left"></i></p>
                        </a>
                        <ul class="nav nav-treeview">
                            <?php if (hasRole(['SYSTEM-ADMIN','GF/SEF-ADMIN','DISPOSAL-ADMIN'])): ?>
                                <li class="nav-item">
                                    <a href="../admin/employee.php" class="nav-link <?= isActivePage(['employee.php', 'property-accountability.php']) ?>">
                                        <i class="nav-icon fa-solid fa-file-invoice"></i>
                                        <p>With Accountability</p>
                                    </a>
                                </li>
                            <?php endif; ?>
                            <li class="nav-item">
                                <a href="../admin/manage-employee.php" class="nav-link <?= isActivePage(['manage-employee.php']) ?>">
                                    <i class="fa-solid fa-file-pen nav-icon"></i>
                                    <p>Manage Employee</p>
                                </a>
                            </li>
                        </ul>
                    </li>
                <?php endif; ?>

                  <?php if (hasRole(['SYSTEM-ADMIN'])): ?>
                    <li class="nav-item">
                        <a href="../admin/department.php" class="nav-link <?= isActivePage(['department.php']) ?>">
                            <i class='fa-solid fa-landmark-alt nav-icon text-white'></i>
                            <p>Agencies</p>
                        </a>
                    </li>
                <?php endif; ?>
                <?php if (hasRole(['SYSTEM-ADMIN','CLEARANCE-ADMIN'])): ?>
                    <li class="nav-item">
                        <a href="#" class="nav-link <?= isActivePage(['clearance.php', 'clearance-statistic.php', 'clearance-type.php']) ?>">
                        <i class='fa-solid fa-bars-progress nav-icon text-white'></i>
                            <p>Manage Clearance<i class="right fas fa-angle-left"></i></p>
                        </a>
                        <ul class="nav nav-treeview">
                             <li class="nav-item">
                                <a href="../services/clearance.php" class="nav-link <?= isActivePage(['clearance.php']) ?>">
                                    <i class='fas fa-tasks nav-icon'></i>
                                    <p>Property Clearance</p>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="../services/clearance-statistic.php" class="nav-link <?= isActivePage(['clearance-statistic.php']) ?>">
                                    <i class='fas fa-chart-bar nav-icon'></i>
                                    <p>Clearance Statistics</p>
                                </a>
                            </li>
                           <li class="nav-item">
                                <a href="../admin/clearance-type.php" class="nav-link <?= isActivePage(['clearance-type.php']) ?>">
                                <i class='fas fa-money-check nav-icon'></i>
                                    <p>Clearance Category</p>
                                </a>
                            </li>
                        </ul>
                    </li>
                <?php endif; ?>
                <!-- Add other menu items here -->
                <?php if (hasRole(['SYSTEM-ADMIN','DISPOSAL-ADMIN'])): ?>
                    <li class="nav-item">
                        <a href="#" class="nav-link <?= isActivePage(['add-return.php', 'dashboard-inventory.php','inventory.php', 'unserviceable.php', 'unserviceable-items.php', 'disposal.php', 'disposal-account-code.php', 'disposal-items.php']) ?>">
                            <i class="nav-icon fa-solid fa-dolly-flatbed text-white"></i>
                            <p>Return<i class="right fas fa-angle-left"></i></p>
                        </a>
                        <ul class="nav nav-treeview">
                            <li class="nav-item">
                                <a href="../admin/add-return.php" class="nav-link <?= isActivePage(['add-return.php']) ?>">
                                <i class='fa-solid fa-plus nav-icon'></i>
                                <p>Add Return</p>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="../admin/dashboard-inventory.php" class="nav-link <?= isActivePage(['dashboard-inventory.php']) ?>">
                                <i class='fa-solid fa-boxes-packing nav-icon'></i>
                                <p>Inventory</p>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="../admin/unserviceable.php" class="nav-link <?= isActivePage(['unserviceable.php']) ?>">
                                <i class='fas fa-exclamation-triangle nav-icon'></i>
                                <p>Unserviceable</p>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="../admin/disposal.php" class="nav-link <?= isActivePage(['disposal.php', 'disposal-account-code.php', 'disposal-items.php']) ?>">
                                <i class='fas fa-recycle nav-icon'></i>
                                <p>Disposal</p>
                                </a>
                            </li>
                        </ul>
                    </li>
                <?php endif; ?>
                <?php if (hasRole(['SYSTEM-ADMIN','GF/SEF-ADMIN'])): ?>
                    <li class="nav-item">
                        <a href="#" class="nav-link <?= isActivePage(['general-fund-account-code.php', 'general-fund-account-inventory.php', 'sef-account-code.php', 'sef-account-inventory.php']) ?>">
                            <i class="nav-icon fa-solid fa-file-alt text-white"></i>
                            <p>Report<i class="right fas fa-angle-left"></i></p>
                        </a>
                        <ul class="nav nav-treeview">
                            <li class="nav-item">
                                <a href="../admin/general-fund-account-code.php" class="nav-link <?= isActivePage(['general-fund-account-code.php', 'general-fund-account-inventory.php']) ?>">
                                <i class='far fa-folder nav-icon'></i>
                                <p>General Fund</p>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="../admin/sef-account-code.php" class="nav-link <?= isActivePage(['sef-account-code.php', 'sef-account-inventory.php']) ?>">
                                <i class='far fa-folder nav-icon'></i>
                                <p>SEF</p>
                                </a>
                            </li>
                        </ul>
                    </li>
                <?php endif; ?>
                <?php if (hasRole(['SYSTEM-ADMIN'])): ?>
                    <li class="nav-item">
                        <a href="../admin/admin-panel.php" class="nav-link <?= isActivePage(['admin-panel.php']) ?>">
                            <i class='fas fa-users-cog nav-icon text-white'></i>
                            <p>Administrator</p>
                        </a>
                    </li>
                <?php endif; ?>
                <?php if (hasRole(['SYSTEM-ADMIN'])): ?>
                    <li class="nav-item">
                        <a href="../admin/activity-log.php" class="nav-link <?= isActivePage(['activity-log.php']) ?>">
                            <i class='fas fa-chart-line nav-icon text-white'></i>
                            <p>Activity Log</p>
                        </a>
                    </li>
                <?php endif; ?>
                <!--logout -->
                <li class="nav-item">
                    <a href="../logout.php" class="nav-link">
                        <i class="fas fa-sign-out-alt nav-icon text-white"></i>
                        <p>Log Out</p>
                    </a>
                </li>
            </ul>
        </nav>
        <!-- /.sidebar-menu -->
    </div>
    <!-- /.sidebar -->
</aside>
