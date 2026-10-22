<?php
ob_start();

// Include standardized session configuration FIRST
require_once 'session_config.php';

date_default_timezone_set('Asia/Manila');

require 'db.php';

// Helper function to get manager users
function getManagerUsers($conn) {
    $managers = array();
    $stmt = $conn->prepare("SELECT id FROM users WHERE role = 'manager'");
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $managers[] = $row['id'];
    }
    $stmt->close();
    return $managers;
}

function getOwnerUsers($conn) {
    $owners = array();
    $stmt = $conn->prepare("SELECT id FROM users WHERE role = 'owner' AND status = 'active'");
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $owners[] = $row['id'];
    }
    $stmt->close();
    return $owners;
}

// Security Enforcement
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Make sure only customers can access this page
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'customer') {
    header("Location: login.php");
    exit();
}

/* =========================================================
   CUSTOMER LOGOUT TOKEN
   PHP 5.5 COMPATIBLE
========================================================= */

if (empty($_SESSION['customer_logout_token'])) {
    if (function_exists('random_bytes')) {
        $_SESSION['customer_logout_token'] = bin2hex(
            random_bytes(32)
        );
    } elseif (function_exists('openssl_random_pseudo_bytes')) {
        $_SESSION['customer_logout_token'] = bin2hex(
            openssl_random_pseudo_bytes(32)
        );
    } else {
        $_SESSION['customer_logout_token'] = hash(
            'sha256',
            uniqid(mt_rand(), true) . session_id()
        );
    }
}

// Match the active logged-in parameters
$user_id = $_SESSION['user_id'];
$customer_name = isset($_SESSION['fullname']) ? $_SESSION['fullname'] : '';
$user_role = isset($_SESSION['role']) ? $_SESSION['role'] : 'Customer';

// =========================================================
// NOTIFICATION COUNT
// =========================================================

$unread_notifications = 0;
$notification_count_result = $conn->query("SELECT COUNT(*) AS total FROM notifications WHERE user_id = " . (int)$user_id . " AND is_read = 0");
if ($notification_count_result && ($notification_count_row = $notification_count_result->fetch_assoc())) {
    $unread_notifications = (int)$notification_count_row['total'];
}

// Helper function to insert notifications easily into the database
function add_notification($conn, $user_id, $title, $description, $type = 'info') {
    $stmt = $conn->prepare("INSERT INTO notifications (user_id, title, description, type, is_read) VALUES (?, ?, ?, ?, 0)");
    $stmt->bind_param("isss", $user_id, $title, $description, $type);
    $stmt->execute();
    $stmt->close();
}

// AJAX API Endpoint Processing
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action']) && $_GET['action'] === 'log_cancellation') {
    header('Content-Type: application/json');
    add_notification($conn, $user_id, "Draft process cancelled", "Timeline reset to Draft status", "alert");
  echo json_encode(array('status' => 'success'));
    exit();
}

$message = isset($_SESSION['success_message']) ? $_SESSION['success_message'] : '';
$is_submitted = isset($_SESSION['is_submitted']) ? $_SESSION['is_submitted'] : false;

unset($_SESSION['success_message']);
unset($_SESSION['is_submitted']);

// Price list configuration
$prices = array( 'Extra Small' => 140, 'Small' => 150, 'Medium' => 175, 'Large' => 195, 'Extra Large' => 235, 'Jumbo' => 255, 'Super Jumbo' => 270, 'Double Yolk' => 320 );

$today = date('Y-m-d');

// =========================================================
// GENERATE RESERVATION CODE
// Format: RES-YYYYMMDD-001
// =========================================================

