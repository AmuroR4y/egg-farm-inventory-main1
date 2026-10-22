<?php
// =========================================================
// CUSTOMER HISTORY
// FULL REPLACEMENT
// =========================================================

// Include standardized session configuration FIRST
require_once 'session_config.php';

date_default_timezone_set('Asia/Manila');

date_default_timezone_set('Asia/Manila');

require 'db.php';

// =========================================================
// SECURITY CHECK
// =========================================================

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$user_role = isset($_SESSION['role']) ? $_SESSION['role'] : 'customer';

if ($user_role !== 'customer') {
    header("Location: login.php");
    exit;
}

// =========================================================
// GET CURRENT USER INFORMATION
// =========================================================

$current_user_name = 'Customer';

$user_stmt = $conn->prepare("
    SELECT fullname, username
    FROM users
    WHERE id = ?
    LIMIT 1
");

if ($user_stmt) {
    $user_stmt->bind_param("i", $user_id);
    $user_stmt->execute();

    $user_result = $user_stmt->get_result();

    if ($user_result && $user_result->num_rows > 0) {
        $user_data = $user_result->fetch_assoc();

        if (!empty($user_data['fullname'])) {
            $current_user_name = $user_data['fullname'];
        } elseif (!empty($user_data['username'])) {
            $current_user_name = $user_data['username'];
        }
    }

    $user_stmt->close();
}

// =========================================================
// FILTERS
// =========================================================

$search = isset($_GET['search'])
    ? trim($_GET['search'])
    : '';

$filter_egg = isset($_GET['egg_type'])
    ? trim($_GET['egg_type'])
    : '';

$filter_method = isset($_GET['delivery_method'])
    ? trim($_GET['delivery_method'])
    : '';

$filter_status = isset($_GET['status'])
    ? trim($_GET['status'])
    : '';

$filter_date = isset($_GET['date_range'])
    ? trim($_GET['date_range'])
    : '';

$page = isset($_GET['page'])
    ? max(1, (int)$_GET['page'])
    : 1;

$limit = 7;

// =========================================================
// EGG OPTIONS
// =========================================================

$egg_options = array(
    'Extra Small',
    'Small',
    'Medium',
    'Large',
    'Extra Large',
    'Jumbo',
    'Super Jumbo',
    'Double Yolk'
);

// =========================================================
// NOTIFICATIONS
// =========================================================

$notifications = array();

$notif_stmt = $conn->prepare("
    SELECT title, description, type, is_read, created_at
    FROM notifications
    WHERE user_id = ?
    ORDER BY created_at DESC
    LIMIT 15
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
    if (!$n['is_read']) {
        $unread_count++;
    }
}

// =========================================================
// RESERVATION QUERY
// =========================================================

$query = "
    SELECT *
    FROM reservations
    WHERE 1=1
";

$params = array();
$types = "";

// Customer history is always restricted to the authenticated customer.
$query .= " AND user_id = ?";
$params[] = $user_id;
$types .= "i";

// =========================================================
// SEARCH
// =========================================================

if ($search !== '') {

    $query .= "
        AND (
            customer_name LIKE ?
            OR contact_number LIKE ?
            OR id LIKE ?
        )
    ";

    $search_param = "%" . $search . "%";

    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;

    $types .= "sss";
}

// =========================================================
// EGG SIZE FILTER
// =========================================================

if ($filter_egg !== '') {

    $query .= " AND egg_type = ?";

    $params[] = $filter_egg;
    $types .= "s";
}

// =========================================================
// DELIVERY METHOD FILTER
// =========================================================

if ($filter_method !== '') {

    $query .= " AND delivery_method = ?";

    $params[] = $filter_method;
    $types .= "s";
}


// =========================================================
// STATUS FILTER
// =========================================================

if ($filter_status !== '') {

    $query .= " AND status = ?";

    $params[] = $filter_status;
    $types .= "s";
}


// =========================================================
// DATE RANGE FILTER
// =========================================================

if ($filter_date !== '') {

    if ($filter_date === 'today') {

        $query .= "
            AND DATE(created_at) = CURDATE()
        ";

    } elseif ($filter_date === '7days') {

        $query .= "
            AND created_at >= DATE_SUB(
                CURDATE(),
                INTERVAL 7 DAY
            )
        ";

    } elseif ($filter_date === '30days') {

        $query .= "
            AND created_at >= DATE_SUB(
                CURDATE(),
                INTERVAL 30 DAY
            )
        ";

    }
}


// =========================================================
// ORDER
// =========================================================

$query .= " ORDER BY id DESC";

// =========================================================
// EXECUTE
// =========================================================

$stmt = $conn->prepare($query);

if (!$stmt) {
    die("Unable to load reservation history.");
}

if (!empty($params)) {

    $bind_names = array();
    $bind_names[] = $types;

    for ($i = 0; $i < count($params); $i++) {
        $bind_names[] = &$params[$i];
    }

    call_user_func_array(
        array($stmt, 'bind_param'),
        $bind_names
    );
}

$stmt->execute();

$result = $stmt->get_result();

// =========================================================
// GROUPING LOGIC
// =========================================================

$grouped_reservations = array();

while ($row = $result->fetch_assoc()) {

    $timestamp_key = isset($row['created_at'])
        ? $row['created_at']
        : $row['reservation_date'];

    $group_key =
        $row['customer_name'] . '_' .
        $row['contact_number'] . '_' .
        $timestamp_key . '_' .
        $row['delivery_method'];

    if (!isset($grouped_reservations[$group_key])) {

        $grouped_reservations[$group_key] = array(

            'id' => $row['id'],

            'ids' => array(
                $row['id']
            ),

            'customer_name' => $row['customer_name'],

            'contact_number' => $row['contact_number'],

            'delivery_address' => isset($row['delivery_address'])
                ? $row['delivery_address']
                : '',

            'delivery_method' => $row['delivery_method'],

            'reservation_date' => $row['reservation_date'],

            'created_at' => isset($row['created_at'])
                ? $row['created_at']
                : $row['reservation_date'],

            'status' => isset($row['status'])
                ? $row['status']
                : 'Pending',

            'total_price' => 0,

            'items' => array()
        );

    } else {

        $grouped_reservations[$group_key]['ids'][] = $row['id'];

    }

    // Add egg item
    $grouped_reservations[$group_key]['items'][] = array(

        'egg_type' => $row['egg_type'],

        'quantity' => (int)$row['quantity'],

        'total_price' => (float)$row['total_price']
    );

    $grouped_reservations[$group_key]['total_price'] +=
        (float)$row['total_price'];
}

$stmt->close();

// =========================================================
// CREATE DISPLAY RESERVATION CODES
// =========================================================

$daily_sequence = array();

foreach ($grouped_reservations as $key => $reservation) {

    $created_at = $reservation['created_at'] ?? null;
    $reservation_date = $reservation['reservation_date'] ?? null;

    $date_timestamp = false;

    if (!empty($created_at)) {
        $date_timestamp = strtotime($created_at);
    }

    if ($date_timestamp === false && !empty($reservation_date)) {
        $date_timestamp = strtotime($reservation_date);
    }

    if ($date_timestamp === false) {
        $date_timestamp = time();
    }

    $date_key = date('Ymd', $date_timestamp);

    if (!isset($daily_sequence[$date_key])) {
        $daily_sequence[$date_key] = 1;
    } else {
        $daily_sequence[$date_key]++;
    }

    $sequence = str_pad(
        $daily_sequence[$date_key],
        3,
        '0',
        STR_PAD_LEFT
    );

    $grouped_reservations[$key]['reservation_code'] =
        'RES-' . $date_key . '-' . $sequence;
}

// =========================================================
// REVERSE SEQUENCE SO NEWEST IS FIRST
// =========================================================

$grouped_reservations = array_values($grouped_reservations);

// =========================================================
// PAGINATION
// =========================================================

$total_records = count($grouped_reservations);

$total_pages = ($total_records > 0)
    ? ceil($total_records / $limit)
    : 1;

if ($page > $total_pages) {
    $page = $total_pages;
}

$offset = ($page - 1) * $limit;

$paged_reservations = array_slice(
    $grouped_reservations,
    $offset,
    $limit
);

// =========================================================
// STATISTICS
// =========================================================

$total_reservations = $total_records;

$total_trays = 0;
$total_spent = 0;

foreach ($grouped_reservations as $reservation) {

    foreach ($reservation['items'] as $item) {
        $total_trays += (int)$item['quantity'];
    }

    // Exclude cancelled reservations from total spent
    if (
        strtolower($reservation['status']) !== 'cancelled'
    ) {
        $total_spent += (float)$reservation['total_price'];
    }
}

// Recent reservation
$recent_reservation = 'No reservations';

if (!empty($grouped_reservations)) {

    $reservation_date = $grouped_reservations[0]['reservation_date'];
    $created_at = $grouped_reservations[0]['created_at'] ?? null;

    // Use created_at as fallback if reservation_date is null
    $date_to_parse = !empty($reservation_date) ? $reservation_date : $created_at;

    if (!empty($date_to_parse)) {
        $recent_timestamp = strtotime($date_to_parse);

        if ($recent_timestamp !== false) {
            $recent_reservation = date('F j, Y', $recent_timestamp);
        } else {
            $recent_reservation = 'Date not available';
        }
    } else {
        $recent_reservation = 'To be scheduled';
    }
}

// =========================================================
// PAGINATION URL
// =========================================================

function getPaginationUrl($page_num)
{
    $params = $_GET;

    $params['page'] = $page_num;

    return '?' . http_build_query($params);
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>VDVC - Reservation History</title>

    <script src="https://cdn.tailwindcss.com"></script>

    <link
        class="no-print"
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"
    >

    <style>

        /* =====================================================
           GLOBAL
        ===================================================== */

        * {
            box-sizing: border-box;
        }

        html,
        body {
            margin: 0;
            padding: 0;
            min-height: 100%;
        }

        body {
            background: #f3f1eb;
            color: #385247;
            font-family: Arial, Helvetica, sans-serif;
            font-size: 13px;
        }

        /* =====================================================
           MAIN CONTENT
        ===================================================== */

        .main-content {
            margin-left: 260px;
            min-height: 100vh;
            padding: 18px 28px 30px 28px;
        }

        .history-shell {
            width: 100%;
            max-width: 1340px;
            margin: 0 auto;
        }

        /* =====================================================
           PAGE CARD
        ===================================================== */
.history-card {
    background: linear-gradient(
        135deg,
        #eeece6 0%,
        #f5f3ed 100%
    );

    border: 1px solid #e2e0d9;
    border-radius: 22px;

    box-shadow:
        0 3px 12px rgba(55, 70, 60, 0.08);

    overflow: hidden;
}

     .customer-dashboard-topbar {

    min-height:
        86px;

    width:
        100%;

    display:
        flex;

    align-items:
        center;

    justify-content:
        space-between;

    padding:
        0 28px;

    background:
        #f7f6f2;

    border-bottom:
        1px solid #dddcd6;

    box-sizing:
        border-box;

    border-radius:
        22px 22px 0 0;
}


/* =========================================================
   HEADER TITLE
========================================================= */

.customer-page-title {

    margin:
        0;

    font-size:
        1.35rem;

    font-weight:
        700;

    color:
        #3f4b45;

    letter-spacing:
        -0.3px;
}


/* =========================================================
   HEADER RIGHT
========================================================= */

.customer-topbar-right {

    display:
        flex;

    align-items:
        center;

    gap:
        18px;

    height:
        100%;
}


/* =========================================================
   NOTIFICATION
========================================================= */

.customer-top-notification {

    position:
        relative;

    width:
        40px;

    height:
        40px;

    display:
        flex;

    align-items:
        center;

    justify-content:
        center;

    text-decoration:
        none;

    color:
        #65716b;

    font-size:
        1.2rem;

    transition:
        0.2s ease;
}


.customer-top-notification:hover {

    color:
        #527d59;
}


/* =========================================================
   NOTIFICATION BADGE
========================================================= */

.customer-notification-badge {

    position:
        absolute;

    top:
        1px;

    right:
        0;

    min-width:
        17px;

    height:
        17px;

    padding:
        0 4px;

    border-radius:
        50%;

    display:
        flex;

    align-items:
        center;

    justify-content:
        center;

    background:
        #527d59;

    color:
        #ffffff;

    font-size:
        0.58rem;

    font-weight:
        700;

    border:
        2px solid #f7f6f2;
}


/* =========================================================
   CUSTOMER NOTIFICATION DROPDOWN
========================================================= */

.customer-notification-dropdown {

    position:
        absolute;

    top:
        100%;

    right:
        0;

    margin-top:
        10px;

    width:
        380px;

    max-height:
        500px;

    background:
        #ffffff;

    border:
        1px solid #e0e4e0;

    border-radius:
        12px;

    box-shadow:
        0 8px 24px rgba(0, 0, 0, 0.12);

    z-index:
        1000;

    overflow:
        hidden;
}

.customer-notification-dropdown.hidden {

    display:
        none;
}

.customer-notification-dropdown-header {

    display:
        flex;

    align-items:
        center;

    justify-content:
        space-between;

    padding:
        16px 20px;

    border-bottom:
        1px solid #e8ebe7;

    background:
        #f7f6f2;
}

.customer-panel-title-group {

    display:
        flex;

    align-items:
        center;

    gap:
        10px;
}

.customer-panel-title-icon {

    width:
        32px;

    height:
        32px;

    border-radius:
        8px;

    display:
        flex;

    align-items:
        center;

    justify-content:
        center;

    background:
        #527d59;

    color:
        #ffffff;

    font-size:
        0.9rem;
}

.customer-dashboard-panel-title {

    font-size:
        0.95rem;

    font-weight:
        700;

    color:
        #214f2c;
}

.customer-mark-all-link {

    font-size:
        0.8rem;

    font-weight:
        600;

    color:
        #527d59;

    cursor:
        pointer;

    text-decoration:
        none;

    transition:
        0.2s ease;
}

.customer-mark-all-link:hover {

    color:
        #214f2c;

    text-decoration:
        underline;
}

.customer-notification-list {

    max-height:
        420px;

    overflow-y:
        auto;

    overflow-x:
        hidden;
}

.customer-notification-item {

    display:
        flex;

    align-items:
        flex-start;

    gap:
        12px;

    padding:
        16px 20px;

    border-bottom:
        1px solid #f0f2ef;

    transition:
        0.2s ease;

    cursor:
        default;
}

.customer-notification-item:hover {

    background:
        #f9faf8;
}

.customer-notification-item.unread {

    background:
        #f0f5e9;
}

.customer-notification-item.unread:hover {

    background:
        #e9f0df;
}

.customer-notification-icon {

    width:
        36px;

    height:
        36px;

    min-width:
        36px;

    border-radius:
        10px;

    display:
        flex;

    align-items:
        center;

    justify-content:
        center;

    font-size:
        0.95rem;
}

.customer-notification-icon.success {

    background:
        #dcfce7;

    color:
        #166534;
}

.customer-notification-icon.alert {

    background:
        #fee2e2;

    color:
        #991b1b;
}

.customer-notification-icon.info {

    background:
        #dbeafe;

    color:
        #1e40af;
}

.customer-notification-icon.warning {

    background:
        #fef3c7;

    color:
        #92400e;
}

.customer-notification-content {

    flex:
        1;

    min-width:
        0;
}

.customer-notification-title {

    font-size:
        0.85rem;

    font-weight:
        700;

    color:
        #214f2c;

    margin-bottom:
        4px;

    line-height:
        1.3;
}

.customer-notification-item.unread .customer-notification-title {

    color:
        #1a4023;
}

.customer-notification-description {

    font-size:
        0.8rem;

    color:
        #5c665f;

    line-height:
        1.4;

    margin-bottom:
        6px;
}

.customer-notification-time {

    font-size:
        0.72rem;

    color:
        #8b968f;

    font-weight:
        500;
}

.customer-notification-empty {

    display:
        flex;

    flex-direction:
        column;

    align-items:
        center;

    justify-content:
        center;

    padding:
        40px 20px;

    color:
        #8b968f;
}

.customer-notification-empty i {

    font-size:
        2rem;

    margin-bottom:
        12px;

    opacity:
        0.6;
}

.customer-notification-empty p {

    font-size:
        0.85rem;

    margin:
        0;
}

.customer-mark-read-btn {

    padding:
        6px 12px;

    font-size:
        0.75rem;

    font-weight:
        600;

    color:
        #527d59;

    background:
        #f0f5e9;

    border:
        1px solid #dce4d5;

    border-radius:
        6px;

    cursor:
        pointer;

    transition:
        0.2s ease;

    margin-left:
        auto;
}

.customer-mark-read-btn:hover {

    background:
        #e9f0df;

    border-color:
        #cdd6c5;
}


/* =========================================================
   HEADER DIVIDER
========================================================= */

.customer-topbar-divider {

    width:
        1px;

    height:
        42px;

    background:
        #deded8;
}


/* =========================================================
   CUSTOMER PROFILE
========================================================= */

.customer-top-profile {

    display:
        flex;

    align-items:
        center;

    gap:
        11px;

    min-width:
        190px;

    text-decoration:
        none;

    color:
        #3f4b45;
}


/* =========================================================
   CUSTOMER AVATAR
========================================================= */

.customer-avatar {

    width:
        54px;

    height:
        54px;

    min-width:
        54px;

    border-radius:
        50%;

    display:
        flex;

    align-items:
        center;

    justify-content:
        center;

    background:
        #e8ebe7;

    border:
        1px solid #d9ddd8;

    color:
        #527d59;

    font-size:
        1.25rem;

    box-shadow:
        0 2px 6px
        rgba(
            0,
            0,
            0,
            0.04
        );
}


/* =========================================================
   CUSTOMER ACCOUNT DETAILS
========================================================= */

.customer-account-details {

    display:
        flex;

    flex-direction:
        column;

    justify-content:
        center;

    min-width:
        100px;
}


/* =========================================================
   CUSTOMER NAME
========================================================= */

.customer-top-name {

    display:
        block;

    margin:
        0;

    color:
        #3f4b45;

    font-size:
        0.88rem;

    font-weight:
        700;

    line-height:
        1.2;
}


/* =========================================================
   DATE AND LIVE TIME
========================================================= */

.customer-date-time {

    display:
        flex;

    flex-direction:
        column;

    align-items:
        flex-start;

    gap:
        2px;

    margin-top:
        5px;

    white-space:
        nowrap;
}


.customer-date {

    font-size:
        0.63rem;

    color:
        #8a9590;

    line-height:
        1.2;
}


.customer-time {

    font-size:
        0.63rem;

    color:
        #527d59;

    font-weight:
        600;

    line-height:
        1.2;

    white-space:
        nowrap;
}


/* =========================================================
   DROPDOWN ICON
========================================================= */

.customer-dropdown-icon {

    width:
        28px;

    display:
        flex;

    align-items:
        center;

    justify-content:
        center;

    color:
        #65716b;

    font-size:
        0.7rem;
}

        /* =====================================================
           NOTIFICATION DROPDOWN
        ===================================================== */

        #notification_dropdown {
            position: absolute;

            right: 0;
            top: 45px;

            width: 315px;

            background: white;

            border: 1px solid #deded8;

            border-radius: 12px;

            box-shadow:
                0 12px 30px rgba(40, 55, 45, 0.14);

            z-index: 200;

            overflow: hidden;
        }

        .notification-header {
            padding: 12px 15px;

            background: #f7f6f2;

            border-bottom: 1px solid #ecebe5;

            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .notification-header-title {
            color: #304a3b;
            font-size: 13px;
            font-weight: 700;
        }

        .notification-new-count {
            font-size: 10px;
            padding: 3px 8px;
            border-radius: 20px;
            font-weight: 700;
        }

        .notification-item {
            padding: 12px 14px;

            display: flex;

            gap: 10px;

            border-bottom: 1px solid #f0efeb;
        }

        .notification-item:hover {
            background: #fafaf7;
        }

        .notification-icon {
            width: 31px;
            height: 31px;

            border-radius: 50%;

            flex-shrink: 0;

            display: flex;
            align-items: center;
            justify-content: center;

            font-size: 11px;
        }

        .notification-title-text {
            color: #40564b;
            font-size: 11px;
            font-weight: 700;

            margin-bottom: 3px;
        }

        .notification-description {
            color: #7d8983;
            font-size: 10px;
            line-height: 1.35;
        }

        .notification-time {
            color: #a3aaa6;
            font-size: 9px;
            margin-top: 4px;
        }

        /* =====================================================
           BODY
        ===================================================== */

        .history-body {
    padding: 26px 28px 30px;
    background-color: #ffffff;

    border-radius:
        0 0 22px 22px;
}
        /* =====================================================
           STAT CARDS
        ===================================================== */

        .stats-grid {
            display: grid;

            grid-template-columns:
                repeat(4, minmax(0, 1fr));

            gap: 15px;

            margin-bottom: 17px;
        }

        .stat-card {
            min-height: 118px;

            border-radius: 12px;

            padding: 17px 21px;

            border: 1px solid rgba(215, 220, 211, 0.8);

            display: flex;
            flex-direction: column;
            justify-content: center;

            box-shadow:
                0 2px 8px rgba(70, 80, 70, 0.04);
        }

        .stat-green {
            background: linear-gradient(
                135deg,
                #f0f5e9,
                #e9f0df
            );
        }

        .stat-yellow {
            background: linear-gradient(
                135deg,
                #faf3df,
                #f7eed8
            );
        }

        .stat-money {
            background: linear-gradient(
                135deg,
                #eef5e8,
                #e8f0df
            );
        }

        .stat-blue {
            background: linear-gradient(
                135deg,
                #edf4f6,
                #e8f1f4
            );
        }

        .stat-icon {
            width: 35px;
            height: 35px;

            border-radius: 50%;

            display: flex;
            align-items: center;
            justify-content: center;

            margin-bottom: 9px;

            font-size: 15px;
        }

        .stat-green .stat-icon,
        .stat-money .stat-icon {
            background: #d6e8c8;
            color: #397049;
        }

        .stat-yellow .stat-icon {
            background: #f3e4b9;
            color: #8d702e;
        }

        .stat-blue .stat-icon {
            background: #d9e9ee;
            color: #356c78;
        }

        .stat-label {
            font-size: 11px;

            color: #5d7468;

            font-weight: 700;

            margin-bottom: 4px;
        }

        .stat-value {
            font-size: 27px;

            line-height: 1.05;

            font-weight: 800;

            color: #214f2c;
        }

        .stat-blue .stat-value {
            font-size: 17px;
            color: #315d68;
        }
/* =====================================================
   SEARCH / FILTER TOOLBAR
===================================================== */

.history-toolbar {
    display: flex;
    align-items: center;
    gap: 8px;
    width: 100%;
    margin: 0 0 10px 0;
    padding: 0;
}


/* =====================================================
   SEARCH BOX
===================================================== */

.search-box {
    position: relative;
     flex: 0 0 50%;
    min-width: 180px;
}

.search-box i {
    position: absolute;
    left: 11px;
    top: 50%;
    transform: translateY(-50%);
    color: #718279;
    font-size: 10px;
    pointer-events: none;
}

.search-box input {
    width: 100%;
    height: 35px;

    padding: 0 10px 0 30px;

    border: 1px solid #d9ded9;
    border-radius: 8px;

    background: #ffffff;
    color: #52645a;

    font-family: Arial, Helvetica, sans-serif;
    font-size: 12px;

    outline: none;
}

.search-box input::placeholder {
    color: #8a9790;
}

.search-box input:focus {
    border-color: #8aa995;

    box-shadow:
        0 0 0 2px rgba(82, 125, 89, 0.08);
}


/* =====================================================
   FILTER SELECT
===================================================== */

.filter-select {
    height: 35px;

    width: 117px;

    flex: 0 0 117px;

    padding: 0 24px 0 10px;

    border: 1px solid #d9ded9;
    border-radius: 8px;

    background-color: #ffffff;

    color: #53665c;

    font-family: Arial, Helvetica, sans-serif;

    font-size: 10px;
    font-weight: 600;

    outline: none;

    cursor: pointer;
}

.filter-select:focus {
    border-color: #8aa995;

    box-shadow:
        0 0 0 2px rgba(82, 125, 89, 0.08);
}
/* =====================================================
   CLEAR FILTER BUTTON
===================================================== */

.clear-filter-button {
    height: 35px;

    width: 68px;

    flex: 0 0 68px;

    border: 1px solid #cfd8d1;

    border-radius: 8px;

    background: #ffffff;

    color: #52705f;

    font-family: Arial, Helvetica, sans-serif;

    font-size: 10px;

    font-weight: 700;

    cursor: pointer;

    display: inline-flex;

    align-items: center;

    justify-content: center;

    gap: 5px;

    transition: 0.15s ease;
}

.clear-filter-button i {
    font-size: 10px;
}

.clear-filter-button:hover {
    background: #edf4ee;

    border-color: #9eb4a4;

    color: #315f49;
}

/* =====================================================
   TABLE CARD
===================================================== */

.table-card {
    width: 100%;

    background: #ffffff;

    border: 1px solid #dfe4df;

    border-radius: 10px;

    overflow: hidden;

    box-shadow:
        0 1px 5px rgba(40, 60, 45, 0.035);
}

.table-scroll {
    width: 100%;
    overflow-x: auto;
}


/* =====================================================
   TABLE
===================================================== */

table {
    width: 100%;

    min-width: 760px;

    border-collapse: collapse;

    table-layout: fixed;
}


/* =====================================================
   TABLE HEADER
===================================================== */

thead {
    background: #e8eee8;
}

thead th {
    height: 34px;

    padding: 7px 10px;

    color: #42594d;

    font-size: 13px;

    font-weight: 800;

    text-align: left;

    white-space: nowrap;

    border-bottom: 1px solid #d9e0da;
}


/* =====================================================
   COLUMN WIDTHS
===================================================== */

thead th:nth-child(1) {
    width: 22%;
}

thead th:nth-child(2) {
    width: 13%;
}

thead th:nth-child(3) {
    width: 13%;
}

thead th:nth-child(4) {
    width: 14%;
}

thead th:nth-child(5) {
    width: 14%;
}

thead th:nth-child(6) {
    width: 10%;
}

thead th:nth-child(7) {
    width: 9%;
}

thead th:nth-child(8) {
    width: 11%;
}


/* =====================================================
   TABLE BODY
===================================================== */

tbody tr {
    background: #ffffff;

    transition: background 0.15s ease;
}

tbody tr:hover {
    background: #fafcf9;
}

tbody td {
    height: 39px;

    padding: 5px 10px;

    color: #65746d;

    font-size: 13px;

    vertical-align: middle;

    border-bottom: 1px solid #ecefea;
}

tbody tr:last-child td {
    border-bottom: none;
}


/* =====================================================
   RESERVATION CODE
===================================================== */

.reservation-code {
    display: flex;

    align-items: center;

    gap: 5px;

    color: #35684d;

    font-size: 13px;

    font-weight: 800;

    white-space: nowrap;
}

.reservation-code:before {
    content: "\f073";

    font-family: "Font Awesome 6 Free";

    font-weight: 900;

    font-size: 11px;

    color: #315e4a;
}


/* =====================================================
   SIZE
===================================================== */

.size-value {
    color: #5e7067;

    font-size: 13px;

    font-weight: 600;

    white-space: nowrap;
}


/* =====================================================
   TOTAL QUANTITY
===================================================== */

.trays-value {
    color: #65736c;

    font-size: 13px;

    font-weight: 600;

    white-space: nowrap;
}


/* =====================================================
   METHOD
===================================================== */

.method-cell {
    white-space: nowrap;
}

.method-display {
    display: inline-flex;

    align-items: center;

    gap: 7px;

    color: #53655c;

    font-size: 13px;

    font-weight: 600;

    white-space: nowrap;
}

.method-display i {
    width: 13px;

    color: #1f5b43;

    font-size: 9px;

    text-align: center;
}


/* =====================================================
   DATE
===================================================== */

.date-main {
    color: #596a61;

    font-size: 13px;

    font-weight: 600;

    white-space: nowrap;
}

.date-time {
    margin-top: 2px;

    color: #819088;

    font-size: 11px;

    white-space: nowrap;
}


/* =====================================================
   TOTAL
===================================================== */

.amount-value {
    color: #596b62;

    font-size: 13px;

    font-weight: 600;

    white-space: nowrap;
}


/* =====================================================
   STATUS
===================================================== */

.status-badge {
    display: inline-flex;

    align-items: center;
    justify-content: center;

    min-width: 51px;

    height: 20px;

    padding: 3px 9px;

    border-radius: 20px;

    font-size: 11px;

    font-weight: 800;

    white-space: nowrap;

    border: none;
}


/* COMPLETED */

.status-completed {
    background: #cfead8;

    color: #438052;
}


/* CANCELLED */

.status-cancelled {
    background: #f8dddd;

    color: #b95750;
}


/* =====================================================
   ACTION
===================================================== */

.actions-cell {
    text-align: center;

    white-space: nowrap;
}

.actions-wrapper {
    display: flex;

    align-items: center;

    justify-content: center;

    gap: 4px;
}

.action-button {
    height: 27px;

    border-radius: 7px;

    display: inline-flex;

    align-items: center;

    justify-content: center;

    cursor: pointer;

    font-family: Arial, Helvetica, sans-serif;

    font-size: 11px;

    font-weight: 700;

    transition: 0.15s ease;
}


/* =====================================================
   PRINT BUTTON
===================================================== */

.print-button {
    width: 58px;

    background: #ffffff;

    color: #315f49;

    border: 1px solid #2f684d;
}

.print-button i {
    margin-right: 4px;

    font-size: 10px;
}

.print-button:hover {
    background: #edf5ef;
}


/* =====================================================
   TABLE FOOTER
===================================================== */

.table-footer {
    min-height: 40px;

    padding: 7px 9px;

    display: flex;

    align-items: center;

    justify-content: space-between;

    background: #ffffff;

    border-top: 1px solid #ecefea;

    color: #829089;

    font-size: 8px;
}

.footer-number {
    color: #66776e;

    font-weight: 700;
}


/* =====================================================
   PAGINATION
===================================================== */

.pagination {
    display: flex;

    align-items: center;

    gap: 4px;
}

.pagination a {
    width: 23px;
    height: 23px;

    border-radius: 50%;

    border: 1px solid #dfe3df;

    background: #ffffff;

    color: #63736a;

    text-decoration: none;

    display: flex;

    align-items: center;

    justify-content: center;

    font-size: 8px;

    font-weight: 700;
}

.pagination a:hover {
    background: #f1f4f1;
}

.pagination a.active {
    background: #214f2c;

    border-color: #214f2c;

    color: #ffffff;
}

.pagination a.disabled {
    color: #c0c7c2;

    background: #f8f9f7;

    pointer-events: none;
}
        /* =====================================================
           CANCEL MODAL
        ===================================================== */

        #cancelModal {
            z-index: 500;
        }

        .cancel-modal-box {
            width: 100%;
            max-width: 390px;

            background: #ffffff;

            border-radius: 14px;

            padding: 25px;

            box-shadow:
                0 20px 50px rgba(0, 0, 0, 0.18);
        }

        /* =====================================================
           PRINT WINDOW
        ===================================================== */

        #receipt-print-window {
            display: none;
        }

        /* =====================================================
           RESPONSIVE
        ===================================================== */

        @media (max-width: 1100px) {

            .stats-grid {
                grid-template-columns:
                    repeat(2, minmax(0, 1fr));
            }

            .history-toolbar {
                flex-wrap: wrap;
            }

            .search-box {
                max-width: none;
                flex: 1 1 280px;
            }

        }

        @media (max-width: 850px) {

            .main-content {
                margin-left: 0;
                padding: 12px;
            }

            .history-header {
                padding: 0 18px;
            }

            .history-title {
                font-size: 22px;
            }

            .history-title-icon {
                width: 38px;
                height: 38px;
                font-size: 18px;
            }

            .user-info,
            .user-chevron {
                display: none;
            }

            .history-body {
                padding: 18px;
            }

        }

        @media (max-width: 600px) {

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .history-title {
                font-size: 19px;
            }

            .user-avatar {
                width: 39px;
                height: 39px;
                font-size: 15px;
            }

            .header-right {
                gap: 6px;
            }

            .history-toolbar {
                align-items: stretch;
            }

            .list-button {
                width: 45px;
            }

            .search-box {
                flex-basis: calc(100% - 57px);
            }

            .filter-select {
                flex: 1;
                width: auto;
            }

            .table-footer {
                flex-direction: column;
                align-items: flex-start;
            }

        }

        /* =====================================================
           PRINT
        ===================================================== */

     @media print {

    @page {
        size: 80mm auto;
        margin: 0;
    }

    html,
    body {
        margin: 0 !important;
        padding: 0 !important;
        width: 80mm !important;
        background: #ffffff !important;
    }

    body * {
        visibility: hidden;
    }

    #receipt-print-window,
    #receipt-print-window * {
        visibility: visible;
    }

    #receipt-print-window {
        position: absolute;
        left: 0;
        top: 0;
