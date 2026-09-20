<?php
function require_admin_role(array $allowedRoles): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $role = $_SESSION['role'] ?? '';
    if (!isset($_SESSION['user_id']) || !in_array($role, $allowedRoles, true)) {
        header('Location: ../user/login.php');
        exit;
    }

    global $conn;
    if ($conn instanceof mysqli) {
        $userId = (int)$_SESSION['user_id'];
        $sessionToken = $_SESSION['session_token'] ?? '';

        if ($sessionToken === '') {
            $sessionToken = bin2hex(random_bytes(32));
            $_SESSION['session_token'] = $sessionToken;

            if ($role === 'super_admin') {
                if (!empty($_SESSION['login_log_id'])) {
                    $currentLogId = (int)$_SESSION['login_log_id'];
                    $conn->query("UPDATE login_logs SET time_out = NOW() WHERE role = 'super_admin' AND time_out IS NULL AND id <> $currentLogId");
                } else {
                    $conn->query("UPDATE login_logs SET time_out = NOW() WHERE role = 'super_admin' AND time_out IS NULL");
                }

                $conn->query("DELETE FROM active_sessions WHERE role = 'super_admin'");
            } else {
                $clearStmt = $conn->prepare("DELETE FROM active_sessions WHERE user_id = ?");
                $clearStmt->bind_param("i", $userId);
                $clearStmt->execute();
                $clearStmt->close();
            }

            $loginLogId = !empty($_SESSION['login_log_id']) ? (int)$_SESSION['login_log_id'] : null;
            $stmt = $conn->prepare("INSERT INTO active_sessions (user_id, role, session_token, login_log_id, last_seen) VALUES (?, ?, ?, ?, NOW())");
            $stmt->bind_param("issi", $userId, $role, $sessionToken, $loginLogId);
            $stmt->execute();
            $stmt->close();
        }

        if ($role === 'super_admin') {
            $stmt = $conn->prepare("SELECT session_token FROM active_sessions WHERE user_id = ? AND role = 'super_admin' LIMIT 1");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $active = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$active || !hash_equals($active['session_token'], $sessionToken)) {
                if (!empty($_SESSION['login_log_id'])) {
                    $logStmt = $conn->prepare("UPDATE login_logs SET time_out = NOW() WHERE id = ? AND time_out IS NULL");
                    $logStmt->bind_param("i", $_SESSION['login_log_id']);
                    $logStmt->execute();
                    $logStmt->close();
                }

                session_unset();
                session_destroy();
                session_start();
                $_SESSION['alerts'] = [['type' => 'error', 'message' => 'Session expired. Another super admin has logged in.']];
                header('Location: ../user/login.php');
                exit;
            }
        }

        $stmt = $conn->prepare("UPDATE active_sessions SET last_seen = NOW() WHERE user_id = ? AND session_token = ?");
        $stmt->bind_param("is", $userId, $sessionToken);
        $stmt->execute();
        $stmt->close();
    }
}

function admin_redirect_for_role(string $role, string $returnPage = ''): string {
    if ($role === 'super_admin' && in_array($returnPage, ['pending.php', 'create_admin.php', 'accounts.php', 'delete_requests.php'], true)) {
        return '../super%20admin/' . $returnPage;
    }

    return $role === 'super_admin' ? '../super%20admin/dashboard.php' : '../admin/dashboard.php';
}

function admin_has_privilege(string $privilege): bool {
    if (($_SESSION['role'] ?? '') === 'super_admin') {
        return true;
    }

    $privileges = array_filter(array_map('trim', explode(',', $_SESSION['privileges'] ?? '')));
    return in_array($privilege, $privileges, true);
}

function full_name(array $user): string {
    return trim(($user['first_name'] ?? '') . ' ' . ($user['middle_name'] ?? '') . ' ' . ($user['last_name'] ?? '') . ' ' . ($user['extension_name'] ?? ''));
}

function render_admin_header(string $title, string $active): void {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?= htmlspecialchars($title); ?> - CSUCC LDMS</title>
        <link rel="stylesheet" href="../css/admin.css">
        <link href="https://cdn.boxicons.com/fonts/basic/boxicons.min.css" rel="stylesheet">
    </head>
    <body>
    <header>
        <a href="dashboard.php" class="logo"><img src="../assets/imgs/logo.png" alt="CSUCC Logo"> CSUCC LDMS</a>
        <nav>
            <a href="dashboard.php" class="<?= $active === 'dashboard' ? 'active' : ''; ?>">Dashboard</a>
            <a href="user_management.php" class="<?= $active === 'user_management' ? 'active' : ''; ?>">User Management</a>
            <a href="pending.php" class="<?= $active === 'pending' ? 'active' : ''; ?>">Pending Approvals</a>
            <a href="delete_requests.php" class="<?= $active === 'delete_requests' ? 'active' : ''; ?>">Deletion Request</a>
            <a href="logs.php" class="<?= $active === 'logs' ? 'active' : ''; ?>">System Logs</a>
            <a href="my_account.php" class="<?= $active === 'account' ? 'active' : ''; ?>">My Account</a>
            <span><?= htmlspecialchars($_SESSION['name'] ?? 'Administrator'); ?></span>
            <a href="../user/logout.php">Logout</a>
        </nav>
    </header>
    <main>
    <?php
}

