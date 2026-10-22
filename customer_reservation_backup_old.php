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

// Map display names to inventory codes
$egg_size_map = array(
    'Extra Small' => 'XS',
    'Small' => 'Small',
    'Medium' => 'Medium',
    'Large' => 'Large',
    'Extra Large' => 'XL',
    'Jumbo' => 'Jumbo',
    'Super Jumbo' => 'Super Jumbo',
    'Double Yolk' => 'Double Yolk'
);

// Fetch current stock for each egg size
$egg_stocks = array();
foreach ($egg_size_map as $display_name => $inventory_code) {
    $stock_query = "
        SELECT current_stock
        FROM egg_inventory
        WHERE egg_size = ?
        ORDER BY id DESC
        LIMIT 1
    ";
    $stmt = $conn->prepare($stock_query);
    $stmt->bind_param("s", $inventory_code);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $egg_stocks[$display_name] = intval($row['current_stock']);
    } else {
        $egg_stocks[$display_name] = 0;
    }
    $stmt->close();
}

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

    // =========================================================
    // SERVER-SIDE STOCK VALIDATION
    // =========================================================

    if (!empty($egg_types) && count($egg_types) === count($quantities)) {

        // Re-fetch current stock from database to ensure up-to-date values
        $validation_errors = array();

        foreach ($egg_types as $i => $egg_type) {
            $quantity = intval($quantities[$i]);

            // Validate egg type is supported
            if (!isset($prices[$egg_type])) {
                $validation_errors[] = "Invalid egg type: {$egg_type}";
                continue;
            }

            // Validate quantity is positive
            if ($quantity <= 0) {
                $validation_errors[] = "Quantity must be greater than zero for {$egg_type}";
                continue;
            }

            // Get current stock from database
            $inventory_code = isset($egg_size_map[$egg_type]) ? $egg_size_map[$egg_type] : null;
            if ($inventory_code === null) {
                $validation_errors[] = "Invalid egg size mapping for {$egg_type}";
                continue;
            }

            $stock_query = "
                SELECT current_stock
                FROM egg_inventory
                WHERE egg_size = ?
                ORDER BY id DESC
                LIMIT 1
            ";
            $stmt = $conn->prepare($stock_query);
            $stmt->bind_param("s", $inventory_code);
            $stmt->execute();
            $result = $stmt->get_result();
            $current_stock = 0;
            if ($row = $result->fetch_assoc()) {
                $current_stock = intval($row['current_stock']);
            }
            $stmt->close();

            // Validate quantity does not exceed available stock
            if ($quantity > $current_stock) {
                $validation_errors[] = "Insufficient stock for {$egg_type}. Available: {$current_stock}, Requested: {$quantity}";
            }
        }

        // If validation failed, return error
        if (!empty($validation_errors)) {
            $_SESSION['success_message'] = implode("; ", $validation_errors);
            $_SESSION['is_submitted'] = false;
            header("Location: " . $_SERVER['PHP_SELF']);
            exit();
        }

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

        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }
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
   CUSTOMER RESERVATION — CUSTOMER DASHBOARD MATCH
   HEADER + PAGE SHELL ONLY
========================================================= */

/* =========================================================
   MAIN CONTENT
========================================================= */
html,
body {

    margin:
        0;

    padding:
        0;

    width:
        100%;

    min-height:
        100%;

    background:
        #f3f1eb;
}
.main-content {

    margin-left:
        312px;

    min-height:
        100vh;

    padding:
        18px;

    background:
        linear-gradient(
            135deg,
            #eeece6 0%,
            #f5f3ed 100%
        );
}

.copy-reservation-btn:hover {
    color: #214f2c !important;
    background: #e8f0ea !important;
}
/* =========================================================
   RESERVATION OUTER CARD
   SAME AS CUSTOMER DASHBOARD WRAPPER
========================================================= */

