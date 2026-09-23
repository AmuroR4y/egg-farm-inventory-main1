<?php
// Include standardized session configuration FIRST
require_once 'session_config.php';

require 'db.php';
// Helper function to insert notifications
function add_notification($conn, $user_id, $title, $description, $type = 'info') {
    $stmt = $conn->prepare("INSERT INTO notifications (user_id, title, description, type, is_read) VALUES (?, ?, ?, ?, 0)");
    $stmt->bind_param("isss", $user_id, $title, $description, $type);
    $stmt->execute();
    $stmt->close();
}

// Helper function to get customer user_id from reservation
function getCustomerUserId($conn, $reservation_code) {
    $stmt = $conn->prepare("SELECT user_id FROM reservations WHERE reservation_code = ? LIMIT 1");
    $stmt->bind_param("s", $reservation_code);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    return $row ? $row['user_id'] : null;
}
date_default_timezone_set('Asia/Manila');

/*
|--------------------------------------------------------------------------
| SECURITY
|--------------------------------------------------------------------------
*/

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Allow both manager and owner roles
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['manager', 'owner'])) {
    header("Location: login.php");
    exit();
}

/*
|--------------------------------------------------------------------------
| STATUS UPDATE + DELIVERY SCHEDULING
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action = isset($_POST['action']) ? $_POST['action'] : '';
    $reservation_code = isset($_POST['reservation_code'])
        ? trim($_POST['reservation_code'])
        : '';

    if ($reservation_code !== '') {

     if ($action === 'confirm') {

    // Start transaction
    $conn->autocommit(false);

    // Initialize diagnostic variables for exception handler
    $diagnostic_egg_type = null;
    $diagnostic_egg_size = null;
    $diagnostic_quantity = null;
    $diagnostic_new_stock = null;

    try {
        // Get customer user_id before updating
        $customer_id = getCustomerUserId($conn, $reservation_code);

        // Lock and check reservation row to prevent concurrent confirmations
        $lock_stmt = $conn->prepare(
            "SELECT status FROM reservations
             WHERE reservation_code = ?
             LIMIT 1
             FOR UPDATE"
        );

        if (!$lock_stmt) {
            throw new Exception("Lock query prepare failed: " . $conn->error);
        }

        $lock_stmt->bind_param("s", $reservation_code);
        $lock_stmt->execute();
        $lock_result = $lock_stmt->get_result();
        $lock_row = $lock_result->fetch_assoc();
        $lock_stmt->close();

        // Verify reservation exists and is Pending
        if (!$lock_row) {
            throw new Exception("Reservation not found.");
        }

        if ($lock_row['status'] !== 'Pending') {
            throw new Exception("Reservation is not in Pending status. Current status: " . $lock_row['status']);
        }

        // Get all reservation items (egg_type, quantity)
        $items_stmt = $conn->prepare(
            "SELECT egg_type, quantity
             FROM reservations
             WHERE reservation_code = ?"
        );

        if (!$items_stmt) {
            throw new Exception("Items query prepare failed: " . $conn->error);
        }

        $items_stmt->bind_param("s", $reservation_code);
        $items_stmt->execute();
        $items_result = $items_stmt->get_result();

        $reservation_items = array();
        while ($item_row = $items_result->fetch_assoc()) {
            $reservation_items[] = $item_row;
        }
        $items_stmt->close();

        // Process each item: validate stock and deduct
        $current_date = date('Y-m-d H:i:s');
        $today_str = date('Ymd');
        $batch_prefix = "EG" . $today_str . "-";

        // Get next batch number for today
        $seq_stmt = $conn->prepare(
            "SELECT batch_id FROM egg_inventory
             WHERE batch_id LIKE ?
             ORDER BY id DESC
             LIMIT 1"
        );

        if (!$seq_stmt) {
            throw new Exception("Batch sequence query prepare failed: " . $conn->error);
        }

        $seq_prefix = $batch_prefix . "%";
        $seq_stmt->bind_param("s", $seq_prefix);
        $seq_stmt->execute();
        $seq_result = $seq_stmt->get_result();
        $seq_row = $seq_result->fetch_assoc();
        $seq_stmt->close();

        if ($seq_row) {
            $last_num = (int)substr($seq_row['batch_id'], -3);
            $next_num = str_pad($last_num + 1, 3, '0', STR_PAD_LEFT);
        } else {
            $next_num = "001";
        }

        foreach ($reservation_items as $item) {
            $egg_type = trim($item['egg_type']); // Trim any whitespace
            $quantity = intval($item['quantity']);

            // Store for diagnostic display
            $diagnostic_egg_type = $egg_type;
            $diagnostic_quantity = $quantity;

            // CRITICAL DEBUG: Log original egg_type with full details
            error_log("CRITICAL: Original egg_type from reservation: " . var_export($egg_type, true));
            error_log("CRITICAL: Original egg_type strlen: " . strlen($egg_type));
            error_log("CRITICAL: Original egg_type hex: " . bin2hex($egg_type));

            // Map reservation egg_type names to inventory enum values
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

            // Check if the egg_type exists in the mapping
            if (!isset($egg_size_map[$egg_type])) {
                error_log("CRITICAL: Egg type not found in mapping: " . var_export($egg_type, true));
                throw new Exception("Invalid egg type: '" . $egg_type . "'. Valid types are: " . implode(', ', array_keys($egg_size_map)));
            }

            $egg_size = $egg_size_map[$egg_type];

            // Store for diagnostic display
            $diagnostic_egg_size = $egg_size;

            // CRITICAL DEBUG: Log mapped egg_size with full details
            error_log("CRITICAL: Mapped egg_size for inventory: " . var_export($egg_size, true));
            error_log("CRITICAL: Mapped egg_size strlen: " . strlen($egg_size));
            error_log("CRITICAL: Mapped egg_size hex: " . bin2hex($egg_size));

            // Lock and get latest current stock for this egg size
            $stock_stmt = $conn->prepare(
                "SELECT current_stock FROM egg_inventory
                 WHERE egg_size = ?
                 ORDER BY id DESC
                 LIMIT 1
                 FOR UPDATE"
            );

            if (!$stock_stmt) {
                throw new Exception("Stock query prepare failed: " . $conn->error);
            }

            // DEBUG: Log stock query with mapped value
            error_log("DEBUG: Stock query with egg_size: '" . $egg_size . "'");

            $stock_stmt->bind_param("s", $egg_size);
            $stock_stmt->execute();
            $stock_result = $stock_stmt->get_result();
            $stock_row = $stock_result->fetch_assoc();
            $stock_stmt->close();

            $current_stock = $stock_row ? intval($stock_row['current_stock']) : 0;

            // DEBUG: Log current stock found
            error_log("DEBUG: Current stock found: " . $current_stock);

            // Validate sufficient stock
            if ($current_stock < $quantity) {
                throw new Exception(
                    "Insufficient stock for {$egg_type} ({$egg_size}). Available: {$current_stock}, Required: {$quantity}"
                );
            }

            // Calculate new stock
            $new_stock = $current_stock - $quantity;

            // Store for diagnostic display
            $diagnostic_new_stock = $new_stock;

            // Generate batch ID for this deduction
            $batch_id = $batch_prefix . $next_num;
            $next_num = str_pad(intval($next_num) + 1, 3, '0', STR_PAD_LEFT);

            // Insert Stock Out ledger record
            $insert_stmt = $conn->prepare(
                "INSERT INTO egg_inventory
                 (batch_id, harvest_date, harvest_time, egg_size, quantity, current_stock, movement_type, reason, date_logged)
                 VALUES (?, ?, ?, ?, ?, ?, 'Stock Out', 'Reservation', ?)"
            );

            // DEBUG: Log the exact prepared statement
            error_log("CRITICAL: Prepared statement: INSERT INTO egg_inventory (batch_id, harvest_date, harvest_time, egg_size, quantity, current_stock, movement_type, reason, date_logged) VALUES (?, ?, ?, ?, ?, ?, 'Stock Out', 'Reservation', ?)");
            error_log("CRITICAL: Expected bind_param types: s(s) for batch_id, s(s) for harvest_date, s(s) for harvest_time, s(s) for egg_size, i(i) for quantity, i(i) for current_stock, s(s) for date_logged");
            error_log("CRITICAL: Total placeholders: 7");
            error_log("CRITICAL: Current bind_param string: 'sssiiss'");

            if (!$insert_stmt) {
                throw new Exception("Inventory insert prepare failed: " . $conn->error);
            }

            // DEBUG: Log values before bind_param
            error_log("DEBUG: Before bind_param - batch_id: '" . $batch_id . "', egg_size: '" . $egg_size . "', quantity: " . $quantity . ", new_stock: " . $new_stock . ", current_date: '" . $current_date . "'");

            // EXPLICIT TYPE CHECKING
            error_log("DEBUG: Type check - batch_id type: " . gettype($batch_id));
            error_log("DEBUG: Type check - egg_size type: " . gettype($egg_size));
            error_log("DEBUG: Type check - quantity type: " . gettype($quantity));
            error_log("DEBUG: Type check - new_stock type: " . gettype($new_stock));
            error_log("DEBUG: Type check - current_date type: " . gettype($current_date));

            // Assign date values to variables to avoid "Only variables should be passed by reference" notice
            $harvest_date = date('Y-m-d');
            $harvest_time = date('H:i:s');

            // CREATE EXPLICIT DEBUG VARIABLES FOR bind_param
            $debug_batch_id = $batch_id;
            $debug_harvest_date = $harvest_date;
            $debug_harvest_time = $harvest_time;
            $debug_egg_size = $egg_size;
            $debug_quantity = $quantity;
            $debug_current_stock = $new_stock;
            $debug_date_logged = $current_date;

            // LOG ALL DEBUG VALUES WITH FULL DETAILS
            error_log("CRITICAL: DEBUG bind_param values:");
            error_log("CRITICAL: Parameter 1 (batch_id): " . var_export($debug_batch_id, true) . " - Type: " . gettype($debug_batch_id));
            error_log("CRITICAL: Parameter 2 (harvest_date): " . var_export($debug_harvest_date, true) . " - Type: " . gettype($debug_harvest_date));
            error_log("CRITICAL: Parameter 3 (harvest_time): " . var_export($debug_harvest_time, true) . " - Type: " . gettype($debug_harvest_time));
            error_log("CRITICAL: Parameter 4 (egg_size): " . var_export($debug_egg_size, true) . " - Type: " . gettype($debug_egg_size));
            error_log("CRITICAL: Parameter 5 (quantity): " . var_export($debug_quantity, true) . " - Type: " . gettype($debug_quantity));
            error_log("CRITICAL: Parameter 6 (current_stock): " . var_export($debug_current_stock, true) . " - Type: " . gettype($debug_current_stock));
            error_log("CRITICAL: Parameter 7 (date_logged): " . var_export($debug_date_logged, true) . " - Type: " . gettype($debug_date_logged));

            // CRITICAL: Verify the bind_param type string matches the 7 placeholders
            error_log("CRITICAL: INSERT has 7 placeholders: batch_id, harvest_date, harvest_time, egg_size, quantity, current_stock, date_logged");
            error_log("CRITICAL: Expected bind_param type string: 'ssssiis' (7 characters)");
            error_log("CRITICAL: Type breakdown: s=string(batch_id), s=string(harvest_date), s=string(harvest_time), s=string(egg_size), i=integer(quantity), i=integer(current_stock), s=string(date_logged)");
            error_log("CRITICAL: Actual bind_param type string being used: 'ssssiis'");

            $insert_stmt->bind_param(
                "ssssiis",
                $debug_batch_id,
                $debug_harvest_date,
                $debug_harvest_time,
                $debug_egg_size,
                $debug_quantity,
                $debug_current_stock,
                $debug_date_logged
            );

            // DEBUG: Log bind_param result
            error_log("DEBUG: bind_param completed successfully");

            // EXPLICIT VALIDATION BEFORE INSERT
            $valid_egg_sizes = [
                'XS',
                'Small',
                'Medium',
                'Large',
                'XL',
                'Jumbo',
                'Super Jumbo',
                'Double Yolk'
            ];

            if (!in_array($egg_size, $valid_egg_sizes, true)) {
                error_log("CRITICAL: INVALID MAPPED EGG SIZE BEFORE INSERT: " . var_export($egg_size, true));
                error_log("CRITICAL: strlen: " . strlen($egg_size));
                error_log("CRITICAL: hex: " . bin2hex($egg_size));
                throw new Exception(
                    "INVALID MAPPED EGG SIZE: " . var_export($egg_size, true) . 
                    ". Valid sizes are: " . implode(', ', $valid_egg_sizes)
                );
            }

            error_log("CRITICAL: VALIDATION PASSED - egg_size: " . var_export($egg_size, true));

            // DEBUG: Log before execute
            error_log("DEBUG: About to execute INSERT statement");

            if (!$insert_stmt->execute()) {
                // DEBUG: Log the actual MySQL error
                error_log("DEBUG: INSERT failed. MySQL error: " . $insert_stmt->error);
                throw new Exception("Inventory insert failed: " . $insert_stmt->error);
            }

            // DEBUG: Log successful execution
            error_log("DEBUG: INSERT statement executed successfully");

            $insert_stmt->close();
        }

        // Update reservation status to Confirmed
        $update_stmt = $conn->prepare(
            "UPDATE reservations
             SET status = 'Confirmed'
             WHERE reservation_code = ?
             AND status = 'Pending'"
        );

        if (!$update_stmt) {
            throw new Exception("Status update prepare failed: " . $conn->error);
        }

        $update_stmt->bind_param("s", $reservation_code);
        $update_stmt->execute();
        $update_stmt->close();

        // Notify customer
        if ($customer_id) {
            add_notification(
                $conn,
                $customer_id,
                "Reservation Confirmed!",
                "Your reservation {$reservation_code} has been confirmed by the farm manager.",
                "success"
            );
        }

        // Commit transaction
        $conn->commit();

        header(
            "Location: manager_reservation.php?view=" .
            urlencode($reservation_code) .
            "&updated=confirmed"
        );
        exit();

    } catch (Exception $e) {
        // Rollback on any error
        $conn->rollback();

        // Store error message in session for display
        $_SESSION['error_message'] = $e->getMessage();

        header(
            "Location: manager_reservation.php?view=" .
            urlencode($reservation_code) .
            "&error=confirm_failed"
        );
        exit();
    }
}

     elseif ($action === 'cancel') {

    // Get customer user_id before updating
    $customer_id = getCustomerUserId($conn, $reservation_code);

    $stmt = $conn->prepare(
        "UPDATE reservations
         SET status = 'Cancelled'
         WHERE reservation_code = ?
         AND status NOT IN ('Cancelled', 'Completed')"
    );

    if (!$stmt) {
        die("Cancel query prepare failed: " . $conn->error);
    }

    $stmt->bind_param("s", $reservation_code);
    $stmt->execute();
    $stmt->close();

    // Notify customer
    if ($customer_id) {
        add_notification(
            $conn,
            $customer_id,
            "Reservation Cancelled",
            "Your reservation {$reservation_code} has been cancelled by the farm manager.",
            "alert"
        );
    }

    header(
        "Location: manager_reservation.php?view=" .
        urlencode($reservation_code) .
        "&updated=cancelled"
    );
    exit();
}

       elseif ($action === 'update_status') {

    $new_status = isset($_POST['new_status'])
        ? trim($_POST['new_status'])
        : '';

    if (
        $new_status === 'Pending' ||
        $new_status === 'Confirmed' ||
        $new_status === 'Completed' ||
        $new_status === 'Cancelled'
    ) {

        // Get customer user_id before updating
        $customer_id = getCustomerUserId($conn, $reservation_code);

        $stmt = $conn->prepare(
            "UPDATE reservations
             SET status = ?
             WHERE reservation_code = ?"
        );

        if (!$stmt) {
            die("Status query prepare failed: " . $conn->error);
        }

        $stmt->bind_param(
            "ss",
            $new_status,
            $reservation_code
        );

        $stmt->execute();
        $stmt->close();

        // Notify customer
        if ($customer_id) {
            $status_message = "";
            $notification_type = "info";
            
            if ($new_status === 'Confirmed') {
                $status_message = "Your reservation {$reservation_code} has been confirmed!";
                $notification_type = "success";
            } elseif ($new_status === 'Completed') {
                $status_message = "Your reservation {$reservation_code} has been marked as completed.";
                $notification_type = "success";
            } elseif ($new_status === 'Cancelled') {
                $status_message = "Your reservation {$reservation_code} has been cancelled.";
                $notification_type = "alert";
            } else {
                $status_message = "Your reservation {$reservation_code} status has been updated to: {$new_status}";
                $notification_type = "info";
            }

            add_notification(
                $conn,
                $customer_id,
                "Status Updated: {$new_status}",
                $status_message,
                $notification_type
            );
        }
    }

    header(
        "Location: manager_reservation.php?view=" .
        urlencode($reservation_code)
    );
    exit();
}
       elseif ($action === 'schedule_delivery') {

    $delivery_date = isset($_POST['delivery_date'])
        ? trim($_POST['delivery_date'])
        : '';

    if ($delivery_date !== '') {

        // Get customer user_id before updating
        $customer_id = getCustomerUserId($conn, $reservation_code);

        $stmt = $conn->prepare(
            "UPDATE reservations
             SET reservation_date = ?
             WHERE reservation_code = ?
             AND delivery_method = 'Delivery'
             AND status NOT IN ('Cancelled', 'Completed')"
        );

        if (!$stmt) {
            die(
                "Delivery schedule query prepare failed: " .
                $conn->error
            );
        }

        $stmt->bind_param(
            "ss",
            $delivery_date,
            $reservation_code
        );

        $stmt->execute();
        $stmt->close();

        // Notify customer
        if ($customer_id) {
            $formatted_date = date('F d, Y', strtotime($delivery_date));
            add_notification(
                $conn,
                $customer_id,
                "Delivery Date Scheduled",
                "Your delivery for reservation {$reservation_code} has been scheduled on {$formatted_date}.",
                "success"
            );
        }
    }

    header(
        "Location: manager_reservation.php?view=" .
        urlencode($reservation_code) .
        "&updated=scheduled"
    );
    exit();
}
    }
}

/*
|--------------------------------------------------------------------------
| FILTERS
|--------------------------------------------------------------------------
*/

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$date_from = isset($_GET['date_from']) ? trim($_GET['date_from']) : '';
$date_to = isset($_GET['date_to']) ? trim($_GET['date_to']) : '';

