<?php
session_start();
require '../server/config.php';

$alerts = [];
$step = $_GET['step'] ?? 'email'; // email, otp, success
$email = $_SESSION['otp_email'] ?? '';
$purpose = $_SESSION['otp_purpose'] ?? 'account_activation';
$user_id = $_SESSION['otp_user_id'] ?? 0;

function is_account_blocked(mysqli $conn, int $userId): bool {
    $stmt = $conn->prepare("SELECT account_status FROM users WHERE id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $account = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return ($account['account_status'] ?? '') === 'blocked';
}

function mask_email_address(string $email): string {
    $parts = explode('@', $email, 2);
    if (count($parts) !== 2) {
        return $email;
    }

    [$local, $domain] = $parts;
    $localLength = strlen($local);
    if ($localLength <= 4) {
        $maskedLocal = str_repeat('*', $localLength);
    } else {
        $maskedLocal = substr($local, 0, 2) . str_repeat('*', $localLength - 4) . substr($local, -2);
    }

    return $maskedLocal . '@' . $domain;
}

if ($step === 'email') {
    // Show email entry form for password reset
    if (isset($_POST['send_otp_btn'])) {
        $email = trim($_POST['email'] ?? '');
        $purpose = $_POST['purpose'] ?? 'password_reset';
        
        if (empty($email)) {
            $alerts[] = ['type' => 'error', 'message' => 'Email is required.'];
        } else {
            $stmt = $conn->prepare("SELECT id, first_name, last_name, role, account_status FROM users WHERE email = ?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                // Don't reveal if email exists
                $alerts[] = ['type' => 'success', 'message' => 'If the email exists, an OTP has been sent.'];
            } else {
                $user = $result->fetch_assoc();
                $stmt->close();

                if (($user['account_status'] ?? '') === 'blocked') {
                    $alerts[] = ['type' => 'error', 'message' => 'Cannot forgot password. Your account is blocked.'];
                    $_SESSION['alerts'] = $alerts;
                    header('Location: forgot_password.php');
                    exit;
                }
                
                $fullName = trim($user['first_name'] . ' ' . $user['last_name']);
                $otp = create_otp($conn, $user['id'], $email, $purpose, $fullName);
                
                if ($otp) {
                    $_SESSION['otp_email'] = $email;
                    $_SESSION['otp_purpose'] = $purpose;
                    $_SESSION['otp_user_id'] = $user['id'];
                    header("Location: verify_otp.php?step=otp");
                    exit;
                } else {
                    $alerts[] = ['type' => 'error', 'message' => 'Failed to generate OTP. Please try again.'];
                }
            }
        }
    }
} elseif ($step === 'otp') {
    if (!$user_id || $email === '') {
        $_SESSION['alerts'] = [['type' => 'error', 'message' => 'OTP session expired. Please log in again to request a new activation code.']];
        header($purpose === 'account_activation' ? 'Location: login.php' : 'Location: forgot_password.php');
        exit;
    }

    // Show OTP entry form
    if (isset($_POST['verify_otp_btn'])) {
        $otp = preg_replace('/\D/', '', $_POST['otp'] ?? '');
        
        if (empty($otp)) {
            $alerts[] = ['type' => 'error', 'message' => 'Please enter the OTP.'];
        } elseif ($purpose === 'password_reset' && is_account_blocked($conn, $user_id)) {
            $alerts[] = ['type' => 'error', 'message' => 'Cannot forgot password. Your account is blocked.'];
        } elseif (verify_otp($conn, $user_id, $otp, $purpose)) {
            if ($purpose === 'account_activation') {
                // Activate account
                $stmt = $conn->prepare("UPDATE users SET account_status = 'approved' WHERE id = ?");
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
                $stmt->close();
                
                $_SESSION['activation_setup_user_id'] = (int)$user_id;
                unset($_SESSION['otp_email'], $_SESSION['otp_purpose'], $_SESSION['otp_user_id']);
                $_SESSION['alerts'] = [['type' => 'success', 'message' => 'Account activated. Please complete your profile and security questions.']];
                header("Location: complete_account_setup.php");
                exit;
            } else {
                // Password reset - verify security questions next.
                $_SESSION['security_verified_user_id'] = $user_id;
                unset($_SESSION['otp_email'], $_SESSION['otp_purpose'], $_SESSION['otp_user_id']);
                header("Location: verify_security_questions.php");
                exit;
            }
        } else {
            $alerts[] = ['type' => 'error', 'message' => 'Invalid or expired OTP. Please try again.'];
        }
    }
    
    // Resend OTP
    if (isset($_POST['resend_otp_btn'])) {
        $stmt = $conn->prepare("SELECT first_name, last_name FROM users WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();
        
        if ($user) {
            $fullName = trim($user['first_name'] . ' ' . $user['last_name']);
            $otp = create_otp($conn, $user_id, $email, $purpose, $fullName);
            if ($otp) {
                $alerts[] = ['type' => 'success', 'message' => 'A new OTP has been sent to your email.'];
            } else {
                $alerts[] = ['type' => 'error', 'message' => 'Failed to resend OTP. Please try again.'];
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $purpose === 'account_activation' ? 'Account Activation' : 'Password Reset' ?> - CSUCC LDMS</title>
    <link rel="stylesheet" href="../css/login.css">
    <link href="https://cdn.boxicons.com/fonts/basic/boxicons.min.css" rel="stylesheet">
    <style>
        .otp-card {
            background: #fff;
            border-radius: 14px;
            box-shadow: 0 18px 45px rgba(0, 0, 0, 0.14);
            margin: 120px auto 40px;
            max-width: 520px;
            padding: 34px;
            text-align: center;
        }

        .otp-card h2 {
            color: #185D2A;
            margin-bottom: 8px;
        }

        .otp-card .subtitle {
            color: #55645d;
            font-size: 14px;
            line-height: 1.5;
            margin-bottom: 18px;
        }

        .otp-meta {
            color: #34483b;
            font-size: 13px;
            margin-bottom: 16px;
        }

        .otp-boxes {
            display: flex;
            gap: 10px;
            justify-content: center;
            margin: 18px 0;
        }

        .otp-boxes input {
            border: 1px solid #cbd8cf;
            border-radius: 8px;
            font-size: 20px;
            font-weight: 700;
            height: 44px;
            text-align: center;
            width: 44px;
        }

        .otp-boxes input:focus {
            border-color: #185D2A;
            outline: none;
            box-shadow: 0 0 0 3px rgba(24, 93, 42, 0.12);
        }

        .otp-actions {
            align-items: center;
            display: flex;
            gap: 10px;
            justify-content: center;
            margin-top: 16px;
        }

        .otp-actions a,
        .otp-actions button,
        .btn-link {
            border: 0;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 700;
            min-height: 38px;
            padding: 8px 14px;
            text-decoration: none;
        }

        .otp-actions a,
        .btn-link {
            background: #eef3ef;
            color: #185D2A;
        }

        .otp-actions button[name="verify_otp_btn"] {
            background: #185D2A;
            color: #fff;
        }

        .alert {
            align-items: center;
            box-shadow: 0 14px 35px rgba(15, 37, 53, 0.18);
            border-radius: 8px;
            display: flex;
            gap: 10px;
            justify-content: space-between;
            padding: 10px;
        }

        .otp-alerts {
            display: grid;
            gap: 8px;
            position: fixed;
            right: 24px;
            top: 96px;
            width: min(360px, calc(100vw - 32px));
            z-index: 200;
        }

        .alert button {
            background: transparent;
            border: 0;
            color: inherit;
            cursor: pointer;
            font-size: 22px;
            line-height: 1;
        }

        .alert.success { background: #e8f6ee; color: #0f5132; }
        .alert.error { background: #fdecea; color: #b42318; }
    </style>
</head>
<body>
<header class="user-auth">
    <a href="index.php" class="logo">
        <img src="../assets/imgs/logo.png" alt="CSUCC Logo">
        CSUCC LDMS
    </a>
    <nav>
        <a href="index.php" class="login-btn-modal">Home</a>
        <a href="login.php" class="login-btn-modal">Login</a>
    </nav>
</header>

<div class="otp-card">
        <?php if ($step === 'email'): ?>
            <h2><?= $purpose === 'account_activation' ? 'Activate Account' : 'Reset Password' ?></h2>
            <p class="subtitle"><?= $purpose === 'account_activation' ? 'Enter your email to receive an activation code.' : 'Enter your email to receive a password reset code.' ?></p>
            
            <?php if (!empty($alerts)): ?>
                <div class="otp-alerts" role="status" aria-live="polite">
                    <?php foreach ($alerts as $alert): ?>
                        <div class="alert <?= htmlspecialchars($alert['type']); ?>">
                            <span><?= htmlspecialchars($alert['message']); ?></span>
                            <button type="button" aria-label="Close notification" onclick="this.parentElement.remove()">&times;</button>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            
            <form method="POST">
                <input type="hidden" name="purpose" value="<?= htmlspecialchars($purpose); ?>">
                <div class="input-box">
                    <label for="email">Email Address <span class="asterisk">*</span></label>
                    <input type="email" name="email" id="email" placeholder="Enter your email" required>
                    <i class='bx bx-envelope'></i>
                </div>
                <button type="submit" name="send_otp_btn" class="btn btn-primary">Send OTP</button>
            </form>
            
        <?php elseif ($step === 'otp'): ?>
            <h2>Email Verification</h2>
            <p class="subtitle">A one-time password (OTP) has been sent to your registered email. Please enter the 6-digit code below to continue.</p>
            <div class="otp-meta">
                Email: <strong><?= htmlspecialchars(mask_email_address($email)); ?></strong><br>
                <strong>Enter Verification Code</strong>
            </div>
            
            <?php if (!empty($alerts)): ?>
                <div class="otp-alerts" role="status" aria-live="polite">
                    <?php foreach ($alerts as $alert): ?>
                        <div class="alert <?= htmlspecialchars($alert['type']); ?>">
                            <span><?= htmlspecialchars($alert['message']); ?></span>
                            <button type="button" aria-label="Close notification" onclick="this.parentElement.remove()">&times;</button>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" id="otpForm">
                <input type="hidden" name="otp" id="otp" required>
                <div class="otp-boxes" aria-label="Enter six digit OTP">
                    <input type="text" inputmode="numeric" maxlength="1" class="otp-digit" autocomplete="one-time-code">
                    <input type="text" inputmode="numeric" maxlength="1" class="otp-digit">
                    <input type="text" inputmode="numeric" maxlength="1" class="otp-digit">
                    <input type="text" inputmode="numeric" maxlength="1" class="otp-digit">
                    <input type="text" inputmode="numeric" maxlength="1" class="otp-digit">
                    <input type="text" inputmode="numeric" maxlength="1" class="otp-digit">
                </div>
                <div class="otp-actions">
                    <a href="forgot_password.php">Previous</a>
                    <button type="button" class="btn-link" id="resendBtn">Resend OTP</button>
                    <button type="submit" name="verify_otp_btn">Verify OTP</button>
                </div>
            </form>

            <form method="POST" id="resendForm" style="display:none;">
                <input type="hidden" name="resend_otp_btn" value="1">
            </form>
            
            <script>
                document.getElementById('resendBtn').addEventListener('click', function() {
                    document.getElementById('resendForm').submit();
                });
                
                const hiddenOtp = document.getElementById('otp');
                const boxes = Array.from(document.querySelectorAll('.otp-digit'));

                function syncOtp() {
                    hiddenOtp.value = boxes.map(box => box.value).join('');
                }

                boxes[0].focus();
                boxes.forEach((box, index) => {
                    box.addEventListener('input', function() {
                        this.value = this.value.replace(/\D/g, '').slice(0, 1);
                        syncOtp();
                        if (this.value && boxes[index + 1]) {
                            boxes[index + 1].focus();
                        }
                    });

                    box.addEventListener('keydown', function(e) {
                        if (e.key === 'Backspace' && !this.value && boxes[index - 1]) {
                            boxes[index - 1].focus();
                        }
                    });

                    box.addEventListener('paste', function(e) {
                        const paste = e.clipboardData.getData('text').replace(/\D/g, '').slice(0, 6);
                        if (paste.length) {
                            e.preventDefault();
                            boxes.forEach((input, i) => input.value = paste[i] || '');
                            syncOtp();
                            boxes[Math.min(paste.length, 6) - 1].focus();
                        }
                    });
                });

                document.getElementById('otpForm').addEventListener('submit', function(e) {
                    syncOtp();
                    if (!/^\d{6}$/.test(hiddenOtp.value)) {
                        e.preventDefault();
                        alert('Please enter the complete 6-digit OTP.');
                    }
                });
            </script>
        <?php endif; ?>
</div>

<footer>
    <p>&copy; 2025 Caraga State University Cabadbaran Campus. All rights reserved.</p>
</footer>
</body>
</html>