function generateReservationCode($conn) {

    $datePrefix = date('Ymd');
    $prefix = 'RES-' . $datePrefix . '-';

    $stmt = $conn->prepare("
        SELECT reservation_code
        FROM reservations
        WHERE reservation_code LIKE ?
        ORDER BY id DESC
        LIMIT 1
    ");

    $likePrefix = $prefix . '%';

    $stmt->bind_param("s", $likePrefix);
    $stmt->execute();

    $result = $stmt->get_result();
    $lastNumber = 0;

    if ($row = $result->fetch_assoc()) {

        $lastCode = $row['reservation_code'];

        $parts = explode('-', $lastCode);

        if (count($parts) == 3) {
            $lastNumber = intval($parts[2]);
        }
    }

    $stmt->close();

    $nextNumber = $lastNumber + 1;

    return $prefix . str_pad($nextNumber, 3, '0', STR_PAD_LEFT);
}


// Current reservation ID displayed on the form
$reservation_code = generateReservationCode($conn);


// =========================================================
// HANDLE FORM SUBMISSION
// =========================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_reservation'])) {

    $customer_name = $_POST['customer_name'];
    $contact_number = $_POST['contact_number'];
    $delivery_address = $_POST['delivery_address'];
    $delivery_method = isset($_POST['delivery_method'])
    ? $_POST['delivery_method']
    : '';

$reservation_date = isset($_POST['reservation_date']) && $_POST['reservation_date'] !== ''
    ? $_POST['reservation_date']
    : null;

$reserved_at = date('Y-m-d H:i:s');


// =========================================================
// RESERVATION DATE RULE
// Pickup  = customer chooses the date
// Delivery = manager will assign the date later
// =========================================================

if ($delivery_method === 'Pickup' && empty($reservation_date)) {

    $_SESSION['success_message'] =
        "Please select your preferred pickup date.";

    $_SESSION['is_submitted'] = false;

    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

if ($delivery_method === 'Delivery') {

    // No date yet.
    // Manager will assign the delivery date later.
    $reservation_date = null;
}

    // Get the reservation code from the hidden form field
    $reservation_code = isset($_POST['reservation_code'])
        ? $_POST['reservation_code']
        : generateReservationCode($conn);

    // Grab item bundle arrays
    $egg_types = isset($_POST['egg_types']) ? $_POST['egg_types'] : array();
    $quantities = isset($_POST['quantities']) ? $_POST['quantities'] : array();

    if (!empty($egg_types) && count($egg_types) === count($quantities)) {

        $conn->autocommit(false);

        try {

            $stmt = $conn->prepare("
              INSERT INTO reservations
(user_id, customer_name, contact_number, delivery_address, egg_type, quantity, delivery_method, reservation_date, reserved_at, total_price, reservation_code, status)
VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $total_bundle_trays = 0;

            for ($i = 0; $i < count($egg_types); $i++) {

                $egg_type = $egg_types[$i];
                $quantity = intval($quantities[$i]);

                if ($quantity <= 0) {
                    continue;
                }

                $price_per_egg = isset($prices[$egg_type])
                    ? $prices[$egg_type]
                    : 0;

                $total_price = $quantity * $price_per_egg;

                $total_bundle_trays += $quantity;

                $status = 'Pending';

             $stmt->bind_param(
    "issssisssdss",
    $user_id,
    $customer_name,
    $contact_number,
    $delivery_address,
    $egg_type,
    $quantity,
    $delivery_method,
    $reservation_date,
    $reserved_at,
    $total_price,
    $reservation_code,
    $status
);

                $stmt->execute();
            }

            $stmt->close();

// Notification to customer
add_notification(
    $conn,
    $user_id,
    "Reservation submitted!",
    "Reservation {$reservation_code} with {$total_bundle_trays} total tray(s) is waiting for manager confirmation.",
    "success"
);

// NOTIFY ALL MANAGERS
$managers = getManagerUsers($conn);
foreach ($managers as $manager_id) {
    add_notification(
        $conn,
        $manager_id,
        "New Reservation Received",
        "Customer {$customer_name} submitted reservation {$reservation_code}. Please review and confirm.",
        "alert"
    );
}

$owners = getOwnerUsers($conn);
foreach ($owners as $owner_id) {
    add_notification(
        $conn,
        $owner_id,
        "New Reservation Received",
        "Customer {$customer_name} submitted reservation {$reservation_code}.",
        "info"
    );
}

            $conn->commit();

            $_SESSION['success_message'] =
                "Reservation {$reservation_code} submitted successfully!";

            $_SESSION['is_submitted'] = true;

        } catch (Exception $e) {

            $conn->rollback();

            $_SESSION['success_message'] =
                "Error processing your reservation.";
        }
    }

    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}
  

// Fetch user notifications
$notifications = array();
$notif_stmt = $conn->prepare("SELECT id, title, description, type, is_read, created_at FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 15");
$notif_stmt->bind_param("i", $user_id);
$notif_stmt->execute();
$result = $notif_stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $notifications[] = $row;
}
$notif_stmt->close();

$unread_count = 0;
foreach ($notifications as $n) {
    if (!$n['is_read']) $unread_count++;
}

// =========================================================
// FETCH CUSTOMER RESERVATIONS
// =========================================================

$my_reservations = array();

$reservation_stmt = $conn->prepare("
   SELECT
    reservation_code,
    customer_name,
    contact_number,
    delivery_address,
    egg_type,
    quantity,
    delivery_method,
    reservation_date,
    reserved_at,
    total_price,
    status
FROM reservations
    WHERE user_id = ?
    ORDER BY id DESC
");

$reservation_stmt->bind_param("i", $user_id);
$reservation_stmt->execute();

$reservation_result = $reservation_stmt->get_result();

while ($row = $reservation_result->fetch_assoc()) {

    $code = $row['reservation_code'];

    if (!isset($my_reservations[$code])) {

        $my_reservations[$code] = array(
            'reservation_code' => $code,
            'customer_name' => $row['customer_name'],
            'delivery_address' => $row['delivery_address'],
            'delivery_method' => $row['delivery_method'],
            'reservation_date' => $row['reservation_date'],
            'reserved_at' => $row['reserved_at'],
            'status' => $row['status'],
            'total_price' => 0,
            'total_quantity' => 0,
            'items' => array()
        );
    }

    $my_reservations[$code]['total_price'] += $row['total_price'];
    $my_reservations[$code]['total_quantity'] += $row['quantity'];

    $my_reservations[$code]['items'][] = array(
        'egg_type' => $row['egg_type'],
        'quantity' => $row['quantity']
    );
}

$reservation_stmt->close();


// =========================================================
// MY RESERVATIONS PAGINATION
// 3 RESERVATIONS PER PAGE
// =========================================================

$reservations_per_page = 3;

$total_reservations = count($my_reservations);

$total_pages = ceil(
    $total_reservations / $reservations_per_page
);

$current_page = isset($_GET['page'])
    ? intval($_GET['page'])
    : 1;

if ($current_page < 1) {
    $current_page = 1;
}

if ($current_page > $total_pages && $total_pages > 0) {
    $current_page = $total_pages;
}

$reservation_offset =
    ($current_page - 1) * $reservations_per_page;


// Convert associative array to indexed array
$my_reservations = array_values($my_reservations);


// Get only reservations for current page
$paginated_reservations = array_slice(
    $my_reservations,
    $reservation_offset,
    $reservations_per_page
);


// Default avatar - no profile_photo column required

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>VDVC - Customer Reservation</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
</head>
<style>


/* =========================================================
   VDVC CUSTOMER RESERVATION PAGE
   Reference-based UI redesign
========================================================= */

:root {
    --vdvc-green: #214f2c;
    --vdvc-green-dark: #103f2d;
    --vdvc-green-light: #eff5ef;
    --vdvc-text: #202622;
    --vdvc-muted: #69736d;
    --vdvc-border: #dedfd9;
    --vdvc-background: #f5f6f2;
    --vdvc-white: #ffffff;
}

* {
    box-sizing: border-box;
}

html,
body {
    margin: 0;
    padding: 0;
    width: 100%;
    min-height: 100%;
    background: var(--vdvc-background);
    color: var(--vdvc-text);
    font-family: Arial, Helvetica, sans-serif;
}

body {
    min-height: 100vh;
    overflow-x: hidden;
}

button,
input,
select,
textarea {
    font-family: inherit;
}

button {
    cursor: pointer;
}

a {
    -webkit-tap-highlight-color: transparent;
}

/* =========================================================
   CUSTOMER NAVBAR — MATCH CUSTOMER DASHBOARD
========================================================= */

.customer-navbar {
    width: 100%;
    height: 84px;

    display: flex;
    align-items: center;
    justify-content: space-between;

    padding: 0 48px;

    background: #0d4828;

    border-top: 1px solid #dddddd;
    border-bottom: 1px solid #e6e6e6;

     /* Keep navbar visible while scrolling */
    position: sticky;
    top: 0;
    left: 0;

    z-index: 1000;
}

/* LOGO */

.customer-navbar-logo {
    display: flex;
    align-items: center;

    width: 190px;
    flex-shrink: 0;
}

.customer-navbar-logo a {
    display: flex;
    align-items: center;
}

.customer-navbar-logo img {
    width: 145px;
    height: auto;
    display: block;
}

/* NAVIGATION LINKS */

.customer-navbar-center {
    height: 100%;

    display: flex;
    align-items: center;
    justify-content: center;

    gap: 38px;

    margin-left: 80px;
}

.customer-nav-link {
    height: 100%;

    display: flex;
    align-items: center;

    position: relative;

    padding: 0 2px;

    text-decoration: none;

    color: #e8f1e9;

    font-size: 14px;
    font-weight: 500;

    white-space: nowrap;

    transition: color 0.2s ease;
}

.customer-nav-link:hover {
    color: #b9e4c3;
}

.customer-nav-link.active {
    color: #ffffff;
    font-weight: 700;
}

.customer-nav-link.active::after {
    content: "";

    position: absolute;

    left: 0;
    right: 0;
    bottom: 20px;

    height: 2px;

    background: #175c31;
}

/* RIGHT SIDE */

.customer-navbar-right {
    display: flex;
    align-items: center;

    gap: 16px;

    min-width: 240px;

    justify-content: flex-end;
}

/* NOTIFICATION */

.customer-notification-wrapper {
    position: relative;
}

.customer-top-notification {
    width: 38px;
    height: 38px;

    display: flex;
    align-items: center;
    justify-content: center;

    border: none;
    background: transparent;

    color: #eaf2e8;

    font-size: 17px;

    cursor: pointer;

    position: relative;

    padding: 0;
}

.customer-top-notification:hover {
    color: #bfe3c7;
}

.customer-notification-badge {
    position: absolute;

    top: 1px;
    right: 1px;

    min-width: 15px;
    height: 15px;

    padding: 0 3px;

    display: flex;
    align-items: center;
    justify-content: center;

   background-color: #eaf2e8;
    color: #0d4828;
    border: 2px solid #0d4828;

    border-radius: 50%;


    font-size: 8px;
    font-weight: 700;
}

/* NOTIFICATION DROPDOWN */

.customer-notification-dropdown {
    position: absolute;

    top: 48px;
    right: 0;

    width: 355px;

    background: #ffffff;

    border: 1px solid #dedede;

    border-radius: 10px;

    box-shadow: 0 12px 30px rgba(0, 0, 0, 0.12);

    overflow: hidden;

    z-index: 1000;
}

.customer-notification-dropdown.hidden {
    display: none !important;
}

.customer-notification-dropdown-header {
    min-height: 52px;

    padding: 0 16px;

    display: flex;
    align-items: center;
    justify-content: space-between;

    border-bottom: 1px solid #eeeeee;
}

.customer-panel-title-group {
    display: flex;
    align-items: center;
    gap: 9px;
}

.customer-panel-title-icon {
    width: 30px;
    height: 30px;

    display: flex;
    align-items: center;
    justify-content: center;

    border-radius: 50%;

    background: #edf5ee;
    color: #39704c;

    font-size: 13px;
}

.customer-dashboard-panel-title {
    font-size: 13px;
    font-weight: 700;
    color: #34483d;
}

.customer-mark-all-link {
    font-size: 10px;
    font-weight: 700;

    color: #39704c;

    text-decoration: none;

    cursor: pointer;
}

.customer-mark-all-link:hover {
    text-decoration: underline;
}

.customer-notification-list {
    max-height: 390px;
    overflow-y: auto;
}

.customer-notification-item {
    display: flex;
    align-items: flex-start;

    gap: 10px;

    padding: 12px 15px;

    border-bottom: 1px solid #f0f0f0;

    cursor: pointer;

    transition: background 0.15s ease;
}

.customer-notification-item:hover {
    background: #fafcf9;
}

.customer-notification-item.unread {
    background: #f1f7f2;
}

.customer-notification-icon {
    width: 34px;
    height: 34px;

    min-width: 34px;

    border-radius: 50%;

    display: flex;
    align-items: center;
    justify-content: center;

    font-size: 13px;
}

.customer-notification-icon.success {
    background: #e8f4e9;
    color: #39834c;
}

.customer-notification-icon.alert {
    background: #fbe9e7;
    color: #c85448;
}

.customer-notification-icon.info {
    background: #e7f0f5;
    color: #46758a;
}

.customer-notification-icon.warning {
    background: #fff2dc;
    color: #bd8127;
}

.customer-notification-content {
    flex: 1;
    min-width: 0;
}

.customer-notification-title {
    font-size: 12px;
    font-weight: 700;

    color: #30473a;

    margin-bottom: 3px;
}

.customer-notification-description {
    font-size: 11px;
    line-height: 1.4;

    color: #718078;
}

.customer-notification-time {
    font-size: 10px;
    color: #9aa39e;

    margin-top: 3px;
}

.customer-notification-empty {
    padding: 40px 20px;

    text-align: center;

    color: #929b95;
}

.customer-notification-empty i {
    font-size: 30px;
    margin-bottom: 10px;
    color: #ccd5cf;
}

.customer-notification-empty p {
    margin: 0;
    font-size: 12px;
}

/* CUSTOMER ACCOUNT */


/* =========================================================
   CUSTOMER ACCOUNT DROPDOWN
========================================================= */

.customer-account-wrapper {
    position: relative;
    display: flex;
    align-items: center;
    gap: 8px;
}

.customer-account-link {
    display: flex;
    align-items: center;
    gap: 9px;
    text-decoration: none;
   color: #263d32;
}



.customer-account-toggle {
    width: 28px;
    height: 28px;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 0;
    border-radius: 50%;
    background: transparent;
    color: #eaf2e8;
    font-size: 11px;
    cursor: pointer;
    transition: background 0.2s ease, color 0.2s ease;
}

.customer-account-toggle:hover {
    background: rgba(255, 255, 255, 0.12);
    color: #fff;
}

.customer-account-dropdown {
    position: absolute;
    top: calc(100% + 13px);
    right: 0;
    width: 190px;
    padding: 7px;
    background: #ffffff;
    border: 1px solid #e1e7e1;
    border-radius: 10px;
    box-shadow: 0 10px 28px rgba(0, 0, 0, 0.16);
    z-index: 1100;
}

.customer-account-dropdown.hidden {
    display: none;
}

.customer-logout-option {
    width: 100%;
    display: flex;
    align-items: center;
    gap: 11px;
    padding: 12px 13px;
    border: none;
    border-radius: 7px;
    background: transparent;
    color: #b33a32;
    font-size: 13px;
    font-weight: 600;
    text-align: left;
    cursor: pointer;
}

.customer-logout-option:hover {
    background: #fff0ee;
}

.customer-logout-option i {
    width: 16px;
    text-align: center;
}

/* =========================================================
   LOGOUT CONFIRMATION MODAL
========================================================= */

.customer-logout-overlay {
    position: fixed;
    inset: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
    background: rgba(15, 31, 21, 0.55);
    z-index: 2000;
}

.customer-logout-overlay.hidden {
    display: none;
}

.customer-logout-modal {
    width: 100%;
    max-width: 390px;
    padding: 28px 25px 23px;
    background: #ffffff;
    border: 1px solid #e4e8e3;
    border-radius: 15px;
    box-shadow: 0 18px 55px rgba(0, 0, 0, 0.22);
    text-align: center;
    animation: logoutModalAppear 0.18s ease-out;
}

.customer-logout-icon {
    width: 58px;
    height: 58px;
    margin: 0 auto 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    background: #fff0ee;
    color: #b33a32;
    font-size: 23px;
}

.customer-logout-modal h2 {
    margin: 0 0 10px;
    color: #263d32;
    font-size: 20px;
    font-weight: 700;
}

.customer-logout-modal p {
    margin: 0;
    color: #737d75;
    font-size: 13px;
    line-height: 1.6;
}

.customer-logout-actions {
    display: flex;
    justify-content: center;
    gap: 10px;
    margin-top: 25px;
}

.customer-logout-cancel,
.customer-logout-confirm {
    min-height: 40px;
    padding: 0 17px;
    border-radius: 7px;
    font-family: inherit;
    font-size: 12px;
    font-weight: 700;
    cursor: pointer;
    transition: background 0.2s ease;
}

.customer-logout-cancel {
    border: 1px solid #d9dfd9;
    background: #ffffff;
    color: #46554d;
}

.customer-logout-cancel:hover {
    background: #f3f5f2;
}

.customer-logout-confirm {
    border: 1px solid #b33a32;
    background: #b33a32;
    color: #ffffff;
}

.customer-logout-confirm:hover {
    background: #962e27;
}

.customer-logout-actions form {
    margin: 0;
}

@keyframes logoutModalAppear {
    from {
        opacity: 0;
        transform: translateY(7px) scale(0.98);
    }

    to {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
}


.customer-avatar {
    width: 43px;
    height: 43px;

    min-width: 43px;

    display: flex;
    align-items: center;
    justify-content: center;

    border-radius: 50%;

   background-color: #dcebdd;
color: #0d4828;

    border: 1px solid #e1e7e1;

   

    font-size: 16px;
}

.customer-account-details {
    display: flex;
    flex-direction: column;

    justify-content: center;

    min-width: 100px;
}

.customer-top-name {
    display: block;

    font-size: 13px;
    font-weight: 700;

    line-height: 1.2;

    color: #ffffff;
}

.customer-date-time {
    display: flex;
    flex-direction: column;

    gap: 1px;

    margin-top: 3px;
}

.customer-date {
    font-size: 9px;
    color: #d5e5d8;
}

.customer-time {
    font-size: 9px;

    color: #d5e5d8;

    font-weight: 600;
}

.customer-dropdown-icon {
    color: #718079;
    font-size: 9px;

    margin-left: 3px;
}

/* =========================================================
   RESPONSIVE NAVBAR — MATCH DASHBOARD
========================================================= */

@media screen and (max-width: 1200px) {
    .customer-navbar {
        padding: 0 30px;
    }

    .customer-navbar-center {
        gap: 25px;
        margin-left: 30px;
    }
}

@media screen and (max-width: 900px) {
    .customer-navbar {
        height: auto;
        min-height: 75px;

        padding: 12px 22px;

        flex-wrap: wrap;

        gap: 10px;
    }

    .customer-navbar-logo {
        width: auto;
    }

    .customer-navbar-logo img {
        width: 120px;
    }

    .customer-navbar-center {
        order: 3;

        width: 100%;

        height: 42px;

        margin-left: 0;

        gap: 28px;

        justify-content: center;
    }

    .customer-navbar-right {
        min-width: auto;
    }
}

@media screen and (max-width: 600px) {
    .customer-navbar {
        padding: 10px 15px;
    }

    .customer-account-details,
    .customer-dropdown-icon {
        display: none;
    }

    .customer-navbar-center {
        gap: 18px;
        overflow-x: auto;
        justify-content: flex-start;
    }

    .customer-nav-link {
        font-size: 12px;
        white-space: nowrap;
        flex-shrink: 0;
    }
}


/* =========================================================
   MAIN PAGE LAYOUT
   No sidebar or dashboard left margin
========================================================= */

.main-content {
    width: 100%;
    min-height: 0;
    margin: 0;
    padding: 15px;
    background: #f3f1eb;
}

.main-content > .flex-1 {
    width: 100%;
    min-width: 0;
    display: block;
}

.main-content > .flex-1 > .w-full,
.main-content > .flex-1 > .max-w-7xl {
    width: 100%;
    max-width: none;
    margin: 0;
    padding: 0;
}

#reservation_form {
    width: 100%;
    margin: 0;
}

.reservation-card {
    width: 100%;
    overflow: visible;
    background: transparent;
    border: none;
    border-radius: 0;
    box-shadow: none;
}

/* =========================================================
   RESERVATION HERO BANNER

   Uses reservation.jpg. Change the path only if your
   actual banner image is stored in another location.
========================================================= */

.reservation-hero {
    position: relative;
    width: 100%;
    min-height: 143px;
    display: flex;
    align-items: center;
    padding: 24px 5%;
    overflow: hidden;
    
    background-image:
       
        url("historyy.jpg");
    background-position: center, center 52%;
    background-size: cover;
    background-repeat: no-repeat;
}

.reservation-hero-content {
    position: relative;
    z-index: 1;
    width: 100%;
    max-width: 1200px;
    margin: 0 auto;
}

.reservation-hero h1 {
    margin: 0 0 7px;
    color: #17472d;
    font-family: Georgia, "Times New Roman", serif;
    font-size: clamp(27px, 3.1vw, 35px);
    font-weight: 700;
    line-height: 1.12;
    letter-spacing: -0.5px;
}

.reservation-hero h1 i {
    margin-right: 5px;
    font-size: 0.8em;
}

.reservation-hero p {
    margin: 0;
    color: #303a32;
    font-size: 15px;
    line-height: 1.45;
}

/* =========================================================
   THREE-STEP PROGRESS BAR
========================================================= */

.reservation-progress {
    width: calc(100% - 56px);
    max-width: 1250px;
    min-height: 76px;
    margin: 14px auto 13px;
    padding: 13px 20px;
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    align-items: center;
    background: rgba(255, 255, 255, 0.8);
    border: 1px solid #eeefe9;
    border-radius: 15px;
    box-shadow: 0 2px 7px rgba(54, 66, 48, 0.025);
}

.reservation-progress-step {
    min-width: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    color: #303830;
    font-size: 11px;
    line-height: 1.45;
}

.reservation-progress-step:not(:last-child) {
    border-right: 1px solid #e2e4de;
}

.reservation-progress-number {
    width: 30px;
    height: 30px;
    min-width: 30px;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 1px solid #d3d9d2;
    border-radius: 50%;
    background: #ffffff;
    color: #38453b;
    font-size: 12px;
}

.reservation-progress-step.active .reservation-progress-number {
    border-color: var(--vdvc-green);
    background: var(--vdvc-green);
    color: #ffffff;
}

.reservation-progress-label {
    max-width: 155px;
}

/* =========================================================
   RESERVATION PROGRESS - COMPLETED STEPS
========================================================= */

.reservation-progress-step.completed .reservation-progress-number {
    background-color: #214f2c;
    border-color: #214f2c;
    color: #ffffff;
}

.reservation-progress-step.completed .reservation-progress-label {
    color: #214f2c;
    font-weight: 700;
}

/* =========================================================
   RESERVATION THREE-COLUMN GRID
========================================================= */

.reservation-card > .p-6 {
    width: 100%;
    max-width: 1300px;
    margin: 0 auto;
    padding: 0 28px 30px !important;
}

.reservation-grid {
    display: grid !important;
    grid-template-columns: minmax(0, 1.03fr) minmax(0, 1.03fr) minmax(0, 1fr);
    align-items: stretch !important;
    gap: 20px !important;
}

.step-column {
    display: flex;
    min-width: 0;
    min-height: 0;
}

.step-card {
    width: 100%;
    min-width: 0;
    min-height: 385px;
    padding: 16px;
    display: flex;
    flex-direction: column;
    background: #ffffff;
    border: 1px solid #dedfd9;
    border-radius: 15px;
    box-shadow: 0 4px 12px rgba(46, 54, 43, 0.055);
    overflow: visible;
}

.step-card h2 {
    margin: 0 0 14px !important;
    color: #242824 !important;
    font-size: 16px !important;
    font-weight: 700 !important;
    line-height: 1.35;
}

.step-scroll {
    min-height: 0;
    flex: 1 1 auto;
    overflow: visible;
    padding-right: 0;
}

.step-1-content {
    display: flex;
    flex-direction: column;
    gap: 9px;
}

.step-1-content > div,
.step-scroll > div {
    min-width: 0;
}

/* =========================================================
   FORM LABELS AND INPUTS
========================================================= */

.step-card label {
    display: block;
    margin-bottom: 4px !important;
    color: #343a35 !important;
    font-size: 13px !important;
    font-weight: 700 !important;
    line-height: 1.35;
    letter-spacing: 0.15px;
    text-transform: uppercase;
}

.step-card input:not([type="hidden"]):not([type="checkbox"]),
.step-card select,
.step-card textarea {
    width: 100%;
    min-width: 0;
    min-height: 32px;
    padding: 6px 10px !important;
    border: 1px solid #d8dbd6 !important;
    border-radius: 8px !important;
    background: #ffffff;
    color: #333a35;
    font-size: 14px !important;
    line-height: 1.35;
    box-shadow: none;
    outline: none;
    transition: border-color 0.15s ease, box-shadow 0.15s ease;
}

.step-card input:focus,
.step-card select:focus,
.step-card textarea:focus {
    border-color: #73967a !important;
    box-shadow: 0 0 0 2px rgba(33, 79, 44, 0.08);
}

.step-card input::placeholder,
.step-card textarea::placeholder {
    color: #8b918c;
    opacity: 1;
}

.step-card input[readonly] {
    background: #f8f9f6 !important;
}

.step-card .relative > i.absolute {
    top: 50% !important;
    right: 11px !important;
    transform: translateY(-50%);
}

.step-card .relative input {
    padding-right: 30px !important;
}

/* =========================================================
   RESERVATION CODE
========================================================= */

.step-1-card > .rounded-lg {
    margin-bottom: 8px !important;
    padding: 8px 12px !important;
    border: 1px solid #cbd8cd !important;
    border-radius: 8px !important;
    background: #f1f6f2 !important;
}

.step-1-card > .rounded-lg label {
    margin-bottom: 2px !important;
    font-size: 11px !important;
}

.step-1-card > .rounded-lg .text-lg {
    color: var(--vdvc-green) !important;
    font-size: 17px !important;
    font-weight: 800 !important;
    line-height: 1.3;
    overflow-wrap: anywhere;
}

.step-1-card > .rounded-lg p {
    margin-top: 2px !important;
    font-size: 11px !important;
}

.copy-reservation-btn {
    width: 30px !important;
    height: 30px !important;
    flex-shrink: 0;
    border: none !important;
    border-radius: 6px !important;
    background: transparent !important;
    color: #68746c !important;
    cursor: pointer;
}

.copy-reservation-btn:hover {
    background: #e5eee6 !important;
    color: var(--vdvc-green) !important;
}

/* =========================================================
   DELIVERY ADDRESS MAP
========================================================= */

#map {
    position: relative;
    z-index: 1;
    width: 100%;
    height: 136px !important;
    min-height: 136px;
    margin-top: 5px;
    overflow: hidden;
    border: 1px solid #d8ded8 !important;
    border-radius: 8px !important;
    background: #edf3ed;
}

.leaflet-container {
    font-family: Arial, Helvetica, sans-serif;
    font-size: 12px;
}

/* =========================================================
   EGG SIZE SELECTION
========================================================= */

.step-column:nth-child(2) .step-scroll > div:first-child {
    margin-bottom: 8px;
}

.step-column:nth-child(2) .grid.grid-cols-4 {
    display: grid !important;
    grid-template-columns: repeat(4, minmax(0, 1fr)) !important;
    gap: 5px !important;
    margin-bottom: 7px !important;
}

.egg-btn {
    min-width: 0;
    min-height: 48px;
    padding: 4px 2px !important;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 2px;
    border: 1px solid #e0e2dd !important;
    border-radius: 8px !important;
    background: #ffffff !important;
    color: #323a33 !important;
    transition: background 0.15s ease, border-color 0.15s ease;
}

.egg-btn:hover {
    border-color: #9eb5a1 !important;
    background: #f7faf6 !important;
}

.egg-btn.selected {
    border-color: var(--vdvc-green) !important;
    background: #f0f6f0 !important;
    color: var(--vdvc-green) !important;
    box-shadow: inset 0 0 0 1px rgba(33, 79, 44, 0.08);
}

.egg-btn > span:first-child {
    width: 17px !important;
    height: 21px !important;
    margin-bottom: 1px !important;
    display: inline-block;
    border: 1px solid #c5cbc5 !important;
    border-radius: 50% 50% 45% 45% !important;
    background: linear-gradient(145deg, #f8f8f4, #e6e8e1) !important;
    box-shadow: inset 0 1px 2px rgba(0, 0, 0, 0.035) !important;
}

.egg-btn > span:nth-child(2) {
    color: #303731 !important;
    font-size: 10px !important;
    font-weight: 500 !important;
    line-height: 1.1 !important;
    text-align: center;
    overflow-wrap: anywhere;
}

.egg-btn > span:nth-child(3) {
    color: var(--vdvc-green) !important;
    font-size: 10px !important;
    font-weight: 700 !important;
    line-height: 1.1 !important;
}

/* =========================================================
   QUANTITY AND METHOD
========================================================= */

#egg_type_select {
    min-height: 29px !important;
    padding: 5px 9px !important;
    border-radius: 7px !important;
}

#quantity_input {
    min-height: 30px !important;
}

