<?php

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

if (isPost()) {
    verifyCsrf();

    $name = input('name');
    $email = input('email');
    $password = input('password');
    $role = input('role');

    if (
        $name === '' ||
        $email === '' ||
        $password === '' ||
        !in_array($role, ['job_seeker', 'employer'], true)
    ) {
        flash('error', 'Please complete all required fields.');
    } else {
        try {
            $stmt = db()->prepare('INSERT INTO users (name, email, password_hash, role, company_name) VALUES (?, ?, ?, ?, ?)');
            $stmt->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT), $role, '']);
            $_SESSION['user_id'] = (int) db()->lastInsertId();
            addNotification((int) $_SESSION['user_id'], 'Welcome to JobNova. Complete your profile to unlock the full portal experience.', 'profile.php');
            flash('success', 'Your account has been created.');
            redirect('dashboard.php');
        } catch (PDOException) {
            flash('error', 'That email is already registered.');
        }
    }
}

renderHeader('Register');
$selectedRole = input('role', 'job_seeker');
?>
<section class="auth-shell">
    <div class="panel auth-panel">
        <p class="eyebrow">Register</p>
        <h1>Create your portal account</h1>
        <form method="post" class="filters">
            <?= csrfField() ?>
            <label>
                Full name
                <input type="text" name="name" value="<?= e(input('name')) ?>" required>
            </label>
            <label>
                Email
                <input type="email" name="email" value="<?= e(input('email')) ?>" required>
            </label>
            <label>
                Password
                <input type="password" name="password" required>
            </label>
            <label>
                Role
                <select name="role" data-role-switch>
                    <option value="job_seeker" <?= $selectedRole === 'job_seeker' ? 'selected' : '' ?>>Job seeker</option>
                    <option value="employer" <?= $selectedRole === 'employer' ? 'selected' : '' ?>>Employer</option>
                </select>
            </label>
            <button type="submit">Create Account</button>
        </form>
        <p class="small">Already registered? <a class="text-link" href="login.php">Login here</a>.</p>
    </div>
</section>
<?php renderFooter(); ?>
