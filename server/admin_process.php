<?php
session_start();
require 'config.php';
require 'admin_helpers.php';

$alerts = [];
$role = $_SESSION['role'] ?? '';
$returnPage = is_string($_POST['return_page'] ?? null) ? $_POST['return_page'] : '';
$redirect = admin_redirect_for_role($role, $returnPage);

function finish_admin_action(array $alerts, string $redirect): void {
    $_SESSION['alerts'] = $alerts;
    header("Location: $redirect");
    exit;
}

if (!isset($_SESSION['user_id']) || !in_array($role, ['admin', 'super_admin'], true)) {
    header('Location: ../user/login.php');
    exit;
}

if (isset($_POST['change_status_btn'])) {
    if ($role === 'admin' && !admin_has_privilege('manage_registration')) {
        finish_admin_action([['type' => 'error', 'message' => 'You do not have registration status privileges.']], $redirect);
    }

    $targetId = (int)($_POST['target_id'] ?? 0);
    $status = $_POST['account_status'] ?? '';

    if (!in_array($status, ['pending', 'approved', 'blocked'], true)) {
        $alerts[] = ['type' => 'error', 'message' => 'Invalid account status.'];
    } elseif ($targetId === (int)$_SESSION['user_id']) {
        $alerts[] = ['type' => 'error', 'message' => 'You cannot change your own account status.'];
    } else {
        $stmt = $conn->prepare("UPDATE users SET account_status = ? WHERE id = ?");
        $stmt->bind_param("si", $status, $targetId);
        $alerts[] = $stmt->execute()
            ? ['type' => 'success', 'message' => 'Account status updated.']
            : ['type' => 'error', 'message' => 'Unable to update account status.'];
        $stmt->close();
    }

    finish_admin_action($alerts, $redirect);
}