display:none;
        width: 80mm !important;
        height: auto !important;
        min-height: 0 !important;

        padding: 5mm !important;
        margin: 0 !important;

        background: #ffffff !important;
        color: #000000 !important;

        box-sizing: border-box;
    }

    #receipt-print-window > div {
        width: 100% !important;
        max-width: none !important;
        min-height: 0 !important;

        margin: 0 !important;
        padding: 0 !important;

        border: none !important;
        box-shadow: none !important;
    }

    .no-print {
        display: none !important;
    }
}

    </style>

</head>

<body>

<?php include('customer_panel.php'); ?>

<div class="main-content">

    <div class="history-shell">

        <div class="history-card">

       <!-- =====================================================
     CUSTOMER DASHBOARD TOPBAR
===================================================== -->

<div class="customer-dashboard-topbar">

    <div class="customer-page-title">
        Reservation History
    </div>

    <div class="customer-topbar-right">

        <!-- NOTIFICATION -->

        <div style="position: relative;">
            <button
                type="button"
                class="customer-top-notification"
                title="Notifications"
                onclick="event.stopPropagation(); toggleCustomerNotificationDropdown()"
            >
                <i class="fa-regular fa-bell"></i>
                <?php if ($unread_count > 0): ?>
                    <span class="customer-notification-badge"><?php echo $unread_count > 99 ? '99+' : $unread_count; ?></span>
                <?php endif; ?>
            </button>

            <!-- NOTIFICATION DROPDOWN -->
            <div
                class="customer-notification-dropdown hidden"
                id="customerNotificationDropdown"
            >
                <div class="customer-notification-dropdown-header">
                    <div class="customer-panel-title-group">
                        <div class="customer-panel-title-icon">
                            <i class="fa-solid fa-bell"></i>
                        </div>
                        <div class="customer-dashboard-panel-title">
                            Notifications
                        </div>
                    </div>
                    <a class="customer-mark-all-link" onclick="markAllCustomerAsRead(event)">
                        Mark All as Read
                    </a>
                </div>

                <div class="customer-notification-list" id="customerNotificationList">
                    <!-- Notifications will be loaded here via JavaScript -->
                </div>
            </div>
        </div>

        <!-- DIVIDER -->

        <div class="customer-topbar-divider"></div>

        <!-- CUSTOMER PROFILE -->

        <a
            href="customer_profile.php"
            class="customer-top-profile"
        >

            <!-- AVATAR -->

            <div class="customer-avatar">
                <i class="fa-solid fa-user"></i>
            </div>

            <!-- CUSTOMER DETAILS -->

            <div class="customer-account-details">

                <span class="customer-top-name">
                    Customer
                </span>

                <div class="customer-date-time">

                    <span
                        class="customer-date"
                        id="customer-live-date"
                    >
                        <?php echo date('F j, Y'); ?>
                    </span>

                    <span
                        class="customer-time"
                        id="customer-live-time"
                    >
                        <?php echo date('h:i:s A'); ?>
                    </span>

                </div>

            </div>

            <!-- DROPDOWN -->

            <div class="customer-dropdown-icon">
                <i class="fa-solid fa-chevron-down"></i>
            </div>

        </a>

    </div>

