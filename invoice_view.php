<?php
require_once 'config.php';
requireLogin();
$isAr = isset($_SESSION['lang']) && $_SESSION['lang'] === 'ar';
$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: invoices.php'); exit; }

$rSale = $conn->query("SELECT s.*, u.full_name as cashier_name, b.name as branch_name, b.name_ar as branch_name_ar FROM sales s LEFT JOIN users u ON u.id=s.user_id LEFT JOIN branches b ON b.id=s.branch_id WHERE s.id=$id LIMIT 1");
$sale = $rSale ? $rSale->fetch_assoc() : null;
if (!$sale) { header('Location: invoices.php'); exit; }

$rItems = $conn->query("SELECT * FROM sale_items WHERE sale_id=$id ORDER BY id");
$items = $rItems ? $rItems->fetch_all(MYSQLI_ASSOC) : [];

$shopName = getSetting('shop_name', 'Demo POS');
$shopNameAr = getSetting('shop_name_ar', 'Demo POS');
$shopAddr = getSetting('shop_address', '');
$shopAddrAr = getSetting('shop_address_ar', '');
$shopPhone = getSetting('shop_phone', '');

$pageTitle = ($isAr ? 'فاتورة' : 'Invoice') . ' ' . $sale['invoice_no'];
include 'includes/head.php';
?>
<style>
@media print {
  body, html { margin: 0 !important; padding: 0 !important; }
  .topbar, .sidebar, .app-layout > div:first-child { display: none !important; }
  .main-content { padding: 0 !important; margin: 0 !important; width: 100% !important; }
  .page-content { max-width: 100% !important; padding: 0 !important; margin: 0 !important; }
  .card { box-shadow: none !important; border: none !important; margin: 0 !important; }
  /* Hide header with shop info */
  .card > div:first-child { display: none !important; }
  /* Hide invoice meta */
  .card > div:nth-child(2) { display: none !important; }
  /* Hide footer */
  .card > div:last-child { display: none !important; }
  /* Show only items and totals */
  .card > div:nth-child(3), .card > div:nth-child(4) { display: block !important; }
  /* Remove scrolling */
  table { width: 100% !important; table-layout: fixed !important; }
  td, th { overflow: visible !important; }
  /* Hide any filters or controls */
  .no-print { display: none !important; }
}
</style>
<div class="app-layout">
<?php include 'includes/sidebar.php'; ?>
<div class="main-content">
  <div class="topbar no-print">
    <div class="topbar-title"><?= $isAr ? 'تفاصيل الفاتورة' : 'Invoice Details' ?></div>
    <div class="topbar-right">
      <a href="invoices.php" class="btn btn-sm btn-outline"><?= $isAr ? 'العودة' : 'Back' ?></a>
      <a href="receipt_print.php?id=<?= $id ?>" target="_blank" class="btn btn-sm btn-primary">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:14px;height:14px;"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
        <?= $isAr ? 'طباعة' : 'Print' ?>
      </a>
      <?php if ($_SESSION['user_role'] === 'admin' && $sale['status'] !== 'void'): ?>
      <form method="POST" action="api/void_sale.php" style="display:inline;" onsubmit="return confirm('<?= $isAr ? 'إلغاء هذه الفاتورة؟' : 'Void this invoice?' ?>')">
        <input type="hidden" name="id" value="<?= $id ?>">
        <button type="submit" class="btn btn-sm btn-danger"><?= $isAr ? 'إلغاء الفاتورة' : 'Void Invoice' ?></button>
      </form>
      <?php endif; ?>
    </div>
  </div>
  <div class="page-content" style="max-width:800px;margin:0 auto;">
    <!-- Invoice Card -->
    <div class="card">
      <!-- Header -->
      <div style="padding:28px 32px;border-bottom:2px solid #e5e7eb;text-align:center;background:linear-gradient(135deg,#f0f9ff,#e0f2fe);">
        <div style="font-size:28px;margin-bottom:6px;">🌸</div>
        <div style="display:flex;gap:32px;justify-content:center;flex-wrap:wrap;">
          <div>
            <div style="font-size:18px;font-weight:800;color:#1e3a5f;"><?= htmlspecialchars($shopName) ?></div>
            <div style="font-size:12px;color:#6b7280;"><?= htmlspecialchars($shopAddr) ?></div>
            <div style="font-size:12px;color:#6b7280;"><?= htmlspecialchars($shopPhone) ?></div>
          </div>
          <div style="text-align:right;direction:rtl;">
            <div style="font-size:18px;font-weight:800;color:#1e3a5f;font-family:'Noto Sans Arabic',sans-serif;"><?= htmlspecialchars($shopNameAr) ?></div>
            <div style="font-size:12px;color:#6b7280;font-family:'Noto Sans Arabic',sans-serif;"><?= htmlspecialchars($shopAddrAr) ?></div>
          </div>
        </div>
      </div>

      <!-- Invoice Meta -->
      <div style="padding:12px 32px;display:grid;grid-template-columns:1fr 1fr;gap:12px;border-bottom:1px solid #e5e7eb;">
        <div>
          <div style="font-size:11px;color:#9ca3af;font-weight:600;text-transform:uppercase;margin-bottom:2px;"><?= $isAr ? 'رقم الفاتورة' : 'Invoice No' ?></div>
          <div style="font-size:16px;font-weight:800;color:#2563eb;"><?= htmlspecialchars($sale['invoice_no']) ?></div>
        </div>
        <div style="text-align:right;">
          <div style="font-size:11px;color:#9ca3af;font-weight:600;text-transform:uppercase;margin-bottom:2px;"><?= $isAr ? 'التاريخ' : 'Date' ?></div>
          <div style="font-weight:600;"><?= date('d/m/Y', strtotime($sale['created_at'])) ?></div>
        </div>
      </div>

      <!-- Items -->
      <div style="padding:0 32px;">
        <table style="width:100%;">
          <thead><tr style="border-bottom:1px solid #e5e7eb;">
            <th style="padding:8px 0;text-align:left;font-size:11px;font-weight:700;color:#9ca3af;text-transform:uppercase;"><?= $isAr ? 'المنتج' : 'Product' ?></th>
            <th style="padding:8px 8px;text-align:center;font-size:11px;font-weight:700;color:#9ca3af;text-transform:uppercase;"><?= $isAr ? 'الكمية' : 'Qty' ?></th>
            <th style="padding:8px 0;text-align:right;font-size:11px;font-weight:700;color:#9ca3af;text-transform:uppercase;"><?= $isAr ? 'الإجمالي' : 'Total' ?></th>
          </tr></thead>
          <tbody>
          <?php foreach ($items as $item): ?>
          <tr style="border-bottom:1px solid #f3f4f6;">
            <td style="padding:10px 0;">
              <div style="font-weight:600;"><?= htmlspecialchars($item['product_name']) ?></div>
              <?php if ($item['product_name_ar']): ?>
              <div style="font-size:11px;color:#6b7280;font-family:'Noto Sans Arabic',sans-serif;"><?= htmlspecialchars($item['product_name_ar']) ?></div>
              <?php endif; ?>
              <?php if ($item['size_label']): ?><div style="font-size:10px;color:#9ca3af;"><?= htmlspecialchars($item['size_label']) ?></div><?php endif; ?>
            </td>
            <td style="padding:10px 8px;text-align:center;font-weight:600;"><?= number_format($item['qty'], $item['qty'] == intval($item['qty']) ? 0 : 2) ?></td>
            <td style="padding:10px 0;text-align:right;font-weight:700;"><?= number_format($item['total'],3) ?> KD</td>
          </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <!-- Totals -->
      <div style="padding:16px 32px;background:#f9fafb;border-top:1px solid #e5e7eb;">
        <div style="max-width:280px;margin-left:auto;">
          <?php if ($sale['promo_discount'] > 0): ?>
          <div style="display:flex;justify-content:space-between;padding:4px 0;font-size:12px;color:#166534;">
            <span><?= $isAr ? 'خصم ترويجي' : 'Promo Discount' ?></span>
            <span>- <?= number_format($sale['promo_discount'],3) ?> KD</span>
          </div>
          <?php endif; ?>
          <?php if ($sale['discount'] > 0): ?>
          <div style="display:flex;justify-content:space-between;padding:4px 0;font-size:12px;color:#dc2626;">
            <span><?= $isAr ? 'الخصم' : 'Discount' ?></span>
            <span>- <?= number_format($sale['discount'],3) ?> KD</span>
          </div>
          <?php endif; ?>
          <div style="display:flex;justify-content:space-between;padding:8px 0;font-size:18px;font-weight:800;border-top:2px solid #1f2937;margin-top:4px;">
            <span><?= $isAr ? 'الإجمالي' : 'TOTAL' ?></span>
            <div style="text-align:right;display:flex;align-items:center;gap:8px;">
              <span style="color:#2563eb;"><?= number_format($sale['total'],3) ?> KD</span>
              <span style="font-size:11px;color:#6b7280;font-weight:600;"><?= strtoupper($sale['payment_method']) ?></span>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
</div>
<script src="assets/js/main.js"></script>
</body></html>
