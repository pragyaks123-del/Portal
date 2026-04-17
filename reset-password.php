<?php

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

$email = input('email');

if (isPost()) {
    verifyCsrf();
    $email = input('email');
    $code = input('code');
    $password = input('password');
    $record = $email !== '' && $code !== '' ? passwordResetRecordByEmailAndCode($email, $code) : null;

    if (!$record || $record['used_at'] !== null || strtotime($record['expires_at']) < time()) {
        flash('error', 'That reset code is invalid or expired.');
    } elseif ($password === '') {
        flash('error', 'Please enter a new password.');
    } else {
        db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute([password_hash($password, PASSWORD_DEFAULT), (int) $record['user_id']]);
        db()->prepare('UPDATE password_resets SET used_at = CURRENT_TIMESTAMP WHERE id = ?')->execute([(int) $record['id']]);
        flash('success', 'Your password has been updated. Please log in.');
        redirect('login.php');
    }
}

renderHeader('Reset Password');
?>
<section class="auth-shell">
    <div class="panel auth-panel">
        <p class="eyebrow">Reset Password</p>
        <h1>Choose a new password</h1>
        <form method="post" class="filters">
            <?= csrfField() ?>
            <label>
                Email address
                <input type="email" name="email" value="<?= e($email) ?>" required>
            </label>
            <label>
                Reset code
                <input type="text" name="code" placeholder="Enter the 6-digit code from your email" inputmode="numeric" maxlength="6" required>
            </label>
            <label>
                New password
                <input type="password" name="password" required>
            </label>
            <button type="submit">Update Password</button>
        </form>
        <p class="small">Enter the same email address that requested the reset, then use the 6-digit code from your email to set a new password.</p>
    </div>
</section>
<?php renderFooter(); ?>