/*
|--------------------------------------------------------------------------
| WHERE CONDITION
|--------------------------------------------------------------------------
*/

$where = array();
$params = array();
$types = '';

if ($search !== '') {

    $where[] = "(
        reservation_code LIKE ?
        OR customer_name LIKE ?
        OR contact_number LIKE ?
        OR reservation_date LIKE ?
        OR delivery_method LIKE ?
        OR status LIKE ?
    )";

    $search_value = '%' . $search . '%';

    $params[] = $search_value;
    $params[] = $search_value;
    $params[] = $search_value;
    $params[] = $search_value;
    $params[] = $search_value;
    $params[] = $search_value;

    $types .= 'ssssss';
}

if ($date_from !== '') {

    $where[] = "DATE(reserved_at) >= ?";
    $params[] = $date_from;
    $types .= 's';
}

if ($date_to !== '') {

    $where[] = "DATE(reserved_at) <= ?";
    $params[] = $date_to;
    $types .= 's';
}

$where_sql = '';

if (count($where) > 0) {
    $where_sql = 'WHERE ' . implode(' AND ', $where);
}

/*
|--------------------------------------------------------------------------
| COUNT FILTERED RESERVATIONS
|--------------------------------------------------------------------------
*/

$count_sql = "
    SELECT COUNT(DISTINCT reservation_code) AS total
    FROM reservations
    " . $where_sql;

$count_stmt = $conn->prepare($count_sql);

if (!$count_stmt) {
    die("Count query prepare failed: " . $conn->error);
}

