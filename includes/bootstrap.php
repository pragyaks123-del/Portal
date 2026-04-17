<?php

declare(strict_types=1);

$sessionDir = __DIR__ . '/../storage/sessions';
if (!is_dir($sessionDir)) {
    mkdir($sessionDir, 0777, true);
}
session_save_path($sessionDir);
session_start();

const APP_NAME = 'JobNova';
const RESUME_DIR = __DIR__ . '/../storage/resumes';
const MAIL_LOG_DIR = __DIR__ . '/../storage/mail';

if (!is_dir(__DIR__ . '/../storage')) {
    mkdir(__DIR__ . '/../storage', 0777, true);
}

if (!is_dir(MAIL_LOG_DIR)) {
    mkdir(MAIL_LOG_DIR, 0777, true);
}

require_once __DIR__ . '/helpers.php';

$dbConfigFile = __DIR__ . '/db-config.php';
$GLOBALS['db_config'] = file_exists($dbConfigFile) ? require $dbConfigFile : [];

$mailConfigFile = __DIR__ . '/mail-config.php';
$GLOBALS['mail_config'] = file_exists($mailConfigFile) ? require $mailConfigFile : [];

function db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $config = databaseConfig();
    initializeDatabaseServer($config);

    $pdo = new PDO(
        sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $config['host'],
            $config['port'],
            $config['database'],
            $config['charset']
        ),
        $config['username'],
        $config['password'],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec('SET NAMES ' . $config['charset']);

    initializeDatabase($pdo);

    return $pdo;
}

function databaseConfig(): array
{
    $config = $GLOBALS['db_config'] ?? [];

    return [
        'host' => (string) ($config['host'] ?? '127.0.0.1'),
        'port' => (int) ($config['port'] ?? 3306),
        'database' => (string) ($config['database'] ?? 'job_portal'),
        'username' => (string) ($config['username'] ?? 'root'),
        'password' => (string) ($config['password'] ?? ''),
        'charset' => (string) ($config['charset'] ?? 'utf8mb4'),
    ];
}

function initializeDatabaseServer(array $config): void
{
    static $serverInitialized = false;

    if ($serverInitialized) {
        return;
    }

    $pdo = new PDO(
        sprintf(
            'mysql:host=%s;port=%d;charset=%s',
            $config['host'],
            $config['port'],
            $config['charset']
        ),
        $config['username'],
        $config['password'],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );

    $databaseName = str_replace('`', '``', $config['database']);
    $charset = preg_replace('/[^a-zA-Z0-9_]+/', '', $config['charset']) ?: 'utf8mb4';

    $pdo->exec(sprintf(
        'CREATE DATABASE IF NOT EXISTS `%s` CHARACTER SET %s COLLATE %s_unicode_ci',
        $databaseName,
        $charset,
        $charset
    ));

    $serverInitialized = true;
}

