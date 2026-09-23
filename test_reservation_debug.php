<?php
// Test script to debug reservation confirmation without UI
require 'db.php';

echo "=== RESERVATION CONFIRMATION DEBUG ===\n\n";

// 1. Check actual database ENUM values
echo "1. CHECKING ACTUAL DATABASE ENUM VALUES:\n";
$result = mysqli_query($conn, "SHOW COLUMNS FROM egg_inventory LIKE 'egg_size'");
if ($result) {
    $row = mysqli_fetch_assoc($result);
    echo "   egg_size column definition: " . $row['Type'] . "\n";
} else {
    echo "   ERROR: Could not get egg_size column info: " . mysqli_error($conn) . "\n";
}
echo "\n";

// 2. Check actual reservation egg_type values
echo "2. CHECKING ACTUAL RESERVATION EGG_TYPE VALUES:\n";
$result = mysqli_query($conn, "SELECT DISTINCT egg_type FROM reservations");
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        echo "   Found egg_type: '" . $row['egg_type'] . "'\n";
    }
} else {
    echo "   ERROR: Could not get reservation egg_types: " . mysqli_error($conn) . "\n";
}
echo "\n";

// 3. Test the mapping logic
echo "3. TESTING MAPPING LOGIC:\n";
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

$result = mysqli_query($conn, "SELECT DISTINCT egg_type FROM reservations");
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $egg_type = trim($row['egg_type']);
        if (isset($egg_size_map[$egg_type])) {
            $mapped = $egg_size_map[$egg_type];
            echo "   '$egg_type' → '$mapped'\n";
        } else {
            echo "   '$egg_type' → NO MAPPING FOUND\n";
        }
    }
}
echo "\n";

// 4. Check if there are any pending reservations
echo "4. CHECKING PENDING RESERVATIONS:\n";
$result = mysqli_query($conn, "SELECT id, reservation_code, egg_type, quantity, status FROM reservations WHERE status = 'Pending' LIMIT 5");
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        echo "   ID: " . $row['id'] . ", Code: " . $row['reservation_code'] . ", Type: '" . $row['egg_type'] . "', Qty: " . $row['quantity'] . ", Status: " . $row['status'] . "\n";
    }
} else {
    echo "   ERROR: Could not get pending reservations: " . mysqli_error($conn) . "\n";
}
echo "\n";

// 5. Test a simple INSERT with valid ENUM value
echo "5. TESTING INSERT WITH VALID ENUM VALUE:\n";
$test_egg_size = 'Large'; // This should definitely work since it requires no mapping
$test_query = "INSERT INTO egg_inventory (batch_id, harvest_date, harvest_time, egg_size, quantity, current_stock, movement_type, reason, date_logged) VALUES ('TEST-001', '2026-09-23', '12:00:00', '$test_egg_size', 1, 1, 'Stock Out', 'Reservation', '2026-09-23 12:00:00')";
echo "   Test query: $test_query\n";
$result = mysqli_query($conn, $test_query);
if ($result) {
    echo "   SUCCESS: Test INSERT worked\n";
    // Clean up the test record
    mysqli_query($conn, "DELETE FROM egg_inventory WHERE batch_id = 'TEST-001'");
} else {
    echo "   FAILED: Test INSERT failed with error: " . mysqli_error($conn) . "\n";
}
echo "\n";

// 6. Test INSERT with mapped value
echo "6. TESTING INSERT WITH MAPPED VALUE (XL):\n";
$test_egg_size = 'XL'; // This is the mapped value for 'Extra Large'
$test_query = "INSERT INTO egg_inventory (batch_id, harvest_date, harvest_time, egg_size, quantity, current_stock, movement_type, reason, date_logged) VALUES ('TEST-002', '2026-09-23', '12:00:00', '$test_egg_size', 1, 1, 'Stock Out', 'Reservation', '2026-09-23 12:00:00')";
echo "   Test query: $test_query\n";
$result = mysqli_query($conn, $test_query);
if ($result) {
    echo "   SUCCESS: Test INSERT with XL worked\n";
    // Clean up the test record
    mysqli_query($conn, "DELETE FROM egg_inventory WHERE batch_id = 'TEST-002'");
} else {
    echo "   FAILED: Test INSERT with XL failed with error: " . mysqli_error($conn) . "\n";
}
echo "\n";

echo "=== DEBUG COMPLETE ===\n";
?>