</div>


            <!-- =================================================
                 PAGE BODY
            ================================================== -->

            <div class="history-body">


                <!-- =================================================
                     STATISTICS
                ================================================== -->

                <div class="stats-grid">

                    <!-- TOTAL RESERVATIONS -->

                    <div class="stat-card stat-green">

                        <div class="stat-icon">
                            <i class="fa-solid fa-calendar-check"></i>
                        </div>

                        <div class="stat-label">
                            Total Reservations
                        </div>

                        <div class="stat-value">
                            <?php echo $total_reservations; ?>
                        </div>

                    </div>


                    <!-- TRAYS ORDERED -->

                    <div class="stat-card stat-yellow">

                        <div class="stat-icon">
                            <i class="fa-solid fa-box"></i>
                        </div>

                        <div class="stat-label">
                            Trays Ordered
                        </div>

                        <div class="stat-value">
                            <?php echo number_format($total_trays); ?>
                        </div>

                    </div>


                    <!-- TOTAL SPENT -->

                    <div class="stat-card stat-money">

                        <div class="stat-icon">
                            <i class="fa-solid fa-peso-sign"></i>
                        </div>

                        <div class="stat-label">
                            Total Spent
                        </div>

                        <div class="stat-value">
                            ₱ <?php
                            echo number_format(
                                $total_spent,
                                0
                            );
                            ?>
                        </div>

                    </div>


                    <!-- RECENT -->

                    <div class="stat-card stat-blue">

                        <div class="stat-icon">
                            <i class="fa-regular fa-clock"></i>
                        </div>

                        <div class="stat-label">
                            Recent Reservation
                        </div>

                        <div class="stat-value">
                            <?php echo htmlspecialchars($recent_reservation ?? 'No reservations'); ?>
                        </div>

                    </div>

                </div>


               <!-- =================================================
     SEARCH / FILTER
