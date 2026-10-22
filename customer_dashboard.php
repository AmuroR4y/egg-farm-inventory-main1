<?php
ob_start();

// Include standardized session configuration FIRST
require_once 'session_config.php';

date_default_timezone_set('Asia/Manila');

require 'db.php';

/* =========================================================
   SECURITY CHECK
========================================================= */

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

if (!isset($_SESSION['role']) || $_SESSION['role'] != 'customer') {
    header("Location: login.php");
    exit();
}

/* PHP 5.5 COMPATIBLE TOKEN COMPARISON */
function customerTokenEquals($known, $submitted)
{
    if (!is_string($known) || !is_string($submitted)) {
        return false;
    }

    if (strlen($known) !== strlen($submitted)) {
        return false;
    }

    $result = 0;
    $length = strlen($known);

    for ($i = 0; $i < $length; $i++) {
        $result |= ord($known[$i]) ^ ord($submitted[$i]);
    }

    return $result === 0;
}
/* =========================================================
   SECURE CUSTOMER LOGOUT
========================================================= */


/* Create a logout token for form validation. */
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
        // Compatible fallback for older PHP installations.
        $_SESSION['customer_logout_token'] = hash(
            'sha256',
            uniqid(mt_rand(), true) . session_id()
        );
    }
}



/* Process logout only after the customer confirms. */
if (
    $_SERVER['REQUEST_METHOD'] == 'POST' &&
    isset($_POST['customer_logout'])
) {
    $submitted_token = isset($_POST['logout_token'])
        ? $_POST['logout_token']
        : '';

    $session_token = isset($_SESSION['customer_logout_token'])
        ? $_SESSION['customer_logout_token']
        : '';

    if (
        $submitted_token !== '' &&
        $session_token !== '' &&
        is_string($submitted_token) &&
        is_string($session_token) &&
        strlen($submitted_token) === strlen($session_token) &&
       customerTokenEquals($session_token, $submitted_token)
    ) {
        $_SESSION = array();

        if (ini_get('session.use_cookies')) {
            $cookie_params = session_get_cookie_params();

            setcookie(
                session_name(),
                '',
                time() - 42000,
                $cookie_params['path'],
                $cookie_params['domain'],
                $cookie_params['secure'],
                $cookie_params['httponly']
            );
        }

        session_destroy();

        header("Location: login.php");
        exit();
    }

    header("Location: customer_dashboard.php");
    exit();
}

$customer_id = $_SESSION['user_id'];
$unread_notifications = 0;

$notification_count_result = $conn->query(
    "SELECT COUNT(*) AS total
     FROM notifications
     WHERE user_id = " . (int)$customer_id . "
     AND is_read = 0"
);

if ($notification_count_result && ($notification_count_row = $notification_count_result->fetch_assoc())) {
    $unread_notifications = (int)$notification_count_row['total'];
}

/* GET LOGGED-IN CUSTOMER NAME FROM DATABASE */

$customer_name = 'Customer';

$customer_sql = "SELECT fullname FROM users WHERE id = ? LIMIT 1";
$customer_stmt = $conn->prepare($customer_sql);

if ($customer_stmt) {
    $customer_stmt->bind_param("i", $customer_id);
    $customer_stmt->execute();

    $customer_result = $customer_stmt->get_result();

    if ($customer_result && ($customer_row = $customer_result->fetch_assoc())) {
        if (!empty($customer_row['fullname'])) {
            $customer_name = $customer_row['fullname'];
        }
    }

    $customer_stmt->close();
}
$active_page = 'dashboard';


/* =========================================================
   TOTAL RESERVATIONS
========================================================= */

$total_reservations = 0;

$reservation_count_sql = "
    SELECT COUNT(DISTINCT reservation_code) AS total
    FROM reservations
    WHERE user_id = ?
";

$stmt = $conn->prepare($reservation_count_sql);

if ($stmt) {
    $stmt->bind_param("i", $customer_id);
    $stmt->execute();

    $result = $stmt->get_result();

    if ($result && $row = $result->fetch_assoc()) {
        $total_reservations = $row['total'];
    }

    $stmt->close();
}


/* =========================================================
   TOTAL TRAYS ORDERED
========================================================= */

$total_trays = 0;

