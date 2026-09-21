<?php
/*
|--------------------------------------------------------------------------
| VDVC EGG FARM - LANDING PAGE
|--------------------------------------------------------------------------
| PHP 5.3+ compatible
|
| IMAGE FILES:
|   hero_chickens.png
|   hero_eggs.png
|   cta_eggs.png
|   about_eggs.png
|   about_farm.png
|   footer_chickens.png
|--------------------------------------------------------------------------
*/
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>VDVC Egg Farm</title>

<style>

/* =========================================================
   RESET
========================================================= */

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

html {
    scroll-behavior: smooth;
}

body {
    font-family: "Segoe UI", Arial, sans-serif;
    color: #142018;
    background: #fffdf8;
    line-height: 1.5;
    overflow-x: hidden;
}

a {
    text-decoration: none;
    color: inherit;
}

img {
    display: block;
    max-width: 100%;
}

button,
a {
    -webkit-tap-highlight-color: transparent;
}

:root {
    --green: #214f2c;
    --green-dark: #173d21;
    --green-light: #729263;
    --cream: #fffdf8;
    --cream-2: #f3f2e8 ;
    --brown: #7a4926;
    --text: #141814;
}

/* =========================================================
   HEADER
========================================================= */
.site-header {
    position: sticky;
    position: -webkit-sticky;
    top: 0;
    left: 0;

    width: 100%;
    height: 82px;

    background: rgba(255,255,255,0.98);

    display: flex;
    align-items: center;
    justify-content: space-between;

    padding: 0 5%;

    z-index: 9999;

    box-shadow: 0 2px 12px rgba(30,40,25,.07);
}

/* =========================================================
   LOGO / BRAND
============.brand {
    display: flex;
    align-items: center;

    flex-shrink: 0;
}


/* =========================================================
   VDVC FARM LOGO
========================================================= */

.brand-mark {
    width: 150px;
    height: 100px;

    display: block;

    flex-shrink: 0;

    background: url("vdvclogo.png") center center / contain no-repeat;

    border: none;
    border-radius: 0;

    overflow: visible;
}


/* Remove old decorative elements */

.brand-mark:before,
.brand-mark:after {
    display: none;
}


/* =========================================================
   NAVIGATION
========================================================= */

.main-nav {
    display: flex;
    align-items: center;

    gap: 28px;

    font-size: 14px;
}


.main-nav a {
    position: relative;

    padding: 8px 2px;

    transition: .2s ease;
}


.main-nav a:hover {
    color: var(--green);
}


.main-nav a.active:after {
    content: "";

    position: absolute;

    left: 0;
    right: 0;
    bottom: 0;

    height: 2px;

    background: var(--green);
}


/* =========================================================
   LOGIN BUTTON
========================================================= */

.login-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;

    gap: 8px;

    padding: 9px 16px;

    background: var(--green);
    color: #fff;

    border: 1px solid var(--green);
    border-radius: 6px;

    font-size: 13px;
    font-weight: 600;

    transition: .2s ease;
}


.login-btn:hover {
    background: var(--green-dark);
    transform: translateY(-1px);
}


/* =========================================================
   LOCK ICON
========================================================= */

.lock-icon {
    width: 14px;
    height: 13px;

    position: relative;

    display: inline-block;

    border: 2px solid currentColor;
    border-radius: 2px;

    margin-top: 5px;
}


.lock-icon:before {
    content: "";

    position: absolute;

    width: 7px;
    height: 7px;

    left: 1.5px;
    top: -8px;

    border: 2px solid currentColor;
    border-bottom: 0;

    border-radius: 7px 7px 0 0;
}


/* =========================================================
   MOBILE MENU
========================================================= */

.mobile-menu {
    display: none;

    width: 40px;
    height: 40px;

    background: #fff;

    border: 1px solid #d7d4c8;
    border-radius: 7px;

    color: var(--green);

    font-size: 22px;

    cursor: pointer;
}
/* =========================================================
   HERO
========================================================= */
.hero {
    position: relative;

    min-height: 485px;

    background: #f6efdf;

    overflow: hidden;

    display: flex;
}


/* LEFT TEXT AREA */
.hero-copy {
    width: 48%;

    padding:
        62px
        0
        75px
        8.1%;

    position: relative;
    z-index: 5;

    background: transparent;
}

.hero-title {
    font-family: Georgia, "Times New Roman", serif;

    font-size: clamp(43px, 4.25vw, 59px);

    line-height: .96;

    letter-spacing: -.8px;

    color: var(--green-dark);

    max-width: 480px;
}

.hero-title span {
    color: #729263;
}

.hero-text {
    max-width: 435px;

    margin-top: 22px;

    font-size: 15px;

    line-height: 1.55;

    color: #171c18;
}

.hero-actions {
    display: flex;

    align-items: center;

    gap: 20px;

    margin-top: 21px;

    flex-wrap: wrap;
}

/* =========================================================
   HERO FARM VALUES / FEATURES
========================================================= */

.hero-features {
    position: absolute;

    left: 0;
    bottom: 0;

    width: 100%;

    display: grid;
    grid-template-columns: repeat(4, 1fr);

    z-index: 7;

    padding: 20px 6% 22px;

    

}


/* EACH COLUMN */

.hero-feature {
    min-height: 105px;

    padding: 0 28px;

    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: flex-start;

    text-align: center;

    position: relative;
}


/* VERTICAL SEPARATION LINES */

.hero-feature:not(:first-child):before {
    content: "";

    position: absolute;

    left: 0;
    top: 8px;
    bottom: 8px;

    width: 1px;

    background: rgba(33,79,44,.30);
}


/* ICON */

.hero-feature-icon {
    width: 48px;
    height: 48px;

    margin-bottom: 8px;

    color: var(--green);

    display: flex;
    align-items: center;
    justify-content: center;
}


.hero-feature-icon svg {
    width: 43px;
    height: 43px;

    fill: none;

    stroke: currentColor;

    stroke-width: 1.7;

    stroke-linecap: round;
    stroke-linejoin: round;
}


/* HEADING */

.hero-feature h3 {
    margin: 0 0 5px;

    color: var(--green-dark);

    font-family: "Segoe UI", Arial, sans-serif;

    font-size: 13px;

    line-height: 1.2;

    font-weight: 700;

    letter-spacing: .4px;

    text-transform: uppercase;
}


/* DESCRIPTION */

.hero-feature p {
    margin: 0;

    max-width: 205px;

    color: #30352f;

    font-family: "Segoe UI", Arial, sans-serif;

    font-size: 11px;

    line-height: 1.4;

    font-weight: 400;
}
/* SECONDARY BUTTON */

.hero-secondary {
    display: inline-flex;
    align-items: center;
    justify-content: center;

    gap: 10px;

    padding: 10px 23px;

    border: 2px solid var(--green);
    border-radius: 7px;

    background: rgba(255,255,255,.38);

    color: var(--green);

    font-weight: 600;

    transition: .2s ease;
}

