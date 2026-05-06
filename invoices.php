<?php
require_once 'config.php';
requireLogin();
$isAr = isset($_SESSION['lang']) && $_SESSION['lang'] === 'ar';

// Filters
$dateFrom  = $_GET['date_from'] ?? date('Y-m-01');
$dateTo    = $_GET['date_to']   ?? date('Y-m-d');
$paymentMethod = $_GET['payment_method'] ?? '';
$search    = $conn->real_escape_string(trim($_GET['search'] ?? ''));

$where = "WHERE s.status != 'void'";
$where .= " AND DATE(s.created_at) BETWEEN '$dateFrom' AND '$dateTo'";
if ($paymentMethod) $where .= " AND s.payment_method = '" . $conn->real_escape_string($paymentMethod) . "'";
if ($search) $where .= " AND (s.invoice_no LIKE '%$search%' OR s.customer_name LIKE '%$search%')";

$rSales = $conn->query("SELECT s.*, u.full_name as cashier_name, (SELECT COUNT(*) FROM sale_items WHERE sale_id=s.id) as item_count FROM sales s LEFT JOIN users u ON u.id=s.user_id $where ORDER BY s.created_at DESC");
$sales = $rSales ? $rSales->fetch_all(MYSQLI_ASSOC) : [];

// Totals
$rTotals = $conn->query("SELECT SUM(total) as grand_total, SUM(paid_amount) as grand_paid, COUNT(*) as cnt FROM sales s $where");
$totals = $rTotals ? $rTotals->fetch_assoc() : [];

