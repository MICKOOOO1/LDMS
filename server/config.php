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

function safe_identifier(string $identifier): string {
    if (!preg_match('/^[A-Za-z0-9_]+$/', $identifier)) {
        die('Invalid database identifier.');
    }

    return $identifier;
}

function table_exists(mysqli $conn, string $table): bool {
    $table = $conn->real_escape_string(safe_identifier($table));
    $result = $conn->query("SHOW TABLES LIKE '$table'");

    return $result && $result->num_rows > 0;
}

function column_exists(mysqli $conn, string $table, string $column): bool {
    $table = safe_identifier($table);
    $column = $conn->real_escape_string($column);
    $result = $conn->query("SHOW COLUMNS FROM `$table` LIKE '$column'");

    return $result && $result->num_rows > 0;
}

function ensure_column(mysqli $conn, string $table, string $column, string $definition): void {
    if (!table_exists($conn, $table)) {
        die('The users table is missing. Run server/setup.php or import database/csudorm.sql first.');
    }

    if (!column_exists($conn, $table, $column) && !$conn->query("ALTER TABLE `$table` ADD `$column` $definition")) {
        die('Database migration failed: ' . $conn->error);
    }
}

function ensure_table(mysqli $conn, string $table, string $sql): void {
    if (!table_exists($conn, $table) && !$conn->query($sql)) {
        die('Database table creation failed: ' . $conn->error);
    }
}

ensure_column($conn, 'users', 'role', "ENUM('user','admin','super_admin') NOT NULL DEFAULT 'user'");
ensure_column($conn, 'users', 'account_status', "ENUM('pending','approved','blocked') NOT NULL DEFAULT 'approved'");
ensure_column($conn, 'users', 'privileges', "TEXT NULL");

ensure_table($conn, 'delete_requests', "CREATE TABLE delete_requests (
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
)");

$result = $conn->query("SELECT COUNT(*) AS total FROM users WHERE role = 'super_admin'");
$superAdminCount = $result ? (int)$result->fetch_assoc()['total'] : 0;
if ($superAdminCount === 0) {
    $defaultPassword = password_hash('SuperAdmin123!', PASSWORD_DEFAULT);
    $stmt = $conn->prepare("INSERT INTO users (
        user_id, first_name, middle_name, last_name, extension_name, dob, age, sex,
        email, username, password_hash, role, account_status, privileges,
        purok_street, barangay, city_municipality, province, country, zip_code
    ) VALUES (
        '0000-0001', 'Super', NULL, 'Admin', NULL, '1990-01-01', 36, 'female',
        'superadmin@csucc-ldms.local', 'super.admin', ?, 'super_admin', 'approved',
        'manage_accounts,manage_privileges,delete_accounts',
        'Campus', 'CSUCC', 'Cabadbaran', 'Agusan Del Norte', 'Philippines', '8605'
    ) ON DUPLICATE KEY UPDATE role = 'super_admin', account_status = 'approved'");
    $stmt->bind_param("s", $defaultPassword);
    $stmt->execute();
    $stmt->close();
}

$result = $conn->query("SELECT COUNT(*) AS total FROM users WHERE role = 'admin'");
$adminCount = $result ? (int)$result->fetch_assoc()['total'] : 0;
if ($adminCount === 0) {
    $defaultPassword = password_hash('Admin123!', PASSWORD_DEFAULT);
    $stmt = $conn->prepare("INSERT INTO users (
        user_id, first_name, middle_name, last_name, extension_name, dob, age, sex,
        email, username, password_hash, role, account_status, privileges,
        purok_street, barangay, city_municipality, province, country, zip_code
    ) VALUES (
        '0000-0002', 'System', NULL, 'Admin', NULL, '1990-01-01', 36, 'female',
        'admin@csucc-ldms.local', 'system.admin', ?, 'admin', 'approved',
        'manage_registration,update_accounts',
        'Campus', 'CSUCC', 'Cabadbaran', 'Agusan Del Norte', 'Philippines', '8605'
    ) ON DUPLICATE KEY UPDATE role = 'admin', account_status = 'approved'");
    $stmt->bind_param("s", $defaultPassword);
    $stmt->execute();
    $stmt->close();
}
?>
