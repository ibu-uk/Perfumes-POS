<?php
require_once '../config.php';
requireLogin();
header('Content-Type: application/json');
$q = $conn->real_escape_string(trim($_GET['q'] ?? ''));
if (strlen($q) < 2) { echo json_encode([]); exit; }
// Use prefix matching (no leading %) so indexes work efficiently
$r = $conn->query("SELECT id, name, phone, points, points_enabled FROM customers WHERE name LIKE '$q%' OR phone LIKE '$q%' LIMIT 10");
$results = $r ? $r->fetch_all(MYSQLI_ASSOC) : [];
echo json_encode($results);
