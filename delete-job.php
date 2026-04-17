<?php

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

$user = requireRole('employer');

if (isPost()) {
    verifyCsrf();
    $jobId = inputInt('job_id');
    $job = fetchJobById($jobId);

    if ($job && (int) $job['employer_id'] === (int) $user['id']) {
        db()->prepare('DELETE FROM jobs WHERE id = ?')->execute([$jobId]);
        flash('success', 'Job deleted successfully.');
    } else {
        flash('error', 'You can only delete your own jobs.');
    }
}

redirect('dashboard.php');