#pickup_date_section,
#delivery_schedule_info {
    margin-top: 8px;
    margin-bottom: 8px;
}

#delivery_schedule_info {
    padding: 9px !important;
    border: 1px solid #d5e2d8 !important;
    border-radius: 8px !important;
    background: #f1f6f2 !important;
}

#delivery_schedule_info p {
    line-height: 1.45;
}

#method_description {
    width: 100%;
    margin-top: 7px !important;
    color: #7a847d;
    font-size: 12px;
}

#btn-delivery,
#btn-pickup {
    min-height: 43px;
    padding: 7px !important;
    gap: 3px !important;
    border: 1px solid #e0e2dd !important;
    border-radius: 8px !important;
    background: #ffffff !important;
    color: #515b53 !important;
}

#btn-delivery i,
#btn-pickup i {
    font-size: 17px !important;
}

#btn-delivery span,
#btn-pickup span {
    font-size: 12px !important;
}

#btn-delivery:hover,
#btn-pickup:hover {
    border-color: #9eb5a1 !important;
    background: #f7faf6 !important;
}

#btn-delivery.selected,
#btn-pickup.selected,
#btn-delivery.active,
#btn-pickup.active {
    border-color: var(--vdvc-green) !important;
    background: #eff5ef !important;
    color: var(--vdvc-green) !important;
}

.step-column:nth-child(2) hr {
    margin-top: 10px !important;
    margin-bottom: 9px !important;
    border-color: #eceee9 !important;
}

.step-column:nth-child(2) button[onclick="addItemToBundle()"] {
    width: 100%;
    min-height: 31px;
    flex-shrink: 0;
    margin-top: 2px;
    padding: 7px 10px !important;
    border: none !important;
    border-radius: 7px !important;
    background: #214f2c !important;
    color: #ffffff !important;
    font-size: 13px !important;
    font-weight: 600 !important;
}

.step-column:nth-child(2) button[onclick="addItemToBundle()"]:hover {
    background: #173b20 !important;
}

/* =========================================================
   STEP 3 - ITEMS TABLE AND SUMMARY
========================================================= */

.step-column:nth-child(3) .step-scroll {
    display: flex;
    flex-direction: column;
    gap: 15px !important;
}

.step-column:nth-child(3) .step-scroll > div:first-child h3 {
    display: none;
}

.step-column:nth-child(3) .border.border-gray-100.rounded-lg {
    border: 1px solid #eceee9 !important;
    border-radius: 8px !important;
    overflow: hidden;
}

.step-column:nth-child(3) table {
    width: 100%;
    table-layout: auto;
    border-collapse: collapse;
}

.step-column:nth-child(3) table thead {
    background: #f5f6f4 !important;
}

.step-column:nth-child(3) table th {
    padding: 9px 8px !important;
    color: #424a43 !important;
    font-size: 12px !important;
    font-weight: 700 !important;
    letter-spacing: 0.2px;
}

.step-column:nth-child(3) table td {
    padding: 9px 8px !important;
    color: #39413b;
    font-size: 12px !important;
}

#bundle_table_body tr td[colspan] {
    padding: 14px 5px !important;
    color: #747d76 !important;
    font-size: 12px !important;
}

.step-column:nth-child(3) .divide-y.divide-gray-100.bg-gray-50 {
    padding: 9px 10px !important;
    background: #fafbf9 !important;
    border-top: 1px solid #eff0ec !important;
}

.step-column:nth-child(3) .divide-y.divide-gray-100.bg-gray-50 > div {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 8px;
    padding: 3px 0;
    font-size: 12px !important;
}

#summary_method,
#summary_date,
#summary_address_display {
    max-width: 60%;
    text-align: right;
    overflow-wrap: anywhere;
}

.step-column:nth-child(3) .flex.justify-between.p-4.bg-slate-50 {
    padding: 10px 12px !important;
    background: #f4f6f3 !important;
    border-top: 1px solid #e5e8e2 !important;
}

