<?php
session_start();
require 'config.php';
require 'admin_helpers.php';
require 'admin_layout.php';

$role = $_SESSION['role'] ?? '';
require_admin_role([$role]);
$activePage = $role === 'super_admin' ? 'accounts.php' : 'user_management.php';
$employeeId = trim($_GET['employee_id'] ?? '');
$search = trim($_GET['search'] ?? '');
$roleFilter = trim($_GET['role'] ?? '');
$statusFilter = trim($_GET['status'] ?? '');
$sort = trim($_GET['sort'] ?? 'newest');
$accounts = fetch_accounts($conn, $employeeId, $search, $roleFilter, $statusFilter, $sort);
$alerts = $_SESSION['alerts'] ?? [];
unset($_SESSION['alerts']);
render_admin_header('User Management', 'accounts', $role);
?>
<section class="page-heading"><h1>User Management</h1><p>Search and update user accounts.</p></section>
<?php foreach ($alerts as $alert): ?><div class="alert <?= htmlspecialchars($alert['type']); ?>"><?= htmlspecialchars($alert['message']); ?></div><?php endforeach; ?>
<section class="panel">
    <div class="section-bar">
        <h2>Account Directory</h2>
        <form method="GET" class="directory-toolbar">
            <label class="search-field"><i class="bx bx-search"></i><input type="search" name="search" value="<?= htmlspecialchars($search); ?>" placeholder="Search users..."></label>
            <input type="text" name="employee_id" value="<?= htmlspecialchars($employeeId); ?>" placeholder="Employee ID">
            <select name="role" aria-label="Filter by role"><option value="">All roles</option><option value="user" <?= $roleFilter === 'user' ? 'selected' : ''; ?>>Customer</option><option value="admin" <?= $roleFilter === 'admin' ? 'selected' : ''; ?>>Admin</option><option value="super_admin" <?= $roleFilter === 'super_admin' ? 'selected' : ''; ?>>Super Admin</option></select>
            <select name="status" aria-label="Filter by status"><option value="">All status</option><option value="approved" <?= $statusFilter === 'approved' ? 'selected' : ''; ?>>Approved / Active</option><option value="pending" <?= $statusFilter === 'pending' ? 'selected' : ''; ?>>Pending</option><option value="blocked" <?= $statusFilter === 'blocked' ? 'selected' : ''; ?>>Blocked</option></select>
            <select name="sort" aria-label="Sort accounts"><option value="newest" <?= $sort === 'newest' ? 'selected' : ''; ?>>Newest first</option><option value="oldest" <?= $sort === 'oldest' ? 'selected' : ''; ?>>Oldest first</option><option value="name" <?= $sort === 'name' ? 'selected' : ''; ?>>Name A-Z</option></select>
            <button type="submit">Filter</button><a href="<?= htmlspecialchars($activePage); ?>">Clear</a>
        </form>
    </div>
    <div class="table-wrap"><table class="data-table account-table">
        <thead><tr><th>ID Number</th><th>Full Name</th><th>Email Address</th><th>Username</th><th>Role</th><th>Status</th><th>Action</th></tr></thead>
        <tbody>
        <?php foreach ($accounts as $account): ?>
            <?php if ($account['role'] === 'super_admin' && $role !== 'super_admin') continue; ?>
            <tr>
                <td><?= htmlspecialchars($account['user_id']); ?></td><td class="name-cell"><?= htmlspecialchars(full_name($account)); ?></td><td><?= htmlspecialchars($account['email']); ?></td><td><?= htmlspecialchars($account['username']); ?></td><td><?= htmlspecialchars($account['role']); ?></td><td><span class="status-pill status-<?= htmlspecialchars($account['account_status']); ?>"><?= htmlspecialchars(ucfirst($account['account_status'])); ?></span></td>
                <td><form action="../server/admin_process.php" method="POST" class="inline-form"><input type="hidden" name="return_page" value="<?= htmlspecialchars($activePage); ?>"><input type="hidden" name="target_id" value="<?= (int)$account['id']; ?>"><select name="account_status"><option value="pending" <?= $account['account_status'] === 'pending' ? 'selected' : ''; ?>>Pending</option><option value="approved" <?= $account['account_status'] === 'approved' ? 'selected' : ''; ?>>Approved</option><option value="blocked" <?= $account['account_status'] === 'blocked' ? 'selected' : ''; ?>>Blocked</option></select><button type="submit" name="change_status_btn">Save</button></form></td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$accounts): ?><tr><td colspan="7">No accounts found.</td></tr><?php endif; ?>
        </tbody>
    </table></div>
</section>
<?php render_admin_footer(); ?>
