<?php
// Database Schema Diagnostic Script
// Uses the exact same database connection as the project
require 'db.php';

echo "<h1>LIVE DATABASE SCHEMA DIAGNOSTIC</h1>";
echo "<p><strong>Database:</strong> egg_farm_system</p>";
echo "<p><strong>Connection:</strong> localhost (root/empty password)</p>";

echo "<h2>1. CHECK ACTUAL egg_size COLUMN DEFINITION</h2>";
$result = mysqli_query($conn, "SHOW COLUMNS FROM egg_inventory LIKE 'egg_size'");
if ($result) {
    $row = mysqli_fetch_assoc($result);
    echo "<pre>";
    print_r($row);
    echo "</pre>";
} else {
    echo "<p>ERROR: " . mysqli_error($conn) . "</p>";
}

echo "<h2>2. CHECK ACTUAL TABLE DEFINITION</h2>";
$result = mysqli_query($conn, "SHOW CREATE TABLE egg_inventory");
if ($result) {
    $row = mysqli_fetch_assoc($result);
    echo "<pre>";
    echo htmlspecialchars($row['Create Table']);
    echo "</pre>";
} else {
    echo "<p>ERROR: " . mysqli_error($conn) . "</p>";
}

echo "<h2>3. CHECK DATABASE SETTINGS</h2>";
$result = mysqli_query($conn, "SELECT @@sql_mode, @@character_set_database, @@collation_database");
if ($result) {
    $row = mysqli_fetch_assoc($result);
    echo "<pre>";
    print_r($row);
    echo "</pre>";
} else {
    echo "<p>ERROR: " . mysqli_error($conn) . "</p>";
}

echo "<h2>4. CHECK FULL COLUMN DETAILS</h2>";
$result = mysqli_query($conn, "SHOW FULL COLUMNS FROM egg_inventory");
if ($result) {
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>Field</th><th>Type</th><th>Collation</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    while ($row = mysqli_fetch_assoc($result)) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($row['Field']) . "</td>";
        echo "<td>" . htmlspecialchars($row['Type']) . "</td>";
        echo "<td>" . htmlspecialchars($row['Collation']) . "</td>";
        echo "<td>" . htmlspecialchars($row['Null']) . "</td>";
        echo "<td>" . htmlspecialchars($row['Key']) . "</td>";
        echo "<td>" . htmlspecialchars($row['Default']) . "</td>";
        echo "<td>" . htmlspecialchars($row['Extra']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>ERROR: " . mysqli_error($conn) . "</p>";
}

echo "<h2>5. CHECK FOR TRIGGERS</h2>";
$result = mysqli_query($conn, "SHOW TRIGGERS FROM egg_farm_system");
if ($result) {
    $triggers = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $triggers[] = $row;
    }
    if (count($triggers) > 0) {
        echo "<p>Found " . count($triggers) . " triggers:</p>";
        echo "<pre>";
        print_r($triggers);
        echo "</pre>";
    } else {
        echo "<p>No triggers found.</p>";
    }
} else {
    echo "<p>ERROR: " . mysqli_error($conn) . "</p>";
}

echo "<h2>6. TEST DIRECT INSERT OF 'XS'</h2>";
echo "<p>Starting transaction for safe test...</p>";
mysqli_begin_transaction($conn);

// First, verify which database we're connected to
$result = mysqli_query($conn, "SELECT DATABASE()");
$db_row = mysqli_fetch_assoc($result);
echo "<p><strong>Current Database:</strong> " . htmlspecialchars($db_row['DATABASE()']) . "</p>";

// Also check server details
$result = mysqli_query($conn, "SELECT @@hostname, @@port");
$server_row = mysqli_fetch_assoc($result);
echo "<p><strong>Server:</strong> " . htmlspecialchars($server_row['@@hostname']) . ":" . htmlspecialchars($server_row['@@port']) . "</p>";

// Create exact test variables matching the actual reservation confirmation
$test_batch_id = 'DIAG-TEST';
$test_harvest_date = '2026-09-23';
$test_harvest_time = '16:40:48';
$test_egg_size = 'XS';
$test_quantity = 1;
$test_current_stock = 1;
$test_date_logged = '2026-09-23 16:40:48';

