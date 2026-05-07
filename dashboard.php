<?php
require_once 'config.php';
requireLogin();
$isAr = isset($_SESSION['lang']) && $_SESSION['lang'] === 'ar';

// Today stats
$today = date('Y-m-d');
$yesterday = date('Y-m-d', strtotime('-1 day'));

$rToday = $conn->query("SELECT COALESCE(SUM(total),0) as rev, COUNT(*) as cnt, COALESCE(SUM(JSON_LENGTH(JSON_ARRAY())),0) as items FROM sales WHERE DATE(created_at)='$today' AND status!='void'");
$todayData = $rToday ? $rToday->fetch_assoc() : ['rev'=>0,'cnt'=>0,'items'=>0];

// Today revenue
$rRev = $conn->query("SELECT COALESCE(SUM(total),0) as rev FROM sales WHERE DATE(created_at)='$today' AND status!='void'");
$todayRev = $rRev ? (float)$rRev->fetch_assoc()['rev'] : 0;

$rRevY = $conn->query("SELECT COALESCE(SUM(total),0) as rev FROM sales WHERE DATE(created_at)='$yesterday' AND status!='void'");
$yesterdayRev = $rRevY ? (float)$rRevY->fetch_assoc()['rev'] : 0;

$rCnt = $conn->query("SELECT COUNT(*) as cnt FROM sales WHERE DATE(created_at)='$today' AND status!='void'");
$todayCnt = $rCnt ? (int)$rCnt->fetch_assoc()['cnt'] : 0;

$rCntY = $conn->query("SELECT COUNT(*) as cnt FROM sales WHERE DATE(created_at)='$yesterday' AND status!='void'");
$yesterdayCnt = $rCntY ? (int)$rCntY->fetch_assoc()['cnt'] : 0;

$rItems = $conn->query("SELECT COALESCE(SUM(si.qty),0) as items FROM sale_items si JOIN sales s ON s.id=si.sale_id WHERE DATE(s.created_at)='$today' AND s.status!='void'");
$todayItems = $rItems ? (float)$rItems->fetch_assoc()['items'] : 0;

// Low stock products (piece type)
$rLow = $conn->query("SELECT p.name, p.name_ar, p.type, p.stock, p.low_stock_threshold, c.name as cat_name FROM products p LEFT JOIN categories c ON c.id=p.category_id WHERE p.is_active=1 AND p.type='weight' AND p.stock <= p.low_stock_threshold ORDER BY p.stock ASC LIMIT 10");
$lowStockW = $rLow ? $rLow->fetch_all(MYSQLI_ASSOC) : [];

$rLowP = $conn->query("SELECT p.name, p.name_ar, ps.size_label, ps.stock, ps.low_stock_threshold FROM product_sizes ps JOIN products p ON p.id=ps.product_id WHERE ps.stock <= ps.low_stock_threshold ORDER BY ps.stock ASC LIMIT 10");
$lowStockP = $rLowP ? $rLowP->fetch_all(MYSQLI_ASSOC) : [];

$totalLow = count($lowStockW) + count($lowStockP);

// Inventory summary
$rInv = $conn->query("SELECT type, COUNT(*) as cnt FROM products WHERE is_active=1 GROUP BY type");
$invSummary = ['piece'=>0,'weight'=>0];
while ($inv = $rInv->fetch_assoc()) $invSummary[$inv['type']] = $inv['cnt'];

// Recent sales
$rRecent = $conn->query("SELECT s.*, u.full_name as cashier FROM sales s LEFT JOIN users u ON u.id=s.user_id WHERE s.status!='void' ORDER BY s.created_at DESC LIMIT 8");
$recentSales = $rRecent ? $rRecent->fetch_all(MYSQLI_ASSOC) : [];

// Top products today
$rTop = $conn->query("SELECT si.product_name, si.product_name_ar, SUM(si.qty) as total_qty, SUM(si.total) as total_rev FROM sale_items si JOIN sales s ON s.id=si.sale_id WHERE DATE(s.created_at)='$today' AND s.status!='void' GROUP BY si.product_name, si.product_name_ar ORDER BY total_rev DESC LIMIT 5");
$topProducts = $rTop ? $rTop->fetch_all(MYSQLI_ASSOC) : [];

