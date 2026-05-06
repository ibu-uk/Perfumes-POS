<?php
require_once '../config.php';
requireAdmin();
$id = (int)($_POST['id'] ?? 0);
if ($id) {
    $conn->query("UPDATE sales SET status='void' WHERE id=$id");
}
header('Location: ../invoice_view.php?id=' . $id . '&msg=voided');
exit;