function render_admin_footer(): void {
    ?>
    </main>
    </body>
    </html>
    <?php
}

function fetch_accounts(mysqli $conn, string $employeeId = '', string $search = '', string $role = '', string $status = '', string $sort = 'newest'): array {
    $conditions = ["users.account_status <> 'pending'"];
    $params = [];
    $types = '';

    if ($employeeId !== '') {
        $conditions[] = 'users.user_id LIKE ?';
        $params[] = '%' . $employeeId . '%';
        $types .= 's';
    }

    if ($search !== '') {
        $conditions[] = '(users.first_name LIKE ? OR users.middle_name LIKE ? OR users.last_name LIKE ? OR users.email LIKE ? OR users.username LIKE ?)';
        $searchLike = '%' . $search . '%';
        array_push($params, $searchLike, $searchLike, $searchLike, $searchLike, $searchLike);
        $types .= 'sssss';
    }

    if (in_array($role, ['user', 'admin', 'super_admin'], true)) {
        $conditions[] = 'users.role = ?';
        $params[] = $role;
        $types .= 's';
    }

    if (in_array($status, ['approved', 'blocked'], true)) {
        $conditions[] = 'users.account_status = ?';
        $params[] = $status;
        $types .= 's';
    }

    $where = $conditions ? ' WHERE ' . implode(' AND ', $conditions) : '';
    $orderBy = $sort === 'oldest' ? 'users.date_registered ASC' : ($sort === 'name' ? 'users.last_name ASC, users.first_name ASC' : 'users.date_registered DESC');
    $stmt = $conn->prepare("SELECT users.*, active_sessions.last_seen AS online_last_seen FROM users LEFT JOIN active_sessions ON active_sessions.user_id = users.id$where ORDER BY $orderBy");
    if ($types !== '') {
        $references = [$types];
        foreach ($params as $key => &$value) {
            $references[] = &$value;
        }
        call_user_func_array([$stmt, 'bind_param'], $references);
    }

    $stmt->execute();
    $result = $stmt->get_result();
    $accounts = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    return $accounts;
}

function fetch_dashboard_stats(mysqli $conn, string $role): array {
    $accountScope = $role === 'admin' ? " AND role <> 'super_admin'" : '';
    $logScope = $role === 'admin' ? " AND role <> 'super_admin'" : '';
    $stats = [
        'accounts' => 0,
        'active_accounts' => 0,
        'pending' => 0,
        'new_accounts' => 0,
        'logins_today' => 0,
        'active_sessions' => 0,
        'recent_logins' => [],
    ];

    $queries = [
        'accounts' => "SELECT COUNT(*) AS total FROM users WHERE 1=1$accountScope",
        'active_accounts' => "SELECT COUNT(*) AS total FROM users WHERE account_status = 'approved'$accountScope",
        'pending' => "SELECT COUNT(*) AS total FROM pending_registrations WHERE status = 'pending'",
        'new_accounts' => "SELECT COUNT(*) AS total FROM users WHERE date_registered >= DATE_FORMAT(CURRENT_DATE, '%Y-%m-01')$accountScope",
        'logins_today' => "SELECT COUNT(*) AS total FROM login_logs WHERE DATE(time_in) = CURRENT_DATE$logScope",
        'active_sessions' => "SELECT COUNT(*) AS total FROM login_logs WHERE time_out IS NULL$logScope",
    ];

    foreach ($queries as $key => $query) {
        $result = $conn->query($query);
        $stats[$key] = $result ? (int)$result->fetch_assoc()['total'] : 0;
    }

    $recentResult = $conn->query("SELECT employee_id, full_name, role, time_in, time_out FROM login_logs WHERE 1=1$logScope ORDER BY time_in DESC LIMIT 5");
    $stats['recent_logins'] = $recentResult ? $recentResult->fetch_all(MYSQLI_ASSOC) : [];

    return $stats;
}

function fetch_pending_registrations(mysqli $conn): array {
    $stmt = $conn->prepare("SELECT * FROM pending_registrations WHERE status = 'pending' ORDER BY created_at ASC");
    $stmt->execute();
    $result = $stmt->get_result();
    $accounts = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    return $accounts;
}

