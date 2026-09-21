<?php
session_start();
require 'config.php';
require 'admin_helpers.php';

$alerts = [];

function auth_allowed_security_questions(): array {
    return [
        'first_pet',
        'childhood_nickname',
        'favorite_subject',
        'mothers_maiden_name',
        'dream_job',
        'first_travel',
        'first_school',
        'childhood_friend',
        'birth_city',
    ];
}

function auth_finish_security_setup(mysqli $conn, int $numericUserId, string $publicUserId, array $post, array &$alerts): bool {
    $q1 = $post['question_1'] ?? '';
    $q2 = $post['question_2'] ?? '';
    $q3 = $post['question_3'] ?? '';
    $a1 = trim($post['answer_1'] ?? '');
    $a2 = trim($post['answer_2'] ?? '');
    $a3 = trim($post['answer_3'] ?? '');
    $questions = [$q1, $q2, $q3];

    if (in_array('', $questions, true) || $a1 === '' || $a2 === '' || $a3 === '') {
        $alerts[] = ['type' => 'error', 'message' => 'All security questions and answers are required.'];
    }

    if (count(array_unique($questions)) < 3) {
        $alerts[] = ['type' => 'error', 'message' => 'Security questions must be different from each other.'];
    }

    if (count(array_diff($questions, auth_allowed_security_questions())) > 0) {
        $alerts[] = ['type' => 'error', 'message' => 'Please choose valid security questions.'];
    }

    if (!empty($alerts)) {
        return false;
    }

    $deleteStmt = $conn->prepare("DELETE FROM security_questions WHERE user_id = ?");
    $deleteStmt->bind_param("s", $publicUserId);
    $deleteStmt->execute();
    $deleteStmt->close();

    $insertStmt = $conn->prepare("INSERT INTO security_questions (user_id, question_code, answer_hash) VALUES (?, ?, ?)");
    foreach ([[$q1, $a1], [$q2, $a2], [$q3, $a3]] as $entry) {
        [$question, $answer] = $entry;
        $hash = password_hash($answer, PASSWORD_DEFAULT);
        $insertStmt->bind_param("sss", $publicUserId, $question, $hash);
        if (!$insertStmt->execute()) {
            $alerts[] = ['type' => 'error', 'message' => 'Failed to save security questions. Please try again.'];
            $insertStmt->close();
            return false;
        }
    }
    $insertStmt->close();

    $setupStmt = $conn->prepare("UPDATE users SET setup_completed = 1 WHERE id = ?");
    $setupStmt->bind_param("i", $numericUserId);
    $saved = $setupStmt->execute();
    $setupStmt->close();

    if (!$saved) {
        $alerts[] = ['type' => 'error', 'message' => 'Unable to complete account setup.'];
        return false;
    }

    return true;
}

// ================= COMPLETE ACCOUNT SETUP AFTER ACTIVATION =================
if (isset($_POST['complete_account_setup_btn']) || (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['question_1'], $_POST['answer_1']) && isset($_SESSION['activation_setup_user_id']))) {
    $setupUserId = (int)($_SESSION['activation_setup_user_id'] ?? 0);
    if (!$setupUserId) {
        $_SESSION['alerts'] = [['type' => 'error', 'message' => 'Setup session expired. Please log in again.']];
        header('Location: ../user/login.php');
        exit;
    }

    $stmt = $conn->prepare("SELECT user_id FROM users WHERE id = ? AND account_status = 'approved' LIMIT 1");
    $stmt->bind_param("i", $setupUserId);
    $stmt->execute();
    $account = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$account) {
        unset($_SESSION['activation_setup_user_id']);
        $_SESSION['alerts'] = [['type' => 'error', 'message' => 'Account setup is unavailable. Please log in again.']];
        header('Location: ../user/login.php');
        exit;
    }

    $firstName = trim($_POST['first_name'] ?? '');
    $middleName = trim($_POST['middle_name'] ?? '');
    $lastName = trim($_POST['last_name'] ?? '');
    $extensionName = trim($_POST['extension_name'] ?? '');
    $dob = $_POST['dob'] ?? '';
    $age = (int)($_POST['age'] ?? 0);
    $sex = $_POST['sex'] ?? '';
    $email = trim($_POST['email'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $purokStreet = trim($_POST['purok_street'] ?? '');
    $barangay = trim($_POST['barangay'] ?? '');
    $city = trim($_POST['city_municipality'] ?? '');
    $province = trim($_POST['province'] ?? '');
    $country = trim($_POST['country'] ?? '');
    $zipCode = trim($_POST['zip_code'] ?? '');

    if ($firstName === '' || $lastName === '' || $dob === '' || $age < 18 || !in_array($sex, ['male', 'female'], true) || $email === '' || $username === '' || $purokStreet === '' || $barangay === '' || $city === '' || $province === '' || $country === '' || $zipCode === '') {
        $alerts[] = ['type' => 'error', 'message' => 'Please complete all profile fields.'];
    }

    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $alerts[] = ['type' => 'error', 'message' => 'Please enter a valid email address.'];
    }

    if (empty($alerts)) {
        $duplicateStmt = $conn->prepare("SELECT id FROM users WHERE (email = ? OR username = ?) AND id <> ? LIMIT 1");
        $duplicateStmt->bind_param("ssi", $email, $username, $setupUserId);
        $duplicateStmt->execute();
        $duplicate = $duplicateStmt->get_result()->fetch_assoc();
        $duplicateStmt->close();

        if ($duplicate) {
            $alerts[] = ['type' => 'error', 'message' => 'Email or username is already used by another account.'];
        }
    }

    if (empty($alerts)) {
        $updateStmt = $conn->prepare("UPDATE users SET first_name=?, middle_name=?, last_name=?, extension_name=?, dob=?, age=?, sex=?, email=?, purok_street=?, barangay=?, city_municipality=?, province=?, country=?, zip_code=? WHERE id=?");
        $updateStmt->bind_param("sssssissssssssi", $firstName, $middleName, $lastName, $extensionName, $dob, $age, $sex, $email, $purokStreet, $barangay, $city, $province, $country, $zipCode, $setupUserId);
        if (!$updateStmt->execute()) {
            $alerts[] = ['type' => 'error', 'message' => 'Unable to save profile details.'];
        }
        $updateStmt->close();
    }

    if (empty($alerts) && auth_finish_security_setup($conn, $setupUserId, $account['user_id'], $_POST, $alerts)) {
        unset($_SESSION['activation_setup_user_id']);
        $_SESSION['alerts'] = [['type' => 'success', 'message' => 'Account setup completed. You can now log in.']];
        header('Location: ../user/login.php');
        exit;
    }

    $_SESSION['alerts'] = $alerts;
    header('Location: ../user/complete_account_setup.php');
    exit;
}

