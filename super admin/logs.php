<?php
session_start();
require '../server/config.php';
require '../server/admin_helpers.php';
require_admin_role(['super_admin']);
require '_header.php';

$alerts = $_SESSION['alerts'] ?? [];
unset($_SESSION['alerts']);

$page = max(1, (int)($_GET['page'] ?? 1));
$search = trim($_GET['search'] ?? '');
$roleFilter = trim($_GET['role'] ?? '');
$actionFilter = trim($_GET['action'] ?? '');
$date = trim($_GET['date'] ?? '');
$perPage = (int)($_GET['per_page'] ?? 10);
$perPage = in_array($perPage, [10, 25, 50], true) ? $perPage : 10;
$logData = fetch_super_admin_system_logs($conn, $page, $perPage, $search, $roleFilter, $actionFilter, $date);

function super_admin_logs_page_url(array $overrides = []): string {
    $params = array_merge($_GET, $overrides);
    foreach ($params as $key => $value) {
        if ($value === '' || $value === null) {
            unset($params[$key]);
        }
    }
    return 'logs.php' . ($params ? '?' . http_build_query($params) : '');
}

function super_admin_log_role_label(string $role): string {
    return $role === 'super_admin' ? 'Super Admin' : ucfirst(str_replace('_', ' ', $role));
}

function super_admin_log_action_label(string $action): string {
    $labels = [
        'login' => 'Login',
        'active_session' => 'Active Session',
        'block_account' => 'Blocked Account',
        'unblock_account' => 'Unblocked Account',
        'create_account' => 'Created Account',
        'delete_account' => 'Deleted Account',
        'request_delete' => 'Requested Deletion',
        'delete_request_reviewed' => 'Reviewed Delete Request',
        'change_role' => 'Changed Role',
    ];

    return $labels[$action] ?? ucfirst(str_replace('_', ' ', $action));
}

render_super_admin_header('System Logs', 'logs');
?>
<?php foreach ($alerts as $alert): ?>
    <div class="alert <?= htmlspecialchars($alert['type']); ?>"><?= htmlspecialchars($alert['message']); ?></div>
<?php endforeach; ?>

<section class="page-heading">
    <h1>Activity Logs</h1>
    <p>Login activity and account actions performed across the system.</p>
</section>

<section class="panel activity-log-panel">
    <div class="section-bar activity-log-heading">
        <div>
            <h2>Activity Logs</h2>
            <p>Account actions and access history</p>
        </div>
    </div>

    <form method="GET" class="activity-log-filters">
        <input type="search" name="search" value="<?= htmlspecialchars($search); ?>" placeholder="Search logs...">
        <select name="action" aria-label="Filter by action">
            <option value="">All Actions</option>
            <option value="login" <?= $actionFilter === 'login' ? 'selected' : ''; ?>>Login</option>
            <option value="active_session" <?= $actionFilter === 'active_session' ? 'selected' : ''; ?>>Active Session</option>
            <option value="block_account" <?= $actionFilter === 'block_account' ? 'selected' : ''; ?>>Blocked Account</option>
            <option value="unblock_account" <?= $actionFilter === 'unblock_account' ? 'selected' : ''; ?>>Unblocked Account</option>
            <option value="create_account" <?= $actionFilter === 'create_account' ? 'selected' : ''; ?>>Created Account</option>
            <option value="delete_account" <?= $actionFilter === 'delete_account' ? 'selected' : ''; ?>>Deleted Account</option>
            <option value="request_delete" <?= $actionFilter === 'request_delete' ? 'selected' : ''; ?>>Requested Deletion</option>
            <option value="delete_request_reviewed" <?= $actionFilter === 'delete_request_reviewed' ? 'selected' : ''; ?>>Reviewed Delete Request</option>
            <option value="change_role" <?= $actionFilter === 'change_role' ? 'selected' : ''; ?>>Changed Role</option>
        </select>
        <select name="role" aria-label="Filter by role">
            <option value="">All Roles</option>
            <option value="user" <?= $roleFilter === 'user' ? 'selected' : ''; ?>>User</option>
            <option value="admin" <?= $roleFilter === 'admin' ? 'selected' : ''; ?>>Admin</option>
            <option value="super_admin" <?= $roleFilter === 'super_admin' ? 'selected' : ''; ?>>Super Admin</option>
        </select>
        <input type="date" name="date" value="<?= htmlspecialchars($date); ?>" aria-label="Filter by date">
        <select name="per_page" aria-label="Rows per page">
            <option value="10" <?= $perPage === 10 ? 'selected' : ''; ?>>10 per page</option>
            <option value="25" <?= $perPage === 25 ? 'selected' : ''; ?>>25 per page</option>
            <option value="50" <?= $perPage === 50 ? 'selected' : ''; ?>>50 per page</option>
        </select>
        <button type="submit">Filter</button>
        <a href="logs.php">Clear</a>
    </form>

    <div class="table-wrap">
        <table class="data-table activity-log-table">
            <thead>
                <tr>
                    <th>User ID</th>
                    <th>Name</th>
                    <th>Role</th>
                    <th>Action</th>
                    <th>Date &amp; Time</th>
                    <th>Session End</th>
                    <th>Details</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($logData['logs'] as $log): ?>
                    <tr>
                        <td><?= htmlspecialchars($log['employee_id']); ?></td>
                        <td><?= htmlspecialchars($log['full_name']); ?></td>
                        <td><?= htmlspecialchars(super_admin_log_role_label($log['role'])); ?></td>
                        <?php $isSessionEvent = in_array($log['action'], ['login', 'active_session'], true); ?>
                        <td><span class="log-action <?= $isSessionEvent ? 'log-action-session' : 'log-action-account'; ?>"><?= htmlspecialchars(super_admin_log_action_label($log['action'])); ?></span></td>
                        <td><?= htmlspecialchars($log['occurred_at']); ?></td>
                        <td><?= $isSessionEvent ? htmlspecialchars($log['time_out'] ?? 'Active') : '&mdash;'; ?></td>
                        <td><?= htmlspecialchars($log['details']); ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($logData['logs'])): ?>
                    <tr><td colspan="7">No activity logs found for the selected filters.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($logData['pages'] > 1): ?>
        <div class="pagination" aria-label="Login log pages">
            <?php if ($page > 1): ?><a href="<?= htmlspecialchars(super_admin_logs_page_url(['page' => $page - 1])); ?>">Previous</a><?php endif; ?>
                <span>Page <?= $page; ?> of <?= $logData['pages']; ?></span>
            <?php if ($page < $logData['pages']): ?><a href="<?= htmlspecialchars(super_admin_logs_page_url(['page' => $page + 1])); ?>">Next</a><?php endif; ?>
        </div>
    <?php endif; ?>
</section>
<?php render_super_admin_footer(); ?>
