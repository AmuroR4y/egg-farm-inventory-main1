<?php
require_once 'db.php';

echo "<h2>Database Stock Verification</h2>";

// Query to get latest stock per egg size (same as snapshotEggs query)
$query = "SELECT egg_size, current_stock
FROM egg_inventory
WHERE id IN (
SELECT MAX(id)
FROM egg_inventory
GROUP BY egg_size
)";

$result = mysqli_query($conn, $query);

if (!$result) {
    die("Query failed: " . mysqli_error($conn));
}

echo "<h3>Latest Stock per Egg Size (Snapshot):</h3>";
echo "<table border='1' cellpadding='10'>";
echo "<tr><th>Egg Size</th><th>Current Stock</th></tr>";

$totalStock = 0;
while ($row = mysqli_fetch_assoc($result)) {
    echo "<tr><td>" . htmlspecialchars($row['egg_size']) . "</td><td>" . $row['current_stock'] . "</td></tr>";
    $totalStock += intval($row['current_stock']);
}
echo "</table>";

echo "<h3>Total Stock (Sum of Latest Records): " . $totalStock . "</h3>";

// For comparison: Sum of ALL historical records (the bug)
$queryAll = "SELECT SUM(current_stock) as total FROM egg_inventory";
$resultAll = mysqli_query($conn, $queryAll);
if ($resultAll) {
    $rowAll = mysqli_fetch_assoc($resultAll);
    echo "<h3>Total Stock (Sum of ALL Historical Records - BUGGY): " . $rowAll['total'] . "</h3>";
    echo "<p><strong>Difference:</strong> " . ($rowAll['total'] - $totalStock) . " units</p>";
}

mysqli_close($conn);
?>