.hero-secondary:hover {
    background: #fff;
}

.info-dot {
    width: 16px;
    height: 16px;

    border-radius: 50%;

    background: var(--green);
    color: #fff;

    display: inline-flex;
    align-items: center;
    justify-content: center;

    font-size: 11px;
    font-weight: 700;
}


/* =========================================================
   HERO PHOTOS
========================================================= */

.hero-visual {
    position: absolute;

    right: 0;
    top: 0;

    width: 63%;
    height: 100%;

    overflow: hidden;

    z-index: 2;
}


/*
   CHICKENS IMAGE
   The image is positioned underneath the egg image.
*/

.hero-chickens {
    position: absolute;

    left: -3%;
    top: 0;

    width: 62%;
    height: 100%;

    object-fit: cover;
    object-position: center center;

    z-index: 1;

    /*
       Removes the hard rectangular edge.
    */
    -webkit-mask-image:
        linear-gradient(
            to right,
            #000 0%,
            #000 56%,
            rgba(0,0,0,.95) 67%,
            rgba(0,0,0,.72) 77%,
            rgba(0,0,0,.35) 88%,
            transparent 100%
        );

    mask-image:
        linear-gradient(
            to right,
            #000 0%,
            #000 56%,
            rgba(0,0,0,.95) 67%,
            rgba(0,0,0,.72) 77%,
            rgba(0,0,0,.35) 88%,
            transparent 100%
        );
}


/*
   EGGS IMAGE
*/

.hero-eggs {
    position: absolute;

    right: -3%;
    top: 0;

    width: 68%;
    height: 100%;

    object-fit: cover;
    object-position: center center;

    z-index: 2;

    /*
       Fade the left side so that the two images
       visually merge instead of showing a border.
    */
    -webkit-mask-image:
        linear-gradient(
            to right,
            transparent 0%,
            rgba(0,0,0,.16) 7%,
            rgba(0,0,0,.48) 14%,
            rgba(0,0,0,.82) 23%,
            #000 31%,
            #000 100%
        );

    mask-image:
        linear-gradient(
            to right,
            transparent 0%,
            rgba(0,0,0,.16) 7%,
            rgba(0,0,0,.48) 14%,
            rgba(0,0,0,.82) 23%,
            #000 31%,
            #000 100%
        );
}


/*
   Overall soft blend at the left side of the photo.
*/

.hero-visual:after {
    content: "";

    position: absolute;

    inset: 0;

    z-index: 4;

    pointer-events: none;

    background:
        linear-gradient(
            90deg,
            #f7f0df 0%,
            rgba(247,240,223,.80) 6%,
            rgba(247,240,223,.25) 17%,
            transparent 35%
        );
}
/* =========================================================
   HERO
========================================================= */

.hero {
    position: relative;

    min-height: 615px;

    background: #f6efdf;

    overflow: hidden;

    display: flex;
}


/* =========================================================
   HERO TEXT AREA
========================================================= */

.hero-copy {
    width: 48%;

    padding: 85px 40px 55px 5.1%;

    position: relative;
    z-index: 5;

    background: transparent;

    display: flex;
    flex-direction: column;
    justify-content: flex-start;
}

/* =========================================================
   HERO TITLE
========================================================= */

.hero-title {
    font-family: Georgia, "Times New Roman", serif;

    font-size: clamp(43px, 4.25vw, 59px);

    line-height: .96;

    letter-spacing: -.8px;

    color: var(--green-dark);

    max-width: 480px;

    margin: 0;
}

.hero-title span {
    color: #729263;
}


/* =========================================================
   HERO DESCRIPTION
========================================================= */

.hero-text {
    max-width: 435px;

    margin-top: 20px;

    font-size: 15px;

    line-height: 1.9;

    color: #171c18;

    margin-bottom: 0;
}


/* =========================================================
   ABOUT SECTION
========================================================= */

.about-section {
    position: relative;

    background: #fffdf8;

    padding:
        31px
        6.2%
        16px;

    text-align: center;

    overflow: hidden;
}

.section-heading {
    position: relative;
    z-index: 3;

    font-family: Georgia, "Times New Roman", serif;

    font-size: 29px;

    line-height: 1.1;

    color: var(--green-dark);
}


/* ORNAMENT */

.ornament {
    position: relative;
    z-index: 3;

    display: flex;
    align-items: center;
    justify-content: center;

    gap: 11px;

    margin: 8px auto 13px;
}

.ornament-line {
    width: 35px;
    height: 1px;

    background: #aaa897;
}

.ornament-leaf {
    color: var(--green);

    font-size: 18px;

    line-height: 1;
}


.about-text {
    position: relative;
    z-index: 3;

    max-width: 900px;

    margin: 0 auto;

    
    font-size: 18px;

    line-height: 1.65;

    color: #173d21;

    text-align: justify;
}
/* =========================================================
   ABOUT STATS / FARM METRICS
========================================================= */

.about-stats {
    width: 100%;
    max-width: 1100px;

    margin: 38px auto 0;

    display: grid;
    grid-template-columns: repeat(4, 1fr);

    

    border-radius: 0;

    overflow: hidden;
}


/* EACH STAT */

.about-stat {
    min-height: 155px;

    padding: 25px 20px 22px;

    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;

    text-align: center;

    position: relative;
}


/* VERTICAL DIVIDER */

.about-stat:not(:first-child):before {
    content: "";

    position: absolute;

    left: 0;
    top: 24px;
    bottom: 24px;

    width: 1px;

      background: rgba(33,79,44,.22);
}


/* ICON */

.about-stat-icon {
    width: 48px;
    height: 48px;

    margin-bottom: 8px;

    color: #b7d96b;

    display: flex;
    align-items: center;
    justify-content: center;
}


.about-stat-icon svg {
    width: 42px;
    height: 42px;

    fill: none;

    stroke: currentColor;

    stroke-width: 1.7;

    stroke-linecap: round;
    stroke-linejoin: round;
}


/* LARGE METRIC */

.about-stat-number {
     color: var(--green-dark);

    font-family: "Segoe UI", Arial, sans-serif;

    font-size: 28px;

    line-height: 1.1;

    font-weight: 700;

    margin-bottom: 5px;
}


/* LABEL */

.about-stat-label {
    color: #30352f;

    font-family: "Segoe UI", Arial, sans-serif;

    font-size: 12px;

    line-height: 1.35;

    font-weight: 400;

    max-width: 130px;
}


/* =========================================================
   ABOUT STATS — TABLET
========================================================= */

@media (max-width: 820px) {

    .about-stats {
        grid-template-columns: repeat(2, 1fr);

        max-width: 600px;
    }

    .about-stat {
        min-height: 145px;
    }

    .about-stat:nth-child(3):before {
        display: none;
    }

    .about-stat:nth-child(3),
    .about-stat:nth-child(4) {
        border-top: 1px solid rgba(255,255,255,.25);
    }

}


