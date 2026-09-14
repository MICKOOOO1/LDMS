<?php
session_start();
require '../server/config.php';
require '../server/admin_helpers.php';
require_admin_role(['user', 'admin', 'super_admin']);

$role = $_SESSION['role'];
$dashboardHref = $role === 'super_admin' ? '../super%20admin/dashboard.php' : ($role === 'admin' ? '../admin/dashboard.php' : 'home.php');
$navigation = $role === 'super_admin'
    ? [
        ['label' => 'Dashboard', 'href' => '../super%20admin/dashboard.php'],
        ['label' => 'Pending Approvals', 'href' => '../super%20admin/pending.php'],
        ['label' => 'User Management', 'href' => '../super%20admin/accounts.php'],
        ['label' => 'Deletion Request', 'href' => '../super%20admin/delete_requests.php'],
    ]
    : ($role === 'admin'
        ? [
            ['label' => 'Dashboard', 'href' => '../admin/dashboard.php'],
            ['label' => 'Pending Approvals', 'href' => '../admin/dashboard.php#pending-registrations'],
            ['label' => 'User Management', 'href' => '../admin/dashboard.php#accounts'],
        ]
        : [['label' => 'Dashboard', 'href' => 'home.php']]);
$page = max(1, (int)($_GET['page'] ?? 1));
$date = trim($_GET['date'] ?? '');
$logData = fetch_login_logs($conn, $role, '', $date, $page);

function logs_page_url(int $page, string $date): string {
    return '?' . http_build_query(['page' => $page, 'date' => $date]);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Logs - CSUCC LDMS</title>
    <link rel="stylesheet" href="../css/admin.css">
</head>
<body>
<header>
    <a href="<?= $dashboardHref; ?>" class="logo"><img src="../assets/imgs/logo.png" alt="CSUCC Logo"> CSUCC LDMS</a>
    <nav>
        <?php foreach ($navigation as $item): ?>
            <a href="<?= $item['href']; ?>"><?= htmlspecialchars($item['label']); ?></a>
        <?php endforeach; ?>
        <a href="logs.php" class="active">System Logs</a>
        <?php if ($role === 'super_admin'): ?>
            <a href="../super%20admin/my_account.php">My Account</a>
        <?php endif; ?>
        <span><?= htmlspecialchars($_SESSION['name'] ?? 'User'); ?></span>
        <a href="logout.php">Logout</a>
    </nav>
</header>

<main>
    <section class="page-heading">
        <h1>System Logs</h1>
        <p>View sign-in and sign-out activity available to your account.</p>
    </section>

    <section class="panel">
        <div class="section-bar">
            <h2>Access History</h2>
            <form method="GET" class="filter-form">
                <label>Date
                    <input type="date" name="date" value="<?= htmlspecialchars($date); ?>">
                </label>
                <button type="submit">Filter</button>
                <a href="logs.php">Clear</a>
            </form>
        </div>

        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>ID No.</th>
                        <th>Full Name</th>
                        <th>Role</th>
                        <th>Time In</th>
                        <th>Time Out</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($logData['logs'] as $log): ?>
                        <tr>
                            <td><?= htmlspecialchars($log['employee_id']); ?></td>
                            <td><?= htmlspecialchars($log['full_name']); ?></td>
                            <td><?= htmlspecialchars($log['role']); ?></td>
                            <td><?= htmlspecialchars($log['time_in']); ?></td>
                            <td><?= htmlspecialchars($log['time_out'] ?? 'Active session'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($logData['logs'])): ?>
                        <tr><td colspan="5">No login logs found for the selected filters.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($logData['pages'] > 1): ?>
            <div class="pagination" aria-label="Login log pages">
                <?php if ($page > 1): ?><a href="<?= htmlspecialchars(logs_page_url($page - 1, $date)); ?>">Previous</a><?php endif; ?>
                <span>Page <?= $page; ?> of <?= $logData['pages']; ?></span>
                <?php if ($page < $logData['pages']): ?><a href="<?= htmlspecialchars(logs_page_url($page + 1, $date)); ?>">Next</a><?php endif; ?>
            </div>
        <?php endif; ?>
    </section>
</main>
</body>
</html>
