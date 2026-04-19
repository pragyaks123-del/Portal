<?php

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

$user = requireRole('job_seeker');

if (isPost()) {
    verifyCsrf();
    $jobId = inputInt('job_id');
    $returnTo = input('return_to', 'jobs.php');

    if (isSavedJob($jobId, (int) $user['id'])) {
        db()->prepare('DELETE FROM saved_jobs WHERE user_id = ? AND job_id = ?')->execute([(int) $user['id'], $jobId]);
        flash('success', 'Job removed from favourites.');
    } else {
        db()->prepare('INSERT IGNORE INTO saved_jobs (user_id, job_id) VALUES (?, ?)')->execute([(int) $user['id'], $jobId]);
        flash('success', 'Job saved to favourites.');
    }

    redirect($returnTo !== '' ? $returnTo : 'jobs.php');
}

redirect('jobs.php');
