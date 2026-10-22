<?php
ob_start();

// Include standardized session configuration FIRST
require_once 'session_config.php';

date_default_timezone_set('Asia/Manila');

require 'db.php';
require_once 'config_age_eligibility.php';

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
$customer_name = isset($_SESSION['fullname'])
    ? $_SESSION['fullname']
    : (isset($_SESSION['customer_name']) ? $_SESSION['customer_name'] : (isset($_SESSION['name']) ? $_SESSION['name'] : 'Customer'));
$user_role = isset($_SESSION['role']) ? $_SESSION['role'] : 'Customer';
$active_page = 'reservation';

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
    <link rel="stylesheet" href="customer_styles.css">
</head>
<style>

/* =========================================================
   RESERVATION PAGE SPECIFIC STYLES
========================================================= */

.reservation-hero {
    background: linear-gradient(135deg, #214f2c 0%, #175c31 100%);
    color: #ffffff;
    padding: 35px 25px;
    text-align: center;
}

.reservation-card {
    padding-top: 0;
}

.reservation-hero h1 {
    margin: 0 0 12px;
    font-size: 26px;
    font-weight: 700;
}

.reservation-hero h1 i {
    margin-right: 10px;
}

.reservation-hero p {
    margin: 0;
    font-size: 14px;
    line-height: 1.6;
    opacity: 0.9;
}

.reservation-progress {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 25px 30px;
    background: #f8f9f6;
    border-bottom: 1px solid #e8e9e6;
}

.reservation-progress-step {
    display: flex;
    align-items: center;
    gap: 12px;
    flex: 1;
}

.reservation-progress-number {
    width: 32px;
    height: 32px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    background: #e1e5e1;
    color: #69736d;
    font-size: 13px;
    font-weight: 700;
    border: 2px solid transparent;
    transition: all 0.35s cubic-bezier(0.4, 0, 0.2, 1);
}

.reservation-progress-label {
    font-size: 13px;
    font-weight: 600;
    color: #69736d;
    transition: color 0.35s cubic-bezier(0.4, 0, 0.2, 1);
}

.reservation-progress-step.completed .reservation-progress-number {
    background-color: #214f2c;
    border-color: #214f2c;
    color: #ffffff;
    box-shadow: 0 2px 6px rgba(33, 79, 44, 0.25);
}

.reservation-progress-step.completed .reservation-progress-label {
    color: #214f2c;
    font-weight: 700;
}

.reservation-progress-step.active .reservation-progress-number {
    border-color: #214f2c;
    background: #214f2c;
    color: #ffffff;
}

.timeline-progress {
    background-color: #214f2c !important;
    height: 4px;
    border-radius: 9999px;
    transition: width 0.6s cubic-bezier(0.4, 0, 0.2, 1);
    max-width: calc(100% - 2rem);
    pointer-events: none;
}

@media (prefers-reduced-motion: reduce) {
    .timeline-progress,
    .reservation-progress-number,
    .reservation-progress-label,
    #timeline_dot_draft,
    #timeline_dot_processing,
    #timeline_dot_submitted {
        transition: none !important;
    }
}

.step-card {
    padding: 30px;
    border-bottom: 1px solid #e8e9e6;
}

.step-card:last-child {
    border-bottom: none;
}

.copy-reservation-btn {
    background: #f0f2ef;
    border: 1px solid #d4d8d3;
    transition: all 0.2s ease;
}

.copy-reservation-btn:hover {
    background: #e5e7e4;
}

#pickup_date_section.hidden {
    display: none;
}

.reservation-list {
    max-height: 300px;
    overflow-y: auto;
}

.step-scroll {
    overflow-y: visible;
    max-height: none;
}

/* =========================================================
   STEP 2 — SCROLLABLE INNER CONTAINER
   The h2 heading lives outside this div and stays pinned.
   Only the content (egg grid, qty, method, add-to-cart)
   scrolls independently within the column.
========================================================= */

.step-2-card {
    display: flex;
    flex-direction: column;
    /* Let the card grow to fill the column height naturally */
    min-height: 0;
}

.step-2-scroll {
    overflow-y: auto;
    max-height: calc(100vh - 290px);
    min-height: 200px;
    padding-right: 6px; /* room for scrollbar */
    /* Prevent nested horizontal overflow on small screens */
    overflow-x: hidden;
}

