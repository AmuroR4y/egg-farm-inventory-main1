<?php
ob_start();

// Include standardized session configuration FIRST
require_once 'session_config.php';

require_once('db.php');

/* =========================================================
   SECURITY CHECK
========================================================= */

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

if (!isset($_SESSION['role']) || $_SESSION['role'] != 'owner') {
    header("Location: login.php");
    exit();
}

/* =========================================================
   OWNER INFORMATION
========================================================= */

$owner_name = 'Owner';
$owner_unread_notifications = 0;
$owner_role = 'Owner';

$owner_notification_result = mysqli_query(
    $conn,
    "SELECT COUNT(*) AS total FROM notifications WHERE user_id = " . (int)$_SESSION['user_id'] . " AND is_read = 0"
);
if ($owner_notification_result && ($owner_notification_row = mysqli_fetch_assoc($owner_notification_result))) {
    $owner_unread_notifications = (int)$owner_notification_row['total'];
}

// Time-based greeting
$hour = date('H');
if ($hour < 12) {
    $greeting = 'Good morning';
} elseif ($hour < 18) {
    $greeting = 'Good afternoon';
} else {
    $greeting = 'Good evening';
}

if (isset($_SESSION['fullname']) && $_SESSION['fullname'] != '') {
    $owner_name = $_SESSION['fullname'];
} elseif (isset($_SESSION['name']) && $_SESSION['name'] != '') {
    $owner_name = $_SESSION['name'];
} elseif (isset($_SESSION['username']) && $_SESSION['username'] != '') {
    $owner_name = $_SESSION['username'];
}

/* =========================================================
   PAGE CONTENT
========================================================= */

$page = isset($_GET['page']) ? $_GET['page'] : 'dashboard';
?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Owner Dashboard</title>

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
    >

    <link rel="stylesheet" href="vdvc/styles.css">
    <link rel="stylesheet" href="shared_notifications.css?v=20261022">

<style>

/* =========================================================
   GLOBAL
========================================================= */

* {
    box-sizing: border-box;
}

html,
body {
    margin: 0;
    padding: 0;
    width: 100%;
    min-height: 100%;
}

body {
    min-height: 100vh;

    font-family:
        Arial,
        Helvetica,
        sans-serif;

    background: #f3f1eb;

    color: #3f4b45;
}


/* =========================================================
   MAIN CONTENT
========================================================= */

.main-content {

    margin-left: 256px;

    min-height: 100vh;

    padding: 18px;

    background:
        #f5f6f3;
}


/* =========================================================
   OWNER DASHBOARD OUTER CARD
========================================================= */

.owner-dashboard-wrapper {

    width: 100%;

    min-height:
        calc(100vh - 36px);

    overflow: hidden;

    background: #ffffff;

    border:
        1px solid #dedbd2;

    border-radius: 22px;

    box-shadow:
        0 12px 35px
        rgba(
            80,
            72,
            55,
            0.10
        );

    padding: 0;
}





.owner-dashboard-topbar {

    min-height:
        86px;

    width:
        100%;

    display:
        flex;

    align-items:
        center;

    justify-content:
        space-between;

    padding:
        0 28px;

    background:
        #f7f6f2;

    border-bottom:
        1px solid #dddcd6;

    box-sizing:
        border-box;
}


/* =========================================================
   HEADER TITLE
========================================================= */

.owner-page-title {

    margin:
        0;

    font-size:
        1.35rem;

    font-weight:
        700;

    color:
        #3f4b45;

    letter-spacing:
        -0.3px;
}


/* =========================================================
   HEADER RIGHT
========================================================= */

.owner-topbar-right {

    display:
        flex;

    align-items:
        center;

    gap:
        18px;

    height:
        100%;
}


/* =========================================================
   NOTIFICATION
========================================================= */

.owner-top-notification {

    position:
        relative;

    width:
        40px;

    height:
        40px;

    display:
        flex;

    align-items:
        center;

    justify-content:
        center;

    background:
        transparent;

    border:
        none;

    color:
        #65716b;

    font-size:
        1.2rem;

    transition:
        0.2s ease;

    cursor:
        pointer;
}


.owner-top-notification:hover {

    color:
        #527d59;
}


/* =========================================================
   NOTIFICATION BADGE
========================================================= */

.owner-notification-badge {

    position:
        absolute;

    top:
        1px;

    right:
        0;

    min-width:
        17px;

    height:
        17px;

    padding:
        0 4px;

    border-radius:
        50%;

    display:
        flex;

    align-items:
        center;

    justify-content:
        center;

    background:
        #527d59;

    color:
        #ffffff;

    font-size:
        0.58rem;

    font-weight:
        700;

    border:
        2px solid #f7f6f2;
}


/* =========================================================
   OWNER NOTIFICATION DROPDOWN
========================================================= */

.owner-notification-dropdown {
    position: absolute;
    top: calc(100% + 8px);
    right: 0;
    width: 360px;
    background: #ffffff;
    border: 1px solid #e2dfd7;
    border-radius: 10px;
    box-shadow: 0 3px 10px rgba(70, 65, 55, 0.05);
    z-index: 1000;
}

