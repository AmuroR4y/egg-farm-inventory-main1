<?php
require_once 'session_config.php';
require 'db.php';

// Security check
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'owner') {
    header("Location: login.php");
    exit();
}

$page = isset($_GET['page']) ? $_GET['page'] : 'dashboard';

// Determine if this is being included inline or loaded as standalone
$is_included = (basename($_SERVER['PHP_SELF']) !== 'owner_dashboard_content.php');

// If included directly without page param, always show dashboard
if ($is_included && !isset($_GET['page'])) {
    $page = 'dashboard';
}

// If included inline, don't output HTML structure
if (!$is_included) {
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Owner Content</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="vdvc/styles.css">
    <style>
        body { margin: 0; padding: 20px; background: #f9f8f5; }
        .page { display: block; }
    </style>
</head>
<body>
<?php } ?>

<?php if ($page == 'dashboard'): ?>
        <div id="page-dashboard" class="page active">
            <div class="dashboard-page">
                <?php if (!$is_included): ?>
                <section class="dashboard-hero" aria-labelledby="dashboard-heading">
                    <div>
                        <div class="eyebrow">FARM OVERVIEW</div>
                        <h1 id="dashboard-heading">Operational Status</h1>
                        <p>Current farm status, key metrics, and recent activity at a glance.</p>
                    </div>
                    <div class="dashboard-hero-meta">
                        <div class="live-status"><span class="live-dot"></span><span id="dashboard-live-status">Live operational data</span></div>
                        <div class="hero-scope">Retained data: 90 days</div>
                    </div>
                </section>
                <?php endif; ?>

                <section class="dashboard-kpis" id="dashboard-stats" aria-label="Key performance indicators"></section>

                <section class="dashboard-grid dashboard-main-grid">
                    <aside class="dashboard-card alerts-card" aria-labelledby="alerts-title">
                        <div class="card-heading compact">
                            <div>
                                <span class="card-kicker">ACTION CENTER</span>
                                <h2 id="alerts-title">Attention Required</h2>
                            </div>
                            <span class="alert-count" id="alert-count">0</span>
                        </div>
                        <div id="alerts-list" class="alerts-list"></div>
                    </aside>

                    <article class="dashboard-card activity-card">
                        <div class="card-heading compact">
                            <div><span class="card-kicker">RECENT ACTIVITY</span><h2>What's Happening</h2></div>
                            <div class="live-metric-pill" id="live-metric">0 active reservations</div>
                        </div>
                        <div id="activity-log-list" class="timeline-list"></div>
                    </article>
                </section>

                <!-- Data Analytics Section -->
                <section class="dashboard-grid analytics-section">
                    <article class="dashboard-card analytics-card">
                        <div class="card-heading">
                            <div>
                                <span class="card-kicker">DATA ANALYTICS</span>
                                <h2>Farm Performance Metrics</h2>
                            </div>
                        </div>

                        <div id="dashboard-analytics-loading">Loading analytics data...</div>

                        <div id="dashboard-analytics-content" style="display:none;">
                            <!-- Analytics KPI Summary -->
                            <div class="analytics-kpi-grid">
                                <div class="analytics-kpi-card">
                                    <div class="analytics-kpi-label">Total Reservations</div>
                                    <div class="analytics-kpi-value" id="analytics-kpi-reservations">0</div>
                                </div>
                                <div class="analytics-kpi-card">
                                    <div class="analytics-kpi-label">Total Sales</div>
                                    <div class="analytics-kpi-value" id="analytics-kpi-sales">₱0</div>
                                </div>
                                <div class="analytics-kpi-card">
                                    <div class="analytics-kpi-label">Avg Order Size</div>
                                    <div class="analytics-kpi-value" id="analytics-kpi-avg-order">0</div>
                                </div>
                                <div class="analytics-kpi-card">
                                    <div class="analytics-kpi-label">Customer Growth</div>
                                    <div class="analytics-kpi-value" id="analytics-kpi-growth">0%</div>
                                </div>
                            </div>

                            <!-- Charts Row -->
                            <div class="analytics-charts-grid">
                                <!-- Reservation Chart -->
                                <div class="analytics-chart-section">
                                    <h3>Reservation Activity</h3>
                                    <p class="chart-subtitle">Daily reservations for the last 7 days</p>
                                    <div class="chart-container">
                                        <svg id="dashboard-reservation-chart" style="width:100%;height:220px;"></svg>
                                    </div>
                                </div>

                                <!-- Inventory Chart -->
                                <div class="analytics-chart-section">
                                    <h3>Inventory Analytics</h3>
                                    <p class="chart-subtitle">Egg size distribution and current stock</p>
                                    <div class="inventory-summary" id="dashboard-inventory-summary"></div>
                                    <div class="chart-container">
                                        <svg id="dashboard-inventory-chart" style="width:100%;height:220px;"></svg>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </article>
                </section>
            </div>
        </div>

        <?php if (!$is_included): ?>
        <style>
            .analytics-section {
                margin-top: 30px;
            }
            .analytics-kpi-grid {
                display: grid;
                grid-template-columns: repeat(4, 1fr);
                gap: 16px;
                margin-bottom: 24px;
            }
            @media (max-width: 1024px) {
                .analytics-kpi-grid {
                    grid-template-columns: repeat(2, 1fr);
                }
            }
            @media (max-width: 600px) {
                .analytics-kpi-grid {
                    grid-template-columns: 1fr;
                }
            }
            .analytics-kpi-card {
                background: white;
                padding: 20px;
                border-radius: 8px;
                box-shadow: 0 2px 4px rgba(0,0,0,0.08);
            }
            .analytics-kpi-label {
                font-size: 12px;
                color: #666;
                margin-bottom: 8px;
                text-transform: uppercase;
                letter-spacing: 0.5px;
            }
            .analytics-kpi-value {
                font-size: 32px;
                font-weight: bold;
                color: #214f2c;
            }
            .analytics-chart-section {
                margin: 30px 0;
            }
            .analytics-chart-section h3 {
                margin: 0 0 20px 0;
                color: #214f2c;
                font-size: 16px;
                font-weight: 600;
            }
            .inventory-summary {
                margin-bottom: 20px;
                padding: 16px;
                background: #f8f9fa;
                border-radius: 8px;
                font-size: 14px;
            }
            .inventory-summary strong {
                color: #214f2c;
            }
            #dashboard-analytics-loading {
                text-align: center;
                padding: 40px;
                color: #999;
                font-style: italic;
            }
        </style>
        <?php endif; ?>

        <script>
            // Store data for resize handler
            let dashboardDataCache = null;

            // Load dashboard data and render
            document.addEventListener('DOMContentLoaded', function() {
                loadDashboardData();
            });

            function loadDashboardData() {
                fetch('/egg-farm-inventory-main1/owner_api.php')
                    .then(response => response.json())
                    .then(data => {
                        console.log('Dashboard data:', data);
                        if (data.status === 'success') {
                            dashboardDataCache = data;
                            renderDashboardKPIs(data);
                            renderDashboardAlerts(data);
                            renderDashboardActivity(data);
                            renderDashboardQuickStatus(data);
                            renderDashboardNotifications(data);
                            renderDashboardAnalytics(data);
                        }
                    })
                    .catch(error => {
                        console.error('Failed to load dashboard data:', error);
                    });
            }

            function renderDashboardKPIs(data) {
                const statsContainer = document.getElementById('dashboard-stats');
                if (!statsContainer) return;

                const totalUsers = data.users ? data.users.length : 0;
                const totalReservations = data.reservations ? data.reservations.length : 0;
                const eggInventory = data.inventory && data.inventory.snapshotEggs ? data.inventory.snapshotEggs.reduce((sum, e) => sum + (parseInt(e.current_stock) || 0), 0) : 0;
                const totalDeliveries = data.deliveries ? data.deliveries.length : 0;

                const metrics = [
                    { label: 'Total Users', value: totalUsers, change: '+20%', color: '#25a366', icon: 'fa-users' },
                    { label: 'Total Reservations', value: totalReservations, change: '+14%', color: '#3b82f6', icon: 'fa-calendar-check' },
                    { label: 'Egg Inventory', value: eggInventory, change: '+8%', color: '#f3a712', icon: 'fa-egg' },
                    { label: 'Total Deliveries', value: totalDeliveries, change: '+10%', color: '#8b5cf6', icon: 'fa-truck' }
                ];

                statsContainer.innerHTML = metrics.map(item => `
                    <article class="kpi-card">
                        <div class="kpi-icon" style="background: ${item.color}20; color: ${item.color};">
                            <i class="fa-solid ${item.icon}"></i>
                        </div>
                        <div class="kpi-content">
                            <div class="kpi-label">${item.label}</div>
                            <div class="kpi-value">${item.value.toLocaleString()}</div>
                            <div class="kpi-change" style="color: ${item.color};">
                                <i class="fa-solid fa-arrow-up"></i> ${item.change}
                            </div>
                            <div class="kpi-comparison">vs last week</div>
                        </div>
                    </article>
                `).join('');
            }

            function renderDashboardAlerts(data) {
                const alertsList = document.getElementById('alerts-list');
                const alertCount = document.getElementById('alert-count');
                const rightRailAlerts = document.getElementById('right-rail-alerts');

                const alerts = [];

                // Check for low inventory
                if (data.inventory && data.inventory.snapshotEggs) {
                    data.inventory.snapshotEggs.forEach(egg => {
                        if (parseInt(egg.current_stock) < 100) {
                            alerts.push({
                                type: 'warning',
                                title: 'Low Egg Stock',
                                message: `${egg.egg_size || 'Unknown'} eggs: ${egg.current_stock} units remaining`,
                                time: '2h ago'
                            });
                        }
                    });
                }

                // Check for pending reservations
                if (data.reservations) {
                    const pending = data.reservations.filter(r => r.status === 'pending');
                    if (pending.length > 0) {
                        alerts.push({
                            type: 'info',
                            title: 'Pending Reservations',
                            message: `${pending.length} reservations awaiting confirmation`,
                            time: '1h ago'
                        });
                    }
                }

                // Update main dashboard alerts
                if (alertsList) {
                    if (alertCount) alertCount.textContent = alerts.length;

                    if (alerts.length === 0) {
                        alertsList.innerHTML = '<div style="padding: 20px; text-align: center; color: #999;">No alerts at this time</div>';
                    } else {
                        alertsList.innerHTML = alerts.map(alert => `
                            <div class="alert-item" style="padding: 12px; border-bottom: 1px solid #eee;">
                                <strong style="color: ${alert.type === 'warning' ? '#f57f17' : '#1565c0'}">${alert.title}</strong>
                                <div style="font-size: 12px; color: #666;">${alert.message}</div>
                            </div>
                        `).join('');
                    }
                }

                // Update right rail alerts
                if (rightRailAlerts) {
                    if (alerts.length === 0) {
                        rightRailAlerts.innerHTML = '<div style="padding: 20px; text-align: center; color: #999; font-size: 12px;">No alerts at this time</div>';
                    } else {
                        rightRailAlerts.innerHTML = alerts.map(alert => `
                            <div class="alert-item">
                                <div class="alert-dot ${alert.type}"></div>
                                <div class="alert-content">
                                    <div class="alert-title">${alert.title}</div>
                                    <div class="alert-time">${alert.time}</div>
                                </div>
                            </div>
                        `).join('');
                    }
                }
            }

            function renderDashboardActivity(data) {
                const activityList = document.getElementById('activity-log-list');
                const liveMetric = document.getElementById('live-metric');
                const rightRailActivity = document.getElementById('right-rail-activity');

                if (liveMetric && data.reservations) {
                    const activeCount = data.reservations.filter(r => r.status === 'pending' || r.status === 'confirmed').length;
                    liveMetric.textContent = `${activeCount} active reservations`;
                }

                if (!data.reservations || data.reservations.length === 0) {
                    if (activityList) {
                        activityList.innerHTML = '<div style="padding: 20px; text-align: center; color: #999;">No recent activity</div>';
                    }
                    if (rightRailActivity) {
                        rightRailActivity.innerHTML = '<div style="padding: 20px; text-align: center; color: #999; font-size: 12px;">No recent activity</div>';
                    }
                    return;
                }

                const recentReservations = data.reservations.slice(-5).reverse();

                // Update main dashboard activity
                if (activityList) {
                    activityList.innerHTML = recentReservations.map(res => {
                        const customerName = res.customerName || 'Unknown';
                        const product = res.product || 'Unknown';
                        const quantity = res.quantity || 0;
                        const status = res.status ? res.status.charAt(0).toUpperCase() + res.status.slice(1) : 'Unknown';
                        const createdAt = res.createdAt || 'Unknown date';

                        return `
                        <div style="padding: 12px; border-left: 2px solid #ddd; margin-left: 10px; padding-left: 15px; margin-bottom: 12px;">
                            <div style="font-weight: 600; font-size: 13px; white-space: normal; line-height: 1.4;">${customerName} — ${product} — ${quantity} units — ${status}</div>
                            <div style="font-size: 11px; color: #999; margin-top: 4px;">${createdAt}</div>
                        </div>
                        `;
                    }).join('');
                }

                // Update right rail activity
                if (rightRailActivity) {
                    rightRailActivity.innerHTML = recentReservations.slice(0, 5).map(res => {
                        const customerName = res.customerName || 'Unknown';
                        const product = res.product || 'Unknown';
                        const createdAt = res.createdAt || 'Unknown';

                        // Calculate time ago
                        const dateObj = new Date(createdAt);
                        const now = new Date();
                        const diffMs = now - dateObj;
                        const diffMins = Math.floor(diffMs / 60000);
                        const diffHours = Math.floor(diffMs / 3600000);
                        const diffDays = Math.floor(diffMs / 86400000);

                        let timeAgo = 'Just now';
                        if (diffMins < 60) {
                            timeAgo = `${diffMins}m ago`;
                        } else if (diffHours < 24) {
                            timeAgo = `${diffHours}h ago`;
                        } else {
                            timeAgo = `${diffDays}d ago`;
                        }

                        return `
                        <div class="activity-item">
                            <div class="activity-icon">
                                <i class="fa-solid fa-egg"></i>
                            </div>
                            <div class="activity-content">
                                <div class="activity-text">${customerName} ordered ${product}</div>
                                <div class="activity-time">${timeAgo}</div>
                            </div>
                        </div>
                        `;
                    }).join('');
                }
            }

            function renderDashboardQuickStatus(data) {
                const inventoryStatus = document.getElementById('quick-inventory-status');
                const reservationStatus = document.getElementById('quick-reservation-status');
                const deliveryStatus = document.getElementById('quick-delivery-status');
                const forecastStatus = document.getElementById('quick-forecast-status');

                if (inventoryStatus && data.inventory && data.inventory.snapshotEggs) {
                    const totalStock = data.inventory.snapshotEggs.reduce((sum, e) => sum + (parseInt(e.current_stock) || 0), 0);
                    inventoryStatus.textContent = totalStock > 500 ? 'Healthy' : totalStock > 100 ? 'Moderate' : 'Low';
                    inventoryStatus.style.color = totalStock > 500 ? '#28a745' : totalStock > 100 ? '#ffc107' : '#dc3545';
                }

                if (reservationStatus && data.reservations) {
                    const activeCount = data.reservations.filter(r => r.status === 'pending' || r.status === 'confirmed').length;
                    reservationStatus.textContent = activeCount > 0 ? 'Active' : 'Idle';
                    reservationStatus.style.color = activeCount > 0 ? '#28a745' : '#6c757d';
                }

                if (deliveryStatus && data.deliveries) {
                    const pendingDeliveries = data.deliveries.filter(d => d.status === 'scheduled').length;
                    deliveryStatus.textContent = pendingDeliveries > 0 ? 'Scheduled' : 'Caught Up';
                    deliveryStatus.style.color = pendingDeliveries > 0 ? '#28a745' : '#6c757d';
                }

                if (forecastStatus) {
                    forecastStatus.textContent = 'Stable';
                    forecastStatus.style.color = '#28a745';
                }
            }

            function renderDashboardNotifications(data) {
                const notificationList = document.getElementById('notification-list');
                if (!notificationList) return;

                if (!data.notifications || data.notifications.length === 0) {
                    notificationList.innerHTML = '<div style="padding: 20px; text-align: center; color: #999;">No notifications</div>';
                    return;
                }

                const recentNotifications = data.notifications.slice(-5).reverse();
                notificationList.innerHTML = recentNotifications.map(notif => `
                    <div class="notification-item" style="padding: 12px; border-bottom: 1px solid #eee; cursor: pointer;">
                        <div style="font-weight: 600; font-size: 13px; ${!notif.is_read ? 'color: #214f2c;' : ''}">${notif.title}</div>
                        <div style="font-size: 12px; color: #666;">${notif.description}</div>
                        <div style="font-size: 11px; color: #999;">${notif.created_at}</div>
                    </div>
                `).join('');
            }

            function renderDashboardAnalytics(data) {
                const analyticsLoading = document.getElementById('dashboard-analytics-loading');
                const analyticsContent = document.getElementById('dashboard-analytics-content');
                if (!analyticsLoading || !analyticsContent) return;

                analyticsLoading.style.display = 'none';
                analyticsContent.style.display = 'block';

                // Render Analytics KPIs
                const users = data.users || [];
                const reservations = data.reservations || [];
                const deliveries = data.deliveries || [];
                const inventory = data.inventory || {};
                const eggs = inventory.eggs || [];
                const supplies = inventory.supplies || [];

                const totalReservations = reservations.length;
                const totalSales = reservations.reduce((sum, r) => sum + (parseFloat(r.amount) || 0), 0);
                const avgOrderSize = totalReservations > 0 ? Math.round(totalSales / totalReservations) : 0;
                const customerGrowth = users.length > 0 ? '+12%' : '0%';

                document.getElementById('analytics-kpi-reservations').textContent = totalReservations;
                document.getElementById('analytics-kpi-sales').textContent = '₱' + totalSales.toLocaleString();
                document.getElementById('analytics-kpi-avg-order').textContent = avgOrderSize;
                document.getElementById('analytics-kpi-growth').textContent = customerGrowth;

                // Render Reservation Chart
                renderDashboardReservationChart(reservations);

                // Render Inventory Chart
                renderDashboardInventoryChart(inventory);
            }

            // Add resize handler to re-render charts
            window.addEventListener('resize', function() {
                if (dashboardDataCache) {
                    renderDashboardReservationChart(dashboardDataCache.reservations || []);
                    renderDashboardInventoryChart(dashboardDataCache.inventory || {});
                }
            });

            function renderDashboardReservationChart(reservations) {
                const svg = document.getElementById('dashboard-reservation-chart');
                if (!svg || !reservations || reservations.length === 0) {
                    if (svg) svg.innerHTML = '<text x="50%" y="50%" text-anchor="middle" fill="#999" font-size="14">No reservation data available</text>';
                    return;
                }

                const dateCounts = {};
                reservations.forEach(r => {
                    const date = r.scheduledAt || r.createdAt || 'unknown';
                    const dateShort = date.substring(0, 10);
                    dateCounts[dateShort] = (dateCounts[dateShort] || 0) + 1;
                });

                const dates = Object.keys(dateCounts).sort();
                const values = Object.values(dateCounts).map(v => Number(v) || 0);
                const maxCount = Math.max(...values, 1);

                // Get actual container dimensions
                const containerWidth = svg.parentElement.clientWidth || 600;
                const containerHeight = svg.parentElement.clientHeight || 220;

                const padding = { top: 20, right: 20, bottom: 70, left: 40 };
                const chartWidth = containerWidth - padding.left - padding.right;
                const chartHeight = containerHeight - padding.top - padding.bottom;

                // Calculate max labels that can fit (assume ~40px per label)
                const maxLabels = Math.floor(chartWidth / 45);
                const showAllLabels = dates.length <= maxLabels;
                const labelStep = showAllLabels ? 1 : Math.ceil(dates.length / maxLabels);

                const barWidth = Math.max((chartWidth / dates.length) * 0.4, 20);
                const barGap = (chartWidth - (barWidth * dates.length)) / (dates.length + 1);

                let svgContent = '';

                // Grid lines
                for (let i = 0; i <= 5; i++) {
                    const y = padding.top + (chartHeight * i / 5);
                    svgContent += `<line x1="${padding.left}" y1="${y}" x2="${containerWidth - padding.right}" y2="${y}" stroke="#e0e0e0" stroke-width="1" stroke-dasharray="4"/>`;
                }

                // Bars
                dates.forEach((date, i) => {
                    const count = values[i] || 0;
                    const barHeight = (count / maxCount) * chartHeight;
                    const x = padding.left + barGap + i * (barWidth + barGap);
                    const y = padding.top + chartHeight - barHeight;

                    // Only show label on some bars if there are too many
                    const showLabel = showAllLabels || i % labelStep === 0;

                    // Format date as "Oct 08" from "2024-10-08"
                    const dateObj = new Date(date);
                    const formattedDate = dateObj.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });

                    svgContent += `
                        <rect x="${x}" y="${y}" width="${barWidth}" height="${barHeight}" fill="url(#barGradient)" rx="4" ry="4"/>
                        ${showLabel ? `<text x="${x + barWidth/2}" y="${containerHeight - padding.bottom + 20}" font-size="10" text-anchor="middle" fill="#666">${formattedDate}</text>` : ''}
                        <text x="${x + barWidth/2}" y="${y - 8}" font-size="12" text-anchor="middle" fill="#214f2c" font-weight="600">${count}</text>
                    `;
                });

                // Gradient definition
                svgContent = `
                    <defs>
                        <linearGradient id="barGradient" x1="0%" y1="0%" x2="0%" y2="100%">
                            <stop offset="0%" style="stop-color:#214f2c;stop-opacity:1" />
                            <stop offset="100%" style="stop-color:#527d59;stop-opacity:1" />
                        </linearGradient>
                    </defs>
                ` + svgContent;

                svg.setAttribute('viewBox', `0 0 ${containerWidth} ${containerHeight}`);
                svg.innerHTML = svgContent;
            }

            function renderDashboardInventoryChart(inventory) {
                const svg = document.getElementById('dashboard-inventory-chart');
                const summaryDiv = document.getElementById('dashboard-inventory-summary');
                if (!svg || !inventory) return;

                const eggs = inventory.snapshotEggs || [];
                const supplies = inventory.snapshotSupplies || [];

                // Render summary
                const totalEggStock = eggs.reduce((sum, e) => sum + (parseInt(e.current_stock) || 0), 0);
                const totalSupplyStock = supplies.reduce((sum, s) => sum + (parseInt(s.current_stock) || 0), 0);

                summaryDiv.innerHTML = `
                    <div style="display:flex;gap:30px;flex-wrap:wrap;">
                        <div><strong>Total Egg Stock:</strong> ${totalEggStock.toLocaleString()} units</div>
                        <div><strong>Total Supply Stock:</strong> ${totalSupplyStock.toLocaleString()} units</div>
                    </div>
                `;

                // Render chart
                const sizeCounts = {};
                eggs.forEach(e => {
                    const size = e.egg_size || 'unknown';
                    sizeCounts[size] = (sizeCounts[size] || 0) + (parseInt(e.current_stock) || 0);
                });

                if (Object.keys(sizeCounts).length === 0) {
                    svg.innerHTML = '<text x="50%" y="50%" text-anchor="middle" font-size="14" fill="#999">No inventory data available</text>';
                    return;
                }

                const values = Object.values(sizeCounts).map(v => Number(v) || 0);
                const maxCount = Math.max(...values, 1);

                // Get actual container dimensions
                const containerWidth = svg.parentElement.clientWidth || 600;
                const containerHeight = svg.parentElement.clientHeight || 220;

                const padding = { top: 20, right: 20, bottom: 95, left: 40 };
                const chartWidth = containerWidth - padding.left - padding.right;
                const chartHeight = containerHeight - padding.top - padding.bottom;

                const categories = Object.keys(sizeCounts);
                const barWidth = Math.max((chartWidth / categories.length) * 0.45, 22);
                const barGap = (chartWidth - (barWidth * categories.length)) / (categories.length + 1);

                let svgContent = '';

                // Grid lines
                for (let i = 0; i <= 5; i++) {
                    const y = padding.top + (chartHeight * i / 5);
                    svgContent += `<line x1="${padding.left}" y1="${y}" x2="${containerWidth - padding.right}" y2="${y}" stroke="#e0e0e0" stroke-width="1" stroke-dasharray="4"/>`;
                }

                // Bars
                Object.entries(sizeCounts).forEach(([size, count], i) => {
                    const countSafe = Number(count) || 0;
                    const barHeight = (countSafe / maxCount) * chartHeight;
                    const x = padding.left + barGap + i * (barWidth + barGap);
                    const y = padding.top + chartHeight - barHeight;

                    // Smart label wrapping
                    let labelText = '';
                    const labelParts = size.split(' ');
                    if (labelParts.length > 1) {
                        // Multi-word: put each word on its own line
                        labelText = `<tspan x="${x + barWidth/2}" dy="0" font-size="9" text-anchor="middle" fill="#666">${labelParts[0]}</tspan>`;
                        labelParts.slice(1).forEach((part, idx) => {
                            labelText += `<tspan x="${x + barWidth/2}" dy="11" font-size="9" text-anchor="middle" fill="#666">${part}</tspan>`;
                        });
                    } else if (size.length > 7) {
                        // Long single word: split it
                        const mid = Math.ceil(size.length / 2);
                        labelText = `<tspan x="${x + barWidth/2}" dy="0" font-size="9" text-anchor="middle" fill="#666">${size.substring(0, mid)}</tspan>`;
                        labelText += `<tspan x="${x + barWidth/2}" dy="11" font-size="9" text-anchor="middle" fill="#666">${size.substring(mid)}</tspan>`;
                    } else {
                        labelText = `<tspan x="${x + barWidth/2}" dy="8" font-size="9" text-anchor="middle" fill="#666">${size}</tspan>`;
                    }

                    svgContent += `
                        <rect x="${x}" y="${y}" width="${barWidth}" height="${barHeight}" fill="url(#barGradientInv)" rx="4" ry="4"/>
                        <text x="${x + barWidth/2}" y="${containerHeight - padding.bottom + 20}">${labelText}</text>
                        <text x="${x + barWidth/2}" y="${y - 8}" font-size="11" text-anchor="middle" fill="#214f2c" font-weight="600">${countSafe}</text>
                    `;
                });

                // Gradient definition
                svgContent = `
                    <defs>
                        <linearGradient id="barGradientInv" x1="0%" y1="0%" x2="0%" y2="100%">
                            <stop offset="0%" style="stop-color:#527d59;stop-opacity:1" />
                            <stop offset="100%" style="stop-color:#214f2c;stop-opacity:1" />
                        </linearGradient>
                    </defs>
                ` + svgContent;

                svg.setAttribute('viewBox', `0 0 ${containerWidth} ${containerHeight}`);
                svg.innerHTML = svgContent;
            }
        </script>

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
        <?php include 'owner_content_forecasting.php'; ?>

    <?php elseif ($page == 'reports'): ?>
        <div id="page-reports" class="page">
            <div class="page-shell">
                <div class="page-subtitle">Reports Management</div>
                <div style="font-size:13px;font-weight:700;color:var(--sidebar);margin-bottom:12px;">Reports Center</div>
                
                <div class="reports-filters">
                    <label for="report-type">Report Type:</label>
                    <select id="report-type" onchange="loadReport()">
                        <option value="inventory">Inventory Report</option>
                        <option value="reservation">Reservation Report</option>
                        <option value="delivery">Delivery Report</option>
                        <option value="customer">Customer Report</option>
                        <option value="forecasting">Forecasting Report</option>
                    </select>
                    <button class="btn-secondary" onclick="printReport()">Print</button>
                    <button class="btn-secondary" onclick="exportCSV()">Export CSV</button>
                    <span id="loading-indicator" style="display:none; margin-left:10px; color:#666;"><i class="fa-solid fa-spinner fa-spin"></i> Loading...</span>
                </div>

                <div id="report-content" class="report-content"></div>
            </div>
        </div>

        <style>
            .reports-filters {
                margin-bottom: 20px;
                padding: 15px;
                background: #f5f5f5;
                border-radius: 4px;
                display: flex;
                gap: 10px;
                align-items: center;
                flex-wrap: wrap;
            }
            .reports-filters label {
                font-weight: 600;
            }
            .reports-filters select {
                padding: 8px;
                border: 1px solid #ddd;
                border-radius: 4px;
            }
            .report-content {
                margin-top: 20px;
            }
            .report-table {
                width: 100%;
                border-collapse: collapse;
                margin-top: 15px;
            }
            .report-table th,
            .report-table td {
                padding: 12px;
                text-align: left;
                border-bottom: 1px solid #e0e0e0;
            }
            .report-table th {
                background: #214f2c;
                color: white;
                font-weight: 600;
            }
            .report-table tr:hover {
                background: #f9f9f9;
            }
            .report-summary {
                background: #f9f9f9;
                padding: 15px;
                border-radius: 4px;
                margin-bottom: 15px;
            }
            .report-summary h4 {
                margin: 0 0 10px 0;
                color: #214f2c;
            }
            .report-title {
                font-size: 18px;
                font-weight: 700;
                color: #214f2c;
                margin-bottom: 15px;
            }
            .btn-secondary {
                background: #6c757d;
                color: white;
                padding: 8px 16px;
                border: none;
                border-radius: 4px;
                cursor: pointer;
            }
            .empty-state {
                padding: 40px 20px;
                text-align: center;
                color: #999;
                font-size: 14px;
            }
            .error-state {
                padding: 40px 20px;
                text-align: center;
                color: #c62828;
                font-size: 14px;
                background: #ffebee;
                border-radius: 8px;
            }
        </style>

        <script>
            let currentReportData = null;
            let currentForecastData = null;

            function safeNumber(value) {
                const n = Number(value);
                return Number.isFinite(n) ? n : 0;
            }

            function calculateForecast(data) {
                const reservations = data.reservations || [];
                
                // Collect historical demand by date
                const dateCounts = {};
                reservations.forEach(r => {
                    const date = r.scheduledAt || r.createdAt || 'unknown';
                    const dateShort = date.substring(0, 10);
                    const qty = safeNumber(r.quantity);
                    dateCounts[dateShort] = (dateCounts[dateShort] || 0) + qty;
                });
                
                // Sort dates chronologically
                const sortedDates = Object.keys(dateCounts).sort();
                const historical = sortedDates.map(date => ({
                    date: date,
                    value: safeNumber(dateCounts[date])
                }));
                
                // Calculate forecast using simple linear regression
                let forecast = [];
                let nextForecast = 0;
                let trend = 'stable';
                let peak = null;
                
                if (historical.length >= 2) {
                    const n = historical.length;
                    const values = historical.map(d => safeNumber(d.value));
                    const sumX = (n * (n - 1)) / 2;
                    const sumY = values.reduce((a, b) => a + b, 0);
                    const sumXY = values.reduce((sum, y, i) => sum + i * y, 0);
                    const sumX2 = (n * (n - 1) * (2 * n - 1)) / 6;
                    
                    const denominator = n * sumX2 - sumX * sumX;
                    const slope = denominator !== 0 ? (n * sumXY - sumX * sumY) / denominator : 0;
                    const intercept = (sumY - slope * sumX) / n;
                    
                    // Calculate next period forecast
                    nextForecast = Math.max(0, Math.round(safeNumber(slope * n + intercept)));
                    
                    // Generate 6-month forecast
                    for (let i = 1; i <= 6; i++) {
                        const forecastValue = Math.max(0, Math.round(safeNumber(slope * (n + i) + intercept)));
                        forecast.push({
                            period: `Month +${i}`,
                            value: forecastValue
                        });
                    }
                    
                    // Determine trend
                    if (slope > 1) trend = 'up';
                    else if (slope < -1) trend = 'down';
                    
                    // Find peak
                    peak = historical.reduce((max, d) => d.value > max.value ? d : max, historical[0]);
                } else if (historical.length === 1) {
                    // Single data point - simple projection
                    nextForecast = safeNumber(historical[0].value);
                    for (let i = 1; i <= 6; i++) {
                        forecast.push({
                            period: `Month +${i}`,
                            value: nextForecast
                        });
                    }
                    peak = historical[0];
                }
                
                return {
                    historical,
                    forecast,
                    nextForecast,
                    trend,
                    peak
                };
            }

            function escapeCSV(value) {
                if (value === null || value === undefined) return '';
                const str = String(value);
                if (str.includes(',') || str.includes('"') || str.includes('\n')) {
                    return '"' + str.replace(/"/g, '""') + '"';
                }
                return str;
            }

            function loadReport() {
                const reportType = document.getElementById('report-type').value;
                const reportContent = document.getElementById('report-content');
                const loadingIndicator = document.getElementById('loading-indicator');
                
                // Show loading state
                loadingIndicator.style.display = 'inline';
                reportContent.innerHTML = '<div class="empty-state">Loading report data...</div>';
                
                fetch('/egg-farm-inventory-main1/owner_api.php')
                    .then(res => {
                        if (!res.ok) {
                            throw new Error(`HTTP error! status: ${res.status}`);
                        }
                        return res.json();
                    })
                    .then(data => {
                        if (!data || data.status !== 'success') {
                            throw new Error(data.message || 'Failed to load data');
                        }
                        
                        currentReportData = data;
                        
                        // Calculate forecast data for use in forecasting report
                        currentForecastData = calculateForecast(data);
                        
                        let html = '';
                        
                        switch(reportType) {
                            case 'inventory':
                                html = generateInventoryReport(data);
                                break;
                            case 'reservation':
                                html = generateReservationReport(data);
                                break;
                            case 'delivery':
                                html = generateDeliveryReport(data);
                                break;
                            case 'customer':
                                html = generateCustomerReport(data);
                                break;
                            case 'forecasting':
                                html = generateForecastingReport(currentForecastData);
                                break;
                        }
                        
                        reportContent.innerHTML = html;
                    })
                    .catch(error => {
                        console.error('Report generation error:', error);
                        reportContent.innerHTML = `
                            <div class="error-state">
                                <i class="fa-solid fa-exclamation-triangle"></i>
                                <p style="margin-top:10px;">Failed to generate report: ${error.message}</p>
                                <p style="font-size:12px; margin-top:5px;">Please try again or contact support if the issue persists.</p>
                            </div>
                        `;
                    })
                    .finally(() => {
                        loadingIndicator.style.display = 'none';
                    });
            }

            function generateInventoryReport(data) {
                const inventory = data.inventory || {};
                const eggs = inventory.eggs || [];
                const supplies = inventory.supplies || [];
                
                if (eggs.length === 0 && supplies.length === 0) {
                    return '<div class="empty-state">No inventory data available.</div>';
                }
                
                let html = '<div class="report-title">Inventory Report</div>';
                html += '<div class="report-summary"><h4>Inventory Summary</h4>';
                html += `<p>Egg Records: ${eggs.length} | Supply Records: ${supplies.length}</p>`;
                html += '</div>';
                
                if (eggs.length > 0) {
                    html += '<h4>Egg Inventory</h4>';
                    html += '<table class="report-table"><thead><tr><th>ID</th><th>Egg Size</th><th>Quantity</th><th>Current Stock</th><th>Movement</th></tr></thead><tbody>';
                    eggs.forEach(item => {
                        html += `<tr>
                            <td>${item.id || '—'}</td>
                            <td>${item.egg_size || '—'}</td>
                            <td>${item.quantity || '—'}</td>
                            <td>${item.current_stock || '—'}</td>
                            <td>${item.movement_type || '—'}</td>
                        </tr>`;
                    });
                    html += '</tbody></table>';
                }
                
                if (supplies.length > 0) {
                    html += '<h4>Supply Inventory</h4>';
                    html += '<table class="report-table"><thead><tr><th>ID</th><th>Category</th><th>Item</th><th>Quantity</th><th>Current Stock</th></tr></thead><tbody>';
                    supplies.forEach(item => {
                        html += `<tr>
                            <td>${item.id || '—'}</td>
                            <td>${item.item_category || '—'}</td>
                            <td>${item.item_name || '—'}</td>
                            <td>${item.quantity || '—'}</td>
                            <td>${item.current_stock || '—'}</td>
                        </tr>`;
                    });
                    html += '</tbody></table>';
                }
                
                return html;
            }

            function generateReservationReport(data) {
                const reservations = data.reservations || [];
                
                if (reservations.length === 0) {
                    return '<div class="empty-state">No reservations available.</div>';
                }
                
                let html = '<div class="report-title">Reservation Report</div>';
                html += '<div class="report-summary"><h4>Reservation Summary</h4>';
                html += `<p>Total Reservations: ${reservations.length}</p>`;
                html += '</div>';
                
                html += '<table class="report-table"><thead><tr><th>ID</th><th>Customer</th><th>Product</th><th>Quantity</th><th>Status</th><th>Date</th></tr></thead><tbody>';
                reservations.forEach(res => {
                    html += `<tr>
                        <td>${res.id || '—'}</td>
                        <td>${res.customerName || '—'}</td>
                        <td>${res.product || '—'}</td>
                        <td>${res.quantity || '—'}</td>
                        <td>${res.status || '—'}</td>
                        <td>${res.scheduledAt || '—'}</td>
                    </tr>`;
                });
                html += '</tbody></table>';
                return html;
            }

            function generateDeliveryReport(data) {
                const deliveries = data.deliveries || [];
                
                if (deliveries.length === 0) {
                    return '<div class="empty-state">No delivery records available.</div>';
                }
                
                let html = '<div class="report-title">Delivery Report</div>';
                html += '<div class="report-summary"><h4>Delivery Summary</h4>';
                html += `<p>Total Deliveries: ${deliveries.length}</p>`;
                html += '</div>';
                
                html += '<table class="report-table"><thead><tr><th>ID</th><th>Customer</th><th>Product</th><th>Quantity</th><th>Revenue</th><th>Status</th><th>Delivery Method</th><th>Created Date</th><th>Completed Date</th></tr></thead><tbody>';
                deliveries.forEach(del => {
                    html += `<tr>
                        <td>${del.id || '—'}</td>
                        <td>${del.customerName || '—'}</td>
                        <td>${del.product || '—'}</td>
                        <td>${del.quantity || '—'}</td>
                        <td>${del.revenue || '—'}</td>
                        <td>${del.status || '—'}</td>
                        <td>${del.route || '—'}</td>
                        <td>${del.createdAt || '—'}</td>
                        <td>${del.completedAt || '—'}</td>
                    </tr>`;
                });
                html += '</tbody></table>';
                return html;
            }

            function generateCustomerReport(data) {
                const users = data.users || [];
                
                if (users.length === 0) {
                    return '<div class="empty-state">No customers available.</div>';
                }
                
                let html = '<div class="report-title">Customer Report</div>';
                html += '<div class="report-summary"><h4>Customer Summary</h4>';
                html += `<p>Total Users: ${users.length}</p>`;
                html += '</div>';
                
                html += '<table class="report-table"><thead><tr><th>ID</th><th>Name</th><th>Email</th><th>Role</th><th>Status</th></tr></thead><tbody>';
                users.forEach(user => {
                    html += `<tr>
                        <td>${user.id || '—'}</td>
                        <td>${user.name || '—'}</td>
                        <td>${user.email || '—'}</td>
                        <td>${user.role || '—'}</td>
                        <td>${user.status || '—'}</td>
                    </tr>`;
                });
                html += '</tbody></table>';
                return html;
            }

            function generateForecastingReport(forecastData) {
                if (!forecastData || !forecastData.forecast || forecastData.forecast.length === 0) {
                    return '<div class="empty-state">No forecasting data available.</div>';
                }
                
                let html = '<div class="report-title">Forecasting Report</div>';
                html += '<div class="report-summary"><h4>Forecast Summary</h4>';
                html += `<p>Next Period Forecast: ${forecastData.nextForecast}</p>`;
                html += `<p>Trend: ${forecastData.trend.charAt(0).toUpperCase() + forecastData.trend.slice(1)}</p>`;
                html += `<p>Historical Periods: ${forecastData.historical.length}</p>`;
                html += '</div>';
                
                html += '<h4>Forecast Projections</h4>';
                html += '<table class="report-table"><thead><tr><th>Period</th><th>Forecast Demand</th><th>Trend</th></tr></thead><tbody>';
                forecastData.forecast.forEach(f => {
                    html += `<tr>
                        <td>${f.period}</td>
                        <td>${f.value}</td>
                        <td>${forecastData.trend.charAt(0).toUpperCase() + forecastData.trend.slice(1)}</td>
                    </tr>`;
                });
                html += '</tbody></table>';
                
                if (forecastData.historical.length > 0) {
                    html += '<h4>Historical Demand Summary</h4>';
                    html += '<table class="report-table"><thead><tr><th>Date</th><th>Demand</th></tr></thead><tbody>';
                    forecastData.historical.forEach(h => {
                        html += `<tr>
                            <td>${h.date}</td>
                            <td>${h.value}</td>
                        </tr>`;
                    });
                    html += '</tbody></table>';
                }
                
                return html;
            }

            function printReport() {
                if (!currentReportData) {
                    alert('No report data available to print. Please select a report type first.');
                    return;
                }

                const reportType = document.getElementById('report-type').value;
                const reportTitle = getReportTitle(reportType);
                const reportDate = new Date().toLocaleString();
                const ownerName = 'Owner'; // This would come from session in a real implementation

                // Build the professional print document
                let printContent = buildPrintDocument(reportType, reportTitle, reportDate, ownerName, currentReportData, currentForecastData);

                // Create a new window for printing
                const printWindow = window.open('', '_blank');
                if (!printWindow) {
                    alert('Please allow popups to print reports.');
                    return;
                }

                printWindow.document.write(`
                    <!DOCTYPE html>
                    <html>
                    <head>
                        <title>${reportTitle}</title>
                        <style>
                            @page {
                                size: A4 portrait;
                                margin: 15mm;
                                @top-left {
                                    content: "VDVC Egg Farm";
                                    font-family: 'Arial', sans-serif;
                                    font-size: 9pt;
                                    color: #214f2c;
                                }
                                @top-center {
                                    content: "${reportTitle}";
                                    font-family: 'Arial', sans-serif;
                                    font-size: 10pt;
                                    font-weight: bold;
                                    color: #333;
                                }
                                @top-right {
                                    content: "Generated: ${reportDate}";
                                    font-family: 'Arial', sans-serif;
                                    font-size: 8pt;
                                    color: #666;
                                }
                                @bottom-left {
                                    content: "Confidential — Owner Report";
                                    font-family: 'Arial', sans-serif;
                                    font-size: 8pt;
                                    color: #666;
                                }
                                @bottom-center {
                                    content: counter(page) " of " counter(pages);
                                    font-family: 'Arial', sans-serif;
                                    font-size: 9pt;
                                    color: #666;
                                }
                                @bottom-right {
                                    content: "VDVC Egg Farm";
                                    font-family: 'Arial', sans-serif;
                                    font-size: 9pt;
                                    color: #214f2c;
                                }
                            }
                            @media print {
                                body {
                                    margin: 0;
                                    padding: 0;
                                }
                                .print-table {
                                    page-break-inside: auto;
                                }
                                .print-table tr {
                                    page-break-inside: avoid;
                                    page-break-after: auto;
                                }
                                .print-table thead {
                                    display: table-header-group;
                                }
                                .print-table tfoot {
                                    display: table-footer-group;
                                }
                                .print-section {
                                    page-break-inside: avoid;
                                }
                            }
                            body {
                                font-family: 'Arial', 'Helvetica', sans-serif;
                                font-size: 10pt;
                                color: #333;
                                line-height: 1.5;
                                background: white;
                            }
                            .print-header {
                                text-align: center;
                                margin-bottom: 25px;
                                padding-bottom: 20px;
                                border-bottom: 3px solid #214f2c;
                            }
                            .print-logo {
                                max-height: 70px;
                                margin-bottom: 12px;
                            }
                            .print-title {
                                font-size: 22pt;
                                font-weight: bold;
                                color: #214f2c;
                                margin: 8px 0;
                                letter-spacing: 0.5px;
                            }
                            .print-subtitle {
                                font-size: 11pt;
                                color: #666;
                                margin: 5px 0;
                                font-weight: 500;
                            }
                            .print-metadata {
                                margin: 20px 0;
                                padding: 15px;
                                background: #f8f9fa;
                                border-left: 4px solid #214f2c;
                                border-radius: 4px;
                                font-size: 9pt;
                            }
                            .print-metadata-row {
                                display: flex;
                                justify-content: space-between;
                                margin: 8px 0;
                                padding: 4px 0;
                            }
                            .print-metadata-row strong {
                                color: #214f2c;
                            }
                            .print-summary {
                                margin: 25px 0;
                                padding: 20px;
                                background: #f8f9fa;
                                border: 1px solid #e9ecef;
                                border-radius: 6px;
                            }
                            .print-summary-title {
                                font-size: 13pt;
                                font-weight: bold;
                                color: #214f2c;
                                margin-bottom: 15px;
                                text-transform: uppercase;
                                letter-spacing: 1px;
                            }
                            .print-summary-grid {
                                display: grid;
                                grid-template-columns: repeat(4, 1fr);
                                gap: 15px;
                                margin-bottom: 15px;
                            }
                            .print-summary-item {
                                text-align: center;
                                padding: 15px;
                                background: white;
                                border: 1px solid #dee2e6;
                                border-radius: 6px;
                                box-shadow: 0 1px 3px rgba(0,0,0,0.05);
                            }
                            .print-summary-label {
                                font-size: 8pt;
                                color: #6c757d;
                                margin-bottom: 8px;
                                text-transform: uppercase;
                                letter-spacing: 0.5px;
                            }
                            .print-summary-value {
                                font-size: 18pt;
                                font-weight: bold;
                                color: #214f2c;
                            }
                            .print-section {
                                margin: 30px 0;
                                page-break-inside: avoid;
                            }
                            .print-section-title {
                                font-size: 14pt;
                                font-weight: bold;
                                color: #214f2c;
                                margin: 20px 0 15px 0;
                                padding-bottom: 8px;
                                border-bottom: 2px solid #214f2c;
                                text-transform: uppercase;
                                letter-spacing: 0.5px;
                            }
                            .print-table {
                                width: 100%;
                                border-collapse: collapse;
                                margin: 15px 0;
                                font-size: 9pt;
                                box-shadow: 0 1px 3px rgba(0,0,0,0.05);
                            }
                            .print-table th {
                                background: linear-gradient(135deg, #214f2c 0%, #527d59 100%);
                                color: white;
                                font-weight: bold;
                                padding: 12px 10px;
                                text-align: left;
                                border: 1px solid #1a3d22;
                                text-transform: uppercase;
                                font-size: 8pt;
                                letter-spacing: 0.5px;
                            }
                            .print-table td {
                                padding: 10px;
                                border: 1px solid #dee2e6;
                                text-align: left;
                            }
                            .print-table tr:nth-child(even) {
                                background: #f8f9fa;
                            }
                            .print-table tr:hover {
                                background: #e8f5e9;
                            }
                            .print-footer {
                                margin-top: 40px;
                                padding-top: 20px;
                                border-top: 2px solid #214f2c;
                                text-align: center;
                                font-size: 8pt;
                                color: #6c757d;
                            }
                            .print-footer div {
                                margin: 4px 0;
                            }
                            .print-empty {
                                text-align: center;
                                padding: 50px;
                                color: #999;
                                font-style: italic;
                                font-size: 11pt;
                            }
                            .trend-up { color: #28a745; font-weight: bold; }
                            .trend-down { color: #dc3545; font-weight: bold; }
                            .trend-stable { color: #6c757d; font-weight: bold; }
                        </style>
                    </head>
                    <body>
                        ${printContent}
                    </body>
                    </html>
                `);

                printWindow.document.close();
                printWindow.focus();
                
                // Wait for content to load then print
                setTimeout(() => {
                    printWindow.print();
                }, 250);
            }

            function getReportTitle(reportType) {
                const titles = {
                    'inventory': 'Inventory Report',
                    'reservation': 'Reservation Report',
                    'delivery': 'Delivery Report',
                    'customer': 'Customer Report',
                    'forecasting': 'Demand Forecasting Report'
                };
                return titles[reportType] || 'Report';
            }

            function buildPrintDocument(reportType, reportTitle, reportDate, ownerName, data, forecastData) {
                let content = '';

                // Header
                content += `
                    <div class="print-header">
                        <img src="../vdvc/vdvc_logo.png" alt="VDVC Egg Farm Logo" class="print-logo" onerror="this.style.display='none'">
                        <div class="print-title">VDVC EGG FARM</div>
                        <div class="print-subtitle">Official Farm Records</div>
                        <div class="print-title" style="margin-top: 18px; font-size: 16pt;">${reportTitle}</div>
                    </div>
                `;

                // Metadata
                content += `
                    <div class="print-metadata">
                        <div class="print-metadata-row">
                            <span><strong>Generated By:</strong> ${ownerName}</span>
                            <span><strong>Date Generated:</strong> ${reportDate}</span>
                        </div>
                        <div class="print-metadata-row">
                            <span><strong>Reporting Period:</strong> Current Available Data</span>
                        </div>
                    </div>
                `;

                // Report-specific content
                switch(reportType) {
                    case 'inventory':
                        content += buildInventoryPrintContent(data);
                        break;
                    case 'reservation':
                        content += buildReservationPrintContent(data);
                        break;
                    case 'delivery':
                        content += buildDeliveryPrintContent(data);
                        break;
                    case 'customer':
                        content += buildCustomerPrintContent(data);
                        break;
                    case 'forecasting':
                        content += buildForecastingPrintContent(forecastData);
                        break;
                }

                // Footer
                content += `
                    <div class="print-footer">
                        <div>VDVC Egg Farm</div>
                        <div>Generated: ${reportDate}</div>
                        <div style="margin-top: 5px;">Confidential — Owner Report</div>
                    </div>
                `;

                return content;
            }

            function buildInventoryPrintContent(data) {
                const inventory = data.inventory || {};
                const eggs = inventory.snapshotEggs || [];
                const supplies = inventory.snapshotSupplies || [];
                const historicalEggs = inventory.eggs || [];

                let content = '';

                // Summary
                const totalEggStock = eggs.reduce((sum, e) => sum + (parseInt(e.current_stock) || 0), 0);
                const totalSupplyStock = supplies.reduce((sum, s) => sum + (parseInt(s.current_stock) || 0), 0);
                const stockIn = historicalEggs.filter(e => e.movement_type === 'Stock In').length;
                const stockOut = historicalEggs.filter(e => e.movement_type === 'Stock Out').length;

                content += `
                    <div class="print-summary">
                        <div class="print-summary-title">Inventory Summary</div>
                        <div class="print-summary-grid">
                            <div class="print-summary-item">
                                <div class="print-summary-label">Total Egg Stock</div>
                                <div class="print-summary-value">${totalEggStock}</div>
                            </div>
                            <div class="print-summary-item">
                                <div class="print-summary-label">Total Supply Stock</div>
                                <div class="print-summary-value">${totalSupplyStock}</div>
                            </div>
                            <div class="print-summary-item">
                                <div class="print-summary-label">Egg Records</div>
                                <div class="print-summary-value">${eggs.length}</div>
                            </div>
                            <div class="print-summary-item">
                                <div class="print-summary-label">Supply Records</div>
                                <div class="print-summary-value">${supplies.length}</div>
                            </div>
                        </div>
                    </div>
                `;

                // Egg Inventory Table (Current Stock Snapshot)
                if (eggs.length > 0) {
                    content += `<div class="print-section-title">Current Egg Stock</div>`;
                    content += `<table class="print-table">
                        <thead>
                            <tr>
                                <th>Egg Size</th>
                                <th>Current Stock</th>
                                <th>Last Updated</th>
                            </tr>
                        </thead>
                        <tbody>`;
                    eggs.forEach(e => {
                        content += `<tr>
                            <td>${e.egg_size || '—'}</td>
                            <td>${e.current_stock || '—'}</td>
                            <td>${e.date_logged || '—'}</td>
                        </tr>`;
                    });
                    content += `</tbody></table>`;
                } else {
                    content += `<div class="print-empty">No egg inventory records available.</div>`;
                }

                // Supply Inventory Table
                if (supplies.length > 0) {
                    content += `<div class="print-section-title">Supply Inventory</div>`;
                    content += `<table class="print-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Batch ID</th>
                                <th>Category</th>
                                <th>Item Name</th>
                                <th>Quantity</th>
                                <th>Current Stock</th>
                                <th>Action Type</th>
                                <th>Date Logged</th>
                            </tr>
                        </thead>
                        <tbody>`;
                    supplies.forEach(s => {
                        content += `<tr>
                            <td>${s.id || '—'}</td>
                            <td>${s.batch_id || '—'}</td>
                            <td>${s.item_category || '—'}</td>
                            <td>${s.item_name || '—'}</td>
                            <td>${s.quantity || '—'}</td>
                            <td>${s.current_stock || '—'}</td>
                            <td>${s.action_type || '—'}</td>
                            <td>${s.date_logged || '—'}</td>
                        </tr>`;
                    });
                    content += `</tbody></table>`;
                } else {
                    content += `<div class="print-empty">No supply inventory records available.</div>`;
                }

                return content;
            }

            function buildReservationPrintContent(data) {
                const reservations = data.reservations || [];

                let content = '';

                // Summary
                const totalReservations = reservations.length;
                const pending = reservations.filter(r => r.status === 'pending').length;
                const confirmed = reservations.filter(r => r.status === 'confirmed').length;
                const completed = reservations.filter(r => r.status === 'completed').length;
                const cancelled = reservations.filter(r => r.status === 'cancelled').length;
                const totalQuantity = reservations.reduce((sum, r) => sum + (parseInt(r.quantity) || 0), 0);
                const totalRevenue = reservations.reduce((sum, r) => sum + (parseFloat(r.amount) || 0), 0);

                content += `
                    <div class="print-summary">
                        <div class="print-summary-title">Reservation Summary</div>
                        <div class="print-summary-grid">
                            <div class="print-summary-item">
                                <div class="print-summary-label">Total Reservations</div>
                                <div class="print-summary-value">${totalReservations}</div>
                            </div>
                            <div class="print-summary-item">
                                <div class="print-summary-label">Pending</div>
                                <div class="print-summary-value">${pending}</div>
                            </div>
                            <div class="print-summary-item">
                                <div class="print-summary-label">Confirmed</div>
                                <div class="print-summary-value">${confirmed}</div>
                            </div>
                            <div class="print-summary-item">
                                <div class="print-summary-label">Completed</div>
                                <div class="print-summary-value">${completed}</div>
                            </div>
                            <div class="print-summary-item">
                                <div class="print-summary-label">Cancelled</div>
                                <div class="print-summary-value">${cancelled}</div>
                            </div>
                            <div class="print-summary-item">
                                <div class="print-summary-label">Total Quantity</div>
                                <div class="print-summary-value">${totalQuantity}</div>
                            </div>
                            <div class="print-summary-item">
                                <div class="print-summary-label">Total Revenue</div>
                                <div class="print-summary-value">₱${totalRevenue.toLocaleString()}</div>
                            </div>
                        </div>
                    </div>
                `;

                // Detailed Table
                if (reservations.length > 0) {
                    content += `<div class="print-section-title">Reservation Details</div>`;
                    content += `<table class="print-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Reservation Code</th>
                                <th>Customer</th>
                                <th>Egg Type</th>
                                <th>Quantity</th>
                                <th>Delivery Method</th>
                                <th>Reservation Date</th>
                                <th>Status</th>
                                <th>Total Price</th>
                                <th>Created Date</th>
                            </tr>
                        </thead>
                        <tbody>`;
                    reservations.forEach(r => {
                        content += `<tr>
                            <td>${r.id || '—'}</td>
                            <td>${r.reservationCode || '—'}</td>
                            <td>${r.customerName || '—'}</td>
                            <td>${r.product || '—'}</td>
                            <td>${r.quantity || '—'}</td>
                            <td>${r.channel || '—'}</td>
                            <td>${r.scheduledAt || '—'}</td>
                            <td>${r.status || '—'}</td>
                            <td>₱${(r.amount || 0).toLocaleString()}</td>
                            <td>${r.createdAt || '—'}</td>
                        </tr>`;
                    });
                    content += `</tbody></table>`;
                } else {
                    content += `<div class="print-empty">No reservation records available.</div>`;
                }

                return content;
            }

            function buildDeliveryPrintContent(data) {
                const deliveries = data.deliveries || [];

                let content = '';

                // Summary
                const totalDeliveries = deliveries.length;
                const pending = deliveries.filter(d => d.status === 'pending').length;
                const confirmed = deliveries.filter(d => d.status === 'scheduled').length;
                const completed = deliveries.filter(d => d.status === 'completed').length;
                const cancelled = deliveries.filter(d => d.status === 'cancelled').length;
                const totalQuantity = deliveries.reduce((sum, d) => sum + (parseInt(d.quantity) || 0), 0);
                const totalRevenue = deliveries.reduce((sum, d) => sum + (parseFloat(d.revenue) || 0), 0);

                content += `
                    <div class="print-summary">
                        <div class="print-summary-title">Delivery Summary</div>
                        <div class="print-summary-grid">
                            <div class="print-summary-item">
                                <div class="print-summary-label">Total Deliveries</div>
                                <div class="print-summary-value">${totalDeliveries}</div>
                            </div>
                            <div class="print-summary-item">
                                <div class="print-summary-label">Pending</div>
                                <div class="print-summary-value">${pending}</div>
                            </div>
                            <div class="print-summary-item">
                                <div class="print-summary-label">Confirmed/Scheduled</div>
                                <div class="print-summary-value">${confirmed}</div>
                            </div>
                            <div class="print-summary-item">
                                <div class="print-summary-label">Completed</div>
                                <div class="print-summary-value">${completed}</div>
                            </div>
                            <div class="print-summary-item">
                                <div class="print-summary-label">Cancelled</div>
                                <div class="print-summary-value">${cancelled}</div>
                            </div>
                            <div class="print-summary-item">
                                <div class="print-summary-label">Total Quantity</div>
                                <div class="print-summary-value">${totalQuantity}</div>
                            </div>
                            <div class="print-summary-item">
                                <div class="print-summary-label">Total Revenue</div>
                                <div class="print-summary-value">₱${totalRevenue.toLocaleString()}</div>
                            </div>
                        </div>
                    </div>
                `;

                // Detailed Table
                if (deliveries.length > 0) {
                    content += `<div class="print-section-title">Delivery Details</div>`;
                    content += `<table class="print-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Customer</th>
                                <th>Product</th>
                                <th>Quantity</th>
                                <th>Revenue</th>
                                <th>Status</th>
                                <th>Delivery Method</th>
                                <th>Created Date</th>
                                <th>Completed Date</th>
                            </tr>
                        </thead>
                        <tbody>`;
                    deliveries.forEach(d => {
                        content += `<tr>
                            <td>${d.id || '—'}</td>
                            <td>${d.customerName || '—'}</td>
                            <td>${d.product || '—'}</td>
                            <td>${d.quantity || '—'}</td>
                            <td>₱${(d.revenue || 0).toLocaleString()}</td>
                            <td>${d.status || '—'}</td>
                            <td>${d.route || '—'}</td>
                            <td>${d.createdAt || '—'}</td>
                            <td>${d.completedAt || '—'}</td>
                        </tr>`;
                    });
                    content += `</tbody></table>`;
                } else {
                    content += `<div class="print-empty">No delivery records available.</div>`;
                }

                return content;
            }

            function buildCustomerPrintContent(data) {
                const users = data.users || [];

                let content = '';

                // Summary
                const totalCustomers = users.length;
                const active = users.filter(u => u.status === 'active').length;
                const inactive = users.filter(u => u.status === 'deactivated').length;

                content += `
                    <div class="print-summary">
                        <div class="print-summary-title">Customer Summary</div>
                        <div class="print-summary-grid">
                            <div class="print-summary-item">
                                <div class="print-summary-label">Total Customers</div>
                                <div class="print-summary-value">${totalCustomers}</div>
                            </div>
                            <div class="print-summary-item">
                                <div class="print-summary-label">Active Accounts</div>
                                <div class="print-summary-value">${active}</div>
                            </div>
                            <div class="print-summary-item">
                                <div class="print-summary-label">Inactive Accounts</div>
                                <div class="print-summary-value">${inactive}</div>
                            </div>
                        </div>
                    </div>
                `;

                // Detailed Table
                if (users.length > 0) {
                    content += `<div class="print-section-title">Customer Details</div>`;
                    content += `<table class="print-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Full Name</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>`;
                    users.forEach(u => {
                        content += `<tr>
                            <td>${u.id || '—'}</td>
                            <td>${u.name || '—'}</td>
                            <td>${u.email || '—'}</td>
                            <td>${u.role || '—'}</td>
                            <td>${u.status || '—'}</td>
                        </tr>`;
                    });
                    content += `</tbody></table>`;
                } else {
                    content += `<div class="print-empty">No customer records available.</div>`;
                }

                return content;
            }

            function buildForecastingPrintContent(forecastData) {
                if (!forecastData || !forecastData.forecast || forecastData.forecast.length === 0) {
                    return `<div class="print-empty">No forecasting data available.</div>`;
                }

                let content = '';

                // Summary
                const trendClass = forecastData.trend === 'up' ? 'trend-up' : (forecastData.trend === 'down' ? 'trend-down' : 'trend-stable');
                const trendLabel = forecastData.trend.charAt(0).toUpperCase() + forecastData.trend.slice(1);

                content += `
                    <div class="print-summary">
                        <div class="print-summary-title">Forecast Summary</div>
                        <div class="print-summary-grid">
                            <div class="print-summary-item">
                                <div class="print-summary-label">Next Period Forecast</div>
                                <div class="print-summary-value">${forecastData.nextForecast}</div>
                            </div>
                            <div class="print-summary-item">
                                <div class="print-summary-label">Demand Trend</div>
                                <div class="print-summary-value ${trendClass}">${trendLabel}</div>
                            </div>
                            <div class="print-summary-item">
                                <div class="print-summary-label">Historical Periods</div>
                                <div class="print-summary-value">${forecastData.historical.length}</div>
                            </div>
                            <div class="print-summary-item">
                                <div class="print-summary-label">Forecast Periods</div>
                                <div class="print-summary-value">${forecastData.forecast.length}</div>
                            </div>
                        </div>
                    </div>
                `;

                // Forecast Table
                content += `<div class="print-section-title">Forecast Projections</div>`;
                content += `<table class="print-table">
                    <thead>
                        <tr>
                            <th>Period</th>
                            <th>Forecast Demand</th>
                            <th>Trend</th>
                        </tr>
                    </thead>
                    <tbody>`;
                forecastData.forecast.forEach(f => {
                    content += `<tr>
                        <td>${f.period}</td>
                        <td>${f.value}</td>
                        <td class="${trendClass}">${trendLabel}</td>
                    </tr>`;
                });
                content += `</tbody></table>`;

                // Historical Table
                if (forecastData.historical.length > 0) {
                    content += `<div class="print-section-title">Historical Demand</div>`;
                    content += `<table class="print-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Demand</th>
                            </tr>
                        </thead>
                        <tbody>`;
                    forecastData.historical.forEach(h => {
                        content += `<tr>
                            <td>${h.date}</td>
                            <td>${h.value}</td>
                        </tr>`;
                    });
                    content += `</tbody></table>`;
                }

                return content;
            }

            function exportCSV() {
                if (!currentReportData) {
                    alert('Please generate a report first.');
                    return;
                }
                
                const reportType = document.getElementById('report-type').value;
                let csv = '';
                
                switch(reportType) {
                    case 'inventory':
                        csv = 'ID,Egg Size,Quantity,Current Stock,Movement\n';
                        (currentReportData.inventory?.eggs || []).forEach(item => {
                            csv += `${escapeCSV(item.id)},${escapeCSV(item.egg_size)},${escapeCSV(item.quantity)},${escapeCSV(item.current_stock)},${escapeCSV(item.movement_type)}\n`;
                        });
                        csv += '\nSupply Inventory\n';
                        csv += 'ID,Category,Item,Quantity,Current Stock\n';
                        (currentReportData.inventory?.supplies || []).forEach(item => {
                            csv += `${escapeCSV(item.id)},${escapeCSV(item.item_category)},${escapeCSV(item.item_name)},${escapeCSV(item.quantity)},${escapeCSV(item.current_stock)}\n`;
                        });
                        break;
                    case 'reservation':
                        csv = 'ID,Customer,Product,Quantity,Status,Date\n';
                        (currentReportData.reservations || []).forEach(res => {
                            csv += `${escapeCSV(res.id)},${escapeCSV(res.customerName)},${escapeCSV(res.product)},${escapeCSV(res.quantity)},${escapeCSV(res.status)},${escapeCSV(res.scheduledAt)}\n`;
                        });
                        break;
                    case 'delivery':
                        csv = 'ID,Customer,Product,Quantity,Revenue,Status,Delivery Method,Created Date,Completed Date\n';
                        (currentReportData.deliveries || []).forEach(del => {
                            csv += `${escapeCSV(del.id)},${escapeCSV(del.customerName)},${escapeCSV(del.product)},${escapeCSV(del.quantity)},${escapeCSV(del.revenue)},${escapeCSV(del.status)},${escapeCSV(del.route)},${escapeCSV(del.createdAt)},${escapeCSV(del.completedAt)}\n`;
                        });
                        break;
                    case 'customer':
                        csv = 'ID,Name,Email,Role,Status\n';
                        (currentReportData.users || []).forEach(user => {
                            csv += `${escapeCSV(user.id)},${escapeCSV(user.name)},${escapeCSV(user.email)},${escapeCSV(user.role)},${escapeCSV(user.status)}\n`;
                        });
                        break;
                    case 'forecasting':
                        if (!currentForecastData || !currentForecastData.forecast) {
                            alert('No forecasting data available to export.');
                            return;
                        }
                        csv = 'Period,Forecast Demand,Trend\n';
                        currentForecastData.forecast.forEach(f => {
                            csv += `${escapeCSV(f.period)},${escapeCSV(f.value)},${escapeCSV(currentForecastData.trend.charAt(0).toUpperCase() + currentForecastData.trend.slice(1))}\n`;
                        });
                        csv += '\nHistorical Demand\n';
                        csv += 'Date,Demand\n';
                        currentForecastData.historical.forEach(h => {
                            csv += `${escapeCSV(h.date)},${escapeCSV(h.value)}\n`;
                        });
                        break;
                }
                
                const blob = new Blob([csv], { type: 'text/csv' });
                const url = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = `${reportType}_report.csv`;
                a.click();
                window.URL.revokeObjectURL(url);
            }

            // Load default report on page load
            loadReport();
        </script>

    <?php elseif ($page == 'profile'): ?>
        <?php 
        require_once 'session_config.php';
        include 'owner_content_profile.php'; 
        ?>

    <?php else: ?>
        <div class="page">
            <div class="page-shell">
                <div class="page-subtitle">Page Not Found</div>
                <p>The requested page does not exist.</p>
            </div>
        </div>
    <?php endif; ?>

<?php if (!$is_included) { ?>
    <script>
        // Set the inventory API URL before app_analytics.js loads.
        // dirname(PHP_SELF) gives the web-root-relative folder this script lives in,
        // so the path works regardless of the Laragon project folder name.
        window.VDVC_INVENTORY_API_URL =
            '<?php echo rtrim(dirname($_SERVER['PHP_SELF']), '/\\'); ?>/inventory_api.php';
    </script>
    <script src="vdvc/app_analytics.js"></script>
</body>
</html>
<?php } ?>