.step-column:nth-child(3) .flex.justify-between.p-4.bg-slate-50 > span:first-child {
    color: #313a33 !important;
    font-size: 14px !important;
}

#total_display {
    color: var(--vdvc-green) !important;
    font-size: 19px !important;
}

/* =========================================================
   RESERVATION TIMELINE
========================================================= */

.timeline-progress {
    background: var(--vdvc-green) !important;
}

.step-column:nth-child(3) .step-scroll h3 {
    margin-bottom: 12px !important;
    color: #4b554d !important;
    font-size: 12px !important;
    font-weight: 700 !important;
    letter-spacing: 0.3px;
    text-transform: uppercase;
}

.step-column:nth-child(3) .relative.flex.items-center.justify-between {
    padding-right: 9px !important;
    padding-left: 9px !important;
}

.step-column:nth-child(3) .relative.flex.items-center.justify-between > div.absolute {
    height: 2px !important;
    background: #e1e4de !important;
}

.step-column:nth-child(3) #timeline_progress_bar {
    height: 2px !important;
    background: var(--vdvc-green) !important;
}

.step-column:nth-child(3) .relative.flex.items-center.justify-between > div.z-10 {
    gap: 1px;
}

.step-column:nth-child(3) .relative.flex.items-center.justify-between > div.z-10 > div {
    width: 15px !important;
    height: 15px !important;
    border-width: 3px !important;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08) !important;
}

.step-column:nth-child(3) .relative.flex.items-center.justify-between > div.z-10 > span {
    font-size: 11px !important;
    white-space: nowrap;
}

/* =========================================================
   SUBMIT AND CANCEL BUTTONS
========================================================= */

button[name="submit_reservation"] {
    min-height: 35px;
    padding: 9px 12px !important;
    border: none !important;
    border-radius: 9px !important;
    background: var(--vdvc-green) !important;
    color: #ffffff !important;
    box-shadow: 0 2px 4px rgba(33, 79, 44, 0.15) !important;
    font-size: 14px !important;
}

button[name="submit_reservation"]:hover {
    background: #173b20 !important;
}

button[onclick="openCancellationModal()"] {
    padding: 7px 4px !important;
    color: #5d655f !important;
    font-size: 13px !important;
}

button[onclick="openCancellationModal()"]:hover {
    color: #b33c35 !important;
}

/* =========================================================
   MY RESERVATIONS & STATUS
   Retain this entire section and its PHP functionality
========================================================= */

.reservation-card .mt-6 {
    width: calc(100% - 56px);
    max-width: 1244px;
    margin: 0 auto 30px !important;
    overflow: hidden;
    border: 1px solid #dedfd9 !important;
    border-radius: 13px !important;
    background: #ffffff !important;
    box-shadow: 0 3px 12px rgba(46, 54, 43, 0.045) !important;
}

.reservation-card .mt-6 > .px-5.py-4 {
    min-height: 65px;
    padding: 14px 18px !important;
    background: #ffffff;
}

.reservation-card .mt-6 h2 {
    margin: 0;
    color: #27332b !important;
    font-size: 16px !important;
    font-weight: 700;
}

.reservation-card .mt-6 .reservation-list {
    padding: 14px !important;
}

.reservation-card .reservation-list > div.border {
    padding: 14px !important;
    border-color: #e4e6e0 !important;
    border-radius: 9px !important;
}

.reservation-card .reservation-list > div.border:hover {
    box-shadow: 0 3px 10px rgba(0, 0, 0, 0.045);
}

.reservation-card .reservation-list .text-base {
    font-size: 13px !important;
    overflow-wrap: anywhere;
}

.reservation-card .reservation-list .text-xl {
    font-size: 18px !important;
}

.reservation-card .mt-6 > .px-5.py-4.border-t,
.reservation-card .mt-6 > .px-5.py-3 {
    padding: 12px 16px !important;
}

.reservation-card .mt-6 a,
.reservation-card .mt-6 button {
    transition: background 0.15s ease, color 0.15s ease;
}

/* =========================================================
   FOOTER
========================================================= */

.customer-footer {
    width: 100%;

    min-height: 68px;

    background: #0d4828;

    color: #ffffff;

    display: flex;
    align-items: center;

    padding: 0 48px;
}

.customer-footer-inner {
    width: 100%;

    max-width: 1740px;

    margin: 0 auto;

    display: flex;
    align-items: center;
    justify-content: space-between;
}

.customer-footer-left {
    display: flex;
    align-items: center;

    gap: 17px;
}

.customer-footer-logo {
    width: 110px;

    padding-right: 15px;

    border-right: 1px solid rgba(255,255,255,0.22);
}

.customer-footer-logo img {
    width: 100px;
    height: auto;

    display: block;
}

.customer-footer-tagline {
    font-size: 11px;

    color: rgba(255,255,255,0.9);
}

.customer-footer-right {
    display: flex;
    align-items: center;

    gap: 18px;
}

.customer-footer-social {
    display: flex;
    align-items: center;

    gap: 17px;
}

.customer-footer-social a {
    color: #ffffff;

    font-size: 13px;

    text-decoration: none;

    opacity: 0.95;

    transition: opacity 0.2s ease;
}

.customer-footer-social a:hover {
    opacity: 0.7;
}

.customer-footer-copy {
    font-size: 11px;

    color: rgba(255,255,255,0.82);
}

/* =========================================================
   MODALS
   Preserve existing modal functionality
========================================================= */

#success_modal,
#cancellation_modal{
    z-index: 2000;
}

#success_modal > div,
#cancellation_modal > div,
#logout_modal > div {
    max-width: 420px;
    border-radius: 14px;
}

/* =========================================================
   RESPONSIVE: SMALL DESKTOP / TABLET
========================================================= */

@media screen and (max-width: 1100px) {
    .customer-navbar {
        padding: 0 24px;
        gap: 12px;
    }

    .customer-navbar-logo {
        width: 145px;
    }

    .customer-navbar-logo img {
        width: 120px;
    }

    .customer-navbar-center {
        gap: 23px;
    }

    .customer-navbar-right {
        gap: 8px;
    }

    .customer-account-details {
        min-width: 78px;
    }

    .reservation-card > .p-6 {
        padding-right: 18px !important;
        padding-left: 18px !important;
    }

    .reservation-grid {
        gap: 13px !important;
    }

    .step-card {
        padding: 13px;
    }

    .step-card h2 {
        font-size: 12px !important;
    }

    .reservation-card .mt-6 {
        width: calc(100% - 36px);
    }
}

/* =========================================================
   RESPONSIVE: TABLET
========================================================= */

@media screen and (max-width: 850px) {
    .customer-navbar {
        height: auto;
        min-height: 68px;
        padding: 10px 18px;
        flex-wrap: wrap;
        row-gap: 7px;
    }

    .customer-navbar-logo {
        width: auto;
    }

    .customer-navbar-logo img {
        width: 112px;
    }

    .customer-navbar-right {
        margin-left: auto;
    }

    .customer-navbar-center {
        order: 3;
        width: 100%;
        height: 38px;
        gap: clamp(18px, 7vw, 45px);
        margin: 0;
        justify-content: center;
    }

    .customer-nav-link {
        font-size: 12px;
    }

    .customer-nav-link.active::after {
        bottom: 2px;
    }

    .reservation-hero {
        min-height: 132px;
        padding: 22px 28px;
    }

    .reservation-hero h1 {
        font-size: 30px;
    }

    .reservation-hero p {
        font-size: 13px;
    }

    .reservation-progress {
        width: calc(100% - 32px);
        padding: 12px 8px;
    }

    .reservation-progress-step {
        gap: 7px;
        font-size: 10px;
    }

    .reservation-progress-number {
        width: 27px;
        height: 27px;
        min-width: 27px;
    }

    .reservation-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
        gap: 14px !important;
    }

    .step-column:nth-child(3) {
        grid-column: 1 / -1;
    }

    .step-card {
        min-height: 0;
        padding: 16px;
    }

    .step-column:nth-child(3) .step-scroll {
        overflow: visible;
    }

    #map {
        height: 180px !important;
    }

    .customer-footer {
        padding: 14px 22px;
    }

    .customer-footer-inner {
        gap: 14px;
    }

    .customer-footer-right {
        gap: 12px;
    }

    .customer-footer-copy {
        white-space: normal;
        text-align: right;
    }
}

/* =========================================================
   RESPONSIVE: MOBILE
========================================================= */

@media screen and (max-width: 560px) {
    .customer-navbar {
        padding: 10px 13px 7px;
        gap: 9px;
    }

    .customer-navbar-logo img {
        width: 100px;
    }

    .customer-avatar {
        width: 35px;
        height: 35px;
        min-width: 35px;
        font-size: 14px;
    }

    .customer-account-details,
    .customer-dropdown-icon {
        display: none;
    }

    .customer-navbar-right {
        gap: 5px;
    }

    .customer-navbar-center {
        justify-content: space-between;
        gap: 13px;
        overflow-x: auto;
    }

    .customer-nav-link {
        flex-shrink: 0;
        font-size: 11px;
    }

    .customer-notification-dropdown {
        position: fixed;
        top: 112px;
        left: 12px;
        right: 12px;
        width: auto;
        max-width: none;
    }

    .reservation-hero {
        min-height: 125px;
        padding: 20px 17px;
        background-position: center, 62% center;
    }

    .reservation-hero h1 {
        max-width: 290px;
        font-size: 26px;
    }

    .reservation-hero p {
        max-width: 270px;
        font-size: 12px;
    }

    .reservation-progress {
        width: calc(100% - 22px);
        min-height: 65px;
        margin: 10px auto;
        padding: 9px 5px;
        border-radius: 11px;
    }

    .reservation-progress-step {
        flex-direction: column;
        gap: 4px;
        text-align: center;
        font-size: 9px;
    }

    .reservation-progress-number {
        width: 24px;
        height: 24px;
        min-width: 24px;
        font-size: 10px;
    }

    .reservation-progress-label {
        max-width: 95px;
    }

    .reservation-card > .p-6 {
        padding: 0 11px 20px !important;
    }

    .reservation-grid {
        grid-template-columns: minmax(0, 1fr) !important;
        gap: 12px !important;
    }

    .step-column,
    .step-column:nth-child(3) {
        grid-column: auto;
        width: 100%;
    }

    .step-card {
        padding: 15px;
        border-radius: 12px;
    }

    .step-card h2 {
        font-size: 14px !important;
    }

    .step-1-content {
        gap: 11px;
    }

    #map {
        height: 190px !important;
    }

    .step-column:nth-child(2) .grid.grid-cols-4 {
        gap: 7px !important;
    }

    .egg-btn {
        min-height: 57px;
    }

    .egg-btn > span:nth-child(2),
    .egg-btn > span:nth-child(3) {
        font-size: 9px !important;
    }

    .reservation-card .mt-6 {
        width: calc(100% - 22px);
        margin-bottom: 20px !important;
    }

    .reservation-card .mt-6 > .px-5.py-4 {
        padding: 13px !important;
        gap: 10px;
    }

    .reservation-card .mt-6 h2 {
        font-size: 14px !important;
    }

    .reservation-card .reservation-list {
        padding: 10px !important;
    }

    .customer-footer {
        padding: 16px;
    }

    .customer-footer-inner {
        flex-direction: column;
        align-items: flex-start;
        gap: 15px;
    }

    .customer-footer-left {
        width: 100%;
    }

    .customer-footer-logo {
        width: 100px;
    }

    .customer-footer-logo img {
        width: 85px;
    }

    .customer-footer-tagline {
        font-size: 10px;
    }

    .customer-footer-right {
        width: 100%;
        flex-wrap: wrap;
        justify-content: space-between;
        gap: 10px;
    }

    .customer-footer-copy {
        font-size: 8px;
        text-align: right;
    }
}

/* =========================================================
   ACCESSIBILITY AND HIDDEN ELEMENTS
========================================================= */

.hidden {
    display: none !important;
}

button:focus-visible,
a:focus-visible {
    outline: 2px solid #6f9876;
    outline-offset: 2px;
}

</style>
<body class="font-sans text-gray-700 antialiased min-h-screen">


<!-- =========================================================
     CUSTOMER NAVIGATION
========================================================= -->