// ================= USER PROFILE UPDATE =================
if (isset($_POST['update_profile_btn'])) {
    if (!isset($_SESSION['user_id'])) {
        header('Location: ../user/login.php');
        exit;
    }

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
            $stmt = $conn->prepare("UPDATE users SET first_name=?, middle_name=?, last_name=?, extension_name=?, email=?, purok_street=?, barangay=?, city_municipality=?, province=?, country=?, zip_code=?, profile_picture=? WHERE id=?");
            $stmt->bind_param("sssssssssssssi", $firstName, $middleName, $lastName, $extensionName, $email, $purokStreet, $barangay, $city, $province, $country, $zipCode, $profilePicture, $userId);
        } else {
            $stmt = $conn->prepare("UPDATE users SET first_name=?, middle_name=?, last_name=?, extension_name=?, email=?, purok_street=?, barangay=?, city_municipality=?, province=?, country=?, zip_code=? WHERE id=?");
            $stmt->bind_param("sssssssssssi", $firstName, $middleName, $lastName, $extensionName, $email, $purokStreet, $barangay, $city, $province, $country, $zipCode, $userId);
        }

        if ($stmt->execute()) {
            $_SESSION['name'] = $firstName;
            $alerts[] = ['type' => 'success', 'message' => 'Profile updated successfully.'];
        } else {
            $alerts[] = ['type' => 'error', 'message' => 'Unable to update profile. Please try again.'];
        }
        $stmt->close();
    }

    $_SESSION['alerts'] = $alerts;
    header('Location: ../user/my_account.php');
    exit;
}