if ($types !== '') {

    if (count($params) == 1) {

        $count_stmt->bind_param(
            $types,
            $params[0]
        );

    } elseif (count($params) == 2) {

        $count_stmt->bind_param(
            $types,
            $params[0],
            $params[1]
        );

    } elseif (count($params) == 3) {

        $count_stmt->bind_param(
            $types,
            $params[0],
            $params[1],
            $params[2]
        );

    } elseif (count($params) == 4) {

        $count_stmt->bind_param(
            $types,
            $params[0],
            $params[1],
            $params[2],
            $params[3]
        );

    } elseif (count($params) == 5) {

        $count_stmt->bind_param(
            $types,
            $params[0],
            $params[1],
            $params[2],
            $params[3],
            $params[4]
        );

    } elseif (count($params) == 6) {

        $count_stmt->bind_param(
            $types,
            $params[0],
            $params[1],
            $params[2],
            $params[3],
            $params[4],
            $params[5]
        );

    } elseif (count($params) == 7) {

        $count_stmt->bind_param(
            $types,
            $params[0],
            $params[1],
            $params[2],
            $params[3],
            $params[4],
            $params[5],
            $params[6]
        );
    }
}

if (!$count_stmt->execute()) {
    die("Count query execute failed: " . $count_stmt->error);
}

$count_result = $count_stmt->get_result();
$count_row = $count_result->fetch_assoc();
$filtered_total = (int)$count_row['total'];

$count_stmt->close();

/*
|--------------------------------------------------------------------------
| RESERVATION LIST
|--------------------------------------------------------------------------
*/

$list_sql = "
    SELECT
        reservation_code,
        MAX(customer_name) AS customer_name,
        MAX(contact_number) AS contact_number,
        MIN(reservation_date) AS reservation_date,
        MAX(delivery_method) AS delivery_method,
        MAX(status) AS status,
        MAX(reserved_at) AS reserved_at,
        SUM(quantity) AS total_trays,
        SUM(total_price) AS total_amount
    FROM reservations
    " . $where_sql . "
    GROUP BY reservation_code
    ORDER BY MAX(reserved_at) DESC
";

$list_stmt = $conn->prepare($list_sql);

if (!$list_stmt) {
    die("List query prepare failed: " . $conn->error);
}

if ($types !== '') {

    if (count($params) == 1) {
        $list_stmt->bind_param($types, $params[0]);

    } elseif (count($params) == 2) {
        $list_stmt->bind_param($types, $params[0], $params[1]);

    } elseif (count($params) == 3) {
        $list_stmt->bind_param(
            $types,
            $params[0],
            $params[1],
            $params[2]
        );

    } elseif (count($params) == 4) {
        $list_stmt->bind_param(
            $types,
            $params[0],
            $params[1],
            $params[2],
            $params[3]
        );

    } elseif (count($params) == 5) {
        $list_stmt->bind_param(
            $types,
            $params[0],
            $params[1],
            $params[2],
            $params[3],
            $params[4]
        );

    } elseif (count($params) == 6) {
        $list_stmt->bind_param(
            $types,
            $params[0],
            $params[1],
            $params[2],
            $params[3],
            $params[4],
            $params[5]
        );

    } elseif (count($params) == 7) {
        $list_stmt->bind_param(
            $types,
            $params[0],
            $params[1],
            $params[2],
            $params[3],
            $params[4],
            $params[5],
            $params[6]
        );

    } elseif (count($params) == 8) {
        $list_stmt->bind_param(
            $types,
            $params[0],
            $params[1],
            $params[2],
            $params[3],
            $params[4],
            $params[5],
            $params[6],
            $params[7]
        );
    }
}

if (!$list_stmt->execute()) {
    die("List query execute failed: " . $list_stmt->error);
}

$list_result = $list_stmt->get_result();

$reservations = array();

while ($row = $list_result->fetch_assoc()) {
    $reservations[] = $row;
}
$list_stmt->close();


/*
|--------------------------------------------------------------------------
| NOTIFICATIONS
|--------------------------------------------------------------------------
|
| Notifications are based on actual Pending reservations.
| No hardcoded notification count is used.
|--------------------------------------------------------------------------
*/

$notification_count = 0;
$notifications = array();

$notification_stmt = $conn->prepare(
    "SELECT
        reservation_code,
        MAX(customer_name) AS customer_name,
        MAX(reserved_at) AS reserved_at
     FROM reservations
     WHERE status = 'Pending'
     GROUP BY reservation_code
     ORDER BY MAX(reserved_at) DESC
     LIMIT 8"
);

if (!$notification_stmt) {
    die(
        "Notification query prepare failed: " .
        $conn->error
    );
}

if (!$notification_stmt->execute()) {
    die(
        "Notification query execute failed: " .
        $notification_stmt->error
    );
}

$notification_result = $notification_stmt->get_result();

while ($notification_row = $notification_result->fetch_assoc()) {

    $notifications[] = $notification_row;
}

$notification_stmt->close();


/*
|--------------------------------------------------------------------------
| NOTIFICATION COUNT
|--------------------------------------------------------------------------
*/

$notification_count_stmt = $conn->prepare(
    "SELECT COUNT(DISTINCT reservation_code) AS total
     FROM reservations
     WHERE status = 'Pending'"
);

if (!$notification_count_stmt) {
    die(
        "Notification count query prepare failed: " .
        $conn->error
    );
}

if (!$notification_count_stmt->execute()) {
    die(
        "Notification count query execute failed: " .
        $notification_count_stmt->error
    );
}

$notification_count_result =
    $notification_count_stmt->get_result();

$notification_count_row =
    $notification_count_result->fetch_assoc();

$notification_count =
    (int)$notification_count_row['total'];

$notification_count_stmt->close();


/*
|--------------------------------------------------------------------------
| SELECTED RESERVATION
|--------------------------------------------------------------------------
*/

$selected_code = isset($_GET['view'])
    ? trim($_GET['view'])
    : '';

if ($selected_code === '' && count($reservations) > 0) {
    $selected_code = $reservations[0]['reservation_code'];
}

/*
|--------------------------------------------------------------------------
| SELECTED RESERVATION DETAILS
|--------------------------------------------------------------------------
*/

$selected_reservation = null;
$selected_items = array();

if ($selected_code !== '') {

    $detail_stmt = $conn->prepare(
        "SELECT
            reservation_code,
            MAX(customer_name) AS customer_name,
            MAX(contact_number) AS contact_number,
            MIN(reservation_date) AS reservation_date,
            MAX(delivery_method) AS delivery_method,
            MAX(status) AS status,
            MAX(reserved_at) AS reserved_at,
            SUM(quantity) AS total_trays,
            SUM(total_price) AS total_amount
         FROM reservations
         WHERE reservation_code = ?
         GROUP BY reservation_code"
    );

    if (!$detail_stmt) {
        die("Detail query prepare failed: " . $conn->error);
    }

    $detail_stmt->bind_param("s", $selected_code);
    $detail_stmt->execute();

    $detail_result = $detail_stmt->get_result();

    if ($detail_result->num_rows > 0) {
        $selected_reservation = $detail_result->fetch_assoc();
    }

    $detail_stmt->close();

    $item_stmt = $conn->prepare(
        "SELECT
            egg_type,
            quantity,
            total_price
         FROM reservations
         WHERE reservation_code = ?
         ORDER BY id ASC"
    );

    if (!$item_stmt) {
        die("Item query prepare failed: " . $conn->error);
    }

    $item_stmt->bind_param("s", $selected_code);

    if (!$item_stmt->execute()) {
        die("Item query execute failed: " . $item_stmt->error);
    }

    $item_result = $item_stmt->get_result();

    while ($item_row = $item_result->fetch_assoc()) {
        $selected_items[] = $item_row;
    }

    $item_stmt->close();
}

/*
|--------------------------------------------------------------------------
| HELPER FUNCTIONS
|--------------------------------------------------------------------------
*/

function formatDateDisplay($date)
{
    if (
        !$date ||
        $date === '0000-00-00' ||
        $date === '0000-00-00 00:00:00'
    ) {
        return '-';
    }

    return date('M d, Y', strtotime($date));
}

function formatDateTimeDisplay($date)
{
    if (
        !$date ||
        $date === '0000-00-00 00:00:00' ||
        $date === '0000-00-00'
    ) {
        return '-';
    }

    $timestamp = strtotime($date);

    if ($timestamp === false) {
        return '-';
    }

    return date(
        'M d, Y h:i A',
        $timestamp
    );
}

function statusClass($status)
{
    if ($status === 'Confirmed') {
        return 'bg-emerald-50 text-emerald-700 border border-emerald-100';
    }

    if ($status === 'Completed') {
        return 'bg-blue-50 text-blue-700 border border-blue-100';
    }

    if ($status === 'Cancelled') {
        return 'bg-red-50 text-red-600 border border-red-100';
    }

    return 'bg-amber-50 text-amber-700 border border-amber-100';
}

