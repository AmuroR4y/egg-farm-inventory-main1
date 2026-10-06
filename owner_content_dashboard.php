<div id="page-dashboard" class="page active">
    <div class="dashboard-page">
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

        <section class="dashboard-grid dashboard-secondary-grid">
            <article class="dashboard-card quick-status-card">
                <div class="card-heading">
                    <div>
                        <span class="card-kicker">QUICK STATUS</span>
                        <h2>Farm Status Summary</h2>
                        <p>Current operational status across key areas.</p>
                    </div>
                </div>
                <div class="quick-status-grid">
                    <div class="status-item">
                        <span class="status-label">Inventory</span>
                        <span class="status-value" id="quick-inventory-status">Healthy</span>
                    </div>
                    <div class="status-item">
                        <span class="status-label">Reservations</span>
                        <span class="status-value" id="quick-reservation-status">Active</span>
                    </div>
                    <div class="status-item">
                        <span class="status-label">Deliveries</span>
                        <span class="status-value" id="quick-delivery-status">On Schedule</span>
                    </div>
                    <div class="status-item">
                        <span class="status-label">Forecast</span>
                        <span class="status-value" id="quick-forecast-status">Stable</span>
                    </div>
                </div>
            </article>

            <article class="dashboard-card notifications-card">
                <div class="card-heading compact">
                    <div><span class="card-kicker">COMMUNICATIONS</span><h2>Notification Center</h2></div>
                    <button class="btn-secondary dashboard-action-btn" id="mark-all-read" type="button">Mark all read</button>
                </div>
                <div id="notification-list" class="notification-list-modern"></div>
            </article>
        </section>

        <section class="dashboard-grid dashboard-bottom-grid">
            <article class="dashboard-card quick-links-card">
                <div class="card-heading">
                    <div>
                        <span class="card-kicker">QUICK ACCESS</span>
                        <h2>Detailed Analysis</h2>
                        <p>Access in-depth analytics and forecasting tools.</p>
                    </div>
                </div>
                <div class="quick-links-grid">
                    <button class="btn-secondary quick-link-item" onclick="window.location.href='owner_dashboard.php?page=analytics'">
                        <i class="fa-solid fa-chart-line"></i>
                        <span>Data Analytics</span>
                        <i class="fa-solid fa-chevron-right"></i>
                    </button>
                    <button class="btn-secondary quick-link-item" onclick="window.location.href='owner_dashboard.php?page=forecasting'">
                        <i class="fa-solid fa-timeline"></i>
                        <span>Demand Forecasting</span>
                        <i class="fa-solid fa-chevron-right"></i>
                    </button>
                    <button class="btn-secondary quick-link-item" onclick="window.location.href='owner_dashboard.php?page=reports'">
                        <i class="fa-solid fa-file-invoice"></i>
                        <span>Reports</span>
                        <i class="fa-solid fa-chevron-right"></i>
                    </button>
                </div>
            </article>
        </section>
    </div>
</div>