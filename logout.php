<?php
require_once 'config.php';
require_once 'db.php';
require_once 'User.php';

// 检查用户是否登录
if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    
    // 创建User实例
    $user = new User($conn);
    
    // 更新用户状态为离线
    $user->updateStatus($user_id, 'offline');
    
    // 销毁会话
    session_unset();
    session_destroy();
}

// 重定向到登录页面
header('Location: login.php');
exit;