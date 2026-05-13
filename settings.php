<?php
require_once 'config.php';
requireAdmin();
$isAr = isset($_SESSION['lang']) && $_SESSION['lang'] === 'ar';
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pAction = $_POST['action'] ?? '';

    if ($pAction === 'upload_logo') {
        if (!empty($_FILES['logo']['tmp_name'])) {
            $ext = strtolower(pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['png','jpg','jpeg','gif','webp','svg'])) {
                $dest = __DIR__ . '/assets/uploads/logo.' . $ext;
                // Remove old logo files
                foreach (glob(__DIR__ . '/assets/uploads/logo.*') as $f) @unlink($f);
                move_uploaded_file($_FILES['logo']['tmp_name'], $dest);
                $logoPath = 'assets/uploads/logo.' . $ext;
                $conn->query("INSERT INTO settings (setting_key, setting_value) VALUES ('shop_logo','$logoPath') ON DUPLICATE KEY UPDATE setting_value='$logoPath'");
                $msg = $isAr ? 'تم حفظ الشعار.' : 'Logo saved.';
            } else {
                $msg = 'Invalid file type. Use PNG, JPG, GIF or SVG.';
            }
        }
        header('Location: settings.php?msg=' . urlencode($msg)); exit;
    }

    if ($pAction === 'save_loyalty') {
        $loyaltyEnabled   = isset($_POST['loyalty_enabled']) ? '1' : '0';
        $kdPerPoint       = max(1, (int)($_POST['loyalty_kd_per_point'] ?? 10));
        $pointValue       = max(1, (int)($_POST['loyalty_point_value'] ?? 1));
        $conn->query("INSERT INTO settings (setting_key, setting_value) VALUES ('loyalty_enabled','$loyaltyEnabled') ON DUPLICATE KEY UPDATE setting_value='$loyaltyEnabled'");
        $conn->query("INSERT INTO settings (setting_key, setting_value) VALUES ('loyalty_kd_per_point','$kdPerPoint') ON DUPLICATE KEY UPDATE setting_value='$kdPerPoint'");
        $conn->query("INSERT INTO settings (setting_key, setting_value) VALUES ('loyalty_point_value','$pointValue') ON DUPLICATE KEY UPDATE setting_value='$pointValue'");
        $msg = $isAr ? 'تم حفظ إعدادات النقاط.' : 'Loyalty settings saved.';
    }

    if ($pAction === 'save_settings') {
        $keys = ['shop_name','shop_name_ar','shop_address','shop_address_ar','shop_phone','currency','currency_ar','tax_rate','receipt_footer','receipt_footer_ar','invoice_prefix','low_stock_days'];
        foreach ($keys as $k) {
            if (isset($_POST[$k])) {
                $val = $conn->real_escape_string($_POST[$k]);
                $conn->query("UPDATE settings SET setting_value='$val' WHERE setting_key='$k'");
            }
        }
        $msg = $isAr ? 'تم حفظ الإعدادات.' : 'Settings saved.';
    }

    if ($pAction === 'add_user') {
        $uname   = $conn->real_escape_string(trim($_POST['username']));
        $upass   = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $fname   = $conn->real_escape_string(trim($_POST['full_name']));
        $fnameAr = $conn->real_escape_string(trim($_POST['full_name_ar']));
        $role    = in_array($_POST['role'], ['admin','cashier']) ? $_POST['role'] : 'cashier';
        $conn->query("INSERT INTO users (username, password, full_name, full_name_ar, role) VALUES ('$uname','$upass','$fname','$fnameAr','$role')");
        $msg = $isAr ? 'تمت إضافة المستخدم.' : 'User added.';
    }

    if ($pAction === 'toggle_user') {
        $uid = (int)$_POST['uid'];
        $conn->query("UPDATE users SET is_active = 1 - is_active WHERE id=$uid AND id != " . (int)$_SESSION['user_id']);
        $msg = $isAr ? 'تم تحديث المستخدم.' : 'User updated.';
    }

    if ($pAction === 'change_password') {
        $uid = (int)$_POST['uid'];
        $np = password_hash($_POST['new_password'], PASSWORD_DEFAULT);
        $conn->query("UPDATE users SET password='$np' WHERE id=$uid");
        $msg = $isAr ? 'تم تغيير كلمة المرور.' : 'Password changed.';
    }

    header('Location: settings.php?msg=' . urlencode($msg));
    exit;
}
if (isset($_GET['msg'])) $msg = $_GET['msg'];

