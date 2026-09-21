<?php
session_start();
require '../server/config.php';
require '../server/admin_helpers.php';
require_admin_role(['super_admin']);
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
require '_header.php';

$alerts = $_SESSION['alerts'] ?? [];
unset($_SESSION['alerts']);

// For Create Account modal - auto-generate ID
$createRoleDefault = 'admin';
$createAccountId = generate_next_id($conn, $createRoleDefault);
$superAdminCountResult = $conn->query("SELECT COUNT(*) AS total FROM users WHERE role = 'super_admin'");
$superAdminCount = (int)($superAdminCountResult->fetch_assoc()['total'] ?? 0);

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

function super_admin_accounts_url(array $overrides = []): string {
    $params = array_merge($_GET, $overrides);
    foreach ($params as $key => $value) {
        if ($value === '' || $value === null) {
            unset($params[$key]);
        }
    }
    return 'accounts.php' . ($params ? '?' . http_build_query($params) : '');
}

function super_admin_status_label(string $status): string {
    return $status === 'approved' ? 'Active' : ucfirst($status);
}

function super_admin_online_status(array $account): string {
    return !empty($account['online_last_seen']) ? 'active' : 'inactive';
}

function super_admin_role_label(string $role): string {
    return $role === 'super_admin' ? 'Super Admin' : ($role === 'admin' ? 'Admin' : 'User');
}

render_super_admin_header('User Management', 'accounts');
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
            <a href="#create-account-modal" class="create-account-btn">+ Create Account</a>
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
            <a href="accounts.php" class="clear-link">Clear</a>
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
                        <td><span class="role-pill role-<?= htmlspecialchars($account['role']); ?>"><?= htmlspecialchars(super_admin_role_label($account['role'])); ?></span></td>
                        <td><span class="approval-pill approval-<?= htmlspecialchars($status); ?>"><?= htmlspecialchars(ucfirst($status)); ?></span></td>
                        <?php $onlineStatus = super_admin_online_status($account); ?>
                        <td><span class="status-pill status-<?= htmlspecialchars($onlineStatus); ?>"><?= htmlspecialchars(ucfirst($onlineStatus)); ?></span></td>
                        <td>
                            <div class="actions-menu">
                                <button type="button" class="actions-trigger" aria-label="Manage account" aria-expanded="false">⋮</button>
                                <div class="actions-menu-content">
                                    <a href="#view-account-<?= (int)$account['id']; ?>" class="action-item">View</a>
                                    <a href="#edit-account-<?= (int)$account['id']; ?>" class="action-item">Edit</a>
                                    <?php if ((int)$account['id'] !== (int)$_SESSION['user_id']): ?>
                                        <a href="#role-privilege-<?= (int)$account['id']; ?>" class="action-item">Role and Privilege</a>
                                    <?php endif; ?>
                                    <?php if ((int)$account['id'] !== (int)$_SESSION['user_id']): ?>
                                        <a href="#block-account-<?= (int)$account['id']; ?>" class="action-item <?= $status === 'blocked' ? 'unblock-text' : ''; ?>">
                                            <?= $status === 'blocked' ? 'Unblock' : 'Block' ?>
                                        </a>
                                        <?php if ($status !== 'blocked'): ?>
                                            <a href="#reset-password-<?= (int)$account['id']; ?>" class="action-item">Reset Password</a>
                                        <?php endif; ?>
                                        <a href="#delete-account-<?= (int)$account['id']; ?>" class="action-item danger">Delete</a>
                                    <?php endif; ?>
                                </div>
                            </div>
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
        <?php if ($page > 1): ?><a href="<?= htmlspecialchars(super_admin_accounts_url(['page' => $page - 1])); ?>">Previous</a><?php endif; ?>
        <span>Page <?= $page; ?> of <?= $totalPages; ?> · <?= $totalAccounts; ?> account<?= $totalAccounts === 1 ? '' : 's'; ?></span>
        <?php if ($page < $totalPages): ?><a href="<?= htmlspecialchars(super_admin_accounts_url(['page' => $page + 1])); ?>">Next</a><?php endif; ?>
    </div>
</section>

