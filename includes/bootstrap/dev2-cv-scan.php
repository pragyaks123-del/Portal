<?php

declare(strict_types=1);


// Dev 2 Sprint 2: Automatic CV Scan - add CV parsing fields when an older database is used.
function ensureResumeScanColumns(PDO $pdo): void
{
    $columns = [];
    $stmt = $pdo->query('SHOW COLUMNS FROM resumes');

    foreach ($stmt->fetchAll() as $column) {
        $columns[$column['Field']] = true;
    }

    $definitions = [
        'parsed_text' => 'ADD COLUMN parsed_text LONGTEXT DEFAULT NULL AFTER skills',
        'scan_status' => "ADD COLUMN scan_status ENUM('pending', 'completed', 'failed') NOT NULL DEFAULT 'pending' AFTER parsed_text",
        'scan_error' => "ADD COLUMN scan_error VARCHAR(255) NOT NULL DEFAULT '' AFTER scan_status",
        'extracted_skills' => 'ADD COLUMN extracted_skills TEXT DEFAULT NULL AFTER scan_error',
        'job_titles' => 'ADD COLUMN job_titles TEXT DEFAULT NULL AFTER extracted_skills',
        'years_experience' => 'ADD COLUMN years_experience INT NOT NULL DEFAULT 0 AFTER job_titles',
        'education' => 'ADD COLUMN education TEXT DEFAULT NULL AFTER years_experience',
        'qualifications' => 'ADD COLUMN qualifications TEXT DEFAULT NULL AFTER education',
        'scanned_at' => 'ADD COLUMN scanned_at DATETIME DEFAULT NULL AFTER qualifications',
    ];

    foreach ($definitions as $column => $definition) {
        if (!isset($columns[$column])) {
            $pdo->exec('ALTER TABLE resumes ' . $definition);
        }
    }

    $pdo->exec(
        "UPDATE resumes
         SET scan_status = 'completed',
             extracted_skills = COALESCE(extracted_skills, skills),
             scan_error = ''
         WHERE scanned_at IS NULL AND scan_status = 'pending'"
    );
}

