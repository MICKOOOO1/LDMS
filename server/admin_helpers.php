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
}

function admin_redirect_for_role(string $role, string $returnPage = ''): string {
    if ($role === 'super_admin' && in_array($returnPage, ['pending.php', 'accounts.php', 'logs.php', 'my_account.php'], true)) {
        return '../super%20admin/' . $returnPage;
    }

    if ($role === 'admin' && in_array($returnPage, ['pending.php', 'user_management.php', 'logs.php', 'my_account.php'], true)) {
        return '../admin/' . $returnPage;
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
        'pending' => "SELECT COUNT(*) AS total FROM users WHERE role = 'user' AND account_status = 'pending'",
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

function fetch_accounts(mysqli $conn, string $employeeId = '', string $search = '', string $role = '', string $status = '', string $sort = 'newest'): array {
    $conditions = [];
    $params = [];
    $types = '';

    if ($employeeId !== '') {
        $conditions[] = 'user_id LIKE ?';
        $params[] = '%' . $employeeId . '%';
        $types .= 's';
    }

    if ($search !== '') {
        $conditions[] = '(first_name LIKE ? OR middle_name LIKE ? OR last_name LIKE ? OR email LIKE ? OR username LIKE ?)';
        $searchLike = '%' . $search . '%';
        array_push($params, $searchLike, $searchLike, $searchLike, $searchLike, $searchLike);
        $types .= 'sssss';
    }

    if (in_array($role, ['user', 'admin', 'super_admin'], true)) {
        $conditions[] = 'role = ?';
        $params[] = $role;
        $types .= 's';
    }

    if (in_array($status, ['pending', 'approved', 'blocked'], true)) {
        $conditions[] = 'account_status = ?';
        $params[] = $status;
        $types .= 's';
    }

    $where = $conditions ? ' WHERE ' . implode(' AND ', $conditions) : '';
    $orderBy = $sort === 'oldest' ? 'date_registered ASC' : ($sort === 'name' ? 'last_name ASC, first_name ASC' : 'date_registered DESC');
    $stmt = $conn->prepare("SELECT * FROM users$where ORDER BY $orderBy");
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

function fetch_pending_registrations(mysqli $conn): array {
    $stmt = $conn->prepare("SELECT * FROM users WHERE role = 'user' AND account_status = 'pending' ORDER BY date_registered ASC");
    $stmt->execute();
    $result = $stmt->get_result();
    $accounts = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    return $accounts;
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
