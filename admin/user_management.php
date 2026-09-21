<?php
session_start();
require '../server/config.php';
require '../server/admin_helpers.php';
require_admin_role(['admin']);
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

$alerts = $_SESSION['alerts'] ?? [];
unset($_SESSION['alerts']);

$employeeId = trim($_GET['employee_id'] ?? '');
$search = trim($_GET['search'] ?? '');
$roleFilter = trim($_GET['role'] ?? '');
$statusFilter = trim($_GET['status'] ?? '');
$sort = trim($_GET['sort'] ?? 'name');
$perPage = (int)($_GET['per_page'] ?? 10);
$perPage = in_array($perPage, [10, 25, 50], true) ? $perPage : 10;
$page = max(1, (int)($_GET['page'] ?? 1));

$allAccounts = fetch_accounts($conn, $employeeId, $search, $roleFilter, $statusFilter, $sort);
$totalAccounts = count($allAccounts);
$totalPages = max(1, (int)ceil($totalAccounts / $perPage));
$page = min($page, $totalPages);
$accounts = array_slice($allAccounts, ($page - 1) * $perPage, $perPage);

function user_management_url(array $overrides = []): string {
    $params = array_merge($_GET, $overrides);
    foreach ($params as $key => $value) {
        if ($value === '' || $value === null) {
            unset($params[$key]);
        }
    }
    return 'user_management.php' . ($params ? '?' . http_build_query($params) : '');
}

function user_management_role_label(string $role): string {
    return $role === 'super_admin' ? 'Super Admin' : ($role === 'admin' ? 'Admin' : 'User');
}

function account_status_label(string $status): string {
    return $status === 'approved' ? 'Active' : ucfirst($status);
}

function account_online_status(array $account): string {
    return !empty($account['online_last_seen']) ? 'active' : 'inactive';
}