/* Responsive — tighter max-height on medium/small viewports */
@media (max-width: 1023px) {
    .step-2-scroll {
        max-height: 520px;
    }
}

@media (max-width: 640px) {
    .step-2-scroll {
        max-height: 420px;
    }
}

/* ---- VDVC dark-green scrollbar (Webkit) ---- */
.step-2-scroll::-webkit-scrollbar {
    width: 5px;
}

.step-2-scroll::-webkit-scrollbar-track {
    background: #f1f6f2;
    border-radius: 8px;
}

.step-2-scroll::-webkit-scrollbar-thumb {
    background: #8db89a;
    border-radius: 8px;
    border: 1px solid #f1f6f2;
}

.step-2-scroll::-webkit-scrollbar-thumb:hover {
    background: #214f2c;
}

/* Firefox */
.step-2-scroll {
    scrollbar-width: thin;
    scrollbar-color: #8db89a #f1f6f2;
}

.reservation-add-to-cart {
    background-color: #214f2c;
}

.reservation-add-to-cart:hover {
    background-color: #175c31;
}

</style>
<body>

<?php require 'customer_header.php'; ?>


<div class="main-content" style="padding-top: 72px;">
      

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

    <div class="step-card step-2-card">

        <h2 class="text-lg font-bold text-slate-800 mb-5">
            Step 2: Add Items
        </h2>

        <div class="step-2-scroll space-y-4">
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
        class="w-full text-white font-medium py-2 px-4 rounded-lg text-xs transition flex items-center justify-center space-x-2 reservation-add-to-cart"
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
                                <div class="relative flex items-center justify-between px-4" id="timeline_container">
                                    <div class="absolute left-4 right-4 h-1 bg-gray-200 top-1/2 -translate-y-1/2 z-0"></div>
                                    <div id="timeline_progress_bar" class="timeline-progress absolute left-4 h-1 top-1/2 -translate-y-1/2 z-0" data-submitted="<?= $is_submitted ? '1' : '0'; ?>" style="width: 0;"></div>
                                    <div class="z-10 flex flex-col items-center">
                                        <div id="timeline_dot_draft" class="w-5 h-5 rounded-full border-4 border-white shadow transition-colors duration-500" style="background-color: #214f2c;"></div>
                                        <span id="timeline_label_draft" class="text-xs font-semibold mt-1" style="color: #214f2c;">Draft</span>
                                    </div>
                                    <div class="z-10 flex flex-col items-center">
                                        <div id="timeline_dot_processing" class="w-5 h-5 rounded-full border-4 border-white shadow transition-colors duration-500 bg-gray-300"></div>
                                        <span id="timeline_label_processing" class="text-xs mt-1 text-gray-400 font-medium transition-colors duration-500">Processing</span>
                                    </div>
                                    <div class="z-10 flex flex-col items-center">
                                        <div id="timeline_dot_submitted" class="w-5 h-5 rounded-full border-4 border-white shadow transition-colors duration-500 bg-gray-300"></div>
                                        <span id="timeline_label_submitted" class="text-xs mt-1 text-gray-400 font-medium transition-colors duration-500">Submitted</span>
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




