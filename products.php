<?php
require_once 'config.php';
requireLogin();
$isAr = isset($_SESSION['lang']) && $_SESSION['lang'] === 'ar';
$typeFilter = $_GET['type'] ?? '';
$action = $_GET['action'] ?? '';
$editId = (int)($_GET['id'] ?? 0);

$msg = '';
$msgType = 'success';

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pAction = $_POST['action'] ?? '';

    if ($pAction === 'save_product') {
        $id       = (int)($_POST['id'] ?? 0);
        $name     = $conn->real_escape_string(trim($_POST['name']));
        $nameAr   = $conn->real_escape_string(trim($_POST['name_ar']));
        $catId    = (int)$_POST['category_id'];
        $type     = in_array($_POST['type'], ['piece','weight']) ? $_POST['type'] : 'piece';
        $barcode  = $conn->real_escape_string(trim($_POST['barcode'] ?? ''));
        $basePrice= (float)$_POST['base_price'];
        $weightU  = in_array($_POST['weight_unit'] ?? 'gram', ['gram','tola']) ? $_POST['weight_unit'] : 'gram';
        $stock    = (float)$_POST['stock'];
        $threshold= (float)$_POST['low_stock_threshold'];
        $desc     = $conn->real_escape_string($_POST['description'] ?? '');
        $descAr   = $conn->real_escape_string($_POST['description_ar'] ?? '');
        $barcodeClause = $barcode ? "'$barcode'" : 'NULL';

        if ($id) {
            $conn->query("UPDATE products SET name='$name', name_ar='$nameAr', category_id=$catId, type='$type', barcode=$barcodeClause, base_price=$basePrice, weight_unit='$weightU', stock=$stock, low_stock_threshold=$threshold, description='$desc', description_ar='$descAr' WHERE id=$id");
            $msg = $isAr ? 'تم تحديث المنتج.' : 'Product updated.';
        } else {
            $conn->query("INSERT INTO products (name, name_ar, category_id, type, barcode, base_price, weight_unit, stock, low_stock_threshold, description, description_ar) VALUES ('$name','$nameAr',$catId,'$type',$barcodeClause,$basePrice,'$weightU',$stock,$threshold,'$desc','$descAr')");
            $id = $conn->insert_id;
            $msg = $isAr ? 'تمت إضافة المنتج.' : 'Product added.';
        }

        // Save sizes for piece type
        if ($type === 'piece' && isset($_POST['sizes']) && is_array($_POST['sizes'])) {
            $conn->query("DELETE FROM product_sizes WHERE product_id=$id");
            $sizeIndex = 0;
            foreach ($_POST['sizes'] as $i => $sz) {
                $szLabel  = $conn->real_escape_string(trim($sz['label'] ?? ''));
                $szBarcode = trim($sz['barcode'] ?? '');
                // Auto-generate unique barcode silently if left blank
                if (!$szBarcode) {
                    $szBarcode = 'P' . str_pad($id, 4, '0', STR_PAD_LEFT) . '-S' . ($sizeIndex + 1);
                }
                $szBarcode = $conn->real_escape_string($szBarcode);
                $szPrice   = (float)($sz['price'] ?? 0);
                $szStock   = (int)($sz['stock'] ?? 0);
                $szThresh  = (int)($sz['threshold'] ?? 5);
                if ($szLabel) {
                    $conn->query("INSERT INTO product_sizes (product_id, size_label, barcode, price, stock, low_stock_threshold, sort_order) VALUES ($id,'$szLabel','$szBarcode',$szPrice,$szStock,$szThresh,$sizeIndex)");
                    $sizeIndex++;
                }
            }
        }
        $qs = $typeFilter ? '?type='.$typeFilter.'&msg='.urlencode($msg) : '?msg='.urlencode($msg);
        header('Location: products.php' . $qs);
        exit;
    }

    if ($pAction === 'delete_product') {
        $id = (int)$_POST['id'];
        $conn->query("UPDATE products SET is_active=0 WHERE id=$id");
        $msg = $isAr ? 'تم حذف المنتج.' : 'Product deleted.';
        $qs = $typeFilter ? '?type='.$typeFilter.'&msg='.urlencode($msg) : '?msg='.urlencode($msg);
        header('Location: products.php' . $qs);
        exit;
    }
}