<header class="customer-navbar">

    <div class="customer-navbar-logo">
        <a href="customer_dashboard.php">
            <img src="vdvclogoo.png" alt="VDVC Egg Farm">
        </a>
    </div>

    <nav class="customer-navbar-center">
        <a href="customer_dashboard.php" class="customer-nav-link">
            Home
        </a>

        <a href="customer_reservation.php"
           class="customer-nav-link active">
            Reservation
        </a>

        <a href="customer_history.php" class="customer-nav-link">
            History
        </a>

        <a href="customer_profile.php" class="customer-nav-link">
            Profile
        </a>
    </nav>

    <div class="customer-navbar-right">

        <div class="customer-notification-wrapper">
            <button type="button"
                    class="customer-top-notification"
                    title="Notifications"
                    onclick="event.stopPropagation(); toggleCustomerNotificationDropdown();">
                <i class="fa-regular fa-bell"></i>

                <?php if ($unread_notifications > 0): ?>
                    <span class="customer-notification-badge">
                        <?php
                        echo $unread_notifications > 99
                            ? '99+'
                            : $unread_notifications;
                        ?>
                    </span>
                <?php endif; ?>
            </button>

            <div class="customer-notification-dropdown hidden"
                 id="customerNotificationDropdown">

                <div class="customer-notification-dropdown-header">
                    <div class="customer-panel-title-group">
                        <div class="customer-panel-title-icon">
                            <i class="fa-solid fa-bell"></i>
                        </div>

                        <div class="customer-dashboard-panel-title">
                            Notifications
                        </div>
                    </div>

                    <a href="javascript:void(0)"
                       class="customer-mark-all-link"
                       onclick="markAllCustomerAsRead(event)">
                        Mark All as Read
                    </a>
                </div>

                <div class="customer-notification-list"
                     id="customerNotificationList">
                </div>
            </div>
        </div>

   
<!-- CUSTOMER ACCOUNT WITH DROPDOWN -->

<div class="customer-account-wrapper">

 <div class="customer-account-link">

        <div class="customer-avatar">
            <i class="fa-solid fa-user"></i>
        </div>

        <div class="customer-account-details">

            <span class="customer-top-name">
                <?php
                echo htmlspecialchars(
                    $customer_name,
                    ENT_QUOTES,
                    'UTF-8'
                );
                ?>
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

                </div>

    <!-- DROPDOWN ARROW -->

    <button
        type="button"
        class="customer-account-toggle"
        id="customerAccountToggle"
        aria-label="Open account menu"
        aria-expanded="false"
        aria-controls="customerAccountDropdown"
        onclick="toggleCustomerAccountDropdown(event)"
    >
        <i class="fa-solid fa-chevron-down"></i>
    </button>

    <!-- ACCOUNT DROPDOWN -->

    <div
        class="customer-account-dropdown hidden"
        id="customerAccountDropdown"
    >

        <button
            type="button"
            class="customer-logout-option"
            onclick="openCustomerLogoutModal()"
        >
            <i class="fa-solid fa-right-from-bracket"></i>
            <span>Logout</span>
        </button>

    </div>

</div>

    </div>
</header>


<div class="main-content">
      

        <!-- Main Workspace -->
        <div class="flex-1 flex flex-col min-w-0">
           

     <div class="w-full mx-auto">
    <form id="reservation_form" action="" method="POST" onsubmit="return validateForm()" autocomplete="off">

        <div class="reservation-card bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">

          
<!-- =====================================================
     CUSTOMER DASHBOARD TOPBAR
===================================================== -->

<!-- =========================================================
     RESERVATION HERO BANNER
========================================================= -->

<section class="reservation-hero">
    <div class="reservation-hero-content">
        <h1>
            <i class="fa-solid fa-leaf"></i>
            Reserve Fresh Eggs
        </h1>

        <p>
            Choose your preferred size and delivery method.<br>
            We'll take care of the rest!
        </p>
    </div>
</section>


<div class="reservation-progress">

    <div class="reservation-progress-step">
        <div class="reservation-progress-number">1</div>
        <div class="reservation-progress-label">
            Contact &amp; Delivery<br>Information
        </div>
    </div>

    <div class="reservation-progress-step">
        <div class="reservation-progress-number">2</div>
        <div class="reservation-progress-label">
            Select Egg Sizes<br>&amp; Quantity
        </div>
    </div>

    <div class="reservation-progress-step">
        <div class="reservation-progress-number">3</div>
        <div class="reservation-progress-label">
            Review &amp; Submit
        </div>
    </div>

</div>





            <!-- =====================================================
                 STEP 1 / STEP 2 / STEP 3
                 ===================================================== -->

            <div class="p-6">

                <div class="reservation-grid grid grid-cols-1 lg:grid-cols-3 gap-6 items-stretch">
                    
                    <!-- Step 1: Info -->
           <div class="step-column">

    <div class="step-card step-1-card">

        <h2 class="text-lg font-bold text-slate-800 mb-5">
            Step 1: Contact & Delivery Information
        </h2>
<!-- Reservation ID -->
<div class="rounded-lg px-4 py-3 mb-4" style="border: 1px solid #b8cbbd; background: #f1f6f2;">

    <div class="flex items-center justify-between">

        <div>
            <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-1">
                Reservation ID
            </label>

           <div class="text-lg font-extrabold tracking-wide" style="color: #214f2c;">
                <?= htmlspecialchars($reservation_code); ?>
            </div>

            <p class="text-[10px] text-gray-400 mt-1">
                Auto-generated
            </p>
        </div>

        <button
            type="button"
            onclick="copyReservationCode()"
           class="copy-reservation-btn w-8 h-8 rounded-lg text-gray-500 transition"
            title="Copy Reservation ID"
        >
            <i class="fa-regular fa-copy"></i>
        </button>

    </div>

</div>

<!-- Hidden Reservation Code -->
<input
    type="hidden"
    name="reservation_code"
    value="<?= htmlspecialchars($reservation_code); ?>"
>
        <div class="step-1-content space-y-4">
                            <div>
                                <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-1">Customer Name</label>
                                <div class="relative">
                                    <input type="text" id="cust_name_input" name="customer_name" placeholder="Enter full name" value="<?= htmlspecialchars($customer_name); ?>" required class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-blue-500">
                                    <i class="fa-solid fa-pen absolute right-3 top-3 text-gray-400 text-xs"></i>
                                </div>
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-1">Contact Number</label>
                               <input
    type="text"
    id="contact_input"
    name="contact_number"
    placeholder="09XXXXXXXXX"
    maxlength="11"
    minlength="11"
    inputmode="numeric"
    pattern="[0-9]{11}"
    required
    oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, 11)"
    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-blue-500"
>
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-1">Delivery Address</label>
                                <input type="text" id="delivery_address_input" name="delivery_address" placeholder="Type location..." required oninput="handleAddressTyping(this.value); updateReservationProgress();" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-blue-500 mb-2">
                                <div class="border border-gray-200 rounded-lg overflow-hidden relative h-48 bg-sky-50 z-10" id="map"></div>
                            </div>
                        </div>
                    </div>
</div>
                    <!-- Step 2: Bundle Items Configurator -->
                 <div class="step-column">

    <div class="step-card">

        <h2 class="text-lg font-bold text-slate-800 mb-5">
            Step 2: Add Items
        </h2>

        <div class="step-scroll space-y-4">
                            <div>
                                <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-2">Egg Size Selection</label>
                                <div class="grid grid-cols-4 gap-2 mb-3">
                                    <?php foreach($prices as $size => $price): ?>
                                        <button type="button" data-egg="<?= $size ?>" onclick="setEgg('<?= $size ?>')" class="egg-btn flex flex-col items-center p-2 border rounded-lg transition-all border-gray-200 hover:bg-gray-50">
                                            <span class="w-5 h-7 bg-slate-100 border border-slate-400 rounded-full inline-block shadow-inner mb-1"></span>
                                            <span class="text-[9px] text-gray-600 font-medium text-center leading-none mb-1"><?= $size ?></span>
                                            <span class="text-[9px] font-bold" style="color: #214f2c;">₱<?= $price ?></span>
                                        </button>
                                    <?php endforeach; ?>
                                </div>
                              <input
    type="text"
    id="egg_type_select"
    placeholder="-- Choose Size --"
    readonly
    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm bg-gray-50 text-gray-700 focus:outline-none cursor-default"
>
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-1">Quantity</label>
                                <input type="number" id="quantity_input" min="1" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-blue-500">
                            </div>
<!-- =====================================================
     PICKUP DATE
     Only visible when Pickup is selected
     ===================================================== -->

<div id="pickup_date_section" class="hidden">

    <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-1">
        Preferred Pickup Date
    </label>

    <input
        type="date"
        id="reservation_date_input"
        name="reservation_date"
        min="<?= $today ?>"
        onchange="updateLiveSummary()"
        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-blue-500"
    >

    <p class="text-[10px] text-gray-400 mt-1">
        Select the date when you prefer to pick up your order at the farm.
    </p>

</div>

<!-- =====================================================
     DELIVERY INFORMATION
     Only visible when Delivery is selected
     ===================================================== -->

<div
    id="delivery_schedule_info"
    class="hidden rounded-lg p-3"
    style="border: 1px solid #d5e2d8; background: #f1f6f2;"
>

    <div class="flex items-start gap-2">

       <i class="fa-solid fa-truck mt-0.5" style="color: #214f2c;"></i>

        <div>

           <p class="text-xs font-bold" style="color: #214f2c;">
                Delivery Schedule
            </p>

          <p class="text-[10px] mt-1 leading-relaxed" style="color: #214f2c;">
                No date is required at this time.
                The farm manager will assign the delivery date
                based on availability.
            </p>

        </div>

    </div>

</div>
                            <div>

    <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-2">
        Delivery Method
    </label>

  <input
    type="hidden"
    name="delivery_method"
    id="delivery_method_input"
>

    <div class="grid grid-cols-2 gap-4">

        <!-- DELIVERY -->
        <button
            type="button"
            id="btn-delivery"
            onclick="setMethod('Delivery')"
            class="p-3 border rounded-lg flex flex-col items-center justify-center space-y-1 transition-all border-gray-200 text-gray-500"
        >
            <i class="fa-solid fa-truck text-lg"></i>
            <span class="text-xs font-semibold">Delivery</span>
        </button>


        <!-- PICKUP -->
        <button
            type="button"
            id="btn-pickup"
            onclick="setMethod('Pickup')"
            class="p-3 border rounded-lg flex flex-col items-center justify-center space-y-1 transition-all border-gray-200 text-gray-500"
        >
            <i class="fa-solid fa-shop text-lg"></i>
            <span class="text-xs font-semibold">Pickup</span>
        </button>

    </div>


    <!-- INFORMATION BASED ON METHOD -->

    <div id="method_description" class="mt-3">

        <div class="text-[10px] text-gray-400 text-center">
            Please select Delivery or Pickup.
        </div>

    </div>


    <hr class="border-gray-100 my-3">


    <button
        type="button"
        onclick="addItemToBundle()"
        class="w-full bg-slate-800 hover:bg-slate-900 text-white font-medium py-2 px-4 rounded-lg text-xs transition flex items-center justify-center space-x-2"
    >
        <i class="fa-solid fa-plus"></i>
        <span>Add To Cart</span>
    </button>

</div>


                        </div>
                    </div>
