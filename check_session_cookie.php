<?php
require_once 'session_config.php';

echo "Session ID: " . session_id() . "\n";
echo "Session cookie path: " . ini_get('session.cookie_path') . "\n";
echo "Session cookie domain: " . ini_get('session.cookie_domain') . "\n";
echo "Session cookie secure: " . ini_get('session.cookie_secure') . "\n";
echo "Session cookie httponly: " . ini_get('session.cookie_httponly') . "\n";
echo "Session cookie samesite: " . ini_get('session.cookie_samesite') . "\n";
echo "Session name: " . session_name() . "\n";
echo "Current script path: " . $_SERVER['SCRIPT_NAME'] . "\n";
?>