function pending_registration_filter(string $month, string $date): array {
    $where = "status = 'pending'";
    $params = [];
    $types = '';

    if (preg_match('/^\\d{4}-\\d{2}$/', $month)) {
        $where .= " AND DATE_FORMAT(created_at, '%Y-%m') = ?";
        $types .= 's';
        $params[] = $month;
    }

    if (preg_match('/^\\d{4}-\\d{2}-\\d{2}$/', $date)) {
        $where .= " AND DATE(created_at) = ?";
        $types .= 's';
        $params[] = $date;
    }

    return [$where, $types, $params];
}

function fetch_pending_registration_count(mysqli $conn, string $month = '', string $date = ''): int {
    [$where, $types, $params] = pending_registration_filter($month, $date);
    $stmt = $conn->prepare("SELECT COUNT(*) AS total FROM pending_registrations WHERE $where");
    if ($types !== '') {
        bind_dynamic_params($stmt, $types, $params);
    }
    $stmt->execute();
    $total = (int)$stmt->get_result()->fetch_assoc()['total'];
    $stmt->close();
    return $total;
}

function fetch_pending_registrations_page(mysqli $conn, int $page, string $month = '', string $date = '', int $perPage = 5): array {
    $page = max(1, $page);
    $offset = ($page - 1) * $perPage;
    [$where, $types, $params] = pending_registration_filter($month, $date);
    $stmt = $conn->prepare("SELECT * FROM pending_registrations WHERE $where ORDER BY created_at ASC LIMIT ? OFFSET ?");
    $params[] = $perPage;
    $params[] = $offset;
    bind_dynamic_params($stmt, $types . 'ii', $params);
    $stmt->execute();
    $result = $stmt->get_result();
    $accounts = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    return $accounts;
}

function fetch_login_logs(mysqli $conn, string $role, string $month, string $date, int $page, int $perPage = 10, string $search = '', string $roleFilter = '', string $actionFilter = ''): array {
    $page = max(1, $page);
    $allowedRoles = $role === 'super_admin' ? ['user', 'admin', 'super_admin'] : ($role === 'admin' ? ['user', 'admin'] : ['user']);
    $placeholders = implode(',', array_fill(0, count($allowedRoles), '?'));
    $where = "role IN ($placeholders)";
    $params = $allowedRoles;
    $types = str_repeat('s', count($allowedRoles));

    if ($roleFilter !== '' && in_array($roleFilter, $allowedRoles, true)) {
        $where .= " AND role = ?";
        $types .= 's';
        $params[] = $roleFilter;
    }

    if ($search !== '') {
        $where .= " AND (employee_id LIKE ? OR full_name LIKE ?)";
        $types .= 'ss';
        $searchTerm = '%' . $search . '%';
        $params[] = $searchTerm;
        $params[] = $searchTerm;
    }

    if ($actionFilter === 'active_session') {
        $where .= " AND time_out IS NULL";
    } elseif ($actionFilter === 'login') {
        $where .= " AND time_out IS NOT NULL";
    }

    if (preg_match('/^\\d{4}-\\d{2}$/', $month)) {
        $where .= " AND DATE_FORMAT(time_in, '%Y-%m') = ?";
        $types .= 's';
        $params[] = $month;
    }

    if (preg_match('/^\\d{4}-\\d{2}-\\d{2}$/', $date)) {
        $where .= " AND DATE(time_in) = ?";
        $types .= 's';
        $params[] = $date;
    }

    $countStmt = $conn->prepare("SELECT COUNT(*) AS total FROM login_logs WHERE $where");
    bind_dynamic_params($countStmt, $types, $params);
    $countStmt->execute();
    $total = (int)$countStmt->get_result()->fetch_assoc()['total'];
    $countStmt->close();

    $offset = ($page - 1) * $perPage;
    $query = "SELECT employee_id, full_name, role, time_in, time_out FROM login_logs WHERE $where ORDER BY time_in DESC LIMIT ? OFFSET ?";
    $stmt = $conn->prepare($query);
    $pageParams = $params;
    $pageTypes = $types . 'ii';
    $pageParams[] = $perPage;
    $pageParams[] = $offset;
    bind_dynamic_params($stmt, $pageTypes, $pageParams);
    $stmt->execute();
    $logs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    return ['logs' => $logs, 'total' => $total, 'pages' => max(1, (int)ceil($total / $perPage))];
}

function bind_dynamic_params(mysqli_stmt $stmt, string $types, array &$params): void {
    $references = [$types];
    foreach ($params as $key => &$value) {
        $references[] = &$value;
    }
    call_user_func_array([$stmt, 'bind_param'], $references);
}

function fetch_delete_requests(mysqli $conn): array {
    $sql = "SELECT dr.*, requester.username AS requester_username, target.user_id AS target_employee_id, target.username AS target_username
            FROM delete_requests dr
            JOIN users requester ON requester.id = dr.requested_by
            JOIN users target ON target.id = dr.target_user
            ORDER BY dr.created_at DESC";
    $result = $conn->query($sql);
    return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}
?>
