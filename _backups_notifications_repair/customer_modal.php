<?php
// Customer Logout Modal Component - Shared across all Customer pages
// This file requires $_SESSION['customer_logout_token'] to be set
?>

<!-- =========================================================
     LOGOUT CONFIRMATION MODAL
========================================================= -->

<div
    class="customer-logout-overlay hidden"
    id="customerLogoutModal"
    role="dialog"
    aria-modal="true"
    aria-labelledby="customerLogoutTitle"
    aria-describedby="customerLogoutMessage"
>

    <div class="customer-logout-modal">

        <div class="customer-logout-icon">
            <i class="fa-solid fa-right-from-bracket"></i>
        </div>

        <h2 id="customerLogoutTitle">Confirm Logout</h2>

        <p id="customerLogoutMessage">
            Are you sure you want to logout?
        </p>

        <div class="customer-logout-actions">

            <button
                type="button"
                class="customer-logout-cancel"
                onclick="closeCustomerLogoutModal()"
            >
                Cancel
            </button>

            <form method="POST" action="customer_dashboard.php">

                <input
                    type="hidden"
                    name="customer_logout"
                    value="1"
                >

                <input
                    type="hidden"
                    name="logout_token"
                    value="<?php echo htmlspecialchars(
                        $_SESSION['customer_logout_token'],
                        ENT_QUOTES,
                        'UTF-8'
                    ); ?>"
                >

                <button
                    type="submit"
                    class="customer-logout-confirm"
                >
                    Yes, Logout
                </button>

            </form>

        </div>

    </div>

</div>


<!-- =========================================================
     ALL NOTIFICATIONS MODAL
     Opened by "View All Notifications" in the bell dropdown.
     JS: openAllNotificationsModal() / closeAllNotificationsModal()
========================================================= -->

<div
    class="cnotif-modal-overlay hidden"
    id="allNotificationsModal"
    role="dialog"
    aria-modal="true"
    aria-labelledby="allNotificationsTitle"
>

    <div class="cnotif-modal">

        <!-- MODAL HEADER -->
        <div class="cnotif-modal-header">

            <div class="cnotif-modal-header-left">
                <div class="cnotif-modal-icon">
                    <i class="fa-solid fa-bell"></i>
                </div>
                <h2 id="allNotificationsTitle">All Notifications</h2>
                <span class="cnotif-modal-count hidden" id="cnotifUnreadCount"></span>
            </div>

            <button
                type="button"
                class="cnotif-modal-close"
                onclick="closeAllNotificationsModal()"
                aria-label="Close notifications"
            >
                <i class="fa-solid fa-xmark"></i>
            </button>

        </div>

        <!-- MODAL BODY — only this scrolls -->
        <div class="cnotif-modal-body" id="cnotifModalBody">

            <div class="cnotif-loading" id="cnotifLoading">
                <i class="fa-solid fa-spinner fa-spin"></i>
                <p>Loading notifications…</p>
            </div>

            <div class="cnotif-list hidden" id="cnotifList">
                <!-- Populated by JS -->
            </div>

            <div class="cnotif-empty hidden" id="cnotifEmpty">
                <i class="fa-regular fa-bell-slash"></i>
                <p>You have no notifications yet.</p>
            </div>

        </div>

        <!-- MODAL FOOTER -->
        <div class="cnotif-modal-footer">
            <button
                type="button"
                class="cnotif-mark-all-btn"
                id="cnotifMarkAllBtn"
                onclick="markAllFromModal()"
            >
                <i class="fa-solid fa-check-double"></i>
                Mark All as Read
            </button>
            <button
                type="button"
                class="cnotif-close-btn"
                onclick="closeAllNotificationsModal()"
            >
                Close
            </button>
        </div>

    </div>

</div>
