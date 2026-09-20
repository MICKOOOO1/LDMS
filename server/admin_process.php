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

require_admin_role(['admin', 'super_admin']);

if (isset($_POST['update_own_profile_btn'])) {
    $userId = (int)$_SESSION['user_id'];
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
    $profilePicture = null;

    if ($firstName === '' || $lastName === '' || $email === '' || $username === '') {
        $alerts[] = ['type' => 'error', 'message' => 'First name, last name, email, and username are required.'];
    }

    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $alerts[] = ['type' => 'error', 'message' => 'Please enter a valid email address.'];
    }

    if (empty($alerts)) {
        $stmt = $conn->prepare("SELECT id FROM users WHERE (email = ? OR username = ?) AND id <> ? LIMIT 1");
        $stmt->bind_param("ssi", $email, $username, $userId);
        $stmt->execute();
        $duplicate = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($duplicate) {
            $alerts[] = ['type' => 'error', 'message' => 'Email or username is already used by another account.'];
        }
    }

    if (empty($alerts) && isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] !== UPLOAD_ERR_NO_FILE) {
        $file = $_FILES['profile_picture'];
        $allowedTypes = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

        if ($file['error'] !== UPLOAD_ERR_OK) {
            $alerts[] = ['type' => 'error', 'message' => 'Profile picture upload failed. Please try again.'];
        } elseif ($file['size'] > 2 * 1024 * 1024) {
            $alerts[] = ['type' => 'error', 'message' => 'Profile picture must be 2MB or smaller.'];
        } else {
            $imageInfo = getimagesize($file['tmp_name']);
            $mimeType = $imageInfo['mime'] ?? '';

            if (!isset($allowedTypes[$mimeType])) {
                $alerts[] = ['type' => 'error', 'message' => 'Profile picture must be JPG, PNG, or WEBP.'];
            } else {
                $uploadDir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'profiles';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0775, true);
                }

                $fileName = 'profile_' . $userId . '_' . time() . '.' . $allowedTypes[$mimeType];
                $destination = $uploadDir . DIRECTORY_SEPARATOR . $fileName;

                if (move_uploaded_file($file['tmp_name'], $destination)) {
                    $profilePicture = 'assets/uploads/profiles/' . $fileName;
                } else {
                    $alerts[] = ['type' => 'error', 'message' => 'Unable to save the profile picture.'];
                }
            }
        }
    }

    if (empty($alerts)) {
        if ($profilePicture !== null) {
            $stmt = $conn->prepare("UPDATE users SET first_name=?, middle_name=?, last_name=?, extension_name=?, email=?, username=?, purok_street=?, barangay=?, city_municipality=?, province=?, country=?, zip_code=?, profile_picture=? WHERE id=?");
            $stmt->bind_param("sssssssssssssi", $firstName, $middleName, $lastName, $extensionName, $email, $username, $purokStreet, $barangay, $city, $province, $country, $zipCode, $profilePicture, $userId);
        } else {
            $stmt = $conn->prepare("UPDATE users SET first_name=?, middle_name=?, last_name=?, extension_name=?, email=?, username=?, purok_street=?, barangay=?, city_municipality=?, province=?, country=?, zip_code=? WHERE id=?");
            $stmt->bind_param("ssssssssssssi", $firstName, $middleName, $lastName, $extensionName, $email, $username, $purokStreet, $barangay, $city, $province, $country, $zipCode, $userId);
        }

        if ($stmt->execute()) {
            $_SESSION['name'] = $firstName;
            $alerts[] = ['type' => 'success', 'message' => 'Profile updated successfully.'];
        } else {
            $alerts[] = ['type' => 'error', 'message' => 'Unable to update profile. Please try again.'];
        }
        $stmt->close();
    }

    finish_admin_action($alerts, $redirect);
}

