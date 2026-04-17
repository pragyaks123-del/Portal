<?php

declare(strict_types=1);

function inputInt(string $key, int $default = 0): int
{
    return (int) ($_POST[$key] ?? $_GET[$key] ?? $default);
}

function csrfToken(): string
{
    if (!isset($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(16));
    }

    return $_SESSION['_csrf'];
}

function csrfField(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrfToken()) . '">';
}

function verifyCsrf(): void
{
    if (!isPost()) {
        return;
    }

    $token = $_POST['_csrf'] ?? '';

    if (!hash_equals(csrfToken(), (string) $token)) {
        flash('error', 'Your session expired. Please try again.');
        redirect($_SERVER['HTTP_REFERER'] ?? 'index.php');
    }
}

function formatMoney(int $value): string
{
    return '$' . number_format($value);
}

function formatDateTime(string $value): string
{
    return date('M j, Y', strtotime($value));
}

function userLabel(array $user): string
{
    return $user['role'] === 'employer'
        ? ($user['company_name'] !== '' ? $user['company_name'] : $user['name'])
        : $user['name'];
}

function getHomepageStats(): array
{
    return [
        'jobs' => (int) db()->query('SELECT COUNT(*) FROM jobs')->fetchColumn(),
        'users' => (int) db()->query('SELECT COUNT(*) FROM users')->fetchColumn(),
        'applications' => (int) db()->query('SELECT COUNT(*) FROM applications')->fetchColumn(),
    ];
}

