<?php
$host = 'localhost';
$user = 'root';
$password = '';
$database = 'csu_dorm_db';

// 1) Connect to MySQL server (without specifying DB yet)
$conn = new mysqli($host, $user, $password);

if ($conn->connect_error) {
    die('Connection failed: ' . $conn->connect_error);
}

// 2) Select the database explicitly
if (!$conn->select_db($database)) {
    die('Database not found on this server: ' . $database);
}
?>