function deliveryIcon($method)
{
    if (strtolower($method) === 'delivery') {
        return 'fa-truck';
    }

    return 'fa-store';
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

    <title>Reservation Management</title>

    <script src="https://cdn.tailwindcss.com"></script>

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
    >

    <style>

        body {
            font-family: Arial, Helvetica, sans-serif;
               background: #f3f1eb; 
        }
        /* =========================================================
   RESERVATION PAGE HEADER
   ========================================================= */

.reservation-page-header {
    min-height: 86px;
    width: 100%;

    display: flex;
    align-items: center;
    justify-content: space-between;

    padding: 0 28px;

    background: #f7f6f2;

    border-bottom: 1px solid #dddcd6;

    box-sizing: border-box;
}


/* =========================================================
   HEADER LEFT
   ========================================================= */

.reservation-header-left {
    display: flex;
    align-items: center;
}

.reservation-header-left h1 {
    margin: 0;

    font-size: 1.35rem;
    font-weight: 700;

    color: #3f4b45;

    letter-spacing: -0.3px;
}


/* =========================================================
   HEADER RIGHT
   ========================================================= */

.reservation-header-right {
    display: flex;
    align-items: center;

    gap: 18px;

    height: 100%;
}


/* =========================================================
   NOTIFICATION
   ========================================================= */

.reservation-notification-wrapper {
    position: relative;

    display: flex;
    align-items: center;
}

.reservation-notification-icon {
    width: 40px;
    height: 40px;

    border: none;
    background: transparent;

    display: flex;
    align-items: center;
    justify-content: center;

    position: relative;

    cursor: pointer;

    color: #65716b;

    font-size: 1.2rem;

    transition: 0.2s ease;
}

.reservation-notification-icon:hover {
    color: #527d59;
}

.reservation-notification-badge {
    position: absolute;

    top: 1px;
    right: 0;

    min-width: 17px;
    height: 17px;

    padding: 0 4px;

    border-radius: 50%;

    display: flex;
    align-items: center;
    justify-content: center;

    background: #527d59;
    color: #ffffff;

    font-size: 0.58rem;
    font-weight: 700;

    border: 2px solid #f7f6f2;
}


/* =========================================================
   HEADER DIVIDER
   ========================================================= */

.reservation-header-divider {
    width: 1px;
    height: 42px;

    background: #deded8;
}


/* =========================================================
   MANAGER PROFILE AREA
   ========================================================= */

.reservation-manager-profile-header {
    display: flex;
    align-items: center;

    gap: 11px;

    min-width: 230px;
}


/* =========================================================
   MANAGER AVATAR
   ========================================================= */

.reservation-manager-avatar {
    width: 54px;
    height: 54px;

    min-width: 54px;

    border-radius: 50%;

    display: flex;
    align-items: center;
    justify-content: center;

    background: #e8ebe7;

    border: 1px solid #d9ddd8;

    color: #527d59;

    font-size: 1.25rem;

    box-shadow:
        0 2px 6px
        rgba(0, 0, 0, 0.04);
}


/* =========================================================
   MANAGER ACCOUNT DETAILS
   ========================================================= */

.reservation-manager-account {
    display: flex;
    flex-direction: column;

    justify-content: center;

    min-width: 120px;
}

.reservation-manager-account strong {
    display: block;

    margin: 0;

    color: #3f4b45;

    font-size: 0.88rem;
    font-weight: 700;

    line-height: 1.2;
}


/* =========================================================
   DATE AND TIME
   ========================================================= */

.reservation-manager-date-time {
    display: flex;
    flex-direction: column;

    align-items: flex-start;

    gap: 2px;

    margin-top: 5px;

    white-space: nowrap;
}

.reservation-manager-date {
    font-size: 0.63rem;

    color: #8a9590;
}

.reservation-manager-time {
    font-size: 0.63rem;

    color: #527d59;

    font-weight: 600;

    line-height: 1.2;
}


/* =========================================================
   DROPDOWN ICON
   ========================================================= */

.reservation-manager-dropdown-icon {
    width: 28px;

    display: flex;
    align-items: center;
    justify-content: center;

    color: #65716b;

    font-size: 0.7rem;

    cursor: pointer;
}


/* =========================================================
   RESPONSIVE HEADER
   ========================================================= */

@media (max-width: 700px) {

    .reservation-page-header {
        padding: 16px 20px;
        min-height: auto;
    }

    .reservation-manager-profile-header {
        min-width: auto;
    }

    .reservation-manager-dropdown-icon {
        display: none;
    }

}

@media (max-width: 560px) {

    .reservation-page-header {
        align-items: flex-start;
        gap: 15px;
    }

    .reservation-header-right {
        gap: 10px;
    }

    .reservation-header-divider {
        display: none;
    }

    .reservation-manager-avatar {
        width: 45px;
        height: 45px;
        min-width: 45px;
    }

}
.main-content {
    margin-left: 312px;
    min-height: 100vh;
    padding: 18px;

    background:
        linear-gradient(
            135deg,
            #eeece6 0%,
            #f5f3ed 100%
        );
}
        .thin-scroll::-webkit-scrollbar {
            width: 5px;
        }

        .thin-scroll::-webkit-scrollbar-track {
            background: #f1f5f9;
        }

        .thin-scroll::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 10px;
        }

        .table-scroll {
            overflow-x: auto;
        }

        .thin-scroll {
            scrollbar-width: thin;
            scrollbar-color: #cbd5e1 #f8fafc;
        }

        .thin-scroll::-webkit-scrollbar {
            width: 5px;
            height: 5px;
        }

        .thin-scroll::-webkit-scrollbar-track {
            background: #f8fafc;
        }

        .thin-scroll::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 10px;
        }

        .thin-scroll::-webkit-scrollbar-thumb:hover {
            background: #94a3b8;
        }

        .reservation-row {
            transition: 0.15s ease;
        }

        .reservation-row:hover {
            background: #f8fafc;
        }

        .selected-row {
            background: #f0fdf9 !important;
        }

        .stat-card {
            transition: 0.15s ease;
        }

        .stat-card:hover {
            transform: translateY(-1px);
        }

        input:focus,
        select:focus {
            outline: none;
            border-color: #0f766e;
            box-shadow: 0 0 0 2px rgba(15, 118, 110, 0.08);
        }
.reservation-page-wrapper {
    width: 100%;
    min-height: calc(100vh - 36px);
}

.reservation-outer-card {
    width: 100%;
    min-height: calc(100vh - 36px);

    overflow: hidden;
  background: #ffffff;

    border: 1px solid #dedbd2;

    border-radius: 22px;

    box-shadow:
        0 12px 35px
        rgba(80, 72, 55, 0.10);
}

.reservation-group-card {
    width: 100%;
    background: #ffffff;
    border: 1px solid #dedbd2;
    border-radius: 22px;
    padding: 14px;
}
        @media (max-width: 1279px) {

            .reservation-list-card {
                height: auto;
            }

            .reservation-details-card {
                height: auto;
                max-height: none;
            }

        }

        @media print {

            body {
                background: white !important;
            }

            .main-content {
                margin: 0 !important;
            }

            button,
            aside,
            form,
            .stat-card {
                display: none !important;
            }

            .bg-white {
                box-shadow: none !important;
            }

        }

    </style>

</head>

<body class="bg-slate-100 font-sans text-gray-700 antialiased min-h-screen">

<?php include('manager_panel.php'); ?>

<div class="main-content">

<?php
// Display error message if confirmation failed
if (isset($_SESSION['error_message'])) {
    echo '<div style="background: #fee2e2; border: 1px solid #fecaca; border-radius: 8px; padding: 16px; margin-bottom: 20px; color: #991b1b;">';
    echo '<strong>Error:</strong> ' . htmlspecialchars($_SESSION['error_message']);
    echo '</div>';
    unset($_SESSION['error_message']);
}
?>

