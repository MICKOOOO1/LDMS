<?php
$host = 'localhost';
$user = 'root';
$password = '';
$database = 'csu_dorm_db';

// Email configuration (configure for your environment).
// NOTE: Never commit a real Gmail App Password. Put your own App Password here locally.
$email_config = [
    'host' => 'smtp.gmail.com',
    'port' => 587,
    'username' => 'mickmickengalan123@gmail.com',
    'password' => 'YOUR_GMAIL_APP_PASSWORD',
    'from_email' => 'mickmickengalan123@gmail.com',
    'from_name' => 'CSUCC LDMS',
    'encryption' => 'tls',
];

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
ensure_column($conn, 'users', 'profile_picture', "VARCHAR(255) NULL");
ensure_column($conn, 'users', 'setup_completed', "TINYINT(1) NOT NULL DEFAULT 1");

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

ensure_table($conn, 'login_logs', "CREATE TABLE login_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    employee_id VARCHAR(15) NOT NULL,
    full_name VARCHAR(160) NOT NULL,
    role ENUM('user','admin','super_admin') NOT NULL,
    time_in DATETIME NOT NULL,
    time_out DATETIME NULL,
    INDEX idx_login_logs_time_in (time_in),
    INDEX idx_login_logs_role (role),
    INDEX idx_login_logs_user_id (user_id)
)");

ensure_table($conn, 'active_sessions', "CREATE TABLE active_sessions (
    user_id INT PRIMARY KEY,
    role ENUM('user','admin','super_admin') NOT NULL,
    session_token VARCHAR(128) NOT NULL,
    login_log_id INT NULL,
    last_seen DATETIME NOT NULL,
    CONSTRAINT fk_active_session_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
)");

ensure_table($conn, 'otp_codes', "CREATE TABLE otp_codes (
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
)");

ensure_table($conn, 'pending_registrations', "CREATE TABLE pending_registrations (
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
    status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    reviewed_by INT NULL,
    reviewed_at TIMESTAMP NULL DEFAULT NULL,
    UNIQUE KEY uk_pending_user_id (user_id),
    UNIQUE KEY uk_pending_email (email),
    UNIQUE KEY uk_pending_username (username),
    INDEX idx_pending_status (status),
    INDEX idx_pending_created (created_at)
)");

ensure_column($conn, 'pending_registrations', 'security_question_1', "VARCHAR(50) NULL");
ensure_column($conn, 'pending_registrations', 'security_answer_hash_1', "VARCHAR(255) NULL");
ensure_column($conn, 'pending_registrations', 'security_question_2', "VARCHAR(50) NULL");
ensure_column($conn, 'pending_registrations', 'security_answer_hash_2', "VARCHAR(255) NULL");
ensure_column($conn, 'pending_registrations', 'security_question_3', "VARCHAR(50) NULL");
ensure_column($conn, 'pending_registrations', 'security_answer_hash_3', "VARCHAR(255) NULL");

function generate_otp(int $length = 6): string {
    $otp = '';
    for ($i = 0; $i < $length; $i++) {
        $otp .= random_int(0, 9);
    }
    return $otp;
}

function smtp_read_response($socket): string {
    $response = '';
    while (($line = fgets($socket, 515)) !== false) {
        $response .= $line;
        if (strlen($line) >= 4 && $line[3] === ' ') {
            break;
        }
    }
    return $response;
}

function smtp_command($socket, string $command, array $expectedCodes): string {
    fwrite($socket, $command . "\r\n");
    $response = smtp_read_response($socket);
    $code = (int)substr($response, 0, 3);
    if (!in_array($code, $expectedCodes, true)) {
        throw new RuntimeException("SMTP command failed: $command | Response: $response");
    }
    return $response;
}