// ================= LOGIN =================
if (isset($_POST['login_btn'])) {
    if (!isset($_SESSION['failed_attempts'])) {
        $_SESSION['failed_attempts'] = 0;
    }
    // Track only credential failures (wrong username/password) for UI hints like showing Forgot Password link
    if (!isset($_SESSION['cred_failed_attempts'])) {
        $_SESSION['cred_failed_attempts'] = 0;
    }

    if (isset($_SESSION['lockout_until']) && time() < $_SESSION['lockout_until']) {
        $remaining = $_SESSION['lockout_until'] - time();
        $alerts[] = ['type' => 'error', 'message' => 'Login temporarily disabled. Try again in ' . $remaining . ' seconds.'];
        $_SESSION['alerts'] = $alerts;
        header("Location: ../user/login.php");
        exit;
    }
    $username = trim($_POST['name']);
    $password = $_POST['password'];

    if (empty($username) || empty($password)) {
        $alerts[] = ['type' => 'error', 'message' => 'Username and password are required.'];
        $_SESSION['last_failure_type'] = 'empty';
    } else {
        $stmt = $conn->prepare("SELECT id, user_id, first_name, middle_name, last_name, extension_name, email, username, password_hash, role, account_status, privileges, setup_completed FROM users WHERE username = ? OR email = ?");
        $stmt->bind_param("ss", $username, $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            $alerts[] = ['type' => 'error', 'message' => 'Invalid username or password.'];
            // Count only credential-based failures
            $_SESSION['cred_failed_attempts'] = ($_SESSION['cred_failed_attempts'] ?? 0) + 1;
            $_SESSION['last_failure_type'] = 'credential';
        } else {
            $user = $result->fetch_assoc();
            if (password_verify($password, $user['password_hash'])) {
                if ($user['account_status'] === 'pending') {
                    // Redirect to OTP verification for account activation
                    $_SESSION['otp_email'] = $user['email'];
                    $_SESSION['otp_purpose'] = 'account_activation';
                    $_SESSION['otp_user_id'] = $user['id'];
                    header("Location: ../user/verify_otp.php?step=otp");
                    exit;
                } elseif ($user['account_status'] === 'blocked') {
                    $alerts[] = ['type' => 'error', 'message' => 'Your account is blocked. Please contact the administrator.'];
                    $_SESSION['last_failure_type'] = 'status';
                } elseif ((int)($user['setup_completed'] ?? 1) === 0) {
                    $_SESSION['activation_setup_user_id'] = (int)$user['id'];
                    header("Location: ../user/complete_account_setup.php");
                    exit;
                } else {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['name'] = $user['first_name'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['privileges'] = $user['privileges'] ?? '';
                $fullName = trim(($user['first_name'] ?? '') . ' ' . ($user['middle_name'] ?? '') . ' ' . ($user['last_name'] ?? '') . ' ' . ($user['extension_name'] ?? ''));
                $logStmt = $conn->prepare("INSERT INTO login_logs (user_id, employee_id, full_name, role, time_in) VALUES (?, ?, ?, ?, NOW())");
                $logStmt->bind_param("isss", $user['id'], $user['user_id'], $fullName, $user['role']);
                $logStmt->execute();
                $_SESSION['login_log_id'] = $conn->insert_id;
                $logStmt->close();
                $sessionToken = bin2hex(random_bytes(32));
                $_SESSION['session_token'] = $sessionToken;

                if ($user['role'] === 'super_admin') {
                    $conn->query("UPDATE login_logs SET time_out = NOW() WHERE role = 'super_admin' AND time_out IS NULL AND id <> " . (int)$_SESSION['login_log_id']);
                    $conn->query("DELETE FROM active_sessions WHERE role = 'super_admin'");
                } else {
                    $clearStmt = $conn->prepare("DELETE FROM active_sessions WHERE user_id = ?");
                    $clearStmt->bind_param("i", $user['id']);
                    $clearStmt->execute();
                    $clearStmt->close();
                }

                $activeStmt = $conn->prepare("INSERT INTO active_sessions (user_id, role, session_token, login_log_id, last_seen) VALUES (?, ?, ?, ?, NOW())");
                $activeStmt->bind_param("issi", $user['id'], $user['role'], $sessionToken, $_SESSION['login_log_id']);
                $activeStmt->execute();
                $activeStmt->close();
                $_SESSION['failed_attempts'] = 0;
                $_SESSION['cred_failed_attempts'] = 0;
                unset($_SESSION['lockout_until'], $_SESSION['last_failure_type']);
                $alerts[] = ['type' => 'success', 'message' => 'Login successful!'];
                $_SESSION['alerts'] = $alerts;
                if ($user['role'] === 'super_admin') {
                    header("Location: ../super%20admin/dashboard.php");
                } elseif ($user['role'] === 'admin') {
                    header("Location: ../admin/dashboard.php");
                } else {
                    header("Location: ../user/home.php");
                }
                exit;
                }
            } else {
                $alerts[] = ['type' => 'error', 'message' => 'Invalid password.'];
                // Count only credential-based failures
                $_SESSION['cred_failed_attempts'] = ($_SESSION['cred_failed_attempts'] ?? 0) + 1;
                $_SESSION['last_failure_type'] = 'credential';
            }
        }
    }

    if (!empty($alerts) && (!isset($_SESSION['user_id']))) {
        $_SESSION['failed_attempts'] = ($_SESSION['failed_attempts'] ?? 0) + 1;
        $fa = $_SESSION['failed_attempts'];
        // Lockout tiers:
        // 3 consecutive failures => 15s
        // 6 consecutive failures => 30s
        // 9 or more consecutive failures => always 60s thereafter
        if ($fa === 3) {
            $_SESSION['lockout_until'] = time() + 15;
            $alerts[] = ['type' => 'error', 'message' => 'Too many failed attempts. Login temporarily disabled.'];
        } elseif ($fa === 6) {
            $_SESSION['lockout_until'] = time() + 30;
            $alerts[] = ['type' => 'error', 'message' => 'Too many failed attempts. Login temporarily disabled.'];
        } elseif ($fa >= 9) {
            $_SESSION['lockout_until'] = time() + 60;
            $alerts[] = ['type' => 'error', 'message' => 'Too many failed attempts. Login temporarily disabled.'];
        }
    }

    $_SESSION['alerts'] = $alerts;
    header("Location: ../user/login.php");
    exit;
}

// Helper: detect 3 of the same letter in a row (e.g., ccc, EEE, ggg), case-insensitive, but allow III
function has_three_consecutive_letters($str) {
    if (strtoupper($str) === 'III') return false; // Exception for III
    $lower = strtolower($str);
    $len = strlen($lower);
    for ($i = 0; $i < $len - 2; $i++) {
        $a = $lower[$i];
        $b = $lower[$i + 1];
        $c = $lower[$i + 2];
        if ($a >= 'a' && $a <= 'z' && $a === $b && $b === $c) {
            return true;
        }
    }
    return false;
}

// Helper: validate Roman numeral
function is_valid_roman_numeral($str) {
    $roman_regex = '/^M{0,3}(CM|CD|D?C{0,3})(XC|XL|L?X{0,3})(IX|IV|V?I{0,3})$/i';
    return preg_match($roman_regex, $str);
}

// ================= REGISTRATION =================
if (isset($_POST['register_btn'])) {

    // Retrieve and sanitize input
    $user_id         = generate_next_id($conn, 'user');
    $first_name      = trim($_POST['first_name']);
    $middle_name     = trim($_POST['middle_name'] ?? '');
    $last_name       = trim($_POST['last_name']);
    $extension_name  = trim($_POST['extension_name'] ?? '');
    $dob             = $_POST['dob'];
    // Calculate age from DOB server-side to ensure accuracy
    $dob_date = new DateTime($dob);
    $today = new DateTime();
    $age = $today->diff($dob_date)->y;
    $sex             = $_POST['sex'];
    $username        = trim($_POST['username']);
    $email           = trim($_POST['email']);
    $password        = $_POST['password'];
    $re_password     = $_POST['re_password'];

    $purok_street     = trim($_POST['purok_street']);
    $barangay         = trim($_POST['barangay']);
    $city_municipality= trim($_POST['city_municipality']);
    $province         = trim($_POST['province']);
    $country          = trim($_POST['country']);
    $zip_code         = trim($_POST['zip_code']);

    // ================= BASIC VALIDATION =================
    if (empty($first_name) || empty($last_name) || empty($dob) || empty($sex) || empty($username) || empty($email) || empty($password) || empty($re_password) || empty($purok_street) || empty($barangay) || empty($city_municipality) || empty($province) || empty($country) || empty($zip_code)) {
        $alerts[] = ['type' => 'error', 'message' => 'All required fields must be filled out.'];
    }

    // Validate age
    if ($age < 18) {
        $alerts[] = ['type' => 'error', 'message' => 'You must be at least 18 years old.'];
    }

    // Password match
    if ($password !== $re_password) {
        $alerts[] = ['type' => 'error', 'message' => 'Passwords do not match.'];
    }

    // Name validations (First, Middle, Last, Extension)
    // Common: only letters and spaces (no digits/special), no triple-identical letters
    // first/middle/last: length 2–50, no double spaces, not all caps
    // extension: optional, if present allow Jr, Junior, Sr, Senior, or Roman numerals
    $nameFields = ['first_name' => $first_name, 'middle_name' => $middle_name, 'last_name' => $last_name, 'extension_name' => $extension_name];
    foreach ($nameFields as $field => $value) {
        if ($value !== '') {
            $label = ucfirst(str_replace('_', ' ', $field));

            if ($field === 'extension_name') {
                // Extension: Can be Sr, Jr, or Roman numerals (I-X)
                $allowed_values = ['Sr', 'Jr', 'I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X'];
                if (!in_array($value, $allowed_values)) {
                    $alerts[] = ['type' => 'error', 'message' => "$label must be Sr, Jr, or a Roman numeral (I-X)."];
                }
                // No need to check for three consecutive letters since we're using dropdown now

            } else {
                // First/Middle/Last
                // Only letters and spaces
                if (!preg_match("/^[A-Za-z\s]+$/", $value)) {
                    $alerts[] = ['type' => 'error', 'message' => "$label can only contain letters and spaces."];
                }

                // Length limits
                if (strlen($value) < 2 || strlen($value) > 50) {
                    $alerts[] = ['type' => 'error', 'message' => "$label must be between 2 and 50 characters."];
                }

                // No double spaces
                if (strpos($value, '  ') !== false) {
                    $alerts[] = ['type' => 'error', 'message' => "$label cannot contain double spaces."];
                }

                // Not all caps (allow spaces)
                if (preg_match('/^[A-Z\s]+$/', $value)) {
                    $alerts[] = ['type' => 'error', 'message' => "$label cannot be all capital letters."];
                }

                // Each word must start with a capital letter, rest lowercase
                if (!preg_match('/^[A-Z][a-z]*(\s[A-Z][a-z]*)*$/', $value)) {
                    $alerts[] = ['type' => 'error', 'message' => "Each word in $label must start with a capital letter."];
                }
            }
        }
    }

    // Address validation
    $addressFields = [
        'Purok/Street' => $purok_street,
        'Barangay' => $barangay,
        'City/Municipality' => $city_municipality,
        'Province' => $province,
        'Country' => $country,
        'Zip Code' => $zip_code
    ];

    foreach ($addressFields as $label => $value) {
        if (empty($value)) {
            $alerts[] = ['type' => 'error', 'message' => "$label is required."];
        } else {
            $isPurokField = ($label === 'Purok/Street');
            $isPurokWord = $isPurokField && preg_match('/^Purok-?/i', $value);
            $isPShortFormat = $isPurokField && preg_match('/^P\d+/', $value); // e.g., P3, P12
            $isPurokFormat = $isPurokWord || $isPShortFormat;
            $dynamicLabel = $isPurokField ? ($isPurokFormat ? 'Purok' : 'Street') : $label;

            if ($label === 'Zip Code') {
                // Exactly 4 digits
                if (!preg_match('/^[0-9]{4}$/', $value)) {
                    $alerts[] = ['type' => 'error', 'message' => 'Zip Code must contain exactly 4 digits.'];
                }
            } else {
                // No leading spaces
                if (preg_match('/^\s/', $value)) {
                    $alerts[] = ['type' => 'error', 'message' => "$label cannot start with a space."];
                }
                // Allow letters, digits, spaces, and basic punctuation
                elseif (preg_match('/[^a-zA-Z0-9\s.,-]/', $value)) {
                    $alerts[] = ['type' => 'error', 'message' => "$label contains invalid characters."];
                }
                // Words and numbers must be separated by spaces (no letter-digit adjacency),
                // EXCEPT for Purok patterns like "Purok-3B" or shorthand P# like "P3"
                elseif (!($label === 'Purok/Street' && (preg_match('/^Purok-/', $value) || preg_match('/^P\d+/', $value))) && preg_match('/[A-Za-z][0-9]|[0-9][A-Za-z]/', $value)) {
                    $alerts[] = ['type' => 'error', 'message' => "$dynamicLabel must have words and numbers separated by a space (e.g., 123 Street)."];
                } else {
                    if ($label === 'Purok/Street') {
                        // If user types P# (e.g., P3), treat as Purok attempt but require full word
                        if (preg_match('/^P\d+/', $value)) {
                            $alerts[] = ['type' => 'error', 'message' => 'Please write "Purok" completely (e.g., Purok-3B).'];
                        }
                        // If user types Purok- followed by a letter (e.g., Purok-B), require a number after the dash
                        elseif (preg_match('/^Purok-[A-Za-z]/', $value)) {
                            $alerts[] = ['type' => 'error', 'message' => 'Purok must have a number immediately after "Purok-" (e.g., Purok-3B).'];
                        }
                        // If user types Purok-# with more than one letter after the number (e.g., Purok-1Bqeqasd), disallow
                        elseif (preg_match('/^Purok-\d+[A-Za-z]{2,}/', $value)) {
                            $alerts[] = ['type' => 'error', 'message' => 'Purok can contain at most one letter after the number (e.g., Purok-3B).'];
                        }
                        // Case 1: Purok-# or Purok-#A (e.g., Purok-1, Purok-3B) with number up to 3 digits and <= 100
                        elseif (preg_match('/^Purok-\d+[A-Z]?$/', $value)) {
                            if (preg_match('/^Purok-(\d+)([A-Z]?)$/', $value, $m)) {
                                $numPart = $m[1];
                                $numVal = (int)$numPart;
                                if (strlen($numPart) > 3 || $numVal > 100) {
                                    $alerts[] = ['type' => 'error', 'message' => 'Purok number must be at most 3 digits and not exceed 100 (e.g., Purok-1, Purok-99, Purok-3B).'];
                                }
                            }
                            // otherwise valid, nothing more to check
                        }
                        // Case 2: starts with a number -> must be followed by a road type word after a space
                        elseif (preg_match('/^\d/', $value)) {
                            $parts = preg_split('/\s+/', $value);
                            $road = $parts[1] ?? '';

                            if ($road === '') {
                                $alerts[] = ['type' => 'error', 'message' => "$dynamicLabel that starts with a number must be followed by a road type (e.g., 123 Street)."];
                            } elseif (preg_match('/^[a-z]/', $road)) {
                                $alerts[] = ['type' => 'error', 'message' => "$dynamicLabel road type must start with a capital letter (e.g., 123 Street)."];
                            }
                        } else {
                            // Generic capitalized-words rule for normal street names
                            $normalized = preg_replace('/[.,-]/', ' ', $value);
                            // Allow either capitalized words or purely numeric words (e.g., "Purok 1")
                            if (!preg_match('/^(?:[A-Z][a-z0-9]*|\d+)(\s(?:[A-Z][a-z0-9]*|\d+))*$/', $normalized)) {
                                $alerts[] = ['type' => 'error', 'message' => "Each word in $dynamicLabel must start with a capital letter or be a number."];
                            }
                        }

                        // Each alphabetic word must have at least 2 letters
                        if (!empty($value)) {
                            $words = preg_split('/\s+/', trim($value));
                            foreach ($words as $w) {
                                if (preg_match('/^[A-Za-z]+$/', $w) && strlen($w) === 1) {
                                    $alerts[] = ['type' => 'error', 'message' => "$dynamicLabel cannot contain single-letter words."];
                                    break;
                                }
                            }
                        }
                    } else {
                        // Other address fields: capitalized words rule
                        $normalized = preg_replace('/[.,-]/', ' ', $value);
                        if (!preg_match('/^[A-Z][a-z0-9]*(\s[A-Z][a-z0-9]*)*$/', $normalized)) {
                            $alerts[] = ['type' => 'error', 'message' => "Each word in $label must start with a capital letter."];
                        }
                    }
                }
            }
        }
    }

    // ID number format: 4 digits, dash, 4 digits (e.g. 2023-0076)
    if (!preg_match('/^\d{4}-\d{4}$/', $user_id)) {
        $alerts[] = ['type' => 'error', 'message' => 'ID Number must be in the format xxxx-xxxx (e.g., 2023-0076).'];
    }

    // Username format checks (lowercase-only and single dot between)
    if (preg_match('/[^a-z.]/', $username)) {
        $alerts[] = ['type' => 'error', 'message' => 'Username must be lowercase letters only.'];
    } elseif (!preg_match('/^[a-z]+\.[a-z]+$/', $username)) {
        $alerts[] = ['type' => 'error', 'message' => 'Username must have one dot between names (e.g., juan.dela).'];
    }

    // Email length validation: 5–70 characters
    if (strlen($email) < 5 || strlen($email) > 70) {
        $alerts[] = ['type' => 'error', 'message' => 'Email Address must be between 5 and 70 characters.'];
    }

    // Check username, email uniqueness (both approved users and pending registrations)
    $stmt = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
    $stmt->bind_param("ss", $username, $email);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows > 0) {
        $alerts[] = ['type' => 'error', 'message' => 'Username or Email already exists.'];
    }
    $stmt->close();

    // Check user_id uniqueness (both approved users and pending registrations)
    if (empty($alerts)) {
        $idStmt = $conn->prepare("SELECT id FROM users WHERE user_id = ?");
        $idStmt->bind_param("s", $user_id);
        $idStmt->execute();
        $idStmt->store_result();
        if ($idStmt->num_rows > 0) {
            $alerts[] = ['type' => 'error', 'message' => 'ID Number already exists. Please try again.'];
        }
        $idStmt->close();
    }

    if (empty($alerts)) {
        $pendingIdStmt = $conn->prepare("SELECT id FROM pending_registrations WHERE user_id = ? AND status = 'pending'");
        $pendingIdStmt->bind_param("s", $user_id);
        $pendingIdStmt->execute();
        $pendingIdStmt->store_result();
        if ($pendingIdStmt->num_rows > 0) {
            $alerts[] = ['type' => 'error', 'message' => 'ID Number already has a pending registration. Please try again.'];
        }
        $pendingIdStmt->close();
    }

    if (empty($alerts)) {
        $pendingStmt = $conn->prepare("SELECT id FROM pending_registrations WHERE (username = ? OR email = ?) AND status = 'pending'");
        $pendingStmt->bind_param("ss", $username, $email);
        $pendingStmt->execute();
        $pendingStmt->store_result();
        if ($pendingStmt->num_rows > 0) {
            $alerts[] = ['type' => 'error', 'message' => 'Username or Email already has a pending registration.'];
        }
        $pendingStmt->close();
    }

    // ================= IF NO ERRORS, HOLD DATA IN SESSION THEN GO TO SECURITY QUESTIONS =================
    // Log all validation errors for debugging (remove in production)
    if (!empty($alerts)) {
        error_log("Registration validation errors: " . json_encode($alerts));
    }

    if (empty($alerts)) {
        $password_hash = password_hash($password, PASSWORD_DEFAULT);

        // Hold validated registration in session; nothing is saved until security questions are set.
        $_SESSION['pending_registration'] = [
            'user_id' => $user_id,
            'first_name' => $first_name,
            'middle_name' => $middle_name,
            'last_name' => $last_name,
            'extension_name' => $extension_name,
            'dob' => $dob,
            'age' => $age,
            'sex' => $sex,
            'username' => $username,
            'email' => $email,
            'password_hash' => $password_hash,
            'purok_street' => $purok_street,
            'barangay' => $barangay,
            'city_municipality' => $city_municipality,
            'province' => $province,
            'country' => $country,
            'zip_code' => $zip_code,
        ];
        $_SESSION['id_number'] = $user_id;
        $_SESSION['first_name'] = $first_name;
        header("Location: ../user/security_question.php");
        exit;
    }

    // If there are errors, show all of them
    if (!empty($alerts)) {
        $_SESSION['alerts'] = $alerts;
        header("Location: ../user/register.php");
        exit;
    }
}

