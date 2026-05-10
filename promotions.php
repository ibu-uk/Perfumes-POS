<?php
require_once 'config.php';
requireLogin();
requireAdmin();
$isAr = isset($_SESSION['lang']) && $_SESSION['lang'] === 'ar';

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'save_promo') {
        $id = (int)($_POST['id'] ?? 0);
        $name = $conn->real_escape_string(trim($_POST['name']));
        $desc = $conn->real_escape_string($_POST['description'] ?? '');
        $discType = in_array($_POST['discount_type'], ['percent','fixed']) ? $_POST['discount_type'] : 'percent';
        $discVal = (float)$_POST['discount_value'];
        $startDate = $conn->real_escape_string($_POST['start_date']);
        $endDate = $conn->real_escape_string($_POST['end_date']);
        $isActive = isset($_POST['is_active']) ? 1 : 0;
        
        if ($id) {
            $conn->query("UPDATE promotions SET name='$name', description='$desc', discount_type='$discType', discount_value=$discVal, start_date='$startDate', end_date='$endDate', is_active=$isActive WHERE id=$id");
            $msg = $isAr ? 'تم تحديث الترويج.' : 'Promotion updated.';
        } else {
            $conn->query("INSERT INTO promotions (name, description, discount_type, discount_value, start_date, end_date, is_active, created_by) VALUES ('$name','$desc','$discType',$discVal,'$startDate','$endDate',$isActive,{$_SESSION['user_id']})");
            $id = $conn->insert_id;
            $msg = $isAr ? 'تمت إضافة الترويج.' : 'Promotion added.';
        }
        
        // Save product links
        $conn->query("DELETE FROM promotion_products WHERE promotion_id=$id");
        if (isset($_POST['products']) && is_array($_POST['products'])) {
            foreach ($_POST['products'] as $prodId) {
                $prodId = (int)$prodId;
                if ($prodId) {
                    $conn->query("INSERT INTO promotion_products (promotion_id, product_id, product_size_id) VALUES ($id, $prodId, NULL)");
                }
            }
        }
        if (isset($_POST['sizes']) && is_array($_POST['sizes'])) {
            foreach ($_POST['sizes'] as $sizeId) {
                $sizeId = (int)$sizeId;
                if ($sizeId) {
                    // Get product_id from size
                    $rSz = $conn->query("SELECT product_id FROM product_sizes WHERE id=$sizeId LIMIT 1");
                    $sz = $rSz ? $rSz->fetch_assoc() : null;
                    if ($sz) {
                        $conn->query("INSERT INTO promotion_products (promotion_id, product_id, product_size_id) VALUES ($id, {$sz['product_id']}, $sizeId)");
                    }
                }
            }
        }
        
        header('Location: promotions.php?msg=' . urlencode($msg));
        exit;
    }
    
    if ($action === 'delete_promo') {
        $id = (int)$_POST['id'];
        $conn->query("DELETE FROM promotions WHERE id=$id");
        $msg = $isAr ? 'تم حذف الترويج.' : 'Promotion deleted.';
        header('Location: promotions.php?msg=' . urlencode($msg));
        exit;
    }
    
    if ($action === 'toggle_promo') {
        $id = (int)$_POST['id'];
        $isActive = (int)$_POST['is_active'];
        $conn->query("UPDATE promotions SET is_active=$isActive WHERE id=$id");
        exit;
    }
}

// Get all promotions
$rPromos = $conn->query("SELECT p.*, (SELECT COUNT(*) FROM promotion_products WHERE promotion_id=p.id) as product_count FROM promotions p ORDER BY created_at DESC");
$promos = $rPromos ? $rPromos->fetch_all(MYSQLI_ASSOC) : [];

// Get all products for selection
$rProducts = $conn->query("SELECT id, name, name_ar, type FROM products WHERE is_active=1 ORDER BY name");
$products = $rProducts ? $rProducts->fetch_all(MYSQLI_ASSOC) : [];

// Get all product sizes for selection
$rSizes = $conn->query("SELECT ps.*, p.name as product_name, p.name_ar as product_name_ar FROM product_sizes ps JOIN products p ON p.id=ps.product_id ORDER BY ps.product_id, ps.sort_order");
$sizesAll = $rSizes ? $rSizes->fetch_all(MYSQLI_ASSOC) : [];

// Edit mode
$editPromo = null;
$editProducts = [];
$editSizes = [];
if (isset($_GET['edit'])) {
    $editId = (int)$_GET['edit'];
    $rEdit = $conn->query("SELECT * FROM promotions WHERE id=$editId LIMIT 1");
    $editPromo = $rEdit ? $rEdit->fetch_assoc() : null;
    if ($editPromo) {
        $rEditProds = $conn->query("SELECT product_id, product_size_id FROM promotion_products WHERE promotion_id=$editId");
        $editProds = $rEditProds ? $rEditProds->fetch_all(MYSQLI_ASSOC) : [];
        foreach ($editProds as $ep) {
            if ($ep['product_size_id']) {
                $editSizes[] = $ep['product_size_id'];
            } else {
                $editProducts[] = $ep['product_id'];
            }
        }
    }
}

