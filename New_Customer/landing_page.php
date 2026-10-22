<?php
// Database Configuration para sa XAMPP / MySQL
$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'vdvc_egg_farm';

// Connection check
$conn = @new mysqli($db_host, $db_user, $db_pass, $db_name);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>VDVC Egg Farm - Fresh Eggs. Trusted Farm. Better for You.</title>

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Merriweather:wght@700;900&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- FontAwesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        /* ==================== GLOBAL STYLES ==================== */
        :root {
            --brand-green: #0a3a28;
            --brand-green-hover: #072b1d;
            --bg-cream: #f8f7f0;
            --text-dark: #0a3a28;
            --text-body: #3c5245;
            --divider-color: #dbe0d7;
            --font-serif: 'Merriweather', Georgia, serif;
            --font-sans: 'Plus Jakarta Sans', sans-serif;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        html {
            scroll-behavior: smooth;
        }

        body {
            font-family: var(--font-sans);
            background-color: #ffffff;
            color: var(--text-dark);
            overflow-x: hidden;
            line-height: 1.4;
        }

        a {
            text-decoration: none;
            color: inherit;
        }

        /* ==================== NAVBAR ==================== */
        .navbar {
            background-color: #0d4828;
            height: 75px;
            padding: 0 5%;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 0;
            z-index: 1000;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .nav-logo {
            display: flex;
            align-items: center;
            text-decoration: none;
            flex-shrink: 0;
        }

        .nav-logo img {
            display: block;
            width: 150px;
            height: 58px;
            object-fit: contain;
            object-position: left center;
        }

        .nav-links {
            display: flex;
            align-items: center;
            gap: 40px;
            list-style: none;
        }

      
/* DEFAULT NAVBAR LINKS */
.nav-links a {
    position: relative;
    color: #ffffff;
    text-decoration: none;
    transition: color 0.3s ease;
}

/* HOVER EFFECT — only when the link is not active */
.nav-links a:hover:not(.active) {
    color: #cce3d7;
}

/* ACTIVE LINK */
.nav-links a.active {
    color: #ffffff;
    font-weight: 700;
}

/* ACTIVE UNDERLINE */
.nav-links a.active::after {
    content: "";
    position: absolute;
    left: 0;
    bottom: -8px;
    width: 100%;
    height: 2px;
    background-color: #ffffff;
}


        .nav-links a:hover {
            color: #cce3d7;
        }

        .btn-nav-login {
            border: 1.5px solid rgba(255, 255, 255, 0.85);
            color: #ffffff;
            padding: 9px 20px;
            border-radius: 8px;
            font-size: 13.5px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
            background: transparent;
            transition: all 0.3s ease;
        }

        .btn-nav-login:hover {
            background-color: #ffffff;
            color: var(--brand-green);
            transform: translateY(-1px);
        }

        .mobile-toggle {
            display: none;
            color: #ffffff;
            font-size: 22px;
            cursor: pointer;
        }

        /* ==================== HERO SECTION ==================== */
        .hero-section {
            position: relative;
            width: 100%;
            min-height: calc(100vh - 75px);
            background: url('landing_home.png') no-repeat center center / cover;
            display: flex;
            align-items: center;
            padding: 0 6%;
            overflow: hidden;
        }

        .hero-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 58%;
            height: 100%;
            background: linear-gradient(
                to right, 
                rgba(248, 246, 238, 0.76) 0%, 
                rgba(248, 246, 238, 0.65) 45%, 
                rgba(248, 246, 238, 0.30) 78%, 
                rgba(248, 246, 238, 0) 100%
            );
            z-index: 1;
            pointer-events: none;
        }

        .hero-content {
            position: relative;
            z-index: 2;
            max-width: 530px;
            padding: 40px 0;
            animation: fadeInUp 0.8s ease-out forwards;
        }

        .hero-title {
            font-family: var(--font-serif);
            font-size: 3.7rem;
            line-height: 1.1;
            color: var(--brand-green);
            font-weight: 900;
            margin-bottom: 22px;
            letter-spacing: -0.5px;
            text-shadow: 0 1px 2px rgba(255, 255, 255, 0.6);
        }

        .hero-subtitle {
            font-size: 15px;
            color: var(--text-body);
            line-height: 1.55;
            margin-bottom: 32px;
            font-weight: 500;
            max-width: 450px;
        }

        .hero-buttons {
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .btn-hero-login {
            background-color: var(--brand-green);
            color: #ffffff;
            padding: 13px 24px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 9px;
            border: 2px solid var(--brand-green);
            transition: all 0.3s ease;
            box-shadow: 0 4px 14px rgba(10, 58, 40, 0.2);
        }

        .btn-hero-login:hover {
            background-color: var(--brand-green-hover);
            transform: translateY(-2px);
            box-shadow: 0 6px 18px rgba(10, 58, 40, 0.3);
        }

        .btn-hero-learn {
            background-color: rgba(248, 246, 238, 0.55);
            color: var(--brand-green);
            padding: 13px 24px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 9px;
            border: 1.5px solid var(--brand-green);
            backdrop-filter: blur(4px);
            transition: all 0.3s ease;
        }

        .btn-hero-learn:hover {
            background-color: var(--brand-green);
            color: #ffffff;
            transform: translateY(-2px);
        }

        /* ==================== BOTTOM FARM VALUES SECTION ==================== */
        .values-bar {
            background-color: var(--bg-cream);
            border-top: 1px solid rgba(10, 58, 40, 0.08);
            padding: 12px 3% 22px;
            margin-top: -12px;
            position: relative;
            z-index: 3;
        }

        .values-container {
            max-width: 1380px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            align-items: center;
        }

        .value-box {
            display: flex;
            align-items: flex-start;
            gap: 14px;
            padding: 0 24px;
            position: relative;
            transition: transform 0.3s ease;
        }

        .value-box:hover {
            transform: translateY(-2px);
        }

        .value-box:not(:last-child)::after {
            content: '';
            position: absolute;
            right: 0;
            top: 5%;
            height: 90%;
            width: 1px;
            background-color: var(--divider-color);
        }

        .value-icon {
            width: 28px;
            height: 28px;
            flex-shrink: 0;
            color: var(--brand-green);
            margin-top: 2px;
        }

        .value-icon svg {
            width: 100%;
            height: 100%;
            fill: none;
            stroke: var(--brand-green);
            stroke-width: 2;
            stroke-linecap: round;
            stroke-linejoin: round;
        }

        .value-text {
            display: flex;
            flex-direction: column;
            margin-top: 5px;
        }

        .value-title {
            font-size: 12.5px;
            font-weight: 800;
            color: var(--brand-green);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 3px;
        }

        .value-desc {
            font-size: 11.5px;
            color: #55685c;
            line-height: 1.35;
            font-weight: 500;
        }

        /* ==================== ABOUT US SECTION ==================== */
        .about-section {
           background-color: var(--bg-cream);
            border-top: 1px solid rgba(10, 58, 40, 0.08);
            position: relative;
            overflow: hidden;
            width: 100%;
        }

        .about-container {
            width: 100%;
            display: grid;
            grid-template-columns: 50% 50%;
            align-items: stretch;
            min-height: 520px;
            position: relative;
            background-color: var(--bg-cream);
        }

        .about-image-wrapper {
            position: relative;
            width: 100%;
            height: 100%;
            min-height: 480px;
            background: url('landing_about.png') no-repeat left center;
            background-size: cover; 
        }

        .about-wave-divider {
            position: absolute;
            top: 0;
            right: -1px;
            width: 130px;
            height: 100%;
            z-index: 3;
            pointer-events: none;
        }

        .about-wave-divider svg {
            width: 100%;
            height: 100%;
            display: block;
        }

        .about-content {
            padding: 55px 6% 55px 4%;
            display: flex;
            flex-direction: column;
            justify-content: center;
            position: relative;
            z-index: 2;
            background-color: var(--bg-cream);
        }

        .about-label-wrap {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 12px;
        }

        .about-label {
            font-size: 12px;
            font-weight: 800;
            color: var(--brand-green);
            letter-spacing: 1.2px;
            text-transform: uppercase;
        }

        .about-label-icon {
            color: var(--brand-green);
            font-size: 12px;
        }

        .about-label-line {
            width: 55px;
            height: 1px;
            background-color: #cbd5cb;
            margin-left: 4px;
        }

        .about-heading {
            font-family: var(--font-serif);
            font-size: 3.2rem;
            color: var(--brand-green);
            font-weight: 900;
            line-height: 1.1;
            margin-bottom: 20px;
            letter-spacing: -0.5px;
        }

        .about-desc {
            font-size: 13.5px;
            color: #4a5d51;
            line-height: 1.65;
            font-weight: 500;
            margin-bottom: 32px;
            max-width: 650px;
        }

        .about-cards-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 14px;
            align-items: center;
        }

        .about-card {
            background: #ffffff;
            border-radius: 12px;
            padding: 16px 14px;
            display: flex;
            align-items: center;
            gap: 12px;
            box-shadow: 0 3px 12px rgba(10, 58, 40, 0.04);
            border: 1px solid rgba(10, 58, 40, 0.05);
            transition: all 0.35s cubic-bezier(0.165, 0.84, 0.44, 1);
        }

        .about-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 22px rgba(10, 58, 40, 0.1);
        }

        .about-card-icon {
            font-size: 22px;
            color: var(--brand-green);
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 34px;
            height: 34px;
        }

        .about-card-info {
            display: flex;
            flex-direction: column;
            line-height: 1.15;
        }

        .about-card-number {
            font-size: 16px;
            font-weight: 800;
            color: var(--brand-green);
            margin-bottom: 2px;
        }

        .about-card-text {
            font-size: 10px;
            color: #687a70;
            font-weight: 600;
        }

        /* ==================== HOW IT WORKS SECTION ==================== */
        .how-section {
       background-color: var(--bg-cream);
            padding: 80px 5% 70px;
            position: relative;
            overflow: hidden;
            border-top: 1px solid rgba(10, 58, 40, 0.08);
        }

        /* Floating Leaf Elements */
        .how-leaf-decor {
            position: absolute;
            color: #2e6648;
            opacity: 0.35;
            pointer-events: none;
            z-index: 1;
        }

        .how-leaf-1 { top: 12%; left: 3%; font-size: 26px; transform: rotate(-25deg); }
        .how-leaf-2 { top: 8%; right: 4%; font-size: 28px; transform: rotate(40deg); }
        .how-leaf-3 { bottom: 35%; left: 2%; font-size: 24px; transform: rotate(15deg); }
        .how-leaf-4 { bottom: 38%; right: 3%; font-size: 26px; transform: rotate(-30deg); }

        .how-header {
            text-align: center;
            margin-bottom: 60px;
            position: relative;
            z-index: 2;
        }

        .how-title {
            font-family: var(--font-serif);
            font-size: 3.2rem;
            color: var(--brand-green);
            font-weight: 900;
            margin-bottom: 12px;
            letter-spacing: -0.5px;
        }

        .how-title-divider {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
        }

        .how-title-divider .line {
            width: 45px;
            height: 1px;
            background-color: #8da396;
        }

        .how-title-divider i {
            color: var(--brand-green);
            font-size: 14px;
        }

        /* Steps Container */
        .how-steps-container {
            max-width: 1200px;
            margin: 0 auto 70px;
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            position: relative;
            z-index: 2;
        }

        .how-step-item {
            display: flex;
            align-items: center;
            gap: 20px;
            flex: 1;
            padding: 0 10px;
        }

        .how-icon-wrapper {
            position: relative;
            width: 110px;
            height: 110px;
            border-radius: 50%;
            background-color: #ffffff;
            border: 1px solid #d4dfd8;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            box-shadow: 0 6px 18px rgba(10, 58, 40, 0.05);
            transition: all 0.35s ease;
        }

        .how-step-item:hover .how-icon-wrapper {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(10, 58, 40, 0.12);
            border-color: var(--brand-green);
        }

        .how-badge {
            position: absolute;
            top: 2px;
            left: 2px;
            background-color: var(--brand-green);
            color: #ffffff;
            font-size: 11px;
            font-weight: 800;
            width: 28px;
            height: 28px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 2px solid #ffffff;
            box-shadow: 0 2px 6px rgba(0,0,0,0.15);
        }

        .how-icon-wrapper i {
            font-size: 38px;
            color: var(--brand-green);
        }

        .how-step-info {
            display: flex;
            flex-direction: column;
            max-width: 220px;
        }

        .how-step-title {
            font-size: 17px;
            font-weight: 800;
            color: var(--brand-green);
            margin-bottom: 6px;
            line-height: 1.25;
        }

        .how-step-desc {
            font-size: 12.5px;
            color: #516558;
            line-height: 1.45;
            font-weight: 500;
        }

        .how-arrow-connector {
            width: 90px;
            height: 30px;
            flex-shrink: 0;
            margin: 40px 10px 0;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .how-arrow-connector svg {
            width: 100%;
            height: 100%;
        }

      /* ==================== MANUAL BACKGROUND IMAGE CTA BANNER ==================== */
        .how-cta-banner {
            max-width: 1180px;
            margin: 0 auto;
            border-radius: 14px;
            position: relative;
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(10, 58, 40, 0.06);
            border: 1px solid rgba(10, 58, 40, 0.12);
            background-color: #f8f7f0; /* Fallback cream color */
            z-index: 2;
            min-height: 110px;
        }

        /* Mano-manong lalagyan ng background image para mas madaling i-adjust */
        .how-cta-bg-img {
            position: absolute;
            top: -130px; /* <--- ITO ANG PWEDE MONG BAGUHIN: mas minus, mas tataas ang litrato */
            left: 0;
            width: 100%; /* Lawak ng imahe sa kaliwang bahagi */
            height: auto;
            object-fit: cover;
            pointer-events: none;
            z-index: 1;
        }

        .how-cta-overlay {
            width: 100%;
            height: 100%;
            padding: 22px 40px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: relative;
            z-index: 2;
            /* Gradient overlay na nakapokus lamang sa gitna para mabasa ang teksto */
           margin-left: 50px;
        }

        .how-cta-content {
            display: flex;
            align-items: center;
            justify-content: space-between;
            width: 100%;
        }

        .how-cta-center-text {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            flex-grow: 1;
            margin: 0 20px;
        }

        .how-cta-title-wrap {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .how-cta-title {
            font-family: var(--font-serif);
            font-size: 2rem;
            color: var(--brand-green);
            font-weight: 900;
            line-height: 1.1;
            letter-spacing: -0.3px;
        }

        .how-cta-subtitle {
            font-size: 13.5px;
            color: #4a5d51;
            font-weight: 600;
            margin-top: 5px;
        }

        .how-cta-leaf-icon {
            color: var(--brand-green);
            font-size: 18px;
            opacity: 0.85;
        }

        .btn-cta-login {
            background-color: var(--brand-green);
            color: #ffffff;
            padding: 12px 24px;
            border-radius: 8px;
            font-size: 13.5px;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 9px;
            transition: all 0.3s ease;
            box-shadow: 0 4px 12px rgba(10, 58, 40, 0.2);
            flex-shrink: 0;
            border: 1.5px solid var(--brand-green);
            margin-right: 50px;
        }

        .btn-cta-login:hover {
            background-color: var(--brand-green-hover);
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(10, 58, 40, 0.3);
        }

       
/* =========================================================
   FOOTER
========================================================= */

.customer-footer {
    width: 100%;

    min-height: 68px;

    background: #0d4828;

    color: #ffffff;

    display: flex;
    align-items: center;

    padding: 0 48px;
}

.customer-footer-inner {
    width: 100%;

    max-width: 1740px;

    margin: 0 auto;

    display: flex;
    align-items: center;
    justify-content: space-between;
}

.customer-footer-left {
    display: flex;
    align-items: center;

    gap: 17px;
}

.customer-footer-logo {
    width: 110px;

    padding-right: 15px;

    border-right: 1px solid rgba(255,255,255,0.22);
}

.customer-footer-logo img {
    width: 100px;
    height: auto;

    display: block;
}

.customer-footer-tagline {
    font-size: 11px;

    color: rgba(255,255,255,0.9);
}

.customer-footer-right {
    display: flex;
    align-items: center;

    gap: 18px;
}

.customer-footer-social {
    display: flex;
    align-items: center;

    gap: 17px;
}

.customer-footer-social a {
    color: #ffffff;

    font-size: 13px;

    text-decoration: none;

    opacity: 0.95;

    transition: opacity 0.2s ease;
}

.customer-footer-social a:hover {
    opacity: 0.7;
}

.customer-footer-copy {
    font-size: 11px;

    color: rgba(255,255,255,0.82);
}
       
/* ==================== REPEATABLE ANIMATIONS ==================== */

@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(25px);
    }

    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.animate-on-scroll {
    opacity: 0;
    transform: translateY(25px);
    transition:
        opacity 0.8s ease-out,
        transform 0.8s cubic-bezier(0.165, 0.84, 0.44, 1);
}

/* Animation plays whenever JavaScript adds this class */
.animate-on-scroll.animated {
    opacity: 1;
    transform: translateY(0);
}

/* Respect the user's reduced-motion preference */
@media (prefers-reduced-motion: reduce) {
    .animate-on-scroll {
        transition: none;
    }

    html {
        scroll-behavior: auto;
    }
}


        /* Responsive Layout */
        @media (max-width: 1024px) {
            .cta-spacer { display: none; }
            .how-cta-overlay {
                padding: 25px 20px;
            }
            .how-cta-content {
                flex-direction: column;
                gap: 18px;
                text-align: center;
            }
            .how-cta-center-text {
                margin: 0;
            }
        }

        @media (max-width: 768px) {
            .mobile-toggle { display: block; }
            .nav-links {
                position: absolute;
                top: 75px;
                left: 0;
                width: 100%;
                background-color: var(--brand-green);
                flex-direction: column;
                padding: 20px 0;
                gap: 18px;
                display: none;
                box-shadow: 0 4px 10px rgba(0,0,0,0.15);
            }
            .nav-links.active { display: flex; }
            .how-cta-title { font-size: 1.5rem; }
        }
        
#values {
    scroll-margin-top: 75px;
}

    </style>
