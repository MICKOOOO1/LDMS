<?php
function render_super_admin_header(string $title, string $active): void {
    $links = [
        'accounts' => 'accounts.php',
        'pending' => 'pending.php',
        'requests' => 'delete_requests.php',
    ];
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
        <a href="dashboard.php" class="logo"><img src="../assets/imgs/logo.png" alt="CSUCC Logo"> CSUCC LDMS</a>
        <nav>
            <a href="dashboard.php" class="<?= $active === 'dashboard' ? 'active' : ''; ?>">Dashboard</a>
            <?php foreach ($links as $key => $href): ?>
                <a href="<?= $href; ?>" class="<?= $active === $key ? 'active' : ''; ?>"><?= $key === 'pending' ? 'Pending Approvals' : ($key === 'accounts' ? 'User Management' : 'Deletion Request'); ?></a>
            <?php endforeach; ?>
            <a href="logs.php" class="<?= $active === 'logs' ? 'active' : ''; ?>">System Logs</a>
            <a href="my_account.php" class="<?= $active === 'account' ? 'active' : ''; ?>">My Account</a>
            <div class="sidebar-user">
                <span class="sidebar-user-name"><?= htmlspecialchars($_SESSION['name'] ?? 'Super Admin'); ?></span>
                <span class="sidebar-user-role">Super Admin</span>
            </div>
            <a href="../user/logout.php">Logout</a>
        </nav>
    </header>
    <main>
    <?php
}

function render_super_admin_footer(): void {
    ?>
    </main>
    </body>
    </html>
    <?php
}
