<?php
/**
 * Standardized Session Configuration
 * Include this file at the very beginning of any page that needs sessions
 * Must be included BEFORE any output (including whitespace)
 */

// Prevent duplicate configuration
if (defined('SESSION_CONFIGURED')) {
    return;
}
define('SESSION_CONFIGURED', true);

// Calculate the correct cookie path based on the current script location
// This ensures the session cookie works across all pages in the project
$scriptPath = $_SERVER['SCRIPT_NAME'] ?? '/';
$projectPath = '/';

// Detect if we're in a subdirectory
if (preg_match('#^(/[^/]+)/#', $scriptPath, $matches)) {
    $projectPath = $matches[1] . '/';
}

// Configure session cookie parameters BEFORE starting session
// These settings ensure the session persists across all pages
ini_set('session.use_only_cookies', '1');
ini_set('session.use_trans_sid', '0');
ini_set('session.cookie_lifetime', '0');  // Session cookie (expires when browser closes)
ini_set('session.cookie_path', $projectPath);
ini_set('session.cookie_domain', '');  // Current domain only
ini_set('session.cookie_secure', '0');  // Allow HTTP (set to 1 for HTTPS only)
ini_set('session.cookie_httponly', '1');  // Prevent JavaScript access
ini_set('session.cookie_samesite', 'Lax');  // CSRF protection
ini_set('session.gc_maxlifetime', '3600');  // 1 hour server-side

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Debug logging (remove in production)
// error_log("Session configured. Path: $projectPath, Session ID: " . session_id());
