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

// 检查用户是否登录
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => '用户未登录']);
    exit;
}

// 引入必要的文件
require_once 'db.php';
require_once 'Friend.php';
require_once 'Group.php';

// 获取当前用户ID
$user_id = $_SESSION['user_id'];

// 创建实例
$friend = new Friend($conn);
$group = new Group($conn);

// 获取待处理的好友请求
$friend_requests = $friend->getPendingRequests($user_id);

// 为好友请求添加类型标记
foreach ($friend_requests as &$request) {
    $request['type'] = 'friend';
}

// 获取群聊邀请
$group_invitations = $group->getGroupInvitations($user_id);

// 为群聊邀请添加类型标记
foreach ($group_invitations as &$invitation) {
    $invitation['type'] = 'group';
}

// 合并所有请求
$all_requests = array_merge($friend_requests, $group_invitations);

// 按创建时间排序
usort($all_requests, function($a, $b) {
    $timeA = strtotime($a['created_at']);
    $timeB = strtotime($b['created_at']);
    return $timeB - $timeA; // 降序排序
});

// 确保返回的数据格式正确
$response = [
    'success' => true,
    'requests' => $all_requests
];

// 返回JSON响应
header('Content-Type: application/json');
echo json_encode($response);