.reservation-card {

    width:
        100%;

    background:
        #ffffff;

    border:
        1px solid #dedbd2;

    border-radius:
        22px;

    box-shadow:
        0 12px 35px
        rgba(
            80,
            72,
            55,
            0.10
        );

    overflow:
        hidden;
}


/* =========================================================
   REMOVE EXTRA TAILWIND OUTER SPACING
========================================================= */

.main-content > .flex-1 {
    width: 100% !important;
}

.main-content > .flex-1 > .max-w-7xl {
    width: 100% !important;
    max-width: none !important;

    margin: 0 !important;

    padding:
        0 !important;
}


/* =========================================================
   CUSTOMER RESERVATION HEADER
   EXACT CUSTOMER DASHBOARD HEADER SIZE
========================================================= */

.reservation-card > header {

    min-height:
        86px !important;

    width:
        100% !important;

    padding:
        0 28px !important;

    display:
        flex !important;

    align-items:
        center !important;

    justify-content:
        space-between !important;

    background:
        #f7f6f2 !important;

    border-bottom:
        1px solid #dddcd6 !important;

    box-sizing:
        border-box !important;
}


/* =========================================================
   HEADER TITLE
========================================================= */

.reservation-card > header h1 {

    margin:
        0 !important;

    padding:
        0 !important;

    font-family:
        Arial,
        Helvetica,
        sans-serif !important;

    font-size:
        1.35rem !important;

    font-weight:
        700 !important;

    line-height:
        1.2 !important;

    color:
        #3f4b45 !important;

    letter-spacing:
        -0.3px !important;
}


/* =========================================================
   HEADER RIGHT SIDE
========================================================= */

.reservation-card > header > div {

    display:
        flex !important;

    align-items:
        center !important;

    gap:
        18px !important;

    height:
        100% !important;
}


/* =========================================================
   CUSTOMER ACCOUNT AREA

   Customer Dashboard:
   Avatar → Name → Date/Time → Dropdown

   Reservation:
   We keep the existing name position but
   make its size/color match.
========================================================= */

.reservation-card > header .text-right {

    display:
        flex !important;

    flex-direction:
        column !important;

    justify-content:
        center !important;

    min-width:
        100px !important;

    text-align:
        left !important;
}


/* =========================================================
   CUSTOMER NAME
========================================================= */

.reservation-card > header .text-right > div:first-child {

    display:
        block !important;

    margin:
        0 !important;

    color:
        #3f4b45 !important;

    font-size:
        0.88rem !important;

    font-weight:
        700 !important;

    line-height:
        1.2 !important;
}


/* =========================================================
   CUSTOMER ROLE
========================================================= */

.reservation-card > header .text-right > div:last-child {

    margin-top:
        5px !important;

    font-size:
        0.63rem !important;

    color:
        #8a9590 !important;

    line-height:
        1.2 !important;
}


/* =========================================================
   PROFILE AVATAR
   EXACT CUSTOMER DASHBOARD SIZE
========================================================= */

.reservation-card > header .w-10.h-10 {

    width:
        54px !important;

    height:
        54px !important;

    min-width:
        54px !important;

    border-radius:
        50% !important;

    overflow:
        hidden !important;

    background:
        #e8ebe7 !important;

    border:
        1px solid #d9ddd8 !important;

    box-shadow:
        0 2px 6px
        rgba(
            0,
            0,
            0,
            0.04
        ) !important;
}


.reservation-card > header .w-10.h-10 img {

    width:
        100% !important;

    height:
        100% !important;

    object-fit:
        cover !important;

    display:
        block !important;
}


/* =========================================================
   NOTIFICATION CONTAINER
========================================================= */

.reservation-card > header .relative {

    position:
        relative !important;
}


/* =========================================================
   NOTIFICATION BELL
========================================================= */

.reservation-card > header .relative > div:first-child {

    width:
        40px !important;

    height:
        40px !important;

    padding:
        0 !important;

    display:
        flex !important;

    align-items:
        center !important;

    justify-content:
        center !important;

    border-radius:
        50% !important;

    color:
        #65716b !important;

    background:
        transparent !important;
}


