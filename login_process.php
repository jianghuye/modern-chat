<?php
require_once 'config.php';
require_once 'db.php';
require_once 'User.php';

// 检查是否是POST请求
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: login.php');
    exit;
}

// 获取表单数据
$email = trim($_POST['email']);
$password = $_POST['password'];

// 验证表单数据
$errors = [];

if (empty($email)) {
    $errors[] = '请输入邮箱地址';
}

if (empty($password)) {
    $errors[] = '请输入密码';
}

// 如果有错误，重定向回登录页面
if (!empty($errors)) {
    $error_message = implode('<br>', $errors);
    header("Location: login.php?error=" . urlencode($error_message));
    exit;
}

// 创建User实例
$user = new User($conn);

// 尝试登录用户
$result = $user->login($email, $password);

if ($result['success']) {
    // 登录成功，将用户信息存储在会话中
    $_SESSION['user_id'] = $result['user']['id'];
    $_SESSION['username'] = $result['user']['username'];
    $_SESSION['email'] = $result['user']['email'];
    $_SESSION['avatar'] = $result['user']['avatar'];
    $_SESSION['last_activity'] = time();
    
    // 重定向到聊天页面
    header('Location: chat.php');
    exit;
} else {
    // 登录失败，重定向回登录页面
    header("Location: login.php?error=" . urlencode($result['message']));
    exit;
}