<?php
require_once 'config.php';
requireAdmin();
$isAr = isset($_SESSION['lang']) && $_SESSION['lang'] === 'ar';
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pAction = $_POST['action'] ?? '';

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

      <!-- Users Management -->
      <div>
        <div class="card mb-20">
          <div class="card-header"><span class="card-title"><?= $isAr?'المستخدمون':'Users' ?></span></div>
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
                <td>
                  <?php if ($u['id'] != $_SESSION['user_id']): ?>
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

        <!-- Add User -->
        <div class="card mb-20">
          <div class="card-header"><span class="card-title"><?= $isAr?'إضافة مستخدم':'Add User' ?></span></div>
          <div class="card-body">
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
              <button type="submit" class="btn btn-success btn-full"><?= $isAr?'إضافة مستخدم':'Add User' ?></button>
            </form>
          </div>
        </div>

        <!-- Change Password -->
        <div class="card">
          <div class="card-header"><span class="card-title"><?= $isAr?'تغيير كلمة المرور':'Change Password' ?></span></div>
          <div class="card-body">
            <form method="POST">
              <input type="hidden" name="action" value="change_password">
              <div class="form-group">
                <label class="form-label"><?= $isAr?'المستخدم':'User' ?></label>
                <select name="uid" class="form-control">
                  <?php foreach ($users as $u): ?>
                  <option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['full_name']) ?> (@<?= $u['username'] ?>)</option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="form-group">
                <label class="form-label"><?= $isAr?'كلمة المرور الجديدة':'New Password' ?></label>
                <input type="password" name="new_password" class="form-control" required minlength="6">
              </div>
              <button type="submit" class="btn btn-warning btn-full"><?= $isAr?'تغيير':'Change Password' ?></button>
            </form>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
</div>
<script src="assets/js/main.js"></script>
</body></html>
