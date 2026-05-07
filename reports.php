<?php
require_once 'config.php';
requireLogin();
$isAr = isset($_SESSION['lang']) && $_SESSION['lang'] === 'ar';

$reportType = $_GET['report_type'] ?? 'dashboard';
$period    = $_GET['period'] ?? 'today';
$dateFrom  = $_GET['date_from'] ?? date('Y-m-d');
$dateTo    = $_GET['date_to']   ?? date('Y-m-d');
$cashierFilter = (int)($_GET['cashier'] ?? 0);

// Resolve period
if ($period === 'all')       { $dateFrom = '2000-01-01'; $dateTo = '2099-12-31'; }
elseif ($period === 'today')     { $dateFrom = $dateTo = date('Y-m-d'); }
elseif ($period === 'yesterday') { $dateFrom = $dateTo = date('Y-m-d', strtotime('-1 day')); }
elseif ($period === 'week')  { $dateFrom = date('Y-m-d', strtotime('monday this week')); $dateTo = date('Y-m-d'); }
elseif ($period === 'month') { $dateFrom = date('Y-m-01'); $dateTo = date('Y-m-d'); }
elseif ($period === 'custom') { /* use GET values */ }

$baseWhere = "WHERE DATE(s.created_at) BETWEEN '$dateFrom' AND '$dateTo' AND s.status != 'void'" . ($cashierFilter ? " AND s.user_id=$cashierFilter" : '');

// Data based on report type
if ($reportType === 'dashboard') {
  // Summary
  $rSum = $conn->query("SELECT COUNT(*) as cnt, COALESCE(SUM(total),0) as revenue, COALESCE(SUM(discount),0) as discounts FROM sales s $baseWhere");
  $sum = $rSum ? $rSum->fetch_assoc() : [];

  // Items sold
  $rItemsSum = $conn->query("SELECT COALESCE(SUM(si.qty),0) as total_qty, COALESCE(SUM(si.total),0) as total_rev FROM sale_items si JOIN sales s ON s.id=si.sale_id $baseWhere");
  $itemsSum = $rItemsSum ? $rItemsSum->fetch_assoc() : [];

  // By payment method
  $rMethods = $conn->query("SELECT payment_method, COUNT(*) as cnt, SUM(total) as rev FROM sales s $baseWhere GROUP BY payment_method");
  $methods = $rMethods ? $rMethods->fetch_all(MYSQLI_ASSOC) : [];

  // Top products
  $rTop = $conn->query("SELECT si.product_name, si.product_name_ar, SUM(si.qty) as qty, SUM(si.total) as rev FROM sale_items si JOIN sales s ON s.id=si.sale_id $baseWhere GROUP BY si.product_name, si.product_name_ar ORDER BY rev DESC LIMIT 10");
  $topProds = $rTop ? $rTop->fetch_all(MYSQLI_ASSOC) : [];

  // Daily breakdown
  $rDaily = $conn->query("SELECT DATE(s.created_at) as day, COUNT(*) as cnt, SUM(total) as rev FROM sales s $baseWhere GROUP BY DATE(s.created_at) ORDER BY day DESC");
  $daily = $rDaily ? $rDaily->fetch_all(MYSQLI_ASSOC) : [];

  // All sales in period
  $rSales = $conn->query("SELECT s.*, u.full_name as cashier FROM sales s LEFT JOIN users u ON u.id=s.user_id $baseWhere ORDER BY s.created_at DESC");
  $sales = $rSales ? $rSales->fetch_all(MYSQLI_ASSOC) : [];
}
elseif ($reportType === 'product') {
  // Product-wise sales
  $rProdSales = $conn->query("SELECT si.product_name, si.product_name_ar, si.size_label, SUM(si.qty) as total_qty, SUM(si.total) as total_rev, COUNT(DISTINCT si.sale_id) as sales_count FROM sale_items si JOIN sales s ON s.id=si.sale_id $baseWhere GROUP BY si.product_name, si.product_name_ar, si.size_label ORDER BY total_rev DESC");
  $productSales = $rProdSales ? $rProdSales->fetch_all(MYSQLI_ASSOC) : [];
}
elseif ($reportType === 'cashier') {
  // Cashier-wise performance
  $rCashierPerf = $conn->query("SELECT u.full_name, COUNT(s.id) as invoice_count, COALESCE(SUM(s.total),0) as total_rev, COALESCE(AVG(s.total),0) as avg_rev FROM users u LEFT JOIN sales s ON s.user_id=u.id AND DATE(s.created_at) BETWEEN '$dateFrom' AND '$dateTo' AND s.status!='void' WHERE u.role IN ('admin','cashier') GROUP BY u.id ORDER BY total_rev DESC");
  $cashierPerf = $rCashierPerf ? $rCashierPerf->fetch_all(MYSQLI_ASSOC) : [];
}
elseif ($reportType === 'stock') {
  // Stock report - combine both weight products and piece product sizes
  $stockReport = [];

  // Weight type products (bakhoor) - stock in products table
  $rWeight = $conn->query("SELECT p.id, p.name, p.name_ar, 'weight' as type, p.stock, p.low_stock_threshold, c.name as category_name, '' as size_label FROM products p LEFT JOIN categories c ON c.id=p.category_id WHERE p.is_active=1 AND p.type='weight' ORDER BY p.name");
  while ($row = $rWeight ? $rWeight->fetch_assoc() : null) {
    $stockReport[] = $row;
  }

  // Piece type products (perfumes) - stock in product_sizes table
  $rSizes = $conn->query("SELECT ps.id, p.name, p.name_ar, 'piece' as type, ps.stock, ps.low_stock_threshold, c.name as category_name, ps.size_label FROM product_sizes ps JOIN products p ON p.id=ps.product_id LEFT JOIN categories c ON c.id=p.category_id WHERE p.is_active=1 ORDER BY p.name, ps.size_label");
  while ($row = $rSizes ? $rSizes->fetch_assoc() : null) {
    $stockReport[] = $row;
  }
}