function smtp_send_mail(array $config, string $to, string $subject, string $html): bool {
    $host = $config['host'] ?? '';
    $port = (int)($config['port'] ?? 587);
    $username = trim($config['username'] ?? '');
    $password = str_replace(' ', '', trim($config['password'] ?? ''));
    $fromEmail = trim($config['from_email'] ?? '') ?: $username;
    $fromName = trim($config['from_name'] ?? 'CSUCC LDMS');
    $encryption = strtolower(trim($config['encryption'] ?? 'tls'));

    if ($host === '' || $username === '' || $password === '' || $fromEmail === '') {
        error_log('SMTP is not configured. Set host, username, password, and from_email in server/config.php.');
        return false;
    }

    $remote = $encryption === 'ssl' ? "ssl://$host:$port" : "$host:$port";
    $socket = @stream_socket_client($remote, $errno, $errstr, 30, STREAM_CLIENT_CONNECT);
    if (!$socket) {
        error_log("SMTP connection failed: $errstr ($errno)");
        return false;
    }

    stream_set_timeout($socket, 30);

    try {
        $response = smtp_read_response($socket);
        if ((int)substr($response, 0, 3) !== 220) {
            throw new RuntimeException("SMTP greeting failed: $response");
        }

        smtp_command($socket, 'EHLO localhost', [250]);

        if ($encryption === 'tls') {
            smtp_command($socket, 'STARTTLS', [220]);
            if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new RuntimeException('Unable to start TLS encryption.');
            }
            smtp_command($socket, 'EHLO localhost', [250]);
        }

        smtp_command($socket, 'AUTH LOGIN', [334]);
        smtp_command($socket, base64_encode($username), [334]);
        smtp_command($socket, base64_encode($password), [235]);
        smtp_command($socket, 'MAIL FROM:<' . $fromEmail . '>', [250]);
        smtp_command($socket, 'RCPT TO:<' . $to . '>', [250, 251]);
        smtp_command($socket, 'DATA', [354]);

        $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
        $safeFromName = str_replace(['"', "\r", "\n"], ['', '', ''], $fromName);
        $messageId = '<' . bin2hex(random_bytes(12)) . '@csucc-ldms.local>';
        $headers = [
            'Date: ' . date(DATE_RFC2822),
            'To: <' . $to . '>',
            'From: "' . $safeFromName . '" <' . $fromEmail . '>',
            'Reply-To: <' . $fromEmail . '>',
            'Subject: ' . $encodedSubject,
            'Message-ID: ' . $messageId,
            'MIME-Version: 1.0',
            'Content-Type: text/html; charset=UTF-8',
            'Content-Transfer-Encoding: 8bit',
        ];

        $payload = implode("\r\n", $headers) . "\r\n\r\n" . $html;
        $payload = preg_replace('/^\./m', '..', $payload);
        fwrite($socket, $payload . "\r\n.\r\n");
        $response = smtp_read_response($socket);
        if ((int)substr($response, 0, 3) !== 250) {
            throw new RuntimeException("SMTP message send failed: $response");
        }

        smtp_command($socket, 'QUIT', [221]);
        fclose($socket);
        return true;
    } catch (Throwable $e) {
        error_log($e->getMessage());
        fclose($socket);
        return false;
    }
}

function send_otp_email(mysqli $conn, string $email, string $otp, string $purpose, string $full_name): bool {
    global $email_config;
    
    $subject = $purpose === 'account_activation' 
        ? 'Your CSUCC LDMS Account Activation Code'
        : 'Your CSUCC LDMS Password Reset Code';
    
    $purpose_text = $purpose === 'account_activation' ? 'account activation' : 'password reset';
    
    $message = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: #1e3a8a; color: white; padding: 20px; text-align: center; }
            .content { padding: 20px; background: #f8fafc; }
            .otp-code { background: #1e3a8a; color: white; font-size: 32px; font-weight: bold; text-align: center; padding: 20px; letter-spacing: 8px; margin: 20px 0; }
            .footer { text-align: center; padding: 20px; color: #64748b; font-size: 12px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>CSUCC LDMS</h1>
            </div>
            <div class='content'>
                <p>Dear <strong>" . htmlspecialchars($full_name) . "</strong>,</p>
                <p>Your <strong>$purpose_text</strong> code is:</p>
                <div class='otp-code'>$otp</div>
                <p>This code will expire in <strong>10 minutes</strong>.</p>
                <p>If you didn't request this, please ignore this email or contact support.</p>
            </div>
            <div class='footer'>
                <p>This is an automated message from CSUCC Ladies Dormitory Management System.</p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    return smtp_send_mail($email_config, $email, $subject, $message);
}

function create_otp(mysqli $conn, int $user_id, string $email, string $purpose, string $full_name): ?string {
    $otp = generate_otp();
    
    // Invalidate any existing unused OTPs for this user/purpose
    $stmt = $conn->prepare("UPDATE otp_codes SET used = 1 WHERE user_id = ? AND purpose = ? AND used = 0");
    $stmt->bind_param("is", $user_id, $purpose);
    $stmt->execute();
    $stmt->close();
    
    // Insert new OTP using MySQL time so expiry uses the same clock as verification.
    $stmt = $conn->prepare("INSERT INTO otp_codes (user_id, email, otp_code, purpose, expires_at) VALUES (?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 10 MINUTE))");
    $stmt->bind_param("isss", $user_id, $email, $otp, $purpose);
    if (!$stmt->execute()) {
        $stmt->close();
        return null;
    }
    $stmt->close();
    
    // Send email
    if (!send_otp_email($conn, $email, $otp, $purpose, $full_name)) {
        error_log("Failed to send OTP email to $email");
        // Don't return null - OTP is stored, just email failed
    }
    
    return $otp;
}

function verify_otp(mysqli $conn, int $user_id, string $otp, string $purpose): bool {
    $otp = preg_replace('/\D/', '', $otp);
    $stmt = $conn->prepare("SELECT id FROM otp_codes WHERE user_id = ? AND otp_code = ? AND purpose = ? AND used = 0 AND expires_at >= NOW() ORDER BY id DESC LIMIT 1");
    $stmt->bind_param("iss", $user_id, $otp, $purpose);
    $stmt->execute();
    $result = $stmt->get_result();
    $valid = $result->num_rows > 0;
    $stmt->close();
    
    if ($valid) {
        // Mark as used
        $stmt = $conn->prepare("UPDATE otp_codes SET used = 1 WHERE user_id = ? AND otp_code = ? AND purpose = ?");
        $stmt->bind_param("iss", $user_id, $otp, $purpose);
        $stmt->execute();
        $stmt->close();
    }
    
    return $valid;
}

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
