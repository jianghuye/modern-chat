<?php
require_once 'config.php';
require_once 'db.php';

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

// 删除好友请求
$stmt = $conn->prepare(
    "DELETE FROM friends WHERE id = ? AND friend_id = ? AND status = 'pending'"
);
$stmt->execute([$request_id, $user_id]);

if ($stmt->rowCount() > 0) {
    header('Location: chat.php?success=' . urlencode('好友请求已拒绝'));
} else {
    header('Location: chat.php?error=' . urlencode('好友请求不存在或已处理'));
}
exit;