render_admin_header('User Management', 'user_management');
?>
    <?php if (!empty($alerts)): ?>
        <div class="admin-toast-wrap" role="status" aria-live="polite">
            <?php foreach ($alerts as $alert): ?>
                <div class="admin-toast <?= htmlspecialchars($alert['type']); ?>">
                    <span><?= htmlspecialchars($alert['message']); ?></span>
                    <button type="button" aria-label="Close notification" onclick="this.parentElement.remove()">&times;</button>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <section class="page-heading user-management-heading">
        <div>
            <h1>User Management</h1>
            <p>Manage accounts, approvals, and privileges.</p>
        </div>
    </section>

    <section class="panel user-management-panel">
        <form method="GET" class="user-management-toolbar">
            <div class="toolbar-search">
                <label class="search-field">
                    <i class="bx bx-search"></i>
                    <input type="search" name="search" value="<?= htmlspecialchars($search); ?>" placeholder="Search users...">
                </label>
                <label class="search-field">
                    <i class="bx bx-id-card"></i>
                    <input type="text" name="employee_id" value="<?= htmlspecialchars($employeeId); ?>" placeholder="Filter by Employee ID">
                </label>
            </div>

            <div class="toolbar-filters">
                <strong>Filters:</strong>
                <select name="role" aria-label="Filter by role">
                    <option value="">All Roles</option>
                    <option value="user" <?= $roleFilter === 'user' ? 'selected' : ''; ?>>User</option>
                    <option value="admin" <?= $roleFilter === 'admin' ? 'selected' : ''; ?>>Admin</option>
                    <option value="super_admin" <?= $roleFilter === 'super_admin' ? 'selected' : ''; ?>>Super Admin</option>
                </select>
                        <select name="status" aria-label="Filter by status">
                            <option value="">All Status</option>
                            <option value="approved" <?= $statusFilter === 'approved' ? 'selected' : ''; ?>>Active</option>
                            <option value="blocked" <?= $statusFilter === 'blocked' ? 'selected' : ''; ?>>Blocked</option>
                        </select>
                <select name="sort" aria-label="Sort accounts">
                    <option value="name" <?= $sort === 'name' ? 'selected' : ''; ?>>Name A-Z</option>
                    <option value="newest" <?= $sort === 'newest' ? 'selected' : ''; ?>>Newest First</option>
                    <option value="oldest" <?= $sort === 'oldest' ? 'selected' : ''; ?>>Oldest First</option>
                </select>
                <select name="per_page" aria-label="Rows per page">
                    <option value="10" <?= $perPage === 10 ? 'selected' : ''; ?>>10 per page</option>
                    <option value="25" <?= $perPage === 25 ? 'selected' : ''; ?>>25 per page</option>
                    <option value="50" <?= $perPage === 50 ? 'selected' : ''; ?>>50 per page</option>
                </select>
                <button type="submit">Apply</button>
                <a href="user_management.php" class="clear-link">Clear</a>
            </div>
        </form>

        <div class="table-wrap user-management-table-wrap">
            <table class="data-table user-management-table">
                <thead>
                    <tr>
                        <th>ID Number</th>
                        <th>Full Name</th>
                        <th>Email Address</th>
                        <th>Username</th>
                        <th>Role</th>
                        <th>Approval</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($accounts as $account): ?>
                        <?php $status = $account['account_status'] ?? 'pending'; ?>
                        <tr>
                            <td><?= htmlspecialchars($account['user_id']); ?></td>
                            <td><strong><?= htmlspecialchars(full_name($account)); ?></strong></td>
                            <td><?= htmlspecialchars($account['email']); ?></td>
                            <td><?= htmlspecialchars($account['username']); ?></td>
                            <td><span class="role-pill role-<?= htmlspecialchars($account['role']); ?>"><?= htmlspecialchars(user_management_role_label($account['role'])); ?></span></td>
                            <td><span class="approval-pill approval-<?= htmlspecialchars($status); ?>"><?= htmlspecialchars(ucfirst($status)); ?></span></td>
                            <?php $onlineStatus = account_online_status($account); ?>
                            <td><span class="status-pill status-<?= htmlspecialchars($onlineStatus); ?>"><?= htmlspecialchars(ucfirst($onlineStatus)); ?></span></td>
                            <td>
                                <a href="#manage-account-<?= (int)$account['id']; ?>" class="actions-trigger" aria-label="Manage account">⋮</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($accounts)): ?>
                        <tr><td colspan="8">No accounts found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="pagination user-management-pagination">
            <?php if ($page > 1): ?><a href="<?= htmlspecialchars(user_management_url(['page' => $page - 1])); ?>">Previous</a><?php endif; ?>
            <span>Page <?= $page; ?> of <?= $totalPages; ?> · <?= $totalAccounts; ?> account<?= $totalAccounts === 1 ? '' : 's'; ?></span>
            <?php if ($page < $totalPages): ?><a href="<?= htmlspecialchars(user_management_url(['page' => $page + 1])); ?>">Next</a><?php endif; ?>
        </div>
    </section>

    <?php foreach ($accounts as $account): ?>
        <?php $status = $account['account_status'] ?? 'pending'; ?>
        <div id="manage-account-<?= (int)$account['id']; ?>" class="account-modal" aria-hidden="true">
            <a href="#" class="account-modal-backdrop" aria-label="Close modal"></a>
            <section class="account-modal-card" role="dialog" aria-modal="true" aria-labelledby="manage-account-title-<?= (int)$account['id']; ?>">
                <div class="account-modal-header">
                    <div>
                        <h2 id="manage-account-title-<?= (int)$account['id']; ?>">Manage Account</h2>
                        <p><?= htmlspecialchars(full_name($account)); ?> · <?= htmlspecialchars($account['user_id']); ?></p>
                    </div>
                    <a href="#" class="account-modal-close" aria-label="Close modal">&times;</a>
                </div>

                <form action="../server/admin_process.php" method="POST" class="modal-inline-form">
                    <input type="hidden" name="target_id" value="<?= (int)$account['id']; ?>">
                    <select name="account_status">
                        <option value="pending" <?= $status === 'pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="approved" <?= $status === 'approved' ? 'selected' : ''; ?>>Approved</option>
                        <option value="blocked" <?= $status === 'blocked' ? 'selected' : ''; ?>>Blocked</option>
                    </select>
                    <button type="submit" name="change_status_btn">Save Status</button>
                </form>

                <form action="../server/admin_process.php" method="POST" class="modal-grid-form">
                    <input type="hidden" name="target_id" value="<?= (int)$account['id']; ?>">
                    <label>First Name<input type="text" name="first_name" value="<?= htmlspecialchars($account['first_name']); ?>" required></label>
                    <label>Middle Name<input type="text" name="middle_name" value="<?= htmlspecialchars($account['middle_name'] ?? ''); ?>"></label>
                    <label>Last Name<input type="text" name="last_name" value="<?= htmlspecialchars($account['last_name']); ?>" required></label>
                    <label>Extension<input type="text" name="extension_name" value="<?= htmlspecialchars($account['extension_name'] ?? ''); ?>"></label>
                    <label>Email<input type="email" name="email" value="<?= htmlspecialchars($account['email']); ?>" required></label>
                    <label>Username<input type="text" name="username" value="<?= htmlspecialchars($account['username']); ?>" required readonly></label>
                    <label>Purok/Street<input type="text" name="purok_street" value="<?= htmlspecialchars($account['purok_street']); ?>"></label>
                    <label>Barangay<input type="text" name="barangay" value="<?= htmlspecialchars($account['barangay']); ?>"></label>
                    <label>City/Municipality<input type="text" name="city_municipality" value="<?= htmlspecialchars($account['city_municipality']); ?>"></label>
                    <label>Province<input type="text" name="province" value="<?= htmlspecialchars($account['province']); ?>"></label>
                    <label>Country<input type="text" name="country" value="<?= htmlspecialchars($account['country']); ?>"></label>
                    <label>Zip Code<input type="text" name="zip_code" value="<?= htmlspecialchars($account['zip_code']); ?>"></label>
                    <div class="modal-actions">
                        <a href="#" class="modal-cancel">Cancel</a>
                        <button type="submit" name="update_account_btn">Update Info</button>
                    </div>
                </form>

                <?php if ($account['role'] === 'admin'): ?>
                    <?php $privileges = array_filter(array_map('trim', explode(',', $account['privileges'] ?? ''))); ?>
                    <form action="../server/admin_process.php" method="POST" class="modal-privileges">
                        <input type="hidden" name="target_id" value="<?= (int)$account['id']; ?>">
                        <label><input type="checkbox" name="privileges[]" value="manage_registration" <?= in_array('manage_registration', $privileges, true) ? 'checked' : ''; ?>> Registration</label>
                        <label><input type="checkbox" name="privileges[]" value="update_accounts" <?= in_array('update_accounts', $privileges, true) ? 'checked' : ''; ?>> Update</label>
                        <label><input type="checkbox" name="privileges[]" value="request_delete" <?= in_array('request_delete', $privileges, true) ? 'checked' : ''; ?>> Delete Requests</label>
                        <button type="submit" name="save_privileges_btn">Save Privileges</button>
                    </form>
                <?php endif; ?>

                <?php if ((int)$account['id'] !== (int)$_SESSION['user_id']): ?>
                    <form action="../server/admin_process.php" method="POST" class="modal-danger-form" onsubmit="return confirm('Delete this account permanently?');">
                        <input type="hidden" name="target_id" value="<?= (int)$account['id']; ?>">
                        <button type="submit" name="delete_account_btn" class="danger">Delete Account</button>
                    </form>
                <?php endif; ?>
            </section>
        </div>
    <?php endforeach; ?>
<?php render_admin_footer(); ?>
