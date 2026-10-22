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

<?php require_once 'shared_notifications_modal.php'; ?>

