<?php
$isAr = isset($_SESSION['lang']) && $_SESSION['lang'] === 'ar';
$dir = $isAr ? 'rtl' : 'ltr';
$pageTitle = $pageTitle ?? 'Demo POS';
?>
<!DOCTYPE html>
<html lang="<?= $isAr ? 'ar' : 'en' ?>" dir="<?= $dir ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($pageTitle) ?> — Demo POS</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Noto+Sans+Arabic:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/main.css">
</head>
<body class="<?= $isAr ? 'rtl' : 'ltr' ?>">