$pageTitle = $isAr ? 'العروض الترويجية' : 'Promotions';
include 'includes/head.php';
?>
<div class="app-layout">
<?php include 'includes/sidebar.php'; ?>
<div class="main-content">
  <div class="topbar">
    <div class="topbar-title"><?= $isAr ? 'العروض الترويجية' : 'Promotions' ?></div>
    <div class="topbar-right">
      <a href="promotions.php" class="btn btn-sm btn-outline"><?= $isAr ? 'قائمة' : 'List' ?></a>
      <a href="promotions.php?new=1" class="btn btn-sm btn-primary">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        <?= $isAr ? 'ترويج جديد' : 'New Promotion' ?>
      </a>
    </div>
  </div>
  
  <div class="page-content">
    <?php if (isset($_GET['msg'])): ?>
    <div class="alert alert-success"><?= htmlspecialchars($_GET['msg']) ?></div>
    <?php endif; ?>
    
    <?php if (isset($_GET['new']) || isset($_GET['edit'])): ?>
    <!-- Form -->
    <div class="card" style="max-width:900px;margin:0 auto;">
      <div class="card-header">
        <span class="card-title"><?= $editPromo ? ($isAr ? 'تعديل الترويج' : 'Edit Promotion') : ($isAr ? 'ترويج جديد' : 'New Promotion') ?></span>
      </div>
      <div class="card-body">
        <form method="POST">
          <input type="hidden" name="action" value="save_promo">
          <input type="hidden" name="id" value="<?= $editPromo['id'] ?? 0 ?>">
          
          <div class="form-group">
            <label class="form-label"><?= $isAr ? 'اسم الترويج' : 'Promotion Name' ?></label>
            <input type="text" name="name" class="form-control" required value="<?= htmlspecialchars($editPromo['name'] ?? '') ?>">
          </div>
          
          <div class="form-group">
            <label class="form-label"><?= $isAr ? 'الوصف' : 'Description' ?></label>
            <textarea name="description" class="form-control" rows="2"><?= htmlspecialchars($editPromo['description'] ?? '') ?></textarea>
          </div>
          
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
            <div class="form-group">
              <label class="form-label"><?= $isAr ? 'نوع الخصم' : 'Discount Type' ?></label>
              <select name="discount_type" class="form-control">
                <option value="percent" <?= ($editPromo['discount_type'] ?? '') === 'percent' ? 'selected' : '' ?>><?= $isAr ? 'نسبة مئوية' : 'Percent' ?></option>
                <option value="fixed" <?= ($editPromo['discount_type'] ?? '') === 'fixed' ? 'selected' : '' ?>><?= $isAr ? 'مبلغ ثابت' : 'Fixed KD' ?></option>
              </select>
            </div>
            <div class="form-group">
              <label class="form-label"><?= $isAr ? 'قيمة الخصم' : 'Discount Value' ?></label>
              <input type="number" name="discount_value" class="form-control" step="0.001" min="0" required value="<?= $editPromo['discount_value'] ?? '' ?>">
            </div>
          </div>
          
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
            <div class="form-group">
              <label class="form-label"><?= $isAr ? 'تاريخ البدء' : 'Start Date' ?></label>
              <input type="date" name="start_date" class="form-control" required value="<?= $editPromo['start_date'] ?? '' ?>">
            </div>
            <div class="form-group">
              <label class="form-label"><?= $isAr ? 'تاريخ الانتهاء' : 'End Date' ?></label>
              <input type="date" name="end_date" class="form-control" required value="<?= $editPromo['end_date'] ?? '' ?>">
            </div>
          </div>
          
          <div class="form-group">
            <label class="form-label"><?= $isAr ? 'المنتجات' : 'Products' ?></label>
            <select name="products[]" class="form-control" multiple style="height:120px;">
              <?php foreach ($products as $p): ?>
              <option value="<?= $p['id'] ?>" <?= in_array($p['id'], $editProducts) ? 'selected' : '' ?>>
                <?= htmlspecialchars($isAr ? $p['name_ar'] : $p['name']) ?> (<?= $isAr ? 'الكل' : 'All' ?>)
              </option>
              <?php endforeach; ?>
            </select>
            <small style="color:#6b7280;"><?= $isAr ? 'اختر منتجات لتطبيق الترويج على جميع الأحجام' : 'Select products to apply promotion to all sizes' ?></small>
          </div>
          
          <div class="form-group">
            <label class="form-label"><?= $isAr ? 'أحجام محددة' : 'Specific Sizes' ?></label>
            <div style="max-height:250px;overflow-y:auto;border:1px solid #e5e7eb;border-radius:6px;padding:12px;background:#f9fafb;">
              <?php 
              $sizesByProduct = [];
              foreach ($sizesAll as $s) {
                $sizesByProduct[$s['product_id']][] = $s;
              }
              foreach ($products as $p) {
                if (isset($sizesByProduct[$p['id']])) {
              ?>
              <div style="margin-bottom:12px;">
                <div style="font-weight:700;color:#374151;margin-bottom:6px;"><?= htmlspecialchars($isAr ? $p['name_ar'] : $p['name']) ?></div>
                <?php foreach ($sizesByProduct[$p['id']] as $s) { ?>
                <label style="display:flex;align-items:center;gap:8px;padding:4px 0;cursor:pointer;">
                  <input type="checkbox" name="sizes[]" value="<?= $s['id'] ?>" <?= in_array($s['id'], $editSizes) ? 'checked' : '' ?>>
                  <span style="font-size:13px;color:#4b5563;"><?= htmlspecialchars($s['size_label']) ?> - <?= number_format($s['price'],3) ?> KD</span>
                </label>
                <?php } ?>
              </div>
              <?php } } ?>
            </div>
            <small style="color:#6b7280;"><?= $isAr ? 'اختر أحجام محددة لتطبيق الترويج عليها فقط' : 'Select specific sizes to apply promotion only to those sizes' ?></small>
          </div>
          
          <div class="form-group">
            <label style="display:flex;align-items:center;gap:8px;">
              <input type="checkbox" name="is_active" value="1" <?= ($editPromo['is_active'] ?? 1) ? 'checked' : '' ?>>
              <span><?= $isAr ? 'نشط' : 'Active' ?></span>
            </label>
          </div>
          
          <div style="display:flex;gap:8px;margin-top:16px;">
            <button type="submit" class="btn btn-primary"><?= $isAr ? 'حفظ' : 'Save' ?></button>
            <a href="promotions.php" class="btn btn-outline"><?= $isAr ? 'إلغاء' : 'Cancel' ?></a>
          </div>
        </form>
      </div>
    </div>
    
    <?php else: ?>
    <!-- List -->
    <div class="card">
      <div class="card-body">
        <?php if (empty($promos)): ?>
        <div class="text-center text-muted" style="padding:40px;"><?= $isAr ? 'لا توجد عروض ترويجية' : 'No promotions found' ?></div>
        <?php else: ?>
        <table>
          <thead><tr>
            <th><?= $isAr ? 'الاسم' : 'Name' ?></th>
            <th><?= $isAr ? 'الخصم' : 'Discount' ?></th>
            <th><?= $isAr ? 'الفترة' : 'Period' ?></th>
            <th><?= $isAr ? 'المنتجات' : 'Products' ?></th>
            <th><?= $isAr ? 'الحالة' : 'Status' ?></th>
            <th></th>
          </tr></thead>
          <tbody>
          <?php foreach ($promos as $p): ?>
          <tr>
            <td style="font-weight:600;"><?= htmlspecialchars($p['name']) ?></td>
            <td>
              <?= number_format($p['discount_value'], 3) ?>
              <?= $p['discount_type'] === 'percent' ? '%' : 'KD' ?>
            </td>
            <td style="font-size:12px;">
              <?= date('d/m/Y', strtotime($p['start_date'])) ?> - <?= date('d/m/Y', strtotime($p['end_date'])) ?>
            </td>
            <td><span class="badge badge-gray"><?= $p['product_count'] ?></span></td>
            <td>
              <?php $now = date('Y-m-d'); $active = $p['is_active'] && $p['start_date'] <= $now && $p['end_date'] >= $now; ?>
              <span class="badge <?= $active ? 'badge-green' : 'badge-gray' ?>">
                <?= $active ? ($isAr ? 'نشط' : 'Active') : ($isAr ? 'غير نشط' : 'Inactive') ?>
              </span>
            </td>
            <td style="text-align:right;">
              <a href="promotions.php?edit=<?= $p['id'] ?>" class="btn btn-sm btn-outline"><?= $isAr ? 'تعديل' : 'Edit' ?></a>
              <form method="POST" style="display:inline;" onsubmit="return confirm('<?= $isAr ? 'حذف هذا الترويج؟' : 'Delete this promotion?' ?>')">
                <input type="hidden" name="action" value="delete_promo">
                <input type="hidden" name="id" value="<?= $p['id'] ?>">
                <button type="submit" class="btn btn-sm btn-danger"><?= $isAr ? 'حذف' : 'Delete' ?></button>
              </form>
            </td>
          </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
        <?php endif; ?>
      </div>
    </div>
    <?php endif; ?>
  </div>
</div>
</div>
<script src="assets/js/main.js"></script>
</body></html>
