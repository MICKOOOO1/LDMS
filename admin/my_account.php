<?php
session_start();
require '../server/config.php';
require '../server/admin_helpers.php';
require_admin_role(['admin']);

$alerts = $_SESSION['alerts'] ?? [];
unset($_SESSION['alerts']);

$stmt = $conn->prepare("SELECT user_id, first_name, middle_name, last_name, extension_name, dob, age, sex, email, username, role, account_status, profile_picture, purok_street, barangay, city_municipality, province, country, zip_code, date_registered FROM users WHERE id = ?");
$userId = (int)$_SESSION['user_id'];
$stmt->bind_param("i", $userId);
$stmt->execute();
$account = $stmt->get_result()->fetch_assoc();
$stmt->close();

render_admin_header('My Account', 'account');
?>
    <section class="page-heading">
        <h1>My Account</h1>
        <p>View your Administrator account information.</p>
    </section>

    <section class="panel">
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

        <?php if ($account): ?>
            <div class="account-card">
                <div class="account-top">
                    <div class="admin-profile-summary">
                        <div class="admin-profile-photo">
                            <?php if (!empty($account['profile_picture'])): ?>
                                <img src="../<?= htmlspecialchars($account['profile_picture']); ?>" alt="Profile picture">
                            <?php else: ?>
                                <span><?= htmlspecialchars(strtoupper(substr($account['first_name'], 0, 1) . substr($account['last_name'], 0, 1))); ?></span>
                            <?php endif; ?>
                        </div>
                        <div>
                        <h3><?= htmlspecialchars(full_name($account)); ?></h3>
                        <p><?= htmlspecialchars($account['user_id']); ?> &middot; <?= htmlspecialchars($account['username']); ?></p>
                        </div>
                    </div>
                    <div class="badges">
                        <span><?= htmlspecialchars($account['role']); ?></span>
                        <span><?= htmlspecialchars($account['account_status']); ?></span>
                    </div>
                </div>
                <form action="../server/admin_process.php" method="POST" enctype="multipart/form-data" class="grid-form compact admin-profile-form">
                    <input type="hidden" name="return_page" value="my_account.php">
                    <label class="full-width">Choose Profile Picture<input type="file" name="profile_picture" accept="image/jpeg,image/png,image/webp"></label>
                    <label>First Name<input type="text" name="first_name" value="<?= htmlspecialchars($account['first_name']); ?>" required></label>
                    <label>Middle Name<input type="text" name="middle_name" value="<?= htmlspecialchars($account['middle_name'] ?? ''); ?>"></label>
                    <label>Last Name<input type="text" name="last_name" value="<?= htmlspecialchars($account['last_name']); ?>" required></label>
                    <label>Extension<input type="text" name="extension_name" value="<?= htmlspecialchars($account['extension_name'] ?? ''); ?>"></label>
                    <label>Employee ID<input type="text" value="<?= htmlspecialchars($account['user_id']); ?>" readonly></label>
                    <label>Email<input type="email" name="email" value="<?= htmlspecialchars($account['email']); ?>" required></label>
                    <label>Username<input type="text" name="username" value="<?= htmlspecialchars($account['username']); ?>" required></label>
                    <label>Date of Birth<input type="date" value="<?= htmlspecialchars($account['dob']); ?>" readonly></label>
                    <label>Age<input type="number" value="<?= (int)$account['age']; ?>" readonly></label>
                    <label>Sex<input type="text" value="<?= htmlspecialchars(ucfirst($account['sex'])); ?>" readonly></label>
                    <label>Purok/Street<input type="text" name="purok_street" value="<?= htmlspecialchars($account['purok_street']); ?>"></label>
                    <label>Barangay<input type="text" name="barangay" value="<?= htmlspecialchars($account['barangay']); ?>"></label>
                    <label>City/Municipality<input type="text" name="city_municipality" value="<?= htmlspecialchars($account['city_municipality']); ?>"></label>
                    <label>Province<input type="text" name="province" value="<?= htmlspecialchars($account['province']); ?>"></label>
                    <label>Country<input type="text" name="country" value="<?= htmlspecialchars($account['country']); ?>"></label>
                    <label>Zip Code<input type="text" name="zip_code" value="<?= htmlspecialchars($account['zip_code']); ?>"></label>
                    <label>Date Registered<input type="text" value="<?= htmlspecialchars($account['date_registered']); ?>" readonly></label>
                    <div class="profile-actions">
                        <button type="submit" name="update_own_profile_btn">Save Changes</button>
                    </div>
                </form>
            </div>
        <?php else: ?>
            <p>Account information is unavailable.</p>
        <?php endif; ?>
    </section>
<?php render_admin_footer(); ?>