/* =========================================================
   ABOUT STATS — MOBILE
========================================================= */

@media (max-width: 560px) {

    .about-stats {
        grid-template-columns: repeat(2, 1fr);

        margin-top: 30px;
    }

    .about-stat {
        min-height: 125px;

        padding: 18px 10px;
    }

    .about-stat-icon {
        width: 38px;
        height: 38px;

        margin-bottom: 5px;
    }

    .about-stat-icon svg {
        width: 34px;
        height: 34px;
    }

    .about-stat-number {
        font-size: 23px;
    }

    .about-stat-label {
        font-size: 10px;

        line-height: 1.3;
    }

}
/* =========================================================
   ABOUT DECORATIONS
   Soft transparent illustrations — no visible rectangle
========================================================= */

.about-decoration {
    position: absolute;

    pointer-events: none;

    z-index: 1;

    opacity: .42;

    display: block;

    /*
       IMPORTANT:
       Make the outside edges of the PNG completely disappear.
    */
    -webkit-mask-repeat: no-repeat;
    mask-repeat: no-repeat;

    -webkit-mask-position: center;
    mask-position: center;

    -webkit-mask-size: 100% 100%;
    mask-size: 100% 100%;

    /*
       Prevent the PNG's rectangular background
       from looking like a separate box.
    */
    mix-blend-mode: multiply;
}


/* ==========================================
   LEFT — EGGS ILLUSTRATION
========================================== */
.about-eggs {
    width: 210px;

    left: -12px;
    top: 5px;

    -webkit-mask-image:
        radial-gradient(
            ellipse 58% 55% at center,
            #000 0%,
            #000 25%,
            rgba(0,0,0,.75) 48%,
            rgba(0,0,0,.35) 65%,
            rgba(0,0,0,.10) 78%,
            transparent 92%
        );

    mask-image:
        radial-gradient(
            ellipse 58% 55% at center,
            #000 0%,
            #000 25%,
            rgba(0,0,0,.75) 48%,
            rgba(0,0,0,.35) 65%,
            rgba(0,0,0,.10) 78%,
            transparent 92%
        );
}


/* ==========================================
   RIGHT — FARM ILLUSTRATION
========================================== */
.about-farm {
    width: 225px;

    right: -12px;
    top: 62px;

    -webkit-mask-image:
        radial-gradient(
            ellipse 58% 55% at center,
            #000 0%,
            #000 25%,
            rgba(0,0,0,.75) 48%,
            rgba(0,0,0,.35) 65%,
            rgba(0,0,0,.10) 78%,
            transparent 92%
        );

    mask-image:
        radial-gradient(
            ellipse 58% 55% at center,
            #000 0%,
            #000 25%,
            rgba(0,0,0,.75) 48%,
            rgba(0,0,0,.35) 65%,
            rgba(0,0,0,.10) 78%,
            transparent 92%
        );
}

/* =========================================================
   BENEFITS
========================================================= */
.benefits-wrap {
    position: relative;

    z-index: 3;

    margin-top: 48px;
}

.benefits-heading {
    font-family: Georgia, "Times New Roman", serif;

    font-size: 28px;

    color: var(--green-dark);
}


/* GRID */
.benefits-grid {
    width: 100%;
    max-width: 1140px;

    margin: 28px auto 0;

    display: grid;

    grid-template-columns:
        repeat(6, minmax(0, 1fr));

    gap: 18px;

    padding: 0 4px;
}
/* CARD */
.benefit-card {
    min-height: 250px;

    padding: 20px 14px 17px;

    background: #fff;

    border-radius: 13px;

    box-shadow:
        0 5px 18px rgba(45,52,38,.08);

    display: flex;
    flex-direction: column;
    align-items: center;

    text-align: center;

    overflow: hidden;
}


/* ICON */

.benefit-icon {
    width: 68px;
    height: 68px;

    flex-shrink: 0;

    border-radius: 50%;

    background: #f0f0e7;

    color: var(--green);

    display: flex;
    align-items: center;
    justify-content: center;

    margin-bottom: 12px;
}

.benefit-icon svg {
    width: 39px;
    height: 39px;

    fill: none;

    stroke: currentColor;

    stroke-width: 1.7;

    stroke-linecap: round;
    stroke-linejoin: round;
}


/* TITLE */

.benefit-title {
    color: var(--green-dark);

    font-size: 16px;

    line-height: 1.2;

    font-weight: 700;

    margin-bottom: 8px;

    min-height: 38px;
}


/* DESCRIPTION */

.benefit-text {
    font-size: 12px;

    line-height: 1.65;

    color: #171b17;
}


/* =========================================================
   HOW IT WORKS
========================================================= */
.how-section {
    position: relative;

    background: #f3f2e8;

    padding:
        25px
        6%
        0;

    margin-top: 33px;

    overflow: hidden;
}
.how-title {
    text-align: center;
}


/* FARM DECORATION */

.how-section:after {
    content: "";

    position: absolute;

    right: -10px;
    bottom: -13px;

    width: 175px;
    height: 175px;

    background:
        url("about_farm.png")
        center / contain
        no-repeat;

    opacity: .18;

    pointer-events: none;
}


/* STEPS */

.steps {
    position: relative;
    z-index: 3;

    max-width: 840px;

    margin: 18px auto 0;

    display: grid;

    grid-template-columns:
        1fr
        45px
        1fr
        45px
        1fr;

    align-items: center;
}


/* STEP */

.step {
    display: grid;

    grid-template-columns:
        84px
        1fr;

    gap: 12px;

    align-items: center;
}


/* CIRCLE */

.step-circle {
    width: 84px;
    height: 84px;

    border: 1px solid #b8c1aa;

    border-radius: 50%;

    background: #fffef9;

    display: flex;
    align-items: center;
    justify-content: center;

    position: relative;

    color: var(--green);
}

.step-circle svg {
    width: 43px;
    height: 43px;

    fill: none;

    stroke: currentColor;

    stroke-width: 1.7;

    stroke-linecap: round;
    stroke-linejoin: round;
}


/* NUMBER */

.step-number {
    position: absolute;

    left: -15px;
    top: 1px;

    width: 30px;
    height: 30px;

    border-radius: 50%;

    background: var(--green);

    color: #fff;

    display: flex;
    align-items: center;
    justify-content: center;

    font-size: 12px;
    font-weight: 700;
}


/* STEP TEXT */

.step h3 {
    font-size: 14px;

    margin-bottom: 6px;

    color: #111;
}

.step p {
    font-size: 12px;

    line-height: 1.45;

    color: #242824;
}


/* ARROW */

.step-arrow {
    color: #6d8d5e;

    text-align: center;

    font-size: 32px;

    font-weight: 300;
}


/* =========================================================
   CTA
========================================================= */

