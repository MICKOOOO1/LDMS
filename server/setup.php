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

function setup_ensure_column(mysqli $conn, string $table, string $column, string $definition): void {
    $stmt = $conn->prepare("SHOW COLUMNS FROM `$table` LIKE ?");
    $stmt->bind_param("s", $column);
    $stmt->execute();
    $result = $stmt->get_result();
    $exists = $result->num_rows > 0;
    $stmt->close();

    if (!$exists) {
        $conn->query("ALTER TABLE `$table` ADD `$column` $definition");
    }
}

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
    role ENUM('user','admin','super_admin') NOT NULL DEFAULT 'user',
    account_status ENUM('pending','approved','blocked') NOT NULL DEFAULT 'approved',
    privileges TEXT NULL,
    purok_street VARCHAR(100) NOT NULL,
    barangay VARCHAR(100) NOT NULL,
    city_municipality VARCHAR(100) NOT NULL,
    province VARCHAR(100) NOT NULL,
    country VARCHAR(100) NOT NULL,
    zip_code VARCHAR(20) NOT NULL,
    profile_picture VARCHAR(255) NULL,
    setup_completed TINYINT(1) NOT NULL DEFAULT 1,
    date_registered TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

if ($conn->query($sql) === TRUE) {
    echo "Users table created successfully<br>";
} else {
    echo "Error creating users table: " . $conn->error . "<br>";
}

setup_ensure_column($conn, 'users', 'role', "ENUM('user','admin','super_admin') NOT NULL DEFAULT 'user'");
setup_ensure_column($conn, 'users', 'account_status', "ENUM('pending','approved','blocked') NOT NULL DEFAULT 'approved'");
setup_ensure_column($conn, 'users', 'privileges', "TEXT NULL");
setup_ensure_column($conn, 'users', 'profile_picture', "VARCHAR(255) NULL");
setup_ensure_column($conn, 'users', 'setup_completed', "TINYINT(1) NOT NULL DEFAULT 1");

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

// 7) Create delete_requests table
$sql = "CREATE TABLE IF NOT EXISTS delete_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    requested_by INT NOT NULL,
    target_user INT NOT NULL,
    reason TEXT NOT NULL,
    user_snapshot TEXT NOT NULL,
    request_status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    reviewed_by INT NULL,
    reviewed_at TIMESTAMP NULL DEFAULT NULL,
    CONSTRAINT fk_delete_request_admin FOREIGN KEY (requested_by) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_delete_request_target FOREIGN KEY (target_user) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_delete_request_reviewer FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL
)";

if ($conn->query($sql) === TRUE) {
    echo "Delete requests table created successfully<br>";
} else {
    echo "Error creating delete requests table: " . $conn->error . "<br>";
}

// 8) Create OTP codes table
$sql = "CREATE TABLE IF NOT EXISTS otp_codes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    email VARCHAR(100) NOT NULL,
    otp_code VARCHAR(6) NOT NULL,
    purpose ENUM('account_activation','password_reset') NOT NULL,
    expires_at DATETIME NOT NULL,
    used TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_otp_user_purpose (user_id, purpose),
    INDEX idx_otp_expires (expires_at)
)";

if ($conn->query($sql) === TRUE) {
    echo "OTP codes table created successfully<br>";
} else {
    echo "Error creating OTP codes table: " . $conn->error . "<br>";
}

// 9) Create active_sessions table
$sql = "CREATE TABLE IF NOT EXISTS active_sessions (
    user_id INT PRIMARY KEY,
    role ENUM('user','admin','super_admin') NOT NULL,
    session_token VARCHAR(128) NOT NULL,
    login_log_id INT NULL,
    last_seen DATETIME NOT NULL,
    CONSTRAINT fk_active_session_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
)";

if ($conn->query($sql) === TRUE) {
    echo "Active sessions table created successfully<br>";
} else {
    echo "Error creating active sessions table: " . $conn->error . "<br>";
}

$sql = "CREATE TABLE IF NOT EXISTS account_action_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    actor_user_id INT NULL,
    actor_employee_id VARCHAR(15) NOT NULL,
    actor_name VARCHAR(160) NOT NULL,
    actor_role VARCHAR(20) NOT NULL,
    action VARCHAR(40) NOT NULL,
    details TEXT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_account_action_logs_created_at (created_at),
    INDEX idx_account_action_logs_actor_role (actor_role),
    INDEX idx_account_action_logs_action (action)
)";

if ($conn->query($sql) === TRUE) {
    echo "Account action logs table created successfully<br>";
} else {
    echo "Error creating account action logs table: " . $conn->error . "<br>";
}

