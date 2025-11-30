<?php
require_once 'db.php';

// 获取所有表名
$stmt = $conn->query("SHOW TABLES");
$tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

echo "数据库表列表：\n";
foreach ($tables as $table) {
    echo "- $table\n";
    
    // 获取表结构
    $stmt = $conn->query("DESCRIBE $table");
    $columns = $stmt->fetchAll();
    
    echo "  表结构：\n";
    foreach ($columns as $column) {
        echo "  - {$column['Field']} ({$column['Type']}) - {$column['Null']} - {$column['Key']} - {$column['Default']} - {$column['Extra']}\n";
    }
    echo "\n";
}
?>