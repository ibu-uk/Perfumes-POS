<?php
require_once '../config.php';
requireAdmin();
header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
$mode  = $input['mode'] ?? '';   // 'single', 'all_on', 'all_off'
$id    = (int)($input['id'] ?? 0);

if ($mode === 'all_on') {
    $conn->query("UPDATE customers SET points_enabled = 1");
    echo json_encode(['success' => true]);
} elseif ($mode === 'all_off') {
    $conn->query("UPDATE customers SET points_enabled = 0");
    echo json_encode(['success' => true]);
} elseif ($mode === 'single' && $id) {
    $conn->query("UPDATE customers SET points_enabled = 1 - points_enabled WHERE id = $id");
    $r = $conn->query("SELECT points_enabled FROM customers WHERE id = $id LIMIT 1");
    $row = $r ? $r->fetch_assoc() : null;
    echo json_encode(['success' => true, 'enabled' => (int)($row['points_enabled'] ?? 0)]);
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid request']);
}