</div>
                    <!-- Step 3: Bundle Checkout Review -->
                  <div class="step-column">

    <div class="step-card">

        <h2 class="text-lg font-bold text-slate-800 mb-5">
            Step 3: Review & Submit
        </h2>

        <div class="step-scroll space-y-6">
                            <div>
                                <h3 class="text-sm font-bold text-slate-700 mb-3"> Items List</h3>
                                <!-- Container for Hidden Form Array Inputs -->
                                <div id="hidden_bundle_inputs"></div>
                                
                                <div class="border border-gray-100 rounded-lg overflow-hidden text-sm">
                                    <table class="w-full text-left border-collapse">
                                        <thead class="bg-gray-50 text-xs text-gray-500 uppercase tracking-wider">
                                            <tr>
                                                <th class="p-3">Item</th>
                                                <th class="p-3 text-center">Qty</th>
                                                <th class="p-3 text-right">Total</th>
                                                <th class="p-3 text-center"></th>
                                            </tr>
                                        </thead>
                                        <tbody id="bundle_table_body" class="divide-y divide-gray-100">
                                            <tr>
                                                <td colspan="4" class="p-4 text-center text-gray-400 text-xs">No items added yet.</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                    
                                    <div class="divide-y divide-gray-100 bg-gray-50 p-3 text-xs space-y-2 border-t border-gray-100">
                                        <div class="flex justify-between"><span class="text-gray-500">Method:</span><span id="summary_method" class="font-semibold text-gray-700">None selected</span></div>
                                        <div class="flex justify-between"><span class="text-gray-500">Date:</span><span id="summary_date" class="font-semibold" style="color: #214f2c;">None selected</span></div>
                                        <div class="flex justify-between"><span class="text-gray-500">Address:</span><span id="summary_address_display" class="font-semibold text-gray-700 max-w-[180px] truncate text-right">None</span></div>
                                    </div>
                                    <div class="flex justify-between p-4 bg-slate-50 items-center border-t border-gray-200">
                                        <span class="font-bold text-slate-800">Grand Total:</span>
                                       <span class="text-xl font-extrabold" style="color: #214f2c;">₱<span id="total_display">0</span></span>
                                    </div>
                                </div>
                            </div>

                            <div>
                                <h3 class="text-xs font-bold text-gray-500 uppercase tracking-wider mb-4">Reservation Timeline</h3>
                                <div class="relative flex items-center justify-between px-4">
                                    <div class="absolute left-4 right-4 h-1 bg-gray-200 top-1/2 -translate-y-1/2 z-0"></div>
                                    <div id="timeline_progress_bar" class="timeline-progress absolute left-4 h-1 top-1/2 -translate-y-1/2 z-0 transition-all duration-500 <?= $is_submitted ? 'w-full' : 'w-0'; ?>"></div>
                                    <div class="z-10 flex flex-col items-center">
                                      <div class="w-5 h-5 rounded-full border-4 border-white shadow" style="background: #214f2c;"></div>
                                       <span class="text-xs font-semibold mt-1" style="color: #214f2c;">Draft</span>
                                    </div>
                                    <div class="z-10 flex flex-col items-center">
                                        <div class="w-5 h-5 rounded-full border-4 border-white shadow bg-gray-300 <?= $is_submitted ? 'style="background: #214f2c;"' : 'bg-gray-300'; ?>"></div>
                                        <span class="text-xs mt-1 <?= $is_submitted ? 'style="color: #214f2c;" font-semibold' : 'text-gray-400 font-medium'; ?>">Processing</span>
                                    </div>
                                    <div class="z-10 flex flex-col items-center">
                                        <div class="w-5 h-5 rounded-full border-4 border-white shadow bg-gray-300 <?= $is_submitted ? 'style="background: #214f2c;"' : 'bg-gray-300'; ?>"></div>
                                        <span class="text-xs mt-1 <?= $is_submitted ? 'style="color: #214f2c;" font-semibold' : 'text-gray-400 font-medium'; ?>">Submitted</span>
                                    </div>
                                </div>
                            </div>

                            <div class="space-y-3 pt-2">
                                <button
    type="submit"
    name="submit_reservation"
    class="w-full text-white font-medium py-3 px-4 rounded-xl shadow-md transition flex items-center justify-center space-x-2"
    style="background-color: #214f2c;"
>
                                    <i class="fa-regular fa-circle-check"></i><span>Submit Reservation</span>
                                </button>
                                <button type="button" onclick="openCancellationModal()" class="w-full text-center text-sm text-gray-500 hover:text-red-500 transition font-medium py-1 block">
                                    <i class="fa-solid fa-xmark mr-1"></i> Cancel pending reservation
                                </button>
                            </div>
                        </div>
                    </div>
                                    </div>
                                    </div>
                                    </div>
                                     </div>
                           <!-- =====================================================
     MY RESERVATIONS & STATUS
     ===================================================== -->

<div class="mt-6 border border-gray-200 rounded-xl overflow-hidden bg-white">

    <!-- Section Header -->
    <div class="px-5 py-4 border-b border-gray-200 flex items-center justify-between">

        <div>
            <h2 class="text-lg font-bold text-slate-800">
                My Reservations & Status
            </h2>

            <p class="text-xs text-gray-500 mt-1">
                View and track the status of your reservations in real-time.
            </p>
        </div>

        <div class="border border-gray-200 rounded-lg px-3 py-2 text-xs font-semibold text-slate-700">
            All Reservations
        </div>

    </div>


    <!-- Reservation List -->

  <div class="reservation-list p-4 space-y-3">

        <?php if (!empty($my_reservations)): ?>

      <?php foreach ($paginated_reservations as $reservation): ?>

                <?php

                $status = $reservation['status'];

                if ($status === 'Confirmed') {

                    $status_class = 'bg-green-100 text-green-700';
                    $status_icon = 'fa-circle-check';
                    $status_title = 'Confirmed';
                    $status_message = 'Your reservation has been confirmed by the manager.';

                } elseif ($status === 'Rejected') {

                    $status_class = 'bg-red-100 text-red-700';
                    $status_icon = 'fa-circle-xmark';
                    $status_title = 'Rejected';
                    $status_message = 'Your reservation was rejected by the manager.';

                } else {

                    $status_class = 'bg-amber-100 text-amber-700';
                    $status_icon = 'fa-clock';
                    $status_title = 'Pending Confirmation';
                    $status_message = 'Waiting for manager review.';

                }

                ?>

                <div class="border border-gray-200 rounded-xl p-4 hover:shadow-sm transition">

                    <div class="grid grid-cols-1 lg:grid-cols-4 gap-4 items-center">

                        <!-- Reservation Information -->
                        <div class="lg:border-r lg:border-gray-200 lg:pr-4">

                            <div class="flex items-center gap-2 mb-2">

                              <span class="text-base font-extrabold" style="color: #214f2c;">
                                    <?= htmlspecialchars($reservation['reservation_code']); ?>
                                </span>

                            </div>

                            <div class="space-y-1 text-xs text-gray-600">

                              <div>

    <?php if ($reservation['delivery_method'] === 'Pickup'): ?>

        <i class="fa-solid fa-shop mr-2 text-gray-400"></i>

        <span class="font-medium">
            Pickup Date:
        </span>

        <?php if (!empty($reservation['reservation_date'])): ?>

            <?= date(
                'M d, Y (D)',
                strtotime($reservation['reservation_date'])
            ); ?>

        <?php else: ?>

            <span class="text-gray-400">
                Not selected
            </span>

        <?php endif; ?>


    <?php elseif ($reservation['delivery_method'] === 'Delivery'): ?>

        <i class="fa-solid fa-truck mr-2 text-gray-400"></i>

        <span class="font-medium">
            Delivery Date:
        </span>

        <?php if (!empty($reservation['reservation_date'])): ?>

            <?= date(
                'M d, Y (D)',
                strtotime($reservation['reservation_date'])
            ); ?>

        <?php else: ?>

            <span class="text-amber-600 font-medium">
                To be scheduled by farm
            </span>

        <?php endif; ?>

    <?php endif; ?>


    <div class="text-xs text-gray-500 mt-1">

        <i class="fa-regular fa-clock mr-1"></i>

        Reserved On:

        <?= date(
            'M d, Y h:i A',
            strtotime($reservation['reserved_at'])
        ); ?>

    </div>

</div>

                                <div>
                                    <?php if ($reservation['delivery_method'] === 'Delivery'): ?>

                                        <i class="fa-solid fa-truck mr-2 text-gray-400"></i>

                                    <?php else: ?>

                                        <i class="fa-solid fa-shop mr-2 text-gray-400"></i>

                                    <?php endif; ?>

                                    <?= htmlspecialchars($reservation['delivery_method']); ?>
                                </div>

                            </div>

                        </div>


                        <!-- Items Ordered -->
                        <div class="lg:border-r lg:border-gray-200 lg:pr-4">

                            <p class="text-[10px] font-bold uppercase tracking-wider text-gray-500 mb-2">
                                Items Ordered
                            </p>

                            <div class="flex flex-wrap gap-2">

                                <?php foreach ($reservation['items'] as $item): ?>

                                 <span class="px-2.5 py-1 rounded-md text-xs font-semibold"
      style="background: #f1f6f2; color: #214f2c;">

                                        <?= htmlspecialchars($item['egg_type']); ?>
                                        x <?= intval($item['quantity']); ?>

                                    </span>

                                <?php endforeach; ?>

                            </div>

                            <p class="text-xs text-gray-500 mt-3">

                                Total Quantity:
                                <strong>
                                    <?= intval($reservation['total_quantity']); ?>
                                </strong>
                                tray(s)

                            </p>

                        </div>


                        <!-- Total Price -->
                        <div class="lg:border-r lg:border-gray-200 lg:pr-4">

                            <p class="text-[10px] font-bold uppercase tracking-wider text-gray-500 mb-2">
                                Total Price
                            </p>

            <p class="text-xl font-extrabold" style="color: #214f2c;">

                                ₱<?= number_format(
                                    $reservation['total_price'],
                                    2
                                ); ?>

                            </p>

                        </div>


                        <!-- Manager Confirmation -->
                        <div>

                            <p class="text-[10px] font-bold uppercase tracking-wider text-gray-500 mb-2">
                                Manager Confirmation
                            </p>

                            <div class="flex items-start gap-2">

                                <i class="fa-solid <?= $status_icon; ?> mt-1 <?= 
                                    $status === 'Confirmed'
                                    ? 'text-green-500'
                                    : ($status === 'Rejected'
                                        ? 'text-red-500'
                                        : 'text-amber-500');
                                ?>"></i>

                                <div>

                                    <div class="font-bold text-sm
                                        <?= $status === 'Confirmed'
                                            ? 'text-green-600'
                                            : ($status === 'Rejected'
                                                ? 'text-red-600'
                                                : 'text-amber-600');
                                        ?>">

                                        <?= htmlspecialchars($status_title); ?>

                                    </div>

                                    <p class="text-xs text-gray-500 mt-1">
                                        <?= htmlspecialchars($status_message); ?>
                                    </p>

                                </div>

                            </div>

                        </div>

                    </div>

                </div>

            <?php endforeach; ?>

        <?php else: ?>

            <!-- No Reservations -->
            <div class="py-10 text-center text-gray-400">

                <i class="fa-regular fa-calendar-xmark text-3xl mb-3"></i>

                <p class="text-sm font-medium">
                    No reservations yet.
                </p>

                <p class="text-xs mt-1">
                    Your submitted reservations will appear here.
                </p>

            </div>

        <?php endif; ?>

       </div>


    <!-- =====================================================
         PAGINATION
         ===================================================== -->

    <?php if ($total_pages > 1): ?>

        <div
            class="px-5 py-4 border-t border-gray-200 flex items-center justify-between"
            style="background: #ffffff;"
        >

            <!-- Previous -->
            <div>

                <?php if ($current_page > 1): ?>

                    <a
                        href="?page=<?= $current_page - 1; ?>"
                        class="inline-flex items-center gap-2 px-3 py-2 rounded-lg border border-gray-200 text-xs font-semibold text-gray-600 hover:bg-gray-50 transition"
                    >
                        <i class="fa-solid fa-chevron-left text-[10px]"></i>
                        Previous
                    </a>

                <?php else: ?>

                    <span
                        class="inline-flex items-center gap-2 px-3 py-2 rounded-lg border border-gray-100 text-xs font-semibold text-gray-300 cursor-not-allowed"
                    >
                        <i class="fa-solid fa-chevron-left text-[10px]"></i>
                        Previous
                    </span>

                <?php endif; ?>

            </div>


            <!-- Page Numbers -->
            <div class="flex items-center gap-1">

                <?php for ($page = 1; $page <= $total_pages; $page++): ?>

                    <?php if ($page == $current_page): ?>

                        <span
                            class="w-8 h-8 flex items-center justify-center rounded-lg text-xs font-bold text-white"
                            style="background: #214f2c;"
                        >
                            <?= $page; ?>
                        </span>

                    <?php else: ?>

                        <a
                            href="?page=<?= $page; ?>"
                            class="w-8 h-8 flex items-center justify-center rounded-lg text-xs font-semibold text-gray-600 border border-gray-200 hover:bg-gray-50 transition"
                        >
                            <?= $page; ?>
                        </a>

                    <?php endif; ?>

                <?php endfor; ?>

            </div>


            <!-- Next -->
            <div>

                <?php if ($current_page < $total_pages): ?>

                    <a
                        href="?page=<?= $current_page + 1; ?>"
                        class="inline-flex items-center gap-2 px-3 py-2 rounded-lg border border-gray-200 text-xs font-semibold text-gray-600 hover:bg-gray-50 transition"
                    >
                        Next
                        <i class="fa-solid fa-chevron-right text-[10px]"></i>
                    </a>

                <?php else: ?>

                    <span
                        class="inline-flex items-center gap-2 px-3 py-2 rounded-lg border border-gray-100 text-xs font-semibold text-gray-300 cursor-not-allowed"
                    >
                        Next
                        <i class="fa-solid fa-chevron-right text-[10px]"></i>
                    </span>

                <?php endif; ?>

            </div>

        </div>

    <?php endif; ?>


    <!-- Information Footer -->

    <?php if (!empty($my_reservations)): ?>

       <div class="px-5 py-3 text-xs"
     style="background: #f1f6f2; border-top: 1px solid #d5e2d8; color: #214f2c;">

            <i class="fa-solid fa-circle-info mr-2"></i>

            You will receive a notification once your reservation status is updated by the manager.

        </div>

    <?php endif; ?>