// 10) Create pending_registrations table
$sql = "CREATE TABLE IF NOT EXISTS pending_registrations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id VARCHAR(15) NOT NULL,
    first_name VARCHAR(50) NOT NULL,
    middle_name VARCHAR(50) NULL,
    last_name VARCHAR(50) NOT NULL,
    extension_name VARCHAR(10) NULL,
    dob DATE NOT NULL,
    age TINYINT UNSIGNED NOT NULL,
    sex ENUM('male', 'female') NOT NULL,
    email VARCHAR(100) NOT NULL,
    username VARCHAR(50) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    purok_street VARCHAR(100) NOT NULL,
    barangay VARCHAR(100) NOT NULL,
    city_municipality VARCHAR(100) NOT NULL,
    province VARCHAR(100) NOT NULL,
    country VARCHAR(100) NOT NULL,
    zip_code VARCHAR(20) NOT NULL,
    security_question_1 VARCHAR(50) NULL,
    security_answer_hash_1 VARCHAR(255) NULL,
    security_question_2 VARCHAR(50) NULL,
    security_answer_hash_2 VARCHAR(255) NULL,
    security_question_3 VARCHAR(50) NULL,
    security_answer_hash_3 VARCHAR(255) NULL,
    status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    reviewed_by INT NULL,
    reviewed_at TIMESTAMP NULL DEFAULT NULL,
    UNIQUE KEY uk_pending_user_id (user_id),
    UNIQUE KEY uk_pending_email (email),
    UNIQUE KEY uk_pending_username (username),
    INDEX idx_pending_status (status),
    INDEX idx_pending_created (created_at)
)";

if ($conn->query($sql) === TRUE) {
    echo "Pending registrations table created successfully<br>";
} else {
    echo "Error creating pending registrations table: " . $conn->error . "<br>";
}

setup_ensure_column($conn, 'pending_registrations', 'security_question_1', "VARCHAR(50) NULL");
setup_ensure_column($conn, 'pending_registrations', 'security_answer_hash_1', "VARCHAR(255) NULL");
setup_ensure_column($conn, 'pending_registrations', 'security_question_2', "VARCHAR(50) NULL");
setup_ensure_column($conn, 'pending_registrations', 'security_answer_hash_2', "VARCHAR(255) NULL");
setup_ensure_column($conn, 'pending_registrations', 'security_question_3', "VARCHAR(50) NULL");
setup_ensure_column($conn, 'pending_registrations', 'security_answer_hash_3', "VARCHAR(255) NULL");

// 10) Seed default super admin account
$year = date('Y');
$superAdminId = $year . '-0001';
$defaultPassword = password_hash('SuperAdmin123!', PASSWORD_DEFAULT);
$sql = "INSERT INTO users (
    user_id, first_name, middle_name, last_name, extension_name, dob, age, sex,
    email, username, password_hash, role, account_status, privileges,
    purok_street, barangay, city_municipality, province, country, zip_code
) VALUES (
    ?, 'Super', NULL, 'Admin', NULL, '1990-01-01', 36, 'female',
    'superadmin@csucc-ldms.local', 'super.admin', ?, 'super_admin', 'approved',
    'manage_accounts,manage_privileges,delete_accounts',
    'Campus', 'CSUCC', 'Cabadbaran', 'Agusan Del Norte', 'Philippines', '8605'
) ON DUPLICATE KEY UPDATE role = 'super_admin', account_status = 'approved'";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ss", $superAdminId, $defaultPassword);
$stmt->execute();
$stmt->close();

// 9) Seed default admin account
$adminId = $year . '-0002';
$defaultPassword = password_hash('Admin123!', PASSWORD_DEFAULT);
$sql = "INSERT INTO users (
    user_id, first_name, middle_name, last_name, extension_name, dob, age, sex,
    email, username, password_hash, role, account_status, privileges,
    purok_street, barangay, city_municipality, province, country, zip_code
) VALUES (
    ?, 'System', NULL, 'Admin', NULL, '1990-01-01', 36, 'female',
    'admin@csucc-ldms.local', 'system.admin', ?, 'admin', 'approved',
    'manage_registration,update_accounts',
    'Campus', 'CSUCC', 'Cabadbaran', 'Agusan Del Norte', 'Philippines', '8605'
) ON DUPLICATE KEY UPDATE role = 'admin', account_status = 'approved'";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ss", $adminId, $defaultPassword);
$stmt->execute();
$stmt->close();

// Migration: Update existing user IDs to 2026- format with unique sequence
function migrate_user_ids(mysqli $conn, string $table): void {
    $result = $conn->query("SELECT id, user_id FROM $table WHERE user_id NOT LIKE '2026-%' AND user_id REGEXP '^[0-9]{4}-[0-9]{4}$'");
    if (!$result) return;
    
    $usedIds = [];
    $existingResult = $conn->query("SELECT user_id FROM $table WHERE user_id LIKE '2026-%'");
    if ($existingResult) {
        while ($row = $existingResult->fetch_assoc()) {
            $usedIds[$row['user_id']] = true;
        }
        $existingResult->close();
    }
    
    $nextNum = 1;
    while ($row = $result->fetch_assoc()) {
        // Find next available number
        while (isset($usedIds['2026-' . str_pad($nextNum, 4, '0', STR_PAD_LEFT)])) {
            $nextNum++;
        }
        $newId = '2026-' . str_pad($nextNum, 4, '0', STR_PAD_LEFT);
        $usedIds[$newId] = true;
        
        $stmt = $conn->prepare("UPDATE $table SET user_id = ? WHERE id = ?");
        $stmt->bind_param("si", $newId, $row['id']);
        $stmt->execute();
        $stmt->close();
        $nextNum++;
    }
    $result->close();
}

migrate_user_ids($conn, 'users');
migrate_user_ids($conn, 'pending_registrations');

$conn->close();

echo "Setup completed!";
?>