================================================== -->

<form
    method="GET"
    action=""
    id="historyFilterForm"
>

    <div class="history-toolbar">

        <!-- SEARCH -->

        <div class="search-box">

            <i class="fa-solid fa-magnifying-glass"></i>

            <input
                type="text"
                name="search"
                value="<?php echo htmlspecialchars($search); ?>"
                placeholder="Search by reservation code..."
            >

        </div>


        <!-- ALL EGG TYPES -->

        <select
            name="egg_type"
            class="filter-select"
            onchange="document.getElementById('historyFilterForm').submit();"
        >

            <option value="">
                All Egg Sizes
            </option>

            <?php foreach ($egg_options as $option): ?>

                <option
                    value="<?php echo htmlspecialchars($option); ?>"
                    <?php
                    echo $filter_egg === $option
                        ? 'selected'
                        : '';
                    ?>
                >

                    <?php
                    echo htmlspecialchars($option);
                    ?>

                </option>

            <?php endforeach; ?>

        </select>


        <!-- ALL METHODS -->

        <select
            name="delivery_method"
            class="filter-select"
            onchange="document.getElementById('historyFilterForm').submit();"
        >

            <option value="">
                All Methods
            </option>

            <option
                value="Delivery"
                <?php
                echo $filter_method === 'Delivery'
                    ? 'selected'
                    : '';
                ?>
            >
                Delivery
            </option>

            <option
                value="Pickup"
                <?php
                echo $filter_method === 'Pickup'
                    ? 'selected'
                    : '';
                ?>
            >
                Pickup
            </option>

        </select>

