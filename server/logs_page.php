<?php
session_start();
require 'config.php';
require 'admin_helpers.php';
require 'admin_layout.php';

$role = $_SESSION['role'] ?? '';
require_admin_role([$role]);
$page = max(1, (int)($_GET['page'] ?? 1));
$date = trim($_GET['date'] ?? '');
$logData = fetch_login_logs($conn, $role, '', $date, $page);
render_admin_header('System Logs', 'logs', $role);
?>
<section class="page-heading"><h1>System Logs</h1><p>View sign-in and sign-out activity available to your account.</p></section>
<section class="panel"><div class="section-bar"><h2>Access History</h2><form method="GET" class="filter-form"><label>Date<input type="date" name="date" value="<?= htmlspecialchars($date); ?>"></label><button type="submit">Filter</button><a href="logs.php">Clear</a></form></div><div class="table-wrap"><table class="data-table"><thead><tr><th>ID No.</th><th>Full Name</th><th>Role</th><th>Time In</th><th>Time Out</th></tr></thead><tbody><?php foreach ($logData['logs'] as $log): ?><tr><td><?= htmlspecialchars($log['employee_id']); ?></td><td><?= htmlspecialchars($log['full_name']); ?></td><td><?= htmlspecialchars($log['role']); ?></td><td><?= htmlspecialchars($log['time_in']); ?></td><td><?= htmlspecialchars($log['time_out'] ?? 'Active session'); ?></td></tr><?php endforeach; ?><?php if (!$logData['logs']): ?><tr><td colspan="5">No login logs found.</td></tr><?php endif; ?></tbody></table></div><?php if ($logData['pages'] > 1): ?><div class="pagination"><span>Page <?= $page; ?> of <?= $logData['pages']; ?></span><?php if ($page > 1): ?><a href="?page=<?= $page - 1; ?>&date=<?= urlencode($date); ?>">Previous</a><?php endif; ?><?php if ($page < $logData['pages']): ?><a href="?page=<?= $page + 1; ?>&date=<?= urlencode($date); ?>">Next</a><?php endif; ?></div><?php endif; ?></section>
<?php render_admin_footer(); ?>