<div class="reservation-page-wrapper">

    <div class="reservation-outer-card">
        <!-- =========================================================
             RESERVATION PAGE HEADER
             ========================================================= -->

        <div class="reservation-page-header">


            <!-- =====================================================
                 HEADER LEFT
                 ===================================================== -->

            <div class="reservation-header-left">

                <h1>
                    Reservation Management
                </h1>

            </div>


            <!-- =====================================================
                 HEADER RIGHT
                 ===================================================== -->

            <div class="reservation-header-right">


                <!-- =================================================
                     NOTIFICATION
                     ================================================= -->

                <div
                    class="reservation-notification-wrapper"
                    id="notificationWrapper"
                >

                    <button
                        type="button"
                        id="notificationButton"
                        class="reservation-notification-icon"
                        aria-label="Notifications"
                        aria-expanded="false"
                    >

                        <i class="fa-regular fa-bell"></i>


                        <?php if ($notification_count > 0): ?>

                            <span
                                id="notificationBadge"
                                class="reservation-notification-badge"
                            >

                                <?php

                                if ($notification_count > 99) {
                                    echo '99+';
                                } else {
                                    echo $notification_count;
                                }

                                ?>

                            </span>

                        <?php endif; ?>

                    </button>


                    <!-- =============================================
                         NOTIFICATION DROPDOWN
                         ============================================= -->

                    <div
                        id="notificationDropdown"
                        class="hidden absolute right-0 top-11
                               w-[320px] bg-white
                               border border-slate-200
                               rounded-lg shadow-lg
                               z-50 overflow-hidden"
                    >


                        <!-- DROPDOWN HEADER -->

                        <div
                            class="px-4 py-3 border-b border-slate-200
                                   flex items-center justify-between"
                        >

                            <div>

                                <p
                                    class="text-sm font-semibold
                                           text-slate-800"
                                >
                                    Notifications
                                </p>

                                <p
                                    class="text-[10px]
                                           text-slate-400 mt-0.5"
                                >
                                    Pending reservation requests
                                </p>

                            </div>


                            <?php if ($notification_count > 0): ?>

                                <span
                                    class="text-[10px]
                                           font-medium
                                           text-amber-600"
                                >

                                    <?php
                                    echo $notification_count;
                                    ?>

                                    pending

                                </span>

                            <?php endif; ?>

                        </div>


                        <!-- NOTIFICATION LIST -->

                        <div
                            class="max-h-[320px]
                                   overflow-y-auto
                                   thin-scroll"
                        >

                            <?php if (count($notifications) > 0): ?>

                                <?php foreach ($notifications as $notification): ?>

                                    <a
                                        href="manager_reservation.php?view=<?php
                                            echo urlencode(
                                                $notification['reservation_code']
                                            );
                                        ?>"
                                        class="block px-4 py-3
                                               border-b border-slate-100
                                               hover:bg-slate-50
                                               transition"
                                    >

                                        <div class="flex items-start gap-3">


                                            <div
                                                class="w-8 h-8
                                                       rounded-full
                                                       bg-amber-50
                                                       flex items-center
                                                       justify-center
                                                       flex-shrink-0"
                                            >

                                                <i
                                                    class="fa-regular
                                                           fa-calendar-plus
                                                           text-amber-600
                                                           text-xs"
                                                ></i>

                                            </div>


                                            <div class="min-w-0 flex-1">


                                                <div
                                                    class="flex
                                                           items-center
                                                           justify-between
                                                           gap-2"
                                                >

                                                    <p
                                                        class="text-[11px]
                                                               font-semibold
                                                               text-slate-800
                                                               truncate"
                                                    >
                                                        New Reservation
                                                    </p>


                                                    <span
                                                        class="text-[9px]
                                                               text-amber-600
                                                               font-medium"
                                                    >
                                                        Pending
                                                    </span>

                                                </div>


                                                <p
                                                    class="text-[10px]
                                                           text-slate-600
                                                           mt-0.5"
                                                >

                                                    <?php
                                                    echo htmlspecialchars(
                                                        $notification['customer_name']
                                                    );
                                                    ?>

                                                </p>


                                                <p
                                                    class="text-[10px]
                                                           text-slate-400
                                                           mt-1"
                                                >

                                                    <?php
                                                    echo htmlspecialchars(
                                                        $notification['reservation_code'] ?? 'No Code'
                                                    );
                                                    ?>

                                                    <span class="mx-1">
                                                        •
                                                    </span>

                                                    <?php
                                                    echo formatDateTimeDisplay(
                                                        $notification['reserved_at']
                                                    );
                                                    ?>

                                                </p>

                                            </div>

                                        </div>

                                    </a>

                                <?php endforeach; ?>


                            <?php else: ?>


                                <div class="px-5 py-8 text-center">

                                    <div
                                        class="w-10 h-10 mx-auto
                                               rounded-full
                                               bg-slate-100
                                               flex items-center
                                               justify-center mb-2"
                                    >

                                        <i
                                            class="fa-regular
                                                   fa-bell-slash
                                                   text-slate-400
                                                   text-sm"
                                        ></i>

                                    </div>


                                    <p
                                        class="text-xs font-medium
                                               text-slate-600"
                                    >
                                        No new notifications
                                    </p>


                                    <p
                                        class="text-[10px]
                                               text-slate-400 mt-1"
                                    >
                                        There are no pending reservations.
                                    </p>

                                </div>


                            <?php endif; ?>

                        </div>


                        <!-- DROPDOWN FOOTER -->

                        <div
                            class="px-4 py-2.5
                                   border-t border-slate-200
                                   bg-slate-50"
                        >

                            <a
                                href="manager_reservation.php"
                                class="block text-center
                                       text-[10px]
                                       font-medium
                                       text-emerald-700
                                       hover:text-emerald-800"
                            >
                                View All Reservations
                            </a>

                        </div>

                    </div>

                </div>


                <!-- =================================================
                     HEADER DIVIDER
                     ================================================= -->

                <div
                    class="reservation-header-divider"
                ></div>


                <!-- =================================================
                     MANAGER PROFILE
                     ================================================= -->

                <div
                    class="reservation-manager-profile-header"
                >


                    <!-- MANAGER AVATAR -->

                    <div class="reservation-manager-avatar">

                        <i class="fa-solid fa-user"></i>

                    </div>


                    <!-- MANAGER INFORMATION -->

                    <div
                        class="reservation-manager-account"
                        id="managerClock"
                        data-server-time="<?php echo time(); ?>"
                    >

                        <strong>
                            Manager
                        </strong>


                        <!-- DATE AND TIME -->

                        <div
                            class="reservation-manager-date-time"
                        >

                            <span
                                id="managerDate"
                                class="reservation-manager-date"
                            >
                                <?php
                                echo date('F d, Y');
                                ?>
                            </span>


                            <span
                                id="managerTime"
                                class="reservation-manager-time"
                            >
                                <?php
                                echo date('h:i:s A');
                                ?>
                            </span>

                        </div>

                    </div>


                    <!-- DROPDOWN ICON -->

                    <div
                        class="reservation-manager-dropdown-icon"
                    >

                        <i class="fa-solid fa-chevron-down"></i>

                    </div>

                </div>

            </div>

        </div>


        <!-- =========================================================
             PAGE CONTENT
             ========================================================= -->

        <div class="p-4 md:p-5">


            <!-- FILTER CARD -->

            <div class="bg-white border border-slate-200 rounded-lg p-4 mb-4">

                <form
                    method="GET"
                    id="reservationFilterForm"
                >

                    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-3">

                        <!-- SEARCH -->

                        <div>

                            <label
                                class="block text-[11px] font-medium
                                       text-slate-600 mb-1.5"
                            >
                                Search
                            </label>

                            <div class="relative">

                                <i
                                    class="fa-solid fa-magnifying-glass
                                           absolute left-3 top-1/2
                                           -translate-y-1/2
                                           text-slate-400 text-[10px]"
                                ></i>

                                <input
                                    type="text"
                                    name="search"
                                    id="reservationSearch"
                                    value="<?php echo htmlspecialchars($search); ?>"
                                    placeholder="Search customer, schedule, delivery or status..."
                                    autocomplete="off"
                                    class="w-full h-9 pl-8 pr-3 rounded-md border
                                           border-slate-200 text-xs text-slate-700"
                                >

                            </div>

                        </div>


                        <!-- RESERVED FROM -->

                        <div>

                            <label
                                class="block text-[11px] font-medium
                                       text-slate-600 mb-1.5"
                            >
                                Reserved From
                            </label>

                            <input
                                type="date"
                                name="date_from"
                                id="dateFrom"
                                value="<?php echo htmlspecialchars($date_from); ?>"
                                class="w-full h-9 px-3 rounded-md border
                                       border-slate-200 text-xs
                                       text-slate-700"
                            >

                        </div>


                        <!-- RESERVED TO -->

                        <div>

                            <label
                                class="block text-[11px] font-medium
                                       text-slate-600 mb-1.5"
                            >
                                Reserved To
                            </label>

                            <div class="flex items-center gap-2">

                                <input
                                    type="date"
                                    name="date_to"
                                    id="dateTo"
                                    value="<?php echo htmlspecialchars($date_to); ?>"
                                    class="flex-1 min-w-0 h-9 px-3 rounded-md
                                           border border-slate-200 text-xs
                                           text-slate-700"
                                >

                                <a
                                    href="manager_reservation.php"
                                    class="h-9 px-3 rounded-md border
                                           border-slate-200 bg-white
                                           text-slate-600 hover:bg-slate-50
                                           text-[11px] font-medium flex
                                           items-center justify-center
                                           whitespace-nowrap"
                                    title="Reset Filters"
                                >

                                    <i class="fa-solid fa-rotate-left mr-1"></i>

                                    Reset

                                </a>

                            </div>

                        </div>

                    </div>

                </form>

            </div>


            <!-- MAIN CONTENT -->

            <div
                class="grid grid-cols-1 xl:grid-cols-[minmax(0,1fr)_360px]
                       gap-4 items-stretch"
            >

                <!-- LEFT SIDE -->

                <div class="min-w-0">

                    <!-- RESERVATION LIST -->

                    <div
                        class="reservation-list-card
                               bg-white border border-slate-200 rounded-lg
                               overflow-hidden flex flex-col"
                    >

                        <div class="px-4 py-3 border-b border-slate-200">

                            <div class="flex items-center justify-between">

                                <div>

                                    <h2 class="text-base font-semibold text-slate-800">
                                        Reservations List
                                    </h2>

                                    <p class="text-[11px] text-slate-400 mt-0.5">

                                        <?php echo number_format($filtered_total); ?>

                                        reservation(s) found

                                    </p>

                                </div>

                                <button
                                    type="button"
                                    onclick="window.print()"
                                    class="border border-slate-200 text-slate-600
                                           hover:bg-slate-50 px-3 py-2 rounded-md
                                           text-[11px]"
                                >

                                    <i class="fa-solid fa-print mr-1"></i>

                                    Print Report

                                </button>

                            </div>

                        </div>


                        <!-- TABLE -->

                        <div
                            class="table-scroll overflow-y-auto
                                   overflow-x-auto thin-scroll"
                            style="max-height: 285px;"
                        >

                            <table class="w-full min-w-[900px]">

                                <thead>

                                    <tr class="bg-slate-50 border-b border-slate-200">

                                        <th
                                            class="text-left px-3 py-3 text-[11px]
                                                   font-semibold text-slate-500"
                                        >
                                            Reservation ID
                                        </th>

                                        <th
                                            class="text-left px-3 py-3 text-[11px]
                                                   font-semibold text-slate-500"
                                        >
                                            Customer
                                        </th>

                                        <th
                                            class="text-left px-3 py-3 text-[11px]
                                                   font-semibold text-slate-500"
                                        >
                                            Contact
                                        </th>

                                        <th
                                            class="text-left px-3 py-3 text-[11px]
                                                   font-semibold text-slate-500"
                                        >
                                            Schedule
                                        </th>

                                        <th
                                            class="text-left px-3 py-3 text-[11px]
                                                   font-semibold text-slate-500"
                                        >
                                            Delivery Method
                                        </th>

                                        <th
                                            class="text-right px-3 py-3 text-[11px]
                                                   font-semibold text-slate-500"
                                        >
                                            Total Trays
                                        </th>

                                        <th
                                            class="text-right px-3 py-3 text-[11px]
                                                   font-semibold text-slate-500"
                                        >
                                            Total Amount
                                        </th>

                                        <th
                                            class="text-center px-3 py-3 text-[11px]
                                                   font-semibold text-slate-500"
                                        >
                                            Status
                                        </th>

                                        <th
                                            class="text-center px-3 py-3 text-[11px]
                                                   font-semibold text-slate-500"
                                        >
                                            Actions
                                        </th>

                                    </tr>

                                </thead>


                                <tbody>

                                <?php if (count($reservations) > 0): ?>

                                    <?php foreach ($reservations as $reservation): ?>

                                        <?php
                                        $is_selected =
                                            ($reservation['reservation_code'] === $selected_code);
                                        ?>

                                        <tr
                                            onclick="window.location.href='<?php

                                                $row_query = array();

                                                if ($search !== '') {
                                                    $row_query['search'] = $search;
                                                }

                                                if ($date_from !== '') {
                                                    $row_query['date_from'] = $date_from;
                                                }

                                                if ($date_to !== '') {
                                                    $row_query['date_to'] = $date_to;
                                                }

                                                $row_query['view'] =
                                                    $reservation['reservation_code'];

                                                echo htmlspecialchars(
                                                    'manager_reservation.php?' .
                                                    http_build_query($row_query)
                                                );

                                            ?>'"
                                            class="reservation-row border-b
                                                   border-slate-100 cursor-pointer
                                                   <?php
                                                   if ($is_selected) {
                                                       echo 'selected-row';
                                                   }
                                                   ?>"
                                        >

                                            <!-- ID -->

                                            <td class="px-3 py-3">

                                                <a
                                                    href="manager_reservation.php?<?php

                                                        $view_query = array();

                                                        if ($search !== '') {
                                                            $view_query['search'] = $search;
                                                        }

                                                        if ($date_from !== '') {
                                                            $view_query['date_from'] = $date_from;
                                                        }

                                                        if ($date_to !== '') {
                                                            $view_query['date_to'] = $date_to;
                                                        }

                                                        $view_query['view'] =
                                                            $reservation['reservation_code'];

                                                        echo htmlspecialchars(
                                                            http_build_query($view_query)
                                                        );

                                                    ?>"
                                                    class="text-[11px] font-medium
                                                           text-slate-700
                                                           hover:text-emerald-700"
                                                >

                                                    <?php
                                                    echo htmlspecialchars(
                                                        $reservation['reservation_code'] ?? 'No Code'
                                                    );
                                                    ?>

                                                </a>

                                            </td>


                                            <!-- CUSTOMER -->

                                            <td class="px-3 py-3">

                                                <p
                                                    class="text-xs font-medium
                                                           text-slate-700"
                                                >
                                                    <?php
                                                    echo htmlspecialchars(
                                                        $reservation['customer_name'] ?? 'Unknown Customer'
                                                    );
                                                    ?>
                                                </p>

                                            </td>


                                            <!-- CONTACT -->

                                            <td class="px-3 py-3">

                                                <span class="text-[11px] text-slate-500">

                                                    <?php
                                                    echo htmlspecialchars(
                                                        $reservation['contact_number'] ?? 'Not provided'
                                                    );
                                                    ?>

                                                </span>

                                            </td>


                                            <!-- SCHEDULE -->

                                            <td class="px-3 py-3">

                                                <?php if ($reservation['delivery_method'] === 'Pickup'): ?>

                                                    <p class="text-[10px] text-slate-400">
                                                        Pickup Date
                                                    </p>

                                                    <?php if (!empty($reservation['reservation_date'])): ?>

                                                        <p
                                                            class="text-[11px]
                                                                   font-medium
                                                                   text-slate-700 mt-0.5"
                                                        >
                                                            <?php
                                                            echo formatDateDisplay(
                                                                $reservation['reservation_date']
                                                            );
                                                            ?>
                                                        </p>

                                                    <?php else: ?>

                                                        <p
                                                            class="text-[11px]
                                                                   font-medium
                                                                   text-amber-600 mt-0.5"
                                                        >
                                                            Not selected
                                                        </p>

                                                    <?php endif; ?>

                                                <?php elseif ($reservation['delivery_method'] === 'Delivery'): ?>

                                                    <p class="text-[10px] text-slate-400">
                                                        Delivery Date
                                                    </p>

                                                    <?php if (!empty($reservation['reservation_date'])): ?>

                                                        <p
                                                            class="text-[11px]
                                                                   font-medium
                                                                   text-slate-700 mt-0.5"
                                                        >
                                                            <?php
                                                            echo formatDateDisplay(
                                                                $reservation['reservation_date']
                                                            );
                                                            ?>
                                                        </p>

                                                    <?php else: ?>

                                                        <p
                                                            class="text-[11px]
                                                                   font-medium
                                                                   text-amber-600 mt-0.5"
                                                        >
                                                            To be scheduled
                                                        </p>

                                                    <?php endif; ?>

                                                <?php endif; ?>

                                            </td>


                                            <!-- DELIVERY -->

                                            <td class="px-3 py-3">

                                                <span
                                                    class="inline-flex items-center gap-1.5
                                                           text-[11px] text-slate-600"
                                                >

                                                    <span
                                                        class="w-5 h-5 rounded-md
                                                               bg-emerald-50 flex
                                                               items-center
                                                               justify-center"
                                                    >

                                                        <i
                                                            class="fa-solid
                                                                <?php
                                                                echo deliveryIcon(
                                                                    $reservation['delivery_method']
                                                                );
                                                                ?>
                                                                text-emerald-700
                                                                text-[8px]"
                                                        ></i>

                                                    </span>

                                                    <?php
                                                    echo htmlspecialchars(
                                                        $reservation['delivery_method'] ?? 'Not specified'
                                                    );
                                                    ?>

                                                </span>

                                            </td>


                                            <!-- TRAYS -->

                                            <td class="px-3 py-3 text-right">

                                                <span
                                                    class="text-xs font-medium
                                                           text-slate-700"
                                                >

                                                    <?php
                                                    echo number_format(
                                                        $reservation['total_trays']
                                                    );
                                                    ?>

                                                </span>

                                            </td>


                                            <!-- AMOUNT -->

                                            <td class="px-3 py-3 text-right">

                                                <span
                                                    class="text-xs font-medium
                                                           text-slate-700"
                                                >

                                                    ₱<?php
                                                    echo number_format(
                                                        $reservation['total_amount'],
                                                        2
                                                    );
                                                    ?>

                                                </span>

                                            </td>


                                            <!-- STATUS -->

                                            <td class="px-3 py-3 text-center">

                                                <span
                                                    class="inline-flex px-2 py-1
                                                           rounded-full text-[10px]
                                                           font-medium
                                                           <?php
                                                           echo statusClass(
                                                               $reservation['status']
                                                           );
                                                           ?>"
                                                >

                                                    <?php
                                                    echo htmlspecialchars(
                                                        $reservation['status']
                                                    );
                                                    ?>

                                                </span>

                                            </td>


                                            <!-- ACTIONS -->

                                            <td class="px-3 py-3">

                                                <div class="flex justify-center gap-1">

                                                    <a
                                                        href="<?php
                                                        echo htmlspecialchars(
                                                            'manager_reservation.php?view=' .
                                                            urlencode(
                                                                $reservation['reservation_code']
                                                            )
                                                        );
                                                        ?>"
                                                        class="w-7 h-7 border
                                                               border-slate-200
                                                               rounded-md flex
                                                               items-center
                                                               justify-center
                                                               text-slate-500
                                                               hover:bg-slate-50"
                                                        title="View Details"
                                                    >

                                                        <i
                                                            class="fa-regular
                                                                   fa-eye text-[9px]"
                                                        ></i>

                                                    </a>


                                                    <button
                                                        type="button"
                                                        onclick="openStatusModal(
                                                            '<?php
                                                            echo htmlspecialchars(
                                                                $reservation['reservation_code'] ?? 'No Code',
                                                                ENT_QUOTES
                                                            );
                                                            ?>',
                                                            '<?php
                                                            echo htmlspecialchars(
                                                                $reservation['status'] ?? 'Pending',
                                                                ENT_QUOTES
                                                            );
                                                            ?>'
                                                        )"
                                                        class="w-7 h-7 border
                                                               border-slate-200
                                                               rounded-md flex
                                                               items-center
                                                               justify-center
                                                               text-slate-500
                                                               hover:bg-slate-50"
                                                        title="Update Status"
                                                    >

                                                        <i
                                                            class="fa-solid
                                                                   fa-ellipsis-vertical
                                                                   text-[9px]"
                                                        ></i>

                                                    </button>

                                                </div>

                                            </td>

                                        </tr>

                                    <?php endforeach; ?>

                                <?php else: ?>

                                    <tr>

                                        <td
                                            colspan="9"
                                            class="px-6 py-12 text-center"
                                        >

                                            <div
                                                class="w-12 h-12 mx-auto
                                                       rounded-full bg-slate-100
                                                       flex items-center
                                                       justify-center mb-3"
                                            >

                                                <i
                                                    class="fa-regular
                                                           fa-calendar-xmark
                                                           text-slate-400"
                                                ></i>

                                            </div>

                                            <p
                                                class="text-base font-medium
                                                       text-slate-600"
                                            >
                                                No reservations found
                                            </p>

                                            <p
                                                class="text-[10px] text-slate-400 mt-1"
                                            >
                                                Try changing your search or filters.
                                            </p>

                                        </td>

                                    </tr>

                                <?php endif; ?>

                                </tbody>

                            </table>

                        </div>


                        <!-- RESERVATION LIST FOOTER -->

                        <div class="px-4 py-3 border-t border-slate-200">

                            <p class="text-[11px] text-slate-500">

                                <?php echo number_format($filtered_total); ?>

                                reservation(s) found

                                <?php if ($filtered_total > 5): ?>

                                    <span class="text-slate-400">
                                        — scroll to view more
                                    </span>

                                <?php endif; ?>

                            </p>

                        </div>

                    </div>

                </div>


                <!-- RIGHT SIDE - RESERVATION DETAILS -->

                <aside
                    class="reservation-details-card
                           bg-white border border-slate-200 rounded-lg
                           overflow-hidden flex flex-col
                           xl:sticky xl:top-4"
                    style="height: 390px;"
                >

                    <!-- HEADER -->

                    <div
                        class="px-4 py-3 border-b border-slate-200
                               flex items-center justify-between"
                    >

                        <h2 class="text-base font-semibold text-slate-800">
                            Reservation Details
                        </h2>

                        <?php if ($selected_code !== ''): ?>

                            <a
                                href="manager_reservation.php"
                                class="text-slate-400 hover:text-slate-700"
                            >

                                <i class="fa-solid fa-xmark text-xs"></i>

                            </a>

                        <?php endif; ?>

                    </div>


                    <?php if ($selected_reservation): ?>

                    <div
                        class="p-4 flex-1 min-h-0 overflow-y-auto thin-scroll"
                    >

                        <!-- RESERVATION ID -->

                        <div
                            class="bg-emerald-50 border border-emerald-100
                                   rounded-lg p-3 mb-4"
                        >

                            <p class="text-xs font-semibold text-slate-700">

                                <?php
                                echo htmlspecialchars(
                                    $selected_reservation['reservation_code'] ?? 'No Code'
                                );
                                ?>

                            </p>

                            <div class="flex items-center gap-2 mt-2">

                                <span class="text-[11px] text-slate-500">
                                    Status:
                                </span>

                                <span
                                    class="px-2 py-1 rounded-full text-[10px]
                                           font-medium
                                           <?php
                                           echo statusClass(
                                               $selected_reservation['status']
                                           );
                                           ?>"
                                >

                                    <?php
                                    echo htmlspecialchars(
                                        $selected_reservation['status']
                                    );
                                    ?>

                                </span>

                            </div>

                        </div>


                        <!-- CUSTOMER INFORMATION -->

                        <div class="mb-4">

                            <div class="flex items-center gap-2 mb-3">

                                <i
                                    class="fa-regular fa-user text-slate-600 text-xs"
                                ></i>

                                <h3
                                    class="text-sm font-semibold text-slate-800"
                                >
                                    Customer Information
                                </h3>

                            </div>


                            <div class="space-y-2">

                                <div class="flex justify-between gap-3">

                                    <span class="text-[11px] text-slate-400">
                                        Name
                                    </span>

                                    <span
                                        class="text-[11px] text-slate-700
                                               font-medium text-right"
                                    >

                                        <?php
                                        echo htmlspecialchars(
                                            $selected_reservation['customer_name']
                                        );
                                        ?>

                                    </span>

                                </div>


                                <div class="flex justify-between gap-3">

                                    <span class="text-[11px] text-slate-400">
                                        Contact Number
                                    </span>

                                    <span
                                        class="text-[11px] text-slate-700
                                               text-right"
                                    >

                                        <?php
                                        echo htmlspecialchars(
                                            $selected_reservation['contact_number']
                                        );
                                        ?>

                                    </span>

                                </div>


                                <div class="flex justify-between gap-3">

                                    <span class="text-[11px] text-slate-400">
                                        Schedule
                                    </span>

                                    <span
                                        class="text-[11px] text-slate-700
                                               text-right"
                                    >

                                        <?php if ($selected_reservation['delivery_method'] === 'Pickup'): ?>

                                            <?php if (!empty($selected_reservation['reservation_date'])): ?>

                                                Pickup:
                                                <?php
                                                echo formatDateDisplay(
                                                    $selected_reservation['reservation_date']
                                                );
                                                ?>

                                            <?php else: ?>

                                                <span class="text-amber-600">
                                                    Pickup date not selected
                                                </span>

                                            <?php endif; ?>

                                        <?php elseif ($selected_reservation['delivery_method'] === 'Delivery'): ?>

                                            <?php if (!empty($selected_reservation['reservation_date'])): ?>

                                                Delivery:
                                                <?php
                                                echo formatDateDisplay(
                                                    $selected_reservation['reservation_date']
                                                );
                                                ?>

                                            <?php else: ?>

                                                <span class="text-amber-600">
                                                    To be scheduled
                                                </span>

                                            <?php endif; ?>

                                        <?php endif; ?>

                                    </span>

                                </div>


                                <div class="flex justify-between gap-3">

                                    <span class="text-[11px] text-slate-400">
                                        Delivery Method
                                    </span>

                                    <span
                                        class="text-[11px] text-slate-700
                                               font-medium text-right"
                                    >

                                        <i
                                            class="fa-solid
                                                <?php
                                                echo deliveryIcon(
                                                    $selected_reservation['delivery_method']
                                                );
                                                ?>
                                                text-emerald-700 mr-1"
                                        ></i>

                                        <?php
                                        echo htmlspecialchars(
                                            $selected_reservation['delivery_method'] ?? 'Not specified'
                                        );
                                        ?>

                                    </span>

                                </div>


                                <div class="flex justify-between gap-3">

                                    <span class="text-[11px] text-slate-400">
                                        Reserved On
                                    </span>

                                    <span
                                        class="text-[11px] text-slate-700
                                               text-right"
                                    >

                                        <?php
                                        echo formatDateTimeDisplay(
                                            $selected_reservation['reserved_at']
                                        );
                                        ?>

                                    </span>

                                </div>

                            </div>

                        </div>


                        <div class="border-t border-slate-100 pt-4 mb-4">

                            <!-- ORDERED ITEMS -->

                            <div class="flex items-center gap-2 mb-3">

                                <i
                                    class="fa-regular fa-clipboard
                                           text-slate-600 text-xs"
                                ></i>

                                <h3
                                    class="text-sm font-semibold text-slate-800"
                                >
                                    Ordered Items
                                </h3>

                            </div>


                            <div
                                class="overflow-hidden border border-slate-100
                                       rounded-md"
                            >

                                <table class="w-full">

                                    <thead>

                                        <tr class="bg-slate-50">

                                            <th
                                                class="text-left px-2 py-2
                                                       text-[10px]
                                                       text-slate-500
                                                       font-medium"
                                            >
                                                Egg Size
                                            </th>

                                            <th
                                                class="text-right px-2 py-2
                                                       text-[10px]
                                                       text-slate-500
                                                       font-medium"
                                            >
                                                Quantity
                                            </th>

                                            <th
                                                class="text-right px-2 py-2
                                                       text-[10px]
                                                       text-slate-500
                                                       font-medium"
                                            >
                                                Price/Tray
                                            </th>

                                            <th
                                                class="text-right px-2 py-2
                                                       text-[10px]
                                                       text-slate-500
                                                       font-medium"
                                            >
                                                Total
                                            </th>

                                        </tr>

                                    </thead>


                                    <tbody>

                                    <?php foreach ($selected_items as $item): ?>

                                        <tr class="border-t border-slate-100">

                                            <td
                                                class="px-2 py-2 text-[10px]
                                                       text-slate-700"
                                            >

                                                <?php
                                                echo htmlspecialchars(
                                                    $item['egg_type']
                                                );
                                                ?>

                                            </td>

                                            <td
                                                class="px-2 py-2 text-[10px]
                                                       text-slate-700
                                                       text-right"
                                            >

                                                <?php
                                                echo number_format(
                                                    $item['quantity']
                                                );
                                                ?>

                                            </td>

                                            <td
                                                class="px-2 py-2 text-[10px]
                                                       text-slate-700
                                                       text-right"
                                            >

                                                ₱<?php

                                                if ((int)$item['quantity'] > 0) {

                                                    echo number_format(
                                                        $item['total_price'] /
                                                        $item['quantity'],
                                                        2
                                                    );

                                                } else {

                                                    echo '0.00';

                                                }

                                                ?>

                                            </td>

                                            <td
                                                class="px-2 py-2 text-[10px]
                                                       text-slate-700
                                                       text-right"
                                            >

                                                ₱<?php
                                                echo number_format(
                                                    $item['total_price'],
                                                    2
                                                );
                                                ?>

                                            </td>

                                        </tr>

                                    <?php endforeach; ?>

                                    </tbody>

                                </table>

                            </div>


                            <!-- TOTALS -->

                            <div class="mt-3 space-y-2">

                                <div class="flex justify-between">

                                    <span class="text-[11px] text-slate-500">
                                        Total Trays
                                    </span>

                                    <span
                                        class="text-xs font-semibold
                                               text-slate-700"
                                    >

                                        <?php
                                        echo number_format(
                                            $selected_reservation['total_trays']
                                        );
                                        ?>

                                    </span>

                                </div>


                                <div
                                    class="flex justify-between items-center"
                                >

                                    <span
                                        class="text-[11px] font-semibold
                                               text-slate-700"
                                    >
                                        Total Amount
                                    </span>

                                    <span
                                        class="text-base font-bold
                                               text-emerald-700"
                                    >

                                        ₱<?php
                                        echo number_format(
                                            $selected_reservation['total_amount'],
                                            2
                                        );
                                        ?>

                                    </span>

                                </div>

                            </div>

                        </div>


                        <!-- MANAGER ACTIONS -->

                        <div class="border-t border-slate-100 pt-4">

                            <div class="flex items-center gap-2 mb-3">

                                <i
                                    class="fa-solid fa-user-gear
                                           text-slate-600 text-xs"
                                ></i>

                                <h3
                                    class="text-sm font-semibold text-slate-800"
                                >
                                    Manager Actions
                                </h3>

                            </div>


                            <!-- DELIVERY SCHEDULE -->

                            <?php if (
                                $selected_reservation['delivery_method'] === 'Delivery' &&
                                $selected_reservation['status'] !== 'Cancelled' &&
                                $selected_reservation['status'] !== 'Completed'
                            ): ?>

                                <div class="mb-4">

                                    <div class="flex items-center gap-2 mb-3">

                                        <i
                                            class="fa-solid fa-truck
                                                   text-blue-600 text-xs"
                                        ></i>

                                        <h3
                                            class="text-sm font-semibold
                                                   text-slate-800"
                                        >
                                            Delivery Schedule
                                        </h3>

                                    </div>


                                    <?php if (!empty($selected_reservation['reservation_date'])): ?>

                                        <div
                                            class="bg-blue-50 border border-blue-100
                                                   rounded-md p-3 mb-2"
                                        >

                                            <p class="text-[10px] text-blue-500">
                                                Current Delivery Date
                                            </p>

                                            <p
                                                class="text-xs font-semibold
                                                       text-blue-700 mt-1"
                                            >

                                                <?php
                                                echo formatDateDisplay(
                                                    $selected_reservation['reservation_date']
                                                );
                                                ?>

                                            </p>

                                        </div>

                                    <?php else: ?>

                                        <div
                                            class="bg-amber-50 border border-amber-100
                                                   rounded-md p-3 mb-2"
                                        >

                                            <p class="text-[10px] text-amber-600">
                                                Delivery Schedule
                                            </p>

                                            <p
                                                class="text-xs font-semibold
                                                       text-amber-700 mt-1"
                                            >
                                                Not yet scheduled
                                            </p>

                                        </div>

                                    <?php endif; ?>


                                    <form method="POST">

                                        <input
                                            type="hidden"
                                            name="action"
                                            value="schedule_delivery"
                                        >

                                        <input
                                            type="hidden"
                                            name="reservation_code"
                                            value="<?php
                                            echo htmlspecialchars(
                                                $selected_reservation['reservation_code'] ?? ''
                                            );
                                            ?>"
                                        >

                                        <label
                                            class="block text-[11px]
                                                   font-medium text-slate-600
                                                   mb-1.5"
                                        >
                                            Select Delivery Date
                                        </label>

                                        <input
                                            type="date"
                                            name="delivery_date"
                                            min="<?php echo date('Y-m-d'); ?>"
                                            value="<?php
                                            if (!empty($selected_reservation['reservation_date'])) {
                                                echo htmlspecialchars(
                                                    $selected_reservation['reservation_date']
                                                );
                                            }
                                            ?>"
                                            required
                                            class="w-full h-9 px-3
                                                   border border-slate-200
                                                   rounded-md text-xs
                                                   text-slate-700 mb-2"
                                        >

                                        <button
                                            type="submit"
                                            onclick="return confirm('Save this delivery schedule?');"
                                            class="w-full h-9 rounded-md
                                                   bg-blue-700
                                                   hover:bg-blue-800
                                                   text-white text-[11px]
                                                   font-medium"
                                        >

                                            <i
                                                class="fa-solid
                                                       fa-calendar-check mr-1"
                                            ></i>

                                            Save Delivery Schedule

                                        </button>

                                    </form>

                                </div>

                            <?php endif; ?>


                            <!-- CONFIRM -->

                            <?php if ($selected_reservation['status'] === 'Pending'): ?>

                                <form method="POST" class="mb-2">

                                    <input
                                        type="hidden"
                                        name="action"
                                        value="confirm"
                                    >

                                    <input
                                        type="hidden"
                                        name="reservation_code"
                                        value="<?php
                                        echo htmlspecialchars(
                                            $selected_reservation['reservation_code'] ?? ''
                                        );
                                        ?>"
                                    >

                                    <button
                                        type="submit"
                                        onclick="return confirm('Confirm this reservation?');"
                                        class="w-full h-9 rounded-md
                                               bg-emerald-800
                                               hover:bg-emerald-900
                                               text-white text-[11px]
                                               font-medium"
                                    >

                                        <i
                                            class="fa-regular
                                                   fa-circle-check mr-1"
                                        ></i>

                                        Confirm Reservation

                                    </button>

                                </form>

                            <?php endif; ?>


                            <!-- UPDATE STATUS -->

                            <button
                                type="button"
                                onclick="openStatusModal(
                                    '<?php
                                    echo htmlspecialchars(
                                        $selected_reservation['reservation_code'] ?? 'No Code',
                                        ENT_QUOTES
                                    );
                                    ?>',
                                    '<?php
                                    echo htmlspecialchars(
                                        $selected_reservation['status'] ?? 'Pending',
                                        ENT_QUOTES
                                    );
                                    ?>'
                                )"
                                class="w-full h-9 rounded-md
                                       bg-amber-100 hover:bg-amber-200
                                       text-amber-700 text-[11px]
                                       font-medium mb-2"
                            >

                                <i class="fa-solid fa-rotate mr-1"></i>

                                Update Status

                            </button>


                            <!-- CANCEL -->

                            <?php if (
                                $selected_reservation['status'] !== 'Cancelled' &&
                                $selected_reservation['status'] !== 'Completed'
                            ): ?>

                                <form method="POST">

                                    <input
                                        type="hidden"
                                        name="action"
                                        value="cancel"
                                    >

                                    <input
                                        type="hidden"
                                        name="reservation_code"
                                        value="<?php
                                        echo htmlspecialchars(
                                            $selected_reservation['reservation_code'] ?? ''
                                        );
                                        ?>"
                                    >

                                    <button
                                        type="submit"
                                        onclick="return confirm('Cancel this reservation?');"
                                        class="w-full h-9 rounded-md
                                               bg-red-50 hover:bg-red-100
                                               text-red-600 border
                                               border-red-100
                                               text-[11px] font-medium"
                                    >

                                        <i
                                            class="fa-regular
                                                   fa-circle-xmark mr-1"
                                        ></i>

                                        Cancel Reservation

                                    </button>

                                </form>

                            <?php endif; ?>

                        </div>

                    </div>

                    <?php else: ?>

                        <!-- EMPTY DETAILS -->

                        <div class="p-8 text-center">

                            <div
                                class="w-12 h-12 mx-auto rounded-full
                                       bg-slate-100 flex items-center
                                       justify-center mb-3"
                            >

                                <i
                                    class="fa-regular fa-calendar
                                           text-slate-400"
                                ></i>

                            </div>

                            <p
                                class="text-sm font-medium text-slate-600"
                            >
                                No reservation selected
                            </p>

                            <p
                                class="text-[11px] text-slate-400 mt-1"
                            >
                                Select a reservation from the list.
                            </p>

                        </div>

                    <?php endif; ?>

                </aside>

            </div>

        </div>

    </div>