<!-- ALL STATUS -->

<select
    name="status"
    class="filter-select"
    onchange="document.getElementById('historyFilterForm').submit();"
>

    <option value="">
        All Status
    </option>

    <option
        value="Completed"
        <?php
        echo $filter_status === 'Completed'
            ? 'selected'
            : '';
        ?>
    >
        Completed
    </option>

    <option
        value="Cancelled"
        <?php
        echo $filter_status === 'Cancelled'
            ? 'selected'
            : '';
        ?>
    >
        Cancelled
    </option>

</select>


        <!-- DATE RANGE -->

        <select
            name="date_range"
            class="filter-select"
            onchange="document.getElementById('historyFilterForm').submit();"
        >

            <option value="">
                Date Range
            </option>

            <option
                value="today"
                <?php
                echo $filter_date === 'today'
                    ? 'selected'
                    : '';
                ?>
            >
                Today
            </option>

            <option
                value="7days"
                <?php
                echo $filter_date === '7days'
                    ? 'selected'
                    : '';
                ?>
            >
                Last 7 Days
            </option>

            <option
                value="30days"
                <?php
                echo $filter_date === '30days'
                    ? 'selected'
                    : '';
                ?>
            >
                Last 30 Days
            </option>

        </select>
<button
    type="button"
    class="clear-filter-button"
    onclick="clearHistoryFilters()"
>
    <i class="fa-solid fa-rotate-left"></i>
    Clear
</button>
    </div>

</form>


<!-- =================================================
     TABLE
================================================== -->

<div class="table-card">

    <div class="table-scroll">

        <table>

           <thead>

    <tr>

        <th>
            Reservation Code
        </th>

        <th>
            Size
        </th>

        <th>
            Total Qty
        </th>

        <th>
            Method
        </th>

        <th>
            Date
        </th>

        <th>
            Total
        </th>

        <th>
            Status
        </th>

        <th style="text-align:center;">
            Action
        </th>

    </tr>

</thead>


            <tbody>

            <?php if (!empty($paged_reservations)): ?>

                <?php foreach ($paged_reservations as $row): ?>

                    <?php

                    // =================================================
                    // RECEIPT DATA
                    // =================================================

                    $receipt_json = htmlspecialchars(
                        json_encode($row),
                        ENT_QUOTES,
                        'UTF-8'
                    );


                    // =================================================
                    // TOTAL QUANTITY
                    // =================================================

                    $total_qty = 0;

                    foreach ($row['items'] as $item) {

                        $total_qty +=
                            (int)$item['quantity'];
                    }


                    // =================================================
                    // IDS
                    // =================================================

                    $ids_list = implode(
                        ',',
                        $row['ids']
                    );


                 // =================================================
// STATUS
// HISTORY ONLY: COMPLETED / CANCELLED
// =================================================

$status = isset($row['status']) && $row['status'] !== null
    ? trim($row['status'])
    : 'Completed';

$status_lower = strtolower($status);

if (
    $status_lower === 'cancelled' ||
    $status_lower === 'canceled'
) {

    $status = 'Cancelled';

    $status_class = 'status-cancelled';

} else {

    $status = 'Completed';

    $status_class = 'status-completed';
}

                    // =================================================
                    // DATE / TIME
                    // =================================================

                    $created_at = $row['created_at'] ?? null;
                    $reservation_date = $row['reservation_date'] ?? null;

                    $date_timestamp = false;

                    if (!empty($created_at)) {
                        $date_timestamp = strtotime($created_at);
                    }

                    if ($date_timestamp === false && !empty($reservation_date)) {
                        $date_timestamp = strtotime($reservation_date);
                    }

                    if (
                        $date_timestamp === false
                    ) {

                        $date_display = !empty($reservation_date) ? $reservation_date : 'Not available';

                        $time_display = '';

                    } else {

                        $date_display =
                            date(
                                'M j, Y',
                                $date_timestamp
                            );

                        $time_display =
                            date(
                                'h:i A',
                                $date_timestamp
                            );
                    }


                    // =================================================
                    // METHOD ICON
                    // =================================================

                    $delivery_method = $row['delivery_method'] ?? '';

                    if (
                        strtolower(
                            $delivery_method
                        ) === 'delivery'
                    ) {

                        $method_icon =
                            'fa-solid fa-truck';

                    } else {

                        $method_icon =
                            'fa-solid fa-person-walking';
                    }


                    // =================================================
                    // SHORT EGG NAMES
                    // =================================================

                    $egg_short_names = array(

                        'Extra Small' => 'XS',
                        'Small' => 'S',
                        'Medium' => 'M',
                        'Large' => 'L',
                        'Extra Large' => 'XL',
                        'Jumbo' => 'J',
                        'Super Jumbo' => 'SJ',
                        'Double Yolk' => 'DY'

                    );

                    ?>

                    <tr>

                        <!-- RESERVATION CODE -->

                        <td>

                            <div class="reservation-code">

                                <?php
                                echo htmlspecialchars(
                                    $row['reservation_code'] ?? 'N/A'
                                );
                                ?>

                            </div>

                        

                        </td>


                     <!-- SIZE -->

<td>

    <div class="size-value">

        <?php

        $display_sizes = array();

        if (isset($row['items']) && is_array($row['items'])) {
            foreach ($row['items'] as $item) {

                $short_name = isset(
                    $egg_short_names[$item['egg_type']]
                )
                ? $egg_short_names[$item['egg_type']]
                : $item['egg_type'];

                if (!in_array(
                    $short_name,
                    $display_sizes
                )) {

                    $display_sizes[] = $short_name;

                }
            }
        }

        echo htmlspecialchars(
            implode(', ', $display_sizes)
        );

        ?>

    </div>