</div>         
                </form>
            </div>
        </div>
   </div>

    <!-- Modals -->
    <div id="success_modal" class="hidden fixed inset-0 bg-slate-900/50 backdrop-blur-sm flex items-center justify-center z-[100] p-4">
        <div class="bg-white rounded-xl shadow-2xl border border-gray-100 max-w-md w-full p-6 text-center space-y-4">
            <div class="w-16 h-16 bg-green-100 text-green-600 rounded-full flex items-center justify-center mx-auto text-3xl"><i class="fa-solid fa-circle-check"></i></div>
            <div><h3 class="text-xl font-bold text-slate-800">Success!</h3><p class="text-sm text-gray-500"><?= htmlspecialchars($message); ?></p></div>
            <button type="button" onclick="closeSuccessModal()" class="w-full bg-green-500 text-white font-semibold py-2 rounded-lg text-sm transition">Great, Thank You!</button>
        </div>
    </div>

    <div id="cancellation_modal" class="hidden fixed inset-0 bg-slate-900/50 backdrop-blur-sm flex items-center justify-center z-[100] p-4">
        <div class="bg-white rounded-xl shadow-2xl border border-gray-100 max-w-md w-full p-6 space-y-4">
            <div class="flex items-center space-x-3 text-amber-500"><i class="fa-solid fa-triangle-exclamation text-2xl"></i><h3 class="text-lg font-bold text-slate-800">Cancel Reservation?</h3></div>
            <p class="text-sm text-gray-500">Are you sure you want to cancel? All bundle items will be cleared.</p>
            <div class="flex justify-end space-x-3"><button type="button" onclick="closeCancellationModal()" class="bg-gray-100 px-4 py-2 rounded-lg text-sm">Keep Editing</button><button type="button" onclick="executeCancellation()" class="bg-red-500 text-white px-4 py-2 rounded-lg text-sm">Yes, Cancel</button></div>
        </div>
    </div>


<!-- LOGOUT CONFIRMATION MODAL -->

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
    <input type="hidden" name="customer_logout" value="1">

    <input
        type="hidden"
        name="logout_token"
        value="<?php
            echo isset($_SESSION['customer_logout_token'])
                ? htmlspecialchars($_SESSION['customer_logout_token'], ENT_QUOTES, 'UTF-8')
                : '';
        ?>"
    >

    <button type="submit" class="customer-logout-confirm">
        Yes, Logout
    </button>
</form>


        </div>

    </div>
</div>


<!-- =========================================================
     FOOTER
========================================================= -->

<footer class="customer-footer">


    <div class="customer-footer-inner">


        <!-- LEFT -->

        <div class="customer-footer-left">


            <div class="customer-footer-logo">

                <img
                    src="vdvclogoo.png"
                    alt="VDVC Egg Farm"
                >

            </div>


            <div class="customer-footer-tagline">

                Fresh Eggs. Trusted Farm. Better for You.

            </div>


        </div>



        <!-- RIGHT -->

        <div class="customer-footer-right">


            <div class="customer-footer-social">

                <a
                    href="#"
                    aria-label="Facebook"
                >
                    <i class="fa-brands fa-facebook-f"></i>
                </a>


                <a
                    href="#"
                    aria-label="Instagram"
                >
                    <i class="fa-brands fa-instagram"></i>
                </a>


                <a
                    href="#"
                    aria-label="YouTube"
                >
                    <i class="fa-brands fa-youtube"></i>
                </a>

            </div>


            <div class="customer-footer-copy">

                © 2026 VDVC Egg Farm.
                All rights reserved.

            </div>


        </div>


    </div>

</footer>

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
   CUSTOMER ACCOUNT DROPDOWN
========================================================= */

function toggleCustomerAccountDropdown(event) {
    event.stopPropagation();

    var dropdown = document.getElementById(
        'customerAccountDropdown'
    );

    var toggle = document.getElementById(
        'customerAccountToggle'
    );

    if (!dropdown || !toggle) {
        return;
    }

    var isOpen = !dropdown.classList.contains('hidden');

    if (isOpen) {
        dropdown.classList.add('hidden');
        toggle.setAttribute('aria-expanded', 'false');
    } else {
        dropdown.classList.remove('hidden');
        toggle.setAttribute('aria-expanded', 'true');
    }
}

function closeCustomerAccountDropdown() {
    var dropdown = document.getElementById(
        'customerAccountDropdown'
    );

    var toggle = document.getElementById(
        'customerAccountToggle'
    );

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

    var modal = document.getElementById(
        'customerLogoutModal'
    );

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
    var modal = document.getElementById(
        'customerLogoutModal'
    );

    if (modal) {
        modal.classList.add('hidden');

        var toggle = document.getElementById(
            'customerAccountToggle'
        );

        if (toggle) {
            toggle.focus();
        }
    }
}

/* =========================================================
   CLOSE ACCOUNT DROPDOWN WHEN CLICKING OUTSIDE
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

    var modal = document.getElementById(
        'customerLogoutModal'
    );

    if (modal && event.target === modal) {
        closeCustomerLogoutModal();
    }
});

/* =========================================================
   CLOSE DROPDOWN AND MODAL WITH ESCAPE
========================================================= */

document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        closeCustomerLogoutModal();
        closeCustomerAccountDropdown();
    }
});



       
const priceList = {
    'Extra Small': 140,
    'Small': 150,
    'Medium': 175,
    'Large': 195,
    'Extra Large': 235,
    'Jumbo': 255,
    'Super Jumbo': 270,
    'Double Yolk': 320
};

let bundleItems = [];
let addressLookupTimeout = null;

/* Tracks whether a location was selected or successfully located on the map */
let mapLocationSelected = false;


/* =========================================================
   RESERVATION PROGRESS STATUS
========================================================= */

function setReservationStepCompleted(stepNumber, isComplete) {

    var steps = document.querySelectorAll(
        '.reservation-progress-step'
    );

    var step = steps[stepNumber - 1];

    if (!step) {
        return;
    }

    if (isComplete) {
        step.classList.add('completed');
    } else {
        step.classList.remove('completed');
    }
}




function updateReservationProgress() {
    var name = document.getElementById('cust_name_input').value.trim();
    var contact = document.getElementById('contact_input').value.trim();
    var address = document.getElementById('delivery_address_input').value.trim();
    var method = document.getElementById('delivery_method_input').value.trim().toLowerCase();
    var pickupDate = document.getElementById('reservation_date_input').value;

    var validName = name.length > 0;
    var validContact = /^09[0-9]{9}$/.test(contact);
    var validAddress = address.length > 0;
    var validMethod = method === 'delivery' || method === 'pickup';
    var validPickupDate = method !== 'pickup' || pickupDate !== '';

    var step1Complete =
        validName &&
        validContact &&
        validAddress &&
        validMethod &&
        validPickupDate;

    var step2Complete = bundleItems.length > 0;
    var step3Complete = step1Complete && step2Complete;

    setReservationStepCompleted(1, step1Complete);
    setReservationStepCompleted(2, step2Complete);
    setReservationStepCompleted(3, step3Complete);
}




        // Leaflet Setup
        const map = L.map('map').setView([14.5995, 120.9842], 13);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(map);
        const marker = L.marker([14.5995, 120.9842], { draggable: true }).addTo(map);

    
function reverseGeocode(lat, lng) {
    fetch(
        'https://nominatim.openstreetmap.org/reverse?format=json&lat=' +
        encodeURIComponent(lat) +
        '&lon=' +
        encodeURIComponent(lng)
    )
    .then(function(response) {
        if (!response.ok) {
            throw new Error('Unable to find the address.');
        }

        return response.json();
    })
    .then(function(data) {
        if (data && data.display_name) {
            document.getElementById('delivery_address_input').value =
                data.display_name;

            document.getElementById('summary_address_display').innerText =
                data.display_name;

            mapLocationSelected = true;

            updateReservationProgress();
        } else {
            mapLocationSelected = false;
            updateReservationProgress();

            alert('Unable to identify this location. Please select another point on the map.');
        }
    })
    .catch(function(error) {
        mapLocationSelected = false;
        updateReservationProgress();

        console.error('Reverse geocoding error:', error);
        alert('Unable to get the address. Please try selecting the location again.');
    });
}


// Kapag nag-click ang user sa map
map.on('click', function(e) {
    marker.setLatLng(e.latlng);

    mapLocationSelected = false;
    updateReservationProgress();

    reverseGeocode(e.latlng.lat, e.latlng.lng);
});


// Kapag ginalaw ng user ang marker
marker.on('dragend', function() {
    var position = marker.getLatLng();

    mapLocationSelected = false;
    updateReservationProgress();

    reverseGeocode(position.lat, position.lng);
});


// Kapag nagta-type sa address field
function handleAddressTyping(val) {
    var addressInput = document.getElementById('delivery_address_input');
    var summaryAddress = document.getElementById('summary_address_display');
    var address = val.trim();

    summaryAddress.innerText = address || 'None';

    // Ang pag-type lamang ay hindi pa confirmed map location.
    mapLocationSelected = false;
    updateReservationProgress();

    clearTimeout(addressLookupTimeout);

    // Huwag maghanap ng location kung isang letra o maikling text pa lang.
    if (address.length < 8) {
        return;
    }

    addressLookupTimeout = setTimeout(function() {
        fetch(
            'https://nominatim.openstreetmap.org/search?format=json&q=' +
            encodeURIComponent(address) +
            '&limit=1'
        )
        .then(function(response) {
            if (!response.ok) {
                throw new Error('Unable to search for the address.');
            }

            return response.json();
        })
        .then(function(data) {
            // Huwag gumamit ng resulta kung binago na ulit ang address.
            if (addressInput.value.trim() !== address) {
                return;
            }

            if (data && data.length > 0) {
                var lat = parseFloat(data[0].lat);
                var lng = parseFloat(data[0].lon);

                if (isNaN(lat) || isNaN(lng)) {
                    return;
                }

                // Ipakita ang nahanap na lokasyon sa map.
                // Hindi pa ito confirmed hangga't hindi nagki-click
                // o nagda-drag ang user sa map.
                map.setView([lat, lng], 16);
                marker.setLatLng([lat, lng]);
                map.invalidateSize();
            }
        })
        .catch(function(error) {
            console.error('Address search error:', error);
        });
    }, 900);
}



        // Bundle Logic Operations
        function setEgg(val) {
            document.getElementById('egg_type_select').value = val;
            document.querySelectorAll('.egg-btn').forEach(btn => {
                btn.className = btn.getAttribute('data-egg') === val 
                   ? "egg-btn flex flex-col items-center p-2 border rounded-lg transition-all"
                    : "egg-btn flex flex-col items-center p-2 border rounded-lg transition-all border-gray-200 hover:bg-gray-50";
            });
        }

     
