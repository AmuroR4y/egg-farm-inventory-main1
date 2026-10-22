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
/* =========================================================
   CUSTOMER LOGOUT TOKEN
   PHP 5.5 COMPATIBLE
========================================================= */

if (empty($_SESSION['customer_logout_token'])) {
    if (function_exists('random_bytes')) {
        $_SESSION['customer_logout_token'] = bin2hex(random_bytes(32));
    } elseif (function_exists('openssl_random_pseudo_bytes')) {
        $_SESSION['customer_logout_token'] = bin2hex(openssl_random_pseudo_bytes(32));
    } else {
        $_SESSION['customer_logout_token'] = hash(
            'sha256',
            uniqid(mt_rand(), true) . session_id()
        );
    }
}
// =========================================================
// GET CURRENT USER INFORMATION
// =========================================================

$customer_name = isset($_SESSION['fullname'])
    ? $_SESSION['fullname']
    : (isset($_SESSION['customer_name']) ? $_SESSION['customer_name'] : (isset($_SESSION['name']) ? $_SESSION['name'] : 'Customer'));
$active_page = 'history';

$unread_notifications = 0;
$notification_count_result = $conn->query("SELECT COUNT(*) AS total FROM notifications WHERE user_id = " . (int)$user_id . " AND is_read = 0");
if ($notification_count_result && ($notification_count_row = $notification_count_result->fetch_assoc())) {
    $unread_notifications = (int)$notification_count_row['total'];
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
    AND COALESCE(LOWER(TRIM(status)), '') NOT LIKE 'pending%'
    AND COALESCE(TRIM(status), '') <> ''
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


/* =========================================================
   RECENT RESERVATION
   Show the latest reservation creation date.
========================================================= */

$recent_reservation = 'No reservations';

if (!empty($grouped_reservations)) {

    $recent_timestamp = false;

    foreach ($grouped_reservations as $recent_row) {

        if (
            isset($recent_row['created_at']) &&
            trim((string)$recent_row['created_at']) !== ''
        ) {

            $row_timestamp = strtotime(
                $recent_row['created_at']
            );

            if (
                $row_timestamp !== false &&
                (
                    $recent_timestamp === false ||
                    $row_timestamp > $recent_timestamp
                )
            ) {
                $recent_timestamp = $row_timestamp;
            }
        }
    }

    if ($recent_timestamp !== false) {

        $recent_reservation = date(
            'M j, Y',
            $recent_timestamp
        );
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

    <link rel="stylesheet" href="customer_styles.css">

    <style>

/* =========================================================
   VDVC CUSTOMER HISTORY — REFERENCE REDESIGN
========================================================= */


/* =========================================================
   CUSTOMER NAVIGATION — USE SHARED STYLES FROM customer_styles.css
========================================================= */

/* Remove duplicate navbar styles - using shared stylesheet */



/* =========================================================
   PAGE LAYOUT
========================================================= */

/* =========================================================
   PAGE LAYOUT — MAIN HISTORY OUTER CARD
========================================================= */

.main-content {
    width: 100%;
    min-height: calc(100vh - 125px);
    margin: 0;
    padding: 10px 0 14px;
}

.history-shell {
    width: calc(100% - 40px);
    max-width: 1740px;
    margin: 0 auto;
}

.history-outer-card {
    width: 100%;
    background: #ffffff;
    border: 1px solid #dedbd2;
    border-radius: 26px;
    overflow: hidden;
    box-shadow: 0 8px 24px rgba(35, 55, 38, 0.08);
}

.history-card {
    width: 100%;
    overflow: visible;
    border: 0;
    border-radius: 0;
    background: transparent;
    box-shadow: none;
}


/* =========================================================
   HISTORY HERO / BANNER
========================================================= */


/* =========================================================
   HISTORY HERO — BUILT INTO OUTER CARD
========================================================= */

.customer-dashboard-topbar {
    position: relative;
    min-height: 143px;
    width: 100%;
    padding: 24px 5%;

    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 20px;

    overflow: hidden;
    border: 0;
    border-radius: 0;

    background-image: url("historyy.jpg");
       background-position: center, center 52%;
    background-size: cover;
    background-repeat: no-repeat;

    border-bottom: 1px solid #e4e8dc;
}

 
/*
If your farm/egg banner uses a different filename, replace only
the URL above with the correct image path in your project.
*/

.customer-page-title {
     margin: 0 0 7px;
    color: #17472d;
    font-family: Georgia, "Times New Roman", serif;
    font-size: clamp(27px, 3.1vw, 29px);
    font-weight: 700;
    line-height: 1.12;
    letter-spacing: -0.5px;
}

.customer-page-title::after {
    content: "View and track the status of your past reservations and egg purchases.";
    display: block;
    margin-top: 7px;
    color: #5c6d5e;
    font-size: 15px;
    font-weight: 400;
    letter-spacing: 0;
}

/* The notification/account controls now live in the navbar. */

.customer-topbar-right {
    display: none;
}


/* =========================================================
   HISTORY BODY — INSIDE OUTER CARD
========================================================= */

.history-body {
    width: 100%;
    padding: 18px 4.5% 24px;
    background: #ffffff;
    border-radius: 0;
}


/* =========================================================
   STATISTICS GRID — SHOW FOUR SUMMARY CARDS
========================================================= */

.stats-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 16px;
    width: 100%;
    margin: 0 0 20px;
}

.stat-card {
    min-width: 0;
    min-height: 105px;
    padding: 17px 18px;
    display: flex;
    flex-direction: column;
    justify-content: center;
    gap: 7px;
    background: #ffffff;
    border: 1px solid #e1e8df;
    border-radius: 10px;
    box-shadow: 0 2px 7px rgba(41, 61, 43, 0.05);
}

.stat-icon {
    width: 30px;
    height: 30px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 8px;
    background: #e8f2e8;
    color: #28613e;
    font-size: 14px;
}

.stat-label {
    color: #718075;
    font-size: 11px;
    font-weight: 600;
}

.stat-value {
    color: #214d31;
    font-size: 21px;
    font-weight: 700;
    line-height: 1.3;
    overflow-wrap: anywhere;
}

/* Individual card accents */

.stat-green .stat-icon {
    background: #e3f2e5;
    color: #28713c;
}

.stat-yellow .stat-icon {
    background: #fff2d6;
    color: #a87518;
}

.stat-money .stat-icon {
    background: #e3edf8;
    color: #356b9b;
}

.stat-blue .stat-icon {
    background: #e8e9fa;
    color: #5556a0;
}

/* Responsive layout */

@media (max-width: 900px) {
    .stats-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 420px) {
    .stats-grid {
        gap: 10px;
    }

    .stat-card {
        min-height: 95px;
        padding: 12px;
    }

    .stat-value {
        font-size: 18px;
    }
}


/* =========================================================
   SEARCH AND FILTER TOOLBAR
========================================================= */

#historyFilterForm {
    width: 100%;
    margin: 0 0 12px;
}

.history-toolbar {
    width: 100%;
    display: grid;
    grid-template-columns: minmax(180px, 2fr) repeat(4, minmax(95px, .9fr)) 70px;
    align-items: center;
    gap: 8px;
    margin: 0;
    padding: 0;
}

.search-box {
    position: relative;
    width: 100%;
    min-width: 0;
}

.search-box i {
    position: absolute;
    top: 50%;
    left: 11px;
    transform: translateY(-50%);
    color: #819087;
    font-size: 10px;
    pointer-events: none;
}

.search-box input {
    width: 100%;
    height: 34px;
    padding: 0 10px 0 29px;
    border: 1px solid #e0e5df;
    border-radius: 6px;
    outline: none;
    background: #fff;
    color: #45594b;
    font-family: Arial, Helvetica, sans-serif;
    font-size: 10px;
}

.search-box input::placeholder {
    color: #939d95;
}

.search-box input:focus,
.filter-select:focus {
    border-color: #7fa58a;
    box-shadow: 0 0 0 2px rgba(61, 119, 77, .08);
}

.filter-select {
    width: 100%;
    min-width: 0;
    height: 34px;
    padding: 0 22px 0 9px;
    border: 1px solid #e0e5df;
    border-radius: 6px;
    outline: none;
    background-color: #fff;
    color: #586a5d;
    font-family: Arial, Helvetica, sans-serif;
    font-size: 10px;
    font-weight: 500;
    cursor: pointer;
}

.clear-filter-button {
    width: 70px;
    height: 34px;
    padding: 0 7px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 5px;
    border: 1px solid #dce3dc;
    border-radius: 6px;
    background: #fff;
    color: #4e7057;
    font-family: Arial, Helvetica, sans-serif;
    font-size: 10px;
    font-weight: 700;
    cursor: pointer;
    transition: background .15s ease;
}

.clear-filter-button:hover {
    background: #edf4ed;
}

/* =========================================================
   TABLE CARD
========================================================= */

.table-card {
    width: 100%;
    overflow: hidden;
    border: 1px solid #e4e9e2;
    border-radius: 8px;
    background: #fff;
    box-shadow: 0 2px 8px rgba(41, 61, 43, .035);
}

.table-card::before {
    content: "My Reservations";
    display: block;
    min-height: 33px;
    padding: 10px 13px 7px;
    color: #304b39;
    font-size: 12px;
    font-weight: 700;
    background: #fff;
}

.table-scroll {
    width: 100%;
    overflow-x: auto;
}

/* =========================================================
   TABLE
========================================================= */


.table-card table {
    width: 100%;
    min-width: 1100px;
    table-layout: fixed;
    border-collapse: collapse;
    background: #fff;
}


.table-card thead {
    background: #f0f4ef;
}

.table-card thead th {
    height: 31px;
    padding: 7px 9px;
    border-top: 1px solid #edf0eb;
    border-bottom: 1px solid #e5eae3;
    color: #68766c;
    font-size: 9px;
    font-weight: 700;
    text-align: left;
    white-space: nowrap;
}


.table-card thead th:nth-child(1) { width: 16%; } /* Reservation Code */
.table-card thead th:nth-child(2) { width: 7%; }  /* Size */
.table-card thead th:nth-child(3) { width: 10%; } /* Total Qty */
.table-card thead th:nth-child(4) { width: 10%; } /* Method */
.table-card thead th:nth-child(5) { width: 13%; } /* Reservation Date */
.table-card thead th:nth-child(6) { width: 14%; } /* Delivery/Pickup Date */
.table-card thead th:nth-child(7) { width: 10%; } /* Total */
.table-card thead th:nth-child(8) { width: 9%; }  /* Status */
.table-card thead th:nth-child(9) { width: 11%; } /* Action */


.table-card tbody tr {
    background: #fff;
    transition: background .15s ease;
}

.table-card tbody tr:hover {
    background: #f8fbf7;
}

.table-card tbody td {
    height: 37px;
    padding: 6px 9px;
    border-bottom: 1px solid #edf0eb;
    color: #657268;
    font-size: 10px;
    vertical-align: middle;
}

.table-card tbody tr:last-child td {
    border-bottom: 0;
}

.table-card .reservation-code {
    display: flex;
    align-items: center;
    gap: 5px;
    color: #28613e;
    font-size: 10px;
    font-weight: 700;
    white-space: nowrap;
}

.table-card .reservation-code::before {
    content: "\f073";
    font-family: "Font Awesome 6 Free";
    font-weight: 900;
    color: #477a54;
    font-size: 9px;
}

.table-card .size-value,
.table-card .trays-value,
.table-card .amount-value {
    color: #5c6d61;
    font-size: 10px;
    font-weight: 500;
    white-space: nowrap;
}

.table-card .method-cell {
    white-space: nowrap;
}

.table-card .method-display {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    color: #5c6c60;
    font-size: 10px;
    font-weight: 500;
    white-space: nowrap;
}

.table-card .method-display i {
    width: 12px;
    color: #38704a;
    font-size: 9px;
    text-align: center;
}

.table-card .date-main {
    color: #5c6b60;
    font-size: 10px;
    font-weight: 500;
    white-space: nowrap;
}

.table-card .date-time {
    margin-top: 3px;
    color: #929c93;
    font-size: 9px;
    white-space: nowrap;
}

.table-card .status-badge {
    min-width: 0;
    min-height: 18px;
    height: auto;
    padding: 4px 8px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border: 0;
    border-radius: 20px;
    font-size: 9px;
    font-weight: 700;
    line-height: 1;
    white-space: nowrap;
}

.table-card .status-completed {
    background: #e0f1df;
    color: #337345;
}

.table-card .status-cancelled {
    background: #f9e3e1;
    color: #b6534c;
}


.table-card .actions-cell {
    text-align: center;
    white-space: nowrap;
}

.table-card .actions-wrapper {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 100%;
    gap: 4px;
}

.table-card .actions-wrapper .action-button {
    margin: 0 auto;
}


.table-card .action-button {
    min-height: 24px;
    height: 24px;
    padding: 0 8px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border: 1px solid #1f5c36;
    border-radius: 5px;
    background: #15532f;
    color: #fff;
    font-family: Arial, Helvetica, sans-serif;
    font-size: 9px;
    font-weight: 700;
    cursor: pointer;
    transition: background .15s ease;
}

.table-card .print-button {
    width: auto;
}

.table-card .action-button i {
    margin-right: 4px;
    font-size: 9px;
}

.table-card .action-button:hover {
    background: #267343;
}

/* Empty-state row */

.table-card .empty-state {
    height: 100px;
    padding: 25px 15px;
    text-align: center;
    color: #87948a;
    font-size: 11px;
}

.table-card .empty-state i {
    margin-right: 6px;
    color: #8da594;
}

/* =========================================================
   TABLE FOOTER AND PAGINATION
========================================================= */

.table-footer {
    min-height: 38px;
    padding: 7px 12px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    border-top: 1px solid #edf0eb;
    background: #fff;
    color: #8a958c;
    font-size: 9px;
}

.footer-number {
    color: #65756a;
    font-weight: 700;
}

.pagination {
    display: flex;
    align-items: center;
    gap: 4px;
}

.pagination a {
    width: 22px;
    height: 22px;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 1px solid #e2e7e1;
    border-radius: 5px;
    background: #fff;
    color: #647369;
    text-decoration: none;
}

/* =========================================================
   MODALS
========================================================= */

#cancelModal {
    z-index: 500;
}

