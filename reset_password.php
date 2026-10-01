<?php
/**
 * Reset Password Page
 * Validates token and shows password reset form
 */

require_once 'db.php';

// Get token from URL
$token = $_GET['token'] ?? '';

// Validate token format
if ($token === '' || !ctype_xdigit($token) || strlen($token) !== 64) {
    $error = "Invalid reset link. Please request a new password reset.";
    $validToken = false;
} else {
    // Hash the token for database lookup
    $tokenHash = hash('sha256', $token);

    // Look up token in database
    $stmt = mysqli_prepare($conn, "
        SELECT prt.id, prt.user_id, prt.expires_at, prt.used, u.fullname, u.status
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
        $error = "Invalid reset link. Please request a new password reset.";
        $validToken = false;
    } elseif ($tokenData['used'] == 1) {
        $error = "This reset link has already been used. Please request a new password reset.";
        $validToken = false;
    } elseif (strtotime($tokenData['expires_at']) < time()) {
        $error = "This reset link has expired. Please request a new password reset.";
        $validToken = false;
    } elseif ($tokenData['status'] !== 'active') {
        $error = "Your account is not active. Please contact support.";
        $validToken = false;
    } else {
        $validToken = true;
        $userId = (int)$tokenData['user_id'];
        $userFullname = $tokenData['fullname'];
    }
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

    <title>VDVC Egg Farm - Reset Password</title>


    <!-- Google Font -->

    <link
        href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap"
        rel="stylesheet"
    >


    <!-- Font Awesome -->

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"
    >


    <style>

        /* =========================================
           GENERAL
        ========================================= */

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Poppins', sans-serif;
        }


        html,
        body {
            width: 100%;
            height: 100%;
        }


        body {

            min-height: 100vh;

            display: flex;

            justify-content: center;

            align-items: center;

            padding: 20px;

            position: relative;

            overflow: hidden;

            background-image: url('/egg-farm-inventory-main1/loginbg.PNG');

            background-size: cover;

            background-position: center;

            background-repeat: no-repeat;

        }


        /* =========================================
           BACKGROUND OVERLAY
        ========================================= */

        body::before {

            content: "";

            position: fixed;

            top: 0;

            left: 0;

            width: 100%;

            height: 100%;

            background: rgba(255, 255, 255, 0.06);

            pointer-events: none;

            z-index: 0;

        }


        /* =========================================
           LOGIN CARD
        ========================================= */

    .login-card {
    width: 100%;
    max-width: 500px;
    max-height: calc(100vh - 40px);
    padding: 22px 30px 20px;

    text-align: center;
    position: relative;
    z-index: 1;

    background: rgba(255, 255, 255, 0.90);

    border: 1px solid rgba(255, 255, 255, 0.7);

    border-radius: 25px;

    box-shadow:
        0 15px 40px rgba(66, 53, 27, 0.16),
        0 4px 10px rgba(66, 53, 27, 0.08);

    backdrop-filter: blur(8px);
    -webkit-backdrop-filter: blur(8px);
}


        /* =========================================
           LOGO
        ========================================= */

        .logo-container {

            margin-bottom: 8px;

        }


        .logo-img {

            width: 320px;

            max-width: 100%;

            height: auto;

            display: block;

            margin: 0 auto;

        }


        /* =========================================
           WELCOME TEXT
        ========================================= */

        .welcome-title {

            color: #274636;

            font-size: 34px;

            font-weight: 700;

            line-height: 1.2;

            margin-bottom: 6px;

        }


        .welcome-subtitle {

            max-width: 470px;

            margin: 0 auto 18px;

            color: #65707a;

            font-size: 14px;

            font-weight: 400;

            line-height: 1.6;

        }


        /* =========================================
           FORM
        ========================================= */

        .login-form {

            width: 100%;

            max-width: 540px;

            margin: 0 auto;

        }


        .form-group {

            position: relative;

            margin-bottom: 12px;

        }


        /* =========================================
           INPUT ICON
        ========================================= */

        .form-group .input-icon {

            position: absolute;

            left: 20px;

            top: 50%;

            transform: translateY(-50%);

            color: #65737c;

            font-size: 18px;

            z-index: 2;

        }


        /* =========================================
           FORM INPUT
        ========================================= */

        .form-control {

            width: 100%;

            height: 58px;

            padding: 0 55px;

            border: 1px solid #c8ced0;

            border-radius: 13px;

            background: rgba(255, 255, 255, 0.65);

            color: #39434a;

            font-size: 16px;

            outline: none;

            transition: all 0.25s ease;

        }


        .form-control::placeholder {

            color: #737d84;

        }


        .form-control:focus {

            border-color: #52715e;

            box-shadow: 0 0 0 3px rgba(82, 113, 94, 0.12);

            background: #ffffff;

        }


        /* =========================================
           PASSWORD TOGGLE
        ========================================= */

        .password-toggle {

            position: absolute;

            right: 20px;

            top: 50%;

            transform: translateY(-50%);

            color: #65737c;

            font-size: 19px;

            cursor: pointer;

            z-index: 2;

            transition: color 0.2s ease;

        }


        .password-toggle:hover {

            color: #294a38;

        }


        /* =========================================
           BUTTON
        ========================================= */

        .btn-submit {

            width: 100%;

            height: 58px;

            border: none;

            border-radius: 13px;

            background: linear-gradient(
                135deg,
                #3e634d,
                #2e4e3c
            );

            color: #ffffff;

            font-size: 17px;

            font-weight: 600;

            letter-spacing: 0.5px;

            cursor: pointer;

            transition: all 0.25s ease;

            box-shadow:
                0 6px 14px rgba(46, 78, 60, 0.18);

        }


        .btn-submit:hover {

            transform: translateY(-2px);

            background: linear-gradient(
                135deg,
                #345843,
                #254332
            );

            box-shadow:
                0 10px 18px rgba(46, 78, 60, 0.25);

        }


        .btn-submit:active {

            transform: translateY(0);

        }


        /* =========================================
           BACK TO LOGIN
        ========================================= */

        .form-footer {

            margin-top: 5px;

            color: #65707a;

            font-size: 12px;

        }


        .form-footer a {

            color: #345c45;

            font-weight: 600;

            text-decoration: none;

            margin-left: 3px;

        }


        .form-footer a:hover {

            text-decoration: underline;

        }


        /* =========================================
           MESSAGE
        ========================================= */

        .message {

            margin-bottom: 15px;

            padding: 12px;

            border-radius: 8px;

            font-size: 14px;

        }

        .message.success {

            background: #d4edda;

            color: #155724;

            border: 1px solid #c3e6cb;

        }

        .message.error {

            background: #f8d7da;

            color: #721c24;

            border: 1px solid #f5c6cb;

        }


        /* =========================================
           PASSWORD REQUIREMENTS
        ========================================= */

        .password-requirements {
            text-align: left;
            margin: 10px 0 15px;
            padding: 12px;
            background: rgba(255, 255, 255, 0.8);
            border-radius: 8px;
            font-size: 13px;
        }

        .password-requirements h4 {
            color: #274636;
            margin: 0 0 8px;
            font-size: 14px;
        }

        .password-requirements ul {
            margin: 0;
            padding-left: 20px;
            color: #65707a;
        }

        .password-requirements li {
            margin: 4px 0;
        }


        /* =========================================
           TABLET
        ========================================= */

        @media screen and (max-width: 768px) {

            body {

                overflow-y: auto;

                padding: 20px;

            }


            .login-card {

                max-width: 550px;

                max-height: none;

                padding: 30px 35px;

            }


            .logo-img {

                width: 300px;

            }


            .welcome-title {

                font-size: 30px;

            }

        }


        /* =========================================
           MOBILE
        ========================================= */

        @media screen and (max-width: 480px) {

            body {

                padding: 15px;

                align-items: flex-start;

            }


            .login-card {

                padding: 25px 20px;

                border-radius: 20px;

            }


            .logo-img {

                width: 250px;

            }


            .welcome-title {

                font-size: 27px;

            }


            .welcome-subtitle {

                font-size: 12px;

                margin-bottom: 18px;

            }


            .form-control {

                height: 55px;

                font-size: 14px;

            }


            .btn-submit {

                height: 55px;

                font-size: 16px;

            }


            .form-group .input-icon,
            .password-toggle {

                font-size: 17px;

            }

        }

    </style>