.cta-section {
    min-height: 230px;

    position: relative;

    overflow: hidden;

  background: #f3f2e8;

    display: flex;
    align-items: center;
    justify-content: center;

    text-align: center;
}


/* LEFT PHOTO */

/* LEFT PHOTO — CTA EGGS */

.cta-photo {
    position: absolute;

    left: 0;
    top: 20px;

    width: 34%;
    height: 100%;

    object-fit: cover;

    object-position: center  35%;

    /*
       Fade the edges of cta_eggs.png
       so the rectangular image border is not visible.
    */
    -webkit-mask-image:
        linear-gradient(
            to right,
            transparent 0%,
            rgba(0,0,0,.25) 8%,
            rgba(0,0,0,.65) 18%,
            #000 30%,
            #000 68%,
            rgba(0,0,0,.75) 82%,
            rgba(0,0,0,.35) 92%,
            transparent 100%
        );

    mask-image:
        linear-gradient(
            to right,
            transparent 0%,
            rgba(0,0,0,.25) 8%,
            rgba(0,0,0,.65) 18%,
            #000 30%,
            #000 68%,
            rgba(0,0,0,.75) 82%,
            rgba(0,0,0,.35) 92%,
            transparent 100%
        );
}


/* PHOTO FADE */

.cta-section:before {
    content: "";

    position: absolute;

    left: 0;
    top: 0;

    width: 39%;
    height: 100%;

    z-index: 1;

    background:
        linear-gradient(
            90deg,
            rgba(255,250,240,0),
            #f3f2e8
        );
}


 /* CHICKENS RIGHT — FADED EDGES */

.cta-hens {
    position: absolute;

    right: 1%;

    bottom: -1px;

    width: 235px;

    opacity: .65;

    z-index: 1;

    mix-blend-mode: multiply;

    /*
       Fade the edges of footer_chickens.png
       so the rectangular image border is not visible.
    */
  -webkit-mask-image:
    radial-gradient(
        ellipse 55% 60% at center,
        #000 0%,
        rgba(0,0,0,.55) 40%,
        rgba(0,0,0,.15) 65%,
        transparent 88%
    );

mask-image:
    radial-gradient(
        ellipse 55% 60% at center,
        #000 0%,
        rgba(0,0,0,.55) 40%,
        rgba(0,0,0,.15) 65%,
        transparent 88%
    );
}

/* CTA CONTENT */

.cta-content {
    position: relative;

    z-index: 4;

    margin-left: 7%;
}

.cta-content h2 {
    font-family: Georgia, "Times New Roman", serif;

    font-size: 27px;

    line-height: 1.05;

    color: var(--green-dark);
}

.cta-content p {
    font-size: 14px;

    margin:
        8px
        0
        10px;
}

.cta-login {
    display: inline-flex;
    align-items: center;
    justify-content: center;

    gap: 10px;

    padding: 9px 24px;

    background: var(--green);

    color: #fff;

    border-radius: 7px;

    font-size: 13px;
    font-weight: 600;

    transition: .2s ease;
}

.cta-login:hover {
    background: var(--green-dark);
}


/* =========================================================
   FOOTER
========================================================= */

.site-footer {
    background: #214f2c;

    color: #fff;

    padding:
        20px
        7%
        11px;

    min-height: 170px;
}

.footer-grid {
    max-width: 1050px;

    margin: 0 auto;

    display: grid;

    grid-template-columns:
        1.2fr
        .85fr
        .95fr
        1fr;

    gap: 50px;
}


/* FOOTER BRAND */

.footer-brand {
    display: flex;

    align-items: flex-start;

    gap: 11px;
}
.footer-logo {
    width: 55px;
    height: 55px;

    display: flex;
    align-items: center;
    justify-content: center;

    flex-shrink: 0;

    border: none;
    border-radius: 0;

    overflow: visible;
}

.footer-logo img {
    width: 55px;
    height: 55px;

    display: block;

    object-fit: contain;
}
.footer-brand-name {
    font-family: Georgia, "Times New Roman", serif;

    font-size: 30px;

    line-height: 1;

    font-weight: 700;
}

.footer-brand-sub {
    font-size: 13px;

    letter-spacing: 1px;
}


/* DESCRIPTION */

.footer-desc {
    margin:
        9px
        0
        9px
        66px;

    max-width: 130px;

    font-size: 11px;

    line-height: 1.45;

    opacity: .94;
}


/* SOCIAL */

.socials {
    display: flex;

    gap: 9px;

    margin-left: 66px;
}

.socials a {
    width: 28px;
    height: 28px;

    border-radius: 50%;

    background: rgba(255,255,255,.15);

    display: flex;
    align-items: center;
    justify-content: center;

    font-size: 12px;

    transition: .2s ease;
}

.socials a:hover {
    background: rgba(255,255,255,.27);
}


/* FOOTER COLUMNS */

.footer-col h3 {
    font-size: 14px;

    margin-bottom: 9px;
}

.footer-col a,
.footer-contact p {
    display: block;

    font-size: 12px;

    line-height: 1.8;

    opacity: .95;
}

.footer-col a:hover {
    text-decoration: underline;
}


/* CONTACT */

.footer-contact p {
    display: flex;

    gap: 8px;

    align-items: flex-start;
}


/* BOTTOM */

.footer-bottom {
    max-width: 1050px;

    margin:
        14px
        auto
        0;

    text-align: center;

    font-size: 11px;

    opacity: .95;
}


/* =========================================================
   RESPONSIVE
========================================================= */

@media (max-width: 1050px) {

 .site-header {
    padding: 0 3%;
}

.main-nav {
    gap: 20px;
}

.brand-name {
    font-size: 26px;
}

.brand-subtitle {
    font-size: 10px;
}
.benefits-grid {
    grid-template-columns:
        repeat(3, minmax(0, 1fr));

    max-width: 760px;

    gap: 18px;
}

    .benefit-card {
        min-height: 220px;
    }
}


/* =========================================================
   TABLET
========================================================= */

