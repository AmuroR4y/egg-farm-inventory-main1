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
   CUSTOMER NOTIFICATION DROPDOWN
========================================================= */

function toggleCustomerNotificationDropdown(event) {
    event.stopPropagation();

    var dropdown = document.getElementById('customerNotificationDropdown');

    if (dropdown.classList.contains('hidden')) {
        dropdown.classList.remove('hidden');
        loadCustomerNotifications();
    } else {
        dropdown.classList.add('hidden');
    }
}

function loadCustomerNotifications() {
    var notificationList = document.getElementById('customerNotificationList');

    // Show loading state
    notificationList.innerHTML = '<div class="customer-notification-empty"><i class="fa-solid fa-spinner fa-spin"></i><p>Loading notifications...</p></div>';

    // Fetch notifications from the dedicated notifications page
    fetch('customer_notifications.php')
        .then(function(response) {
            return response.text();
        })
        .then(function(html) {
            // Parse the HTML to extract notification data
            var parser = new DOMParser();
            var doc = parser.parseFromString(html, 'text/html');

            // Extract notification items from the dedicated page
            var notificationItems = doc.querySelectorAll('.notification-item');

            if (notificationItems.length > 0) {
                var notificationsHtml = '';
                notificationItems.forEach(function(item) {
                    var isUnread = item.classList.contains('unread');
                    var icon = item.querySelector('.notification-icon');
                    var title = item.querySelector('.notification-title');
                    var description = item.querySelector('.notification-desc');
                    var time = item.querySelector('.notification-time');

                    notificationsHtml += '<div class="customer-notification-item' + (isUnread ? ' unread' : '') + '" onclick="markCustomerAsRead(this, event)">';
                    notificationsHtml += '<div class="customer-notification-icon ' + (icon ? icon.className.replace('notification-icon', '') : 'info') + '">';
                    notificationsHtml += icon ? icon.innerHTML : '<i class="fa-solid fa-bell"></i>';
                    notificationsHtml += '</div>';
                    notificationsHtml += '<div class="customer-notification-content">';
                    notificationsHtml += '<div class="customer-notification-title">' + (title ? title.textContent : 'Notification') + '</div>';
                    notificationsHtml += '<div class="customer-notification-description">' + (description ? description.textContent : '') + '</div>';
                    notificationsHtml += '<div class="customer-notification-time">' + (time ? time.textContent : '') + '</div>';
                    notificationsHtml += '</div>';
                    notificationsHtml += '</div>';
                });

                notificationList.innerHTML = notificationsHtml;
            } else {
                notificationList.innerHTML = '<div class="customer-notification-empty"><i class="fa-fa-regular fa-bell-slash"></i><p>No notifications</p></div>';
            }
        })
        .catch(function(error) {
            console.error('Failed to load notifications:', error);
            notificationList.innerHTML = '<div class="customer-notification-empty"><i class="fa-solid fa-triangle-exclamation"></i><p>Failed to load notifications</p></div>';
        });
}

function markCustomerAsRead(element, event) {
    event.stopPropagation();

    var notificationId = element.getAttribute('data-notification-id');

    if (notificationId) {
        var formData = new FormData();
        formData.append('mark_read', notificationId);

        fetch('customer_notifications.php', {
            method: 'POST',
            body: formData
        })
        .then(function(response) {
            return response.text();
        })
        .then(function() {
            element.classList.remove('unread');
            updateCustomerNotificationBadge();
        })
        .catch(function(error) {
            console.error('Failed to mark notification as read:', error);
        });
    }
}

function markAllCustomerAsRead(event) {
    event.preventDefault();
    event.stopPropagation();

    var formData = new FormData();
    formData.append('mark_all_read', '1');

    fetch('customer_notifications.php', {
        method: 'POST',
        body: formData
    })
    .then(function(response) {
        return response.text();
    })
    .then(function() {
        // Reload page to update all notification states
        window.location.reload();
    })
    .catch(function(error) {
        console.error('Failed to mark all notifications as read:', error);
    });
}

