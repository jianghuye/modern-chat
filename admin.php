<?php
// 启用错误报告以便调试
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// 设置错误日志
ini_set('error_log', 'error.log');

// 检查用户是否是管理员
require_once 'config.php';
require_once 'db.php';

// 检查用户是否登录
// if (!isset($_SESSION['user_id'])) {
//     header('Location: login.php');
//     exit;
// }

// 确保is_admin字段存在并将第一个用户设置为管理员
try {
    // 检查users表是否有is_admin字段
    $stmt = $conn->prepare("SHOW COLUMNS FROM users LIKE 'is_admin'");
    $stmt->execute();
    $column_exists = $stmt->fetch();
    
    if (!$column_exists) {
        // 添加is_admin字段
        $conn->exec("ALTER TABLE users ADD COLUMN is_admin BOOLEAN DEFAULT FALSE AFTER status");
        error_log("Added is_admin column to users table");
    }
    
    // 将第一个用户设置为管理员
    $conn->exec("UPDATE users SET is_admin = TRUE WHERE id = 1");
    error_log("Set first user as admin");
} catch (PDOException $e) {
    error_log("Admin setup error: " . $e->getMessage());
}

require_once 'User.php';
require_once 'Group.php';
require_once 'Message.php';

// 创建实例
$user = new User($conn);
$group = new Group($conn);
$message = new Message($conn);

// 获取当前用户信息
$current_user = $user->getUserById($_SESSION['user_id']);

// 检查用户是否是管理员，或者用户名是Admin且邮箱以admin@开头
if (!$current_user['is_admin'] && !($current_user['username'] === 'Admin' && strpos($current_user['email'], 'admin@') === 0)) {
    header('Location: chat.php');
    exit;
}

