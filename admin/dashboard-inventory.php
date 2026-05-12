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

<div class="content-wrapper">
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1>Return to Stock Inventory</h1>
                </div>
                 <div class="col-sm-6">
            <ol class="breadcrumb float-sm-right">
              <li class="breadcrumb-item"><a href="dashboard.php">Home</a></li>
              <li class="breadcrumb-item active">Return to Stock Inventory</li>
            </ol>
          </div>
            </div>
        </div>
    </section>

    <!-- Main content -->
    <section class="content">
      <div class="container-fluid">
           
      <?php
        // Fetch data
        $sql = $conn->prepare("
            SELECT 
                SUM(CASE WHEN item = 'FURNITURE AND FIXTURES' THEN 1 ELSE 0 END) AS furniture_count,
                SUM(CASE WHEN item = 'DESKTOP COMPUTER' THEN 1 ELSE 0 END) AS desktop_count,
                SUM(CASE WHEN item = 'LAPTOP' THEN 1 ELSE 0 END) AS laptop_count,
                SUM(CASE WHEN item = 'AIRCONDITIONER' THEN 1 ELSE 0 END) AS aircon_count,
                SUM(CASE WHEN item = 'MOTOR VEHICLE' THEN 1 ELSE 0 END) AS vehicle_count,
                SUM(CASE WHEN item = 'PRINTER' THEN 1 ELSE 0 END) AS printer_count,
                SUM(CASE WHEN item = 'SERVER' THEN 1 ELSE 0 END) AS server_count,
                SUM(CASE WHEN item = 'OTHER MACHINERY AND EQUIPMENT' THEN 1 ELSE 0 END) AS machinery_count  
            FROM return_to_stock
        ");
        $sql->execute();
        $data = $sql->get_result()->fetch_assoc();

        
            // Reusable function to render small-box components
            function renderSmallBox($icon, $title, $count = 0, $item, $color) {
              $count = $count ?? 0; // Default to 0 if null  
              return "
                <div class='col-lg-2 col-4'>
                    <div class='small-box bg-$color'>
                        <div class='inner'>
                            <h3>" . htmlspecialchars($count) . "<sup style='font-size: 20px'> Available</sup></h3>
                            <p>" . htmlspecialchars($title) . "</p>
                        </div>
                        <div class='icon'>
                            <i class='fa-solid $icon'></i>
                        </div>
                        <a href='inventory.php?item=" . urlencode($item) . "' class='small-box-footer'>
                            More info <i class='fas fa-arrow-circle-right'></i>
                        </a>
                    </div>
                </div>";
            }
        ?>

        <!-- Equipment Section -->
        <div class="row">
        <?php
                // Render small-box components
                echo renderSmallBox('fa-computer', 'Desktop Computer', $data['desktop_count'], 'DESKTOP COMPUTER', 'info');
                echo renderSmallBox('fa-laptop', 'Laptop', $data['laptop_count'], 'LAPTOP', 'maroon');
                echo renderSmallBox('fa-fan', 'Airconditioner', $data['aircon_count'], 'AIRCONDITIONER', 'warning');
                echo renderSmallBox('fa-truck-front', 'Motor Vehicle', $data['vehicle_count'], 'MOTOR VEHICLE', 'danger');
                echo renderSmallBox('fa-print', 'Printer', $data['printer_count'], 'PRINTER', 'primary');
                echo renderSmallBox('fa-server', 'Server', $data['server_count'], 'SERVER', 'orange');
                echo renderSmallBox('fa-gears', 'Other Machinery and Equipment', $data['machinery_count'], 'OTHER MACHINERY AND EQUIPMENT', 'lightblue');
                echo renderSmallBox('fa-chair', 'Furniture N Fixtures', $data['furniture_count'], 'FURNITURE AND FIXTURES', 'purple'); ?>
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