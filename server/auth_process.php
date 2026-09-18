<?php
session_start();
require 'config.php';

$alerts = [];

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
        $stmt = $conn->prepare("SELECT id, user_id, username, password_hash, first_name, middle_name, last_name, extension_name, role, account_status, privileges FROM users WHERE username = ? OR email = ?");
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
                    $alerts[] = ['type' => 'error', 'message' => 'Your registration is pending administrator approval.'];
                    $_SESSION['last_failure_type'] = 'status';
                } elseif ($user['account_status'] === 'blocked') {
                    $alerts[] = ['type' => 'error', 'message' => 'Your account is blocked. Please contact the administrator.'];
                    $_SESSION['last_failure_type'] = 'status';
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
    $user_id         = trim($_POST['id_number']);
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
    if (empty($user_id) || empty($first_name) || empty($last_name) || empty($dob) || empty($sex) || empty($username) || empty($email) || empty($password) || empty($re_password) || empty($purok_street) || empty($barangay) || empty($city_municipality) || empty($province) || empty($country) || empty($zip_code)) {
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

    // Check username, email, and user_id uniqueness
    $stmt = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ? OR user_id = ?");
    $stmt->bind_param("sss", $username, $email, $user_id);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows > 0) {
        $alerts[] = ['type' => 'error', 'message' => 'Username, Email, or ID Number already exists.'];
    }
    $stmt->close();

    // ================= IF NO ERRORS, INSERT USER THEN GO TO SECURITY QUESTIONS =================
    // Log all validation errors for debugging (remove in production)
    if (!empty($alerts)) {
        error_log("Registration validation errors: " . json_encode($alerts));
    }
    
    if (empty($alerts)) {
        $password_hash = password_hash($password, PASSWORD_DEFAULT);

        $role = 'user';
        $account_status = 'pending';
        $stmt = $conn->prepare("INSERT INTO users (user_id, first_name, middle_name, last_name, extension_name, dob, age, sex, username, email, password_hash, role, account_status, purok_street, barangay, city_municipality, province, country, zip_code) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
        $stmt->bind_param(
            "ssssssissssssssssss",
            $user_id, $first_name, $middle_name, $last_name, $extension_name,
            $dob, $age, $sex, $username, $email, $password_hash, $role, $account_status,
            $purok_street, $barangay, $city_municipality, $province, $country, $zip_code
        );

        if ($stmt->execute()) {
            $_SESSION['id_number'] = $user_id;
            $_SESSION['first_name'] = $first_name;
            $_SESSION['alerts'] = [['type' => 'success', 'message' => 'Almost done! Please set your security questions.']];
            header("Location: ../user/security_question.php");
            exit;
        } else {
            // Get detailed error message from database
            $error_msg = $stmt->error;
            $alerts[] = ['type' => 'error', 'message' => 'Registration failed: ' . $error_msg . '. Please try again later.'];
        }
        $stmt->close();
    }

    // If there are errors, show all of them
    if (!empty($alerts)) {
        $_SESSION['alerts'] = $alerts;
        header("Location: ../user/register.php");
        exit;
    }
}

if (isset($_POST['save_questions_btn'])) {
    $id_number = trim($_POST['id_number'] ?? '');
    $q1 = $_POST['question_1'] ?? '';
    $q2 = $_POST['question_2'] ?? '';
    $q3 = $_POST['question_3'] ?? '';
    $a1 = trim($_POST['answer_1'] ?? '');
    $a2 = trim($_POST['answer_2'] ?? '');
    $a3 = trim($_POST['answer_3'] ?? '');

    if ($id_number === '') {
        $alerts[] = ['type' => 'error', 'message' => 'Missing ID number for security questions. Please register again.'];
    }

    $questions = [$q1, $q2, $q3];
    if (in_array('', $questions, true)) {
        $alerts[] = ['type' => 'error', 'message' => 'All security questions must be selected.'];
    }
    if (count(array_unique($questions)) < 3) {
        $alerts[] = ['type' => 'error', 'message' => 'Security questions must be different from each other.'];
    }
    if ($a1 === '' || $a2 === '' || $a3 === '') {
        $alerts[] = ['type' => 'error', 'message' => 'All security question answers are required.'];
    }

    if (empty($alerts)) {
        // Store hashed answers for better security
        $stmt = $conn->prepare("INSERT INTO security_questions (user_id, question_code, answer_hash) VALUES (?,?,?)");

        $entries = [
            [$id_number, $q1, $a1],
            [$id_number, $q2, $a2],
            [$id_number, $q3, $a3],
        ];

        foreach ($entries as $entry) {
            [$uid, $qc, $ans] = $entry;
            $hashed = password_hash($ans, PASSWORD_DEFAULT);
            $stmt->bind_param("sss", $uid, $qc, $hashed);
            if (!$stmt->execute()) {
                $alerts[] = ['type' => 'error', 'message' => 'Failed to save security questions. Please try again.'];
                break;
            }
        }

        if (empty($alerts)) {
            $_SESSION['alerts'] = [['type' => 'success', 'message' => 'Registration completed successfully! Please wait for administrator approval before logging in.']];
            header("Location: ../user/login.php");
            exit;
        }
    }

    $_SESSION['alerts'] = $alerts;
    $_SESSION['id_number'] = $id_number;
    header("Location: ../user/security_question.php");
    exit;
}

// ================= FORGOT PASSWORD: STEP 1 (VERIFY ID + USERNAME, LOAD QUESTIONS) =================
if (isset($_POST['forgot_step1_btn'])) {
    $id_number = trim($_POST['id_number'] ?? '');

    if ($id_number === '') {
        $alerts[] = ['type' => 'error', 'message' => 'ID number is required.'];
    }

    if (empty($alerts)) {
        $stmt = $conn->prepare("SELECT user_id, username FROM users WHERE user_id = ?");
        $stmt->bind_param("s", $id_number);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            $alerts[] = ['type' => 'error', 'message' => 'No account found with that ID number.'];
        } else {
            $userRow = $result->fetch_assoc();
            // Store details for later steps
            $_SESSION['forgot_step'] = 2;
            $_SESSION['forgot_id_number'] = $userRow['user_id'];
            $_SESSION['forgot_username'] = $userRow['username'];
        }
    }

    $_SESSION['alerts'] = $alerts;
    header("Location: ../user/forgot_password.php");
    exit;
}