<?php foreach ($accounts as $account): ?>
    <?php $status = $account['account_status'] ?? 'pending'; ?>
    
    <!-- View Account Modal -->
    <div id="view-account-<?= (int)$account['id']; ?>" class="account-modal" aria-hidden="true">
        <a href="#" class="account-modal-backdrop" aria-label="Close modal"></a>
        <section class="account-modal-card" role="dialog" aria-modal="true" aria-labelledby="view-account-title-<?= (int)$account['id']; ?>">
            <div class="account-modal-header">
                <div>
                    <h2 id="view-account-title-<?= (int)$account['id']; ?>">View Account</h2>
                    <p><?= htmlspecialchars(full_name($account)); ?> · <?= htmlspecialchars($account['user_id']); ?></p>
                </div>
                <a href="#" class="account-modal-close" aria-label="Close modal">&times;</a>
            </div>
            <div class="modal-view-content">
                <div class="view-row"><strong>ID Number:</strong> <span><?= htmlspecialchars($account['user_id']); ?></span></div>
                <div class="view-row"><strong>Full Name:</strong> <span><?= htmlspecialchars(full_name($account)); ?></span></div>
                <div class="view-row"><strong>First Name:</strong> <span><?= htmlspecialchars($account['first_name']); ?></span></div>
                <div class="view-row"><strong>Middle Name:</strong> <span><?= htmlspecialchars($account['middle_name'] ?? 'N/A'); ?></span></div>
                <div class="view-row"><strong>Last Name:</strong> <span><?= htmlspecialchars($account['last_name']); ?></span></div>
                <div class="view-row"><strong>Extension:</strong> <span><?= htmlspecialchars($account['extension_name'] ?? 'N/A'); ?></span></div>
                <div class="view-row"><strong>Email:</strong> <span><?= htmlspecialchars($account['email']); ?></span></div>
                <div class="view-row"><strong>Username:</strong> <span><?= htmlspecialchars($account['username']); ?></span></div>
                <div class="view-row"><strong>Role:</strong> <span><span class="role-pill role-<?= htmlspecialchars($account['role']); ?>"><?= htmlspecialchars(super_admin_role_label($account['role'])); ?></span></span></div>
                <div class="view-row"><strong>Status:</strong> <span><span class="approval-pill approval-<?= htmlspecialchars($status); ?>"><?= htmlspecialchars(ucfirst($status)); ?></span></span></div>
                <div class="view-row"><strong>Active:</strong> <span><span class="status-pill status-<?= htmlspecialchars(super_admin_online_status($account)); ?>"><?= htmlspecialchars(ucfirst(super_admin_online_status($account))); ?></span></span></div>
                <div class="view-row"><strong>Date of Birth:</strong> <span><?= htmlspecialchars($account['dob']); ?></span></div>
                <div class="view-row"><strong>Age:</strong> <span><?= htmlspecialchars($account['age']); ?></span></div>
                <div class="view-row"><strong>Sex:</strong> <span><?= htmlspecialchars($account['sex']); ?></span></div>
                <div class="view-row"><strong>Address:</strong> <span><?= htmlspecialchars($account['purok_street'] . ', ' . $account['barangay'] . ', ' . $account['city_municipality'] . ', ' . $account['province'] . ', ' . $account['country'] . ' ' . $account['zip_code']); ?></span></div>
                <div class="view-row"><strong>Date Registered:</strong> <span><?= htmlspecialchars($account['date_registered'] ?? 'N/A'); ?></span></div>
            </div>
        </section>
    </div>

    <!-- Edit Account Modal -->
    <div id="edit-account-<?= (int)$account['id']; ?>" class="account-modal" aria-hidden="true">
        <a href="#" class="account-modal-backdrop" aria-label="Close modal"></a>
        <section class="account-modal-card" role="dialog" aria-modal="true" aria-labelledby="edit-account-title-<?= (int)$account['id']; ?>">
            <div class="account-modal-header">
                <div>
                    <h2 id="edit-account-title-<?= (int)$account['id']; ?>">Edit Account</h2>
                    <p><?= htmlspecialchars(full_name($account)); ?> · <?= htmlspecialchars($account['user_id']); ?></p>
                </div>
                <a href="#" class="account-modal-close" aria-label="Close modal">&times;</a>
            </div>
            <form action="../server/admin_process.php" method="POST" class="modal-grid-form">
                <input type="hidden" name="return_page" value="accounts.php">
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
        </section>
    </div>

    <?php if ((int)$account['id'] !== (int)$_SESSION['user_id']): ?>
    <!-- Role and Privilege Modal -->
    <div id="role-privilege-<?= (int)$account['id']; ?>" class="account-modal" aria-hidden="true">
        <a href="#" class="account-modal-backdrop" aria-label="Close modal"></a>
        <section class="account-modal-card role-privilege-modal-card" role="dialog" aria-modal="true" aria-labelledby="role-privilege-title-<?= (int)$account['id']; ?>">
            <div class="account-modal-header">
                <div>
                    <h2 id="role-privilege-title-<?= (int)$account['id']; ?>">Role and Privilege</h2>
                    <p><?= htmlspecialchars(full_name($account)); ?> · <?= htmlspecialchars($account['user_id']); ?></p>
                </div>
                <a href="#" class="account-modal-close" aria-label="Close modal">&times;</a>
            </div>
            <form action="../server/admin_process.php" method="POST" class="modal-inline-form">
                <input type="hidden" name="return_page" value="accounts.php">
                <input type="hidden" name="target_id" value="<?= (int)$account['id']; ?>">
                <label>Role
                    <select name="role" required>
                        <option value="user" <?= $account['role'] === 'user' ? 'selected' : ''; ?>>User</option>
                        <option value="admin" <?= $account['role'] === 'admin' ? 'selected' : ''; ?>>Administrator</option>
                        <option value="super_admin" <?= $account['role'] === 'super_admin' ? 'selected' : ''; ?>>Super Administrator</option>
                    </select>
                </label>
                <button type="submit" name="change_role_btn">Save Role</button>
            </form>
            <?php if ($account['role'] === 'admin' || $account['role'] === 'super_admin'): ?>
                <?php $privileges = array_filter(array_map('trim', explode(',', $account['privileges'] ?? ''))); ?>
                <form action="../server/admin_process.php" method="POST" class="modal-privileges">
                    <input type="hidden" name="return_page" value="accounts.php">
                    <input type="hidden" name="target_id" value="<?= (int)$account['id']; ?>">
                    <?php if ($account['role'] === 'admin'): ?>
                        <label><input type="checkbox" name="privileges[]" value="manage_registration" <?= in_array('manage_registration', $privileges, true) ? 'checked' : ''; ?>> Manage Registration</label>
                        <label><input type="checkbox" name="privileges[]" value="update_accounts" <?= in_array('update_accounts', $privileges, true) ? 'checked' : ''; ?>> Update Accounts</label>
                        <label><input type="checkbox" name="privileges[]" value="request_delete" <?= in_array('request_delete', $privileges, true) ? 'checked' : ''; ?>> Request Delete</label>
                    <?php elseif ($account['role'] === 'super_admin'): ?>
                        <label><input type="checkbox" name="privileges[]" value="manage_accounts" <?= in_array('manage_accounts', $privileges, true) ? 'checked' : ''; ?>> Manage Accounts</label>
                        <label><input type="checkbox" name="privileges[]" value="manage_privileges" <?= in_array('manage_privileges', $privileges, true) ? 'checked' : ''; ?>> Manage Privileges</label>
                        <label><input type="checkbox" name="privileges[]" value="delete_accounts" <?= in_array('delete_accounts', $privileges, true) ? 'checked' : ''; ?>> Delete Accounts</label>
                    <?php endif; ?>
                    <button type="submit" name="save_privileges_btn">Save Privileges</button>
                </form>
            <?php endif; ?>
        </section>
    </div>
    <?php endif; ?>

    <?php if ((int)$account['id'] !== (int)$_SESSION['user_id']): ?>
    <!-- Block/Unblock Modal -->
    <div id="block-account-<?= (int)$account['id']; ?>" class="account-modal" aria-hidden="true">
        <a href="#" class="account-modal-backdrop" aria-label="Close modal"></a>
        <section class="account-modal-card compact-modal" role="dialog" aria-modal="true" aria-labelledby="block-account-title-<?= (int)$account['id']; ?>">
            <div class="account-modal-header">
                <div>
                    <h2 id="block-account-title-<?= (int)$account['id']; ?>"><?= $status === 'blocked' ? 'Unblock Account' : 'Block Account' ?></h2>
                    <p><?= htmlspecialchars(full_name($account)); ?> · <?= htmlspecialchars($account['user_id']); ?></p>
                </div>
                <a href="#" class="account-modal-close" aria-label="Close modal">&times;</a>
            </div>
            <form action="../server/admin_process.php" method="POST" class="modal-grid-form">
                <input type="hidden" name="return_page" value="accounts.php">
                <input type="hidden" name="target_id" value="<?= (int)$account['id']; ?>">
                <label>Reason<textarea name="reason" rows="3" placeholder="Enter reason..." required></textarea></label>
                <?php if ($status === 'blocked'): ?>
                    <button type="submit" name="unblock_account_btn">Unblock Account</button>
                <?php else: ?>
                    <button type="submit" name="block_account_btn" class="danger">Block Account</button>
                <?php endif; ?>
            </form>
        </section>
    </div>
    <?php endif; ?>

    <?php if ($status !== 'blocked'): ?>
    <!-- Reset Password Modal -->
    <div id="reset-password-<?= (int)$account['id']; ?>" class="account-modal" aria-hidden="true">
        <a href="#" class="account-modal-backdrop" aria-label="Close modal"></a>
        <section class="account-modal-card" role="dialog" aria-modal="true" aria-labelledby="reset-password-title-<?= (int)$account['id']; ?>">
            <div class="account-modal-header">
                <div>
                    <h2 id="reset-password-title-<?= (int)$account['id']; ?>">Reset Password</h2>
                    <p><?= htmlspecialchars(full_name($account)); ?> · <?= htmlspecialchars($account['user_id']); ?></p>
                </div>
                <a href="#" class="account-modal-close" aria-label="Close modal">&times;</a>
            </div>
            <form action="../server/admin_process.php" method="POST" class="modal-grid-form">
                <input type="hidden" name="return_page" value="accounts.php">
                <input type="hidden" name="target_id" value="<?= (int)$account['id']; ?>">
                <label>New Password<input type="password" name="new_password" required></label>
                <label>Confirm Password<input type="password" name="confirm_password" required></label>
                <div class="modal-actions">
                    <a href="#" class="modal-cancel">Cancel</a>
                    <button type="submit" name="reset_password_btn" class="danger">Reset Password</button>
                </div>
            </form>
        </section>
    </div>
    <?php endif; ?>

    <?php if ((int)$account['id'] !== (int)$_SESSION['user_id']): ?>
    <!-- Delete Account Modal -->
    <div id="delete-account-<?= (int)$account['id']; ?>" class="account-modal" aria-hidden="true">
        <a href="#" class="account-modal-backdrop" aria-label="Close modal"></a>
        <section class="account-modal-card compact-modal" role="dialog" aria-modal="true" aria-labelledby="delete-account-title-<?= (int)$account['id']; ?>">
            <div class="account-modal-header">
                <div>
                    <h2 id="delete-account-title-<?= (int)$account['id']; ?>">Delete Account</h2>
                    <p><?= htmlspecialchars(full_name($account)); ?> · <?= htmlspecialchars($account['user_id']); ?></p>
                </div>
                <a href="#" class="account-modal-close" aria-label="Close modal">&times;</a>
            </div>
            <form action="../server/admin_process.php" method="POST" class="modal-grid-form" onsubmit="return confirm('Delete this account permanently? This action cannot be undone.');">
                <input type="hidden" name="return_page" value="accounts.php">
                <input type="hidden" name="target_id" value="<?= (int)$account['id']; ?>">
                <label>Reason<textarea name="reason" rows="3" placeholder="Enter reason for deletion..." required></textarea></label>
                <button type="submit" name="delete_account_btn" class="danger">Delete Account</button>
            </form>
        </section>
    </div>
    <?php endif; ?>
