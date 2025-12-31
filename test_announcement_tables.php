<?php
// 启用错误报告
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('max_execution_time', 30); // 设置脚本最大执行时间为30秒

// 直接加载配置和数据库连接文件
require_once 'config.php';
require_once 'db.php';

echo "检查公告系统数据库表...\n";

// 检查 $conn 是否存在且是 PDO 对象
if (!isset($conn) || !($conn instanceof PDO)) {
    echo "❌ 数据库连接失败，$conn 不存在或不是 PDO 对象\n";
    exit(1);
}

// 检查 announcements 表
try {
    $sql = "CREATE TABLE IF NOT EXISTS announcements (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        content TEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        is_active BOOLEAN DEFAULT TRUE,
        admin_id INT NOT NULL,
        FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE CASCADE
    )";
    $conn->exec($sql);
    echo "✅ announcements 表已创建或已存在\n";
} catch (PDOException $e) {
    echo "❌ 创建 announcements 表失败: " . $e->getMessage() . "\n";
}

// 检查 user_announcement_read 表
try {
    $sql = "CREATE TABLE IF NOT EXISTS user_announcement_read (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        announcement_id INT NOT NULL,
        read_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (announcement_id) REFERENCES announcements(id) ON DELETE CASCADE,
        UNIQUE KEY unique_user_announcement (user_id, announcement_id)
    )";
    $conn->exec($sql);
    echo "✅ user_announcement_read 表已创建或已存在\n";
} catch (PDOException $e) {
    echo "❌ 创建 user_announcement_read 表失败: " . $e->getMessage() . "\n";
}

echo "\n检查完成！\n";
?>