</div>

</div>


<!-- ============================================================= -->
<!-- UPDATE STATUS MODAL -->
<!-- ============================================================= -->

<div
    id="statusModal"
    class="fixed inset-0 bg-slate-900/40 hidden items-center
           justify-center z-50 p-4"
>

    <div class="bg-white rounded-lg shadow-xl w-full max-w-sm">

        <div
            class="px-5 py-4 border-b border-slate-200
                   flex items-center justify-between"
        >

            <h3 class="text-base font-semibold text-slate-800">
                Update Reservation Status
            </h3>

            <button
                type="button"
                onclick="closeStatusModal()"
                class="text-slate-400 hover:text-slate-700"
            >

                <i class="fa-solid fa-xmark"></i>

            </button>

        </div>


        <form method="POST">

            <div class="p-5">

                <input
                    type="hidden"
                    name="action"
                    value="update_status"
                >

                <input
                    type="hidden"
                    name="reservation_code"
                    id="modalReservationCode"
                    value=""
                >

                <label
                    class="block text-[11px] font-medium
                           text-slate-600 mb-2"
                >
                    Select New Status
                </label>

                <select
                    name="new_status"
                    class="w-full h-10 px-3 border
                           border-slate-200 rounded-md text-sm"
                >

                    <option value="Pending">
                        Pending
                    </option>

                    <option value="Confirmed">
                        Confirmed
                    </option>

                    <option value="Completed">
                        Completed
                    </option>

                    <option value="Cancelled">
                        Cancelled
                    </option>

                </select>

            </div>


            <div
                class="px-5 py-4 border-t border-slate-200
                       flex justify-end gap-2"
            >

                <button
                    type="button"
                    onclick="closeStatusModal()"
                    class="px-4 h-9 rounded-md border
                           border-slate-200 text-[11px]
                           text-slate-600"
                >
                    Cancel
                </button>

                <button
                    type="submit"
                    class="px-4 h-9 rounded-md
                           bg-emerald-800 hover:bg-emerald-900
                           text-white text-[11px] font-medium"
                >
                    Save Status
                </button>

            </div>

        </form>

    </div>