// Cashier options for filter
$rCashiers = $conn->query("SELECT id, full_name FROM users WHERE role IN ('admin','cashier') ORDER BY full_name");
$cashiers = $rCashiers ? $rCashiers->fetch_all(MYSQLI_ASSOC) : [];

$pageTitle = $isAr ? 'التقارير' : 'Reports';
include 'includes/head.php';
?>
<div class="app-layout">
<?php include 'includes/sidebar.php'; ?>
<div class="main-content">
  <div class="topbar no-print">
    <div class="topbar-title"><?= $isAr ? 'التقارير' : 'Reports' ?></div>
    <div class="topbar-right">
      <a href="lang.php?lang=<?= $isAr ? 'en' : 'ar' ?>" class="lang-btn"><?= $isAr ? 'EN' : 'ع' ?></a>
      <button onclick="exportToExcel()" class="btn btn-sm btn-outline">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:14px;height:14px;"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2=17"/><polyline points="10 9 9 9 8 9"/></svg>
        <?= $isAr ? 'تصدير Excel' : 'Export Excel' ?>
      </button>
      <button onclick="window.print()" class="btn btn-sm btn-outline">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:14px;height:14px;"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
        <?= $isAr ? 'طباعة / PDF' : 'Print / PDF' ?>
      </button>
    </div>
  </div>
  <div class="page-content">

    <!-- Report Type Tabs -->
    <div class="card mb-24 no-print">
      <div class="card-body" style="padding:14px 20px;">
        <div style="display:flex;gap:8px;flex-wrap:wrap;">
          <a href="reports.php?report_type=dashboard&period=<?= $period ?>" class="btn btn-sm <?= $reportType === 'dashboard' ? 'btn-primary' : 'btn-outline' ?>"><?= $isAr ? 'لوحة التحكم' : 'Dashboard' ?></a>
          <a href="reports.php?report_type=product&period=<?= $period ?>" class="btn btn-sm <?= $reportType === 'product' ? 'btn-primary' : 'btn-outline' ?>"><?= $isAr ? 'تقرير المنتجات' : 'Product Report' ?></a>
          <a href="reports.php?report_type=cashier&period=<?= $period ?>" class="btn btn-sm <?= $reportType === 'cashier' ? 'btn-primary' : 'btn-outline' ?>"><?= $isAr ? 'تقرير الكاشير' : 'Cashier Report' ?></a>
          <a href="reports.php?report_type=stock" class="btn btn-sm <?= $reportType === 'stock' ? 'btn-primary' : 'btn-outline' ?>"><?= $isAr ? 'تقرير المخزون' : 'Stock Report' ?></a>
        </div>
      </div>
    </div>

    <!-- Period Selector (for Dashboard, Product, Cashier reports) -->
    <?php if ($reportType !== 'stock'): ?>
    <div class="card mb-24 no-print">
      <div class="card-body" style="padding:14px 20px;">
        <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:flex-end;">
          <?php foreach (['all'=>($isAr?'الكل':'All'), 'today'=>($isAr?'اليوم':'Today'), 'yesterday'=>($isAr?'أمس':'Yesterday'), 'week'=>($isAr?'هذا الأسبوع':'This Week'), 'month'=>($isAr?'هذا الشهر':'This Month'), 'custom'=>($isAr?'مخصص':'Custom')] as $p => $label): ?>
          <a href="reports.php?report_type=<?= $reportType ?>&period=<?= $p ?>" class="btn btn-sm <?= $period === $p ? 'btn-primary' : 'btn-outline' ?>"><?= $label ?></a>
          <?php endforeach; ?>

          <?php if ($period === 'custom'): ?>
          <form method="GET" style="display:flex;gap:8px;align-items:flex-end;">
            <input type="hidden" name="report_type" value="<?= $reportType ?>">
            <input type="hidden" name="period" value="custom">
            <input type="date" name="date_from" class="form-control" style="width:150px;" value="<?= $dateFrom ?>">
            <input type="date" name="date_to" class="form-control" style="width:150px;" value="<?= $dateTo ?>">
            <select name="cashier" class="form-control" style="width:140px;">
              <option value="0"><?= $isAr ? 'كل الكاشيرين' : 'All Cashiers' ?></option>
              <?php foreach ($cashiers as $c): ?>
              <option value="<?= $c['id'] ?>" <?= $cashierFilter === $c['id'] ? 'selected' : '' ?>><?= htmlspecialchars($c['full_name']) ?></option>
              <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-primary btn-sm"><?= $isAr ? 'تطبيق' : 'Apply' ?></button>
          </form>
          <?php endif; ?>

          <div style="margin-<?= $isAr?'right':'left' ?>:auto;font-size:13px;color:#6b7280;align-self:center;">
            📅 <?= $period === 'all' ? ($isAr?'الكل':'All') : date('d/m/Y', strtotime($dateFrom)) . ' — ' . date('d/m/Y', strtotime($dateTo)) ?>
            <?php if ($cashierFilter): ?> 👤 <?= htmlspecialchars($cashiers[array_search($cashierFilter, array_column($cashiers, 'id'))]['full_name'] ?? '') ?><?php endif; ?>
          </div>
        </div>
      </div>
    </div>
    <?php endif; ?>

    <!-- Print Header -->
    <div style="display:none;" class="print-only">
      <div style="text-align:center;margin-bottom:20px;">
        <h2 style="font-size:20px;"><?= $isAr ? 'تقرير المبيعات' : 'Sales Report' ?></h2>
        <p><?= $period === 'all' ? ($isAr?'الكل':'All') : date('d/m/Y', strtotime($dateFrom)) . ' — ' . date('d/m/Y', strtotime($dateTo)) ?></p>
        <?php if ($cashierFilter): ?><p style="font-size:14px;color:#6b7280;"><?= $isAr ? 'الكاشير:' : 'Cashier:' ?> <?= htmlspecialchars($cashiers[array_search($cashierFilter, array_column($cashiers, 'id'))]['full_name'] ?? '') ?></p><?php endif; ?>
      </div>
    </div>

    <?php if ($reportType === 'dashboard'): ?>
    <!-- Dashboard Report Content -->
    <!-- Summary Cards -->
    <div class="stat-grid" style="grid-template-columns:repeat(3,1fr);">
      <div class="stat-card">
        <div class="stat-label"><?= $isAr ? 'الإيراد الإجمالي' : 'Total Revenue' ?></div>
        <div class="stat-value"><?= number_format((float)($sum['revenue']??0),3) ?> <small style="font-size:14px;">KD</small></div>
      </div>
      <div class="stat-card">
        <div class="stat-label"><?= $isAr ? 'عدد الفواتير' : 'Invoices' ?></div>
        <div class="stat-value"><?= (int)($sum['cnt']??0) ?></div>
      </div>
      <div class="stat-card">
        <div class="stat-label"><?= $isAr ? 'إجمالي الخصومات' : 'Total Discounts' ?></div>
        <div class="stat-value" style="color:#d97706;"><?= number_format((float)($sum['discounts']??0),3) ?> <small style="font-size:14px;">KD</small></div>
      </div>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:20px;">
      <!-- Payment Methods -->
      <div class="card">
        <div class="card-header"><span class="card-title"><?= $isAr ? 'طرق الدفع' : 'Payment Methods' ?></span></div>
        <div class="card-body p-0">
          <?php if (empty($methods)): ?>
            <div class="text-center text-muted" style="padding:20px;"><?= $isAr ? 'لا توجد بيانات' : 'No data' ?></div>
          <?php else: ?>
          <?php foreach ($methods as $m): ?>
          <div style="display:flex;justify-content:space-between;align-items:center;padding:12px 20px;border-bottom:1px solid #f3f4f6;">
            <div>
              <span class="badge badge-gray"><?= strtoupper($m['payment_method']) ?></span>
              <span style="font-size:12px;color:#6b7280;margin-<?= $isAr?'right':'left' ?>:8px;"><?= $m['cnt'] ?> <?= $isAr?'فاتورة':'invoices' ?></span>
            </div>
            <span style="font-weight:700;"><?= number_format($m['rev'],3) ?> KD</span>
          </div>
          <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>

      <!-- Top Products -->
      <div class="card">
        <div class="card-header"><span class="card-title"><?= $isAr ? 'أكثر المنتجات مبيعاً' : 'Top Products' ?></span></div>
        <div class="card-body p-0">
          <?php if (empty($topProds)): ?>
            <div class="text-center text-muted" style="padding:20px;"><?= $isAr ? 'لا توجد بيانات' : 'No data' ?></div>
          <?php else: ?>
          <?php foreach ($topProds as $i => $tp): ?>
          <div style="display:flex;align-items:center;gap:10px;padding:10px 20px;<?= $i>0?'border-top:1px solid #f3f4f6;':'' ?>">
            <div style="width:20px;height:20px;background:#eff6ff;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:10px;font-weight:700;color:#2563eb;flex-shrink:0;"><?= $i+1 ?></div>
            <div style="flex:1;min-width:0;">
              <div style="font-size:13px;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?= htmlspecialchars($isAr ? $tp['product_name_ar'] : $tp['product_name']) ?></div>
              <div style="font-size:11px;color:#9ca3af;"><?= number_format($tp['qty'],1) ?> <?= $isAr?'وحدة':'units' ?></div>
            </div>
            <div style="font-weight:700;color:#2563eb;font-size:13px;"><?= number_format($tp['rev'],3) ?> KD</div>
          </div>
          <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- Daily Breakdown (if multi-day) -->
    <?php if (count($daily) > 1): ?>
    <div class="card mb-24">
      <div class="card-header"><span class="card-title"><?= $isAr ? 'التفصيل اليومي' : 'Daily Breakdown' ?></span></div>
      <div class="table-wrapper">
        <table>
          <thead><tr>
            <th><?= $isAr ? 'التاريخ' : 'Date' ?></th>
            <th><?= $isAr ? 'الفواتير' : 'Invoices' ?></th>
            <th><?= $isAr ? 'الإيراد' : 'Revenue' ?></th>
          </tr></thead>
          <tbody>
          <?php foreach ($daily as $d): ?>
          <tr>
            <td><?= date('D, d/m/Y', strtotime($d['day'])) ?></td>
            <td><?= $d['cnt'] ?></td>
            <td style="font-weight:700;"><?= number_format($d['rev'],3) ?> KD</td>
          </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php endif; ?>

    <!-- Full Invoice List -->
    <div class="card">
      <div class="card-header">
        <span class="card-title"><?= $isAr ? 'تفاصيل الفواتير' : 'Invoice Details' ?></span>
      </div>
      <div class="table-wrapper">
        <table>
          <thead><tr>
            <th><?= $isAr ? 'الفاتورة' : 'Invoice' ?></th>
            <th><?= $isAr ? 'التاريخ' : 'Date' ?></th>
            <th><?= $isAr ? 'العميل' : 'Customer' ?></th>
            <th><?= $isAr ? 'الإجمالي' : 'Total' ?></th>
            <th><?= $isAr ? 'الدفع' : 'Payment' ?></th>
            <th><?= $isAr ? 'الحالة' : 'Status' ?></th>
            <th class="no-print"></th>
          </tr></thead>
          <tbody>
          <?php foreach ($sales as $s): ?>
          <tr>
            <td style="font-weight:700;color:#2563eb;"><?= htmlspecialchars($s['invoice_no']) ?></td>
            <td style="font-size:12px;"><?= date('d/m/Y h:i A', strtotime($s['created_at'])) ?></td>
            <td style="color:#6b7280;"><?= htmlspecialchars($s['customer_name'] ?: '—') ?></td>
            <td style="font-weight:700;"><?= number_format($s['total'],3) ?> KD</td>
            <td><span class="badge badge-gray"><?= strtoupper($s['payment_method']) ?></span></td>
            <td>
              <?php $sc = ['paid'=>'badge-green','unpaid'=>'badge-red','partial'=>'badge-yellow']; ?>
              <span class="badge <?= $sc[$s['status']] ?? 'badge-gray' ?>"><?= ucfirst($s['status']) ?></span>
            </td>
            <td class="no-print"><a href="invoice_view.php?id=<?= $s['id'] ?>" class="btn btn-sm btn-outline"><?= $isAr?'عرض':'View' ?></a></td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($sales)): ?>
          <tr><td colspan="7" class="text-center text-muted" style="padding:30px;"><?= $isAr ? 'لا توجد فواتير' : 'No invoices found' ?></td></tr>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php endif; ?>

    <?php if ($reportType === 'product'): ?>
    <!-- Product Report Content -->
    <div class="card">
      <div class="card-header">
        <span class="card-title"><?= $isAr ? 'تقرير المنتجات' : 'Product Report' ?></span>
      </div>
      <div class="table-wrapper">
        <table>
          <thead><tr>
            <th><?= $isAr ? 'المنتج' : 'Product' ?></th>
            <th><?= $isAr ? 'الحجم' : 'Size' ?></th>
            <th><?= $isAr ? 'الكمية' : 'Qty' ?></th>
            <th><?= $isAr ? 'عدد المبيعات' : 'Sales Count' ?></th>
            <th><?= $isAr ? 'الإيراد' : 'Revenue' ?></th>
          </tr></thead>
          <tbody>
          <?php foreach ($productSales as $ps): ?>
          <tr>
            <td><?= htmlspecialchars($isAr ? $ps['product_name_ar'] : $ps['product_name']) ?></td>
            <td><?= htmlspecialchars($ps['size_label'] ?: '—') ?></td>
            <td style="font-weight:600;"><?= number_format($ps['total_qty'],1) ?></td>
            <td><?= $ps['sales_count'] ?></td>
            <td style="font-weight:700;color:#2563eb;"><?= number_format($ps['total_rev'],3) ?> KD</td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($productSales)): ?>
          <tr><td colspan="5" class="text-center text-muted" style="padding:30px;"><?= $isAr ? 'لا توجد بيانات' : 'No data' ?></td></tr>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php endif; ?>

    <?php if ($reportType === 'cashier'): ?>
    <!-- Cashier Report Content -->
    <div class="card">
      <div class="card-header">
        <span class="card-title"><?= $isAr ? 'تقرير الكاشير' : 'Cashier Report' ?></span>
      </div>
      <div class="table-wrapper">
        <table>
          <thead><tr>
            <th><?= $isAr ? 'الكاشير' : 'Cashier' ?></th>
            <th><?= $isAr ? 'عدد الفواتير' : 'Invoice Count' ?></th>
            <th><?= $isAr ? 'الإيراد الإجمالي' : 'Total Revenue' ?></th>
            <th><?= $isAr ? 'متوسط الفاتورة' : 'Avg Invoice' ?></th>
          </tr></thead>
          <tbody>
          <?php foreach ($cashierPerf as $cp): ?>
          <tr>
            <td style="font-weight:700;"><?= htmlspecialchars($cp['full_name']) ?></td>
            <td><?= $cp['invoice_count'] ?></td>
            <td style="font-weight:700;color:#2563eb;"><?= number_format($cp['total_rev'],3) ?> KD</td>
            <td><?= number_format($cp['avg_rev'],3) ?> KD</td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($cashierPerf)): ?>
          <tr><td colspan="4" class="text-center text-muted" style="padding:30px;"><?= $isAr ? 'لا توجد بيانات' : 'No data' ?></td></tr>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php endif; ?>

    <?php if ($reportType === 'stock'): ?>
    <!-- Stock Report Content -->
    <div class="card">
      <div class="card-header">
        <span class="card-title"><?= $isAr ? 'تقرير المخزون' : 'Stock Report' ?></span>
      </div>
      <div class="table-wrapper">
        <table>
          <thead><tr>
            <th><?= $isAr ? 'المنتج' : 'Product' ?></th>
            <th><?= $isAr ? 'الحجم' : 'Size' ?></th>
            <th><?= $isAr ? 'المخزون' : 'Stock' ?></th>
            <th><?= $isAr ? 'الحد الأدنى' : 'Low Stock' ?></th>
            <th><?= $isAr ? 'الحالة' : 'Status' ?></th>
          </tr></thead>
          <tbody>
          <?php foreach ($stockReport as $sr): ?>
          <tr>
            <td><?= htmlspecialchars($isAr ? $sr['name_ar'] : $sr['name']) ?></td>
            <td><?= htmlspecialchars($sr['size_label'] ?: '—') ?></td>
            <td style="font-weight:700;"><?= number_format($sr['stock'],0) ?></td>
            <td><?= number_format($sr['low_stock_threshold'],0) ?></td>
            <td>
              <?php if ($sr['stock'] <= $sr['low_stock_threshold']): ?>
              <span class="badge badge-red"><?= $isAr ? 'منخفض' : 'Low' ?></span>
              <?php else: ?>
              <span class="badge badge-green"><?= $isAr ? 'جيد' : 'OK' ?></span>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($stockReport)): ?>
          <tr><td colspan="5" class="text-center text-muted" style="padding:30px;"><?= $isAr ? 'لا توجد بيانات' : 'No data' ?></td></tr>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php endif; ?>
  </div>
</div>
</div>
<style>
@media print {
  .print-only { display: block !important; }
  .no-print { display: none !important; }
  .app-layout { display: block; }
  .sidebar { display: none !important; }
  .main-content { margin: 0 !important; padding: 0 !important; }
  .page-content { padding: 0 !important; }
  body { background: #fff !important; }
  .card { box-shadow: none !important; border: 1px solid #000 !important; margin-bottom: 20px !important; }
  .stat-grid { grid-template-columns: repeat(3, 1fr) !important; }
  .table-wrapper { overflow: visible !important; }
  .page-content > div:nth-child(2),
  .page-content > div:nth-child(3),
  .page-content > div:nth-child(4) {
    display: none !important;
  }
}
</style>
<script>
function exportToExcel() {
  const reportType = '<?= $reportType ?>';
  const period = '<?= $period ?>';
  const dateFrom = '<?= $dateFrom ?>';
  const dateTo = '<?= $dateTo ?>';
  const cashier = '<?= $cashierFilter ?>';
  window.location.href = 'api/export_report.php?report_type=' + reportType + '&period=' + period + '&date_from=' + dateFrom + '&date_to=' + dateTo + '&cashier=' + cashier;
}
</script>
<script src="assets/js/main.js"></script>
</body></html>
