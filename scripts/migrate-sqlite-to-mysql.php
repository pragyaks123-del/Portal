<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$sqlitePath = $root . '/storage/job_portal.sqlite';
$config = require $root . '/includes/db-config.php';

if (!is_file($sqlitePath)) {
    fwrite(STDERR, "SQLite database not found at {$sqlitePath}\n");
    exit(1);
}

$sqlite = new PDO('sqlite:' . $sqlitePath);
$sqlite->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$sqlite->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

$server = new PDO(
    sprintf(
        'mysql:host=%s;port=%d;charset=%s',
        $config['host'] ?? '127.0.0.1',
        (int) ($config['port'] ?? 3306),
        $config['charset'] ?? 'utf8mb4'
    ),
    $config['username'] ?? 'root',
    $config['password'] ?? '',
    [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]
);

$databaseName = str_replace('`', '``', (string) ($config['database'] ?? 'job_portal'));
$charset = preg_replace('/[^a-zA-Z0-9_]+/', '', (string) ($config['charset'] ?? 'utf8mb4')) ?: 'utf8mb4';

$server->exec(sprintf(
    'CREATE DATABASE IF NOT EXISTS `%s` CHARACTER SET %s COLLATE %s_unicode_ci',
    $databaseName,
    $charset,
    $charset
));

$mysql = new PDO(
    sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=%s',
        $config['host'] ?? '127.0.0.1',
        (int) ($config['port'] ?? 3306),
        $config['database'] ?? 'job_portal',
        $config['charset'] ?? 'utf8mb4'
    ),
    $config['username'] ?? 'root',
    $config['password'] ?? '',
    [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]
);

$mysql->exec('SET FOREIGN_KEY_CHECKS = 0');

foreach ([
    'notifications',
    'applications',
    'resumes',
    'saved_jobs',
    'favorites',
    'jobs',
    'password_resets',
    'users',
] as $table) {
    $mysql->exec("DROP TABLE IF EXISTS `{$table}`");
}

$schema = [
    <<<'SQL'
CREATE TABLE users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('job_seeker', 'employer') NOT NULL,
    bio TEXT DEFAULT NULL,
    company_name VARCHAR(255) DEFAULT '',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
SQL,
    <<<'SQL'
CREATE TABLE password_resets (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    token VARCHAR(64) NOT NULL UNIQUE,
    expires_at DATETIME NOT NULL,
    used_at DATETIME DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_password_resets_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
SQL,
    <<<'SQL'
CREATE TABLE jobs (
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
    CONSTRAINT fk_jobs_employer FOREIGN KEY (employer_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
SQL,
    <<<'SQL'
CREATE TABLE saved_jobs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    job_id INT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_saved_jobs_user_job (user_id, job_id),
    CONSTRAINT fk_saved_jobs_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_saved_jobs_job FOREIGN KEY (job_id) REFERENCES jobs(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
SQL,
    <<<'SQL'
CREATE TABLE resumes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL UNIQUE,
    original_name VARCHAR(255) NOT NULL,
    stored_name VARCHAR(255) NOT NULL,
    summary TEXT DEFAULT NULL,
    skills TEXT DEFAULT NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_resumes_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
SQL,
    <<<'SQL'
CREATE TABLE applications (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    job_id INT UNSIGNED NOT NULL,
    applicant_id INT UNSIGNED NOT NULL,
    cover_letter TEXT DEFAULT NULL,
    status ENUM('pending', 'accepted', 'rejected') NOT NULL DEFAULT 'pending',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_applications_job_applicant (job_id, applicant_id),
    CONSTRAINT fk_applications_job FOREIGN KEY (job_id) REFERENCES jobs(id) ON DELETE CASCADE,
    CONSTRAINT fk_applications_applicant FOREIGN KEY (applicant_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
SQL,
    <<<'SQL'
CREATE TABLE notifications (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    message TEXT NOT NULL,
    link VARCHAR(255) DEFAULT '',
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_notifications_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
SQL,
];

foreach ($schema as $statement) {
    $mysql->exec($statement);
}

$tableOrder = [
    'users',
    'password_resets',
    'jobs',
    'saved_jobs',
    'resumes',
    'applications',
    'notifications',
];

foreach ($tableOrder as $table) {
    $rows = $sqlite->query("SELECT * FROM {$table}")->fetchAll();

    if ($rows === []) {
        echo $table . ":0\n";
        continue;
    }

    $columns = array_keys($rows[0]);
    $columnList = implode(', ', array_map(static fn (string $column): string => "`{$column}`", $columns));
    $placeholderList = implode(', ', array_fill(0, count($columns), '?'));
    $statement = $mysql->prepare("INSERT INTO `{$table}` ({$columnList}) VALUES ({$placeholderList})");

    foreach ($rows as $row) {
        $statement->execute(array_values($row));
    }

    if (in_array('id', $columns, true)) {
        $maxId = max(array_map(static fn (array $row): int => (int) $row['id'], $rows));
        $mysql->exec('ALTER TABLE `' . $table . '` AUTO_INCREMENT = ' . ($maxId + 1));
    }

    echo $table . ':' . count($rows) . "\n";
}

$mysql->exec('SET FOREIGN_KEY_CHECKS = 1');

echo "Migration complete\n";
