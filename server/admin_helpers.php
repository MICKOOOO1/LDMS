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

function fetch_accounts(mysqli $conn, string $employeeId = ''): array {
    if ($employeeId !== '') {
        $like = '%' . $employeeId . '%';
        $stmt = $conn->prepare("SELECT * FROM users WHERE user_id LIKE ? ORDER BY date_registered DESC");
        $stmt->bind_param("s", $like);
    } else {
        $stmt = $conn->prepare("SELECT * FROM users ORDER BY date_registered DESC");
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

function pending_registration_filter(string $month, string $date): array {
    $where = "role = 'user' AND account_status = 'pending'";
    $params = [];
    $types = '';

    if (preg_match('/^\\d{4}-\\d{2}$/', $month)) {
        $where .= " AND DATE_FORMAT(date_registered, '%Y-%m') = ?";
        $types .= 's';
        $params[] = $month;
    }

    if (preg_match('/^\\d{4}-\\d{2}-\\d{2}$/', $date)) {
        $where .= " AND DATE(date_registered) = ?";
        $types .= 's';
        $params[] = $date;
    }

    return [$where, $types, $params];
}

function fetch_pending_registration_count(mysqli $conn, string $month = '', string $date = ''): int {
    [$where, $types, $params] = pending_registration_filter($month, $date);
    $stmt = $conn->prepare("SELECT COUNT(*) AS total FROM users WHERE $where");
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
    $stmt = $conn->prepare("SELECT * FROM users WHERE $where ORDER BY date_registered ASC LIMIT ? OFFSET ?");
    $params[] = $perPage;
    $params[] = $offset;
    bind_dynamic_params($stmt, $types . 'ii', $params);
    $stmt->execute();
    $result = $stmt->get_result();
    $accounts = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    return $accounts;
}

function fetch_login_logs(mysqli $conn, string $role, string $month, string $date, int $page, int $perPage = 10): array {
    $page = max(1, $page);
    $allowedRoles = $role === 'super_admin' ? ['user', 'admin', 'super_admin'] : ($role === 'admin' ? ['user', 'admin'] : ['user']);
    $placeholders = implode(',', array_fill(0, count($allowedRoles), '?'));
    $where = "role IN ($placeholders)";
    $params = $allowedRoles;
    $types = str_repeat('s', count($allowedRoles));

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
