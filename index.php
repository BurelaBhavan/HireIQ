<?php
/**
 * Root index — redirect to login
 * AI Interview Assessment Platform
 */
declare(strict_types=1);
require_once __DIR__ . '/config/app.php';
redirect(BASE_URL . '/login.php');
