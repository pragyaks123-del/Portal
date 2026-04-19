<?php

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

$user = requireLogin();
markNotificationsRead((int) $user['id']);
$notifications = notificationsForUser((int) $user['id']);

renderHeader('Notifications');
?>
<section class="panel">
    <p class="eyebrow">Notifications</p>
    <h1>Recent alerts and updates</h1>
    <div class="notification-list">
        <?php if (!$notifications): ?>
            <div class="empty">You do not have any notifications yet.</div>
        <?php endif; ?>
        <?php foreach ($notifications as $notification): ?>
            <article class="card soft-card">
                <strong><?= e($notification['message']) ?></strong>
                <p class="small"><?= formatDateTime($notification['created_at']) ?></p>
                <?php if ($notification['link'] !== ''): ?>
                    <a class="text-link" href="<?= e($notification['link']) ?>">Open</a>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>
    </div>
</section>
<?php renderFooter(); ?>