</head>
<body>

    <!-- NAVBAR -->
    <nav class="navbar">
        <a href="landing_page.php" class="nav-logo">
            <img src="vdvclogoo.png" alt="VDVC Egg Farm Logo">
        </a>

        <ul class="nav-links" id="navLinks">
            <li><a href="#home" class="nav-item active">Home</a></li>
            <li><a href="#values" class="nav-item">About Us</a></li>
            <li><a href="#how-it-works" class="nav-item">How It Works</a></li>
        </ul>

        <a href="login.php" class="btn-nav-login">
            <i class="fa-solid fa-lock"></i>
            <span>Login to System</span>
        </a>

        <div class="mobile-toggle" id="mobileToggle">
            <i class="fa-solid fa-bars"></i>
        </div>
    </nav>

    <!-- HERO SECTION -->
    <section class="hero-section" id="home">
        <div class="hero-content">
            <h1 class="hero-title">Fresh Eggs.<br>Trusted Farm.<br>Better for You.</h1>
            <p class="hero-subtitle">
                VDVC Egg Farm provides you fresh, quality eggs and a convenient way to order, track, and receive your eggs hassle-free.
            </p>
            <div class="hero-buttons">
                <a href="login.php" class="btn-hero-login">
                    <i class="fa-solid fa-lock"></i>
                    <span>Login to System</span>
                </a>
                <a href="#about" class="btn-hero-learn">
                    <i class="fa-solid fa-circle-info"></i>
                    <span>Learn More</span>
                </a>
            </div>
        </div>
    </section>

    <!-- FARM VALUES BAR -->
    <section class="values-bar" id="values">
        <div class="values-container">
            
            <div class="value-box">
                <div class="value-icon">
                    <svg viewBox="0 0 24 24">
                        <path d="M12 22v-9M12 13C12 7.5 7.5 5 4 5c0 5 2.5 8 8 8zM12 13c0-5.5 4.5-8 8-8 0 5-2.5 8-8 8z"/>
                    </svg>
                </div>
                <div class="value-text">
                    <span class="value-title">NATURAL & SAFE</span>
                    <span class="value-desc">No hormones, no unnecessary antibiotics.</span>
                </div>
            </div>

            <div class="value-box">
                <div class="value-icon">
                    <svg viewBox="0 0 24 24">
                        <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                        <path d="m9 12 2 2 4-4"/>
                    </svg>
                </div>
                <div class="value-text">
                    <span class="value-title">HIGH STANDARDS</span>
                    <span class="value-desc">Strict quality control at every step</span>
                </div>
            </div>

            <div class="value-box">
                <div class="value-icon">
                    <svg viewBox="0 0 24 24">
                        <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>
                        <path d="M9 13h6M12 10v6"/>
                    </svg>
                </div>
                <div class="value-text">
                    <span class="value-title">ANIMAL WELFARE</span>
                    <span class="value-desc">Humane care in a clean and healthy environment</span>
                </div>
            </div>

            <div class="value-box">
                <div class="value-icon">
                    <svg viewBox="0 0 24 24">
                        <path d="M2 22l10-10M12 12c.5-2.5 2-6.5 7-7 0 0-1 6-5 8.5M12 12c-2.5.5-6.5 2-7 7 0 0 6-1 8.5-5"/>
                    </svg>
                </div>
                <div class="value-text">
                    <span class="value-title">SUSTAINABLE FARMING</span>
                    <span class="value-desc">Eco-friendly practices for a better tomorrow.</span>
                </div>
            </div>

        </div>
    </section>

    <!-- ABOUT US SECTION -->
    <section class="about-section" id="about">
        <div class="about-container">
            
            <div class="about-image-wrapper">
                <div class="about-wave-divider">
                    <svg viewBox="0 0 100 500" preserveAspectRatio="none">
                        <path d="M 100,0 C -35,140 135,360 0,500 L 100,500 Z" fill="#f8f7f0"></path>
                    </svg>
                </div>
            </div>

            <div class="about-content">
                <div class="about-label-wrap animate-on-scroll">
                    <span class="about-label">ABOUT US</span>
                    <i class="fa-solid fa-leaf about-label-icon"></i>
                    <div class="about-label-line"></div>
                </div>

                <h2 class="about-heading animate-on-scroll">About Our Farm</h2>

                <p class="about-desc animate-on-scroll">
                    VDVC Egg Farm is committed to providing fresh and high-quality eggs sourced from a trusted local farm. We value freshness, quality, and reliability in every order we prepare for our customers. Through this system, customers can easily place reservations, monitor their orders, and receive their eggs with less hassle. Our goal is to make every step simple, reliable, and convenient.
                </p>

                <div class="about-cards-grid animate-on-scroll">
                    <div class="about-card">
                        <div class="about-card-icon"><i class="fa-solid fa-house"></i></div>
                        <div class="about-card-info">
                            <span class="about-card-number">15+</span>
                            <span class="about-card-text">Years of Experience</span>
                        </div>
                    </div>

                    <div class="about-card">
                        <div class="about-card-icon"><i class="fa-solid fa-user-group"></i></div>
                        <div class="about-card-info">
                            <span class="about-card-number">25K+</span>
                            <span class="about-card-text">Happy Customers</span>
                        </div>
                    </div>

                    <div class="about-card">
                        <div class="about-card-icon"><i class="fa-solid fa-shield"></i></div>
                        <div class="about-card-info">
                            <span class="about-card-number">100%</span>
                            <span class="about-card-text">Quality Assurance</span>
                        </div>
                    </div>

                    <div class="about-card">
                        <div class="about-card-icon"><i class="fa-solid fa-truck-fast"></i></div>
                        <div class="about-card-info">
                            <span class="about-card-number">PHILIPPINES</span>
                            <span class="about-card-text">Locally Farmed</span>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </section>

    <!-- HOW IT WORKS SECTION -->
    <section class="how-section" id="how-it-works">
        <i class="fa-solid fa-leaf how-leaf-decor how-leaf-1"></i>
        <i class="fa-solid fa-leaf how-leaf-decor how-leaf-2"></i>
        <i class="fa-solid fa-leaf how-leaf-decor how-leaf-3"></i>
        <i class="fa-solid fa-leaf how-leaf-decor how-leaf-4"></i>

        <div class="how-header animate-on-scroll">
            <h2 class="how-title">How It Works</h2>
            <div class="how-title-divider">
                <div class="line"></div>
                <i class="fa-solid fa-leaf"></i>
                <div class="line"></div>
            </div>
        </div>

        <div class="how-steps-container animate-on-scroll">
            <!-- Step 1 -->
            <div class="how-step-item">
                <div class="how-icon-wrapper">
                    <span class="how-badge">01</span>
                    <i class="fa-regular fa-user"></i>
                </div>
                <div class="how-step-info">
                    <h3 class="how-step-title">Create an Account</h3>
                    <p class="how-step-desc">Sign up and log in to your account.</p>
                </div>
            </div>

            <div class="how-arrow-connector">
                <svg viewBox="0 0 100 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M5 10 C 35 2, 65 18, 90 10" stroke="#a3b8ac" stroke-width="2" stroke-dasharray="4 4"/>
                    <path d="M85 5 L 92 10 L 85 15" stroke="#a3b8ac" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </div>

            <!-- Step 2 -->
            <div class="how-step-item">
                <div class="how-icon-wrapper">
                    <span class="how-badge">02</span>
                    <i class="fa-solid fa-cart-shopping"></i>
                </div>
                <div class="how-step-info">
                    <h3 class="how-step-title">Place a Reservation</h3>
                    <p class="how-step-desc">Choose your eggs and handle your reservation.</p>
                </div>
            </div>

            <div class="how-arrow-connector">
                <svg viewBox="0 0 100 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M5 10 C 35 18, 65 2, 90 10" stroke="#a3b8ac" stroke-width="2" stroke-dasharray="4 4"/>
                    <path d="M85 5 L 92 10 L 85 15" stroke="#a3b8ac" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </div>

            <!-- Step 3 -->
            <div class="how-step-item">
                <div class="how-icon-wrapper">
                    <span class="how-badge">03</span>
                    <i class="fa-solid fa-truck"></i>
                </div>
                <div class="how-step-info">
                    <h3 class="how-step-title">Pickup or Delivery</h3>
                    <p class="how-step-desc">We prepare and deliver your eggs or you may pick them up.</p>
                </div>
            </div>
        </div>

       <!-- EXACT CTA BANNER WITH MANUAL IMAGE POSITIONING -->
        <div class="how-cta-banner animate-on-scroll">
            <!-- Mano-manong background image kung saan pwede mong baguhin ang top positioning sa CSS -->
            <img src="fresh.png" alt="Fresh Eggs Background" class="how-cta-bg-img">

            <div class="how-cta-overlay">
                <div class="how-cta-content">
                    
                    <div style="flex-shrink: 0; width: 100px;" class="cta-spacer"></div>

                    <div class="how-cta-center-text">
                        <div class="how-cta-title-wrap">
                            <h3 class="how-cta-title">Ready to enjoy fresh eggs?</h3>
                            <i class="fa-solid fa-leaf how-cta-leaf-icon"></i>
                        </div>
                        <p class="how-cta-subtitle">Log in now to get started!</p>
                    </div>

                    <a href="login.php" class="btn-cta-login">
                        <i class="fa-solid fa-lock"></i>
                        <span>Login to System</span>
                    </a>

                </div>
            </div>
        </div>
    </section>

    