if (isset($_POST['update_account_btn'])) {
    if ($role === 'admin' && !admin_has_privilege('update_accounts')) {
        finish_admin_action([['type' => 'error', 'message' => 'You do not have account update privileges.']], $redirect);
    }

    $targetId = (int)($_POST['target_id'] ?? 0);
    $firstName = trim($_POST['first_name'] ?? '');
    $middleName = trim($_POST['middle_name'] ?? '');
    $lastName = trim($_POST['last_name'] ?? '');
    $extensionName = trim($_POST['extension_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $purokStreet = trim($_POST['purok_street'] ?? '');
    $barangay = trim($_POST['barangay'] ?? '');
    $city = trim($_POST['city_municipality'] ?? '');
    $province = trim($_POST['province'] ?? '');
    $country = trim($_POST['country'] ?? '');
    $zipCode = trim($_POST['zip_code'] ?? '');

    if ($targetId <= 0 || $firstName === '' || $lastName === '' || $email === '' || $username === '') {
        $alerts[] = ['type' => 'error', 'message' => 'Required account fields are missing.'];
    } else {
        $stmt = $conn->prepare("UPDATE users SET first_name=?, middle_name=?, last_name=?, extension_name=?, email=?, username=?, purok_street=?, barangay=?, city_municipality=?, province=?, country=?, zip_code=? WHERE id=?");
        $stmt->bind_param("ssssssssssssi", $firstName, $middleName, $lastName, $extensionName, $email, $username, $purokStreet, $barangay, $city, $province, $country, $zipCode, $targetId);
        $alerts[] = $stmt->execute()
            ? ['type' => 'success', 'message' => 'Account information updated.']
            : ['type' => 'error', 'message' => 'Unable to update account information.'];
        $stmt->close();
    }

    finish_admin_action($alerts, $redirect);
}

if (isset($_POST['save_privileges_btn'])) {
    if ($role !== 'super_admin') {
        $alerts[] = ['type' => 'error', 'message' => 'Only the super admin can give privileges.'];
        finish_admin_action($alerts, $redirect);
    }

    $targetId = (int)($_POST['target_id'] ?? 0);
    $allowed = ['manage_registration', 'update_accounts'];
    $selected = array_values(array_intersect($_POST['privileges'] ?? [], $allowed));
    $privileges = implode(',', $selected);

    $stmt = $conn->prepare("UPDATE users SET privileges = ? WHERE id = ? AND role = 'admin'");
    $stmt->bind_param("si", $privileges, $targetId);
    $alerts[] = $stmt->execute()
        ? ['type' => 'success', 'message' => 'Privileges updated.']
        : ['type' => 'error', 'message' => 'Unable to update privileges.'];
    $stmt->close();

    finish_admin_action($alerts, $redirect);
}

if (isset($_POST['delete_account_btn'])) {
    if ($role !== 'super_admin') {
        $alerts[] = ['type' => 'error', 'message' => 'Only the super admin can delete accounts.'];
        finish_admin_action($alerts, $redirect);
    }

    $targetId = (int)($_POST['target_id'] ?? 0);
    if ($targetId === (int)$_SESSION['user_id']) {
        $alerts[] = ['type' => 'error', 'message' => 'You cannot delete your own account.'];
    } else {
        $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
        $stmt->bind_param("i", $targetId);
        $alerts[] = $stmt->execute()
            ? ['type' => 'success', 'message' => 'Account deleted.']
            : ['type' => 'error', 'message' => 'Unable to delete account.'];
        $stmt->close();
    }

    finish_admin_action($alerts, $redirect);
}

if (isset($_POST['request_delete_btn'])) {
    finish_admin_action([['type' => 'error', 'message' => 'Only the super admin can delete accounts.']], $redirect);

    if ($role === 'admin' && !admin_has_privilege('request_delete')) {
        finish_admin_action([['type' => 'error', 'message' => 'You do not have delete request privileges.']], $redirect);
    }

    $targetId = (int)($_POST['target_id'] ?? 0);
    $reason = trim($_POST['reason'] ?? '');

    if ($reason === '') {
        $alerts[] = ['type' => 'error', 'message' => 'Please enter a reason for deletion.'];
        finish_admin_action($alerts, $redirect);
    }

    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->bind_param("i", $targetId);
    $stmt->execute();
    $target = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$target) {
        $alerts[] = ['type' => 'error', 'message' => 'Account not found.'];
    } elseif ($target['role'] === 'super_admin') {
        $alerts[] = ['type' => 'error', 'message' => 'Administrators cannot request deletion of a super admin account.'];
    } else {
        $snapshot = json_encode($target, JSON_PRETTY_PRINT);
        $requester = (int)$_SESSION['user_id'];
        $stmt = $conn->prepare("INSERT INTO delete_requests (requested_by, target_user, reason, user_snapshot) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("iiss", $requester, $targetId, $reason, $snapshot);
        $alerts[] = $stmt->execute()
            ? ['type' => 'success', 'message' => 'Delete request sent to the super admin.']
            : ['type' => 'error', 'message' => 'Unable to send delete request.'];
        $stmt->close();
    }

    finish_admin_action($alerts, $redirect);
}

if (isset($_POST['review_delete_request_btn'])) {
    if ($role !== 'super_admin') {
        $alerts[] = ['type' => 'error', 'message' => 'Only the super admin can review delete requests.'];
        finish_admin_action($alerts, $redirect);
    }

    $requestId = (int)($_POST['request_id'] ?? 0);
    $decision = $_POST['decision'] ?? '';

    $stmt = $conn->prepare("SELECT target_user FROM delete_requests WHERE id = ? AND request_status = 'pending'");
    $stmt->bind_param("i", $requestId);
    $stmt->execute();
    $request = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$request || !in_array($decision, ['approved', 'rejected'], true)) {
        $alerts[] = ['type' => 'error', 'message' => 'Invalid delete request decision.'];
    } else {
        if ($decision === 'approved') {
            $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
            $stmt->bind_param("i", $request['target_user']);
            $stmt->execute();
            $stmt->close();
        }

        $reviewer = (int)$_SESSION['user_id'];
        $stmt = $conn->prepare("UPDATE delete_requests SET request_status = ?, reviewed_by = ?, reviewed_at = NOW() WHERE id = ?");
        $stmt->bind_param("sii", $decision, $reviewer, $requestId);
        $alerts[] = $stmt->execute()
            ? ['type' => 'success', 'message' => 'Delete request reviewed.']
            : ['type' => 'error', 'message' => 'Unable to review delete request.'];
        $stmt->close();
    }

    finish_admin_action($alerts, $redirect);
}

if (isset($_POST['create_staff_btn'])) {
    if ($role !== 'super_admin') {
        $alerts[] = ['type' => 'error', 'message' => 'Only the super admin can create admin accounts.'];
        finish_admin_action($alerts, $redirect);
    }

    $newRole = $_POST['role'] ?? 'admin';
    $userId = trim($_POST['id_number'] ?? '');
    $firstName = trim($_POST['first_name'] ?? '');
    $lastName = trim($_POST['last_name'] ?? '');
    $sex = $_POST['sex'] ?? '';
    $email = trim($_POST['email'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!in_array($newRole, ['admin', 'super_admin'], true) || $userId === '' || $firstName === '' || $lastName === '' || $sex === '' || $email === '' || $username === '' || $password === '') {
        $alerts[] = ['type' => 'error', 'message' => 'Please complete all required admin account fields.'];
    } elseif (!preg_match('/^\d{4}-\d{4}$/', $userId)) {
        $alerts[] = ['type' => 'error', 'message' => 'Employee ID must use xxxx-xxxx format.'];
    } else {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $dob = $_POST['dob'] ?: '1990-01-01';
        $age = (int)($_POST['age'] ?: 18);
        $privileges = $newRole === 'super_admin' ? 'manage_accounts,manage_privileges,delete_accounts' : 'manage_registration,update_accounts';
        $stmt = $conn->prepare("INSERT INTO users (user_id, first_name, last_name, dob, age, sex, email, username, password_hash, role, account_status, privileges, purok_street, barangay, city_municipality, province, country, zip_code) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'approved', ?, 'Campus', 'CSUCC', 'Cabadbaran', 'Agusan Del Norte', 'Philippines', '8605')");
        $stmt->bind_param("ssssissssss", $userId, $firstName, $lastName, $dob, $age, $sex, $email, $username, $hash, $newRole, $privileges);
        $alerts[] = $stmt->execute()
            ? ['type' => 'success', 'message' => 'Admin account created.']
            : ['type' => 'error', 'message' => 'Unable to create admin account: ' . $stmt->error];
        $stmt->close();
    }

    finish_admin_action($alerts, $redirect);
}

finish_admin_action([['type' => 'error', 'message' => 'No admin action selected.']], $redirect);
?>