.reservation-card > header .relative > div:first-child:hover {

    background:
        transparent !important;

    color:
        #527d59 !important;
}


/* =========================================================
   NOTIFICATION ICON
========================================================= */

.reservation-card > header .relative > div:first-child i {

    color:
        #65716b !important;

    font-size:
        1.2rem !important;
}


.reservation-card > header .relative > div:first-child:hover i {

    color:
        #527d59 !important;
}


/* =========================================================
   NOTIFICATION BADGE
   SAME SIZE/COLOR AS DASHBOARD
========================================================= */

#bell_badge {

    top:
        1px !important;

    right:
        0 !important;

    width:
        auto !important;

    min-width:
        17px !important;

    height:
        17px !important;

    padding:
        0 4px !important;

    border-radius:
        50% !important;

    background:
        #527d59 !important;

    border:
        2px solid #f7f6f2 !important;
}


/* =========================================================
   NOTIFICATION DROPDOWN
   CHANGE BLUE ACCENTS TO DASHBOARD GREEN
========================================================= */

#notification_dropdown {

    width:
        320px !important;

    margin-top:
        12px !important;

    background:
        #ffffff !important;

    border:
        1px solid #dedbd2 !important;

    border-radius:
        12px !important;

    box-shadow:
        0 12px 30px
        rgba(
            70,
            65,
            55,
            0.12
        ) !important;
}


/* =========================================================
   NOTIFICATION HEADER
========================================================= */

#notification_dropdown > div:first-child {

    background:
        #f7f6f2 !important;

    border-bottom:
        1px solid #e9e6df !important;
}


#notification_dropdown > div:first-child span:first-child {

    color:
        #3f4b45 !important;
}


/* =========================================================
   UNREAD COUNT
========================================================= */

#unread_count {

    background:
        #e9f1e6 !important;

    color:
        #527d59 !important;
}


/* =========================================================
   NOTIFICATION ITEMS
========================================================= */

#notification_list > div {

    transition:
        background 0.2s ease;
}


#notification_list > div:hover {

    background:
        #fafaf7 !important;
}


/* =========================================================
   NOTIFICATION ICON COLORS
========================================================= */

#notification_list .bg-blue-100 {

    background:
        #eef3ed !important;

    color:
        #527d59 !important;
}


/* =========================================================
   RESERVATION BODY
   SAME INNER SPACING FEEL AS DASHBOARD
========================================================= */

.reservation-card > .p-6 {

    padding:
        22px 28px 28px !important;
}


/* =========================================================
   MY RESERVATIONS SECTION
========================================================= */

.reservation-card .mt-6 {

    border:
        1px solid #e2dfd7 !important;

    border-radius:
        10px !important;

    background:
        #ffffff !important;

    box-shadow:
        0 3px 10px
        rgba(
            70,
            65,
            55,
            0.05
        ) !important;
}


/* =========================================================
   RESPONSIVE
========================================================= */

@media screen and (max-width: 1100px) {

    .main-content {

        margin-left:
            270px !important;

        width:
            calc(100% - 270px) !important;
    }
}


@media screen and (max-width: 900px) {

    .main-content {

        margin-left:
            0 !important;

        width:
            100% !important;

        padding:
            15px !important;
    }

    .reservation-card {

        border-radius:
            18px !important;
    }

    .reservation-card > header {

        padding:
            0 18px !important;
    }
}


@media screen and (max-width: 600px) {

    .reservation-card > .p-6 {

        padding:
            18px !important;
    }

    .reservation-card > header h1 {

        font-size:
            1.15rem !important;
    }

    .reservation-card > header .text-right {

        display:
            none !important;
    }
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

    background:
        transparent;

    border:
        none;

    color:
        #65716b;

    font-size:
        1.2rem;

    transition:
        0.2s ease;

    cursor:
        pointer;
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
    position: absolute;
    top: calc(100% + 8px);
    right: 0;
    width: 360px;
    background: #ffffff;
    border: 1px solid #e2dfd7;
    border-radius: 10px;
    box-shadow: 0 3px 10px rgba(70, 65, 55, 0.05);
    z-index: 1000;
}