<script>

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
    var nameEl = document.getElementById('cust_name_input');
    var contactEl = document.getElementById('contact_input');
    var addressEl = document.getElementById('delivery_address_input');
    var methodEl = document.getElementById('delivery_method_input');
    var pickupDateEl = document.getElementById('reservation_date_input');

    var name = nameEl ? nameEl.value.trim() : '';
    var contact = contactEl ? contactEl.value.trim() : '';
    var address = addressEl ? addressEl.value.trim() : '';
    var method = methodEl ? methodEl.value.trim().toLowerCase() : '';
    var pickupDate = pickupDateEl ? pickupDateEl.value : '';

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

    var step2Complete = (typeof bundleItems !== 'undefined') && bundleItems.length > 0;
    var step3Complete = step1Complete && step2Complete;

    setReservationStepCompleted(1, step1Complete);
    setReservationStepCompleted(2, step2Complete);
    setReservationStepCompleted(3, step3Complete);

    // Update timeline progress bar and nodes
    var progressBar = document.getElementById('timeline_progress_bar');
    if (!progressBar) return;

    var isSubmitted = progressBar.getAttribute('data-submitted') === '1';
    var dotProcessing = document.getElementById('timeline_dot_processing');
    var labelProcessing = document.getElementById('timeline_label_processing');
    var dotSubmitted = document.getElementById('timeline_dot_submitted');
    var labelSubmitted = document.getElementById('timeline_label_submitted');

    if (isSubmitted) {
        // Full submitted state
        progressBar.style.width = 'calc(100% - 2rem)';
        if (dotProcessing) {
            dotProcessing.style.backgroundColor = '#214f2c';
            dotProcessing.classList.remove('bg-gray-300');
        }
        if (labelProcessing) {
            labelProcessing.style.color = '#214f2c';
            labelProcessing.classList.remove('text-gray-400');
            labelProcessing.classList.add('font-semibold');
        }
        if (dotSubmitted) {
            dotSubmitted.style.backgroundColor = '#214f2c';
            dotSubmitted.classList.remove('bg-gray-300');
        }
        if (labelSubmitted) {
            labelSubmitted.style.color = '#214f2c';
            labelSubmitted.classList.remove('text-gray-400');
            labelSubmitted.classList.add('font-semibold');
        }
    } else {
        // Interactive Drafting / Processing state
        if (step3Complete) {
            // Step 1 and Step 2 complete: reaches Processing
            progressBar.style.width = 'calc((100% - 2rem) * 0.5)';
            if (dotProcessing) {
                dotProcessing.style.backgroundColor = '#214f2c';
                dotProcessing.classList.remove('bg-gray-300');
            }
            if (labelProcessing) {
                labelProcessing.style.color = '#214f2c';
                labelProcessing.classList.remove('text-gray-400');
                labelProcessing.classList.add('font-semibold');
            }
        } else if (step1Complete || step2Complete) {
            // Partial progress towards processing
            progressBar.style.width = 'calc((100% - 2rem) * 0.25)';
            if (dotProcessing) {
                dotProcessing.style.backgroundColor = '';
                dotProcessing.classList.add('bg-gray-300');
            }
            if (labelProcessing) {
                labelProcessing.style.color = '';
                labelProcessing.classList.add('text-gray-400');
                labelProcessing.classList.remove('font-semibold');
            }
        } else {
            // Pure draft
            progressBar.style.width = '0%';
            if (dotProcessing) {
                dotProcessing.style.backgroundColor = '';
                dotProcessing.classList.add('bg-gray-300');
            }
            if (labelProcessing) {
                labelProcessing.style.color = '';
                labelProcessing.classList.add('text-gray-400');
                labelProcessing.classList.remove('font-semibold');
            }
        }

        if (dotSubmitted) {
            dotSubmitted.style.backgroundColor = '';
            dotSubmitted.classList.add('bg-gray-300');
        }
        if (labelSubmitted) {
            labelSubmitted.style.color = '';
            labelSubmitted.classList.add('text-gray-400');
            labelSubmitted.classList.remove('font-semibold');
        }
    }
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

        <?php if (!empty($message)): ?>
            document.addEventListener('DOMContentLoaded', openSuccessModal);
        <?php endif; ?>



function matchReservationStepHeights() {

    const stepCards = document.querySelectorAll('.step-card');
    const step1Card = document.querySelector('.step-1-card');
    const scrollAreas = document.querySelectorAll('.step-scroll, .step-2-scroll');

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

var dateInput = document.getElementById('reservation_date_input');
if (dateInput) {
    dateInput.addEventListener('change', function() {
        updateLiveSummary();
        updateReservationProgress();
    });
}

/* Set the initial progress state when the page loads */
updateLiveSummary();

// Smooth entry animation on page load
window.addEventListener('load', function() {
    var progressBar = document.getElementById('timeline_progress_bar');
    if (progressBar && progressBar.getAttribute('data-submitted') === '1') {
        progressBar.style.width = '0%';
        setTimeout(function() {
            updateReservationProgress();
        }, 150);
    } else {
        updateReservationProgress();
    }
});


    </script>

<?php require 'customer_modal.php'; ?>

<?php require 'customer_footer.php'; ?>

</body>
</html>