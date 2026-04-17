<?php

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

$user = requireLogin();
$resume = fetchResume((int) $user['id']);
$employerProfile = $user['role'] === 'employer' ? (fetchEmployerProfile((int) $user['id']) ?? []) : [];

if (isPost()) {
    verifyCsrf();

    $action = input('action');

    if ($action === 'profile') {
        $name = input('name');
        $email = input('email');
        $bio = input('bio');
        $companyName = $user['role'] === 'employer' ? input('company_name') : '';

        db()->prepare('UPDATE users SET name = ?, email = ?, bio = ?, company_name = ? WHERE id = ?')->execute([$name, $email, $bio, $companyName, (int) $user['id']]);

        if ($user['role'] === 'employer') {
            $website = input('website');
            $industry = input('industry');
            $companySize = input('company_size');
            $location = input('location');
            $foundedYear = input('founded_year');
            $contactPhone = input('contact_phone');
            $linkedinUrl = input('linkedin_url');
            $tagline = input('tagline');
            $overview = input('overview');

            db()->prepare(
                'INSERT INTO employer_profiles (user_id, company_name, website, industry, company_size, location, founded_year, contact_phone, linkedin_url, tagline, overview)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE
                    company_name = VALUES(company_name),
                    website = VALUES(website),
                    industry = VALUES(industry),
                    company_size = VALUES(company_size),
                    location = VALUES(location),
                    founded_year = VALUES(founded_year),
                    contact_phone = VALUES(contact_phone),
                    linkedin_url = VALUES(linkedin_url),
                    tagline = VALUES(tagline),
                    overview = VALUES(overview)'
            )->execute([
                (int) $user['id'],
                $companyName,
                $website,
                $industry,
                $companySize,
                $location,
                $foundedYear,
                $contactPhone,
                $linkedinUrl,
                $tagline,
                $overview,
            ]);
        }

        flash('success', 'Profile updated successfully.');
        redirect('profile.php');
    }

    if ($action === 'resume' && $user['role'] === 'job_seeker') {
        $summary = input('summary');
        $skills = input('skills');
        $file = $_FILES['resume_file'] ?? null;

        if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
            flash('error', 'Please choose a PDF or DOC resume file.');
            redirect('profile.php');
        }

        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        if (!in_array($extension, ['pdf', 'doc', 'docx'], true)) {
            flash('error', 'Only PDF, DOC, and DOCX files are allowed.');
            redirect('profile.php');
        }

        $storedName = uniqid('resume_', true) . '.' . $extension;
        move_uploaded_file($file['tmp_name'], RESUME_DIR . '/' . $storedName);

        if ($resume) {
            if (is_file(RESUME_DIR . '/' . $resume['stored_name'])) {
                unlink(RESUME_DIR . '/' . $resume['stored_name']);
            }

            db()->prepare('UPDATE resumes SET original_name = ?, stored_name = ?, summary = ?, skills = ?, updated_at = CURRENT_TIMESTAMP WHERE user_id = ?')
                ->execute([$file['name'], $storedName, $summary, $skills, (int) $user['id']]);
        } else {
            db()->prepare('INSERT INTO resumes (user_id, original_name, stored_name, summary, skills) VALUES (?, ?, ?, ?, ?)')
                ->execute([(int) $user['id'], $file['name'], $storedName, $summary, $skills]);
        }

        flash('success', 'Resume uploaded successfully.');
        redirect('profile.php');
    }
}

$user = currentUser();
$resume = fetchResume((int) $user['id']);
$employerProfile = $user['role'] === 'employer' ? (fetchEmployerProfile((int) $user['id']) ?? []) : [];