.owner-notification-dropdown.hidden {
    display: none;
}

.owner-notification-dropdown-header {
    min-height: 50px;
    padding: 0 16px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    border-bottom: 1px solid #e9e6df;
}

.owner-panel-title-group {
    display: flex;
    align-items: center;
    gap: 10px;
}

.owner-panel-title-icon {
    width: 30px;
    height: 30px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    background: #eef3ed;
    color: #527d59;
    font-size: 14px;
}

.owner-dashboard-panel-title {
    font-size: 14px;
    font-weight: 700;
    color: #3f4b45;
}

.owner-mark-all-link {
    font-size: 11px;
    font-weight: 700;
    color: #527d59;
    text-decoration: none;
    cursor: pointer;
}

.owner-mark-all-link:hover {
    text-decoration: underline;
}

.owner-notification-list {
    max-height: 400px;
    overflow-y: auto;
}

.owner-notification-item {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    padding: 12px 16px;
    border-bottom: 1px solid #f0eee8;
    cursor: pointer;
    transition: background 0.15s;
}

.owner-notification-item:hover {
    background: #faf9f7;
}

.owner-notification-item.unread {
    background: #f0f5f1;
}

.owner-notification-item.unread:hover {
    background: #e8f0e9;
}

.owner-notification-icon {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    font-size: 14px;
}

.owner-notification-icon.success {
    background: #e8f5e9;
    color: #2e7d32;
}

.owner-notification-icon.alert {
    background: #fff3e0;
    color: #e65100;
}

.owner-notification-icon.info {
    background: #e3f2fd;
    color: #1565c0;
}

.owner-notification-icon.warning {
    background: #fff8e1;
    color: #f57f17;
}

.owner-notification-content {
    flex: 1;
    min-width: 0;
}

.owner-notification-title {
    font-weight: 600;
    font-size: 13px;
    color: #1a2e1c;
    margin-bottom: 4px;
}

.owner-notification-item.unread .owner-notification-title {
    color: #214f2c;
}

.owner-notification-description {
    font-size: 12px;
    color: #5a6b60;
    line-height: 1.4;
}

.owner-notification-time {
    font-size: 11px;
    color: #9aaa9a;
    margin-top: 4px;
}

.owner-notification-empty {
    padding: 40px 20px;
    text-align: center;
    color: #9aaa9a;
}

.owner-notification-empty i {
    font-size: 32px;
    margin-bottom: 12px;
    color: #c8d4c4;
}

.owner-notification-empty p {
    font-size: 13px;
    margin: 0;
}


/* =========================================================
   HEADER DIVIDER
========================================================= */

.owner-topbar-divider {

    width:
        1px;

    height:
        42px;

    background:
        #deded8;
}


/* =========================================================
   OWNER PROFILE
========================================================= */

.owner-top-profile {

    display:
        flex;

    align-items:
        center;

    gap:
        11px;

    min-width:
        190px;

    text-decoration:
        none;

    color:
        #3f4b45;
}


/* =========================================================
   OWNER AVATAR
========================================================= */

.owner-avatar {

    width:
        54px;

    height:
        54px;

    min-width:
        54px;

    border-radius:
        50%;

    display:
        flex;

    align-items:
        center;

    justify-content:
        center;

    background:
        #e8ebe7;

    border:
        1px solid #d9ddd8;

    color:
        #527d59;

    font-size:
        1.25rem;

    box-shadow:
        0 2px 6px
        rgba(
            0,
            0,
            0,
            0.04
        );
}


/* =========================================================
   OWNER ACCOUNT DETAILS
========================================================= */

.owner-account-details {

    display:
        flex;

    flex-direction:
        column;

    justify-content:
        center;

    min-width:
        100px;
}


/* =========================================================
   OWNER NAME
========================================================= */

.owner-top-name {

    display:
        block;

    margin:
        0 0 2px 0;

    color:
        #253447;

    font-size:
        0.95rem;

    font-weight:
        600;

    line-height:
        1.2;
}

.owner-top-role {
    display: block;
    margin: 0 0 2px 0;
    color: #75859a;
    font-size: 0.75rem;
    font-weight: 500;
}


/* =========================================================
   DATE AND LIVE TIME
========================================================= */

.owner-date-time {

    display:
        flex;

    flex-direction:
        column;

    align-items:
        flex-start;

    gap:
        2px;

    margin-top:
        5px;

    white-space:
        nowrap;
}


.owner-date {

    font-size:
        0.63rem;

    color:
        #8a9590;

    line-height:
        1.2;
}


.owner-time {

    font-size:
        0.63rem;

    color:
        #527d59;

    font-weight:
        600;

    line-height:
        1.2;

    white-space:
        nowrap;
}


/* =========================================================
   DROPDOWN ICON
========================================================= */

.owner-dropdown-icon {

    width:
        28px;

    display:
        flex;

    align-items:
        center;

    justify-content:
        center;

    color:
        #65716b;

    font-size:
        0.7rem;
}

/* =========================================================
   DASHBOARD CONTENT
========================================================= */

.owner-dashboard-content {

    width:
        100%;

    padding:
        22px 28px 28px;
}

