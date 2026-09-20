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
$logData = fetch_login_logs($conn, 'super_admin', '', $date, $page, $perPage, $search, $roleFilter, $actionFilter);

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

function super_admin_log_action(array $log): string {
    return empty($log['time_out']) ? 'active_session' : 'login';
}

function super_admin_log_action_label(array $log): string {
    return empty($log['time_out']) ? 'Active Session' : 'Login';
}

function super_admin_log_details(array $log): string {
    if (empty($log['time_out'])) {
        return $log['full_name'] . ' is currently logged in.';
    }

    return $log['full_name'] . ' logged in successfully.';
}

render_super_admin_header('System Logs', 'logs');
?>
<?php foreach ($alerts as $alert): ?>
    <div class="alert <?= htmlspecialchars($alert['type']); ?>"><?= htmlspecialchars($alert['message']); ?></div>
<?php endforeach; ?>

<section class="page-heading">
    <h1>Activity Logs</h1>
    <p>System audit trail for user, administrator, and super administrator access.</p>
</section>

<section class="panel activity-log-panel">
    <div class="section-bar activity-log-heading">
        <div>
            <h2>Activity Logs</h2>
            <p>System audit trail</p>
        </div>
    </div>

    <form method="GET" class="activity-log-filters">
        <input type="search" name="search" value="<?= htmlspecialchars($search); ?>" placeholder="Search logs...">
        <select name="action" aria-label="Filter by action">
            <option value="">All Actions</option>
            <option value="login" <?= $actionFilter === 'login' ? 'selected' : ''; ?>>Login</option>
            <option value="active_session" <?= $actionFilter === 'active_session' ? 'selected' : ''; ?>>Active Session</option>
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
                    <th>Time In</th>
                    <th>Time Out</th>
                    <th>Details</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($logData['logs'] as $log): ?>
                    <tr>
                        <td><?= htmlspecialchars($log['employee_id']); ?></td>
                        <td><?= htmlspecialchars($log['full_name']); ?></td>
                        <td><?= htmlspecialchars(super_admin_log_role_label($log['role'])); ?></td>
                        <td><span class="log-action log-action-<?= htmlspecialchars(super_admin_log_action($log)); ?>"><?= htmlspecialchars(super_admin_log_action_label($log)); ?></span></td>
                        <td><?= htmlspecialchars($log['time_in']); ?></td>
                        <td><?= htmlspecialchars($log['time_out'] ?? 'Active'); ?></td>
                        <td><?= htmlspecialchars(super_admin_log_details($log)); ?></td>
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