@media (max-width: 820px) {

   .site-header {
    height: 74px;
}

.brand-mark {
    width: 54px;
    height: 54px;
}

.brand-name {
    font-size: 24px;
}

.brand-subtitle {
    font-size: 10px;
}

    .main-nav {
        display: none;

        position: absolute;

       top: 70px;
        left: 0;
        right: 0;

        background: #fff;

        padding: 15px 5%;

        flex-direction: column;

        align-items: stretch;

        gap: 2px;

        box-shadow:
            0 5px 15px rgba(0,0,0,.08);
    }

    .main-nav.open {
        display: flex;
    }

    .main-nav .login-btn {
        width: 100%;
    }

    .mobile-menu {
        display: block;
    }


    /* HERO */
/* =====================================================
   HERO — TABLET
===================================================== */

.hero {
    min-height: 610px;

    position: relative;
}

.hero-copy {
    width: 100%;

    padding:
        43px
        8%
        260px;

    position: relative;

    z-index: 5;

    background:
        linear-gradient(
            180deg,
            rgba(248,242,230,.98) 0%,
            rgba(248,242,230,.90) 55%,
            rgba(248,242,230,.15) 100%
        );
}

.hero-title {
    font-size: 48px;

    line-height: .96;

    max-width: 480px;
}

.hero-text {
    max-width: 435px;

    margin-top: 22px;

    font-size: 14px;

    line-height: 1.55;
}

.hero-actions {
    display: flex;

    align-items: center;

    gap: 12px;

    margin-top: 19px;

    flex-wrap: wrap;
}

    .hero-visual {
        width: 100%;

        height: 330px;

        top: auto;
        bottom: 0;
    }

    .hero-title {
        font-size: 48px;
    }

   


    /* ABOUT */

    .about-section {
        padding-top: 35px;
    }

    .about-eggs {
        width: 150px;

        opacity: .35;
    }

    .about-farm {
        width: 170px;

        opacity: .35;
    }


    /* HOW */

    .steps {
        grid-template-columns: 1fr;

        gap: 18px;

        max-width: 500px;
    }

    .step {
        grid-template-columns:
            84px
            1fr;
    }

    .step-arrow {
        display: none;
    }


    /* CTA */

    .cta-content {
        margin: 0 20px;
    }

    .cta-hens {
        opacity: .25;

        width: 190px;
    }


    /* FOOTER */

    .footer-grid {
        grid-template-columns:
            repeat(2, 1fr);

        gap: 28px;
    }
            /* HERO FARM VALUES */

        .hero-features {
            grid-template-columns: repeat(2, 1fr);

            padding: 14px 4% 16px;

            gap: 12px;
        }

        .hero-feature {
            min-height: 85px;

            padding: 0 12px;
        }

        .hero-feature-icon {
            width: 38px;
            height: 38px;

            margin-bottom: 5px;
        }

        .hero-feature-icon svg {
            width: 34px;
            height: 34px;
        }

        .hero-feature h3 {
            font-size: 11px;
        }

        .hero-feature p {
            font-size: 10px;
        }

        .hero-feature:nth-child(3):before {
            display: none;
        }
}


/* =========================================================
   MOBILE
========================================================= */

@media (max-width: 560px) {

   .brand-mark {
    width: 42px;
    height: 42px;
}

.brand-name {
    font-size: 22px;
}

.brand-subtitle {
    font-size: 9px;
}

    .login-btn {
        padding:
            10px
            13px;

        font-size: 12px;
    }


    /* HERO */

    .hero-title {
        font-size: 40px;
    }

    .hero-text {
        font-size: 14px;
    }

    .hero-actions {
        gap: 9px;

        flex-wrap: wrap;
    }

    .hero-actions a {
        padding:
            10px
            15px;

        font-size: 13px;
    }


    /* BENEFITS */
.benefits-grid {
    grid-template-columns:
        repeat(2, minmax(0, 1fr));

    gap: 14px;

    padding: 0;
}

.benefit-card {
    min-height: 225px;

    padding: 17px 10px 15px;
}


    /* DECORATIONS */

    .about-eggs,
    .about-farm {
        display: none;
    }


    /* FOOTER */

    .footer-grid {
        grid-template-columns: 1fr;
    }

    .footer-desc,
    .socials {
        margin-left: 0;
    }

    .footer-desc {
        max-width: 200px;
    }
            /* HERO FARM VALUES — MOBILE */

        .hero-features {
            grid-template-columns: repeat(2, 1fr);

            padding: 10px 3% 12px;

            gap: 8px;
        }

        .hero-feature {
            min-height: 78px;

            padding: 0 6px;
        }

        .hero-feature-icon {
            width: 32px;
            height: 32px;

            margin-bottom: 4px;
        }

        .hero-feature-icon svg {
            width: 29px;
            height: 29px;
        }

        .hero-feature h3 {
            font-size: 9px;

            letter-spacing: .2px;
        }

        .hero-feature p {
            font-size: 8px;

            line-height: 1.3;
        }
}

</style>
</head>


<body id="home">


<!-- =========================================================
     HEADER
========================================================= -->

<header class="site-header">
<a 
    href="#home" 
    class="brand" 
    aria-label="VDVC Egg Farm Home"
>
    <span class="brand-mark"></span>
</a>

    <nav
        class="main-nav"
        id="mainNav"
    >

        <a
            href="#home"
            class="active"
        >
            Home
        </a>

        <a href="#about">
            About Us
        </a>

        <a href="#how-it-works">
            How It Works
        </a>

        <a href="#contact">
            Contact Us
        </a>

        <a
            href="login.php"
            class="login-btn"
        >

            <span class="lock-icon"></span>

            Login to System

        </a>

    </nav>


    <button
        type="button"
        class="mobile-menu"
        id="mobileMenu"
        aria-label="Open menu"
    >
        &#9776;
    </button>

</header>



<main>


<!-- =========================================================
     HERO
========================================================= -->

<section
    class="hero"
    id="hero"
