<?php
session_start();
require '../server/config.php';
require '../server/admin_helpers.php';
require_admin_role(['super_admin']);
require '_header.php';

$stmt = $conn->prepare("SELECT user_id, first_name, middle_name, last_name, extension_name, dob, age, sex, email, username, role, account_status, date_registered FROM users WHERE id = ?");
$userId = (int)$_SESSION['user_id'];
$stmt->bind_param("i", $userId);
$stmt->execute();
$account = $stmt->get_result()->fetch_assoc();
$stmt->close();

render_super_admin_header('My Account', 'account');
?>
<section class="page-heading">
    <h1>My Account</h1>
    <p>View your Super Admin account information.</p>
</section>

<section class="panel">
    <?php if ($account): ?>
        <div class="account-card">
            <div class="account-top">
                <div>
                    <h3><?= htmlspecialchars(full_name($account)); ?></h3>
                    <p><?= htmlspecialchars($account['user_id']); ?> · <?= htmlspecialchars($account['username']); ?></p>
                </div>
                <div class="badges">
                    <span><?= htmlspecialchars($account['role']); ?></span>
                    <span><?= htmlspecialchars($account['account_status']); ?></span>
                </div>
            </div>
            <div class="grid-form compact">
                <label>First Name<input type="text" value="<?= htmlspecialchars($account['first_name']); ?>" readonly></label>
                <label>Middle Name<input type="text" value="<?= htmlspecialchars($account['middle_name'] ?? ''); ?>" readonly></label>
                <label>Last Name<input type="text" value="<?= htmlspecialchars($account['last_name']); ?>" readonly></label>
                <label>Extension<input type="text" value="<?= htmlspecialchars($account['extension_name'] ?? ''); ?>" readonly></label>
                <label>Employee ID<input type="text" value="<?= htmlspecialchars($account['user_id']); ?>" readonly></label>
                <label>Email<input type="email" value="<?= htmlspecialchars($account['email']); ?>" readonly></label>
                <label>Date of Birth<input type="date" value="<?= htmlspecialchars($account['dob']); ?>" readonly></label>
                <label>Age<input type="number" value="<?= (int)$account['age']; ?>" readonly></label>
                <label>Sex<input type="text" value="<?= htmlspecialchars($account['sex']); ?>" readonly></label>
                <label>Date Registered<input type="text" value="<?= htmlspecialchars($account['date_registered']); ?>" readonly></label>
            </div>
        </div>
    <?php else: ?>
        <p>Account information is unavailable.</p>
    <?php endif; ?>
</section>
<?php render_super_admin_footer(); ?>
