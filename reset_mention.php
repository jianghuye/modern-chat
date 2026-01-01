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
    
    // 检查用户是否登录
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'message' => '用户未登录']);
        exit;
    }
    
    // 检查是否是GET请求
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        echo json_encode(['success' => false, 'message' => '无效的请求方法']);
        exit;
    }
    
    $user_id = $_SESSION['user_id'];
    $chat_type = isset($_GET['chat_type']) ? $_GET['chat_type'] : '';
    $chat_id = isset($_GET['chat_id']) ? intval($_GET['chat_id']) : 0;
    
    // 验证数据
    if (empty($chat_type) || $chat_id <= 0) {
        echo json_encode(['success' => false, 'message' => '无效的请求参数']);
        exit;
    }
    
    // 检查数据库连接
    if (!$conn) {
        echo json_encode(['success' => false, 'message' => '数据库连接失败']);
        exit;
    }
    
    // 重置@提及标记
    $stmt = $conn->prepare("UPDATE chat_settings SET has_mention = FALSE WHERE user_id = ? AND chat_type = ? AND chat_id = ?");
    $stmt->execute([$user_id, $chat_type, $chat_id]);
    
    echo json_encode(['success' => true, 'message' => '重置@提及标记成功']);
} catch (Exception $e) {
    // 捕获所有异常并返回错误信息
    $error_msg = "服务器内部错误: " . $e->getMessage();
    error_log($error_msg);
    echo json_encode(['success' => false, 'message' => $error_msg]);
}