<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>VDVC Egg Farm - Forgot Password</title>


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


            .form-group .input-icon {

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
            Forgot Password?
        </h1>


        <p class="welcome-subtitle">

            Enter your email address and we'll send you a link<br>
 
            to reset your password.

        </p>


        <!-- MESSAGE -->
        <?php if (isset($_GET['message'])): ?>
            <div class="message <?php echo htmlspecialchars($_GET['type'] ?? 'success'); ?>">
                <?php echo htmlspecialchars($_GET['message']); ?>
            </div>
        <?php endif; ?>


        <!-- FORM -->

        <form
            action="forgot_password_process.php"
            method="POST"
            class="login-form"
        >


            <!-- EMAIL -->

            <div class="form-group">

                <i class="fa-regular fa-envelope input-icon"></i>

                <input
                    type="email"
                    name="email"
                    class="form-control"
                    placeholder="Email Address"
                    required
                >

            </div>


            <!-- SUBMIT BUTTON -->

            <button
                type="submit"
                class="btn-submit"
            >

                Send Reset Link

                <i
                    class="fa-solid fa-paper-plane"
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


    </div>


</body>


</html>
