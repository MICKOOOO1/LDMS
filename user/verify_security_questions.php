<?php
session_start();
require '../server/config.php';

$alerts = $_SESSION['alerts'] ?? [];
unset($_SESSION['alerts']);

$numericUserId = (int)($_SESSION['security_verified_user_id'] ?? 0);
if (!$numericUserId) {
    $_SESSION['alerts'] = [['type' => 'error', 'message' => 'Session expired. Please request a new OTP.']];
    header('Location: forgot_password.php');
    exit;
}

$stmt = $conn->prepare("SELECT user_id, username, first_name, last_name FROM users WHERE id = ?");
$stmt->bind_param("i", $numericUserId);
$stmt->execute();
$account = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$account) {
    unset($_SESSION['security_verified_user_id']);
    $_SESSION['alerts'] = [['type' => 'error', 'message' => 'Account not found. Please try again.']];
    header('Location: forgot_password.php');
    exit;
}

function security_question_label(string $code): string {
    return match ($code) {
        'first_pet' => 'What was the name of your first pet?',
        'childhood_nickname' => 'What was your childhood nickname?',
        'favorite_subject' => 'What was your favorite subject in school?',
        'mothers_maiden_name' => "What is your mother's maiden name?",
        'dream_job' => 'What was your dream job when you were a child?',
        'first_travel' => 'What was the first place you traveled to?',
        'first_school' => 'What was the name of your first school?',
        'childhood_friend' => 'What was the name of your childhood best friend?',
        'birth_city' => 'In which city were you born?',
        default => $code,
    };
}

function mask_visible_identifier(string $value): string {
    $value = trim($value);
    $length = strlen($value);

    if ($length <= 4) {
        return str_repeat('*', $length);
    }

    return substr($value, 0, 2) . str_repeat('*', $length - 4) . substr($value, -2);
}

$stmt = $conn->prepare("SELECT question_code, answer_hash FROM security_questions WHERE user_id = ? ORDER BY id ASC");
$stmt->bind_param("s", $account['user_id']);
$stmt->execute();
$result = $stmt->get_result();
$questions = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

if (isset($_POST['verify_security_btn'])) {
    if (count($questions) === 0) {
        $alerts[] = ['type' => 'error', 'message' => 'No security questions are set for this account. Please contact the administrator.'];
    } else {
        $correctAnswers = 0;
        foreach ($questions as $question) {
            $answer = trim($_POST['answers'][$question['question_code']] ?? '');
            if ($answer !== '' && password_verify($answer, $question['answer_hash'])) {
                $correctAnswers++;
            }
        }

        if ($correctAnswers >= 2) {
            unset($_SESSION['security_verified_user_id']);
            $_SESSION['reset_verified'] = true;
            $_SESSION['reset_user_id'] = $numericUserId;
            header('Location: change_password.php');
            exit;
        }

        $alerts[] = ['type' => 'error', 'message' => 'At least two security answers must be correct.'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Security Verification - CSUCC LDMS</title>
    <link rel="stylesheet" href="../css/forgot_password.css">
    <link href="https://cdn.boxicons.com/fonts/basic/boxicons.min.css" rel="stylesheet">
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

<?php if (!empty($alerts)): ?>
    <div class="alert-box">
        <?php foreach ($alerts as $alert): ?>
            <div class="alert <?= htmlspecialchars($alert['type']); ?>">
                <span><?= htmlspecialchars($alert['message']); ?></span>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<div class="auth-modal show">
    <div class="form-box change-password">
        <h2>Security Questions</h2>
        <p>OTP verified. Please answer your security questions. At least two correct answers are required to continue resetting your password.</p>
        <p><strong>ID Number:</strong> <?= htmlspecialchars(mask_visible_identifier($account['user_id'])); ?></p>
        <p><strong>Username:</strong> <?= htmlspecialchars(mask_visible_identifier($account['username'])); ?></p>

        <?php if (empty($questions)): ?>
            <p>No security questions are set for this account. Please contact the administrator.</p>
            <p><a href="forgot_password.php">Back to Forgot Password</a></p>
        <?php else: ?>
            <form action="verify_security_questions.php" method="POST" novalidate>
                <?php foreach ($questions as $question): ?>
                    <div class="input-box full-width">
                        <label><?= htmlspecialchars(security_question_label($question['question_code'])); ?></label>
                        <input type="password" name="answers[<?= htmlspecialchars($question['question_code']); ?>]" required>
                        <i class='bx bx-eye-slash'></i>
                    </div>
                <?php endforeach; ?>
                <button type="submit" name="verify_security_btn" class="btn btn-primary">Continue</button>
            </form>
        <?php endif; ?>
    </div>
</div>

<footer>
    <p>&copy; 2025 Caraga State University Cabadbaran Campus. All rights reserved.</p>
</footer>
<script src="../javascript/script.js"></script>
</body>
</html>