<!-- =========================================================
     FOOTER
========================================================= -->

<footer class="customer-footer">


    <div class="customer-footer-inner">


        <!-- LEFT -->

        <div class="customer-footer-left">


            <div class="customer-footer-logo">

                <img
                    src="vdvclogoo.png"
                    alt="VDVC Egg Farm"
                >

            </div>


            <div class="customer-footer-tagline">

                Fresh Eggs. Trusted Farm. Better for You.

            </div>


        </div>



        <!-- RIGHT -->

        <div class="customer-footer-right">


            <div class="customer-footer-social">

                <a
                    href="#"
                    aria-label="Facebook"
                >
                    <i class="fa-brands fa-facebook-f"></i>
                </a>


                <a
                    href="#"
                    aria-label="Instagram"
                >
                    <i class="fa-brands fa-instagram"></i>
                </a>


                <a
                    href="#"
                    aria-label="YouTube"
                >
                    <i class="fa-brands fa-youtube"></i>
                </a>

            </div>


            <div class="customer-footer-copy">

                © 2026 VDVC Egg Farm.
                All rights reserved.

            </div>


        </div>


    </div>

</footer>

<!-- JAVASCRIPT -->
<script>
    
/* Always start at Home when the page is refreshed */
if ('scrollRestoration' in history) {
    history.scrollRestoration = 'manual';
}

