<?php
session_start();
require '../server/config.php';
require '../server/admin_helpers.php';
require_admin_role(['user', 'admin', 'super_admin']);

$role = $_SESSION['role'];
if ($role === 'super_admin') {
    header('Location: ../super%20admin/logs.php');
    exit;
}
if ($role === 'admin') {
    header('Location: ../admin/logs.php');
    exit;
}

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
    <link rel="stylesheet" href="../css/home.css">
    <style>
        body {
            overflow: auto;
        }

        .user-logs-main {
            margin: 0 auto;
            max-width: 1060px;
            min-height: 100vh;
            padding: 130px 20px 60px;
        }

        .user-logs-main .page-heading {
            margin-bottom: 22px;
        }
    </style>
</head>
<body>
<header class="user-auth">
    <a href="index.php" class="logo">
        <img src="../assets/imgs/logo.png" alt="CSUCC Logo">
        CSUCC LDMS
    </a>
    <nav>
        <a href="my_account.php" class="hello">Hello, <?= htmlspecialchars($_SESSION['name'] ?? 'User'); ?></a>
        <a href="home.php" class="login-btn-modal">Home</a>
        <a href="logs.php" class="login-btn-modal">Logs</a>
        <a href="logout.php" class="login-btn-modal">Logout</a>
    </nav>
</header>

<main class="user-logs-main">
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
