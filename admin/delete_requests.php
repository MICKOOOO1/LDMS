<?php
session_start();
require '../server/config.php';
require '../server/admin_helpers.php';
require_admin_role(['admin']);

$alerts = $_SESSION['alerts'] ?? [];
unset($_SESSION['alerts']);

$deleteRequests = fetch_delete_requests($conn);

render_admin_header('Deletion Requests', 'delete_requests');
?>
    <?php foreach ($alerts as $alert): ?>
        <div class="alert <?= htmlspecialchars($alert['type']); ?>"><?= htmlspecialchars($alert['message']); ?></div>
    <?php endforeach; ?>

    <section class="page-heading">
        <h1>Deletion Requests</h1>
        <p>Review account deletion requests submitted by administrators.</p>
    </section>

    <section class="panel">
        <div class="request-list">
            <?php foreach ($deleteRequests as $request): ?>
                <article class="request-card">
                    <h3><?= htmlspecialchars($request['target_employee_id']); ?></h3>
                    <p><strong>Requested by:</strong> <?= htmlspecialchars($request['requester_username']); ?></p>
                    <p><strong>Reason:</strong> <?= htmlspecialchars($request['reason']); ?></p>
                    <pre><?= htmlspecialchars($request['user_snapshot']); ?></pre>
                    <p><strong>Status:</strong> <?= htmlspecialchars($request['request_status']); ?></p>
                    <?php if ($request['request_status'] === 'pending'): ?>
                        <form action="../server/admin_process.php" method="POST" class="inline-form">
                            <input type="hidden" name="request_id" value="<?= (int)$request['id']; ?>">
                            <button type="submit" name="review_delete_request_btn" value="approved">Approve</button>
                            <button type="submit" name="review_delete_request_btn" value="rejected" class="danger">Reject</button>
                        </form>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
            <?php if (empty($deleteRequests)): ?><p>No delete requests yet.</p><?php endif; ?>
        </div>
    </section>
<?php render_admin_footer(); ?>