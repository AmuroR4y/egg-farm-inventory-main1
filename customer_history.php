<?php
// =========================================================
// CUSTOMER HISTORY
// FULL REPLACEMENT
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

$user_id = $_SESSION['user_id'];
$user_role = isset($_SESSION['role']) ? $_SESSION['role'] : 'customer';

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

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$filter_egg = isset($_GET['egg_type']) ? trim($_GET['egg_type']) : '';
$filter_method = isset($_GET['delivery_method']) ? trim($_GET['delivery_method']) : '';

$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;

$limit = 5;

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

// Customer can only see own reservation records
if (strtolower($user_role) === 'customer') {

    $query .= " AND user_id = ?";

    $params[] = $user_id;
    $types .= "i";
}

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

    $date_timestamp = strtotime($reservation['created_at']);

    if ($date_timestamp === false) {
        $date_timestamp = strtotime($reservation['reservation_date']);
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

    $recent_timestamp = strtotime(
        $grouped_reservations[0]['reservation_date']
    );

    if ($recent_timestamp !== false) {

        $recent_reservation = date(
            'F j, Y',
            $recent_timestamp
        );

    } else {

        $recent_reservation =
            $grouped_reservations[0]['reservation_date'];
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
            border-radius: 14px;

            box-shadow:
                0 3px 12px rgba(55, 70, 60, 0.08);

            overflow: visible;
        }

        /* =====================================================
           HEADER
        ===================================================== */

        .history-header {
            min-height: 82px;

            padding: 0 28px;

            background: #f7f6f2;

            border-bottom: 1px solid #e2e0d9;

            display: flex;
            align-items: center;
            justify-content: space-between;

            border-radius: 14px 14px 0 0;
        }

        .history-title-wrap {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .history-title-icon {
            width: 43px;
            height: 43px;

            border-radius: 50%;

            background: #edf1e9;

            border: 1px solid #dfe5dc;

            display: flex;
            align-items: center;
            justify-content: center;

            color: #214f2c;

            font-size: 21px;
        }

        .history-title {
            margin: 0;

            font-size: 27px;
            line-height: 1;

            font-weight: 700;

            color: #214f2c;

            letter-spacing: -0.4px;
        }

        /* =====================================================
           HEADER RIGHT
        ===================================================== */

        .header-right {
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .notification-area {
            position: relative;
        }

        .notification-button {
            position: relative;

            width: 38px;
            height: 38px;

            border: none;
            background: transparent;

            color: #557066;

            cursor: pointer;

            display: flex;
            align-items: center;
            justify-content: center;

            border-radius: 50%;

            transition: 0.2s ease;
        }

        .notification-button:hover {
            background: #edf0eb;
        }

        .notification-button i {
            font-size: 18px;
        }

        .notification-badge {
            position: absolute;

            top: 2px;
            right: 1px;

            min-width: 14px;
            height: 14px;

            padding: 0 3px;

            border-radius: 20px;

            background: #4d8258;

            color: white;

            font-size: 8px;
            font-weight: 700;

            display: flex;
            align-items: center;
            justify-content: center;

            border: 2px solid #f7f6f2;
        }

        /* =====================================================
           USER AREA
           ===================================================== */

        .user-area {
            display: flex;
            align-items: center;
            gap: 10px;

            cursor: default;
        }

        /* IMPORTANT:
           NO vertical divider here.
        */

        .user-avatar {
            width: 46px;
            height: 46px;

            border-radius: 50%;

            background: #edf1eb;

            border: 1px solid #dce3dc;

            display: flex;
            align-items: center;
            justify-content: center;

            color: #52715f;

            font-size: 18px;
        }

        .user-info {
            min-width: 108px;
        }

        .user-name {
            color: #34483e;
            font-size: 15px;
            font-weight: 700;

            line-height: 1.2;
        }

        .current-date {
            color: #89978f;
            font-size: 10px;

            margin-top: 3px;
        }

        .current-time {
            color: #447354;
            font-size: 10px;

            font-weight: 700;

            margin-top: 2px;
        }

        .user-chevron {
            color: #65756c;
            font-size: 11px;
            margin-left: 3px;
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
           TOOLBAR
        ===================================================== */

        .history-toolbar {
            min-height: 64px;

            border-top: 1px solid #e7e5df;
            border-bottom: 1px solid #e7e5df;

            display: flex;
            align-items: center;

            gap: 12px;

            margin-top: 5px;

            padding: 11px 0;
        }

        /* List button from your reference image */

        .list-button {
            width: 57px;
            height: 45px;

            flex-shrink: 0;

            background: #fafaf7;

            border: 1px solid #e2e2dc;

            border-radius: 10px;

            color: #4b7561;

            display: flex;
            align-items: center;
            justify-content: center;

            cursor: pointer;

            transition: 0.2s ease;
        }

        .list-button:hover {
            background: #eef3eb;
            border-color: #ccd8ce;
        }

        .list-button i {
            font-size: 19px;
        }

        /* =====================================================
           SEARCH
        ===================================================== */

        .search-box {
            position: relative;

            flex: 1;

            min-width: 180px;
            max-width: 455px;
        }

        .search-box i {
            position: absolute;

            left: 14px;
            top: 50%;

            transform: translateY(-50%);

            color: #8b9991;

            font-size: 14px;
        }

        .search-box input {
            width: 100%;

            height: 45px;

            border: 1px solid #dedfd9;

            background: #fbfbf9;

            border-radius: 10px;

            padding:
                0 14px 0 39px;

            color: #4b5d54;

            font-size: 12px;

            outline: none;
        }

        .search-box input::placeholder {
            color: #9aa39e;
        }

        .search-box input:focus {
            border-color: #9db6a3;

            background: #ffffff;

            box-shadow:
                0 0 0 3px rgba(83, 121, 94, 0.08);
        }

        /* =====================================================
           SELECT FILTERS
        ===================================================== */

        .filter-select {
            width: 145px;
            height: 45px;

            border: 1px solid #dedfd9;

            background: #fbfbf9;

            border-radius: 10px;

            padding: 0 35px 0 13px;

            color: #52645a;

            font-size: 12px;
            font-weight: 600;

            outline: none;

            cursor: pointer;
        }

        .filter-select:focus {
            border-color: #9db6a3;

            box-shadow:
                0 0 0 3px rgba(83, 121, 94, 0.08);
        }

        /* =====================================================
           TABLE CARD
        ===================================================== */

        .table-card {
            margin-top: 17px;

            background: #ffffff;

            border: 1px solid #e4e4df;

            border-radius: 12px;

            overflow: hidden;

            box-shadow:
                0 2px 8px rgba(60, 70, 62, 0.04);
        }

        .table-scroll {
            width: 100%;
            overflow-x: auto;
        }

        table {
            width: 100%;

            border-collapse: collapse;

            min-width: 900px;
        }

        /* =====================================================
           TABLE HEADER
        ===================================================== */

        thead {
            background: #edf3e7;
        }

        thead th {
            padding: 13px 15px;

            color: #4d6659;

            font-size: 10px;

            font-weight: 800;

            text-align: left;

            white-space: nowrap;

            border-bottom: 1px solid #dfe6dc;
        }

        /* =====================================================
           TABLE BODY
        ===================================================== */

        tbody tr {
            background: #ffffff;

            transition: 0.15s ease;
        }

        tbody tr:hover {
            background: #fafcf9;
        }

        tbody td {
            padding: 13px 15px;

            border-bottom: 1px solid #eeeeea;

            color: #607069;

            font-size: 11px;

            vertical-align: middle;
        }

        tbody tr:last-child td {
            border-bottom: none;
        }

        .reservation-code {
            color: #416957;

            font-weight: 800;

            font-size: 10px;

            white-space: nowrap;
        }

        .date-main {
            color: #52675d;

            font-size: 11px;

            font-weight: 700;

            white-space: nowrap;
        }

        .date-time {
            color: #89948f;

            font-size: 9px;

            margin-top: 3px;

            white-space: nowrap;
        }

        .egg-size-list {
            display: flex;

            flex-direction: column;

            gap: 5px;

            min-width: 105px;
        }

        .egg-size-row {
            display: flex;

            align-items: center;

            gap: 5px;
        }

        .egg-badge {
            display: inline-flex;

            align-items: center;

            padding: 4px 8px;

            border-radius: 20px;

            font-size: 9px;

            font-weight: 700;

            white-space: nowrap;
        }

        .egg-quantity {
            color: #718079;

            font-size: 9px;

            font-weight: 700;

            white-space: nowrap;
        }

        .trays-value {
            color: #456354;

            font-size: 13px;

            font-weight: 800;

            text-align: center;
        }

        .amount-value {
            color: #35634a;

            font-size: 12px;

            font-weight: 800;

            white-space: nowrap;
        }

        .method-badge {
            display: inline-flex;

            align-items: center;

            padding: 5px 10px;

            border-radius: 20px;

            font-size: 9px;

            font-weight: 700;

            white-space: nowrap;
        }

        .method-delivery {
            background: #e8f1f4;
            color: #397081;
            border: 1px solid #d5e5e9;
        }

        .method-pickup {
            background: #eee9f4;
            color: #70578a;
            border: 1px solid #e1d8ea;
        }

        /* =====================================================
           STATUS
        ===================================================== */

        .status-badge {
            display: inline-flex;

            align-items: center;
            justify-content: center;

            padding: 5px 11px;

            border-radius: 20px;

            font-size: 9px;

            font-weight: 800;

            white-space: nowrap;
        }

        .status-pending {
            background: #faf0d6;
            color: #a07a27;
            border: 1px solid #f0dfb2;
        }

        .status-confirmed {
            background: #e7f2e7;
            color: #4c8757;
            border: 1px solid #d3e7d3;
        }

        .status-delivered {
            background: #e4f0f7;
            color: #3978a5;
            border: 1px solid #d2e4ef;
        }

        .status-cancelled {
            background: #fae8e6;
            color: #bf5b51;
            border: 1px solid #f1d3cf;
        }

        .status-completed {
            background: #e7f2e7;
            color: #4c8757;
            border: 1px solid #d3e7d3;
        }

        /* =====================================================
           ACTIONS
        ===================================================== */

        .actions-cell {
            white-space: nowrap;
        }

        .actions-wrapper {
            display: flex;

            align-items: center;

            justify-content: center;

            gap: 6px;
        }

        .action-button {
            width: 32px;
            height: 32px;

            border-radius: 8px;

            border: none;

            display: inline-flex;

            align-items: center;
            justify-content: center;

            cursor: pointer;

            transition: 0.2s ease;

            font-size: 12px;
        }

        .print-button {
            background: #e8f1ea;
            color: #38704c;
            border: 1px solid #d4e4d7;
        }

        .print-button:hover {
            background: #dceadd;
        }

        .cancel-button {
            background: #f8e8e6;
            color: #bb5a50;
            border: 1px solid #efd3cf;
        }

        .cancel-button:hover {
            background: #f4dcd9;
        }

        /* =====================================================
           EMPTY STATE
        ===================================================== */

        .empty-state {
            padding: 55px 20px;

            text-align: center;

            color: #98a39e;

            font-size: 11px;
        }

        .empty-state i {
            display: block;

            font-size: 29px;

            margin-bottom: 10px;

            color: #ccd3ce;
        }

        /* =====================================================
           TABLE FOOTER
        ===================================================== */

        .table-footer {
            padding: 12px 16px;

            background: #fafaf7;

            border-top: 1px solid #ecece7;

            display: flex;

            align-items: center;

            justify-content: space-between;

            gap: 15px;

            color: #929d97;

            font-size: 10px;
        }

        .footer-number {
            color: #65736c;
            font-weight: 700;
        }

        .pagination {
            display: flex;

            align-items: center;

            gap: 4px;
        }

        .pagination a {
            width: 31px;
            height: 31px;

            border-radius: 7px;

            border: 1px solid #e1e2dc;

            background: #ffffff;

            color: #63736a;

            text-decoration: none;

            display: flex;

            align-items: center;
            justify-content: center;

            font-size: 10px;

            font-weight: 700;
        }

        .pagination a:hover {
            background: #f1f4ef;
        }

        .pagination a.active {
            background: #214f2c;

            border-color: #214f2c;

            color: #ffffff;
        }

        .pagination a.disabled {
            color: #c0c5c1;

            background: #f7f7f4;

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
                size: portrait;
                margin: 0;
            }

            html,
            body {
                height: 100%;
                margin: 0;
                padding: 0;

                background: #ffffff !important;
            }

            body * {
                visibility: hidden;
            }

            #receipt-print-window,
            #receipt-print-window * {
                visibility: visible;

                display: block !important;

                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }

            #receipt-print-window {
                position: absolute;

                left: 0;
                top: 0;

                width: 100%;
                height: 100vh;

                padding: 30px;

                box-sizing: border-box;

                background: white !important;

                color: black;
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

            <!-- =================================================
                 HEADER
            ================================================== -->

            <div class="history-header">

                <div class="history-title-wrap">

                    <div class="history-title-icon">
                        <i class="fa-solid fa-clock-rotate-left"></i>
                    </div>

                    <h1 class="history-title">
                        Reservation History
                    </h1>

                </div>


                <!-- HEADER RIGHT -->

                <div class="header-right">

                    <!-- NOTIFICATION -->

                    <div class="notification-area">

                        <button
                            type="button"
                            onclick="toggleNotifications(event)"
                            class="notification-button"
                            title="Notifications"
                        >

                            <i class="fa-regular fa-bell"></i>

                            <?php if ($unread_count > 0): ?>

                                <span
                                    id="bell_badge"
                                    class="notification-badge"
                                >
                                    <?php echo $unread_count > 9 ? '9+' : $unread_count; ?>
                                </span>

                            <?php endif; ?>

                        </button>


                        <!-- NOTIFICATION DROPDOWN -->

                        <div
                            id="notification_dropdown"
                            class="hidden"
                        >

                            <div class="notification-header">

                                <span class="notification-header-title">
                                    Notifications
                                </span>

                                <span
                                    id="unread_count"
                                    class="notification-new-count <?php echo $unread_count > 0 ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-400'; ?>"
                                >
                                    <?php
                                    echo $unread_count > 0
                                        ? $unread_count . ' New'
                                        : '0 New';
                                    ?>
                                </span>

                            </div>


                            <div
                                id="notification_list"
                                style="max-height: 300px; overflow-y: auto;"
                            >

                                <?php if (!empty($notifications)): ?>

                                    <?php foreach ($notifications as $notif): ?>

                                        <?php

                                        $icon_bg =
                                            'background:#e7f0e9;color:#467452;';

                                        $icon_fa =
                                            'fa-solid fa-circle-info';

                                        if ($notif['type'] === 'success') {

                                            $icon_bg =
                                                'background:#e4f1e5;color:#478354;';

                                            $icon_fa =
                                                'fa-solid fa-circle-check';

                                        } elseif ($notif['type'] === 'alert') {

                                            $icon_bg =
                                                'background:#fae7e4;color:#bb5d52;';

                                            $icon_fa =
                                                'fa-solid fa-triangle-exclamation';
                                        }

                                        ?>

                                        <div class="notification-item">

                                            <div
                                                class="notification-icon"
                                                style="<?php echo $icon_bg; ?>"
                                            >
                                                <i
                                                    class="<?php echo $icon_fa; ?>"
                                                ></i>
                                            </div>

                                            <div style="flex:1; min-width:0;">

                                                <div class="notification-title-text">
                                                    <?php
                                                    echo htmlspecialchars(
                                                        $notif['title']
                                                    );
                                                    ?>
                                                </div>

                                                <div class="notification-description">
                                                    <?php
                                                    echo htmlspecialchars(
                                                        $notif['description']
                                                    );
                                                    ?>
                                                </div>

                                                <div class="notification-time">
                                                    <?php
                                                    echo date(
                                                        'M d, g:i a',
                                                        strtotime(
                                                            $notif['created_at']
                                                        )
                                                    );
                                                    ?>
                                                </div>

                                            </div>

                                        </div>

                                    <?php endforeach; ?>

                                <?php else: ?>

                                    <div
                                        style="
                                            padding:35px 15px;
                                            text-align:center;
                                            color:#a2aaa5;
                                            font-size:11px;
                                        "
                                    >

                                        <i
                                            class="fa-regular fa-bell-slash"
                                            style="
                                                display:block;
                                                font-size:23px;
                                                margin-bottom:8px;
                                                color:#cbd1cd;
                                            "
                                        ></i>

                                        No new notifications

                                    </div>

                                <?php endif; ?>

                            </div>

                        </div>

                    </div>


                    <!-- USER -->

                    <div class="user-area">

                        <div class="user-avatar">
                            <i class="fa-regular fa-user"></i>
                        </div>

                        <div class="user-info">

                            <div class="user-name">
                                <?php
                                echo htmlspecialchars(
                                    $current_user_name
                                );
                                ?>
                            </div>

                            <div class="current-date">
                                <?php
                                echo date('F j, Y');
                                ?>
                            </div>

                            <div
                                id="liveTime"
                                class="current-time"
                            >
                                <?php echo date('h:i:s A'); ?>
                            </div>

                        </div>

                        <i class="fa-solid fa-chevron-down user-chevron"></i>

                    </div>

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
                            <?php echo htmlspecialchars($recent_reservation); ?>
                        </div>

                    </div>

                </div>


                <!-- =================================================
                     SEARCH / FILTER TOOLBAR
                ================================================== -->

                <form
                    method="GET"
                    action=""
                    id="historyFilterForm"
                >

                    <div class="history-toolbar">

                        <!-- LIST ICON -->

                        <button
                            type="button"
                            class="list-button"
                            title="Show All Reservations"
                            onclick="clearHistoryFilters()"
                        >
                            <i class="fa-solid fa-list"></i>
                        </button>


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


                        <!-- ALL SIZES -->

                        <select
                            name="egg_type"
                            class="filter-select"
                            onchange="document.getElementById('historyFilterForm').submit();"
                        >

                            <option value="">
                                All Sizes
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
                                    <?php echo htmlspecialchars($option); ?>
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
                                        Date &amp; Time
                                    </th>

                                    <th>
                                        Egg Size
                                    </th>

                                    <th style="text-align:center;">
                                        Trays Ordered
                                    </th>

                                    <th>
                                        Total Amount
                                    </th>

                                    <th>
                                        Delivery Method
                                    </th>

                                    <th>
                                        Status
                                    </th>

                                    <th style="text-align:center;">
                                        Actions
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
                                    // IDS FOR CANCELLATION
                                    // =================================================

                                    $ids_list = implode(
                                        ',',
                                        $row['ids']
                                    );

                                    // =================================================
                                    // STATUS
                                    // =================================================

                                    $status =
                                        !empty($row['status'])
                                        ? $row['status']
                                        : 'Pending';

                                    $status_class = 'status-pending';

                                    $status_lower =
                                        strtolower($status);

                                    if (
                                        $status_lower === 'confirmed'
                                    ) {

                                        $status_class =
                                            'status-confirmed';

                                    } elseif (
                                        $status_lower === 'delivered'
                                    ) {

                                        $status_class =
                                            'status-delivered';

                                    } elseif (
                                        $status_lower === 'cancelled'
                                        ||
                                        $status_lower === 'canceled'
                                    ) {

                                        $status_class =
                                            'status-cancelled';

                                    } elseif (
                                        $status_lower === 'completed'
                                    ) {

                                        $status_class =
                                            'status-completed';
                                    }

                                    // =================================================
                                    // DATE/TIME
                                    // =================================================

                                    $date_timestamp =
                                        strtotime(
                                            $row['created_at']
                                        );

                                    if (
                                        $date_timestamp === false
                                    ) {

                                        $date_timestamp =
                                            strtotime(
                                                $row['reservation_date']
                                            );
                                    }

                                    if (
                                        $date_timestamp === false
                                    ) {

                                        $date_display =
                                            $row['reservation_date'];

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

                                    ?>

                                    <tr>

                                        <!-- RESERVATION CODE -->

                                        <td>

                                            <div class="reservation-code">

                                                <?php
                                                echo htmlspecialchars(
                                                    $row['reservation_code']
                                                );
                                                ?>

                                            </div>

                                        </td>


                                        <!-- DATE & TIME -->

                                        <td>

                                            <div class="date-main">

                                                <?php
                                                echo htmlspecialchars(
                                                    $date_display
                                                );
                                                ?>

                                            </div>

                                            <?php if ($time_display !== ''): ?>

                                                <div class="date-time">

                                                    <?php
                                                    echo htmlspecialchars(
                                                        $time_display
                                                    );
                                                    ?>

                                                </div>

                                            <?php endif; ?>

                                        </td>


                                        <!-- EGG SIZE -->

                                        <td>

                                            <div class="egg-size-list">

                                                <?php foreach ($row['items'] as $item): ?>

                                                    <?php

                                                    $egg_class =
                                                        'background:#eef1ee;color:#56645d;border:1px solid #dfe4df;';

                                                    switch (
                                                        $item['egg_type']
                                                    ) {

                                                        case 'Extra Small':
                                                        case 'Small':

                                                            $egg_class =
                                                                'background:#f0f1ed;color:#667068;border:1px solid #e0e2dc;';

                                                            break;

                                                        case 'Medium':

                                                            $egg_class =
                                                                'background:#f6f0d9;color:#88732f;border:1px solid #ebe0b9;';

                                                            break;

                                                        case 'Large':

                                                            $egg_class =
                                                                'background:#f7e9d9;color:#9b6b39;border:1px solid #eed8bd;';

                                                            break;

                                                        case 'Extra Large':
                                                        case 'Jumbo':
                                                        case 'Super Jumbo':
                                                        case 'Double Yolk':

                                                            $egg_class =
                                                                'background:#f5e5e1;color:#a35d54;border:1px solid #ead1cc;';

                                                            break;
                                                    }

                                                    ?>

                                                    <div class="egg-size-row">

                                                        <span
                                                            class="egg-badge"
                                                            style="<?php echo $egg_class; ?>"
                                                        >
                                                            <?php
                                                            echo htmlspecialchars(
                                                                $item['egg_type']
                                                            );
                                                            ?>
                                                        </span>

                                                        <span class="egg-quantity">
                                                            x<?php
                                                            echo (int)$item['quantity'];
                                                            ?>
                                                        </span>

                                                    </div>

                                                <?php endforeach; ?>

                                            </div>

                                        </td>


                                        <!-- TRAYS -->

                                        <td>

                                            <div class="trays-value">

                                                <?php
                                                echo number_format(
                                                    $total_qty
                                                );
                                                ?>

                                            </div>

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


                                        <!-- METHOD -->

                                        <td>

                                            <span
                                                class="method-badge <?php
                                                echo $row['delivery_method'] === 'Delivery'
                                                    ? 'method-delivery'
                                                    : 'method-pickup';
                                                ?>"
                                            >

                                                <?php
                                                echo htmlspecialchars(
                                                    $row['delivery_method']
                                                );
                                                ?>

                                            </span>

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


                                        <!-- ACTIONS -->

                                        <td class="actions-cell">

                                            <div class="actions-wrapper">

                                                <!-- PRINT -->

                                                <button
                                                    type="button"
                                                    onclick="printReceipt(<?php echo $receipt_json; ?>)"
                                                    class="action-button print-button"
                                                    title="Print Reservation"
                                                >

                                                    <i class="fa-solid fa-print"></i>

                                                </button>


                                                <!-- CANCEL -->

                                                <?php
                                                if (
                                                    $status_lower !== 'cancelled'
                                                    &&
                                                    $status_lower !== 'canceled'
                                                    &&
                                                    $status_lower !== 'delivered'
                                                    &&
                                                    $status_lower !== 'completed'
                                                ):
                                                ?>

                                                    <button
                                                        type="button"
                                                        onclick="openCancelModal('<?php echo htmlspecialchars($ids_list); ?>')"
                                                        class="action-button cancel-button"
                                                        title="Cancel Reservation"
                                                    >

                                                        <i class="fa-solid fa-xmark"></i>

                                                    </button>

                                                <?php endif; ?>

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

                                to

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


                        <!-- PAGINATION -->

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
                                        href="<?php echo getPaginationUrl($i); ?>"
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

/* =========================================================
   LIVE TIME
========================================================= */

function updateLiveTime() {

    var now = new Date();

    var hours = now.getHours();
    var minutes = now.getMinutes();
    var seconds = now.getSeconds();

    var ampm = hours >= 12 ? 'PM' : 'AM';

    hours = hours % 12;

    hours = hours ? hours : 12;

    hours = hours < 10
        ? '0' + hours
        : hours;

    minutes = minutes < 10
        ? '0' + minutes
        : minutes;

    seconds = seconds < 10
        ? '0' + seconds
        : seconds;

    var timeString =
        hours + ':' +
        minutes + ':' +
        seconds + ' ' +
        ampm;

    var timeElement =
        document.getElementById('liveTime');

    if (timeElement) {
        timeElement.textContent = timeString;
    }
}

updateLiveTime();

setInterval(
    updateLiveTime,
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

    var printWindow =
        document.getElementById(
            'receipt-print-window'
        );

    var unitPrices = {

        'Extra Small': 140,

        'Small': 150,

        'Medium': 175,

        'Large': 195,

        'Extra Large': 235,

        'Jumbo': 255,

        'Super Jumbo': 270,

        'Double Yolk': 320
    };


    var itemsHTML = '';


    data.items.forEach(
        function(item) {

            var unitPrice =
                unitPrices[item.egg_type] || 0;

            var computedPrice =
                unitPrice *
                parseInt(item.quantity);


            itemsHTML +=
                '<tr style="border-bottom:1px solid #e2e8f0;">' +

                '<td style="padding:12px;font-weight:600;">' +
                '🥚 ' +
                item.egg_type +
                ' Size' +
                '</td>' +

                '<td style="padding:12px;text-align:center;">' +
                '₱' +
                unitPrice.toFixed(2) +
                '</td>' +

                '<td style="padding:12px;text-align:center;font-weight:700;">' +
                'x ' +
                item.quantity +
                '</td>' +

                '<td style="padding:12px;text-align:right;font-weight:700;">' +
                '₱' +
                computedPrice.toLocaleString(
                    undefined,
                    {
                        minimumFractionDigits: 2
                    }
                ) +
                '</td>' +

                '</tr>';
        }
    );


    var reservationCode =
        data.reservation_code
            ? data.reservation_code
            : 'RES-' + data.id;


    printWindow.innerHTML =

        '<div style="' +
        'font-family:Arial,Helvetica,sans-serif;' +
        'max-width:750px;' +
        'min-height:calc(100vh - 60px);' +
        'margin:0 auto;' +
        'padding:30px;' +
        'border:1px solid #e2e8f0;' +
        'background:#ffffff;' +
        'box-sizing:border-box;' +
        'display:flex;' +
        'flex-direction:column;' +
        'justify-content:space-between;' +
        '">' +

        '<div>' +

        '<div style="' +
        'display:flex;' +
        'justify-content:space-between;' +
        'align-items:center;' +
        'border-bottom:2px solid #f1f5f9;' +
        'padding-bottom:15px;' +
        'margin-bottom:25px;' +
        '">' +

        '<div style="display:flex;align-items:center;gap:14px;">' +

        '<img src="vdvc.png" ' +
        'alt="VDVC Logo" ' +
        'style="width:55px;height:55px;object-fit:contain;">' +

        '<div>' +

        '<h2 style="' +
        'margin:0;' +
        'font-size:21px;' +
        'font-weight:800;' +
        'color:#214f2c;' +
        'text-transform:uppercase;' +
        '">' +
        'VDVC Egg Farm' +
        '</h2>' +

        '<span style="' +
        'font-size:11px;' +
        'background:#e7f1e8;' +
        'color:#39704a;' +
        'padding:3px 8px;' +
        'border-radius:999px;' +
        'font-weight:700;' +
        '">' +
        'Reservation Receipt' +
        '</span>' +

        '</div>' +

        '</div>' +

        '<div style="text-align:right;">' +

        '<div style="' +
        'font-size:11px;' +
        'font-weight:700;' +
        'color:#64748b;' +
        'text-transform:uppercase;' +
        '">' +
        'Reservation Code' +
        '</div>' +

        '<div style="' +
        'font-size:18px;' +
        'font-weight:800;' +
        'color:#39704a;' +
        '">' +
        reservationCode +
        '</div>' +

        '</div>' +

        '</div>' +


        '<div style="margin-bottom:25px;">' +

        '<h3 style="' +
        'font-size:13px;' +
        'font-weight:700;' +
        'color:#1e293b;' +
        'text-transform:uppercase;' +
        'margin-bottom:8px;' +
        '">' +
        'Contact & Delivery Information' +
        '</h3>' +

        '<div style="' +
        'background:#f8fafc;' +
        'border:1px solid #e2e8f0;' +
        'border-radius:8px;' +
        'padding:15px;' +
        'font-size:13px;' +
        'line-height:1.5;' +
        '">' +

        '<div style="margin-bottom:6px;">' +
        '<strong>Customer Name:</strong> ' +
        escapeHTML(data.customer_name) +
        '</div>' +

        '<div style="margin-bottom:6px;">' +
        '<strong>Contact Number:</strong> ' +
        escapeHTML(data.contact_number) +
        '</div>' +

        '<div>' +
        '<strong>Delivery Address:</strong> ' +
        escapeHTML(
            data.delivery_address
                ? data.delivery_address
                : 'N/A (Store Pickup Specified)'
        ) +
        '</div>' +

        '</div>' +

        '</div>' +


        '<div style="margin-bottom:25px;">' +

        '<h3 style="' +
        'font-size:13px;' +
        'font-weight:700;' +
        'color:#1e293b;' +
        'text-transform:uppercase;' +
        'margin-bottom:8px;' +
        '">' +
        'Reservation Details' +
        '</h3>' +

        '<div style="' +
        'background:#f8fafc;' +
        'border:1px solid #e2e8f0;' +
        'border-radius:8px;' +
        'padding:15px;' +
        'font-size:13px;' +
        'line-height:1.5;' +
        '">' +

        '<div style="margin-bottom:6px;">' +
        '<strong>Delivery Method:</strong> ' +
        escapeHTML(data.delivery_method) +
        '</div>' +

        '<div>' +
        '<strong>Reservation Date:</strong> ' +
        escapeHTML(data.reservation_date) +
        '</div>' +

        '</div>' +

        '</div>' +


        '<div style="margin-bottom:25px;">' +

        '<h3 style="' +
        'font-size:13px;' +
        'font-weight:700;' +
        'color:#1e293b;' +
        'text-transform:uppercase;' +
        'margin-bottom:8px;' +
        '">' +
        'Order Summary Breakdown' +
        '</h3>' +

        '<table style="' +
        'width:100%;' +
        'font-size:13px;' +
        'border-collapse:collapse;' +
        'border:1px solid #e2e8f0;' +
        '">' +

        '<tbody style="color:#334155;">' +

        itemsHTML +

        '<tr style="background:#f8fafc;font-size:14px;font-weight:800;">' +

        '<td colspan="3" style="padding:12px;text-align:right;text-transform:uppercase;">' +
        'Total Price Amount:' +
        '</td>' +

        '<td style="' +
        'padding:12px;' +
        'text-align:right;' +
        'color:#39704a;' +
        'font-size:17px;' +
        '">' +

        '₱' +

        parseFloat(
            data.total_price
        ).toLocaleString(
            undefined,
            {
                minimumFractionDigits: 2
            }
        ) +

        '</td>' +

        '</tr>' +

        '</tbody>' +

        '</table>' +

        '</div>' +

        '</div>' +


        '<div style="' +
        'text-align:center;' +
        'border-top:1px dashed #cbd5e1;' +
        'padding-top:20px;' +
        'font-size:11px;' +
        'color:#94a3b8;' +
        '">' +

        '<p style="' +
        'margin:0;' +
        'font-weight:600;' +
        'color:#64748b;' +
        'font-size:13px;' +
        '">' +
        'Thank you for your reservation with VDVC Egg Farm!' +
        '</p>' +

        '<p style="margin:4px 0 0 0;">' +
        'System managed securely under © 2026 Egg Reservation Systems.' +
        '</p>' +

        '</div>' +

        '</div>';


    window.print();
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