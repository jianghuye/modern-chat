<?php
require_once 'config.php';
require_once 'db.php';
require_once 'Friend.php';

// 检查用户是否登录
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$request_id = isset($_GET['request_id']) ? intval($_GET['request_id']) : 0;

if (!$request_id) {
    header('Location: chat.php?error=' . urlencode('无效的请求ID'));
    exit;
}

// 创建Friend实例
$friend = new Friend($conn);

// 接受好友请求
$result = $friend->acceptFriendRequest($user_id, $request_id);

if ($result['success']) {
    header('Location: chat.php?success=' . urlencode($result['message']));
} else {
    header('Location: chat.php?error=' . urlencode($result['message']));
}
exit;