</td>


                        <!-- TOTAL QTY -->

                        <td>

                            <div class="trays-value">

                                <?php
                                echo number_format(
                                    $total_qty
                                );
                                ?>

                                <?php
                                echo $total_qty == 1
                                    ? ' tray'
                                    : ' trays';
                                ?>

                            </div>

                        </td>


                        <!-- METHOD -->

                        <td class="method-cell">

                            <span class="method-display">

                                <i
                                    class="<?php echo $method_icon; ?>"
                                ></i>

                                <?php
                                echo htmlspecialchars(
                                    $row['delivery_method'] ?? 'N/A'
                                );
                                ?>

                            </span>

                        </td>


                        <!-- DATE -->

                        <td>

                            <div class="date-main">

                                <?php
                                echo htmlspecialchars(
                                    $date_display ?? 'Not available'
                                );
                                ?>

                            </div>

                            <?php if (
                                $time_display !== ''
                            ): ?>

                                <div class="date-time">

                                    <?php
                                    echo htmlspecialchars(
                                        $time_display ?? ''
                                    );
                                    ?>

                                </div>

                            <?php endif; ?>

                        </td>


                        <!-- TOTAL -->

                        <td>

                            <div class="amount-value">

                                ₱<?php
                                echo number_format(
                                    $row['total_price'],
                                    2
                                );
                                ?>

                            </div>

                        </td>


                        <!-- STATUS -->

                        <td>

                            <span
                                class="status-badge <?php echo $status_class; ?>"
                            >

                                <?php
                                echo htmlspecialchars(
                                    $status
                                );
                                ?>

                            </span>

                        </td>


                        <!-- ACTION -->

                        <td class="actions-cell">

                            <div class="actions-wrapper">

                                <button
                                    type="button"
                                    onclick="printReceipt(<?php echo $receipt_json; ?>)"
                                    class="action-button print-button"
                                    title="Print Reservation"
                                >

                                    <i class="fa-solid fa-print"></i>

                                    Print

                                </button>


                             

                            </div>

                        </td>

                    </tr>

                <?php endforeach; ?>

            <?php else: ?>

                <tr>

                    <td
                        colspan="8"
                        class="empty-state"
                    >

                        <i class="fa-regular fa-folder-open"></i>

                        No reservation histories found.

                    </td>

                </tr>

            <?php endif; ?>

            </tbody>

        </table>

    </div>


    <!-- =================================================
         TABLE FOOTER
    ================================================== -->

    <div class="table-footer">

        <div>

            <?php if ($total_records > 0): ?>

                Showing

                <span class="footer-number">
                    <?php echo $offset + 1; ?>
                </span>

                -

                <span class="footer-number">
                    <?php
                    echo min(
                        $offset + $limit,
                        $total_records
                    );
                    ?>
                </span>

                of

                <span class="footer-number">
                    <?php echo $total_records; ?>
                </span>

                reservations.

            <?php else: ?>

                Showing 0 reservations.

            <?php endif; ?>

        </div>


        <?php if ($total_pages > 1): ?>

            <div class="pagination">

                <!-- PREVIOUS -->

                <a
                    href="<?php
                    echo $page > 1
                        ? getPaginationUrl($page - 1)
                        : '#';
                    ?>"
                    class="<?php
                    echo $page <= 1
                        ? 'disabled'
                        : '';
                    ?>"
                    title="Previous"
                >

                    <i class="fa-solid fa-chevron-left"></i>

                </a>


                <!-- PAGE NUMBERS -->

                <?php for (
                    $i = 1;
                    $i <= $total_pages;
                    $i++
                ): ?>

                    <a
                        href="<?php
                        echo getPaginationUrl($i);
                        ?>"
                        class="<?php
                        echo $page === $i
                            ? 'active'
                            : '';
                        ?>"
                    >

                        <?php echo $i; ?>

                    </a>

                <?php endfor; ?>


                <!-- NEXT -->

                <a
                    href="<?php
                    echo $page < $total_pages
                        ? getPaginationUrl($page + 1)
                        : '#';
                    ?>"
                    class="<?php
                    echo $page >= $total_pages
                        ? 'disabled'
                        : '';
                    ?>"
                    title="Next"
                >

                    <i class="fa-solid fa-chevron-right"></i>

                </a>

            </div>

        <?php endif; ?>

    </div>

</div>

            </div>

        </div>

    </div>

</div>


<!-- =========================================================
     PRINT WINDOW
========================================================= -->

<div id="receipt-print-window"></div>


<!-- =========================================================
     LOGOUT MODAL
========================================================= -->

<div
    id="logout_modal"
    class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-[600] p-4"
>

    <div
        class="bg-white rounded-xl shadow-2xl max-w-sm w-full p-6"
    >

        <div class="flex items-center gap-3 mb-4">

            <div
                class="w-10 h-10 rounded-full bg-red-100 flex items-center justify-center text-red-600"
            >
                <i class="fa-solid fa-right-from-bracket"></i>
            </div>

            <h3 class="text-lg font-bold text-gray-800">
                Confirm Logout
            </h3>

        </div>

        <p class="text-sm text-gray-500 mb-5">
            Are you sure you want to end your session?
        </p>

        <div class="flex justify-end gap-3">

            <button
                type="button"
                onclick="closeLogoutModal()"
                class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-4 py-2 rounded-lg text-sm font-semibold"
            >
                Stay Logged In
            </button>

            <a
                href="logout.php"
                class="bg-red-500 hover:bg-red-600 text-white px-4 py-2 rounded-lg text-sm font-semibold"
            >
                Yes, Logout
            </a>

        </div>

    </div>

</div>


<!-- =========================================================
     CANCEL MODAL
========================================================= -->

<div
    id="cancelModal"
    class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center p-4"
>

    <div class="cancel-modal-box">

        <div class="flex items-center gap-3 mb-3">

            <div
                class="w-10 h-10 rounded-full bg-red-100 flex items-center justify-center text-red-600"
            >
                <i class="fa-solid fa-triangle-exclamation"></i>
            </div>

            <h3 class="text-lg font-bold text-gray-800">
                Confirm Cancellation
            </h3>

        </div>

        <p class="text-sm text-gray-600 mb-6">
            Are you sure you want to cancel this reservation?
            This action cannot be undone.
        </p>

        <form
            id="cancelForm"
            action="cancel.php"
            method="POST"
        >

            <input
                type="hidden"
                name="id"
                id="cancelId"
            >

            <div class="flex gap-3">

                <button
                    type="button"
                    onclick="closeCancelModal()"
                    class="flex-1 bg-gray-100 hover:bg-gray-200 text-gray-700 py-2.5 rounded-lg font-semibold text-sm"
                >
                    Keep
                </button>

                <button
                    type="submit"
                    class="flex-1 bg-red-500 hover:bg-red-600 text-white py-2.5 rounded-lg font-semibold text-sm"
                >
                    Yes, Cancel
                </button>

            </div>

        </form>

    </div>

</div>


<script>

function updateCustomerDateTime() {

    var now = new Date();

    var options = {
        month: 'long',
        day: 'numeric',
        year: 'numeric'
    };

    var dateText = now.toLocaleDateString(
        'en-US',
        options
    );

    var timeText = now.toLocaleTimeString(
        'en-US',
        {
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit',
            hour12: true
        }
    );

    var dateElement = document.getElementById(
        'customer-live-date'
    );

    var timeElement = document.getElementById(
        'customer-live-time'
    );

    if (dateElement) {
        dateElement.textContent = dateText;
    }

    if (timeElement) {
        timeElement.textContent = timeText;
    }
}

updateCustomerDateTime();

setInterval(
    updateCustomerDateTime,
    1000
);


/* =========================================================
   CLEAR FILTERS
   LIST ICON DOES NOT OPEN ANY OLD FILTER PANEL
========================================================= */

function clearHistoryFilters() {

    window.location.href = 'customer_history.php';
}


/* =========================================================
   NOTIFICATIONS
========================================================= */

function toggleNotifications(event) {

    if (event) {
        event.stopPropagation();
    }

    var dropdown =
        document.getElementById(
            'notification_dropdown'
        );

    if (!dropdown) {
        return;
    }

    dropdown.classList.toggle('hidden');

    if (!dropdown.classList.contains('hidden')) {

        var badge =
            document.getElementById(
                'bell_badge'
            );

        if (badge) {
            badge.style.display = 'none';
        }

        var unreadCount =
            document.getElementById(
                'unread_count'
            );

        if (unreadCount) {

            unreadCount.innerText =
                '0 New';

            unreadCount.className =
                'notification-new-count bg-gray-100 text-gray-400';
        }
    }
}


/* =========================================================
   CLOSE NOTIFICATIONS WHEN CLICKING OUTSIDE
========================================================= */

document.addEventListener(
    'click',
    function(event) {

        var area =
            document.querySelector(
                '.notification-area'
            );

        var dropdown =
            document.getElementById(
                'notification_dropdown'
            );

        if (
            area &&
            dropdown &&
            !area.contains(event.target)
        ) {

            dropdown.classList.add('hidden');
        }

    }
);


/* =========================================================
   CANCEL MODAL
========================================================= */

function openCancelModal(idString) {

    document.getElementById(
        'cancelId'
    ).value = idString;

    var modal =
        document.getElementById(
            'cancelModal'
        );

    modal.classList.remove('hidden');
    modal.classList.add('flex');
}


function closeCancelModal() {

    var modal =
        document.getElementById(
            'cancelModal'
        );

    modal.classList.add('hidden');
    modal.classList.remove('flex');
}


/* =========================================================
   CLOSE CANCEL MODAL OUTSIDE
========================================================= */

document.addEventListener(
    'click',
    function(event) {

        var modal =
            document.getElementById(
                'cancelModal'
            );

        if (
            event.target === modal
        ) {

            closeCancelModal();
        }

    }
);


/* =========================================================
   LOGOUT
========================================================= */

function openLogoutModal() {

    var modal =
        document.getElementById(
            'logout_modal'
        );

    if (modal) {
        modal.classList.remove('hidden');
    }
}


