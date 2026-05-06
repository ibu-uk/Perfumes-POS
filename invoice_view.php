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
      <div style="padding:20px 32px;display:grid;grid-template-columns:1fr 1fr;gap:20px;border-bottom:1px solid #e5e7eb;">
        <div>
          <div style="font-size:11px;color:#9ca3af;font-weight:600;text-transform:uppercase;margin-bottom:4px;"><?= $isAr ? 'رقم الفاتورة' : 'Invoice No' ?></div>
          <div style="font-size:20px;font-weight:800;color:#2563eb;"><?= htmlspecialchars($sale['invoice_no']) ?></div>
        </div>
        <div style="text-align:right;">
          <div style="font-size:11px;color:#9ca3af;font-weight:600;text-transform:uppercase;margin-bottom:4px;"><?= $isAr ? 'التاريخ والوقت' : 'Date & Time' ?></div>
          <div style="font-weight:600;"><?= date('d/m/Y h:i A', strtotime($sale['created_at'])) ?></div>
        </div>
        <?php if ($sale['customer_name']): ?>
        <div>
          <div style="font-size:11px;color:#9ca3af;font-weight:600;text-transform:uppercase;margin-bottom:4px;"><?= $isAr ? 'العميل' : 'Customer' ?></div>
          <div style="font-weight:600;"><?= htmlspecialchars($sale['customer_name']) ?></div>
        </div>
        <?php endif; ?>
        <div style="text-align:right;">
          <div style="font-size:11px;color:#9ca3af;font-weight:600;text-transform:uppercase;margin-bottom:4px;"><?= $isAr ? 'الكاشير' : 'Cashier' ?></div>
          <div style="font-weight:600;"><?= htmlspecialchars($sale['cashier_name'] ?? '—') ?></div>
        </div>
      </div>

      <!-- Items -->
      <div style="padding:0 32px;">
        <table style="width:100%;">
          <thead><tr style="border-bottom:1px solid #e5e7eb;">
            <th style="padding:10px 0;text-align:left;font-size:11px;font-weight:700;color:#9ca3af;text-transform:uppercase;"><?= $isAr ? 'المنتج' : 'Product' ?></th>
            <th style="padding:10px 8px;text-align:center;font-size:11px;font-weight:700;color:#9ca3af;text-transform:uppercase;"><?= $isAr ? 'الكمية' : 'Qty' ?></th>
            <th style="padding:10px 8px;text-align:right;font-size:11px;font-weight:700;color:#9ca3af;text-transform:uppercase;"><?= $isAr ? 'السعر' : 'Price' ?></th>
            <th style="padding:10px 0;text-align:right;font-size:11px;font-weight:700;color:#9ca3af;text-transform:uppercase;"><?= $isAr ? 'الإجمالي' : 'Total' ?></th>
          </tr></thead>
          <tbody>
          <?php foreach ($items as $item): ?>
          <tr style="border-bottom:1px solid #f3f4f6;">
            <td style="padding:12px 0;">
              <div style="font-weight:600;"><?= htmlspecialchars($item['product_name']) ?></div>
              <?php if ($item['product_name_ar']): ?>
              <div style="font-size:12px;color:#6b7280;font-family:'Noto Sans Arabic',sans-serif;"><?= htmlspecialchars($item['product_name_ar']) ?></div>
              <?php endif; ?>
              <?php if ($item['size_label']): ?><div style="font-size:11px;color:#9ca3af;"><?= htmlspecialchars($item['size_label']) ?></div><?php endif; ?>
            </td>
            <td style="padding:12px 8px;text-align:center;font-weight:600;"><?= number_format($item['qty'], 2) ?></td>
            <td style="padding:12px 8px;text-align:right;color:#6b7280;"><?= number_format($item['unit_price'],3) ?> KD</td>
            <td style="padding:12px 0;text-align:right;font-weight:700;"><?= number_format($item['total'],3) ?> KD</td>
          </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <!-- Totals -->
      <div style="padding:20px 32px;background:#f9fafb;border-top:1px solid #e5e7eb;">
        <div style="max-width:280px;margin-left:auto;">
          <div style="display:flex;justify-content:space-between;padding:5px 0;font-size:13px;color:#6b7280;">
            <span><?= $isAr ? 'المجموع الفرعي' : 'Subtotal' ?></span>
            <span><?= number_format($sale['subtotal'],3) ?> KD</span>
          </div>
          <?php if ($sale['discount'] > 0): ?>
          <div style="display:flex;justify-content:space-between;padding:5px 0;font-size:13px;color:#dc2626;">
            <span><?= $isAr ? 'الخصم' : 'Discount' ?></span>
            <span>- <?= number_format($sale['discount'],3) ?> KD</span>
          </div>
          <?php endif; ?>
          <?php if ($sale['tax'] > 0): ?>
          <div style="display:flex;justify-content:space-between;padding:5px 0;font-size:13px;color:#6b7280;">
            <span><?= $isAr ? 'الضريبة' : 'Tax' ?></span>
            <span><?= number_format($sale['tax'],3) ?> KD</span>
          </div>
          <?php endif; ?>
          <div style="display:flex;justify-content:space-between;padding:10px 0;font-size:20px;font-weight:800;border-top:2px solid #1f2937;margin-top:6px;">
            <span><?= $isAr ? 'الإجمالي' : 'TOTAL' ?></span>
            <div style="text-align:right;display:flex;align-items:center;gap:8px;">
              <span style="color:#2563eb;"><?= number_format($sale['total'],3) ?> KD</span>
              <span style="font-size:12px;color:#6b7280;font-weight:600;"><?= strtoupper($sale['payment_method']) ?></span>
            </div>
          </div>
        </div>
      </div>

      <!-- Footer -->
      <div style="padding:16px 32px;text-align:center;border-top:1px solid #e5e7eb;">
        <div style="font-size:13px;color:#6b7280;"><?= htmlspecialchars(getSetting('receipt_footer', 'Thank you for your visit!')) ?></div>
        <div style="font-size:13px;color:#6b7280;font-family:'Noto Sans Arabic',sans-serif;margin-top:3px;"><?= htmlspecialchars(getSetting('receipt_footer_ar', 'شكراً لزيارتكم!')) ?></div>
      </div>
    </div>
  </div>
</div>
</div>
<script src="assets/js/main.js"></script>
</body></html>
