<?php
require_once 'session_config.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'owner') {
    header("Location: login.php");
    exit();
}

require __DIR__ . '/vdvc-dashboard.html';
