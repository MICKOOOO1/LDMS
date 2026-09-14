<?php
session_start();
require '../server/config.php';
require '../server/admin_helpers.php';
require_admin_role(['super_admin']);
require '_header.php';

$alerts = $_SESSION['alerts'] ?? [];
unset($_SESSION['alerts']);
render_super_admin_header('Create Administrator', 'create');
?>
<section class="page-heading">
    <h1>Create Administrator</h1>
    <p>Create another administrator or super administrator account.</p>
</section>

<?php foreach ($alerts as $alert): ?>
    <div class="alert <?= htmlspecialchars($alert['type']); ?>"><?= htmlspecialchars($alert['message']); ?></div>
<?php endforeach; ?>

<section class="panel">
    <form action="../server/admin_process.php" method="POST" class="grid-form">
        <input type="hidden" name="return_page" value="create_admin.php">
        <label>Role
            <select name="role" required>
                <option value="admin">Administrator</option>
                <option value="super_admin">Super Administrator</option>
            </select>
        </label>
        <label>Employee ID
            <input type="text" name="id_number" placeholder="xxxx-xxxx" required>
        </label>
        <label>First Name
            <input type="text" name="first_name" required>
        </label>
        <label>Last Name
            <input type="text" name="last_name" required>
        </label>
        <label>Date of Birth
            <input type="date" name="dob" value="1990-01-01">
        </label>
        <label>Age
            <input type="number" name="age" value="18" min="18">
        </label>
        <label>Sex
            <select name="sex" required>
                <option value="female">Female</option>
                <option value="male">Male</option>
            </select>
        </label>
        <label>Email
            <input type="email" name="email" required>
        </label>
        <label>Username
            <input type="text" name="username" required>
        </label>
        <label>Password
            <input type="password" name="password" required>
        </label>
        <button type="submit" name="create_staff_btn">Create Account</button>
    </form>
</section>
<?php render_super_admin_footer(); ?>