$total_trays_sql = "
    SELECT SUM(quantity) AS total
    FROM reservations
    WHERE user_id = ?
";

$stmt = $conn->prepare($total_trays_sql);

if ($stmt) {
    $stmt->bind_param("i", $customer_id);
    $stmt->execute();

    $result = $stmt->get_result();

    if ($result && $row = $result->fetch_assoc()) {
        $total_trays = $row['total'] ? $row['total'] : 0;
    }

    $stmt->close();
}


/* =========================================================
   AVAILABLE EGG STOCKS
========================================================= */

$egg_stocks = array();

$stock_sql = "
    SELECT
        e.egg_size,
        e.current_stock AS available_stock
    FROM egg_inventory e
    INNER JOIN (
        SELECT egg_size, MAX(id) AS id
        FROM egg_inventory
        GROUP BY egg_size
    ) latest ON latest.id = e.id
    ORDER BY 
        FIELD(
                e.egg_size,
            'XS',
            'Small',
            'Medium',
            'Large',
            'XL',
            'Jumbo',
            'Super Jumbo',
            'Double Yolk'
        )
";

$stock_result = $conn->query($stock_sql);

if ($stock_result) {
    while ($stock_row = $stock_result->fetch_assoc()) {
        $egg_stocks[] = $stock_row;
    }
}


/* =========================================================
   RECENT CUSTOMER ACTIVITY
========================================================= */

$recent_activities = array();

$activity_sql = "
    SELECT
        reservation_code,
        egg_type AS egg_size,
        quantity,
        status,
        reserved_at
    FROM reservations
    WHERE user_id = ?
    ORDER BY reserved_at DESC
    LIMIT 5
";

$stmt = $conn->prepare($activity_sql);

if ($stmt) {
    $stmt->bind_param("i", $customer_id);
    $stmt->execute();

    $result = $stmt->get_result();

    if ($result) {
        while ($activity_row = $result->fetch_assoc()) {
            $recent_activities[] = $activity_row;
        }
    }

    $stmt->close();
}


/* =========================================================
   GREETING
========================================================= */

$current_hour = date('H');

if ($current_hour < 12) {
    $greeting = "Good morning";
} elseif ($current_hour < 18) {
    $greeting = "Good afternoon";
} else {
    $greeting = "Good evening";
}


/* =========================================================
   GET FIRST NAME
========================================================= */

$name_parts = explode(" ", trim($customer_name));
$first_name = $name_parts[0];


/* =========================================================
   STATUS CLASS
========================================================= */