.customer-notification-dropdown.hidden {
    display: none;
}

.customer-notification-dropdown-header {
    min-height: 50px;
    padding: 0 16px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    border-bottom: 1px solid #e9e6df;
}

.customer-panel-title-group {
    display: flex;
    align-items: center;
    gap: 10px;
}

.customer-panel-title-icon {
    width: 30px;
    height: 30px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    background: #eef3ed;
    color: #527d59;
    font-size: 14px;
}

.customer-dashboard-panel-title {
    font-size: 14px;
    font-weight: 700;
    color: #3f4b45;
}

.customer-mark-all-link {
    font-size: 11px;
    font-weight: 700;
    color: #527d59;
    text-decoration: none;
    cursor: pointer;
}

.customer-mark-all-link:hover {
    text-decoration: underline;
}

.customer-notification-list {
    max-height: 400px;
    overflow-y: auto;
}

.customer-notification-item {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    padding: 12px 16px;
    border-bottom: 1px solid #f0eee8;
    cursor: pointer;
    transition: background 0.15s;
}

.customer-notification-item:hover {
    background: #faf9f7;
}

.customer-notification-item.unread {
    background: #f0f5f1;
}

.customer-notification-item.unread:hover {
    background: #e8f0e9;
}

.customer-notification-icon {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    font-size: 14px;
}

.customer-notification-icon.success {
    background: #e8f5e9;
    color: #2e7d32;
}

.customer-notification-icon.alert {
    background: #fff3e0;
    color: #e65100;
}

.customer-notification-icon.info {
    background: #e3f2fd;
    color: #1565c0;
}

.customer-notification-icon.warning {
    background: #fff8e1;
    color: #f57f17;
}

.customer-notification-content {
    flex: 1;
    min-width: 0;
}

.customer-notification-title {
    font-weight: 600;
    font-size: 13px;
    color: #1a2e1c;
    margin-bottom: 4px;
}

.customer-notification-item.unread .customer-notification-title {
    color: #214f2c;
}

.customer-notification-description {
    font-size: 12px;
    color: #5a6b60;
    line-height: 1.4;
}

.customer-notification-time {
    font-size: 11px;
    color: #9aaa9a;
    margin-top: 4px;
}

.customer-notification-empty {
    padding: 40px 20px;
    text-align: center;
    color: #9aaa9a;
}

.customer-notification-empty i {
    font-size: 32px;
    margin-bottom: 12px;
    color: #c8d4c4;
}

.customer-notification-empty p {
    font-size: 13px;
    margin: 0;
}

.customer-mark-read-btn {
    background: none;
    border: none;
    color: #527d59;
    font-size: 11px;
    font-weight: 600;
    cursor: pointer;
    padding: 4px 8px;
    margin-top: 8px;
}

