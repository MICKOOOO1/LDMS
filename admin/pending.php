<?php
session_start();
require '../server/config.php';
require '../server/admin_helpers.php';
require_admin_role(['admin']);

$alerts = $_SESSION['alerts'] ?? [];
unset($_SESSION['alerts']);

$approvalPage = max(1, (int)($_GET['approval_page'] ?? 1));
$approvalMonth = trim($_GET['approval_month'] ?? '');
$approvalDate = trim($_GET['approval_date'] ?? '');
$pendingTotal = fetch_pending_registration_count($conn, $approvalMonth, $approvalDate);
$pendingRegistrations = fetch_pending_registrations_page($conn, $approvalPage, $approvalMonth, $approvalDate);
$approvalPages = max(1, (int)ceil($pendingTotal / 5));

render_admin_header('Pending Approvals', 'pending');
?>
    <?php foreach ($alerts as $alert): ?>
        <div class="alert <?= htmlspecialchars($alert['type']); ?>"><?= htmlspecialchars($alert['message']); ?></div>
    <?php endforeach; ?>

    <section class="page-heading">
        <h1>Pending Approvals</h1>
        <p>Review and approve or block user registrations.</p>
    </section>

    <section class="panel">
        <div class="section-bar">
            <h2>Approval Queue</h2>
            <form method="GET" class="filter-form">
                <input type="month" name="approval_month" value="<?= htmlspecialchars($approvalMonth); ?>">
                <input type="date" name="approval_date" value="<?= htmlspecialchars($approvalDate); ?>">
                <button type="submit">Filter</button>
                <a href="pending.php">Clear</a>
            </form>
            <span class="count-badge"><?= $pendingTotal; ?> waiting</span>
        </div>

        <div class="account-list">
            <?php foreach ($pendingRegistrations as $account): ?>
                <article class="account-card pending-card">
                    <div class="account-top">
                        <div>
                            <h3><?= htmlspecialchars(full_name($account)); ?></h3>
                            <p><?= htmlspecialchars($account['user_id']); ?> &middot; <?= htmlspecialchars($account['username']); ?> &middot; <?= htmlspecialchars($account['email']); ?></p>
                            <p><?= htmlspecialchars($account['purok_street']); ?>, <?= htmlspecialchars($account['barangay']); ?>, <?= htmlspecialchars($account['city_municipality']); ?></p>
                        </div>
<div class="badges">
                        <span class="badge pending">Pending</span>
                        <span><?= htmlspecialchars($account['created_at']); ?></span>
                    </div>
                    </div>
                    <form action="../server/admin_process.php" method="POST" class="inline-form">
                        <input type="hidden" name="target_id" value="<?= (int)$account['id']; ?>">
                        <button type="submit" name="change_status_btn" onclick="this.form.account_status.value='approved'">Approve</button>
                        <button type="submit" name="change_status_btn" class="danger" onclick="this.form.account_status.value='blocked'">Reject</button>
                        <input type="hidden" name="account_status" value="">
                    </form>
                </article>
            <?php endforeach; ?>
            <?php if (empty($pendingRegistrations)): ?><p>No pending registrations.</p><?php endif; ?>
        </div>

        <?php if ($approvalPages > 1): ?>
            <div class="pagination" aria-label="Pending registration pages">
                <?php if ($approvalPage > 1): ?><a href="?<?= htmlspecialchars(http_build_query(['approval_page' => $approvalPage - 1, 'approval_month' => $approvalMonth, 'approval_date' => $approvalDate])); ?>">Previous</a><?php endif; ?>
                <span>Page <?= $approvalPage; ?> of <?= $approvalPages; ?></span>
                <?php if ($approvalPage < $approvalPages): ?><a href="?<?= htmlspecialchars(http_build_query(['approval_page' => $approvalPage + 1, 'approval_month' => $approvalMonth, 'approval_date' => $approvalDate])); ?>">Next</a><?php endif; ?>
            </div>
        <?php endif; ?>
    </section>
<?php render_admin_footer(); ?>