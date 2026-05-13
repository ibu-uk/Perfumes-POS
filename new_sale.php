<?php
require_once 'config.php';
requireLogin();
$isAr = isset($_SESSION['lang']) && $_SESSION['lang'] === 'ar';

// Load categories
$rCats = $conn->query("SELECT * FROM categories WHERE parent_id IS NULL AND is_active=1 ORDER BY sort_order, name");
$categories = $rCats ? $rCats->fetch_all(MYSQLI_ASSOC) : [];

// Load products for JS
$rProds = $conn->query("
    SELECT p.id, p.name, p.name_ar, p.type, p.base_price, p.weight_unit, p.stock, p.low_stock_threshold, p.barcode, p.image, c.name as cat_name, c.name_ar as cat_name_ar, c.id as cat_id
    FROM products p
    LEFT JOIN categories c ON c.id=p.category_id
    WHERE p.is_active=1 ORDER BY p.name
");
$products = $rProds ? $rProds->fetch_all(MYSQLI_ASSOC) : [];

// Load product sizes for JS
$rSizes = $conn->query("SELECT * FROM product_sizes ORDER BY product_id, sort_order");
$sizesAll = $rSizes ? $rSizes->fetch_all(MYSQLI_ASSOC) : [];
$sizesByProduct = [];
foreach ($sizesAll as $s) $sizesByProduct[$s['product_id']][] = $s;

// Attach sizes to products
foreach ($products as &$p) {
    $p['sizes'] = $sizesByProduct[$p['id']] ?? [];
}
unset($p);

// Load active promotions
$now = date('Y-m-d');
$rPromos = $conn->query("
    SELECT p.*, pp.product_id, pp.product_size_id
    FROM promotions p
    JOIN promotion_products pp ON pp.promotion_id = p.id
    WHERE p.is_active = 1 
    AND p.start_date <= '$now' 
    AND p.end_date >= '$now'
");
$promos = $rPromos ? $rPromos->fetch_all(MYSQLI_ASSOC) : [];
$promosByProduct = [];
$promosBySize = [];
foreach ($promos as $promo) {
    if ($promo['product_size_id']) {
        // Size-level promo
        $promosBySize[$promo['product_size_id']] = [
            'id' => $promo['id'],
            'name' => $promo['name'],
            'discount_type' => $promo['discount_type'],
            'discount_value' => $promo['discount_value']
        ];
    } else {
        // Product-level promo
        $promosByProduct[$promo['product_id']] = [
            'id' => $promo['id'],
            'name' => $promo['name'],
            'discount_type' => $promo['discount_type'],
            'discount_value' => $promo['discount_value']
        ];
    }
}

// Attach promotions to products and sizes
foreach ($products as &$p) {
    // Check size-level promo first, then product-level
    $p['promotion'] = null;
}
foreach ($sizesAll as &$s) {
    $s['promotion'] = $promosBySize[$s['id']] ?? null;
    // Also attach to parent product if no size-level promo but product-level promo exists
    if (!$s['promotion'] && isset($promosByProduct[$s['product_id']])) {
        $s['promotion'] = $promosByProduct[$s['product_id']];
    }
}
// Re-attach sizes to products
$sizesByProduct = [];
foreach ($sizesAll as $s) $sizesByProduct[$s['product_id']][] = $s;
foreach ($products as &$p) {
    $p['sizes'] = $sizesByProduct[$p['id']] ?? [];
    // Product-level promo (for weight products or when no specific size promo)
    if (!isset($promosByProduct[$p['id']])) {
        $p['promotion'] = null;
    } else {
        $p['promotion'] = $promosByProduct[$p['id']];
    }
}
unset($p);
unset($s);

$pageTitle = $isAr ? 'بيع جديد' : 'New Sale';
include 'includes/head.php';
?>
<style>
.pos-layout { display:grid; grid-template-columns:1fr 380px; gap:16px; height:calc(100vh - 60px - 32px); }
.pos-products-area { display:flex; flex-direction:column; overflow:hidden; }
.pos-right { display:flex; flex-direction:column; overflow:hidden; }
.cart-items-area { flex:1; overflow-y:auto; padding:10px; }
.payment-section { padding:14px; border-top:1px solid #e5e7eb; }
.method-btns { display:grid; grid-template-columns:repeat(3,1fr); gap:6px; margin-bottom:12px; }
.method-btn {
  padding:8px 4px; border:2px solid #e5e7eb; border-radius:8px;
  background:#fff; font-size:12px; font-weight:600; cursor:pointer; text-align:center; transition:all .15s;
}
.method-btn.active { border-color:#2563eb; background:#eff6ff; color:#2563eb; }
.numpad { display:grid; grid-template-columns:repeat(3,1fr); gap:6px; margin-top:10px; }
.num-btn {
  padding:12px; border:1px solid #e5e7eb; border-radius:8px; background:#fff;
  font-size:16px; font-weight:600; cursor:pointer; text-align:center; transition:background .1s;
}
.num-btn:hover { background:#f9fafb; }
.num-btn.del { background:#fee2e2; color:#dc2626; border-color:#fca5a5; }
.num-btn.zero { grid-column:span 2; }
</style>
<div class="app-layout">
<?php include 'includes/sidebar.php'; ?>
<div class="main-content">
  <div class="topbar">
    <div class="topbar-title"><?= $isAr ? 'بيع جديد' : 'New Sale' ?></div>
    <div class="topbar-right">
      <a href="lang.php?lang=<?= $isAr ? 'en' : 'ar' ?>" class="lang-btn"><?= $isAr ? 'EN' : 'ع' ?></a>
    </div>
  </div>
  <div class="page-content" style="padding:16px;">
    <div class="pos-layout">

      <!-- LEFT: Products -->
      <div class="pos-products-area card" style="overflow:hidden;display:flex;flex-direction:column;">
        <div style="padding:12px;border-bottom:1px solid #f3f4f6;">
          <div class="product-search-bar">
            <input type="text" id="productSearch" class="form-control" placeholder="<?= $isAr ? 'بحث عن منتج...' : 'Search product...' ?>" oninput="filterProducts()">
            <select id="catFilter" class="form-control" style="max-width:160px;" onchange="filterProducts()">
              <option value=""><?= $isAr ? 'كل الفئات' : 'All Categories' ?></option>
              <?php foreach ($categories as $cat): ?>
              <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($isAr ? $cat['name_ar'] : $cat['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="category-tabs" id="catTabs">
            <button class="cat-tab active" onclick="setCatFilter('', this)"><?= $isAr ? 'الكل' : 'All' ?></button>
            <button class="cat-tab" onclick="setCatFilter('piece', this)" style="background:#eff6ff;color:#2563eb;border-color:#bfdbfe;"><?= $isAr ? 'عطور' : 'Perfumes' ?></button>
            <button class="cat-tab" onclick="setCatFilter('weight', this)" style="background:#fdf2f8;color:#9333ea;border-color:#d8b4fe;"><?= $isAr ? 'بخور' : 'Bakhoor' ?></button>
            <?php foreach ($categories as $cat): ?>
            <?php if (strtolower($cat['name']) === 'bakhoor' || strtolower($cat['name_ar']) === 'بخور') continue; ?>
            <button class="cat-tab" onclick="setCatFilter('cat_<?= $cat['id'] ?>', this)"><?= htmlspecialchars($isAr ? $cat['name_ar'] : $cat['name']) ?></button>
            <?php endforeach; ?>
          </div>
        </div>
        <div id="productGrid" class="product-grid" style="flex:1;overflow-y:auto;padding:14px;align-content:start;"></div>
      </div>

      <!-- RIGHT: Cart + Panel -->
      <div class="pos-right card">
        <div class="tab-bar">
          <button class="tab-btn active" id="tabCart" onclick="switchTab('cart')"><?= $isAr ? 'السلة' : 'Cart' ?></button>
          <button class="tab-btn" id="tabBarcode" onclick="switchTab('barcode')"><?= $isAr ? 'الباركود' : 'Barcode' ?></button>
          <button class="tab-btn" id="tabRecent" onclick="switchTab('recent')"><?= $isAr ? 'الأخيرة' : 'Recent' ?></button>
        </div>

        <!-- Cart Tab -->
        <div id="cartTab" style="flex:1;display:flex;flex-direction:column;overflow:hidden;">
          <div class="cart-items-area" id="cartItems">
            <div class="cart-empty" id="cartEmpty">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
              <div><?= $isAr ? 'السلة فارغة' : 'Cart is empty' ?></div>
              <div style="font-size:11px;margin-top:4px;"><?= $isAr ? 'اختر منتجاً للبدء' : 'Select a product to start' ?></div>
            </div>
          </div>
          <!-- Customer & discount -->
          <div style="padding:0 10px 6px;border-top:1px solid #f3f4f6;">
            <div style="padding-top:8px;position:relative;">
              <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:6px;">
                <div style="position:relative;">
                  <input type="tel" id="custPhone" class="form-control" style="font-size:12px;" placeholder="<?= $isAr ? 'رقم الجوال' : 'Mobile No.' ?>" autocomplete="off" oninput="searchCustomer()">
                  <div id="custDropdown" style="display:none;position:absolute;top:100%;left:0;right:0;background:#fff;border:1px solid #e5e7eb;border-radius:6px;box-shadow:0 4px 12px rgba(0,0,0,0.1);z-index:999;max-height:160px;overflow-y:auto;"></div>
                </div>
                <input type="text" id="custName" class="form-control" style="font-size:12px;" placeholder="<?= $isAr ? 'اسم العميل' : 'Customer Name' ?>">
              </div>
              <div id="custPointsBadge" style="display:none;padding:6px 10px;background:#fef9c3;border-radius:6px;font-size:11px;margin-bottom:4px;display:none;align-items:center;justify-content:space-between;">
                <span style="font-weight:700;color:#854d0e;">★ <span id="custPointsVal">0</span> <?= $isAr ? 'نقطة' : 'pts' ?> = <span id="custPointsKd">0.000</span> KD</span>
                <label style="display:flex;align-items:center;gap:5px;cursor:pointer;font-weight:600;color:#1d4ed8;font-size:11px;">
                  <input type="checkbox" id="redeemPoints" onchange="applyPointsRedeem()" style="width:14px;height:14px;">
                  <?= $isAr ? 'استبدال' : 'Redeem' ?>
                </label>
              </div>
              <input type="hidden" id="custId" value="">
              <input type="hidden" id="custPointsEnabled" value="1">
              <div style="display:flex;gap:4px;">
                <input type="number" id="discountAmt" class="form-control" style="font-size:12px;" placeholder="<?= $isAr ? 'خصم' : 'Disc.' ?>" min="0" step="0.001" oninput="recalcCart()">
                <span style="display:flex;align-items:center;font-size:12px;font-weight:600;color:#6b7280;padding:0 8px;">KD</span>
              </div>
            </div>
          </div>
          <div class="payment-section">
            <div class="cart-total-row"><span><?= $isAr ? 'المجموع الفرعي' : 'Subtotal' ?></span><span id="subtotalDisplay">0.000 KD</span></div>
            <div class="cart-total-row" id="discountRow"><span><?= $isAr ? 'الخصم' : 'Discount' ?></span><span id="discountDisplay" style="color:#dc2626;">- 0.000 KD</span></div>
            <div class="cart-total-row grand"><span><?= $isAr ? 'الإجمالي' : 'Total' ?></span><span id="totalDisplay">0.000 KD</span></div>
            <div class="method-btns mt-16">
              <button class="method-btn active" data-method="cash" onclick="selectMethod('cash')"><?= $isAr ? 'نقد' : 'Cash' ?></button>
              <button class="method-btn" data-method="knet" onclick="selectMethod('knet')">KNET</button>
              <button class="method-btn" data-method="wamt" onclick="selectMethod('wamt')">WAMT</button>
            </div>
            <button class="btn btn-success btn-full btn-lg" onclick="completeSale()" id="checkoutBtn">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:18px;height:18px;"><polyline points="20 6 9 17 4 12"/></svg>
              <?= $isAr ? 'إتمام البيع' : 'Complete Sale' ?>
            </button>
          </div>
        </div>

        <!-- Barcode Tab -->
        <div id="barcodeTab" style="flex:1;padding:20px;display:none;">
          <div class="barcode-input-wrap mb-16">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 5v14M7 5v14M11 5v14M15 5v14M19 5v14M2 3h20M2 21h20"/></svg>
            <input type="text" id="barcodeInput" class="form-control barcode-input" placeholder="<?= $isAr ? 'امسح الباركود...' : 'Scan barcode...' ?>" autofocus>
          </div>
          <div id="barcodeResult" style="margin-top:16px;"></div>
          <div style="margin-top:20px;padding:14px;background:#f9fafb;border-radius:8px;font-size:12px;color:#6b7280;text-align:center;">
            <div style="font-size:24px;margin-bottom:6px;">📷</div>
            <?= $isAr ? 'وجّه الماسح نحو الباركود أو أدخله يدوياً' : 'Point scanner at barcode or type manually' ?>
          </div>
        </div>

        <!-- Recent Tab -->
        <div id="recentTab" style="flex:1;overflow-y:auto;display:none;" id="recentSalesTab">
          <div id="recentSalesList" style="padding:10px;"></div>
        </div>
      </div>
    </div>
  </div>
</div>
</div>

<!-- Size Selection Modal -->
<div class="modal-overlay hidden" id="sizeModal">
  <div class="modal" style="max-width:520px;width:96%;">
    <div class="modal-header">
      <h3 id="sizeModalTitle"><?= $isAr ? 'اختر الحجم' : 'Select Size' ?></h3>
      <button class="modal-close" onclick="closeSizeModal()">×</button>
    </div>
    <div class="modal-body">
      <!-- Size cards grid -->
      <div id="sizeOptions" class="size-options"></div>
      <!-- Qty row — hidden until a size is selected -->
      <div id="sizeQtyRow" style="display:none;margin-top:16px;padding:14px;background:#f0f7ff;border-radius:12px;">
        <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;">
          <div>
            <div style="font-size:12px;color:#6b7280;margin-bottom:2px;"><?= $isAr?'الحجم المختار':'Selected size' ?></div>
            <div id="sizeQtyLabel" style="font-size:15px;font-weight:800;color:#1d4ed8;"></div>
          </div>
          <div style="display:flex;align-items:center;gap:0;border:2px solid #bfdbfe;border-radius:10px;overflow:hidden;background:#fff;">
            <button type="button" onclick="szChangeQty(-1)" style="width:40px;height:42px;border:none;background:#eff6ff;font-size:22px;font-weight:700;cursor:pointer;color:#1d4ed8;">−</button>
            <input type="number" id="szQtyInput" value="1" min="1" max="999"
              style="width:52px;height:42px;border:none;border-left:1.5px solid #bfdbfe;border-right:1.5px solid #bfdbfe;text-align:center;font-size:18px;font-weight:800;color:#1d4ed8;"
              oninput="szUpdateTotal()">
            <button type="button" onclick="szChangeQty(1)" style="width:40px;height:42px;border:none;background:#eff6ff;font-size:22px;font-weight:700;cursor:pointer;color:#1d4ed8;">+</button>
          </div>
          <div style="text-align:right;">
            <div style="font-size:11px;color:#6b7280;"><?= $isAr?'الإجمالي':'Total' ?></div>
            <div id="szTotalDisplay" style="font-size:16px;font-weight:800;color:#15803d;">0.000 KD</div>
          </div>
        </div>
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-outline" onclick="closeSizeModal()"><?= $isAr ? 'إلغاء' : 'Cancel' ?></button>
      <button class="btn btn-primary" onclick="addSizeToCart()"><?= $isAr ? 'إضافة للسلة' : 'Add to Cart' ?></button>
    </div>
  </div>
</div>

<!-- Weight Input Modal -->
<div class="modal-overlay hidden" id="weightModal">
  <div class="modal">
    <div class="modal-header">
      <h3 id="weightModalTitle"><?= $isAr ? 'أدخل الوزن' : 'Enter Weight' ?></h3>
      <button class="modal-close" onclick="closeWeightModal()">×</button>
    </div>
    <div class="modal-body">
      <div style="text-align:center;margin-bottom:16px;">
        <div style="font-size:13px;color:#6b7280;" id="weightProductInfo"></div>
        <div style="font-size:14px;font-weight:600;color:#2563eb;margin-top:4px;" id="weightPriceInfo"></div>
      </div>
      <div style="text-align:center;margin-bottom:16px;">
        <input type="number" id="weightInput" class="form-control" style="font-size:24px;font-weight:700;text-align:center;max-width:200px;margin:0 auto;" placeholder="0.0" min="0" step="0.1" oninput="calcWeightTotal()">
        <div style="margin-top:6px;font-size:13px;color:#6b7280;" id="weightUnitLabel"></div>
      </div>
      <div id="numpadArea" style="max-width:240px;margin:0 auto;">
        <div class="numpad">
          <?php foreach ([7,8,9,4,5,6,1,2,3] as $n): ?>
          <button class="num-btn" onclick="numpadPress(<?= $n ?>)"><?= $n ?></button>
          <?php endforeach; ?>
          <button class="num-btn" onclick="numpadPress('.')" >.</button>
          <button class="num-btn zero" onclick="numpadPress(0)">0</button>
          <button class="num-btn del" onclick="numpadDel()">⌫</button>
        </div>
      </div>
      <div style="text-align:center;margin-top:14px;">
        <div style="font-size:13px;color:#6b7280;"><?= $isAr ? 'الإجمالي' : 'Total' ?>:</div>
        <div style="font-size:22px;font-weight:800;color:#2563eb;" id="weightTotalCalc">0.000 KD</div>
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-outline" onclick="closeWeightModal()"><?= $isAr ? 'إلغاء' : 'Cancel' ?></button>
      <button class="btn btn-primary" onclick="addWeightToCart()"><?= $isAr ? 'إضافة للسلة' : 'Add to Cart' ?></button>
    </div>
  </div>
</div>

<!-- Checkout Success Modal -->
<div class="modal-overlay hidden" id="successModal">
  <div class="modal" style="max-width:340px;">
    <div class="modal-body" style="text-align:center;padding:32px 24px;">
      <div style="font-size:52px;margin-bottom:12px;">✅</div>
      <h3 style="font-size:18px;font-weight:700;margin-bottom:6px;"><?= $isAr ? 'تم البيع بنجاح!' : 'Sale Complete!' ?></h3>
      <div style="font-size:13px;color:#6b7280;margin-bottom:16px;" id="successInfo"></div>
      <div style="display:flex;gap:8px;justify-content:center;flex-wrap:wrap;">
        <button class="btn btn-primary" onclick="printReceipt()"><?= $isAr ? 'طباعة الفاتورة' : 'Print Receipt' ?></button>
        <button class="btn btn-outline" onclick="newSale()"><?= $isAr ? 'بيع جديد' : 'New Sale' ?></button>
      </div>
    </div>
  </div>
</div>

<script>
const isAr = <?= $isAr ? 'true' : 'false' ?>;
const loyaltyEnabled = <?= getSetting('loyalty_enabled','1') === '1' ? 'true' : 'false' ?>;
const loyaltyKdPerPoint = <?= (int)getSetting('loyalty_kd_per_point', 10) ?>;
const loyaltyPointValue = <?= (int)getSetting('loyalty_point_value', 1) ?>;
const allProducts = <?= json_encode($products, JSON_UNESCAPED_UNICODE) ?>;
let cart = [];
let currentFilter = '';
let selectedMethod = 'cash';
let currentSaleId = null;
let sizeModalProduct = null;
let selectedSizeId = null;
let paidManuallyEdited = false;
let weightModalProduct = null;

// ---- Product Grid ----
function filterProducts() {
    const q = document.getElementById('productSearch').value.toLowerCase();
    const cat = document.getElementById('catFilter').value;
    renderProducts(q, cat, currentFilter);
}

function setCatFilter(f, btn) {
    currentFilter = f;
    document.querySelectorAll('.cat-tab').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    filterProducts();
}

function renderProducts(q = '', catId = '', typeFilter = '') {
    const grid = document.getElementById('productGrid');
    let prods = allProducts.filter(p => {
        const name = isAr ? p.name_ar : p.name;
        const matchQ = !q || name.toLowerCase().includes(q) || (p.barcode && p.barcode.toLowerCase().includes(q));
        const matchCat = !catId || (typeFilter.startsWith('cat_') ? p.cat_id == catId.replace('cat_','') : true);
        const matchType = !typeFilter || typeFilter === '' ||
            (typeFilter === 'piece' && p.type === 'piece') ||
            (typeFilter === 'weight' && p.type === 'weight') ||
            (typeFilter.startsWith('cat_') && String(p.cat_id) === typeFilter.replace('cat_',''));
        return matchQ && matchCat && matchType;
    });

    if (!prods.length) {
        grid.innerHTML = `<div class="text-center text-muted" style="padding:40px;grid-column:1/-1;">${isAr ? 'لا توجد منتجات' : 'No products found'}</div>`;
        return;
    }

    grid.innerHTML = prods.map(p => {
        const name = isAr ? p.name_ar : p.name;
        const cat  = isAr ? p.cat_name_ar : p.cat_name;

        // Correct stock for piece products (stock lives in product_sizes, not products)
        let stockVal, isLow;
        if (p.type === 'piece' && p.sizes && p.sizes.length) {
            stockVal = p.sizes.reduce((s, sz) => s + parseInt(sz.stock || 0), 0);
            isLow    = p.sizes.some(sz => parseInt(sz.stock || 0) <= parseInt(sz.low_stock_threshold || 5));
        } else {
            stockVal = parseFloat(p.stock || 0);
            isLow    = stockVal <= parseFloat(p.low_stock_threshold || 0);
        }

        // Price display
        let priceStr = '';
        if (p.type === 'weight') {
            priceStr = parseFloat(p.base_price).toFixed(3) + ' KD/' + (p.weight_unit === 'tola' ? (isAr?'تولة':'tola') : (isAr?'غ':'g'));
        } else if (p.sizes && p.sizes.length) {
            const pricedSizes = p.sizes.filter(s => parseFloat(s.price) > 0);
            const minP = pricedSizes.length ? Math.min(...pricedSizes.map(s => parseFloat(s.price))) : 0;
            priceStr = (isAr ? 'من ' : 'from ') + minP.toFixed(3) + ' KD';
        } else {
            priceStr = parseFloat(p.base_price).toFixed(3) + ' KD';
        }

        // Size badges for piece products
        let sizesHtml = '';
        if (p.type === 'piece' && p.sizes && p.sizes.length) {
            const badges = p.sizes.map(sz => {
                const stkNum = parseInt(sz.stock || 0);
                const sLow   = stkNum <= parseInt(sz.low_stock_threshold || 5);
                const bg     = sLow ? '#fee2e2' : '#eff6ff';
                const fg     = sLow ? '#dc2626' : '#2563eb';
                return `<span style="display:inline-block;padding:2px 7px;border-radius:4px;font-size:10px;font-weight:700;margin:1px;background:${bg};color:${fg};" title="${parseFloat(sz.price).toFixed(3)} KD · ${stkNum} pcs">${sz.size_label}</span>`;
            }).join('');
            sizesHtml = `<div style="margin:5px 0 2px;line-height:1.4;">${badges}</div>`;
        }

        const unit = p.type === 'weight' ? (p.weight_unit === 'tola' ? (isAr?'تولة':'tola') : (isAr?'غ':'g')) : (isAr?'قطعة':'pcs');
        const imgHtml = p.image ? `<div style="text-align:center;margin-bottom:6px;"><img src="${p.image}" style="height:52px;width:52px;object-fit:cover;border-radius:8px;border:1px solid #e5e7eb;"></div>` : '';
        return `<div class="product-card-pos" onclick="selectProduct(${p.id})" style="align-self:start;">
            ${imgHtml}
            <div class="p-name">${name}</div>
            <div class="p-cat">${cat || ''}</div>
            <div class="p-price">${priceStr}</div>
            ${sizesHtml}
            <div class="p-stock ${isLow ? 'low' : ''}">${stockVal} ${unit}</div>
        </div>`;
    }).join('');
}

// ---- Product Selection ----
function selectProduct(id) {
    const p = allProducts.find(x => x.id == id);
    if (!p) return;
    if (p.type === 'weight') {
        openWeightModal(p);
    } else if (p.sizes && p.sizes.length > 0) {
        openSizeModal(p);
    } else {
        addToCart({id: p.id, name: p.name, name_ar: p.name_ar, sizeId: null, sizeLabel: null, price: parseFloat(p.base_price), type: 'piece'});
    }
}

// ---- Size Modal (state stored in data-* attrs, no globals) ----
function openSizeModal(p) {
    const m = document.getElementById('sizeModal');
    m.dataset.pid = p.id;
    m.dataset.sid = '';
    document.getElementById('sizeModalTitle').textContent = (isAr ? p.name_ar : p.name) + ' — ' + (isAr ? 'اختر الحجم' : 'Select Size');
    document.getElementById('sizeQtyRow').style.display = 'none';
    document.getElementById('szQtyInput').value = '1';
    document.getElementById('sizeOptions').innerHTML = p.sizes.map(s => {
        const oos = parseInt(s.stock) <= 0;
        const low = !oos && parseInt(s.stock) <= parseInt(s.low_stock_threshold || 5);
        const clr = oos ? '#dc2626' : (low ? '#d97706' : '#16a34a');
        return '<div class="size-opt" id="sopt_' + s.id + '" onclick="' + (oos ? 'void(0)' : 'selectSize(' + s.id + ')') + '" style="' + (oos ? 'opacity:.45;cursor:not-allowed;' : 'cursor:pointer;') + '">'
            + '<div class="size-lbl">' + s.size_label + '</div>'
            + '<div class="size-price">' + parseFloat(s.price).toFixed(3) + ' KD</div>'
            + '<div class="size-stock" style="color:' + clr + ';">' + s.stock + ' ' + (isAr ? 'قط' : 'pcs') + '</div>'
            + '</div>';
    }).join('');
    m.classList.remove('hidden');
}
function selectSize(sId) {
    const m = document.getElementById('sizeModal');
    const prod = allProducts.find(function(p){ return String(p.id) === String(m.dataset.pid); });
    if (!prod) return;
    const sz = prod.sizes.find(function(s){ return String(s.id) === String(sId); });
    if (!sz) return;
    m.dataset.sid = String(sId);
    document.querySelectorAll('.size-opt').forEach(function(e){ e.classList.remove('selected'); });
    var card = document.getElementById('sopt_' + sId);
    if (card) card.classList.add('selected');
    document.getElementById('sizeQtyRow').style.display = '';
    document.getElementById('sizeQtyLabel').textContent = sz.size_label + ' · ' + parseFloat(sz.price).toFixed(3) + ' KD';
    var inp = document.getElementById('szQtyInput');
    inp.max = parseInt(sz.stock) || 999;
    inp.value = 1;
    szUpdateTotal();
    inp.focus();
}
function szChangeQty(delta) {
    var inp = document.getElementById('szQtyInput');
    inp.value = Math.max(1, Math.min(parseInt(inp.max) || 999, (parseInt(inp.value) || 1) + delta));
    szUpdateTotal();
}
function szUpdateTotal() {
    var m = document.getElementById('sizeModal');
    if (!m.dataset.pid || !m.dataset.sid) return;
    var prod = allProducts.find(function(p){ return String(p.id) === String(m.dataset.pid); });
    if (!prod) return;
    var sz = prod.sizes.find(function(s){ return String(s.id) === String(m.dataset.sid); });
    if (!sz) return;
    var qty = parseInt(document.getElementById('szQtyInput').value) || 1;
    document.getElementById('szTotalDisplay').textContent = (qty * parseFloat(sz.price)).toFixed(3) + ' KD';
}
function addSizeToCart() {
    var m = document.getElementById('sizeModal');
    var pid = m.dataset.pid, sid = m.dataset.sid;
    if (!pid) { alert(isAr ? 'خطأ' : 'Error'); return; }
    if (!sid) { alert(isAr ? 'اختر حجمًا أولاً' : 'Select a size first'); return; }
    var prod = allProducts.find(function(p){ return String(p.id) === String(pid); });
    var sz   = prod ? prod.sizes.find(function(s){ return String(s.id) === String(sid); }) : null;
    if (!prod || !sz) { alert(isAr ? 'خطأ - أعد المحاولة' : 'Error - please retry'); return; }
    var qty = Math.max(1, parseInt(document.getElementById('szQtyInput').value) || 1);
    addToCart({ id: prod.id, name: prod.name, name_ar: prod.name_ar,
        sizeId: sz.id, sizeLabel: sz.size_label, price: parseFloat(sz.price), qty: qty, type: 'piece' });
    closeSizeModal();
}
function closeSizeModal() {
    var m = document.getElementById('sizeModal');
    m.classList.add('hidden');
    m.dataset.pid = '';
    m.dataset.sid = '';
}

// ---- Weight Modal ----
function openWeightModal(p) {
    weightModalProduct = p;
    document.getElementById('weightModalTitle').textContent = (isAr ? p.name_ar : p.name);
    document.getElementById('weightProductInfo').textContent = isAr ? p.name_ar : p.name;
    document.getElementById('weightPriceInfo').textContent = parseFloat(p.base_price).toFixed(3) + ' KD per ' + (p.weight_unit === 'tola' ? 'tola' : 'gram');
    document.getElementById('weightUnitLabel').textContent = p.weight_unit === 'tola' ? (isAr ? 'تولة' : 'Tola') : (isAr ? 'غرام' : 'Grams');
    document.getElementById('weightInput').value = '';
    document.getElementById('weightTotalCalc').textContent = '0.000 KD';
    document.getElementById('weightModal').classList.remove('hidden');
    document.getElementById('weightInput').focus();
}
function calcWeightTotal() {
    if (!weightModalProduct) return;
    const w = parseFloat(document.getElementById('weightInput').value) || 0;
    const total = w * parseFloat(weightModalProduct.base_price);
    document.getElementById('weightTotalCalc').textContent = total.toFixed(3) + ' KD';
}
function numpadPress(v) {
    const inp = document.getElementById('weightInput');
    if (v === '.' && inp.value.includes('.')) return;
    inp.value += v;
    calcWeightTotal();
}
function numpadDel() {
    const inp = document.getElementById('weightInput');
    inp.value = inp.value.slice(0, -1);
    calcWeightTotal();
}
function addWeightToCart() {
    if (!weightModalProduct) return;
    const w = parseFloat(document.getElementById('weightInput').value);
    if (!w || w <= 0) { alert(isAr ? 'أدخل الوزن' : 'Enter weight'); return; }
    const p = weightModalProduct;
    addToCart({id: p.id, name: p.name, name_ar: p.name_ar, sizeId: null, sizeLabel: p.weight_unit === 'tola' ? (w + ' tola') : (w + ' g'), price: parseFloat(p.base_price), qty: w, type: 'weight'});
    closeWeightModal();
}
function closeWeightModal() { document.getElementById('weightModal').classList.add('hidden'); }

// ---- Cart ----
function addToCart(item) {
    // Check for promotion (size-level first, then product-level)
    let finalPrice = item.price;
    let promoInfo = null;
    
    if (item.sizeId) {
        // Check size-level promo
        const size = allProducts.flatMap(p => p.sizes || []).find(s => s.id === item.sizeId);
        if (size && size.promotion) {
            const promo = size.promotion;
            if (promo.discount_type === 'percent') {
                finalPrice = item.price * (1 - promo.discount_value / 100);
            } else {
                finalPrice = item.price - promo.discount_value;
            }
            finalPrice = Math.max(0, finalPrice);
            promoInfo = promo;
        }
    }
    
    // If no size-level promo, check product-level
    if (!promoInfo) {
        const product = allProducts.find(p => p.id === item.id);
        if (product && product.promotion) {
            const promo = product.promotion;
            if (promo.discount_type === 'percent') {
                finalPrice = item.price * (1 - promo.discount_value / 100);
            } else {
                finalPrice = item.price - promo.discount_value;
            }
            finalPrice = Math.max(0, finalPrice);
            promoInfo = promo;
        }
    }
    
    const key = item.id + '_' + (item.sizeId || 'base') + '_' + (item.type === 'weight' ? Date.now() : '');
    const existing = item.type !== 'weight' ? cart.find(c => c.key === (item.id + '_' + (item.sizeId || 'base') + '_')) : null;
    if (existing) {
        existing.qty = parseFloat((existing.qty + (item.qty || 1)).toFixed(3));
    } else {
        cart.push({ 
            key: key, 
            id: item.id, 
            name: item.name, 
            name_ar: item.name_ar, 
            sizeId: item.sizeId, 
            sizeLabel: item.sizeLabel, 
            price: finalPrice, 
            originalPrice: item.price,
            promo: promoInfo,
            qty: item.qty || 1, 
            type: item.type 
        });
    }
    renderCart();
}

function renderCart() {
    const area  = document.getElementById('cartItems');
    const empty = document.getElementById('cartEmpty');
    // Remove only cart-item divs — never touch #cartEmpty via innerHTML=
    area.querySelectorAll('.cart-item').forEach(function(el){ el.remove(); });
    if (!cart.length) {
        if (empty) empty.style.display = '';
        recalcCart();
        return;
    }
    if (empty) empty.style.display = 'none';
    area.insertAdjacentHTML('beforeend', cart.map(function(c, i) {
        const name  = isAr ? c.name_ar : c.name;
        const total = (c.price * c.qty).toFixed(3);
        const qtyCtrl = c.type === 'weight'
            ? '<div class="qty-control"><span style="font-size:13px;font-weight:600;color:#6b7280;">' + parseFloat(c.qty).toFixed(1) + ' ' + (isAr?'وحدة':'u') + '</span></div>'
            : '<div class="qty-control">'
                + '<button class="qty-btn" onclick="changeQty(' + i + ',-1)">−</button>'
                + '<input class="qty-input" type="number" value="' + c.qty + '" min="1" onchange="setQty(' + i + ',this.value)">'
                + '<button class="qty-btn" onclick="changeQty(' + i + ',1)">+</button>'
              + '</div>';
        // Promo badge
        let promoBadge = '';
        if (c.promo) {
            const discValue = c.promo.discount_type === 'percent' ? c.promo.discount_value + '%' : c.promo.discount_value + ' KD';
            promoBadge = '<span style="display:inline-block;padding:2px 6px;border-radius:4px;font-size:9px;font-weight:700;background:#dcfce7;color:#166534;margin-left:4px;">🏷 ' + discValue + '</span>';
        }
        return '<div class="cart-item">'
            + '<div class="cart-item-info">'
                + '<div class="cart-item-name">' + name + (c.promo ? promoBadge : '') + '</div>'
                + '<div class="cart-item-sub">' + (c.sizeLabel || '') + ' · ' + c.price.toFixed(3) + ' KD ' + (c.type==='weight'?(isAr?'لكل وحدة':'/unit'):(isAr?'للقطعة':'/ea')) + '</div>'
            + '</div>'
            + qtyCtrl
            + '<div class="cart-item-total">' + total + ' KD</div>'
            + '<button onclick="removeFromCart(' + i + ')" style="background:none;border:none;cursor:pointer;color:#9ca3af;font-size:16px;padding:0 4px;" title="Remove">×</button>'
        + '</div>';
    }).join(''));
    recalcCart();
}

function changeQty(i, delta) {
    cart[i].qty = Math.max(1, cart[i].qty + delta);
    renderCart();
}
function setQty(i, v) {
    cart[i].qty = Math.max(1, parseFloat(v) || 1);
    renderCart();
}
function removeFromCart(i) {
    cart.splice(i, 1);
    renderCart();
}

function recalcCart() {
    const sub = cart.reduce((s, c) => s + c.price * c.qty, 0);
    let disc = parseFloat(document.getElementById('discountAmt').value) || 0;
    disc = Math.min(disc, sub);
    const total = Math.max(0, sub - disc);
    document.getElementById('subtotalDisplay').textContent = sub.toFixed(3) + ' KD';
    document.getElementById('discountDisplay').textContent = '- ' + disc.toFixed(3) + ' KD';
    document.getElementById('totalDisplay').textContent = total.toFixed(3) + ' KD';
}

function selectMethod(m) {
    selectedMethod = m;
    document.querySelectorAll('.method-btn').forEach(b => b.classList.toggle('active', b.dataset.method === m));
}

// ---- Tabs ----
function switchTab(tab) {
    ['cart','barcode','recent'].forEach(t => {
        document.getElementById(t+'Tab').style.display = t === tab ? 'flex' : 'none';
        document.getElementById('tab'+t.charAt(0).toUpperCase()+t.slice(1)).classList.toggle('active', t === tab);
    });
    if (tab === 'barcode') setTimeout(() => document.getElementById('barcodeInput').focus(), 50);
    if (tab === 'recent') loadRecentSales();
}
document.getElementById('recentTab').style.display = 'none';
document.getElementById('barcodeTab').style.display = 'none';

// ---- Barcode ----
document.getElementById('barcodeInput').addEventListener('keydown', function(e) {
    if (e.key === 'Enter') {
        const code = this.value.trim();
        if (!code) return;
        const prod = allProducts.find(p => p.barcode === code);
        if (prod) {
            selectProduct(prod.id);
            document.getElementById('barcodeResult').innerHTML = `<div class="alert alert-success">✓ ${isAr ? 'تم إضافة:' : 'Added:'} ${isAr ? prod.name_ar : prod.name}</div>`;
            switchTab('cart');
        } else {
            // Also check sizes
            let found = false;
            for (const p of allProducts) {
                if (p.sizes) {
                    const sz = p.sizes.find(s => s.barcode === code);
                    if (sz) {
                        addToCart({id: p.id, name: p.name, name_ar: p.name_ar, sizeId: sz.id, sizeLabel: sz.size_label, price: parseFloat(sz.price), type: 'piece'});
                        document.getElementById('barcodeResult').innerHTML = `<div class="alert alert-success">✓ ${isAr ? p.name_ar : p.name} (${sz.size_label})</div>`;
                        switchTab('cart');
                        found = true;
                        break;
                    }
                }
            }
            if (!found) document.getElementById('barcodeResult').innerHTML = `<div class="alert alert-danger">${isAr ? 'لم يُعثر على الباركود' : 'Barcode not found: ' + code}</div>`;
        }
        this.value = '';
    }
});

// ---- Complete Sale ----
async function completeSale() {
    if (!cart.length) { alert(isAr ? 'السلة فارغة!' : 'Cart is empty!'); return; }
    const btn = document.getElementById('checkoutBtn');
    btn.disabled = true;
    btn.textContent = isAr ? 'جاري المعالجة...' : 'Processing...';

    const sub = cart.reduce((s, c) => s + c.price * c.qty, 0);
    let discVal = parseFloat(document.getElementById('discountAmt').value) || 0;
    let discAmt = discVal;
    discAmt = Math.min(discAmt, sub);
    const total = Math.max(0, sub - discAmt);
    const paid = total;

    const payload = {
        cart: cart,
        subtotal: sub,
        discount: discAmt,
        discount_type: 'fixed',
        total: total,
        paid_amount: paid,
        payment_method: selectedMethod,
        customer_name: document.getElementById('custName').value,
        customer_phone: document.getElementById('custPhone').value,
        customer_id: document.getElementById('custId').value || null,
        redeemed_points: document.getElementById('redeemPoints').checked ? (parseInt(document.getElementById('custPointsVal').textContent) || 0) : 0,
        tax: 0,
        promo_discount: cart.reduce((sum, c) => {
            if (c.promo && c.originalPrice) {
                const savings = (c.originalPrice - c.price) * c.qty;
                return sum + savings;
            }
            return sum;
        }, 0)
    };

    try {
        const res = await fetch('api/save_sale.php', {method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify(payload)});
        const data = await res.json();
        if (data.success) {
            currentSaleId = data.sale_id;
            document.getElementById('successInfo').textContent = `${isAr ? 'فاتورة' : 'Invoice'} ${data.invoice_no} · ${total.toFixed(3)} KD`;
            document.getElementById('successModal').classList.remove('hidden');
        } else {
            alert('Error: ' + (data.error || 'Unknown error'));
        }
    } catch(e) {
        alert('Connection error');
    }
    btn.disabled = false;
    btn.innerHTML = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:18px;height:18px;"><polyline points="20 6 9 17 4 12"/></svg> ${isAr ? 'إتمام البيع' : 'Complete Sale'}`;
}

function printReceipt() {
    if (currentSaleId) window.open('receipt_print.php?id='+currentSaleId, '_blank');
}
function newSale() {
    cart = [];
    renderCart();
    document.getElementById('custPhone').value = '';
    document.getElementById('custName').value = '';
    document.getElementById('custId').value = '';
    document.getElementById('custPointsEnabled').value = '1';
    document.getElementById('redeemPoints').checked = false;
    document.getElementById('custPointsBadge').style.display = 'none';
    document.getElementById('discountAmt').value = '';
    document.getElementById('successModal').classList.add('hidden');
}

// ---- Customer Search ----
let custSearchTimer = null;
function searchCustomer() {
    const q = document.getElementById('custPhone').value.trim();
    const dd = document.getElementById('custDropdown');
    clearTimeout(custSearchTimer);
    if (q.length < 2) { dd.style.display = 'none'; return; }
    custSearchTimer = setTimeout(async () => {
        try {
            const res = await fetch('api/search_customers.php?q=' + encodeURIComponent(q));
            const data = await res.json();
            if (!data.length) { dd.style.display = 'none'; return; }
            dd.innerHTML = data.map(c =>
                `<div onclick="selectCustomer(${c.id},'${c.name.replace(/'/g,"\\'")}','${(c.phone||'').replace(/'/g,"\\'")}',${c.points},${c.points_enabled??1})"
                  style="padding:8px 12px;cursor:pointer;border-bottom:1px solid #f3f4f6;font-size:12px;"
                  onmouseover="this.style.background='#f9fafb'" onmouseout="this.style.background=''">
                  <span style="font-weight:600;">${c.name}</span>
                  <span style="color:#9ca3af;margin-left:8px;">${c.phone||''}</span>
                  <span style="float:right;color:#854d0e;">★ ${c.points}</span>
                </div>`
            ).join('');
            dd.style.display = 'block';
        } catch(e) {}
    }, 300);
}
function selectCustomer(id, name, phone, points, pointsEnabled) {
    document.getElementById('custId').value = id;
    document.getElementById('custName').value = name;
    document.getElementById('custPhone').value = phone;
    document.getElementById('custPointsEnabled').value = pointsEnabled;
    document.getElementById('custDropdown').style.display = 'none';
    document.getElementById('redeemPoints').checked = false;
    const badge = document.getElementById('custPointsBadge');
    if (loyaltyEnabled && pointsEnabled && points > 0) {
        const kdVal = (points * loyaltyPointValue).toFixed(3);
        document.getElementById('custPointsVal').textContent = points;
        document.getElementById('custPointsKd').textContent = kdVal;
        badge.style.display = 'flex';
    } else {
        badge.style.display = 'none';
    }
    applyPointsRedeem();
}
function applyPointsRedeem() {
    const redeem = document.getElementById('redeemPoints').checked;
    const points = parseInt(document.getElementById('custPointsVal').textContent) || 0;
    if (redeem && loyaltyEnabled) {
        const kdDiscount = points * loyaltyPointValue;
        document.getElementById('discountAmt').value = kdDiscount.toFixed(3);
    } else {
        document.getElementById('discountAmt').value = '';
    }
    recalcCart();
}
document.addEventListener('click', function(e) {
    if (!e.target.closest('#custPhone') && !e.target.closest('#custDropdown')) {
        document.getElementById('custDropdown').style.display = 'none';
    }
});

// ---- Recent Sales ----
async function loadRecentSales() {
    try {
        const res = await fetch('api/recent_sales.php');
        const data = await res.json();
        const list = document.getElementById('recentSalesList');
        if (!data.length) { list.innerHTML = `<div class="text-center text-muted" style="padding:30px;">${isAr ? 'لا توجد مبيعات' : 'No recent sales'}</div>`; return; }
        list.innerHTML = data.map(s => `
            <div style="padding:10px;border:1px solid #e5e7eb;border-radius:8px;margin-bottom:8px;">
                <div style="display:flex;justify-content:space-between;align-items:center;">
                    <span style="font-weight:700;color:#2563eb;">${s.invoice_no}</span>
                    <span style="font-weight:700;">${parseFloat(s.total).toFixed(3)} KD</span>
                </div>
                <div style="font-size:11px;color:#9ca3af;margin-top:3px;">${s.created_at} · ${s.cashier || ''}</div>
                <div style="margin-top:6px;display:flex;gap:6px;">
                    <a href="receipt_print.php?id=${s.id}" target="_blank" class="btn btn-sm btn-outline">${isAr ? 'طباعة' : 'Print'}</a>
                    <a href="invoice_view.php?id=${s.id}" class="btn btn-sm btn-outline">${isAr ? 'عرض' : 'View'}</a>
                </div>
            </div>
        `).join('');
    } catch(e) {}
}

// Init
renderProducts();
</script>
</body>
</html>
