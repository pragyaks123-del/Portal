<?php

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

$user = requireRole('employer');

if (isPost()) {
    verifyCsrf();
    $applicationId = inputInt('application_id');
    $status = input('status');

    if (!in_array($status, ['pending', 'accepted', 'rejected'], true)) {
        flash('error', 'Invalid application status.');
        redirect('dashboard.php');
    }

    $stmt = db()->prepare(
        'SELECT applications.*, jobs.employer_id, jobs.title
         FROM applications
         JOIN jobs ON jobs.id = applications.job_id
         WHERE applications.id = ?'
    );
    $stmt->execute([$applicationId]);
    $application = $stmt->fetch();

    if (!$application || (int) $application['employer_id'] !== (int) $user['id']) {
        flash('error', 'You can only manage applications for your own jobs.');
        redirect('dashboard.php');
    }

    db()->prepare('UPDATE applications SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?')->execute([$status, $applicationId]);
    addNotification((int) $application['applicant_id'], 'Your application for ' . $application['title'] . ' was updated to ' . $status . '.', 'dashboard.php');
    flash('success', 'Application status updated.');
}

redirect('dashboard.php');