$pageTitle = $isAr ? 'الفواتير' : 'Invoices';
include 'includes/head.php';
?>
<div class="app-layout">
<?php include 'includes/sidebar.php'; ?>
<div class="main-content">
  <div class="topbar">
    <div class="topbar-title"><?= $isAr ? 'الفواتير' : 'Invoices' ?></div>
    <div class="topbar-right">
      <a href="lang.php?lang=<?= $isAr ? 'en' : 'ar' ?>" class="lang-btn"><?= $isAr ? 'EN' : 'ع' ?></a>
      <a href="new_sale.php" class="btn btn-primary btn-sm">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        <?= $isAr ? 'بيع جديد' : 'New Sale' ?>
      </a>
    </div>
  </div>
  <div class="page-content">

    <!-- Summary Totals -->
    <div class="stat-grid" style="grid-template-columns:repeat(2,1fr);" >
      <div class="stat-card">
        <div class="stat-label"><?= $isAr ? 'الإجمالي الكلي' : 'Grand Total' ?></div>
        <div class="stat-value" style="font-size:28px;color:#2563eb;"><?= number_format((float)($totals['grand_total'] ?? 0), 3) ?> <small style="font-size:18px;">KD</small></div>
      </div>
      <div class="stat-card">
        <div class="stat-label"><?= $isAr ? 'عدد الفواتير' : 'Invoice Count' ?></div>
        <div class="stat-value"><?= (int)($totals['cnt'] ?? 0) ?></div>
      </div>
    </div>

    <!-- Filters -->
    <div class="card mb-24">
      <div class="card-body" style="padding:14px 20px;">
        <form method="GET" style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end;">
          <div class="form-group mb-0">
            <label class="form-label"><?= $isAr ? 'من' : 'From' ?></label>
            <input type="date" name="date_from" class="form-control" value="<?= $dateFrom ?>">
          </div>
          <div class="form-group mb-0">
            <label class="form-label"><?= $isAr ? 'إلى' : 'To' ?></label>
            <input type="date" name="date_to" class="form-control" value="<?= $dateTo ?>">
          </div>
          <div class="form-group mb-0">
            <label class="form-label"><?= $isAr ? 'طريقة الدفع' : 'Payment' ?></label>
            <select name="payment_method" class="form-control">
              <option value=""><?= $isAr ? 'الكل' : 'All' ?></option>
              <option value="cash" <?= $paymentMethod === 'cash' ? 'selected' : '' ?>><?= $isAr ? 'نقد' : 'Cash' ?></option>
              <option value="knet" <?= $paymentMethod === 'knet' ? 'selected' : '' ?>>KNET</option>
              <option value="wamt" <?= $paymentMethod === 'wamt' ? 'selected' : '' ?>>WAMT</option>
            </select>
          </div>
          <div class="form-group mb-0" style="flex:1;min-width:160px;">
            <label class="form-label"><?= $isAr ? 'بحث' : 'Search' ?></label>
            <input type="text" name="search" class="form-control" placeholder="<?= $isAr ? 'رقم الفاتورة / العميل...' : 'Invoice No / Customer...' ?>" value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
          </div>
          <div class="form-group mb-0">
            <label class="form-label" style="visibility:hidden;">.</label>
            <div style="display:flex;gap:6px;">
              <button type="submit" class="btn btn-primary"><?= $isAr ? 'فلترة' : 'Filter' ?></button>
              <a href="invoices.php" class="btn btn-outline"><?= $isAr ? 'إعادة' : 'Reset' ?></a>
            </div>
          </div>
          <!-- Quick date shortcuts -->
          <div style="display:flex;gap:4px;align-self:flex-end;flex-wrap:wrap;">
            <a href="invoices.php?date_from=<?= date('Y-m-d') ?>&date_to=<?= date('Y-m-d') ?>" class="btn btn-sm btn-outline"><?= $isAr ? 'اليوم' : 'Today' ?></a>
            <a href="invoices.php?date_from=<?= date('Y-m-d', strtotime('monday this week')) ?>&date_to=<?= date('Y-m-d') ?>" class="btn btn-sm btn-outline"><?= $isAr ? 'هذا الأسبوع' : 'This Week' ?></a>
            <a href="invoices.php?date_from=<?= date('Y-m-01') ?>&date_to=<?= date('Y-m-d') ?>" class="btn btn-sm btn-outline"><?= $isAr ? 'هذا الشهر' : 'This Month' ?></a>
          </div>
        </form>
      </div>
    </div>

    <!-- Invoices Table -->
    <div class="card">
      <div class="card-header">
        <span class="card-title"><?= $isAr ? 'قائمة الفواتير' : 'Invoice List' ?> <span class="badge badge-blue" style="margin-<?= $isAr?'right':'left' ?>:8px;"><?= count($sales) ?></span></span>
        <button onclick="window.print()" class="btn btn-sm btn-outline no-print">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:14px;height:14px;"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
          <?= $isAr ? 'طباعة' : 'Print' ?>
        </button>
      </div>
      <div class="table-wrapper">
        <table>
          <thead><tr>
            <th><?= $isAr ? 'رقم الفاتورة' : 'Invoice No' ?></th>
            <th><?= $isAr ? 'التاريخ / الوقت' : 'Date / Time' ?></th>
            <th><?= $isAr ? 'العميل' : 'Customer' ?></th>
            <th><?= $isAr ? 'البنود' : 'Items' ?></th>
            <th><?= $isAr ? 'الإجمالي' : 'Total' ?></th>
            <th><?= $isAr ? 'الكاشير' : 'Cashier' ?></th>
            <th><?= $isAr ? 'الدفع' : 'Method' ?></th>
            <th><?= $isAr ? 'الحالة' : 'Status' ?></th>
            <th class="no-print"></th>
          </tr></thead>
          <tbody>
          <?php if (empty($sales)): ?>
            <tr><td colspan="9" class="text-center text-muted" style="padding:30px;"><?= $isAr ? 'لا توجد فواتير للفترة المحددة' : 'No invoices for the selected period' ?></td></tr>
          <?php else: ?>
            <?php foreach ($sales as $s): ?>
            <tr>
              <td style="font-weight:700;color:#2563eb;"><?= htmlspecialchars($s['invoice_no']) ?></td>
              <td>
                <div style="font-size:12px;font-weight:600;"><?= date('d/m/Y', strtotime($s['created_at'])) ?></div>
                <div style="font-size:11px;color:#9ca3af;"><?= date('h:i A', strtotime($s['created_at'])) ?></div>
              </td>
              <td style="color:#6b7280;"><?= htmlspecialchars($s['customer_name'] ?: '—') ?></td>
              <td><span class="badge badge-gray"><?= $s['item_count'] ?></span></td>
              <td style="font-weight:700;"><?= number_format($s['total'],3) ?> KD</td>
              <td style="color:#6b7280;font-size:12px;"><?= htmlspecialchars($s['cashier_name'] ?? '—') ?></td>
              <td><span class="badge badge-gray"><?= strtoupper($s['payment_method']) ?></span></td>
              <td>
                <?php $sc = ['paid'=>'badge-green','unpaid'=>'badge-red','partial'=>'badge-yellow']; ?>
                <span class="badge <?= $sc[$s['status']] ?? 'badge-gray' ?>">
                  <?= $isAr ? ['paid'=>'مدفوع','unpaid'=>'غير مدفوع','partial'=>'جزئي'][$s['status']] ?? $s['status'] : ucfirst($s['status']) ?>
                </span>
              </td>
              <td class="no-print">
                <div style="display:flex;gap:5px;">
                  <a href="invoice_view.php?id=<?= $s['id'] ?>" class="btn btn-sm btn-outline"><?= $isAr ? 'عرض' : 'View' ?></a>
                  <a href="receipt_print.php?id=<?= $s['id'] ?>" target="_blank" class="btn btn-sm btn-outline">🖨</a>
                  <?php if ($_SESSION['user_role'] === 'admin'): ?>
                  <button onclick="voidInvoice(<?= $s['id'] ?>, '<?= htmlspecialchars($s['invoice_no']) ?>')" class="btn btn-sm btn-danger" style="padding:4px 10px;"><?= $isAr ? 'حذف' : 'Delete' ?></button>
                  <?php endif; ?>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>
          <?php endif; ?>
          </tbody>
          <?php if (!empty($sales)): ?>
          <tfoot>
            <tr style="background:#f9fafb;font-weight:700;">
              <td colspan="4" style="padding:12px 16px;text-align:<?= $isAr ? 'right' : 'left' ?>;"><?= $isAr ? 'الإجمالي' : 'TOTAL' ?></td>
              <td style="padding:12px 16px;"><?= number_format((float)($totals['grand_total'] ?? 0),3) ?> KD</td>
              <td colspan="4"></td>
            </tr>
          </tfoot>
          <?php endif; ?>
        </table>
      </div>
    </div>
  </div>
</div>
</div>
<script src="assets/js/main.js"></script>
<script>
function voidInvoice(id, invNo) {
    if (!confirm('<?= $isAr ? 'هل أنت متأكد من حذف هذه الفاتورة؟ سيتم وضع علامة "ملغاة" ولن يتم حذف البيانات.' : 'Are you sure you want to delete this invoice? It will be marked as void but data will not be removed.' ?>\n\nInvoice: ' + invNo)) return;
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = 'api/void_sale.php';
    form.innerHTML = '<input type="hidden" name="id" value="' + id + '">';
    document.body.appendChild(form);
    form.submit();
}
</script>
</body></html>
