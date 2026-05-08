<?php

declare(strict_types=1);

$sessionDir = __DIR__ . '/../storage/sessions';
if (!is_dir($sessionDir)) {
    // Dev 1: Auth & Security - prepare the session storage used for secure login handling.
    mkdir($sessionDir, 0777, true);
}
session_save_path($sessionDir);
session_start();

// Dev 1: Auth & Security - shared application constants loaded before protected modules run.
const APP_NAME = 'JobNova';
const RESUME_DIR = __DIR__ . '/../storage/resumes';
const MAIL_LOG_DIR = __DIR__ . '/../storage/mail';

if (!is_dir(__DIR__ . '/../storage')) {
    mkdir(__DIR__ . '/../storage', 0777, true);
}

if (!is_dir(MAIL_LOG_DIR)) {
    mkdir(MAIL_LOG_DIR, 0777, true);
}

require_once __DIR__ . '/helpers.php';

$composerAutoload = __DIR__ . '/../vendor/autoload.php';
if (is_file($composerAutoload)) {
    require_once $composerAutoload;
}

require_once __DIR__ . '/cv-scanner.php';

$dbConfigFile = __DIR__ . '/db-config.php';
$GLOBALS['db_config'] = file_exists($dbConfigFile) ? require $dbConfigFile : [];

$mailConfigFile = __DIR__ . '/mail-config.php';
$GLOBALS['mail_config'] = file_exists($mailConfigFile) ? require $mailConfigFile : [];

$cvParserConfigFile = __DIR__ . '/cvparser-config.php';
$GLOBALS['cvparser_config'] = file_exists($cvParserConfigFile) ? require $cvParserConfigFile : [];

// Split developer bootstrap files: Dev 1 auth/security, Dev 2 CV scan, and shared database/layout code.
require_once __DIR__ . '/bootstrap/dev1-auth-security.php';
require_once __DIR__ . '/bootstrap/dev2-cv-scan.php';
require_once __DIR__ . '/bootstrap/shared-database-layout.php';