.customer-mark-read-btn:hover {
    text-decoration: underline;
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
/* =========================================================
   RESERVATION STEP CARDS
========================================================= */

.step-column {
    min-width: 0;
    min-height: 0;
    display: flex;
}

.step-card {
    background: #ffffff;
    border: 1px solid #dedbd2;
    border-radius: 16px;
    padding: 22px;
    height: 100%;
    width: 100%;
    box-sizing: border-box;
    box-shadow: 0 4px 14px rgba(80, 72, 55, 0.06);

    /* IMPORTANT */
    display: flex;
    flex-direction: column;
    min-height: 0;
    overflow: hidden;
}

.step-1-card {
    background: #ffffff;
}

/* =========================================================
   STEP 2 / STEP 3 INTERNAL SCROLL
========================================================= */

.step-scroll {
    min-height: 0;
    flex: 1 1 auto;
    overflow-y: auto;
    overflow-x: hidden;

    padding-right: 6px;
}

/* =========================================================
   STEP 2 SCROLLBAR
========================================================= */

.step-column:nth-child(2) .step-scroll::-webkit-scrollbar {
    width: 7px;
}

.step-column:nth-child(2) .step-scroll::-webkit-scrollbar-track {
    background: #f1f2ef;
    border-radius: 10px;
}

.step-column:nth-child(2) .step-scroll::-webkit-scrollbar-thumb {
    background: #b8c8bc;
    border-radius: 10px;
}

.step-column:nth-child(2) .step-scroll::-webkit-scrollbar-thumb:hover {
    background: #527d59;
}

/* Firefox */

.step-column:nth-child(2) .step-scroll {
    scrollbar-width: thin;
    scrollbar-color: #b8c8bc #f1f2ef;
}

/* =========================================================
   STEP 2 CONTENT
========================================================= */

.step-column:nth-child(2) .step-scroll {
    padding-bottom: 4px;
}

/* =========================================================
   STEP 2 METHOD INFORMATION
========================================================= */

#method_description {
    width: 100%;
    box-sizing: border-box;
}

/* =========================================================
   PICKUP / DELIVERY INFORMATION
========================================================= */

#pickup_date_section,
#delivery_schedule_info {
    width: 100%;
    box-sizing: border-box;
}

/* =========================================================
   ADD TO CART
========================================================= */

.step-column:nth-child(2) button[onclick="addItemToBundle()"] {
    flex-shrink: 0;
    margin-top: 2px;
}


.egg-btn.selected {
    border-color: #214f2c !important;
    background: #f1f6f2 !important;
    color: #214f2c !important;
}

.timeline-progress {
    background: #214f2c !important;
}
button[name="submit_reservation"]:hover {
    background-color: #173b20 !important;
}
</style>
<body class="font-sans text-gray-700 antialiased min-h-screen">

<?php include('customer_panel.php'); ?>

<div class="main-content">
      

        <!-- Main Workspace -->
        <div class="flex-1 flex flex-col min-w-0">
           

     <div class="w-full mx-auto">
    <form id="reservation_form" action="" method="POST" onsubmit="return validateForm()" autocomplete="off">

        <div class="reservation-card bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">

          
<!-- =====================================================
     CUSTOMER DASHBOARD TOPBAR
===================================================== -->

<div class="customer-dashboard-topbar">

    <div class="customer-page-title">
        Customer Reservation
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
                <?php if ($unread_notifications > 0): ?>
                    <span class="customer-notification-badge"><?php echo $unread_notifications > 99 ? '99+' : $unread_notifications; ?></span>
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
                                <input type="text" id="delivery_address_input" name="delivery_address" placeholder="Type location..." required oninput="handleAddressTyping(this.value)" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-blue-500 mb-2">
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
                                        <?php
                                        $available_stock = isset($egg_stocks[$size]) ? $egg_stocks[$size] : 0;
                                        $stock_class = $available_stock === 0 ? 'text-red-500' : ($available_stock < 100 ? 'text-amber-500' : 'text-green-600');
                                        $disabled_attr = $available_stock === 0 ? 'disabled style="opacity:0.5;cursor:not-allowed;"' : '';
                                        ?>
                                        <button type="button" data-egg="<?= $size ?>" data-stock="<?= $available_stock ?>" onclick="setEgg('<?= $size ?>')" <?= $disabled_attr ?> class="egg-btn flex flex-col items-center p-2 border rounded-lg transition-all border-gray-200 hover:bg-gray-50">
                                            <span class="w-5 h-7 bg-slate-100 border border-slate-400 rounded-full inline-block shadow-inner mb-1"></span>
                                            <span class="text-[9px] text-gray-600 font-medium text-center leading-none mb-1"><?= $size ?></span>
                                            <span class="text-[9px] font-bold" style="color: #214f2c;">₱<?= $price ?></span>
                                            <span class="text-[8px] <?= $stock_class ?> font-medium">Stock: <?= $available_stock ?></span>
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
        required
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

    <!-- Interactive Logout Confirmation Modal -->