<?php endforeach; ?>

<div id="create-account-modal" class="account-modal" aria-hidden="true">
    <a href="#" class="account-modal-backdrop" aria-label="Close modal"></a>
    <section class="account-modal-card create-account-modal-card" role="dialog" aria-modal="true" aria-labelledby="create-account-title">
        <div class="account-modal-header">
            <div>
                <h2 id="create-account-title">Create Account</h2>
                <p>Create an administrator or super administrator account.</p>
            </div>
            <a href="#" class="account-modal-close" aria-label="Close modal">&times;</a>
        </div>

        <form action="../server/admin_process.php" method="POST" class="modal-grid-form">
            <input type="hidden" name="return_page" value="accounts.php">
            <label>Role <span class="asterisk">*</span>
                <select name="role" id="createRoleSelect" required onchange="updateCreateAccountId(this.value)">
                    <option value="admin"<?= $createRoleDefault === 'admin' ? ' selected' : ''; ?>>Administrator</option>
                    <option value="super_admin"<?= $createRoleDefault === 'super_admin' ? ' selected' : ''; ?><?= $superAdminCount >= 2 ? ' disabled' : ''; ?>>Super Administrator<?= $superAdminCount >= 2 ? ' (limit reached)' : ''; ?></option>
                </select>
                <small class="input-message"></small>
            </label>
            <label>Employee ID
                <input type="text" name="id_number" id="createAccountId" value="<?= htmlspecialchars($createAccountId); ?>" readonly style="background-color: #e8e8e8; color: #666;">
                <small class="input-message"></small>
            </label>
            <label>Email <span class="asterisk">*</span>
                <input type="email" name="email" required>
                <small class="input-message"></small>
            </label>
            <label>Username <span class="asterisk">*</span>
                <input type="text" name="username" required pattern="[a-z]+\.[a-z]+" title="Use lowercase letters in the format name.name, with no spaces or numbers." autocomplete="off">
                <small class="input-message"></small>
            </label>
            <label>Password <span class="asterisk">*</span>
                <input type="password" name="password" required>
                <small class="input-message"></small>
            </label>
            <div class="modal-actions">
                <a href="#" class="modal-cancel">Cancel</a>
                <button type="submit" name="create_staff_btn">Create Account</button>
            </div>
        </form>
    </section>
