<?php
// Setup script to create database and tables for CSUCC LDMS

$host = 'localhost';
$user = 'root';
$password = '';

// 1) Connect to MySQL server
$conn = new mysqli($host, $user, $password);

if ($conn->connect_error) {
    die('Connection failed: ' . $conn->connect_error);
}

// 2) Create database if not exists
$sql = "CREATE DATABASE IF NOT EXISTS csu_dorm_db";
if ($conn->query($sql) === TRUE) {
    echo "Database created successfully<br>";
} else {
    echo "Error creating database: " . $conn->error . "<br>";
}

// 3) Select the database
$conn->select_db('csu_dorm_db');

// 4) Create users table
$sql = "CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id VARCHAR(15) NOT NULL UNIQUE,
    first_name VARCHAR(50) NOT NULL,
    middle_name VARCHAR(50) NULL,
    last_name VARCHAR(50) NOT NULL,
    extension_name VARCHAR(10) NULL,
    dob DATE NOT NULL,
    age TINYINT UNSIGNED NOT NULL,
    sex ENUM('male', 'female') NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    username VARCHAR(50) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    purok_street VARCHAR(100) NOT NULL,
    barangay VARCHAR(100) NOT NULL,
    city_municipality VARCHAR(100) NOT NULL,
    province VARCHAR(100) NOT NULL,
    country VARCHAR(100) NOT NULL,
    zip_code VARCHAR(20) NOT NULL,
    date_registered TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

if ($conn->query($sql) === TRUE) {
    echo "Users table created successfully<br>";
} else {
    echo "Error creating users table: " . $conn->error . "<br>";
}

// 5) Create security_questions table
$sql = "CREATE TABLE IF NOT EXISTS security_questions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id VARCHAR(15) NOT NULL,
    question_code VARCHAR(50) NOT NULL,
    answer_hash VARCHAR(255) NOT NULL,
    CONSTRAINT fk_sq_user
        FOREIGN KEY (user_id) REFERENCES users(user_id)
        ON DELETE CASCADE
)";

if ($conn->query($sql) === TRUE) {
    echo "Security questions table created successfully<br>";
} else {
    echo "Error creating security questions table: " . $conn->error . "<br>";
}

// 6) Create login_attempts table
$sql = "CREATE TABLE IF NOT EXISTS login_attempts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL,
    attempts INT NOT NULL DEFAULT 0,
    lockout_until INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

if ($conn->query($sql) === TRUE) {
    echo "Login attempts table created successfully<br>";
} else {
    echo "Error creating login attempts table: " . $conn->error . "<br>";
}

$conn->close();

echo "Setup completed!";
?>
