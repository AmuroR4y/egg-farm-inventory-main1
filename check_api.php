<?php
session_start();
$_SESSION['user_id'] = 1;
$_SESSION['role'] = 'owner';

require 'db.php';

// Fetch reservations
$reservations = [];
$reservationQuery = "SELECT id, customer_name, delivery_method, egg_type, quantity, total_price, status, created_at, reservation_date, user_id FROM reservations ORDER BY created_at DESC";
$reservationResult = mysqli_query($conn, $reservationQuery);

if ($reservationResult) {
    while ($row = mysqli_fetch_assoc($reservationResult)) {
        $status = strtolower($row['status']);
        $status = str_replace(' ', '_', $status);

        $reservations[] = [
            'id' => (int)$row['id'],
            'reservationCode' => 'RES-' . str_pad($row['id'], 5, '0', STR_PAD_LEFT),
            'customerName' => $row['customer_name'],
            'channel' => $row['delivery_method'],
            'product' => $row['egg_type'],
            'quantity' => (int)$row['quantity'],
            'amount' => (float)$row['total_price'],
            'status' => $status,
            'createdAt' => $row['created_at'],
            'scheduledAt' => $row['reservation_date'],
            'userId' => (int)$row['user_id']
        ];
    }
}

echo json_encode($reservations, JSON_PRETTY_PRINT);
mysqli_close($conn);
?>
