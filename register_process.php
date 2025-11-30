<?php
require_once 'config.php';
require_once 'db.php';
require_once 'User.php';

// 检查是否是POST请求
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: register.php');
    exit;
}

// 获取表单数据
$username = trim($_POST['username']);
$email = trim($_POST['email']);
$password = $_POST['password'];
$confirm_password = $_POST['confirm_password'];

// 验证表单数据
$errors = [];

if (strlen($username) < 3 || strlen($username) > 50) {
    $errors[] = '用户名长度必须在3-50个字符之间';
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = '请输入有效的邮箱地址';
}

if (strlen($password) < 6) {
    $errors[] = '密码长度必须至少为6个字符';
}

if ($password !== $confirm_password) {
    $errors[] = '两次输入的密码不一致';
}

// 如果有错误，重定向回注册页面
if (!empty($errors)) {
    $error_message = implode('<br>', $errors);
    header("Location: register.php?error=" . urlencode($error_message));
    exit;
}

// 创建User实例
$user = new User($conn);

// 尝试注册用户
$result = $user->register($username, $email, $password);

if ($result['success']) {
    // 注册成功，重定向到登录页面
    header("Location: login.php?success=" . urlencode('注册成功，请登录'));
    exit;
} else {
    // 注册失败，重定向回注册页面
    header("Location: register.php?error=" . urlencode($result['message']));
    exit;
}