.cancel-modal-box {
    width: 100%;
    max-width: 390px;
    padding: 25px;
    border-radius: 14px;
    background: #fff;
    box-shadow: 0 20px 50px rgba(0, 0, 0, .18);
}

#receipt-print-window {
    display: none;
}

/* =========================================================
   RESPONSIVE
========================================================= */

@media (max-width: 1100px) {
      .history-shell {
        width: calc(100% - 28px);
    }

    .customer-dashboard-topbar {
        min-height: 105px;
        padding: 20px 18px;
        background-position: center, right center;
    }

    .customer-page-title {
        font-size: 20px;
    }

    .customer-page-title::after {
        max-width: 260px;
        font-size: 10px;
        line-height: 1.5;
    }

    .history-body {
        padding: 14px 12px 20px;
    }

    .history-toolbar {
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 7px;
    }

    .search-box {
        grid-column: 1 / -1;
    }

    .clear-filter-button {
        width: 100%;
    }

    .table-card {
        border-radius: 7px;
    }

    .table-footer {
        flex-wrap: wrap;
    }

}

@media (max-width: 420px) {

    .customer-dashboard-topbar {
        min-height: 100px;
    }

    .customer-page-title {
        font-size: 18px;
    }

    .history-toolbar {
        grid-template-columns: 1fr 1fr;
    }

    .search-box {
        grid-column: 1 / -1;
    }

}

