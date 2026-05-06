<?php
// ONE-TIME USE — Delete this file after running it!
require_once 'config.php';

$newPassword = 'admin123';
$hash = password_hash($newPassword, PASSWORD_DEFAULT);

// Update admin user
$safe = $conn->real_escape_string($hash);
$r = $conn->query("UPDATE users SET password='$safe' WHERE username='admin'");

// Update shop name to Demo POS
$conn->query("UPDATE settings SET setting_value='Demo POS' WHERE setting_key='shop_name'");

if ($conn->affected_rows > 0) {
    echo '<div style="font-family:sans-serif;padding:20px;background:#dcfce7;border:1px solid #16a34a;border-radius:8px;max-width:400px;margin:40px auto;">';
    echo '<h2 style="color:#16a34a;">✅ Password Reset!</h2>';
    echo '<p>Admin password is now: <strong>admin123</strong></p>';
    echo '<p style="margin-top:12px;"><a href="index.php" style="color:#2563eb;">→ Go to Login</a></p>';
    echo '<p style="margin-top:8px;font-size:12px;color:#6b7280;">⚠️ Delete this file now: <code>reset_admin.php</code></p>';
    echo '</div>';
} else {
    echo '<div style="font-family:sans-serif;padding:20px;background:#fee2e2;border:1px solid #dc2626;border-radius:8px;max-width:400px;margin:40px auto;">';
    echo '<h2 style="color:#dc2626;">❌ Failed</h2>';
    echo '<p>Could not find admin user. Make sure you ran <strong>db_setup.sql</strong> in phpMyAdmin first.</p>';
    echo '<p style="margin-top:8px;"><a href="db_setup.sql" download>Download db_setup.sql</a></p>';
    echo '</div>';
}
?>