if (isset($_POST['save_questions_btn']) || (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['question_1'], $_POST['answer_1'], $_POST['id_number']) && isset($_SESSION['pending_registration']))) {
    $pending = $_SESSION['pending_registration'] ?? null;
    $id_number = trim($_POST['id_number'] ?? '');
    $q1 = $_POST['question_1'] ?? '';
    $q2 = $_POST['question_2'] ?? '';
    $q3 = $_POST['question_3'] ?? '';
    $a1 = trim($_POST['answer_1'] ?? '');
    $a2 = trim($_POST['answer_2'] ?? '');
    $a3 = trim($_POST['answer_3'] ?? '');
    $allowedQuestions = [
        // Question 1
        'first_pet',
        'childhood_nickname',
        'favorite_subject',
        // Question 2
        'mothers_maiden_name',
        'dream_job',
        'first_travel',
        // Question 3
        'first_school',
        'childhood_friend',
        'birth_city',
    ];

    if ($id_number === '') {
        $alerts[] = ['type' => 'error', 'message' => 'Missing ID number for security questions. Please register again.'];
    }

    if (!$pending || ($pending['user_id'] ?? '') !== $id_number) {
        $alerts[] = ['type' => 'error', 'message' => 'Registration session expired. Please fill up the registration form again.'];
    }

    $questions = [$q1, $q2, $q3];
    if (in_array('', $questions, true)) {
        $alerts[] = ['type' => 'error', 'message' => 'All security questions must be selected.'];
    }
    if (count(array_unique($questions)) < 3) {
        $alerts[] = ['type' => 'error', 'message' => 'Security questions must be different from each other.'];
    }
    if (count(array_diff($questions, $allowedQuestions)) > 0) {
        $alerts[] = ['type' => 'error', 'message' => 'Please choose valid security questions.'];
    }
    if ($a1 === '' || $a2 === '' || $a3 === '') {
        $alerts[] = ['type' => 'error', 'message' => 'All security question answers are required.'];
    }

    if (empty($alerts)) {
        // Re-check duplicates at final submit (closes double-submit / stale-session races).
        $dupStmt = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ? OR user_id = ?");
        $dupStmt->bind_param("sss", $pending['username'], $pending['email'], $pending['user_id']);
        $dupStmt->execute();
        $dupStmt->store_result();
        if ($dupStmt->num_rows > 0) {
            $alerts[] = ['type' => 'error', 'message' => 'Username, Email, or ID Number already exists.'];
        }
        $dupStmt->close();
    }

    if (empty($alerts)) {
        $dupStmt = $conn->prepare("SELECT id FROM pending_registrations WHERE (username = ? OR email = ? OR user_id = ?) AND status = 'pending'");
        $dupStmt->bind_param("sss", $pending['username'], $pending['email'], $pending['user_id']);
        $dupStmt->execute();
        $dupStmt->store_result();
        if ($dupStmt->num_rows > 0) {
            $alerts[] = ['type' => 'error', 'message' => 'This account was already submitted and is waiting for approval.'];
        }
        $dupStmt->close();
    }

    if (empty($alerts)) {
        // Final submit: save pending registration together with hashed security answers.
        $hash1 = password_hash($a1, PASSWORD_DEFAULT);
        $hash2 = password_hash($a2, PASSWORD_DEFAULT);
        $hash3 = password_hash($a3, PASSWORD_DEFAULT);

        $stmt = $conn->prepare("INSERT INTO pending_registrations (user_id, first_name, middle_name, last_name, extension_name, dob, age, sex, username, email, password_hash, purok_street, barangay, city_municipality, province, country, zip_code, security_question_1, security_answer_hash_1, security_question_2, security_answer_hash_2, security_question_3, security_answer_hash_3) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
        $stmt->bind_param(
            "ssssssissssssssssssssss",
            $pending['user_id'], $pending['first_name'], $pending['middle_name'], $pending['last_name'], $pending['extension_name'],
            $pending['dob'], $pending['age'], $pending['sex'], $pending['username'], $pending['email'], $pending['password_hash'],
            $pending['purok_street'], $pending['barangay'], $pending['city_municipality'], $pending['province'], $pending['country'], $pending['zip_code'],
            $q1, $hash1, $q2, $hash2, $q3, $hash3
        );

        try {
            $saved = $stmt->execute();
        } catch (mysqli_sql_exception $e) {
            $saved = false;
            $dbError = $e->getMessage();
            $dbCode = $e->getCode();
        }

        if (!empty($saved)) {
            unset($_SESSION['pending_registration'], $_SESSION['id_number'], $_SESSION['first_name'], $_SESSION['selected_questions']);
            $stmt->close();
            $_SESSION['alerts'] = [['type' => 'success', 'message' => 'Registration submitted for approval. You will be notified once approved.']];
            header("Location: ../user/login.php");
            exit;
        }

        $error = $dbError ?? $stmt->error;
        $code = $dbCode ?? 0;
        if ($code === 1062 || str_contains($error, 'Duplicate entry')) {
            $alerts[] = ['type' => 'error', 'message' => 'This account was already submitted and is waiting for approval.'];
        } else {
            $alerts[] = ['type' => 'error', 'message' => 'Registration failed. Please try again later.'];
        }
        $stmt->close();
    }

    $_SESSION['alerts'] = $alerts;
    $_SESSION['id_number'] = $id_number;
    $_SESSION['first_name'] = $pending['first_name'] ?? ($_SESSION['first_name'] ?? null);
    $_SESSION['selected_questions'] = [$q1, $q2, $q3];
    header("Location: ../user/security_question.php");
    exit;
}

// ================= FORGOT PASSWORD: REQUEST OTP =================
if (isset($_POST['forgot_request_otp_btn'])) {
    $identifier = trim($_POST['identifier'] ?? ''); // email or user_id

    if ($identifier === '') {
        $alerts[] = ['type' => 'error', 'message' => 'Email or ID number is required.'];
    } else {
        // Find user by email or user_id
        $stmt = $conn->prepare("SELECT id, user_id, email, first_name, last_name, account_status FROM users WHERE email = ? OR user_id = ?");
        $stmt->bind_param("ss", $identifier, $identifier);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            // Don't reveal if account exists
            $alerts[] = ['type' => 'success', 'message' => 'If the account exists, a reset code has been sent to the registered email.'];
        } else {
            $user = $result->fetch_assoc();
            $stmt->close();

            if (($user['account_status'] ?? '') === 'blocked') {
                $alerts[] = ['type' => 'error', 'message' => 'Cannot forgot password. Your account is blocked.'];
                $_SESSION['alerts'] = $alerts;
                header('Location: ../user/forgot_password.php');
                exit;
            }

            $fullName = trim($user['first_name'] . ' ' . $user['last_name']);
            $otp = create_otp($conn, $user['id'], $user['email'], 'password_reset', $fullName);

            if ($otp) {
                $_SESSION['otp_email'] = $user['email'];
                $_SESSION['otp_purpose'] = 'password_reset';
                $_SESSION['otp_user_id'] = (int)$user['id'];
                header("Location: ../user/verify_otp.php?step=otp");
                exit;
            } else {
                $alerts[] = ['type' => 'error', 'message' => 'Failed to send reset code. Please try again.'];
            }
        }
    }

    $_SESSION['alerts'] = $alerts;
    header("Location: ../user/forgot_password.php");
    exit;
}

