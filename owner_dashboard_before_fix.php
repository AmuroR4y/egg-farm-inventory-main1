<?php
require_once 'session_config.php';
require 'db.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'owner') {
    header("Location: login.php");
    exit();
}

// Get owner unread notifications
$owner_unread_notifications = 0;
if (isset($_SESSION['user_id'])) {
    $owner_notification_result = $conn->query("SELECT COUNT(*) AS total FROM notifications WHERE user_id = " . (int)$_SESSION['user_id'] . " AND is_read = 0");
    if ($owner_notification_result && ($owner_notification_row = $owner_notification_result->fetch_assoc())) {
        $owner_unread_notifications = (int)$owner_notification_row['total'];
    }
}

require __DIR__ . '/vdvc-dashboard.html';
