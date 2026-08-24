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

function admin_redirect_for_role(string $role): string {
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
