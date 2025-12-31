<?php
// 启动会话
session_start();

require_once 'config.php';
require_once 'db.php';
require_once 'Friend.php';

// 检查用户是否登录
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => '用户未登录']);
    exit;
}

$user_id = $_SESSION['user_id'];
$request_id = isset($_GET['request_id']) ? intval($_GET['request_id']) : 0;

if (!$request_id) {
    echo json_encode(['success' => false, 'message' => '无效的请求ID']);
    exit;
}

// 创建Friend实例
$friend = new Friend($conn);

// 拒绝好友请求
$result = $friend->rejectFriendRequest($user_id, $request_id);

// 返回JSON响应
header('Content-Type: application/json');
echo json_encode($result);
exit;