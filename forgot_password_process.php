<?php
/**
 * Forgot Password Process Handler
 * Generates password reset token and sends email via Brevo SMTP
 */

require_once 'db.php';
require_once 'email_config.php';

// Load PHPMailer
require_once __DIR__ . '/vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

header('Content-Type: text/html; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] != 'POST') {
    header("Location: forgot_password.php");
    exit();
}

// Get and validate email
$email = trim($_POST['email'] ?? '');

if ($email === '') {
    header("Location: forgot_password.php?message=Please enter your email address.&type=error");
    exit();
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    header("Location: forgot_password.php?message=Please enter a valid email address.&type=error");
    exit();
}

// Prepare statement to find active user
$stmt = mysqli_prepare($conn, "SELECT id, fullname FROM users WHERE email = ? AND status = 'active' LIMIT 1");
mysqli_stmt_bind_param($stmt, 's', $email);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$user = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

// Only proceed if user exists
if ($user) {
    $userId = (int)$user['id'];
    $userFullname = $user['fullname'];

    // Invalidate previous unused tokens for this user
    $invalidateStmt = mysqli_prepare($conn, "UPDATE password_reset_tokens SET used = 1 WHERE user_id = ? AND used = 0");
    mysqli_stmt_bind_param($invalidateStmt, 'i', $userId);
    mysqli_stmt_execute($invalidateStmt);
    mysqli_stmt_close($invalidateStmt);

    // Generate secure token
    $token = bin2hex(random_bytes(32));
    $tokenHash = hash('sha256', $token);

    // Set expiration (1 hour from now)
    $expiresAt = date('Y-m-d H:i:s', time() + 3600);

    // Store token hash in database
    $insertStmt = mysqli_prepare($conn, "INSERT INTO password_reset_tokens (user_id, token_hash, expires_at) VALUES (?, ?, ?)");
    mysqli_stmt_bind_param($insertStmt, 'iss', $userId, $tokenHash, $expiresAt);
    $insertSuccess = mysqli_stmt_execute($insertStmt);
    mysqli_stmt_close($insertStmt);

    // If token was stored successfully, send email
    if ($insertSuccess) {
        // Create reset link
        $resetLink = "http://" . $_SERVER['HTTP_HOST'] . "/egg-farm-inventory-main1/reset_password.php?token=" . $token;

        // Load email template
        $emailTemplate = file_get_contents(__DIR__ . '/email_templates/password_reset.html');

        // Replace placeholders
        $emailTemplate = str_replace('{{FULLNAME}}', htmlspecialchars($userFullname), $emailTemplate);
        $emailTemplate = str_replace('{{RESET_LINK}}', $resetLink, $emailTemplate);
        $emailTemplate = str_replace('{{EXPIRY_HOURS}}', '1', $emailTemplate);

        try {
            $mail = new PHPMailer(true);

            // Server settings
            $mail->isSMTP();
            $mail->Host = BREVO_SMTP_HOST;
            $mail->Port = (int)BREVO_SMTP_PORT;
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->SMTPAuth = true;
            $mail->Username = BREVO_SMTP_USERNAME;
            $mail->Password = BREVO_SMTP_PASSWORD;

            // Recipients
            $mail->setFrom(BREVO_FROM_EMAIL, BREVO_FROM_NAME);
            $mail->addAddress($email, $userFullname);

            // Content
            $mail->isHTML(true);
            $mail->Subject = 'Password Reset Request - VDVC Egg Farm';
            $mail->Body = $emailTemplate;
            $mail->AltBody = "Hello " . $userFullname . ",\n\nYou requested a password reset for your VDVC Egg Farm account.\n\nClick the following link to reset your password:\n" . $resetLink . "\n\nThis link will expire in 1 hour.\n\nIf you did not request this password reset, please ignore this email.\n\nThank you,\nVDVC Egg Farm";

            // Send email
            $mail->send();

        } catch (Exception $e) {
            // Log error but don't expose to user
            error_log("Failed to send password reset email: " . $e->getMessage());
        }
    }
}

// Generic response - do not reveal whether email exists
header("Location: forgot_password.php?message=If an account with that email exists, a password reset link has been sent.&type=success");
exit();
?>
