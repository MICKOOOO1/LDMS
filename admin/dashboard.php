<?php
session_start();
require '../server/config.php';
require '../server/admin_helpers.php';
require_admin_role(['admin']);

$alerts = $_SESSION['alerts'] ?? [];
unset($_SESSION['alerts']);

$employeeId = trim($_GET['employee_id'] ?? '');
$pendingRegistrations = fetch_pending_registrations($conn);
$accounts = fetch_accounts($conn, $employeeId);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administrator - CSUCC LDMS</title>
    <link rel="stylesheet" href="../css/admin.css">
    <link href="https://cdn.boxicons.com/fonts/basic/boxicons.min.css" rel="stylesheet">
</head>
<body>
<header>
    <a href="dashboard.php" class="logo"><img src="../assets/imgs/logo.png" alt="CSUCC Logo"> CSUCC LDMS</a>
    <nav>
        <span><?= htmlspecialchars($_SESSION['name'] ?? 'Administrator'); ?></span>
        <a href="../user/logout.php">Logout</a>
    </nav>
</header>

<main>
    <?php foreach ($alerts as $alert): ?>
        <div class="alert <?= htmlspecialchars($alert['type']); ?>"><?= htmlspecialchars($alert['message']); ?></div>
    <?php endforeach; ?>

    <section class="page-heading">
        <h1>Administrator</h1>
        <p>Review registrations, filter accounts, and update user information.</p>
    </section>

    <section class="panel">
        <div class="section-bar">
            <h2>Pending Registrations</h2>
            <span class="count-badge"><?= count($pendingRegistrations); ?> waiting</span>
        </div>

        <div class="account-list">
            <?php foreach ($pendingRegistrations as $account): ?>
                <article class="account-card pending-card">
                    <div class="account-top">
                        <div>
                            <h3><?= htmlspecialchars(full_name($account)); ?></h3>
                            <p><?= htmlspecialchars($account['user_id']); ?> &middot; <?= htmlspecialchars($account['username']); ?> &middot; <?= htmlspecialchars($account['email']); ?></p>
                            <p><?= htmlspecialchars($account['purok_street']); ?>, <?= htmlspecialchars($account['barangay']); ?>, <?= htmlspecialchars($account['city_municipality']); ?></p>
                        </div>
                        <div class="badges">
                            <span><?= htmlspecialchars($account['account_status']); ?></span>
                            <span><?= htmlspecialchars($account['date_registered']); ?></span>
                        </div>
                    </div>

                    <form action="../server/admin_process.php" method="POST" class="inline-form">
                        <input type="hidden" name="target_id" value="<?= (int)$account['id']; ?>">
                        <button type="submit" name="change_status_btn" onclick="this.form.account_status.value='approved'">Accept / Approve</button>
                        <button type="submit" name="change_status_btn" class="danger" onclick="this.form.account_status.value='blocked'">Block</button>
                        <input type="hidden" name="account_status" value="">
                    </form>
                </article>
            <?php endforeach; ?>
            <?php if (empty($pendingRegistrations)): ?>
                <p>No pending registrations.</p>
            <?php endif; ?>
        </div>
    </section>

    <section class="panel">
        <div class="section-bar">
            <h2>Accounts</h2>
            <form method="GET" class="filter-form">
                <input type="text" name="employee_id" value="<?= htmlspecialchars($employeeId); ?>" placeholder="Filter by Employee ID">
                <button type="submit"><i class='bx bx-search'></i> Filter</button>
                <a href="dashboard.php">Clear</a>
            </form>
        </div>

        <div class="account-list">
            <?php foreach ($accounts as $account): ?>
                <?php if ($account['role'] === 'super_admin') { continue; } ?>
                <article class="account-card">
                    <div class="account-top">
                        <div>
                            <h3><?= htmlspecialchars(full_name($account)); ?></h3>
                            <p><?= htmlspecialchars($account['user_id']); ?> · <?= htmlspecialchars($account['username']); ?> · <?= htmlspecialchars($account['email']); ?></p>
                        </div>
                        <div class="badges">
                            <span><?= htmlspecialchars($account['role']); ?></span>
                            <span><?= htmlspecialchars($account['account_status']); ?></span>
                        </div>
                    </div>

                    <form action="../server/admin_process.php" method="POST" class="inline-form">
                        <input type="hidden" name="target_id" value="<?= (int)$account['id']; ?>">
                        <select name="account_status">
                            <option value="pending" <?= $account['account_status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="approved" <?= $account['account_status'] === 'approved' ? 'selected' : ''; ?>>Approved</option>
                            <option value="blocked" <?= $account['account_status'] === 'blocked' ? 'selected' : ''; ?>>Blocked</option>
                        </select>
                        <button type="submit" name="change_status_btn">Save Status</button>
                    </form>

                    <form action="../server/admin_process.php" method="POST" class="grid-form compact">
                        <input type="hidden" name="target_id" value="<?= (int)$account['id']; ?>">
                        <input type="text" name="first_name" value="<?= htmlspecialchars($account['first_name']); ?>" placeholder="First name" required>
                        <input type="text" name="middle_name" value="<?= htmlspecialchars($account['middle_name'] ?? ''); ?>" placeholder="Middle name">
                        <input type="text" name="last_name" value="<?= htmlspecialchars($account['last_name']); ?>" placeholder="Last name" required>
                        <input type="text" name="extension_name" value="<?= htmlspecialchars($account['extension_name'] ?? ''); ?>" placeholder="Extension">
                        <input type="email" name="email" value="<?= htmlspecialchars($account['email']); ?>" placeholder="Email" required>
                        <input type="text" name="username" value="<?= htmlspecialchars($account['username']); ?>" placeholder="Username" required>
                        <input type="text" name="purok_street" value="<?= htmlspecialchars($account['purok_street']); ?>" placeholder="Purok/Street">
                        <input type="text" name="barangay" value="<?= htmlspecialchars($account['barangay']); ?>" placeholder="Barangay">
                        <input type="text" name="city_municipality" value="<?= htmlspecialchars($account['city_municipality']); ?>" placeholder="City/Municipality">
                        <input type="text" name="province" value="<?= htmlspecialchars($account['province']); ?>" placeholder="Province">
                        <input type="text" name="country" value="<?= htmlspecialchars($account['country']); ?>" placeholder="Country">
                        <input type="text" name="zip_code" value="<?= htmlspecialchars($account['zip_code']); ?>" placeholder="Zip Code">
                        <button type="submit" name="update_account_btn">Update Info</button>
                    </form>

                </article>
            <?php endforeach; ?>
        </div>
    </section>
</main>
</body>
</html>
