<?php
// =========================================================
// VDVC Egg Farm Management System
// Shared All Notifications Modal Component
// Applied to: Customer, Manager, and Owner/Admin
// =========================================================
?>

<!-- =========================================================
     ALL NOTIFICATIONS MODAL
     Opened by "View All" in the notification dropdown.
========================================================= -->

<div
    class="cnotif-modal-overlay hidden"
    id="allNotificationsModal"
    role="dialog"
    aria-modal="true"
    aria-labelledby="allNotificationsTitle"
>

    <div class="cnotif-modal" onclick="event.stopPropagation()">

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
                onclick="closeAllNotificationsModal(event)"
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
                <!-- Populated by shared_notifications.js -->
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
                onclick="markAllFromModal(event)"
            >
                <i class="fa-solid fa-check-double"></i>
                Mark All as Read
            </button>
            <button
                type="button"
                class="cnotif-close-btn"
                onclick="closeAllNotificationsModal(event)"
            >
                Close
            </button>
        </div>

    </div>

</div>