</div>


<script>
/* =========================================================
   MANILA LIVE CLOCK
   ========================================================= */

(function () {

    var clockContainer =
        document.getElementById('managerClock');

    var dateElement =
        document.getElementById('managerDate');

    var timeElement =
        document.getElementById('managerTime');

    if (!clockContainer ||
        !dateElement ||
        !timeElement) {
        return;
    }


    var serverTimestamp =
        parseInt(
            clockContainer.getAttribute(
                'data-server-time'
            ),
            10
        );


    if (isNaN(serverTimestamp)) {
        serverTimestamp =
            Math.floor(
                new Date().getTime() / 1000
            );
    }


    /*
     * Convert the PHP server timestamp into
     * a continuously running Manila clock.
     */

    var currentTimestamp =
        serverTimestamp;


    function padNumber(number) {

        return number < 10
            ? '0' + number
            : number;

    }


    function updateManagerClock() {

        var currentDate =
            new Date(
                currentTimestamp * 1000
            );


        /*
         * Asia/Manila is UTC+8.
         *
         * We use the timestamp generated by PHP
         * as the starting point, then display it
         * in Manila time.
         */

        var manilaDateString =
            currentDate.toLocaleString(
                'en-US',
                {
                    timeZone: 'Asia/Manila',
                    month: 'long',
                    day: '2-digit',
                    year: 'numeric'
                }
            );


        var manilaTimeString =
            currentDate.toLocaleString(
                'en-US',
                {
                    timeZone: 'Asia/Manila',
                    hour: '2-digit',
                    minute: '2-digit',
                    second: '2-digit',
                    hour12: true
                }
            );


        dateElement.textContent =
            manilaDateString;

        timeElement.textContent =
            manilaTimeString;


        currentTimestamp++;

    }


    updateManagerClock();


    setInterval(
        updateManagerClock,
        1000
    );

})();

