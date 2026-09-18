<?php
session_start();
require '../server/config.php';
require '../server/admin_helpers.php';
require_admin_role(['admin']);

$alerts = $_SESSION['alerts'] ?? [];
unset($_SESSION['alerts']);

$employeeId = trim($_GET['employee_id'] ?? '');
$search = trim($_GET['search'] ?? '');
$roleFilter = trim($_GET['role'] ?? '');
$statusFilter = trim($_GET['status'] ?? '');
$sort = trim($_GET['sort'] ?? 'newest');
$dashboardStats = fetch_dashboard_stats($conn, 'admin');
$pendingRegistrations = fetch_pending_registrations($conn);
$accounts = fetch_accounts($conn, $employeeId, $search, $roleFilter, $statusFilter, $sort);
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
        <a href="dashboard.php" class="active">Dashboard</a>
        <a href="user_management.php">User Management</a>
        <a href="pending.php">Pending Approval</a>
        <a href="logs.php">System Logs</a>
        <a href="my_account.php">My Account</a>
    </nav>
</header>
<div class="topbar">
    <span><?= htmlspecialchars($_SESSION['name'] ?? 'Administrator'); ?></span>
    <a href="../user/logout.php">Logout</a>
</div>

<main>
    <?php foreach ($alerts as $alert): ?>
        <div class="alert <?= htmlspecialchars($alert['type']); ?>"><?= htmlspecialchars($alert['message']); ?></div>
    <?php endforeach; ?>

    <section class="page-heading">
        <h1>Administrator</h1>
        <p>Monitor account activity, review registrations, and manage user information.</p>
    </section>

    <section class="stat-grid" aria-label="Dashboard summary">
        <a class="stat-card" href="#accounts"><span class="stat-label">Managed Accounts</span><strong><?= $dashboardStats['accounts']; ?></strong><span>Non-super-admin accounts</span></a>
        <a class="stat-card" href="#accounts"><span class="stat-label">Active Accounts</span><strong><?= $dashboardStats['active_accounts']; ?></strong><span>Approved accounts</span></a>
        <a class="stat-card" href="#pending-registrations"><span class="stat-label">Pending Approvals</span><strong><?= $dashboardStats['pending']; ?></strong><span>Waiting for review</span></a>
        <a class="stat-card" href="logs.php"><span class="stat-label">Logins Today</span><strong><?= $dashboardStats['logins_today']; ?></strong><span><?= $dashboardStats['active_sessions']; ?> active sessions</span></a>
        <a class="stat-card" href="#accounts"><span class="stat-label">Added This Month</span><strong><?= $dashboardStats['new_accounts']; ?></strong><span>New registrations</span></a>
    </section>

    <section class="panel dashboard-activity">
        <div class="section-bar"><h2>Recent Login Activity</h2><a href="logs.php">View all logs</a></div>
        <div class="table-wrap"><table class="data-table"><thead><tr><th>ID Number</th><th>Full Name</th><th>Role</th><th>Time In</th><th>Time Out</th></tr></thead><tbody>
        <?php foreach ($dashboardStats['recent_logins'] as $log): ?>
            <tr><td><?= htmlspecialchars($log['employee_id']); ?></td><td><?= htmlspecialchars($log['full_name']); ?></td><td><?= htmlspecialchars($log['role']); ?></td><td><?= htmlspecialchars($log['time_in']); ?></td><td><?= htmlspecialchars($log['time_out'] ?? 'Active session'); ?></td></tr>
        <?php endforeach; ?>
        <?php if (empty($dashboardStats['recent_logins'])): ?><tr><td colspan="5">No login activity recorded yet.</td></tr><?php endif; ?>
        </tbody></table></div>
    </section>

    <section class="panel" id="pending-registrations">
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

    <section class="panel" id="accounts">
        <div class="section-bar">
            <h2>Accounts</h2>
            <form method="GET" class="directory-toolbar">
                <label class="search-field"><i class="bx bx-search"></i><input type="search" name="search" value="<?= htmlspecialchars($search); ?>" placeholder="Search users..."></label>
                <input type="text" name="employee_id" value="<?= htmlspecialchars($employeeId); ?>" placeholder="Employee ID">
                <select name="role" aria-label="Filter by role">
                    <option value="">All roles</option>
                    <option value="user" <?= $roleFilter === 'user' ? 'selected' : ''; ?>>Customer</option>
                    <option value="admin" <?= $roleFilter === 'admin' ? 'selected' : ''; ?>>Admin</option>
                    <option value="super_admin" <?= $roleFilter === 'super_admin' ? 'selected' : ''; ?>>Super Admin</option>
                </select>
                <select name="status" aria-label="Filter by status">
                    <option value="">All status</option>
                    <option value="approved" <?= $statusFilter === 'approved' ? 'selected' : ''; ?>>Approved / Active</option>
                    <option value="pending" <?= $statusFilter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                    <option value="blocked" <?= $statusFilter === 'blocked' ? 'selected' : ''; ?>>Blocked</option>
                </select>
                <select name="sort" aria-label="Sort accounts">
                    <option value="newest" <?= $sort === 'newest' ? 'selected' : ''; ?>>Newest first</option>
                    <option value="oldest" <?= $sort === 'oldest' ? 'selected' : ''; ?>>Oldest first</option>
                    <option value="name" <?= $sort === 'name' ? 'selected' : ''; ?>>Name A-Z</option>
                </select>
                <button type="submit">Filter</button>
                <a href="dashboard.php">Clear</a>
            </form>
        </div>

        <div class="table-wrap">
            <table class="data-table account-table">
                <thead><tr><th>ID Number</th><th>Full Name</th><th>Email Address</th><th>Username</th><th>Role</th><th>Approval</th><th>Status</th><th>Actions</th></tr></thead>
                <tbody>
            <?php foreach ($accounts as $account): ?>
                <?php if ($account['role'] === 'super_admin') { continue; } ?>
                <tr>
                    <td><?= htmlspecialchars($account['user_id']); ?></td>
                    <td class="name-cell"><?= htmlspecialchars(full_name($account)); ?></td>
                    <td><?= htmlspecialchars($account['email']); ?></td>
                    <td><?= htmlspecialchars($account['username']); ?></td>
                    <td><span class="role-pill role-<?= htmlspecialchars($account['role']); ?>"><?= $account['role'] === 'user' ? 'Customer' : 'Admin'; ?></span></td>
                    <td><span class="status-pill approval-<?= htmlspecialchars($account['account_status']); ?>"><?= $account['account_status'] === 'pending' ? 'Pending' : 'Approved'; ?></span></td>
                    <td><span class="status-pill status-<?= htmlspecialchars($account['account_status']); ?>"><?= $account['account_status'] === 'pending' ? 'Pending First Login' : ucfirst($account['account_status'] === 'approved' ? 'active' : $account['account_status']); ?></span></td>
                    <td>
                        <details class="row-actions">
                            <summary aria-label="Actions for <?= htmlspecialchars(full_name($account)); ?>">&#8942;</summary>
                            <div class="action-menu">
                                <button type="button" class="menu-item" onclick="this.closest('td').querySelector('.account-details').classList.toggle('is-visible')">
                                    <i class="bx bx-show"></i><span>View Details</span>
                                </button>
                                <button type="button" class="menu-item" onclick="this.closest('td').querySelector('.account-edit-form').classList.toggle('is-visible')">
                                    <i class="bx bx-edit-alt"></i><span>Edit</span>
                                </button>
                                <button type="button" class="menu-item" disabled title="Password reset is not available yet">
                                    <i class="bx bx-key"></i><span>Reset Password</span>
                                </button>

                                <form action="../server/admin_process.php" method="POST">
                                    <input type="hidden" name="target_id" value="<?= (int)$account['id']; ?>">
                                    <input type="hidden" name="account_status" value="approved">
                                    <button type="submit" name="change_status_btn" class="menu-item"><i class="bx bx-check"></i><span>Approve</span></button>
                                </form>
                                <form action="../server/admin_process.php" method="POST">
                                    <input type="hidden" name="target_id" value="<?= (int)$account['id']; ?>">
                                    <input type="hidden" name="account_status" value="blocked">
                                    <button type="submit" name="change_status_btn" class="menu-item danger-item"><i class="bx bx-x"></i><span>Reject</span></button>
                                </form>
                                <form action="../server/admin_process.php" method="POST">
                                    <input type="hidden" name="target_id" value="<?= (int)$account['id']; ?>">
                                    <input type="hidden" name="account_status" value="blocked">
                                    <button type="submit" name="block_account_btn" class="menu-item"><i class="bx bx-block"></i><span>Block</span></button>
                                </form>
                                <form action="../server/admin_process.php" method="POST" onsubmit="return confirm('Delete this account?');">
                                    <input type="hidden" name="target_id" value="<?= (int)$account['id']; ?>">
                                    <button type="submit" name="delete_account_btn" class="menu-item danger-item"><i class="bx bx-trash"></i><span>Delete</span></button>
                                </form>
                            </div>
                            <div class="account-details">
                                <strong><?= htmlspecialchars(full_name($account)); ?></strong>
                                <span><?= htmlspecialchars($account['email']); ?></span>
                                <span><?= htmlspecialchars($account['user_id']); ?> &middot; <?= htmlspecialchars($account['username']); ?></span>
                            </div>
                            <form action="../server/admin_process.php" method="POST" class="grid-form compact account-edit-form">
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
                        </details>
                    </td>
                </tr>
            <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        </div>
    </section>
</main>
</body>
</html>
