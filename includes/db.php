<?php
require_once __DIR__ . '/../config.php';

// Reusable mysqli connection for all project files.
$conn = mysqli_connect($db_host, $db_user, $db_password, $db_name);

if (!$conn) {
    error_log('Database connection failed: ' . mysqli_connect_error());
    die('Database connection failed. Please check the project database configuration.');
}

if (!mysqli_set_charset($conn, 'utf8mb4')) {
    error_log('Database charset error: ' . mysqli_error($conn));
    die('Database setup error. Please try again later.');
}
?>
