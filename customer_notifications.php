<?php
// =========================================================
// CUSTOMER NOTIFICATIONS PAGE
// =========================================================

if (!isset($_SESSION)) {
    session_start();
}

date_default_timezone_set('Asia/Manila');

require 'db.php';

// =========================================================
// SECURITY CHECK
// =========================================================

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'customer') {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// =========================================================
// MARK NOTIFICATION AS READ (AJAX HANDLER)
// =========================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_read'])) {
    $notif_id = intval($_POST['notification_id']);
    
    $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $notif_id, $user_id);
    $stmt->execute();
    $stmt->close();
    
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
    
    header("Location: customer_notifications.php");
    exit;
}

// =========================================================
// GET ALL NOTIFICATIONS
// =========================================================

$notifications = array();

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

// Count unread
$unread_count = 0;
foreach ($notifications as $n) {
    if (!$n['is_read']) {
        $unread_count++;
    }
}

// Get user info
$current_user_name = 'Customer';
$user_stmt = $conn->prepare("SELECT fullname FROM users WHERE id = ? LIMIT 1");
if ($user_stmt) {
    $user_stmt->bind_param("i", $user_id);
    $user_stmt->execute();
    $user_result = $user_stmt->get_result();
    if ($user_result && $user_result->num_rows > 0) {
        $user_data = $user_result->fetch_assoc();
        $current_user_name = $user_data['fullname'];
    }
    $user_stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications - Customer</title>
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
        .main-content {
            margin-left: 260px;
            padding: 20px;
            min-height: 100vh;
        }
        .notifications-wrapper {
            background: #fff;
            border: 1px solid #dedbd2;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.06);
            overflow: hidden;
        }
        .notifications-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px 24px;
            background: #f9f8f5;
            border-bottom: 1px solid #e8e5dc;
        }
        .notifications-title {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .notifications-title h1 {
            margin: 0;
            font-size: 20px;
            font-weight: 600;
            color: #214f2c;
        }
        .notification-badge {
            background: #e74c3c;
            color: #fff;
            font-size: 12px;
            font-weight: 600;
            padding: 2px 8px;
            border-radius: 10px;
        }
        .mark-all-btn {
            background: #214f2c;
            color: #fff;
            border: none;
            padding: 8px 16px;
            border-radius: 8px;
            font-size: 13px;
            cursor: pointer;
            transition: background 0.2s;
        }
        .mark-all-btn:hover { background: #2d6b3d; }
        .notifications-list {
            max-height: 600px;
            overflow-y: auto;
        }
        .notification-item {
            display: flex;
            align-items: flex-start;
            gap: 16px;
            padding: 16px 24px;
            border-bottom: 1px solid #f0eee8;
            transition: background 0.15s;
            cursor: pointer;
        }
        .notification-item:hover { background: #faf9f7; }
        .notification-item.unread { background: #f0f7f0; }
        .notification-item.unread:hover { background: #e8f0e8; }
        .notification-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        .notification-icon.success { background: #e8f5e9; color: #2e7d32; }
        .notification-icon.alert { background: #ffebee; color: #c62828; }
        .notification-icon.info { background: #e3f2fd; color: #1565c0; }
        .notification-content { flex: 1; }
        .notification-title {
            font-weight: 600;
            font-size: 14px;
            color: #1a1a1a;
            margin-bottom: 4px;
        }
        .notification-desc {
            font-size: 13px;
            color: #666;
            line-height: 1.4;
        }
        .notification-time {
            font-size: 12px;
            color: #999;
            flex-shrink: 0;
        }
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #999;
        }
        .empty-state i {
            font-size: 48px;
            margin-bottom: 16px;
            color: #ddd;
        }
        .empty-state h3 {
            font-size: 18px;
            font-weight: 500;
            margin-bottom: 8px;
            color: #666;
        }
        @media (max-width: 900px) {
            .main-content { margin-left: 0; }
        }
    </style>
</head>
<body>

<?php include('customer_panel.php'); ?>

<div class="main-content">
    <div class="notifications-wrapper">
        <div class="notifications-header">
            <div class="notifications-title">
                <i class="fa-solid fa-bell" style="color: #214f2c;"></i>
                <h1>Notifications</h1>
                <?php if ($unread_count > 0): ?>
                    <span class="notification-badge"><?php echo $unread_count; ?></span>
                <?php endif; ?>
            </div>
            <?php if ($unread_count > 0): ?>
                <form method="POST" style="margin: 0;">
                    <input type="hidden" name="mark_all_read" value="1">
                    <button type="submit" class="mark-all-btn">
                        Mark All as Read
                    </button>
                </form>
            <?php endif; ?>
        </div>

        <div class="notifications-list">
            <?php if (count($notifications) > 0): ?>
                <?php foreach ($notifications as $notification): ?>
                    <div class="notification-item <?php echo !$notification['is_read'] ? 'unread' : ''; ?>"
                         onclick="markAsRead(<?php echo $notification['id']; ?>)">
                        <div class="notification-icon <?php echo htmlspecialchars($notification['type']); ?>">
                            <?php
                            $icon = 'fa-bell';
                            if ($notification['type'] === 'success') $icon = 'fa-check';
                            if ($notification['type'] === 'alert') $icon = 'fa-exclamation';
                            if ($notification['type'] === 'info') $icon = 'fa-info';
                            ?>
                            <i class="fa-solid <?php echo $icon; ?>"></i>
                        </div>
                        <div class="notification-content">
                            <div class="notification-title"><?php echo htmlspecialchars($notification['title']); ?></div>
                            <div class="notification-desc"><?php echo htmlspecialchars($notification['description']); ?></div>
                        </div>
                        <div class="notification-time">
                            <?php echo date('M d, Y g:i A', strtotime($notification['created_at'])); ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fa-regular fa-bell"></i>
                    <h3>No Notifications</h3>
                    <p>You don't have any notifications yet.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
function markAsRead(notificationId) {
    fetch('customer_notifications.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'mark_read=1&notification_id=' + notificationId
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        }
    });
}
</script>

</body>
</html>