if (isset($_POST['change_status_btn']) || isset($_POST['block_account_btn']) || isset($_POST['unblock_account_btn'])) {
    if ($role === 'admin' && !admin_has_privilege('manage_registration')) {
        finish_admin_action([['type' => 'error', 'message' => 'You do not have registration status privileges.']], $redirect);
    }

    $targetId = (int)($_POST['target_id'] ?? 0);
    $status = $_POST['account_status'] ?? '';
    if (isset($_POST['block_account_btn'])) {
        $status = 'blocked';
    } elseif (isset($_POST['unblock_account_btn'])) {
        $status = 'approved';
    }

    // Check if target is in pending_registrations
    $pendingStmt = $conn->prepare("SELECT * FROM pending_registrations WHERE id = ?");
    $pendingStmt->bind_param("i", $targetId);
    $pendingStmt->execute();
    $pendingResult = $pendingStmt->get_result();
    $pending = $pendingResult->fetch_assoc();
    $pendingStmt->close();

    if ($pending) {
        // Handle pending registration approval/rejection
        if (!in_array($status, ['approved', 'blocked'], true)) {
            $alerts[] = ['type' => 'error', 'message' => 'Invalid action for pending registration.'];
        } else {
            if ($status === 'approved') {
                // Move to users table
                $fullName = trim($pending['first_name'] . ' ' . $pending['last_name']);
                $stmt = $conn->prepare("INSERT INTO users (user_id, first_name, middle_name, last_name, extension_name, dob, age, sex, email, username, password_hash, role, account_status, setup_completed, purok_street, barangay, city_municipality, province, country, zip_code) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'user', 'approved', 0, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("ssssssissssssssss", $pending['user_id'], $pending['first_name'], $pending['middle_name'], $pending['last_name'], $pending['extension_name'], $pending['dob'], $pending['age'], $pending['sex'], $pending['email'], $pending['username'], $pending['password_hash'], $pending['purok_street'], $pending['barangay'], $pending['city_municipality'], $pending['province'], $pending['country'], $pending['zip_code']);
                
                if ($stmt->execute()) {
                    $newUserDbId = $conn->insert_id;
                    $stmt->close();

                    // Copy the security questions answered at registration so forgot-password works.
                    $securityEntries = [
                        [$pending['security_question_1'] ?? null, $pending['security_answer_hash_1'] ?? null],
                        [$pending['security_question_2'] ?? null, $pending['security_answer_hash_2'] ?? null],
                        [$pending['security_question_3'] ?? null, $pending['security_answer_hash_3'] ?? null],
                    ];
                    $securityEntries = array_values(array_filter($securityEntries, fn($entry) => !empty($entry[0]) && !empty($entry[1])));
                    if (!empty($securityEntries)) {
                        $secStmt = $conn->prepare("INSERT INTO security_questions (user_id, question_code, answer_hash) VALUES (?,?,?)");
                        foreach ($securityEntries as $entry) {
                            $secStmt->bind_param("sss", $pending['user_id'], $entry[0], $entry[1]);
                            $secStmt->execute();
                        }
                        $secStmt->close();
                    }

                    // Mark pending as approved
                    $updateStmt = $conn->prepare("UPDATE pending_registrations SET status = 'approved', reviewed_by = ?, reviewed_at = NOW() WHERE id = ?");
                    $updateStmt->bind_param("ii", $_SESSION['user_id'], $targetId);
                    $updateStmt->execute();
                    $updateStmt->close();
                    
                    // Send activation OTP email
                    create_otp($conn, $newUserDbId, $pending['email'], 'account_activation', $fullName);
                    
                    $alerts[] = ['type' => 'success', 'message' => 'Registration approved. Activation code sent to user email.'];
                } else {
                    $alerts[] = ['type' => 'error', 'message' => 'Failed to approve registration: ' . $stmt->error];
                }
                $stmt->close();
            } else {
                // Reject/block
                $updateStmt = $conn->prepare("UPDATE pending_registrations SET status = 'rejected', reviewed_by = ?, reviewed_at = NOW() WHERE id = ?");
                $updateStmt->bind_param("ii", $_SESSION['user_id'], $targetId);
                $updateStmt->execute();
                $updateStmt->close();
                $alerts[] = ['type' => 'success', 'message' => 'Registration rejected.'];
            }
        }
    } else {
        // Existing logic for users table
        if (!in_array($status, ['pending', 'approved', 'blocked'], true)) {
            $alerts[] = ['type' => 'error', 'message' => 'Invalid account status.'];
        } elseif ($targetId === (int)$_SESSION['user_id']) {
            $alerts[] = ['type' => 'error', 'message' => 'You cannot change your own account status.'];
        } else {
            $targetStmt = $conn->prepare("SELECT role FROM users WHERE id = ?");
            $targetStmt->bind_param("i", $targetId);
            $targetStmt->execute();
            $target = $targetStmt->get_result()->fetch_assoc();
            $targetStmt->close();

            if (!$target) {
                $alerts[] = ['type' => 'error', 'message' => 'Account not found.'];
            } elseif ($role === 'admin' && $target['role'] === 'super_admin') {
                $alerts[] = ['type' => 'error', 'message' => 'Administrators cannot change a super admin account.'];
            } else {
                $stmt = $conn->prepare("UPDATE users SET account_status = ? WHERE id = ?");
                $stmt->bind_param("si", $status, $targetId);
                $alerts[] = $stmt->execute()
                    ? ['type' => 'success', 'message' => $status === 'blocked' ? 'Account blocked.' : 'Account unblocked and approved.']
                    : ['type' => 'error', 'message' => 'Unable to update account status.'];
                $stmt->close();
            }
        }
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
    if ($role !== 'super_admin' && !admin_has_privilege('manage_privileges')) {
        $alerts[] = ['type' => 'error', 'message' => 'You do not have permission to manage privileges.'];
        finish_admin_action($alerts, $redirect);
    }

    $targetId = (int)($_POST['target_id'] ?? 0);
    $allowed = ['manage_registration', 'update_accounts', 'request_delete'];
    $selected = array_values(array_intersect($_POST['privileges'] ?? [], $allowed));
    $privileges = implode(',', $selected);

    $stmt = $conn->prepare("UPDATE users SET privileges = ? WHERE id = ? AND role IN ('admin', 'super_admin')");
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
        $duplicateStmt = $conn->prepare("SELECT user_id, email, username FROM users WHERE user_id = ? OR email = ? OR username = ? LIMIT 1");
        $duplicateStmt->bind_param("sss", $userId, $email, $username);
        $duplicateStmt->execute();
        $duplicate = $duplicateStmt->get_result()->fetch_assoc();
        $duplicateStmt->close();

        if ($duplicate) {
            if ($duplicate['email'] === $email) {
                $alerts[] = ['type' => 'error', 'message' => 'Email address is already registered.'];
            } elseif ($duplicate['username'] === $username) {
                $alerts[] = ['type' => 'error', 'message' => 'Username is already registered.'];
            } else {
                $alerts[] = ['type' => 'error', 'message' => 'Employee ID is already registered.'];
            }
            finish_admin_action($alerts, $redirect);
        }

        $hash = password_hash($password, PASSWORD_DEFAULT);
        $dob = $_POST['dob'] ?: '1990-01-01';
        $age = (int)($_POST['age'] ?: 18);
        $privileges = $newRole === 'super_admin' ? 'manage_accounts,manage_privileges,delete_accounts' : 'manage_registration,update_accounts';
        $fullName = trim("$firstName $lastName");
        
        // Create account with pending status - will be activated via OTP
        $stmt = $conn->prepare("INSERT INTO users (user_id, first_name, last_name, dob, age, sex, email, username, password_hash, role, account_status, privileges, setup_completed, purok_street, barangay, city_municipality, province, country, zip_code) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?, 0, 'Campus', 'CSUCC', 'Cabadbaran', 'Agusan Del Norte', 'Philippines', '8605')");
        $stmt->bind_param("ssssissssss", $userId, $firstName, $lastName, $dob, $age, $sex, $email, $username, $hash, $newRole, $privileges);
        
        try {
            $created = $stmt->execute();
        } catch (mysqli_sql_exception $e) {
            $created = false;
            $dbError = $e->getMessage();
        }

        if ($created) {
            $newUserId = $conn->insert_id;
            $stmt->close();
            
            // Generate and send OTP for account activation
            $otp = create_otp($conn, $newUserId, $email, 'account_activation', $fullName);
            if ($otp) {
                $alerts[] = ['type' => 'success', 'message' => "Admin account created. An activation code has been sent to $email. The account will be activated once the code is verified."];
            } else {
                $alerts[] = ['type' => 'warning', 'message' => "Admin account created but failed to send activation email. Please contact the user to verify their account."];
            }
        } else {
            $error = $dbError ?? $stmt->error;
            if (str_contains($error, "Duplicate entry")) {
                $alerts[] = ['type' => 'error', 'message' => 'Account creation failed because the ID, email, or username already exists.'];
            } else {
                $alerts[] = ['type' => 'error', 'message' => 'Unable to create admin account: ' . $error];
            }
            $stmt->close();
        }
    }

    finish_admin_action($alerts, $redirect);
}

finish_admin_action([['type' => 'error', 'message' => 'No admin action selected.']], $redirect);
?>
