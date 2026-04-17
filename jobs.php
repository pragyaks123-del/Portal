<?php

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

$user = currentUser();
$filters = buildJobFilters();
$jobs = fetchJobs($filters, 0, $user ? (int) $user['id'] : null);

renderHeader('Jobs');
?>
<section class="page-hero compact-hero">
    <p class="eyebrow">Jobs</p>
    <h1>Search roles by keyword, location, category, salary, and type.</h1>
    <p>Save favourites, narrow results, and jump straight into application flows from a cleaner listing view.</p>
</section>

<section class="listing-layout">
    <aside class="panel filter-panel">
        <form method="get" class="filters">
            <label>
                Keyword
                <input type="text" name="keyword" value="<?= e($filters['keyword']) ?>" placeholder="Developer, designer">
            </label>
            <label>
                Location
                <input type="text" name="location" value="<?= e($filters['location']) ?>" placeholder="Kathmandu">
            </label>
            <label>
                Category
                <input type="text" name="category" value="<?= e($filters['category']) ?>" placeholder="Engineering">
            </label>
            <label>
                Job Type
                <select name="job_type">
                    <option value="">Any type</option>
                    <option value="Full-time" <?= $filters['job_type'] === 'Full-time' ? 'selected' : '' ?>>Full-time</option>
                    <option value="Contract" <?= $filters['job_type'] === 'Contract' ? 'selected' : '' ?>>Contract</option>
                    <option value="Part-time" <?= $filters['job_type'] === 'Part-time' ? 'selected' : '' ?>>Part-time</option>
                </select>
            </label>
            <label>
                Minimum salary
                <input type="range" min="0" max="150000" step="5000" name="salary_min" value="<?= (int) $filters['salary_min'] ?>" data-range-label="salary-value">
                <span class="range-value" id="salary-value"><?= $filters['salary_min'] > 0 ? formatMoney((int) $filters['salary_min']) : 'Any salary' ?></span>
            </label>
            <?php if ($user && $user['role'] === 'job_seeker'): ?>
                <label class="checkbox">
                    <input type="checkbox" name="saved" value="1" <?= $filters['saved'] === '1' ? 'checked' : '' ?>>
                    Show only saved jobs
                </label>
            <?php endif; ?>
            <button type="submit">Apply Filters</button>
            <a class="button-link ghost full-width" href="jobs.php">Reset</a>
        </form>
    </aside>

    <div class="stack">
        <?php if (!$jobs): ?>
            <div class="panel empty">No jobs matched these filters yet. Try broadening the search or removing one filter.</div>
        <?php endif; ?>
        <?php foreach ($jobs as $job): ?>
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
                <div class="card-actions">
                    <a class="button-link slim" href="job.php?id=<?= (int) $job['id'] ?>">View Details</a>
                    <?php if ($user && $user['role'] === 'job_seeker'): ?>
                        <form method="post" action="toggle-save-job.php" class="inline-form">
                            <?= csrfField() ?>
                            <input type="hidden" name="job_id" value="<?= (int) $job['id'] ?>">
                            <input type="hidden" name="return_to" value="jobs.php?<?= e(http_build_query(array_filter($filters, fn ($value) => $value !== '' && $value !== 0))) ?>">
                            <button class="button-link ghost slim" type="submit"><?= ((int) $job['is_saved']) === 1 ? 'Saved' : 'Save Job' ?></button>
                        </form>
                    <?php endif; ?>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
</section>
<?php renderFooter(); ?>