if (isset($_GET['msg'])) { $msg = $_GET['msg']; }

// Load categories
$rCats = $conn->query("SELECT * FROM categories WHERE is_active=1 ORDER BY name");
$categories = $rCats ? $rCats->fetch_all(MYSQLI_ASSOC) : [];

// Load for edit
$editProduct = null;
$editSizes = [];
if ($editId) {
    $r = $conn->query("SELECT * FROM products WHERE id=$editId LIMIT 1");
    $editProduct = $r ? $r->fetch_assoc() : null;
    $rSz = $conn->query("SELECT * FROM product_sizes WHERE product_id=$editId ORDER BY sort_order");
    $editSizes = $rSz ? $rSz->fetch_all(MYSQLI_ASSOC) : [];
    $action = 'add';
}

// Search + Pagination
$search  = trim($_GET['search'] ?? '');
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 25;
$offset  = ($page - 1) * $perPage;

// Load products list
$where = "WHERE p.is_active=1";
if ($typeFilter) $where .= " AND p.type='" . $conn->real_escape_string($typeFilter) . "'";
if ($search)     $where .= " AND (p.name LIKE '%" . $conn->real_escape_string($search) . "%' OR p.name_ar LIKE '%" . $conn->real_escape_string($search) . "%' OR p.barcode LIKE '%" . $conn->real_escape_string($search) . "%')";

$rTotal   = $conn->query("SELECT COUNT(*) as cnt FROM products p $where");
$totalRows = $rTotal ? (int)$rTotal->fetch_assoc()['cnt'] : 0;
$totalPages = max(1, ceil($totalRows / $perPage));