window.addEventListener('beforeunload', function () {
    window.scrollTo(0, 0);
});

document.addEventListener('DOMContentLoaded', function () {

    var mobileToggle = document.getElementById('mobileToggle');
    var navLinks = document.getElementById('navLinks');
    var navItems = document.querySelectorAll('.nav-item');

    /* =========================================
       MOBILE NAVIGATION
    ========================================= */

    if (mobileToggle && navLinks) {
        mobileToggle.addEventListener('click', function () {
            navLinks.classList.toggle('active');
        });
    }

    /* =========================================
       REPEATABLE SECTION ANIMATIONS
    ========================================= */

    var animatedElements = document.querySelectorAll(
        '.animate-on-scroll'
    );

    var animationObserver = new IntersectionObserver(
        function (entries) {
            entries.forEach(function (entry) {

                if (entry.isIntersecting) {

                    /* Remove the class so the animation can restart */
                    entry.target.classList.remove('animated');

                    /* Force the browser to restart the animation */
                    void entry.target.offsetWidth;

                    /* Play the animation again */
                    entry.target.classList.add('animated');

                } else {

                    /*
                     * Reset when the element leaves the viewport.
                     * It will animate again on the next visit.
                     */
                    entry.target.classList.remove('animated');

                }

            });
        },
        {
            root: null,
            rootMargin: '-75px 0px -10% 0px',
            threshold: 0.1
        }
    );

    animatedElements.forEach(function (element) {
        animationObserver.observe(element);
    });

    /* =========================================
       REPEAT HOME HERO ANIMATION
    ========================================= */

    var homeSection = document.getElementById('home');
    var heroContent = document.querySelector('.hero-content');

    if (homeSection && heroContent) {

        var heroObserver = new IntersectionObserver(
            function (entries) {
                entries.forEach(function (entry) {

                    if (entry.isIntersecting) {

                        heroContent.style.animation = 'none';

                        /* Reset the animation before replaying it */
                        void heroContent.offsetWidth;

                        heroContent.style.animation =
                            'fadeInUp 0.8s ease-out forwards';

                    }

                });
            },
            {
                root: null,
                rootMargin: '-75px 0px -10% 0px',
                threshold: 0.1
            }
        );

        heroObserver.observe(homeSection);
    }

    /* =========================================
       NAVBAR ACTIVE SECTION
    ========================================= */

  
var sections = [
    { element: document.getElementById('home'), active: 'home' },
    { element: document.getElementById('values'), active: 'values' },
    { element: document.getElementById('about'), active: 'values' },
    { element: document.getElementById('how-it-works'), active: 'how-it-works' }
];

function setActiveLink(sectionId) {
    navItems.forEach(function (link) {
        if (link.getAttribute('href') === '#' + sectionId) {
            link.classList.add('active');
        } else {
            link.classList.remove('active');
        }
    });
}

function updateActiveNavbar() {
    var navbar = document.querySelector('.navbar');
    var line = (navbar ? navbar.offsetHeight : 75) + 40;
    var current = 'home';

    sections.forEach(function (item) {
        if (!item.element) return;

        var rect = item.element.getBoundingClientRect();

        if (rect.top <= line && rect.bottom > line) {
            current = item.active;
        }
    });

    setActiveLink(current);
}

window.addEventListener('scroll', updateActiveNavbar);
window.addEventListener('resize', updateActiveNavbar);

navItems.forEach(function (link) {
    link.addEventListener('click', function () {
        var href = this.getAttribute('href');

        if (href === '#values') {
            setActiveLink('values');
        } else if (href) {
            setActiveLink(href.substring(1));
        }

        if (navLinks && navLinks.classList.contains('active')) {
            navLinks.classList.remove('active');
        }
    });
});

updateActiveNavbar();

/* Start at Home after refreshing */
window.scrollTo(0, 0);
setActiveLink('home');

});
</script>

</body>
</html>