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

$owner_notification_result = mysqli_query(
    $conn,
    "SELECT COUNT(*) AS total FROM notifications WHERE user_id = " . (int)$_SESSION['user_id'] . " AND is_read = 0"
);
if ($owner_notification_result && ($owner_notification_row = mysqli_fetch_assoc($owner_notification_result))) {
    $owner_unread_notifications = (int)$owner_notification_row['total'];
}

if (isset($_SESSION['name']) && $_SESSION['name'] != '') {
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

    margin-left: 260px;

    min-height: 100vh;

    padding: 18px;

    background:
        linear-gradient(
            135deg,
            #eeece6 0%,
            #f5f3ed 100%
        );
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
        0;

    color:
        #3f4b45;

    font-size:
        0.88rem;

    font-weight:
        700;

    line-height:
        1.2;
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


/* =========================================================
   CONTENT AREA
========================================================= */

.owner-content-area {
    padding: 20px;
    min-height: 400px;
}

/* =========================================================
   RESPONSIVE
========================================================= */

@media screen and (max-width: 1100px) {

    .main-content {

        margin-left:
            270px;

        width:
            calc(100% - 270px);
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

            <h1 class="owner-page-title">Owner Dashboard</h1>

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
                            <a href="owner_notifications.php" class="owner-mark-all-link">View All</a>
                        </div>

                        <div class="owner-notification-list" id="ownerNotificationList">
                            <!-- Notifications will be loaded here via JavaScript -->
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
                        <div class="owner-date-time">
                            <span class="owner-date" id="ownerCurrentDate"></span>
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
                <div class="dashboard-header">
                    <div class="greeting-wrapper">
                        <div class="greeting-icon">
                            <i class="fa-solid fa-chart-pie"></i>
                        </div>
                        <div class="greeting-text-container">
                            <h1>Owner Dashboard</h1>
                            <p>Welcome back, <?php echo htmlspecialchars($owner_name); ?>. Here's your farm overview.</p>
                        </div>
                    </div>
                </div>
                
                <div class="owner-content-area">
                    <iframe src="vdvc-dashboard.html" style="width: 100%; height: 800px; border: none;"></iframe>
                </div>
                
            <?php elseif ($page == 'users'): ?>
                <div class="dashboard-header">
                    <div class="greeting-wrapper">
                        <div class="greeting-icon">
                            <i class="fa-solid fa-users"></i>
                        </div>
                        <div class="greeting-text-container">
                            <h1>User Management</h1>
                            <p>Manage manager and customer accounts.</p>
                        </div>
                    </div>
                </div>
                
                <div class="owner-content-area">
                    <iframe src="vdvc-dashboard.html" style="width: 100%; height: 800px; border: none;"></iframe>
                </div>
                
            <?php elseif ($page == 'analytics'): ?>
                <div class="dashboard-header">
                    <div class="greeting-wrapper">
                        <div class="greeting-icon">
                            <i class="fa-solid fa-chart-line"></i>
                        </div>
                        <div class="greeting-text-container">
                            <h1>Data Analytics</h1>
                            <p>View farm performance analytics and insights.</p>
                        </div>
                    </div>
                </div>
                
                <div class="owner-content-area">
                    <iframe src="vdvc-dashboard.html" style="width: 100%; height: 800px; border: none;"></iframe>
                </div>
                
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
                    <iframe src="vdvc-dashboard.html" style="width: 100%; height: 800px; border: none;"></iframe>
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
                    <iframe src="vdvc-dashboard.html" style="width: 100%; height: 800px; border: none;"></iframe>
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
                    <iframe src="vdvc-dashboard.html" style="width: 100%; height: 800px; border: none;"></iframe>
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
                    <iframe src="vdvc-dashboard.html" style="width: 100%; height: 800px; border: none;"></iframe>
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

    // Fetch notifications from the dedicated notifications page
    fetch('owner_notifications.php')
        .then(function(response) {
            return response.text();
        })
        .then(function(html) {
            // Parse the HTML to extract notification data
            var parser = new DOMParser();
            var doc = parser.parseFromString(html, 'text/html');

            // Extract notification items from the dedicated page
            var notificationItems = doc.querySelectorAll('.notification-item');

            if (notificationItems.length > 0) {
                var notificationsHtml = '';
                notificationItems.forEach(function(item) {
                    var isUnread = item.classList.contains('unread');
                    var iconElement = item.querySelector('.notification-icon');
                    var iconClass = 'info';
                    var iconHtml = '<div class="owner-notification-icon info"><i class="fa-solid fa-bell"></i></div>';

                    if (iconElement) {
                        if (iconElement.classList.contains('success')) {
                            iconClass = 'success';
                            iconHtml = '<div class="owner-notification-icon success"><i class="fa-solid fa-check"></i></div>';
                        } else if (iconElement.classList.contains('alert')) {
                            iconClass = 'alert';
                            iconHtml = '<div class="owner-notification-icon alert"><i class="fa-solid fa-triangle-exclamation"></i></div>';
                        } else if (iconElement.classList.contains('warning')) {
                            iconClass = 'warning';
                            iconHtml = '<div class="owner-notification-icon warning"><i class="fa-solid fa-circle-exclamation"></i></div>';
                        }
                    }

                    var titleElement = item.querySelector('.notification-title');
                    var descElement = item.querySelector('.notification-desc');
                    var timeElement = item.querySelector('.notification-time');

                    var title = titleElement ? titleElement.textContent : 'Notification';
                    var description = descElement ? descElement.textContent : '';
                    var time = timeElement ? timeElement.textContent : '';

                    notificationsHtml += '<div class="owner-notification-item ' + (isUnread ? 'unread' : '') + '">';
                    notificationsHtml += iconHtml;
                    notificationsHtml += '<div class="owner-notification-content">';
                    notificationsHtml += '<div class="owner-notification-title">' + title + '</div>';
                    notificationsHtml += '<div class="owner-notification-description">' + description + '</div>';
                    notificationsHtml += '<div class="owner-notification-time">' + time + '</div>';
                    notificationsHtml += '</div></div>';
                });
                notificationList.innerHTML = notificationsHtml;
            } else {
                notificationList.innerHTML = '<div class="owner-notification-empty"><i class="fa-regular fa-bell-slash"></i><p>No notifications yet</p></div>';
            }
        })
        .catch(function() {
            notificationList.innerHTML = '<div class="owner-notification-empty"><i class="fa-solid fa-exclamation-circle"></i><p>Unable to load notifications</p></div>';
        });
}
</script>

</body>

</html>