$pageTitle = $isAr ? 'لوحة التحكم' : 'Dashboard';
include 'includes/head.php';
?>
<div class="app-layout">
<?php include 'includes/sidebar.php'; ?>
<div class="main-content">
  <!-- Topbar -->
  <div class="topbar">
    <div class="topbar-title">
      <?= $isAr ? 'لوحة التحكم — اليوم' : 'Dashboard — Today' ?>
    </div>
    <div class="topbar-right">
      <span class="badge-branch">
        <?= $isAr ? 'الفرع الرئيسي' : 'Main Branch' ?>
      </span>
      <span class="badge-date"><?= date('j M Y') ?></span>
      <a href="lang.php?lang=<?= $isAr ? 'en' : 'ar' ?>" class="lang-btn">
        <?= $isAr ? 'EN' : 'ع' ?>
      </a>
      <a href="new_sale.php" class="btn btn-primary btn-sm">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        <?= $isAr ? 'بيع جديد' : 'New Sale' ?>
      </a>
    </div>
  </div>

  <div class="page-content">
    <!-- Stat Cards -->
    <div class="stat-grid">
      <div class="stat-card">
        <div class="stat-label"><?= $isAr ? 'إيراد اليوم' : "Today's Revenue" ?></div>
        <div class="stat-value"><?= number_format($todayRev, 3) ?> <small style="font-size:14px;font-weight:500;">KD</small></div>
        <div class="stat-sub">
          <?php if ($yesterdayRev > 0): $pct = round(($todayRev - $yesterdayRev) / $yesterdayRev * 100); ?>
            <span class="<?= $pct >= 0 ? 'stat-up' : 'stat-down' ?>">
              <?= $pct >= 0 ? '↑' : '↓' ?> <?= abs($pct) ?>%
            </span>
            <?= $isAr ? 'مقارنة بالأمس' : 'vs yesterday' ?>
          <?php else: ?>
            <span class="text-muted"><?= $isAr ? 'لا توجد بيانات الأمس' : 'No yesterday data' ?></span>
          <?php endif; ?>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-label"><?= $isAr ? 'المعاملات' : 'Transactions' ?></div>
        <div class="stat-value"><?= $todayCnt ?></div>
        <div class="stat-sub">
          <?php $diff = $todayCnt - $yesterdayCnt; ?>
          <?php if ($yesterdayCnt > 0): ?>
            <span class="<?= $diff >= 0 ? 'stat-up' : 'stat-down' ?>">
              <?= $diff >= 0 ? '↑ ' . $diff : '↓ ' . abs($diff) ?>
            </span>
            <?= $isAr ? 'أكثر من الأمس' : 'more than avg' ?>
          <?php else: ?>
            <span class="text-muted"><?= $isAr ? 'اليوم الأول' : 'First day' ?></span>
          <?php endif; ?>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-label"><?= $isAr ? 'البنود المباعة' : 'Items Sold' ?></div>
        <div class="stat-value"><?= number_format($todayItems, 1) ?></div>
        <div class="stat-sub text-muted"><?= $isAr ? 'قطع + وزن' : 'Pieces + weight' ?></div>
      </div>
      <div class="stat-card <?= $totalLow > 0 ? 'stat-danger' : '' ?>">
        <div class="stat-label"><?= $isAr ? 'مخزون منخفض' : 'Low Stock' ?></div>
        <div class="stat-value"><?= $totalLow ?> <?= $isAr ? 'منتج' : 'items' ?></div>
        <div class="stat-sub <?= $totalLow > 0 ? 'text-danger fw-bold' : 'text-muted' ?>">
          <?= $totalLow > 0 ? ($isAr ? 'يحتاج إعادة طلب' : 'Needs reorder') : ($isAr ? 'كل شيء جيد' : 'All good') ?>
        </div>
      </div>
    </div>

    <div style="display:grid;grid-template-columns:1fr 320px;gap:20px;">
      <!-- Left: Inventory + Recent Sales -->
      <div>
        <!-- Inventory Summary -->
        <?php
        $invPiece  = $conn->query("SELECT COUNT(*) as c FROM products WHERE is_active=1 AND type='piece'");
        $invWeight = $conn->query("SELECT COUNT(*) as c FROM products WHERE is_active=1 AND type='weight'");
        $invPieceCount  = $invPiece  ? (int)$invPiece->fetch_assoc()['c']  : 0;
        $invWeightCount = $invWeight ? (int)$invWeight->fetch_assoc()['c'] : 0;
        $invTotalPcs = $conn->query("SELECT COALESCE(SUM(stock),0) as s FROM product_sizes");
        $totalPcs = $invTotalPcs ? (float)$invTotalPcs->fetch_assoc()['s'] : 0;
        $invTotalGrams = $conn->query("SELECT COALESCE(SUM(stock),0) as s FROM products WHERE is_active=1 AND type='weight'");
        $totalGrams = $invTotalGrams ? (float)$invTotalGrams->fetch_assoc()['s'] : 0;
        ?>
        <div class="card mb-24">
          <div class="card-header">
            <span class="card-title">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:18px;height:18px;display:inline;vertical-align:middle;margin-right:6px;"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/></svg>
              <?= $isAr ? 'ملخص المخزون' : 'Inventory Summary' ?>
            </span>
            <a href="products.php" class="btn btn-sm btn-outline"><?= $isAr ? 'إدارة المنتجات' : 'Manage Products' ?></a>
          </div>
          <div class="card-body" style="padding:16px;">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
              <div style="background:#eff6ff;border-radius:10px;padding:14px 16px;">
                <div style="font-size:11px;font-weight:700;color:#2563eb;margin-bottom:4px;"><?= $isAr?'عطور (قطعة)':'Perfumes (piece)' ?></div>
                <div style="font-size:22px;font-weight:800;color:#1d4ed8;"><?= $invPieceCount ?></div>
                <div style="font-size:11px;color:#6b7280;margin-top:2px;"><?= number_format($totalPcs,0) ?> <?= $isAr?'قطعة في المخزون':'pcs in stock' ?></div>
              </div>
              <div style="background:#fdf4ff;border-radius:10px;padding:14px 16px;">
                <div style="font-size:11px;font-weight:700;color:#9333ea;margin-bottom:4px;"><?= $isAr?'بخور (وزن)':'Bakhoor (weight)' ?></div>
                <div style="font-size:22px;font-weight:800;color:#7e22ce;"><?= $invWeightCount ?></div>
                <div style="font-size:11px;color:#6b7280;margin-top:2px;"><?= number_format($totalGrams,1) ?> <?= $isAr?'غرام في المخزون':'g in stock' ?></div>
              </div>
              <div style="background:#f0fdf4;border-radius:10px;padding:14px 16px;">
                <div style="font-size:11px;font-weight:700;color:#16a34a;margin-bottom:4px;"><?= $isAr?'إجمالي المنتجات':'Total Products' ?></div>
                <div style="font-size:22px;font-weight:800;color:#15803d;"><?= $invPieceCount + $invWeightCount ?></div>
                <div style="font-size:11px;color:#6b7280;margin-top:2px;"><?= $isAr?'منتج نشط':'active items' ?></div>
              </div>
              <div style="background:<?= $totalLow>0 ? '#fef2f2' : '#f9fafb' ?>;border-radius:10px;padding:14px 16px;border:1px solid <?= $totalLow>0 ? '#fecaca' : '#e5e7eb' ?>;">
                <div style="font-size:11px;font-weight:700;color:<?= $totalLow>0 ? '#dc2626' : '#6b7280' ?>;margin-bottom:4px;"><?= $isAr?'مخزون منخفض':'Low Stock' ?></div>
                <div style="font-size:22px;font-weight:800;color:<?= $totalLow>0 ? '#dc2626' : '#6b7280' ?>;"><?= $totalLow ?></div>
                <div style="font-size:11px;color:#6b7280;margin-top:2px;"><?= $totalLow>0 ? ($isAr?'تحتاج إعادة طلب':'need reorder') : ($isAr?'كل شيء جيد':'all good') ?></div>
              </div>
            </div>
            <?php if ($invPieceCount + $invWeightCount === 0): ?>
            <div style="text-align:center;padding:12px 0 4px;font-size:13px;color:#6b7280;">
              <?= $isAr?'لا توجد منتجات بعد. ':'No products yet. ' ?>
              <a href="products.php?action=add" style="color:#2563eb;font-weight:600;"><?= $isAr?'أضف منتجًا':'Add one now' ?></a>
            </div>
            <?php endif; ?>
          </div>
        </div>

        <!-- Recent Sales -->
        <div class="card">
          <div class="card-header">
            <span class="card-title"><?= $isAr ? 'آخر المبيعات' : 'Recent Sales' ?></span>
            <a href="invoices.php" class="btn btn-sm btn-outline"><?= $isAr ? 'عرض الكل' : 'View All' ?></a>
          </div>
          <div class="table-wrapper">
            <table>
              <thead><tr>
                <th><?= $isAr ? 'الفاتورة' : 'Invoice' ?></th>
                <th><?= $isAr ? 'الوقت' : 'Time' ?></th>
                <th><?= $isAr ? 'المبلغ' : 'Total' ?></th>
                <th><?= $isAr ? 'الدفع' : 'Payment' ?></th>
                <th><?= $isAr ? 'الحالة' : 'Status' ?></th>
                <th></th>
              </tr></thead>
              <tbody>
              <?php if (empty($recentSales)): ?>
                <tr><td colspan="6" class="text-center text-muted" style="padding:30px;">
                  <?= $isAr ? 'لا توجد مبيعات اليوم' : 'No sales today' ?>
                </td></tr>
              <?php else: ?>
                <?php foreach ($recentSales as $sale): ?>
                <tr>
                  <td style="font-weight:700;color:#2563eb;"><?= htmlspecialchars($sale['invoice_no']) ?></td>
                  <td style="color:#6b7280;"><?= date('h:i A', strtotime($sale['created_at'])) ?></td>
                  <td style="font-weight:700;"><?= number_format($sale['total'],3) ?> KD</td>
                  <td>
                    <span class="badge badge-gray"><?= strtoupper(htmlspecialchars($sale['payment_method'])) ?></span>
                  </td>
                  <td>
                    <?php $sc = ['paid'=>'badge-green','unpaid'=>'badge-red','partial'=>'badge-yellow','void'=>'badge-gray']; ?>
                    <span class="badge <?= $sc[$sale['status']] ?? 'badge-gray' ?>">
                      <?= $isAr ? ['paid'=>'مدفوع','unpaid'=>'غير مدفوع','partial'=>'جزئي','void'=>'ملغي'][$sale['status']] : ucfirst($sale['status']) ?>
                    </span>
                  </td>
                  <td>
                    <a href="invoice_view.php?id=<?= $sale['id'] ?>" class="btn btn-sm btn-outline"><?= $isAr ? 'عرض' : 'View' ?></a>
                  </td>
                </tr>
                <?php endforeach; ?>
              <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- Right Panel -->
      <div>
        <!-- Quick Actions -->
        <div class="card mb-16">
          <div class="card-header"><span class="card-title"><?= $isAr ? 'إجراءات سريعة' : 'Quick Actions' ?></span></div>
          <div class="card-body" style="display:flex;flex-direction:column;gap:8px;">
            <a href="new_sale.php" class="btn btn-primary btn-full">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
              <?= $isAr ? 'بيع جديد' : 'New Sale' ?>
            </a>
            <a href="products.php?action=add" class="btn btn-outline btn-full">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
              <?= $isAr ? 'إضافة منتج' : 'Add Product' ?>
            </a>
            <a href="reports.php" class="btn btn-outline btn-full">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
              <?= $isAr ? 'تقرير اليوم' : "Today's Report" ?>
            </a>
          </div>
        </div>

        <!-- Top Products -->
        <div class="card mb-16">
          <div class="card-header"><span class="card-title"><?= $isAr ? 'أكثر المنتجات مبيعاً اليوم' : "Today's Top Products" ?></span></div>
          <div class="card-body p-0">
            <?php if (empty($topProducts)): ?>
              <div class="text-center text-muted" style="padding:24px;"><?= $isAr ? 'لا توجد مبيعات بعد' : 'No sales yet' ?></div>
            <?php else: ?>
              <?php foreach ($topProducts as $i => $tp): ?>
              <div style="display:flex;align-items:center;gap:10px;padding:10px 16px;<?= $i > 0 ? 'border-top:1px solid #f3f4f6;' : '' ?>">
                <div style="width:22px;height:22px;background:#eff6ff;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:700;color:#2563eb;"><?= $i+1 ?></div>
                <div style="flex:1;min-width:0;">
                  <div style="font-size:13px;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?= htmlspecialchars($isAr ? $tp['product_name_ar'] : $tp['product_name']) ?></div>
                  <div style="font-size:11px;color:#9ca3af;"><?= number_format($tp['total_qty'],1) ?> <?= $isAr ? 'وحدة' : 'units' ?></div>
                </div>
                <div style="font-size:13px;font-weight:700;color:#2563eb;"><?= number_format($tp['total_rev'],3) ?> KD</div>
              </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </div>

        <!-- Low Stock Alert -->
        <?php if ($totalLow > 0): ?>
        <div class="card">
          <div class="card-header">
            <span class="card-title" style="color:#dc2626;"><?= $isAr ? 'تنبيه: مخزون منخفض' : 'Low Stock Alert' ?></span>
          </div>
          <div class="card-body p-0">
            <?php foreach (array_merge($lowStockW, array_slice($lowStockP, 0, 5)) as $i => $ls): ?>
            <div style="display:flex;align-items:center;justify-content:space-between;padding:9px 16px;<?= $i > 0 ? 'border-top:1px solid #f3f4f6;' : '' ?>">
              <div>
                <div style="font-size:13px;font-weight:600;"><?= htmlspecialchars($isAr ? $ls['name_ar'] : $ls['name']) ?></div>
                <?php if (isset($ls['size_label'])): ?><div style="font-size:11px;color:#9ca3af;"><?= htmlspecialchars($ls['size_label']) ?></div><?php endif; ?>
              </div>
              <span class="badge badge-red"><?= number_format($ls['stock'],1) ?></span>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>
