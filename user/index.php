<?php
session_start();

if (isset($_SESSION['user_id'])) {
    header('Location: home.php');
    exit;
}

$name = $_SESSION['name'] ?? null;
$alerts = $_SESSION['alerts'] ?? [];
$active_form = $_SESSION['active_form'] ?? '';

unset($_SESSION['alerts'], $_SESSION['active_form']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login & Registration</title>
    <link rel="stylesheet" href="../css/style.css">
    <link href="https://cdn.boxicons.com/fonts/basic/boxicons.min.css" rel="stylesheet">
</head>
<body>

<header class="user-auth">
    <a href="index.php" class="logo">
        <img src="../assets/imgs/logo.png" alt="CSUCC Logo">
        CSUCC LDMS
    </a>
    <nav>
        <a href="login.php" class="login-btn-modal">Login</a>
        <a href="register.php" class="login-btn-modal">Register</a>
    </nav>
</header>

<section class="hero">
    <div class="hero-container">
        <h1>Welcome to the Ladies Dormitory</h1>
        <p>Management System. Manage residents, rooms, and payments easily.</p>
        <button type="button" class="btn signin-btn" onclick="window.location.href='login.php'">
            Sign in now!
        </button>
    </div>
</section>

<?php if (!empty($alerts)): ?>
<div class="alert-box">
    <?php foreach ($alerts as $alert): ?>
        <div class="alert <?= $alert['type']; ?>">
            <i class='bx <?= $alert['type'] === 'success' ? 'bxs-check-circle' : 'bxs-x-circle'; ?>'></i>
            <span><?= $alert['message']; ?></span>
        </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<div class="auth-modal <?= $active_form === 'register' ? 'show slide expanded' : ($active_form === 'login' ? 'show' : ''); ?>">
    <button type="button" class="close-btn-modal">
        <i class='bx bx-x'></i>
    </button>
</div>
<footer>
    <p>&copy; 2025 Caraga State University Cabadbaran Campus. All rights reserved.</p>
</footer>


<script src="../javascript/script.js"></script>
</body>
</html>