/* Styles for included dashboard content */
.owner-content-area .dashboard-page {
    margin: 0;
    padding: 0;
}

.owner-content-area .dashboard-hero {
    margin-bottom: 16px;
    padding: 4px 2px 2px;
    background: transparent;
    border-radius: 0;
}

.owner-content-area .dashboard-kpis {
    margin-bottom: 16px;
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 16px;
}

@media (max-width: 1024px) {
    .owner-content-area .dashboard-kpis {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 600px) {
    .owner-content-area .dashboard-kpis {
        grid-template-columns: 1fr;
    }
}

.owner-content-area .dashboard-grid {
    margin-bottom: 16px;
    display: grid;
    gap: 14px;
}

.owner-content-area .dashboard-main-grid {
    grid-template-columns: 1fr 1.5fr;
}

.owner-content-area .dashboard-secondary-grid {
    grid-template-columns: 1fr 1fr;
}

@media (max-width: 1024px) {
    .owner-content-area .dashboard-main-grid,
    .owner-content-area .dashboard-secondary-grid {
        grid-template-columns: 1fr;
    }
}

.owner-content-area .dashboard-card {
    background: white;
    border: 1px solid #e2dfd7;
    border-radius: 10px;
    padding: 17px 18px 15px;
    box-shadow: 0 3px 10px rgba(70,65,55,0.06);
}

.owner-content-area .analytics-section {
    margin-top: 24px;
}

/* Main Dashboard Alert and Activity Styling */
.owner-content-area .alerts-list {
    max-height: 300px;
    overflow-y: auto;
}

.owner-content-area .timeline-list {
    max-height: 300px;
    overflow-y: auto;
}

.owner-content-area .alert-item {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    padding: 12px 0;
    border-bottom: 1px solid #f5f5f5;
}

.owner-content-area .alert-item:last-child {
    border-bottom: none;
}

/* KPI Card Styling */
.owner-content-area .kpi-card {
    background: white;
    border: 1px solid #e1e8e3;
    border-radius: 12px;
    padding: 16px;
    display: flex;
    align-items: flex-start;
    gap: 12px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.04);
}

.owner-content-area .kpi-icon {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
    flex-shrink: 0;
}

.owner-content-area .kpi-content {
    flex: 1;
    min-width: 0;
}

.owner-content-area .kpi-label {
    font-size: 13px;
    color: #75859a;
    font-weight: 500;
    margin-bottom: 4px;
}

.owner-content-area .kpi-value {
    font-size: 24px;
    font-weight: 700;
    color: #253447;
    line-height: 1;
    margin-bottom: 4px;
}

.owner-content-area .kpi-change {
    font-size: 12px;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 4px;
    margin-bottom: 2px;
}

.owner-content-area .kpi-comparison {
    font-size: 11px;
    color: #75859a;
}

/* Analytics-specific styles */
.owner-content-area .analytics-kpi-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 16px;
    margin-bottom: 24px;
}

@media (max-width: 1024px) {
    .owner-content-area .analytics-kpi-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 600px) {
    .owner-content-area .analytics-kpi-grid {
        grid-template-columns: 1fr;
    }
}

.owner-content-area .analytics-kpi-card {
    background: white;
    padding: 16px;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.08);
    border: 1px solid #e2dfd7;
    min-height: 80px;
    display: flex;
    flex-direction: column;
    justify-content: center;
}

.owner-content-area .analytics-kpi-label {
    font-size: 11px;
    color: #666;
    margin-bottom: 6px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    font-weight: 600;
    word-wrap: break-word;
}

.owner-content-area .analytics-kpi-value {
    font-size: 24px;
    font-weight: bold;
    color: #214f2c;
    line-height: 1;
    word-break: break-all;
}

.owner-content-area .analytics-chart-section {
    margin: 30px 0;
}

.owner-content-area .analytics-chart-section h3 {
    margin: 0 0 20px 0;
    color: #214f2c;
    font-size: 16px;
    font-weight: 600;
}

.owner-content-area .chart-subtitle {
    margin: 0 0 15px 0;
    color: #75859a;
    font-size: 12px;
}

.owner-content-area .analytics-charts-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
}

@media (max-width: 1024px) {
    .owner-content-area .analytics-charts-grid {
        grid-template-columns: 1fr;
    }
}

.owner-content-area .inventory-summary {
    margin-bottom: 20px;
    padding: 16px;
    background: #f8f9fa;
    border-radius: 8px;
    font-size: 14px;
}

.owner-content-area .inventory-summary strong {
    color: #214f2c;
}

.owner-content-area #dashboard-analytics-loading {
    text-align: center;
    padding: 40px;
    color: #999;
    font-style: italic;
}

/* Dashboard Layout with Right Rail */
.dashboard-layout {
    display: flex;
    gap: 18px;
    align-items: flex-start;
}

.dashboard-main {
    flex: 1;
    min-width: 0;
}

.dashboard-right-rail {
    width: 322px;
    flex-shrink: 0;
    display: flex;
    flex-direction: column;
    gap: 14px;
}

@media (max-width: 1200px) {
    .dashboard-layout {
        flex-direction: column;
    }
    .dashboard-right-rail {
        width: 100%;
    }
}

