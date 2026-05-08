<?php

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

$jobId = inputInt('id');
$job = fetchJobById($jobId);

if (!$job) {
    flash('error', 'That job could not be found.');
    redirect('jobs.php');
}

$user = currentUser();
$applicationStatus = $user && $user['role'] === 'job_seeker' ? jobApplicationStatus($jobId, (int) $user['id']) : null;
$resume = $user && $user['role'] === 'job_seeker' ? fetchResume((int) $user['id']) : null;
// Dev 2: CV Scan - build a private seeker-only match score for this job.
$match = $user && $user['role'] === 'job_seeker' ? resumeMatchForJob($resume, $job) : null;
$relatedJobs = fetchRelatedJobs($job, 3);

renderHeader('Job Details');
?>
<section class="page-hero compact-hero">
    <p class="eyebrow">Job Details</p>
    <h1><?= e($job['title']) ?></h1>
    <div class="job-meta">
        <span><?= e($job['company_name'] ?: $job['employer_name']) ?></span>
        <span><?= e($job['location']) ?></span>
        <span><?= e($job['category']) ?></span>
        <span><?= e($job['job_type']) ?></span>
    </div>
</section>

<section class="detail-layout">
    <article class="panel detail-copy">
        <h2>Job Description</h2>
        <p><?= e($job['description']) ?></p>

        <h3>Pay Responsibilities</h3>
        <ul class="detail-list">
            <li>Salary range: <?= formatMoney((int) $job['salary_min']) ?> to <?= formatMoney((int) $job['salary_max']) ?></li>
            <li>Skills requested: <?= e($job['skills']) ?></li>
            <li>Employment type: <?= e($job['job_type']) ?></li>
            <li>Posted on <?= formatDateTime($job['created_at']) ?></li>
        </ul>

        <h3>Professional Skills</h3>
        <p><?= e($job['skills']) ?></p>

        <h3>Tags</h3>
        <div class="job-meta">
            <?php foreach (explode(',', $job['skills']) as $skill): ?>
                <span class="badge badge-soft"><?= e(trim($skill)) ?></span>
            <?php endforeach; ?>
        </div>
    </article>

    <aside class="stack">
        <div class="panel sidebar-card">
            <h3>Job Overview</h3>
            <div class="info-row"><strong>Category</strong><span><?= e($job['category']) ?></span></div>
            <div class="info-row"><strong>Location</strong><span><?= e($job['location']) ?></span></div>
            <div class="info-row"><strong>Job Type</strong><span><?= e($job['job_type']) ?></span></div>
            <div class="info-row"><strong>Salary</strong><span><?= formatMoney((int) $job['salary_min']) ?> - <?= formatMoney((int) $job['salary_max']) ?></span></div>
            <div class="info-row"><strong>Contact</strong><span><?= e($job['employer_email']) ?></span></div>
        </div>

        <div class="panel sidebar-card">
            <h3>Apply Now</h3>
            <?php if (!$user): ?>
                <p class="small">Create an account or log in as a job seeker to apply.</p>
                <a class="button-link full-width" href="login.php">Login to Apply</a>
            <?php elseif ($user['role'] !== 'job_seeker'): ?>
                <p class="small">Employer accounts can manage jobs and applicants from the dashboard.</p>
                <a class="button-link full-width ghost" href="dashboard.php">Open Dashboard</a>
            <?php elseif ($applicationStatus): ?>
                <p class="small">You already applied for this job.</p>
                <span class="status <?= statusClass($applicationStatus) ?> full-width"><?= ucfirst($applicationStatus) ?></span>
            <?php elseif (!$resume): ?>
                <p class="small">Upload your resume first so employers can review your experience.</p>
                <a class="button-link full-width" href="profile.php">Upload Resume</a>
            <?php else: ?>
                <form method="post" action="apply.php" class="filters">
                    <?= csrfField() ?>
                    <input type="hidden" name="job_id" value="<?= (int) $job['id'] ?>">
                    <label>
                        Cover Letter
                        <textarea name="cover_letter" placeholder="Share why you're a good fit for this role."></textarea>
                    </label>
                    <button type="submit">Submit Application</button>
                </form>
            <?php endif; ?>
        </div>

        <?php if ($user && $user['role'] === 'job_seeker'): ?>
            <div class="panel sidebar-card">
                <h3>Your CV Match</h3>
                <?php // Dev 2: CV Scan - show matched and missing requirements without exposing the resume to employers here. ?>
                <?php if ($match): ?>
                    <strong class="match-score-large"><?= (int) $match['score'] ?>% match</strong>
                    <div class="match-breakdown open">
                        <?php foreach ($match['matched_requirements'] as $requirement): ?>
                            <p class="match-line have"><span aria-hidden="true">&#10003;</span><?= e($requirement) ?></p>
                        <?php endforeach; ?>
                        <?php foreach ($match['missing_requirements'] as $requirement): ?>
                            <p class="match-line missing"><span aria-hidden="true">&#10007;</span><?= e($requirement) ?></p>
                        <?php endforeach; ?>
                        <?php if ($match['missing_requirements']): ?>
                            <p class="small">You are missing: <?= e(implode(', ', $match['missing_requirements'])) ?></p>
                        <?php else: ?>
                            <p class="small">You meet the listed requirements for this role.</p>
                        <?php endif; ?>
                    </div>
                <?php elseif ($resume && ($resume['scan_status'] ?? '') === 'failed'): ?>
                    <p class="status danger">CV unreadable</p>
                    <p class="small"><?= e($resume['scan_error'] ?? 'We could not read your CV - please upload a text-based PDF.') ?></p>
                <?php else: ?>
                    <p class="small">Upload and scan your resume to see a private match score.</p>
                    <a class="button-link full-width ghost" href="profile.php">Upload Resume</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if ($user && $user['role'] === 'job_seeker'): ?>
            <form method="post" action="toggle-save-job.php" class="panel sidebar-card filters">
                <?= csrfField() ?>
                <input type="hidden" name="job_id" value="<?= (int) $job['id'] ?>">
                <input type="hidden" name="return_to" value="job.php?id=<?= (int) $job['id'] ?>">
                <button type="submit"><?= isSavedJob((int) $job['id'], (int) $user['id']) ? 'Remove Saved Job' : 'Save This Job' ?></button>
            </form>
        <?php endif; ?>
    </aside>
</section>

<section class="panel">
    <div class="section-head">
        <div>
            <p class="eyebrow">Related Jobs</p>
            <h2>More roles like this</h2>
        </div>
    </div>
    <div class="stack">
        <?php foreach ($relatedJobs as $relatedJob): ?>
            <article class="job-card related-card">
                <div>
                    <h3><a href="job.php?id=<?= (int) $relatedJob['id'] ?>"><?= e($relatedJob['title']) ?></a></h3>
                    <p class="meta-line"><?= e($relatedJob['company_name'] ?: $relatedJob['employer_name']) ?></p>
                </div>
                <div class="job-meta">
                    <span><?= e($relatedJob['location']) ?></span>
                    <span><?= e($relatedJob['category']) ?></span>
                </div>
                <div class="card-actions">
                    <a class="button-link slim" href="job.php?id=<?= (int) $relatedJob['id'] ?>">View Job</a>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
</section>
<?php renderFooter(); ?>
