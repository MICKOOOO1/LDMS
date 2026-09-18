<?php
function render_admin_header(string $title, string $active, string $role): void {
    $isSuperAdmin = $role === 'super_admin';
    $folder = $isSuperAdmin ? '../super%20admin/' : '../admin/';
    $accountsPage = $isSuperAdmin ? 'accounts.php' : 'user_management.php';
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?= htmlspecialchars($title); ?> - CSUCC LDMS</title>
        <link rel="stylesheet" href="../css/admin.css">
        <link href="https://cdn.boxicons.com/fonts/basic/boxicons.min.css" rel="stylesheet">
    </head>
    <body>
    <header>
        <a href="<?= $folder; ?>dashboard.php" class="logo"><img src="../assets/imgs/logo.png" alt="CSUCC Logo"> CSUCC LDMS</a>
        <nav>
            <a href="<?= $folder; ?>dashboard.php" class="<?= $active === 'dashboard' ? 'active' : ''; ?>">Dashboard</a>
            <a href="<?= $folder . $accountsPage; ?>" class="<?= $active === 'accounts' ? 'active' : ''; ?>">User Management</a>
            <a href="<?= $folder; ?>pending.php" class="<?= $active === 'pending' ? 'active' : ''; ?>">Pending Approval</a>
            <a href="<?= $folder; ?>logs.php" class="<?= $active === 'logs' ? 'active' : ''; ?>">System Logs</a>
            <a href="<?= $folder; ?>my_account.php" class="<?= $active === 'account' ? 'active' : ''; ?>">My Account</a>
        </nav>
    </header>
    <div class="topbar">
        <span><?= htmlspecialchars($_SESSION['name'] ?? ($isSuperAdmin ? 'Super Admin' : 'Administrator')); ?></span>
        <a href="../user/logout.php">Logout</a>
    </div>
    <main>
    <?php
}

function render_admin_footer(): void {
    echo '</main></body></html>';
}
