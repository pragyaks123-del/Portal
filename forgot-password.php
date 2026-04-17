<?php

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

if (isPost()) {
    verifyCsrf();

    $email = input('email');
    $stmt = db()->prepare('SELECT * FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user) {
        $expiresAt = date('Y-m-d H:i:s', strtotime('+1 hour'));
        db()->prepare('DELETE FROM password_resets WHERE user_id = ? AND used_at IS NULL')->execute([(int) $user['id']]);

        $code = '';
        try {
            $code = createUniquePasswordResetCode();
            db()->prepare('INSERT INTO password_resets (user_id, token, expires_at) VALUES (?, ?, ?)')
                ->execute([(int) $user['id'], $code, $expiresAt]);
        } catch (Throwable) {
            $code = '';
        }

        if ($code === '') {
            flash('error', 'We could not generate a reset code right now. Please try again.');
            redirect('forgot-password.php');
        }

        $subject = 'Your JobNova password reset code';
        $message = "Hello {$user['name']},\n\nWe received a request to reset your JobNova password.\n\nYour JobNova password reset code is: {$code}\n\nThis code will expire in 1 hour.\n\nUse it on the reset password page together with your email address.\n\nIf you did not request this reset, you can ignore this email.";
        $sent = sendEmailMessage($email, $subject, $message);

        if ($sent) {
            flash('success', 'A 6-digit password reset code has been sent to your email address.');
            redirect('reset-password.php?email=' . urlencode($email));
        }

        if (isLocalDevelopment()) {
            flash('success', "Mail is not configured on this local server. Use this development reset code: {$code}");
            redirect('reset-password.php?email=' . urlencode($email));
        }

        db()->prepare('DELETE FROM password_resets WHERE user_id = ? AND token = ?')->execute([(int) $user['id'], $code]);
        flash('error', 'We could not send the email. Please configure mail on this server and try again.');
    } else {
        flash('error', 'We could not find an account with that email.');
    }
}

renderHeader('Forgot Password');
?>
<section class="auth-shell">
    <div class="panel auth-panel">
        <p class="eyebrow">Password Reset</p>
        <h1>Request a reset code</h1>
        <form method="post" class="filters">
            <?= csrfField() ?>
            <label>
                Account email
                <input type="email" name="email" value="<?= e(input('email')) ?>" required>
            </label>
            <button type="submit">Send Reset Code</button>
        </form>
        <p class="small">JobNova will email you a 6-digit reset code. If SMTP is unavailable on localhost, the code is also written to `storage/mail/` for testing.</p>
    </div>
</section>
<?php renderFooter(); ?>
