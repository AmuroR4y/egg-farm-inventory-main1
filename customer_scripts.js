/* =========================================================
   CUSTOMER SHARED JAVASCRIPT
========================================================= */

/* =========================================================
   LIVE DATE AND TIME
========================================================= */

function updateCustomerDateTime() {
    var now = new Date();

    var dateElement = document.getElementById('customer-live-date');
    var timeElement = document.getElementById('customer-live-time');

    if (dateElement) {
        var options = { year: 'numeric', month: 'long', day: 'numeric' };
        dateElement.textContent = now.toLocaleDateString('en-US', options);
    }

    if (timeElement) {
        timeElement.textContent = now.toLocaleTimeString('en-US', {
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit',
            hour12: true
        });
    }
}

// Update time every second
setInterval(updateCustomerDateTime, 1000);

// Initial update
updateCustomerDateTime();

/* =========================================================
   CUSTOMER NOTIFICATION DROPDOWN (Simple Toggle)
========================================================= */

function toggleCustomerNotificationDropdown() {
    var dropdown = document.getElementById('customerNotificationDropdown');
    if (dropdown) {
        if (dropdown.classList.contains('hidden')) {
            dropdown.classList.remove('hidden');
        } else {
            dropdown.classList.add('hidden');
        }
    }
}

/* =========================================================
   CUSTOMER ACCOUNT DROPDOWN
========================================================= */

function toggleCustomerAccountDropdown(event) {
    event.stopPropagation();

    var dropdown = document.getElementById('customerAccountDropdown');
    var toggle = document.getElementById('customerAccountToggle');

    if (dropdown.classList.contains('hidden')) {
        dropdown.classList.remove('hidden');
        toggle.setAttribute('aria-expanded', 'true');
    } else {
        dropdown.classList.add('hidden');
        toggle.setAttribute('aria-expanded', 'false');
    }
}

function closeCustomerAccountDropdown() {
    var dropdown = document.getElementById('customerAccountDropdown');
    var toggle = document.getElementById('customerAccountToggle');

    if (dropdown) {
        dropdown.classList.add('hidden');
    }

    if (toggle) {
        toggle.setAttribute('aria-expanded', 'false');
    }
}

/* =========================================================
   LOGOUT CONFIRMATION MODAL
========================================================= */

function openCustomerLogoutModal() {
    closeCustomerAccountDropdown();

    var modal = document.getElementById('customerLogoutModal');

    if (modal) {
        modal.classList.remove('hidden');

        var cancelButton = modal.querySelector(
            '.customer-logout-cancel'
        );

        if (cancelButton) {
            cancelButton.focus();
        }
    }
}

function closeCustomerLogoutModal() {
    var modal = document.getElementById('customerLogoutModal');

    if (modal) {
        modal.classList.add('hidden');

        var toggle = document.getElementById('customerAccountToggle');

        if (toggle) {
            toggle.focus();
        }
    }
}

/* =========================================================
   CLOSE MENUS WHEN CLICKING OUTSIDE
========================================================= */

document.addEventListener('click', function(event) {

    var accountWrapper = document.querySelector(
        '.customer-account-wrapper'
    );

    if (
        accountWrapper &&
        !accountWrapper.contains(event.target)
    ) {
        closeCustomerAccountDropdown();
    }

    // Close notification dropdown when clicking outside it
    var notifWrapper = document.querySelector(
        '.customer-notification-wrapper'
    );

    if (
        notifWrapper &&
        !notifWrapper.contains(event.target)
    ) {
        var notifDropdown = document.getElementById('customerNotificationDropdown');
        if (notifDropdown) notifDropdown.classList.add('hidden');
    }

    var modal = document.getElementById(
        'customerLogoutModal'
    );

    if (
        modal &&
        !modal.classList.contains('hidden') &&
        event.target === modal
    ) {
        closeCustomerLogoutModal();
    }
});

/* =========================================================
   CLOSE LOGOUT MODAL WITH ESCAPE
========================================================= */


document.addEventListener('keydown', function(event) {

    if (event.key === 'Escape') {
        closeCustomerLogoutModal();
        closeCustomerAccountDropdown();
    }
});
