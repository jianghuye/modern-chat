<?php
require_once 'db.php';

// 创建群聊相关的数据表
$create_tables_sql = "
-- 创建群聊表
CREATE TABLE IF NOT EXISTS `groups` (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    creator_id INT NOT NULL,
    owner_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (creator_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 创建群聊成员表
CREATE TABLE IF NOT EXISTS group_members (
    id INT AUTO_INCREMENT PRIMARY KEY,
    group_id INT NOT NULL,
    user_id INT NOT NULL,
    is_admin BOOLEAN DEFAULT FALSE,
    joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (group_id) REFERENCES `groups`(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_group_user (group_id, user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 创建群聊消息表
CREATE TABLE IF NOT EXISTS group_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    group_id INT NOT NULL,
    sender_id INT NOT NULL,
    content TEXT,
    file_path VARCHAR(255),
    file_name VARCHAR(255),
    file_size INT,
    file_type VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (group_id) REFERENCES `groups`(id) ON DELETE CASCADE,
    FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
";

try {
    // 执行SQL语句
    $conn->exec($create_tables_sql);
    echo "群聊相关数据表创建成功！\n";
    
    // 显示创建的表结构
    $stmt = $conn->query("SHOW TABLES LIKE 'group%' OR TABLES LIKE 'groups'");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "\n创建的表：\n";
    foreach ($tables as $table) {
        echo "- $table\n";
        
        $stmt = $conn->query("DESCRIBE $table");
        $columns = $stmt->fetchAll();
        
        echo "  表结构：\n";
        foreach ($columns as $column) {
            echo "  - {$column['Field']} ({$column['Type']}) - {$column['Null']} - {$column['Key']} - {$column['Default']} - {$column['Extra']}\n";
        }
        echo "\n";
    }
} catch(PDOException $e) {
    echo "创建表失败：" . $e->getMessage() . "\n";
}

// 关闭数据库连接
$db->disconnect();
?>