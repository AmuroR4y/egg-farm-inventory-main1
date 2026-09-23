<?php
require_once 'session_config.php';
require_once 'db.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'owner') {
    http_response_code(403);
    echo json_encode(array('status' => 'error', 'message' => 'Owner access required.'));
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $notificationId = isset($_POST['notification_id']) ? (int)$_POST['notification_id'] : 0;
    if (isset($_POST['mark_all_read'])) {
        $stmt = mysqli_prepare($conn, "UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0");
        mysqli_stmt_bind_param($stmt, 'i', $_SESSION['user_id']);
    } elseif ($notificationId > 0) {
        $stmt = mysqli_prepare($conn, "UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
        mysqli_stmt_bind_param($stmt, 'ii', $notificationId, $_SESSION['user_id']);
    } else {
        http_response_code(400);
        echo json_encode(array('status' => 'error', 'message' => 'Invalid notification request.'));
        exit();
    }
    $success = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    echo json_encode(array('status' => $success ? 'success' : 'error'));
    exit();
}

function fetch_all_rows($conn, $sql) {
    $rows = array();
    $result = mysqli_query($conn, $sql);
    if (!$result) {
        throw new Exception(mysqli_error($conn));
    }
    while ($row = mysqli_fetch_assoc($result)) {
        $rows[] = $row;
    }
    return $rows;
}

function normalize_status($status) {
    return strtolower(str_replace(' ', '_', trim((string)$status)));
}

try {
    $users = array();
    foreach (fetch_all_rows($conn, "SELECT id, fullname, email, role, status FROM users ORDER BY id") as $row) {
        $users[] = array(
            'id' => (int)$row['id'],
            'name' => $row['fullname'],
            'email' => $row['email'],
            'role' => ucfirst($row['role']),
            'department' => 'N/A',
            'status' => $row['status'] === 'active' ? 'active' : 'deactivated'
        );
    }

    $profileRows = fetch_all_rows($conn, "SELECT fullname, email FROM users WHERE id = " . (int)$_SESSION['user_id'] . " AND role = 'owner' LIMIT 1");
    $profile = $profileRows ? array('fullName' => $profileRows[0]['fullname'], 'email' => $profileRows[0]['email'], 'phone' => '', 'jobTitle' => '', 'avatar' => '') : array();

    $reservations = array();
    $deliveries = array();
    foreach (fetch_all_rows($conn, "SELECT id, user_id, customer_name, egg_type, quantity, delivery_method, reservation_date, total_price, created_at, reserved_at, reservation_code, status FROM reservations ORDER BY id DESC") as $row) {
        $status = normalize_status($row['status']);
        $reservation = array(
            'id' => (int)$row['id'],
            'reservationCode' => $row['reservation_code'],
            'customerName' => $row['customer_name'],
            'channel' => $row['delivery_method'] ?: 'Unspecified',
            'product' => $row['egg_type'],
            'quantity' => (int)$row['quantity'],
            'amount' => (float)$row['total_price'],
            'status' => $status,
            'createdAt' => $row['created_at'],
            'scheduledAt' => $row['reservation_date'],
            'userId' => (int)$row['user_id']
        );
        $reservations[] = $reservation;

        if ($row['delivery_method'] === 'Delivery') {
            $deliveryStatus = $status === 'completed' || $status === 'delivered' ? 'completed' : ($status === 'confirmed' ? 'scheduled' : $status);
            $deliveries[] = array(
                'id' => (int)$row['id'],
                'reservationId' => (int)$row['id'],
                'customerName' => $row['customer_name'],
                'product' => $row['egg_type'],
                'quantity' => (int)$row['quantity'],
                'revenue' => (float)$row['total_price'],
                'status' => $deliveryStatus,
                'route' => $row['delivery_method'],
                'createdAt' => $row['created_at'],
                'completedAt' => $deliveryStatus === 'completed' ? ($row['reserved_at'] ?: $row['created_at']) : ''
            );
        }
    }

    $notifications = array();
    foreach (fetch_all_rows($conn, "SELECT id, title, description, type, is_read, created_at FROM notifications WHERE user_id = " . (int)$_SESSION['user_id'] . " ORDER BY created_at DESC") as $row) {
        $notifications[] = array(
            'id' => (int)$row['id'],
            'title' => $row['title'],
            'message' => $row['description'],
            'type' => $row['type'],
            'read' => (bool)$row['is_read'],
            'createdAt' => $row['created_at']
        );
    }

    $eggs = fetch_all_rows($conn, "SELECT id, batch_id, harvest_date, harvest_time, egg_size, quantity, current_stock, movement_type, reason, date_logged FROM egg_inventory ORDER BY id DESC");
    $supplies = fetch_all_rows($conn, "SELECT id, batch_id, item_category, item_name, quantity, current_stock, action_type, reason, date_logged FROM supply_inventory ORDER BY id DESC");
    $snapshotEggs = fetch_all_rows($conn, "SELECT e.* FROM egg_inventory e INNER JOIN (SELECT egg_size, MAX(id) AS id FROM egg_inventory GROUP BY egg_size) latest ON latest.id = e.id");
    $snapshotSupplies = fetch_all_rows($conn, "SELECT s.* FROM supply_inventory s INNER JOIN (SELECT item_category, item_name, MAX(id) AS id FROM supply_inventory GROUP BY item_category, item_name) latest ON latest.id = s.id");

    echo json_encode(array(
        'status' => 'success',
        'users' => $users,
        'profile' => $profile,
        'reservations' => $reservations,
        'deliveries' => $deliveries,
        'notifications' => $notifications,
        'reports' => array(),
        'activityLog' => array(),
        'inventory' => array(
            'eggs' => $eggs,
            'supplies' => $supplies,
            'snapshotEggs' => $snapshotEggs,
            'snapshotSupplies' => $snapshotSupplies,
            'source' => 'database',
            'loadedAt' => date('Y-m-d H:i:s'),
            'error' => ''
        )
    ));
} catch (Exception $error) {
    http_response_code(500);
    echo json_encode(array('status' => 'error', 'message' => 'Unable to load owner data.'));
}
