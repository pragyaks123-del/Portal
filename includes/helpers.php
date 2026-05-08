<?php

declare(strict_types=1);

// Split developer helper files: Dev 1 security, Dev 2 employer, Dev 3 seeker, and shared helpers.
require_once __DIR__ . '/helpers/dev1-auth-security.php';
require_once __DIR__ . '/helpers/dev2-employer.php';
require_once __DIR__ . '/helpers/dev3-job-seeker.php';
require_once __DIR__ . '/helpers/shared-helpers.php';

// All Devs: Shared helper functions used by auth, employer, seeker, dashboard, and notification pages.