// Load settings
$rSet = $conn->query("SELECT setting_key, setting_value FROM settings");
$settings = [];
while ($row = $rSet->fetch_assoc()) $settings[$row['setting_key']] = $row['setting_value'];

// Load users
$rUsers = $conn->query("SELECT * FROM users ORDER BY role, full_name");
$users = $rUsers ? $rUsers->fetch_all(MYSQLI_ASSOC) : [];

$pageTitle = $isAr ? 'الإعدادات' : 'Settings';
include 'includes/head.php';
?>
<div class="app-layout">
<?php include 'includes/sidebar.php'; ?>
<div class="main-content">
  <div class="topbar">
    <div class="topbar-title"><?= $isAr ? 'الإعدادات' : 'Settings' ?></div>
    <div class="topbar-right">
      <a href="lang.php?lang=<?= $isAr ? 'en' : 'ar' ?>" class="lang-btn"><?= $isAr ? 'EN' : 'ع' ?></a>
    </div>
  </div>
  <div class="page-content">
    <?php if ($msg): ?><div class="alert alert-success"><?= htmlspecialchars($msg) ?></div><?php endif; ?>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;align-items:start;">

      <!-- Shop Settings -->
      <div class="card">
        <div class="card-header"><span class="card-title"><?= $isAr?'إعدادات المتجر':'Shop Settings' ?></span></div>
        <div class="card-body">
          <form method="POST">
            <input type="hidden" name="action" value="save_settings">
            <div class="form-row">
              <div class="form-group">
                <label class="form-label">Shop Name (EN)</label>
                <input type="text" name="shop_name" class="form-control" value="<?= htmlspecialchars($settings['shop_name'] ?? '') ?>">
              </div>
              <div class="form-group">
                <label class="form-label">اسم المتجر (AR)</label>
                <input type="text" name="shop_name_ar" class="form-control" dir="rtl" value="<?= htmlspecialchars($settings['shop_name_ar'] ?? '') ?>">
              </div>
            </div>
            <div class="form-group">
              <label class="form-label">Address (EN)</label>
              <textarea name="shop_address" class="form-control" rows="2"><?= htmlspecialchars($settings['shop_address'] ?? '') ?></textarea>
            </div>
            <div class="form-group">
              <label class="form-label">العنوان (AR)</label>
              <textarea name="shop_address_ar" class="form-control" rows="2" dir="rtl"><?= htmlspecialchars($settings['shop_address_ar'] ?? '') ?></textarea>
            </div>
            <div class="form-row">
              <div class="form-group">
                <label class="form-label"><?= $isAr?'الهاتف':'Phone' ?></label>
                <input type="text" name="shop_phone" class="form-control" value="<?= htmlspecialchars($settings['shop_phone'] ?? '') ?>">
              </div>
              <div class="form-group">
                <label class="form-label"><?= $isAr?'بادئة الفاتورة':'Invoice Prefix' ?></label>
                <input type="text" name="invoice_prefix" class="form-control" value="<?= htmlspecialchars($settings['invoice_prefix'] ?? 'INV') ?>">
              </div>
            </div>
            <div class="form-row">
              <div class="form-group">
                <label class="form-label"><?= $isAr?'نسبة الضريبة %':'Tax Rate %' ?></label>
                <input type="number" name="tax_rate" class="form-control" step="0.1" min="0" value="<?= htmlspecialchars($settings['tax_rate'] ?? '0') ?>">
              </div>
              <div class="form-group">
                <label class="form-label"><?= $isAr?'حد المخزون (أيام)':'Low Stock Days' ?></label>
                <input type="number" name="low_stock_days" class="form-control" value="<?= htmlspecialchars($settings['low_stock_days'] ?? '7') ?>">
              </div>
            </div>
            <div class="form-row">
              <div class="form-group">
                <label class="form-label"><?= $isAr?'تذييل الفاتورة (EN)':'Receipt Footer (EN)' ?></label>
                <input type="text" name="receipt_footer" class="form-control" value="<?= htmlspecialchars($settings['receipt_footer'] ?? '') ?>">
              </div>
              <div class="form-group">
                <label class="form-label"><?= $isAr?'تذييل الفاتورة (AR)':'Receipt Footer (AR)' ?></label>
                <input type="text" name="receipt_footer_ar" class="form-control" dir="rtl" value="<?= htmlspecialchars($settings['receipt_footer_ar'] ?? '') ?>">
              </div>
            </div>
            <button type="submit" class="btn btn-primary btn-full"><?= $isAr?'حفظ الإعدادات':'Save Settings' ?></button>
          </form>
        </div>
      </div>

      <!-- Logo Upload -->
      <div class="card" style="margin-bottom:20px;">
        <div class="card-header"><span class="card-title"><?= $isAr?'شعار المتجر':'Shop Logo' ?></span></div>
        <div class="card-body">
          <?php $currentLogo = $settings['shop_logo'] ?? ''; ?>
          <?php if ($currentLogo && file_exists(__DIR__ . '/' . $currentLogo)): ?>
          <div style="text-align:center;margin-bottom:16px;">
            <img src="<?= htmlspecialchars($currentLogo) ?>?v=<?= time() ?>" alt="Logo" style="max-height:80px;max-width:200px;object-fit:contain;border-radius:8px;border:1px solid #e5e7eb;padding:8px;">
          </div>
          <?php endif; ?>
          <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action" value="upload_logo">
            <div class="form-group">
              <label class="form-label"><?= $isAr?'رفع شعار جديد':'Upload New Logo' ?></label>
              <input type="file" name="logo" class="form-control" accept="image/*" required>
              <div style="font-size:11px;color:#9ca3af;margin-top:4px;">PNG, JPG, SVG — <?= $isAr?'يظهر على القائمة الجانبية وصفحة تسجيل الدخول':'Shown on sidebar and login page' ?></div>
            </div>
            <button type="submit" class="btn btn-primary btn-full"><?= $isAr?'حفظ الشعار':'Save Logo' ?></button>
          </form>
        </div>
      </div>

      <!-- Loyalty Points Settings -->
      <div class="card" style="margin-bottom:20px;">
        <div class="card-header"><span class="card-title"><?= $isAr?'نقاط الولاء':'Loyalty Points' ?></span></div>
        <div class="card-body">
          <form method="POST">
            <input type="hidden" name="action" value="save_loyalty">
            <div class="form-group" style="display:flex;align-items:center;gap:12px;padding:10px;background:#f9fafb;border-radius:8px;margin-bottom:12px;">
              <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-weight:600;">
                <input type="checkbox" name="loyalty_enabled" <?= ($settings['loyalty_enabled'] ?? '1') === '1' ? 'checked' : '' ?> style="width:18px;height:18px;">
                <?= $isAr?'تفعيل نظام النقاط':'Enable Loyalty Points System' ?>
              </label>
            </div>
            <div class="form-row">
              <div class="form-group">
                <label class="form-label"><?= $isAr?'كل كم دينار = نقطة':'KD Spent per 1 Point' ?></label>
                <input type="number" name="loyalty_kd_per_point" class="form-control" min="1" value="<?= (int)($settings['loyalty_kd_per_point'] ?? 10) ?>">
                <div style="font-size:11px;color:#9ca3af;margin-top:4px;"><?= $isAr?'مثال: 10 = كل 10 دنانير تعطي نقطة':'e.g. 10 = every 10 KD earns 1 point' ?></div>
              </div>
              <div class="form-group">
                <label class="form-label"><?= $isAr?'قيمة النقطة (دينار)':'1 Point Value (KD)' ?></label>
                <input type="number" name="loyalty_point_value" class="form-control" min="1" value="<?= (int)($settings['loyalty_point_value'] ?? 1) ?>">
                <div style="font-size:11px;color:#9ca3af;margin-top:4px;"><?= $isAr?'مثال: 1 = النقطة الواحدة = 1 دينار خصم':'e.g. 1 = 1 point = 1 KD discount' ?></div>
              </div>
            </div>
            <button type="submit" class="btn btn-primary btn-full"><?= $isAr?'حفظ إعدادات النقاط':'Save Loyalty Settings' ?></button>
          </form>
        </div>
      </div>

      <!-- Users Management -->
      <div>
        <div class="card">
          <div class="card-header" style="display:flex;justify-content:space-between;align-items:center;">
            <span class="card-title"><?= $isAr?'المستخدمون':'Users' ?></span>
            <button onclick="document.getElementById('addUserModal').style.display='flex'" class="btn btn-sm btn-primary"><?= $isAr?'+ إضافة مستخدم':'+ Add User' ?></button>
          </div>
          <div class="table-wrapper">
            <table>
              <thead><tr>
                <th><?= $isAr?'المستخدم':'User' ?></th>
                <th><?= $isAr?'الدور':'Role' ?></th>
                <th><?= $isAr?'الحالة':'Status' ?></th>
                <th></th>
              </tr></thead>
              <tbody>
              <?php foreach ($users as $u): ?>
              <tr>
                <td>
                  <div style="font-weight:600;"><?= htmlspecialchars($u['full_name']) ?></div>
                  <div style="font-size:11px;color:#9ca3af;">@<?= htmlspecialchars($u['username']) ?></div>
                </td>
                <td><span class="badge <?= $u['role']==='admin' ? 'badge-blue' : 'badge-gray' ?>"><?= ucfirst($u['role']) ?></span></td>
                <td>
                  <span class="badge <?= $u['is_active'] ? 'badge-green' : 'badge-red' ?>">
                    <?= $u['is_active'] ? ($isAr?'نشط':'Active') : ($isAr?'معطل':'Inactive') ?>
                  </span>
                </td>
                <td style="text-align:right;">
                  <?php if ($u['id'] != $_SESSION['user_id']): ?>
                  <button onclick="openChangePassword(<?= $u['id'] ?>, '<?= htmlspecialchars($u['full_name']) ?>')" class="btn btn-sm btn-outline" style="margin-right:4px;"><?= $isAr?'كلمة المرور':'Password' ?></button>
                  <form method="POST" style="display:inline;">
                    <input type="hidden" name="action" value="toggle_user">
                    <input type="hidden" name="uid" value="<?= $u['id'] ?>">
                    <button class="btn btn-sm btn-outline"><?= $u['is_active'] ? ($isAr?'تعطيل':'Disable') : ($isAr?'تفعيل':'Enable') ?></button>
                  </form>
                  <?php endif; ?>
                </td>
              </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
