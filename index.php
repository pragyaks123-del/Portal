<?php

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

$featuredJobs = fetchFeaturedJobs(5);

renderHeader('Home');
?>
<section class="hero hero-home hero-home-single">
    <div class="hero-copy">
        <p class="eyebrow">Smart Career Discovery</p>
        <h1>Find Your Dream Job Today!</h1>
        <p>JobNova helps job seekers discover stronger opportunities and gives employers a polished space to present their teams, roles, and hiring process with confidence.</p>
        <p class="hero-subcopy">Browse fresh openings, explore credible company profiles, and move from search to application through a clean experience designed to feel professional at every step.</p>
        <form method="get" action="jobs.php" class="search-bar search-overlay home-hero-search">
            <label>
                Search job
                <input type="text" name="keyword" placeholder="Job title or skill">
            </label>
            <label>
                Location
                <input type="text" name="location" placeholder="Kathmandu or remote">
            </label>
            <label>
                Category
                <input type="text" name="category" placeholder="Design, Engineering">
            </label>
            <label>
                Search
                <button type="submit">Search Job</button>
            </label>
        </form>
    </div>
</section>

<section class="section-head">
    <div>
        <p class="eyebrow">Recent Jobs Available</p>
        <h2>Fresh opportunities posted by active employers</h2>
    </div>
    <a class="text-link" href="jobs.php">View all</a>
</section>

<section class="jobs-showcase">
    <?php foreach ($featuredJobs as $job): ?>
        <article class="job-card featured-card">
            <div class="job-card-top">
                <div>
                    <h3><a href="job.php?id=<?= (int) $job['id'] ?>"><?= e($job['title']) ?></a></h3>
                    <p class="meta-line"><?= e($job['company_name'] ?: $job['employer_name']) ?></p>
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
                <a class="button-link slim" href="job.php?id=<?= (int) $job['id'] ?>">Apply Now</a>
                <span class="small"><?= formatDateTime($job['created_at']) ?></span>
            </div>
        </article>
    <?php endforeach; ?>
</section>

<section class="home-value-band">
    <div class="panel">
        <p class="eyebrow">Why JobNova</p>
        <h2>A clearer hiring experience for both sides of the process</h2>
        <div class="cards-grid">
            <div class="card soft-card">
                <strong>Focused job discovery</strong>
                <p>Search by skill, location, and category to narrow down relevant roles quickly.</p>
            </div>
            <div class="card soft-card">
                <strong>Professional employer presence</strong>
                <p>Employers can present company details clearly and manage hiring activity from one workspace.</p>
            </div>
            <div class="card soft-card">
                <strong>Simplified applications</strong>
                <p>Candidates can apply, upload resumes, and keep track of their progress without friction.</p>
            </div>
            <div class="card soft-card">
                <strong>Secure account recovery</strong>
                <p>Password resets now use verification codes sent by email for a safer, cleaner recovery flow.</p>
            </div>
        </div>
    </div>
    <div class="panel accent-panel spotlight-panel">
        <p class="eyebrow">Built For Momentum</p>
        <h2>Designed to feel fast, credible, and easy to trust</h2>
        <div class="stack">
            <p>From the first search to the final application update, the experience stays consistent and easy to navigate.</p>
            <div class="spotlight-points">
                <span class="badge badge-soft">Clean job browsing</span>
                <span class="badge badge-soft">Employer-ready profiles</span>
                <span class="badge badge-soft">Straightforward applications</span>
                <span class="badge badge-soft">Secure password recovery</span>
            </div>
        </div>
        <div class="card-actions">
            <a class="button-link" href="jobs.php">Explore Jobs</a>
            <a class="button-link ghost" href="register.php">Create Account</a>
        </div>
    </div>
</section>
<?php renderFooter(); ?>
