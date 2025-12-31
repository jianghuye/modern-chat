<?php
// 启动会话
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 设置响应头为JSON
header('Content-Type: application/json');

// 检查用户是否登录
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => '用户未登录']);
    exit;
}

// 检查请求方法
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => '无效的请求方法']);
    exit;
}

// 包含数据库连接和User类
require_once 'db.php';
require_once 'User.php';

// 获取当前用户ID
$user_id = $_SESSION['user_id'];

// 创建Database实例并连接
$db = new Database();
$connection = $db->connect();

if (!$connection) {
    echo json_encode(['success' => false, 'message' => '数据库连接失败']);
    exit;
}

// 创建User实例
$user = new User($connection);

try {
    // 使用User类的agreeToTerms方法更新协议同意状态
    $result = $user->agreeToTerms($user_id);
    
    if ($result) {
        echo json_encode(['success' => true, 'message' => '协议同意状态更新成功']);
    } else {
        echo json_encode(['success' => false, 'message' => '更新失败，请稍后重试']);
    }
} catch (Exception $e) {
    // 错误处理
    error_log('更新协议同意状态失败: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => '服务器内部错误']);
}
?>