renderHeader('Profile');
?>
<section class="content-grid">
    <div class="panel">
        <p class="eyebrow">Profile</p>
        <h1><?= $user['role'] === 'employer' ? 'Build your company presence' : 'Manage your account details' ?></h1>
        <form method="post" class="filters">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="profile">
            <?php if ($user['role'] === 'employer'): ?>
                <div class="company-profile-grid">
                    <label>
                        Contact person
                        <input type="text" name="name" value="<?= e($user['name']) ?>" required>
                    </label>
                    <label>
                        Work email
                        <input type="email" name="email" value="<?= e($user['email']) ?>" required>
                    </label>
                    <label>
                        Company name
                        <input type="text" name="company_name" value="<?= e($user['company_name']) ?>" required>
                    </label>
                    <label>
                        Website
                        <input type="url" name="website" value="<?= e($employerProfile['website'] ?? '') ?>" placeholder="https://company.com">
                    </label>
                    <label>
                        Industry
                        <input type="text" name="industry" value="<?= e($employerProfile['industry'] ?? '') ?>" placeholder="Software, Finance, Healthcare">
                    </label>
                    <label>
                        Company size
                        <select name="company_size">
                            <?php $companySize = (string) ($employerProfile['company_size'] ?? ''); ?>
                            <option value="">Select company size</option>
                            <option value="1-10" <?= $companySize === '1-10' ? 'selected' : '' ?>>1-10 employees</option>
                            <option value="11-50" <?= $companySize === '11-50' ? 'selected' : '' ?>>11-50 employees</option>
                            <option value="51-200" <?= $companySize === '51-200' ? 'selected' : '' ?>>51-200 employees</option>
                            <option value="201-500" <?= $companySize === '201-500' ? 'selected' : '' ?>>201-500 employees</option>
                            <option value="500+" <?= $companySize === '500+' ? 'selected' : '' ?>>500+ employees</option>
                        </select>
                    </label>
                    <label>
                        Headquarters
                        <input type="text" name="location" value="<?= e($employerProfile['location'] ?? '') ?>" placeholder="Kathmandu, Nepal">
                    </label>
                    <label>
                        Founded year
                        <input type="text" name="founded_year" value="<?= e($employerProfile['founded_year'] ?? '') ?>" placeholder="2018">
                    </label>
                    <label>
                        Contact phone
                        <input type="text" name="contact_phone" value="<?= e($employerProfile['contact_phone'] ?? '') ?>" placeholder="+977 98XXXXXXXX">
                    </label>
                    <label>
                        LinkedIn URL
                        <input type="url" name="linkedin_url" value="<?= e($employerProfile['linkedin_url'] ?? '') ?>" placeholder="https://linkedin.com/company/...">
                    </label>
                    <label class="span-all">
                        Company tagline
                        <input type="text" name="tagline" value="<?= e($employerProfile['tagline'] ?? '') ?>" placeholder="What makes your company stand out?">
                    </label>
                    <label class="span-all">
                        About the company
                        <textarea name="overview" placeholder="Describe your mission, culture, hiring approach, and what candidates should know."><?= e($employerProfile['overview'] ?? '') ?></textarea>
                    </label>
                    <label class="span-all">
                        Recruiter bio
                        <textarea name="bio" placeholder="Add a short intro about the hiring contact or talent team."><?= e($user['bio']) ?></textarea>
                    </label>
                </div>
            <?php else: ?>
                <label>
                    Full name
                    <input type="text" name="name" value="<?= e($user['name']) ?>" required>
                </label>
                <label>
                    Email
                    <input type="email" name="email" value="<?= e($user['email']) ?>" required>
                </label>
                <label>
                    Bio
                    <textarea name="bio"><?= e($user['bio']) ?></textarea>
                </label>
            <?php endif; ?>
            <button type="submit">Save Profile</button>
        </form>
    </div>

    <?php if ($user['role'] === 'employer'): ?>
        <div class="panel accent-panel company-preview-panel">
            <p class="eyebrow">Company Snapshot</p>
            <h2><?= e($user['company_name'] !== '' ? $user['company_name'] : 'Your company profile') ?></h2>
            <p class="company-tagline"><?= e($employerProfile['tagline'] ?? 'Add a concise company tagline to make your employer profile feel credible and complete.') ?></p>
            <div class="company-facts">
                <div class="company-fact">
                    <span>Industry</span>
                    <strong><?= e($employerProfile['industry'] ?? 'Not added yet') ?></strong>
                </div>
                <div class="company-fact">
                    <span>Size</span>
                    <strong><?= e($employerProfile['company_size'] ?? 'Not added yet') ?></strong>
                </div>
                <div class="company-fact">
                    <span>Location</span>
                    <strong><?= e($employerProfile['location'] ?? 'Not added yet') ?></strong>
                </div>
                <div class="company-fact">
                    <span>Founded</span>
                    <strong><?= e($employerProfile['founded_year'] ?? 'Not added yet') ?></strong>
                </div>
            </div>
            <div class="stack">
                <div class="card soft-card">
                    <strong>Public-facing details</strong>
                    <p class="small">Website: <?= e($employerProfile['website'] ?? 'Not added yet') ?></p>
                    <p class="small">LinkedIn: <?= e($employerProfile['linkedin_url'] ?? 'Not added yet') ?></p>
                    <p class="small">Phone: <?= e($employerProfile['contact_phone'] ?? 'Not added yet') ?></p>
                </div>
                <div class="card soft-card">
                    <strong>Company overview</strong>
                    <p><?= e($employerProfile['overview'] ?? 'Add a company overview so candidates can understand your mission, culture, and growth stage.') ?></p>
                </div>
                <div class="card soft-card">
                    <strong>Recruiter contact</strong>
                    <p class="small"><?= e($user['name']) ?><?= $user['bio'] !== '' ? ' | ' . e($user['bio']) : '' ?></p>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($user['role'] === 'job_seeker'): ?>
        <div class="panel">
            <p class="eyebrow">Resume Management</p>
            <h2>Upload or replace your resume</h2>
            <?php if ($resume): ?>
                <div class="card soft-card">
                    <strong><?= e($resume['original_name']) ?></strong>
                    <p><?= e($resume['summary']) ?></p>
                    <p class="small">Skills: <?= e($resume['skills']) ?></p>
                    <p class="small"><a class="text-link" href="resume-download.php?file=<?= urlencode($resume['stored_name']) ?>">Download current resume</a></p>
                </div>
            <?php endif; ?>
            <form method="post" enctype="multipart/form-data" class="filters">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="resume">
                <label>
                    Resume file
                    <input type="file" name="resume_file" accept=".pdf,.doc,.docx" required>
                </label>
                <label>
                    Summary
                    <textarea name="summary"><?= e($resume['summary'] ?? '') ?></textarea>
                </label>
                <label>
                    Skills
                    <input type="text" name="skills" value="<?= e($resume['skills'] ?? '') ?>" placeholder="PHP, JavaScript, UI, SQL">
                </label>
                <button type="submit">Upload Resume</button>
            </form>
        </div>
    <?php endif; ?>
</section>
<?php renderFooter(); ?>
