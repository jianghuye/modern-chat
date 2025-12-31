<?php
// 禁用错误显示，只记录到日志
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
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
    require_once 'Group.php';

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
    $group_id = isset($_GET['group_id']) ? intval($_GET['group_id']) : 0;

    // 验证数据
    if (!$group_id) {
        echo json_encode(['success' => false, 'message' => '请选择群聊']);
        exit;
    }

    // 检查数据库连接
    if (!$conn) {
        echo json_encode(['success' => false, 'message' => '数据库连接失败']);
        exit;
    }

    // 检查群聊是否被封禁
    $stmt = $conn->prepare("SELECT reason, ban_end FROM group_bans WHERE group_id = ? AND status = 'active'");
    $stmt->execute([$group_id]);
    $ban_info = $stmt->fetch();
    
    if ($ban_info) {
        // 检查封禁是否已过期
        if ($ban_info['ban_end'] && strtotime($ban_info['ban_end']) < time()) {
            // 更新封禁状态为过期
            $stmt = $conn->prepare("UPDATE group_bans SET status = 'expired' WHERE group_id = ? AND status = 'active'");
            $stmt->execute([$group_id]);
            
            // 插入过期日志
            $stmt = $conn->prepare("INSERT INTO group_ban_logs (ban_id, action, action_by) VALUES ((SELECT id FROM group_bans WHERE group_id = ? ORDER BY id DESC LIMIT 1), 'expire', NULL)");
            $stmt->execute([$group_id]);
        } else {
            // 群聊被封禁，返回错误信息
            echo json_encode(['success' => false, 'message' => '群聊被封禁，您暂时无法查看群聊成员和使用群聊功能']);
            exit;
        }
    }

    // 创建Group实例
    $group = new Group($conn);

    // 验证用户是群成员或群聊是全员群聊
    $is_member = false;
    
    // 检查是否是全员群聊
    $stmt = $conn->prepare("SELECT all_user_group FROM groups WHERE id = ?");
    $stmt->execute([$group_id]);
    $group_info = $stmt->fetch();
    
    if ($group_info && $group_info['all_user_group'] == 1) {
        // 全员群聊，所有用户都可以访问
        $is_member = true;
    } else {
        // 普通群聊，检查用户是否是群成员
        $member_role = $group->getMemberRole($group_id, $user_id);
        $is_member = $member_role !== false;
    }
    
    if (!$is_member) {
        echo json_encode(['success' => false, 'message' => '您不是该群聊的成员']);
        exit;
    }

    // 获取群聊成员列表
    $members = $group->getGroupMembers($group_id);
    
    // 获取群聊信息
    $group_info = $group->getGroupInfo($group_id);
    
    // 处理成员数据，添加角色信息和好友状态
    $processed_members = [];
    foreach ($members as $member) {
        // 检查当前用户与该成员的好友关系
        $friendship_status = 'none';
        if ($member['id'] != $user_id) {
            $stmt = $conn->prepare("SELECT status FROM friends WHERE (user_id = ? AND friend_id = ?) OR (user_id = ? AND friend_id = ?)");
            $stmt->execute([$user_id, $member['id'], $member['id'], $user_id]);
            $friendship = $stmt->fetch();
            if ($friendship) {
                $friendship_status = $friendship['status'];
            }
        }
        
        $processed_members[] = [
            'id' => $member['id'],
            'username' => $member['username'],
            'email' => $member['email'],
            'avatar' => $member['avatar'],
            'is_admin' => $member['is_admin'],
            'is_owner' => $member['id'] == $group_info['owner_id'],
            'friendship_status' => $friendship_status
        ];
    }

    // 群聊上限
    $max_members = 2000;

    // 获取当前用户的角色信息
    $member_role = $group->getMemberRole($group_id, $user_id);
    $current_user_role = [
        'is_owner' => $user_id == $group_info['owner_id'],
        'is_admin' => $member_role['is_admin']
    ];

    echo json_encode([
        'success' => true,
        'members' => $processed_members,
        'max_members' => $max_members,
        'is_owner' => $current_user_role['is_owner'],
        'is_admin' => $current_user_role['is_admin'],
        'current_user_id' => $user_id,
        'group_owner_id' => $group_info['owner_id'],
        'all_user_group' => $group_info['all_user_group']
    ]);
} catch (Exception $e) {
    // 捕获所有异常并返回错误信息
    $error_msg = "服务器内部错误: " . $e->getMessage();
    error_log($error_msg);
    echo json_encode(['success' => false, 'message' => $error_msg]);
}
?>