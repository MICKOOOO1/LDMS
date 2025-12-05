<?php
session_start();

// Retrieve session variables or set defaults
$name = $_SESSION['name'] ?? null; // first name (used elsewhere)
$username = $_SESSION['username'] ?? '';
$alerts = $_SESSION['alerts'] ?? [];
$active_form = $_SESSION['active_form'] ?? '';
$user_id = $_SESSION['change_password_user_id'] ?? '';

// Clear only alert-related session variables, preserve others (like change_password_user_id)
unset($_SESSION['alerts'], $_SESSION['active_form']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Change Password - CSUCC Ladies Dormitory Management System</title>
    <link rel="stylesheet" href="../css/change_password.css">
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
            <a href="register.php" class="login-btn-modal">Register</a>
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
            <h2>Change Password</h2>
            <p>User ID Number: <?= htmlspecialchars($user_id) ?></p>
            <form action="../server/auth_process.php" method="POST" novalidate>

                <div class="input-box full-width">
                    <label>New Password</label>
                    <input type="password" name="password" placeholder="New Password" required>
                    <i class='bx bx-eye-slash'></i>
                    <small class="input-message"></small>
                    <div class="strength-meter">
                        <div class="strength-bar"></div>
                    </div>
                </div>

                <div class="input-box full-width">
                    <label>Re-enter Password</label>
                    <input type="password" name="re_password" placeholder="Re-enter New Password" required>
                    <i class='bx bx-eye-slash'></i>
                    <small class="input-message"></small>
                </div>

                <button type="submit" name="change_password_btn" class="btn">Save Password</button>
            </form>
        </div>
    </div>

    <footer>
        <p>&copy; 2025 Caraga State University Cabadbaran Campus. All rights reserved.</p>
    </footer>

    <script src="../javascript/script.js"></script>
</body>
</html>