>

    <div class="hero-copy">

        <h1 class="hero-title">

            Fresh Eggs.<br>

            Trusted Farm.<br>

            <span>
                Better for You.
            </span>

        </h1>


        <p class="hero-text">

            VDVC Egg Farm provides you fresh, quality eggs
            and a convenient way to order, track, and receive
            your eggs hassle-free.

        </p>


        <div class="hero-actions">

            <a
                href="login.php"
                class="login-btn"
            >

                <span class="lock-icon"></span>

                Login to System

            </a>


            <a
                href="#about"
                class="hero-secondary"
            >

                <span class="info-dot">
                    i
                </span>

                Learn More

            </a>

        </div>
            <!-- =================================================
                 HERO FARM VALUES
            ================================================== -->

            <div class="hero-features">

                <!-- 01 NATURAL & SAFE -->

                <div class="hero-feature">

                    <div class="hero-feature-icon">

                        <svg viewBox="0 0 48 48">

                            <!-- Stem -->

                            <path d="
                                M24 39
                                C24 31 24 24 28 17
                            "></path>

                            <!-- Left leaf -->

                            <path d="
                                M24 29
                                C17 29 12 25 11 18
                                C18 18 23 21 24 29
                                Z
                            "></path>

                            <!-- Right leaf -->

                            <path d="
                                M25 23
                                C27 15 33 11 40 11
                                C39 18 34 23 25 23
                                Z
                            "></path>

                        </svg>

                    </div>


                    <h3>
                        Natural &amp; Safe
                    </h3>


                    <p>
                        No hormones, no unnecessary antibiotics
                    </p>

                </div>



                <!-- 02 HIGH STANDARDS -->

                <div class="hero-feature">

                    <div class="hero-feature-icon">

                        <svg viewBox="0 0 48 48">

                            <!-- Shield -->

                            <path d="
                                M24 5
                                L39 11
                                V22
                                C39 31 33 38 24 42
                                C15 38 9 31 9 22
                                V11
                                Z
                            "></path>

                            <!-- Check -->

                            <path d="
                                M16 23
                                L21 28
                                L32 17
                            "></path>

                        </svg>

                    </div>


                    <h3>
                        High Standards
                    </h3>


                    <p>
                        Strict quality control at every step
                    </p>

                </div>



                <!-- 03 ANIMAL WELFARE -->

                <div class="hero-feature">

                    <div class="hero-feature-icon">

                        <svg viewBox="0 0 48 48">

                            <!-- Heart -->

                            <path d="
                                M24 40
                                C21 37 8 29 8 18
                                C8 11 16 8 21 13
                                L24 16
                                L27 13
                                C32 8 40 11 40 18
                                C40 29 27 37 24 40
                                Z
                            "></path>

                            <!-- Pig face -->

                            <path d="
                                M16 23
                                C16 19 19 17 24 17
                                C29 17 32 19 32 23
                                V28
                                C32 32 29 34 24 34
                                C19 34 16 32 16 28
                                Z
                            "></path>

                            <!-- Pig ears -->

                            <path d="
                                M17 21
                                L14 18
                                L14 23
                            "></path>

                            <path d="
                                M31 21
                                L34 18
                                L34 23
                            "></path>

                            <!-- Pig nose -->

                            <ellipse
                                cx="24"
                                cy="28"
                                rx="5"
                                ry="3"
                            ></ellipse>

                            <circle
                                cx="22"
                                cy="28"
                                r="1"
                            ></circle>

                            <circle
                                cx="26"
                                cy="28"
                                r="1"
                            ></circle>

                        </svg>

                    </div>


                    <h3>
                        Animal Welfare
                    </h3>


                    <p>
                        Humane care in a clean and healthy environment
                    </p>

                </div>



                <!-- 04 SUSTAINABLE FARMING -->

                <div class="hero-feature">

                    <div class="hero-feature-icon">

                        <svg viewBox="0 0 48 48">

                            <!-- Stem -->

                            <path d="
                                M24 40
                                C24 32 24 24 20 17
                            "></path>

                            <path d="
                                M24 40
                                C24 31 27 24 32 18
                            "></path>

                            <!-- Left sprouting leaf -->

                            <path d="
                                M21 25
                                C14 25 10 21 10 14
                                C17 14 21 18 21 25
                                Z
                            "></path>

                            <!-- Right sprouting leaf -->

                            <path d="
                                M27 25
                                C28 18 33 14 40 14
                                C39 21 35 25 27 25
                                Z
                            "></path>

                        </svg>

                    </div>


                    <h3>
                        Sustainable Farming
                    </h3>


                    <p>
                        Eco-friendly practices for a better tomorrow
                    </p>

                </div>

            </div>
    </div>


    <!-- HERO PHOTOS -->
<div
    class="hero-visual"
    aria-hidden="true"
>

    <img
        src="hero_chickens.jpg"
        alt=""
        class="hero-chickens"
    >

    <img
        src="hero_eggs.jpg"
        alt=""
        class="hero-eggs"
    >

</div>


</section>



<!-- =========================================================
     ABOUT
========================================================= -->

<section
    class="about-section"
    id="about"
>


    <img
        src="about_eggs.png"
        alt=""
        class="about-decoration about-eggs"
    >


    <img
        src="about_farm.png"
        alt=""
        class="about-decoration about-farm"
    >


    <h2 class="section-heading">
        About Us
    </h2>


    <div
        class="ornament"
        aria-hidden="true"
    >

        <span class="ornament-line"></span>

        <span class="ornament-leaf">
            ♧
        </span>

        <span class="ornament-line"></span>

    </div>
<div class="about-text">

    <p>
        VDVC Egg Farm is committed to providing fresh and high-quality eggs sourced from a trusted local farm.
        We value freshness, quality, and reliability in every order we prepare for our customers.  Through this system, customers can easily place reservations, monitor their orders, and receive their eggs with less hassle.
        Our goal is to make every step simple, reliable, and convenient.
    </p>

       
    