</div>
<?php render_super_admin_footer(); ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Actions menu dropdown toggle
    document.querySelectorAll('.actions-trigger').forEach(function(trigger) {
        trigger.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            var menu = this._actionsMenu || this.nextElementSibling;
            this._actionsMenu = menu;
            var isOpen = menu.classList.contains('show');
            
            // Close all other menus
            document.querySelectorAll('.actions-menu-content.show').forEach(function(openMenu) {
                if (openMenu !== menu) {
                    openMenu.classList.remove('show');
                }
            });
            
            // Toggle current menu
            menu.classList.toggle('show');
            this.setAttribute('aria-expanded', !isOpen);

            if (!isOpen) {
                var triggerRect = this.getBoundingClientRect();
                document.body.appendChild(menu);
                menu.classList.add('is-floating');
                menu.style.left = Math.max(8, triggerRect.right - menu.offsetWidth) + 'px';
                menu.style.top = Math.min(window.innerHeight - menu.offsetHeight - 8, triggerRect.bottom + 6) + 'px';
            }
        });
    });

    // Close menus when clicking outside
    document.addEventListener('click', function(e) {
        if (!e.target.closest('.actions-menu, .actions-menu-content')) {
            document.querySelectorAll('.actions-menu-content.show').forEach(function(menu) {
                menu.classList.remove('show');
            });
            document.querySelectorAll('.actions-trigger[aria-expanded="true"]').forEach(function(trigger) {
                trigger.setAttribute('aria-expanded', 'false');
            });
        }
    });

    // Close menus on Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            document.querySelectorAll('.actions-menu-content.show').forEach(function(menu) {
                menu.classList.remove('show');
            });
            document.querySelectorAll('.actions-trigger[aria-expanded="true"]').forEach(function(trigger) {
                trigger.setAttribute('aria-expanded', 'false');
            });
        }
    });
});

