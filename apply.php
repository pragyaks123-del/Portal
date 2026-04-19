<?php

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

$user = requireRole('job_seeker');

if (isPost()) {
    verifyCsrf();

    $jobId = inputInt('job_id');
    $coverLetter = input('cover_letter');
    $job = fetchJobById($jobId);
    $resume = fetchResume((int) $user['id']);

    if (!$job) {
        flash('error', 'That job no longer exists.');
        redirect('jobs.php');
    }

    if (!$resume) {
        flash('error', 'Please upload a resume before applying.');
        redirect('profile.php');
    }

    if (jobApplicationStatus($jobId, (int) $user['id'])) {
        flash('error', 'You already applied for this job.');
        redirect('job.php?id=' . $jobId);
    }

    db()->prepare('INSERT INTO applications (job_id, applicant_id, cover_letter) VALUES (?, ?, ?)')
        ->execute([$jobId, (int) $user['id'], $coverLetter]);

    addNotification((int) $job['employer_id'], $user['name'] . ' applied for ' . $job['title'] . '.', 'dashboard.php');
    addNotification((int) $user['id'], 'Your application for ' . $job['title'] . ' is now pending review.', 'dashboard.php');
    flash('success', 'Application submitted successfully.');
    redirect('dashboard.php');
}

redirect('jobs.php');
