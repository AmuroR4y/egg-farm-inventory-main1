<!DOCTYPE html>

<html lang="en">

<head>


<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>Create Account - VDVC Egg Farm</title>


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
        min-height: 100%;
    }


    body {

        min-height: 100vh;

        display: flex;

        justify-content: center;

        align-items: center;

        padding: 12px;

        position: relative;

        overflow: hidden;

        background-image: url('/EggFarm/loginbg.PNG');

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
       REGISTER CARD
    ========================================= */

    .register-card {

    width: 100%;

    max-width: 500px;

    position: relative;

    z-index: 1;

    text-align: center;

    padding: 28px 35px 26px;

    background: rgba(255, 255, 255, 0.90);

    border: 1px solid rgba(255, 255, 255, 0.75);

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

        margin-bottom: 7px;

    }


    .logo-img {

        width: 270px;

        max-width: 100%;

        height: auto;

        display: block;

        margin: 0 auto;

    }


    /* =========================================
       HEADER TEXT
    ========================================= */

    .register-title {

        color: #274636;

        font-size: 29px;

        font-weight: 700;

        line-height: 1.2;

        margin-bottom: 5px;

    }


    .register-subtitle {

        max-width: 420px;

        margin: 0 auto 15px;

        color: #65707a;

        font-size: 12px;

        font-weight: 400;

        line-height: 1.6;

    }


    /* =========================================
       FORM
    ========================================= */

    .register-form {

        width: 100%;

        margin: 0 auto;

    }


    .form-group {

        position: relative;

        margin-bottom: 16px;

    }


    /* =========================================
       INPUT ICON
    ========================================= */

    .input-icon {

        position: absolute;

        left: 18px;

        top: 50%;

        transform: translateY(-50%);

        color: #65737c;

        font-size: 16px;

        z-index: 2;

    }


    /* =========================================
       FORM CONTROL
    ========================================= */

    .form-control {

        width: 100%;

        height: 58px;

        padding: 0 50px;

        border: 1px solid #c8ced0;

        border-radius: 11px;

        background: rgba(255, 255, 255, 0.65);

        color: #39434a;

        font-size: 13px;

        outline: none;

        transition: all 0.25s ease;

    }


    .form-control::placeholder {

        color: #737d84;

    }


    .form-control:focus {

        border-color: #52715e;

        box-shadow:
            0 0 0 3px
            rgba(82, 113, 94, 0.12);

        background: #ffffff;

    }


    /* =========================================
       PASSWORD TOGGLE
    ========================================= */

    .toggle-password {

        position: absolute;

        right: 18px;

        top: 50%;

        transform: translateY(-50%);

        color: #65737c;

        font-size: 17px;

        cursor: pointer;

        z-index: 3;

        transition: color 0.2s ease;

    }


    .toggle-password:hover {

        color: #294a38;

    }


    /* =========================================
       REGISTER BUTTON
    ========================================= */

    .btn-submit {

        width: 100%;

        height: 58px;

        margin-top: 3px;

        border: none;

        border-radius: 11px;

        background: linear-gradient(
            135deg,
            #3e634d,
            #2e4e3c
        );

        color: #ffffff;

        font-size: 14px;

        font-weight: 600;

        letter-spacing: 0.5px;

        cursor: pointer;

        transition: all 0.25s ease;

        box-shadow:
            0 6px 14px
            rgba(46, 78, 60, 0.18);

    }


    .btn-submit:hover {

        transform: translateY(-2px);

        background: linear-gradient(
            135deg,
            #345843,
            #254332
        );

        box-shadow:
            0 10px 18px
            rgba(46, 78, 60, 0.25);

    }


    .btn-submit:active {

        transform: translateY(0);

    }


    .btn-submit i {

        margin-left: 8px;

    }


    /* =========================================
       DIVIDER
    ========================================= */

    .divider {

        display: flex;

        align-items: center;

        gap: 18px;

        margin: 14px 0 10px;

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

        font-size: 12px;

    }


    /* =========================================
       LOGIN LINK
    ========================================= */

    .form-footer {

        font-size: 11px;

        color: #65707a;

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
       FARM FOOTER
    ========================================= */

    .farm-footer {

        margin-top: 15px;

        text-align: center;

    }


    .farm-name {

        display: flex;

        align-items: center;

        justify-content: center;

        gap: 7px;

        color: #53636b;

        font-size: 11px;

        font-weight: 500;

        letter-spacing: 0.3px;

    }


    .farm-name::before,
    .farm-name::after {

        content: "";

        width: 40px;

        height: 1px;

        background: #9fa9a4;

    }


    .farm-name i {

        color: #345c45;

        font-size: 9px;

    }


    .farm-tagline {

        margin-top: 3px;

        color: #68747d;

        font-size: 9px;

        letter-spacing: 0.3px;

    }


    /* =========================================
       TOAST NOTIFICATION
    ========================================= */

    .notification {

        visibility: hidden;

        min-width: 280px;

        max-width: 90%;

        background: #2e4e3c;

        color: #ffffff;

        text-align: center;

        border-radius: 10px;

        padding: 12px 18px;

        position: fixed;

        z-index: 9999;

        left: 50%;

        top: 15px;

        transform: translateX(-50%);

        font-size: 13px;

        font-weight: 500;

        box-shadow:
            0 5px 18px
            rgba(0, 0, 0, 0.15);

        opacity: 0;

        transition:
            opacity 0.3s ease,
            top 0.3s ease,
            visibility 0.3s ease;

    }


    .notification.show {

        visibility: visible;

        opacity: 1;

        top: 25px;

    }


    /* =========================================
       RESPONSIVE TABLET
    ========================================= */

    @media screen and (max-width: 768px) {

        body {

            padding: 15px;

            overflow-y: auto;

        }


        .register-card {

            max-width: 460px;

            padding: 28px 30px 26px;

        }


        .logo-img {

            width: 250px;

        }

    }


    /* =========================================
       RESPONSIVE MOBILE
    ========================================= */

    @media screen and (max-width: 480px) {

        body {

            padding: 10px;

            align-items: flex-start;

            overflow-y: auto;

        }


        .register-card {

            padding: 24px 20px;

            border-radius: 20px;

        }


        .logo-img {

            width: 230px;

        }


        .register-title {

            font-size: 25px;

        }


        .register-subtitle {

            font-size: 11px;

        }


        .form-group {

            margin-bottom: 14px;

        }


        .form-control {

            height: 54px;

            font-size: 12px;

        }


        .btn-submit {

            height: 54px;

        }

    }


    /* =========================================
       SHORT SCREEN HEIGHT
    ========================================= */

    @media screen and (max-height: 750px)
    and (min-width: 769px) {

        .register-card {

            padding: 20px 35px 18px;

        }


        .logo-img {

            width: 220px;

        }


        .logo-container {

            margin-bottom: 4px;

        }


        .register-title {

            font-size: 25px;

            margin-bottom: 3px;

        }


        .register-subtitle {

            margin-bottom: 10px;

            font-size: 11px;

        }


        .form-group {

            margin-bottom: 12px;

        }


        .form-control {

            height: 52px;

        }


        .btn-submit {

            height: 52px;

        }


        .divider {

            margin: 10px 0 8px;

        }


        .farm-footer {

            margin-top: 10px;

        }

    }

</style>


</head>

<body>


<!-- =========================================
     TOAST NOTIFICATION
========================================= -->

<div
    id="toastNotification"
    class="notification"
>
    Registration Successful!
</div>


<!-- =========================================
     REGISTER CARD
========================================= -->

<div class="register-card">


    <!-- LOGO -->

    <div class="logo-container">

        <img
            src="/EggFarm/vdvclogo.png"
            alt="VDVC Egg Farm Logo"
            class="logo-img"
        >

    </div>


    <!-- HEADER -->

    <h1 class="register-title">

        Create Your Account

    </h1>


    <p class="register-subtitle">

       Join VDVC Egg Farm to access our services and<br>
 enjoy a convenient experience.
       

    </p>


    <!-- =========================================
         REGISTRATION FORM
    ========================================= -->

    <form
        id="registrationForm"
        action="register_process.php"
        method="POST"
        class="register-form"
    >


        <!-- FULL NAME -->

        <div class="form-group">

            <i class="fa-regular fa-user input-icon"></i>

            <input
                type="text"
                name="fullname"
                class="form-control"
                placeholder="Full Name"
                required
            >

        </div>


        <!-- USERNAME -->

        <div class="form-group">

            <i class="fa-regular fa-circle-user input-icon"></i>

            <input
                type="text"
                name="username"
                class="form-control"
                placeholder="Username"
                required
            >

        </div>


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


        <!-- PASSWORD -->

        <div class="form-group">

            <i class="fa-solid fa-lock input-icon"></i>

            <input
                type="password"
                name="password"
                id="passwordField"
                class="form-control"
                placeholder="Password"
                required
            >


            <i
                class="fa-solid fa-eye toggle-password"
                id="togglePasswordIcon"
                onclick="togglePassword()"
            ></i>

        </div>


        <!-- CONFIRM PASSWORD -->

        <div class="form-group">

            <i class="fa-solid fa-lock input-icon"></i>

            <input
                type="password"
                name="confirm_password"
                id="confirmPasswordField"
                class="form-control"
                placeholder="Confirm Password"
                required
            >


            <i
                class="fa-solid fa-eye toggle-password"
                id="toggleConfirmPasswordIcon"
                onclick="toggleConfirmPassword()"
            ></i>

        </div>


        <!-- REGISTER BUTTON -->

        <button
            type="submit"
            class="btn-submit"
        >

            Register

            <i class="fa-solid fa-arrow-right"></i>

        </button>


    </form>


    <!-- DIVIDER -->

    <div class="divider">

        <span>or</span>

    </div>


    <!-- LOGIN LINK -->

    <div class="form-footer">

        Already have an account?

        <a href="login.php">

            Log In

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


<!-- =========================================
     JAVASCRIPT
========================================= -->

<script>


    /* =========================================
       PASSWORD SHOW / HIDE
    ========================================= */

    function togglePassword() {

        var passwordField =
            document.getElementById(
                "passwordField"
            );


        var passwordIcon =
            document.getElementById(
                "togglePasswordIcon"
            );


        if (passwordField.type === "password") {

            passwordField.type = "text";


            passwordIcon.className =
                "fa-solid fa-eye-slash toggle-password";

        }

        else {

            passwordField.type = "password";


            passwordIcon.className =
                "fa-solid fa-eye toggle-password";

        }

    }


    /* =========================================
       CONFIRM PASSWORD SHOW / HIDE
    ========================================= */

    function toggleConfirmPassword() {

        var confirmPasswordField =
            document.getElementById(
                "confirmPasswordField"
            );


        var confirmPasswordIcon =
            document.getElementById(
                "toggleConfirmPasswordIcon"
            );


        if (
            confirmPasswordField.type === "password"
        ) {

            confirmPasswordField.type = "text";


            confirmPasswordIcon.className =
                "fa-solid fa-eye-slash toggle-password";

        }

        else {

            confirmPasswordField.type = "password";


            confirmPasswordIcon.className =
                "fa-solid fa-eye toggle-password";

        }

    }


    /* =========================================
       AJAX REGISTRATION
    ========================================= */

    var registrationForm =
        document.getElementById(
            "registrationForm"
        );


    var toastNotification =
        document.getElementById(
            "toastNotification"
        );


    registrationForm.addEventListener(

        "submit",

        function(e) {


            e.preventDefault();


            var password =
                document.getElementById(
                    "passwordField"
                ).value;


            var confirmPassword =
                document.getElementById(
                    "confirmPasswordField"
                ).value;


            /* CHECK PASSWORD MATCH */

            if (password !== confirmPassword) {


                toastNotification.textContent =
                    "Passwords do not match.";


                toastNotification.style.backgroundColor =
                    "#c0392b";


                toastNotification.classList.add(
                    "show"
                );


                setTimeout(

                    function() {

                        toastNotification.classList.remove(
                            "show"
                        );

                    },

                    3000

                );


                return;

            }


            var formData =
                new FormData(
                    registrationForm
                );


            fetch(

                "register_process.php",

                {

                    method: "POST",

                    body: formData

                }

            )


            .then(

                function(response) {

                    return response.json();

                }

            )


            .then(

                function(data) {


                    toastNotification.textContent =
                        data.message;


                    /* SUCCESS */

                    if (
                        data.status === "success"
                    ) {


                        toastNotification.style.backgroundColor =
                            "#2e4e3c";


                        toastNotification.classList.add(
                            "show"
                        );


                        registrationForm.reset();


                        /* REDIRECT AFTER SUCCESS */

                        setTimeout(

                            function() {

                                window.location.href =
                                    "login.php";

                            },

                            2000

                        );

                    }


                    /* ERROR */

                    else {


                        toastNotification.style.backgroundColor =
                            "#c0392b";


                        toastNotification.classList.add(
                            "show"
                        );


                        setTimeout(

                            function() {

                                toastNotification.classList.remove(
                                    "show"
                                );

                            },

                            3000

                        );

                    }

                }

            )


            .catch(

                function(error) {


                    console.error(
                        "Error:",
                        error
                    );


                    toastNotification.textContent =
                        "Something went wrong. Please try again.";


                    toastNotification.style.backgroundColor =
                        "#c0392b";


                    toastNotification.classList.add(
                        "show"
                    );


                    setTimeout(

                        function() {

                            toastNotification.classList.remove(
                                "show"
                            );

                        },

                        3000

                    );

                }

            );

        }

    );


</script>


</body>

</html>