function addItemToBundle() {

    const size = document.getElementById(
        'egg_type_select'
    ).value;

    const qty = parseInt(
        document.getElementById('quantity_input').value,
        10
    );

    if (!size || isNaN(qty) || qty <= 0) {

        alert('Please select a valid size and quantity.');

        return;
    }

    const existingItem = bundleItems.find(
        function(item) {
            return item.size === size;
        }
    );

    if (existingItem) {

        existingItem.qty += qty;

    } else {

        bundleItems.push({
            size: size,
            qty: qty,
            price: priceList[size]
        });
    }

    /*
       Do not clear the quantity, egg size,
       customer information, address, or cart.
    */

    updateLiveSummary();

    updateReservationProgress();
}


        function removeBundleItem(index) {
            bundleItems.splice(index, 1);
            updateLiveSummary();
                updateReservationProgress();
        }

        function updateLiveSummary() {
            const tbody = document.getElementById('bundle_table_body');
            const hiddenInputs = document.getElementById('hidden_bundle_inputs');
            tbody.innerHTML = '';
            hiddenInputs.innerHTML = '';

            let grandTotal = 0;

            if (bundleItems.length === 0) {
                tbody.innerHTML = `<tr><td colspan="4" class="p-4 text-center text-gray-400 text-xs">No items added to the bundle yet.</td></tr>`;
            } else {
                bundleItems.forEach((item, index) => {
                    const rowTotal = item.price * item.qty;
                    grandTotal += rowTotal;

                    tbody.innerHTML += `
                        <tr>
                            <td class="p-3 font-medium text-gray-700">${item.size}</td>
                            <td class="p-3 text-center font-bold text-gray-600">${item.qty}</td>
                            <td class="p-3 text-right font-semibold text-gray-800">₱${rowTotal.toLocaleString()}</td>
                            <td class="p-3 text-center">
                                <button type="button" onclick="removeBundleItem(${index})" class="text-red-400 hover:text-red-600"><i class="fa-solid fa-trash-can"></i></button>
                            </td>
                        </tr>`;

                    hiddenInputs.innerHTML += `
                        <input type="hidden" name="egg_types[]" value="${item.size}">
                        <input type="hidden" name="quantities[]" value="${item.qty}">`;
                });
            }

           document.getElementById('total_display').innerText =
    grandTotal.toLocaleString();


const method =
    document.getElementById('delivery_method_input').value;

const date =
    document.getElementById('reservation_date_input').value;


if (method === 'Pickup') {

    document.getElementById('summary_date').innerText =
        date || 'Select pickup date';

}
else if (method === 'Delivery') {

    document.getElementById('summary_date').innerText =
        'To be scheduled by farm';

}
else {

    document.getElementById('summary_date').innerText =
        'None selected';

}
    updateReservationProgress();
        }

        function setMethod(method) {

    const methodInput = document.getElementById('delivery_method_input');
    const pickupSection = document.getElementById('pickup_date_section');
    const deliveryInfo = document.getElementById('delivery_schedule_info');
    const dateInput = document.getElementById('reservation_date_input');

    const summaryMethod = document.getElementById('summary_method');
    const summaryDate = document.getElementById('summary_date');

    const btnDelivery = document.getElementById('btn-delivery');
    const btnPickup = document.getElementById('btn-pickup');

    const methodDescription = document.getElementById('method_description');


    // Save selected method
    methodInput.value = method;


    // =====================================================
    // DELIVERY
    // =====================================================

    if (method === 'Delivery') {

        // Hide pickup date
        pickupSection.classList.add('hidden');

        // Show delivery information
        deliveryInfo.classList.remove('hidden');

        // Date is NOT required
        dateInput.required = false;

        // Clear date
        dateInput.value = '';

        // Update summary
        summaryMethod.innerText = 'Delivery';
        summaryDate.innerText = 'To be scheduled by farm';

        // Description
        methodDescription.innerHTML = `
           <div class="border border-blue-100 bg-blue-50 rounded-lg p-3"
     style="border-color: #d5e2d8; background-color: #f1f6f2;">
    <div class="flex items-start gap-2">
        <i class="fa-solid fa-truck mt-0.5" style="color: #214f2c;"></i>

        <div>
            <p class="text-xs font-bold" style="color: #214f2c;">
                Delivery Selected
            </p>

            <p class="text-[10px] mt-1" style="color: #214f2c;">
                The farm manager will assign your delivery date
                based on availability.
            </p>
        </div>
    </div>
</div>
        `;

    }


    // =====================================================
    // PICKUP
    // =====================================================

    else if (method === 'Pickup') {

        // Show pickup date
        pickupSection.classList.remove('hidden');

        // Hide delivery information
        deliveryInfo.classList.add('hidden');

        // Date IS required
        dateInput.required = true;

        // Update summary
        summaryMethod.innerText = 'Pickup';

        if (dateInput.value) {
            summaryDate.innerText = dateInput.value;
        } else {
            summaryDate.innerText = 'Select pickup date';
        }

        // Description
        methodDescription.innerHTML = `
          <div class="rounded-lg p-3"
     style="border: 1px solid #d5e2d8; background-color: #f1f6f2;">
    <div class="flex items-start gap-2">
        <i class="fa-solid fa-shop mt-0.5" style="color: #214f2c;"></i>

        <div>
            <p class="text-xs font-bold" style="color: #214f2c;">
                Pickup Selected
            </p>

            <p class="text-[10px] mt-1" style="color: #214f2c;">
                Please select your preferred pickup date
                from the farm.
            </p>
        </div>
    </div>
</div>
        `;
    }


    // =====================================================
    // BUTTON STYLING
    // =====================================================

    btnDelivery.className =
        method === 'Delivery'
       ? "p-3 border rounded-lg flex flex-col items-center justify-center space-y-1 border-green-800 bg-green-50 text-green-800"
        : "p-3 border rounded-lg flex flex-col items-center justify-center space-y-1 border-gray-200 text-gray-500";

    btnPickup.className =
        method === 'Pickup'
       ? "p-3 border rounded-lg flex flex-col items-center justify-center space-y-1 border-green-800 bg-green-50 text-green-800"
        : "p-3 border rounded-lg flex flex-col items-center justify-center space-y-1 border-gray-200 text-gray-500";
            updateLiveSummary();
    updateReservationProgress();
}

    
function validateForm() {

    var customerName = document.getElementById(
        'cust_name_input'
    ).value.trim();

    var contactNumber = document.getElementById(
        'contact_input'
    ).value.trim();

    var deliveryAddress = document.getElementById(
        'delivery_address_input'
    ).value.trim();

    var deliveryMethod = document.getElementById(
        'delivery_method_input'
    ).value;

    var reservationDate = document.getElementById(
        'reservation_date_input'
    ).value;


    /* CUSTOMER NAME */

    if (!customerName) {

        alert('Please enter your full name.');

        document.getElementById(
            'cust_name_input'
        ).focus();

        return false;
    }


    /* CONTACT NUMBER */

    if (!/^09[0-9]{9}$/.test(contactNumber)) {

        alert(
            'Please enter a valid Philippine mobile number starting with 09.'
        );

        document.getElementById(
            'contact_input'
        ).focus();

        return false;
    }


    /* DELIVERY ADDRESS AND MAP */

    if (!deliveryAddress || !mapLocationSelected) {

        alert(
            'Please enter an address and select a valid location on the map.'
        );

        document.getElementById(
            'delivery_address_input'
        ).focus();

        return false;
    }


    /* DELIVERY METHOD */

    if (
        deliveryMethod !== 'Delivery' &&
        deliveryMethod !== 'Pickup'
    ) {

        alert(
            'Please choose either Delivery or Pickup.'
        );

        return false;
    }


    /* PICKUP DATE */

    if (
        deliveryMethod === 'Pickup' &&
        !reservationDate
    ) {

        alert(
            'Please select your preferred pickup date.'
        );

        document.getElementById(
            'reservation_date_input'
        ).focus();

        return false;
    }


    /* CART ITEMS */

    if (bundleItems.length === 0) {

        alert(
            'Please add at least one item to your cart.'
        );

        return false;
    }


    /* DELIVERY DOES NOT REQUIRE A CUSTOMER-SELECTED DATE */

    if (deliveryMethod === 'Delivery') {

        document.getElementById(
            'reservation_date_input'
        ).value = '';
    }


    updateReservationProgress();

    return true;
}


        function openSuccessModal() { document.getElementById('success_modal').classList.remove('hidden'); }
        function closeSuccessModal() { document.getElementById('success_modal').classList.add('hidden'); }
        function openCancellationModal() { document.getElementById('cancellation_modal').classList.remove('hidden'); }
        function closeCancellationModal() { document.getElementById('cancellation_modal').classList.add('hidden'); }

        function executeCancellation() {
            closeCancellationModal();
            fetch('?action=log_cancellation', { method: 'POST' })
            .then(res => res.json()).then(data => { if(data.status === 'success') location.reload(); });
        }

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
            formData.append('mark_read', '1');
            formData.append('notification_id', notificationId);

            fetch('customer_notifications.php', {
                method: 'POST',
                body: formData
            })
            .then(function(response) {
                return response.json();
            })
            .then(function(data) {
                if (data.success) {
                    // Update UI
                    var notificationItem = buttonElement.closest('.customer-notification-item');
                    if (notificationItem) {
                        notificationItem.classList.remove('unread');
                        notificationItem.classList.add('read');
                        buttonElement.remove();

                        // Update badge
                        var badge = document.querySelector('.customer-notification-badge');
                        if (badge) {
                            var currentBadge = parseInt(badge.textContent);
                            if (!isNaN(currentBadge) && currentBadge > 0) {
                                if (currentBadge > 1) {
                                    badge.textContent = currentBadge - 1;
                                } else {
                                    badge.remove();
                                }
                            }
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

        var customerNotificationDropdown = null;
        var isCustomerDropdownOpen = false;

        <?php if (!empty($message)): ?>
            document.addEventListener('DOMContentLoaded', openSuccessModal);
        <?php endif; ?>



function matchReservationStepHeights() {

    const stepCards = document.querySelectorAll('.step-card');
    const step1Card = document.querySelector('.step-1-card');
    const scrollAreas = document.querySelectorAll('.step-scroll');

    if (!step1Card || stepCards.length === 0) {
        return;
    }

    /* =====================================================
       MOBILE / TABLET
       Keep Step 2 scrollable instead of expanding endlessly
    ===================================================== */

    if (window.innerWidth < 1024) {

        stepCards.forEach(function(card) {
            card.style.height = '';
        });

        scrollAreas.forEach(function(area) {

            area.style.height = '';
            area.style.maxHeight = '';
            area.style.overflowY = 'auto';

        });

        return;
    }


    /* =====================================================
       CLEAR PREVIOUS INLINE HEIGHTS
    ===================================================== */

    stepCards.forEach(function(card) {
        card.style.height = '';
    });

    scrollAreas.forEach(function(area) {
        area.style.height = '';
        area.style.maxHeight = '';
        area.style.overflowY = 'auto';
    });


    /* =====================================================
       GET STEP 1 NATURAL HEIGHT
    ===================================================== */

    const step1Height =
        step1Card.getBoundingClientRect().height;


    /* =====================================================
       MAKE ALL STEP CARDS THE SAME HEIGHT
    ===================================================== */

    stepCards.forEach(function(card) {

        card.style.height =
            step1Height + 'px';

        card.style.minHeight =
            step1Height + 'px';

    });


    /* =====================================================
       STEP 2 AND STEP 3 INTERNAL SCROLL
    ===================================================== */

    scrollAreas.forEach(function(area) {

        area.style.flex =
            '1 1 auto';

        area.style.minHeight =
            '0';

        area.style.height =
            'auto';

        area.style.maxHeight =
            'none';

        area.style.overflowY =
            'auto';

        area.style.overflowX =
            'hidden';

    });

}

/* Run after page finishes loading */
window.addEventListener('load', function() {
    setTimeout(function() {
        matchReservationStepHeights();
    }, 300);
});


/* Recalculate when browser is resized */
window.addEventListener('resize', function() {
    matchReservationStepHeights();
});


function copyReservationCode() {

    var reservationCode = <?php echo json_encode($reservation_code); ?>;

    if (navigator.clipboard) {

        navigator.clipboard.writeText(reservationCode).then(function() {

            alert('Reservation ID copied: ' + reservationCode);

        });

    } else {

        var tempInput = document.createElement('input');
        tempInput.value = reservationCode;

        document.body.appendChild(tempInput);
        tempInput.select();

        document.execCommand('copy');

        document.body.removeChild(tempInput);

        alert('Reservation ID copied: ' + reservationCode);
    }
}

/* =========================================================
   AUTOMATIC RESERVATION PROGRESS UPDATES
========================================================= */

document.getElementById('cust_name_input').addEventListener(
    'input',
    updateReservationProgress
);

document.getElementById('contact_input').addEventListener(
    'input',
    updateReservationProgress
);

document.getElementById('reservation_date_input').addEventListener(
    'change',
    updateLiveSummary
);

/* Set the initial progress state when the page loads */
updateLiveSummary();
updateReservationProgress();


    </script>
</body>
</html>