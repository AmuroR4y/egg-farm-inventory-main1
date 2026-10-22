/* =========================================================
   VDVC EGG FARM MANAGEMENT SYSTEM
   Shared Notification Controller (Customer, Manager, Owner)
   ========================================================= */

(function (window, document) {
    'use strict';

    // Prevent duplicate script execution
    if (window._vdvcNotifInitialized) {
        return;
    }
    window._vdvcNotifInitialized = true;

    // Detect active role configuration
    function getActiveRoleConfig() {
        if (window.VDVC_ROLE === 'owner' || document.getElementById('ownerNotificationDropdown')) {
            return {
                role: 'owner',
                endpoint: 'owner_notifications.php',
                badgeSelector: '.owner-notification-badge',
                dropdownId: 'ownerNotificationDropdown',
                listId: 'ownerNotificationList'
            };
        }
        if (window.VDVC_ROLE === 'manager' || (document.getElementById('notificationDropdown') && !document.getElementById('customerNotificationDropdown'))) {
            return {
                role: 'manager',
                endpoint: 'manager_notifications.php',
                badgeSelector: '.notification-badge',
                dropdownId: 'notificationDropdown',
                listId: 'notificationList'
            };
        }
        return {
            role: 'customer',
            endpoint: 'customer_notifications.php',
            badgeSelector: '.customer-notification-badge',
            dropdownId: 'customerNotificationDropdown',
            listId: 'customerNotificationList'
        };
    }

    // HTML escape helper
    function escapeHtml(str) {
        if (!str) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    // Date formatting helper
    function formatDate(dateStr) {
        if (!dateStr) return '';
        try {
            var d = new Date(dateStr.replace(' ', 'T'));
            if (isNaN(d.getTime())) return dateStr;
            return d.toLocaleString('en-US', {
                month: 'short',
                day: 'numeric',
                year: 'numeric',
                hour: 'numeric',
                minute: '2-digit',
                hour12: true
            });
        } catch (e) {
            return dateStr;
        }
    }

    // Decrement badge count
    function decrementNavbarBadge(selector) {
        var badge = document.querySelector(selector);
        if (!badge) return;
        var text = badge.textContent.replace('+', '').trim();
        var current = parseInt(text, 10);
        if (!isNaN(current) && current > 1) {
            badge.textContent = (current - 1) > 99 ? '99+' : (current - 1);
        } else {
            badge.style.display = 'none';
        }
    }

    // Clear badge count completely
    function clearNavbarBadge(selector) {
        var badge = document.querySelector(selector);
        if (badge) {
            badge.style.display = 'none';
            badge.textContent = '0';
        }
    }

    // Close all open dropdowns
    function closeAnyOpenDropdown() {
        ['customerNotificationDropdown', 'notificationDropdown', 'ownerNotificationDropdown'].forEach(function (id) {
            var el = document.getElementById(id);
            if (el && !el.classList.contains('hidden')) {
                el.classList.add('hidden');
            }
        });
    }

    // =========================================================
    // EXPOSED GLOBAL FUNCTIONS
    // =========================================================

    window.openAllNotificationsModal = function (event) {
        if (event) {
            try {
                event.stopPropagation();
                if (event.preventDefault) event.preventDefault();
            } catch (e) {}
        }
        if (window.event) {
            try { window.event.stopPropagation(); } catch (e) {}
        }

        // Close dropdown first
        closeAnyOpenDropdown();

        var modal = document.getElementById('allNotificationsModal');
        if (!modal) {
            console.warn('[VDVC] #allNotificationsModal element not found in DOM.');
            return;
        }

        modal.classList.remove('hidden');
        document.body.style.overflow = 'hidden';

        window.fetchAllNotifications();
    };

    window.closeAllNotificationsModal = function (event) {
        if (event && event.stopPropagation) {
            event.stopPropagation();
        }
        var modal = document.getElementById('allNotificationsModal');
        if (modal) {
            modal.classList.add('hidden');
        }
        document.body.style.overflow = '';
    };

    window.fetchAllNotifications = function () {
        var cfg = getActiveRoleConfig();
        var loading = document.getElementById('cnotifLoading');
        var list = document.getElementById('cnotifList');
        var empty = document.getElementById('cnotifEmpty');
        var countEl = document.getElementById('cnotifUnreadCount');
        var markBtn = document.getElementById('cnotifMarkAllBtn');

        if (loading) loading.classList.remove('hidden');
        if (list) { list.classList.add('hidden'); list.innerHTML = ''; }
        if (empty) empty.classList.add('hidden');
        if (countEl) { countEl.classList.add('hidden'); countEl.textContent = ''; }

        fetch(cfg.endpoint + '?action=json', {
            method: 'GET',
            credentials: 'same-origin'
        })
        .then(function (res) {
            if (!res.ok) throw new Error('HTTP ' + res.status);
            return res.json();
        })
        .then(function (data) {
            if (loading) loading.classList.add('hidden');

            var notifications = (data && data.notifications) ? data.notifications : [];

            if (notifications.length === 0) {
                if (empty) empty.classList.remove('hidden');
                if (markBtn) markBtn.disabled = true;
                return;
            }

            var unreadCount = notifications.filter(function (n) { return !n.is_read; }).length;

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

            var html = '';
            notifications.forEach(function (n) {
                var iconClass = 'info';
                var iconName = 'fa-bell';
                if (n.type === 'success') {
                    iconClass = 'success';
                    iconName = 'fa-check';
                } else if (n.type === 'alert') {
                    iconClass = 'alert';
                    iconName = 'fa-triangle-exclamation';
                } else if (n.type === 'warning') {
                    iconClass = 'warning';
                    iconName = 'fa-circle-exclamation';
                }

                var safeTitle = escapeHtml(n.title);
                var safeDesc = escapeHtml(n.description);
                var safeTime = escapeHtml(formatDate(n.created_at));
                var unreadClass = n.is_read ? '' : ' unread';
                var dotHtml = n.is_read ? '' : '<span class="cnotif-unread-dot"></span>';

                html += '<div class="cnotif-item' + unreadClass + '" data-id="' + n.id + '" onclick="markModalItemRead(this)">';
                html += '  <div class="cnotif-item-icon ' + iconClass + '"><i class="fa-solid ' + iconName + '"></i></div>';
                html += '  <div class="cnotif-item-content">';
                html += '    <div class="cnotif-item-title">' + safeTitle + '</div>';
                html += '    <div class="cnotif-item-desc">' + safeDesc + '</div>';
                html += '    <div class="cnotif-item-meta"><span class="cnotif-item-time">' + safeTime + '</span>' + dotHtml + '</div>';
                html += '  </div>';
                html += '</div>';
            });

            if (list) {
                list.innerHTML = html;
                list.classList.remove('hidden');
            }
        })
        .catch(function (err) {
            console.error('[VDVC] fetchAllNotifications error:', err);
            if (loading) loading.classList.add('hidden');
            if (list) {
                list.innerHTML = '<div class="cnotif-loading"><i class="fa-solid fa-triangle-exclamation"></i><p>Unable to load notifications.</p></div>';
                list.classList.remove('hidden');
            }
        });
    };

    window.markModalItemRead = function (element) {
        if (!element || !element.classList.contains('unread')) return;
        var notifId = parseInt(element.getAttribute('data-id'), 10);
        if (!notifId) return;

        var cfg = getActiveRoleConfig();
        var formData = new FormData();
        formData.append('mark_read', '1');
        formData.append('notification_id', notifId);

        fetch(cfg.endpoint, {
            method: 'POST',
            credentials: 'same-origin',
            body: formData
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (data && (data.success || data.status === 'success')) {
                element.classList.remove('unread');
                var dot = element.querySelector('.cnotif-unread-dot');
                if (dot) dot.remove();

                var countEl = document.getElementById('cnotifUnreadCount');
                if (countEl) {
                    var cur = parseInt(countEl.textContent, 10) || 0;
                    if (cur <= 1) {
                        countEl.classList.add('hidden');
                        var markBtn = document.getElementById('cnotifMarkAllBtn');
                        if (markBtn) markBtn.disabled = true;
                    } else {
                        countEl.textContent = cur - 1;
                    }
                }

                decrementNavbarBadge(cfg.badgeSelector);
            }
        })
        .catch(function (err) {
            console.error('[VDVC] markModalItemRead error:', err);
        });
    };

    window.markAllFromModal = function (event) {
        if (event && event.stopPropagation) {
            event.stopPropagation();
        }
        var markBtn = document.getElementById('cnotifMarkAllBtn');
        if (markBtn) markBtn.disabled = true;

        var cfg = getActiveRoleConfig();
        var formData = new FormData();
        formData.append('mark_all_read', '1');
        formData.append('ajax', '1');

        fetch(cfg.endpoint, {
            method: 'POST',
            credentials: 'same-origin',
            body: formData
        })
        .then(function (r) { return r.text(); })
        .then(function () {
            var items = document.querySelectorAll('#cnotifList .cnotif-item.unread');
            items.forEach(function (item) {
                item.classList.remove('unread');
                var dot = item.querySelector('.cnotif-unread-dot');
                if (dot) dot.remove();
            });

            var countEl = document.getElementById('cnotifUnreadCount');
            if (countEl) countEl.classList.add('hidden');

            clearNavbarBadge(cfg.badgeSelector);
            if (markBtn) markBtn.disabled = true;

            // Also mark items read in any dropdown preview list
            var dropdownItems = document.querySelectorAll('.notification-item.unread, .owner-notification-item.unread, .customer-notification-item.unread');
            dropdownItems.forEach(function (el) {
                el.classList.remove('unread');
                el.classList.add('read');
            });
        })
        .catch(function (err) {
            console.error('[VDVC] markAllFromModal error:', err);
            if (markBtn) markBtn.disabled = false;
        });
    };

    // Owner mark all wrapper for header link
    window.markAllOwnerAsRead = function (event) {
        if (event) {
            event.preventDefault();
            event.stopPropagation();
        }
        var formData = new FormData();
        formData.append('mark_all_read', '1');
        formData.append('ajax', '1');

        fetch('owner_notifications.php', {
            method: 'POST',
            credentials: 'same-origin',
            body: formData
        })
        .then(function () {
            clearNavbarBadge('.owner-notification-badge');
            var items = document.querySelectorAll('.owner-notification-item.unread');
            items.forEach(function (it) { it.classList.remove('unread'); });
            window.location.reload();
        })
        .catch(function (err) {
            console.error('[VDVC] markAllOwnerAsRead error:', err);
        });
    };

    // Global Keydown Handler (Escape closes modal)
    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            var modal = document.getElementById('allNotificationsModal');
            if (modal && !modal.classList.contains('hidden')) {
                window.closeAllNotificationsModal();
            }
        }
    });

    // Global Click Handler (Backdrop closes modal; outside clicks close dropdowns)
    document.addEventListener('click', function (event) {
        var modal = document.getElementById('allNotificationsModal');
        if (modal && !modal.classList.contains('hidden') && event.target === modal) {
            window.closeAllNotificationsModal();
            return;
        }

        // Close dropdown when clicking outside
        ['customer-notification-wrapper', 'topbar-right', 'owner-topbar-right', 'notification-wrapper'].forEach(function (cls) {
            var wrapper = document.querySelector('.' + cls);
            if (wrapper && !wrapper.contains(event.target)) {
                // Handled specifically per role
            }
        });
    });

})(window, document);
