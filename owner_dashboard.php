<?php
// =========================================================
// OWNER DASHBOARD - VDVC Enterprise Dashboard Integration
// Complete Analytics & Management Interface for Owner
// =========================================================

ob_start();

// Include standardized session configuration FIRST
require_once 'session_config.php';

include 'db.php';

/* =========================================================
   SECURITY CHECK - OWNER ONLY
========================================================= */
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'owner') {
    error_log("Owner Dashboard Access Denied. Session Data: " . print_r($_SESSION, true));
    header("Location: login.php");
    exit();
}

$owner_name = isset($_SESSION['fullname']) ? $_SESSION['fullname'] : 'Owner';

/* =========================================================
   DATE SETTINGS
========================================================= */
$current_month_start = date('Y-m-01');
$current_month_end = date('Y-m-t');
$today = date('Y-m-d');
$last_7_days_start = date('Y-m-d', strtotime('-6 days'));

/* =========================================================
   DASHBOARD METRICS - Comprehensive Data Collection
========================================================= */

// Total Customers
$total_customers = 0;
$customer_query = mysqli_query($conn, "SELECT COUNT(*) as total FROM users WHERE role = 'customer' AND status = 'active'");
if ($customer_query && $row = mysqli_fetch_assoc($customer_query)) {
    $total_customers = $row['total'];
}

// Total Reservations (This Month)
$monthly_reservations = 0;
$res_query = mysqli_query($conn, "SELECT COUNT(*) as total FROM reservations WHERE DATE(created_at) BETWEEN '$current_month_start' AND '$current_month_end'");
if ($res_query && $row = mysqli_fetch_assoc($res_query)) {
    $monthly_reservations = $row['total'];
}

// Total Egg Inventory
$total_egg_stock = 0;
$egg_query = mysqli_query($conn, "SELECT SUM(current_stock) as total FROM egg_inventory");
if ($egg_query && $row = mysqli_fetch_assoc($egg_query)) {
    $total_egg_stock = $row['total'] ? $row['total'] : 0;
}

// Pending Reservations
$pending_reservations = 0;
$pending_query = mysqli_query($conn, "SELECT COUNT(*) as total FROM reservations WHERE status = 'Pending'");
if ($pending_query && $row = mysqli_fetch_assoc($pending_query)) {
    $pending_reservations = $row['total'];
}

// Total Managers
$total_managers = 0;
$manager_query = mysqli_query($conn, "SELECT COUNT(*) as total FROM users WHERE role = 'manager' AND status = 'active'");
if ($manager_query && $row = mysqli_fetch_assoc($manager_query)) {
    $total_managers = $row['total'];
}

// Today's Deliveries
$today_deliveries = 0;
$delivery_query = mysqli_query($conn, "SELECT COUNT(*) as total FROM reservations WHERE reservation_date = '$today' AND delivery_method = 'Delivery'");
if ($delivery_query && $row = mysqli_fetch_assoc($delivery_query)) {
    $today_deliveries = $row['total'];
}

// Low Stock Alerts
$low_stock_items = array();
$low_stock_query = mysqli_query($conn, "SELECT egg_size, current_stock FROM egg_inventory WHERE current_stock < 100 ORDER BY current_stock ASC LIMIT 5");
if ($low_stock_query) {
    while ($row = mysqli_fetch_assoc($low_stock_query)) {
        $low_stock_items[] = $row;
    }
}

// Recent Activity
$recent_activities = array();
$activity_query = mysqli_query($conn, "SELECT r.*, u.fullname as customer_name FROM reservations r LEFT JOIN users u ON r.user_id = u.id ORDER BY r.created_at DESC LIMIT 10");
if ($activity_query) {
    while ($row = mysqli_fetch_assoc($activity_query)) {
        $recent_activities[] = $row;
    }
}

// Chart Data - Last 7 Days Reservations
$chart_labels = array();
$reservation_chart_data = array();
$delivery_chart_data = array();

for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $chart_labels[] = date('M d', strtotime($date));
    
    // Reservations count
    $count_query = mysqli_query($conn, "SELECT COUNT(*) as total FROM reservations WHERE DATE(created_at) = '$date'");
    if ($count_query && $row = mysqli_fetch_assoc($count_query)) {
        $reservation_chart_data[] = (int)$row['total'];
    } else {
        $reservation_chart_data[] = 0;
    }
    
    // Deliveries count
    $delivery_count_query = mysqli_query($conn, "SELECT COUNT(*) as total FROM reservations WHERE reservation_date = '$date' AND delivery_method = 'Delivery'");
    if ($delivery_count_query && $row = mysqli_fetch_assoc($delivery_count_query)) {
        $delivery_chart_data[] = (int)$row['total'];
    } else {
        $delivery_chart_data[] = 0;
    }
}

