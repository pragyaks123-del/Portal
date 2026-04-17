<?php

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

if (isPost()) {
    verifyCsrf();

    $email = input('email');
    $password = input('password');

    $stmt = db()->prepare('SELECT * FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password_hash'])) {
        $_SESSION['user_id'] = (int) $user['id'];
        flash('success', 'Welcome back, ' . $user['name'] . '.');
        redirect('dashboard.php');
    }

    flash('error', 'Invalid email or password.');
}

renderHeader('Login');
?>
<section class="auth-shell">
    <div class="panel auth-panel">
        <p class="eyebrow">Login</p>
        <h1>Access your account securely</h1>
        <form method="post" class="filters">
            <?= csrfField() ?>
            <label>
                Email
                <input type="email" name="email" value="<?= e(input('email')) ?>" required>
            </label>
            <label>
                Password
                <input type="password" name="password" required>
            </label>
            <button type="submit">Login</button>
        </form>
        <div class="auth-links">
            <a class="text-link" href="forgot-password.php">Forgot password?</a>
            <a class="text-link" href="register.php">Create account</a>
        </div>
    </div>
</section>
<?php renderFooter(); ?>