function updateCustomerNotificationBadge() {
    var badge = document.querySelector('.customer-notification-badge');
    if (badge) {
        var currentCount = parseInt(badge.textContent);
        if (currentCount > 1) {
            badge.textContent = currentCount - 1;
        } else {
            badge.style.display = 'none';
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
        closeAllNotificationsModal();
    }
});

/* =========================================================
   ALL NOTIFICATIONS MODAL
   Triggered by "View All Notifications" in the bell dropdown.
========================================================= */

var _cnotifOpen = false; // guard: prevent duplicate fetches

function openAllNotificationsModal() {
    // Stop the triggering click from bubbling to document listeners
    // (they would check notification-wrapper containment and could interfere)
    if (window.event) {
        try { window.event.stopPropagation(); } catch(e) {}
    }

    // Close the bell dropdown first
    var dropdown = document.getElementById('customerNotificationDropdown');
    if (dropdown) dropdown.classList.add('hidden');

    var overlay = document.getElementById('allNotificationsModal');
    if (!overlay) {
        console.error('[VDVC] allNotificationsModal element not found in DOM');
        return;
    }

    overlay.classList.remove('hidden');
    document.body.style.overflow = 'hidden'; // prevent background scroll

    _cnotifOpen = true;
    _fetchAllNotifications();
}

function closeAllNotificationsModal() {
    var overlay = document.getElementById('allNotificationsModal');
    if (!overlay) return;

    overlay.classList.add('hidden');
    document.body.style.overflow = '';
    _cnotifOpen = false;
}

// Close when clicking the backdrop (outside the modal panel)
document.addEventListener('click', function(event) {
    var overlay = document.getElementById('allNotificationsModal');
    if (
        overlay &&
        !overlay.classList.contains('hidden') &&
        event.target === overlay
    ) {
        closeAllNotificationsModal();
    }
});

function _fetchAllNotifications() {
    var loading  = document.getElementById('cnotifLoading');
    var list     = document.getElementById('cnotifList');
    var empty    = document.getElementById('cnotifEmpty');
    var countEl  = document.getElementById('cnotifUnreadCount');
    var markBtn  = document.getElementById('cnotifMarkAllBtn');

    // Reset state
    if (loading) { loading.classList.remove('hidden'); }
    if (list)    { list.classList.add('hidden'); list.innerHTML = ''; }
    if (empty)   { empty.classList.add('hidden'); }
    if (countEl) { countEl.classList.add('hidden'); countEl.textContent = ''; }

    fetch('customer_notifications.php?action=json', {
        method: 'GET',
        credentials: 'same-origin'
    })
    .then(function(response) {
        if (!response.ok) throw new Error('Network error: ' + response.status);
        return response.json();
    })
    .then(function(data) {
        if (loading) loading.classList.add('hidden');

        var notifications = data.notifications || [];

        if (notifications.length === 0) {
            if (empty) empty.classList.remove('hidden');
            if (markBtn) markBtn.disabled = true;
            return;
        }

        // Count unread
        var unreadCount = notifications.filter(function(n) { return !n.is_read; }).length;

        if (countEl) {
            if (unreadCount > 0) {
                countEl.textContent = unreadCount > 99 ? '99+' : unreadCount;
                countEl.classList.remove('hidden');
            } else {
                countEl.classList.add('hidden');
            }
        }

        if (markBtn) {
            markBtn.disabled = (unreadCount === 0);
        }

        // Build list HTML
        var html = '';
        notifications.forEach(function(n) {
            var iconClass = 'info';
            var iconName  = 'fa-bell';
            if (n.type === 'success') { iconClass = 'success'; iconName = 'fa-check'; }
            if (n.type === 'alert')   { iconClass = 'alert';   iconName = 'fa-exclamation'; }
            if (n.type === 'warning') { iconClass = 'warning'; iconName = 'fa-triangle-exclamation'; }
            if (n.type === 'info')    { iconClass = 'info';    iconName = 'fa-info'; }

            // Safely escape text (use textContent assignment pattern via a temp element)
            var safeTitle = _escapeHtml(n.title);
            var safeDesc  = _escapeHtml(n.description);
            var safeTime  = _escapeHtml(_formatNotifDate(n.created_at));

            var unreadClass = n.is_read ? '' : ' unread';
            var dotHtml     = n.is_read ? '' : '<span class="cnotif-unread-dot"></span>';

            html += '<div class="cnotif-item' + unreadClass + '" data-id="' + n.id + '" onclick="_markModalItemRead(this)">';
            html += '  <div class="cnotif-item-icon ' + iconClass + '">';
            html += '    <i class="fa-solid ' + iconName + '"></i>';
            html += '  </div>';
            html += '  <div class="cnotif-item-content">';
            html += '    <div class="cnotif-item-title">' + safeTitle + '</div>';
            html += '    <div class="cnotif-item-desc">' + safeDesc + '</div>';
            html += '    <div class="cnotif-item-meta">';
            html += '      <span class="cnotif-item-time">' + safeTime + '</span>';
            html +=        dotHtml;
            html += '    </div>';
            html += '  </div>';
            html += '</div>';
        });

        if (list) {
            list.innerHTML = html;
            list.classList.remove('hidden');
        }
    })
    .catch(function(err) {
        console.error('All Notifications modal fetch error:', err);
        if (loading) loading.classList.add('hidden');
        if (list) {
            list.innerHTML = '<div class="cnotif-loading"><i class="fa-solid fa-triangle-exclamation"></i><p>Failed to load notifications. Please try again.</p></div>';
            list.classList.remove('hidden');
        }
    });
}

