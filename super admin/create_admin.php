<?php
session_start();
require '../server/config.php';
require '../server/admin_helpers.php';
require_admin_role(['super_admin']);
require '_header.php';

$alerts = $_SESSION['alerts'] ?? [];
unset($_SESSION['alerts']);

$superAdminCountResult = $conn->query("SELECT COUNT(*) AS total FROM users WHERE role = 'super_admin'");
$superAdminCount = (int)($superAdminCountResult->fetch_assoc()['total'] ?? 0);
$roleForId = $_POST['role'] ?? 'admin';
if ($superAdminCount >= 2 && $roleForId === 'super_admin') {
    $roleForId = 'admin';
}
$generatedId = generate_next_id($conn, $roleForId);

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
            <select name="role" id="roleSelect" required onchange="updateGeneratedId(this.value)">
                <option value="admin"<?= $roleForId === 'admin' ? ' selected' : ''; ?>>Administrator</option>
                <option value="super_admin"<?= $roleForId === 'super_admin' ? ' selected' : ''; ?><?= $superAdminCount >= 2 ? ' disabled' : ''; ?>>Super Administrator<?= $superAdminCount >= 2 ? ' (limit reached)' : ''; ?></option>
            </select>
        </label>
        <label>Employee ID
            <input type="text" name="id_number" id="generatedId" value="<?= htmlspecialchars($generatedId); ?>" readonly style="background-color: #e8e8e8; color: #666;">
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
            <input type="text" name="username" required pattern="[a-z]+\.[a-z]+" title="Use lowercase letters in the format name.name, with no spaces or numbers." autocomplete="off">
        </label>
        <label>Password
            <input type="password" name="password" required>
        </label>
        <button type="submit" name="create_staff_btn">Create Account</button>
    </form>
</section>
<?php render_super_admin_footer(); ?>

<script>
function updateGeneratedId(role) {
    fetch('../server/generate_id.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'role=' + encodeURIComponent(role)
    })
    .then(res => res.json())
    .then(data => {
        document.getElementById('generatedId').value = data.id;
    })
    .catch(() => {});
}
</script>