/* =========================================================
   PRINT
========================================================= */

@media print {
    @page {
        size: 80mm auto;
        margin: 0;
    }

    html,
    body {
        width: 80mm !important;
        margin: 0 !important;
        padding: 0 !important;
        background: #fff !important;
    }

    body * {
        visibility: hidden;
    }

    #receipt-print-window,
    #receipt-print-window * {
        visibility: visible;
    }

    #receipt-print-window {
        display: block !important;
        position: absolute;
        top: 0;
        left: 0;
        width: 80mm !important;
        height: auto !important;
        min-height: 0 !important;
        margin: 0 !important;
        padding: 5mm !important;
        background: #fff !important;
        color: #000 !important;
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

    .no-print,
    .customer-navbar,
    .customer-footer,
    .customer-dashboard-topbar,
    .history-body {
        display: none !important;
    }
}

    </style>

</head>

<body>

<?php require 'customer_header.php'; ?>
<div class="main-content">

    <div class="history-shell">
<div class="history-outer-card">
        <div class="history-card">

       <!-- =====================================================
     CUSTOMER DASHBOARD TOPBAR
===================================================== -->


<!-- =========================================================
     HISTORY PAGE BANNER
========================================================= -->

<div class="customer-dashboard-topbar">
    <h1 class="customer-page-title">
        My Reservations &amp; History
    </h1>
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
        value="Confirmed"
        <?php
        echo $filter_status === 'Confirmed'
            ? 'selected'
            : '';
        ?>
    >
        Confirmed
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
           Reservation Date
        </th>
         <th>Delivery/Pickup Date</th>

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

