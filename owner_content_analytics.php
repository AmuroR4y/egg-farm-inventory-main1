<div id="page-analytics" class="page">
    <div class="page-shell">
        <div class="page-subtitle">Data Analytics</div>
        <div style="font-size:13px;font-weight:700;color:var(--sidebar);margin-bottom:12px;">Performance Analytics Dashboard</div>
        <div class="analytics-container">
            <div class="panel">
                <div class="panel-title">Farm Performance Metrics</div>
                <p style="font-size:11.5px;color:var(--text-soft);margin-bottom:16px;line-height:1.5;">View detailed analytics across inventory, reservations, and operations.</p>
                
                <div id="analytics-loading" style="text-align:center;padding:40px;">Loading analytics data...</div>
                
                <div id="analytics-content" style="display:none;">
                    <!-- KPI Summary -->
                    <div class="kpi-grid">
                        <div class="kpi-card">
                            <div class="kpi-label">Total Users</div>
                            <div class="kpi-value" id="kpi-users">0</div>
                        </div>
                        <div class="kpi-card">
                            <div class="kpi-label">Total Reservations</div>
                            <div class="kpi-value" id="kpi-reservations">0</div>
                        </div>
                        <div class="kpi-card">
                            <div class="kpi-label">Active Reservations</div>
                            <div class="kpi-value" id="kpi-active-reservations">0</div>
                        </div>
                        <div class="kpi-card">
                            <div class="kpi-label">Completed Reservations</div>
                            <div class="kpi-value" id="kpi-completed-reservations">0</div>
                        </div>
                        <div class="kpi-card">
                            <div class="kpi-label">Total Egg Inventory</div>
                            <div class="kpi-value" id="kpi-egg-inventory">0</div>
                        </div>
                        <div class="kpi-card">
                            <div class="kpi-label">Total Supply Inventory</div>
                            <div class="kpi-value" id="kpi-supply-inventory">0</div>
                        </div>
                        <div class="kpi-card">
                            <div class="kpi-label">Total Deliveries</div>
                            <div class="kpi-value" id="kpi-deliveries">0</div>
                        </div>
                        <div class="kpi-card">
                            <div class="kpi-label">Completed Deliveries</div>
                            <div class="kpi-value" id="kpi-completed-deliveries">0</div>
                        </div>
                    </div>

                    <!-- Reservation Analytics -->
                    <div class="analytics-section">
                        <h3>Reservation Activity</h3>
                        <div class="chart-container">
                            <svg id="reservation-chart" viewBox="0 0 600 200" style="width:100%;height:200px;"></svg>
                        </div>
                    </div>

                    <!-- Reservation Status -->
                    <div class="analytics-section">
                        <h3>Reservation Status Distribution</h3>
                        <div class="chart-container" style="display:flex;justify-content:center;align-items:center;">
                            <svg id="reservation-status-chart" viewBox="0 0 400 300" style="width:400px;height:300px;"></svg>
                        </div>
                        <div class="status-legend" id="reservation-status-legend"></div>
                    </div>

                    <!-- Inventory Analytics -->
                    <div class="analytics-section">
                        <h3>Inventory Analytics</h3>
                        <div class="inventory-summary" id="inventory-summary"></div>
                        <div class="chart-container">
                            <svg id="inventory-chart" viewBox="0 0 600 200" style="width:100%;height:200px;"></svg>
                        </div>
                    </div>

                    <!-- Stock Movement -->
                    <div class="analytics-section">
                        <h3>Stock Movement</h3>
                        <div class="chart-container">
                            <svg id="stock-movement-chart" viewBox="0 0 600 200" style="width:100%;height:200px;"></svg>
                        </div>
                    </div>

                    <!-- Delivery Analytics -->
                    <div class="analytics-section">
                        <h3>Delivery Status Distribution</h3>
                        <div class="chart-container" style="display:flex;justify-content:center;align-items:center;">
                            <svg id="delivery-status-chart" viewBox="0 0 400 300" style="width:400px;height:300px;"></svg>
                        </div>
                        <div class="status-legend" id="delivery-status-legend"></div>
                    </div>

                    <!-- Egg/Product Analytics -->
                    <div class="analytics-section">
                        <h3>Egg Size Distribution</h3>
                        <div class="chart-container" style="display:flex;justify-content:center;align-items:center;">
                            <svg id="egg-distribution-chart" viewBox="0 0 400 300" style="width:400px;height:300px;"></svg>
                        </div>
                        <div class="status-legend" id="egg-distribution-legend"></div>
                    </div>

                    <!-- Supply Analytics -->
                    <div class="analytics-section">
                        <h3>Supply Inventory</h3>
                        <div class="chart-container">
                            <svg id="supply-chart" viewBox="0 0 600 200" style="width:100%;height:200px;"></svg>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .kpi-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }
    .kpi-card {
        background: white;
        padding: 20px;
        border-radius: 8px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }
    .kpi-label {
        font-size: 14px;
        color: #666;
        margin-bottom: 8px;
    }
    .kpi-value {
        font-size: 32px;
        font-weight: bold;
        color: #214f2c;
    }
    .analytics-section {
        background: white;
        padding: 24px;
        border-radius: 12px;
        margin-bottom: 24px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    }
    .analytics-section h3 {
        margin-top: 0;
        color: #214f2c;
        font-size: 18px;
        font-weight: 600;
    }
    .chart-container {
        margin: 24px 0;
        min-height: 220px;
        background: #fafafa;
        border-radius: 8px;
        padding: 16px;
    }
    .chart-container svg {
        display: block;
        width: 100%;
        height: auto;
    }
    .status-breakdown, .inventory-summary, .delivery-summary, .product-insights, .supply-insights {
        margin-top: 20px;
    }
    .status-legend {
        display: flex;
        flex-wrap: wrap;
        gap: 16px;
        margin-top: 20px;
        justify-content: center;
    }
    .legend-item {
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 13px;
        color: #555;
    }
    .legend-color {
        width: 16px;
        height: 16px;
        border-radius: 4px;
    }
    .status-item {
        display: flex;
        justify-content: space-between;
        padding: 14px 16px;
        border-bottom: 1px solid #f0f0f0;
        background: white;
    }
    .status-item:first-child {
        border-top-left-radius: 8px;
        border-top-right-radius: 8px;
    }
    .status-item:last-child {
        border-bottom: none;
        border-bottom-left-radius: 8px;
        border-bottom-right-radius: 8px;
    }
    .bar-chart {
        display: flex;
        align-items: center;
        margin: 12px 0;
        padding: 8px 0;
    }
    .bar-label {
        width: 120px;
        font-size: 13px;
        color: #555;
        font-weight: 500;
    }
    .bar-track {
        flex: 1;
        background: #f0f0f0;
        height: 28px;
        border-radius: 14px;
        overflow: hidden;
        margin: 0 12px;
    }
    .bar-fill {
        height: 100%;
        background: linear-gradient(135deg, #214f2c 0%, #527d59 100%);
        border-radius: 14px;
        transition: width 0.4s ease;
    }
    .bar-value {
        width: 60px;
        text-align: right;
        font-weight: 600;
        color: #214f2c;
        font-size: 14px;
    }
</style>

<script>
(function() {
    function safeNumber(value) {
        const n = Number(value);
        return Number.isFinite(n) ? n : 0;
    }

    function loadAnalytics() {
        fetch('/egg-farm-inventory-main1/owner_api.php')
            .then(res => res.json())
            .then(data => {
                document.getElementById('analytics-loading').style.display = 'none';
                document.getElementById('analytics-content').style.display = 'block';
                
                renderKPIs(data);
                renderReservationAnalytics(data);
                renderInventoryAnalytics(data);
                renderStockMovement(data);
                renderDeliveryAnalytics(data);
                renderEggDistribution(data);
                renderSupplyDistribution(data);
            })
            .catch(error => {
                console.error('[ANALYTICS ERROR]', error);
                document.getElementById('analytics-loading').innerHTML = 'Error loading analytics data.';
            });
    }

    function renderKPIs(data) {
        const users = data.users || [];
        const reservations = data.reservations || [];
        const deliveries = data.deliveries || [];
        const inventory = data.inventory || {};
        const eggs = inventory.eggs || [];
        const supplies = inventory.supplies || [];

        document.getElementById('kpi-users').textContent = users.length;
        document.getElementById('kpi-reservations').textContent = reservations.length;
        document.getElementById('kpi-active-reservations').textContent = 
            reservations.filter(r => r.status === 'confirmed' || r.status === 'pending').length;
        document.getElementById('kpi-completed-reservations').textContent = 
            reservations.filter(r => r.status === 'completed').length;
        
        const totalEggQty = eggs.reduce((sum, e) => sum + safeNumber(e.quantity), 0);
        const totalSupplyQty = supplies.reduce((sum, s) => sum + safeNumber(s.quantity), 0);
        
        document.getElementById('kpi-egg-inventory').textContent = totalEggQty;
        document.getElementById('kpi-supply-inventory').textContent = totalSupplyQty;
        document.getElementById('kpi-deliveries').textContent = deliveries.length;
        document.getElementById('kpi-completed-deliveries').textContent = 
            deliveries.filter(d => d.status === 'completed').length;
    }

    function renderReservationAnalytics(data) {
        const reservations = data.reservations || [];
        
        // Status breakdown - Donut chart
        const statusCounts = {};
        reservations.forEach(r => {
            const status = r.status || 'unknown';
            statusCounts[status] = (statusCounts[status] || 0) + 1;
        });
        
        renderDonutChart('reservation-status-chart', statusCounts, 'reservation-status-legend');
        
        // Modern SVG chart showing reservation trend by date - Bar chart
        const svg = document.getElementById('reservation-chart');
        
        if (reservations.length > 0) {
            const dateCounts = {};
            reservations.forEach(r => {
                const date = r.scheduledAt || r.createdAt || 'unknown';
                const dateShort = date.substring(0, 10);
                dateCounts[dateShort] = (dateCounts[dateShort] || 0) + 1;
            });
            
            const dates = Object.keys(dateCounts).sort();
            const values = Object.values(dateCounts).map(safeNumber);
            const maxCount = Math.max(...values, 1);
            const width = 600;
            const height = 200;
            const padding = { top: 20, right: 20, bottom: 40, left: 40 };
            const chartWidth = width - padding.left - padding.right;
            const chartHeight = height - padding.top - padding.bottom;
            const barWidth = Math.max((chartWidth / dates.length) * 0.6, 20);
            const barGap = (chartWidth - (barWidth * dates.length)) / (dates.length + 1);
            
            let svgContent = '';
            
            // Grid lines
            for (let i = 0; i <= 5; i++) {
                const y = padding.top + (chartHeight * i / 5);
                svgContent += `<line x1="${padding.left}" y1="${y}" x2="${width - padding.right}" y2="${y}" stroke="#e0e0e0" stroke-width="1" stroke-dasharray="4"/>`;
            }
            
            // Bars
            dates.forEach((date, i) => {
                const count = safeNumber(dateCounts[date]);
                const barHeight = (count / maxCount) * chartHeight;
                const x = padding.left + barGap + i * (barWidth + barGap);
                const y = padding.top + chartHeight - barHeight;
                
                svgContent += `
                    <rect x="${x}" y="${y}" width="${barWidth}" height="${barHeight}" fill="url(#barGradient)" rx="4" ry="4"/>
                    <text x="${x + barWidth/2}" y="${height - padding.bottom + 15}" font-size="11" text-anchor="middle" fill="#666">${date.substring(5)}</text>
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
            
            svg.innerHTML = svgContent;
        } else {
            svg.innerHTML = '<text x="300" y="100" text-anchor="middle" font-size="14" fill="#666">No reservation data available</text>';
        }
    }

    function renderInventoryAnalytics(data) {
        const inventory = data.inventory || {};
        const eggs = inventory.eggs || [];
        const supplies = inventory.supplies || [];
        
        let totalEggQty = 0;
        let totalSupplyQty = 0;
        
        eggs.forEach(e => {
            totalEggQty += safeNumber(e.quantity);
        });
        supplies.forEach(s => {
            totalSupplyQty += safeNumber(s.quantity);
        });
        
        document.getElementById('inventory-summary').innerHTML = `
            <div class="status-item">
                <span>Total Egg Quantity</span>
                <strong>${totalEggQty}</strong>
            </div>
            <div class="status-item">
                <span>Total Supply Quantity</span>
                <strong>${totalSupplyQty}</strong>
            </div>
            <div class="status-item">
                <span>Egg Records</span>
                <strong>${eggs.length}</strong>
            </div>
            <div class="status-item">
                <span>Supply Records</span>
                <strong>${supplies.length}</strong>
            </div>
        `;
        
        // Egg size breakdown chart with modern styling - Bar chart
        const sizeCounts = {};
        eggs.forEach(e => {
            const size = e.egg_size || 'unknown';
            sizeCounts[size] = (sizeCounts[size] || 0) + safeNumber(e.quantity);
        });
        
        const svg = document.getElementById('inventory-chart');
        
        if (Object.keys(sizeCounts).length > 0) {
            const values = Object.values(sizeCounts).map(safeNumber);
            const maxCount = Math.max(...values, 1);
            const width = 600;
            const height = 200;
            const padding = { top: 20, right: 20, bottom: 70, left: 40 };
            const chartWidth = width - padding.left - padding.right;
            const chartHeight = height - padding.top - padding.bottom;
            const barWidth = Math.max((chartWidth / Object.keys(sizeCounts).length) * 0.6, 30);
            const barGap = (chartWidth - (barWidth * Object.keys(sizeCounts).length)) / (Object.keys(sizeCounts).length + 1);

            let svgContent = '';

            // Grid lines
            for (let i = 0; i <= 5; i++) {
                const y = padding.top + (chartHeight * i / 5);
                svgContent += `<line x1="${padding.left}" y1="${y}" x2="${width - padding.right}" y2="${y}" stroke="#e0e0e0" stroke-width="1" stroke-dasharray="4"/>`;
            }

            // Bars
            Object.entries(sizeCounts).forEach(([size, count], i) => {
                const countSafe = safeNumber(count);
                const barHeight = (countSafe / maxCount) * chartHeight;
                const x = padding.left + barGap + i * (barWidth + barGap);
                const y = padding.top + chartHeight - barHeight;

                // Split long labels into two lines
                const labelParts = size.split(' ');
                let labelText = '';
                if (labelParts.length > 1) {
                    labelText = `<tspan x="${x + barWidth/2}" dy="0" font-size="11" text-anchor="middle" fill="#666">${labelParts[0]}</tspan>`;
                    labelText += `<tspan x="${x + barWidth/2}" dy="14" font-size="11" text-anchor="middle" fill="#666">${labelParts.slice(1).join(' ')}</tspan>`;
                } else {
                    labelText = `<tspan x="${x + barWidth/2}" dy="7" font-size="11" text-anchor="middle" fill="#666">${size}</tspan>`;
                }

                svgContent += `
                    <rect x="${x}" y="${y}" width="${barWidth}" height="${barHeight}" fill="url(#barGradientInv)" rx="4" ry="4"/>
                    <text x="${x + barWidth/2}" y="${height - padding.bottom + 10}">${labelText}</text>
                    <text x="${x + barWidth/2}" y="${y - 8}" font-size="12" text-anchor="middle" fill="#527d59" font-weight="600">${countSafe}</text>
                `;
            });
            
            // Gradient definition
            svgContent = `
                <defs>
                    <linearGradient id="barGradientInv" x1="0%" y1="0%" x2="0%" y2="100%">
                        <stop offset="0%" style="stop-color:#527d59;stop-opacity:1" />
                        <stop offset="100%" style="stop-color:#7a9e7f;stop-opacity:1" />
                    </linearGradient>
                </defs>
            ` + svgContent;
            
            svg.innerHTML = svgContent;
        } else {
            svg.innerHTML = '<text x="300" y="100" text-anchor="middle" font-size="14" fill="#666">No inventory data available</text>';
        }
    }

    function renderStockMovement(data) {
        const inventory = data.inventory || {};
        const eggs = inventory.eggs || [];
        
        const stockIn = {};
        const stockOut = {};
        
        eggs.forEach(e => {
            const size = e.egg_size || 'unknown';
            const qty = safeNumber(e.quantity);
            const movement = e.movement_type || 'unknown';
            
            if (movement.toLowerCase().includes('in') || movement.toLowerCase().includes('add')) {
                stockIn[size] = (stockIn[size] || 0) + qty;
            } else if (movement.toLowerCase().includes('out') || movement.toLowerCase().includes('remove')) {
                stockOut[size] = (stockOut[size] || 0) + qty;
            }
        });
        
        const allSizes = [...new Set([...Object.keys(stockIn), ...Object.keys(stockOut)])];
        const svg = document.getElementById('stock-movement-chart');
        
        if (allSizes.length > 0) {
            const allValues = [...Object.values(stockIn), ...Object.values(stockOut)].map(safeNumber);
            const maxValue = Math.max(...allValues, 1);
            const width = 600;
            const height = 200;
            const padding = { top: 35, right: 20, bottom: 40, left: 40 };
            const chartWidth = width - padding.left - padding.right;
            const chartHeight = height - padding.top - padding.bottom;
            const groupWidth = chartWidth / allSizes.length;
            const barWidth = Math.max(groupWidth * 0.3, 15);
            
            let svgContent = '';
            
            // Grid lines
            for (let i = 0; i <= 5; i++) {
                const y = padding.top + (chartHeight * i / 5);
                svgContent += `<line x1="${padding.left}" y1="${y}" x2="${width - padding.right}" y2="${y}" stroke="#e0e0e0" stroke-width="1" stroke-dasharray="4"/>`;
            }
            
            // Grouped bars
            allSizes.forEach((size, i) => {
                const inVal = safeNumber(stockIn[size] || 0);
                const outVal = safeNumber(stockOut[size] || 0);
                const groupX = padding.left + i * groupWidth + groupWidth / 2;
                
                const inHeight = (inVal / maxValue) * chartHeight;
                const outHeight = (outVal / maxValue) * chartHeight;
                
                // Wrap long labels into two lines
                const labelParts = size.split(' ');
                let labelText = '';
                if (labelParts.length > 1 && size.length > 8) {
                    labelText = `<tspan x="${groupX}" dy="0" font-size="10" text-anchor="middle" fill="#666">${labelParts[0]}</tspan><tspan x="${groupX}" dy="12" font-size="10" text-anchor="middle" fill="#666">${labelParts.slice(1).join(' ')}</tspan>`;
                } else {
                    labelText = `<tspan x="${groupX}" dy="0" font-size="10" text-anchor="middle" fill="#666">${size}</tspan>`;
                }
                
                svgContent += `
                    <rect x="${groupX - barWidth - 2}" y="${padding.top + chartHeight - inHeight}" width="${barWidth}" height="${inHeight}" fill="#28a745" rx="3" ry="3"/>
                    <rect x="${groupX + 2}" y="${padding.top + chartHeight - outHeight}" width="${barWidth}" height="${outHeight}" fill="#dc3545" rx="3" ry="3"/>
                    <text x="${groupX}" y="${height - padding.bottom + 10}">${labelText}</text>
                `;
            });
            
            // Legend - positioned above the plotting area
            svgContent += `
                <rect x="${width - 140}" y="8" width="12" height="12" fill="#28a745" rx="2"/>
                <text x="${width - 120}" y="18" font-size="11" fill="#333">Stock In</text>
                <rect x="${width - 40}" y="8" width="12" height="12" fill="#dc3545" rx="2"/>
                <text x="${width - 20}" y="18" font-size="11" fill="#333">Stock Out</text>
            `;
            
            svg.innerHTML = svgContent;
        } else {
            svg.innerHTML = '<text x="300" y="100" text-anchor="middle" font-size="14" fill="#666">No stock movement data available</text>';
        }
    }

    function renderDeliveryAnalytics(data) {
        const deliveries = data.deliveries || [];
        
        const statusCounts = {};
        deliveries.forEach(d => {
            const status = d.status || 'unknown';
            statusCounts[status] = (statusCounts[status] || 0) + 1;
        });
        
        renderDonutChart('delivery-status-chart', statusCounts, 'delivery-status-legend');
    }

    function renderEggDistribution(data) {
        const inventory = data.inventory || {};
        const eggs = inventory.eggs || [];
        
        const sizeCounts = {};
        eggs.forEach(e => {
            const size = e.egg_size || 'unknown';
            sizeCounts[size] = (sizeCounts[size] || 0) + safeNumber(e.quantity);
        });
        
        renderDonutChart('egg-distribution-chart', sizeCounts, 'egg-distribution-legend');
    }

    function renderSupplyDistribution(data) {
        const inventory = data.inventory || {};
        const supplies = inventory.supplies || [];
        
        const categoryCounts = {};
        supplies.forEach(s => {
            const category = s.item_category || 'unknown';
            categoryCounts[category] = (categoryCounts[category] || 0) + safeNumber(s.quantity);
        });
        
        const svg = document.getElementById('supply-chart');
        
        if (Object.keys(categoryCounts).length > 0) {
            const values = Object.values(categoryCounts).map(safeNumber);
            const maxValue = Math.max(...values, 1);
            const width = 600;
            const height = 200;
            const padding = { top: 20, right: 20, bottom: 40, left: 40 };
            const chartWidth = width - padding.left - padding.right;
            const chartHeight = height - padding.top - padding.bottom;
            const barWidth = Math.max((chartWidth / Object.keys(categoryCounts).length) * 0.6, 30);
            const barGap = (chartWidth - (barWidth * Object.keys(categoryCounts).length)) / (Object.keys(categoryCounts).length + 1);
            
            let svgContent = '';
            
            // Grid lines
            for (let i = 0; i <= 5; i++) {
                const y = padding.top + (chartHeight * i / 5);
                svgContent += `<line x1="${padding.left}" y1="${y}" x2="${width - padding.right}" y2="${y}" stroke="#e0e0e0" stroke-width="1" stroke-dasharray="4"/>`;
            }
            
            // Bars
            Object.entries(categoryCounts).forEach(([category, count], i) => {
                const countSafe = safeNumber(count);
                const barHeight = (countSafe / maxValue) * chartHeight;
                const x = padding.left + barGap + i * (barWidth + barGap);
                const y = padding.top + chartHeight - barHeight;
                
                svgContent += `
                    <rect x="${x}" y="${y}" width="${barWidth}" height="${barHeight}" fill="url(#barGradientSup)" rx="4" ry="4"/>
                    <text x="${x + barWidth/2}" y="${height - padding.bottom + 15}" font-size="11" text-anchor="middle" fill="#666">${category}</text>
                    <text x="${x + barWidth/2}" y="${y - 8}" font-size="12" text-anchor="middle" fill="#527d59" font-weight="600">${countSafe}</text>
                `;
            });
            
            // Gradient definition
            svgContent = `
                <defs>
                    <linearGradient id="barGradientSup" x1="0%" y1="0%" x2="0%" y2="100%">
                        <stop offset="0%" style="stop-color:#527d59;stop-opacity:1" />
                        <stop offset="100%" style="stop-color:#7a9e7f;stop-opacity:1" />
                    </linearGradient>
                </defs>
            ` + svgContent;
            
            svg.innerHTML = svgContent;
        } else {
            svg.innerHTML = '<text x="300" y="100" text-anchor="middle" font-size="14" fill="#666">No supply data available</text>';
        }
    }

    function renderDonutChart(svgId, data, legendId) {
        const svg = document.getElementById(svgId);
        const legend = document.getElementById(legendId);
        
        const entries = Object.entries(data);
        if (entries.length === 0) {
            svg.innerHTML = '<text x="200" y="150" text-anchor="middle" font-size="14" fill="#666">No data available</text>';
            legend.innerHTML = '';
            return;
        }
        
        const total = entries.reduce((sum, [_, value]) => sum + safeNumber(value), 0);
        const colors = ['#214f2c', '#527d59', '#7a9e7f', '#a8bea5', '#d4ded4', '#28a745', '#dc3545', '#ffc107', '#17a2b8', '#6c757d'];
        const centerX = 200;
        const centerY = 150;
        const outerRadius = 100;
        const innerRadius = 60;
        
        let svgContent = '';
        let startAngle = -Math.PI / 2;
        let legendHTML = '';
        
        entries.forEach(([label, value], i) => {
            const valueSafe = safeNumber(value);
            const percentage = total > 0 ? valueSafe / total : 0;
            const angle = percentage * 2 * Math.PI;
            const endAngle = startAngle + angle;
            
            const x1 = centerX + outerRadius * Math.cos(startAngle);
            const y1 = centerY + outerRadius * Math.sin(startAngle);
            const x2 = centerX + outerRadius * Math.cos(endAngle);
            const y2 = centerY + outerRadius * Math.sin(endAngle);
            const x3 = centerX + innerRadius * Math.cos(endAngle);
            const y3 = centerY + innerRadius * Math.sin(endAngle);
            const x4 = centerX + innerRadius * Math.cos(startAngle);
            const y4 = centerY + innerRadius * Math.sin(startAngle);
            
            const largeArcFlag = angle > Math.PI ? 1 : 0;
            
            if (entries.length === 1) {
                // Full circle for single item
                svgContent += `<circle cx="${centerX}" cy="${centerY}" r="${outerRadius}" fill="${colors[i % colors.length]}"/>`;
                svgContent += `<circle cx="${centerX}" cy="${centerY}" r="${innerRadius}" fill="#fafafa"/>`;
            } else {
                svgContent += `<path d="M ${x1} ${y1} A ${outerRadius} ${outerRadius} 0 ${largeArcFlag} 1 ${x2} ${y2} L ${x3} ${y3} A ${innerRadius} ${innerRadius} 0 ${largeArcFlag} 0 ${x4} ${y4} Z" fill="${colors[i % colors.length]}"/>`;
            }
            
            // Legend
            legendHTML += `
                <div class="legend-item">
                    <div class="legend-color" style="background:${colors[i % colors.length]}"></div>
                    <span>${label.charAt(0).toUpperCase() + label.slice(1)} (${valueSafe})</span>
                </div>
            `;
            
            startAngle = endAngle;
        });
        
        // Center text
        svgContent += `<text x="${centerX}" y="${centerY}" text-anchor="middle" dominant-baseline="middle" font-size="24" font-weight="bold" fill="#214f2c">${total}</text>`;
        svgContent += `<text x="${centerX}" y="${centerY + 20}" text-anchor="middle" dominant-baseline="middle" font-size="12" fill="#666">Total</text>`;
        
        svg.innerHTML = svgContent;
        legend.innerHTML = legendHTML;
    }

    // Load analytics when page loads
    loadAnalytics();
})();
</script>