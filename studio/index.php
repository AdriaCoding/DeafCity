<?php

// Required constants from config/config.php:
//   STUDIO_PASSWORD         — plaintext password string
//   STUDIO_SESSION_LIFETIME — session duration in seconds (default: 86400)

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/vendor/autoload.php';

use Studio\AuthGuard;

session_start();
$guard = new AuthGuard($_SESSION);

$action = $_GET['action'] ?? null;

// Logout
if ($action === 'logout') {
    $guard->logout();
    session_destroy();
    header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
    exit;
}

// Login attempt
$showError = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submitted = $_POST['password'] ?? '';
    if ($guard->login($submitted)) {
        header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
        exit;
    }
    $showError = true;
}

// Gate: show blocker if not authenticated
if (!$guard->isAuthenticated()) {
    require __DIR__ . '/views/blocker.php';
    exit;
}

// Authenticated — dispatch to Studio shell (future actions added here)
require __DIR__ . '/views/shell.php';
