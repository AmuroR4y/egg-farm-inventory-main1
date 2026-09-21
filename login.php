<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>VDVC Egg Farm - Login</title>


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
           LOGIN FORM
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
           FORGOT PASSWORD
        ========================================= */

        .form-utilities {

            display: flex;

            justify-content: flex-end;

            align-items: center;

            margin-top: -2px;

            margin-bottom: 14px;

        }


        .forgot-link {

            color: #5d6d74;

            text-decoration: none;

            font-size: 12px;

            font-weight: 500;

            transition: all 0.2s ease;

        }


        .forgot-link:hover {

            color: #294a38;

            text-decoration: underline;

        }


        /* =========================================
           LOGIN BUTTON
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
           SIGN UP
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
           DIVIDER
        ========================================= */

        .divider {

            display: flex;

            align-items: center;

            gap: 20px;

            margin: 16px 0;

        }


        .divider::before,
        .divider::after {

            content: "";

            flex: 1;

            height: 1px;

            background: #cbd0cc;

        }


        .divider span {

            color: #69727a;

            font-size: 14px;

        }


        /* =========================================
           FARM FOOTER
        ========================================= */

        .farm-footer {

            text-align: center;

        }


        .farm-name {
margin-top: 20px;
            display: flex;

            align-items: center;

            justify-content: center;

            gap: 8px;

            color: #53636b;

            font-size: 13px;

            font-weight: 500;

            letter-spacing: 0.4px;

        }


        .farm-name::before,
        .farm-name::after {

            content: "";

            width: 45px;

            height: 1px;

            background: #9fa9a4;

        }


        .farm-name i {

            color: #345c45;

            font-size: 10px;

        }


        .farm-tagline {

            margin-top: 3px;

            color: #68747d;

            font-size: 11px;

            letter-spacing: 0.3px;

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


            .divider {

                margin: 15px 0;

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
            Welcome Back!
        </h1>


        <p class="welcome-subtitle">

            Log in to your account to access your dashboard and<br>
 
            manage your activities with ease.

        </p>


        <!-- LOGIN FORM -->

        <form
            action="login_process.php"
            method="POST"
            class="login-form"
        >


            <!-- USERNAME -->

            <div class="form-group">

                <i class="fa-regular fa-user input-icon"></i>

                <input
                    type="text"
                    name="username"
                    class="form-control"
                    placeholder="Username"
                    required
                >

            </div>


            <!-- PASSWORD -->

            <div class="form-group password-group">

                <i class="fa-solid fa-lock input-icon"></i>

                <input
                    type="password"
                    name="password"
                    id="password"
                    class="form-control"
                    placeholder="Password"
                    required
                >


                <i
                    class="fa-solid fa-eye password-toggle"
                    id="passwordToggle"
                    onclick="togglePassword()"
                ></i>

            </div>


            <!-- FORGOT PASSWORD -->

            <div class="form-utilities">

                <a
                    href="#"
                    class="forgot-link"
                >
                    Forgot Password?
                </a>

            </div>


            <!-- LOGIN BUTTON -->

            <button
                type="submit"
                class="btn-submit"
            >

                Log In

                <i
                    class="fa-solid fa-arrow-right"
                    style="margin-left: 8px;"
                ></i>

            </button>


            <!-- SIGN UP -->

           


        </form>


        <!-- DIVIDER -->

        <div class="divider">

            <span>or</span>

        </div>

 <div class="form-footer">

                Don't have an account?

                <a href="register.php">
                    Sign Up
                </a>

            </div>
        <!-- FARM FOOTER -->

        <div class="farm-footer">

            <div class="farm-name">

                <i class="fa-solid fa-seedling"></i>

                <span>VDVC Egg Farm</span>

                <i class="fa-solid fa-seedling"></i>

            </div>


            <div class="farm-tagline">

                Fresh Eggs. Trusted Farm. Better for You.

            </div>

        </div>


    </div>


    <!-- PASSWORD SHOW/HIDE -->

    <script>

        function togglePassword() {

            var password =
                document.getElementById("password");

            var toggle =
                document.getElementById("passwordToggle");


            if (password.type === "password") {

                password.type = "text";

                toggle.className =
                    "fa-solid fa-eye-slash password-toggle";

            }

            else {

                password.type = "password";

                toggle.className =
                    "fa-solid fa-eye password-toggle";

            }

        }

    </script>


</body>

</html>