<div id="logout_modal" class="hidden fixed inset-0 bg-slate-900/50 backdrop-blur-sm flex items-center justify-center z-[100] p-4">
    <div class="bg-white rounded-xl shadow-2xl border border-gray-100 max-w-sm w-full p-6 space-y-4 transform transition-all">
        <div class="flex items-center space-x-3 text-red-500">
            <div class="w-10 h-10 bg-red-100 rounded-full flex items-center justify-center text-red-600">
                <i class="fa-solid fa-right-from-bracket text-base"></i>
            </div>
            <h3 class="text-lg font-bold text-slate-800">Confirm Logout</h3>
        </div>
        <p class="text-sm text-gray-500">Are you sure you want to end your session? You will need to log back in to manage reservations.</p>
        <div class="flex justify-end space-x-3 pt-2">
            <button type="button" onclick="closeLogoutModal()" class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-4 py-2 rounded-lg text-sm font-semibold transition">
                Stay Logged In
            </button>
            <a href="logout.php" class="bg-red-500 hover:bg-red-600 text-white px-4 py-2 rounded-lg text-sm font-semibold text-center transition">
                Yes, Logout
            </a>
        </div>
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



        function openLogoutModal() {
    document.getElementById('logout_modal').classList.remove('hidden');
}

function closeLogoutModal() {
    document.getElementById('logout_modal').classList.add('hidden');
}

