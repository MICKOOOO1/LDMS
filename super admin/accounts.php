<?php
session_start();
require '../server/config.php';
require '../server/admin_helpers.php';
require_admin_role(['super_admin']);
require '_header.php';

$alerts = $_SESSION['alerts'] ?? [];
unset($_SESSION['alerts']);
$employeeId = trim($_GET['employee_id'] ?? '');
$accounts = fetch_accounts($conn, $employeeId);
render_super_admin_header('User Management', 'accounts');
?>
<section class="page-heading">
    <h1>User Management</h1>
    <p>Filter, update, manage privileges, and delete accounts.</p>
</section>

<?php foreach ($alerts as $alert): ?>
    <div class="alert <?= htmlspecialchars($alert['type']); ?>"><?= htmlspecialchars($alert['message']); ?></div>
<?php endforeach; ?>

<section class="panel">
    <div class="section-bar">
        <h2>Account Directory</h2>
        <a href="create_admin.php">Create Administrator</a>
        <form method="GET" class="filter-form">
            <input type="text" name="employee_id" value="<?= htmlspecialchars($employeeId); ?>" placeholder="Filter by Employee ID">
            <button type="submit"><i class="bx bx-search"></i> Filter</button>
            <a href="accounts.php">Clear</a>
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
                    <input type="hidden" name="return_page" value="accounts.php">
                    <input type="hidden" name="target_id" value="<?= (int)$account['id']; ?>">
                    <select name="account_status">
                        <option value="pending" <?= $account['account_status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="approved" <?= $account['account_status'] === 'approved' ? 'selected' : ''; ?>>Approved</option>
                        <option value="blocked" <?= $account['account_status'] === 'blocked' ? 'selected' : ''; ?>>Blocked</option>
                    </select>
                    <button type="submit" name="change_status_btn">Save Status</button>
                    <?php if ($account['account_status'] === 'blocked'): ?>
                        <button type="submit" name="unblock_account_btn">Unblock Account</button>
                    <?php elseif ((int)$account['id'] !== (int)$_SESSION['user_id']): ?>
                        <button type="submit" name="block_account_btn" class="danger">Block Account</button>
                    <?php endif; ?>
                </form>

                <form action="../server/admin_process.php" method="POST" class="grid-form compact">
                    <input type="hidden" name="return_page" value="accounts.php">
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
                        <input type="hidden" name="return_page" value="accounts.php">
                        <input type="hidden" name="target_id" value="<?= (int)$account['id']; ?>">
                        <label><input type="checkbox" name="privileges[]" value="manage_registration" <?= in_array('manage_registration', $privileges, true) ? 'checked' : ''; ?>> Registration status</label>
                        <label><input type="checkbox" name="privileges[]" value="update_accounts" <?= in_array('update_accounts', $privileges, true) ? 'checked' : ''; ?>> Update accounts</label>
                        <button type="submit" name="save_privileges_btn">Save Privileges</button>
                    </form>
                <?php endif; ?>

                <?php if ((int)$account['id'] !== (int)$_SESSION['user_id']): ?>
                    <form action="../server/admin_process.php" method="POST" onsubmit="return confirm('Delete this account permanently?');">
                        <input type="hidden" name="return_page" value="accounts.php">
                        <input type="hidden" name="target_id" value="<?= (int)$account['id']; ?>">
                        <button type="submit" name="delete_account_btn" class="danger">Delete Account</button>
                    </form>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>
    </div>
</section>
<?php render_super_admin_footer(); ?>