/* =========================================================
   STATUS DISPLAY
   Preserve the actual status from the database.
========================================================= */

$status = isset($row['status'])
    ? trim($row['status'])
    : '';

$status_lower = strtolower($status);

if (
    $status_lower === 'cancelled' ||
    $status_lower === 'canceled'
) {

    $status = 'Cancelled';

    $status_class = 'status-cancelled';

} elseif ($status_lower === 'completed') {

    $status = 'Completed';

    $status_class = 'status-completed';

} elseif ($status_lower === 'confirmed') {

    $status = 'Confirmed';

    $status_class = 'status-completed';

} else {

    $status = ucwords($status_lower);

    $status_class = 'status-completed';
}


               
/* =================================================
   RESERVATION DATE
   Date when the customer submitted the reservation
================================================= */

$reservation_timestamp = false;

if (
    isset($row['created_at']) &&
    trim((string)$row['created_at']) !== ''
) {
    $reservation_timestamp = strtotime(
        $row['created_at']
    );
}

if ($reservation_timestamp !== false) {

    $reservation_date_display = date(
        'M j, Y',
        $reservation_timestamp
    );

    $reservation_time_display = date(
        'h:i A',
        $reservation_timestamp
    );

} else {

    $reservation_date_display = 'Unavailable';
    $reservation_time_display = '';
}


