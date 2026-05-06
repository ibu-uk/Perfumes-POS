<?php
require_once 'config.php';
$r = $conn->query('SELECT id, name, name_ar, parent_id, is_active FROM categories ORDER BY name');
while($row = $r->fetch_assoc()) {
    echo $row['id'] . ' | ' . $row['name'] . ' | ' . $row['name_ar'] . ' | parent: ' . ($row['parent_id'] ?? 'NULL') . ' | active: ' . $row['is_active'] . "\n";
}
