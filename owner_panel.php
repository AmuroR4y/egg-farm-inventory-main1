<?php 
// Include standardized session configuration FIRST
require_once 'session_config.php';

// Allow only owner role
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'owner') { 
    header("Location: login.php"); 
    exit(); 
} 

$current = basename($_SERVER['PHP_SELF']);

// Get owner unread notifications
$owner_unread_notifications = 0;
$owner_notification_result = $conn->query("SELECT COUNT(*) AS total FROM notifications WHERE user_id = " . (int)$_SESSION['user_id'] . " AND is_read = 0");
if ($owner_notification_result && ($owner_notification_row = $owner_notification_result->fetch_assoc())) {
    $owner_unread_notifications = (int)$owner_notification_row['total'];
}

// Get owner name
$owner_name = 'Owner';
if (isset($_SESSION['fullname']) && $_SESSION['fullname'] != '') {
    $owner_name = $_SESSION['fullname'];
} elseif (isset($_SESSION['name']) && $_SESSION['name'] != '') {
    $owner_name = $_SESSION['name'];
} elseif (isset($_SESSION['username']) && $_SESSION['username'] != '') {
    $owner_name = $_SESSION['username'];
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
    margin-top: 0;
    margin-bottom: 0;
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

</style>

<!-- ================================
     SIDEBAR
================================ -->

<nav class="sidebar">


<!-- LOGO -->
<div class="logo-section">
    
    <img src="/egg-farm-inventory-main1/vdvclogoo.png" class="logo-img">

    <div class="panel-title">
        Owner Panel
    </div>

</div>


<!-- MENU -->
<ul class="menu-list">

    <!-- DASHBOARD -->
    <a href="owner_dashboard.php"
    class="menu-item <?= ($current == 'owner_dashboard.php') ? 'active' : ''; ?>">

        <i class="fa-solid fa-chart-pie"></i>
        Dashboard

    </a>


    <!-- DATA ANALYTICS -->
    <a href="owner_dashboard.php?page=analytics"
    class="menu-item <?= (isset($_GET['page']) && $_GET['page'] == 'analytics') ? 'active' : ''; ?>">

        <i class="fa-solid fa-chart-line"></i>
        Data Analytics

    </a>


    <!-- DEMAND FORECASTING -->
    <a href="owner_dashboard.php?page=forecasting"
    class="menu-item <?= (isset($_GET['page']) && $_GET['page'] == 'forecasting') ? 'active' : ''; ?>">

        <i class="fa-solid fa-timeline"></i>
        Demand Forecasting

    </a>


    <!-- REPORTS -->
    <a href="owner_dashboard.php?page=reports"
    class="menu-item <?= (isset($_GET['page']) && $_GET['page'] == 'reports') ? 'active' : ''; ?>">

        <i class="fa-solid fa-file-invoice"></i>
        Reports

    </a>


    <!-- PROFILE -->
    <a href="owner_dashboard.php?page=profile"
    class="menu-item <?= (isset($_GET['page']) && $_GET['page'] == 'profile') ? 'active' : ''; ?>">

        <i class="fa-solid fa-user-gear"></i>
        Profile

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