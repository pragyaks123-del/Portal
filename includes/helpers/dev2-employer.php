<?php

declare(strict_types=1);


function fetchEmployerProfile(int $userId): ?array
{
    // Dev 2: Employer & Job Posting - load employer company profile information.
    $stmt = db()->prepare('SELECT * FROM employer_profiles WHERE user_id = ?');
    $stmt->execute([$userId]);
    $profile = $stmt->fetch();

    return $profile ?: null;
}


function employerJobs(int $userId): array
{
    // Dev 2: Employer Dashboard - list posted jobs with applicant counts.
    $stmt = db()->prepare(
        'SELECT jobs.*,
                (SELECT COUNT(*) FROM applications WHERE applications.job_id = jobs.id) AS applicant_count
         FROM jobs
         WHERE employer_id = ?
         ORDER BY created_at DESC'
    );
    $stmt->execute([$userId]);

    return $stmt->fetchAll();
}


function applicantsForEmployer(int $employerId): array
{
    // Dev 2: Application Review - show applicants and resume details for employer decisions.
    $stmt = db()->prepare(
        'SELECT applications.*, jobs.title,
                users.name AS applicant_name, users.email AS applicant_email,
                resumes.original_name, resumes.summary, resumes.skills, resumes.stored_name
         FROM applications
         JOIN jobs ON jobs.id = applications.job_id
         JOIN users ON users.id = applications.applicant_id
         LEFT JOIN resumes ON resumes.user_id = users.id
         WHERE jobs.employer_id = ?
         ORDER BY applications.updated_at DESC'
    );
    $stmt->execute([$employerId]);

    return $stmt->fetchAll();
}