</head>


<body>


    <!-- LOGIN CARD -->

    <div class="login-card">


        <!-- LOGO -->

        <div class="logo-container">

            <img
                src="/egg-farm-inventory-main1/vdvclogo.png"
                alt="VDVC Egg Farm Logo"
                class="logo-img"
            >

        </div>


        <!-- WELCOME TEXT -->

        <h1 class="welcome-title">
            Reset Password
        </h1>


        <?php if (!$validToken): ?>
            <!-- ERROR MESSAGE -->
            <div class="message error">
                <?php echo htmlspecialchars($error); ?>
            </div>

            <div class="form-footer">
                <a href="forgot_password.php">Request a new password reset</a>
            </div>
        <?php else: ?>
            <p class="welcome-subtitle">

                Reset your password for <?php echo htmlspecialchars($userFullname); ?>.

            </p>


            <!-- MESSAGE -->
            <?php if (isset($_GET['message'])): ?>
                <div class="message <?php echo htmlspecialchars($_GET['type'] ?? 'success'); ?>">
                    <?php echo htmlspecialchars($_GET['message']); ?>
                </div>
            <?php endif; ?>


            <!-- FORM -->

            <form
                action="reset_password_process.php"
                method="POST"
                class="login-form"
            >

                <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">

                <!-- NEW PASSWORD -->

                <div class="form-group password-group">

                    <i class="fa-solid fa-lock input-icon"></i>

                    <input
                        type="password"
                        name="new_password"
                        id="new_password"
                        class="form-control"
                        placeholder="New Password"
                        required
                    >

                    <i
                        class="fa-solid fa-eye password-toggle"
                        id="newPasswordToggle"
                        onclick="toggleNewPassword()"
                    ></i>

                </div>


                <!-- CONFIRM PASSWORD -->

                <div class="form-group password-group">

                    <i class="fa-solid fa-lock input-icon"></i>

                    <input
                        type="password"
                        name="confirm_password"
                        id="confirm_password"
                        class="form-control"
                        placeholder="Confirm New Password"
                        required
                    >

                    <i
                        class="fa-solid fa-eye password-toggle"
                        id="confirmPasswordToggle"
                        onclick="toggleConfirmPassword()"
                    ></i>

                </div>


                <!-- PASSWORD REQUIREMENTS -->

                <div class="password-requirements">
                    <h4>Password Requirements:</h4>
                    <ul>
                        <li>Minimum 8 characters</li>
                        <li>At least one special character (!@#$%^&*(),.?":{}|<>)</li>
                        <li>Passwords must match</li>
                    </ul>
                </div>


                <!-- SUBMIT BUTTON -->

                <button
                    type="submit"
                    class="btn-submit"
                >

                    Reset Password

                    <i
                        class="fa-solid fa-key"
                        style="margin-left: 8px;"
                    ></i>

                </button>


            </form>


            <!-- BACK TO LOGIN -->

            <div class="form-footer">

                Remember your password?

                <a href="login.php">
                    Back to Login
                </a>

            </div>
        <?php endif; ?>


    </div>


    <!-- PASSWORD TOGGLE SCRIPTS -->

    <script>
        function toggleNewPassword() {
            var password = document.getElementById("new_password");
            var toggle = document.getElementById("newPasswordToggle");

            if (password.type === "password") {
                password.type = "text";
                toggle.className = "fa-solid fa-eye-slash password-toggle";
            } else {
                password.type = "password";
                toggle.className = "fa-solid fa-eye password-toggle";
            }
        }

        function toggleConfirmPassword() {
            var password = document.getElementById("confirm_password");
            var toggle = document.getElementById("confirmPasswordToggle");

            if (password.type === "password") {
                password.type = "text";
                toggle.className = "fa-solid fa-eye-slash password-toggle";
            } else {
                password.type = "password";
                toggle.className = "fa-solid fa-eye password-toggle";
            }
        }
    </script>


</body>


</html>
