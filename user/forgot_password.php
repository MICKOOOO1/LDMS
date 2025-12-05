<?php
session_start();
require '../server/config.php';

$alerts = $_SESSION['alerts'] ?? [];
unset($_SESSION['alerts']);

// Extract specific errors
$answer_errors = $_SESSION['answer_errors'] ?? [];
unset($_SESSION['answer_errors']);

$idError = null;
if (!empty($alerts)) {
    foreach ($alerts as $idx => $alert) {
        if (isset($alert['type'], $alert['message']) &&
            $alert['type'] === 'error' &&
            ($alert['message'] === 'No account found with that ID number.' || $alert['message'] === 'ID number is required.')) {
            $idError = $alert['message'];
            unset($alerts[$idx]);
        }
    }
    // Reindex alerts array after unsetting
    $alerts = array_values($alerts);
}

$step = $_SESSION['forgot_step'] ?? 1;
$userIdNumber = $_SESSION['forgot_id_number'] ?? null;
$username = $_SESSION['forgot_username'] ?? null;

// Safety: if step is anything other than 1 or 2, or if step 2 lacks an ID, reset to step 1
if ($step !== 1 && $step !== 2) {
    $step = 1;
}
if ($step === 2 && !$userIdNumber) {
    $step = 1;
}

$questions = [];

if ($step === 2 && $userIdNumber) {
    // Load this user's questions
    $stmt = $conn->prepare("SELECT question_code FROM security_questions WHERE user_id = ?");
    $stmt->bind_param("s", $userIdNumber);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $questions[] = $row['question_code'];
    }
    $stmt->close();
}
function label_for_code($code) {
    switch ($code) {
        case 'mothers_maiden_name': return "What is your mother's maiden name?";
        case 'first_school':        return 'What was the name of your first school?';
        case 'first_pet':           return 'What is the name of your first pet?';
        case 'birth_city':          return 'In which city were you born?';
        case 'favorite_media':      return 'What is your favorite book, movie, or song?';
        case 'childhood_friend':    return 'What was the name of your childhood best friend?';
        default: return $code;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - CSUCC Ladies Dormitory Management System</title>
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
        <h2>Recover Account</h2>

        <?php if ($step === 1): ?>
            <p>To ensure your account security, please enter your register ID Number below.</p>
            <form action="../server/auth_process.php" method="POST" novalidate>
                <div class="input-box full-width">
                    <label>ID Number</label>
                    <input type="text" name="id_number" placeholder="xxxx-xxxx" required>
                    <small class="input-message"><?= $idError ? htmlspecialchars($idError) : '' ?></small>
                </div>
                <button type="submit" name="forgot_step1_btn" class="btn">Next</button>
            </form>
        <?php elseif ($step === 2 && !empty($questions)): ?>
            <form action="../server/auth_process.php" method="POST" novalidate>
                <div class="input-box full-width">
                    <p>ID number: <?= htmlspecialchars($userIdNumber) ?></p>
                    <p>Username: <?= htmlspecialchars($username) ?></p>
                    <p>Please answer all of your security questions below:</p>
                </div>

                <?php foreach ($questions as $code): ?>
                    <div class="input-box full-width">
                        <label><?= htmlspecialchars(label_for_code($code)) ?></label>
                        <input type="password" name="answers[<?= htmlspecialchars($code) ?>]" required>
                        <small class="input-message"><?= isset($answer_errors[$code]) ? htmlspecialchars($answer_errors[$code]) : '' ?></small>
                        <i class='bx bx-eye-slash'></i>
                    </div>
                <?php endforeach; ?>

                <div class="btn-row">
                    <button type="submit" name="forgot_back_btn" class="btn btn-secondary" formnovalidate onclick="this.form.dataset.skipValidation='1'">Back</button>
                    <button type="submit" name="forgot_step2_btn" class="btn btn-primary">Verify</button>
                </div>
            </form>
        <?php elseif ($step === 2): ?>
            <p>No security questions found for this account. Please ensure you have set them during registration or contact support.</p>
        <?php endif; ?>
    </div>
</div>

<footer>
    <p>&copy; 2025 Caraga State University Cabadbaran Campus. All rights reserved.</p>
</footer>
<script src="../javascript/script.js"></script>
</body>
</html>