echo "<p><strong>Test values (matching actual reservation structure):</strong></p>";
echo "<pre>";
echo "test_batch_id: " . var_export($test_batch_id, true) . " (type: " . gettype($test_batch_id) . ")\n";
echo "test_harvest_date: " . var_export($test_harvest_date, true) . " (type: " . gettype($test_harvest_date) . ")\n";
echo "test_harvest_time: " . var_export($test_harvest_time, true) . " (type: " . gettype($test_harvest_time) . ")\n";
echo "test_egg_size: " . var_export($test_egg_size, true) . " (type: " . gettype($test_egg_size) . ")\n";
echo "test_quantity: " . var_export($test_quantity, true) . " (type: " . gettype($test_quantity) . ")\n";
echo "test_current_stock: " . var_export($test_current_stock, true) . " (type: " . gettype($test_current_stock) . ")\n";
echo "test_date_logged: " . var_export($test_date_logged, true) . " (type: " . gettype($test_date_logged) . ")\n";
echo "</pre>";

echo "<p><strong>Expected bind_param types: 'ssssiis' (7 characters for 7 parameters)</strong></p>";
echo "<p><strong>Type breakdown:</strong> s=batch_id, s=harvest_date, s=harvest_time, s=egg_size, i=quantity, i=current_stock, s=date_logged</p>";

$test_query = "INSERT INTO egg_inventory (batch_id, harvest_date, harvest_time, egg_size, quantity, current_stock, movement_type, reason, date_logged) VALUES (?, ?, ?, ?, ?, ?, 'Stock Out', 'Reservation', ?)";
$stmt = mysqli_prepare($conn, $test_query);

if ($stmt) {
    echo "<p><strong>Prepare statement: SUCCESS</strong></p>";

    // Use CORRECT bind_param type string: "ssssiis" (7 characters for 7 parameters)
    $bind_result = mysqli_stmt_bind_param($stmt, "ssssiis", $test_batch_id, $test_harvest_date, $test_harvest_time, $test_egg_size, $test_quantity, $test_current_stock, $test_date_logged);
    echo "<p><strong>bind_param result:</strong> " . ($bind_result ? "SUCCESS" : "FAILED") . "</p>";

    if ($bind_result) {
        $execute_result = mysqli_stmt_execute($stmt);
        echo "<p><strong>execute result:</strong> " . ($execute_result ? "SUCCESS" : "FAILED") . "</p>";

        if (!$execute_result) {
            echo "<p><strong>MySQL Error:</strong> " . mysqli_stmt_error($stmt) . "</p>";
            echo "<p><strong>MySQL Error Number:</strong> " . mysqli_stmt_errno($stmt) . "</p>";
        } else {
            echo "<p><strong>✅ DIRECT XS INSERT SUCCEEDED!</strong></p>";
            // Clean up
            mysqli_query($conn, "DELETE FROM egg_inventory WHERE batch_id = '$test_batch_id'");
        }
    } else {
        echo "<p><strong>bind_param Error:</strong> " . mysqli_stmt_error($stmt) . "</p>";
    }

    mysqli_stmt_close($stmt);
} else {
    echo "<p><strong>Prepare failed:</strong> " . mysqli_error($conn) . "</p>";
}

mysqli_rollback($conn);
echo "<p><strong>Transaction rolled back (test data cleaned).</strong></p>";

echo "<h2>7. CHECK EXISTING egg_size VALUES IN DATABASE</h2>";
$result = mysqli_query($conn, "SELECT DISTINCT egg_size FROM egg_inventory");
if ($result) {
    echo "<p>Existing egg_size values:</p>";
    echo "<ul>";
    while ($row = mysqli_fetch_assoc($result)) {
        echo "<li>" . htmlspecialchars($row['egg_size']) . "</li>";
    }
    echo "</ul>";
} else {
    echo "<p>ERROR: " . mysqli_error($conn) . "</p>";
}

echo "<h2>8. CHECK RESERVATION egg_type VALUES</h2>";
$result = mysqli_query($conn, "SELECT DISTINCT egg_type FROM reservations WHERE egg_type IS NOT NULL");
if ($result) {
    echo "<p>Existing reservation egg_type values:</p>";
    echo "<ul>";
    while ($row = mysqli_fetch_assoc($result)) {
        echo "<li>" . htmlspecialchars($row['egg_type']) . "</li>";
    }
    echo "</ul>";
} else {
    echo "<p>ERROR: " . mysqli_error($conn) . "</p>";
}

echo "<h2>DIAGNOSTIC COMPLETE</h2>";
?>