/* =========================================================
   NOTIFICATION DROPDOWN
   ========================================================= */

(function () {

    var notificationButton =
        document.getElementById(
            'notificationButton'
        );

    var notificationDropdown =
        document.getElementById(
            'notificationDropdown'
        );

    var notificationWrapper =
        document.getElementById(
            'notificationWrapper'
        );


    if (
        !notificationButton ||
        !notificationDropdown ||
        !notificationWrapper
    ) {
        return;
    }


    notificationButton.addEventListener(
        'click',
        function (event) {

            event.stopPropagation();


            var isHidden =
                notificationDropdown.classList.contains(
                    'hidden'
                );


            if (isHidden) {

                notificationDropdown.classList.remove(
                    'hidden'
                );

                notificationButton.setAttribute(
                    'aria-expanded',
                    'true'
                );

            } else {

                notificationDropdown.classList.add(
                    'hidden'
                );

                notificationButton.setAttribute(
                    'aria-expanded',
                    'false'
                );

            }

        }
    );


    document.addEventListener(
        'click',
        function (event) {

            if (
                !notificationWrapper.contains(
                    event.target
                )
            ) {

                notificationDropdown.classList.add(
                    'hidden'
                );

                notificationButton.setAttribute(
                    'aria-expanded',
                    'false'
                );

            }

        }
    );

})();
/* =========================================================
   AUTOMATIC RESERVATION FILTER
   ========================================================= */

(function () {

    var searchInput =
        document.getElementById('reservationSearch');

    var dateFromInput =
        document.getElementById('dateFrom');

    var dateToInput =
        document.getElementById('dateTo');

    var filterForm =
        document.getElementById('reservationFilterForm');

    var searchTimer = null;

    function submitReservationFilter() {

        if (!filterForm) {
            return;
        }

        filterForm.submit();
    }


    /* SEARCH WHILE TYPING */

    if (searchInput) {

        searchInput.addEventListener('input', function () {

            clearTimeout(searchTimer);

            searchTimer = setTimeout(function () {

                submitReservationFilter();

            }, 350);

        });

    }


    /* RESERVED FROM */

    if (dateFromInput) {

        dateFromInput.addEventListener('change', function () {

            submitReservationFilter();

        });

    }


    /* RESERVED TO */

    if (dateToInput) {

        dateToInput.addEventListener('change', function () {

            submitReservationFilter();

        });

    }

})();


function openStatusModal(code, currentStatus) {

    document.getElementById(
        'modalReservationCode'
    ).value = code;

    var statusSelect = document.querySelector(
        '#statusModal select[name="new_status"]'
    );

    if (statusSelect) {
        statusSelect.value = currentStatus;
    }

    var modal =
        document.getElementById('statusModal');

    modal.classList.remove('hidden');
    modal.classList.add('flex');
}


function closeStatusModal() {

    var modal =
        document.getElementById('statusModal');

    modal.classList.add('hidden');
    modal.classList.remove('flex');
}


window.onclick = function(event) {

    var modal =
        document.getElementById('statusModal');

    if (event.target === modal) {
        closeStatusModal();
    }

};

</script>

</body>
</html>