</div>
        <!-- =====================================================
             ABOUT FARM METRICS
        ====================================================== -->

        <div class="about-stats">


            <!-- 01 YEARS OF EXPERIENCE -->

            <div class="about-stat">

                <div class="about-stat-icon">

                    <svg viewBox="0 0 48 48">

                        <!-- Sun / Gear Badge -->

                        <circle
                            cx="24"
                            cy="24"
                            r="8"
                        ></circle>

                        <path d="
                            M24 5
                            V10
                            M24 38
                            V43
                            M5 24
                            H10
                            M38 24
                            H43
                        "></path>

                        <path d="
                            M10.5 10.5
                            L14 14
                            M34 34
                            L37.5 37.5
                            M37.5 10.5
                            L34 14
                            M14 34
                            L10.5 37.5
                        "></path>

                        <circle
                            cx="24"
                            cy="24"
                            r="3"
                        ></circle>

                    </svg>

                </div>


                <div class="about-stat-number">
                    15+
                </div>


                <div class="about-stat-label">
                    Years of<br>
                    Experience
                </div>

            </div>



            <!-- 02 HAPPY CUSTOMERS -->

            <div class="about-stat">

                <div class="about-stat-icon">

                    <svg viewBox="0 0 48 48">

                        <!-- Center Person -->

                        <circle
                            cx="24"
                            cy="15"
                            r="5"
                        ></circle>

                        <path d="
                            M15
                            38
                            C15.5 30
                            19 26
                            24 26
                            C29 26
                            32.5 30
                            33 38
                        "></path>


                        <!-- Left Person -->

                        <circle
                            cx="11"
                            cy="19"
                            r="4"
                        ></circle>

                        <path d="
                            M3
                            38
                            C3.5 32
                            6 28
                            11 28
                            C13 28
                            14.5 29
                            16 31
                        "></path>


                        <!-- Right Person -->

                        <circle
                            cx="37"
                            cy="19"
                            r="4"
                        ></circle>

                        <path d="
                            M32
                            31
                            C33.5 29
                            35 28
                            37 28
                            C42 28
                            44.5 32
                            45 38
                        "></path>

                    </svg>

                </div>


                <div class="about-stat-number">
                    25K+
                </div>


                <div class="about-stat-label">
                    Happy<br>
                    Customers
                </div>

            </div>



            <!-- 03 QUALITY ASSURANCE -->

            <div class="about-stat">

                <div class="about-stat-icon">

                    <svg viewBox="0 0 48 48">

                        <!-- Shield -->

                        <path d="
                            M24 5
                            L39 11
                            V22
                            C39 31
                            33 38
                            24 42
                            C15 38
                            9 31
                            9 22
                            V11
                            Z
                        "></path>


                        <!-- Leaf -->

                        <path d="
                            M24 32
                            C20 28
                            18 24
                            20 19
                            C26 19
                            30 22
                            30 27
                            C30 31
                            27 33
                            24 32
                            Z
                        "></path>


                        <!-- Leaf Stem -->

                        <path d="
                            M24 32
                            C24 27
                            26 24
                            29 21
                        "></path>

                    </svg>

                </div>


                <div class="about-stat-number">
                    100%
                </div>


                <div class="about-stat-label">
                    Quality<br>
                    Assurance
                </div>

            </div>



            <!-- 04 LOCALLY FARMED -->

            <div class="about-stat">

                <div class="about-stat-icon">

                    <svg viewBox="0 0 48 48">

                        <!-- Truck -->

                        <path d="
                            M4 28
                            H30
                            V14
                            H4
                            Z
                        "></path>

                        <path d="
                            M30 20
                            H37
                            L44 27
                            V35
                            H30
                            Z
                        "></path>


                        <!-- Wheels -->

                        <circle
                            cx="12"
                            cy="36"
                            r="4"
                        ></circle>

                        <circle
                            cx="36"
                            cy="36"
                            r="4"
                        ></circle>


                        <path d="
                            M16 36
                            H32
                        "></path>


                        <!-- Small Farm Mark -->

                        <path d="
                            M15 18
                            C18 15
                            21 15
                            23 18
                        "></path>

                    </svg>

                </div>


                <div class="about-stat-number">
                    PHILIPPINES
                </div>


                <div class="about-stat-label">
                    Locally<br>
                    Farmed
                </div>

            </div>


        </div>


    <!-- =====================================================
         BENEFITS
    ====================================================== -->

    <div class="benefits-wrap">

        <h2 class="benefits-heading">
            Benefits for You
        </h2>


        <div
            class="ornament"
            aria-hidden="true"
        >

            <span class="ornament-line"></span>

            <span class="ornament-leaf">
                ♧
            </span>

            <span class="ornament-line"></span>

        </div>


        <div class="benefits-grid">


            <!-- 01 -->

            <article class="benefit-card">

                <div class="benefit-icon">

                    <svg viewBox="0 0 48 48">

                        <rect
                            x="11"
                            y="5"
                            width="26"
                            height="38"
                            rx="3"
                        ></rect>

                        <path d="M18 11h12"></path>

                        <path d="M16 20h15l-2 8H19z"></path>

                        <circle
                            cx="21"
                            cy="33"
                            r="1.7"
                        ></circle>

                        <circle
                            cx="29"
                            cy="33"
                            r="1.7"
                        ></circle>

                    </svg>

                </div>


                <h3 class="benefit-title">
                    Easy &amp;<br>
                    Convenient
                </h3>


                <p class="benefit-text">
                    Place your orders anytime,
                    anywhere in just a few clicks.
                </p>

            </article>



            <!-- 02 -->

            <article class="benefit-card">

                <div class="benefit-icon">

                    <svg viewBox="0 0 48 48">

                        <path d="
                            M24 5
                            l16 6
                            v11
                            c0 10-6.7 17.1-16 21
                            c-9.3-3.9-16-11-16-21
                            V11z
                        "></path>

                        <path d="
                            M16 24
                            l5 5
                            l11-12
                        "></path>

                    </svg>

                </div>


                <h3 class="benefit-title">
                    Reliable &amp;<br>
                    Secure
                </h3>


                <p class="benefit-text">
                    Your information and orders
                    are safe with us, every time.
                </p>

            </article>



            <!-- 03 -->

            <article class="benefit-card">

                <div class="benefit-icon">

                    <svg viewBox="0 0 48 48">

                        <path d="
                            M24 7
                            C18 13 12 19.5 12 27
                            a12 12 0 0 0 24 0
                            c0-7.5-6-14-12-20z
                        "></path>

                        <path d="
                            M19 28
                            c0-4 2-7 4-9
                        "></path>

                    </svg>

                </div>


                <h3 class="benefit-title">
                    Fresh &amp;<br>
                    Quality Eggs
                </h3>


                <p class="benefit-text">
                    We ensure farm-fresh eggs
                    that you and your family can trust.
                </p>

            </article>



            <!-- 04 -->

            <article class="benefit-card">

                <div class="benefit-icon">

                    <svg viewBox="0 0 48 48">

                        <path d="
                            M4 29h27V15H4z
                        "></path>

                        <path d="
                            M31 21h7l6 7v7H31z
                        "></path>

                        <circle
                            cx="12"
                            cy="36"
                            r="4"
                        ></circle>

                        <circle
                            cx="37"
                            cy="36"
                            r="4"
                        ></circle>

                        <path d="
                            M16 36h17
                        "></path>

                    </svg>

                </div>


                <h3 class="benefit-title">
                    On-Time<br>
                    Delivery
                </h3>


                <p class="benefit-text">
                    Receive your eggs on time,
                    right at your doorstep.
                </p>

            </article>



            <!-- 05 -->

            <article class="benefit-card">

                <div class="benefit-icon">

                    <svg viewBox="0 0 48 48">

                        <circle
                            cx="24"
                            cy="24"
                            r="17"
                        ></circle>

                        <path d="
                            M24 14v11l7 4
                        "></path>

                        <path d="
                            M24 5v3
                            M24 40v3
                            M5 24h3
                            M40 24h3
                        "></path>

                    </svg>

                </div>


                <h3 class="benefit-title">
                    Track with<br>
                    Ease
                </h3>


                <p class="benefit-text">
                    Track your orders in real-time
                    and stay updated.
                </p>

            </article>



            <!-- 06 -->

            <article class="benefit-card">

                <div class="benefit-icon">

                    <svg viewBox="0 0 48 48">

                        <path d="
                            M24 40
                            S8 31 8 19
                            c0-7 8-10 13-4
                            c1.3 1.5 3 3 3 3
                            s1.7-1.5 3-3
                            c5-6 13-3 13 4
                            c0 12-16 21-16 21z
                        "></path>

                    </svg>

                </div>


                <h3 class="benefit-title">
                    Better<br>
                    Experience
                </h3>


                <p class="benefit-text">
                    Designed to give you a smooth
                    and satisfying ordering experience.
                </p>

            </article>


        </div>

    </div>

</section>



<!-- =========================================================
     HOW IT WORKS
========================================================= -->

<section
    class="how-section"
    id="how-it-works"