$rProds = $conn->query("
    SELECT p.*, c.name as cat_name, c.name_ar as cat_name_ar,
      (SELECT COUNT(*) FROM product_sizes WHERE product_id=p.id) as size_count,
      (SELECT SUM(stock) FROM product_sizes WHERE product_id=p.id) as total_size_stock,
      (SELECT GROUP_CONCAT(
        CONCAT(size_label,'||',COALESCE(barcode,''),'||',price,'||',stock,'||',low_stock_threshold)
        ORDER BY sort_order SEPARATOR ';;'
      ) FROM product_sizes WHERE product_id=p.id) as size_details
    FROM products p
    LEFT JOIN categories c ON c.id=p.category_id
    $where ORDER BY p.name ASC
    LIMIT $perPage OFFSET $offset
");
$products = $rProds ? $rProds->fetch_all(MYSQLI_ASSOC) : [];

$pageTitle = $isAr ? 'المنتجات' : 'Products';
include 'includes/head.php';
?>
<div class="app-layout">
<?php include 'includes/sidebar.php'; ?>
<div class="main-content">
  <div class="topbar">
    <div class="topbar-title"><?= $isAr ? 'إدارة المنتجات' : 'Products Management' ?></div>
    <div class="topbar-right">
      <a href="lang.php?lang=<?= $isAr ? 'en' : 'ar' ?>" class="lang-btn"><?= $isAr ? 'EN' : 'ع' ?></a>
      <a href="products.php?action=add<?= $typeFilter ? '&type='.$typeFilter : '' ?>" class="btn btn-primary btn-sm">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        <?= $isAr ? 'منتج جديد' : 'New Product' ?>
      </a>
    </div>
  </div>
  <div class="page-content">
    <?php if ($msg): ?>
    <div class="alert alert-<?= $msgType ?>"><?= htmlspecialchars($msg) ?></div>
    <?php endif; ?>

    <div style="display:grid;grid-template-columns:<?= ($action === 'add') ? '1fr 420px' : '1fr' ?>;gap:20px;align-items:start;">

      <!-- Products List -->
      <div class="card">
        <div class="card-header" style="flex-wrap:wrap;gap:8px;">
          <span class="card-title">
            <?= $typeFilter === 'piece' ? ($isAr ? 'العطور' : 'Perfumes') : ($typeFilter === 'weight' ? ($isAr ? 'البخور' : 'Bakhoor') : ($isAr ? 'كل المنتجات' : 'All Products')) ?>
            <span class="badge badge-blue" style="margin-<?= $isAr ? 'right' : 'left' ?>:8px;"><?= $totalRows ?></span>
          </span>
          <form method="GET" style="display:flex;gap:6px;align-items:center;flex:1;max-width:280px;" action="products.php">
            <?php if ($typeFilter): ?><input type="hidden" name="type" value="<?= htmlspecialchars($typeFilter) ?>"><?php endif; ?>
            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="<?= $isAr?'بحث بالاسم أو باركود...':'Search name or barcode...' ?>" class="form-control" style="font-size:12px;padding:5px 10px;">
            <button type="submit" class="btn btn-sm btn-outline" style="white-space:nowrap;">&#128269;</button>
            <?php if ($search): ?><a href="products.php<?= $typeFilter?'?type='.$typeFilter:'' ?>" class="btn btn-sm btn-outline" style="white-space:nowrap;">×</a><?php endif; ?>
          </form>
          <div style="display:flex;gap:6px;">
            <a href="products.php<?= $search?'?search='.urlencode($search):'' ?>" class="btn btn-sm <?= !$typeFilter ? 'btn-primary' : 'btn-outline' ?>"><?= $isAr ? 'الكل' : 'All' ?></a>
            <a href="products.php?type=piece<?= $search?'&search='.urlencode($search):'' ?>" class="btn btn-sm <?= $typeFilter === 'piece' ? 'btn-primary' : 'btn-outline' ?>"><?= $isAr ? 'قطعة' : 'Piece' ?></a>
            <a href="products.php?type=weight<?= $search?'&search='.urlencode($search):'' ?>" class="btn btn-sm <?= $typeFilter === 'weight' ? 'btn-primary' : 'btn-outline' ?>"><?= $isAr ? 'وزن' : 'Weight' ?></a>
          </div>
        </div>
        <div class="table-wrapper">
          <table>
            <thead><tr>
              <th><?= $isAr ? 'المنتج' : 'Product' ?></th>
              <th><?= $isAr ? 'الفئة' : 'Category' ?></th>
              <th><?= $isAr ? 'النوع' : 'Type' ?></th>
              <th><?= $isAr ? 'الأحجام — الباركود / السعر / المخزون' : 'Sizes — Barcode / Price / Stock' ?></th>
              <th><?= $isAr ? 'الإجراءات' : 'Actions' ?></th>
            </tr></thead>
            <tbody>
            <?php if (empty($products)): ?>
              <tr><td colspan="7" class="text-center text-muted" style="padding:30px;">
                <?= $isAr ? 'لا توجد منتجات. أضف منتجاً من الزر أعلاه.' : 'No products. Add one using the button above.' ?>
              </td></tr>
            <?php else: ?>
              <?php foreach ($products as $p):
                // Parse size_details string into array
                $parsedSizes = [];
                if ($p['size_details']) {
                    foreach (explode(';;', $p['size_details']) as $sd) {
                        $parts = explode('||', $sd);
                        if (count($parts) >= 5) {
                            $parsedSizes[] = [
                                'label'     => $parts[0],
                                'barcode'   => $parts[1],
                                'price'     => (float)$parts[2],
                                'stock'     => (int)$parts[3],
                                'threshold' => (int)$parts[4],
                            ];
                        }
                    }
                }
                // Low stock check
                $isLow = false;
                if ($p['type'] === 'piece') {
                    foreach ($parsedSizes as $ps) { if ($ps['stock'] <= $ps['threshold']) { $isLow = true; break; } }
                } else {
                    $isLow = (float)$p['stock'] <= (float)$p['low_stock_threshold'];
                }
              ?>
              <tr>
                <td>
                  <div style="font-weight:600;"><?= htmlspecialchars($isAr ? $p['name_ar'] : $p['name']) ?></div>
                  <div style="font-size:11px;color:#9ca3af;"><?= htmlspecialchars($isAr ? $p['name'] : $p['name_ar']) ?></div>
                </td>
                <td style="color:#6b7280;font-size:12px;"><?= htmlspecialchars($isAr ? ($p['cat_name_ar'] ?? '—') : ($p['cat_name'] ?? '—')) ?></td>
                <td><span class="type-pill <?= $p['type'] === 'piece' ? 'type-piece' : 'type-weight' ?>"><?= $p['type'] === 'piece' ? ($isAr?'قطعة':'piece') : ($isAr?'وزن':'weight') ?></span></td>
                <td>
                  <?php if ($p['type'] === 'weight'): ?>
                    <div style="font-size:12px;">
                      <div style="font-family:monospace;color:#6b7280;"><?= htmlspecialchars($p['barcode'] ?? '—') ?></div>
                      <div style="font-weight:700;color:#2563eb;margin-top:2px;"><?= number_format($p['base_price'],3) ?> KD / <?= $p['weight_unit'] === 'tola' ? ($isAr?'تولة':'tola') : ($isAr?'غ':'g') ?></div>
                      <div><?php
                        $wStk = (float)$p['stock'];
                        $wLow = $wStk <= (float)$p['low_stock_threshold'];
                      ?><span class="badge <?= $wLow ? 'badge-red' : 'badge-green' ?>" style="font-size:11px;"><?= number_format($wStk,1) ?> <?= $p['weight_unit'] === 'tola' ? ($isAr?'تولة':'tola') : ($isAr?'غ':'g') ?></span></div>
                    </div>
                  <?php elseif (!empty($parsedSizes)): ?>
                    <table style="width:100%;border-collapse:collapse;font-size:11px;">
                      <thead><tr style="border-bottom:1px solid #e5e7eb;">
                        <th style="padding:2px 6px 4px 0;color:#9ca3af;font-weight:600;text-align:left;"><?= $isAr?'الحجم':'Size' ?></th>
                        <th style="padding:2px 6px 4px;color:#9ca3af;font-weight:600;text-align:left;"><?= $isAr?'الباركود':'Barcode' ?></th>
                        <th style="padding:2px 6px 4px;color:#9ca3af;font-weight:600;text-align:right;"><?= $isAr?'السعر':'Price' ?></th>
                        <th style="padding:2px 0 4px 6px;color:#9ca3af;font-weight:600;text-align:right;"><?= $isAr?'المخزون':'Stock' ?></th>
                      </tr></thead>
                      <tbody>
                      <?php foreach ($parsedSizes as $ps):
                        $sLow = $ps['stock'] <= $ps['threshold'];
                      ?>
                      <tr style="border-bottom:1px solid #f3f4f6;">
                        <td style="padding:3px 6px 3px 0;font-weight:700;color:#1f2937;"><?= htmlspecialchars($ps['label']) ?></td>
                        <td style="padding:3px 6px;font-family:monospace;color:#6b7280;font-size:10px;"><?= htmlspecialchars($ps['barcode']) ?></td>
                        <td style="padding:3px 6px;font-weight:700;color:#2563eb;text-align:right;"><?= number_format($ps['price'],3) ?> KD</td>
                        <td style="padding:3px 0 3px 6px;text-align:right;">
                          <span style="font-weight:700;color:<?= $sLow ? '#dc2626' : '#16a34a' ?>;"><?= $ps['stock'] ?> <?= $isAr?'قط':'pcs' ?></span>
                          <?php if ($sLow): ?><span style="color:#dc2626;font-size:9px;margin-left:2px;">&#9660;</span><?php endif; ?>
                        </td>
                      </tr>
                      <?php endforeach; ?>
                      </tbody>
                    </table>
                  <?php else: ?>
                    <span style="color:#9ca3af;font-size:12px;"><?= $isAr?'لا أحجام':'No sizes' ?></span>
                  <?php endif; ?>
                </td>
                <td>
                  <div style="display:flex;gap:6px;">
                    <a href="products.php?action=add&id=<?= $p['id'] ?><?= $typeFilter ? '&type='.$typeFilter : '' ?>" class="btn btn-sm btn-outline">✏️</a>
                    <form method="POST" style="display:inline;" onsubmit="return confirm('<?= $isAr ? 'هل أنت متأكد؟' : 'Delete this product?' ?>')">
                      <input type="hidden" name="action" value="delete_product">
                      <input type="hidden" name="id" value="<?= $p['id'] ?>">
                      <button type="submit" class="btn btn-sm btn-danger">🗑</button>
                    </form>
                    <a href="barcode_print.php?id=<?= $p['id'] ?>" target="_blank" class="btn btn-sm btn-outline" title="Print Barcode">🔖</a>
                  </div>
                </td>
              </tr>
              <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
          </table>
        </div>
        <?php if ($totalPages > 1): ?>
        <div style="display:flex;align-items:center;justify-content:space-between;padding:12px 16px;border-top:1px solid #f3f4f6;">
          <div style="font-size:12px;color:#6b7280;">
            <?php
            $from = $offset + 1;
            $to   = min($offset + $perPage, $totalRows);
            echo $isAr ? "عرض $from–$to من $totalRows منتج" : "Showing $from–$to of $totalRows products";
            ?>
          </div>
          <div style="display:flex;gap:4px;">
            <?php
            $baseQs = ($typeFilter ? 'type='.$typeFilter.'&' : '') . ($search ? 'search='.urlencode($search).'&' : '');
            ?>
            <a href="?<?= $baseQs ?>page=<?= max(1,$page-1) ?>" class="btn btn-sm btn-outline" <?= $page<=1 ? 'style="opacity:.4;pointer-events:none;"' : '' ?>>‹</a>
            <?php for ($pg = max(1,$page-2); $pg <= min($totalPages,$page+2); $pg++): ?>
            <a href="?<?= $baseQs ?>page=<?= $pg ?>" class="btn btn-sm <?= $pg===$page ? 'btn-primary' : 'btn-outline' ?>"><?= $pg ?></a>
            <?php endfor; ?>
            <a href="?<?= $baseQs ?>page=<?= min($totalPages,$page+1) ?>" class="btn btn-sm btn-outline" <?= $page>=$totalPages ? 'style="opacity:.4;pointer-events:none;"' : '' ?>>›</a>
          </div>
        </div>
        <?php endif; ?>
      </div>

      <!-- Add/Edit Form -->
      <?php if ($action === 'add'): ?>
      <div class="card" style="position:sticky;top:76px;">
        <div class="card-header">
          <span class="card-title"><?= $editProduct ? ($isAr ? 'تعديل منتج' : 'Edit Product') : ($isAr ? 'منتج جديد' : 'New Product') ?></span>
          <?php if ($editProduct): ?><a href="products.php<?= $typeFilter ? '?type='.$typeFilter : '' ?>" class="btn btn-sm btn-outline"><?= $isAr ? 'إلغاء' : 'Cancel' ?></a><?php endif; ?>
        </div>
        <div class="card-body" style="overflow-y:auto;max-height:calc(100vh - 160px);">
          <form method="POST" id="productForm">
            <input type="hidden" name="action" value="save_product">
            <input type="hidden" name="id" value="<?= $editProduct['id'] ?? 0 ?>">

            <div class="form-row">
              <div class="form-group">
                <label class="form-label">Name (EN) *</label>
                <input type="text" name="name" class="form-control" required value="<?= htmlspecialchars($editProduct['name'] ?? '') ?>">
              </div>
              <div class="form-group">
                <label class="form-label">الاسم (AR) *</label>
                <input type="text" name="name_ar" class="form-control" dir="rtl" required value="<?= htmlspecialchars($editProduct['name_ar'] ?? '') ?>">
              </div>
            </div>

            <div class="form-row">
              <div class="form-group">
                <label class="form-label"><?= $isAr ? 'الفئة' : 'Category' ?></label>
                <select name="category_id" class="form-control">
                  <option value="0"><?= $isAr ? 'بدون فئة' : 'No Category' ?></option>
                  <?php foreach ($categories as $cat): ?>
                  <option value="<?= $cat['id'] ?>" <?= ($editProduct['category_id'] ?? 0) == $cat['id'] ? 'selected' : '' ?>><?= htmlspecialchars($isAr ? $cat['name_ar'] : $cat['name']) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="form-group">
                <label class="form-label"><?= $isAr ? 'نوع البيع' : 'Sale Type' ?></label>
                <select name="type" class="form-control" id="typeSelect" onchange="toggleType()">
                  <option value="piece" <?= ($editProduct['type'] ?? $typeFilter) === 'piece' ? 'selected' : '' ?>><?= $isAr ? 'بالقطعة' : 'By Piece' ?></option>
                  <option value="weight" <?= ($editProduct['type'] ?? '') === 'weight' ? 'selected' : '' ?>><?= $isAr ? 'بالوزن' : 'By Weight' ?></option>
                </select>
              </div>
            </div>

            <div class="form-group">
              <label class="form-label"><?= $isAr ? 'الباركود (رئيسي)' : 'Barcode (Main)' ?></label>
              <input type="text" name="barcode" class="form-control" style="font-family:monospace;" value="<?= htmlspecialchars($editProduct['barcode'] ?? '') ?>" placeholder="<?= $isAr ? 'اختياري' : 'Optional' ?>">
            </div>

            <!-- Weight fields -->
            <div id="weightFields" style="display:none;">
              <div class="form-row">
                <div class="form-group">
                  <label class="form-label"><?= $isAr ? 'السعر لكل وحدة' : 'Price per Unit' ?></label>
                  <input type="number" name="base_price" class="form-control" step="0.001" min="0" value="<?= $editProduct['base_price'] ?? '' ?>" placeholder="0.000">
                </div>
                <div class="form-group">
                  <label class="form-label"><?= $isAr ? 'وحدة الوزن' : 'Weight Unit' ?></label>
                  <select name="weight_unit" class="form-control">
                    <option value="gram" <?= ($editProduct['weight_unit'] ?? '') === 'gram' ? 'selected' : '' ?>><?= $isAr ? 'غرام' : 'Gram' ?></option>
                    <option value="tola" <?= ($editProduct['weight_unit'] ?? '') === 'tola' ? 'selected' : '' ?>><?= $isAr ? 'تولة' : 'Tola' ?></option>
                  </select>
                </div>
              </div>
              <div class="form-row">
                <div class="form-group">
                  <label class="form-label"><?= $isAr ? 'المخزون الحالي' : 'Current Stock' ?></label>
                  <input type="number" name="stock" class="form-control" step="0.1" min="0" value="<?= $editProduct['stock'] ?? 0 ?>">
                </div>
                <div class="form-group">
                  <label class="form-label"><?= $isAr ? 'حد التنبيه' : 'Low Stock Threshold' ?></label>
                  <input type="number" name="low_stock_threshold" class="form-control" step="0.1" min="0" value="<?= $editProduct['low_stock_threshold'] ?? 10 ?>">
                </div>
              </div>
            </div>

            <!-- Piece / Sizes -->
            <div id="pieceFields">
              <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
                <label class="form-label mb-0"><?= $isAr ? 'الأحجام والأسعار والمخزون' : 'Sizes, Prices & Stock' ?></label>
                <button type="button" class="btn btn-sm btn-outline" onclick="addSizeRow()"><?= $isAr ? '+ حجم' : '+ Size' ?></button>
              </div>
              <div id="sizesContainer">
                <?php
                $sizeRowsData = !empty($editSizes)
                    ? array_map(fn($s) => ['label'=>$s['size_label'],'price'=>$s['price'],'barcode'=>$s['barcode']??'','stock'=>(int)$s['stock'],'threshold'=>(int)($s['low_stock_threshold']??5)], $editSizes)
                    : [['label'=>'50ml','price'=>'','barcode'=>'','stock'=>0,'threshold'=>5],['label'=>'100ml','price'=>'','barcode'=>'','stock'=>0,'threshold'=>5],['label'=>'150ml','price'=>'','barcode'=>'','stock'=>0,'threshold'=>5]];
                ?>
                <?php foreach ($sizeRowsData as $i => $sz): ?>
                <div class="size-row" style="border:1px solid #e5e7eb;border-radius:10px;margin-bottom:10px;overflow:hidden;">
                  <!-- Size label header -->
                  <div style="display:flex;align-items:center;gap:8px;padding:8px 10px;background:#f9fafb;border-bottom:1px solid #e5e7eb;">
                    <span style="font-size:11px;font-weight:700;color:#6b7280;white-space:nowrap;"><?= $isAr?'الحجم:':'Size:' ?></span>
                    <input type="text" name="sizes[<?= $i ?>][label]" class="form-control" placeholder="<?= $isAr?'مثل: 100ml':'e.g. 50ml' ?>" value="<?= htmlspecialchars($sz['label']) ?>" style="font-size:14px;font-weight:800;color:#1d4ed8;border-color:#bfdbfe;background:#eff6ff;max-width:120px;">
                    <button type="button" class="btn btn-sm btn-danger" onclick="this.closest('.size-row').remove()" style="margin-left:auto;padding:4px 10px;"><?= $isAr?'حذف':'Remove' ?></button>
                  </div>
                  <!-- Fields grid -->
                  <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;padding:10px;">
                    <div>
                      <label style="font-size:11px;font-weight:700;color:#6b7280;display:block;margin-bottom:3px;"><?= $isAr?'السعر (KD)':'Price (KD)' ?></label>
                      <input type="number" name="sizes[<?= $i ?>][price]" class="form-control" placeholder="0.000" step="0.001" min="0" value="<?= $sz['price'] ?>" style="font-size:13px;font-weight:700;">
                    </div>
                    <div>
                      <label style="font-size:11px;font-weight:700;color:#6b7280;display:block;margin-bottom:3px;"><?= $isAr?'الباركود (اختياري)':'Barcode (optional)' ?></label>
                      <input type="text" name="sizes[<?= $i ?>][barcode]" class="form-control" placeholder="<?= $isAr?'تلقائي إذا تركت فارغًا':'auto if empty' ?>" value="<?= htmlspecialchars($sz['barcode']) ?>" style="font-family:monospace;font-size:11px;">
                    </div>
                    <div>
                      <label style="font-size:11px;font-weight:700;color:#2563eb;display:block;margin-bottom:3px;"><?= $isAr?'المخزون (قطعة)':'Stock (pcs)' ?></label>
                      <input type="number" name="sizes[<?= $i ?>][stock]" class="form-control" placeholder="0" value="<?= $sz['stock'] ?>" style="font-size:16px;font-weight:800;border-color:#bfdbfe;background:#eff6ff;">
                    </div>
                    <div>
                      <label style="font-size:11px;font-weight:700;color:#9ca3af;display:block;margin-bottom:3px;"><?= $isAr?'تنبيه مخزون منخفض':'Low Stock Alert' ?></label>
                      <input type="number" name="sizes[<?= $i ?>][threshold]" class="form-control" placeholder="5" value="<?= $sz['threshold'] ?>" style="font-size:13px;">
                    </div>
                  </div>
                </div>
                <?php endforeach; ?>
              </div>
              <div style="font-size:11px;color:#6b7280;padding:6px 4px;background:#f0f9ff;border-radius:6px;margin-top:4px;">
                ℹ️ <strong><?= $isAr?'المخزون':'Stock' ?>:</strong> <?= $isAr?'أدخل عدد القطع لكل حجم. سيتم خصمها تلقائياً عند كل بيع.':'Enter quantity per size. Stock auto-decreases on every sale.' ?>
                &nbsp;|&nbsp; <strong><?= $isAr?'التنبيه':'Alert' ?>:</strong> <?= $isAr?'تنبيه عند الوصول لهذا الحد':'Alert when stock reaches this number' ?>
              </div>
            </div>

            <button type="submit" class="btn btn-primary btn-full" style="margin-top:16px;">
              <?= $editProduct ? ($isAr ? 'حفظ التعديلات' : 'Save Changes') : ($isAr ? 'إضافة المنتج' : 'Add Product') ?>
            </button>
          </form>
        </div>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>
</div>
<script>
let sizeIdx = <?= count($editSizes) ?: 3 ?>;
function addSizeRow() {
    const c = document.getElementById('sizesContainer');
    const d = document.createElement('div');
    d.className = 'size-row';
    d.style.cssText = 'border:1px solid #e5e7eb;border-radius:10px;margin-bottom:10px;overflow:hidden;';
    d.innerHTML = `
        <div style="display:flex;align-items:center;gap:8px;padding:8px 10px;background:#f9fafb;border-bottom:1px solid #e5e7eb;">
            <span style="font-size:11px;font-weight:700;color:#6b7280;white-space:nowrap;">Size:</span>
            <input type="text" name="sizes[${sizeIdx}][label]" class="form-control" placeholder="e.g. 200ml" style="font-size:14px;font-weight:800;color:#1d4ed8;border-color:#bfdbfe;background:#eff6ff;max-width:120px;">
            <button type="button" class="btn btn-sm btn-danger" onclick="this.closest('.size-row').remove()" style="margin-left:auto;padding:4px 10px;">Remove</button>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;padding:10px;">
            <div>
                <label style="font-size:11px;font-weight:700;color:#6b7280;display:block;margin-bottom:3px;">Price (KD)</label>
                <input type="number" name="sizes[${sizeIdx}][price]" class="form-control" placeholder="0.000" step="0.001" min="0" style="font-size:13px;font-weight:700;">
            </div>
            <div>
                <label style="font-size:11px;font-weight:700;color:#6b7280;display:block;margin-bottom:3px;">Barcode (optional)</label>
                <input type="text" name="sizes[${sizeIdx}][barcode]" class="form-control" placeholder="auto if empty" style="font-family:monospace;font-size:11px;">
            </div>
            <div>
                <label style="font-size:11px;font-weight:700;color:#2563eb;display:block;margin-bottom:3px;">Stock (pcs)</label>
                <input type="number" name="sizes[${sizeIdx}][stock]" class="form-control" placeholder="0" value="0" style="font-size:16px;font-weight:800;border-color:#bfdbfe;background:#eff6ff;">
            </div>
            <div>
                <label style="font-size:11px;font-weight:700;color:#9ca3af;display:block;margin-bottom:3px;">Low Stock Alert</label>
                <input type="number" name="sizes[${sizeIdx}][threshold]" class="form-control" placeholder="5" value="5" style="font-size:13px;">
            </div>
        </div>
    `;
    c.appendChild(d);
    sizeIdx++;
}
function toggleType() {
    const t = document.getElementById('typeSelect').value;
    document.getElementById('weightFields').style.display = t === 'weight' ? '' : 'none';
    document.getElementById('pieceFields').style.display  = t === 'piece'  ? '' : 'none';
}
toggleType();
</script>
<script src="assets/js/main.js"></script>
</body>
</html>
