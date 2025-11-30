<?php
require_once 'config.php';
require_once 'db.php';
require_once 'User.php';

// 检查用户是否登录
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => '用户未登录']);
    exit;
}

$user_id = $_SESSION['user_id'];
$search_term = isset($_GET['q']) ? trim($_GET['q']) : '';

if (empty($search_term)) {
    echo json_encode(['success' => false, 'message' => '请输入搜索关键词']);
    exit;
}

// 创建User实例
$user = new User($conn);

// 搜索用户
$results = $user->searchUsers($search_term, $user_id);

// 获取用户与搜索结果的关系状态
foreach ($results as $key => $result) {
    // 检查是否已经是好友
    $stmt = $conn->prepare(
        "SELECT status FROM friends 
         WHERE (user_id = ? AND friend_id = ?) OR (user_id = ? AND friend_id = ?)"
    );
    $stmt->execute([$user_id, $result['id'], $result['id'], $user_id]);
    $friendship = $stmt->fetch();
    
    if ($friendship) {
        $results[$key]['friendship_status'] = $friendship['status'];
    } else {
        $results[$key]['friendship_status'] = 'none';
    }
}

echo json_encode([
    'success' => true,
    'users' => $results
]);