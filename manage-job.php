<?php

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

$user = requireRole('employer');
$jobId = inputInt('id');
$job = $jobId > 0 ? fetchJobById($jobId) : null;

if ($job && (int) $job['employer_id'] !== (int) $user['id']) {
    flash('error', 'You can only edit your own jobs.');
    redirect('dashboard.php');
}

if (isPost()) {
    verifyCsrf();

    $title = input('title');
    $salaryMin = inputInt('salary_min');
    $salaryMax = inputInt('salary_max');
    $skills = input('skills');
    $location = input('location');
    $category = input('category');
    $jobType = input('job_type');
    $description = input('description');

    if ($title === '' || $skills === '' || $location === '' || $category === '' || $jobType === '' || $description === '') {
        flash('error', 'Please complete all job fields.');
    } else {
        if ($job) {
            db()->prepare('UPDATE jobs SET title = ?, salary_min = ?, salary_max = ?, skills = ?, location = ?, category = ?, job_type = ?, description = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?')
                ->execute([$title, $salaryMin, $salaryMax, $skills, $location, $category, $jobType, $description, (int) $job['id']]);
            flash('success', 'Job updated successfully.');
        } else {
            db()->prepare('INSERT INTO jobs (employer_id, title, category, location, salary_min, salary_max, job_type, skills, description) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)')
                ->execute([(int) $user['id'], $title, $category, $location, $salaryMin, $salaryMax, $jobType, $skills, $description]);
            $newJobId = (int) db()->lastInsertId();
            $seekers = db()->query("SELECT id FROM users WHERE role = 'job_seeker'")->fetchAll();
            foreach ($seekers as $seeker) {
                addNotification((int) $seeker['id'], 'A new job was posted: ' . $title, 'job.php?id=' . $newJobId);
            }
            flash('success', 'Job posted successfully.');
        }

        redirect('dashboard.php');
    }
}

renderHeader($job ? 'Edit Job' : 'Create Job');
?>
<section class="auth-shell wide-shell">
    <div class="panel auth-panel wide-panel">
        <p class="eyebrow"><?= $job ? 'Edit Job' : 'Create Job Posting' ?></p>
        <h1><?= $job ? 'Update your listing' : 'Publish a new opportunity' ?></h1>
        <form method="post" class="filters two-col">
            <?= csrfField() ?>
            <label>
                Job title
                <input type="text" name="title" value="<?= e($job['title'] ?? input('title')) ?>" required>
            </label>
            <label>
                Category
                <input type="text" name="category" value="<?= e($job['category'] ?? input('category')) ?>" required>
            </label>
            <label>
                Location
                <input type="text" name="location" value="<?= e($job['location'] ?? input('location')) ?>" required>
            </label>
            <label>
                Job type
                <select name="job_type">
                    <?php $selectedType = $job['job_type'] ?? input('job_type'); ?>
                    <option value="Full-time" <?= $selectedType === 'Full-time' ? 'selected' : '' ?>>Full-time</option>
                    <option value="Contract" <?= $selectedType === 'Contract' ? 'selected' : '' ?>>Contract</option>
                    <option value="Part-time" <?= $selectedType === 'Part-time' ? 'selected' : '' ?>>Part-time</option>
                </select>
            </label>
            <label>
                Salary minimum
                <input type="number" name="salary_min" value="<?= e((string) ($job['salary_min'] ?? input('salary_min'))) ?>" required>
            </label>
            <label>
                Salary maximum
                <input type="number" name="salary_max" value="<?= e((string) ($job['salary_max'] ?? input('salary_max'))) ?>" required>
            </label>
            <label class="span-all">
                Skills
                <input type="text" name="skills" value="<?= e($job['skills'] ?? input('skills')) ?>" placeholder="PHP, SQL, Design, Communication" required>
            </label>
            <label class="span-all">
                Description
                <textarea name="description" required><?= e($job['description'] ?? input('description')) ?></textarea>
            </label>
            <div class="span-all card-actions">
                <button type="submit"><?= $job ? 'Save Changes' : 'Publish Job' ?></button>
                <a class="button-link ghost" href="dashboard.php">Cancel</a>
            </div>
        </form>
    </div>
</section>
<?php renderFooter(); ?>
