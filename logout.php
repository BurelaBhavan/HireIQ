<?php
/**
 * Logout Handler
 * AI Interview Assessment Platform
 */

declare(strict_types=1);
require_once __DIR__ . '/config/app.php';

logoutUser();   // Destroys session and redirects to /login.php