>

    <h2 class="section-heading how-title">
        How It Works
    </h2>


    <div
        class="ornament"
        aria-hidden="true"
    >

        <span class="ornament-line"></span>

        <span class="ornament-leaf">
            ♧
        </span>

        <span class="ornament-line"></span>

    </div>


    <div class="steps">


        <!-- STEP 01 -->

        <div class="step">

            <div class="step-circle">

                <span class="step-number">
                    01
                </span>

                <svg viewBox="0 0 48 48">

                    <circle
                        cx="24"
                        cy="16"
                        r="8"
                    ></circle>

                    <path d="
                        M10 42
                        c1-9 6-14 14-14
                        s13 5 14 14
                    "></path>

                </svg>

            </div>


            <div>

                <h3>
                    Create an Account
                </h3>

                <p>
                    Sign up and log in
                    to your account.
                </p>

            </div>

        </div>



        <div
            class="step-arrow"
            aria-hidden="true"
        >
            &#8594;
        </div>



        <!-- STEP 02 -->

        <div class="step">

            <div class="step-circle">

                <span class="step-number">
                    02
                </span>

                <svg viewBox="0 0 48 48">

                    <path d="
                        M7 10h5l4 23h22l4-16H14
                    "></path>

                    <circle
                        cx="20"
                        cy="40"
                        r="2"
                    ></circle>

                    <circle
                        cx="34"
                        cy="40"
                        r="2"
                    ></circle>

                    <path d="
                        M23 17h10
                    "></path>

                </svg>

            </div>


            <div>

                <h3>
                    Place Your Order
                </h3>

                <p>
                    Choose your eggs and
                    submit your reservation.
                </p>

            </div>

        </div>



        <div
            class="step-arrow"
            aria-hidden="true"
        >
            &#8594;
        </div>



        <!-- STEP 03 -->

        <div class="step">

            <div class="step-circle">

                <span class="step-number">
                    03
                </span>

                <svg viewBox="0 0 48 48">

                    <path d="
                        M4 29h29V14H4z
                    "></path>

                    <path d="
                        M33 20h7l5 7v8H33z
                    "></path>

                    <circle
                        cx="12"
                        cy="38"
                        r="4"
                    ></circle>

                    <circle
                        cx="38"
                        cy="38"
                        r="4"
                    ></circle>

                    <path d="
                        M16 38h18
                    "></path>

                    <path d="
                        M12 19v9
                        M8 24h8
                    "></path>

                </svg>

            </div>


            <div>

                <h3>
                    We Deliver to You
                </h3>

                <p>
                    We prepare and deliver
                    your eggs or you may pick them up.
                </p>

            </div>

        </div>

    </div>

</section>



<!-- =========================================================
     CTA
========================================================= -->

<section class="cta-section">

    <img
        src="cta_eggss.jpg"
        alt=""
        class="cta-photo"
    >


    <img
        src="footer_chickens.png"
        alt=""
        class="cta-hens"
    >


    <div class="cta-content">

        <h2>
            Ready to enjoy fresh eggs<br>
            with ease?
        </h2>

        <p>
            Login now to get started!
        </p>

       

    </div>

</section>


</main>



<!-- =========================================================
     FOOTER
========================================================= -->

<footer
    class="site-footer"
    id="contact"
>


    <div class="footer-grid">


        <!-- BRAND -->

        <div>

            <div class="footer-brand">

<div class="footer-logo">
    <img src="vdvclogoo.png" alt="VDVC Egg Farm Logo">
</div>

                <div>

                    <div class="footer-brand-name">
                        VDVC
                    </div>

                    <div class="footer-brand-sub">
                        EGG FARM
                    </div>

                </div>

            </div>


            <p class="footer-desc">
                Bringing fresh eggs
                from our farm to your table.
            </p>


            <div class="socials">

                <a
                    href="#"
                    aria-label="Facebook"
                >
                    f
                </a>

                <a
                    href="#"
                    aria-label="Instagram"
                >
                    ◎
                </a>

                <a
                    href="mailto:info@vdvceggfarm.com"
                    aria-label="Email"
                >
                    @
                </a>

            </div>

        </div>



        <!-- QUICK LINKS -->

        <div class="footer-col">

            <h3>
                Quick Links
            </h3>

            <a href="#home">
                Home
            </a>

            <a href="#about">
                About Us
            </a>

            <a href="#how-it-works">
                How It Works
            </a>

            <a href="#contact">
                Contact Us
            </a>

        </div>



        <!-- CUSTOMER -->

        <div class="footer-col">

            <h3>
                Customer
            </h3>

            <a href="customer_dashboard.php">
                My Orders
            </a>

            <a href="customer_reservation.php">
                Track Order
            </a>

            <a href="#how-it-works">
                Delivery Information
            </a>

            <a href="#">
                Help Center / FAQs
            </a>

        </div>



        <!-- CONTACT -->

        <div class="footer-col footer-contact">

            <h3>
                Contact Us
            </h3>

            <p>
                <span>☎</span>
                <span>
                    +63 912 345 6789
                </span>
            </p>

            <p>
                <span>✉</span>
                <span>
                    info@vdvceggfarm.com
                </span>
            </p>

            <p>
                <span>⌖</span>
                <span>
                    Lemery, Batangas,<br>
                    Philippines
                </span>
            </p>

        </div>


    </div>


    <div class="footer-bottom">

        &copy;
        <?php echo date("Y"); ?>
        VDVC Egg Farm.
        All Rights Reserved.

    </div>

</footer>



<!-- =========================================================
     JAVASCRIPT
     PHP 5.3 / OLD BROWSER FRIENDLY
========================================================= -->

<script>

(function () {

    var menuButton =
        document.getElementById("mobileMenu");

    var nav =
        document.getElementById("mainNav");

    var navLinks =
        nav.getElementsByTagName("a");


    /* MOBILE MENU */

    menuButton.onclick = function () {

        if (
            nav.className.indexOf("open") === -1
        ) {

            nav.className += " open";

        } else {

            nav.className =
                nav.className.replace(
                    " open",
                    ""
                );

        }

    };


    /* CLOSE MOBILE MENU */

    for (
        var i = 0;
        i < navLinks.length;
        i++
    ) {

        navLinks[i].onclick = function () {

            nav.className =
                nav.className.replace(
                    " open",
                    ""
                );

        };

    }


    /* =====================================================
       ACTIVE NAVIGATION WHILE SCROLLING
    ====================================================== */

    var sections = [

        {
            id: "home",
            link: 0
        },

        {
            id: "about",
            link: 1
        },

        {
            id: "how-it-works",
            link: 2
        },

        {
            id: "contact",
            link: 3
        }

    ];


    window.onscroll = function () {

        var current = 0;

        /*
           Header is sticky, so compensate for
           its height.
        */

        var scrollPosition =
            window.pageYOffset + 140;


        for (
            var i = 0;
            i < sections.length;
            i++
        ) {

            var element =
                document.getElementById(
                    sections[i].id
                );


            if (
                element &&
                element.offsetTop <= scrollPosition
            ) {

                current =
                    sections[i].link;

            }

        }


        for (
            var j = 0;
            j < navLinks.length;
            j++
        ) {

            navLinks[j].className =
                navLinks[j].className.replace(
                    " active",
                    ""
                );

        }


        if (navLinks[current]) {

            navLinks[current].className +=
                " active";

        }

    };


})();

</script>


</body>
</html>