// ================= FORGOT PASSWORD: BACK BUTTON =================
if (isset($_POST['forgot_back_btn'])) {
    // Reset to step 1
    $_SESSION['forgot_step'] = 1;
    unset($_SESSION['forgot_id_number'], $_SESSION['forgot_username'], $_SESSION['answer_errors']);
    header("Location: ../user/forgot_password.php");
    exit;
}

// ================= FORGOT PASSWORD: STEP 2 (VERIFY SECURITY QUESTION) =================
if (isset($_POST['forgot_step2_btn'])) {
    $id_number = $_SESSION['forgot_id_number'] ?? '';
    $answers = $_POST['answers'] ?? [];

    if ($id_number === '') {
        $alerts[] = ['type' => 'error', 'message' => 'Session expired. Please start the forgot password process again.'];
    }

    // Fetch all question_code and answer_hash for this user
    $stored = [];
    if ($id_number !== '') {
        $stmt = $conn->prepare("SELECT question_code, answer_hash FROM security_questions WHERE user_id = ?");
        $stmt->bind_param("s", $id_number);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $stored[$row['question_code']] = $row['answer_hash'];
        }
        $stmt->close();

        if (empty($stored)) {
            $alerts[] = ['type' => 'error', 'message' => 'No security questions found for this account.'];
        }
    }

    // Validate that all required answers are provided
    if (empty($alerts) && !empty($stored)) {
        foreach ($stored as $code => $_hash) {
            $ans = trim($answers[$code] ?? '');
            if ($ans === '') {
                $alerts[] = ['type' => 'error', 'message' => 'All security questions must be answered.'];
                break;
            }
        }
    }

    // Verify each answer
    if (empty($alerts) && !empty($stored)) {
        $answer_errors = [];
        $all_ok = true;
        foreach ($stored as $code => $hash) {
            $ans = trim($answers[$code] ?? '');
            if (!password_verify($ans, $hash)) {
                $answer_errors[$code] = 'Wrong answer.';
                $all_ok = false;
            }
        }

        if ($all_ok) {
            $_SESSION['change_password_user_id'] = $id_number;
            $_SESSION['forgot_step'] = 0;
            header("Location: ../user/change_password.php");
            exit;
        } else {
            $_SESSION['answer_errors'] = $answer_errors;
        }
    }

    $_SESSION['alerts'] = $alerts;
    $_SESSION['forgot_step'] = 2;
    header("Location: ../user/forgot_password.php");
    exit;
}

// ================= CHANGE PASSWORD AFTER SECURITY QUESTION =================
if (isset($_POST['change_password_btn'])) {
    $user_id = $_SESSION['change_password_user_id'] ?? '';
    $new_password = $_POST['password'] ?? '';
    $re_new_password = $_POST['re_password'] ?? '';

    if ($user_id === '') {
        $alerts[] = ['type' => 'error', 'message' => 'Session expired. Please complete the forgot password process again.'];
    }
    if ($new_password === '' || $re_new_password === '') {
        $alerts[] = ['type' => 'error', 'message' => 'Please enter and confirm your new password.'];
    } elseif ($new_password !== $re_new_password) {
        $alerts[] = ['type' => 'error', 'message' => 'New passwords do not match.'];
    }

    if ($user_id !== '' && $new_password !== '' && empty($alerts)) {
        // Fetch current password hash to prevent reusing the same password
        $stmt = $conn->prepare("SELECT password_hash FROM users WHERE user_id = ?");
        $stmt->bind_param("s", $user_id);
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
        $stmt = $conn->prepare("UPDATE users SET password_hash = ? WHERE user_id = ?");
        $stmt->bind_param("ss", $new_hash, $user_id);

        if ($stmt->execute()) {
            unset($_SESSION['change_password_user_id']);
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

?>
