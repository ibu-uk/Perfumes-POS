<?php
require_once 'config.php';
requireLogin();
$isAr = isset($_SESSION['lang']) && $_SESSION['lang'] === 'ar';
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pAction = $_POST['action'] ?? '';
    if ($pAction === 'save') {
        $id     = (int)($_POST['id'] ?? 0);
        $name   = $conn->real_escape_string(trim($_POST['name']));
        $nameAr = $conn->real_escape_string(trim($_POST['name_ar']));
        $icon   = $conn->real_escape_string($_POST['icon'] ?? 'tag');
        $sort   = (int)$_POST['sort_order'];
        if ($id) {
            $conn->query("UPDATE categories SET name='$name', name_ar='$nameAr', icon='$icon', sort_order=$sort WHERE id=$id");
            $msg = $isAr ? 'تم تحديث الفئة.' : 'Category updated.';
        } else {
            $conn->query("INSERT INTO categories (name, name_ar, icon, sort_order) VALUES ('$name','$nameAr','$icon',$sort)");
            $msg = $isAr ? 'تمت إضافة الفئة.' : 'Category added.';
        }
    } elseif ($pAction === 'delete') {
        $id = (int)$_POST['id'];
        $conn->query("UPDATE categories SET is_active=0 WHERE id=$id");
        $msg = $isAr ? 'تم حذف الفئة.' : 'Category deleted.';
    }
    header('Location: categories.php?msg=' . urlencode($msg));
    exit;
}
if (isset($_GET['msg'])) $msg = $_GET['msg'];

$editId = (int)($_GET['id'] ?? 0);
$editCat = null;
if ($editId) {
    $r = $conn->query("SELECT * FROM categories WHERE id=$editId LIMIT 1");
    $editCat = $r ? $r->fetch_assoc() : null;
}

$rCats = $conn->query("SELECT c.*, (SELECT COUNT(*) FROM products WHERE category_id=c.id AND is_active=1) as product_count FROM categories c WHERE c.is_active=1 ORDER BY c.sort_order, c.name");
$categories = $rCats ? $rCats->fetch_all(MYSQLI_ASSOC) : [];

$pageTitle = $isAr ? 'الفئات' : 'Categories';
include 'includes/head.php';
?>
<div class="app-layout">
<?php include 'includes/sidebar.php'; ?>
<div class="main-content">
  <div class="topbar">
    <div class="topbar-title"><?= $isAr ? 'الفئات' : 'Categories' ?></div>
    <div class="topbar-right">
      <a href="lang.php?lang=<?= $isAr ? 'en' : 'ar' ?>" class="lang-btn"><?= $isAr ? 'EN' : 'ع' ?></a>
    </div>
  </div>
  <div class="page-content">
    <?php if ($msg): ?><div class="alert alert-success"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
    <div style="display:grid;grid-template-columns:1fr 380px;gap:20px;align-items:start;">
      <div class="card">
        <div class="card-header">
          <span class="card-title"><?= $isAr ? 'الفئات' : 'Categories' ?> <span class="badge badge-blue" style="margin-<?= $isAr?'right':'left' ?>:8px;"><?= count($categories) ?></span></span>
        </div>
        <div class="table-wrapper">
          <table>
            <thead><tr>
              <th><?= $isAr ? 'الفئة' : 'Category' ?></th>
              <th><?= $isAr ? 'المنتجات' : 'Products' ?></th>
              <th><?= $isAr ? 'الترتيب' : 'Order' ?></th>
              <th><?= $isAr ? 'الإجراءات' : 'Actions' ?></th>
            </tr></thead>
            <tbody>
            <?php if (empty($categories)): ?>
              <tr><td colspan="4" class="text-center text-muted" style="padding:30px;"><?= $isAr ? 'لا توجد فئات' : 'No categories yet' ?></td></tr>
            <?php else: ?>
              <?php foreach ($categories as $c): ?>
              <tr>
                <td>
                  <div style="font-weight:600;"><?= htmlspecialchars($isAr ? $c['name_ar'] : $c['name']) ?></div>
                  <div style="font-size:11px;color:#9ca3af;"><?= htmlspecialchars($isAr ? $c['name'] : $c['name_ar']) ?></div>
                </td>
                <td><span class="badge badge-blue"><?= $c['product_count'] ?></span></td>
                <td style="color:#6b7280;"><?= $c['sort_order'] ?></td>
                <td>
                  <div style="display:flex;gap:6px;">
                    <a href="categories.php?id=<?= $c['id'] ?>" class="btn btn-sm btn-outline">✏️</a>
                    <?php if ($c['product_count'] == 0): ?>
                    <form method="POST" style="display:inline;" onsubmit="return confirm('<?= $isAr ? 'حذف هذه الفئة؟' : 'Delete category?' ?>')">
                      <input type="hidden" name="action" value="delete">
                      <input type="hidden" name="id" value="<?= $c['id'] ?>">
                      <button type="submit" class="btn btn-sm btn-danger">🗑</button>
                    </form>
                    <?php endif; ?>
                  </div>
                </td>
              </tr>
              <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>

      <div class="card" style="position:sticky;top:76px;">
        <div class="card-header">
          <span class="card-title"><?= $editCat ? ($isAr ? 'تعديل فئة' : 'Edit Category') : ($isAr ? 'فئة جديدة' : 'New Category') ?></span>
          <?php if ($editCat): ?><a href="categories.php" class="btn btn-sm btn-outline"><?= $isAr ? 'إلغاء' : 'Cancel' ?></a><?php endif; ?>
        </div>
        <div class="card-body">
          <form method="POST">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id" value="<?= $editCat['id'] ?? 0 ?>">
            <div class="form-group">
              <label class="form-label">Name (EN) *</label>
              <input type="text" name="name" class="form-control" required value="<?= htmlspecialchars($editCat['name'] ?? '') ?>">
            </div>
            <div class="form-group">
              <label class="form-label">الاسم (AR) *</label>
              <input type="text" name="name_ar" class="form-control" dir="rtl" required value="<?= htmlspecialchars($editCat['name_ar'] ?? '') ?>">
            </div>
            <div class="form-row">
              <div class="form-group">
                <label class="form-label"><?= $isAr ? 'الأيقونة' : 'Icon' ?></label>
                <select name="icon" class="form-control">
                  <?php foreach (['droplet','flame','wind','flower','gift','tag','star','heart','box'] as $ic): ?>
                  <option value="<?= $ic ?>" <?= ($editCat['icon'] ?? '') === $ic ? 'selected' : '' ?>><?= $ic ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="form-group">
                <label class="form-label"><?= $isAr ? 'الترتيب' : 'Sort Order' ?></label>
                <input type="number" name="sort_order" class="form-control" value="<?= $editCat['sort_order'] ?? 0 ?>" min="0">
              </div>
            </div>
            <button type="submit" class="btn btn-primary btn-full">
              <?= $editCat ? ($isAr ? 'حفظ التعديلات' : 'Save Changes') : ($isAr ? 'إضافة الفئة' : 'Add Category') ?>
            </button>
          </form>
        </div>
      </div>
    </div>
  </div>
</div>
</div>
<script src="assets/js/main.js"></script>
</body></html>