// Optional: Close the modal if the user clicks anywhere outside of the white modal container box
window.addEventListener('click', function(event) {
    const logoutModal = document.getElementById('logout_modal');
    if (event.target === logoutModal) {
        closeLogoutModal();
    }
});


        const priceList = { 'Extra Small': 140, 'Small': 150, 'Medium': 175, 'Large': 195, 'Extra Large': 235, 'Jumbo': 255, 'Super Jumbo': 270, 'Double Yolk': 320 };
        let bundleItems = [];
        let addressLookupTimeout = null;

        // Leaflet Setup
        const map = L.map('map').setView([14.5995, 120.9842], 13);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(map);
        const marker = L.marker([14.5995, 120.9842], { draggable: true }).addTo(map);

        function reverseGeocode(lat, lng) {
            fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}`)
                .then(res => res.json()).then(data => {
                    if (data && data.display_name) {
                        document.getElementById('delivery_address_input').value = data.display_name;
                        document.getElementById('summary_address_display').innerText = data.display_name;
                    }
                });
        }
        map.on('click', e => { marker.setLatLng(e.latlng); reverseGeocode(e.latlng.lat, e.latlng.lng); });
        marker.on('dragend', () => { reverseGeocode(marker.getLatLng().lat, marker.getLatLng().lng); });

        function handleAddressTyping(val) {
            document.getElementById('summary_address_display').innerText = val || 'None';
            clearTimeout(addressLookupTimeout);
            if (!val.trim()) return;
            addressLookupTimeout = setTimeout(() => {
                fetch(`https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(val)}&limit=1`)
                    .then(res => res.json()).then(data => {
                        if (data.length > 0) {
                            map.setView([data[0].lat, data[0].lon], 16);
                            marker.setLatLng([data[0].lat, data[0].lon]);
                        }
                    });
            }, 600);
        }

        // Bundle Logic Operations
        function setEgg(val) {
            document.getElementById('egg_type_select').value = val;
            document.querySelectorAll('.egg-btn').forEach(btn => {
                btn.className = btn.getAttribute('data-egg') === val
                   ? "egg-btn flex flex-col items-center p-2 border rounded-lg transition-all"
                    : "egg-btn flex flex-col items-center p-2 border rounded-lg transition-all border-gray-200 hover:bg-gray-50";
            });

            // Update quantity input max based on available stock
            const btn = document.querySelector(`.egg-btn[data-egg="${val}"]`);
            const qtyInput = document.getElementById('quantity_input');
            if (btn && qtyInput) {
                const availableStock = parseInt(btn.getAttribute('data-stock')) || 0;
                qtyInput.max = availableStock;
                if (availableStock === 0) {
                    qtyInput.value = '';
                    qtyInput.placeholder = 'Out of stock';
                } else {
                    qtyInput.placeholder = 'Enter quantity';
                }
            }
        }

        function addItemToBundle() {
            const size = document.getElementById('egg_type_select').value;
            const qty = parseInt(document.getElementById('quantity_input').value);

            if (!size || isNaN(qty) || qty <= 0) {
                alert('Please select a valid size and quantity.');
                return;
            }

            // Get available stock from the button's data-stock attribute
            const btn = document.querySelector(`.egg-btn[data-egg="${size}"]`);
            if (!btn) {
                alert('Invalid egg size selected.');
                return;
            }

            const availableStock = parseInt(btn.getAttribute('data-stock')) || 0;

            // Calculate total quantity for this size (existing + new)
            const existingItem = bundleItems.find(item => item.size === size);
            const currentQty = existingItem ? existingItem.qty : 0;
            const totalQty = currentQty + qty;

            // Validate against available stock
            if (totalQty > availableStock) {
                alert(`Insufficient stock for ${size}. Available: ${availableStock}, Requested: ${totalQty}`);
                return;
            }

            if (existingItem) {
                existingItem.qty += qty;
            } else {
                bundleItems.push({ size, qty, price: priceList[size] });
            }

            document.getElementById('quantity_input').value = '';
            updateLiveSummary();
        }

        function removeBundleItem(index) {
            bundleItems.splice(index, 1);
            updateLiveSummary();
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
}

      function validateForm() {

    const contactNumber =
        document.getElementById('contact_input').value;

    const deliveryMethod =
        document.getElementById('delivery_method_input').value;

    const reservationDate =
        document.getElementById('reservation_date_input').value;


    // =====================================================
    // CONTACT NUMBER
    // =====================================================

    if (contactNumber.length !== 11) {

        alert('Contact number must be exactly 11 digits.');

        document.getElementById('contact_input').focus();

        return false;
    }


    if (!/^09[0-9]{9}$/.test(contactNumber)) {

        alert(
            'Please enter a valid Philippine mobile number starting with 09.'
        );

        document.getElementById('contact_input').focus();

        return false;
    }


    // =====================================================
    // ITEMS
    // =====================================================

    if (bundleItems.length === 0) {

        alert(
            'Please add at least one item row to your bundle layout.'
        );

        return false;
    }

    // Validate stock availability for all items
    for (const item of bundleItems) {
        const btn = document.querySelector(`.egg-btn[data-egg="${item.size}"]`);
        if (!btn) {
            alert(`Invalid egg size: ${item.size}`);
            return false;
        }

        const availableStock = parseInt(btn.getAttribute('data-stock')) || 0;
        if (item.qty > availableStock) {
            alert(`Insufficient stock for ${item.size}. Available: ${availableStock}, Requested: ${item.qty}`);
            return false;
        }
    }


    // =====================================================
    // DELIVERY METHOD
    // =====================================================

    if (!deliveryMethod) {

        alert(
            'Please choose either Delivery or Pickup.'
        );

        return false;
    }


    // =====================================================
    // PICKUP DATE
    // =====================================================

    if (deliveryMethod === 'Pickup' && !reservationDate) {

        alert(
            'Please select your preferred pickup date.'
        );

        document.getElementById('reservation_date_input').focus();

        return false;
    }


    // =====================================================
    // DELIVERY
    // =====================================================

    if (deliveryMethod === 'Delivery') {

        // Delivery should NOT have a customer-selected date.
        document.getElementById('reservation_date_input').value = '';
    }


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


    </script>
</body>
</html>