/* =================================================
   DELIVERY / PICKUP DATE
   Scheduled date stored in reservation_date
================================================= */

$scheduled_timestamp = false;

if (
    isset($row['reservation_date']) &&
    trim((string)$row['reservation_date']) !== ''
) {
    $scheduled_timestamp = strtotime(
        $row['reservation_date']
    );
}

if ($scheduled_timestamp !== false) {

    $scheduled_date_display = date(
        'M j, Y',
        $scheduled_timestamp
    );

} else {

    $scheduled_date_display = 'Not scheduled';
}



                    // =================================================
                    // METHOD ICON
                    // =================================================

                    if (
                        strtolower(
                            $row['delivery_method']
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
                                    $row['reservation_code']
                                );
                                ?>

                            </div>

                        

                        </td>


                     <!-- SIZE -->

<td>

    <div class="size-value">

        <?php

        $display_sizes = array();

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
                                    $row['delivery_method']
                                );
                                ?>

                            </span>

                        </td>


                     
<!-- RESERVATION DATE -->

<td>
    <div class="date-main">
        <?php
        echo htmlspecialchars(
            $reservation_date_display,
            ENT_QUOTES,
            'UTF-8'
        );
        ?>
    </div>

    <?php if ($reservation_time_display !== ''): ?>
        <div class="date-time">
            <?php
            echo htmlspecialchars(
                $reservation_time_display,
                ENT_QUOTES,
                'UTF-8'
            );
            ?>
        </div>
    <?php endif; ?>
</td>


<!-- DELIVERY / PICKUP DATE -->

<td>
    <div class="date-main">
        <?php
        echo htmlspecialchars(
            $scheduled_date_display,
            ENT_QUOTES,
            'UTF-8'
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

                   <td colspan="9" class="empty-state">

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
</div>

<!-- =========================================================
     PRINT WINDOW
========================================================= -->

<div id="receipt-print-window"></div>



<!-- =========================================================
     CUSTOMER LOGOUT CONFIRMATION MODAL
========================================================= -->




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





</script>

<?php require 'customer_modal.php'; ?>

<?php require 'customer_footer.php'; ?>

</body>
</html>