function getActivityIcon($status)
{
    $status = strtolower($status);

    if ($status == 'confirmed') {
        return array(
            'icon' => 'fa-calendar-check',
            'class' => 'confirmed'
        );
    }

    if ($status == 'completed') {
        return array(
            'icon' => 'fa-circle-check',
            'class' => 'completed'
        );
    }

    if (
        $status == 'cancelled' ||
        $status == 'canceled' ||
        $status == 'rejected'
    ) {
        return array(
            'icon' => 'fa-circle-xmark',
            'class' => 'cancelled'
        );
    }

    if ($status == 'pending') {
        return array(
            'icon' => 'fa-clock',
            'class' => 'pending'
        );
    }

    return array(
        'icon' => 'fa-calendar',
        'class' => 'default'
    );
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

    <title>Customer Dashboard</title>

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
    >

    <link rel="stylesheet" href="shared_notifications.css?v=20261022">

 <style>

/* =========================================================
   GLOBAL RESET
========================================================= */

* {
    box-sizing: border-box;
}

html,
body {
    margin: 0;
    padding: 0;
    width: 100%;
    min-height: 100%;
}

body {
    min-height: 100vh;
    font-family: Arial, Helvetica, sans-serif;
    background: #f3f1eb;
    color: #263d32;
    font-size: 12px;
}

a {
    color: inherit;
}

button,
input,
select,
textarea {
    font-family: inherit;
}


/* =========================================================
   NAVBAR
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


/* =========================================================
   LOGO
========================================================= */

.customer-navbar-logo {
    display: flex;
    align-items: center;

    width: 190px;
    flex-shrink: 0;
}

.customer-navbar-logo img {
    width: 145px;
    height: auto;
    display: block;
}


/* =========================================================
   NAVIGATION
========================================================= */

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
    font-weight: 400;

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


/* =========================================================
   NAVBAR RIGHT
========================================================= */

.customer-navbar-right {
    display: flex;
    align-items: center;

    gap: 16px;

    min-width: 240px;

    justify-content: flex-end;
}


/* =========================================================
   NOTIFICATION
========================================================= */

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


/* =========================================================
   NOTIFICATION DROPDOWN
========================================================= */

.customer-notification-dropdown {
    position: absolute;

    top: 48px;
    right: 0;

    width: 355px;

    background: #ffffff;

    border: 1px solid #dedede;

    border-radius: 10px;

    box-shadow:
        0 12px 30px rgba(0, 0, 0, 0.12);

    overflow: hidden;

    z-index: 1000;
}

.customer-notification-dropdown.hidden {
    display: none;
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


/* =========================================================
   CUSTOMER ACCOUNT
========================================================= */

.customer-account-link {
    display: flex;
    align-items: center;

    gap: 9px;

    text-decoration: none;

    color: #263d32;
}

.customer-avatar {
    width: 42px;
    height: 42px;
    min-width: 42px;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 1px solid #e1e9df;
    border-radius: 50%;
   background-color: #dcebdd;
color: #0d4828;
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
 
.customer-date{
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
   CUSTOMER ACCOUNT DROPDOWN
========================================================= */

.customer-account-wrapper {
    position: relative;
    display: flex;
    align-items: center;
    gap: 8px;
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


/* =========================================================
   HERO / WELCOME BANNER
========================================================= */

.customer-hero {
    width: 100%;
    height: 150px;

    position: relative;

    overflow: hidden;

    background-image:
     
        url("welcome.jpg");

    background-size: cover;

    background-position: center 48%;

    display: flex;
    align-items: center;
}

.customer-hero-content {
    width: calc(100% - 100px);

    max-width: 1740px;

    margin: 0 auto;

    padding-left: 130px;
}



.customer-hero-title {
    margin: 0 0 7px;

    font-family: Georgia, "Times New Roman", serif;

    font-size: clamp(27px, 3.1vw, 35px);

   font-weight: 700;
    line-height: 1.12;
    letter-spacing: -0.5px;

    color: #155331;

}

.customer-hero-subtitle {
    margin: 0;

    font-size: 15px;

    color: #303a32;

    letter-spacing: 1.45;
}


/* =========================================================
   MAIN PAGE
========================================================= */

.customer-page {
    width: calc(100% - 100px);

    max-width: 1740px;

    margin: 0 auto;

    padding: 16px 0 22px;
   
}


/* =========================================================
   SUMMARY CARDS
========================================================= */

.summary-grid {
    display: grid;

    grid-template-columns:
        repeat(3, 1fr);

    gap: 16px;

    margin-bottom: 14px;
}

.summary-card {
    min-height: 82px;

    background: #ffffff;

    border: 1px solid #dfe3df;

    border-radius: 9px;

    padding: 14px 18px;

    display: flex;
    align-items: center;

    box-shadow:
        0 2px 7px rgba(38, 61, 50, 0.05);
}

.summary-icon {
    width: 45px;
    height: 45px;

    min-width: 45px;

    display: flex;
    align-items: center;
    justify-content: center;

    border-radius: 50%;

    margin-right: 13px;

    font-size: 17px;
}

.summary-icon.account {
    background: #edf4ed;
    color: #3b8052;
}

.summary-icon.reservation {
    background: #f0eafa;
    color: #7655a1;
}

.summary-icon.trays {
    background: #e8f2f5;
    color: #36748b;
}

.summary-content {
    min-width: 0;
}

.summary-content span {
    display: block;
    font-size: 14px;
    font-weight: 700;
    color: #59635c;
    line-height: 1.25;
}

.summary-content h2 {
    margin: 5px 0 0;
    font-size: 25px;
    color: #38433d;
    font-weight: 700;
    line-height: 1.15;
}

.summary-content small {
    display: block;
    margin-top: 5px;
    font-size: 12px;
    color: #8b9089;
    line-height: 1.2;
}


/* =========================================================
   DASHBOARD GRID
========================================================= */

.dashboard-grid {
    display: grid;

    grid-template-columns:
        minmax(0, 1.65fr)
        minmax(330px, 1fr);

    gap: 14px;

    align-items: stretch;
}


/* =========================================================
   DASHBOARD CARD
========================================================= */

.dashboard-card {
    min-width: 0;

    background: #ffffff;

    border: 1px solid #dfe3df;

    border-radius: 9px;

    overflow: hidden;

    box-shadow:
        0 2px 7px rgba(38, 61, 50, 0.05);
}


/* =========================================================
   CARD HEADER
========================================================= */

.card-header {
    min-height: 56px;

    padding: 0 16px;

    display: flex;
    align-items: center;
    justify-content: space-between;

    border-bottom: 1px solid #e7eae7;
}

.card-title {
    display: flex;
    align-items: center;

    gap: 9px;
}

.card-title-icon {
    width: 32px;
    height: 32px;

    display: flex;
    align-items: center;
    justify-content: center;

    border-radius: 50%;

    background: #edf4ed;

    color: #39794d;

    font-size: 13px;
}

.card-title-text h2 {
    margin: 0;
    font-size: 15px;
    font-weight: 700;
    color: #3f4b45;
    line-height: 1.2;
}

.card-title-text p {
    margin: 4px 0 0;
    font-size: 11px;
    color: #8a9089;
    line-height: 1.2;
}

.view-all-btn {
    font-size: 12px;
    font-weight: 700;
    color: #527d59;
    text-decoration: none;
}


.view-all-btn:hover {
    text-decoration: underline;
}


/* =========================================================
   STOCK TABLE
========================================================= */

.stock-table-container {
    width: 100%;

    padding: 9px 12px 4px;

    overflow-x: auto;
}

.stock-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 12px;
}


.stock-table thead {
    background: #edf3ee;
}

.stock-table th {
    padding: 9px 8px;
    color: #627068;
    font-size: 12px;
    font-weight: 700;
    text-align: left;
    white-space: nowrap;
}

.stock-table td {
    padding: 11px 10px;
    color: #59635c;
    border-bottom: 1px solid #eceae4;
    font-size: 13px;
}

.stock-table tbody tr:last-child td {
    border-bottom: none;
}

.stock-table tbody tr:hover {
    background: #fafcf9;
}

.stock-table td strong {
    color: #273d32;

    font-size: 10px;

    font-weight: 700;
}

.stock-number {
    font-size: 17px;
    font-weight: 700;
    color: #46554d;
}

.stock-status {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 12px;
    color: #527d59;
    font-weight: 700;
}

.status-dot {
    width: 6px;
    height: 6px;

    border-radius: 50%;

    background: #3c8b50;
}


/* =========================================================
   STOCK FOOTER
========================================================= */

.stock-footer {
    padding: 5px 12px 13px;
}

.stock-info {
    display: flex;
    align-items: center;
    gap: 8px;
    color: #8a9089;
    font-size: 11px;
    margin-bottom: 12px;
}

.stock-info i {
    color: #3f8050;

    font-size: 10px;
}

.reservation-button {
    width: 100%;
    max-width: 220px;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 9px;
    padding: 12px 18px;
    background: linear-gradient(
        135deg,
        #527d59,
        #315f4a
    );
    color: #ffffff;
    border-radius: 8px;
    text-decoration: none;
    font-size: 13px;
    font-weight: 600;
}

.reservation-button:hover {
    background: #174b27;

    transform: translateY(-1px);
}


/* =========================================================
   ACTIVITY LIST
========================================================= */

.activity-list {
    padding: 0 13px;

    max-height: 350px;

    overflow-y: auto;
}

.activity-list::-webkit-scrollbar {
    width: 4px;
}

.activity-list::-webkit-scrollbar-thumb {
    background: #d4ddd6;

    border-radius: 10px;
}

.activity-item {
    min-height: 53px;

    display: flex;
    align-items: center;

    gap: 9px;

    border-bottom: 1px solid #edf0ed;
}

.activity-item:last-child {
    border-bottom: none;
}

.activity-icon {
    width: 31px;
    height: 31px;

    min-width: 31px;

    border-radius: 50%;

    display: flex;
    align-items: center;
    justify-content: center;

    font-size: 11px;
}

.activity-icon.confirmed {
    background: #e8f4e9;
    color: #3b8a4d;
}

.activity-icon.completed {
    background: #e7f1f4;
    color: #3b7587;
}

.activity-icon.cancelled {
    background: #f9e9e7;
    color: #c75a4f;
}

.activity-icon.pending {
    background: #fff1dc;
    color: #ba7b20;
}

.activity-icon.default {
    background: #edf1f1;
    color: #60736b;
}

.activity-content {
    flex: 1;

    min-width: 0;
}

.activity-content h4 {
    margin: 0 0 4px;
    font-size: 13px;
    color: #4d5851;
    font-weight: 700;
    line-height: 1.25;
}

.activity-content p {
    margin: 0;
    font-size: 11px;
    color: #8a9089;
    line-height: 1.25;
}

.activity-time {
    font-size: 11px;
    color: #969a94;
    white-space: nowrap;
}


/* =========================================================
   EMPTY STATE
========================================================= */

.empty-state {
    padding: 30px 15px;

    text-align: center;

    color: #929d96;
}

.empty-state i {
    display: block;

    margin-bottom: 8px;

    font-size: 27px;

    color: #cbd4cd;
}

.empty-state p {
    margin: 0;

    font-size: 10px;
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
   RESPONSIVE
========================================================= */

@media screen and (max-width: 1200px) {

    .customer-navbar {
        padding: 0 30px;
    }

    .customer-navbar-center {
        gap: 25px;
        margin-left: 30px;
    }

    .customer-page,
    .customer-hero-content {
        width: calc(100% - 60px);
    }

    .customer-hero-content {
        padding-left: 60px;
    }

    .dashboard-grid {
        grid-template-columns: 1fr;
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

    .customer-hero {
        height: 145px;
    }

    .customer-hero-content {
        width: calc(100% - 44px);
        padding-left: 30px;
    }

    .customer-hero-title {
        font-size: 27px;
    }

    .customer-page {
        width: calc(100% - 44px);
    }

    .summary-grid {
        grid-template-columns: 1fr;
    }

    .customer-footer {
        padding: 16px 22px;
    }

    .customer-footer-inner {
        gap: 15px;
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
    }

    .customer-hero {
        height: 135px;

        background-position: center;
    }

    .customer-hero-content {
        width: calc(100% - 30px);
        padding-left: 15px;
    }

    .customer-hero-title {
        font-size: 25px;
    }

    .customer-hero-small {
        font-size: 12px;
    }

    .customer-hero-subtitle {
        font-size: 10px;
    }

    .customer-page {
        width: calc(100% - 30px);

        padding-top: 12px;
    }

    .dashboard-grid {
        grid-template-columns: 1fr;
    }

    .stock-table {
        min-width: 500px;
    }

    .customer-footer-inner {
        flex-direction: column;

        align-items: flex-start;
    }

    .customer-footer-right {
        width: 100%;

        justify-content: space-between;
    }
}


/* =========================================
   CUSTOMER DASHBOARD - MAIN OUTER CARD
========================================= */

.customer-outer-card {
    width: calc(100% - 30px);
    max-width: 1740px;
    margin: 10px auto 10px;

    background: #ffffff;
    border: 1px solid #dedbd2;
    border-radius: 26px;

    overflow: hidden;
    box-shadow: 0 8px 24px rgba(35, 55, 38, 0.08);
}

/* HERO BUILT INTO THE OUTER CARD */

.customer-outer-card .customer-hero {
    width: 100%;
    max-width: none;
    margin: 0;

    border-radius: 0;
    overflow: hidden;
}

/* PAGE CONTENT INSIDE THE SAME CARD */

.customer-outer-card .customer-page {
    width: 100%;
    max-width: none;
    margin: 0;

    padding: 24px 28px 30px;
    background: transparent;
}

/* RESPONSIVE OUTER CARD */

@media (max-width: 768px) {
    .customer-outer-card {
        width: calc(100% - 28px);
        margin: 18px auto 24px;
        border-radius: 20px;
    }

    .customer-outer-card .customer-page {
        padding: 18px 14px 22px;
    }
}

@media (max-width: 480px) {
    .customer-outer-card {
        width: calc(100% - 20px);
        border-radius: 16px;
    }

    .customer-outer-card .customer-page {
        padding: 14px 10px 18px;
    }
}


</style>

    <link rel="stylesheet" href="customer_navbar.css?v=20261022_viewall">
</head>

<body>

<!-- =========================================================
     CUSTOMER NAVBAR (STANDARDIZED)
========================================================= -->
<?php require 'customer_header.php'; ?>



<!-- =========================================================
     WELCOME HERO
========================================================= -->
<div class="customer-outer-card">
<section class="customer-hero">

    <div class="customer-hero-content">

     


        <h1 class="customer-hero-title">
 Welcome back,
            <?php
            echo htmlspecialchars($first_name);
            ?>!

        </h1>


        <p class="customer-hero-subtitle">

            Fresh eggs, trusted farm, better for you.

        </p>

    </div>

</section>



<!-- =========================================================
     MAIN CUSTOMER CONTENT
========================================================= -->

<main class="customer-page">


    <!-- =====================================================
         SUMMARY CARDS
    ====================================================== -->

    <section class="summary-grid">


        <!-- ACCOUNT TYPE -->

        <div class="summary-card">

            <div class="summary-icon account">

                <i class="fa-regular fa-user"></i>

            </div>


            <div class="summary-content">

                <span>
                    Account Type
                </span>

                <h2>
                    Customer
                </h2>

                <small>
                    Regular Customer
                </small>

            </div>

        </div>



        <!-- TOTAL RESERVATIONS -->

        <div class="summary-card">

            <div class="summary-icon reservation">

                <i class="fa-regular fa-calendar-check"></i>

            </div>


            <div class="summary-content">

                <span>
                    Total Reservations
                </span>

                <h2>

                    <?php
                    echo $total_reservations;
                    ?>

                </h2>

                <small>
                    All time
                </small>

            </div>

        </div>



        <!-- TRAYS ORDERED -->

        <div class="summary-card">

            <div class="summary-icon trays">

                <i class="fa-solid fa-cube"></i>

            </div>


            <div class="summary-content">

                <span>
                    Trays Ordered
                </span>

                <h2>

                    <?php
                    echo $total_trays;
                    ?>

                </h2>

                <small>
                    All time
                </small>

            </div>

        </div>


    </section>



    <!-- =====================================================
         MAIN DASHBOARD GRID
    ====================================================== -->

    <section class="dashboard-grid">


        <!-- =================================================
             AVAILABLE EGG STOCKS
        ================================================== -->

        <div class="dashboard-card">


            <!-- CARD HEADER -->

            <div class="card-header">

                <div class="card-title">

                    <div class="card-title-icon">

                        <i class="fa-solid fa-egg"></i>

                    </div>


                    <div class="card-title-text">

                        <h2>
                            Available Egg Stocks
                        </h2>

                        <p>
                            Real-time availability for all egg sizes.
                        </p>

                    </div>

                </div>

            </div>



            <!-- STOCK TABLE -->

            <div class="stock-table-container">


                <?php if (count($egg_stocks) > 0) { ?>


                    <table class="stock-table">


                        <thead>

                            <tr>

                                <th>
                                    Egg Size
                                </th>

                                <th>
                                    Available Stock
                                </th>

                                <th>
                                    Unit
                                </th>

                                <th>
                                    Status
                                </th>

                            </tr>

                        </thead>


                        <tbody>


                            <?php foreach ($egg_stocks as $stock) { ?>


                                <?php

                                $available_stock =
                                    isset($stock['available_stock'])
                                    ? $stock['available_stock']
                                    : 0;

                                ?>


                                <tr>


                                    <!-- EGG SIZE -->

                                    <td>

                                        <strong>

                                            <?php
                                            echo htmlspecialchars(
                                                $stock['egg_size']
                                            );
                                            ?>

                                        </strong>

                                    </td>



                                    <!-- AVAILABLE STOCK -->

                                    <td>

                                        <span class="stock-number">

                                            <?php
                                            echo $available_stock;
                                            ?>

                                        </span>

                                    </td>



                                    <!-- UNIT -->

                                    <td>

                                        trays

                                    </td>



                                    <!-- STATUS -->

                                    <td>


                                        <?php if ($available_stock > 0) { ?>


                                            <span class="stock-status">

                                                <span class="status-dot"></span>

                                                Available

                                            </span>


                                        <?php } else { ?>


                                            <span
                                                style="
                                                    color:#c65b4e;
                                                    font-size:9px;
                                                    font-weight:700;
                                                "
                                            >

                                                <i
                                                    class="fa-solid fa-circle"
                                                    style="
                                                        font-size:6px;
                                                    "
                                                ></i>

                                                Out of Stock

                                            </span>


                                        <?php } ?>


                                    </td>


                                </tr>


                            <?php } ?>


                        </tbody>


                    </table>


                <?php } else { ?>


                    <div class="empty-state">

                        <i class="fa-solid fa-box-open"></i>

                        <p>
                            No egg stocks are currently available.
                        </p>

                    </div>


                <?php } ?>


            </div>



            <!-- STOCK FOOTER -->

            <div class="stock-footer">


                <div class="stock-info">

                    <i class="fa-solid fa-circle-info"></i>

                    <span>
                        Stocks are updated in real-time.
                        Reserve now to secure your preferred stock.
                    </span>

                </div>


                <a
                    href="customer_reservation.php"
                    class="reservation-button"
                >

                    <i class="fa-regular fa-calendar-plus"></i>

                    Make a Reservation

                </a>


            </div>


        </div>



        <!-- =================================================
             RECENT ACTIVITY
        ================================================== -->

        <div class="dashboard-card">


            <!-- CARD HEADER -->

            <div class="card-header">


                <div class="card-title">

                    <div class="card-title-icon">

                        <i class="fa-solid fa-clock-rotate-left"></i>

                    </div>


                    <div class="card-title-text">

                        <h2>
                            Recent Activity
                        </h2>

                    </div>

                </div>


                <a
                    href="customer_history.php"
                    class="view-all-btn"
                >

                    View All

                </a>


            </div>



            <!-- ACTIVITY -->

            <div class="activity-list">


                <?php if (count($recent_activities) > 0) { ?>


                    <?php foreach ($recent_activities as $activity) { ?>


                        <?php

                        $icon_data =
                            getActivityIcon(
                                $activity['status']
                            );

                        ?>


                        <div class="activity-item">


                            <!-- ICON -->

                            <div
                                class="
                                    activity-icon
                                    <?php
                                    echo $icon_data['class'];
                                    ?>
                                "
                            >

                                <i
                                    class="
                                        fa-solid
                                        <?php
                                        echo $icon_data['icon'];
                                        ?>
                                    "
                                ></i>

                            </div>



                            <!-- CONTENT -->

                            <div class="activity-content">


                                <h4>

                                    Reservation
                                    <?php
                                    echo htmlspecialchars(
                                        $activity['reservation_code']
                                    );
                                    ?>

                                    <?php
                                    echo htmlspecialchars(
                                        strtolower(
                                            $activity['status']
                                        )
                                    );
                                    ?>

                                </h4>


                                <p>


                                    <?php

                                    if (
                                        !empty(
                                            $activity['reserved_at']
                                        )
                                    ) {

                                        echo date(
                                            "M d, Y",
                                            strtotime(
                                                $activity['reserved_at']
                                            )
                                        );

                                    }

                                    ?>


                                    &nbsp;•&nbsp;


                                    <?php
                                    echo $activity['quantity'];
                                    ?>

                                    trays


                                    (
                                    <?php
                                    echo htmlspecialchars(
                                        $activity['egg_size']
                                    );
                                    ?>
                                    )

                                </p>


                            </div>



                            <!-- TIME -->

                            <div class="activity-time">


                                <?php

                                if (
                                    !empty(
                                        $activity['reserved_at']
                                    )
                                ) {

                                    echo date(
                                        "g:i A",
                                        strtotime(
                                            $activity['reserved_at']
                                        )
                                    );

                                }

                                ?>


                            </div>


                        </div>


                    <?php } ?>


                <?php } else { ?>


                    <div class="empty-state">

                        <i class="fa-regular fa-calendar"></i>

                        <p>
                            No recent reservation activity.
                        </p>

                    </div>


                <?php } ?>


            </div>


        </div>


    </section>


</main>
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

// Mark All as Read function for dropdown header
function markAllCustomerAsRead(event)
{
    if (event) {
        event.preventDefault();
        event.stopPropagation();
    }

    var formData = new FormData();
    formData.append('mark_all_read', '1');
    formData.append('ajax', '1');

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
   CLOSE MENUS WHEN CLICKING OUTSIDE
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

    if (
        modal &&
        event.target === modal
    ) {
        closeCustomerLogoutModal();
    }
});

/* =========================================================
   CLOSE LOGOUT MODAL WITH ESCAPE
========================================================= */

document.addEventListener('keydown', function(event) {

    if (event.key === 'Escape') {
        closeCustomerLogoutModal();
        closeCustomerAccountDropdown();
    }
});

</script>

<script src="shared_notifications.js?v=20261022"></script>
<script src="customer_scripts.js?v=20261022_2"></script>

<script>
// Load notifications using shared system
document.addEventListener('DOMContentLoaded', function() {
    var dropdown = document.getElementById('customerNotificationDropdown');
    var list = document.getElementById('customerNotificationList');
    
    if (dropdown && list) {
        fetch('customer_notifications.php?action=json')
            .then(function(response) { return response.json(); })
            .then(function(data) {
                var notifications = data.notifications || [];
                if (notifications.length === 0) {
                    list.innerHTML = '<div class="customer-notification-empty"><i class="fa-regular fa-bell-slash"></i><p>No notifications</p></div>';
                } else {
                    var html = '';
                    notifications.forEach(function(n) {
                        var iconClass = n.type || 'info';
                        var iconName = 'fa-bell';
                        var isUnread = !n.is_read;
                        var time = n.created_at;
                        
                        // Map notification types to icons
                        if (n.type === 'success') {
                            iconClass = 'success';
                            iconName = 'fa-check';
                        } else if (n.type === 'alert') {
                            iconClass = 'alert';
                            iconName = 'fa-exclamation';
                        } else if (n.type === 'warning') {
                            iconClass = 'warning';
                            iconName = 'fa-circle-exclamation';
                        }
                        
                        html += '<div class="customer-notification-item' + (isUnread ? ' unread' : '') + '" data-id="' + n.id + '">';
                        html += '<div class="customer-notification-icon ' + iconClass + '"><i class="fa-solid ' + iconName + '"></i></div>';
                        html += '<div class="customer-notification-content">';
                        html += '<div class="customer-notification-title">' + n.title + '</div>';
                        html += '<div class="customer-notification-description">' + n.description + '</div>';
                        html += '<div class="customer-notification-time">' + time + '</div>';
                        html += '</div>';
                        html += '</div>';
                    });
                    list.innerHTML = html;
                    
                    // Add click handlers for marking as read
                    var items = list.querySelectorAll('.customer-notification-item.unread');
                    items.forEach(function(item) {
                        item.addEventListener('click', function() {
                            var notifId = item.getAttribute('data-id');
                            markSingleNotificationAsRead(notifId, item);
                        });
                    });
                }
            })
            .catch(function(error) {
                list.innerHTML = '<div class="customer-notification-empty"><i class="fa-solid fa-exclamation-circle"></i><p>Failed to load notifications</p></div>';
            });
    }
});

// Mark single notification as read
function markSingleNotificationAsRead(notificationId, itemElement) {
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
            itemElement.classList.remove('unread');
            itemElement.classList.add('read');
            
            // Update badge count
            var badge = document.querySelector('.customer-notification-badge');
            if (badge) {
                var currentCount = parseInt(badge.textContent);
                if (!isNaN(currentCount) && currentCount > 1) {
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
</script>

<?php require 'shared_notifications_modal.php'; ?>

<!-- =========================================================
     LOGOUT CONFIRMATION MODAL
========================================================= -->

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

                <input
                    type="hidden"
                    name="customer_logout"
                    value="1"
                >

                <input
                    type="hidden"
                    name="logout_token"
                    value="<?php echo htmlspecialchars(
                        $_SESSION['customer_logout_token'],
                        ENT_QUOTES,
                        'UTF-8'
                    ); ?>"
                >

                <button
                    type="submit"
                    class="customer-logout-confirm"
                >
                    Yes, Logout
                </button>

            </form>

        </div>

    </div>

</div>

</body>

</html>