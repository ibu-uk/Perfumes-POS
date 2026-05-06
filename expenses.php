<?php
require_once 'config.php';
requireLogin();
$isAr = isset($_SESSION['lang']) && $_SESSION['lang'] === 'ar';

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pAction = $_POST['action'] ?? '';
    if ($pAction === 'save') {
        $id    = (int)($_POST['id'] ?? 0);
        $cat   = $conn->real_escape_string($_POST['category'] ?? '');
        $desc  = $conn->real_escape_string($_POST['description'] ?? '');
        $amt   = (float)$_POST['amount'];
        $paidTo= $conn->real_escape_string($_POST['paid_to'] ?? '');
        $date  = $conn->real_escape_string($_POST['expense_date'] ?? date('Y-m-d'));
        $userId= (int)$_SESSION['user_id'];
        if ($id) {
            $conn->query("UPDATE expenses SET category='$cat', description='$desc', amount=$amt, paid_to='$paidTo', expense_date='$date' WHERE id=$id");
            $msg = $isAr ? 'تم تحديث المصروف.' : 'Expense updated.';
        } else {
            $conn->query("INSERT INTO expenses (category, description, amount, paid_to, expense_date, user_id) VALUES ('$cat','$desc',$amt,'$paidTo','$date',$userId)");
            $msg = $isAr ? 'تمت إضافة المصروف.' : 'Expense added.';
        }
    } elseif ($pAction === 'delete') {
        $id = (int)$_POST['id'];
        $conn->query("DELETE FROM expenses WHERE id=$id");
        $msg = $isAr ? 'تم حذف المصروف.' : 'Expense deleted.';
    }
    header('Location: expenses.php?msg=' . urlencode($msg));
    exit;
}
if (isset($_GET['msg'])) $msg = $_GET['msg'];

$dateFrom = $_GET['date_from'] ?? date('Y-m-01');
$dateTo   = $_GET['date_to']   ?? date('Y-m-d');

$rExp = $conn->query("SELECT e.*, u.full_name as user_name FROM expenses e LEFT JOIN users u ON u.id=e.user_id WHERE e.expense_date BETWEEN '$dateFrom' AND '$dateTo' ORDER BY e.expense_date DESC, e.id DESC");
$expenses = $rExp ? $rExp->fetch_all(MYSQLI_ASSOC) : [];

$rTotal = $conn->query("SELECT COALESCE(SUM(amount),0) as total FROM expenses WHERE expense_date BETWEEN '$dateFrom' AND '$dateTo'");
$totalExp = $rTotal ? (float)$rTotal->fetch_assoc()['total'] : 0;

$editId = (int)($_GET['edit'] ?? 0);
$editExp = null;
if ($editId) {
    $r = $conn->query("SELECT * FROM expenses WHERE id=$editId LIMIT 1");
    $editExp = $r ? $r->fetch_assoc() : null;
}

