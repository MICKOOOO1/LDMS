<?php
session_start();
require '../server/config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

if (($_SESSION['role'] ?? '') === 'admin') {
    header('Location: ../admin/my_account.php');
    exit;
}

if (($_SESSION['role'] ?? '') === 'super_admin') {
    header('Location: ../super%20admin/my_account.php');
    exit;
}

function user_account_full_name(array $account): string {
    return trim(implode(' ', array_filter([
        $account['first_name'] ?? '',
        $account['middle_name'] ?? '',
        $account['last_name'] ?? '',
        $account['extension_name'] ?? '',
    ])));
}

$alerts = $_SESSION['alerts'] ?? [];
unset($_SESSION['alerts']);

$stmt = $conn->prepare("SELECT user_id, first_name, middle_name, last_name, extension_name, dob, age, sex, email, username, role, account_status, profile_picture, purok_street, barangay, city_municipality, province, country, zip_code, date_registered FROM users WHERE id = ?");
$userId = (int)$_SESSION['user_id'];
$stmt->bind_param('i', $userId);
$stmt->execute();
$account = $stmt->get_result()->fetch_assoc();
$stmt->close();

$name = $_SESSION['name'] ?? 'User';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Account - CSUCC LDMS</title>
    <link rel="stylesheet" href="../css/home.css">
    <link href="https://cdn.boxicons.com/fonts/basic/boxicons.min.css" rel="stylesheet">
</head>
<body class="user-account-page">
<header class="user-auth">
    <a href="index.php" class="logo">
        <img src="../assets/imgs/logo.png" alt="CSUCC Logo">
        CSUCC LDMS
    </a>
    <nav>
        <a href="my_account.php" class="hello">Hello, <?= htmlspecialchars($name); ?></a>
        <a href="home.php" class="login-btn-modal">Home</a>
        <a href="logs.php" class="login-btn-modal">Logs</a>
        <a href="logout.php" class="login-btn-modal">Logout</a>
    </nav>
</header>

<main class="user-account-main">
    <section class="user-account-card">
        <div class="user-account-heading">
            <div>
                <p class="eyebrow">Profile</p>
                <h1>My Account</h1>
                <p>View your registered dormitory account information.</p>
            </div>
            <?php if ($account): ?>
                <span class="account-status"><?= htmlspecialchars(ucfirst($account['account_status'])); ?></span>
            <?php endif; ?>
        </div>

        <?php if (!empty($alerts)): ?>
            <div class="user-alerts" role="status" aria-live="polite">
                <?php foreach ($alerts as $alert): ?>
                    <div class="user-alert <?= htmlspecialchars($alert['type']); ?>">
                        <span><?= htmlspecialchars($alert['message']); ?></span>
                        <button type="button" aria-label="Close notification" onclick="this.parentElement.remove()">&times;</button>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if ($account): ?>
            <form action="../server/auth_process.php" method="POST" enctype="multipart/form-data" class="user-account-grid">
                <div class="account-summary full-width">
                    <div class="profile-photo">
                        <?php if (!empty($account['profile_picture'])): ?>
                            <img src="../<?= htmlspecialchars($account['profile_picture']); ?>" alt="Profile picture">
                        <?php else: ?>
                            <span><?= htmlspecialchars(strtoupper(substr($account['first_name'], 0, 1) . substr($account['last_name'], 0, 1))); ?></span>
                        <?php endif; ?>
                    </div>
                    <div>
                        <h2><?= htmlspecialchars(user_account_full_name($account)); ?></h2>
                        <p><?= htmlspecialchars($account['user_id']); ?> · <?= htmlspecialchars($account['username']); ?></p>
                    </div>
                    <input type="file" name="profile_picture" accept="image/jpeg,image/png,image/webp" class="profile-file-input" aria-label="Choose profile picture">
                </div>
                <label>First Name<input type="text" name="first_name" value="<?= htmlspecialchars($account['first_name']); ?>" required></label>
                <label>Middle Name<input type="text" name="middle_name" value="<?= htmlspecialchars($account['middle_name'] ?? ''); ?>"></label>
                <label>Last Name<input type="text" name="last_name" value="<?= htmlspecialchars($account['last_name']); ?>" required></label>
                <label>Extension<input type="text" name="extension_name" value="<?= htmlspecialchars($account['extension_name'] ?? ''); ?>"></label>
                <label>ID Number<input type="text" value="<?= htmlspecialchars($account['user_id']); ?>" readonly></label>
                <label>Email<input type="email" name="email" value="<?= htmlspecialchars($account['email']); ?>" required></label>
                <label>Username<input type="text" name="username" value="<?= htmlspecialchars($account['username']); ?>" required></label>
                <label>Date of Birth<input type="date" value="<?= htmlspecialchars($account['dob']); ?>" readonly></label>
                <label>Age<input type="number" value="<?= (int)$account['age']; ?>" readonly></label>
                <label>Sex<input type="text" value="<?= htmlspecialchars(ucfirst($account['sex'])); ?>" readonly></label>
                <label>Purok/Street<input type="text" name="purok_street" value="<?= htmlspecialchars($account['purok_street']); ?>"></label>
                <label>Barangay<input type="text" name="barangay" value="<?= htmlspecialchars($account['barangay']); ?>"></label>
                <label>City/Municipality<input type="text" name="city_municipality" value="<?= htmlspecialchars($account['city_municipality']); ?>"></label>
                <label>Province<input type="text" name="province" value="<?= htmlspecialchars($account['province']); ?>"></label>
                <label>Country<input type="text" name="country" value="<?= htmlspecialchars($account['country']); ?>"></label>
                <label>Zip Code<input type="text" name="zip_code" value="<?= htmlspecialchars($account['zip_code']); ?>"></label>
                <label>Date Registered<input type="text" value="<?= htmlspecialchars($account['date_registered']); ?>" readonly></label>
                <div class="profile-actions">
                    <button type="submit" name="update_profile_btn">Save Changes</button>
                </div>
            </form>
        <?php else: ?>
            <p>Account information is unavailable.</p>
        <?php endif; ?>
    </section>
</main>

<footer>
    <p>&copy; 2025 Caraga State University Cabadbaran Campus. All rights reserved.</p>
</footer>
</body>
</html>
