<?php

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

$user = requireRole('job_seeker');
$saved = savedJobs((int) $user['id']);

renderHeader('Saved Jobs');
?>
<section class="page-hero compact-hero">
    <p class="eyebrow">Saved Jobs</p>
    <h1>Keep track of the roles you want to revisit.</h1>
    <p>Review your shortlisted opportunities, return to full job descriptions, or remove roles once they are no longer relevant.</p>
</section>

<section class="stack">
    <?php if (!$saved): ?>
        <div class="panel empty">
            You have not saved any jobs yet. Browse current openings and save the ones you want to come back to.
            <div class="card-actions" style="margin-top: 1rem;">
                <a class="button-link" href="jobs.php">Browse Jobs</a>
            </div>
        </div>
    <?php endif; ?>

    <?php foreach ($saved as $job): ?>
        <article class="job-card list-card">
            <div class="job-card-top">
                <div>
                    <p class="meta-line"><?= e($job['company_name'] ?: $job['employer_name']) ?></p>
                    <h2><a href="job.php?id=<?= (int) $job['id'] ?>"><?= e($job['title']) ?></a></h2>
                </div>
                <span class="badge badge-soft"><?= e($job['job_type']) ?></span>
            </div>
            <div class="job-meta">
                <span><?= e($job['location']) ?></span>
                <span><?= e($job['category']) ?></span>
                <span><?= formatMoney((int) $job['salary_min']) ?> - <?= formatMoney((int) $job['salary_max']) ?></span>
            </div>
            <p><?= e($job['description']) ?></p>
            <div class="card-actions wrap-actions">
                <a class="button-link slim" href="job.php?id=<?= (int) $job['id'] ?>">View Details</a>
                <form method="post" action="toggle-save-job.php" class="inline-form">
                    <?= csrfField() ?>
                    <input type="hidden" name="job_id" value="<?= (int) $job['id'] ?>">
                    <input type="hidden" name="return_to" value="saved-jobs.php">
                    <button class="button-link ghost slim" type="submit">Remove Saved Job</button>
                </form>
            </div>
        </article>
    <?php endforeach; ?>
</section>
<?php renderFooter(); ?>
