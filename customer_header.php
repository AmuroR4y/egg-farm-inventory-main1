<?php
// Customer Header Component - Shared across all Customer pages
// Required variables before inclusion:
// - $customer_name (string)
// - $unread_notifications (int)
// - $active_page (string): 'dashboard', 'reservation', 'history', or 'profile'

if (!isset($customer_name)) {
    $customer_name = !empty($_SESSION['fullname'])
        ? $_SESSION['fullname']
        : (!empty($_SESSION['customer_name'])
            ? $_SESSION['customer_name']
            : (!empty($_SESSION['username']) ? $_SESSION['username'] : 'Customer'));
}

if (!isset($unread_notifications)) {
    $unread_notifications = 0;
    if (isset($conn) && isset($_SESSION['user_id'])) {
        $notif_res = $conn->query("SELECT COUNT(*) AS total FROM notifications WHERE user_id = " . (int)$_SESSION['user_id'] . " AND is_read = 0");
        if ($notif_res && ($row = $notif_res->fetch_assoc())) {
            $unread_notifications = (int)$row['total'];
        }
    }
}

if (!isset($active_page)) {
    $active_page = 'dashboard';
}
?>

<link rel="stylesheet" href="customer_navbar.css?v=20261022_viewall">

<!-- =========================================================
     CUSTOMER NAVBAR
========================================================= -->

<header class="customer-navbar">

    <!-- =====================================================
         LOGO
    ====================================================== -->

    <div class="customer-navbar-logo">
        <a href="customer_dashboard.php">
            <img src="vdvclogoo.png" alt="VDVC Egg Farm">
        </a>
    </div>

    <!-- =====================================================
         CENTER NAVIGATION
    ====================================================== -->

    <nav class="customer-navbar-center">
        <a href="customer_dashboard.php" class="customer-nav-link<?= ($active_page === 'dashboard') ? ' active' : ''; ?>">
            Home
        </a>
        <a href="customer_reservation.php" class="customer-nav-link<?= ($active_page === 'reservation') ? ' active' : ''; ?>">
            Reservation
        </a>
        <a href="customer_history.php" class="customer-nav-link<?= ($active_page === 'history') ? ' active' : ''; ?>">
            History
        </a>
        <a href="customer_profile.php" class="customer-nav-link<?= ($active_page === 'profile') ? ' active' : ''; ?>">
            Profile
        </a>
    </nav>

    <!-- =====================================================
         RIGHT SIDE
    ====================================================== -->

    <div class="customer-navbar-right">

        <!-- NOTIFICATION -->
        <div class="customer-notification-wrapper">
            <button
                type="button"
                class="customer-top-notification"
                title="Notifications"
                onclick="event.stopPropagation(); toggleCustomerNotificationDropdown();"
            >
                <i class="fa-regular fa-bell"></i>
                <?php if ($unread_notifications > 0): ?>
                    <span class="customer-notification-badge">
                        <?php echo $unread_notifications > 99 ? '99+' : $unread_notifications; ?>
                    </span>
                <?php endif; ?>
            </button>

            <!-- NOTIFICATION DROPDOWN -->
            <div class="customer-notification-dropdown hidden" id="customerNotificationDropdown">
                <div class="customer-notification-dropdown-header">
                    <div class="customer-panel-title-group">
                        <div class="customer-panel-title-icon">
                            <i class="fa-solid fa-bell"></i>
                        </div>
                        <div class="customer-dashboard-panel-title">
                            Notifications
                        </div>
                    </div>
                    <div class="customer-header-actions">
                        <button
                            type="button"
                            class="customer-view-all-header-link"
                            onclick="openAllNotificationsModal(event)"
                        >
                            View All
                        </button>
                        <span class="customer-header-action-divider">|</span>
                        <a class="customer-mark-all-link" onclick="markAllCustomerAsRead(event)">
                            Mark All as Read
                        </a>
                    </div>
                </div>

                <div class="customer-notification-list" id="customerNotificationList"></div>

                <!-- VIEW ALL FOOTER -->
                <div class="customer-notification-dropdown-footer">
                    <button
                        type="button"
                        class="customer-view-all-btn"
                        onclick="event.stopPropagation(); openAllNotificationsModal();"
                    >
                        View All Notifications
                    </button>
                </div>
            </div>
        </div>

        <!-- CUSTOMER ACCOUNT WITH DROPDOWN -->
        <div class="customer-account-wrapper">
            <div class="customer-account-link">
                <div class="customer-avatar">
                    <i class="fa-solid fa-user"></i>
                </div>
                <div class="customer-account-details">
                    <span class="customer-top-name">
                        <?php echo htmlspecialchars($customer_name, ENT_QUOTES, 'UTF-8'); ?>
                    </span>
                    <div class="customer-date-time">
                        <span class="customer-date" id="customer-live-date">
                            <?php echo date('F j, Y'); ?>
                        </span>
                        <span class="customer-time" id="customer-live-time">
                            <?php echo date('h:i:s A'); ?>
                        </span>
                    </div>
                </div>
            </div>

            <!-- DROPDOWN ARROW -->
            <button
                type="button"
                class="customer-account-toggle"
                id="customerAccountToggle"
                aria-label="Open account menu"
                aria-expanded="false"
                aria-controls="customerAccountDropdown"
                onclick="toggleCustomerAccountDropdown(event)"
            >
                <i class="fa-solid fa-chevron-down"></i>
            </button>

            <!-- ACCOUNT DROPDOWN -->
            <div class="customer-account-dropdown hidden" id="customerAccountDropdown">
                <button
                    type="button"
                    class="customer-logout-option"
                    onclick="openCustomerLogoutModal()"
                >
                    <i class="fa-solid fa-right-from-bracket"></i>
                    <span>Logout</span>
                </button>
            </div>
        </div>

    </div>

</header>
