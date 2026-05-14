<?php
require_once 'config.php';
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    if ($username && $password) {
        $u = $conn->real_escape_string($username);
        $r = $conn->query("SELECT * FROM users WHERE username='$u' AND is_active=1 LIMIT 1");
        if ($r && $row = $r->fetch_assoc()) {
            if (password_verify($password, $row['password'])) {
                $_SESSION['user_id']   = $row['id'];
                $_SESSION['user_name'] = $row['full_name'];
                $_SESSION['user_role'] = $row['role'];
                $_SESSION['branch_id'] = $row['branch_id'];
                $_SESSION['lang']      = 'en';
                header('Location: dashboard.php');
                exit;
            }
        }
        $error = 'Invalid username or password.';
    } else {
        $error = 'Please enter username and password.';
    }
}

$shopName = getSetting('shop_name', 'Demo POS');
$shopNameAr = getSetting('shop_name_ar', 'Demo POS');
$shopLogo = getSetting('shop_logo', '');
$logoExists = $shopLogo && file_exists(__DIR__ . '/' . $shopLogo);
?>
<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Login — Demo POS</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&family=Noto+Sans+Arabic:wght@400;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/main.css">
<style>
.login-page { background: linear-gradient(135deg, #1e3a5f 0%, #2563eb 50%, #7c3aed 100%); }
.login-card { padding: 44px 40px; }
.bilingual-title { display: flex; flex-direction: column; align-items: center; gap: 2px; }
.bilingual-title .en { font-size: 22px; font-weight: 800; color: #1f2937; }
.bilingual-title .ar { font-size: 18px; font-weight: 700; color: #4b5563; font-family: 'Noto Sans Arabic', sans-serif; }
.divider { height: 1px; background: #e5e7eb; margin: 20px 0; }
.login-footer { text-align: center; font-size: 11px; color: #9ca3af; margin-top: 20px; }
</style>
</head>
<body>
<div class="login-page">
  <div class="login-card">
    <div class="login-logo">
      <?php if ($logoExists): ?>
      <img src="<?= htmlspecialchars($shopLogo) ?>?v=<?= filemtime(__DIR__ . '/' . $shopLogo) ?>" loading="lazy" alt="Logo" style="height:72px;width:auto;max-width:180px;object-fit:contain;margin-bottom:8px;">
      <?php else: ?>
      <div class="logo-icon">🌸</div>
      <?php endif; ?>
      <div class="bilingual-title">
        <span class="en"><?= htmlspecialchars($shopName) ?></span>
        <span class="ar"><?= htmlspecialchars($shopNameAr) ?></span>
      </div>
      <p style="margin-top:6px;font-size:12px;color:#6b7280;">Point of Sale System</p>
    </div>
    <div class="divider"></div>
    <?php if ($error): ?>
      <div class="alert alert-danger" style="margin-bottom:14px;"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <form method="POST">
      <div class="form-group">
        <label class="form-label">Username / اسم المستخدم</label>
        <input type="text" name="username" class="form-control" placeholder="Enter username" autocomplete="username" value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" required>
      </div>
      <div class="form-group">
        <label class="form-label">Password / كلمة المرور</label>
        <input type="password" name="password" class="form-control" placeholder="••••••••" autocomplete="current-password" required>
      </div>
      <button type="submit" class="btn btn-primary btn-full btn-lg" style="margin-top:8px;">
        Sign In / تسجيل الدخول
      </button>
    </form>
  </div>
</div>
</body>
</html>
