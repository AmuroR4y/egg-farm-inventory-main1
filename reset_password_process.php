<?php
/**
 * Reset Password Process Handler
 * Validates token and updates password
 */

require_once 'db.php';

header('Content-Type: text/html; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] != 'POST') {
    header("Location: login.php");
    exit();
}

// Get form data
$token = $_POST['token'] ?? '';
$newPassword = $_POST['new_password'] ?? '';
$confirmPassword = $_POST['confirm_password'] ?? '';

// Validate token format
if ($token === '' || !ctype_xdigit($token) || strlen($token) !== 64) {
    header("Location: reset_password.php?token=" . urlencode($token) . "&message=Invalid reset link.&type=error");
    exit();
}

// Validate passwords
if ($newPassword === '') {
    header("Location: reset_password.php?token=" . urlencode($token) . "&message=New password is required.&type=error");
    exit();
}

if ($confirmPassword === '') {
    header("Location: reset_password.php?token=" . urlencode($token) . "&message=Please confirm your new password.&type=error");
    exit();
}

if ($newPassword !== $confirmPassword) {
    header("Location: reset_password.php?token=" . urlencode($token) . "&message=Passwords do not match.&type=error");
    exit();
}

// Password strength validation (same as Owner Profile)
if (strlen($newPassword) < 8) {
    header("Location: reset_password.php?token=" . urlencode($token) . "&message=Password must be at least 8 characters long.&type=error");
    exit();
}

if (!preg_match('/[!@#$%^&*(),.?":{}|<>]/', $newPassword)) {
    header("Location: reset_password.php?token=" . urlencode($token) . "&message=Password must contain at least one special character.&type=error");
    exit();
}

// Hash the token for database lookup
$tokenHash = hash('sha256', $token);

// Look up token in database
$stmt = mysqli_prepare($conn, "
    SELECT prt.id, prt.user_id, prt.expires_at, prt.used, u.status
    FROM password_reset_tokens prt
    JOIN users u ON prt.user_id = u.id
    WHERE prt.token_hash = ?
    LIMIT 1
");
mysqli_stmt_bind_param($stmt, 's', $tokenHash);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$tokenData = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

// Validate token
if (!$tokenData) {
    header("Location: reset_password.php?token=" . urlencode($token) . "&message=Invalid reset link.&type=error");
    exit();
}

if ($tokenData['used'] == 1) {
    header("Location: reset_password.php?token=" . urlencode($token) . "&message=This reset link has already been used.&type=error");
    exit();
}

if (strtotime($tokenData['expires_at']) < time()) {
    header("Location: reset_password.php?token=" . urlencode($token) . "&message=This reset link has expired.&type=error");
    exit();
}

if ($tokenData['status'] !== 'active') {
    header("Location: reset_password.php?token=" . urlencode($token) . "&message=Your account is not active.&type=error");
    exit();
}

$userId = (int)$tokenData['user_id'];
$tokenId = (int)$tokenData['id'];

// Hash new password with modern algorithm
$newPasswordHash = password_hash($newPassword, PASSWORD_DEFAULT);

// Update password
$updateStmt = mysqli_prepare($conn, "UPDATE users SET password = ? WHERE id = ?");
mysqli_stmt_bind_param($updateStmt, 'si', $newPasswordHash, $userId);
$updateSuccess = mysqli_stmt_execute($updateStmt);
mysqli_stmt_close($updateStmt);

if ($updateSuccess) {
    // Mark token as used
    $markUsedStmt = mysqli_prepare($conn, "UPDATE password_reset_tokens SET used = 1 WHERE id = ?");
    mysqli_stmt_bind_param($markUsedStmt, 'i', $tokenId);
    mysqli_stmt_execute($markUsedStmt);
    mysqli_stmt_close($markUsedStmt);

    // Invalidate other outstanding tokens for this user
    $invalidateStmt = mysqli_prepare($conn, "UPDATE password_reset_tokens SET used = 1 WHERE user_id = ? AND used = 0 AND id != ?");
    mysqli_stmt_bind_param($invalidateStmt, 'ii', $userId, $tokenId);
    mysqli_stmt_execute($invalidateStmt);
    mysqli_stmt_close($invalidateStmt);

    // Redirect to login with success message
    header("Location: login.php?message=Password reset successfully. Please log in with your new password.&type=success");
    exit();
} else {
    header("Location: reset_password.php?token=" . urlencode($token) . "&message=Failed to reset password. Please try again.&type=error");
    exit();
}
?>