</div>

<!-- Add User Modal -->
<div id="addUserModal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);z-index:9999;align-items:center;justify-content:center;">
  <div style="background:#fff;border-radius:12px;padding:32px;max-width:500px;width:90%;box-shadow:0 20px 25px -5px rgba(0,0,0,0.1);">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
      <h3 style="margin:0;font-size:18px;font-weight:700;"><?= $isAr?'إضافة مستخدم جديد':'Add New User' ?></h3>
      <button onclick="document.getElementById('addUserModal').style.display='none'" style="background:none;border:none;font-size:24px;cursor:pointer;">&times;</button>
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="add_user">
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Name (EN)</label>
          <input type="text" name="full_name" class="form-control" required>
        </div>
        <div class="form-group">
          <label class="form-label">الاسم (AR)</label>
          <input type="text" name="full_name_ar" class="form-control" dir="rtl">
        </div>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label"><?= $isAr?'اسم المستخدم':'Username' ?></label>
          <input type="text" name="username" class="form-control" required>
        </div>
        <div class="form-group">
          <label class="form-label"><?= $isAr?'كلمة المرور':'Password' ?></label>
          <input type="password" name="password" class="form-control" required>
        </div>
      </div>
      <div class="form-group">
        <label class="form-label"><?= $isAr?'الدور':'Role' ?></label>
        <select name="role" class="form-control">
          <option value="cashier"><?= $isAr?'كاشير':'Cashier' ?></option>
          <option value="admin"><?= $isAr?'مدير':'Admin' ?></option>
        </select>
      </div>
      <button type="submit" class="btn btn-primary btn-full"><?= $isAr?'إضافة':'Add' ?></button>
    </form>
  </div>