</div>

<!-- Welcome Modal -->
<?php if (!isset($_SESSION['welcome_shown'])): ?>
<div id="welcomeModal" style="display:flex;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);z-index:9999;align-items:center;justify-content:center;">
    <div style="background:#fff;border-radius:12px;padding:32px;max-width:400px;width:90%;box-shadow:0 20px 25px -5px rgba(0,0,0,0.1);">
        <div style="text-align:center;">
            <div style="width:64px;height:64px;background:#dbeafe;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 16px;">
                <svg viewBox="0 0 24 24" fill="none" stroke="#2563eb" stroke-width="2" style="width:32px;height:32px;"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
            </div>
            <h3 style="font-size:20px;font-weight:700;color:#1f2937;margin:0 0 8px;"><?= $isAr ? 'مرحباً بك' : 'Welcome' ?></h3>
            <p style="font-size:16px;color:#374151;margin:0 0 24px;font-weight:600;"><?= $isAr ? 'مرحباً بك في Demo POS، ' . htmlspecialchars($_SESSION['user_name']) : 'Welcome to Demo POS, ' . htmlspecialchars($_SESSION['user_name']) ?></p>
            <button onclick="closeWelcomeModal()" style="width:100%;padding:12px 20px;background:#2563eb;border:none;border-radius:8px;font-size:14px;font-weight:600;color:#fff;cursor:pointer;"><?= $isAr ? 'ابدأ' : 'Get Started' ?></button>
        </div>
    </div>
</div>
<?php $_SESSION['welcome_shown'] = true; ?>
<?php endif; ?>

<script>
function closeWelcomeModal() {
    document.getElementById('welcomeModal').style.display = 'none';
}
</script>
<script src="assets/js/main.js"></script>
</body>
</html>
