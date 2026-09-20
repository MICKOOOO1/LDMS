<?php
session_start();
require '../server/config.php';
require '../server/admin_helpers.php';
require_admin_role(['admin']);

$alerts = $_SESSION['alerts'] ?? [];
unset($_SESSION['alerts']);

$dashboardStats = fetch_dashboard_stats($conn, 'admin');

render_admin_header('Administrator Dashboard', 'dashboard');
?>
    <?php foreach ($alerts as $alert): ?>
        <div class="alert <?= htmlspecialchars($alert['type']); ?>"><?= htmlspecialchars($alert['message']); ?></div>
    <?php endforeach; ?>

    <section class="page-heading">
        <h1>Administrator</h1>
        <p>Welcome back, <?= htmlspecialchars($_SESSION['name'] ?? 'Administrator'); ?>. Use the navigation to manage the system.</p>
    </section>

    <section class="stat-grid" aria-label="Dashboard summary">
        <a class="stat-card" href="user_management.php"><span class="stat-label">Managed Accounts</span><strong><?= $dashboardStats['accounts']; ?></strong><span>Non-super-admin accounts</span></a>
        <a class="stat-card" href="user_management.php"><span class="stat-label">Active Accounts</span><strong><?= $dashboardStats['active_accounts']; ?></strong><span>Approved accounts</span></a>
        <a class="stat-card" href="pending.php"><span class="stat-label">Pending Approvals</span><strong><?= $dashboardStats['pending']; ?></strong><span>Waiting for review</span></a>
        <a class="stat-card" href="logs.php"><span class="stat-label">Logins Today</span><strong><?= $dashboardStats['logins_today']; ?></strong><span><?= $dashboardStats['active_sessions']; ?> active sessions</span></a>
        <a class="stat-card" href="user_management.php"><span class="stat-label">Added This Month</span><strong><?= $dashboardStats['new_accounts']; ?></strong><span>New registrations</span></a>
    </section>

    <section class="panel dashboard-actions">
        <h2>Quick Actions</h2>
        <div class="action-grid">
            <a href="user_management.php">User Management</a>
            <a href="pending.php">Pending Approvals</a>
            <a href="delete_requests.php">Deletion Request</a>
            <a href="logs.php">System Logs</a>
            <a href="my_account.php">My Account</a>
        </div>
    </section>
<?php render_admin_footer(); ?>