function updateCreateAccountId(role) {
    fetch('../server/generate_id.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'role=' + encodeURIComponent(role)
    })
    .then(res => res.json())
    .then(data => {
        document.getElementById('createAccountId').value = data.id;
    })
    .catch(() => {});
}

// ===== Live validation for Create Account modal =====
function setCreateMessage(input, message, isValid) {
    const msgEl = input.parentElement.querySelector('.input-message');
    if (msgEl) {
        msgEl.textContent = message;
        msgEl.style.color = isValid ? 'green' : 'red';
    }
}

// Helper to find elements in the create account modal
function getCreateInput(name) {
    const modal = document.getElementById('create-account-modal');
    return modal ? modal.querySelector('[name="' + name + '"]') : null;
}

// ===== Username validation =====
const createUsername = getCreateInput('username');
if (createUsername) {
    createUsername.addEventListener('input', () => {
        const val = createUsername.value.trim();
        if (val === '') {
            setCreateMessage(createUsername, 'Username is required.', false);
            return;
        }
        if (!/^[a-z]+\.[a-z]+$/.test(val)) {
            setCreateMessage(createUsername, 'Username must be lowercase with one dot (e.g., juan.dela).', false);
            return;
        }
        setCreateMessage(createUsername, '', true);
    });

    createUsername.addEventListener('blur', () => {
        const val = createUsername.value.trim();
        if (!/^[a-z]+\.[a-z]+$/.test(val)) return;
        
        fetch('../server/check_username.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'username=' + encodeURIComponent(val)
        })
        .then(res => res.json())
        .then(data => setCreateMessage(createUsername, data.message, data.available ?? data.valid))
        .catch(() => setCreateMessage(createUsername, 'Server error.', false));
    });
}

