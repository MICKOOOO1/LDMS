<?php
session_start();
require '../server/config.php';
require '../server/admin_helpers.php';
require_admin_role(['super_admin']);
require '_header.php';

$alerts = $_SESSION['alerts'] ?? [];
unset($_SESSION['alerts']);

$pendingResult = $conn->query("SELECT COUNT(*) AS total FROM users WHERE role = 'user' AND account_status = 'pending'");
$adminResult = $conn->query("SELECT COUNT(*) AS total FROM users WHERE role IN ('admin', 'super_admin')");
$accountResult = $conn->query("SELECT COUNT(*) AS total FROM users");
$requestResult = $conn->query("SELECT COUNT(*) AS total FROM delete_requests WHERE request_status = 'pending'");

$pendingCount = $pendingResult ? (int)$pendingResult->fetch_assoc()['total'] : 0;
$adminCount = $adminResult ? (int)$adminResult->fetch_assoc()['total'] : 0;
$accountCount = $accountResult ? (int)$accountResult->fetch_assoc()['total'] : 0;
$requestCount = $requestResult ? (int)$requestResult->fetch_assoc()['total'] : 0;

render_super_admin_header('Super Admin Dashboard', '');
?>
<section class="page-heading">
    <h1>Super Admin Dashboard</h1>
    <p>Manage user accounts, approvals, system logs, deletion requests, and your account.</p>
</section>

<?php foreach ($alerts as $alert): ?>
    <div class="alert <?= htmlspecialchars($alert['type']); ?>"><?= htmlspecialchars($alert['message']); ?></div>
<?php endforeach; ?>

<section class="stat-grid" aria-label="System summary">
    <a class="stat-card" href="pending.php">
        <span class="stat-label">Pending Approvals</span>
        <strong><?= $pendingCount; ?></strong>
        <span>Review approvals</span>
    </a>
    <a class="stat-card" href="accounts.php">
        <span class="stat-label">Administrator Accounts</span>
        <strong><?= $adminCount; ?></strong>
        <span>Manage staff access</span>
    </a>
    <a class="stat-card" href="accounts.php">
        <span class="stat-label">User Management</span>
        <strong><?= $accountCount; ?></strong>
        <span>View account directory</span>
    </a>
    <a class="stat-card" href="delete_requests.php">
        <span class="stat-label">Deletion Requests</span>
        <strong><?= $requestCount; ?></strong>
        <span>Review requests</span>
    </a>
</section>

<section class="panel dashboard-actions">
    <h2>Quick Actions</h2>
    <div class="action-grid">
        <a href="pending.php">Pending Approvals</a>
        <a href="accounts.php">User Management</a>
        <a href="create_admin.php">Create Administrator</a>
        <a href="delete_requests.php">Deletion Request</a>
        <a href="logs.php">System Logs</a>
        <a href="my_account.php">My Account</a>
    </div>
</section>
<?php render_super_admin_footer(); ?>