// Egg Inventory by Size for Product Mix
$egg_inventory_by_size = array();
$egg_size_query = mysqli_query($conn, "SELECT egg_size, current_stock FROM egg_inventory ORDER BY current_stock DESC");
if ($egg_size_query) {
    while ($row = mysqli_fetch_assoc($egg_size_query)) {
        $egg_inventory_by_size[] = $row;
    }
}

// Total reserved eggs
$total_reserved = 0;
$reserved_query = mysqli_query($conn, "SELECT SUM(quantity) as total FROM reservations WHERE status IN ('Pending', 'Confirmed')");
if ($reserved_query && $row = mysqli_fetch_assoc($reserved_query)) {
    $total_reserved = $row['total'] ? $row['total'] : 0;
}

// Calculate available stock
$available_stock = $total_egg_stock - $total_reserved;

?>

<!-- HTML CONTENT -->
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Owner Dashboard - VDVC Egg Farm</title>
    
    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    
    <style>
        /* =========================================================
           BASE STYLES - Matching Existing Project
        ========================================================= */
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            background: #f3f1eb;
            color: #3f4b45;
            min-height: 100vh;
        }
        
        /* =========================================================
           SIDEBAR - Matching Existing Project
        ========================================================= */
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
            z-index: 100;
        }
        
        .logo-section {
            text-align: center;
            margin-bottom: 35px;
            padding-bottom: 20px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.18);
        }
        
        .logo-img {
            width: 180px;
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
        
        .menu-list {
            list-style: none;
            display: flex;
            flex-direction: column;
            gap: 8px;
            flex: 1;
            padding: 0;
            margin: 0;
        }
        
        .menu-item {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 13px 20px;
            text-decoration: none;
            color: #e8f1e9;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.25s ease;
            cursor: pointer;
            border: none;
            background: transparent;
            width: 100%;
            text-align: left;
        }
        
        .menu-item i {
            width: 25px;
            text-align: center;
            font-size: 18px;
            color: #b8d8b0;
            transition: all 0.25s ease;
        }
        
        .menu-item:hover {
            background: #356b42;
            color: #ffffff;
            transform: translateX(4px);
        }
        
        .menu-item:hover i {
            color: #dff3d8;
        }
        
        .menu-item.active {
            background: #ffffff;
            color: #214f2c;
            font-weight: 600;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.12);
        }
        
        .menu-item.active i {
            color: #214f2c;
        }
        
        .logout-section {
            margin-top: auto;
            margin-bottom: 20px;
        }
        
        .btn-logout {
            color: #ffd0d0 !important;
        }
        
        .btn-logout i {
            color: #ffaaaa !important;
        }
        
        .btn-logout:hover {
            background: rgba(229, 62, 62, 0.18) !important;
            color: #ffffff !important;
        }
        
        .btn-logout:hover i {
            color: #ffb4b4 !important;
        }
        
        /* =========================================================
           MAIN CONTENT
        ========================================================= */
        .main-content {
            margin-left: 260px;
            padding: 30px;
            min-height: 100vh;
        }
        
        .dashboard-header {
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }
        
        .dashboard-header h1 {
            font-size: 28px;
            font-weight: 700;
            color: #214f2c;
            margin: 0 0 8px 0;
        }
        
        .welcome-text {
            color: #59635c;
            font-size: 15px;
        }
        
        .live-status {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 13px;
            color: #527d59;
            font-weight: 500;
        }
        
        .live-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #27ae60;
            box-shadow: 0 0 0 3px rgba(39, 174, 96, 0.2);
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
        
        /* =========================================================
           STATS GRID
        ========================================================= */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: #fff;
            border: 1px solid #e2dfd7;
            border-radius: 12px;
            padding: 20px;
            display: flex;
            align-items: center;
            gap: 16px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
            transition: all 0.2s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 16px rgba(0,0,0,0.08);
        }
        
        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
        }
        
        .stat-icon.customers { background: #e8f5e9; color: #2e7d32; }
        .stat-icon.reservations { background: #e3f2fd; color: #1565c0; }
        .stat-icon.eggs { background: #fff3e0; color: #e65100; }
        .stat-icon.pending { background: #ffebee; color: #c62828; }
        .stat-icon.managers { background: #f3e5f5; color: #6a1b9a; }
        .stat-icon.deliveries { background: #e0f2f1; color: #00695c; }
        
        .stat-content h3 {
            font-size: 24px;
            font-weight: 700;
            color: #1a1a1a;
            margin: 0 0 4px 0;
        }
        
        .stat-content p {
            font-size: 13px;
            color: #666;
            margin: 0;
        }
        
        /* =========================================================
           DASHBOARD GRID
        ========================================================= */
        .dashboard-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .dashboard-grid.three-columns {
            grid-template-columns: repeat(3, 1fr);
        }
        
        .dashboard-card {
            background: #fff;
            border: 1px solid #e2dfd7;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
            overflow: hidden;
        }
        
        .card-header {
            padding: 16px 20px;
            background: #f9f8f5;
            border-bottom: 1px solid #e8e5dc;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .card-header h3 {
            font-size: 16px;
            font-weight: 600;
            color: #214f2c;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .card-body {
            padding: 20px;
        }
        
        .view-all {
            font-size: 13px;
            color: #527d59;
            text-decoration: none;
            font-weight: 500;
        }
        
        .view-all:hover { 
            text-decoration: underline; 
        }
        
        /* =========================================================
           ACTIVITY LIST
        ========================================================= */
        .activity-list {
            max-height: 400px;
            overflow-y: auto;
        }
        
        .activity-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 14px 20px;
            border-bottom: 1px solid #f0eee8;
            transition: background 0.15s;
        }
        
        .activity-item:hover { 
            background: #faf9f7; 
        }
        
        .activity-item:last-child { 
            border-bottom: none; 
        }
        
        .activity-icon {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            flex-shrink: 0;
        }
        
        .activity-icon.new { background: #e8f5e9; color: #2e7d32; }
        .activity-icon.confirmed { background: #e3f2fd; color: #1565c0; }
        .activity-icon.completed { background: #e8eaf6; color: #3f51b5; }
        .activity-icon.cancelled { background: #ffebee; color: #c62828; }
        .activity-icon.pending { background: #fff3e0; color: #e65100; }
        
        .activity-content { 
            flex: 1; 
            min-width: 0; 
        }
        
        .activity-title {
            font-size: 14px;
            font-weight: 500;
            color: #1a1a1a;
            margin-bottom: 2px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .activity-meta {
            font-size: 12px;
            color: #888;
        }
        
        .activity-time {
            font-size: 12px;
            color: #999;
            flex-shrink: 0;
        }
        
        /* =========================================================
           ALERTS
        ========================================================= */
        .alerts-list {
            padding: 16px 20px;
        }
        
        .alert-item {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            padding: 12px;
            background: #fff8e1;
            border: 1px solid #ffecb3;
            border-radius: 8px;
            margin-bottom: 10px;
        }
        
        .alert-item:last-child { 
            margin-bottom: 0; 
        }
        
        .alert-item.warning {
            background: #fff3e0;
            border-color: #ffe0b2;
        }
        
        .alert-item.danger {
            background: #ffebee;
            border-color: #ffcdd2;
        }
        
        .alert-icon {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: #ffc107;
            color: #333;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            flex-shrink: 0;
        }
        
        .alert-item.warning .alert-icon {
            background: #ff9800;
            color: #fff;
        }
        
        .alert-item.danger .alert-icon {
            background: #f44336;
            color: #fff;
        }
        
        .alert-content h4 {
            font-size: 13px;
            font-weight: 600;
            color: #333;
            margin: 0 0 2px 0;
        }
        
        .alert-content p {
            font-size: 12px;
            color: #666;
            margin: 0;
        }
        
        /* =========================================================
           CHART CONTAINER
        ========================================================= */
        .chart-container {
            position: relative;
            height: 300px;
            margin-top: 20px;
        }
        
        .chart-legend {
            display: flex;
            gap: 20px;
            margin-top: 15px;
            justify-content: center;
            font-size: 12px;
            color: #666;
        }
        
        .legend-item {
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        .legend-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
        }
        
        /* =========================================================
           QUICK ACTIONS
        ========================================================= */
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 10px;
        }
        
        .quick-action-btn {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px;
            background: #f9f8f5;
            border: 1px solid #e8e5dc;
            border-radius: 8px;
            color: #3f4b45;
            font-size: 13px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
            text-decoration: none;
        }
        
        .quick-action-btn:hover {
            background: #214f2c;
            color: #fff;
            border-color: #214f2c;
        }
        
        .quick-action-btn i {
            font-size: 16px;
            color: #527d59;
        }
        
        .quick-action-btn:hover i {
            color: #fff;
        }
        
        /* =========================================================
           EMPTY STATE
        ========================================================= */
        .empty-state {
            padding: 40px;
            text-align: center;
            color: #999;
        }
        
        .empty-state i {
            font-size: 32px;
            margin-bottom: 12px;
            color: #ccc;
        }
        
        /* =========================================================
           RESPONSIVE
        ========================================================= */
        @media (max-width: 1200px) {
            .stats-grid {
                grid-template-columns: repeat(3, 1fr);
            }
        }
        
        @media (max-width: 992px) {
            .sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s ease;
            }
            
            .sidebar.open {
                transform: translateX(0);
            }
            
            .main-content {
                margin-left: 0;
                padding: 20px;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .dashboard-grid {
                grid-template-columns: 1fr;
            }
            
            .dashboard-grid.three-columns {
                grid-template-columns: 1fr;
            }
        }
        
        @media (max-width: 576px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .quick-actions {
                grid-template-columns: 1fr;
            }
            
            .dashboard-header {
                flex-direction: column;
                gap: 15px;
            }
        }
        
        /* Mobile Menu Toggle */
        .mobile-toggle {
            display: none;
            position: fixed;
            top: 20px;
            left: 20px;
            z-index: 101;
            background: #214f2c;
            color: #fff;
            border: none;
            width: 40px;
            height: 40px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 18px;
        }
        
        @media (max-width: 992px) {
            .mobile-toggle {
                display: flex;
                align-items: center;
                justify-content: center;
            }
        }
        
        .sidebar-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            z-index: 99;
        }
        
        .sidebar-overlay.active {
            display: block;
        }
    </style>
</head>
<body>

<!-- Mobile Toggle -->
<button class="mobile-toggle" onclick="toggleSidebar()">
    <i class="fa-solid fa-bars"></i>
</button>

<!-- Sidebar Overlay -->
<div class="sidebar-overlay" onclick="toggleSidebar()"></div>

<!-- Sidebar -->
<nav class="sidebar">
    <div class="logo-section">
        <img src="vdvclogoo.png" class="logo-img" alt="VDVC Logo">
        <div class="panel-title">Owner Panel</div>
    </div>
    
    <ul class="menu-list">
        <a href="owner_dashboard.php" class="menu-item active">
            <i class="fa-solid fa-chart-pie"></i>
            Dashboard
        </a>
        
        <a href="manager_reports.php" class="menu-item">
            <i class="fa-solid fa-file-invoice-dollar"></i>
            Reports & Analytics
        </a>
        
        <a href="inventory.php" class="menu-item">
            <i class="fa-solid fa-boxes-stacked"></i>
            Inventory
        </a>
        
        <a href="manager_reservation.php" class="menu-item">
            <i class="fa-solid fa-calendar-check"></i>
            Reservations
        </a>
        
        <div class="logout-section">
            <a href="logout.php" class="menu-item btn-logout">
                <i class="fa-solid fa-right-from-bracket"></i>
                Log Out
            </a>
        </div>
    </ul>
</nav>

<!-- Main Content -->
<div class="main-content">
    <!-- Dashboard Header -->
    <div class="dashboard-header">
        <div>
            <h1>Welcome, <?php echo htmlspecialchars($owner_name); ?>!</h1>
            <p class="welcome-text">Here's an overview of your farm's performance and activities.</p>
        </div>
        <div class="live-status">
            <span class="live-dot"></span>
            <span>Live operational data</span>
        </div>
    </div>
    
    <!-- Stats Grid -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon customers">
                <i class="fa-solid fa-users"></i>
            </div>
            <div class="stat-content">
                <h3><?php echo number_format($total_customers); ?></h3>
                <p>Total Customers</p>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon reservations">
                <i class="fa-solid fa-calendar-check"></i>
            </div>
            <div class="stat-content">
                <h3><?php echo number_format($monthly_reservations); ?></h3>
                <p>This Month's Reservations</p>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon eggs">
                <i class="fa-solid fa-egg"></i>
            </div>
            <div class="stat-content">
                <h3><?php echo number_format($total_egg_stock); ?></h3>
                <p>Total Egg Stock (Trays)</p>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon pending">
                <i class="fa-solid fa-clock"></i>
            </div>
            <div class="stat-content">
                <h3><?php echo number_format($pending_reservations); ?></h3>
                <p>Pending Reservations</p>
            </div>
        </div>
    </div>
    
    <!-- Dashboard Grid Row 1 -->
    <div class="dashboard-grid">
        <!-- Reservation Trends Chart -->
        <div class="dashboard-card">
            <div class="card-header">
                <h3><i class="fa-solid fa-chart-line"></i> Reservation Trends (Last 7 Days)</h3>
            </div>
            <div class="card-body">
                <div class="chart-container">
                    <canvas id="reservationChart"></canvas>
                </div>
                <div class="chart-legend">
                    <div class="legend-item">
                        <span class="legend-dot" style="background: #214f2c;"></span>
                        <span>Daily Reservations</span>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Alerts & Quick Actions -->
        <div class="dashboard-card">
            <div class="card-header">
                <h3><i class="fa-solid fa-bell"></i> Alerts & Quick Actions</h3>
            </div>
            <div class="card-body">
                <?php if (count($low_stock_items) > 0 || $pending_reservations > 0): ?>
                    <div class="alerts-list" style="margin-bottom: 20px;">
                        <?php if ($pending_reservations > 0): ?>
                            <div class="alert-item warning">
                                <div class="alert-icon" style="background: #ff9800; color: #fff;">
                                    <i class="fa-solid fa-clock"></i>
                                </div>
                                <div class="alert-content">
                                    <h4>Pending Action</h4>
                                    <p><?php echo $pending_reservations; ?> reservation(s) awaiting confirmation</p>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <?php foreach ($low_stock_items as $item): ?>
                            <div class="alert-item">
                                <div class="alert-icon">
                                    <i class="fa-solid fa-triangle-exclamation"></i>
                                </div>
                                <div class="alert-content">
                                    <h4>Low Stock Alert</h4>
                                    <p><?php echo htmlspecialchars($item['egg_size']); ?> eggs are running low (<?php echo $item['current_stock']; ?> trays remaining)</p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div style="padding: 20px; text-align: center; color: #27ae60; margin-bottom: 20px;">
                        <i class="fa-solid fa-check-circle" style="font-size: 32px; margin-bottom: 8px;"></i>
                        <p style="margin: 0;">All systems operational</p>
                    </div>
                <?php endif; ?>
                
                <div class="quick-actions">
                    <a href="manager_reservation.php" class="quick-action-btn">
                        <i class="fa-solid fa-calendar-plus"></i>
                        New Reservation
                    </a>
                    <a href="inventory.php" class="quick-action-btn">
                        <i class="fa-solid fa-box-open"></i>
                        Update Stock
                    </a>
                    <a href="manager_reports.php" class="quick-action-btn">
                        <i class="fa-solid fa-file-export"></i>
                        Export Report
                    </a>
                    <a href="manager_delivery.php" class="quick-action-btn">
                        <i class="fa-solid fa-truck"></i>
                        Schedule Delivery
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Dashboard Grid Row 2: Recent Activity -->
    <div class="dashboard-grid">
        <!-- Recent Reservations -->
        <div class="dashboard-card">
            <div class="card-header">
                <h3><i class="fa-solid fa-clock-rotate-left"></i> Recent Reservations</h3>
                <a href="manager_reservation.php" class="view-all">View All</a>
            </div>
            <div class="activity-list">
                <?php if (count($recent_activities) > 0): ?>
                    <?php foreach ($recent_activities as $activity): ?>
                        <?php
                        $status_class = 'new';
                        $status_icon = 'fa-calendar';
                        $status_color = '#f39c12';
                        
                        if ($activity['status'] === 'Confirmed') {
                            $status_class = 'confirmed';
                            $status_icon = 'fa-check';
                            $status_color = '#27ae60';
                        } elseif ($activity['status'] === 'Completed') {
                            $status_class = 'completed';
                            $status_icon = 'fa-check-double';
                            $status_color = '#3498db';
                        } elseif ($activity['status'] === 'Cancelled') {
                            $status_class = 'cancelled';
                            $status_icon = 'fa-xmark';
                            $status_color = '#e74c3c';
                        } elseif ($activity['status'] === 'Pending') {
                            $status_class = 'pending';
                            $status_icon = 'fa-clock';
                            $status_color = '#f39c12';
                        }
                        ?>
                        <div class="activity-item">
                            <div class="activity-icon <?php echo $status_class; ?>">
                                <i class="fa-solid <?php echo $status_icon; ?>"></i>
                            </div>
                            <div class="activity-content">
                                <div class="activity-title">
                                    <?php echo htmlspecialchars($activity['customer_name'] ?? 'Unknown'); ?> - 
                                    <?php echo htmlspecialchars($activity['egg_type'] ?? 'N/A'); ?>
                                </div>
                                <div class="activity-meta">
                                    <?php echo $activity['quantity'] ?? 0; ?> trays • 
                                    <span style="color: <?php echo $status_color; ?>; font-weight: 500;">
                                        <?php echo $activity['status']; ?>
                                    </span>
                                </div>
                            </div>
                            <div class="activity-time">
                                <?php echo date('M d', strtotime($activity['created_at'])); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fa-solid fa-inbox"></i>
                        <p>No recent reservations</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Additional Stats -->
        <div class="dashboard-card">
            <div class="card-header">
                <h3><i class="fa-solid fa-chart-pie"></i> Farm Overview</h3>
            </div>
            <div class="card-body">
                <div class="stats-grid" style="grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 0;">
                    <div class="stat-card" style="padding: 15px;">
                        <div class="stat-icon managers" style="width: 40px; height: 40px; font-size: 18px;">
                            <i class="fa-solid fa-user-tie"></i>
                        </div>
                        <div class="stat-content">
                            <h3 style="font-size: 20px;"><?php echo number_format($total_managers); ?></h3>
                            <p style="font-size: 12px;">Active Managers</p>
                        </div>
                    </div>
                    
                    <div class="stat-card" style="padding: 15px;">
                        <div class="stat-icon deliveries" style="width: 40px; height: 40px; font-size: 18px;">
                            <i class="fa-solid fa-truck-fast"></i>
                        </div>
                        <div class="stat-content">
                            <h3 style="font-size: 20px;"><?php echo number_format($today_deliveries); ?></h3>
                            <p style="font-size: 12px;">Today's Deliveries</p>
                        </div>
                    </div>
                </div>
                
                <div style="margin-top: 20px; padding-top: 20px; border-top: 1px solid #e8e5dc;">
                    <h4 style="font-size: 14px; font-weight: 600; color: #214f2c; margin-bottom: 10px;">
                        <i class="fa-solid fa-lightbulb" style="color: #ffc107;"></i> 
                        Quick Insight
                    </h4>
                    <p style="font-size: 13px; color: #59635c; line-height: 1.5; margin: 0;">
                        <?php if ($pending_reservations > 0): ?>
                            You have <strong><?php echo $pending_reservations; ?></strong> pending reservation(s) requiring attention.
                        <?php elseif (count($low_stock_items) > 0): ?>
                            <strong><?php echo count($low_stock_items); ?></strong> egg size(s) are running low on stock.
                        <?php else: ?>
                            All systems are running smoothly. No immediate action required.
                        <?php endif; ?>
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Chart.js for Analytics -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
    // Mobile Sidebar Toggle
    function toggleSidebar() {
        document.querySelector('.sidebar').classList.toggle('open');
        document.querySelector('.sidebar-overlay').classList.toggle('active');
    }
    
    // Reservation Chart
    const ctx = document.getElementById('reservationChart').getContext('2d');
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: <?php echo json_encode($chart_labels); ?>,
            datasets: [{
                label: 'Reservations',
                data: <?php echo json_encode($reservation_chart_data); ?>,
                borderColor: '#214f2c',
                backgroundColor: 'rgba(33, 79, 44, 0.1)',
                borderWidth: 2,
                fill: true,
                tension: 0.4,
                pointBackgroundColor: '#214f2c',
                pointBorderColor: '#fff',
                pointBorderWidth: 2,
                pointRadius: 4,
                pointHoverRadius: 6
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    backgroundColor: '#214f2c',
                    titleColor: '#fff',
                    bodyColor: '#fff',
                    padding: 10,
                    cornerRadius: 6,
                    displayColors: false
                }
            },
            scales: {
                x: {
                    grid: {
                        display: false
                    },
                    ticks: {
                        color: '#666',
                        font: {
                            size: 11
                        }
                    }
                },
                y: {
                    beginAtZero: true,
                    grid: {
                        color: '#f0f0f0'
                    },
                    ticks: {
                        color: '#666',
                        font: {
                            size: 11
                        },
                        stepSize: 1
                    }
                }
            }
        }
    });
</script>

</body>
</html>
<?php
// End output buffering and flush
ob_end_flush();
?>
