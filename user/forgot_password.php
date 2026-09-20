<?php
session_start();
require '../server/config.php';

$alerts = $_SESSION['alerts'] ?? [];
unset($_SESSION['alerts']);
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
                <i class='bx <?= $alert['type'] === 'success' ? 'bxs-check-circle' : 'bxs-x-circle'; ?>'></i>
                <span><?= htmlspecialchars($alert['message']); ?></span>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<div class="auth-modal show">
    <div class="form-box change-password">
        <h2>Forgot Password</h2>
        <p>Enter your email or ID number to receive a password reset code.</p>
        <form action="../server/auth_process.php" method="POST" novalidate>
            <div class="input-box full-width">
                <label>Email or ID Number</label>
                <input type="text" name="identifier" placeholder="Enter email or ID number (xxxx-xxxx)" required>
            </div>
            <button type="submit" name="forgot_request_otp_btn" class="btn btn-primary">Send Reset Code</button>
        </form>
        <p style="margin-top: 1rem;"><a href="login.php">Back to Login</a></p>
    </div>
</div>

<footer>
    <p>&copy; 2025 Caraga State University Cabadbaran Campus. All rights reserved.</p>
</footer>
<script src="../javascript/script.js"></script>
</body>
</html>