function closeLogoutModal() {

    var modal =
        document.getElementById(
            'logout_modal'
        );

    if (modal) {
        modal.classList.add('hidden');
    }
}


/* =========================================================
   PRINT RECEIPT
========================================================= */
function printReceipt(data) {

    var printWindow = document.getElementById('receipt-print-window');



    var unitPrices = {
        "Extra Small": 140,
        "Small": 150,
        "Medium": 175,
        "Large": 195,
        "Extra Large": 235,
        "Jumbo": 255,
        "Super Jumbo": 270,
        "Double Yolk": 320
    };

    var sizeCodes = {
        "Extra Small": "XS",
        "Small": "S",
        "Medium": "M",
        "Large": "L",
        "Extra Large": "XL",
        "Jumbo": "J",
        "Super Jumbo": "SJ",
        "Double Yolk": "DY"
    };

    var totalAmount = 0;
    var itemsHTML = "";

    if (data.items && data.items.length > 0) {

        data.items.forEach(function(item) {

            var eggType = item.egg_type || "";
            var quantity = parseInt(item.quantity, 10) || 0;

            var unitPrice = unitPrices[eggType] || 0;

            var lineTotal = unitPrice * quantity;

            totalAmount += lineTotal;

            var shortCode = sizeCodes[eggType] || eggType;

            itemsHTML +=
                '<div class="receipt-item">' +

                    '<div class="item-main">' +
                        '<span class="item-size">' +
                            escapeHTML(shortCode) +
                        '</span>' +

                        '<span class="item-qty">' +
                            escapeHTML(String(quantity)) +
                        '</span>' +

                        '<span class="item-price">' +
                            '₱' + lineTotal.toFixed(2) +
                        '</span>' +
                    '</div>' +

                    '<div class="item-description">' +
                        escapeHTML(eggType) +
                        ' @ ₱' + unitPrice.toFixed(2) +
                    '</div>' +

                '</div>';
        });
    }

    /*
     * If total_price already exists in the database,
     * use it when available.
     */
    var displayedTotal = parseFloat(data.total_price);

    if (isNaN(displayedTotal)) {
        displayedTotal = totalAmount;
    }

    var deliveryMethod = data.delivery_method || "";

    var deliveryText = "";

    if (deliveryMethod.toLowerCase() === "delivery") {

        deliveryText = "Delivery";

    } else if (deliveryMethod.toLowerCase() === "pickup") {

        deliveryText = "Pickup";

    } else {

        deliveryText = deliveryMethod;
    }

    var addressHTML = "";

    if (
        deliveryMethod.toLowerCase() === "delivery" &&
        data.delivery_address
    ) {

        addressHTML =
            '<div class="info-row">' +
                '<span class="info-label">Address</span>' +
                '<span class="info-value">' +
                    escapeHTML(data.delivery_address) +
                '</span>' +
            '</div>';

    } else {

        addressHTML =
            '<div class="info-row">' +
                '<span class="info-label">Location</span>' +
                '<span class="info-value">Farm Pickup</span>' +
            '</div>';
    }

    printWindow.innerHTML =
        '<div class="thermal-receipt">' +

            /* =========================
               HEADER
            ========================== */

            '<div class="receipt-header">' +

                '<img ' +
                    'src="vdvclogo.png" ' +
                    'class="receipt-logo" ' +
                    'alt="VDVC Egg Farm Logo"' +
                '>' +

                '<div class="farm-name">' +
                    'VDVC EGG FARM' +
                '</div>' +

                '<div class="receipt-title">' +
                    'RESERVATION RECEIPT' +
                '</div>' +

                '<div class="receipt-code">' +
                    escapeHTML(data.reservation_code || "") +
                '</div>' +

            '</div>' +

            '<div class="separator"></div>' +

            /* =========================
               CUSTOMER INFORMATION
            ========================== */

            '<div class="section-title">' +
                'CUSTOMER INFORMATION' +
            '</div>' +

            '<div class="info-row">' +
                '<span class="info-label">Customer</span>' +
                '<span class="info-value">' +
                    escapeHTML(data.customer_name || "") +
                '</span>' +
            '</div>' +

            '<div class="info-row">' +
                '<span class="info-label">Contact</span>' +
                '<span class="info-value">' +
                    escapeHTML(data.contact_number || "") +
                '</span>' +
            '</div>' +

            '<div class="info-row">' +
                '<span class="info-label">Method</span>' +
                '<span class="info-value">' +
                    escapeHTML(deliveryText) +
                '</span>' +
            '</div>' +

            addressHTML +

            '<div class="separator"></div>' +

            /* =========================
               RESERVATION DETAILS
            ========================== */

            '<div class="section-title">' +
                'RESERVATION DETAILS' +
            '</div>' +

            '<div class="info-row">' +
                '<span class="info-label">Date</span>' +
                '<span class="info-value">' +
                    escapeHTML(data.reservation_date || "Pending") +
                '</span>' +
            '</div>' +

            '<div class="info-row">' +
                '<span class="info-label">Status</span>' +
                '<span class="info-value">' +
                    escapeHTML(data.status || "") +
                '</span>' +
            '</div>' +

            '<div class="separator"></div>' +

            /* =========================
               ORDER SUMMARY
            ========================== */

            '<div class="section-title">' +
                'ORDER SUMMARY' +
            '</div>' +

            '<div class="items-header">' +
                '<span>SIZE</span>' +
                '<span>QTY</span>' +
                '<span>AMOUNT</span>' +
            '</div>' +

            itemsHTML +

            '<div class="separator"></div>' +

            /* =========================
               TOTAL
            ========================== */

            '<div class="total-row">' +
                '<span>TOTAL</span>' +
                '<span>₱' + displayedTotal.toFixed(2) + '</span>' +
            '</div>' +

            '<div class="separator"></div>' +

            /* =========================
               FOOTER
            ========================== */

            '<div class="receipt-footer">' +

                '<div class="thank-you">' +
                    'Thank you for your reservation!' +
                '</div>' +

                '<div>' +
                    'VDVC Egg Farm' +
                '</div>' +

                '<div class="footer-small">' +
                    'Please keep this receipt for your reference.' +
                '</div>' +

            '</div>' +

        '</div>';

    /*
 * Temporary styles for thermal receipt
 */

var oldStyle = document.getElementById('thermal-receipt-style');

if (oldStyle) {
    oldStyle.parentNode.removeChild(oldStyle);
}

var style = document.createElement('style');

style.id = 'thermal-receipt-style';

style.innerHTML =

    /*
     * NORMAL PAGE
     * Receipt must stay hidden.
     */
    '#receipt-print-window {' +
        'display: none;' +
    '}' +

    /*
     * RECEIPT DESIGN
     */
    '#receipt-print-window .thermal-receipt {' +
        'width: 70mm;' +
        'max-width: 70mm;' +
        'margin: 0 auto;' +
        'padding: 3mm 0;' +
        'box-sizing: border-box;' +
        'font-family: Arial, Helvetica, sans-serif;' +
        'font-size: 10px;' +
        'line-height: 1.35;' +
        'color: #000000;' +
        'background: #ffffff;' +
    '}' +

    '#receipt-print-window .receipt-header {' +
        'text-align: center;' +
        'margin-bottom: 3mm;' +
    '}' +

    '#receipt-print-window .receipt-logo {' +
        'display: block;' +
        'width: 42mm;' +
        'max-width: 100%;' +
        'height: auto;' +
        'margin: 0 auto 2mm auto;' +
        'object-fit: contain;' +
    '}' +

    '#receipt-print-window .farm-name {' +
        'font-size: 16px;' +
        'font-weight: 700;' +
        'letter-spacing: 0.5px;' +
        'margin-top: 1mm;' +
    '}' +

    '#receipt-print-window .receipt-title {' +
        'font-size: 11px;' +
        'font-weight: 700;' +
        'margin-top: 1mm;' +
    '}' +

    '#receipt-print-window .receipt-code {' +
        'font-size: 10px;' +
        'font-weight: 700;' +
        'margin-top: 1.5mm;' +
    '}' +

    '#receipt-print-window .separator {' +
        'border-top: 1px dashed #000000;' +
        'margin: 3mm 0;' +
        'height: 0;' +
    '}' +

    '#receipt-print-window .section-title {' +
        'font-size: 10px;' +
        'font-weight: 700;' +
        'text-align: left;' +
        'margin-bottom: 1.5mm;' +
    '}' +

    '#receipt-print-window .info-row {' +
        'display: flex;' +
        'align-items: flex-start;' +
        'justify-content: space-between;' +
        'gap: 3mm;' +
        'margin-bottom: 1.2mm;' +
    '}' +

    '#receipt-print-window .info-label {' +
        'font-weight: 700;' +
        'flex: 0 0 22mm;' +
    '}' +

    '#receipt-print-window .info-value {' +
        'text-align: right;' +
        'flex: 1;' +
        'word-break: break-word;' +
    '}' +

    '#receipt-print-window .items-header {' +
        'display: grid;' +
        'grid-template-columns: 1fr 12mm 23mm;' +
        'gap: 1mm;' +
        'font-weight: 700;' +
        'font-size: 9px;' +
        'padding-bottom: 1mm;' +
        'border-bottom: 1px solid #000000;' +
    '}' +

    '#receipt-print-window .items-header span:nth-child(2),' +
    '#receipt-print-window .items-header span:nth-child(3) {' +
        'text-align: right;' +
    '}' +

    '#receipt-print-window .receipt-item {' +
        'padding: 1.8mm 0;' +
        'border-bottom: 1px dotted #777777;' +
    '}' +

    '#receipt-print-window .item-main {' +
        'display: grid;' +
        'grid-template-columns: 1fr 12mm 23mm;' +
        'gap: 1mm;' +
        'align-items: center;' +
    '}' +

    '#receipt-print-window .item-size {' +
        'font-weight: 700;' +
    '}' +

    '#receipt-print-window .item-qty,' +
    '#receipt-print-window .item-price {' +
        'text-align: right;' +
    '}' +

    '#receipt-print-window .item-description {' +
        'font-size: 8.5px;' +
        'margin-top: 0.5mm;' +
        'color: #333333;' +
    '}' +

    '#receipt-print-window .total-row {' +
        'display: flex;' +
        'justify-content: space-between;' +
        'align-items: center;' +
        'font-size: 13px;' +
        'font-weight: 700;' +
        'margin-top: 2mm;' +
    '}' +

    '#receipt-print-window .receipt-footer {' +
        'text-align: center;' +
        'font-size: 9px;' +
        'margin-top: 3mm;' +
    '}' +

    '#receipt-print-window .thank-you {' +
        'font-weight: 700;' +
        'font-size: 10px;' +
        'margin-bottom: 1mm;' +
    '}' +

    '#receipt-print-window .footer-small {' +
        'font-size: 8px;' +
        'margin-top: 1.5mm;' +
    '}' +

    /*
     * PRINT ONLY
     */
    '@media print {' +

        '@page {' +
            'size: 80mm auto;' +
            'margin: 0;' +
        '}' +

        'html, body {' +
            'width: 80mm !important;' +
            'height: auto !important;' +
            'min-height: 0 !important;' +
            'margin: 0 !important;' +
            'padding: 0 !important;' +
            'background: #ffffff !important;' +
        '}' +

        'body {' +
            'overflow: visible !important;' +
        '}' +

        'body * {' +
            'visibility: hidden !important;' +
        '}' +

        '#receipt-print-window {' +
            'display: block !important;' +
            'visibility: visible !important;' +
            'position: absolute !important;' +
            'left: 0 !important;' +
            'top: 0 !important;' +
            'width: 80mm !important;' +
            'height: auto !important;' +
            'min-height: 0 !important;' +
            'margin: 0 !important;' +
            'padding: 0 !important;' +
            'background: #ffffff !important;' +
            'overflow: visible !important;' +
        '}' +

        '#receipt-print-window .thermal-receipt,' +
        '#receipt-print-window .thermal-receipt * {' +
            'visibility: visible !important;' +
        '}' +

        '#receipt-print-window .thermal-receipt {' +
            'display: block !important;' +
            'width: 70mm !important;' +
            'max-width: 70mm !important;' +
            'height: auto !important;' +
            'min-height: 0 !important;' +
            'margin: 0 auto !important;' +
            'padding: 3mm 0 !important;' +
            'box-sizing: border-box !important;' +
            'background: #ffffff !important;' +
            'border: none !important;' +
            'box-shadow: none !important;' +
            'overflow: visible !important;' +
        '}' +

        '.no-print {' +
            'display: none !important;' +
        '}' +

    '}';

document.head.appendChild(style);

/* =========================================================
   CUSTOMER NOTIFICATION DROPDOWN
========================================================= */

var customerNotificationDropdown = null;
var isCustomerDropdownOpen = false;

function toggleCustomerNotificationDropdown()
{
    if (!customerNotificationDropdown) {
        customerNotificationDropdown = document.getElementById('customerNotificationDropdown');
    }

    if (customerNotificationDropdown) {
        isCustomerDropdownOpen = !isCustomerDropdownOpen;

        if (isCustomerDropdownOpen) {
            customerNotificationDropdown.classList.remove('hidden');
            loadCustomerNotifications();
        } else {
            customerNotificationDropdown.classList.add('hidden');
        }
    }
}

function closeCustomerNotificationDropdown()
{
    if (customerNotificationDropdown && isCustomerDropdownOpen) {
        customerNotificationDropdown.classList.add('hidden');
        isCustomerDropdownOpen = false;
    }
}

// Close dropdown when clicking outside
document.addEventListener('click', function(event) {
    if (customerNotificationDropdown && isCustomerDropdownOpen) {
        var notificationButton = document.querySelector('.customer-top-notification');
        var notificationContainer = customerNotificationDropdown.parentElement;
        if (!notificationContainer.contains(event.target)) {
            closeCustomerNotificationDropdown();
        }
    }
});

function loadCustomerNotifications()
{
    var notificationList = document.getElementById('customerNotificationList');
    if (!notificationList) return;

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
                    var iconElement = item.querySelector('.notification-icon');
                    var iconClass = 'info';
                    var iconHtml = '<div class="customer-notification-icon info"><i class="fa-solid fa-bell"></i></div>';

                    if (iconElement) {
                        if (iconElement.classList.contains('success')) {
                            iconClass = 'success';
                            iconHtml = '<div class="customer-notification-icon success"><i class="fa-solid fa-check"></i></div>';
                        } else if (iconElement.classList.contains('alert')) {
                            iconClass = 'alert';
                            iconHtml = '<div class="customer-notification-icon alert"><i class="fa-solid fa-exclamation"></i></div>';
                        } else if (iconElement.classList.contains('warning')) {
                            iconClass = 'warning';
                            iconHtml = '<div class="customer-notification-icon warning"><i class="fa-solid fa-circle-exclamation"></i></div>';
                        } else {
                            iconHtml = iconElement.outerHTML;
                        }
                    }

                    var titleElement = item.querySelector('.notification-title');
                    var title = titleElement ? titleElement.textContent : 'Notification';

                    var descElement = item.querySelector('.notification-desc');
                    var description = descElement ? descElement.textContent : '';

                    var timeElement = item.querySelector('.notification-time');
                    var time = timeElement ? timeElement.textContent : '';

                    var notificationId = item.getAttribute('onclick') ? item.getAttribute('onclick').match(/\d+/) : null;

                    notificationsHtml += '<div class="customer-notification-item ' + (isUnread ? 'unread' : 'read') + '">';
                    notificationsHtml += iconHtml;
                    notificationsHtml += '<div class="customer-notification-content">';
                    notificationsHtml += '<div class="customer-notification-title">' + title + '</div>';
                    notificationsHtml += '<div class="customer-notification-description">' + description + '</div>';
                    notificationsHtml += '<div class="customer-notification-time">' + time + '</div>';
                    notificationsHtml += '</div>';
                    if (notificationId && isUnread) {
                        notificationsHtml += '<button class="customer-mark-read-btn" onclick="markCustomerAsRead(' + notificationId + ', this)">Mark as Read</button>';
                    }
                    notificationsHtml += '</div>';
                });
                notificationList.innerHTML = notificationsHtml;
            } else {
                notificationList.innerHTML = '<div class="customer-notification-empty"><i class="fa-regular fa-bell-slash"></i><p>No notifications</p></div>';
            }
        })
        .catch(function(error) {
            notificationList.innerHTML = '<div class="customer-notification-empty"><i class="fa-solid fa-exclamation-circle"></i><p>Failed to load notifications</p></div>';
        });
}