$pageTitle = $isAr ? 'المصروفات' : 'Expenses';
include 'includes/head.php';
?>
<div class="app-layout">
<?php include 'includes/sidebar.php'; ?>
<div class="main-content">
  <div class="topbar">
    <div class="topbar-title"><?= $isAr ? 'المصروفات' : 'Expenses' ?></div>
    <div class="topbar-right">
      <a href="lang.php?lang=<?= $isAr ? 'en' : 'ar' ?>" class="lang-btn"><?= $isAr ? 'EN' : 'ع' ?></a>
    </div>
  </div>
  <div class="page-content">
    <?php if ($msg): ?><div class="alert alert-success"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
    <div style="display:grid;grid-template-columns:1fr 380px;gap:20px;align-items:start;">
      <div>
        <!-- Filter -->
        <div class="card mb-16">
          <div class="card-body" style="padding:12px 16px;">
            <form method="GET" style="display:flex;gap:8px;align-items:flex-end;flex-wrap:wrap;">
              <div class="form-group mb-0">
                <label class="form-label"><?= $isAr?'من':'From' ?></label>
                <input type="date" name="date_from" class="form-control" value="<?= $dateFrom ?>">
              </div>
              <div class="form-group mb-0">
                <label class="form-label"><?= $isAr?'إلى':'To' ?></label>
                <input type="date" name="date_to" class="form-control" value="<?= $dateTo ?>">
              </div>
              <button type="submit" class="btn btn-primary btn-sm" style="align-self:flex-end;"><?= $isAr?'فلترة':'Filter' ?></button>
            </form>
          </div>
        </div>

        <div class="stat-card mb-16" style="background:linear-gradient(135deg,#fef3c7,#fff);">
          <div class="stat-label"><?= $isAr?'إجمالي المصروفات':'Total Expenses' ?></div>
          <div class="stat-value" style="color:#d97706;"><?= number_format($totalExp,3) ?> <small style="font-size:14px;">KD</small></div>
        </div>

        <div class="card">
          <div class="card-header"><span class="card-title"><?= $isAr?'قائمة المصروفات':'Expense List' ?></span></div>
          <div class="table-wrapper">
            <table>
              <thead><tr>
                <th><?= $isAr?'التاريخ':'Date' ?></th>
                <th><?= $isAr?'الفئة':'Category' ?></th>
                <th><?= $isAr?'الوصف':'Description' ?></th>
                <th><?= $isAr?'المبلغ':'Amount' ?></th>
                <th><?= $isAr?'مدفوع لـ':'Paid To' ?></th>
                <th></th>
              </tr></thead>
              <tbody>
              <?php if (empty($expenses)): ?>
                <tr><td colspan="6" class="text-center text-muted" style="padding:30px;"><?= $isAr?'لا توجد مصروفات':'No expenses' ?></td></tr>
              <?php else: ?>
                <?php foreach ($expenses as $e): ?>
                <tr>
                  <td><?= date('d/m/Y', strtotime($e['expense_date'])) ?></td>
                  <td><span class="badge badge-yellow"><?= htmlspecialchars($e['category'] ?: '—') ?></span></td>
                  <td style="color:#6b7280;"><?= htmlspecialchars($e['description'] ?: '—') ?></td>
                  <td style="font-weight:700;color:#d97706;"><?= number_format($e['amount'],3) ?> KD</td>
                  <td style="color:#6b7280;"><?= htmlspecialchars($e['paid_to'] ?: '—') ?></td>
                  <td>
                    <div style="display:flex;gap:4px;">
                      <a href="expenses.php?edit=<?= $e['id'] ?>" class="btn btn-sm btn-outline">✏️</a>
                      <form method="POST" style="display:inline;" onsubmit="return confirm('Delete?')">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?= $e['id'] ?>">
                        <button class="btn btn-sm btn-danger">🗑</button>
                      </form>
                    </div>
                  </td>
                </tr>
                <?php endforeach; ?>
              <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- Form -->
      <div class="card" style="position:sticky;top:76px;">
        <div class="card-header">
          <span class="card-title"><?= $editExp ? ($isAr?'تعديل مصروف':'Edit Expense') : ($isAr?'مصروف جديد':'New Expense') ?></span>
          <?php if ($editExp): ?><a href="expenses.php" class="btn btn-sm btn-outline"><?= $isAr?'إلغاء':'Cancel' ?></a><?php endif; ?>
        </div>
        <div class="card-body">
          <form method="POST">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id" value="<?= $editExp['id'] ?? 0 ?>">
            <div class="form-group">
              <label class="form-label"><?= $isAr?'الفئة':'Category' ?></label>
              <select name="category" class="form-control">
                <?php foreach (['Rent','Salaries','Utilities','Supplies','Marketing','Other'] as $c): ?>
                <option value="<?= $c ?>" <?= ($editExp['category'] ?? '') === $c ? 'selected' : '' ?>><?= $c ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group">
              <label class="form-label"><?= $isAr?'الوصف':'Description' ?></label>
              <textarea name="description" class="form-control" rows="2"><?= htmlspecialchars($editExp['description'] ?? '') ?></textarea>
            </div>
            <div class="form-row">
              <div class="form-group">
                <label class="form-label"><?= $isAr?'المبلغ (KD)':'Amount (KD)' ?></label>
                <input type="number" name="amount" class="form-control" step="0.001" min="0" required value="<?= $editExp['amount'] ?? '' ?>">
              </div>
              <div class="form-group">
                <label class="form-label"><?= $isAr?'التاريخ':'Date' ?></label>
                <input type="date" name="expense_date" class="form-control" value="<?= $editExp['expense_date'] ?? date('Y-m-d') ?>">
              </div>
            </div>
            <div class="form-group">
              <label class="form-label"><?= $isAr?'مدفوع لـ':'Paid To' ?></label>
              <input type="text" name="paid_to" class="form-control" value="<?= htmlspecialchars($editExp['paid_to'] ?? '') ?>">
            </div>
            <button type="submit" class="btn btn-primary btn-full"><?= $editExp ? ($isAr?'حفظ':'Save') : ($isAr?'إضافة':'Add Expense') ?></button>
          </form>
        </div>
      </div>
    </div>
  </div>
</div>
</div>
<script src="assets/js/main.js"></script>
</body></html>
