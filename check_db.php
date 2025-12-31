<?php
require 'db.php';
// $conn 变量已经在 db.php 中创建

echo "Columns in groups table:\n";
$stmt = $conn->query('SHOW COLUMNS FROM groups');
$columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($columns as $col) {
    echo $col['Field'] . "\n";
}

echo "\nTotal users: ";
$stmt = $conn->query('SELECT COUNT(*) as total FROM users');
$total = $stmt->fetch()['total'];
echo $total;

echo "\n\nGroups:\n";
$stmt = $conn->query('SELECT id, name, all_user_group FROM groups');
$groups = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($groups as $group) {
    echo $group['id'] . ' - ' . $group['name'] . ' - all_user_group: ' . $group['all_user_group'] . "\n";
}
?>