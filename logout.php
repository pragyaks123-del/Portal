<?php

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

session_unset();
session_destroy();
session_start();
flash('success', 'You have been logged out.');
redirect('index.php');
