<?php 
// Include standardized session configuration FIRST
require_once 'session_config.php';

// Allow both manager and owner roles
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['manager', 'owner'])) { 
    header("Location: login.php"); 
    exit(); 
} 
 
$current = basename($_SERVER['PHP_SELF']);

// Unread notification count for sidebar badge
$_panel_unread_notif = 0;
if (isset($_SESSION['user_id'])) {
    require_once 'db.php';
    $_panel_uid = (int)$_SESSION['user_id'];
    $_panel_res = mysqli_query($conn,
        "SELECT COUNT(*) AS total FROM notifications WHERE user_id = $_panel_uid AND is_read = 0"
    );
    if ($_panel_res && ($r = mysqli_fetch_assoc($_panel_res))) {
        $_panel_unread_notif = (int)$r['total'];
    }
}
?> 

<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<style>

/* ================================
   GLOBAL
================================ */
* {
    box-sizing: border-box;
    font-family: 'Poppins', sans-serif;
}


/* ================================
   SIDEBAR
================================ */
.sidebar {
    width: 260px;
    height: 100vh;
    background: #214f2c;
    display: flex;
    flex-direction: column;
    padding: 30px 15px;
    border-right: 1px solid #183d21;
    position: fixed;
    left: 0;
    top: 0;
    overflow: hidden;
}


/* ================================
   LOGO SECTION
================================ */
.logo-section {
    text-align: center;
    margin-bottom: 35px;
    padding-bottom: 20px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.18);
}

.logo-img {
    width: 200px;
    display: block;
    margin: auto;
}

.panel-title {
    color: #ffffff;
    font-size: 15px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-top: 5px;
}


/* ================================
   MENU LIST
================================ */
.menu-list {
    list-style: none;
    display: flex;
    flex-direction: column;
    gap: 8px;
    flex: 1;
    padding: 0;
    margin: 0;
}


/* ================================
   MENU ITEMS
================================ */
.menu-item {
    display: flex;
    align-items: center;
    gap: 15px;
    padding: 13px 20px;
    text-decoration: none;
    
    /* Soft white text */
    color: #e8f1e9;
    
    border-radius: 10px;
    font-size: 14px;
    font-weight: 500;
    transition: all 0.25s ease;
}


/* ================================
   MENU ICONS
================================ */
.menu-item i {
    width: 25px;
    text-align: center;
    font-size: 18px;
    
    /* Light green icon */
    color: #b8d8b0;
    
    transition: all 0.25s ease;
}


/* ================================
   HOVER EFFECT
================================ */
.menu-item:hover {
    background: #356b42;
    color: #ffffff;
    transform: translateX(4px);
}

.menu-item:hover i {
    color: #dff3d8;
}


/* ================================
   ACTIVE MENU
================================ */
.menu-item.active {
    background: #ffffff;
    color: #214f2c;
    font-weight: 600;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.12);
}

.menu-item.active i {
    color: #214f2c;
}


/* ================================
   LOGOUT SECTION
================================ */
.logout-section {
    margin-top: auto;
    margin-bottom: 190px;
    position: relative;
    z-index: 2;
}


/* LOGOUT BUTTON */
.btn-logout {
    color: #ffd0d0;
}

.btn-logout i {
    color: #ffaaaa;
}

.btn-logout:hover {
    background: rgba(229, 62, 62, 0.18);
    color: #ffffff;
}

.btn-logout:hover i {
    color: #ffb4b4;
}


/* ================================
   MAIN CONTENT
================================ */
.main-content {
    margin-left: 260px;
    padding: 30px;
}

/* ================================
   SIDEBAR NOTIFICATION BADGE
================================ */
.notif-count {
    margin-left: auto;
    background: #e74c3c;
    color: #fff;
    font-size: 10px;
    font-weight: 700;
    min-width: 18px;
    height: 18px;
    border-radius: 9px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 0 5px;
}

</style>

<!-- ================================
     SIDEBAR
================================ -->

<nav class="sidebar">


<!-- LOGO -->
<div class="logo-section">
    
    <img src="/egg-farm-inventory-main1/vdvclogoo.png" class="logo-img">

    <div class="panel-title">
        Manager Panel
    </div>

</div>


<!-- MENU -->
<ul class="menu-list">

    <!-- DASHBOARD -->
    <a href="manager_dashboard.php"
    class="menu-item <?= ($current == 'manager_dashboard.php') ? 'active' : ''; ?>">

        <i class="fa-solid fa-chart-pie"></i>
        Dashboard

    </a>


    <!-- INVENTORY -->
    <a href="inventory.php"
    class="menu-item <?= ($current == 'inventory.php') ? 'active' : ''; ?>">

        <i class="fa-solid fa-boxes-stacked"></i>
        Inventory Management

    </a>


    <!-- RESERVATION -->
    <a href="manager_reservation.php"
    class="menu-item <?= ($current == 'manager_reservation.php') ? 'active' : ''; ?>">

        <i class="fa-solid fa-calendar-check"></i>
        Reservation Management

    </a>


    <!-- DELIVERY -->
    <a href="manager_delivery.php"
    class="menu-item <?= ($current == 'manager_delivery.php') ? 'active' : ''; ?>">

        <i class="fa-solid fa-truck-ramp-box"></i>
        Delivery Management

    </a>


    <!-- PROFILE -->
    <a href="manager_profile.php"
    class="menu-item <?= ($current == 'manager_profile.php') ? 'active' : ''; ?>">

        <i class="fa-solid fa-user-gear"></i>
        Profile Management

    </a>


    <!-- REPORTS -->
    <a href="manager_reports.php"
    class="menu-item <?= ($current == 'manager_reports.php') ? 'active' : ''; ?>">

        <i class="fa-solid fa-file-invoice-dollar"></i>
        Reports

    </a>


    <!-- NOTIFICATIONS -->
    <a href="manager_notifications.php"
    class="menu-item <?= ($current == 'manager_notifications.php') ? 'active' : ''; ?>">

        <i class="fa-solid fa-bell"></i>
        Notifications
        <?php if ($_panel_unread_notif > 0): ?>
            <span class="notif-count"><?= $_panel_unread_notif > 9 ? '9+' : $_panel_unread_notif; ?></span>
        <?php endif; ?>

    </a>

    <!-- LOGOUT -->
    <div class="logout-section">

        <a href="logout.php" class="menu-item btn-logout">

            <i class="fa-solid fa-right-from-bracket"></i>
            Log Out

        </a>

    </div>

</ul>


</nav>