// ===== Email validation =====
const createEmail = getCreateInput('email');
if (createEmail) {
    createEmail.addEventListener('input', () => {
        const val = createEmail.value.trim();
        if (val === '') {
            setCreateMessage(createEmail, 'Email is required.', false);
            return;
        }
        if (!val.includes('@')) {
            setCreateMessage(createEmail, 'Please include @ in email address.', false);
            return;
        }
        if (val.length < 5 || val.length > 70) {
            setCreateMessage(createEmail, 'Email must be 5-70 characters.', false);
            return;
        }
        setCreateMessage(createEmail, '', true);
    });

    createEmail.addEventListener('blur', () => {
        const val = createEmail.value.trim();
        if (!val.includes('@') || val.length < 5 || val.length > 70) return;
        
        fetch('../server/check_email.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'email=' + encodeURIComponent(val)
        })
        .then(res => res.json())
        .then(data => setCreateMessage(createEmail, data.message, data.available ?? data.valid))
        .catch(() => setCreateMessage(createEmail, 'Server error.', false));
    });
}

// ===== Password strength =====
const createPassword = getCreateInput('password');
if (createPassword) {
    createPassword.addEventListener('input', () => {
        const val = createPassword.value;
        let strength = 0;
        let messages = [];
        
        if (val.length >= 8) strength++;
        else messages.push('At least 8 characters');
        if (/[A-Z]/.test(val)) strength++;
        else messages.push('One uppercase letter');
        if (/[a-z]/.test(val)) strength++;
        else messages.push('One lowercase letter');
        if (/[0-9]/.test(val)) strength++;
        else messages.push('One number');
        if (/[\W]/.test(val)) strength++;
        else messages.push('One special character');
        
        if (messages.length === 0) {
            setCreateMessage(createPassword, 'Strong password', true);
        } else {
            setCreateMessage(createPassword, 'Weak: ' + messages.join(', '), false);
        }
    });
}

// ===== Form submit validation =====
const createForm = document.querySelector('#create-account-modal form');
if (createForm) {
    createForm.addEventListener('submit', e => {
        const email = getCreateInput('email');
        const username = getCreateInput('username');
        const password = getCreateInput('password');
        const role = getCreateInput('role');
        
        let firstInvalid = null;
        
        if (email && email.value.trim() === '') {
            e.preventDefault();
            setCreateMessage(email, 'Email is required.', false);
            firstInvalid = email;
        }
        if (username && username.value.trim() === '') {
            e.preventDefault();
            setCreateMessage(username, 'Username is required.', false);
            if (!firstInvalid) firstInvalid = username;
        }
        if (password && password.value.trim() === '') {
            e.preventDefault();
            setCreateMessage(password, 'Password is required.', false);
            if (!firstInvalid) firstInvalid = password;
        }
        if (role && role.value === '') {
            e.preventDefault();
            setCreateMessage(role, 'Role is required.', false);
            if (!firstInvalid) firstInvalid = role;
        }
        
        if (firstInvalid) firstInvalid.focus();
    });
}
</script>
