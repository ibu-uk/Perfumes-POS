<?php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'demo_pos');

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    die('<div style="font-family:sans-serif;padding:20px;background:#fee;border:1px solid #f00;">
        Database connection failed: ' . $conn->connect_error . '<br>
        Please run <b>db_setup.sql</b> in phpMyAdmin first.
    </div>');
}
$conn->set_charset('utf8mb4');

// Session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function requireLogin() {
    if (!isset($_SESSION['user_id'])) {
        header('Location: index.php');
        exit;
    }
}

function requireAdmin() {
    requireLogin();
    if ($_SESSION['user_role'] !== 'admin') {
        header('Location: dashboard.php?error=access_denied');
        exit;
    }
}

function requireAttendanceAdmin() {
    requireLogin();
    $attendance_role = $_SESSION['attendance_role'] ?? 'none';
    if ($attendance_role !== 'admin') {
        header('Location: attendance_dashboard.php?error=access_denied');
        exit;
    }
}

function requireAttendanceHR() {
    requireLogin();
    $attendance_role = $_SESSION['attendance_role'] ?? 'none';
    if ($attendance_role !== 'admin' && $attendance_role !== 'hr') {
        header('Location: attendance_dashboard.php?error=access_denied');
        exit;
    }
}

function getSetting($key, $default = '') {
    global $conn;
    $key = $conn->real_escape_string($key);
    $r = $conn->query("SELECT setting_value FROM settings WHERE setting_key='$key' LIMIT 1");
    if ($r && $r->num_rows) return $r->fetch_assoc()['setting_value'];
    return $default;
}

function generateInvoiceNo() {
    global $conn;
    $prefix = getSetting('invoice_prefix', 'INV');
    $date = date('Ymd');
    $r = $conn->query("SELECT COUNT(*) as c FROM sales WHERE DATE(created_at)=CURDATE()");
    $count = ($r ? $r->fetch_assoc()['c'] : 0) + 1;
    return $prefix . '-' . $date . '-' . str_pad($count, 3, '0', STR_PAD_LEFT);
}

function formatKWD($amount) {
    return number_format((float)$amount, 3) . ' KD';
}

function isRTL() {
    return isset($_SESSION['lang']) && $_SESSION['lang'] === 'ar';
}
?>
