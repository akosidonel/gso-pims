<?php 
// DB credentials.
define('DB_HOST','192.168.1.132'); // Use 'localhost' for local server
define('DB_PORT', 3306); // Use integer for port
define('DB_USER','root');
define('DB_PASS','Gso@pque2025');
define('DB_NAME','gsodbms');
// Establish database connection.
$conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);

// Echo the error if connection fails
if (!$conn) {
    echo 'Database connection failed: ' . mysqli_connect_error();
    exit();
}

mysqli_set_charset($conn, "utf8");
?>