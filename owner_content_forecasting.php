<div id="page-forecasting" class="page">
    <div class="page-shell">
        <div class="page-subtitle">Demand Forecasting</div>
        <div style="font-size:13px;font-weight:700;color:var(--sidebar);margin-bottom:12px;">Predictive Analysis Center</div>
        <div class="forecasting-container">
            <div class="panel">
                <div class="panel-title">Demand Projections</div>
                <p style="font-size:11.5px;color:var(--text-soft);margin-bottom:16px;line-height:1.5;">View demand forecasts and inventory predictions based on historical data.</p>
                
                <div id="forecasting-loading" style="text-align:center;padding:40px;">Loading forecast data...</div>
                
                <div id="forecasting-content" style="display:none;">
                    <!-- Forecast KPI -->
                    <div class="forecast-kpi-card">
                        <div class="kpi-label">Next Period Forecast</div>
                        <div class="kpi-value" id="next-forecast">0</div>
                        <div class="kpi-subtitle">Projected demand based on historical data</div>
                    </div>

                    <!-- Current Demand -->
                    <div class="forecast-section">
                        <h3>Current Demand Summary</h3>
                        <div class="demand-summary" id="demand-summary"></div>
                    </div>

                    <!-- Historical Demand Chart -->
                    <div class="forecast-section">
                        <h3>Historical Demand</h3>
                        <div class="chart-container">
                            <svg id="historical-demand-chart" viewBox="0 0 600 200" style="width:100%;height:200px;"></svg>
                        </div>
                    </div>

                    <!-- Forecast Chart -->
                    <div class="forecast-section">
                        <h3>Demand Forecast</h3>
                        <div class="chart-container">
                            <svg id="forecast-chart" viewBox="0 0 600 200" style="width:100%;height:200px;"></svg>
                        </div>
                    </div>

                    <!-- Historical vs Forecast Comparison -->
                    <div class="forecast-section">
                        <h3>Historical vs Forecast Comparison</h3>
                        <div class="chart-container">
                            <svg id="comparison-chart" viewBox="0 0 600 250" style="width:100%;height:250px;"></svg>
                        </div>
                    </div>

                    <!-- Demand Trend Line Chart -->
                    <div class="forecast-section">
                        <h3>Demand Trend</h3>
                        <div class="chart-container">
                            <svg id="trend-chart" viewBox="0 0 600 200" style="width:100%;height:200px;"></svg>
                        </div>
                    </div>

                    <!-- Trend Analysis -->
                    <div class="forecast-section">
                        <h3>Trend Analysis</h3>
                        <div class="trend-summary" id="trend-summary"></div>
                    </div>

                    <!-- Peak Demand -->
                    <div class="forecast-section">
                        <h3>Peak Demand Period</h3>
                        <div class="peak-period" id="peak-period"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .forecast-kpi-card {
        background: linear-gradient(135deg, #214f2c 0%, #527d59 100%);
        color: white;
        padding: 32px;
        border-radius: 12px;
        margin-bottom: 32px;
        text-align: center;
        box-shadow: 0 4px 12px rgba(33, 79, 44, 0.2);
    }
    .forecast-kpi-card .kpi-label {
        font-size: 16px;
        opacity: 0.9;
        margin-bottom: 12px;
        font-weight: 500;
    }
    .forecast-kpi-card .kpi-value {
        font-size: 52px;
        font-weight: bold;
        margin-bottom: 12px;
        color: white !important;
    }
    .forecast-kpi-card .kpi-subtitle {
        font-size: 14px;
        opacity: 0.8;
    }
    .forecast-section {
        background: white;
        padding: 24px;
        border-radius: 12px;
        margin-bottom: 24px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    }
    .forecast-section h3 {
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
    .demand-summary, .trend-summary, .peak-period {
        margin-top: 20px;
    }
    .summary-item {
        display: flex;
        justify-content: space-between;
        padding: 14px 16px;
        border-bottom: 1px solid #f0f0f0;
        background: white;
    }
    .summary-item:first-child {
        border-top-left-radius: 8px;
        border-top-right-radius: 8px;
    }
    .summary-item:last-child {
        border-bottom: none;
        border-bottom-left-radius: 8px;
        border-bottom-right-radius: 8px;
    }
    .trend-up {
        color: #28a745;
        font-weight: 600;
    }
    .trend-down {
        color: #dc3545;
        font-weight: 600;
    }
    .trend-stable {
        color: #6c757d;
        font-weight: 600;
    }
</style>

<script>
(function() {
    function safeNumber(value) {
        const n = Number(value);
        return Number.isFinite(n) ? n : 0;
    }

    function loadForecasting() {
        fetch('/egg-farm-inventory-main1/owner_api.php')
            .then(res => res.json())
            .then(data => {
                document.getElementById('forecasting-loading').style.display = 'none';
                document.getElementById('forecasting-content').style.display = 'block';
                
                const forecastData = calculateForecast(data);
                renderForecastSummary(data, forecastData);
                renderHistoricalDemandChart(forecastData.historical);
                renderDemandForecastChart(forecastData.forecast);
                renderComparisonChart(forecastData.historical, forecastData.forecast);
                renderTrendChart(forecastData.historical, forecastData.forecast);
                renderTrendSummary(forecastData.trend);
                renderPeakPeriod(forecastData.peak);
            })
            .catch(error => {
                console.error('[FORECAST ERROR]', error);
                document.getElementById('forecasting-loading').innerHTML = 'Error loading forecast data.';
            });
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

    function renderForecastSummary(data, forecastData) {
        const reservations = data.reservations || [];
        const totalDemand = reservations.reduce((sum, r) => sum + safeNumber(r.quantity), 0);
        
        document.getElementById('next-forecast').textContent = forecastData.nextForecast;
        document.getElementById('demand-summary').innerHTML = `
            <div class="summary-item">
                <span>Total Historical Demand</span>
                <strong>${totalDemand}</strong>
            </div>
            <div class="summary-item">
                <span>Historical Periods</span>
                <strong>${forecastData.historical.length}</strong>
            </div>
            <div class="summary-item">
                <span>Average Demand/Period</span>
                <strong>${forecastData.historical.length > 0 ? Math.round(totalDemand / forecastData.historical.length) : 0}</strong>
            </div>
        `;
    }

    function renderHistoricalDemandChart(historical) {
        const svg = document.getElementById('historical-demand-chart');
        
        if (!Array.isArray(historical) || historical.length === 0) {
            svg.innerHTML = '<text x="300" y="100" text-anchor="middle" font-size="14" fill="#666">No historical data available</text>';
            return;
        }
        
        const values = historical.map(d => safeNumber(d.value));
        const maxValue = Math.max(...values, 1);
        const width = 600;
        const height = 200;
        const padding = { top: 20, right: 20, bottom: 40, left: 40 };
        const chartWidth = width - padding.left - padding.right;
        const chartHeight = height - padding.top - padding.bottom;
        const barWidth = Math.max((chartWidth / historical.length) * 0.6, 20);
        const barGap = (chartWidth - (barWidth * historical.length)) / (historical.length + 1);
        
        let svgContent = '';
        
        // Grid lines
        for (let i = 0; i <= 5; i++) {
            const y = padding.top + (chartHeight * i / 5);
            svgContent += `<line x1="${padding.left}" y1="${y}" x2="${width - padding.right}" y2="${y}" stroke="#e0e0e0" stroke-width="1" stroke-dasharray="4"/>`;
        }
        
        // Bars
        historical.forEach((d, i) => {
            const value = safeNumber(d.value);
            const barHeight = (value / maxValue) * chartHeight;
            const x = padding.left + barGap + i * (barWidth + barGap);
            const y = padding.top + chartHeight - barHeight;
            
            svgContent += `
                <rect x="${x}" y="${y}" width="${barWidth}" height="${barHeight}" fill="url(#barGradientHist)" rx="4" ry="4"/>
                <text x="${x + barWidth/2}" y="${height - padding.bottom + 15}" font-size="11" text-anchor="middle" fill="#666">${d.date.substring(5)}</text>
                <text x="${x + barWidth/2}" y="${y - 8}" font-size="12" text-anchor="middle" fill="#214f2c" font-weight="600">${value}</text>
            `;
        });
        
        // Gradient definition
        svgContent = `
            <defs>
                <linearGradient id="barGradientHist" x1="0%" y1="0%" x2="0%" y2="100%">
                    <stop offset="0%" style="stop-color:#214f2c;stop-opacity:1" />
                    <stop offset="100%" style="stop-color:#527d59;stop-opacity:1" />
                </linearGradient>
            </defs>
        ` + svgContent;
        
        svg.innerHTML = svgContent;
    }

    function renderDemandForecastChart(forecast) {
        const svg = document.getElementById('forecast-chart');
        
        if (!Array.isArray(forecast) || forecast.length === 0) {
            svg.innerHTML = '<text x="300" y="100" text-anchor="middle" font-size="14" fill="#666">No forecast data available</text>';
            return;
        }
        
        const values = forecast.map(d => safeNumber(d.value));
        const maxValue = Math.max(...values, 1);
        const width = 600;
        const height = 200;
        const padding = { top: 20, right: 20, bottom: 40, left: 40 };
        const chartWidth = width - padding.left - padding.right;
        const chartHeight = height - padding.top - padding.bottom;
        const barWidth = Math.max((chartWidth / forecast.length) * 0.6, 30);
        const barGap = (chartWidth - (barWidth * forecast.length)) / (forecast.length + 1);
        
        let svgContent = '';
        
        // Grid lines
        for (let i = 0; i <= 5; i++) {
            const y = padding.top + (chartHeight * i / 5);
            svgContent += `<line x1="${padding.left}" y1="${y}" x2="${width - padding.right}" y2="${y}" stroke="#e0e0e0" stroke-width="1" stroke-dasharray="4"/>`;
        }
        
        // Bars
        forecast.forEach((d, i) => {
            const value = safeNumber(d.value);
            const barHeight = (value / maxValue) * chartHeight;
            const x = padding.left + barGap + i * (barWidth + barGap);
            const y = padding.top + chartHeight - barHeight;
            
            svgContent += `
                <rect x="${x}" y="${y}" width="${barWidth}" height="${barHeight}" fill="url(#barGradientFcast)" rx="4" ry="4"/>
                <text x="${x + barWidth/2}" y="${height - padding.bottom + 15}" font-size="11" text-anchor="middle" fill="#666">${d.period}</text>
                <text x="${x + barWidth/2}" y="${y - 8}" font-size="12" text-anchor="middle" fill="#527d59" font-weight="600">${value}</text>
            `;
        });
        
        // Gradient definition
        svgContent = `
            <defs>
                <linearGradient id="barGradientFcast" x1="0%" y1="0%" x2="0%" y2="100%">
                    <stop offset="0%" style="stop-color:#527d59;stop-opacity:1" />
                    <stop offset="100%" style="stop-color:#7a9e7f;stop-opacity:1" />
                </linearGradient>
            </defs>
        ` + svgContent;
        
        svg.innerHTML = svgContent;
    }

    function renderComparisonChart(historical, forecast) {
        const svg = document.getElementById('comparison-chart');
        
        if ((!Array.isArray(historical) || historical.length === 0) && (!Array.isArray(forecast) || forecast.length === 0)) {
            svg.innerHTML = '<text x="300" y="125" text-anchor="middle" font-size="14" fill="#666">No comparison data available</text>';
            return;
        }
        
        // Only show periods that have data in either historical or forecast
        const maxPeriods = Math.max(historical.length, forecast.length);
        const allValues = [
            ...(Array.isArray(historical) ? historical.map(d => safeNumber(d.value)) : []),
            ...(Array.isArray(forecast) ? forecast.map(d => safeNumber(d.value)) : [])
        ];
        const maxValue = Math.max(...allValues, 1);
        const width = 600;
        const height = 250;
        const padding = { top: 40, right: 20, bottom: 40, left: 40 };
        const chartWidth = width - padding.left - padding.right;
        const chartHeight = height - padding.top - padding.bottom;
        const groupWidth = chartWidth / maxPeriods;
        const barWidth = Math.max(groupWidth * 0.3, 15);
        
        let svgContent = '';
        
        // Grid lines
        for (let i = 0; i <= 5; i++) {
            const y = padding.top + (chartHeight * i / 5);
            svgContent += `<line x1="${padding.left}" y1="${y}" x2="${width - padding.right}" y2="${y}" stroke="#e0e0e0" stroke-width="1" stroke-dasharray="4"/>`;
        }
        
        // Grouped bars
        for (let i = 0; i < maxPeriods; i++) {
            const groupX = padding.left + i * groupWidth + groupWidth / 2;
            
            // Historical bar
            if (i < historical.length) {
                const value = safeNumber(historical[i].value);
                const barHeight = (value / maxValue) * chartHeight;
                const y = padding.top + chartHeight - barHeight;
                
                svgContent += `
                    <rect x="${groupX - barWidth - 2}" y="${y}" width="${barWidth}" height="${barHeight}" fill="url(#barGradientHist)" rx="4" ry="4"/>
                    <text x="${groupX - barWidth/2}" y="${y - 8}" font-size="11" text-anchor="middle" fill="#214f2c" font-weight="600">${value}</text>
                `;
            }
            
            // Forecast bar
            if (i < forecast.length) {
                const value = safeNumber(forecast[i].value);
                const barHeight = (value / maxValue) * chartHeight;
                const y = padding.top + chartHeight - barHeight;
                
                svgContent += `
                    <rect x="${groupX + 2}" y="${y}" width="${barWidth}" height="${barHeight}" fill="url(#barGradientFcast)" opacity="0.8" rx="4" ry="4"/>
                    <text x="${groupX + barWidth/2}" y="${y - 8}" font-size="11" text-anchor="middle" fill="#527d59" font-weight="600">${value}</text>
                `;
            }
            
            // Label
            let label = 'M+' + (i + 1);
            const histItem = historical[i];
            const fcastItem = forecast[i];
            if (histItem && histItem.date) {
                label = histItem.date.substring(5);
            } else if (fcastItem && fcastItem.period) {
                label = fcastItem.period;
            }
            svgContent += `<text x="${groupX}" y="${height - padding.bottom + 15}" font-size="10" text-anchor="middle" fill="#666">${label}</text>`;
        }
        
        // Legend
        svgContent += `
            <rect x="20" y="10" width="15" height="15" fill="url(#barGradientHist)" rx="3"/>
            <text x="40" y="22" font-size="12" fill="#333">Historical</text>
            <rect x="100" y="10" width="15" height="15" fill="url(#barGradientFcast)" opacity="0.8" rx="3"/>
            <text x="120" y="22" font-size="12" fill="#333">Forecast</text>
        `;
        
        // Gradient definitions
        svgContent = `
            <defs>
                <linearGradient id="barGradientHist" x1="0%" y1="0%" x2="0%" y2="100%">
                    <stop offset="0%" style="stop-color:#214f2c;stop-opacity:1" />
                    <stop offset="100%" style="stop-color:#527d59;stop-opacity:1" />
                </linearGradient>
                <linearGradient id="barGradientFcast" x1="0%" y1="0%" x2="0%" y2="100%">
                    <stop offset="0%" style="stop-color:#527d59;stop-opacity:1" />
                    <stop offset="100%" style="stop-color:#7a9e7f;stop-opacity:1" />
                </linearGradient>
            </defs>
        ` + svgContent;
        
        svg.innerHTML = svgContent;
    }

    function renderTrendSummary(trend) {
        const trendClass = trend === 'up' ? 'trend-up' : (trend === 'down' ? 'trend-down' : 'trend-stable');
        const trendText = trend === 'up' ? 'Increasing' : (trend === 'down' ? 'Decreasing' : 'Stable');
        
        document.getElementById('trend-summary').innerHTML = `
            <div class="summary-item">
                <span>Current Trend</span>
                <strong class="${trendClass}">${trendText}</strong>
            </div>
            <div class="summary-item">
                <span>Trend Direction</span>
                <strong>${trend.charAt(0).toUpperCase() + trend.slice(1)}</strong>
            </div>
        `;
    }

    function renderTrendChart(historical, forecast) {
        const svg = document.getElementById('trend-chart');
        
        if ((!Array.isArray(historical) || historical.length === 0) && (!Array.isArray(forecast) || forecast.length === 0)) {
            svg.innerHTML = '<text x="300" y="100" text-anchor="middle" font-size="14" fill="#666">No trend data available</text>';
            return;
        }
        
        // Combine historical and forecast for continuous line
        const allData = [];
        if (Array.isArray(historical)) {
            historical.forEach((d, i) => {
                allData.push({ label: d.date.substring(5), value: safeNumber(d.value), type: 'historical' });
            });
        }
        if (Array.isArray(forecast)) {
            forecast.forEach((d, i) => {
                allData.push({ label: d.period, value: safeNumber(d.value), type: 'forecast' });
            });
        }
        
        const values = allData.map(d => d.value);
        const maxValue = Math.max(...values, 1);
        const width = 600;
        const height = 200;
        const padding = { top: 20, right: 20, bottom: 60, left: 40 };
        const chartWidth = width - padding.left - padding.right;
        const chartHeight = height - padding.top - padding.bottom;
        const stepX = chartWidth / (allData.length - 1 || 1);
        
        let svgContent = '';
        
        // Grid lines
        for (let i = 0; i <= 5; i++) {
            const y = padding.top + (chartHeight * i / 5);
            svgContent += `<line x1="${padding.left}" y1="${y}" x2="${width - padding.right}" y2="${y}" stroke="#e0e0e0" stroke-width="1" stroke-dasharray="4"/>`;
        }
        
        // Build line path
        let pathD = '';
        allData.forEach((d, i) => {
            const x = padding.left + i * stepX;
            const y = padding.top + chartHeight - (d.value / maxValue) * chartHeight;
            
            if (i === 0) {
                pathD += `M ${x} ${y}`;
            } else {
                pathD += ` L ${x} ${y}`;
            }
        });
        
        svgContent += `<path d="${pathD}" fill="none" stroke="#214f2c" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>`;
        
        // Calculate label skipping to prevent overlap
        const historicalCount = Array.isArray(historical) ? historical.length : 0;
        const forecastCount = Array.isArray(forecast) ? forecast.length : 0;
        const totalPoints = allData.length;
        
        // Determine if we need to skip historical labels
        let skipInterval = 1;
        if (historicalCount > 8) {
            skipInterval = Math.ceil(historicalCount / 6);
        }
        
        // Points and labels
        allData.forEach((d, i) => {
            const x = padding.left + i * stepX;
            const y = padding.top + chartHeight - (d.value / maxValue) * chartHeight;
            const color = d.type === 'forecast' ? '#527d59' : '#214f2c';
            
            svgContent += `<circle cx="${x}" cy="${y}" r="5" fill="${color}" stroke="white" stroke-width="2"/>`;
            
            // Smart label rendering
            let showLabel = true;
            let labelY = height - padding.bottom + 20;
            let labelFontSize = 10;
            let labelRotation = 0;
            
            if (d.type === 'historical') {
                // Skip some historical labels if there are too many
                if (historicalCount > 8 && i % skipInterval !== 0) {
                    showLabel = false;
                }
            } else {
                // Always show forecast labels (only 6 of them)
                showLabel = true;
            }
            
            if (showLabel) {
                if (totalPoints > 10) {
                    // Rotate labels slightly if many points
                    labelRotation = -30;
                    labelY = height - padding.bottom + 35;
                    labelFontSize = 9;
                }
                
                if (labelRotation !== 0) {
                    svgContent += `<text x="${x}" y="${labelY}" font-size="${labelFontSize}" text-anchor="middle" fill="#666" transform="rotate(${labelRotation}, ${x}, ${labelY})">${d.label}</text>`;
                } else {
                    svgContent += `<text x="${x}" y="${labelY}" font-size="${labelFontSize}" text-anchor="middle" fill="#666">${d.label}</text>`;
                }
            }
        });
        
        // Legend
        svgContent += `
            <circle cx="20" cy="10" r="5" fill="#214f2c"/>
            <text x="30" y="14" font-size="11" fill="#333">Historical</text>
            <circle cx="100" cy="10" r="5" fill="#527d59"/>
            <text x="110" y="14" font-size="11" fill="#333">Forecast</text>
        `;
        
        svg.innerHTML = svgContent;
    }

    function renderPeakPeriod(peak) {
        if (!peak) {
            document.getElementById('peak-period').innerHTML = '<p>No peak period data available.</p>';
            return;
        }
        
        document.getElementById('peak-period').innerHTML = `
            <div class="summary-item">
                <span>Peak Date</span>
                <strong>${peak.date}</strong>
            </div>
            <div class="summary-item">
                <span>Peak Demand</span>
                <strong>${peak.value}</strong>
            </div>
        `;
    }

    // Load forecasting when page loads
    loadForecasting();
})();
</script>