function _markModalItemRead(element) {
    if (element.classList.contains('unread')) {
        var notifId = parseInt(element.getAttribute('data-id'), 10);
        if (!notifId) return;

        var formData = new FormData();
        formData.append('mark_read', '1');
        formData.append('notification_id', notifId);

        fetch('customer_notifications.php', {
            method: 'POST',
            credentials: 'same-origin',
            body: formData
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.success) {
                element.classList.remove('unread');

                // Remove the unread dot
                var dot = element.querySelector('.cnotif-unread-dot');
                if (dot) dot.remove();

                // Decrement the modal unread count badge
                var countEl = document.getElementById('cnotifUnreadCount');
                if (countEl) {
                    var cur = parseInt(countEl.textContent, 10) || 0;
                    if (cur <= 1) {
                        countEl.classList.add('hidden');
                    } else {
                        countEl.textContent = cur - 1;
                    }
                }

                // Also update the navbar badge
                updateCustomerNotificationBadge();
            }
        })
        .catch(function(err) {
            console.error('Mark read error:', err);
        });
    }
}

function markAllFromModal() {
    var markBtn = document.getElementById('cnotifMarkAllBtn');
    if (markBtn) markBtn.disabled = true;

    var formData = new FormData();
    formData.append('mark_all_read', '1');

    fetch('customer_notifications.php', {
        method: 'POST',
        credentials: 'same-origin',
        body: formData
    })
    .then(function(r) { return r.text(); })
    .then(function() {
        // Mark all items as read in the modal UI
        var items = document.querySelectorAll('#cnotifList .cnotif-item.unread');
        items.forEach(function(item) {
            item.classList.remove('unread');
            var dot = item.querySelector('.cnotif-unread-dot');
            if (dot) dot.remove();
        });

        // Hide modal unread count badge
        var countEl = document.getElementById('cnotifUnreadCount');
        if (countEl) countEl.classList.add('hidden');

        // Hide navbar badge
        var navBadge = document.querySelector('.customer-notification-badge');
        if (navBadge) navBadge.style.display = 'none';
    })
    .catch(function(err) {
        console.error('Mark all read error:', err);
        if (markBtn) markBtn.disabled = false;
    });
}

/* ---- Helpers ---- */

function _escapeHtml(str) {
    if (!str) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

function _formatNotifDate(dateStr) {
    if (!dateStr) return '';
    try {
        var d = new Date(dateStr.replace(' ', 'T'));
        if (isNaN(d.getTime())) return dateStr;
        return d.toLocaleString('en-US', {
            month:  'short',
            day:    'numeric',
            year:   'numeric',
            hour:   'numeric',
            minute: '2-digit',
            hour12: true
        });
    } catch (e) {
        return dateStr;
    }
}
