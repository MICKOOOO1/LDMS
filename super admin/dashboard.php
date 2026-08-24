<?php
session_start();
require '../server/config.php';
require '../server/admin_helpers.php';
require_admin_role(['super_admin']);

$alerts = $_SESSION['alerts'] ?? [];
unset($_SESSION['alerts']);

$employeeId = trim($_GET['employee_id'] ?? '');
$pendingRegistrations = fetch_pending_registrations($conn);
$accounts = fetch_accounts($conn, $employeeId);
$deleteRequests = fetch_delete_requests($conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Super Admin - CSUCC LDMS</title>
    <link rel="stylesheet" href="../css/admin.css">
    <link href="https://cdn.boxicons.com/fonts/basic/boxicons.min.css" rel="stylesheet">
</head>
<body>
<header>
    <a href="dashboard.php" class="logo"><img src="../assets/imgs/logo.png" alt="CSUCC Logo"> CSUCC LDMS</a>
    <nav>
        <span><?= htmlspecialchars($_SESSION['name'] ?? 'Super Admin'); ?></span>
        <a href="../user/logout.php">Logout</a>
    </nav>
</header>

<main>
    <?php foreach ($alerts as $alert): ?>
        <div class="alert <?= htmlspecialchars($alert['type']); ?>"><?= htmlspecialchars($alert['message']); ?></div>
    <?php endforeach; ?>

    <section class="page-heading">
        <h1>Super Admin</h1>
        <p>Manage registrations, privileges, account updates, and account deletion.</p>
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
        <h2>Create Administrator</h2>
        <form action="../server/admin_process.php" method="POST" class="grid-form">
            <label>Role
                <select name="role" required>
                    <option value="admin">Administrator</option>
                    <option value="super_admin">Super Administrator</option>
                </select>
            </label>
            <label>Employee ID
                <input type="text" name="id_number" placeholder="xxxx-xxxx" required>
            </label>
            <label>First Name
                <input type="text" name="first_name" required>
            </label>
            <label>Last Name
                <input type="text" name="last_name" required>
            </label>
            <label>Date of Birth
                <input type="date" name="dob" value="1990-01-01">
            </label>
            <label>Age
                <input type="number" name="age" value="18" min="18">
            </label>
            <label>Sex
                <select name="sex" required>
                    <option value="female">Female</option>
                    <option value="male">Male</option>
                </select>
            </label>
            <label>Email
                <input type="email" name="email" required>
            </label>
            <label>Username
                <input type="text" name="username" required>
            </label>
            <label>Password
                <input type="password" name="password" required>
            </label>
            <button type="submit" name="create_staff_btn">Create Account</button>
        </form>
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

                    <?php if ($account['role'] === 'admin'): ?>
                        <?php $privileges = array_filter(array_map('trim', explode(',', $account['privileges'] ?? ''))); ?>
                        <form action="../server/admin_process.php" method="POST" class="privilege-form">
                            <input type="hidden" name="target_id" value="<?= (int)$account['id']; ?>">
                            <label><input type="checkbox" name="privileges[]" value="manage_registration" <?= in_array('manage_registration', $privileges, true) ? 'checked' : ''; ?>> Registration status</label>
                            <label><input type="checkbox" name="privileges[]" value="update_accounts" <?= in_array('update_accounts', $privileges, true) ? 'checked' : ''; ?>> Update accounts</label>
                            <button type="submit" name="save_privileges_btn">Save Privileges</button>
                        </form>
                    <?php endif; ?>

                    <?php if ((int)$account['id'] !== (int)$_SESSION['user_id']): ?>
                        <form action="../server/admin_process.php" method="POST" onsubmit="return confirm('Delete this account permanently?');">
                            <input type="hidden" name="target_id" value="<?= (int)$account['id']; ?>">
                            <button type="submit" name="delete_account_btn" class="danger">Delete Account</button>
                        </form>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="panel">
        <h2>Admin Delete Requests</h2>
        <div class="request-list">
            <?php foreach ($deleteRequests as $request): ?>
                <article class="request-card">
                    <h3><?= htmlspecialchars($request['target_employee_id']); ?> · <?= htmlspecialchars($request['target_username']); ?></h3>
                    <p><strong>Requested by:</strong> <?= htmlspecialchars($request['requester_username']); ?></p>
                    <p><strong>Reason:</strong> <?= htmlspecialchars($request['reason']); ?></p>
                    <pre><?= htmlspecialchars($request['user_snapshot']); ?></pre>
                    <p><strong>Status:</strong> <?= htmlspecialchars($request['request_status']); ?></p>
                    <?php if ($request['request_status'] === 'pending'): ?>
                        <form action="../server/admin_process.php" method="POST" class="inline-form">
                            <input type="hidden" name="request_id" value="<?= (int)$request['id']; ?>">
                            <button type="submit" name="review_delete_request_btn" value="1" onclick="this.form.decision.value='approved'">Approve Delete</button>
                            <button type="submit" name="review_delete_request_btn" value="1" onclick="this.form.decision.value='rejected'" class="secondary">Reject</button>
                            <input type="hidden" name="decision" value="">
                        </form>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
            <?php if (empty($deleteRequests)): ?>
                <p>No delete requests yet.</p>
            <?php endif; ?>
        </div>
    </section>
</main>
</body>
</html>
