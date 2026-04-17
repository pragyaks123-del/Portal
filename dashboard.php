<?php

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

$user = requireLogin();
$stats = dashboardStats($user);

if ($user['role'] === 'employer') {
    $jobs = employerJobs((int) $user['id']);
    $applicants = applicantsForEmployer((int) $user['id']);
} else {
    $applications = seekerApplications((int) $user['id']);
    $saved = savedJobs((int) $user['id']);
}

renderHeader('Dashboard');
?>
<section class="page-hero compact-hero">
    <p class="eyebrow">Dashboard</p>
    <h1><?= $user['role'] === 'employer' ? 'Manage your hiring pipeline with clarity' : 'Stay organized throughout your job search' ?></h1>
    <p><?= $user['role'] === 'employer'
        ? e(userLabel($user)) . ' can review active listings, evaluate applicants, and keep hiring decisions moving from one focused workspace.'
        : e(userLabel($user)) . ' can follow application progress, revisit saved roles, and keep every opportunity within easy reach.' ?></p>
</section>

<section class="stats-grid dashboard-stats">
    <?php foreach ($stats as $label => $value): ?>
        <div class="stat dark-stat">
            <strong><?= (int) $value ?></strong>
            <?= e(ucfirst($label)) ?>
        </div>
    <?php endforeach; ?>
</section>

<?php if ($user['role'] === 'employer'): ?>
    <section class="content-grid">
        <div class="panel">
            <div class="section-head">
                <div>
                    <p class="eyebrow">Job Posting</p>
                    <h2>Your active listings</h2>
                </div>
                <a class="button-link slim" href="manage-job.php">Create Job</a>
            </div>
            <div class="stack">
                <?php foreach ($jobs as $job): ?>
                    <article class="job-card list-card">
                        <div class="job-card-top">
                            <div>
                                <h3><a href="job.php?id=<?= (int) $job['id'] ?>"><?= e($job['title']) ?></a></h3>
                                <p class="meta-line"><?= e($job['location']) ?> • <?= e($job['category']) ?></p>
                            </div>
                            <span class="badge badge-soft"><?= (int) $job['applicant_count'] ?> applicants</span>
                        </div>
                        <div class="card-actions">
                            <a class="button-link slim ghost" href="manage-job.php?id=<?= (int) $job['id'] ?>">Edit</a>
                            <form method="post" action="delete-job.php" class="inline-form">
                                <?= csrfField() ?>
                                <input type="hidden" name="job_id" value="<?= (int) $job['id'] ?>">
                                <button class="button-link slim danger" type="submit">Delete</button>
                            </form>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="panel">
            <p class="eyebrow">Applications</p>
            <h2>Recent applicants</h2>
            <div class="stack">
                <?php if (!$applicants): ?>
                    <div class="empty">No one has applied yet.</div>
                <?php endif; ?>
                <?php foreach ($applicants as $application): ?>
                    <article class="card soft-card">
                        <h3><?= e($application['applicant_name']) ?></h3>
                        <p class="meta-line"><?= e($application['title']) ?> • <?= e($application['applicant_email']) ?></p>
                        <p><?= e($application['cover_letter']) ?></p>
                        <?php if ($application['original_name']): ?>
                            <p class="small">Resume: <a class="text-link" href="resume-download.php?file=<?= urlencode($application['stored_name']) ?>"><?= e($application['original_name']) ?></a></p>
                        <?php endif; ?>
                        <div class="card-actions wrap-actions">
                            <a class="button-link slim ghost" href="applicant-profile.php?id=<?= (int) $application['applicant_id'] ?>">View Profile</a>
                        </div>
                        <form method="post" action="update-application.php" class="card-actions wrap-actions">
                            <?= csrfField() ?>
                            <input type="hidden" name="application_id" value="<?= (int) $application['id'] ?>">
                            <select name="status">
                                <option value="pending" <?= $application['status'] === 'pending' ? 'selected' : '' ?>>Pending</option>
                                <option value="accepted" <?= $application['status'] === 'accepted' ? 'selected' : '' ?>>Accepted</option>
                                <option value="rejected" <?= $application['status'] === 'rejected' ? 'selected' : '' ?>>Rejected</option>
                            </select>
                            <button class="button-link slim" type="submit">Update Status</button>
                        </form>
                    </article>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
<?php else: ?>
    <section class="content-grid">
        <div class="panel">
            <p class="eyebrow">Applications</p>
            <h2>Your applied jobs</h2>
            <div class="stack">
                <?php if (!$applications): ?>
                    <div class="empty">You have not applied to any jobs yet.</div>
                <?php endif; ?>
                <?php foreach ($applications as $application): ?>
                    <article class="job-card list-card">
                        <div class="job-card-top">
                            <div>
                                <h3><a href="job.php?id=<?= (int) $application['job_id'] ?>"><?= e($application['title']) ?></a></h3>
                                <p class="meta-line"><?= e($application['company_name'] ?: $application['employer_name']) ?></p>
                            </div>
                            <span class="status <?= statusClass($application['status']) ?>"><?= ucfirst($application['status']) ?></span>
                        </div>
                        <div class="job-meta">
                            <span><?= e($application['location']) ?></span>
                            <span><?= e($application['category']) ?></span>
                            <span><?= e($application['job_type']) ?></span>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="panel">
            <p class="eyebrow">Saved Jobs</p>
            <h2>Favorites you can revisit later</h2>
            <div class="stack">
                <?php if (!$saved): ?>
                    <div class="empty">You have not saved any jobs yet.</div>
                <?php endif; ?>
                <?php foreach ($saved as $job): ?>
                    <article class="job-card related-card">
                        <div>
                            <h3><a href="job.php?id=<?= (int) $job['id'] ?>"><?= e($job['title']) ?></a></h3>
                            <p class="meta-line"><?= e($job['company_name'] ?: $job['employer_name']) ?></p>
                        </div>
                        <span class="badge badge-soft"><?= e($job['location']) ?></span>
                    </article>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
<?php endif; ?>
<?php renderFooter(); ?>
