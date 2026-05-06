<?php
require_once '../config.php';
requireLogin();
header('Content-Type: application/json');
$r = $conn->query("SELECT s.id, s.invoice_no, s.total, s.status, s.payment_method, s.created_at, u.full_name as cashier FROM sales s LEFT JOIN users u ON u.id=s.user_id WHERE s.status!='void' ORDER BY s.created_at DESC LIMIT 10");
$rows = $r ? $r->fetch_all(MYSQLI_ASSOC) : [];
echo json_encode($rows);
