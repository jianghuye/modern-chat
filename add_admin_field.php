<?php
// 启用错误报告以便调试
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// 设置错误日志
ini_set('error_log', 'error.log');

// 开始会话
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

try {
    require_once 'config.php';
    require_once 'db.php';

    // 检查数据库连接
    if (!$conn) {
        die('数据库连接失败');
    }

    // 修改用户表，添加管理员字段
    $sql = "ALTER TABLE users ADD COLUMN is_admin BOOLEAN DEFAULT FALSE AFTER status";
    if ($conn->exec($sql) !== false) {
        echo "管理员字段添加成功";
    } else {
        echo "管理员字段添加失败或已存在";
    }

    // 将第一个用户设置为管理员
    $sql = "UPDATE users SET is_admin = TRUE WHERE id = 1";
    if ($conn->exec($sql) !== false) {
        echo "<br>第一个用户已设置为管理员";
    } else {
        echo "<br>设置管理员失败";
    }
} catch (Exception $e) {
    // 捕获所有异常并返回错误信息
    $error_msg = "服务器内部错误: " . $e->getMessage();
    error_log($error_msg);
    echo $error_msg;
}
?>