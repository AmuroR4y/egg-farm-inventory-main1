<?php
/**
 * Email Configuration
 * Loads Brevo SMTP credentials from .env file
 * This file should be included in any script that sends email
 */

// Prevent direct access
if (!defined('APP_ACCESS')) {
    define('APP_ACCESS', true);
}

// Load environment variables from .env file
function loadEnv($file) {
    if (!file_exists($file)) {
        return false;
    }

    $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        // Skip comments
        if (strpos(trim($line), '#') === 0) {
            continue;
        }

        // Parse KEY=VALUE
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);

            // Remove quotes if present
            if (preg_match('/^(["\'])(.*)\1$/', $value, $matches)) {
                $value = $matches[2];
            }

            // Set environment variable
            putenv("$key=$value");
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }
    }

    return true;
}

// Load .env file
$envFile = __DIR__ . '/.env';
loadEnv($envFile);

// Define constants for email configuration
define('BREVO_SMTP_HOST', getenv('BREVO_SMTP_HOST') ?: 'smtp-relay.brevo.com');
define('BREVO_SMTP_PORT', getenv('BREVO_SMTP_PORT') ?: '587');
define('BREVO_SMTP_USERNAME', getenv('BREVO_SMTP_USERNAME') ?: '');
define('BREVO_SMTP_PASSWORD', getenv('BREVO_SMTP_PASSWORD') ?: '');
define('BREVO_FROM_EMAIL', getenv('BREVO_FROM_EMAIL') ?: 'noreply@yourdomain.com');
define('BREVO_FROM_NAME', getenv('BREVO_FROM_NAME') ?: 'VDVC Egg Farm');

// Validate required fields (only check if constants are not empty strings)
if (BREVO_SMTP_USERNAME === '' || BREVO_SMTP_PASSWORD === '') {
    // In production, you might want to log this or throw an error
    // For now, we'll allow the code to continue but email sending will fail
    error_log('Warning: Brevo SMTP credentials not configured in .env file');
}
?>