function fetchFeaturedJobs(int $limit = 5): array
{
    $stmt = db()->prepare(
        'SELECT jobs.*, users.name AS employer_name, users.company_name
         FROM jobs
         JOIN users ON users.id = jobs.employer_id
         ORDER BY jobs.created_at DESC
         LIMIT ?'
    );
    $stmt->bindValue(1, $limit, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll();
}

function buildJobFilters(): array
{
    return [
        'keyword' => input('keyword'),
        'location' => input('location'),
        'category' => input('category'),
        'job_type' => input('job_type'),
        'salary_min' => inputInt('salary_min'),
        'saved' => input('saved'),
    ];
}

function fetchJobs(array $filters = [], int $limit = 0, ?int $savedForUserId = null): array
{
    $sql = '
        SELECT jobs.*, users.name AS employer_name, users.company_name,
               EXISTS(
                   SELECT 1 FROM saved_jobs
                   WHERE saved_jobs.job_id = jobs.id
                   AND saved_jobs.user_id = :saved_user_id
               ) AS is_saved
        FROM jobs
        JOIN users ON users.id = jobs.employer_id
        WHERE 1 = 1
    ';

    $params = [':saved_user_id' => $savedForUserId ?? 0];

    if (($filters['keyword'] ?? '') !== '') {
        $sql .= ' AND (jobs.title LIKE :keyword OR jobs.skills LIKE :keyword OR jobs.description LIKE :keyword)';
        $params[':keyword'] = '%' . $filters['keyword'] . '%';
    }

    if (($filters['location'] ?? '') !== '') {
        $sql .= ' AND jobs.location LIKE :location';
        $params[':location'] = '%' . $filters['location'] . '%';
    }

    if (($filters['category'] ?? '') !== '') {
        $sql .= ' AND jobs.category LIKE :category';
        $params[':category'] = '%' . $filters['category'] . '%';
    }

    if (($filters['job_type'] ?? '') !== '') {
        $sql .= ' AND jobs.job_type = :job_type';
        $params[':job_type'] = $filters['job_type'];
    }

    if (($filters['salary_min'] ?? 0) > 0) {
        $sql .= ' AND jobs.salary_max >= :salary_min';
        $params[':salary_min'] = (int) $filters['salary_min'];
    }

    if (($filters['saved'] ?? '') === '1' && $savedForUserId) {
        $sql .= ' AND EXISTS (SELECT 1 FROM saved_jobs WHERE saved_jobs.job_id = jobs.id AND saved_jobs.user_id = :saved_only_user)';
        $params[':saved_only_user'] = $savedForUserId;
    }

    $sql .= ' ORDER BY jobs.created_at DESC';

    if ($limit > 0) {
        $sql .= ' LIMIT ' . (int) $limit;
    }

    $stmt = db()->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll();
}

function fetchJobById(int $jobId): ?array
{
    $stmt = db()->prepare(
        'SELECT jobs.*, users.name AS employer_name, users.company_name, users.email AS employer_email
         FROM jobs
         JOIN users ON users.id = jobs.employer_id
         WHERE jobs.id = ?'
    );
    $stmt->execute([$jobId]);
    $job = $stmt->fetch();

    return $job ?: null;
}

function fetchRelatedJobs(array $job, int $limit = 3): array
{
    $stmt = db()->prepare(
        'SELECT jobs.*, users.name AS employer_name, users.company_name
         FROM jobs
         JOIN users ON users.id = jobs.employer_id
         WHERE jobs.id != :job_id
           AND (jobs.category = :category OR jobs.location = :location)
         ORDER BY jobs.created_at DESC
         LIMIT :limit'
    );
    $stmt->bindValue(':job_id', (int) $job['id'], PDO::PARAM_INT);
    $stmt->bindValue(':category', $job['category'], PDO::PARAM_STR);
    $stmt->bindValue(':location', $job['location'], PDO::PARAM_STR);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll();
}

function fetchResume(int $userId): ?array
{
    $stmt = db()->prepare('SELECT * FROM resumes WHERE user_id = ?');
    $stmt->execute([$userId]);
    $resume = $stmt->fetch();

    return $resume ?: null;
}

function fetchEmployerProfile(int $userId): ?array
{
    $stmt = db()->prepare('SELECT * FROM employer_profiles WHERE user_id = ?');
    $stmt->execute([$userId]);
    $profile = $stmt->fetch();

    return $profile ?: null;
}

function jobApplicationStatus(int $jobId, int $userId): ?string
{
    $stmt = db()->prepare('SELECT status FROM applications WHERE job_id = ? AND applicant_id = ?');
    $stmt->execute([$jobId, $userId]);
    $status = $stmt->fetchColumn();

    return $status === false ? null : (string) $status;
}

function isSavedJob(int $jobId, int $userId): bool
{
    $stmt = db()->prepare('SELECT COUNT(*) FROM saved_jobs WHERE job_id = ? AND user_id = ?');
    $stmt->execute([$jobId, $userId]);

    return (int) $stmt->fetchColumn() > 0;
}

function dashboardStats(array $user): array
{
    if ($user['role'] === 'employer') {
        $jobs = db()->prepare('SELECT COUNT(*) FROM jobs WHERE employer_id = ?');
        $jobs->execute([$user['id']]);
        $applications = db()->prepare(
            'SELECT COUNT(*)
             FROM applications
             JOIN jobs ON jobs.id = applications.job_id
             WHERE jobs.employer_id = ?'
        );
        $applications->execute([$user['id']]);

        return [
            'jobs' => (int) $jobs->fetchColumn(),
            'applications' => (int) $applications->fetchColumn(),
            'alerts' => unreadNotificationCount((int) $user['id']),
        ];
    }

    $applications = db()->prepare('SELECT COUNT(*) FROM applications WHERE applicant_id = ?');
    $applications->execute([$user['id']]);
    $saved = db()->prepare('SELECT COUNT(*) FROM saved_jobs WHERE user_id = ?');
    $saved->execute([$user['id']]);

    return [
        'applications' => (int) $applications->fetchColumn(),
        'saved' => (int) $saved->fetchColumn(),
        'alerts' => unreadNotificationCount((int) $user['id']),
    ];
}

function employerJobs(int $userId): array
{
    $stmt = db()->prepare(
        'SELECT jobs.*,
                (SELECT COUNT(*) FROM applications WHERE applications.job_id = jobs.id) AS applicant_count
         FROM jobs
         WHERE employer_id = ?
         ORDER BY created_at DESC'
    );
    $stmt->execute([$userId]);

    return $stmt->fetchAll();
}

function seekerApplications(int $userId): array
{
    $stmt = db()->prepare(
        'SELECT applications.*, jobs.title, jobs.location, jobs.job_type, jobs.category,
                users.name AS employer_name, users.company_name
         FROM applications
         JOIN jobs ON jobs.id = applications.job_id
         JOIN users ON users.id = jobs.employer_id
         WHERE applications.applicant_id = ?
         ORDER BY applications.updated_at DESC'
    );
    $stmt->execute([$userId]);

    return $stmt->fetchAll();
}

function savedJobs(int $userId): array
{
    $stmt = db()->prepare(
        'SELECT jobs.*, users.name AS employer_name, users.company_name
         FROM saved_jobs
         JOIN jobs ON jobs.id = saved_jobs.job_id
         JOIN users ON users.id = jobs.employer_id
         WHERE saved_jobs.user_id = ?
         ORDER BY saved_jobs.created_at DESC'
    );
    $stmt->execute([$userId]);

    return $stmt->fetchAll();
}

function applicantsForEmployer(int $employerId): array
{
    $stmt = db()->prepare(
        'SELECT applications.*, jobs.title,
                users.name AS applicant_name, users.email AS applicant_email,
                resumes.original_name, resumes.summary, resumes.skills, resumes.stored_name
         FROM applications
         JOIN jobs ON jobs.id = applications.job_id
         JOIN users ON users.id = applications.applicant_id
         LEFT JOIN resumes ON resumes.user_id = users.id
         WHERE jobs.employer_id = ?
         ORDER BY applications.updated_at DESC'
    );
    $stmt->execute([$employerId]);

    return $stmt->fetchAll();
}

function notificationsForUser(int $userId): array
{
    $stmt = db()->prepare('SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC');
    $stmt->execute([$userId]);

    return $stmt->fetchAll();
}

function markNotificationsRead(int $userId): void
{
    $stmt = db()->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = ?');
    $stmt->execute([$userId]);
}

function passwordResetRecord(string $token): ?array
{
    $stmt = db()->prepare(
        'SELECT password_resets.*, users.email
         FROM password_resets
         JOIN users ON users.id = password_resets.user_id
         WHERE token = ?'
    );
    $stmt->execute([$token]);
    $record = $stmt->fetch();

    return $record ?: null;
}

function passwordResetRecordByEmailAndCode(string $email, string $code): ?array
{
    $stmt = db()->prepare(
        'SELECT password_resets.*, users.email
         FROM password_resets
         JOIN users ON users.id = password_resets.user_id
         WHERE users.email = ? AND password_resets.token = ?
         ORDER BY password_resets.created_at DESC
         LIMIT 1'
    );
    $stmt->execute([$email, $code]);
    $record = $stmt->fetch();

    return $record ?: null;
}

function generateResetToken(): string
{
    return bin2hex(random_bytes(32));
}

function generateResetCode(): string
{
    return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
}

function createUniquePasswordResetCode(): string
{
    for ($attempt = 0; $attempt < 5; $attempt++) {
        $code = generateResetCode();
        $stmt = db()->prepare('SELECT COUNT(*) FROM password_resets WHERE token = ? AND used_at IS NULL AND expires_at >= NOW()');
        $stmt->execute([$code]);

        if ((int) $stmt->fetchColumn() === 0) {
            return $code;
        }
    }

    throw new RuntimeException('Unable to generate a unique reset code.');
}

function appBaseUrl(): string
{
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = (string) ($_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost');
    $scriptName = (string) ($_SERVER['SCRIPT_NAME'] ?? '');
    $basePath = str_replace('\\', '/', dirname($scriptName));

    if ($basePath === '/' || $basePath === '.') {
        $basePath = '';
    }

    return $scheme . '://' . $host . $basePath;
}

function appUrl(string $path): string
{
    $path = ltrim($path, '/');

    return rtrim(appBaseUrl(), '/') . '/' . $path;
}

function mailConfig(): array
{
    $config = $GLOBALS['mail_config'] ?? [];

    return is_array($config) ? $config : [];
}

function isLocalDevelopment(): bool
{
    $host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? ''));
    $serverAddr = (string) ($_SERVER['SERVER_ADDR'] ?? '');
    $remoteAddr = (string) ($_SERVER['REMOTE_ADDR'] ?? '');

    return str_contains($host, 'localhost')
        || str_contains($host, '127.0.0.1')
        || $serverAddr === '127.0.0.1'
        || $serverAddr === '::1'
        || $remoteAddr === '127.0.0.1'
        || $remoteAddr === '::1';
}

