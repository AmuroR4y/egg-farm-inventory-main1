<?php
include 'db.php';

// Set owner password to "owner"
$hash = md5('owner');
$stmt = mysqli_prepare($conn, "UPDATE users SET password = ? WHERE id = 1");
mysqli_stmt_bind_param($stmt, 's', $hash);
mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);

echo "Owner password set to 'owner'\n";
mysqli_close($conn);
?>
