<?php
require_once 'config.php';
requireLogin();
$lang = ($_GET['lang'] ?? 'en') === 'ar' ? 'ar' : 'en';
$_SESSION['lang'] = $lang;
$back = $_SERVER['HTTP_REFERER'] ?? 'dashboard.php';
header('Location: ' . $back);
exit;