// 直接获取所有群聊，不依赖Group类的getAllGroups()方法
try {
    $stmt = $conn->prepare("SELECT g.*, 
                                        u1.username as creator_username, 
                                        u2.username as owner_username,
                                        (SELECT COUNT(*) FROM group_members WHERE group_id = g.id) as member_count
                                 FROM groups g
                                 JOIN users u1 ON g.creator_id = u1.id
                                 JOIN users u2 ON g.owner_id = u2.id
                                 ORDER BY g.created_at DESC");
    $stmt->execute();
    $all_groups = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Get All Groups Error: " . $e->getMessage());
    $all_groups = [];
}

// 直接获取所有用户，不依赖User类的getAllUsers()方法
try {
    $stmt = $conn->prepare("SELECT * FROM users ORDER BY created_at DESC");
    $stmt->execute();
    $all_users = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Get All Users Error: " . $e->getMessage());
    $all_users = [];
}

// 直接获取所有群聊消息，不依赖Group类的getAllGroupMessages()方法
try {
    $stmt = $conn->prepare("SELECT gm.*, 
                                        u.username as sender_username,
                                        g.name as group_name
                                 FROM group_messages gm
                                 JOIN users u ON gm.sender_id = u.id
                                 JOIN groups g ON gm.group_id = g.id
                                 ORDER BY gm.created_at DESC
                                 LIMIT 1000"); // 限制1000条消息
    $stmt->execute();
    $all_group_messages = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Get All Group Messages Error: " . $e->getMessage());
    $all_group_messages = [];
}

// 直接获取所有好友消息，不依赖Message类的getAllFriendMessages()方法
try {
    $stmt = $conn->prepare("SELECT m.*, 
                                        u1.username as sender_username, 
                                        u2.username as receiver_username
                                 FROM messages m
                                 JOIN users u1 ON m.sender_id = u1.id
                                 JOIN users u2 ON m.receiver_id = u2.id
                                 ORDER BY m.created_at DESC
                                 LIMIT 1000"); // 限制1000条消息
    $stmt->execute();
    $all_friend_messages = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Get All Friend Messages Error: " . $e->getMessage());
    $all_friend_messages = [];
}

// 解散群聊
if (isset($_POST['action']) && $_POST['action'] === 'delete_group' && isset($_POST['group_id'])) {
    $group_id = intval($_POST['group_id']);
    $result = $group->deleteGroup($group_id, $current_user['id']);
    if ($result) {
        header('Location: admin.php?success=群聊已成功解散');
        exit;
    } else {
        header('Location: admin.php?error=群聊解散失败');
        exit;
    }
}

// 处理用户管理操作
if (isset($_POST['action']) && isset($_POST['user_id'])) {
    $user_id = intval($_POST['user_id']);
    
    // 防止管理员删除自己
    if ($user_id === $current_user['id']) {
        header('Location: admin.php?error=不能操作自己的账户');
        exit;
    }
    
    // 注销用户（添加is_deleted字段或使用其他方式标记）
    if ($_POST['action'] === 'deactivate_user') {
        try {
            // 检查users表是否有is_deleted字段
            $stmt = $conn->prepare("SHOW COLUMNS FROM users LIKE 'is_deleted'");
            $stmt->execute();
            $column_exists = $stmt->fetch();
            
            if ($column_exists) {
                // 如果有is_deleted字段，使用该字段标记
                $stmt = $conn->prepare("UPDATE users SET is_deleted = TRUE WHERE id = ?");
                $stmt->execute([$user_id]);
            } else {
                // 否则，使用avatar字段存储特殊值来标记删除
                $stmt = $conn->prepare("UPDATE users SET avatar = 'deleted_user' WHERE id = ?");
                $stmt->execute([$user_id]);
            }
            header('Location: admin.php?success=用户已成功注销');
            exit;
        } catch (PDOException $e) {
            error_log("Deactivate user error: " . $e->getMessage());
            header('Location: admin.php?error=用户注销失败');
            exit;
        }
    }
    
    // 强制删除用户
    if ($_POST['action'] === 'delete_user') {
        try {
            $conn->beginTransaction();
            
            // 删除用户相关数据
            // 先检查表是否存在，存在则删除
            
            // 检查messages表
            $stmt = $conn->prepare("SHOW TABLES LIKE 'messages'");
            $stmt->execute();
            if ($stmt->fetch()) {
                $stmt = $conn->prepare("DELETE FROM messages WHERE sender_id = ? OR receiver_id = ?");
                $stmt->execute([$user_id, $user_id]);
            }
            
            // 检查group_messages表
            $stmt = $conn->prepare("SHOW TABLES LIKE 'group_messages'");
            $stmt->execute();
            if ($stmt->fetch()) {
                $stmt = $conn->prepare("DELETE FROM group_messages WHERE sender_id = ?");
                $stmt->execute([$user_id]);
            }
            
            // 检查group_members表
            $stmt = $conn->prepare("SHOW TABLES LIKE 'group_members'");
            $stmt->execute();
            if ($stmt->fetch()) {
                $stmt = $conn->prepare("DELETE FROM group_members WHERE user_id = ?");
                $stmt->execute([$user_id]);
            }
            
            // 检查friends表（好友请求和好友关系）
            $stmt = $conn->prepare("SHOW TABLES LIKE 'friends'");
            $stmt->execute();
            if ($stmt->fetch()) {
                $stmt = $conn->prepare("DELETE FROM friends WHERE user_id = ? OR friend_id = ?");
                $stmt->execute([$user_id, $user_id]);
            }
            
            // 检查sessions表
            $stmt = $conn->prepare("SHOW TABLES LIKE 'sessions'");
            $stmt->execute();
            if ($stmt->fetch()) {
                $stmt = $conn->prepare("DELETE FROM sessions WHERE user_id = ? OR friend_id = ?");
                $stmt->execute([$user_id, $user_id]);
            }
            
            // 最后删除用户
            $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            
            $conn->commit();
            header('Location: admin.php?success=用户已成功删除');
            exit;
        } catch (PDOException $e) {
            $conn->rollBack();
            error_log("Delete user error: " . $e->getMessage());
            header('Location: admin.php?error=用户删除失败');
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>管理页面 - Modern Chat</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f0f2f5;
            color: #333;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .header {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .header h1 {
            font-size: 24px;
            color: #667eea;
        }
        
        .header .user-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .header .avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
        }
        
        .header .username {
            font-weight: 600;
        }
        
        .header .logout-btn {
            padding: 8px 16px;
            background: #ff4757;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            transition: background-color 0.2s;
        }
        
        .header .logout-btn:hover {
            background: #ff3742;
        }
        
        .section {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
        }
        
        .section h2 {
            font-size: 20px;
            margin-bottom: 20px;
            color: #333;
            border-bottom: 2px solid #667eea;
            padding-bottom: 10px;
        }
        
        .section h3 {
            font-size: 16px;
            margin-bottom: 15px;
            color: #666;
        }
        
        .groups-list {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
        }
        
        .group-item {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            border: 1px solid #e0e0e0;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        
        .group-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }
        
        .group-item h4 {
            font-size: 16px;
            margin-bottom: 10px;
            color: #667eea;
        }
        
        .group-item p {
            font-size: 14px;
            margin-bottom: 8px;
            color: #666;
        }
        
        .group-item .members {
            margin-top: 10px;
            font-size: 13px;
            color: #888;
        }
        
        .delete-group-btn {
            margin-top: 10px;
            padding: 6px 12px;
            background: #ff4757;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
            transition: background-color 0.2s;
        }
        
        .delete-group-btn:hover {
            background: #ff3742;
        }
        
        .messages-container {
            max-height: 400px;
            overflow-y: auto;
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-top: 15px;
        }
        
        .message {
            background: white;
            padding: 10px;
            border-radius: 8px;
            margin-bottom: 10px;
            border: 1px solid #e0e0e0;
        }
        
        .message-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 5px;
        }
        
        .message-sender {
            font-weight: 600;
            color: #667eea;
        }
        
        .message-time {
            font-size: 12px;
            color: #888;
        }
        
        .message-content {
            font-size: 14px;
            color: #333;
        }
        
        .message-file {
            margin-top: 5px;
            font-size: 13px;
            color: #666;
        }
        
        .message-file a {
            color: #667eea;
            text-decoration: none;
        }
        
        .message-file a:hover {
            text-decoration: underline;
        }
        
        .success-message {
            background: #d4edda;
            color: #155724;
            padding: 10px;
            border-radius: 6px;
            margin-bottom: 20px;
        }
        
        .error-message {
            background: #f8d7da;
            color: #721c24;
            padding: 10px;
            border-radius: 6px;
            margin-bottom: 20px;
        }
        
        .tabs {
            display: flex;
            margin-bottom: 20px;
            border-bottom: 1px solid #e0e0e0;
        }
        
        .tab {
            padding: 10px 20px;
            background: transparent;
            border: none;
            border-bottom: 2px solid transparent;
            cursor: pointer;
            font-size: 16px;
            color: #666;
            transition: all 0.2s;
        }
        
        .tab.active {
            border-bottom-color: #667eea;
            color: #667eea;
            font-weight: 600;
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }
        
        .search-input {
            width: 100%;
            padding: 10px;
            border: 1px solid #e0e0e0;
            border-radius: 6px;
            font-size: 14px;
            margin-bottom: 20px;
        }
        
        .search-input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 2px rgba(102, 126, 234, 0.1);
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>管理页面</h1>
            <div class="user-info">
                <div class="avatar">
                    <?php echo substr($current_user['username'], 0, 2); ?>
                </div>
                <span class="username"><?php echo $current_user['username']; ?></span>
                <span>(管理员)</span>
                <a href="chat.php" class="logout-btn">返回聊天</a>
            </div>
        </div>

        <?php if (isset($_GET['success'])): ?>
            <div class="success-message"><?php echo $_GET['success']; ?></div>
        <?php endif; ?>
        <?php if (isset($_GET['error'])): ?>
            <div class="error-message"><?php echo $_GET['error']; ?></div>
        <?php endif; ?>

        <div class="section">
            <h2>管理功能</h2>
            <div class="tabs">
                <button class="tab active" onclick="openTab(event, 'groups')">群聊管理</button>
                <button class="tab" onclick="openTab(event, 'group_messages')">群聊消息</button>
                <button class="tab" onclick="openTab(event, 'friend_messages')">好友消息</button>
                <button class="tab" onclick="openTab(event, 'users')">用户管理</button>
            </div>

            <!-- 群聊管理 -->
            <div id="groups" class="tab-content active">
                <h3>所有群聊</h3>
                <div class="groups-list">
                    <?php foreach ($all_groups as $group_item): ?>
                        <div class="group-item">
                            <h4><?php echo $group_item['name']; ?></h4>
                            <p>创建者: <?php echo $group_item['creator_username']; ?></p>
                            <p>群主: <?php echo $group_item['owner_username']; ?></p>
                            <p class="members">成员数量: <?php echo $group_item['member_count']; ?></p>
                            <p>创建时间: <?php echo $group_item['created_at']; ?></p>
                            <form method="post" onsubmit="return confirm('确定要解散这个群聊吗？此操作不可恢复！');">
                                <input type="hidden" name="action" value="delete_group">
                                <input type="hidden" name="group_id" value="<?php echo $group_item['id']; ?>">
                                <button type="submit" class="delete-group-btn">解散群聊</button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- 群聊消息 -->
            <div id="group_messages" class="tab-content">
                <h3>所有群聊消息</h3>
                <div class="messages-container">
                    <?php foreach ($all_group_messages as $msg): ?>
                        <div class="message">
                            <div class="message-header">
                                <span class="message-sender">
                                    <?php echo $msg['sender_username']; ?> (群聊: <?php echo $msg['group_name']; ?>)
                                </span>
                                <span class="message-time"><?php echo $msg['created_at']; ?></span>
                            </div>
                            <div class="message-content">
                                <?php if ($msg['content']): ?>
                                    <?php echo $msg['content']; ?>
                                <?php endif; ?>
                                <?php if ($msg['file_path']): ?>
                                    <div class="message-file">
                                        <a href="<?php echo $msg['file_path']; ?>" target="_blank">
                                            📎 <?php echo $msg['file_name']; ?>
                                        </a>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- 好友消息 -->
            <div id="friend_messages" class="tab-content">
                <h3>所有好友消息</h3>
                <div class="messages-container">
                    <?php foreach ($all_friend_messages as $msg): ?>
                        <div class="message">
                            <div class="message-header">
                                <span class="message-sender">
                                    <?php echo $msg['sender_username']; ?> → <?php echo $msg['receiver_username']; ?>
                                </span>
                                <span class="message-time"><?php echo $msg['created_at']; ?></span>
                            </div>
                            <div class="message-content">
                                <?php if ($msg['content']): ?>
                                    <?php echo $msg['content']; ?>
                                <?php endif; ?>
                                <?php if ($msg['file_path']): ?>
                                    <div class="message-file">
                                        <a href="<?php echo $msg['file_path']; ?>" target="_blank">
                                            📎 <?php echo $msg['file_name']; ?>
                                        </a>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- 用户管理 -->
            <div id="users" class="tab-content">
                <h3>所有用户</h3>
                <div class="groups-list">
                    <?php foreach ($all_users as $user_item): ?>
                        <div class="group-item">
                            <h4><?php echo $user_item['username']; ?></h4>
                            <p>邮箱: <?php echo $user_item['email']; ?></p>
                            <p>状态: <?php echo $user_item['status']; ?></p>
                            <p>角色: <?php echo $user_item['is_admin'] ? '管理员' : '普通用户'; ?></p>
                            <p>注册时间: <?php echo $user_item['created_at']; ?></p>
                            <p>最后活跃: <?php echo $user_item['last_active']; ?></p>
                            <div style="margin-top: 10px; display: flex; gap: 8px;">
                                <?php if ($user_item['id'] !== $current_user['id']): ?>
                                    <form method="post" style="margin: 0;" onsubmit="return confirm('确定要注销这个用户吗？用户将不允许登录。');">
                                        <input type="hidden" name="action" value="deactivate_user">
                                        <input type="hidden" name="user_id" value="<?php echo $user_item['id']; ?>">
                                        <button type="submit" style="padding: 6px 12px; background: #ffa726; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 12px;">注销用户</button>
                                    </form>
                                    <form method="post" style="margin: 0;" onsubmit="return confirm('确定要强制删除这个用户吗？此操作不可恢复！');">
                                        <input type="hidden" name="action" value="delete_user">
                                        <input type="hidden" name="user_id" value="<?php echo $user_item['id']; ?>">
                                        <button type="submit" style="padding: 6px 12px; background: #ef5350; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 12px;">强制删除</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        function openTab(evt, tabName) {
            // 关闭所有标签页
            var i, tabcontent, tablinks;
            tabcontent = document.getElementsByClassName("tab-content");
            for (i = 0; i < tabcontent.length; i++) {
                tabcontent[i].style.display = "none";
            }
            tablinks = document.getElementsByClassName("tab");
            for (i = 0; i < tablinks.length; i++) {
                tablinks[i].className = tablinks[i].className.replace(" active", "");
            }
            // 打开当前标签页
            document.getElementById(tabName).style.display = "block";
            evt.currentTarget.className += " active";
        }
    </script>
</body>
</html>