function markCustomerAsRead(notificationId, buttonElement)
{
    var formData = new FormData();
    formData.append('notification_id', notificationId);
    formData.append('action', 'mark_read');

    fetch('customer_notifications.php', {
        method: 'POST',
        body: formData
    })
    .then(function(response) {
        return response.json();
    })
    .then(function(data) {
        if (data.success) {
            var notificationItem = buttonElement.closest('.customer-notification-item');
            if (notificationItem) {
                notificationItem.classList.remove('unread');
                notificationItem.classList.add('read');
                buttonElement.style.display = 'none';
            }

            // Update badge count
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
    })
    .catch(function(error) {
        console.error('Failed to mark notification as read:', error);
    });
}

function markAllCustomerAsRead(event)
{
    event.preventDefault();
    event.stopPropagation();

    var formData = new FormData();
    formData.append('action', 'mark_all_read');

    fetch('customer_notifications.php', {
        method: 'POST',
        body: formData
    })
    .then(function(response) {
        return response.json();
    })
    .then(function(data) {
        if (data.success) {
            // Reload page to update all notification states
            window.location.reload();
        }
    })
    .catch(function(error) {
        console.error('Failed to mark all notifications as read:', error);
    });
}

    /*
     * Wait for logo to load before printing.
     */
  var logo = printWindow.querySelector('.receipt-logo');

function startPrinting() {

    setTimeout(function() {
        window.print();
    }, 300);
}

if (logo) {

    if (logo.complete) {

        startPrinting();

    } else {

        logo.onload = function() {
            startPrinting();
        };

        logo.onerror = function() {
            startPrinting();
        };
    }

} else {

    startPrinting();
}
}

/* =========================================================
   ESCAPE HTML FOR PRINT
========================================================= */

function escapeHTML(value) {

    if (value === null || value === undefined) {
        return '';
    }

    return String(value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

</script>

</body>
</html>