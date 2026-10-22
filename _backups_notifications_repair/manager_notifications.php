<?php
// =========================================================
// MANAGER NOTIFICATIONS PAGE
// =========================================================

require_once 'session_config.php';
require 'db.php';

date_default_timezone_set('Asia/Manila');

// =========================================================
// SECURITY CHECK
// =========================================================

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['manager', 'owner'])) {
    header("Location: login.php");
    exit;
}

$user_id = (int)$_SESSION['user_id'];

// =========================================================
// MARK SINGLE NOTIFICATION AS READ (AJAX)
// =========================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_read'])) {
    $notif_id = intval($_POST['notification_id']);

    $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $notif_id, $user_id);
    $stmt->execute();
    $stmt->close();

    header('Content-Type: application/json');
    echo json_encode(['success' => true]);
    exit;
}

// =========================================================
// MARK ALL AS READ
// =========================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_all_read'])) {
    $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->close();

    header("Location: manager_notifications.php");
    exit;
}

// =========================================================
// GET ALL NOTIFICATIONS
// =========================================================

$notifications = [];

$notif_stmt = $conn->prepare("
    SELECT id, title, description, type, is_read, created_at
    FROM notifications
    WHERE user_id = ?
    ORDER BY created_at DESC
");

if ($notif_stmt) {
    $notif_stmt->bind_param("i", $user_id);
    $notif_stmt->execute();
    $result_notif = $notif_stmt->get_result();
    while ($row = $result_notif->fetch_assoc()) {
        $notifications[] = $row;
    }
    $notif_stmt->close();
}

$unread_count = 0;
foreach ($notifications as $n) {
    if (!$n['is_read']) $unread_count++;
}

// =========================================================
// GET USER NAME
// =========================================================

$manager_name = 'Manager';
if (isset($_SESSION['name']) && $_SESSION['name'] !== '') {
    $manager_name = $_SESSION['name'];
} elseif (isset($_SESSION['username']) && $_SESSION['username'] !== '') {
    $manager_name = $_SESSION['username'];
} else {
    $name_stmt = $conn->prepare("SELECT fullname FROM users WHERE id = ? LIMIT 1");
    if ($name_stmt) {
        $name_stmt->bind_param("i", $user_id);
        $name_stmt->execute();
        $name_result = $name_stmt->get_result();
        if ($name_result && $name_result->num_rows > 0) {
            $name_row = $name_result->fetch_assoc();
            $manager_name = $name_row['fullname'];
        }
        $name_stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications – Manager Panel</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        * { box-sizing: border-box; }

        body {
            margin: 0;
            padding: 0;
            font-family: 'Poppins', Arial, sans-serif;
            background: #f3f1eb;
            color: #3f4b45;
        }

        /* ───── Main layout ───── */
        .main-content {
            margin-left: 260px;
            padding: 32px 28px;
            min-height: 100vh;
        }

        /* ───── Page header ───── */
        .page-header {
            margin-bottom: 24px;
        }
        .page-header h1 {
            margin: 0 0 4px;
            font-size: 22px;
            font-weight: 700;
            color: #214f2c;
        }
        .page-header p {
            margin: 0;
            font-size: 13px;
            color: #6b7a72;
        }

        /* ───── Notifications wrapper ───── */
        .notifications-wrapper {
            background: #fff;
            border: 1px solid #dedbd2;
            border-radius: 16px;
            box-shadow: 0 4px 24px rgba(0,0,0,0.07);
            overflow: hidden;
        }

        /* ───── Header bar ───── */
        .notifications-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px 26px;
            background: linear-gradient(135deg, #214f2c 0%, #2d6b3d 100%);
        }
        .notifications-title {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .notifications-title i {
            font-size: 20px;
            color: #b8d8b0;
        }
        .notifications-title h2 {
            margin: 0;
            font-size: 18px;
            font-weight: 600;
            color: #fff;
        }
        .unread-pill {
            background: #e74c3c;
            color: #fff;
            font-size: 11px;
            font-weight: 700;
            padding: 3px 9px;
            border-radius: 20px;
            letter-spacing: 0.3px;
        }

        /* ───── Mark-all button ───── */
        .mark-all-btn {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            background: rgba(255,255,255,0.15);
            color: #fff;
            border: 1px solid rgba(255,255,255,0.3);
            padding: 8px 18px;
            border-radius: 8px;
            font-size: 13px;
            font-family: 'Poppins', sans-serif;
            font-weight: 500;
            cursor: pointer;
            transition: background 0.2s, border-color 0.2s;
            backdrop-filter: blur(4px);
        }
        .mark-all-btn:hover {
            background: rgba(255,255,255,0.25);
            border-color: rgba(255,255,255,0.5);
        }

        /* ───── Filters / stats bar ───── */
        .stats-bar {
            display: flex;
            gap: 20px;
            padding: 14px 26px;
            background: #f9f8f5;
            border-bottom: 1px solid #e8e5dc;
            font-size: 13px;
            color: #5a6b60;
        }
        .stats-bar span strong {
            color: #214f2c;
        }

        /* ───── Notification list ───── */
        .notifications-list {
            max-height: 680px;
            overflow-y: auto;
        }
        .notifications-list::-webkit-scrollbar { width: 5px; }
        .notifications-list::-webkit-scrollbar-track { background: #f0eee8; }
        .notifications-list::-webkit-scrollbar-thumb { background: #c8d4c4; border-radius: 4px; }

        /* ───── Notification item ───── */
        .notification-item {
            display: flex;
            align-items: flex-start;
            gap: 16px;
            padding: 18px 26px;
            border-bottom: 1px solid #f0eee8;
            cursor: pointer;
            transition: background 0.15s;
            position: relative;
        }
        .notification-item:last-child { border-bottom: none; }
        .notification-item:hover { background: #faf9f7; }
        .notification-item.unread { background: #f0f5f1; }
        .notification-item.unread:hover { background: #e8f0e9; }

        /* Unread indicator dot */
        .notification-item.unread::before {
            content: '';
            position: absolute;
            left: 10px;
            top: 50%;
            transform: translateY(-50%);
            width: 7px;
            height: 7px;
            background: #214f2c;
            border-radius: 50%;
        }

        /* ───── Icon ───── */
        .notification-icon {
            width: 42px;
            height: 42px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            font-size: 16px;
        }
        .notification-icon.success { background: #e8f5e9; color: #2e7d32; }
        .notification-icon.alert   { background: #fff3e0; color: #e65100; }
        .notification-icon.info    { background: #e3f2fd; color: #1565c0; }
        .notification-icon.warning { background: #fff8e1; color: #f57f17; }

        /* ───── Content ───── */
        .notification-content { flex: 1; min-width: 0; }
        .notification-title {
            font-weight: 600;
            font-size: 14px;
            color: #1a2e1c;
            margin-bottom: 4px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .notification-item.unread .notification-title { color: #214f2c; }
        .notification-desc {
            font-size: 13px;
            color: #5a6b60;
            line-height: 1.5;
        }
        .notification-meta {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-top: 6px;
        }
        .notification-time {
            font-size: 11px;
            color: #9aaa9a;
        }
        .unread-label {
            font-size: 10px;
            font-weight: 600;
            color: #214f2c;
            background: #d4edda;
            padding: 2px 7px;
            border-radius: 8px;
            text-transform: uppercase;
            letter-spacing: 0.4px;
        }

        /* ───── Empty state ───── */
        .empty-state {
            text-align: center;
            padding: 70px 20px;
            color: #9aaa9a;
        }
        .empty-state i {
            font-size: 52px;
            margin-bottom: 18px;
            color: #c8d4c4;
            display: block;
        }
        .empty-state h3 {
            font-size: 17px;
            font-weight: 600;
            margin: 0 0 8px;
            color: #6b7a72;
        }
        .empty-state p {
            font-size: 13px;
            margin: 0;
        }

        /* ───── Toast ───── */
        .toast {
            position: fixed;
            bottom: 28px;
            right: 28px;
            background: #214f2c;
            color: #fff;
            padding: 12px 20px;
            border-radius: 10px;
            font-size: 13px;
            font-weight: 500;
            box-shadow: 0 4px 16px rgba(0,0,0,0.18);
            display: none;
            z-index: 9999;
            animation: slideUp 0.3s ease;
        }
        @keyframes slideUp {
            from { transform: translateY(20px); opacity: 0; }
            to   { transform: translateY(0);   opacity: 1; }
        }

        @media (max-width: 900px) {
            .main-content { margin-left: 0; padding: 20px 16px; }
        }
    </style>
</head>
<body>

<?php include('manager_panel.php'); ?>

<div class="main-content">

    <div class="page-header">
        <h1><i class="fa-solid fa-bell" style="font-size:18px; margin-right:8px; color:#214f2c;"></i>Notifications</h1>
        <p>All system notifications for your account, <?php echo htmlspecialchars($manager_name); ?>.</p>
    </div>

    <div class="notifications-wrapper">

        <!-- Header -->
        <div class="notifications-header">
            <div class="notifications-title">
                <i class="fa-solid fa-bell"></i>
                <h2>Notification Center</h2>
                <?php if ($unread_count > 0): ?>
                    <span class="unread-pill"><?php echo $unread_count; ?> new</span>
                <?php endif; ?>
            </div>
            <?php if ($unread_count > 0): ?>
                <form method="POST" style="margin:0;">
                    <input type="hidden" name="mark_all_read" value="1">
                    <button type="submit" class="mark-all-btn">
                        <i class="fa-solid fa-check-double"></i>
                        Mark All as Read
                    </button>
                </form>
            <?php endif; ?>
        </div>

        <!-- Stats bar -->
        <div class="stats-bar">
            <span>Total: <strong><?php echo count($notifications); ?></strong></span>
            <span>Unread: <strong><?php echo $unread_count; ?></strong></span>
            <span>Read: <strong><?php echo count($notifications) - $unread_count; ?></strong></span>
        </div>

        <!-- List -->
        <div class="notifications-list">
            <?php if (count($notifications) > 0): ?>
                <?php foreach ($notifications as $notif): ?>
                    <?php
                        $icon = 'fa-bell';
                        $icon_class = 'info';
                        if ($notif['type'] === 'success') { $icon = 'fa-check'; $icon_class = 'success'; }
                        if ($notif['type'] === 'alert')   { $icon = 'fa-triangle-exclamation'; $icon_class = 'alert'; }
                        if ($notif['type'] === 'warning') { $icon = 'fa-circle-exclamation'; $icon_class = 'warning'; }
                        if ($notif['type'] === 'info')    { $icon = 'fa-circle-info'; $icon_class = 'info'; }
                    ?>
                    <div class="notification-item <?php echo !$notif['is_read'] ? 'unread' : ''; ?>"
                         onclick="markAsRead(<?php echo (int)$notif['id']; ?>, this)">
                        <div class="notification-icon <?php echo htmlspecialchars($icon_class); ?>">
                            <i class="fa-solid <?php echo $icon; ?>"></i>
                        </div>
                        <div class="notification-content">
                            <div class="notification-title"><?php echo htmlspecialchars($notif['title']); ?></div>
                            <div class="notification-desc"><?php echo htmlspecialchars($notif['description']); ?></div>
                            <div class="notification-meta">
                                <span class="notification-time">
                                    <i class="fa-regular fa-clock" style="margin-right:3px;"></i>
                                    <?php echo date('M d, Y g:i A', strtotime($notif['created_at'])); ?>
                                </span>
                                <?php if (!$notif['is_read']): ?>
                                    <span class="unread-label">New</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fa-regular fa-bell-slash"></i>
                    <h3>No Notifications Yet</h3>
                    <p>You're all caught up — notifications will appear here when there's activity.</p>
                </div>
            <?php endif; ?>
        </div>

    </div><!-- /.notifications-wrapper -->
</div><!-- /.main-content -->

<div class="toast" id="toast-msg"></div>

<script>
function showToast(msg) {
    var t = document.getElementById('toast-msg');
    t.textContent = msg;
    t.style.display = 'block';
    setTimeout(function() { t.style.display = 'none'; }, 2500);
}

function markAsRead(notificationId, el) {
    if (!el.classList.contains('unread')) return; // already read

    fetch('manager_notifications.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'mark_read=1&notification_id=' + notificationId
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.success) {
            el.classList.remove('unread');
            var label = el.querySelector('.unread-label');
            if (label) label.remove();
            showToast('Notification marked as read.');
        }
    })
    .catch(function() {});
}
</script>

</body>
</html>