// ================= CHANGE PASSWORD AFTER OTP VERIFICATION =================
if (isset($_POST['change_password_btn'])) {
    $user_id = $_SESSION['reset_user_id'] ?? 0;

    if (!$user_id || !($_SESSION['reset_verified'] ?? false)) {
        $alerts[] = ['type' => 'error', 'message' => 'Session expired. Please complete the forgot password process again.'];
    }

    if ($user_id) {
        $statusStmt = $conn->prepare("SELECT account_status FROM users WHERE id = ?");
        $statusStmt->bind_param("i", $user_id);
        $statusStmt->execute();
        $status = $statusStmt->get_result()->fetch_assoc()['account_status'] ?? '';
        $statusStmt->close();
        if ($status === 'blocked') {
            $alerts[] = ['type' => 'error', 'message' => 'Cannot forgot password. Your account is blocked.'];
            unset($_SESSION['reset_user_id'], $_SESSION['reset_verified']);
        }
    }
    $new_password = $_POST['password'] ?? '';
    $re_new_password = $_POST['re_password'] ?? '';

    if ($new_password === '' || $re_new_password === '') {
        $alerts[] = ['type' => 'error', 'message' => 'Please enter and confirm your new password.'];
    } elseif ($new_password !== $re_new_password) {
        $alerts[] = ['type' => 'error', 'message' => 'New passwords do not match.'];
    }

    if ($user_id && $new_password !== '' && empty($alerts)) {
        // Fetch current password hash to prevent reusing the same password
        $stmt = $conn->prepare("SELECT password_hash FROM users WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $stmt->bind_result($current_hash);

        if ($stmt->fetch()) {
            // If the new password matches the current one, block it
            if (password_verify($new_password, $current_hash)) {
                $alerts[] = ['type' => 'error', 'message' => "New Password can't be the current password."];
            }
        } else {
            $alerts[] = ['type' => 'error', 'message' => 'Unable to verify current password. Please try again.'];
        }

        $stmt->close();
    }

    if (empty($alerts)) {
        $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
        $stmt->bind_param("si", $new_hash, $user_id);

        if ($stmt->execute()) {
            unset($_SESSION['reset_user_id'], $_SESSION['reset_verified']);
            $_SESSION['alerts'] = [['type' => 'success', 'message' => 'Password changed successfully. You can now login.' ]];
            header("Location: ../user/login.php");
            exit;
        } else {
            $alerts[] = ['type' => 'error', 'message' => 'Failed to change password. Please try again.'];
        }
    }

    $_SESSION['alerts'] = $alerts;
    header("Location: ../user/change_password.php");
    exit;
}

// Fallback: a POST reached this script without matching any handler
// (e.g. Enter-key submit that omits the button value). Never white-page.
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $_SESSION['alerts'] = [['type' => 'error', 'message' => 'Invalid request. Please try again.']];
    header("Location: ../user/login.php");
    exit;
}

?>