/* Right Rail Cards */
.right-rail-card {
    background: white;
    border: 1px solid #e1e8e3;
    border-radius: 12px;
    padding: 16px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.04);
}

.rail-card-header {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 12px;
    padding-bottom: 12px;
    border-bottom: 1px solid #f0f0f0;
}

.rail-card-icon {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background: #e1efe7;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #194f32;
    font-size: 14px;
}

.rail-card-title {
    flex: 1;
    font-size: 14px;
    font-weight: 600;
    color: #253447;
}

.rail-card-link {
    font-size: 12px;
    color: #2f7a45;
    text-decoration: none;
    font-weight: 500;
}

.rail-card-link:hover {
    text-decoration: underline;
}

.rail-card-content {
    min-height: 100px;
}

/* Alert Items */
.alert-item {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    padding: 10px 0;
    border-bottom: 1px solid #f5f5f5;
}

.alert-item:last-child {
    border-bottom: none;
}

.alert-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    margin-top: 4px;
    flex-shrink: 0;
}

.alert-dot.warning { background: #f3a712; }
.alert-dot.info { background: #3b82f6; }
.alert-dot.success { background: #25a366; }
.alert-dot.error { background: #e0443e; }

.alert-content {
    flex: 1;
    min-width: 0;
}

.alert-title {
    font-size: 13px;
    font-weight: 500;
    color: #253447;
    margin-bottom: 2px;
}

.alert-time {
    font-size: 11px;
    color: #75859a;
}

/* Activity Items */
.activity-item {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    padding: 10px 0;
    border-bottom: 1px solid #f5f5f5;
}

.activity-item:last-child {
    border-bottom: none;
}

.activity-icon {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background: #e1efe7;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #194f32;
    font-size: 12px;
    flex-shrink: 0;
}

.activity-content {
    flex: 1;
    min-width: 0;
}

.activity-text {
    font-size: 12px;
    color: #253447;
    line-height: 1.4;
    margin-bottom: 2px;
}

.activity-time {
    font-size: 11px;
    color: #75859a;
}

/* Quick Link Buttons */
.quick-link-btn {
    width: 100%;
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 12px 16px;
    border-radius: 8px;
    font-size: 13px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s;
    margin-bottom: 8px;
    border: none;
}

.quick-link-btn:last-child {
    margin-bottom: 0;
}

.quick-link-btn.primary {
    background: #194f32;
    color: white;
}

.quick-link-btn.primary:hover {
    background: #2f7a45;
}

.quick-link-btn.secondary {
    background: white;
    color: #253447;
    border: 1px solid #e1e8e3;
}

.quick-link-btn.secondary:hover {
    background: #f5f6f3;
}

.quick-link-btn i:first-child {
    font-size: 14px;
}

.quick-link-btn span {
    flex: 1;
    text-align: left;
}

.quick-link-btn i:last-child {
    font-size: 12px;
    color: #75859a;
}

/* Dashboard Footer */
.dashboard-footer {
    display: flex;
    align-items: center;
    gap: 16px;
    padding: 14px 20px;
    background: white;
    border: 1px solid #e1e8e3;
    border-radius: 12px;
    margin-top: 24px;
}

.footer-left,
.footer-right {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 12px;
    color: #75859a;
}

.footer-left i,
.footer-right i {
    color: #194f32;
    font-size: 14px;
}

.footer-divider {
    width: 1px;
    height: 24px;
    background: #e1e8e3;
}


/* =========================================================
   DASHBOARD GREETING
========================================================= */

.dashboard-header {

    margin-bottom:
        24px;
}


.greeting-wrapper {

    display:
        flex;

    align-items:
        flex-start;

    gap:
        10px;
}


.greeting-icon {

    color:
        #315f4a;

    font-size:
        22px;

    margin-top:
        4px;
}


.greeting-text-container {

    display:
        flex;

    flex-direction:
        column;
}


.dashboard-header h1 {

    margin:
        0;

    font-family:
        Georgia,
        "Times New Roman",
        serif;

    font-size:
        25px;

    font-weight:
        700;

    color:
        #315f4a;

    letter-spacing:
        0.5px;

    line-height:
        1.1;
}


.dashboard-header h1 span {

    font-size:
        25px;
}


.dashboard-header p {

    margin:
        5px 0 0;

    font-family:
        Arial,
        Helvetica,
        sans-serif;

    font-size:
        13px;

    font-weight:
        400;

    color:
        #77817b;

    letter-spacing:
        0.2px;
}


/* Topbar Left Section */
.topbar-left {
    display: flex;
    align-items: center;
    gap: 16px;
}

.hamburger-btn {
    width: 40px;
    height: 40px;
    border-radius: 8px;
    background: #f5f6f3;
    border: none;
    color: #253447;
    font-size: 16px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
}

.greeting-section {
    display: flex;
    flex-direction: column;
}

.greeting-title {
    font-size: 18px;
    font-weight: 600;
    color: #253447;
    margin: 0;
    line-height: 1.3;
}

.greeting-subtitle {
    font-size: 13px;
    color: #75859a;
    margin: 2px 0 0 0;
    line-height: 1.4;
}

/* =========================================================
   CONTENT AREA
========================================================= */

.owner-content-area {
    padding: 0;
    min-height: 400px;
    overflow: visible;
}

.owner-content-area iframe {
    width: 100%;
    height: auto;
    min-height: 600px;
    border: none;
    display: block;
}

/* =========================================================
   RESPONSIVE
========================================================= */

@media screen and (max-width: 1100px) {

    .main-content {

        margin-left:
            256px;

        width:
            calc(100% - 256px);
    }
}

@media screen and (max-width: 900px) {

    .main-content {

        margin-left:
            0;

        width:
            100%;

        padding:
            15px;
    }

    .owner-dashboard-wrapper {

        border-radius:
            18px;
    }

    .owner-account-details,
    .owner-dropdown-icon {

        display:
            none;
    }
}

@media screen and (max-width: 600px) {

    .owner-dashboard-content {

        padding:
            18px;
    }

    .dashboard-header h1 {

        font-size:
            22px;
    }

    .owner-dashboard-topbar {

        padding:
            0 18px;
    }
}

</style>

</head>

<body>

<?php include('owner_panel.php'); ?>

<div class="main-content">

    <div class="owner-dashboard-wrapper">

        <div class="owner-dashboard-topbar">

            <div class="topbar-left">
                <button class="hamburger-btn">
                    <i class="fa-solid fa-bars"></i>
                </button>
                <div class="greeting-section">
                    <h1 class="greeting-title"><?php echo $greeting; ?>, <?php echo htmlspecialchars($owner_name); ?>!</h1>
                    <p class="greeting-subtitle">Here's what's happening with your farm today.</p>
                </div>
            </div>

            <div class="owner-topbar-right">

                <div style="position: relative;">
                    <button
                        type="button"
                        class="owner-top-notification"
                        title="Notifications"
                        onclick="event.stopPropagation(); toggleOwnerNotificationDropdown()"
                    >
                        <i class="fa-regular fa-bell"></i>

                        <?php if ($owner_unread_notifications > 0): ?>
                            <span class="owner-notification-badge">
                                <?php
                                if ($owner_unread_notifications > 9) {
                                    echo '9+';
                                } else {
                                    echo $owner_unread_notifications;
                                }
                                ?>
                            </span>
                        <?php endif; ?>
                    </button>

                    <!-- NOTIFICATION DROPDOWN -->
                    <div
                        class="owner-notification-dropdown hidden"
                        id="ownerNotificationDropdown"
                    >
                        <div class="owner-notification-dropdown-header">
                            <div class="owner-panel-title-group">
                                <div class="owner-panel-title-icon">
                                    <i class="fa-solid fa-bell"></i>
                                </div>
                                <span class="owner-dashboard-panel-title">Notifications</span>
                            </div>
                            <div class="owner-header-actions">
                                <button
                                    type="button"
                                    class="owner-view-all-header-link"
                                    id="ownerViewAllBtn"
                                    onclick="openAllNotificationsModal(event)"
                                >
                                    View All
                                </button>
                                <span class="owner-header-action-divider">|</span>
                                <a
                                    href="#"
                                    class="owner-mark-all-link"
                                    onclick="markAllNotificationsAsRead(event); return false;"
                                >
                                    Mark All as Read
                                </a>
                            </div>
                        </div>

                        <div class="owner-notification-list" id="ownerNotificationList">
                            <!-- Notifications will be loaded here via JavaScript -->
                        </div>

                        <div class="owner-notification-dropdown-footer">
                            <button
                                type="button"
                                class="owner-view-all-btn"
                                onclick="openAllNotificationsModal(event)"
                            >
                                View All Notifications
                            </button>
                        </div>
                    </div>
                </div>

                <div class="owner-topbar-divider"></div>

                <a href="owner_dashboard.php?page=profile" class="owner-top-profile">
                    <div class="owner-avatar">
                        <i class="fa-solid fa-user"></i>
                    </div>
                    <div class="owner-account-details">
                        <span class="owner-top-name"><?php echo htmlspecialchars($owner_name); ?></span>
                        <span class="owner-top-role"><?php echo htmlspecialchars($owner_role); ?></span>
                        <div class="owner-date-time">
                            <span class="owner-time" id="ownerCurrentTime"></span>
                        </div>
                    </div>
                    <div class="owner-dropdown-icon">
                        <i class="fa-solid fa-chevron-down"></i>
                    </div>
                </a>

            </div>

        </div>

        <div class="owner-dashboard-content">
            
            <?php if ($page == 'dashboard'): ?>
                <div class="owner-content-area">
                    <div class="dashboard-layout">
                        <div class="dashboard-main">
                            <?php include 'owner_dashboard_content.php'; ?>
                        </div>
                        <div class="dashboard-right-rail">
                            <!-- Alerts Card -->
                            <div class="right-rail-card">
                                <div class="rail-card-header">
                                    <div class="rail-card-icon">
                                        <i class="fa-solid fa-bell"></i>
                                    </div>
                                    <span class="rail-card-title">Alerts</span>
                                    <a href="#" class="rail-card-link">View All</a>
                                </div>
                                <div class="rail-card-content" id="right-rail-alerts">
                                    <!-- Alerts will be populated by JavaScript -->
                                </div>
                            </div>

                            <!-- Recent Activity Card -->
                            <div class="right-rail-card">
                                <div class="rail-card-header">
                                    <div class="rail-card-icon">
                                        <i class="fa-solid fa-clock-rotate-left"></i>
                                    </div>
                                    <span class="rail-card-title">Recent Activity</span>
                                    <a href="#" class="rail-card-link">View All</a>
                                </div>
                                <div class="rail-card-content" id="right-rail-activity">
                                    <!-- Activity will be populated by JavaScript -->
                                </div>
                            </div>

                            <!-- Quick Links Card -->
                            <div class="right-rail-card">
                                <div class="rail-card-header">
                                    <div class="rail-card-icon">
                                        <i class="fa-solid fa-leaf"></i>
                                    </div>
                                    <span class="rail-card-title">Quick Links</span>
                                </div>
                                <div class="rail-card-content">
                                    <button class="quick-link-btn primary" onclick="window.location.href='owner_dashboard.php?page=forecasting'">
                                        <i class="fa-solid fa-timeline"></i>
                                        <span>Demand Forecasting</span>
                                        <i class="fa-solid fa-chevron-right"></i>
                                    </button>
                                    <button class="quick-link-btn secondary" onclick="window.location.href='owner_dashboard.php?page=reports'">
                                        <i class="fa-solid fa-file-invoice"></i>
                                        <span>Reports</span>
                                        <i class="fa-solid fa-chevron-right"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Footer -->
                    <div class="dashboard-footer">
                        <div class="footer-left">
                            <i class="fa-solid fa-leaf"></i>
                            <span>Healthy Chickens • Quality Eggs • Better Tomorrow</span>
                        </div>
                        <div class="footer-divider"></div>
                        <div class="footer-right">
                            <i class="fa-solid fa-egg"></i>
                            <span>VDVC Egg Farm</span>
                        </div>
                    </div>
                </div>

            <?php elseif ($page == 'users'): ?>
                <?php
                // Redirect to dashboard since User Management has been removed
                header("Location: owner_dashboard.php?page=dashboard");
                exit();
                ?>
                
            <?php elseif ($page == 'analytics'): ?>
                <?php
                // Redirect to dashboard since analytics is now integrated
                header("Location: owner_dashboard.php?page=dashboard");
                exit();
                ?>

            <?php elseif ($page == 'forecasting'): ?>
                <div class="dashboard-header">
                    <div class="greeting-wrapper">
                        <div class="greeting-icon">
                            <i class="fa-solid fa-timeline"></i>
                        </div>
                        <div class="greeting-text-container">
                            <h1>Demand Forecasting</h1>
                            <p>View demand forecasts and inventory predictions.</p>
                        </div>
                    </div>
                </div>

                <div class="owner-content-area">
                    <iframe src="owner_dashboard_content.php?page=forecasting" style="width: 100%; min-height: 600px; border: none;"></iframe>
                </div>

            <?php elseif ($page == 'reports'): ?>
                <div class="dashboard-header">
                    <div class="greeting-wrapper">
                        <div class="greeting-icon">
                            <i class="fa-solid fa-file-invoice"></i>
                        </div>
                        <div class="greeting-text-container">
                            <h1>Reports</h1>
                            <p>Generate and view farm reports.</p>
                        </div>
                    </div>
                </div>

                <div class="owner-content-area">
                    <iframe src="owner_dashboard_content.php?page=reports" style="width: 100%; min-height: 600px; border: none;"></iframe>
                </div>

            <?php elseif ($page == 'profile'): ?>
                <div class="dashboard-header">
                    <div class="greeting-wrapper">
                        <div class="greeting-icon">
                            <i class="fa-solid fa-user-gear"></i>
                        </div>
                        <div class="greeting-text-container">
                            <h1>Profile Management</h1>
                            <p>Update your profile and security settings.</p>
                        </div>
                    </div>
                </div>

                <div class="owner-content-area">
                    <iframe src="owner_dashboard_content.php?page=profile" style="width: 100%; min-height: 600px; border: none;"></iframe>
                </div>
                
            <?php else: ?>
                <div class="dashboard-header">
                    <div class="greeting-wrapper">
                        <div class="greeting-icon">
                            <i class="fa-solid fa-chart-pie"></i>
                        </div>
                        <div class="greeting-text-container">
                            <h1>Owner Dashboard</h1>
                            <p>Welcome back, <?php echo htmlspecialchars($owner_name); ?>.</p>
                        </div>
                    </div>
                </div>
                
                <div class="owner-content-area">
                    <iframe src="owner_dashboard_content.php?page=dashboard" style="width: 100%; min-height: 600px; border: none;"></iframe>
                </div>
            <?php endif; ?>

        </div>

    </div>

</div>

<script>
// Update date and time
function updateOwnerDateTime() {
    var now = new Date();
    var dateOptions = { weekday: 'short', month: 'short', day: 'numeric' };
    var timeOptions = { hour: 'numeric', minute: '2-digit', hour12: true };
    
    var dateElement = document.getElementById('ownerCurrentDate');
    var timeElement = document.getElementById('ownerCurrentTime');
    
    if (dateElement) {
        dateElement.textContent = now.toLocaleDateString('en-US', dateOptions);
    }
    
    if (timeElement) {
        timeElement.textContent = now.toLocaleTimeString('en-US', timeOptions);
    }
}

// Update immediately and then every second
updateOwnerDateTime();
setInterval(updateOwnerDateTime, 1000);

// Notification dropdown functionality
var ownerNotificationDropdown = null;
var isOwnerDropdownOpen = false;

function toggleOwnerNotificationDropdown() {
    if (!ownerNotificationDropdown) {
        ownerNotificationDropdown = document.getElementById('ownerNotificationDropdown');
    }
    
    if (ownerNotificationDropdown) {
        isOwnerDropdownOpen = !isOwnerDropdownOpen;
        
        if (isOwnerDropdownOpen) {
            ownerNotificationDropdown.classList.remove('hidden');
            loadOwnerNotifications();
        } else {
            ownerNotificationDropdown.classList.add('hidden');
        }
    }
}

function closeOwnerNotificationDropdown() {
    if (ownerNotificationDropdown && isOwnerDropdownOpen) {
        ownerNotificationDropdown.classList.add('hidden');
        isOwnerDropdownOpen = false;
    }
}

// Close dropdown when clicking outside
document.addEventListener('click', function(event) {
    if (ownerNotificationDropdown && isOwnerDropdownOpen) {
        var notificationButton = document.querySelector('.owner-top-notification');
        var notificationContainer = ownerNotificationDropdown.parentElement;
        if (!notificationContainer.contains(event.target)) {
            closeOwnerNotificationDropdown();
        }
    }
});

function loadOwnerNotifications() {
    var notificationList = document.getElementById('ownerNotificationList');
    if (!notificationList) return;

    // Show loading state
    notificationList.innerHTML = '<div class="owner-notification-empty"><i class="fa-solid fa-spinner fa-spin"></i><p>Loading notifications...</p></div>';

    // Fetch notifications from owner_api.php JSON endpoint
    fetch('/egg-farm-inventory-main1/owner_api.php')
        .then(function(response) {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(function(data) {
            if (data.status === 'success' && data.notifications && data.notifications.length > 0) {
                var notificationsHtml = '';
                data.notifications.forEach(function(notification) {
                    notificationsHtml += buildNotificationHtml(notification);
                });
                
                var unreadCount = data.notifications.filter(function(n) { return !n.read; }).length;
                var headerMarkAll = document.querySelector('.owner-mark-all-link');
                if (headerMarkAll) {
                    if (unreadCount === 0) {
                        headerMarkAll.style.opacity = '0.5';
                        headerMarkAll.style.pointerEvents = 'none';
                    } else {
                        headerMarkAll.style.opacity = '1';
                        headerMarkAll.style.pointerEvents = 'auto';
                    }
                }

                notificationList.innerHTML = notificationsHtml;
            } else {
                notificationList.innerHTML = '<div class="owner-notification-empty"><i class="fa-regular fa-bell-slash"></i><p>No new notifications</p></div>';
            }
        })
        .catch(function() {
            notificationList.innerHTML = '<div class="owner-notification-empty"><i class="fa-solid fa-exclamation-circle"></i><p>Unable to load notifications</p></div>';
        });
}

function buildNotificationHtml(notification) {
    var isUnread = !notification.read;
    var iconClass = 'info';
    var icon = 'fa-bell';
    
    if (notification.type === 'success') {
        iconClass = 'success';
        icon = 'fa-check';
    } else if (notification.type === 'alert') {
        iconClass = 'alert';
        icon = 'fa-triangle-exclamation';
    } else if (notification.type === 'warning') {
        iconClass = 'warning';
        icon = 'fa-circle-exclamation';
    } else if (notification.type === 'info') {
        iconClass = 'info';
        icon = 'fa-circle-info';
    }
    
    var timeAgo = formatTimeAgo(notification.createdAt);
    
    var html = '<div class="owner-notification-item ' + (isUnread ? 'unread' : '') + '" onclick="handleNotificationClick(' + notification.id + ', \'' + notification.title + '\', event)">';
    html += '<div class="owner-notification-icon ' + iconClass + '"><i class="fa-solid ' + icon + '"></i></div>';
    html += '<div class="owner-notification-content">';
    html += '<div class="owner-notification-title">' + escapeHtml(notification.title) + '</div>';
    html += '<div class="owner-notification-description">' + escapeHtml(notification.message) + '</div>';
    html += '<div class="owner-notification-time">' + timeAgo + '</div>';
    html += '</div></div>';
    
    return html;
}

function formatTimeAgo(timestamp) {
    if (!timestamp) return '';
    
    var date = new Date(timestamp);
    var now = new Date();
    var seconds = Math.floor((now - date) / 1000);
    
    if (seconds < 60) {
        return 'Just now';
    } else if (seconds < 3600) {
        var minutes = Math.floor(seconds / 60);
        return minutes + ' minute' + (minutes > 1 ? 's' : '') + ' ago';
    } else if (seconds < 86400) {
        var hours = Math.floor(seconds / 3600);
        return hours + ' hour' + (hours > 1 ? 's' : '') + ' ago';
    } else if (seconds < 604800) {
        var days = Math.floor(seconds / 86400);
        return days + ' day' + (days > 1 ? 's' : '') + ' ago';
    } else {
        return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
    }
}

function escapeHtml(text) {
    if (!text) return '';
    var div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function handleNotificationClick(notificationId, title, event) {
    event.stopPropagation();
    
    // Mark notification as read
    markNotificationAsRead(notificationId, function() {
        // Navigate based on notification type
        navigateBasedOnNotification(title);
    });
}

function markNotificationAsRead(notificationId, callback) {
    var formData = new FormData();
    formData.append('notification_id', notificationId);
    
    fetch('/egg-farm-inventory-main1/owner_api.php', {
        method: 'POST',
        body: formData
    })
    .then(function(response) {
        return response.json();
    })
    .then(function(data) {
        if (data.status === 'success') {
            // Update badge
            updateBadgeCount();
            
            // Remove unread styling from the clicked notification
            var notificationItems = document.querySelectorAll('.owner-notification-item');
            notificationItems.forEach(function(item) {
                if (item.getAttribute('onclick') && item.getAttribute('onclick').includes(notificationId)) {
                    item.classList.remove('unread');
                }
            });
            
            if (callback) callback();
        }
    })
    .catch(function() {
        console.error('Failed to mark notification as read');
    });
}

function markAllNotificationsAsRead(event) {
    event.stopPropagation();
    
    var formData = new FormData();
    formData.append('mark_all_read', '1');
    
    fetch('/egg-farm-inventory-main1/owner_api.php', {
        method: 'POST',
        body: formData
    })
    .then(function(response) {
        return response.json();
    })
    .then(function(data) {
        if (data.status === 'success') {
            // Update badge to 0
            updateBadgeCount();
            
            // Remove unread styling from all notifications
            var notificationItems = document.querySelectorAll('.owner-notification-item');
            notificationItems.forEach(function(item) {
                item.classList.remove('unread');
            });
            
            // Update the header "Mark All as Read" link
            var headerMarkAll = document.querySelector('.owner-mark-all-link');
            if (headerMarkAll) {
                headerMarkAll.style.opacity = '0.5';
                headerMarkAll.style.pointerEvents = 'none';
            }
        }
    })
    .catch(function() {
        console.error('Failed to mark all notifications as read');
    });
}

function updateBadgeCount() {
    // Fetch updated unread count from server
    fetch('/egg-farm-inventory-main1/owner_api.php')
        .then(function(response) {
            return response.json();
        })
        .then(function(data) {
            if (data.status === 'success' && data.notifications) {
                var unreadCount = data.notifications.filter(function(n) { return !n.read; }).length;
                var badge = document.querySelector('.owner-notification-badge');
                
                if (unreadCount > 0) {
                    if (!badge) {
                        // Create badge if it doesn't exist
                        var bell = document.querySelector('.owner-top-notification');
                        if (bell) {
                            badge = document.createElement('span');
                            badge.className = 'owner-notification-badge';
                            bell.appendChild(badge);
                        }
                    }
                    
                    if (badge) {
                        badge.textContent = unreadCount > 9 ? '9+' : unreadCount;
                        badge.style.display = 'flex';
                    }
                } else {
                    if (badge) {
                        badge.style.display = 'none';
                    }
                }
            }
        })
        .catch(function() {
            console.error('Failed to update badge count');
        });
}

function navigateBasedOnNotification(title) {
    // Close dropdown
    closeOwnerNotificationDropdown();
    
    // Navigate based on notification title
    if (title.includes('Reservation')) {
        window.location.href = 'owner_dashboard.php?page=dashboard';
    } else if (title.includes('Inventory') || title.includes('Stock')) {
        window.location.href = 'owner_dashboard.php?page=analytics';
    } else if (title.includes('Forecast') || title.includes('Demand')) {
        window.location.href = 'owner_dashboard.php?page=forecasting';
    } else if (title.includes('Delivery') || title.includes('Report')) {
        window.location.href = 'owner_dashboard.php?page=reports';
    }
    // If no specific destination, just mark as read (no navigation)
}

// Function to update owner name in header (called from iframe)
function updateOwnerName(newName) {
    const ownerNameElements = document.querySelectorAll('.owner-top-name');
    ownerNameElements.forEach(element => {
        element.textContent = newName;
    });
}

// Update current time
function updateCurrentTime() {
    const now = new Date();
    const timeString = now.toLocaleTimeString('en-US', {
        hour: 'numeric',
        minute: '2-digit',
        hour12: true
    });
    const timeElement = document.getElementById('ownerCurrentTime');
    if (timeElement) {
        timeElement.textContent = timeString;
    }
}

// Update time immediately and then every minute
updateCurrentTime();
setInterval(updateCurrentTime, 60000);
</script>

<?php require_once 'shared_notifications_modal.php'; ?>
<script src="shared_notifications.js?v=20261022"></script>
</body>

</html>
