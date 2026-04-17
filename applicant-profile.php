<?php

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

$user = requireRole('employer');
$applicantId = inputInt('id');

$profileStmt = db()->prepare(
    'SELECT users.id, users.name, users.email, users.bio, users.created_at,
            resumes.original_name, resumes.stored_name, resumes.summary, resumes.skills
     FROM users
     JOIN applications ON applications.applicant_id = users.id
     JOIN jobs ON jobs.id = applications.job_id
     LEFT JOIN resumes ON resumes.user_id = users.id
     WHERE users.id = ? AND jobs.employer_id = ?
     LIMIT 1'
);
$profileStmt->execute([$applicantId, (int) $user['id']]);
$applicant = $profileStmt->fetch();

if (!$applicant) {
    flash('error', 'That applicant profile is not available for your jobs.');
    redirect('dashboard.php');
}

$applicationsStmt = db()->prepare(
    'SELECT applications.status, applications.cover_letter, applications.updated_at,
            jobs.id AS job_id, jobs.title, jobs.location, jobs.category, jobs.job_type
     FROM applications
     JOIN jobs ON jobs.id = applications.job_id
     WHERE applications.applicant_id = ? AND jobs.employer_id = ?
     ORDER BY applications.updated_at DESC'
);
$applicationsStmt->execute([$applicantId, (int) $user['id']]);
$applications = $applicationsStmt->fetchAll();

renderHeader('Applicant Profile');
?>
<section class="page-hero compact-hero">
    <p class="eyebrow">Applicant Profile</p>
    <h1><?= e($applicant['name']) ?></h1>
    <p>Review the candidate's profile, resume details, and applications connected to your jobs before making a hiring decision.</p>
</section>

<section class="content-grid">
    <div class="panel">
        <p class="eyebrow">Candidate Details</p>
        <h2>Profile overview</h2>
        <div class="stack">
            <div class="card soft-card">
                <strong>Email</strong>
                <p class="small"><?= e($applicant['email']) ?></p>
            </div>
            <div class="card soft-card">
                <strong>Bio</strong>
                <p><?= e($applicant['bio'] !== '' ? $applicant['bio'] : 'No bio has been added yet.') ?></p>
            </div>
            <div class="card soft-card">
                <strong>Member since</strong>
                <p class="small"><?= e(formatDateTime($applicant['created_at'])) ?></p>
            </div>
        </div>
    </div>

    <div class="panel">
        <p class="eyebrow">Resume</p>
        <h2>Professional snapshot</h2>
        <div class="stack">
            <?php if ($applicant['original_name']): ?>
                <div class="card soft-card">
                    <strong>Resume file</strong>
                    <p class="small"><a class="text-link" href="resume-download.php?file=<?= urlencode($applicant['stored_name']) ?>"><?= e($applicant['original_name']) ?></a></p>
                </div>
                <div class="card soft-card">
                    <strong>Summary</strong>
                    <p><?= e($applicant['summary'] !== '' ? $applicant['summary'] : 'No summary has been added yet.') ?></p>
                </div>
                <div class="card soft-card">
                    <strong>Skills</strong>
                    <p><?= e($applicant['skills'] !== '' ? $applicant['skills'] : 'No skills have been listed yet.') ?></p>
                </div>
            <?php else: ?>
                <div class="empty">This applicant has not uploaded a resume yet.</div>
            <?php endif; ?>
        </div>
    </div>
</section>

<section class="panel">
    <div class="section-head">
        <div>
            <p class="eyebrow">Applications</p>
            <h2>Roles this candidate applied for</h2>
        </div>
    </div>
    <div class="stack">
        <?php foreach ($applications as $application): ?>
            <article class="job-card list-card">
                <div class="job-card-top">
                    <div>
                        <h3><a href="job.php?id=<?= (int) $application['job_id'] ?>"><?= e($application['title']) ?></a></h3>
                        <p class="meta-line"><?= e($application['location']) ?> • <?= e($application['category']) ?> • <?= e($application['job_type']) ?></p>
                    </div>
                    <span class="status <?= statusClass($application['status']) ?>"><?= e(ucfirst($application['status'])) ?></span>
                </div>
                <p><?= e($application['cover_letter'] !== '' ? $application['cover_letter'] : 'No cover letter was submitted for this application.') ?></p>
            </article>
        <?php endforeach; ?>
    </div>
</section>
<?php renderFooter(); ?>