function initializeDatabase(PDO $pdo): void
{
    static $initialized = false;

    if ($initialized) {
        return;
    }

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS users (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            email VARCHAR(255) NOT NULL UNIQUE,
            password_hash VARCHAR(255) NOT NULL,
            role ENUM('job_seeker', 'employer') NOT NULL,
            bio TEXT DEFAULT NULL,
            company_name VARCHAR(255) DEFAULT '',
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

        CREATE TABLE IF NOT EXISTS password_resets (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id INT UNSIGNED NOT NULL,
            token VARCHAR(64) NOT NULL UNIQUE,
            expires_at DATETIME NOT NULL,
            used_at DATETIME DEFAULT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

        CREATE TABLE IF NOT EXISTS employer_profiles (
            user_id INT UNSIGNED PRIMARY KEY,
            company_name VARCHAR(255) NOT NULL DEFAULT '',
            website VARCHAR(255) NOT NULL DEFAULT '',
            industry VARCHAR(255) NOT NULL DEFAULT '',
            company_size VARCHAR(100) NOT NULL DEFAULT '',
            location VARCHAR(255) NOT NULL DEFAULT '',
            founded_year VARCHAR(20) NOT NULL DEFAULT '',
            contact_phone VARCHAR(50) NOT NULL DEFAULT '',
            linkedin_url VARCHAR(255) NOT NULL DEFAULT '',
            tagline VARCHAR(255) NOT NULL DEFAULT '',
            overview TEXT DEFAULT NULL,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

        CREATE TABLE IF NOT EXISTS jobs (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            employer_id INT UNSIGNED NOT NULL,
            title VARCHAR(255) NOT NULL,
            category VARCHAR(255) NOT NULL,
            location VARCHAR(255) NOT NULL,
            salary_min INT NOT NULL,
            salary_max INT NOT NULL,
            job_type VARCHAR(100) NOT NULL,
            skills TEXT NOT NULL,
            description TEXT NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (employer_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

        CREATE TABLE IF NOT EXISTS saved_jobs (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id INT UNSIGNED NOT NULL,
            job_id INT UNSIGNED NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE(user_id, job_id),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (job_id) REFERENCES jobs(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

        CREATE TABLE IF NOT EXISTS resumes (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id INT UNSIGNED NOT NULL UNIQUE,
            original_name VARCHAR(255) NOT NULL,
            stored_name VARCHAR(255) NOT NULL,
            summary TEXT DEFAULT NULL,
            skills TEXT DEFAULT NULL,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

        CREATE TABLE IF NOT EXISTS applications (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            job_id INT UNSIGNED NOT NULL,
            applicant_id INT UNSIGNED NOT NULL,
            cover_letter TEXT DEFAULT NULL,
            status ENUM('pending', 'accepted', 'rejected') NOT NULL DEFAULT 'pending',
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE(job_id, applicant_id),
            FOREIGN KEY (job_id) REFERENCES jobs(id) ON DELETE CASCADE,
            FOREIGN KEY (applicant_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

        CREATE TABLE IF NOT EXISTS notifications (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id INT UNSIGNED NOT NULL,
            message TEXT NOT NULL,
            link VARCHAR(255) DEFAULT '',
            is_read TINYINT(1) NOT NULL DEFAULT 0,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    if (!is_dir(RESUME_DIR)) {
        mkdir(RESUME_DIR, 0777, true);
    }

    seedDemoData($pdo);
    $initialized = true;
}

function seedDemoData(PDO $pdo): void
{
    $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ?');
    $stmt->execute(['employer@example.com']);
    $employer = $stmt->fetch();

    if (!$employer) {
        $createUser = $pdo->prepare('INSERT INTO users (name, email, password_hash, role, bio, company_name) VALUES (?, ?, ?, ?, ?, ?)');
        $createUser->execute([
            'Ava Employer',
            'employer@example.com',
            password_hash('password123', PASSWORD_DEFAULT),
            'employer',
            'Building teams for product, engineering, and creative roles across Nepal and remote regions.',
            'BrightFuture Labs',
        ]);
        $employerId = (int) $pdo->lastInsertId();
    } else {
        $employerId = (int) $employer['id'];
    }

    $stmt->execute(['seeker@example.com']);
    $seeker = $stmt->fetch();

    if (!$seeker) {
        $createUser = $pdo->prepare('INSERT INTO users (name, email, password_hash, role, bio, company_name) VALUES (?, ?, ?, ?, ?, ?)');
        $createUser->execute([
            'Noah Seeker',
            'seeker@example.com',
            password_hash('password123', PASSWORD_DEFAULT),
            'job_seeker',
            'Frontend developer focused on accessible interfaces, PHP products, and polished user journeys.',
            '',
        ]);
        $seekerId = (int) $pdo->lastInsertId();
    } else {
        $seekerId = (int) $seeker['id'];
    }

    $jobCountStmt = $pdo->prepare('SELECT COUNT(*) FROM jobs WHERE employer_id = ?');
    $jobCountStmt->execute([$employerId]);

    if ((int) $jobCountStmt->fetchColumn() < 5) {
        $pdo->prepare('DELETE FROM jobs WHERE employer_id = ?')->execute([$employerId]);

        $jobStmt = $pdo->prepare(
            'INSERT INTO jobs (employer_id, title, category, location, salary_min, salary_max, job_type, skills, description)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );

        $jobs = [
            ['Corporate Solutions Executive', 'Business', 'Kathmandu', 55000, 78000, 'Full-time', 'Sales, CRM, Communication', 'Own client relationships, manage growth campaigns, and partner with product teams to deliver tailored hiring solutions.'],
            ['Forward Security Director', 'Security', 'Lalitpur', 90000, 130000, 'Full-time', 'Leadership, Risk, Strategy', 'Lead operational security planning, build safer infrastructure, and mentor a growing security operations team.'],
            ['Regional Creative Facilitator', 'Design', 'Remote', 60000, 88000, 'Contract', 'Figma, Branding, Collaboration', 'Shape employer brand campaigns and create visual systems that improve how teams hire and communicate.'],
            ['Internal Integration Planner', 'Operations', 'Bhaktapur', 50000, 76000, 'Full-time', 'Planning, Process, Stakeholders', 'Coordinate cross-functional delivery, streamline recruiting workflows, and improve reporting across the portal.'],
            ['District Internet Director', 'Engineering', 'Pokhara', 85000, 120000, 'Full-time', 'PHP, JavaScript, APIs, SQL', 'Build scalable product features for the job portal, maintain APIs, and ship high-quality hiring workflows.'],
        ];

        foreach ($jobs as $job) {
            $jobStmt->execute([$employerId, ...$job]);
        }
    }

    if ((int) $pdo->query('SELECT COUNT(*) FROM notifications')->fetchColumn() === 0) {
        addNotification($seekerId, 'Welcome to JobNova. Complete your profile and upload your resume to start applying.', 'profile.php');
        addNotification($employerId, 'Your employer workspace is ready. Post a new role or review applicants from your dashboard.', 'dashboard.php');
    }
}

function currentUser(): ?array
{
    if (!isset($_SESSION['user_id'])) {
        return null;
    }

    $stmt = db()->prepare('SELECT * FROM users WHERE id = ?');
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();

    return $user ?: null;
}

function isLoggedIn(): bool
{
    return currentUser() !== null;
}

function requireLogin(): array
{
    $user = currentUser();

    if ($user === null) {
        redirect('login.php');
    }

    return $user;
}

function requireRole(string $role): array
{
    $user = requireLogin();

    if ($user['role'] !== $role) {
        flash('error', 'You do not have access to that page.');
        redirect('dashboard.php');
    }

    return $user;
}

function redirect(string $path): never
{
    header('Location: ' . $path);
    exit;
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = compact('type', 'message');
}

function getFlashes(): array
{
    $messages = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);

    return $messages;
}

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function isPost(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

function input(string $key, string $default = ''): string
{
    return trim($_POST[$key] ?? $_GET[$key] ?? $default);
}

function addNotification(int $userId, string $message, string $link = ''): void
{
    $stmt = db()->prepare('INSERT INTO notifications (user_id, message, link) VALUES (?, ?, ?)');
    $stmt->execute([$userId, $message, $link]);
}

function unreadNotificationCount(int $userId): int
{
    $stmt = db()->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0');
    $stmt->execute([$userId]);

    return (int) $stmt->fetchColumn();
}

function renderHeader(string $title): void
{
    $user = currentUser();
    $notificationCount = $user ? unreadNotificationCount((int) $user['id']) : 0;
    $stylesPath = __DIR__ . '/../assets/styles.css';
    $stylesVersion = is_file($stylesPath) ? (string) filemtime($stylesPath) : (string) time();
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?= e($title) ?> | <?= APP_NAME ?></title>
        <link rel="stylesheet" href="assets/styles.css?v=<?= e($stylesVersion) ?>">
    </head>
    <body>
    <div class="page-backdrop"></div>
    <header class="site-header">
        <div class="wrap nav-row">
            <a class="brand" href="index.php">
                <span class="brand-mark">JN</span>
                <span><?= APP_NAME ?></span>
            </a>
            <button class="nav-toggle" type="button" data-nav-toggle aria-expanded="false" aria-controls="site-nav">Menu</button>
            <nav class="nav-links" id="site-nav" data-nav-menu>
                <a href="index.php">Home</a>
                <a href="jobs.php">Jobs</a>
                <a href="contact.php">Contact</a>
                <?php if ($user): ?>
                    <?php if ($user['role'] === 'job_seeker'): ?>
                        <a href="saved-jobs.php">Saved Jobs</a>
                    <?php endif; ?>
                    <a href="dashboard.php">Dashboard</a>
                    <a href="notifications.php">Alerts<?php if ($notificationCount > 0): ?> <span class="badge"><?= $notificationCount ?></span><?php endif; ?></a>
                    <a href="profile.php">Profile</a>
                    <a class="button-link ghost" href="logout.php">Logout</a>
                <?php else: ?>
                    <a href="login.php">Login</a>
                    <a class="button-link" href="register.php">Register</a>
                <?php endif; ?>
            </nav>
        </div>
    </header>
    <main class="wrap page-shell">
        <?php foreach (getFlashes() as $flash): ?>
            <div class="flash flash-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
        <?php endforeach; ?>
    <?php
}

function renderFooter(): void
{
    $scriptPath = __DIR__ . '/../assets/app.js';
    $scriptVersion = is_file($scriptPath) ? (string) filemtime($scriptPath) : (string) time();
    ?>
    </main>
    <footer class="site-footer">
        <div class="wrap footer-grid">
            <div>
                <h4>Company</h4>
                <a href="index.php">Home</a>
                <a href="jobs.php">Browse jobs</a>
                <a href="contact.php">Contact us</a>
            </div>
            <div>
                <h4>Account</h4>
                <a href="register.php">Create account</a>
                <a href="login.php">Login</a>
                <a href="forgot-password.php">Reset password</a>
            </div>
        </div>
        <div class="wrap footer-bottom">
            <span>&copy; <?= date('Y') ?> <?= APP_NAME ?></span>
        </div>
    </footer>
    <script src="assets/app.js?v=<?= e($scriptVersion) ?>"></script>
    </body>
    </html>
    <?php
}

function statusClass(string $status): string
{
    return match ($status) {
        'accepted' => 'success',
        'rejected' => 'danger',
        default => 'warning',
    };
}
