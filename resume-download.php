<?php

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

$user = requireLogin();
$file = basename(input('file'));
$path = RESUME_DIR . '/' . $file;

if ($file === '' || !is_file($path)) {
    flash('error', 'Resume file not found.');
    redirect('dashboard.php');
}

if ($user['role'] === 'job_seeker') {
    $resume = fetchResume((int) $user['id']);
    if (!$resume || $resume['stored_name'] !== $file) {
        flash('error', 'You can only download your own resume.');
        redirect('dashboard.php');
    }
} else {
    $stmt = db()->prepare(
        'SELECT COUNT(*)
         FROM resumes
         JOIN applications ON applications.applicant_id = resumes.user_id
         JOIN jobs ON jobs.id = applications.job_id
         WHERE resumes.stored_name = ? AND jobs.employer_id = ?'
    );
    $stmt->execute([$file, (int) $user['id']]);

    if ((int) $stmt->fetchColumn() === 0) {
        flash('error', 'You can only download resumes from applicants to your jobs.');
        redirect('dashboard.php');
    }
}

header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . basename($path) . '"');
header('Content-Length: ' . (string) filesize($path));
readfile($path);
exit;