function logEmailMessage(string $to, string $subject, string $message): string
{
    $timestamp = date('Ymd-His');
    $safeEmail = preg_replace('/[^a-z0-9._-]+/i', '-', strtolower($to)) ?: 'recipient';
    $fileName = $timestamp . '-' . $safeEmail . '.txt';
    $path = MAIL_LOG_DIR . '/' . $fileName;
    $body = "To: {$to}\nSubject: {$subject}\nDate: " . date('c') . "\n\n{$message}\n";

    file_put_contents($path, $body);

    return $path;
}

function smtpReadResponse($socket): string
{
    $response = '';

    while (!feof($socket)) {
        $line = fgets($socket, 515);

        if ($line === false) {
            break;
        }

        $response .= $line;

        if (strlen($line) < 4 || $line[3] !== '-') {
            break;
        }
    }

    return $response;
}

function smtpExpectCode($socket, array $expectedCodes): string
{
    $response = smtpReadResponse($socket);
    $code = (int) substr($response, 0, 3);

    if (!in_array($code, $expectedCodes, true)) {
        throw new RuntimeException('SMTP unexpected response: ' . trim($response));
    }

    return $response;
}

function smtpWriteLine($socket, string $command): void
{
    fwrite($socket, $command . "\r\n");
}

function sendEmailViaSmtp(string $to, string $subject, string $message, array $config): bool
{
    $host = trim((string) ($config['host'] ?? ''));
    $port = (int) ($config['port'] ?? 587);
    $encryption = strtolower((string) ($config['encryption'] ?? 'tls'));
    $username = (string) ($config['username'] ?? '');
    $password = (string) ($config['password'] ?? '');
    $fromEmail = trim((string) ($config['from_email'] ?? ''));
    $fromName = trim((string) ($config['from_name'] ?? APP_NAME));
    $timeout = (int) ($config['timeout'] ?? 15);

    if ($host === '' || $fromEmail === '') {
        return false;
    }

    $transportHost = $encryption === 'ssl' ? 'ssl://' . $host : $host;
    $socket = @stream_socket_client(
        $transportHost . ':' . $port,
        $errorNumber,
        $errorMessage,
        $timeout,
        STREAM_CLIENT_CONNECT
    );

    if (!$socket) {
        error_log("JobNova SMTP connect failed: {$errorMessage} ({$errorNumber})");
        return false;
    }

    stream_set_timeout($socket, $timeout);

    try {
        smtpExpectCode($socket, [220]);

        $serverName = $_SERVER['SERVER_NAME'] ?? 'localhost';
        smtpWriteLine($socket, 'EHLO ' . $serverName);
        smtpExpectCode($socket, [250]);

        if ($encryption === 'tls') {
            smtpWriteLine($socket, 'STARTTLS');
            smtpExpectCode($socket, [220]);

            $cryptoEnabled = stream_socket_enable_crypto(
                $socket,
                true,
                STREAM_CRYPTO_METHOD_TLS_CLIENT
            );

            if ($cryptoEnabled !== true) {
                throw new RuntimeException('SMTP STARTTLS negotiation failed.');
            }

            smtpWriteLine($socket, 'EHLO ' . $serverName);
            smtpExpectCode($socket, [250]);
        }

        if ($username !== '' || $password !== '') {
            smtpWriteLine($socket, 'AUTH LOGIN');
            smtpExpectCode($socket, [334]);
            smtpWriteLine($socket, base64_encode($username));
            smtpExpectCode($socket, [334]);
            smtpWriteLine($socket, base64_encode($password));
            smtpExpectCode($socket, [235]);
        }

        smtpWriteLine($socket, 'MAIL FROM:<' . $fromEmail . '>');
        smtpExpectCode($socket, [250]);
        smtpWriteLine($socket, 'RCPT TO:<' . $to . '>');
        smtpExpectCode($socket, [250, 251]);
        smtpWriteLine($socket, 'DATA');
        smtpExpectCode($socket, [354]);

        $headers = [
            'Date: ' . date(DATE_RFC2822),
            'From: ' . $fromName . ' <' . $fromEmail . '>',
            'To: ' . $to,
            'Subject: ' . $subject,
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
        ];
        $body = implode("\r\n", $headers) . "\r\n\r\n" . str_replace("\n.", "\n..", $message) . "\r\n.";

        smtpWriteLine($socket, $body);
        smtpExpectCode($socket, [250]);
        smtpWriteLine($socket, 'QUIT');
        fclose($socket);

        return true;
    } catch (Throwable $exception) {
        error_log('JobNova SMTP send failed: ' . $exception->getMessage());
        fclose($socket);

        return false;
    }
}

function sendEmailMessage(string $to, string $subject, string $message): bool
{
    $config = mailConfig();

    if (($config['enabled'] ?? false) === true && sendEmailViaSmtp($to, $subject, $message, $config)) {
        return true;
    }

    $headers = [
        'MIME-Version: 1.0',
        'Content-type: text/plain; charset=UTF-8',
        'From: JobNova <no-reply@jobnova.local>',
    ];

    $sent = @mail($to, $subject, $message, implode("\r\n", $headers));

    if (!$sent) {
        error_log('JobNova mail send failed for ' . $to);
        logEmailMessage($to, $subject, $message);
    }

    return $sent;
}