</div>

<!-- Change Password Modal -->
<div id="changePasswordModal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);z-index:9999;align-items:center;justify-content:center;">
  <div style="background:#fff;border-radius:12px;padding:32px;max-width:400px;width:90%;box-shadow:0 20px 25px -5px rgba(0,0,0,0.1);">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
      <h3 style="margin:0;font-size:18px;font-weight:700;"><?= $isAr?'تغيير كلمة المرور':'Change Password' ?></h3>
      <button onclick="document.getElementById('changePasswordModal').style.display='none'" style="background:none;border:none;font-size:24px;cursor:pointer;">&times;</button>
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="change_password">
      <input type="hidden" name="uid" id="changePasswordUid">
      <div class="form-group">
        <label class="form-label"><?= $isAr?'المستخدم':'User' ?></label>
        <div id="changePasswordUser" style="font-weight:600;padding:8px 0;color:#374151;"></div>
      </div>
      <div class="form-group">
        <label class="form-label"><?= $isAr?'كلمة المرور الجديدة':'New Password' ?></label>
        <input type="password" name="new_password" class="form-control" required minlength="6">
      </div>
      <button type="submit" class="btn btn-primary btn-full"><?= $isAr?'تغيير':'Change' ?></button>
    </form>
  </div>
</div>

<script>
function openChangePassword(uid, name) {
  document.getElementById('changePasswordUid').value = uid;
  document.getElementById('changePasswordUser').textContent = name;
  document.getElementById('changePasswordModal').style.display = 'flex';
}
</script>
<script src="assets/js/main.js"></script>
</body></html>
